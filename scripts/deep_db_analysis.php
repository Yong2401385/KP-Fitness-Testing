<?php
// scripts/deep_db_analysis.php

// Suppress output buffering/session issues for CLI
define('CLI_MODE', true);

require_once __DIR__ . '/../includes/config.db.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database for deep analysis.\n";
} catch(PDOException $e){
    die("ERROR: Could not connect. " . $e->getMessage());
}

$issues = [];

// 1. Check for Orphaned Reservations
echo "Checking for orphaned reservations...\n";
$sql = "SELECT r.ReservationID, r.UserID, r.SessionID 
        FROM reservations r 
        LEFT JOIN users u ON r.UserID = u.UserID 
        LEFT JOIN sessions s ON r.SessionID = s.SessionID 
        WHERE u.UserID IS NULL OR s.SessionID IS NULL";
$stmt = $pdo->query($sql);
$orphans = $stmt->fetchAll();
if (count($orphans) > 0) {
    foreach ($orphans as $o) {
        $issues[] = "[CRITICAL] Orphaned Reservation ID: {$o['ReservationID']} (User: {$o['UserID']}, Session: {$o['SessionID']}) - User or Session missing.";
    }
}

// 2. Check Session Booking Counts
echo "Checking session booking count consistency...\n";
$sql = "SELECT s.SessionID, s.CurrentBookings, COUNT(r.ReservationID) as ActualBookings 
        FROM sessions s 
        LEFT JOIN reservations r ON s.SessionID = r.SessionID AND r.Status IN ('booked', 'attended', 'Rated')
        GROUP BY s.SessionID 
        HAVING s.CurrentBookings != ActualBookings";
$stmt = $pdo->query($sql);
$mismatches = $stmt->fetchAll();
if (count($mismatches) > 0) {
    foreach ($mismatches as $m) {
        $issues[] = "[WARNING] Session {$m['SessionID']} has CurrentBookings={$m['CurrentBookings']} but found {$m['ActualBookings']} valid reservations.";
    }
}

// 3. Check for Attendance vs Reservation Status inconsistency
echo "Checking attendance consistency...\n";
// Users in attendance table but not marked as 'attended' or 'Rated' in reservations
$sql = "SELECT a.AttendanceID, a.SessionID, a.UserID, r.Status as ResStatus
        FROM attendance a
        JOIN reservations r ON a.SessionID = r.SessionID AND a.UserID = r.UserID
        WHERE r.Status NOT IN ('attended', 'Rated')";
$stmt = $pdo->query($sql);
$att_issues = $stmt->fetchAll();
if (count($att_issues) > 0) {
    foreach ($att_issues as $ai) {
        $issues[] = "[LOGIC] Attendance ID {$ai['AttendanceID']} exists, but Reservation Status is '{$ai['ResStatus']}' (expected 'attended' or 'Rated').";
    }
}

// 4. Check for Expired Memberships that are still set on users without AutoRenew logic handling
echo "Checking for potentially expired memberships...\n";
$sql = "SELECT UserID, FullName, MembershipEndDate 
        FROM users 
        WHERE MembershipID IS NOT NULL 
        AND MembershipEndDate < CURDATE() 
        AND (NextMembershipID IS NULL AND AutoRenew = 0)";
$stmt = $pdo->query($sql);
$expired = $stmt->fetchAll();
if (count($expired) > 0) {
    // This is just informational/warning, as they might just be lapsed users.
    if (count($expired) < 10) {
        foreach ($expired as $ex) {
            $issues[] = "[INFO] User {$ex['UserID']} ({$ex['FullName']}) has expired membership (End: {$ex['MembershipEndDate']}).";
        }
    } else {
        $issues[] = "[INFO] " . count($expired) . " users have expired memberships.";
    }
}

// 5. Check for Trainer Double Booking
echo "Checking for trainer schedule conflicts...\n";
$sql = "SELECT s1.SessionID as ID1, s1.SessionDate, s1.StartTime as Start1, s1.EndTime as End1, s1.TrainerID,
               s2.SessionID as ID2, s2.StartTime as Start2, s2.EndTime as End2
        FROM sessions s1
        JOIN sessions s2 ON s1.TrainerID = s2.TrainerID 
                         AND s1.SessionDate = s2.SessionDate 
                         AND s1.SessionID < s2.SessionID
        WHERE s1.Status != 'cancelled' AND s2.Status != 'cancelled'
        AND (
            (s1.StartTime < s2.EndTime AND s1.EndTime > s2.StartTime)
        )";
$stmt = $pdo->query($sql);
$conflicts = $stmt->fetchAll();
if (count($conflicts) > 0) {
    foreach ($conflicts as $c) {
        $issues[] = "[CRITICAL] Trainer {$c['TrainerID']} is double-booked on {$c['SessionDate']}: Sessions {$c['ID1']} ({$c['Start1']}-{$c['End1']}) and {$c['ID2']} ({$c['Start2']}-{$c['End2']}).";
    }
}

// 6. Check for Invalid Session Times
echo "Checking for invalid session times...\n";
$sql = "SELECT SessionID, StartTime, EndTime FROM sessions WHERE StartTime >= EndTime";
$stmt = $pdo->query($sql);
$invalid_times = $stmt->fetchAll();
if (count($invalid_times) > 0) {
    foreach ($invalid_times as $it) {
        $issues[] = "[CRITICAL] Session {$it['SessionID']} has invalid time range: {$it['StartTime']} to {$it['EndTime']}.";
    }
}

// Report
echo "\n--- ANALYSIS REPORT ---\n";
if (empty($issues)) {
    echo "No significant data integrity issues found.\n";
} else {
    echo "Found " . count($issues) . " potential issues:\n";
    foreach ($issues as $issue) {
        echo "$issue\n";
    }
}
echo "\nAnalysis complete.\n";
?>
