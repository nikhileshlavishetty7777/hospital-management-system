<?php
// ============================================================
// api/billing.php — Invoices & Payments API
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireAuth();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$id     = (int)($_GET['id'] ?? 0);

switch ($method) {

    case 'GET':
        if ($id) {
            $inv = Database::fetchOne("
                SELECT i.*, u.full_name AS patient_name, p.patient_code, u.phone
                FROM invoices i
                JOIN patients p ON p.id = i.patient_id
                JOIN users    u ON u.id = p.user_id
                WHERE i.id = ?
            ", [$id]);
            if (!$inv) jsonResponse(['success'=>false,'message'=>'Invoice not found.'], 404);
            $items = Database::fetchAll("SELECT * FROM invoice_items WHERE invoice_id=?", [$id]);
            jsonResponse(['success'=>true,'data'=>$inv,'items'=>$items]);
        }

        // Filter params
        $where  = ['1=1']; $params = [];
        if (!empty($_GET['patient_id'])) { $where[] = 'i.patient_id=?'; $params[] = (int)$_GET['patient_id']; }
        if (!empty($_GET['status']))     { $where[] = 'i.payment_status=?'; $params[] = clean($_GET['status']); }
        if (!empty($_GET['from']))       { $where[] = 'DATE(i.created_at)>=?'; $params[] = clean($_GET['from']); }
        if (!empty($_GET['to']))         { $where[] = 'DATE(i.created_at)<=?'; $params[] = clean($_GET['to']); }

        $ws = implode(' AND ', $where);
        $invoices = Database::fetchAll("
            SELECT i.id, i.invoice_no, i.total, i.paid, i.balance, i.payment_status,
                   i.payment_method, i.created_at,
                   u.full_name AS patient_name, p.patient_code
            FROM invoices i
            JOIN patients p ON p.id = i.patient_id
            JOIN users    u ON u.id = p.user_id
            WHERE {$ws}
            ORDER BY i.id DESC LIMIT 100
        ", $params);

        $summary = Database::fetchOne("
            SELECT COALESCE(SUM(total),0) AS total_invoiced,
                   COALESCE(SUM(paid),0)  AS total_collected,
                   COALESCE(SUM(balance),0) AS total_pending,
                   COUNT(*) AS invoice_count
            FROM invoices i WHERE {$ws}
        ", $params);

        jsonResponse(['success'=>true,'data'=>$invoices,'summary'=>$summary]);

    case 'POST':
        Auth::requireRole(['admin','receptionist']);
        $errors = [];
        if (empty($body['patient_id'])) $errors['patient_id'] = 'Patient required.';
        if (empty($body['items']))      $errors['items']      = 'At least one item required.';
        if ($errors) jsonResponse(['success'=>false,'message'=>'Validation failed.','errors'=>$errors], 422);

        $subtotal = 0;
        foreach ($body['items'] as $item) {
            $subtotal += (float)($item['unit_price'] ?? 0) * (int)($item['qty'] ?? 1);
        }
        $discount = (float)($body['discount'] ?? 0);
        $taxable  = $subtotal - $discount;
        $tax      = round($taxable * GST_RATE, 2);
        $total    = $taxable + $tax;
        $paid     = (float)($body['paid'] ?? 0);
        $balance  = $total - $paid;
        $status   = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'pending');

        Database::beginTransaction();
        try {
            $year    = date('Y');
            $cntRow  = Database::fetchOne("SELECT COUNT(*)+1 AS c FROM invoices WHERE YEAR(created_at)=?", [$year]);
            $invNo   = 'INV-'.$year.'-'.str_pad($cntRow['c'], 4, '0', STR_PAD_LEFT);

            $invId = Database::insert("
                INSERT INTO invoices
                  (invoice_no, patient_id, appointment_id, admission_id,
                   subtotal, discount, tax, total, paid, balance,
                   payment_method, payment_status, gst_number, notes, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ", [
                $invNo,
                (int)$body['patient_id'],
                !empty($body['appointment_id']) ? (int)$body['appointment_id'] : null,
                !empty($body['admission_id'])   ? (int)$body['admission_id']   : null,
                $subtotal, $discount, $tax, $total, $paid, $balance,
                clean($body['payment_method'] ?? 'cash'),
                $status,
                clean($body['gst_number'] ?? ''),
                clean($body['notes']      ?? ''),
                Auth::id(),
            ]);

            foreach ($body['items'] as $item) {
                $qty      = (int)($item['qty']        ?? 1);
                $price    = (float)($item['unit_price'] ?? 0);
                $subtotalItem = $qty * $price;
                Database::query("INSERT INTO invoice_items (invoice_id, description, category, qty, unit_price, subtotal) VALUES (?,?,?,?,?,?)",
                    [$invId, clean($item['description'] ?? 'Service'), clean($item['category'] ?? 'consultation'), $qty, $price, $subtotalItem]);
            }

            // Update appointment payment status
            if (!empty($body['appointment_id'])) {
                Database::query("UPDATE appointments SET payment_status=? WHERE id=?",
                    [$status === 'paid' ? 'paid' : 'pending', (int)$body['appointment_id']]);
            }

            Database::commit();
            auditLog('CREATE_INVOICE', 'invoices', $invId);
            jsonResponse(['success'=>true,'message'=>'Invoice created!','invoice_id'=>$invId,'invoice_no'=>$invNo,'total'=>$total]);

        } catch (Throwable $e) {
            Database::rollback();
            jsonResponse(['success'=>false,'message'=>'Failed: '.$e->getMessage()], 500);
        }

    case 'PUT':
        if (!$id) jsonResponse(['success'=>false,'message'=>'Invoice ID required.'], 400);
        Auth::requireRole(['admin','receptionist']);

        $inv = Database::fetchOne("SELECT * FROM invoices WHERE id=?", [$id]);
        if (!$inv) jsonResponse(['success'=>false,'message'=>'Not found.'], 404);

        $paid    = (float)($body['paid'] ?? $inv['paid']);
        $balance = $inv['total'] - $paid;
        $status  = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'pending');

        Database::query("UPDATE invoices SET paid=?, balance=?, payment_status=?, payment_method=? WHERE id=?",
            [$paid, $balance, $status, clean($body['payment_method'] ?? $inv['payment_method']), $id]);

        auditLog('UPDATE_INVOICE', 'invoices', $id, $inv, $body);
        jsonResponse(['success'=>true,'message'=>'Payment recorded.','new_status'=>$status,'balance'=>$balance]);

    default:
        jsonResponse(['success'=>false,'message'=>'Method not allowed.'], 405);
}
