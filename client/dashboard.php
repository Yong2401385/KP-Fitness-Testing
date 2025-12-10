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
    $feedback = ['type' => 'error', 'message' => 'Could not fetch dashboard data: ' . $e->getMessage()];
    $user = [];
    $upcomingBookings = [];
    $workoutPlanCount = 0;
    $membership = null;
    $bmi = 'N/A';
    $bmiCategory = 'N/A';
}


include 'includes/client_header.php';
?>

<style>
.welcome-section {
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
}
.welcome-section h1 {
    font-size: 1.8rem;
}
.welcome-section .quick-actions {
    margin-top: 1.5rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.stat-card {
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
}
.stat-card .stat-number {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--primary-color);
}
.stat-card .stat-label {
    color: var(--text-dark);
    margin-top: 0.5rem;
    font-size: 0.9rem;
}
.content-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}
.card {
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 2rem;
}
.card-title {
    font-size: 1.5rem;
    color: var(--primary-color);
    margin-bottom: 1.5rem;
}
.card ul {
    list-style: none;
}
.card li {
    padding: 0.8rem 0;
    border-bottom: 1px solid var(--border-color);
}
.card li:last-child {
    border-bottom: none;
}
</style>

<div class="welcome-section">
    <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $user['FullName'])[0]); ?>!</h1>
    <p>Ready to continue your fitness journey? Here's a snapshot of your progress.</p>
    <div class="quick-actions">
        <a href="booking.php" class="btn btn-primary">Book a Class</a>
        <a href="workout_planner.php" class="btn btn-secondary">AI Workout Planner</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?php echo count($upcomingBookings); ?></div>
        <div class="stat-label">Upcoming Classes</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $workoutPlanCount; ?></div>
        <div class="stat-label">Saved Workouts</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $bmi; ?></div>
        <div class="stat-label"><?php echo $bmiCategory; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-number" style="font-size: 1.5rem; text-transform: capitalize;">
            <?php echo $membership ? htmlspecialchars($membership['Type']) : 'None'; ?>
        </div>
        <div class="stat-label">Membership</div>
    </div>
</div>

<div class="content-grid">
    <div class="card">
        <h2 class="card-title">Upcoming Classes</h2>
        <ul>
            <?php if (empty($upcomingBookings)): ?>
                <li>No upcoming classes. Why not book one?</li>
            <?php else: ?>
                <?php foreach ($upcomingBookings as $booking): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($booking['ClassName']); ?></strong><br>
                        <small><?php echo format_date($booking['SessionDate']); ?> at <?php echo format_time($booking['Time']); ?></small>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
    <div class="card">
        <h2 class="card-title">Health Stats</h2>
         <ul>
            <li><strong>Height:</strong> <?php echo htmlspecialchars($user['Height'] ?? 'N/A'); ?> cm</li>
            <li><strong>Weight:</strong> <?php echo htmlspecialchars($user['Weight'] ?? 'N/A'); ?> kg</li>
            <li><strong>BMI:</strong> <?php echo $bmi; ?> (<?php echo $bmiCategory; ?>)</li>
         </ul>
         <a href="#" class="btn btn-secondary" style="margin-top: 1rem;">Update Profile</a>
    </div>
</div>

<?php include 'includes/client_footer.php'; ?>
