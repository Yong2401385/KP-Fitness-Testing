<?php
define('PAGE_TITLE', 'Attendance');
require_once '../includes/config.php';
require_trainer();

$trainerId = $_SESSION['UserID'];
$sessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$mode = isset($_GET['mode']) ? $_GET['mode'] : ''; 

$feedback = [];

// Determine View Mode
// session_list: No session selected
// mark_attendance: Session selected & mode is not 'view'
// view_details: Session selected & mode IS 'view'
if ($sessionId > 0) {
    if ($mode === 'view') {
        $viewMode = 'view_details';
    } else {
        $viewMode = 'mark_attendance';
    }
} else {
    $viewMode = 'session_list';
}

// --- If viewing session list, fetch all trainer's sessions ---
if ($viewMode === 'session_list') {
    try {
        // Filter Date (Default to Today)
        $filterDate = $_GET['date'] ?? date('Y-m-d');

        // Fetch sessions for the specific date
        $stmt = $pdo->prepare("
            SELECT s.SessionID, s.SessionDate, s.StartTime, s.Room, s.CurrentBookings, s.Status,
                   c.ClassName, c.MaxCapacity
            FROM sessions s
            JOIN activities c ON s.ClassID = c.ClassID
            WHERE s.TrainerID = ? AND s.SessionDate = ?
            ORDER BY s.StartTime
        ");
        $stmt->execute([$trainerId, $filterDate]);
        $filteredSessions = $stmt->fetchAll();
        
        // Removed old separate queries for todays, upcoming, completed
        
    } catch (PDOException $e) {
        $feedback = ['type' => 'danger', 'message' => 'Could not fetch sessions: ' . $e->getMessage()];
        $filteredSessions = [];
    }
}

// --- Security Check: Ensure this session belongs to the logged-in trainer ---
if ($viewMode === 'mark_attendance' || $viewMode === 'view_details') {
    try {
        $stmt = $pdo->prepare("SELECT SessionID, ClassID FROM sessions WHERE SessionID = ? AND TrainerID = ?");
        $stmt->execute([$sessionId, $trainerId]);
        $session = $stmt->fetch();
        if (!$session) {
            // If session doesn't exist or doesn't belong to trainer, redirect to session list
            redirect('attendance.php');
        }
    } catch (PDOException $e) {
        redirect('attendance.php'); // Redirect on error
    }
}

// --- Handle Form Submission (Only for mark_attendance mode) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance']) && $viewMode === 'mark_attendance') {
    validate_csrf_token($_POST['csrf_token']);
    // For checkboxes, only checked items are sent in $_POST['attendance']
    // keys are ReservationIDs, values are 'present'
    $attendance_data = $_POST['attendance'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // We need to handle BOTH checked (present) and unchecked (absent) items.
        // First, get all booked clients for this session to know who COULD be present.
        $stmt = $pdo->prepare("SELECT ReservationID, UserID FROM reservations WHERE SessionID = ? AND Status = 'booked'");
        $stmt->execute([$sessionId]);
        $all_reservations = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // map ReservationID => UserID

        foreach ($all_reservations as $reservationId => $userId) {
            // If ID is in POST data, they are PRESENT. If not, they are ABSENT.
            $status = array_key_exists($reservationId, $attendance_data) ? 'present' : 'absent';
            
            // Check if an attendance record already exists
            $stmt = $pdo->prepare("SELECT AttendanceID FROM attendance WHERE SessionID = ? AND UserID = ?");
            $stmt->execute([$sessionId, $userId]);
            $existing_record = $stmt->fetch();

            if ($existing_record) {
                // Update existing record
                $stmt = $pdo->prepare("UPDATE attendance SET Status = ? WHERE AttendanceID = ?");
                $stmt->execute([$status, $existing_record['AttendanceID']]);
            } else {
                // Insert new record
                $stmt = $pdo->prepare("INSERT INTO attendance (SessionID, UserID, Status) VALUES (?, ?, ?)");
                $stmt->execute([$sessionId, $userId, $status]);
            }

            // Sync to reservations table
            $resStatus = ($status === 'present') ? 'attended' : 'no_show';
            $stmt = $pdo->prepare("UPDATE reservations SET Status = ? WHERE SessionID = ? AND UserID = ?");
            $stmt->execute([$resStatus, $sessionId, $userId]);
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
if ($viewMode === 'mark_attendance' || $viewMode === 'view_details') {
    try {
        // Get session details
        $stmt = $pdo->prepare("
            SELECT s.SessionDate, s.StartTime, c.ClassName, s.SessionID
            FROM sessions s JOIN activities c ON s.ClassID = c.ClassID 
            WHERE s.SessionID = ?
        ");
        $stmt->execute([$sessionId]);
        $sessionDetails = $stmt->fetch();
        
        // Get booked clients for this session
        $stmt = $pdo->prepare("
            SELECT r.ReservationID, a.AttendanceDate as CheckInTime, u.UserID, u.FullName, a.Status as AttendanceStatus
            FROM reservations r 
            JOIN users u ON r.UserID = u.UserID 
            LEFT JOIN attendance a ON r.UserID = a.UserID AND r.SessionID = a.SessionID
            WHERE r.SessionID = ? AND r.Status = 'booked'
            ORDER BY a.AttendanceDate DESC, u.FullName
        ");
        $stmt->execute([$sessionId]);
        $bookedClients = $stmt->fetchAll();

    } catch (PDOException $e) {
        $feedback = ['type' => 'danger', 'message' => 'Could not fetch class list: ' . $e->getMessage()];
        $sessionDetails = [];
        $bookedClients = [];
    }
} else {
    // Initialize empty arrays for session list view
    $sessionDetails = [];
    $bookedClients = [];
}


include 'includes/trainer_header.php';
?>

<?php if ($viewMode === 'session_list'): ?>
    <!-- SESSION LIST VIEW -->
    <?php 
    $filterDate = $_GET['date'] ?? date('Y-m-d');
    ?>

    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-clipboard-check me-2"></i>Attendance Management</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <form action="attendance.php" method="GET" class="d-flex align-items-center">
                <label for="date" class="text-white me-2">Date:</label>
                <input type="date" class="form-control" name="date" id="date" value="<?php echo htmlspecialchars($filterDate); ?>" onchange="this.form.submit()">
            </form>
        </div>
    </div>

    <?php if (!empty($feedback)): ?>
        <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $feedback['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Filtered Sessions List -->
    <div class="card mb-4">
        <div class="card-header bg-primary">
            <i class="fas fa-calendar-day me-2 text-white"></i><span class="text-white">Sessions for <?php echo format_date($filterDate); ?></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Time</th>
                            <th>Class</th>
                            <th>Room</th>
                            <th>Bookings</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($filteredSessions)): ?>
                            <tr><td colspan="6" class="text-center text-muted">No sessions scheduled for this date.</td></tr>
                        <?php else: ?>
                            <?php foreach ($filteredSessions as $session): ?>
                                <tr>
                                    <td><strong><?php echo format_time($session['StartTime']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($session['ClassName']); ?></td>
                                    <td><?php echo htmlspecialchars($session['Room']); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo $session['CurrentBookings']; ?> / <?php echo $session['MaxCapacity']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($session['Status'] == 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($session['Status'] == 'cancelled'): ?>
                                            <span class="badge bg-danger">Cancelled</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Scheduled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <?php if ($session['Status'] != 'cancelled'): ?>
                                                <a href="attendance.php?session_id=<?php echo $session['SessionID']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-clipboard-check me-1"></i>Take Attendance
                                                </a>
                                            <?php endif; ?>
                                            <a href="attendance.php?session_id=<?php echo $session['SessionID']; ?>&mode=view" class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-eye me-1"></i>Details
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- DETAILS / MARK ATTENDANCE VIEW -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <div>
            <h1 class="h2"><?php echo $viewMode === 'mark_attendance' ? 'Mark Attendance' : 'Session Details'; ?></h1>
            <?php if($sessionDetails): ?>
                <p class="text-muted">
                    <strong>Class:</strong> <?php echo htmlspecialchars($sessionDetails['ClassName']); ?> | 
                    <strong>Date:</strong> <?php echo format_date($sessionDetails['SessionDate']); ?> at <?php echo format_time($sessionDetails['StartTime']); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php 
        // Show session code if session is live
        if ($sessionDetails) {
            $isLive = is_session_live($sessionDetails['SessionDate'], $sessionDetails['StartTime']);
            if ($isLive) {
                $sessionCode = get_or_create_session_code($sessionDetails['SessionID'], $pdo);
                if ($sessionCode) {
                    echo '<div class="session-code-box">';
                    echo '<small class="text-muted d-block">Session Code</small>';
                    echo '<div class="code">' . $sessionCode . '</div>';
                    echo '</div>';
                }
            }
        }
        ?>
    </div>

    <style>
    .session-code-box {
        background: #e7f5ff;
        border: 2px dashed #28a745;
        padding: 0.5rem 1.5rem;
        border-radius: 0.5rem;
        text-align: center;
    }

    .session-code-box .code {
        font-size: 2rem;
        font-weight: bold;
        color: #28a745;
        letter-spacing: 3px;
        font-family: 'Courier New', monospace;
    }

    .checked-in-badge {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        color: #155724;
        font-size: 0.85rem;
        font-weight: 500;
    }
    </style>

    <?php if (!empty($feedback)): ?>
        <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $feedback['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <a href="attendance.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Session List
        </a>
        <?php if($viewMode === 'view_details'): ?>
            <a href="attendance.php?session_id=<?php echo $sessionId; ?>" class="btn btn-primary float-end">
                <i class="fas fa-clipboard-check me-1"></i>Take Attendance
            </a>
        <?php endif; ?>
    </div>

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
                                <th>Check-In Status</th>
                                <th>Attendance Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bookedClients)): ?>
                                <tr><td colspan="3" class="text-center">No clients have booked this session.</td></tr>
                            <?php else: ?>
                                <?php foreach ($bookedClients as $client): 
                                    $hasCheckedIn = !empty($client['CheckInTime']);
                                ?>
                                    <tr <?php echo $hasCheckedIn ? 'style="background-color: #f8fff9;"' : ''; ?>>
                                        <td>
                                            <?php echo htmlspecialchars($client['FullName']); ?>
                                            <?php if ($hasCheckedIn): ?>
                                                <i class="fas fa-check-circle text-success ms-2" title="Checked in at <?php echo format_time($client['CheckInTime']); ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($hasCheckedIn): ?>
                                                <span class="checked-in-badge">
                                                    <i class="fas fa-user-check"></i> Checked in at <?php echo format_time($client['CheckInTime']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Not checked in</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($viewMode === 'mark_attendance'): ?>
                                                <div class="form-check form-switch fs-4">
                                                    <input class="form-check-input" type="checkbox" name="attendance[<?php echo $client['ReservationID']; ?>]" 
                                                           value="present" id="att_<?php echo $client['ReservationID']; ?>"
                                                           <?php echo ($client['AttendanceStatus'] ?? '') === 'present' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label fs-6 pt-1 ms-2" for="att_<?php echo $client['ReservationID']; ?>">
                                                        <?php echo ($client['AttendanceStatus'] ?? '') === 'present' ? 'Present' : 'Absent'; ?>
                                                    </label>
                                                </div>
                                            <?php else: ?>
                                                <?php 
                                                    $status = $client['AttendanceStatus'] ?? 'pending';
                                                    $badgeClass = 'bg-secondary';
                                                    if ($status === 'present') $badgeClass = 'bg-success';
                                                    if ($status === 'absent') $badgeClass = 'bg-danger';
                                                    if ($status === 'late') $badgeClass = 'bg-warning text-dark';
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?> text-uppercase"><?php echo $status; ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($bookedClients) && $viewMode === 'mark_attendance'): ?>
                    <button type="submit" name="mark_attendance" class="btn btn-primary mt-3">Save Attendance</button>
                    
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const checkboxes = document.querySelectorAll('input[type="checkbox"][name^="attendance"]');
                        checkboxes.forEach(function(checkbox) {
                            checkbox.addEventListener('change', function() {
                                const label = this.nextElementSibling;
                                if (this.checked) {
                                    label.textContent = 'Present';
                                } else {
                                    label.textContent = 'Absent';
                                }
                            });
                        });
                    });
                    </script>
                <?php endif; ?>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/trainer_footer.php'; ?>
