<?php
// ============================================================
// ajax/appointments.php — Appointments & Prescription AJAX
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireAuth();
header('Content-Type: application/json');

$action = clean($_GET['action'] ?? '');
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {

    // ── Create prescription ──────────────────────────────────
    case 'create_rx':
        Auth::requireRole('doctor');
        $doctor = Database::fetchOne("SELECT id FROM doctors WHERE user_id=?", [Auth::id()]);
        if (!$doctor) jsonResponse(['success'=>false,'message'=>'Doctor profile not found.'], 403);
        $did = $doctor['id'];

        $errors = [];
        if (empty($body['patient_id']))  $errors['patient_id']  = 'Patient required.';
        if (empty($body['diagnosis']))   $errors['diagnosis']   = 'Diagnosis required.';
        if (empty($body['medicines']) || !is_array($body['medicines'])) {
            $errors['medicines'] = 'At least one medicine required.';
        }
        if ($errors) jsonResponse(['success'=>false,'message'=>'Validation failed.','errors'=>$errors], 422);

        Database::beginTransaction();
        try {
            $year    = date('Y');
            $cntRow  = Database::fetchOne("SELECT COUNT(*)+1 AS c FROM prescriptions WHERE YEAR(created_at)=?", [$year]);
            $rxNo    = 'RX-'.$year.'-'.str_pad($cntRow['c'], 4, '0', STR_PAD_LEFT);

            $rxId = Database::insert("
                INSERT INTO prescriptions
                  (prescription_no, appointment_id, patient_id, doctor_id,
                   diagnosis, notes, follow_up_date, status)
                VALUES (?,?,?,?,?,?,?,?)
            ", [
                $rxNo,
                !empty($body['appointment_id']) ? (int)$body['appointment_id'] : null,
                (int)$body['patient_id'],
                $did,
                clean($body['diagnosis']),
                clean($body['notes'] ?? ''),
                !empty($body['follow_up_date']) ? $body['follow_up_date'] : null,
                'active',
            ]);

            foreach ($body['medicines'] as $med) {
                if (empty(trim($med['medicine'] ?? ''))) continue;
                Database::query("
                    INSERT INTO prescription_items
                      (prescription_id, medicine_name, dosage, frequency, duration, instructions)
                    VALUES (?,?,?,?,?,?)
                ", [
                    $rxId,
                    clean($med['medicine']),
                    clean($med['dosage']       ?? ''),
                    clean($med['frequency']    ?? ''),
                    clean($med['duration']     ?? ''),
                    clean($med['instructions'] ?? ''),
                ]);
            }

            // Notify patient
            $patUser = Database::fetchOne(
                "SELECT u.id FROM users u JOIN patients p ON p.user_id=u.id WHERE p.id=?",
                [(int)$body['patient_id']]
            );
            if ($patUser) {
                Database::query("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)",
                    [$patUser['id'], 'New Prescription',
                     "Your prescription {$rxNo} has been created. Please collect your medicines from the pharmacy.", 'success']
                );
            }

            Database::commit();
            auditLog('CREATE_PRESCRIPTION', 'prescriptions', $rxId);
            jsonResponse(['success'=>true,'message'=>'Prescription saved!','prescription_no'=>$rxNo,'prescription_id'=>$rxId]);

        } catch (Throwable $e) {
            Database::rollback();
            jsonResponse(['success'=>false,'message'=>'Failed: '.$e->getMessage()], 500);
        }

    // ── Apply doctor leave ───────────────────────────────────
    case 'apply_leave':
        Auth::requireRole('doctor');
        $doctor = Database::fetchOne("SELECT id FROM doctors WHERE user_id=?", [Auth::id()]);
        if (!$doctor) jsonResponse(['success'=>false,'message'=>'Doctor not found.'], 403);

        $from   = clean($body['from_date'] ?? '');
        $to     = clean($body['to_date']   ?? '');
        $reason = clean($body['reason']    ?? '');

        if (!$from || !$to) jsonResponse(['success'=>false,'message'=>'Both dates required.'], 422);
        if ($to < $from)    jsonResponse(['success'=>false,'message'=>'End date must be after start date.'], 422);

        $leaveId = Database::insert("
            INSERT INTO doctor_leaves (doctor_id, from_date, to_date, reason, status)
            VALUES (?,?,?,?,?)",
            [$doctor['id'], $from, $to, $reason, 'pending']
        );

        // Notify admin
        $admins = Database::fetchAll("SELECT id FROM users WHERE role='admin' AND status='active'");
        $docName = Auth::user()['name'];
        foreach ($admins as $admin) {
            Database::query("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)",
                [$admin['id'], 'Leave Request', "{$docName} has applied for leave from {$from} to {$to}.", 'warning']
            );
        }

        auditLog('APPLY_LEAVE', 'doctor_leaves', $leaveId);
        jsonResponse(['success'=>true,'message'=>'Leave request submitted for approval!','leave_id'=>$leaveId]);

    // ── Get available time slots ─────────────────────────────
    case 'get_slots':
        $doctorId = (int)($body['doctor_id'] ?? 0);
        $date     = clean($body['date'] ?? '');
        if (!$doctorId || !$date) jsonResponse(['success'=>false,'message'=>'Doctor ID and date required.'], 422);

        $doctor = Database::fetchOne("SELECT time_from, time_to, slot_duration FROM doctors WHERE id=?", [$doctorId]);
        if (!$doctor) jsonResponse(['success'=>false,'message'=>'Doctor not found.'], 404);

        // Get booked slots
        $booked = Database::fetchAll(
            "SELECT appointment_time FROM appointments WHERE doctor_id=? AND appointment_date=? AND status NOT IN ('cancelled','no_show')",
            [$doctorId, $date]
        );
        $bookedTimes = array_column($booked, 'appointment_time');

        // Generate slots
        $slots   = [];
        $from    = strtotime($doctor['time_from']);
        $to      = strtotime($doctor['time_to']);
        $dur     = (int)$doctor['slot_duration'] * 60;

        for ($t = $from; $t < $to; $t += $dur) {
            $time    = date('H:i', $t);
            $display = date('h:i A', $t);
            $isBooked= in_array($time.':00', $bookedTimes) || in_array($time, $bookedTimes);
            $slots[] = ['time' => $time, 'display' => $display, 'available' => !$isBooked];
        }

        jsonResponse(['success'=>true,'slots'=>$slots,'date'=>$date]);

    // ── Reschedule appointment ───────────────────────────────
    case 'reschedule':
        $apptId  = (int)($body['appointment_id'] ?? 0);
        $newDate = clean($body['new_date'] ?? '');
        $newTime = clean($body['new_time'] ?? '');
        if (!$apptId || !$newDate || !$newTime) jsonResponse(['success'=>false,'message'=>'All fields required.'], 422);

        $appt = Database::fetchOne("SELECT * FROM appointments WHERE id=?", [$apptId]);
        if (!$appt) jsonResponse(['success'=>false,'message'=>'Appointment not found.'], 404);

        // Ownership check
        if (Auth::role() === 'patient') {
            $pat = Database::fetchOne("SELECT id FROM patients WHERE user_id=?", [Auth::id()]);
            if (!$pat || $pat['id'] !== (int)$appt['patient_id']) {
                jsonResponse(['success'=>false,'message'=>'Forbidden.'], 403);
            }
        }

        // Check new slot availability
        $clash = Database::fetchOne("
            SELECT id FROM appointments
            WHERE doctor_id=? AND appointment_date=? AND appointment_time=?
              AND status NOT IN ('cancelled','no_show') AND id != ?
        ", [$appt['doctor_id'], $newDate, $newTime, $apptId]);
        if ($clash) jsonResponse(['success'=>false,'message'=>'This slot is already taken.'], 409);

        Database::query("UPDATE appointments SET appointment_date=?, appointment_time=?, status='booked' WHERE id=?",
            [$newDate, $newTime, $apptId]);

        auditLog('RESCHEDULE_APPOINTMENT', 'appointments', $apptId);
        jsonResponse(['success'=>true,'message'=>'Appointment rescheduled successfully!']);

    // ── Queue status update ──────────────────────────────────
    case 'update_queue':
        Auth::requireRole(['doctor','receptionist','admin']);
        $apptId = (int)($body['appointment_id'] ?? 0);
        $status = clean($body['status'] ?? '');
        $allowed= ['waiting','in_progress','completed','no_show'];
        if (!$apptId || !in_array($status, $allowed)) {
            jsonResponse(['success'=>false,'message'=>'Invalid parameters.'], 422);
        }
        Database::query("UPDATE appointments SET status=? WHERE id=?", [$status, $apptId]);
        auditLog('UPDATE_QUEUE_STATUS', 'appointments', $apptId);
        jsonResponse(['success'=>true,'message'=>'Queue updated.','new_status'=>$status]);

    // ── Today's queue summary ────────────────────────────────
    case 'queue_summary':
        Auth::requireRole(['doctor','receptionist','admin']);
        $doctorId = (int)($body['doctor_id'] ?? 0);
        if (!$doctorId && Auth::role() === 'doctor') {
            $d = Database::fetchOne("SELECT id FROM doctors WHERE user_id=?", [Auth::id()]);
            $doctorId = $d['id'] ?? 0;
        }

        $queue = Database::fetchAll("
            SELECT a.id, a.token_number, a.appointment_time, a.status, a.type,
                   u.full_name AS patient_name, p.patient_code, p.allergies
            FROM appointments a
            JOIN patients p ON p.id=a.patient_id JOIN users u ON u.id=p.user_id
            WHERE a.doctor_id=? AND a.appointment_date=CURDATE()
              AND a.status NOT IN ('cancelled','no_show')
            ORDER BY a.token_number
        ", [$doctorId]);

        $stats = [
            'total'       => count($queue),
            'waiting'     => count(array_filter($queue, fn($q)=>$q['status']==='waiting')),
            'in_progress' => count(array_filter($queue, fn($q)=>$q['status']==='in_progress')),
            'completed'   => count(array_filter($queue, fn($q)=>$q['status']==='completed')),
        ];

        jsonResponse(['success'=>true,'queue'=>$queue,'stats'=>$stats]);

    default:
        jsonResponse(['success'=>false,'message'=>'Unknown action: '.$action], 400);
}
