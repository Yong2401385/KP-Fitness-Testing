<?php
define('PAGE_TITLE', 'My Schedule');
require_once '../includes/config.php';
require_trainer();

$trainerId = $_SESSION['UserID'];
$feedback = [];

// Handle Cancellation / Rescheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_session') {
    $sessionId = intval($_POST['session_id']);
    $reason = $_POST['reason'] ?? '';
    $reschedule = isset($_POST['reschedule']) && $_POST['reschedule'] === 'yes';
    
    try {
        // Verify session belongs to trainer
        $stmt = $pdo->prepare("SELECT SessionID FROM sessions WHERE SessionID = ? AND TrainerID = ?");
        $stmt->execute([$sessionId, $trainerId]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid session or permission denied.");
        }

        if ($reschedule) {
            $newDate = $_POST['new_date'];
            $newTime = $_POST['new_time'];
            
            // Validate date/time
            if (empty($newDate) || empty($newTime)) {
                throw new Exception("New date and time are required for rescheduling.");
            }

            // Update session with new StartTime and calculated EndTime
            // First, get duration to calculate EndTime
            $stmtDuration = $pdo->prepare("SELECT a.Duration FROM sessions s JOIN activities a ON s.ClassID = a.ClassID WHERE s.SessionID = ?");
            $stmtDuration->execute([$sessionId]);
            $duration = $stmtDuration->fetchColumn();
            $duration = $duration ? $duration : 60; // Default 60 if fail
            
            // Calculate EndTime
            $startDateTime = new DateTime("$newDate $newTime");
            $endDateTime = clone $startDateTime;
            $endDateTime->add(new DateInterval('PT' . $duration . 'M'));
            $newEndTime = $endDateTime->format('H:i:s');

            $stmt = $pdo->prepare("UPDATE sessions SET SessionDate = ?, StartTime = ?, EndTime = ?, Status = 'scheduled' WHERE SessionID = ?");
            $stmt->execute([$newDate, $newTime, $newEndTime, $sessionId]);
            
            // Notify clients (Placeholder)
            // $clients = get_booked_clients($sessionId);
            // foreach($clients as $client) { create_notification($client['UserID'], 'Session Rescheduled', "Class rescheduled to $newDate $newTime. Reason: $reason"); }

            $feedback = ['type' => 'success', 'message' => 'Session successfully rescheduled.'];
        } else {
            // Cancel session
            $stmt = $pdo->prepare("UPDATE sessions SET Status = 'cancelled' WHERE SessionID = ?");
            $stmt->execute([$sessionId]);
            
            // --- Handle Client Cancellations & Refunds ---
            // Fetch all booked reservations for this session
                $stmt = $pdo->prepare("
                    SELECT r.ReservationID, r.UserID, r.PaidAmount, a.ClassName, s.SessionDate, s.StartTime
                    FROM reservations r
                    JOIN sessions s ON r.SessionID = s.SessionID
                    JOIN activities a ON s.ClassID = a.ClassID
                    WHERE r.SessionID = ? AND r.Status = 'booked'
                ");            $stmt->execute([$sessionId]);
            $bookedClients = $stmt->fetchAll();
            
            foreach ($bookedClients as $booking) {
                // 1. Update Reservation Status
                $updateRes = $pdo->prepare("UPDATE reservations SET Status = 'cancelled' WHERE ReservationID = ?");
                $updateRes->execute([$booking['ReservationID']]);
                
                // 2. Process Refund (Trainer cancelled, so full refund)
                if ($booking['PaidAmount'] > 0) {
                    $desc = "Refund: Session cancelled by trainer ({$booking['ClassName']})";
                    $refundStmt = $pdo->prepare("INSERT INTO payments (UserID, Amount, Status, MembershipID, PaymentType, Description) VALUES (?, ?, 'refunded', 4, 'System', ?)");
                    $refundStmt->execute([$booking['UserID'], $booking['PaidAmount'], $desc]);
                }
                
                // 3. Notify Client
                $sessionTimeStr = format_date($booking['SessionDate']) . ' at ' . format_time($booking['StartTime']);
                $msg = "Important: The {$booking['ClassName']} session scheduled for $sessionTimeStr has been cancelled by the trainer.";
                if ($booking['PaidAmount'] > 0) {
                    $msg .= " A refund of RM " . number_format($booking['PaidAmount'], 2) . " has been processed.";
                }
                create_notification($booking['UserID'], 'Session Cancelled by Trainer', $msg, 'warning');
            }
            
            $feedback = ['type' => 'success', 'message' => 'Session cancelled. ' . count($bookedClients) . ' client(s) have been notified and refunded if applicable.'];
        }
    } catch (Exception $e) {
        $feedback = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Get Month and Year from GET or default to current
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Navigation Logic
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Calendar Generation Variables
$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDayOfMonth);
$dateComponents = getdate($firstDayOfMonth);
$dayOfWeek = $dateComponents['wday']; // 0 (Sun) - 6 (Sat)
$monthName = $dateComponents['month'];

// Fetch sessions for this month
try {
    $startDate = "$year-$month-01";
    $endDate = "$year-$month-$daysInMonth";
    
        $stmt = $pdo->prepare("
    
            SELECT s.SessionID, s.SessionDate, s.StartTime, s.Room, s.Status, s.CurrentBookings, c.ClassName, c.MaxCapacity
    
            FROM sessions s
    
            JOIN activities c ON s.ClassID = c.ClassID
    
            WHERE s.TrainerID = ? 
    
            AND s.SessionDate BETWEEN ? AND ?
    
            ORDER BY s.StartTime ASC
    
        ");
    $stmt->execute([$trainerId, $startDate, $endDate]);
    $monthSessions = $stmt->fetchAll(PDO::FETCH_ASSOC); 
    
    $sessionsByDay = [];
    foreach ($monthSessions as $row) {
        $day = intval(date('j', strtotime($row['SessionDate'])));
        $sessionsByDay[$day][] = $row;
    }

} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch schedule data: ' . $e->getMessage()];
    $sessionsByDay = [];
}

include 'includes/trainer_header.php';
?>

<style>
    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 15px; /* Increased gap */
        margin-bottom: 2rem;
        background: linear-gradient(145deg, #1a1a1a, #222); /* Subtle gradient background */
        padding: 20px;
        border-radius: 16px;
        box-shadow: inset 0 0 20px rgba(0,0,0,0.5);
    }
    .calendar-day-header {
        text-align: center;
        font-weight: 800;
        color: #fff; /* Brighter header */
        padding: 15px 0; /* Increased padding */
        font-size: 1.2rem; /* Larger header text */
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .calendar-day {
        background-color: rgba(255, 255, 255, 0.03); /* Glassmorphism effect */
        backdrop-filter: blur(5px);
        border-radius: 12px; /* Increased border radius */
        border: 1px solid rgba(255, 255, 255, 0.08);
        min-height: 140px; /* Increased min-height */
        padding: 15px;
        position: relative;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); /* Smooth transition */
        cursor: pointer;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .calendar-day:hover {
        background-color: rgba(255, 255, 255, 0.08);
        border-color: var(--primary-color);
        transform: translateY(-8px) scale(1.02); /* More dynamic hover */
        box-shadow: 0 10px 25px rgba(0,0,0,0.4), 0 0 10px rgba(255, 107, 0, 0.2); /* Glow effect */
        z-index: 2;
    }
    .calendar-day.empty {
        background-color: transparent;
        border: none;
        cursor: default;
        backdrop-filter: none;
    }
    .calendar-day.empty:hover {
        background-color: transparent;
        transform: none;
        box-shadow: none;
    }
    .calendar-day.today {
        border: 2px solid var(--primary-color); /* Thicker border */
        background: linear-gradient(135deg, rgba(255, 107, 0, 0.15), rgba(255, 107, 0, 0.05));
        box-shadow: 0 0 15px rgba(255, 107, 0, 0.3);
    }
    .day-number {
        font-size: 1.8rem; /* Even larger day number */
        font-weight: 900;
        color: rgba(255, 255, 255, 0.8);
        text-shadow: 0 2px 4px rgba(0,0,0,0.5);
    }
    .class-count-badge { /* Renamed to .class-count-text conceptually for orange text */
        color: var(--primary-color); /* Orange text */
        font-size: 1.1rem;
        font-weight: 800;
        text-align: right; /* Align right to match day number */
        align-self: flex-end; /* Keep it at the bottom-right */
        /* Removed: background, padding, border-radius, width, max-width, box-shadow, border */
    }
    
    /* Modal Navigation */
    .modal-date-nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
    }
    .modal-arrow {
        cursor: pointer;
        padding: 0 10px;
        font-size: 1.2rem;
        color: var(--primary-color);
    }
    .modal-arrow:hover {
        color: #fff;
    }
</style>

<div class="container-fluid pt-3">
    <div class="calendar-header mb-2">
        <h2 class="h3 mb-0 text-white">My Schedule</h2>
        <div class="d-flex align-items-center">
            <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="btn btn-outline-secondary me-2"><i class="fas fa-chevron-left"></i></a>
            <h4 class="mb-0 mx-3 text-white" style="min-width: 180px; text-align: center;"><?php echo "$monthName $year"; ?></h4>
            <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="btn btn-outline-secondary ms-2"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
    <hr class="border-white opacity-100 mb-4">

    <?php if (!empty($feedback)): ?>
        <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $feedback['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Day Headers -->
    <div class="calendar-grid mb-0">
        <div class="calendar-day-header">Sun</div>
        <div class="calendar-day-header">Mon</div>
        <div class="calendar-day-header">Tue</div>
        <div class="calendar-day-header">Wed</div>
        <div class="calendar-day-header">Thu</div>
        <div class="calendar-day-header">Fri</div>
        <div class="calendar-day-header">Sat</div>
    </div>

    <div class="calendar-grid">
        <?php
        // Empty cells for days before the 1st
        for ($i = 0; $i < $dayOfWeek; $i++) {
            echo '<div class="calendar-day empty"></div>';
        }

        // Days of the month
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateString = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
            $isToday = ($dateString == date('Y-m-d'));
            $daySessions = $sessionsByDay[$day] ?? [];
            $sessionCount = count($daySessions);
            
            echo "<div class='calendar-day " . ($isToday ? 'today' : '') . "' onclick='openDayModal(\"$dateString\")'>";
            echo "<div class='d-flex justify-content-between align-items-start w-100'>"; /* Flex container for top elements */
            echo "<div class='day-number flex-grow-1'>$day</div>"; /* Day number top-left */
            if ($sessionCount > 0) {
                $countText = $sessionCount . ($sessionCount === 1 ? ' Class' : ' Classes');
                echo "<div class='text-primary fw-bold text-end' style='font-size: 1.1rem;'>$countText</div>"; /* Orange text, top-right */
            }
            echo "</div>"; /* Close flex container */
            
            echo "</div>"; // Close calendar-day
            
            // Generate invisible data container for this day to easily load into modal
            if ($sessionCount > 0) {
                echo "<div id='data-$dateString' style='display:none;'>";
                foreach ($daySessions as $session) {
                    $live = is_session_live($session['SessionDate'], $session['StartTime']);
                    echo "<div class='card mb-3 bg-dark border-secondary shadow-lg'>"; // Added shadow
                    echo "<div class='card-body p-4'>"; // Increased padding
                    echo "<div class='d-flex justify-content-between align-items-start mb-3'>";
                    echo "<h5 class='card-title text-primary fw-bold mb-0' style='font-size: 1.4rem;'>" . htmlspecialchars($session['ClassName']) . "</h5>"; // Larger, primary color
                    if ($live) echo "<span class='badge bg-success py-2 px-3' style='font-size: 0.9rem;'>LIVE</span>"; // Larger badge
                    echo "</div>";
                    echo "<p class='card-text text-white mb-3' style='font-size: 1.1rem;'>"; // Larger, white text
                    echo "<i class='far fa-clock me-2'></i> " . format_time($session['StartTime']) . "<br>";
                    echo "<i class='fas fa-door-open me-2'></i> " . htmlspecialchars($session['Room']) . "<br>";
                    echo "<i class='fas fa-users me-2'></i> " . $session['CurrentBookings'] . " / " . $session['MaxCapacity'];
                    echo "</p>";
                    echo "<div class='d-grid gap-2 mt-4'>"; // Grid for buttons
                    echo "<a href='attendance.php?session_id=" . $session['SessionID'] . "' class='btn btn-primary btn-lg w-100'>Take Attendance</a>"; // Larger buttons
                    if ($session['Status'] == 'scheduled') {
                        echo "<button class='btn btn-outline-danger btn-lg w-100' onclick='openCancelModal(" . $session['SessionID'] . ", \"" . htmlspecialchars($session['ClassName']) . "\")'>Cancel / Reschedule</button>"; // Larger buttons
                    }
                    echo "</div>";
                    echo "</div></div>";
                }
                echo "</div>";
            } else {
                echo "<div id='data-$dateString' style='display:none;'><p class='text-muted text-center py-3'>No classes scheduled for this day.</p></div>";
            }
        }
        
        // Fill remaining cells
        $remainingDays = 7 - (($dayOfWeek + $daysInMonth) % 7);
        if ($remainingDays < 7) {
            for ($i = 0; $i < $remainingDays; $i++) {
                echo '<div class="calendar-day empty"></div>';
            }
        }
        ?>
    </div>
</div>

<!-- Day Details Modal -->
<div class="modal fade" id="dayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"> <!-- Changed to modal-lg for larger overall size -->
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary" style="background-color: var(--dash-accent);"> <!-- Orange background for header -->
                <div class="modal-date-nav">
                    <i class="fas fa-chevron-left modal-arrow fa-lg" onclick="changeDay(-1)"></i> <!-- Larger arrows -->
                    <h4 class="modal-title text-white fw-bold" id="modalDateTitle" style="font-size: 1.5rem;">Date</h4> <!-- Larger, white, bold title -->
                    <i class="fas fa-chevron-right modal-arrow fa-lg" onclick="changeDay(1)"></i> <!-- Larger arrows -->
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBodyContent">
                <!-- Content injected by JS -->
            </div>
        </div>
    </div>
</div>

<!-- Cancel/Reschedule Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
             <div class="modal-header border-secondary">
                <h5 class="modal-title">Cancel Session: <span id="cancelClassName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="cancelForm" method="POST">
                    <input type="hidden" name="action" value="cancel_session">
                    <input type="hidden" name="session_id" id="cancelSessionId">
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Cancellation</label>
                        <textarea class="form-control bg-secondary text-white border-0" name="reason" rows="3" required placeholder="e.g. Emergency, Sick leave..."></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="rescheduleCheck" name="reschedule" value="yes" onchange="toggleRescheduleInputs()">
                        <label class="form-check-label" for="rescheduleCheck">Reschedule this session?</label>
                    </div>
                    
                    <div id="rescheduleInputs" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label">New Date</label>
                            <input type="date" class="form-control bg-secondary text-white border-0" name="new_date" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Time</label>
                            <input type="time" class="form-control bg-secondary text-white border-0" name="new_time">
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger">Confirm Cancellation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    let currentDate = ''; // Format YYYY-MM-DD

    function openDayModal(dateStr) {
        currentDate = dateStr;
        updateModalContent();
        new bootstrap.Modal(document.getElementById('dayModal')).show();
    }

    function changeDay(offset) {
        let date = new Date(currentDate);
        date.setDate(date.getDate() + offset);
        
        // Format YYYY-MM-DD manually to avoid timezone issues causing off-by-one
        let y = date.getFullYear();
        let m = String(date.getMonth() + 1).padStart(2, '0');
        let d = String(date.getDate()).padStart(2, '0');
        let newDateStr = `${y}-${m}-${d}`;
        
        currentDate = newDateStr;
        updateModalContent();
    }

    function updateModalContent() {
        // Update Title
        const dateObj = new Date(currentDate);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('modalDateTitle').textContent = dateObj.toLocaleDateString('en-US', options);
        
        const dataContainer = document.getElementById('data-' + currentDate);
        const modalBody = document.getElementById('modalBodyContent');
        
        if (dataContainer) {
            modalBody.innerHTML = dataContainer.innerHTML;
        } else {
            modalBody.innerHTML = "<p class='text-center text-muted py-3'>No schedule data available for this date.<br><small>(Try navigating to that month in the calendar view)</small></p>";
        }
    }
    
    function openCancelModal(sessionId, className) {
        // Hide day modal first (optional, but cleaner)
        // bootstrap.Modal.getInstance(document.getElementById('dayModal')).hide();
        
        document.getElementById('cancelSessionId').value = sessionId;
        document.getElementById('cancelClassName').textContent = className;
        
        // Reset form
        document.getElementById('rescheduleCheck').checked = false;
        toggleRescheduleInputs();
        
        new bootstrap.Modal(document.getElementById('cancelModal')).show();
    }
    
    function toggleRescheduleInputs() {
        const isChecked = document.getElementById('rescheduleCheck').checked;
        const inputs = document.getElementById('rescheduleInputs');
        inputs.style.display = isChecked ? 'block' : 'none';
        
        // Toggle required attributes for HTML5 validation
        const dateInput = inputs.querySelector('input[name="new_date"]');
        const timeInput = inputs.querySelector('input[name="new_time"]');
        if (isChecked) {
            dateInput.setAttribute('required', 'required');
            timeInput.setAttribute('required', 'required');
        } else {
            dateInput.removeAttribute('required');
            timeInput.removeAttribute('required');
        }
    }
</script>

<?php include 'includes/trainer_footer.php'; ?>
