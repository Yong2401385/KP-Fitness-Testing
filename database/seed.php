<?php
require_once __DIR__ . '/../includes/config.php';

// --- Configuration ---
const YEAR = 2025;
const NUM_CLIENTS = 300;
const MAX_BOOKINGS_PER_CLIENT = 50;

// --- Data ---
$trainersData = [
    ['FullName' => 'John Doe', 'Email' => 'john.doe@kpfitness.com', 'Gender' => 'Male', 'Specialist' => 'Cardio', 'WorkingHours' => '9-5', 'JobType' => 'Full-time'],
    ['FullName' => 'Jane Smith', 'Email' => 'jane.smith@kpfitness.com', 'Gender' => 'Female', 'Specialist' => 'Strength', 'WorkingHours' => '10-6', 'JobType' => 'Full-time'],
    ['FullName' => 'Peter Jones', 'Email' => 'peter.jones@kpfitness.com', 'Gender' => 'Male', 'Specialist' => 'MindAndBody', 'WorkingHours' => '8-4', 'JobType' => 'Full-time'],
    ['FullName' => 'Mary Williams', 'Email' => 'mary.williams@kpfitness.com', 'Gender' => 'Female', 'Specialist' => 'HIIT_Circuit', 'WorkingHours' => '12-8', 'JobType' => 'Part-time'],
    ['FullName' => 'David Brown', 'Email' => 'david.brown@kpfitness.com', 'Gender' => 'Male', 'Specialist' => 'Combat', 'WorkingHours' => '2-10', 'JobType' => 'Full-time'],
    ['FullName' => 'Susan Davis', 'Email' => 'susan.davis@kpfitness.com', 'Gender' => 'Female', 'Specialist' => 'Cardio', 'WorkingHours' => '9-1', 'JobType' => 'Part-time'],
    ['FullName' => 'Richard Miller', 'Email' => 'richard.miller@kpfitness.com', 'Gender' => 'Male', 'Specialist' => 'Strength', 'WorkingHours' => '4-8', 'JobType' => 'Part-time'],
    ['FullName' => 'Patricia Wilson', 'Email' => 'patricia.wilson@kpfitness.com', 'Gender' => 'Female', 'Specialist' => 'MindAndBody', 'WorkingHours' => '9-5', 'JobType' => 'Full-time'],
];

$classesData = [
    ['ClassName' => 'Zumba', 'Description' => 'A fun and high-energy dance fitness class.', 'Duration' => 45, 'MaxCapacity' => 25, 'DifficultyLevel' => 'beginner', 'Price' => 35.00, 'Specialist' => 'Cardio'],
    ['ClassName' => 'Spin Cycling', 'Description' => 'An intense indoor cycling workout.', 'Duration' => 45, 'MaxCapacity' => 20, 'DifficultyLevel' => 'intermediate', 'Price' => 35.00, 'Specialist' => 'Cardio'],
    ['ClassName' => 'BodyPump', 'Description' => 'A full-body barbell workout.', 'Duration' => 50, 'MaxCapacity' => 15, 'DifficultyLevel' => 'intermediate', 'Price' => 40.00, 'Specialist' => 'Strength'],
    ['ClassName' => 'Weight Training', 'Description' => 'Classic weight training for all levels.', 'Duration' => 50, 'MaxCapacity' => 15, 'DifficultyLevel' => 'beginner', 'Price' => 40.00, 'Specialist' => 'Strength'],
    ['ClassName' => 'Yoga', 'Description' => 'Improve flexibility, strength, and mindfulness.', 'Duration' => 60, 'MaxCapacity' => 20, 'DifficultyLevel' => 'beginner', 'Price' => 45.00, 'Specialist' => 'MindAndBody'],
    ['ClassName' => 'Pilates', 'Description' => 'A low-impact workout that focuses on core strength.', 'Duration' => 60, 'MaxCapacity' => 20, 'DifficultyLevel' => 'beginner', 'Price' => 45.00, 'Specialist' => 'MindAndBody'],
    ['ClassName' => 'Tai Chi', 'Description' => 'A gentle form of exercise for all ages.', 'Duration' => 60, 'MaxCapacity' => 20, 'DifficultyLevel' => 'beginner', 'Price' => 45.00, 'Specialist' => 'MindAndBody'],
    ['ClassName' => 'Bootcamp', 'Description' => 'A challenging mix of cardio and strength exercises.', 'Duration' => 45, 'MaxCapacity' => 20, 'DifficultyLevel' => 'advanced', 'Price' => 40.00, 'Specialist' => 'HIIT_Circuit'],
    ['ClassName' => 'Metabolic Conditioning', 'Description' => 'A high-intensity workout to boost your metabolism.', 'Duration' => 45, 'MaxCapacity' => 20, 'DifficultyLevel' => 'advanced', 'Price' => 40.00, 'Specialist' => 'HIIT_Circuit'],
    ['ClassName' => 'Boxing', 'Description' => 'Learn boxing techniques and get a great workout.', 'Duration' => 50, 'MaxCapacity' => 12, 'DifficultyLevel' => 'intermediate', 'Price' => 45.00, 'Specialist' => 'Combat'],
];

