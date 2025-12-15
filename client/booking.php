<?php
define('PAGE_TITLE', 'Book Classes');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];

// --- Get user membership status ---
$stmt = $pdo->prepare("
    SELECT m.PlanName 
    FROM users u
    LEFT JOIN membership m ON u.MembershipID = m.MembershipID
    WHERE u.UserID = ?
");
$stmt->execute([$userId]);
$membership = $stmt->fetch();
$isPremiumMember = $membership && in_array($membership['PlanName'], ['Annual Class Membership', 'Unlimited Class Membership']);

// Fetch all class categories for the filter dropdown
$stmt = $pdo->query("SELECT CategoryID, CategoryName FROM class_categories ORDER BY CategoryName");
$allCategories = $stmt->fetchAll();

// Fetch booking history
$stmt = $pdo->prepare("
    SELECT r.ReservationID, r.is_recurring, CONCAT(s.SessionDate, ' ', s.Time) as StartTime, a.ClassName as ActivityName, c.CategoryName, r.Status
    FROM reservations r
    JOIN sessions s ON r.SessionID = s.SessionID
    JOIN activities a ON s.ClassID = a.ClassID
    JOIN class_categories c ON a.CategoryID = c.CategoryID
    WHERE r.UserID = ?
    ORDER BY StartTime DESC
");
$stmt->execute([$userId]);
$bookingHistory = $stmt->fetchAll();


include 'includes/client_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Class Booking</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-lg btn-primary" data-bs-toggle="modal" data-bs-target="#bookingModal">
            <i class="fas fa-plus-circle me-2"></i>Book a Class
        </button>
    </div>
</div>

<!-- Feedback Toast -->
<div id="feedback-toast" class="toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 p-3" role="alert" aria-live="assertive" aria-atomic="true" style="z-index: 1100">
    <div class="d-flex">
        <div class="toast-body"></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
</div>

<!-- My Schedule -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">My Schedule</h5>
        <div class="btn-group">
            <button class="btn btn-outline-secondary btn-sm" id="prev-week-btn"><i class="fas fa-chevron-left"></i></button>
            <span class="btn btn-light btn-sm fw-bold border" id="current-week-display" style="min-width: 200px; color: #333; cursor: default;">...</span>
            <button class="btn btn-outline-secondary btn-sm" id="next-week-btn"><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
    <div class="card-body">
        <div id="my-schedule-container" class="list-group list-group-flush">
            <!-- Schedule items will be loaded here -->
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Booking History -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Booking History</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr><th>Date</th><th>Class</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($bookingHistory)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No booking history.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bookingHistory as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(format_date($booking['StartTime'])); ?></td>
                                <td><?php echo htmlspecialchars($booking['CategoryName']); ?> - <?php echo htmlspecialchars($booking['ActivityName']); ?></td>
                                <td><span class="badge bg-<?php 
                                    $s = strtolower($booking['Status']);
                                    if (in_array($s, ['booked', 'attended', 'done', 'rated'])) echo 'success';
                                    elseif (in_array($s, ['cancelled', 'absent'])) echo 'danger';
                                    elseif ($s === 're-scheduled') echo 'info';
                                    else echo 'secondary';
                                ?>"><?php echo htmlspecialchars(ucfirst($booking['Status'])); ?></span></td>
                                <td>
                                    <?php if ($booking['Status'] === 'Attended' || $booking['Status'] === 'Done'): ?>
                                        <button class="btn btn-outline-primary btn-sm rate-btn" data-reservation-id="<?php echo $booking['ReservationID']; ?>">Rate</button>
                                    <?php elseif ($booking['Status'] === 'Absent'): ?>
                                        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#bookingModal">Re-schedule</button>
                                    <?php elseif ($booking['Status'] === 'Re-scheduled' || $booking['Status'] === 'booked'): ?>
                                        <button class="btn btn-outline-danger btn-sm cancel-booking-btn" data-reservation-id="<?php echo $booking['ReservationID']; ?>" data-is-recurring="<?php echo $booking['is_recurring']; ?>">Cancel</button>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bookingModalLabel">Book a Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Filter and Calendar -->
                    <div class="col-lg-7">
                        <div class="mb-3">
                            <label for="category-filter" class="form-label">Filter by Class Category</label>
                            <select id="category-filter" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($allCategories as $category): ?>
                                    <option value="<?php echo $category['CategoryID']; ?>"><?php echo htmlspecialchars($category['CategoryName']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="calendar-container" style="width: 100%; height: 400px;"></div>
                    </div>
                    <!-- Sessions -->
                    <div class="col-lg-5">
                        <h6>Available Classes for <span id="selected-date-display">...</span></h6>
                        <div id="sessions-container" class="list-group" style="max-height: 450px; overflow-y: auto;">
                            <div class="text-center text-muted p-3">Select a date to see classes.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rating Modal -->
<div class="modal fade" id="ratingModal" tabindex="-1" aria-labelledby="ratingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ratingModalLabel">Rate Your Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="rating-form">
                    <input type="hidden" name="reservationId" id="rating-reservation-id">
                    <div class="mb-3 text-center">
                        <label class="form-label">Your Rating</label>
                        <div>
                            <i class="fas fa-star star-rating" data-value="1"></i>
                            <i class="fas fa-star star-rating" data-value="2"></i>
                            <i class="fas fa-star star-rating" data-value="3"></i>
                            <i class="fas fa-star star-rating" data-value="4"></i>
                            <i class="fas fa-star star-rating" data-value="5"></i>
                        </div>
                        <input type="hidden" name="ratingScore" id="rating-score" required>
                    </div>
                    <div class="mb-3">
                        <label for="rating-comment" class="form-label">Comment</label>
                        <textarea class="form-control" id="rating-comment" name="comment" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Rating</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentModalLabel">Complete Your Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="payment-methods">
                    <p class="mb-3">Total Amount: <strong id="payment-amount" class="text-primary"></strong></p>
                    <p>Select a payment method:</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary payment-method-btn" data-method="Credit Card"><i class="fas fa-credit-card me-2"></i>Credit Card</button>
                        <button class="btn btn-outline-primary payment-method-btn" data-method="Debit Card"><i class="fas fa-credit-card me-2"></i>Debit Card</button>
                        <button class="btn btn-outline-primary payment-method-btn" data-method="E-wallet"><i class="fas fa-wallet me-2"></i>E-wallet</button>
                        <button class="btn btn-outline-primary payment-method-btn" data-method="Online Banking"><i class="fas fa-university me-2"></i>Online Banking</button>
                    </div>
                    <div class="text-center mt-3">
                        <button id="pay-now-btn" class="btn btn-primary btn-lg" disabled>Pay Now</button>
                    </div>
                </div>
                <div id="payment-loading" class="text-center d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Processing your payment...</p>
                </div>
                <div id="payment-success" class="text-center d-none">
                    <i class="fas fa-check-circle fa-4x text-success"></i>
                    <h4 class="mt-3">Payment Successful!</h4>
                    <p>Booking confirmed.</p>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelModalLabel">Cancel Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="cancel-message">Are you sure you want to cancel this booking?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger d-none" id="cancel-series-btn">Cancel & Stop pre-booking</button>
                <button type="button" class="btn btn-danger" id="cancel-one-btn">Cancel Booking</button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Details Modal -->
<div class="modal fade" id="scheduleDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Class Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h4 id="detail-activity" class="text-primary mb-3"></h4>
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Time:</div>
                    <div class="col-sm-8" id="detail-time"></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Trainer:</div>
                    <div class="col-sm-8" id="detail-trainer"></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Room:</div>
                    <div class="col-sm-8" id="detail-room"></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Category:</div>
                    <div class="col-sm-8" id="detail-category"></div>
                </div>
                <div class="mt-3">
                    <p class="fw-bold mb-1">Description:</p>
                    <p class="text-muted" id="detail-description"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const bookingModalEl = document.getElementById('bookingModal');
    const bookingModal = new bootstrap.Modal(bookingModalEl);
    const ratingModalEl = document.getElementById('ratingModal');
    const ratingModal = new bootstrap.Modal(ratingModalEl);
    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
    const scheduleDetailsModal = new bootstrap.Modal(document.getElementById('scheduleDetailsModal'));
    
    // --- My Schedule Logic ---
    let currentWeekStart = getStartOfWeek(new Date());

    function getStartOfWeek(date) {
        const d = new Date(date);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Adjust when day is Sunday
        return new Date(d.setDate(diff));
    }

    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }
    
    function updateScheduleDisplay() {
        const start = new Date(currentWeekStart);
        const end = new Date(currentWeekStart);
        end.setDate(end.getDate() + 6);

        const options = { month: 'short', day: 'numeric' };
        document.getElementById('current-week-display').textContent = `${start.toLocaleDateString('en-US', options)} - ${end.toLocaleDateString('en-US', options)}`;
        
        fetchSchedule(formatDate(start), formatDate(end));
    }

    function fetchSchedule(startDate, endDate) {
        const container = document.getElementById('my-schedule-container');
        container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';

        fetch(`../api/get_user_schedule.php?start_date=${startDate}&end_date=${endDate}`)
            .then(res => res.json())
            .then(data => {
                container.innerHTML = '';
                if (data.length === 0) {
                    container.innerHTML = '<div class="text-center text-muted py-4">No classes scheduled for this week.</div>';
                    return;
                }

                // Group by date
                const grouped = {};
                data.forEach(item => {
                    if (!grouped[item.SessionDate]) grouped[item.SessionDate] = [];
                    grouped[item.SessionDate].push(item);
                });

                // Render
                Object.keys(grouped).forEach(date => {
                    const dateObj = new Date(date);
                    const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
                    const dayHeader = document.createElement('div');
                    dayHeader.className = 'list-group-item list-group-item-secondary fw-bold';
                    dayHeader.textContent = `${dayName}, ${dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`;
                    container.appendChild(dayHeader);

                    grouped[date].forEach(session => {
                        const item = document.createElement('div');
                        item.className = 'list-group-item py-3';
                        // Escaping logic for attributes
                        const safeActivity = session.ActivityName.replace(/"/g, '&quot;');
                        const safeDesc = (session.Description || 'No description').replace(/"/g, '&quot;');
                        
                        // Format time to HH:MM
                        const timeStr = session.Time.substring(0, 5);
                        
                        item.innerHTML = `
                            <div class="row align-items-center text-center text-md-start">
                                <div class="col-md-2">
                                    <h4 class="mb-0 fw-bold text-dark">${timeStr}</h4>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="mb-0 text-primary text-wrap">${session.ActivityName}</h4>
                                </div>
                                <div class="col-md-2">
                                    <h5 class="mb-0 text-secondary"><i class="fas fa-map-marker-alt me-2"></i>${session.Room || 'N/A'}</h5>
                                </div>
                                <div class="col-md-3">
                                    <h5 class="mb-0 text-secondary"><i class="fas fa-user-tie me-2"></i>${session.TrainerName}</h5>
                                </div>
                                <div class="col-md-2 text-md-end mt-2 mt-md-0">
                                    <button class="btn btn-outline-info w-100 view-details-btn" 
                                        data-activity="${safeActivity}"
                                        data-time="${session.SessionDate} at ${timeStr}"
                                        data-trainer="${session.TrainerName}"
                                        data-room="${session.Room || 'N/A'}"
                                        data-category="${session.CategoryName} (${session.DifficultyLevel})"
                                        data-description="${safeDesc}">
                                        Details
                                    </button>
                                </div>
                            </div>
                        `;
                        container.appendChild(item);
                    });
                });
            })
            .catch(err => {
                container.innerHTML = '<div class="text-center text-danger py-4">Failed to load schedule.</div>';
            });
    }

    document.getElementById('prev-week-btn').addEventListener('click', () => {
        currentWeekStart.setDate(currentWeekStart.getDate() - 7);
        updateScheduleDisplay();
    });

    document.getElementById('next-week-btn').addEventListener('click', () => {
        currentWeekStart.setDate(currentWeekStart.getDate() + 7);
        updateScheduleDisplay();
    });

    // Details Button Click
    document.addEventListener('click', function(e) {
        if (e.target.matches('.view-details-btn')) {
            const btn = e.target;
            document.getElementById('detail-activity').textContent = btn.dataset.activity;
            document.getElementById('detail-time').textContent = btn.dataset.time;
            document.getElementById('detail-trainer').textContent = btn.dataset.trainer;
            document.getElementById('detail-room').textContent = btn.dataset.room;
            document.getElementById('detail-category').textContent = btn.dataset.category;
            document.getElementById('detail-description').textContent = btn.dataset.description;
            scheduleDetailsModal.show();
        }
    });

    // Initial load
    updateScheduleDisplay();

    // Variables to store pending booking details
    let pendingSessionId = null;
    let pendingRepeatWeekly = false;
    let pendingCancellationId = null;

    let calendar = new VanillaCalendar('#calendar-container', {
        settings: {
            selection: { day: 'single' },
            visibility: { theme: 'light' }
        },
        actions: {
            clickDay(e, self) {
                selectedDate = self.selectedDates[0];
                if (selectedDate) {
                    document.getElementById('selected-date-display').textContent = selectedDate;
                    fetchSessions();
                }
            },
        },
    });
    calendar.init();
    let selectedDate = null;
    
    // Payment Modal Logic
    document.querySelectorAll('.payment-method-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            document.querySelectorAll('.payment-method-btn').forEach(btn => btn.classList.remove('active'));
            e.currentTarget.classList.add('active');
            document.getElementById('pay-now-btn').disabled = false;
        });
    });

    document.getElementById('pay-now-btn').addEventListener('click', () => {
        document.getElementById('payment-methods').classList.add('d-none');
        document.getElementById('payment-loading').classList.remove('d-none');

        // Simulate payment processing
        setTimeout(() => {
            document.getElementById('payment-loading').classList.add('d-none');
            // document.getElementById('payment-success').classList.remove('d-none'); // Don't show success yet, wait for booking confirmation
            
            // Proceed with booking after simulated payment
            handleBooking(pendingSessionId, pendingRepeatWeekly, true);
            paymentModal.hide();
             // Reset modal state for next time
            setTimeout(() => {
                document.getElementById('payment-methods').classList.remove('d-none');
                document.querySelectorAll('.payment-method-btn').forEach(btn => btn.classList.remove('active'));
                document.getElementById('pay-now-btn').disabled = true;
            }, 500);

        }, 2000);
    });

    // Cancel Modal Logic
    document.getElementById('cancel-one-btn').addEventListener('click', () => {
        if (pendingCancellationId) {
            handleCancellation(pendingCancellationId, 'one');
            cancelModal.hide();
        }
    });

    document.getElementById('cancel-series-btn').addEventListener('click', () => {
        if (pendingCancellationId) {
            handleCancellation(pendingCancellationId, 'all');
            cancelModal.hide();
        }
    });

    // Fetch sessions function
    function fetchSessions() {
        if (!selectedDate) return;
        const categoryFilter = document.getElementById('category-filter').value;
        const sessionsContainer = document.getElementById('sessions-container');
        sessionsContainer.innerHTML = '<div class="text-center p-3"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';

        fetch(`../api/get_sessions.php?date=${selectedDate}&category_id=${categoryFilter}`)
            .then(response => response.json())
            .then(data => {
                sessionsContainer.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(session => {
                        const is_full = session.CurrentBookings >= session.MaxCapacity;
                        const percentage = session.MaxCapacity > 0 ? (session.CurrentBookings / session.MaxCapacity) * 100 : 0;
                        const sessionEl = document.createElement('div');
                        sessionEl.className = 'list-group-item';

                        const div1 = document.createElement('div');
                        div1.className = 'd-flex w-100 justify-content-between';

                        const h6 = document.createElement('h6');
                        h6.className = 'mb-1';
                        h6.textContent = session.ActivityName;
                        div1.appendChild(h6);

                        const small1 = document.createElement('small');
                        small1.textContent = session.Time;
                        div1.appendChild(small1);

                        sessionEl.appendChild(div1);

                        const p = document.createElement('p');
                        p.className = 'mb-1';
                        p.textContent = 'Trainer: ' + session.TrainerName;
                        sessionEl.appendChild(p);

                        const progressDiv = document.createElement('div');
                        progressDiv.className = 'progress';
                        progressDiv.style.height = '5px';
                        const progressBar = document.createElement('div');
                        progressBar.className = 'progress-bar';
                        progressBar.style.width = percentage + '%';
                        progressDiv.appendChild(progressBar);
                        sessionEl.appendChild(progressDiv);

                        const small2 = document.createElement('small');
                        small2.textContent = `${session.CurrentBookings} / ${session.MaxCapacity} Booked`;
                        sessionEl.appendChild(small2);

                        const button = document.createElement('button');
                        button.className = 'btn btn-primary btn-sm float-end book-session-btn';
                        button.dataset.sessionId = session.SessionID;
                        button.textContent = is_full ? 'Full' : 'Book';
                        if (is_full) {
                            button.disabled = true;
                        }
                        sessionEl.appendChild(button);
                        
                        sessionsContainer.appendChild(sessionEl);
                    });
                } else {
                    sessionsContainer.innerHTML = '<div class="text-center text-muted p-3">No classes available.</div>';
                }
            });
    }

    document.getElementById('category-filter').addEventListener('change', fetchSessions);

    // Main event delegator for clicks
    document.addEventListener('click', function(e) {
        // Book button
        if (e.target.matches('.book-session-btn')) {
            e.preventDefault();
            const sessionId = e.target.dataset.sessionId;
            
            let confirmMessage = "Confirm this booking?";
            <?php if ($isPremiumMember): ?>
            confirmMessage += "\n\nWould you like to book this class for the next 2 weeks as well?";
            const repeatWeekly = confirm(confirmMessage);
            handleBooking(sessionId, repeatWeekly);
            <?php else: ?>
            if(confirm(confirmMessage)) {
                handleBooking(sessionId, false);
            }
            <?php endif; ?>
        }

        // Cancel button
        if (e.target.matches('.cancel-booking-btn')) {
            e.preventDefault();
            pendingCancellationId = e.target.dataset.reservationId;
            const isRecurring = e.target.dataset.isRecurring === '1';
            
            const cancelMessage = document.getElementById('cancel-message');
            const cancelSeriesBtn = document.getElementById('cancel-series-btn');
            const cancelOneBtn = document.getElementById('cancel-one-btn');

            if (isRecurring) {
                cancelMessage.textContent = "This is a recurring booking. Do you want to cancel just this booking or stop the entire pre-booking series?";
                cancelSeriesBtn.classList.remove('d-none');
                cancelOneBtn.textContent = "Only Current Booking";
            } else {
                cancelMessage.textContent = "Are you sure you want to cancel this booking?";
                cancelSeriesBtn.classList.add('d-none');
                cancelOneBtn.textContent = "Cancel Booking";
            }
            
            cancelModal.show();
        }
        
        // Rate button
        if (e.target.matches('.rate-btn')) {
            e.preventDefault();
            const reservationId = e.target.dataset.reservationId;
            document.getElementById('rating-reservation-id').value = reservationId;
            ratingModal.show();
        }

        // Star rating
        if (e.target.matches('.star-rating')) {
            const score = e.target.dataset.value;
            document.getElementById('rating-score').value = score;
            document.querySelectorAll('.star-rating').forEach(star => {
                star.classList.toggle('text-warning', star.dataset.value <= score);
            });
        }
    });
    
    // Rating form submission
    document.getElementById('rating-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'rate');
        formData.append('csrf_token', '<?php echo get_csrf_token(); ?>');

        fetch('../api/booking_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            showFeedback(data.message, data.success);
            if(data.success) {
                ratingModal.hide();
                setTimeout(() => location.reload(), 1500);
            }
        });
    });

    // Booking handler function
    function handleBooking(sessionId, repeatWeekly, paymentConfirmed = false) {
        const body = new FormData();
        body.append('action', 'book');
        body.append('sessionId', sessionId);
        body.append('csrf_token', '<?php echo get_csrf_token(); ?>');
        if (repeatWeekly) {
            body.append('repeat_weekly', 'true');
        }
        if (paymentConfirmed) {
            body.append('payment_confirmed', 'true');
        }

        fetch('../api/booking_handler.php', { method: 'POST', body: body })
            .then(res => res.json())
            .then(data => {
                if (data.payment_required) {
                    // Store details for payment modal
                    pendingSessionId = sessionId;
                    pendingRepeatWeekly = repeatWeekly;
                    document.getElementById('payment-amount').textContent = 'RM ' + parseFloat(data.price).toFixed(2);
                    paymentModal.show();
                } else {
                    showFeedback(data.message, data.success);
                    if (data.success) {
                        bookingModal.hide();
                        setTimeout(() => location.reload(), 1500);
                    }
                }
            });
    }
    
    // Cancellation handler function
    function handleCancellation(reservationId, cancelScope) {
        const body = new FormData();
        body.append('action', 'cancel');
        body.append('reservationId', reservationId);
        body.append('cancel_scope', cancelScope);
        body.append('csrf_token', '<?php echo get_csrf_token(); ?>');

        fetch('../api/booking_handler.php', { method: 'POST', body: body })
            .then(res => res.json())
            .then(data => {
                showFeedback(data.message, data.success);
                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            });
    }

    // Toast feedback function
    function showFeedback(message, success) {
        const toastEl = document.getElementById('feedback-toast');
        const toastBody = toastEl.querySelector('.toast-body');
        toastEl.classList.remove('bg-success', 'bg-danger');
        toastEl.classList.add(success ? 'bg-success' : 'bg-danger');
        toastBody.textContent = message;
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }
});
</script>

<style>
.star-rating {
    cursor: pointer;
    font-size: 1.5rem;
    color: #ccc;
}
.modal-header h5 {
    color: #000;
}
.modal-body {
    color: #000;
}
</style>

<?php include 'includes/client_footer.php'; ?>
