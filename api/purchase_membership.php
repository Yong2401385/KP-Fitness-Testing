<?php
require_once '../includes/config.php';
require_client();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_POST['csrf_token']) || !isset($_POST['membershipId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'CSRF token or membership ID is missing.']);
    exit;
}

validate_csrf_token($_POST['csrf_token']);

$userId = $_SESSION['UserID'];
$membershipId = intval($_POST['membershipId']);
$response = [];

try {
    $stmt = $pdo->prepare("SELECT * FROM membership WHERE MembershipID = ?");
    $stmt->execute([$membershipId]);
    $membership = $stmt->fetch();

    if ($membership) {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO payments (UserID, MembershipID, Amount, PaymentMethod, Status) VALUES (?, ?, ?, 'credit_card', 'completed')");
        $stmt->execute([$userId, $membershipId, $membership['Cost']]);
        
        $stmt = $pdo->prepare("UPDATE users SET MembershipID = ? WHERE UserID = ?");
        $stmt->execute([$membershipId, $userId]);
        
        $pdo->commit();
        $response = ['success' => true, 'message' => 'Membership purchased successfully! You can now book classes.'];
    } else {
        $response = ['success' => false, 'message' => 'Invalid membership plan selected.'];
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    $response = ['success' => false, 'message' => 'A database error occurred: ' . $e->getMessage()];
}

echo json_encode($response);
