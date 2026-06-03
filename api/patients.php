<?php
// ============================================================
// api/patients.php — RESTful patients API
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireAuth();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

// Parse body for PUT/PATCH
$body = [];
if (in_array($method, ['PUT','PATCH'])) {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
}

$id = (int)($_GET['id'] ?? 0);

switch ($method) {

    // ── GET: list or single ───────────────────────────────────
    case 'GET':
        if ($id) {
            $patient = Database::fetchOne("
                SELECT p.*, u.full_name, u.email, u.phone, u.status, u.created_at AS reg_date,
                       u.avatar, u.last_login
                FROM patients p
                JOIN users u ON u.id = p.user_id
                WHERE p.id = ?
            ", [$id]);
            if (!$patient) jsonResponse(['success'=>false,'message'=>'Patient not found'], 404);

            // Medical history
            $appointments = Database::fetchAll("
                SELECT a.*, u.full_name AS doctor_name, dep.name AS dept_name
                FROM appointments a
                JOIN doctors d ON d.id = a.doctor_id
                JOIN users u ON u.id = d.user_id
                JOIN departments dep ON dep.id = a.department_id
                WHERE a.patient_id = ?
                ORDER BY a.appointment_date DESC LIMIT 20
            ", [$id]);

            $prescriptions = Database::fetchAll("
                SELECT pr.*, u.full_name AS doctor_name,
                       GROUP_CONCAT(pi.medicine_name SEPARATOR ', ') AS medicines
                FROM prescriptions pr
                JOIN doctors d ON d.id = pr.doctor_id
                JOIN users u ON u.id = d.user_id
                LEFT JOIN prescription_items pi ON pi.prescription_id = pr.id
                WHERE pr.patient_id = ?
                GROUP BY pr.id ORDER BY pr.created_at DESC LIMIT 10
            ", [$id]);

            $labOrders = Database::fetchAll("
                SELECT lo.*, lt.name AS test_name, lt.category
                FROM lab_orders lo
                JOIN lab_tests lt ON lt.id = lo.test_id
                WHERE lo.patient_id = ?
                ORDER BY lo.created_at DESC LIMIT 10
            ", [$id]);

            jsonResponse(['success'=>true,'data'=>$patient,'appointments'=>$appointments,'prescriptions'=>$prescriptions,'lab_orders'=>$labOrders]);
        }

        // List patients
        $search = clean($_GET['q'] ?? '');
        $limit  = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);

        $sql    = "SELECT p.id, p.patient_code, p.gender, p.blood_group, p.dob, p.city,
                          u.full_name, u.email, u.phone, u.status
                   FROM patients p JOIN users u ON u.id = p.user_id";
        $params = [];
        if ($search) {
            $sql .= " WHERE (u.full_name LIKE ? OR p.patient_code LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
            $like = "%{$search}%";
            $params = [$like,$like,$like,$like];
        }
        $sql .= " ORDER BY p.id DESC LIMIT ? OFFSET ?";
        $params[] = $limit; $params[] = $offset;

        $total    = Database::fetchOne("SELECT COUNT(*) AS c FROM patients p JOIN users u ON u.id=p.user_id" . ($search?" WHERE (u.full_name LIKE ? OR p.patient_code LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)":""), $search?["%$search%","%$search%","%$search%","%$search%"]:[])['c'];
        $patients = Database::fetchAll($sql, $params);

        jsonResponse(['success'=>true,'data'=>$patients,'total'=>$total,'limit'=>$limit,'offset'=>$offset]);

    // ── POST: create ──────────────────────────────────────────
    case 'POST':
        Auth::requireRole(['admin','receptionist']);

        $data = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?? []);

        // Validation
        $errors = [];
        if (empty($data['full_name']))  $errors['full_name']  = 'Full name is required.';
        if (empty($data['email']))      $errors['email']      = 'Email is required.';
        if (empty($data['phone']))      $errors['phone']      = 'Phone is required.';
        if (empty($data['gender']))     $errors['gender']     = 'Gender is required.';
        if (empty($data['password']))   $errors['password']   = 'Password is required.';
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
            $errors['email'] = 'Invalid email format.';

        if ($errors) jsonResponse(['success'=>false,'message'=>'Validation failed.','errors'=>$errors], 422);

        // Check email uniqueness
        $exists = Database::fetchOne("SELECT id FROM users WHERE email = ?", [$data['email']]);
        if ($exists) jsonResponse(['success'=>false,'message'=>'Email already registered.','errors'=>['email'=>'This email is already in use.']], 409);

        Database::beginTransaction();
        try {
            // Create user
            $userId = Database::insert(
                "INSERT INTO users (uuid, full_name, email, phone, password, role) VALUES (?,?,?,?,?,?)",
                [uuid4(), clean($data['full_name']), clean($data['email']), clean($data['phone']),
                 password_hash($data['password'], PASSWORD_BCRYPT), 'patient']
            );

            // Create patient record
            $patientId = Database::insert(
                "INSERT INTO patients (user_id, patient_code, dob, gender, blood_group, address, city, state, pincode, allergies, chronic_diseases, emergency_name, emergency_phone, emergency_relation, insurance_provider, insurance_number, insurance_expiry)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $userId,
                    generateCode('PAT', $userId),
                    $data['dob']              ?: null,
                    clean($data['gender']),
                    clean($data['blood_group']  ?? ''),
                    clean($data['address']      ?? ''),
                    clean($data['city']         ?? ''),
                    clean($data['state']        ?? ''),
                    clean($data['pincode']      ?? ''),
                    clean($data['allergies']    ?? ''),
                    clean($data['chronic_diseases'] ?? ''),
                    clean($data['emergency_name']   ?? ''),
                    clean($data['emergency_phone']  ?? ''),
                    clean($data['emergency_relation'] ?? ''),
                    clean($data['insurance_provider'] ?? ''),
                    clean($data['insurance_number']   ?? ''),
                    $data['insurance_expiry'] ?: null,
                ]
            );

            Database::commit();
            auditLog('CREATE_PATIENT', 'patients', $patientId, [], ['patient_id'=>$patientId]);
            jsonResponse(['success'=>true,'message'=>'Patient registered successfully!','patient_id'=>$patientId,'user_id'=>$userId]);

        } catch (Throwable $e) {
            Database::rollback();
            jsonResponse(['success'=>false,'message'=>'Registration failed: '.$e->getMessage()], 500);
        }

    // ── PUT: update ───────────────────────────────────────────
    case 'PUT':
        Auth::requireRole(['admin','receptionist']);
        if (!$id) jsonResponse(['success'=>false,'message'=>'Patient ID required.'], 400);

        $patient = Database::fetchOne("SELECT p.*, p.user_id FROM patients p WHERE p.id = ?", [$id]);
        if (!$patient) jsonResponse(['success'=>false,'message'=>'Patient not found.'], 404);

        $old = $patient;
        Database::beginTransaction();
        try {
            Database::query("UPDATE users SET full_name=?, email=?, phone=? WHERE id=?",
                [clean($body['full_name'] ?? $patient['full_name']),
                 clean($body['email']     ?? ''),
                 clean($body['phone']     ?? ''),
                 $patient['user_id']]);

            Database::query("UPDATE patients SET dob=?, gender=?, blood_group=?, address=?, city=?, state=?, pincode=?,
                             allergies=?, chronic_diseases=?, emergency_name=?, emergency_phone=?, emergency_relation=?,
                             insurance_provider=?, insurance_number=?, insurance_expiry=? WHERE id=?",
                [$body['dob'] ?: null, clean($body['gender'] ?? ''), clean($body['blood_group'] ?? ''),
                 clean($body['address'] ?? ''), clean($body['city'] ?? ''), clean($body['state'] ?? ''),
                 clean($body['pincode'] ?? ''), clean($body['allergies'] ?? ''),
                 clean($body['chronic_diseases'] ?? ''), clean($body['emergency_name'] ?? ''),
                 clean($body['emergency_phone'] ?? ''), clean($body['emergency_relation'] ?? ''),
                 clean($body['insurance_provider'] ?? ''), clean($body['insurance_number'] ?? ''),
                 $body['insurance_expiry'] ?: null, $id]);

            Database::commit();
            auditLog('UPDATE_PATIENT', 'patients', $id, $old, $body);
            jsonResponse(['success'=>true,'message'=>'Patient updated successfully!']);
        } catch (Throwable $e) {
            Database::rollback();
            jsonResponse(['success'=>false,'message'=>'Update failed: '.$e->getMessage()], 500);
        }

    // ── DELETE ────────────────────────────────────────────────
    case 'DELETE':
        Auth::requireRole('admin');
        if (!$id) jsonResponse(['success'=>false,'message'=>'Patient ID required.'], 400);
        $p = Database::fetchOne("SELECT user_id FROM patients WHERE id=?", [$id]);
        if (!$p) jsonResponse(['success'=>false,'message'=>'Not found.'], 404);
        Database::query("UPDATE users SET status='inactive' WHERE id=?", [$p['user_id']]);
        auditLog('DEACTIVATE_PATIENT', 'patients', $id);
        jsonResponse(['success'=>true,'message'=>'Patient deactivated.']);

    default:
        jsonResponse(['success'=>false,'message'=>'Method not allowed.'], 405);
}
