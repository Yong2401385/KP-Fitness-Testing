<?php
define('PAGE_TITLE', 'Session Scheduling');
require_once '../includes/config.php';
require_admin();

$feedback = [];
$edit_session = null;

// --- Handle Form Submissions ---

// Handle Create or Update Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_session'])) {
    $classId = intval($_POST['classId']);
    $trainerId = intval($_POST['trainerId']);
    $sessionDate = sanitize_input($_POST['sessionDate']);
    $time = sanitize_input($_POST['time']);
    $room = sanitize_input($_POST['room']);
    $sessionId = isset($_POST['sessionId']) ? intval($_POST['sessionId']) : null;

    // Validation
    if (empty($classId) || empty($trainerId) || empty($sessionDate) || empty($time) || empty($room)) {
        $feedback = ['type' => 'error', 'message' => 'Please fill in all required fields.'];
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
            $feedback = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

// Handle Cancel/Reactivate Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['cancel_session']) || isset($_POST['reactivate_session']))) {
    $sessionId = intval($_POST['sessionId']);
    $newStatus = isset($_POST['cancel_session']) ? 'cancelled' : 'scheduled';
    $action = $newStatus === 'cancelled' ? 'cancelled' : 'reactivated';
    try {
        $stmt = $pdo->prepare("UPDATE sessions SET Status = ? WHERE SessionID = ?");
        if ($stmt->execute([$newStatus, $sessionId])) {
            $feedback = ['type' => 'success', 'message' => "Session has been $action."];
        }
    } catch (PDOException $e) {
        $feedback = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// --- Fetch Data for Display ---
try {
    // Fetch sessions
    $stmt = $pdo->prepare("
        SELECT s.*, c.ClassName, u.FullName as TrainerName 
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
    $feedback = ['type' => 'error', 'message' => 'Could not fetch data: ' . $e->getMessage()];
    $sessions = $active_classes = $active_trainers = [];
}

include 'includes/admin_header.php';
?>

<style>
.card {
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
}
.card-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 1.5rem;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    align-items: end;
}
.form-group { display: flex; flex-direction: column; }
.form-group label { margin-bottom: 0.5rem; font-weight: 600; }
.form-group input, .form-group select {
    width: 100%;
    padding: 0.75rem;
    background: var(--dark-bg);
    border: 2px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-light);
    font-size: 1rem;
}
.table-container { overflow-x: auto; }
.sessions-table { width: 100%; border-collapse: collapse; }
.sessions-table th, .sessions-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}
.sessions-table th { color: var(--primary-color); }
.status-scheduled { color: var(--success-color); }
.status-cancelled { color: var(--error-color); }
</style>

<div class="page-header">
    <h1>Session Scheduling</h1>
    <p>Schedule class sessions with trainers, dates, and times.</p>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?>">
        <?php echo $feedback['message']; ?>
    </div>
<?php endif; ?>

<!-- Schedule Session Form -->
<div class="card">
    <h2 class="card-title">Schedule New Session</h2>
    <form action="sessions.php" method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label for="classId">Class</label>
                <select id="classId" name="classId" required>
                    <option value="">Select a class...</option>
                    <?php foreach ($active_classes as $class): ?>
                        <option value="<?php echo $class['ClassID']; ?>"><?php echo htmlspecialchars($class['ClassName']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="trainerId">Trainer</label>
                <select id="trainerId" name="trainerId" required>
                    <option value="">Select a trainer...</option>
                     <?php foreach ($active_trainers as $trainer): ?>
                        <option value="<?php echo $trainer['UserID']; ?>"><?php echo htmlspecialchars($trainer['FullName']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="sessionDate">Date</label>
                <input type="date" id="sessionDate" name="sessionDate" required>
            </div>
            <div class="form-group">
                <label for="time">Time</label>
                <input type="time" id="time" name="time" required>
            </div>
            <div class="form-group">
                <label for="room">Room</label>
                <input type="text" id="room" name="room" placeholder="e.g., Studio A" required>
            </div>
            <div class="form-group">
                <button type="submit" name="save_session" class="btn btn-primary" style="width: 100%; padding: 0.8rem;">Schedule Session</button>
            </div>
        </div>
    </form>
</div>

<!-- Scheduled Sessions List -->
<div class="card">
    <h2 class="card-title">Scheduled Sessions</h2>
    <div class="table-container">
        <table class="sessions-table">
            <thead>
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
                        <td><?php echo $session['CurrentBookings']; ?> / <?php // Need to join classes table for MaxCapacity ?></td>
                        <td class="status-<?php echo strtolower($session['Status']); ?>" style="text-transform: capitalize;"><?php echo htmlspecialchars($session['Status']); ?></td>
                        <td>
                            <form action="sessions.php" method="POST" style="display:inline;">
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

<?php include 'includes/admin_footer.php'; ?>
