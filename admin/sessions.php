<?php
define('PAGE_TITLE', 'Session Scheduling');
require_once '../includes/config.php';
require_admin();

$feedback = [];
$edit_session = null;

// --- Handle Form Submissions ---

// Handle Create or Update Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_session'])) {
    validate_csrf_token($_POST['csrf_token']);
    $classId = intval($_POST['classId']);
    $trainerId = intval($_POST['trainerId']);
    $sessionDate = sanitize_input($_POST['sessionDate']);
    $time = sanitize_input($_POST['time']);
    $room = sanitize_input($_POST['room']);
    $sessionId = isset($_POST['sessionId']) ? intval($_POST['sessionId']) : null;

    // Validation
    if (empty($classId) || empty($trainerId) || empty($sessionDate) || empty($time) || empty($room)) {
        $feedback = ['type' => 'danger', 'message' => 'Please fill in all required fields.'];
    } else {
        try {
            if ($sessionId) { // Update
                $stmt = $pdo->prepare("UPDATE sessions SET ClassID = ?, TrainerID = ?, SessionDate = ?, Time = ?, Room = ? WHERE SessionID = ?");
                if ($stmt->execute([$classId, $trainerId, $sessionDate, $time, $room, $sessionId])) {
                    $feedback = ['type' => 'success', 'message' => 'Session updated successfully.'];
                }
            } else { // Create
                $stmt = $pdo->prepare("INSERT INTO sessions (ClassID, TrainerID, SessionDate, Time, Room) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$classId, $trainerId, $sessionDate, $time, $room])) {
                    $feedback = ['type' => 'success', 'message' => 'Session scheduled successfully.'];
                }
            }
        } catch (PDOException $e) {
            $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

// Handle Cancel/Reactivate Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['cancel_session']) || isset($_POST['reactivate_session']))) {
    validate_csrf_token($_POST['csrf_token']);
    $sessionId = intval($_POST['sessionId']);
    $newStatus = isset($_POST['cancel_session']) ? 'cancelled' : 'scheduled';
    $action = $newStatus === 'cancelled' ? 'cancelled' : 'reactivated';
    try {
        $stmt = $pdo->prepare("UPDATE sessions SET Status = ? WHERE SessionID = ?");
        if ($stmt->execute([$newStatus, $sessionId])) {
            $feedback = ['type' => 'success', 'message' => "Session has been $action."];
        }
    } catch (PDOException $e) {
        $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// --- Fetch Data for Display ---
try {
    // Fetch sessions
    $stmt = $pdo->prepare("
        SELECT s.*, c.ClassName, c.MaxCapacity, u.FullName as TrainerName 
        FROM sessions s
        JOIN classes c ON s.ClassID = c.ClassID
        JOIN users u ON s.TrainerID = u.UserID
        WHERE u.Role = 'trainer'
        ORDER BY s.SessionDate DESC, s.Time DESC
    ");
    $stmt->execute();
    $sessions = $stmt->fetchAll();

    // Fetch active classes for the dropdown
    $stmt_classes = $pdo->prepare("SELECT ClassID, ClassName FROM classes WHERE IsActive = TRUE ORDER BY ClassName");
    $stmt_classes->execute();
    $active_classes = $stmt_classes->fetchAll();
    
    // Fetch active trainers for the dropdown
    $stmt_trainers = $pdo->prepare("SELECT UserID, FullName FROM users WHERE IsActive = TRUE AND Role = 'trainer' ORDER BY FullName");
    $stmt_trainers->execute();
    $active_trainers = $stmt_trainers->fetchAll();

} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch data: ' . $e->getMessage()];
    $sessions = $active_classes = $active_trainers = [];
}

include 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Session Scheduling</h1>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Schedule Session Form -->
<div class="card mb-4">
    <div class="card-header">
        Schedule New Session
    </div>
    <div class="card-body">
        <form action="sessions.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="classId" class="form-label">Class</label>
                    <select class="form-select" id="classId" name="classId" required>
                        <option value="">Select a class...</option>
                        <?php foreach ($active_classes as $class): ?>
                            <option value="<?php echo $class['ClassID']; ?>"><?php echo htmlspecialchars($class['ClassName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="trainerId" class="form-label">Trainer</label>
                    <select class="form-select" id="trainerId" name="trainerId" required>
                        <option value="">Select a trainer...</option>
                         <?php foreach ($active_trainers as $trainer): ?>
                            <option value="<?php echo $trainer['UserID']; ?>"><?php echo htmlspecialchars($trainer['FullName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="sessionDate" class="form-label">Date</label>
                    <input type="date" class="form-control" id="sessionDate" name="sessionDate" required>
                </div>
                <div class="col-md-4">
                    <label for="time" class="form-label">Time</label>
                    <input type="time" class="form-control" id="time" name="time" required>
                </div>
                <div class="col-md-4">
                    <label for="room" class="form-label">Room</label>
                    <input type="text" class="form-control" id="room" name="room" placeholder="e.g., Studio A" required>
                </div>
                <div class="col-12">
                    <button type="submit" name="save_session" class="btn btn-primary">Schedule Session</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Scheduled Sessions List -->
<div class="card">
    <div class="card-header">
        Scheduled Sessions
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Date & Time</th>
                        <th>Class</th>
                        <th>Trainer</th>
                        <th>Room</th>
                        <th>Bookings</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td><?php echo format_date($session['SessionDate']); ?> at <?php echo format_time($session['Time']); ?></td>
                            <td><?php echo htmlspecialchars($session['ClassName']); ?></td>
                            <td><?php echo htmlspecialchars($session['TrainerName']); ?></td>
                            <td><?php echo htmlspecialchars($session['Room']); ?></td>
                            <td><?php echo $session['CurrentBookings']; ?> / <?php echo $session['MaxCapacity']; ?></td>
                            <td>
                                <?php 
                                $statusClass = 'bg-secondary';
                                if ($session['Status'] === 'scheduled') $statusClass = 'bg-success';
                                if ($session['Status'] === 'cancelled') $statusClass = 'bg-danger';
                                ?>
                                <span class="badge <?php echo $statusClass; ?> text-capitalize"><?php echo htmlspecialchars($session['Status']); ?></span>
                            </td>
                            <td>
                                <form action="sessions.php" method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                    <input type="hidden" name="sessionId" value="<?php echo $session['SessionID']; ?>">
                                    <?php if ($session['Status'] === 'scheduled'): ?>
                                        <button type="submit" name="cancel_session" class="btn btn-danger btn-sm">Cancel</button>
                                    <?php else: ?>
                                        <button type="submit" name="reactivate_session" class="btn btn-success btn-sm">Reactivate</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
