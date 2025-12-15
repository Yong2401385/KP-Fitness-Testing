<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

$response = ['reply' => "I'm sorry, I don't understand that. Can you please rephrase your question?"];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userMessage = strtolower(trim($_POST['message'] ?? ''));

    if (str_contains($userMessage, 'hello') || str_contains($userMessage, 'hi')) {
        $response['reply'] = "Hello there! How can I assist you today? I can tell you about our membership plans, class schedules, or help you register!";
    } elseif (str_contains($userMessage, 'membership') || str_contains($userMessage, 'price') || str_contains($userMessage, 'cost')) {
        $response['reply'] = "We have several membership options: monthly, yearly, and one-time passes. You can find all the details on our <a href='" . SITE_URL . "/membership.php'>Membership page</a>.";
    } elseif (str_contains($userMessage, 'class') || str_contains($userMessage, 'schedule')) {
        try {
            $stmt = $pdo->query("SELECT Group_CONCAT(DISTINCT cc.CategoryName) as categories, Group_CONCAT(DISTINCT a.ClassName) as activities FROM class_categories cc JOIN activities a ON cc.CategoryID = a.CategoryID");
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $response['reply'] = "We offer a variety of classes in categories like " . $data['categories'] . ". Some of our popular activities include " . $data['activities'] . ". Check out the full schedule and book a class on our <a href='" . SITE_URL . "/client/booking.php'>Class Booking page</a>.";
        } catch (PDOException $e) {
            $response['reply'] = "We have a wide range of classes available. Please check out the full schedule and book a class on our <a href='" . SITE_URL . "/client/booking.php'>Class Booking page</a>.";
        }
    } elseif (str_contains($userMessage, 'register') || str_contains($userMessage, 'sign up') || str_contains($userMessage, 'join')) {
        $response['reply'] = "Great! You can join our fitness community by registering on our <a href='" . SITE_URL . "/register.php'>Registration page</a>.";
    } elseif (str_contains($userMessage, 'location') || str_contains($userMessage, 'address')) {
        $response['reply'] = "We are located at 123, Proto Street, Kota Kinabalu, Sabah.";
    } elseif (str_contains($userMessage, 'contact')) {
        $response['reply'] = "You can reach us by phone (details to be added) or email (details to be added soon!).";
    }
}

echo json_encode($response);
