<?php
// config.php

// --- DATABASE CONFIGURATION ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Default XAMPP user
define('DB_PASS', '');     // Default XAMPP password
define('DB_NAME', 'kp_fitness_db');

// --- SITE CONFIGURATION ---
define('SITE_NAME', 'KP Fitness');
define('SITE_URL', 'http://localhost/KP%20Testing'); // Adjust if your path is different

// --- SESSION MANAGEMENT ---
// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
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
    return date('D, M j, Y', strtotime($date));
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

?>
