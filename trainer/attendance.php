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
        $feedback = ['type' => 'error', 'message' => 'A database error occurred: ' . $e->getMessage()];
    }
}


// --- Fetch Data for Display ---
try {
    // Get session details
    $stmt = $pdo->prepare("
        SELECT s.SessionDate, s.Time, c.ClassName 
        FROM sessions s JOIN classes c ON s.ClassID = c.ClassID 
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
    $feedback = ['type' => 'error', 'message' => 'Could not fetch class list: ' . $e->getMessage()];
    $sessionDetails = [];
    $bookedClients = [];
}

include 'includes/trainer_header.php';
?>

<style>
.card { background: var(--light-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
.card-title { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); margin-bottom: 1.5rem; }
.attendance-table { width: 100%; border-collapse: collapse; }
.attendance-table th, .attendance-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
.attendance-table th { color: var(--primary-color); }
.attendance-table td:last-child { display: flex; gap: 1rem; }
.attendance-table select {
    width: 100%; padding: 0.75rem; background: var(--dark-bg); 
    border: 2px solid var(--border-color); border-radius: 8px; 
    color: var(--text-light); font-size: 1rem;
}
</style>

<div class="page-header">
    <h1>Mark Attendance</h1>
    <?php if($sessionDetails): ?>
        <p>
            <strong>Class:</strong> <?php echo htmlspecialchars($sessionDetails['ClassName']); ?> <br>
            <strong>Date:</strong> <?php echo format_date($sessionDetails['SessionDate']); ?> at <?php echo format_time($sessionDetails['Time']); ?>
        </p>
    <?php endif; ?>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?>"><?php echo $feedback['message']; ?></div>
<?php endif; ?>

<div class="card">
    <h2 class="card-title">Client List</h2>
    <form action="attendance.php?session_id=<?php echo $sessionId; ?>" method="POST">
        <table class="attendance-table">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Attendance Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookedClients)): ?>
                    <tr><td colspan="2" style="text-align: center;">No clients have booked this session.</td></tr>
                <?php else: ?>
                    <?php foreach ($bookedClients as $client): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($client['FullName']); ?></td>
                            <td>
                                <select name="attendance[<?php echo $client['ReservationID']; ?>]">
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
        <?php if (!empty($bookedClients)): ?>
            <button type="submit" name="mark_attendance" class="btn btn-primary" style="margin-top: 2rem;">Save Attendance</button>
        <?php endif; ?>
    </form>
</div>

<?php include 'includes/trainer_footer.php'; ?>
