<?php
define('PAGE_TITLE', 'Book Classes');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];
$feedback = [];

// --- Handle Actions ---

// Handle Booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_session'])) {
    validate_csrf_token($_POST['csrf_token']);
    $sessionId = intval($_POST['sessionId']);
    
    // Check for active membership first
    $stmt = $pdo->prepare("SELECT u.MembershipID FROM users u WHERE u.UserID = ?");
    $stmt->execute([$userId]);
    $userMembership = $stmt->fetch();

    if (!$userMembership['MembershipID']) {
        $feedback = ['type' => 'danger', 'message' => 'You need an active membership to book classes. Please purchase a plan first.'];
    } else {
        try {
            $pdo->beginTransaction();
            // Check session capacity (lock the row for update)
            $stmt = $pdo->prepare("SELECT CurrentBookings, (SELECT MaxCapacity FROM classes c WHERE c.ClassID = s.ClassID) as MaxCapacity FROM sessions s WHERE s.SessionID = ? FOR UPDATE");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();

            if ($session['CurrentBookings'] >= $session['MaxCapacity']) {
                $feedback = ['type' => 'danger', 'message' => 'This class is already full.'];
            } else {
                // Check for duplicate booking
                $stmt = $pdo->prepare("SELECT ReservationID FROM reservations WHERE UserID = ? AND SessionID = ? AND Status = 'booked'");
                $stmt->execute([$userId, $sessionId]);
                if ($stmt->fetch()) {
                    $feedback = ['type' => 'danger', 'message' => 'You have already booked this session.'];
                } else {
                    // Create reservation
                    $stmt = $pdo->prepare("INSERT INTO reservations (UserID, SessionID, Status) VALUES (?, ?, 'booked')");
                    $stmt->execute([$userId, $sessionId]);
                    // Update count
                    $stmt = $pdo->prepare("UPDATE sessions SET CurrentBookings = CurrentBookings + 1 WHERE SessionID = ?");
                    $stmt->execute([$sessionId]);
                    $pdo->commit();
                    $feedback = ['type' => 'success', 'message' => 'Class booked successfully!'];
                }
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $feedback = ['type' => 'danger', 'message' => 'A database error occurred. Please try again.'];
        }
    }
}

// Handle Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    validate_csrf_token($_POST['csrf_token']);
    $reservationId = intval($_POST['reservationId']);
     try {
        $pdo->beginTransaction();
        // Get reservation details
        $stmt = $pdo->prepare("SELECT SessionID FROM reservations WHERE ReservationID = ? AND UserID = ? AND Status = 'booked'");
        $stmt->execute([$reservationId, $userId]);
        $reservation = $stmt->fetch();
        
        if ($reservation) {
            // Update reservation status
            $stmt = $pdo->prepare("UPDATE reservations SET Status = 'cancelled' WHERE ReservationID = ?");
            $stmt->execute([$reservationId]);
            // Decrement session booking count
            $stmt = $pdo->prepare("UPDATE sessions SET CurrentBookings = GREATEST(0, CurrentBookings - 1) WHERE SessionID = ?");
            $stmt->execute([$reservation['SessionID']]);
            $pdo->commit();
            $feedback = ['type' => 'success', 'message' => 'Your booking has been cancelled.'];
        }
    } catch(PDOException $e) {
        $pdo->rollBack();
        $feedback = ['type' => 'danger', 'message' => 'Could not cancel booking. Please try again.'];
    }
}

// --- Fetch Data for Display ---
// Fetch available sessions
$stmt = $pdo->prepare("
    SELECT s.*, c.ClassName, c.MaxCapacity, u.FullName as TrainerName 
    FROM sessions s
    JOIN classes c ON s.ClassID = c.ClassID
    JOIN users u ON s.TrainerID = u.UserID
    WHERE s.SessionDate >= CURDATE() AND s.Status = 'scheduled'
    ORDER BY s.SessionDate, s.Time
");
$stmt->execute();
$availableSessions = $stmt->fetchAll();

// Fetch user's current bookings
$stmt = $pdo->prepare("
    SELECT r.ReservationID, s.SessionDate, s.Time, c.ClassName, u.FullName as TrainerName
    FROM reservations r
    JOIN sessions s ON r.SessionID = s.SessionID
    JOIN classes c ON s.ClassID = c.ClassID
    JOIN users u ON s.TrainerID = u.UserID
    WHERE r.UserID = ? AND r.Status = 'booked' AND s.SessionDate >= CURDATE()
    ORDER BY s.SessionDate, s.Time
");
$stmt->execute([$userId]);
$myBookings = $stmt->fetchAll();

include 'includes/client_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Class Booking</h1>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- My Bookings -->
<div class="card mb-4">
    <div class="card-header">
        My Upcoming Bookings
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr><th>Class</th><th>Date & Time</th><th>Trainer</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($myBookings)): ?>
                        <tr><td colspan="4" class="text-center">You have no upcoming bookings.</td></tr>
                    <?php else: ?>
                        <?php foreach ($myBookings as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['ClassName']); ?></td>
                                <td><?php echo format_date($booking['SessionDate']) . ' at ' . format_time($booking['Time']); ?></td>
                                <td><?php echo htmlspecialchars($booking['TrainerName']); ?></td>
                                <td>
                                    <form action="booking.php" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                        <input type="hidden" name="reservationId" value="<?php echo $booking['ReservationID']; ?>">
                                        <button type="submit" name="cancel_booking" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this booking?');">Cancel</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Available Classes -->
<div class="card">
    <div class="card-header">
        Available Classes
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr><th>Class</th><th>Date & Time</th><th>Trainer</th><th>Availability</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($availableSessions as $session): 
                        $is_full = $session['CurrentBookings'] >= $session['MaxCapacity'];
                        $percentage = $session['MaxCapacity'] > 0 ? ($session['CurrentBookings'] / $session['MaxCapacity']) * 100 : 0;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($session['ClassName']); ?></td>
                            <td><?php echo format_date($session['SessionDate']) . ' at ' . format_time($session['Time']); ?></td>
                            <td><?php echo htmlspecialchars($session['TrainerName']); ?></td>
                            <td>
                                <span><?php echo $session['CurrentBookings'] . ' / ' . $session['MaxCapacity']; ?></span>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $session['CurrentBookings']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $session['MaxCapacity']; ?>"></div>
                                </div>
                            </td>
                            <td>
                                <form action="booking.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                    <input type="hidden" name="sessionId" value="<?php echo $session['SessionID']; ?>">
                                    <button type="submit" name="book_session" class="btn btn-primary btn-sm" <?php echo $is_full ? 'disabled' : ''; ?>>
                                        <?php echo $is_full ? 'Full' : 'Book'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/client_footer.php'; ?>
