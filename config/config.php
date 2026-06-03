<?php
// ============================================================
// config/config.php — App-wide constants & helpers
// ============================================================

define('APP_NAME',     'MediCare HMS');
define('APP_VERSION',  '1.0.0');
define('APP_URL',      rtrim(getenv('APP_URL') ?: 'http://localhost/hospital-management-system', '/'));
define('BASE_PATH',    dirname(__DIR__));
define('UPLOAD_PATH',  BASE_PATH . '/assets/uploads/');
define('UPLOAD_URL',   APP_URL  . '/assets/uploads/');
define('MAX_FILE_MB',  10);
define('GST_RATE',     0.18);   // 18 %
define('TIMEZONE',     'Asia/Kolkata');

date_default_timezone_set(TIMEZONE);

// ── Autoload core files ──────────────────────────────────────
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/auth.php';

// ── Helpers ──────────────────────────────────────────────────

/** Sanitise input */
function clean(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}

/** Redirect */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/** Generate unique code  e.g. PAT-00234 */
function generateCode(string $prefix, int $id): string {
    return $prefix . '-' . str_pad($id, 5, '0', STR_PAD_LEFT);
}

/** UUID v4 */
function uuid4(): string {
    $data    = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/** JSON response helper (for API endpoints) */
function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Flash message (stored in session) */
function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/** Format currency */
function formatCurrency(float $amount): string {
    return '₹' . number_format($amount, 2);
}

/** Allowed upload MIME types */
define('ALLOWED_MIMES', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]);

/** Handle file upload, returns relative path or null */
function handleUpload(array $file, string $subDir = 'docs'): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > MAX_FILE_MB * 1024 * 1024) return null;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_MIMES, true)) return null;
    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = uniqid('', true) . '.' . strtolower($ext);
    $dir  = UPLOAD_PATH . $subDir . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) return null;
    return $subDir . '/' . $name;
}

/** Write audit log */
function auditLog(string $action, string $table = '', int $recordId = 0, array $old = [], array $new = []): void {
    try {
        Database::query(
            "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $_SESSION['user_id'] ?? null,
                $action, $table, $recordId ?: null,
                $old ? json_encode($old) : null,
                $new  ? json_encode($new)  : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]
        );
    } catch (Throwable) { /* never break on audit failure */ }
}
