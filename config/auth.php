<?php
// ============================================================
// config/auth.php — Session-based auth with role control
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

class Auth {
    // ── Login ────────────────────────────────────────────────
    public static function attempt(string $email, string $password): bool {
        $user = Database::fetchOne(
            "SELECT id, uuid, full_name, email, phone, password, role, avatar, status FROM users WHERE email = ? LIMIT 1",
            [$email]
        );
        if (!$user || !password_verify($password, $user['password'])) return false;
        if ($user['status'] !== 'active') return false;

        // Persist session
        self::setSession($user);

        // Update last_login
        Database::query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
        auditLog('LOGIN', 'users', $user['id']);
        return true;
    }

    // ── Set session after login ──────────────────────────────
    public static function setSession(array $user): void {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['uuid']      = $user['uuid'];
        $_SESSION['name']      = $user['full_name'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['avatar']    = $user['avatar'] ?? null;
        $_SESSION['logged_in'] = true;
    }

    // ── Check if authenticated ───────────────────────────────
    public static function check(): bool {
        return !empty($_SESSION['logged_in']);
    }

    // ── Require auth (redirect if not) ──────────────────────
    public static function requireAuth(): void {
        if (!self::check()) redirect(APP_URL . '/login.php');
    }

    // ── Require specific role(s) ─────────────────────────────
    public static function requireRole(string|array $roles): void {
        self::requireAuth();
        $allowed = (array) $roles;
        if (!in_array($_SESSION['role'], $allowed, true)) {
            http_response_code(403);
            die(self::forbiddenPage());
        }
    }

    // ── Current user info ────────────────────────────────────
    public static function user(): array {
        return [
            'id'     => $_SESSION['user_id']   ?? 0,
            'uuid'   => $_SESSION['uuid']       ?? '',
            'name'   => $_SESSION['name']       ?? 'Guest',
            'email'  => $_SESSION['email']      ?? '',
            'role'   => $_SESSION['role']       ?? 'guest',
            'avatar' => $_SESSION['avatar']     ?? null,
        ];
    }

    public static function id(): int    { return (int)($_SESSION['user_id'] ?? 0); }
    public static function role(): string { return $_SESSION['role'] ?? 'guest'; }

    // ── Logout ───────────────────────────────────────────────
    public static function logout(): void {
        auditLog('LOGOUT', 'users', self::id());
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // ── OTP helpers ──────────────────────────────────────────
    public static function generateOTP(): string {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function saveOTP(int $userId, string $otp): void {
        Database::query(
            "UPDATE users SET otp_code = ?, otp_expires = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?",
            [password_hash($otp, PASSWORD_BCRYPT), $userId]
        );
    }

    public static function verifyOTP(int $userId, string $otp): bool {
        $row = Database::fetchOne(
            "SELECT otp_code, otp_expires FROM users WHERE id = ? AND otp_expires > NOW()",
            [$userId]
        );
        if (!$row) return false;
        if (!password_verify($otp, $row['otp_code'])) return false;
        Database::query("UPDATE users SET otp_code = NULL, otp_expires = NULL WHERE id = ?", [$userId]);
        return true;
    }

    // ── 403 page ────────────────────────────────────────────
    private static function forbiddenPage(): string {
        return '<!DOCTYPE html><html><head><title>403 Forbidden</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>
        <body class="bg-dark text-white d-flex align-items-center justify-content-center" style="height:100vh">
        <div class="text-center"><h1 class="display-1">403</h1><p class="lead">Access Denied</p>
        <a href="' . APP_URL . '/login.php" class="btn btn-primary">Go to Login</a></div></body></html>';
    }

    // ── Role dashboard URL ───────────────────────────────────
    public static function dashboardUrl(): string {
        $map = [
            'admin'          => '/admin/dashboard.php',
            'doctor'         => '/doctor/dashboard.php',
            'receptionist'   => '/receptionist/dashboard.php',
            'pharmacist'     => '/pharmacist/dashboard.php',
            'lab_technician' => '/laboratory/dashboard.php',
            'patient'        => '/patient/dashboard.php',
        ];
        return APP_URL . ($map[self::role()] ?? '/login.php');
    }
}
