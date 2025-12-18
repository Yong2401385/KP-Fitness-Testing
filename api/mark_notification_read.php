<?php
require_once '../includes/config.php';
// Allow any logged-in user (Client, Trainer, Admin) to mark their own notifications
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$notificationId = $data['id'] ?? null;
$userId = $_SESSION['UserID'];

if (!validate_csrf_token($data['csrf_token'] ?? null, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF validation failed']);
    exit;
}

if (!$notificationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE notifications SET IsRead = 1 WHERE NotificationID = ? AND UserID = ?");
    $stmt->execute([$notificationId, $userId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error marking notification read: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
