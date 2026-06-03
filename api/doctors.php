<?php
// ============================================================
// api/doctors.php — RESTful Doctors API
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

    case 'GET':
        if ($id) {
            $doctor = Database::fetchOne("
                SELECT d.*, u.full_name, u.email, u.phone, u.avatar, u.status AS user_status,
                       dep.name AS dept_name, dep.color AS dept_color
                FROM doctors d
                JOIN users u ON u.id = d.user_id
                JOIN departments dep ON dep.id = d.department_id
                WHERE d.id = ?
            ", [$id]);
            if (!$doctor) jsonResponse(['success'=>false,'message'=>'Doctor not found.'], 404);

            // Today's schedule
            $todayAppts = Database::fetchAll("
                SELECT a.token_number, a.appointment_time, a.status,
                       u.full_name AS patient_name, p.patient_code
                FROM appointments a
                JOIN patients p ON p.id=a.patient_id
                JOIN users    u ON u.id=p.user_id
                WHERE a.doctor_id=? AND a.appointment_date=CURDATE()
                ORDER BY a.token_number
            ", [$id]);

            // Rating stats
            $ratings = Database::fetchOne("SELECT rating, total_ratings FROM doctors WHERE id=?", [$id]);

            jsonResponse(['success'=>true,'data'=>$doctor,'schedule'=>$todayAppts,'ratings'=>$ratings]);
        }

        // List doctors with optional filters
        $where  = ['u.status=\'active\'']; $params = [];
        if (!empty($_GET['department_id'])) { $where[] = 'd.department_id=?'; $params[] = (int)$_GET['department_id']; }
        if (!empty($_GET['status']))        { $where[] = 'd.status=?';         $params[] = clean($_GET['status']); }
        if (!empty($_GET['q'])) {
            $like    = '%'.clean($_GET['q']).'%';
            $where[] = '(u.full_name LIKE ? OR d.specialization LIKE ?)';
            $params  = array_merge($params, [$like, $like]);
        }

        $ws = implode(' AND ', $where);
        $doctors = Database::fetchAll("
            SELECT d.id, d.doctor_code, d.specialization, d.experience_years,
                   d.consultation_fee, d.available_days, d.time_from, d.time_to,
                   d.slot_duration, d.status, d.rating, d.total_ratings,
                   u.full_name, u.email, u.phone, u.avatar,
                   dep.name AS dept_name, dep.color AS dept_color,
                   (SELECT COUNT(*) FROM appointments WHERE doctor_id=d.id AND appointment_date=CURDATE()) AS today_appts
            FROM doctors d
            JOIN users u ON u.id = d.user_id
            JOIN departments dep ON dep.id = d.department_id
            WHERE {$ws}
            ORDER BY d.status='available' DESC, u.full_name ASC
        ", $params);

        jsonResponse(['success'=>true,'data'=>$doctors,'count'=>count($doctors)]);

    case 'POST':
        Auth::requireRole('admin');
        $data = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?? []);

        $errors = [];
        if (empty($data['full_name']))      $errors['full_name']      = 'Name required.';
        if (empty($data['email']))          $errors['email']          = 'Email required.';
        if (empty($data['department_id']))  $errors['department_id']  = 'Department required.';
        if (empty($data['specialization'])) $errors['specialization'] = 'Specialization required.';
        if (empty($data['password']))       $errors['password']       = 'Password required.';
        if ($errors) jsonResponse(['success'=>false,'message'=>'Validation failed.','errors'=>$errors], 422);

        $exists = Database::fetchOne("SELECT id FROM users WHERE email=?", [$data['email']]);
        if ($exists) jsonResponse(['success'=>false,'message'=>'Email already registered.','errors'=>['email'=>'Already in use.']], 409);

        Database::beginTransaction();
        try {
            $userId = Database::insert(
                "INSERT INTO users (uuid, full_name, email, phone, password, role) VALUES (?,?,?,?,?,?)",
                [uuid4(), clean($data['full_name']), clean($data['email']),
                 clean($data['phone'] ?? ''), password_hash($data['password'], PASSWORD_BCRYPT), 'doctor']
            );

            $doctorId = Database::insert("
                INSERT INTO doctors
                  (user_id, doctor_code, department_id, specialization, qualification,
                   experience_years, consultation_fee, bio, license_number,
                   available_days, time_from, time_to, slot_duration)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ", [
                $userId,
                generateCode('DOC', $userId),
                (int)$data['department_id'],
                clean($data['specialization']),
                clean($data['qualification']   ?? ''),
                (int)($data['experience_years'] ?? 0),
                (float)($data['consultation_fee'] ?? 500),
                clean($data['bio']             ?? ''),
                clean($data['license_number']  ?? ''),
                clean($data['available_days']  ?? 'Mon,Tue,Wed,Thu,Fri'),
                $data['time_from']             ?? '09:00:00',
                $data['time_to']               ?? '17:00:00',
                (int)($data['slot_duration']   ?? 30),
            ]);

            Database::commit();
            auditLog('CREATE_DOCTOR', 'doctors', $doctorId);
            jsonResponse(['success'=>true,'message'=>'Doctor registered successfully!','doctor_id'=>$doctorId]);
        } catch (Throwable $e) {
            Database::rollback();
            jsonResponse(['success'=>false,'message'=>'Failed: '.$e->getMessage()], 500);
        }

    case 'PUT':
        Auth::requireRole(['admin','doctor']);
        if (!$id) jsonResponse(['success'=>false,'message'=>'Doctor ID required.'], 400);

        $doctor = Database::fetchOne("SELECT d.*, d.user_id FROM doctors d WHERE d.id=?", [$id]);
        if (!$doctor) jsonResponse(['success'=>false,'message'=>'Not found.'], 404);

        // Doctors can only edit their own profile
        if (Auth::role() === 'doctor') {
            $ownDoc = Database::fetchOne("SELECT id FROM doctors WHERE user_id=?", [Auth::id()]);
            if (!$ownDoc || $ownDoc['id'] !== $id) {
                jsonResponse(['success'=>false,'message'=>'Forbidden.'], 403);
            }
        }

        $old = $doctor;
        Database::beginTransaction();
        try {
            // Update user fields
            $userUpdates = [];
            if (isset($body['full_name'])) $userUpdates[] = ['full_name', clean($body['full_name'])];
            if (isset($body['phone']))     $userUpdates[] = ['phone',     clean($body['phone'])];
            if ($userUpdates) {
                $sets   = implode(',', array_map(fn($u) => "{$u[0]}=?", $userUpdates));
                $vals   = array_merge(array_map(fn($u) => $u[1], $userUpdates), [$doctor['user_id']]);
                Database::query("UPDATE users SET {$sets} WHERE id=?", $vals);
            }

            // Update doctor fields
            $allowed = ['department_id','specialization','qualification','experience_years',
                        'consultation_fee','bio','license_number','available_days',
                        'time_from','time_to','slot_duration','status'];
            $sets = []; $params = [];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $body)) { $sets[] = "{$f}=?"; $params[] = $body[$f]; }
            }
            if ($sets) {
                $params[] = $id;
                Database::query("UPDATE doctors SET ".implode(',',$sets)." WHERE id=?", $params);
            }

            Database::commit();
            auditLog('UPDATE_DOCTOR', 'doctors', $id, $old, $body);
            jsonResponse(['success'=>true,'message'=>'Doctor profile updated!']);
        } catch (Throwable $e) {
            Database::rollback();
            jsonResponse(['success'=>false,'message'=>'Update failed: '.$e->getMessage()], 500);
        }

    // Get available slots for a doctor on a date
    case 'GET':
        // handled above — this is for ?action=slots
        break;

    default:
        jsonResponse(['success'=>false,'message'=>'Method not allowed.'], 405);
}
