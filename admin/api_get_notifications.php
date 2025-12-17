<?php
require_once '../includes/config.php';
require_admin();

header('Content-Type: application/json');

$userId = $_SESSION['UserID'];

// Handle POST requests (Mark as read)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_all_read') {
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET IsRead = 1 WHERE UserID = ?");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Handle GET requests (Fetch notifications)
try {
    // Get unread count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE UserID = ? AND IsRead = 0");
    $stmt->execute([$userId]);
    $unreadCount = $stmt->fetchColumn();

    // Get latest 10 notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE UserID = ? ORDER BY CreatedAt DESC LIMIT 10");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates nicely
    foreach ($notifications as &$notif) {
        $notif['TimeAgo'] = time_elapsed_string($notif['CreatedAt']);
    }

    echo json_encode([
        'success' => true,
        'unreadCount' => $unreadCount,
        'notifications' => $notifications
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Helper function for "X minutes ago"
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
