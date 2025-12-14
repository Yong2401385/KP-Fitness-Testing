<?php
define('PAGE_TITLE', 'Book Classes');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];

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

<div id="feedback-toast" class="toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 p-3" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
        <div class="toast-body"></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
</div>

<!-- My Bookings -->
<div class="card mb-4">
    <div class="card-header">
        My Upcoming Bookings
    </div>
    <div class="card-body" id="my-bookings-container">
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
                                    <button class="btn btn-danger btn-sm cancel-booking-btn" data-reservation-id="<?php echo $booking['ReservationID']; ?>">Cancel</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Calendar and Sessions -->
<div class="row">
    <!-- Calendar -->
    <div class="col-lg-7 mb-4">
        <div class="card">
            <div class="card-header">
                Select a Date
            </div>
            <div class="card-body">
                <div id="calendar-container" style="width: 100%; height: 400px;"></div>
            </div>
        </div>
    </div>

    <!-- Sessions for Selected Date -->
    <div class="col-lg-5 mb-4">
        <div class="card">
            <div class="card-header">
                Available Classes for <span id="selected-date-display">...</span>
            </div>
            <div class="card-body" id="sessions-container">
                <p class="text-muted text-center">Select a date from the calendar to see available classes.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const calendar = new VanillaCalendar('#calendar-container', {
        settings: {
            selection: {
                day: 'single',
            },
            visibility: {
                theme: 'light',
                weekend: false,
            },
        },
        actions: {
            clickDay(e, self) {
                const selectedDate = self.selectedDates[0];
                if (selectedDate) {
                    document.getElementById('selected-date-display').textContent = selectedDate;
                    fetchSessions(selectedDate);
                }
            },
        },
    });
    calendar.init();

    function fetchSessions(date) {
        const sessionsContainer = document.getElementById('sessions-container');
        sessionsContainer.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';

        fetch(`../api/get_sessions.php?date=${date}`)
            .then(response => response.json())
            .then(data => {
                sessionsContainer.innerHTML = '';
                if (data.length > 0) {
                    const sessionList = document.createElement('ul');
                    sessionList.className = 'list-group list-group-flush';
                    data.forEach(session => {
                        const is_full = session.CurrentBookings >= session.MaxCapacity;
                        const percentage = session.MaxCapacity > 0 ? (session.CurrentBookings / session.MaxCapacity) * 100 : 0;

                        const listItem = document.createElement('li');
                        listItem.className = 'list-group-item';
                        listItem.innerHTML = `
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">${session.ClassName}</h6>
                                <small>${session.Time}</small>
                            </div>
                            <p class="mb-1">Trainer: ${session.TrainerName}</p>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar" role="progressbar" style="width: ${percentage}%;" aria-valuenow="${session.CurrentBookings}" aria-valuemin="0" aria-valuemax="${session.MaxCapacity}"></div>
                            </div>
                            <small>${session.CurrentBookings} / ${session.MaxCapacity} Booked</small>
                            <button class="btn btn-primary btn-sm float-end book-session-btn" data-session-id="${session.SessionID}" ${is_full ? 'disabled' : ''}>
                                ${is_full ? 'Full' : 'Book'}
                            </button>
                        `;
                        sessionList.appendChild(listItem);
                    });
                    sessionsContainer.appendChild(sessionList);
                } else {
                    sessionsContainer.innerHTML = '<p class="text-muted text-center">No classes available for this date.</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching sessions:', error);
                sessionsContainer.innerHTML = '<p class="text-danger text-center">Could not load sessions. Please try again.</p>';
            });
    }

    function handleBookingAction(e) {
        const target = e.target;
        if (!target.matches('.book-session-btn') && !target.matches('.cancel-booking-btn')) {
            return;
        }
        
        e.preventDefault();

        const isBooking = target.matches('.book-session-btn');
        const action = isBooking ? 'book' : 'cancel';
        const id = isBooking ? target.dataset.sessionId : target.dataset.reservationId;
        const body = new FormData();
        body.append('action', action);
        body.append(isBooking ? 'sessionId' : 'reservationId', id);
        body.append('csrf_token', '<?php echo get_csrf_token(); ?>');

        fetch('../api/booking_handler.php', {
            method: 'POST',
            body: body
        })
        .then(response => response.json())
        .then(data => {
            showFeedback(data.message, data.success);
            if (data.success) {
                refreshMyBookings();
                const selectedDate = calendar.selectedDates[0];
                if (selectedDate) {
                    fetchSessions(selectedDate);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showFeedback('An unexpected error occurred.', false);
        });
    }

    function refreshMyBookings() {
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newBookings = doc.getElementById('my-bookings-container').innerHTML;
                document.getElementById('my-bookings-container').innerHTML = newBookings;
            });
    }

    function showFeedback(message, success) {
        const toastEl = document.getElementById('feedback-toast');
        const toastBody = toastEl.querySelector('.toast-body');
        
        toastEl.classList.remove('bg-success', 'bg-danger');
        toastEl.classList.add(success ? 'bg-success' : 'bg-danger');
        toastBody.textContent = message;
        
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }

    document.addEventListener('click', handleBookingAction);
});
</script>

<?php include 'includes/client_footer.php'; ?>
