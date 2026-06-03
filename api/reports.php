<?php
// ============================================================
// api/reports.php — Lab Orders & Reports API
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireAuth();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($_GET['id'] ?? 0);

switch ($method) {

    case 'GET':
        if ($id) {
            $order = Database::fetchOne("
                SELECT lo.*, lt.name AS test_name, lt.category, lt.price, lt.turnaround,
                       u.full_name AS patient_name, p.patient_code,
                       u_d.full_name AS doctor_name
                FROM lab_orders lo
                JOIN lab_tests lt ON lt.id = lo.test_id
                JOIN patients  p  ON p.id  = lo.patient_id
                JOIN users     u  ON u.id  = p.user_id
                LEFT JOIN users u_d ON u_d.id = lo.technician_id
                WHERE lo.id = ?
            ", [$id]);
            if (!$order) jsonResponse(['success'=>false,'message'=>'Order not found.'], 404);
            jsonResponse(['success'=>true,'data'=>$order]);
        }

        // List with filters
        $where  = ['1=1']; $params = [];
        if (!empty($_GET['patient_id'])) { $where[] = 'lo.patient_id=?'; $params[] = (int)$_GET['patient_id']; }
        if (!empty($_GET['status']))     { $where[] = 'lo.status=?';     $params[] = clean($_GET['status']); }
        if (!empty($_GET['from']))       { $where[] = 'DATE(lo.created_at)>=?'; $params[] = clean($_GET['from']); }
        if (!empty($_GET['to']))         { $where[] = 'DATE(lo.created_at)<=?'; $params[] = clean($_GET['to']); }

        if (Auth::role() === 'patient') {
            $pat = Database::fetchOne("SELECT id FROM patients WHERE user_id=?", [Auth::id()]);
            if ($pat) { $where[] = 'lo.patient_id=?'; $params[] = $pat['id']; }
        }

        $ws = implode(' AND ', $where);
        $orders = Database::fetchAll("
            SELECT lo.id, lo.order_no, lo.status, lo.payment_status, lo.created_at,
                   lo.sample_date, lo.report_date, lo.report_file, lo.result,
                   lt.name AS test_name, lt.category, lt.price,
                   u.full_name AS patient_name, p.patient_code
            FROM lab_orders lo
            JOIN lab_tests lt ON lt.id = lo.test_id
            JOIN patients  p  ON p.id  = lo.patient_id
            JOIN users     u  ON u.id  = p.user_id
            WHERE {$ws}
            ORDER BY lo.created_at DESC LIMIT 100
        ", $params);

        jsonResponse(['success'=>true,'data'=>$orders,'count'=>count($orders)]);

    case 'POST':
        // Create new lab order OR upload report (multipart)
        $data = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?? []);

        // Upload report to existing order
        if ($id) {
            Auth::requireRole(['lab_technician','admin']);
            $order = Database::fetchOne("SELECT * FROM lab_orders WHERE id=?", [$id]);
            if (!$order) jsonResponse(['success'=>false,'message'=>'Order not found.'], 404);

            $filePath = null;
            if (!empty($_FILES['report_file']['name'])) {
                $filePath = handleUpload($_FILES['report_file'], 'lab_reports');
                if (!$filePath) jsonResponse(['success'=>false,'message'=>'File upload failed. Check size/type.'], 422);
            }

            Database::query("UPDATE lab_orders SET
                status='completed', result=?, remarks=?, report_file=?,
                report_date=NOW(), technician_id=?
                WHERE id=?",
                [
                    clean($data['result']  ?? ''),
                    clean($data['remarks'] ?? ''),
                    $filePath ?? $order['report_file'],
                    Auth::id(),
                    $id,
                ]
            );

            // Notify patient
            $patUser = Database::fetchOne(
                "SELECT u.id FROM users u JOIN patients p ON p.user_id=u.id WHERE p.id=?",
                [$order['patient_id']]
            );
            if ($patUser) {
                Database::query(
                    "INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)",
                    [$patUser['id'], 'Lab Report Ready', "Your lab report for order {$order['order_no']} is ready. Please collect or download.", 'success']
                );
            }

            auditLog('UPLOAD_LAB_REPORT', 'lab_orders', $id);
            jsonResponse(['success'=>true,'message'=>'Report uploaded successfully!']);
        }

        // Create new order
        Auth::requireRole(['admin','receptionist','doctor','lab_technician']);
        $errors = [];
        if (empty($data['patient_id'])) $errors['patient_id'] = 'Patient required.';
        if (empty($data['test_id']))    $errors['test_id']    = 'Test required.';
        if ($errors) jsonResponse(['success'=>false,'message'=>'Validation failed.','errors'=>$errors], 422);

        Database::beginTransaction();
        try {
            $year   = date('Y');
            $cntRow = Database::fetchOne("SELECT COUNT(*)+1 AS c FROM lab_orders WHERE YEAR(created_at)=?", [$year]);
            $orderNo = 'LAB-'.$year.'-'.str_pad($cntRow['c'], 4, '0', STR_PAD_LEFT);

            $orderId = Database::insert("
                INSERT INTO lab_orders (order_no, patient_id, doctor_id, test_id, status, payment_status)
                VALUES (?,?,?,?,?,?)
            ", [
                $orderNo,
                (int)$data['patient_id'],
                !empty($data['doctor_id']) ? (int)$data['doctor_id'] : null,
                (int)$data['test_id'],
                'ordered',
                'pending',
            ]);

            Database::commit();
            auditLog('CREATE_LAB_ORDER', 'lab_orders', $orderId);
            jsonResponse(['success'=>true,'message'=>'Lab order created!','order_id'=>$orderId,'order_no'=>$orderNo]);
        } catch (Throwable $e) {
            Database::rollback();
            jsonResponse(['success'=>false,'message'=>'Failed: '.$e->getMessage()], 500);
        }

    case 'PUT':
        if (!$id) jsonResponse(['success'=>false,'message'=>'Order ID required.'], 400);
        Auth::requireRole(['lab_technician','admin']);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $order = Database::fetchOne("SELECT * FROM lab_orders WHERE id=?", [$id]);
        if (!$order) jsonResponse(['success'=>false,'message'=>'Not found.'], 404);

        $allowed = ['status','result','remarks','sample_date'];
        $sets = []; $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $body)) {
                $sets[]   = "{$f}=?";
                $params[] = clean((string)$body[$f]);
            }
        }
        if (in_array('sample_collected', array_values($body))) {
            $sets[]   = 'sample_date=NOW()';
        }
        if (!$sets) jsonResponse(['success'=>false,'message'=>'Nothing to update.'], 400);
        $params[] = $id;
        Database::query("UPDATE lab_orders SET ".implode(',',$sets)." WHERE id=?", $params);
        auditLog('UPDATE_LAB_ORDER', 'lab_orders', $id, $order, $body);
        jsonResponse(['success'=>true,'message'=>'Order updated.']);

    default:
        jsonResponse(['success'=>false,'message'=>'Method not allowed.'], 405);
}
