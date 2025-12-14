<?php
require_once '../includes/config.php';
require_client();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_POST['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'CSRF token is missing.']);
    exit;
}

validate_csrf_token($_POST['csrf_token']);

$action = $_POST['action'] ?? null;
$userId = $_SESSION['UserID'];
$response = [];

try {
    if ($action === 'book') {
        $sessionId = intval($_POST['sessionId']);
        
        // Check for active membership first
        $stmt = $pdo->prepare("SELECT u.MembershipID FROM users u WHERE u.UserID = ?");
        $stmt->execute([$userId]);
        $userMembership = $stmt->fetch();

        if (!$userMembership['MembershipID']) {
            $response = ['success' => false, 'message' => 'You need an active membership to book classes.'];
        } else {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT CurrentBookings, (SELECT MaxCapacity FROM classes c WHERE c.ClassID = s.ClassID) as MaxCapacity FROM sessions s WHERE s.SessionID = ? FOR UPDATE");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();

            if ($session['CurrentBookings'] >= $session['MaxCapacity']) {
                $response = ['success' => false, 'message' => 'This class is already full.'];
            } else {
                $stmt = $pdo->prepare("SELECT ReservationID FROM reservations WHERE UserID = ? AND SessionID = ? AND Status = 'booked'");
                $stmt->execute([$userId, $sessionId]);
                if ($stmt->fetch()) {
                    $response = ['success' => false, 'message' => 'You have already booked this session.'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO reservations (UserID, SessionID, Status) VALUES (?, ?, 'booked')");
                    $stmt->execute([$userId, $sessionId]);
                    $stmt = $pdo->prepare("UPDATE sessions SET CurrentBookings = CurrentBookings + 1 WHERE SessionID = ?");
                    $stmt->execute([$sessionId]);
                    $pdo->commit();
                    $response = ['success' => true, 'message' => 'Class booked successfully!'];
                }
            }
        }
    } elseif ($action === 'cancel') {
        $reservationId = intval($_POST['reservationId']);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT SessionID FROM reservations WHERE ReservationID = ? AND UserID = ? AND Status = 'booked'");
        $stmt->execute([$reservationId, $userId]);
        $reservation = $stmt->fetch();
        
        if ($reservation) {
            $stmt = $pdo->prepare("UPDATE reservations SET Status = 'cancelled' WHERE ReservationID = ?");
            $stmt->execute([$reservationId]);
            $stmt = $pdo->prepare("UPDATE sessions SET CurrentBookings = GREATEST(0, CurrentBookings - 1) WHERE SessionID = ?");
            $stmt->execute([$reservation['SessionID']]);
            $pdo->commit();
            $response = ['success' => true, 'message' => 'Your booking has been cancelled.'];
        } else {
            $response = ['success' => false, 'message' => 'Could not find the booking to cancel.'];
        }
    } else {
        http_response_code(400);
        $response = ['success' => false, 'message' => 'Invalid action.'];
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    $response = ['success' => false, 'message' => 'A database error occurred: ' . $e->getMessage()];
}

echo json_encode($response);
