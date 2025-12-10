<?php
define('PAGE_TITLE', 'Trainer Dashboard');
require_once '../includes/config.php';
require_trainer(); // Ensure only trainers can access

$trainerId = $_SESSION['UserID'];
$feedback = [];

// --- Fetch Data for Display ---
try {
    // Today's schedule
    $stmt = $pdo->prepare("
        SELECT s.SessionID, s.Time, c.ClassName, s.Room, s.CurrentBookings, c.MaxCapacity
        FROM sessions s
        JOIN classes c ON s.ClassID = c.ClassID
        WHERE s.TrainerID = ? AND s.SessionDate = CURDATE() AND s.Status = 'scheduled'
        ORDER BY s.Time
    ");
    $stmt->execute([$trainerId]);
    $todaysSchedule = $stmt->fetchAll();
    
    // Upcoming classes (next 5)
    $stmt = $pdo->prepare("
        SELECT s.SessionDate, s.Time, c.ClassName
        FROM sessions s
        JOIN classes c ON s.ClassID = c.ClassID
        WHERE s.TrainerID = ? AND s.SessionDate > CURDATE() AND s.Status = 'scheduled'
        ORDER BY s.SessionDate, s.Time
        LIMIT 5
    ");
    $stmt->execute([$trainerId]);
    $upcomingClasses = $stmt->fetchAll();

    // Stats
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE TrainerID = ? AND SessionDate < CURDATE()");
    $stmt->execute([$trainerId]);
    $totalSessionsConducted = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations r JOIN sessions s ON r.SessionID = s.SessionID WHERE s.TrainerID = ?");
    $stmt->execute([$trainerId]);
    $totalClientBookings = $stmt->fetchColumn();

} catch (PDOException $e) {
    $feedback = ['type' => 'error', 'message' => 'Could not fetch dashboard data: ' . $e->getMessage()];
    $todaysSchedule = $upcomingClasses = [];
    $totalSessionsConducted = $totalClientBookings = 0;
}

include 'includes/trainer_header.php';
?>

<style>
.card { background: var(--light-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
.card-title { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); margin-bottom: 1.5rem; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
.stat-card { background: var(--light-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; text-align: center; }
.stat-card .stat-number { font-size: 2.2rem; font-weight: 800; color: var(--primary-color); }
.stat-card .stat-label { color: var(--text-dark); margin-top: 0.5rem; font-size: 0.9rem; }
.sessions-table { width: 100%; border-collapse: collapse; }
.sessions-table th, .sessions-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
.sessions-table th { color: var(--primary-color); }
</style>

<div class="page-header">
    <h1>Trainer Dashboard</h1>
    <p>Welcome, <?php echo htmlspecialchars($_SESSION['FullName']); ?>. Here is your schedule and summary.</p>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?>"><?php echo $feedback['message']; ?></div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?php echo count($todaysSchedule); ?></div>
        <div class="stat-label">Classes Today</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $totalClientBookings; ?></div>
        <div class="stat-label">Total Client Bookings</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $totalSessionsConducted; ?></div>
        <div class="stat-label">Sessions Conducted</div>
    </div>
    <div class="stat-card">
        <div class="stat-number">4.7 <i class="fas fa-star" style="color: #ffd700;"></i></div>
        <div class="stat-label">Average Rating</div>
    </div>
</div>

<div class="card">
    <h2 class="card-title">Today's Schedule</h2>
    <table class="sessions-table">
        <thead>
            <tr><th>Time</th><th>Class</th><th>Room</th><th>Bookings</th><th>Action</th></tr>
        </thead>
        <tbody>
            <?php if(empty($todaysSchedule)): ?>
                <tr><td colspan="5" style="text-align: center;">No classes scheduled for today.</td></tr>
            <?php else: ?>
                <?php foreach($todaysSchedule as $session): ?>
                <tr>
                    <td><?php echo format_time($session['Time']); ?></td>
                    <td><?php echo htmlspecialchars($session['ClassName']); ?></td>
                    <td><?php echo htmlspecialchars($session['Room']); ?></td>
                    <td><?php echo $session['CurrentBookings'] . ' / ' . $session['MaxCapacity']; ?></td>
                    <td><a href="attendance.php?session_id=<?php echo $session['SessionID']; ?>" class="btn btn-primary btn-sm">Take Attendance</a></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2 class="card-title">Upcoming Classes</h2>
    <table class="sessions-table">
        <thead>
            <tr><th>Date & Time</th><th>Class</th></tr>
        </thead>
        <tbody>
             <?php if(empty($upcomingClasses)): ?>
                <tr><td colspan="2" style="text-align: center;">No upcoming classes found.</td></tr>
            <?php else: ?>
                <?php foreach($upcomingClasses as $class_item): ?>
                <tr>
                    <td><?php echo format_date($class_item['SessionDate']) . ' at ' . format_time($class_item['Time']); ?></td>
                    <td><?php echo htmlspecialchars($class_item['ClassName']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/trainer_footer.php'; ?>
