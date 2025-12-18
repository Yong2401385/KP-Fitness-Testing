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
$height = isset($_POST['height']) ? intval($_POST['height']) : null;
$weight = isset($_POST['weight']) ? intval($_POST['weight']) : null;

if ($height < 50 || $height > 300) {
    echo json_encode(['success' => false, 'message' => 'Height must be between 50cm and 300cm.']);
    exit;
}
if ($weight < 20 || $weight > 500) {
    echo json_encode(['success' => false, 'message' => 'Weight must be between 20kg and 500kg.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE users SET Height = ?, Weight = ? WHERE UserID = ?");
    if ($stmt->execute([$height, $weight, $userId])) {
        // Calculate new BMI to return
        $bmi = calculate_bmi($height, $weight);
        $bmiCategory = get_bmi_category($bmi);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Stats updated successfully!',
            'bmi' => $bmi,
            'bmiCategory' => $bmiCategory
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update stats.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>