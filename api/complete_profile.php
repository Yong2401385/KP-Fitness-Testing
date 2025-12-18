<?php
require_once '../includes/config.php';
require_client();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

if (!isset($_POST['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CSRF token is missing.']);
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF validation failed.']);
    exit;
}

$userId = $_SESSION['UserID'];
$phone = sanitize_input($_POST['phone'] ?? '');
$dob = sanitize_input($_POST['dateOfBirth'] ?? '');
$height = intval($_POST['height'] ?? 0);
$weight = intval($_POST['weight'] ?? 0);
$gender = sanitize_input($_POST['gender'] ?? ''); // New: Get gender

// Basic Validation
if (empty($phone) || empty($dob) || empty($height) || empty($weight) || empty($gender)) { // New: Include gender
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

// Gender Validation
$allowedGenders = ['Male', 'Female', 'Other'];
if (!in_array($gender, $allowedGenders)) {
    echo json_encode(['success' => false, 'message' => 'Invalid gender selected.']);
    exit;
}

// Height and Weight Validation
if ($height < 50 || $height > 300) {
    echo json_encode(['success' => false, 'message' => 'Height must be between 50cm and 300cm.']);
    exit;
}
if ($weight < 20 || $weight > 500) {
    echo json_encode(['success' => false, 'message' => 'Weight must be between 20kg and 500kg.']);
    exit;
}

// Phone Validation (Malaysia)
if (!preg_match('/^01\d-\d{3,4} \d{4}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone format. Use 01X-XXX XXXX or 01X-XXXX XXXX.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE users SET Phone = ?, DateOfBirth = ?, Height = ?, Weight = ?, Gender = ? WHERE UserID = ?"); // New: Include Gender
    if ($stmt->execute([$phone, $dob, $height, $weight, $gender, $userId])) {
        
        // Mark the 'Action Required' notification as read
        $stmt_notif = $pdo->prepare("UPDATE notifications SET IsRead = 1 WHERE UserID = ? AND Title = 'Action Required: Complete Profile'");
        $stmt_notif->execute([$userId]);

        create_notification($userId, 'Profile Completed', 'Thank you for completing your profile! You can now access all features.', 'success');
        
        $_SESSION['profile_prompt_dismissed'] = true;
        echo json_encode(['success' => true, 'message' => 'Profile completed successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile.']);
    }
} catch (PDOException $e) {
    error_log('Database error in complete_profile.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An internal error occurred.']);
}
