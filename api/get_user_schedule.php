<?php
require_once '../includes/config.php';
require_client();

header('Content-Type: application/json');

if (!isset($_GET['start_date']) || !isset($_GET['end_date'])) {
    echo json_encode(['error' => 'Missing date range']);
    exit;
}

$userId = $_SESSION['UserID'];
$startDate = $_GET['start_date'];
$endDate = $_GET['end_date'];

try {
    $stmt = $pdo->prepare("
        SELECT r.ReservationID, s.SessionDate, s.StartTime, s.Room, a.ClassName as ActivityName, a.Description, c.CategoryName, a.DifficultyLevel, u.FullName as TrainerName
        FROM reservations r
        JOIN sessions s ON r.SessionID = s.SessionID
        JOIN activities a ON s.ClassID = a.ClassID
        JOIN class_categories c ON a.CategoryID = c.CategoryID
        JOIN users u ON s.TrainerID = u.UserID
        WHERE r.UserID = ? 
        AND r.Status = 'booked' 
        AND s.SessionDate BETWEEN ? AND ?
        ORDER BY s.SessionDate ASC, s.StartTime ASC
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($schedule);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('Database error in get_user_schedule.php: ' . $e->getMessage());
    echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
}
?>