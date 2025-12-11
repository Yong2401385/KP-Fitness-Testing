<?php
define('PAGE_TITLE', 'Client Dashboard');
require_once '../includes/config.php';
require_client(); // Ensure only clients can access

// Get user data
$userId = $_SESSION['UserID'];
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE UserID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    // Calculate BMI
    $bmi = calculate_bmi($user['Height'], $user['Weight']);
    $bmiCategory = get_bmi_category($bmi);
    
    // Get upcoming bookings (next 5)
    $stmt = $pdo->prepare("
        SELECT s.SessionDate, s.Time, c.ClassName 
        FROM reservations r
        JOIN sessions s ON r.SessionID = s.SessionID
        JOIN classes c ON s.ClassID = c.ClassID
        WHERE r.UserID = ? AND r.Status = 'booked' AND s.SessionDate >= CURDATE()
        ORDER BY s.SessionDate, s.Time
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $upcomingBookings = $stmt->fetchAll();

    // Get workout plan count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM workout_plans WHERE UserID = ?");
    $stmt->execute([$userId]);
    $workoutPlanCount = $stmt->fetchColumn();

    // Get current membership
    $stmt = $pdo->prepare("
        SELECT m.Type, p.Status as PaymentStatus
        FROM users u 
        LEFT JOIN membership m ON u.MembershipID = m.MembershipID
        LEFT JOIN payments p ON p.UserID = u.UserID AND p.MembershipID = m.MembershipID
        WHERE u.UserID = ?
        ORDER BY p.PaymentDate DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $membership = $stmt->fetch();

} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch dashboard data: ' . $e->getMessage()];
    $user = [];
    $upcomingBookings = [];
    $workoutPlanCount = 0;
    $membership = null;
    $bmi = 'N/A';
    $bmiCategory = 'N/A';
}


include 'includes/client_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
</div>

<div class="card bg-light text-dark p-4 mb-4">
    <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $user['FullName'])[0]); ?>!</h2>
    <p class="lead">Ready to continue your fitness journey? Here's a snapshot of your progress.</p>
    <div class="mt-2">
        <a href="booking.php" class="btn btn-primary">Book a Class</a>
        <a href="workout_planner.php" class="btn btn-secondary">AI Workout Planner</a>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-4 fw-bold text-primary"><?php echo count($upcomingBookings); ?></div>
                <div class="text-muted">Upcoming Classes</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-4 fw-bold text-primary"><?php echo $workoutPlanCount; ?></div>
                <div class="text-muted">Saved Workouts</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-4 fw-bold text-primary"><?php echo $bmi; ?></div>
                <div class="text-muted"><?php echo $bmiCategory; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="h3 fw-bold text-primary text-capitalize">
                    <?php echo $membership ? htmlspecialchars($membership['Type']) : 'None'; ?>
                </div>
                <div class="text-muted">Membership</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                Upcoming Classes
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php if (empty($upcomingBookings)): ?>
                        <li class="list-group-item">No upcoming classes. Why not book one?</li>
                    <?php else: ?>
                        <?php foreach ($upcomingBookings as $booking): ?>
                            <li class="list-group-item">
                                <strong><?php echo htmlspecialchars($booking['ClassName']); ?></strong><br>
                                <small><?php echo format_date($booking['SessionDate']); ?> at <?php echo format_time($booking['Time']); ?></small>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                Health Stats
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between"><strong>Height:</strong> <?php echo htmlspecialchars($user['Height'] ?? 'N/A'); ?> cm</li>
                    <li class="list-group-item d-flex justify-content-between"><strong>Weight:</strong> <?php echo htmlspecialchars($user['Weight'] ?? 'N/A'); ?> kg</li>
                    <li class="list-group-item d-flex justify-content-between"><strong>BMI:</strong> <?php echo $bmi; ?> (<?php echo $bmiCategory; ?>)</li>
                </ul>
                <a href="#" class="btn btn-secondary mt-3">Update Profile</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/client_footer.php'; ?>
