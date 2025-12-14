<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_GET['date'])) {
    echo json_encode([]);
    exit;
}

$date = $_GET['date'];

try {
    $stmt = $pdo->prepare("
        SELECT s.SessionID, s.SessionDate, s.Time, c.ClassName, c.MaxCapacity, u.FullName as TrainerName, s.CurrentBookings
        FROM sessions s
        JOIN classes c ON s.ClassID = c.ClassID
        JOIN users u ON s.TrainerID = u.UserID
        WHERE s.SessionDate = ? AND s.Status = 'scheduled'
        ORDER BY s.Time
    ");
    $stmt->execute([$date]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($sessions);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
