<?php
/**
 * Daily Maintenance Script
 * Run this via cron/scheduler once a day (e.g., at 00:01 AM).
 * 
 * Tasks:
 * 1. Process expired memberships (downgrade or expire).
 * 2. Process recurring bookings (extend window - NOT YET IMPLEMENTED but placeholder).
 */

// Use __DIR__ to find config
require_once __DIR__ . '/../includes/config.db.php';

// CLI Check
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

echo "Starting Daily Maintenance: " . date('Y-m-d H:i:s') . "\n";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- Task 1: Membership Expiry & Rotation ---
    echo "Checking membership expiries...\n";
    
    // Find users whose membership expired YESTERDAY (or before) and haven't been processed
    $stmt = $pdo->prepare(
        "SELECT UserID, MembershipEndDate, NextMembershipID, AutoRenew 
        FROM users 
        WHERE MembershipEndDate < CURDATE() 
        AND (NextMembershipID IS NOT NULL OR AutoRenew = 0)"
    );
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    $processed = 0;
    
    foreach ($users as $user) {
        $userId = $user['UserID'];
        
        if ($user['NextMembershipID']) {
            // Process Scheduled Change (Downgrade/Upgrade)
            $nextPlanId = $user['NextMembershipID'];
            
            // Get duration
            $pStmt = $pdo->prepare("SELECT Duration, PlanName FROM membership WHERE MembershipID = ?");
            $pStmt->execute([$nextPlanId]);
            $nextPlan = $pStmt->fetch();
            
            if ($nextPlan) {
                $newStartDate = date('Y-m-d');
                $newEndDate = date('Y-m-d', strtotime("+{$nextPlan['Duration']} days"));
                $newAutoRenew = ($nextPlan['Duration'] >= 30) ? 1 : 0;
                
                $uStmt = $pdo->prepare(
                    "UPDATE users 
                    SET MembershipID = ?, MembershipStartDate = ?, MembershipEndDate = ?, NextMembershipID = NULL, AutoRenew = ? 
                    WHERE UserID = ?"
                );
                $uStmt->execute([$nextPlanId, $newStartDate, $newEndDate, $newAutoRenew, $userId]);
                
                // Add notification
                $msg = "Your membership has switched to {$nextPlan['PlanName']}.";
                $nStmt = $pdo->prepare("INSERT INTO notifications (UserID, Title, Message, Type) VALUES (?, 'Membership Update', ?, 'info')");
                $nStmt->execute([$userId, $msg]);
                
                $processed++;
            }
        } elseif ($user['AutoRenew'] == 0) {
            // Expire to Non-Member (Assuming ID 1 is Non-Member/Default?)
            // We need to know what "expired" means. 
            // Usually, we set MembershipID to NULL or a specific 'Non-Member' ID.
            // Let's assume we just leave it expired (EndDate in past) so logic checks fail?
            // Or explicitly set to NULL.
            
            // For now, let's just log it. The existing checks rely on EndDate < Today.
            // So technically no DB update is needed unless we want to clear the FK.
        }
    }
    
    echo "Processed $processed membership changes.\n";

    // --- Task 2: Auto-Generate Future Schedule (Rolling 4 Weeks) ---
    echo "Checking future schedule (Target: 4 weeks ahead)...\n";
    generateFutureSessions($pdo);

    echo "Daily Maintenance Completed.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

function generateFutureSessions($pdo) {
    // 1. Get Data
    // Fetch Trainers
    $stmt = $pdo->query("SELECT UserID, Specialist, DaysOff FROM users WHERE Role = 'trainer' AND IsActive = 1");
    $trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$trainers) {
        echo "No active trainers found.\n";
        return;
    }
    
    // Group Trainers by Specialty
    $trainersBySpecialty = [];
    foreach ($trainers as $t) {
        $trainersBySpecialty[$t['Specialist']][] = $t;
    }
    
    // Fetch Activities
    $stmt = $pdo->query("SELECT ClassID, Duration, Specialist FROM activities WHERE IsActive = 1");
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $activitiesBySpecialty = [];
    foreach ($activities as $a) {
        $activitiesBySpecialty[$a['Specialist']][] = $a['ClassID']; // Store ID for simplicity
    }
    
    // 2. Determine Date Range
    $targetDate = new DateTime('+4 weeks');
    
    // Find latest session
    $stmt = $pdo->query("SELECT MAX(SessionDate) FROM sessions");
    $lastSessionDate = $stmt->fetchColumn();
    
    if ($lastSessionDate) {
        $startDate = new DateTime($lastSessionDate);
        $startDate->modify('+1 day');
    } else {
        $startDate = new DateTime(); // Start today if empty
    }
    
    if ($startDate > $targetDate) {
        echo "Schedule is already populated up to " . $startDate->format('Y-m-d') . " (Target: " . $targetDate->format('Y-m-d') . "). No new sessions needed.\n";
        return;
    }
    
    echo "Generating sessions from " . $startDate->format('Y-m-d') . " to " . $targetDate->format('Y-m-d') . "...\n";
    
    // 3. Generation Loop
    $sessionTimes = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00'];
    $durationStmt = $pdo->prepare("SELECT Duration FROM activities WHERE ClassID = ?");
    $insertStmt = $pdo->prepare("INSERT INTO sessions (SessionDate, StartTime, EndTime, Room, ClassID, TrainerID) VALUES (?, ?, ?, ?, ?, ?)");
    
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($startDate, $interval, $targetDate->modify('+1 day')); // Inclusive of target
    
    $count = 0;
    
    foreach ($daterange as $date) {
        $currentDate = $date->format('Y-m-d');
        $dayOfWeek = (int)$date->format('w');
        $trainerSchedule = []; // Track bookings for this day: [Time => [TrainerIDs]]
        
        foreach ($sessionTimes as $time) {
            foreach ($activitiesBySpecialty as $specialty => $activityIds) {
                if (!isset($trainersBySpecialty[$specialty])) continue;
                
                // Pick random activity
                $activityId = $activityIds[array_rand($activityIds)];
                
                // Pick valid trainer
                $candidates = $trainersBySpecialty[$specialty];
                $validTrainers = [];
                
                foreach ($candidates as $tData) {
                    // Check Days Off
                    $offDays = json_decode($tData['DaysOff'] ?? '[]');
                    if (is_array($offDays) && in_array($dayOfWeek, $offDays)) {
                        continue;
                    }
                    
                    // Check Schedule Overlap
                    if (in_array($tData['UserID'], $trainerSchedule[$time] ?? [])) {
                        continue;
                    }
                    
                    $validTrainers[] = $tData['UserID'];
                }
                
                if (empty($validTrainers)) continue;
                
                $trainerId = $validTrainers[array_rand($validTrainers)];
                
                // Calculate End Time
                $durationStmt->execute([$activityId]);
                $act = $durationStmt->fetch();
                $duration = $act['Duration'];
                
                $startObj = new DateTime($time);
                $endObj = clone $startObj;
                $endObj->add(new DateInterval('PT' . $duration . 'M'));
                $endTime = $endObj->format('H:i:s');
                
                $room = "Room " . rand(1, 5);
                
                $insertStmt->execute([$currentDate, $time, $endTime, $room, $activityId, $trainerId]);
                
                $trainerSchedule[$time][] = $trainerId;
                $count++;
            }
        }
    }
    
    echo "Generated $count new sessions.\n";
}

