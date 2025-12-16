<?php
require_once '../includes/config.php';
require_client();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$_SESSION['profile_prompt_dismissed'] = true;
echo json_encode(['success' => true]);
