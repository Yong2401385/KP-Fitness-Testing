<?php
require_once '../includes/config.php';
require_client();

header('Content-Type: application/json');

if (!isset($_GET['date'])) {
    echo json_encode([]);
    exit;
}

$date = $_GET['date'];
$categoryId = $_GET['category_id'] ?? null;
$difficulty = $_GET['difficulty'] ?? null;

try {
    $sql = "
        SELECT s.SessionID, s.SessionDate, s.Time, a.ClassName as ActivityName, a.MaxCapacity, u.FullName as TrainerName, s.CurrentBookings
        FROM sessions s
        JOIN activities a ON s.ClassID = a.ClassID
        JOIN users u ON s.TrainerID = u.UserID
        WHERE s.SessionDate = :date AND s.Status = 'scheduled'
    ";
    $params = [':date' => $date];

    if (!empty($categoryId)) {
        $sql .= " AND a.CategoryID = :category_id";
        $params[':category_id'] = $categoryId;
    }

    if (!empty($difficulty)) {
        $sql .= " AND a.DifficultyLevel = :difficulty";
        $params[':difficulty'] = $difficulty;
    }

    $sql .= " ORDER BY s.Time";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($sessions);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Database error in get_sessions.php: ' . $e->getMessage());
    echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
}
