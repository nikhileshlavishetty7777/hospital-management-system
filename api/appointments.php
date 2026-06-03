<?php
// ============================================================
// api/appointments.php — RESTful appointments API
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireAuth();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$body   = [];
if (in_array($method, ['PUT','PATCH'])) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
}
$id = (int)($_GET['id'] ?? 0);

switch ($method) {

    // ── GET ───────────────────────────────────────────────────
    case 'GET':
        if ($id) {
            $appt = Database::fetchOne("
                SELECT a.*,
                       u_p.full_name AS patient_name, p.patient_code,
                       u_d.full_name AS doctor_name,  d.specialization,
                       dep.name      AS dept_name,    dep.color AS dept_color
                FROM appointments a
                JOIN patients    p   ON p.id   = a.patient_id
                JOIN users       u_p ON u_p.id = p.user_id
                JOIN doctors     d   ON d.id   = a.doctor_id
                JOIN users       u_d ON u_d.id = d.user_id
                JOIN departments dep ON dep.id = a.department_id
                WHERE a.id = ?
            ", [$id]);
            if (!$appt) jsonResponse(['success'=>false,'message'=>'Appointment not found.'], 404);
            jsonResponse(['success'=>true,'data'=>$appt]);
        }

        // List with filters
        $where  = ['1=1'];
        $params = [];

        if (!empty($_GET['date'])) {
            $where[]  = 'a.appointment_date = ?';
            $params[] = clean($_GET['date']);
        }
        if (!empty($_GET['doctor_id'])) {
            $where[]  = 'a.doctor_id = ?';
            $params[] = (int)$_GET['doctor_id'];
        }
        if (!empty($_GET['patient_id'])) {
            $where[]  = 'a.patient_id = ?';
            $params[] = (int)$_GET['patient_id'];
        }
        if (!empty($_GET['status'])) {
            $where[]  = 'a.status = ?';
            $params[] = clean($_GET['status']);
        }
        if (!empty($_GET['department_id'])) {
            $where[]  = 'a.department_id = ?';
            $params[] = (int)$_GET['department_id'];
        }
        // Role-based filter: doctors only see their own
        if (Auth::role() === 'doctor') {
            $doc = Database::fetchOne("SELECT id FROM doctors WHERE user_id=?", [Auth::id()]);
            if ($doc) { $where[] = 'a.doctor_id = ?'; $params[] = $doc['id']; }
        }
        if (Auth::role() === 'patient') {
            $pat = Database::fetchOne("SELECT id FROM patients WHERE user_id=?", [Auth::id()]);
            if ($pat) { $where[] = 'a.patient_id = ?'; $params[] = $pat['id']; }
        }

        $whereStr = implode(' AND ', $where);
        $limit    = (int)($_GET['limit']  ?? 50);
        $offset   = (int)($_GET['offset'] ?? 0);

        $appts = Database::fetchAll("
            SELECT a.id, a.appointment_no, a.appointment_date, a.appointment_time,
                   a.token_number, a.type, a.status, a.symptoms, a.payment_status,
                   u_p.full_name AS patient_name, p.patient_code,
                   u_d.full_name AS doctor_name,  d.specialization,
                   dep.name      AS dept_name
            FROM appointments a
            JOIN patients    p   ON p.id   = a.patient_id
            JOIN users       u_p ON u_p.id = p.user_id
            JOIN doctors     d   ON d.id   = a.doctor_id
            JOIN users       u_d ON u_d.id = d.user_id
            JOIN departments dep ON dep.id = a.department_id
            WHERE {$whereStr}
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT ? OFFSET ?
        ", [...$params, $limit, $offset]);

        $total = Database::fetchOne("SELECT COUNT(*) AS c FROM appointments a WHERE {$whereStr}", $params)['c'];
        jsonResponse(['success'=>true,'data'=>$appts,'total'=>$total]);

    // ── POST: book appointment ────────────────────────────────
    case 'POST':
        $data = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?? []);

        $errors = [];
        if (empty($data['patient_id']))        $errors['patient_id']        = 'Patient is required.';
        if (empty($data['doctor_id']))         $errors['doctor_id']         = 'Doctor is required.';
        if (empty($data['department_id']))     $errors['department_id']     = 'Department is required.';
        if (empty($data['appointment_date']))  $errors['appointment_date']  = 'Date is required.';
        if (empty($data['appointment_time']))  $errors['appointment_time']  = 'Time is required.';

        if ($errors) jsonResponse(['success'=>false,'message'=>'Validation failed.','errors'=>$errors], 422);

        // Check slot availability
        $clash = Database::fetchOne("
            SELECT id FROM appointments
            WHERE doctor_id=? AND appointment_date=? AND appointment_time=?
              AND status NOT IN ('cancelled','no_show')
        ", [(int)$data['doctor_id'], $data['appointment_date'], $data['appointment_time']]);
        if ($clash) jsonResponse(['success'=>false,'message'=>'This time slot is already booked. Please choose another.'], 409);

        // Get next token for the day
        $tokenRow = Database::fetchOne("
            SELECT COALESCE(MAX(token_number),0)+1 AS next_token
            FROM appointments
            WHERE doctor_id=? AND appointment_date=?
        ", [(int)$data['doctor_id'], $data['appointment_date']]);
        $token = $tokenRow['next_token'];

        Database::beginTransaction();
        try {
            // Generate appointment number
            $year    = date('Y');
            $countRow= Database::fetchOne("SELECT COUNT(*)+1 AS c FROM appointments WHERE YEAR(created_at)=?", [$year]);
            $apptNo  = 'APT-' . $year . str_pad($countRow['c'], 4, '0', STR_PAD_LEFT);

            $apptId = Database::insert("
                INSERT INTO appointments
                  (appointment_no, patient_id, doctor_id, department_id,
                   appointment_date, appointment_time, token_number, type,
                   status, symptoms, notes, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
            ", [
                $apptNo,
                (int)$data['patient_id'],
                (int)$data['doctor_id'],
                (int)$data['department_id'],
                $data['appointment_date'],
                $data['appointment_time'],
                $token,
                clean($data['type']  ?? 'opd'),
                'booked',
                clean($data['symptoms'] ?? ''),
                clean($data['notes']    ?? ''),
                Auth::id(),
            ]);

            // Notification to patient
            $patUser = Database::fetchOne(
                "SELECT u.id FROM users u JOIN patients p ON p.user_id=u.id WHERE p.id=?",
                [(int)$data['patient_id']]
            );
            if ($patUser) {
                Database::query("INSERT INTO notifications (user_id, title, message, type)
                    VALUES (?, 'Appointment Confirmed', ?, 'success')",
                    [$patUser['id'], "Your appointment {$apptNo} has been booked successfully for {$data['appointment_date']} at {$data['appointment_time']}."]
                );
            }

            Database::commit();
            auditLog('CREATE_APPOINTMENT', 'appointments', $apptId, [], ['appt_no'=>$apptNo]);
            jsonResponse(['success'=>true,'message'=>'Appointment booked!','appointment_id'=>$apptId,'appointment_no'=>$apptNo,'token'=>$token]);

        } catch (Throwable $e) {
            Database::rollback();
            jsonResponse(['success'=>false,'message'=>'Booking failed: '.$e->getMessage()], 500);
        }

    // ── PUT: update status / reschedule ───────────────────────
    case 'PUT':
        if (!$id) jsonResponse(['success'=>false,'message'=>'Appointment ID required.'], 400);
        $appt = Database::fetchOne("SELECT * FROM appointments WHERE id=?", [$id]);
        if (!$appt) jsonResponse(['success'=>false,'message'=>'Not found.'], 404);

        $allowed = ['status','appointment_date','appointment_time','notes','symptoms'];
        $sets = []; $params = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $sets[]   = "{$field} = ?";
                $params[] = clean((string)$body[$field]);
            }
        }
        if (!$sets) jsonResponse(['success'=>false,'message'=>'Nothing to update.'], 400);
        $params[] = $id;
        Database::query("UPDATE appointments SET ".implode(',',$sets)." WHERE id=?", $params);
        auditLog('UPDATE_APPOINTMENT', 'appointments', $id, $appt, $body);
        jsonResponse(['success'=>true,'message'=>'Appointment updated.']);

    // ── DELETE: cancel ────────────────────────────────────────
    case 'DELETE':
        if (!$id) jsonResponse(['success'=>false,'message'=>'ID required.'], 400);
        Database::query("UPDATE appointments SET status='cancelled' WHERE id=?", [$id]);
        auditLog('CANCEL_APPOINTMENT', 'appointments', $id);
        jsonResponse(['success'=>true,'message'=>'Appointment cancelled.']);

    default:
        jsonResponse(['success'=>false,'message'=>'Method not allowed.'], 405);
}
