<?php
define('PAGE_TITLE', 'Historical Attendance');
require_once '../includes/config.php';
require_trainer();

$trainerId = $_SESSION['UserID'];
$feedback = [];

// Fetch all past sessions for this trainer
try {
    $stmt = $pdo->prepare("
        SELECT s.SessionID, s.SessionDate, s.Time, a.ClassName, a.DifficultyLevel, s.Room, s.Status
        FROM sessions s
        JOIN activities a ON s.ClassID = a.ClassID
        WHERE s.TrainerID = ? AND s.SessionDate < CURDATE()
        ORDER BY s.SessionDate DESC, s.Time DESC
    ");
    $stmt->execute([$trainerId]);
    $pastSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch attendance for each past session
    foreach ($pastSessions as &$session) {
        $stmt = $pdo->prepare("
            SELECT u.FullName, r.Status AS BookingStatus, a.Status AS AttendanceStatus, a.Notes
            FROM reservations r
            JOIN users u ON r.UserID = u.UserID
            LEFT JOIN attendance a ON r.ReservationID = a.ReservationID -- Assuming ReservationID in attendance now
            WHERE r.SessionID = ?
            ORDER BY u.FullName
        ");
        $stmt->execute([$session['SessionID']]);
        $session['attendance_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($session); // Break the reference

} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch historical attendance: ' . $e->getMessage()];
    $pastSessions = [];
}


include 'includes/trainer_header.php';
?>

<div class="d-flex justify-content-between align-items-center pb-3 mb-4 border-bottom">
    <h1 class="h2">Historical Attendance</h1>
    <p class="lead text-body-secondary m-0">Review past class attendance records.</p>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (empty($pastSessions)): ?>
    <div class="alert alert-info text-center">
        No past sessions found to display attendance.
    </div>
<?php else: ?>
    <div class="accordion" id="attendanceAccordion">
        <?php foreach ($pastSessions as $index => $session): ?>
            <div class="accordion-item text-bg-dark">
                <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                    <button class="accordion-button <?php echo ($index == 0) ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo ($index == 0) ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <span><?php echo htmlspecialchars($session['ClassName']); ?> (<?php echo format_date($session['SessionDate']); ?> at <?php echo format_time($session['Time']); ?>)</span>
                            <span class="badge bg-secondary text-capitalize me-2"><?php echo htmlspecialchars($session['Status']); ?></span>
                        </div>
                    </button>
                </h2>
                <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo ($index == 0) ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#attendanceAccordion">
                    <div class="accordion-body">
                        <?php if (empty($session['attendance_records'])): ?>
                            <p class="text-center text-body-secondary">No booking records for this session.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Client Name</th>
                                            <th>Booking Status</th>
                                            <th>Attendance Status</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($session['attendance_records'] as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['FullName']); ?></td>
                                                <td><span class="badge bg-info text-capitalize"><?php echo htmlspecialchars($record['BookingStatus']); ?></span></td>
                                                <td>
                                                    <?php if ($record['AttendanceStatus']): ?>
                                                        <span class="badge bg-<?php echo ($record['AttendanceStatus'] === 'present' ? 'success' : ($record['AttendanceStatus'] === 'absent' ? 'danger' : 'warning')); ?> text-capitalize">
                                                            <?php echo htmlspecialchars($record['AttendanceStatus']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Not Marked</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['Notes'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'includes/trainer_footer.php'; ?>
