<?php
require_once '../includes/config.php';
require_client(); // Ensure only clients can access this handler

header('Content-Type: application/json');

$userId = $_SESSION['UserID'];

// --- Helper function to call Ollama API ---
function get_ollama_response($prompt) {
    $data = [
        'model' => 'llama3.1:8b', // User's specified Ollama model
        'prompt' => $prompt,
        'stream' => false
    ];

    $ch = curl_init('http://localhost:11434/api/generate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response_json = curl_exec($ch);

    if (curl_errno($ch)) {
        // Handle cURL error
        $error_msg = curl_error($ch);
        curl_close($ch);
        return "Sorry, I'm having trouble connecting to my brain. Please try again later. (Error: " . $error_msg . ")";
    }

    curl_close($ch);

    $response_data = json_decode($response_json, true);
    return $response_data['response'] ?? "Sorry, I received an empty response from my brain. Please try again.";
}

// --- Main Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userMessage = trim($_POST['message'] ?? '');
    $context_info = '';

    // 1. Fetch context from the database
    try {
        // Get next booking
        $stmt_booking = $pdo->prepare("SELECT s.SessionDate, s.Time, c.ClassName FROM reservations r JOIN sessions s ON r.SessionID = s.SessionID JOIN classes c ON s.ClassID = c.ClassID WHERE r.UserID = ? AND r.Status = 'booked' AND s.SessionDate >= CURDATE() ORDER BY s.SessionDate, s.Time LIMIT 1");
        $stmt_booking->execute([$userId]);
        $nextBooking = $stmt_booking->fetch();
        
        // Get membership
        $stmt_membership = $pdo->prepare("SELECT m.Type, p.PaymentDate, m.Duration FROM users u JOIN membership m ON u.MembershipID = m.MembershipID LEFT JOIN payments p ON p.UserID = u.UserID AND p.MembershipID = m.MembershipID WHERE u.UserID = ? AND p.Status = 'completed' ORDER BY p.PaymentDate DESC LIMIT 1");
        $stmt_membership->execute([$userId]);
        $membership = $stmt_membership->fetch();

        // Get latest workout plan
        $stmt_plan = $pdo->prepare("SELECT PlanName FROM workout_plans WHERE UserID = ? ORDER BY CreatedAt DESC LIMIT 1");
        $stmt_plan->execute([$userId]);
        $latestPlan = $stmt_plan->fetchColumn();

        // Build context string
        $context_info .= "Their next class is: " . ($nextBooking ? htmlspecialchars($nextBooking['ClassName']) . " on " . format_date($nextBooking['SessionDate']) : "None") . ". ";
        if ($membership) {
            $expiryDate = date('Y-m-d', strtotime($membership['PaymentDate'] . ' + ' . $membership['Duration'] . ' days'));
            $context_info .= "Their membership is: " . htmlspecialchars($membership['Type']) . " and it is valid until " . format_date($expiryDate) . ". ";
        } else {
            $context_info .= "They do not have an active membership. ";
        }
        $context_info .= "Their latest workout plan is: '" . ($latestPlan ? htmlspecialchars($latestPlan) : "None") . "'. ";

    } catch (PDOException $e) {
        // If database fails, the AI won't have context, which is acceptable.
        $context_info = "Could not fetch user data from the database.";
    }

    // 2. Construct the Master Prompt
    $prompt = "You are a helpful and friendly fitness assistant for a gym named KP Fitness.
    The user's name is " . htmlspecialchars($_SESSION['FullName']) . ".
    Today's date is " . date("F j, Y") . ".

    Here is some context about the user from our database:
    - " . $context_info . "

    Here are links to important pages:
    - Booking page: " . SITE_URL . "/client/booking.php
    - Membership page: " . SITE_URL . "/client/membership.php
    - Workout planner page: " . SITE_URL . "/client/workout_planner.php
    - Profile page: " . SITE_URL . "/client/profile.php

    Based on this context, please answer the user's question in a conversational and helpful way.
    If the question is unrelated to fitness, the gym, or the user's data, politely say that you cannot help with that.
    If the user asks for information you don't have, guide them to the correct page using the links provided.
    Keep your answers concise and friendly.

    User's Question: \"" . $userMessage . "\"";

    // 3. Get the AI's response
    $ai_reply = get_ollama_response($prompt);

    // 4. Send the response
    $response = ['reply' => $ai_reply];
    echo json_encode($response);

} else {
    // Handle non-POST requests if necessary
    echo json_encode(['reply' => 'Invalid request method.']);
}