$sessionTimes = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00'];

// --- Helper Functions ---
function truncateTables($pdo) {
    echo "Truncating tables...\n";
    $tables = ['ratings', 'reservations', 'sessions', 'users', 'activities', 'class_categories', 'membership'];
    foreach ($tables as $table) {
        try {
            $pdo->exec("DELETE FROM `$table`");
            $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
        } catch (PDOException $e) {
            // Ignore errors for tables that might not exist yet
        }
    }
    // Re-insert system admin
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (UserID, FullName, Email, Password, Role) VALUES (1, 'System Administrator', 'admin@kpfitness.com', ?, 'admin')");
    $stmt->execute([$adminPassword]);
    echo "Tables truncated and admin user re-inserted.\n";
}


function createTrainers($pdo, $trainersData) {
    echo "Creating trainers...\n";
    $stmt = $pdo->prepare("INSERT INTO users (FullName, Email, Password, Role, Gender, Specialist, WorkingHours, JobType) VALUES (?, ?, ?, 'trainer', ?, ?, ?, ?)");
    foreach ($trainersData as $trainer) {
        $password = password_hash('trainer123', PASSWORD_DEFAULT);
        $stmt->execute([$trainer['FullName'], $trainer['Email'], $password, $trainer['Gender'], $trainer['Specialist'], $trainer['WorkingHours'], $trainer['JobType']]);
    }
    echo count($trainersData) . " trainers created.\n";
    return $pdo->query("SELECT Specialist, UserID FROM users WHERE Role = 'trainer'")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
}

function createMemberships($pdo) {
    echo "Creating memberships...\n";
    $plans = [
        ['8 Class Membership', 'monthly', 8, 88.00, '8 classes per month'],
        ['Unlimited Class Membership', 'monthly', 30, 118.00, 'Unlimited classes per month'],
        ['Annual Class Membership', 'yearly', 365, 1183.00, 'Unlimited classes all year, 2 months free'],
        ['Non-Member', 'onetime', 0, 0.00, 'Pay-per-class access'],
    ];
    $stmt = $pdo->prepare("INSERT INTO membership (PlanName, Type, Duration, Cost, Benefits) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE Type=VALUES(Type), Duration=VALUES(Duration), Cost=VALUES(Cost), Benefits=VALUES(Benefits)");
    foreach ($plans as $plan) {
        $stmt->execute($plan);
    }
    echo count($plans) . " memberships created.\n";
}

function createActivities($pdo, $classesData) {
    echo "Creating activities...\n";
    
    // Create categories
    $categories = array_unique(array_column($classesData, 'Specialist'));
    $stmt = $pdo->prepare("INSERT INTO class_categories (CategoryName) VALUES (?) ON DUPLICATE KEY UPDATE CategoryName=CategoryName");
    foreach ($categories as $category) {
        $stmt->execute([$category]);
    }
    $categoryIds = $pdo->query("SELECT CategoryName, CategoryID FROM class_categories")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Create activities
    $stmt = $pdo->prepare("INSERT INTO activities (ClassName, Description, Duration, MaxCapacity, DifficultyLevel, Price, Specialist, CategoryID) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($classesData as $class) {
        $stmt->execute([$class['ClassName'], $class['Description'], $class['Duration'], $class['MaxCapacity'], $class['DifficultyLevel'], $class['Price'], $class['Specialist'], $categoryIds[$class['Specialist']]]);
    }
    echo count($classesData) . " activities created.\n";
    return $pdo->query("SELECT Specialist, ClassID FROM activities")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
}

