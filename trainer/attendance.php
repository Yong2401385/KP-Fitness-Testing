<?php
define('PAGE_TITLE', 'Mark Attendance');
require_once '../includes/config.php';
require_trainer();

$trainerId = $_SESSION['UserID'];
$sessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$feedback = [];

// --- Security Check: Ensure this session belongs to the logged-in trainer ---
try {
    $stmt = $pdo->prepare("SELECT SessionID, ClassID FROM sessions WHERE SessionID = ? AND TrainerID = ?");
    $stmt->execute([$sessionId, $trainerId]);
    $session = $stmt->fetch();
    if (!$session) {
        // If session doesn't exist or doesn't belong to trainer, deny access.
        redirect('dashboard.php');
    }
} catch (PDOException $e) {
    redirect('dashboard.php'); // Redirect on error
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    validate_csrf_token($_POST['csrf_token']);
    $attendance_data = $_POST['attendance'] ?? [];
    
    try {
        $pdo->beginTransaction();
        foreach ($attendance_data as $reservationId => $status) {
            $reservationId = intval($reservationId);
            $status = sanitize_input($status);

            // Check if an attendance record already exists
            $stmt = $pdo->prepare("SELECT AttendanceID FROM attendance WHERE SessionID = ? AND UserID = (SELECT UserID FROM reservations WHERE ReservationID = ?)");
            $stmt->execute([$sessionId, $reservationId]);
            $existing_record = $stmt->fetch();

            if ($existing_record) {
                // Update existing record
                $stmt = $pdo->prepare("UPDATE attendance SET Status = ? WHERE AttendanceID = ?");
                $stmt->execute([$status, $existing_record['AttendanceID']]);
            } else {
                // Insert new record
                $stmt = $pdo->prepare("INSERT INTO attendance (SessionID, UserID, Status) SELECT ?, UserID, ? FROM reservations WHERE ReservationID = ?");
                $stmt->execute([$sessionId, $status, $reservationId]);
            }
        }
        
        // Mark session as completed
        $stmt = $pdo->prepare("UPDATE sessions SET Status = 'completed' WHERE SessionID = ?");
        $stmt->execute([$sessionId]);
        
        $pdo->commit();
        $feedback = ['type' => 'success', 'message' => 'Attendance has been marked successfully.'];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $feedback = ['type' => 'danger', 'message' => 'A database error occurred: ' . $e->getMessage()];
    }
}


// --- Fetch Data for Display ---
try {
    // Get session details
    $stmt = $pdo->prepare("
        SELECT s.SessionDate, s.Time, a.ClassName 
        FROM sessions s JOIN activities a ON s.ClassID = a.ClassID 
        WHERE s.SessionID = ?
    ");
    $stmt->execute([$sessionId]);
    $sessionDetails = $stmt->fetch();
    
    // Get booked clients for this session
    $stmt = $pdo->prepare("
        SELECT r.ReservationID, u.UserID, u.FullName, a.Status as AttendanceStatus
        FROM reservations r 
        JOIN users u ON r.UserID = u.UserID 
        LEFT JOIN attendance a ON r.UserID = a.UserID AND r.SessionID = a.SessionID
        WHERE r.SessionID = ? AND r.Status = 'booked'
        ORDER BY u.FullName
    ");
    $stmt->execute([$sessionId]);
    $bookedClients = $stmt->fetchAll();

} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch class list: ' . $e->getMessage()];
    $sessionDetails = [];
    $bookedClients = [];
}

include 'includes/trainer_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2">Mark Attendance</h1>
        <?php if($sessionDetails): ?>
            <p class="text-muted">
                <strong>Class:</strong> <?php echo htmlspecialchars($sessionDetails['ClassName']); ?> | 
                <strong>Date:</strong> <?php echo format_date($sessionDetails['SessionDate']); ?> at <?php echo format_time($sessionDetails['Time']); ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        Client List
    </div>
    <div class="card-body">
        <form action="attendance.php?session_id=<?php echo $sessionId; ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Client Name</th>
                            <th>Attendance Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookedClients)): ?>
                            <tr><td colspan="2" class="text-center">No clients have booked this session.</td></tr>
                        <?php else: ?>
                            <?php foreach ($bookedClients as $client): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($client['FullName']); ?></td>
                                    <td>
                                        <select class="form-select" name="attendance[<?php echo $client['ReservationID']; ?>]">
                                            <option value="present" <?php echo ($client['AttendanceStatus'] ?? '') === 'present' ? 'selected' : ''; ?>>Present</option>
                                            <option value="absent" <?php echo ($client['AttendanceStatus'] ?? '') === 'absent' ? 'selected' : ''; ?>>Absent</option>
                                            <option value="late" <?php echo ($client['AttendanceStatus'] ?? '') === 'late' ? 'selected' : ''; ?>>Late</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($bookedClients)): ?>
                <button type="submit" name="mark_attendance" class="btn btn-primary mt-3">Save Attendance</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php include 'includes/trainer_footer.php'; ?>
