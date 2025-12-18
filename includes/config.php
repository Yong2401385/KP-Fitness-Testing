<?php
// config.php

// --- DATABASE CONFIGURATION ---
require_once 'config.db.php';

// --- SITE CONFIGURATION ---
define('SITE_NAME', 'KP Fitness');
define('SITE_URL', 'http://localhost/KP%20Testing'); // Adjust if your path is different

// --- SESSION MANAGEMENT ---
// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
    // Generate a CSRF token if one doesn't exist
    if (empty($_SESSION['csrf_token'])) {
        generate_csrf_token();
    }
}

// --- CSRF PROTECTION ---

/**
 * Generates a new CSRF token and stores it in the session.
 */
function generate_csrf_token() {
    // Generate a random token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Validates a CSRF token from a form submission.
 * Dies with a fatal error if the token is invalid, unless $returnBool is true.
 * @param string $token The token from the form.
 * @param bool $returnBool If true, returns false on failure instead of dying.
 * @return bool True if valid, false if invalid (when $returnBool is true).
 */
function validate_csrf_token($token, $returnBool = false) {
    if (!isset($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        if ($returnBool) {
            return false;
        }
        // Token is invalid, stop the script
        die('CSRF validation failed.');
    }
    return true;
}


/**
 * Gets the current CSRF token.
 * @return string The CSRF token.
 */
function get_csrf_token() {
    return $_SESSION['csrf_token'];
}

// --- DATABASE CONNECTION (PDO) ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e){
    // If connection fails, stop the script and show an error
    die("ERROR: Could not connect. " . $e->getMessage());
}

// --- CORE HELPER FUNCTIONS ---

/**
 * Sanitizes user input to prevent XSS attacks.
 * @param string $data The input data to sanitize.
 * @return string The sanitized data.
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Redirects the user to a specified URL.
 * @param string $url The URL to redirect to.
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Checks if a user is logged in.
 * @return bool True if the user is logged in, false otherwise.
 */
function is_logged_in() {
    return isset($_SESSION['UserID']);
}

/**
 * Gets the role of the logged-in user.
 * @return string|null The user's role or null if not logged in.
 */
function get_user_role() {
    return is_logged_in() ? $_SESSION['Role'] : null;
}

/**
 * Checks if the logged-in user is an admin.
 * @return bool True if the user is an admin, false otherwise.
 */
function is_admin() {
    return get_user_role() === 'admin';
}

/**
 * Checks if the logged-in user is a trainer.
 * @return bool True if the user is a trainer, false otherwise.
 */
function is_trainer() {
    return get_user_role() === 'trainer';
}

/**
 * Checks if the logged-in user is a client.
 * @return bool True if the user is a client, false otherwise.
 */
function is_client() {
    return get_user_role() === 'client';
}

/**
 * If the user is not an admin, redirects them to the login page.
 */
function require_admin() {
    if (!is_admin()) {
        redirect(SITE_URL . '/login.php');
    }
}

/**
 * If the user is not a trainer, redirects them to the login page.
 */
function require_trainer() {
    if (!is_trainer()) {
        redirect(SITE_URL . '/login.php');
    }
}

/**
 * If the user is not a client, redirects them to the login page.
 */
function require_client() {
    if (!is_client()) {
        redirect(SITE_URL . '/login.php');
    }
}

/**
 * Creates a notification for a user.
 * @param int $userId The ID of the user to notify.
 * @param string $title The title of the notification.
 * @param string $message The notification message.
 * @param string $type The type of notification (info, warning, success, error).
 */
function create_notification($userId, $title, $message, $type = 'info') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (UserID, Title, Message, Type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $message, $type]);
    } catch (PDOException $e) {
        // Silently fail or log the error, so it doesn't break the user flow
        error_log("Failed to create notification: " . $e->getMessage());
    }
}

/**
 * Gets the count of unread notifications for a user.
 * @param int $userId The ID of the user.
 * @return int The number of unread notifications.
 */