function createSessions($pdo, $year, $sessionTimes, $trainersBySpecialty, $activitiesBySpecialty) {
    echo "Creating sessions for $year...\n";
    $stmt = $pdo->prepare("INSERT INTO sessions (SessionDate, Time, Room, ClassID, TrainerID) VALUES (?, ?, ?, ?, ?)");
    $startDate = new DateTime("$year-01-01");
    $endDate = new DateTime("$year-12-31");
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($startDate, $interval, $endDate);

    $trainerSchedule = [];

    foreach ($daterange as $date) {
        $currentDate = $date->format('Y-m-d');
        $trainerSchedule[$currentDate] = [];

        foreach ($sessionTimes as $time) {
            foreach ($activitiesBySpecialty as $specialty => $activities) {
                if (!isset($trainersBySpecialty[$specialty])) continue;

                $activityId = $activities[array_rand($activities)];
                $availableTrainers = array_diff($trainersBySpecialty[$specialty], $trainerSchedule[$currentDate][$time] ?? []);
                
                if (empty($availableTrainers)) continue;
                
                $trainerId = $availableTrainers[array_rand($availableTrainers)];
                
                $room = "Room " . rand(1, 5);
                $stmt->execute([$currentDate, $time, $room, $activityId, $trainerId]);

                if (!isset($trainerSchedule[$currentDate][$time])) {
                    $trainerSchedule[$currentDate][$time] = [];
                }
                $trainerSchedule[$currentDate][$time][] = $trainerId;
            }
        }
    }
    echo "Sessions created for $year.\n";
}

function createClients($pdo, $numClients) {
    echo "Creating $numClients clients...\n";
    $stmt = $pdo->prepare("INSERT INTO users (FullName, Email, Password, Role, Phone, DateOfBirth, Height, Weight, MembershipID, MembershipStartDate, MembershipEndDate) VALUES (?, ?, ?, 'client', ?, ?, ?, ?, ?, ?, ?)");
    $membershipIds = $pdo->query("SELECT MembershipID, Duration FROM membership")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    for ($i = 0; $i < $numClients; $i++) {
        $fullName = "Client " . ($i + 1);
        $email = "client" . ($i + 1) . "@example.com";
        $password = password_hash('client123', PASSWORD_DEFAULT);
        $phone = '555-'.str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $dob = rand(1970, 2005) . '-' . rand(1, 12) . '-' . rand(1, 28);
        $height = rand(150, 200);
        $weight = rand(50, 120);
        
        $hasMembership = rand(0, 1);
        $membershipId = null;
        $startDate = null;
        $endDate = null;
        if ($hasMembership) {
            $membershipId = array_rand($membershipIds);
            $startDate = new DateTime(YEAR . "-01-01");
            $startDate->add(new DateInterval('P' . rand(0, 364) . 'D'));
            $endDate = clone $startDate;
            $endDate->add(new DateInterval('P' . $membershipIds[$membershipId] . 'D'));
            $startDate = $startDate->format('Y-m-d');
            $endDate = $endDate->format('Y-m-d');
        }

        $stmt->execute([$fullName, $email, $password, $phone, $dob, $height, $weight, $membershipId, $startDate, $endDate]);
    }
    echo "$numClients clients created.\n";
}


function createBookings($pdo, $numClients, $maxBookings) {
    echo "Creating bookings...\n";
    $clients = $pdo->query("SELECT UserID FROM users WHERE Role = 'client'")->fetchAll(PDO::FETCH_COLUMN);
    $sessions = $pdo->query("SELECT SessionID, SessionDate FROM sessions")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("INSERT INTO reservations (UserID, SessionID, Status, BookingDate) VALUES (?, ?, ?, ?)");
    $updateSessionStmt = $pdo->prepare("UPDATE sessions SET CurrentBookings = CurrentBookings + 1 WHERE SessionID = ?");

    foreach ($clients as $client) {
        $numBookings = rand(1, $maxBookings);
        for ($i = 0; $i < $numBookings; $i++) {
            if(empty($sessions)) continue;
            $session = $sessions[array_rand($sessions)];
            $status = 'attended';
            $bookingDate = new DateTime($session['SessionDate']);
            $bookingDate->sub(new DateInterval('P' . rand(1, 30) . 'D'));
            try {
                $stmt->execute([$client, $session['SessionID'], $status, $bookingDate->format('Y-m-d H:i:s')]);
                $updateSessionStmt->execute([$session['SessionID']]);
            } catch (PDOException $e) {
                // Ignore duplicate bookings
            }
        }
    }
    echo "Bookings created.\n";
}

// --- Main Execution ---

try {

    truncateTables($pdo);

    

    $trainersBySpecialty = createTrainers($pdo, $trainersData);

    createMemberships($pdo);

    $activitiesBySpecialty = createActivities($pdo, $classesData);

    createSessions($pdo, YEAR, $sessionTimes, $trainersBySpecialty, $activitiesBySpecialty);

    createClients($pdo, NUM_CLIENTS);

    createBookings($pdo, NUM_CLIENTS, MAX_BOOKINGS_PER_CLIENT);



    echo "Dummy data generation completed successfully!\n";

} catch (Exception $e) {

    die("An error occurred: " . $e->getMessage() . "\n");

}


