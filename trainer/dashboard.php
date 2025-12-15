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
        SELECT s.SessionID, s.Time, a.ClassName, s.Room, s.CurrentBookings, a.MaxCapacity
        FROM sessions s
        JOIN activities a ON s.ClassID = a.ClassID
        WHERE s.TrainerID = ? AND s.SessionDate = CURDATE() AND s.Status = 'scheduled'
        ORDER BY s.Time
    ");
    $stmt->execute([$trainerId]);
    $todaysSchedule = $stmt->fetchAll();
    
    // Upcoming classes (next 5)
    $stmt = $pdo->prepare("
        SELECT s.SessionDate, s.Time, a.ClassName
        FROM sessions s
        JOIN activities a ON s.ClassID = a.ClassID
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
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch dashboard data: ' . $e->getMessage()];
    $todaysSchedule = $upcomingClasses = [];
    $totalSessionsConducted = $totalClientBookings = 0;
}

include 'includes/trainer_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-4 fw-bold text-warning"><?php echo count($todaysSchedule); ?></div>
                <h6>Classes Today</h6>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-4 fw-bold text-warning"><?php echo $totalClientBookings; ?></div>
                <h6>Total Client Bookings</h6>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-4 fw-bold text-warning"><?php echo $totalSessionsConducted; ?></div>
                <h6>Sessions Conducted</h6>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-4 fw-bold text-warning">4.7 <i class="fas fa-star text-warning"></i></div>
                <h6>Average Rating</h6>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        Today's Schedule
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr><th>Time</th><th>Class</th><th>Room</th><th>Bookings</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($todaysSchedule)): ?>
                        <tr><td colspan="5" class="text-center">No classes scheduled for today.</td></tr>
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
    </div>
</div>

<div class="card">
    <div class="card-header">
        Upcoming Classes
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr><th>Date & Time</th><th>Class</th></tr>
                </thead>
                <tbody>
                     <?php if(empty($upcomingClasses)): ?>
                        <tr><td colspan="2" class="text-center">No upcoming classes found.</td></tr>
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
    </div>
</div>

<?php include 'includes/trainer_footer.php'; ?>
