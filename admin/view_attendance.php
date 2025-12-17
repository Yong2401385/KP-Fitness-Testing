<?php
define('PAGE_TITLE', 'View Session Attendance');
require_once '../includes/config.php';
require_admin();

$sessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$feedback = [];

if ($sessionId <= 0) {
    $feedback = ['type' => 'danger', 'message' => 'Invalid session ID.'];
    $sessionDetails = [];
    $bookedClients = [];
} else {
    try {
        // Get session details
        $stmt = $pdo->prepare("
            SELECT s.SessionID, s.SessionDate, s.StartTime, s.EndTime, s.Room, s.Status, c.ClassName, c.MaxCapacity
            FROM sessions s 
            JOIN activities c ON s.ClassID = c.ClassID 
            WHERE s.SessionID = ?
        ");
        $stmt->execute([$sessionId]);
        $sessionDetails = $stmt->fetch();
        
        if (!$sessionDetails) {
            $feedback = ['type' => 'danger', 'message' => 'Session not found.'];
            $bookedClients = [];
        } else {
            // Get booked clients and their attendance
            $stmt = $pdo->prepare("
                SELECT r.ReservationID, a.AttendanceDate as CheckInTime, u.UserID, u.FullName, u.Email, 
                       a.Status as AttendanceStatus, r.Status as ReservationStatus
                FROM reservations r 
                JOIN users u ON r.UserID = u.UserID 
                LEFT JOIN attendance a ON r.UserID = a.UserID AND r.SessionID = a.SessionID
                WHERE r.SessionID = ? AND r.Status IN ('booked', 'attended', 'no_show')
                ORDER BY a.AttendanceDate DESC, u.FullName
            ");
            $stmt->execute([$sessionId]);
            $bookedClients = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $feedback = ['type' => 'danger', 'message' => 'Could not fetch attendance data: ' . $e->getMessage()];
        $sessionDetails = [];
        $bookedClients = [];
    }
}

include 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Session Attendance</h1>
    <a href="sessions.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back to Sessions
    </a>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($sessionDetails): ?>
    <!-- Session Information -->
    <div class="mb-4">
        <div class="row">
            <div class="col-md-6">
                <h4><?php echo htmlspecialchars($sessionDetails['ClassName']); ?></h4>
                <p class="text-muted mb-2">
                    <strong>Date:</strong> <?php echo format_date($sessionDetails['SessionDate']); ?><br>
                    <strong>Time:</strong> <?php echo format_time($sessionDetails['StartTime']); ?> - <?php echo format_time($sessionDetails['EndTime']); ?><br>
                    <strong>Room:</strong> <?php echo htmlspecialchars($sessionDetails['Room']); ?><br>
                    <strong>Status:</strong> 
                    <span class="badge bg-<?php echo $sessionDetails['Status'] === 'completed' ? 'success' : ($sessionDetails['Status'] === 'cancelled' ? 'danger' : 'primary'); ?>">
                        <?php echo ucfirst($sessionDetails['Status']); ?>
                    </span>
                </p>
            </div>
            <div class="col-md-6">
                <h5>Attendance Summary</h5>
                <p class="mb-1">
                    <strong>Total Booked:</strong> <?php echo count($bookedClients); ?> / <?php echo $sessionDetails['MaxCapacity']; ?><br>
                    <strong>Present:</strong> <?php echo count(array_filter($bookedClients, function($c) { return $c['AttendanceStatus'] === 'present'; })); ?><br>
                    <strong>Absent:</strong> <?php echo count(array_filter($bookedClients, function($c) { return $c['AttendanceStatus'] === 'absent' || ($c['CheckInTime'] === null && $c['ReservationStatus'] === 'booked'); })); ?><br>
                    <strong>Late:</strong> <?php echo count(array_filter($bookedClients, function($c) { return $c['AttendanceStatus'] === 'late'; })); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Attendance List -->
    <div class="mb-4">
        <h3 class="mb-3">Attendees</h3>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Check-in Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookedClients)): ?>
                        <tr>
                            <td colspan="4" class="text-center">No bookings for this session.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookedClients as $client): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($client['FullName']); ?></td>
                                <td><?php echo htmlspecialchars($client['Email']); ?></td>
                                <td>
                                    <?php if ($client['CheckInTime']): ?>
                                        <?php echo format_date($client['CheckInTime']) . ' at ' . format_time($client['CheckInTime']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not checked in</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($client['AttendanceStatus'] === 'present'): ?>
                                        <span class="badge bg-success">Present</span>
                                    <?php elseif ($client['AttendanceStatus'] === 'late'): ?>
                                        <span class="badge bg-warning">Late</span>
                                    <?php elseif ($client['AttendanceStatus'] === 'absent'): ?>
                                        <span class="badge bg-danger">Absent</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Marked</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/admin_footer.php'; ?>

