<?php
// ============================================================
// ajax/search_patient.php — Live patient search
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireAuth();
header('Content-Type: application/json');

$q     = clean($_GET['q'] ?? '');
$limit = (int)($_GET['limit'] ?? 10);

if (strlen($q) < 2) {
    echo json_encode(['success'=>true,'data'=>[]]);
    exit;
}

$like = "%{$q}%";
$rows = Database::fetchAll("
    SELECT p.id, p.patient_code, p.gender, p.blood_group, p.dob,
           u.full_name, u.phone, u.email
    FROM patients p
    JOIN users u ON u.id = p.user_id
    WHERE u.status = 'active'
      AND (u.full_name LIKE ? OR p.patient_code LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)
    ORDER BY u.full_name
    LIMIT ?
", [$like, $like, $like, $like, $limit]);

echo json_encode(['success'=>true,'data'=>$rows,'count'=>count($rows)]);
