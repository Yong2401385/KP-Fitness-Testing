<?php
require_once '../includes/config.php';
require_client(); // Ensure only clients can access this handler

header('Content-Type: application/json');

$response = ['reply' => "I'm sorry, I don't understand that. Can you please rephrase your question?"];
$userId = $_SESSION['UserID'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userMessage = strtolower(trim($_POST['message'] ?? ''));

    // --- General Greetings and Info ---
    if (str_contains($userMessage, 'hello') || str_contains($userMessage, 'hi')) {
        $response['reply'] = "Hello, " . htmlspecialchars(explode(' ', $_SESSION['FullName'])[0]) . "! How can I help you today? I can tell you about your bookings, membership, or workout plans.";
    } 
    // --- User-specific Queries ---
    elseif (str_contains($userMessage, 'my next class') || str_contains($userMessage, 'upcoming booking')) {
        try {
            $stmt = $pdo->prepare("
                SELECT s.SessionDate, s.Time, c.ClassName 
                FROM reservations r
                JOIN sessions s ON r.SessionID = s.SessionID
                JOIN classes c ON s.ClassID = c.ClassID
                WHERE r.UserID = ? AND r.Status = 'booked' AND s.SessionDate >= CURDATE()
                ORDER BY s.SessionDate, s.Time
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $nextBooking = $stmt->fetch();

            if ($nextBooking) {
                $response['reply'] = "Your next class is " . htmlspecialchars($nextBooking['ClassName']) . " on " . format_date($nextBooking['SessionDate']) . " at " . format_time($nextBooking['Time']) . ".";
            } else {
                $response['reply'] = "You don't have any upcoming classes booked. Why not check out our <a href='" . SITE_URL . "/client/booking.php'>Class Booking page</a>?";
            }
        } catch (PDOException $e) {
            $response['reply'] = "Sorry, I had trouble fetching your next class: " . $e->getMessage();
        }
    } elseif (str_contains($userMessage, 'my membership') || str_contains($userMessage, 'membership status') || str_contains($userMessage, 'expire')) {
        try {
            $stmt = $pdo->prepare("
                SELECT m.Type, p.PaymentDate, m.Duration
                FROM users u
                JOIN membership m ON u.MembershipID = m.MembershipID
                LEFT JOIN payments p ON p.UserID = u.UserID AND p.MembershipID = m.MembershipID
                WHERE u.UserID = ? AND p.Status = 'completed'
                ORDER BY p.PaymentDate DESC LIMIT 1
            ");
            $stmt->execute([$userId]);
            $membership = $stmt->fetch();

            if ($membership) {
                $expiryDate = date('Y-m-d', strtotime($membership['PaymentDate'] . ' + ' . $membership['Duration'] . ' days'));
                $response['reply'] = "You have an active " . htmlspecialchars($membership['Type']) . " membership. It is valid until " . format_date($expiryDate) . ".";
            } else {
                $response['reply'] = "You do not currently have an active membership. You can purchase one on the <a href='" . SITE_URL . "/client/membership.php'>Membership page</a>.";
            }
        } catch (PDOException $e) {
            $response['reply'] = "Sorry, I had trouble fetching your membership details: " . $e->getMessage();
        }
    } elseif (str_contains($userMessage, 'workout plan') || str_contains($userMessage, 'my plan')) {
        try {
            $stmt = $pdo->prepare("SELECT PlanName FROM workout_plans WHERE UserID = ? ORDER BY CreatedAt DESC LIMIT 1");
            $stmt->execute([$userId]);
            $latestPlan = $stmt->fetchColumn();

            if ($latestPlan) {
                $response['reply'] = "Your latest workout plan is '" . htmlspecialchars($latestPlan) . "'. You can view or generate new plans on the <a href='" . SITE_URL . "/client/workout_planner.php'>AI Workout Planner page</a>.";
            } else {
                $response['reply'] = "You don't have any saved workout plans. Let's create one for you on the <a href='" . SITE_URL . "/client/workout_planner.php'>AI Workout Planner page</a>!";
            }
        } catch (PDOException $e) {
            $response['reply'] = "Sorry, I had trouble fetching your workout plan: " . $e->getMessage();
        }
    } elseif (str_contains($userMessage, 'change password') || str_contains($userMessage, 'update profile') || str_contains($userMessage, 'my details')) {
        $response['reply'] = "You can update your personal details or change your password on your <a href='" . SITE_URL . "/client/profile.php'>Profile page</a>.";
    } 
    // --- General Fallback ---
    else {
        // Default message if no specific intent is matched
        $response['reply'] = "Hello " . htmlspecialchars(explode(' ', $_SESSION['FullName'])[0]) . "! I can help with questions about your bookings, membership, or workout plans.";
    }
}

echo json_encode($response);
