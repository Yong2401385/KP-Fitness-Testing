<?php
define('PAGE_TITLE', 'Book Classes');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];
$feedback = [];

// --- Handle Actions ---

// Handle Booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_session'])) {
    $sessionId = intval($_POST['sessionId']);
    
    // Check for active membership first
    $stmt = $pdo->prepare("SELECT u.MembershipID FROM users u WHERE u.UserID = ?");
    $stmt->execute([$userId]);
    $userMembership = $stmt->fetch();

    if (!$userMembership['MembershipID']) {
        $feedback = ['type' => 'error', 'message' => 'You need an active membership to book classes. Please purchase a plan first.'];
    } else {
        try {
            $pdo->beginTransaction();
            // Check session capacity (lock the row for update)
            $stmt = $pdo->prepare("SELECT CurrentBookings, (SELECT MaxCapacity FROM classes c WHERE c.ClassID = s.ClassID) as MaxCapacity FROM sessions s WHERE s.SessionID = ? FOR UPDATE");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();

            if ($session['CurrentBookings'] >= $session['MaxCapacity']) {
                $feedback = ['type' => 'error', 'message' => 'This class is already full.'];
            } else {
                // Check for duplicate booking
                $stmt = $pdo->prepare("SELECT ReservationID FROM reservations WHERE UserID = ? AND SessionID = ? AND Status = 'booked'");
                $stmt->execute([$userId, $sessionId]);
                if ($stmt->fetch()) {
                    $feedback = ['type' => 'error', 'message' => 'You have already booked this session.'];
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
            $feedback = ['type' => 'error', 'message' => 'A database error occurred. Please try again.'];
        }
    }
}

// Handle Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
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
        $feedback = ['type' => 'error', 'message' => 'Could not cancel booking. Please try again.'];
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
.table-container { overflow-x: auto; }
.sessions-table { width: 100%; border-collapse: collapse; }
.sessions-table th, .sessions-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}
.sessions-table th { color: var(--primary-color); }
.capacity-bar {
    width: 100%;
    background-color: #555;
    border-radius: 4px;
    height: 10px;
    overflow: hidden;
}
.capacity-fill {
    height: 100%;
    background-color: var(--primary-color);
    border-radius: 4px;
}
</style>

<div class="page-header">
    <h1>Class Booking</h1>
    <p>Browse and book your spot in our upcoming classes.</p>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?>">
        <?php echo $feedback['message']; ?>
    </div>
<?php endif; ?>

<!-- My Bookings -->
<div class="card">
    <h2 class="card-title">My Upcoming Bookings</h2>
    <div class="table-container">
        <table class="sessions-table">
            <thead>
                <tr><th>Class</th><th>Date & Time</th><th>Trainer</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php if (empty($myBookings)): ?>
                    <tr><td colspan="4" style="text-align: center;">You have no upcoming bookings.</td></tr>
                <?php else: ?>
                    <?php foreach ($myBookings as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['ClassName']); ?></td>
                            <td><?php echo format_date($booking['SessionDate']) . ' at ' . format_time($booking['Time']); ?></td>
                            <td><?php echo htmlspecialchars($booking['TrainerName']); ?></td>
                            <td>
                                <form action="booking.php" method="POST">
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

<!-- Available Classes -->
<div class="card">
    <h2 class="card-title">Available Classes</h2>
    <div class="table-container">
        <table class="sessions-table">
            <thead>
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
                            <div class="capacity-bar"><div class="capacity-fill" style="width: <?php echo $percentage; ?>%;"></div></div>
                        </td>
                        <td>
                            <form action="booking.php" method="POST">
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

<?php include 'includes/client_footer.php'; ?>
