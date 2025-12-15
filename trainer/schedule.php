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
        SELECT s.SessionID, s.SessionDate, s.Time, s.Room, s.Status, s.CurrentBookings, a.ClassName, a.MaxCapacity
        FROM sessions s
        JOIN activities a ON s.ClassID = a.ClassID
        WHERE s.TrainerID = ?
        ORDER BY s.SessionDate DESC, s.Time DESC
    ");
    $stmt->execute([$trainerId]);
    $allSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch schedule data: ' . $e->getMessage()];
    $allSessions = [];
}

include 'includes/trainer_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">My Schedule</h1>
</div>


<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        All My Sessions
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
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
                        <tr><td colspan="6" class="text-center">You have no sessions assigned to you.</td></tr>
                    <?php else: ?>
                        <?php foreach($allSessions as $session): ?>
                            <tr>
                                <td><?php echo format_date($session['SessionDate']); ?> at <?php echo format_time($session['Time']); ?></td>
                                <td><?php echo htmlspecialchars($session['ClassName']); ?></td>
                                <td><?php echo htmlspecialchars($session['Room']); ?></td>
                                <td><?php echo $session['CurrentBookings'] . ' / ' . $session['MaxCapacity']; ?></td>
                                <td>
                                    <?php
                                    $statusClass = 'bg-secondary';
                                    if ($session['Status'] === 'scheduled') $statusClass = 'bg-success';
                                    if ($session['Status'] === 'cancelled') $statusClass = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?> text-capitalize"><?php echo htmlspecialchars($session['Status']); ?></span>
                                </td>
                                <td>
                                    <?php if ($session['Status'] === 'scheduled'): ?>
                                        <a href="attendance.php?session_id=<?php echo $session['SessionID']; ?>" class="btn btn-primary btn-sm">Attendance</a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/trainer_footer.php'; ?>
