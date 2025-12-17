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
    SELECT r.ReservationID, r.is_recurring, CONCAT(s.SessionDate, ' ', s.StartTime) as StartTime, a.ClassName as ActivityName, c.CategoryName, r.Status
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
                        <div class="filter-card">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="category-filter" class="form-label">Filter by Category</label>
                                    <select id="category-filter" class="form-select">
                                        <option value="">All Categories</option>
                                        <?php foreach ($allCategories as $category): ?>
                                            <option value="<?php echo $category['CategoryID']; ?>"><?php echo htmlspecialchars($category['CategoryName']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="difficulty-filter" class="form-label">Filter by Difficulty</label>
                                    <select id="difficulty-filter" class="form-select">
                                        <option value="">All Levels</option>
                                        <option value="beginner">Beginner</option>
                                        <option value="intermediate">Intermediate</option>
                                        <option value="advanced">Advanced</option>
                                    </select>
                                </div>
                            </div>
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
    window.bookingConfig = {
        csrfToken: '<?php echo get_csrf_token(); ?>',
        isPremiumMember: <?php echo json_encode($isPremiumMember); ?>
    };
</script>
<script src="../assets/js/client-booking.js"></script>

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

/* Custom Calendar Styles */
#calendar-container .vanilla-calendar {
    background-color: #f8f9fa;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    padding: 15px;
    border: 1px solid #ccc; /* Darker border added */
}
#calendar-container .vanilla-calendar-day__btn_selected {
    background-color: #ff8c00 !important;
    color: white !important;
    border-radius: 50%;
}
#calendar-container .vanilla-calendar-day__btn_today {
    color: #ff8c00 !important;
    font-weight: bold;
    border: 1px solid #ff8c00;
    border-radius: 50%;
}
#calendar-container .vanilla-calendar-header__content {
    color: #333;
    font-weight: bold;
}
#calendar-container .vanilla-calendar-week__day {
    color: #666;
}

/* Filter Card Styles */
.filter-card {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border-left: 5px solid #ff8c00; /* Orange accent */
    margin-bottom: 20px;
    border: 1px solid #ccc; /* Darker border added */
}
.filter-card .form-label {
    font-weight: 600;
    color: #444;
}
.filter-card .form-select {
    border: 1px solid #e0e0e0;
    border-radius: 6px;
}
.filter-card .form-select:focus {
    border-color: #ff8c00;
    box-shadow: 0 0 0 0.25rem rgba(255, 140, 0, 0.25);
}
</style>

<?php include 'includes/client_footer.php'; ?>