function get_unread_notifications_count($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE UserID = ? AND IsRead = FALSE");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Gets all notifications for a user.
 * @param int $userId The ID of the user.
 * @return array An array of notifications.
 */
function get_notifications($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE UserID = ? ORDER BY CreatedAt DESC LIMIT 10");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Calculates Body Mass Index (BMI).
 * @param int|null $height Height in cm.
 * @param int|null $weight Weight in kg.
 * @return string The calculated BMI or 'N/A'.
 */
function calculate_bmi($height, $weight) {
    if ($height > 0 && $weight > 0) {
        $heightInMeters = $height / 100;
        $bmi = $weight / ($heightInMeters * $heightInMeters);
        return number_format($bmi, 1);
    }
    return 'N/A';
}

/**
 * Gets the BMI category based on the BMI value.
 * @param float|string $bmi The BMI value.
 * @return string The BMI category.
 */
function get_bmi_category($bmi) {
    if (!is_numeric($bmi)) return 'N/A';
    
    if ($bmi < 18.5) {
        return 'Underweight';
    } elseif ($bmi >= 18.5 && $bmi <= 24.9) {
        return 'Normal weight';
    } elseif ($bmi >= 25 && $bmi <= 29.9) {
        return 'Overweight';
    } else {
        return 'Obesity';
    }
}

/**
 * Formats a date string.
 * @param string $date The date string to format.
 * @return string The formatted date.
 */
function format_date($date) {
    return date('d/m/Y', strtotime($date));
}

/**
 * Formats a time string.
 * @param string $time The time string to format.
 * @return string The formatted time.
 */
function format_time($time) {
    return date('g:i A', strtotime($time));
}

/**
 * Formats a number as currency.
 * @param float $number The number to format.
 * @return string The formatted currency string.
 */
function format_currency($number) {
    return 'RM ' . number_format($number, 2);
}

/**
 * Checks and rotates membership if expired.
 * Handles Downgrade/Upgrade transitions.
 */
function check_membership_expiry() {
    global $pdo;
    if (!is_logged_in()) return;

    $userId = $_SESSION['UserID'];
    
    // Fetch current status
    $stmt = $pdo->prepare("SELECT MembershipID, MembershipEndDate, NextMembershipID, AutoRenew FROM users WHERE UserID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user && $user['MembershipEndDate'] && strtotime($user['MembershipEndDate']) < time()) {
        // Membership has expired
        
        if ($user['NextMembershipID']) {
            // Rotate to next membership (Downgrade/Scheduled Change)
            $nextPlanId = $user['NextMembershipID'];
            
            // Get duration of next plan
            $stmt = $pdo->prepare("SELECT Duration FROM membership WHERE MembershipID = ?");
            $stmt->execute([$nextPlanId]);
            $nextPlan = $stmt->fetch();
            
            if ($nextPlan) {
                $newStartDate = date('Y-m-d');
                $newEndDate = date('Y-m-d', strtotime("+{$nextPlan['Duration']} days"));
                
                // Determine AutoRenew for new plan (default to 1 for monthly/yearly)
                $newAutoRenew = ($nextPlan['Duration'] >= 30) ? 1 : 0;

                $stmt = $pdo->prepare("UPDATE users SET MembershipID = ?, MembershipStartDate = ?, MembershipEndDate = ?, NextMembershipID = NULL, AutoRenew = ? WHERE UserID = ?");
                $stmt->execute([$nextPlanId, $newStartDate, $newEndDate, $newAutoRenew, $userId]);
                
                // Refresh session or notify user?
                // Logic handles it silently, next page load sees new plan.
            }
        } elseif ($user['AutoRenew']) {
            // Simple Auto-Renew (Same Plan)
            // Ideally, this should trigger a payment. Since we don't have a real gateway background worker,
            // we'll assume manual payment is needed OR just extend it if that's the "Simulated" behavior.
            // For this specific request about "Downgrade", I will leave this alone to avoid over-engineering the auto-renew without payment.
            // If expired and no next plan, they just lose access (as per standard logic).
        }
    }
}

// Run check if logged in
if (is_logged_in()) {
    check_membership_expiry();
}

/**
 * Checks if a session is currently "live" (active).
 * A session is live from 15 minutes before start time until 90 minutes after start time.
 * @param string $date YYYY-MM-DD
 * @param string $time HH:MM:SS
 * @return bool
 */
function is_session_live($date, $time) {
    $sessionStart = strtotime("$date $time");
    $now = time();
    // Live window: 15 mins before to 90 mins after (assuming avg class is 45-60 mins)
    $windowStart = $sessionStart - (15 * 60); 
    $windowEnd = $sessionStart + (90 * 60);

    return ($now >= $windowStart && $now <= $windowEnd);
}

/**
 * Retrieves the existing Session Code or generates a new one if it doesn't exist.
 * @param int $sessionId
 * @param PDO $pdo
 * @return string The session code
 */
function get_or_create_session_code($sessionId, $pdo) {
    // Check existing
    $stmt = $pdo->prepare("SELECT SessionCode FROM sessions WHERE SessionID = ?");
    $stmt->execute([$sessionId]);
    $result = $stmt->fetch();
    
    if ($result && !empty($result['SessionCode'])) {
        return $result['SessionCode'];
    }
    
    // Generate new code (6 chars, uppercase alphanumeric)
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    // Save to DB
    $update = $pdo->prepare("UPDATE sessions SET SessionCode = ? WHERE SessionID = ?");
    $update->execute([$code, $sessionId]);
    
    return $code;
}

/**
 * Formats a raw phone number string for display in the Malaysia 01X-XXX XXXX or 01X-XXXX XXXX format.
 * It first cleans the number by removing non-digits, then applies the specific formatting.
 *
 * @param string|null $rawPhone The raw phone number string from the database.
 * @return string The formatted phone number, or an empty string if input is null/empty.
 */
function format_phone_display(?string $rawPhone): string {
    if (empty($rawPhone)) {
        return '';
    }

    // Remove all non-digit characters
    $cleanPhone = preg_replace('/\D/', '', $rawPhone);

    // Apply formatting based on length
    // Malaysian mobile numbers typically start with 01 and are 9-10 digits long after '0'.
    // So total 10 or 11 digits.
    if (strlen($cleanPhone) == 10 && substr($cleanPhone, 0, 2) === '01') { // e.g., 0123456789 -> 012-345 6789
        return substr($cleanPhone, 0, 3) . '-' . substr($cleanPhone, 3, 3) . ' ' . substr($cleanPhone, 6, 4);
    } elseif (strlen($cleanPhone) == 11 && substr($cleanPhone, 0, 2) === '01') { // e.g., 01234567890 -> 012-3456 7890
        return substr($cleanPhone, 0, 3) . '-' . substr($cleanPhone, 3, 4) . ' ' . substr($cleanPhone, 7, 4);
    }
    
    // If it doesn't match typical Malaysian mobile, return cleaned or original (decide based on strictness)
    // For now, return the cleaned number if it doesn't fit the expected format to avoid misformatting.
    return $rawPhone; // Fallback to original if not matching specific format
}

?>
