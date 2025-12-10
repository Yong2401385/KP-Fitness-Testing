<?php
define('PAGE_TITLE', 'My Schedule');
require_once '../includes/config.php';
require_trainer();

$trainerId = $_SESSION['UserID'];
$feedback = [];

// --- Fetch Data for Display ---
try {
    // Fetch all scheduled sessions for this trainer
    $stmt = $pdo->prepare("
        SELECT s.SessionID, s.SessionDate, s.Time, s.Room, s.Status, s.CurrentBookings, c.ClassName, c.MaxCapacity
        FROM sessions s
        JOIN classes c ON s.ClassID = c.ClassID
        WHERE s.TrainerID = ?
        ORDER BY s.SessionDate DESC, s.Time DESC
    ");
    $stmt->execute([$trainerId]);
    $allSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $feedback = ['type' => 'error', 'message' => 'Could not fetch schedule data: ' . $e->getMessage()];
    $allSessions = [];
}

include 'includes/trainer_header.php';
?>

<style>
.card { background: var(--light-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
.card-title { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); margin-bottom: 1.5rem; }
.table-container { overflow-x: auto; }
.schedule-table { width: 100%; border-collapse: collapse; }
.schedule-table th, .schedule-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}
.schedule-table th { color: var(--primary-color); }
.status-scheduled { color: var(--success-color, #51cf66); }
.status-cancelled { color: var(--error-color, #ff6b6b); }
.status-completed { color: var(--text-dark, #cccccc); }
</style>

<div class="page-header">
    <h1>My Schedule</h1>
    <p>A complete overview of all your past, present, and future classes.</p>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?>"><?php echo $feedback['message']; ?></div>
<?php endif; ?>

<div class="card">
    <h2 class="card-title">All My Sessions</h2>
    <div class="table-container">
        <table class="schedule-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Class</th>
                    <th>Room</th>
                    <th>Bookings</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allSessions)): ?>
                    <tr><td colspan="6" style="text-align: center;">You have no sessions assigned to you.</td></tr>
                <?php else: ?>
                    <?php foreach($allSessions as $session): ?>
                        <tr>
                            <td><?php echo format_date($session['SessionDate']); ?> at <?php echo format_time($session['Time']); ?></td>
                            <td><?php echo htmlspecialchars($session['ClassName']); ?></td>
                            <td><?php echo htmlspecialchars($session['Room']); ?></td>
                            <td><?php echo $session['CurrentBookings'] . ' / ' . $session['MaxCapacity']; ?></td>
                            <td class="status-<?php echo strtolower($session['Status']); ?>" style="text-transform: capitalize; font-weight: bold;">
                                <?php echo htmlspecialchars($session['Status']); ?>
                            </td>
                            <td>
                                <?php if ($session['Status'] === 'scheduled'): ?>
                                    <a href="attendance.php?session_id=<?php echo $session['SessionID']; ?>" class="btn btn-primary btn-sm">Attendance</a>
                                <?php else: ?>
                                    <span style="color: var(--text-dark);">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/trainer_footer.php'; ?>
