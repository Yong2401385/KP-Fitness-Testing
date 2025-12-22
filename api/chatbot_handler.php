<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

$response = ['reply' => "I'm sorry, I don't understand that. Can you please rephrase your question?"];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userMessage = strtolower(trim($_POST['message'] ?? ''));

    if (str_contains($userMessage, 'membership') || str_contains($userMessage, 'price') || str_contains($userMessage, 'cost')) {
        $response['reply'] = "We have several membership options: monthly, yearly, and one-time passes. You can find all the details on our <a href='" . SITE_URL . "/index.php#pricing'>Membership Plans section</a>.";
    } elseif (str_contains($userMessage, 'class') || str_contains($userMessage, 'schedule') || str_contains($userMessage, 'workout') || str_contains($userMessage, 'train')) {
        try {
            $stmt = $pdo->query("SELECT Group_CONCAT(DISTINCT cc.CategoryName) as categories, Group_CONCAT(DISTINCT a.ClassName) as activities FROM class_categories cc JOIN activities a ON cc.CategoryID = a.CategoryID");
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $response['reply'] = "We offer a variety of classes and workouts in categories like " . $data['categories'] . ". Some of our popular activities include " . $data['activities'] . ". Check out the full schedule on our <a href='" . SITE_URL . "/index.php#classes'>Classes section</a>.";
        } catch (PDOException $e) {
            $response['reply'] = "We have a wide range of classes and training sessions available. Please check out the full schedule on our <a href='" . SITE_URL . "/index.php#classes'>Classes section</a>.";
        }
    } elseif (str_contains($userMessage, 'register') || str_contains($userMessage, 'sign up') || str_contains($userMessage, 'join') || str_contains($userMessage, 'account')) {
        $response['reply'] = "Great! You can join our fitness community by registering on our <a href='" . SITE_URL . "/register.php'>Registration page</a>. It only takes a minute!";
    } elseif (str_contains($userMessage, 'location') || str_contains($userMessage, 'address') || str_contains($userMessage, 'located') || str_contains($userMessage, 'where') || str_contains($userMessage, 'gym')) {
        $response['reply'] = "KP Fitness is located at 123, Proto Street, Kota Kinabalu, Sabah. We are easily accessible and have ample parking!";
    } elseif (str_contains($userMessage, 'hour') || str_contains($userMessage, 'time') || str_contains($userMessage, 'open') || str_contains($userMessage, 'close')) {
        $response['reply'] = "We are open daily to serve you:<br>Monday - Friday: 6:00 AM - 11:00 PM<br>Saturday - Sunday: 8:00 AM - 8:00 PM";
    } elseif (str_contains($userMessage, 'trial') || str_contains($userMessage, 'free') || str_contains($userMessage, 'try') || str_contains($userMessage, 'guest')) {
        $response['reply'] = "Yes! We offer a one-day free trial for new customers. Just walk in and speak to our front desk, or <a href='" . SITE_URL . "/register.php'>register online</a> to get started!";
    } elseif (str_contains($userMessage, 'facility') || str_contains($userMessage, 'equipment') || str_contains($userMessage, 'amenities') || str_contains($userMessage, 'locker')) {
        $response['reply'] = "Our facilities include Mr. Olympia approved equipment, a spacious cardio zone, dedicated heavy-lifting area, clean locker rooms, and premium shower facilities.";
    } elseif (str_contains($userMessage, 'contact') || str_contains($userMessage, 'support') || str_contains($userMessage, 'help') || str_contains($userMessage, 'phone') || str_contains($userMessage, 'email') || str_contains($userMessage, 'staff')) {
        $response['reply'] = "Our friendly staff is here to help! You can reach us by phone at +60 10 388-4269 or email us at staff@kpfit.com.";
    } elseif (str_contains($userMessage, 'hello') || preg_match('/\bhi\b/', $userMessage)) {
        $response['reply'] = "Hello there! How can I assist you today? I can tell you about our membership plans, class schedules, or help you register!";
    }
}

echo json_encode($response);
