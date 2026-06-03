<?php
// ============================================================
// authentication/register_process.php
// Processes validated registration from OTP-verified session
// Called AFTER otp_verification.php confirms the OTP
// ============================================================
require_once __DIR__ . '/../config/config.php';

// Must come from OTP verification flow
if (empty($_SESSION['reg_verified']) || empty($_SESSION['pending_reg'])) {
    flash('danger', 'Registration session expired. Please start again.');
    redirect(APP_URL . '/register.php');
}

$reg  = $_SESSION['pending_reg'];
$role = $reg['role'];

// Double-check email uniqueness (race condition guard)
$exists = Database::fetchOne("SELECT id FROM users WHERE email = ?", [$reg['email']]);
if ($exists) {
    unset($_SESSION['pending_reg'], $_SESSION['reg_verified']);
    flash('danger', 'This email is already registered. Please log in.');
    redirect(APP_URL . '/login.php');
}

Database::beginTransaction();
try {

    // ── 1. Insert into users table ───────────────────────────
    $userId = Database::insert(
        "INSERT INTO users
             (uuid, full_name, email, phone, password, role, avatar, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'active')",
        [
            uuid4(),
            $reg['full_name'],
            $reg['email'],
            $reg['phone'],
            $reg['password'],   // already bcrypt-hashed in register.php
            $role,
            $reg['avatar'],     // may be null
        ]
    );

    // ── 2. Role-specific record ──────────────────────────────
    switch ($role) {

        case 'patient':
            $patientId = Database::insert(
                "INSERT INTO patients
                     (user_id, patient_code, dob, gender, blood_group, created_at)
                 VALUES (?, ?, ?, ?, '', NOW())",
                [
                    $userId,
                    generateCode('PAT', $userId),
                    !empty($reg['dob']) ? $reg['dob'] : null,
                    $reg['gender'],
                ]
            );
            auditLog('REGISTER_PATIENT', 'patients', $patientId, [], [
                'user_id' => $userId,
                'email'   => $reg['email'],
            ]);
            break;

        case 'doctor':
            // department_id defaults to 1 if not set
            $deptId = $reg['department_id'] ?: 1;

            $doctorId = Database::insert(
                "INSERT INTO doctors
                     (user_id, doctor_code, department_id, specialization,
                      qualification, experience_years, consultation_fee,
                      available_days, time_from, time_to, slot_duration, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'Mon,Tue,Wed,Thu,Fri', '09:00:00', '17:00:00', 30, 'available')",
                [
                    $userId,
                    generateCode('DOC', $userId),
                    $deptId,
                    $reg['specialization'] ?: 'General Physician',
                    $reg['qualification']  ?: 'MBBS',
                    (int) $reg['experience_years'],
                    (float)($reg['consultation_fee'] ?: 500),
                ]
            );

            // Notify admin of new doctor pending review
            $admins = Database::fetchAll(
                "SELECT id FROM users WHERE role='admin' AND status='active'"
            );
            foreach ($admins as $admin) {
                Database::query(
                    "INSERT INTO notifications
                         (user_id, title, message, type)
                     VALUES (?, ?, ?, 'info')",
                    [
                        $admin['id'],
                        'New Doctor Registered',
                        "Dr. {$reg['full_name']} has registered as a doctor. Please review and verify their profile.",
                    ]
                );
            }

            auditLog('REGISTER_DOCTOR', 'doctors', $doctorId, [], [
                'user_id' => $userId,
                'email'   => $reg['email'],
            ]);
            break;

        case 'receptionist':
            // Receptionists have no sub-table; notify admin
            $admins = Database::fetchAll(
                "SELECT id FROM users WHERE role='admin' AND status='active'"
            );
            foreach ($admins as $admin) {
                Database::query(
                    "INSERT INTO notifications
                         (user_id, title, message, type)
                     VALUES (?, ?, ?, 'info')",
                    [
                        $admin['id'],
                        'New Receptionist Registered',
                        "{$reg['full_name']} has registered as a receptionist. Please review their account.",
                    ]
                );
            }

            auditLog('REGISTER_RECEPTIONIST', 'users', $userId, [], [
                'email' => $reg['email'],
            ]);
            break;
    }

    Database::commit();

    // ── 3. Clean up session ──────────────────────────────────
    unset(
        $_SESSION['pending_reg'],
        $_SESSION['reg_verified'],
        $_SESSION['reg_otp'],
        $_SESSION['reg_otp_expires'],
        $_SESSION['reg_otp_email'],
        $_SESSION['reg_demo_otp']
    );

    // ── 4. Auto-login the new user ───────────────────────────
    $newUser = Database::fetchOne(
        "SELECT id, uuid, full_name, email, phone, role, avatar, status
         FROM users WHERE id = ?",
        [$userId]
    );
    Auth::setSession($newUser);

    // Update last_login
    Database::query("UPDATE users SET last_login = NOW() WHERE id = ?", [$userId]);

    $roleLabels = [
        'patient'      => 'Patient Portal',
        'doctor'       => 'Doctor Dashboard',
        'receptionist' => 'Receptionist Dashboard',
    ];
    $label = $roleLabels[$role] ?? 'Dashboard';

    flash('success', "Welcome, {$reg['full_name']}! Your account has been created. You are now logged in.");
    redirect(Auth::dashboardUrl());

} catch (Throwable $e) {
    Database::rollback();
    // Log the error
    error_log('[HMS Register Process] ' . $e->getMessage());
    flash('danger', 'Registration failed due to a server error. Please try again.');
    redirect(APP_URL . '/register.php');
}
