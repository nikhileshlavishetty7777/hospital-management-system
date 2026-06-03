<?php
// ============================================================
// ajax/notifications.php — Notification AJAX handlers
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireAuth();
header('Content-Type: application/json');

$action = clean($_GET['action'] ?? 'list');
$userId = Auth::id();

switch ($action) {

    case 'list':
        $items = Database::fetchAll("
            SELECT id, title, message, type, is_read, link, created_at,
                   CASE
                     WHEN created_at >= NOW() - INTERVAL 1 MINUTE  THEN 'Just now'
                     WHEN created_at >= NOW() - INTERVAL 1 HOUR    THEN CONCAT(TIMESTAMPDIFF(MINUTE, created_at, NOW()), 'm ago')
                     WHEN created_at >= NOW() - INTERVAL 1 DAY     THEN CONCAT(TIMESTAMPDIFF(HOUR,   created_at, NOW()), 'h ago')
                     ELSE DATE_FORMAT(created_at, '%d %b')
                   END AS ago
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 20
        ", [$userId]);

        $unread = Database::fetchOne("SELECT COUNT(*) AS c FROM notifications WHERE user_id=? AND is_read=0", [$userId])['c'];
        echo json_encode(['success'=>true,'items'=>$items,'unread'=>(int)$unread]);
        break;

    case 'read':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) Database::query("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?", [$id, $userId]);
        echo json_encode(['success'=>true]);
        break;

    case 'read_all':
        Database::query("UPDATE notifications SET is_read=1 WHERE user_id=?", [$userId]);
        echo json_encode(['success'=>true]);
        break;

    case 'unread_count':
        $count = Database::fetchOne("SELECT COUNT(*) AS c FROM notifications WHERE user_id=? AND is_read=0", [$userId])['c'];
        echo json_encode(['success'=>true,'count'=>(int)$count]);
        break;

    case 'push':
        // Internal: push a notification to a user (admin only via internal call)
        Auth::requireRole('admin');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['user_id']) || empty($data['title']) || empty($data['message'])) {
            echo json_encode(['success'=>false,'message'=>'Missing required fields.']);
            break;
        }
        Database::query("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)",
            [(int)$data['user_id'], clean($data['title']), clean($data['message']),
             clean($data['type'] ?? 'info'), clean($data['link'] ?? '')]);
        echo json_encode(['success'=>true,'message'=>'Notification sent.']);
        break;

    default:
        echo json_encode(['success'=>false,'message'=>'Unknown action.']);
}
