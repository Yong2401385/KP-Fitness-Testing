<?php
require_once '../includes/config.php';
require_client();

header('Content-Type: application/json');

$userId = $_SESSION['UserID'];

try {
    $stmt = $pdo->prepare("
        SELECT Weight, CreatedAt 
        FROM weight_history 
        WHERE UserID = ? 
        ORDER BY CreatedAt ASC
    ");
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For the purpose of this demo, if the user has no weight history, 
    // we will generate some random data.
    if (empty($history)) {
        $history = [
            ['Weight' => 70, 'CreatedAt' => date('Y-m-d', strtotime('-4 week'))],
            ['Weight' => 72, 'CreatedAt' => date('Y-m-d', strtotime('-3 week'))],
            ['Weight' => 71, 'CreatedAt' => date('Y-m-d', strtotime('-2 week'))],
            ['Weight' => 69, 'CreatedAt' => date('Y-m-d', strtotime('-1 week'))],
        ];
    }

    echo json_encode($history);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
