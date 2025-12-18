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

if (!validate_csrf_token($_POST['csrf_token'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF validation failed.']);
    exit;
}

// Helper function defined at the top scope
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

    // Ensure is_recurring is integer 0 or 1
    $isRecurringInt = $isRecurring ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO reservations (UserID, SessionID, Status, PaidAmount, is_recurring, recurrence_id, parent_reservation_id) VALUES (?, ?, 'booked', ?, ?, ?, ?)");
    $stmt->execute([$userId, $sessionId, $paidAmount, $isRecurringInt, $recurrenceId, $parentReservationId]);
    $newReservationId = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("UPDATE sessions SET CurrentBookings = CurrentBookings + 1 WHERE SessionID = ?");
    $stmt->execute([$sessionId]);

    return $newReservationId;
}

$action = $_POST['action'] ?? null;
$userId = $_SESSION['UserID'];
$response = [];

try {
    if ($action === 'book') {
        $sessionId = intval($_POST['sessionId']);
        $repeatWeekly = isset($_POST['repeat_weekly']) && $_POST['repeat_weekly'] === 'true';
        
        // Check for active membership first
        $stmt = $pdo->prepare("SELECT u.MembershipID, m.PlanName FROM users u LEFT JOIN membership m ON u.MembershipID = m.MembershipID WHERE u.UserID = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        $isPremiumMember = $user && in_array($user['PlanName'], ['Annual Class Membership', 'Unlimited Class Membership']);
        $isMember = $user && $user['MembershipID'] && $user['PlanName'] !== 'Non-Member';
        
        // Logic for 8 Class Membership Limit
        if ($isMember && stripos($user['PlanName'], '8 Class') !== false) {
            $stmt = $pdo->prepare("SELECT MembershipStartDate, MembershipEndDate FROM users WHERE UserID = ?");
            $stmt->execute([$userId]);
            $dates = $stmt->fetch();
            
            if ($dates) {
                $stmt = $pdo->prepare(" 
                    SELECT COUNT(*) 
                    FROM reservations r 
                    JOIN sessions s ON r.SessionID = s.SessionID 
                    WHERE r.UserID = ? 
                    AND r.Status IN ('booked', 'attended', 'Done', 'Rated')
                    AND s.SessionDate BETWEEN ? AND ?
                ");
                $stmt->execute([$userId, $dates['MembershipStartDate'], $dates['MembershipEndDate']]);
                $used = $stmt->fetchColumn();
                
                if ($used >= 8) {
                    $isMember = false; // Force payment
                }
            }
        }

        $paymentConfirmed = isset($_POST['payment_confirmed']) && $_POST['payment_confirmed'] === 'true';

        if ($repeatWeekly && !$isPremiumMember) {
            $response = ['success' => false, 'message' => 'Weekly repeat bookings are only available for premium members.'];
            echo json_encode($response);
            exit;
        }

    // Get session details
    $stmt = $pdo->prepare("SELECT CONCAT(s.SessionDate, ' ', s.StartTime) as StartTime, a.Duration, a.Price, s.ClassID, s.StartTime, s.TrainerID, a.ClassName FROM sessions s JOIN activities a ON s.ClassID = a.ClassID WHERE s.SessionID = ?");
        $stmt->execute([$sessionId]);
        $newSession = $stmt->fetch();

        if (!$newSession) {
            $response = ['success' => false, 'message' => 'Session not found.'];
            echo json_encode($response);
            exit;
        }

        $newSessionStart = new DateTime($newSession['StartTime']);
        $newSessionEnd = (clone $newSessionStart)->add(new DateInterval('PT' . $newSession['Duration'] . 'M'));

        // Prevent booking past sessions
        $now = new DateTime();
        if ($newSessionStart < $now) {
            $response = ['success' => false, 'message' => 'Cannot book a session that has already started or passed.'];
            echo json_encode($response);
            exit;
        }
        
        // Time collision check
        $stmt = $pdo->prepare(" 
            SELECT CONCAT(s.SessionDate, ' ', s.StartTime) as StartTime, a.Duration 
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
                $description = "Class Booking: " . $newSession['ClassName'];
                $stmt = $pdo->prepare("INSERT INTO payments (UserID, Amount, Status, MembershipID, PaymentType, Description) VALUES (?, ?, 'completed', 4, 'Booking', ?)");
                $stmt->execute([$userId, $newSession['Price'], $description]);
            }

            try {
                $paidAmount = !$isMember ? $newSession['Price'] : null;
                $recurrenceId = $repeatWeekly ? uniqid('recur_') : null;
                
                $parentReservationId = createBooking($pdo, $userId, $sessionId, $repeatWeekly, $recurrenceId, null, $paidAmount);
                $response['message'] = 'Class booked successfully!';
                
                // Notification for initial booking
                $dateStr = format_date($newSessionStart->format('Y-m-d'));
                $timeStr = format_time($newSession['StartTime']);
                create_notification($userId, 'Class Booked', "You have successfully booked {$newSession['ClassName']} on {$dateStr} at {$timeStr}.", 'success');

                // Notify Trainer
                $stmt = $pdo->prepare("SELECT FullName FROM users WHERE UserID = ?");
                $stmt->execute([$userId]);
                $clientName = $stmt->fetchColumn();
                create_notification($newSession['TrainerID'], 'New Booking', "$clientName has booked your {$newSession['ClassName']} class on {$dateStr} at {$timeStr}.", 'success');

                if ($repeatWeekly) {
                    $response['message'] .= ' Weekly repeat booking for 2 weeks created.';
                    $originalSessionDate = new DateTime($newSessionStart->format('Y-m-d'));
                    
                    // Notification for recurrence
                    create_notification($userId, 'Recurring Booking', "Recurring booking enabled for {$newSession['ClassName']} for the next 2 weeks.", 'success');

                    for ($i = 1; $i <= 2; $i++) {
                        $nextDate = (clone $originalSessionDate)->add(new DateInterval("P{$i}W"));
                        
                        // Duplicate check for this specific date
                        $stmt = $pdo->prepare("SELECT SessionID FROM sessions WHERE ClassID = ? AND SessionDate = ? AND StartTime = ? AND Status = 'scheduled'");
                        $stmt->execute([$newSession['ClassID'], $nextDate->format('Y-m-d'), $newSession['StartTime']]);
                        $recurringSession = $stmt->fetch();

                        if ($recurringSession) {
                            try {
                                createBooking($pdo, $userId, $recurringSession['SessionID'], true, $recurrenceId, $parentReservationId, $paidAmount);
                            } catch (Exception $e) {
                                // Ignore if a single recurring booking fails (e.g., full), but don't stop the whole process.
                            }
                        }
                    }
                }
                
                $pdo->commit();
                $response['success'] = true;

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('Database error in booking_handler.php (booking transaction): ' . $e->getMessage());
                $response = ['success' => false, 'message' => 'An internal error occurred while processing your booking.'];
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
    $stmt = $pdo->prepare("SELECT r.ReservationID, r.SessionID, r.PaidAmount, CONCAT(s.SessionDate, ' ', s.StartTime) as SessionStart, a.ClassName, s.TrainerID, r.is_recurring, r.recurrence_id 
                           FROM reservations r 
                           JOIN sessions s ON r.SessionID = s.SessionID 
                           JOIN activities a ON s.ClassID = a.ClassID 
                           WHERE r.ReservationID = ? AND r.UserID = ?");
        $stmt->execute([$reservationId, $userId]);
        $reservation = $stmt->fetch();
        
        if ($reservation) {
            $reservationsToCancel = [];
            if ($reservation['is_recurring'] && $cancelScope === 'all') {
                        $stmt = $pdo->prepare("SELECT r.ReservationID, r.SessionID, r.PaidAmount, CONCAT(s.SessionDate, ' ', s.StartTime) as SessionStart, a.ClassName, s.TrainerID 
                                               FROM reservations r 
                                               JOIN sessions s ON r.SessionID = s.SessionID 
                                               JOIN activities a ON s.ClassID = a.ClassID 
                                               WHERE r.recurrence_id = ? AND r.UserID = ? AND r.Status = 'booked' AND CONCAT(s.SessionDate, ' ', s.StartTime) >= CURDATE()");
                $stmt->execute([$reservation['recurrence_id'], $userId]);
                $reservationsToCancel = $stmt->fetchAll();
            } else {
                $reservationsToCancel[] = $reservation;
            }

            $refundCount = 0;
            $noRefundCount = 0;
            
            // Get Client Name once
            $stmt = $pdo->prepare("SELECT FullName FROM users WHERE UserID = ?");
            $stmt->execute([$userId]);
            $clientName = $stmt->fetchColumn();

            foreach ($reservationsToCancel as $res) {
                // Check for Refund Eligibility (48 hours)
                $shouldRefund = false;
                if ($res['PaidAmount'] > 0) {
                    $sessionStart = new DateTime($res['SessionStart']);
                    $now = new DateTime();
                    $diff = $now->diff($sessionStart); // Interval
                    
                    // Convert to total hours
                    $hours = ($diff->days * 24) + $diff->h;
                    if ($diff->invert) $hours = 0; // Already passed

                    if ($hours >= 48) {
                        $shouldRefund = true;
                        // Process Refund Record
                        $desc = "Refund for " . $res['ClassName'];
                        $stmt = $pdo->prepare("INSERT INTO payments (UserID, Amount, Status, MembershipID, PaymentType, Description) VALUES (?, ?, 'refunded', 4, 'Booking', ?)");
                        $stmt->execute([$userId, $res['PaidAmount'], $desc]);
                        $refundCount++;
                    } else {
                        $noRefundCount++;
                    }
                }

                $stmt = $pdo->prepare("UPDATE reservations SET Status = 'cancelled' WHERE ReservationID = ?");
                $stmt->execute([$res['ReservationID']]);
                $stmt = $pdo->prepare("UPDATE sessions SET CurrentBookings = GREATEST(0, CurrentBookings - 1) WHERE SessionID = ?");
                $stmt->execute([$res['SessionID']]);
                
                // Notify Trainer
                $classTime = format_date(date('Y-m-d', strtotime($res['SessionStart']))) . ' at ' . format_time(date('H:i:s', strtotime($res['SessionStart'])));
                create_notification($res['TrainerID'], 'Booking Cancelled', "$clientName has cancelled their booking for {$res['ClassName']} on $classTime.", 'warning');
            }
            
            $msg = 'Booking cancelled.';
            if ($refundCount > 0) {
                $msg .= " Refund processed for $refundCount class(es).";
            }
            if ($noRefundCount > 0) {
                $msg .= " No refund for $noRefundCount class(es) (less than 48h notice).";
            }
            
            create_notification($userId, 'Booking Cancelled', $msg, 'info');
            
            $pdo->commit();
            $response = ['success' => true, 'message' => $msg];

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
            "SELECT r.UserID, s.TrainerID, a.ClassName
             FROM reservations r
             JOIN sessions s ON r.SessionID = s.SessionID
             JOIN activities a ON s.ClassID = a.ClassID
             WHERE r.ReservationID = ? AND r.UserID = ? AND r.Status = 'attended'"
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
            
            // Notify Trainer
            $stmt = $pdo->prepare("SELECT FullName FROM users WHERE UserID = ?");
            $stmt->execute([$userId]);
            $clientName = $stmt->fetchColumn();
            
            $ratingMsg = "$clientName rated your {$reservation['ClassName']} class $ratingScore/5 stars.";
            if (!empty($comment)) {
                $ratingMsg .= " Comment: \"$comment\"";
            }
            create_notification($reservation['TrainerID'], 'New Rating', $ratingMsg, 'success');
            
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
    error_log('Database error in booking_handler.php: ' . $e->getMessage());
    $response = ['success' => false, 'message' => 'An internal error occurred. Please try again later.'];
}

echo json_encode($response);