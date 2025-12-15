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
        $repeatWeekly = isset($_POST['repeat_weekly']) && $_POST['repeat_weekly'] == 'true';
        
        // Check for active membership first
        $stmt = $pdo->prepare("SELECT u.MembershipID, m.PlanName FROM users u LEFT JOIN membership m ON u.MembershipID = m.MembershipID WHERE u.UserID = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        $isPremiumMember = $user && in_array($user['PlanName'], ['Annual Class Membership', 'Unlimited Class Membership']);
        $isMember = $user && $user['MembershipID'] && $user['PlanName'] !== 'Non-Member';
        $paymentConfirmed = isset($_POST['payment_confirmed']) && $_POST['payment_confirmed'] == 'true';

        if ($repeatWeekly && !$isPremiumMember) {
            $response = ['success' => false, 'message' => 'Weekly repeat bookings are only available for premium members.'];
            echo json_encode($response);
            exit;
        }

        // Get details of the session being booked
        $stmt = $pdo->prepare("SELECT CONCAT(s.SessionDate, ' ', s.Time) as StartTime, a.Duration, a.Price, s.ClassID, s.Time FROM sessions s JOIN activities a ON s.ClassID = a.ClassID WHERE s.SessionID = ?");
        $stmt->execute([$sessionId]);
        $newSession = $stmt->fetch();

        if (!$newSession) {
            $response = ['success' => false, 'message' => 'Session not found.'];
            echo json_encode($response);
            exit;
        }

        $newSessionStart = new DateTime($newSession['StartTime']);
        $newSessionEnd = (clone $newSessionStart)->add(new DateInterval('PT' . $newSession['Duration'] . 'M'));
        
        // Time collision check
        $stmt = $pdo->prepare("
            SELECT CONCAT(s.SessionDate, ' ', s.Time) as StartTime, a.Duration 
            FROM reservations r
            JOIN sessions s ON r.SessionID = s.SessionID
            JOIN activities a ON s.ClassID = a.ClassID
            WHERE r.UserID = ? AND r.Status = 'booked'
        ");
        $stmt->execute([$userId]);
        $existingReservations = $stmt->fetchAll();

        foreach ($existingReservations as $res) {
            $existingStart = new DateTime($res['StartTime']);
            $existingEnd = (clone $existingStart)->add(new DateInterval('PT' . $res['Duration'] . 'M'));

            // Check for overlap
            if ($newSessionStart < $existingEnd && $newSessionEnd > $existingStart) {
                $response = ['success' => false, 'message' => 'Booking failed. This class has a time conflict with another one of your booked classes.'];
                echo json_encode($response);
                exit;
            }
        }
        
        if (!$isMember && !$paymentConfirmed) {
            $response = [
                'success' => false, 
                'payment_required' => true, 
                'message' => 'As a non-member, you must pay for this class.',
                'price' => $newSession['Price']
            ];
        } else {
            $pdo->beginTransaction();

            // Handle payment for non-members
            if (!$isMember && $paymentConfirmed) {
                $stmt = $pdo->prepare("INSERT INTO payments (UserID, Amount, Status, MembershipID) VALUES (?, ?, 'completed', 4)");
                $stmt->execute([$userId, $newSession['Price']]);
            }

            // The actual booking logic, wrapped in a function to be reused for recurring bookings
            function createBooking($pdo, $userId, $sessionId, $isRecurring, $recurrenceId, $parentReservationId, $paidAmount) {
                $stmt = $pdo->prepare("SELECT CurrentBookings, (SELECT MaxCapacity FROM activities a WHERE a.ClassID = s.ClassID) as MaxCapacity FROM sessions s WHERE s.SessionID = ? FOR UPDATE");
                $stmt->execute([$sessionId]);
                $session = $stmt->fetch();

                if ($session['CurrentBookings'] >= $session['MaxCapacity']) {
                    throw new Exception('This class is already full.');
                }

                $stmt = $pdo->prepare("SELECT ReservationID FROM reservations WHERE UserID = ? AND SessionID = ? AND Status = 'booked'");
                $stmt->execute([$userId, $sessionId]);
                if ($stmt->fetch()) {
                    throw new Exception('You have already booked this session.');
                }

                $stmt = $pdo->prepare("INSERT INTO reservations (UserID, SessionID, Status, PaidAmount, is_recurring, recurrence_id, parent_reservation_id) VALUES (?, ?, 'booked', ?, ?, ?, ?)");
                $stmt->execute([$userId, $sessionId, $paidAmount, $isRecurring, $recurrenceId, $parentReservationId]);
                $newReservationId = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("UPDATE sessions SET CurrentBookings = CurrentBookings + 1 WHERE SessionID = ?");
                $stmt->execute([$sessionId]);

                return $newReservationId;
            }

            try {
                $paidAmount = !$isMember ? $newSession['Price'] : null;
                $recurrenceId = $repeatWeekly ? uniqid('recur_') : null;
                
                $parentReservationId = createBooking($pdo, $userId, $sessionId, $repeatWeekly, $recurrenceId, null, $paidAmount);
                $response['message'] = 'Class booked successfully!';

                if ($repeatWeekly) {
                    $response['message'] .= ' Weekly repeat booking for 2 weeks created.';
                    $originalSessionDate = new DateTime($newSessionStart->format('Y-m-d'));

                    for ($i = 1; $i <= 2; $i++) {
                        $nextDate = (clone $originalSessionDate)->add(new DateInterval("P{$i}W"));
                        
                        $stmt = $pdo->prepare("SELECT SessionID FROM sessions WHERE ClassID = ? AND SessionDate = ? AND Time = ? AND Status = 'scheduled'");
                        $stmt->execute([$newSession['ClassID'], $nextDate->format('Y-m-d'), $newSession['Time']]);
                        $nextSession = $stmt->fetch();

                        if ($nextSession) {
                            try {
                                createBooking($pdo, $userId, $nextSession['SessionID'], true, $recurrenceId, $parentReservationId, $paidAmount);
                            } catch (Exception $e) {
                                // Ignore if a single recurring booking fails (e.g., full), but don't stop the whole process.
                            }
                        }
                    }
                }
                
                $pdo->commit();
                $response['success'] = true;

            } catch (Exception $e) {
                $pdo->rollBack();
                $response = ['success' => false, 'message' => $e->getMessage()];
            }
        }
    } elseif ($action === 'cancel') {
        $reservationId = intval($_POST['reservationId']);
        $cancelScope = $_POST['cancel_scope'] ?? 'one'; // 'one' or 'all'

        $pdo->beginTransaction();

        // Get reservation details
        $stmt = $pdo->prepare("SELECT SessionID, recurrence_id, is_recurring FROM reservations WHERE ReservationID = ? AND UserID = ? AND Status = 'booked'");
        $stmt->execute([$reservationId, $userId]);
        $reservation = $stmt->fetch();
        
        if ($reservation) {
            if ($reservation['is_recurring'] && $cancelScope === 'all') {
                // Cancel all future bookings in the series
                $stmt = $pdo->prepare(
                    "SELECT r.ReservationID, r.SessionID 
                     FROM reservations r
                     JOIN sessions s ON r.SessionID = s.SessionID
                     WHERE r.recurrence_id = ? AND r.UserID = ? AND r.Status = 'booked' AND CONCAT(s.SessionDate, ' ', s.Time) >= CURDATE()"
                );
                $stmt->execute([$reservation['recurrence_id'], $userId]);
                $reservationsToCancel = $stmt->fetchAll();

                foreach ($reservationsToCancel as $res) {
                    $stmt = $pdo->prepare("UPDATE reservations SET Status = 'cancelled' WHERE ReservationID = ?");
                    $stmt->execute([$res['ReservationID']]);
                    $stmt = $pdo->prepare("UPDATE sessions SET CurrentBookings = GREATEST(0, CurrentBookings - 1) WHERE SessionID = ?");
                    $stmt->execute([$res['SessionID']]);
                }
                $response = ['success' => true, 'message' => 'The recurring booking series has been cancelled.'];

            } else {
                // Cancel a single booking
                $stmt = $pdo->prepare("UPDATE reservations SET Status = 'cancelled' WHERE ReservationID = ?");
                $stmt->execute([$reservationId]);
                $stmt = $pdo->prepare("UPDATE sessions SET CurrentBookings = GREATEST(0, CurrentBookings - 1) WHERE SessionID = ?");
                $stmt->execute([$reservation['SessionID']]);
                $response = ['success' => true, 'message' => 'Your booking has been cancelled.'];
            }
            $pdo->commit();
        } else {
            $pdo->rollBack();
            $response = ['success' => false, 'message' => 'Could not find the booking to cancel.'];
        }
    } elseif ($action === 'rate') {
        $reservationId = intval($_POST['reservationId']);
        $ratingScore = intval($_POST['ratingScore']);
        $comment = sanitize_input($_POST['comment'] ?? '');

        if ($ratingScore < 1 || $ratingScore > 5) {
            $response = ['success' => false, 'message' => 'Invalid rating score. Please provide a rating between 1 and 5.'];
            echo json_encode($response);
            exit;
        }

        $pdo->beginTransaction();

        // Get reservation details
        $stmt = $pdo->prepare(
            "SELECT r.UserID, s.TrainerID 
             FROM reservations r
             JOIN sessions s ON r.SessionID = s.SessionID
             WHERE r.ReservationID = ? AND r.UserID = ? AND r.Status = 'Done'"
        );
        $stmt->execute([$reservationId, $userId]);
        $reservation = $stmt->fetch();

        if ($reservation) {
            // Insert into ratings table
            $stmt = $pdo->prepare("INSERT INTO ratings (ReservationID, UserID, TrainerID, RatingScore, Comment) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$reservationId, $userId, $reservation['TrainerID'], $ratingScore, $comment]);

            // Update reservation status to 'Rated'
            $stmt = $pdo->prepare("UPDATE reservations SET Status = 'Rated' WHERE ReservationID = ?");
            $stmt->execute([$reservationId]);
            
            $pdo->commit();
            $response = ['success' => true, 'message' => 'Thank you for your feedback!'];
        } else {
            $pdo->rollBack();
            $response = ['success' => false, 'message' => 'You can only rate completed classes.'];
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
