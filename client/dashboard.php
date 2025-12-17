<?php
define('PAGE_TITLE', 'Client Dashboard');
require_once '../includes/config.php';
require_client(); // Ensure only clients can access

// Get user data
$userId = $_SESSION['UserID'];
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE UserID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        // User not found (e.g., deleted), force logout
        redirect('../logout.php');
    }
    
    // Calculate BMI
    $bmi = calculate_bmi($user['Height'], $user['Weight']);
    $bmiCategory = get_bmi_category($bmi);
    
    // Get upcoming bookings (next 5)
    $stmt = $pdo->prepare(query: "
        SELECT s.SessionDate, s.StartTime, a.ClassName 
        FROM reservations r
        JOIN sessions s ON r.SessionID = s.SessionID
        JOIN activities a ON s.ClassID = a.ClassID
        WHERE r.UserID = ? AND r.Status = 'booked' AND s.SessionDate >= CURDATE()
        ORDER BY s.SessionDate, s.StartTime
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $upcomingBookings = $stmt->fetchAll();

    // Get workout plan count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM workout_plans WHERE UserID = ?");
    $stmt->execute([$userId]);
    $workoutPlanCount = $stmt->fetchColumn();

    // Get current membership
    $stmt = $pdo->prepare("
        SELECT m.PlanName, p.Status as PaymentStatus
        FROM users u 
        LEFT JOIN membership m ON u.MembershipID = m.MembershipID
        LEFT JOIN payments p ON p.UserID = u.UserID AND p.MembershipID = m.MembershipID
        WHERE u.UserID = ?
        ORDER BY p.PaymentDate DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $membership = $stmt->fetch();

    // Check if profile is incomplete
    $profileIncomplete = (
        empty($user['Phone']) || 
        empty($user['DateOfBirth']) || 
        empty($user['Height']) || 
        empty($user['Weight']) ||
        empty($user['Gender'])
    );

    $showCompleteProfileModal = false;

    if ($profileIncomplete) {
        // Show modal only if not dismissed in session
        if (!isset($_SESSION['profile_prompt_dismissed'])) {
            $showCompleteProfileModal = true;
        }

        // Ensure persistent notification exists
        $stmt = $pdo->prepare("SELECT NotificationID FROM notifications WHERE UserID = ? AND Title = 'Action Required: Complete Profile' AND IsRead = 0");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            create_notification($userId, 'Action Required: Complete Profile', 'Please complete your profile details to access all features. Click here to setup now.', 'warning');
        }
    }

} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch dashboard data: ' . $e->getMessage()];
    $user = [];
    $upcomingBookings = [];
    $workoutPlanCount = 0;
    $membership = null;
    $bmi = 'N/A';
    $bmiCategory = 'N/A';
    $showCompleteProfileModal = false;
}

$motivationalQuotes = [
    "The only bad workout is the one that didn't happen.",
    "Your body can stand almost anything. It’s your mind that you have to convince.",
    "Success isn’t always about greatness. It’s about consistency. Consistent hard work gains success. Greatness will come.",
    "The secret of getting ahead is getting started.",
    "Don't limit your challenges. Challenge your limits."
];
$quote = $motivationalQuotes[array_rand($motivationalQuotes)];

include 'includes/client_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
</div>

<div class="card p-4 mb-4">
    <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $user['FullName'])[0]); ?>!</h2>
    <p class="lead">Ready to continue your fitness journey? Here's a snapshot of your progress.</p>
    <p class="fst-italic">"<?php echo htmlspecialchars($quote); ?>"</p>
    <div class="mt-3">
        <a href="booking.php" class="btn btn-primary btn-lg">Book a Class</a>
        <a href="workout_planner.php" class="btn btn-primary btn-lg">AI Workout Planner</a>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="display-4 fw-bold text-primary mb-2"><?php echo count($upcomingBookings); ?></div>
                <h6 class="mt-auto">Upcoming Classes</h6>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="display-4 fw-bold text-primary mb-2"><?php echo $workoutPlanCount; ?></div>
                <h6 class="mt-auto">Saved Workouts</h6>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="display-4 fw-bold text-primary mb-2"><?php echo $bmi; ?></div>
                <h6 class="mt-auto"><?php echo $bmiCategory; ?> (BMI)</h6>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="display-4 fw-bold text-primary text-capitalize mb-2">
                    <?php 
                    if (!empty($membership['PlanName'])) {
                        if ($membership['PlanName'] === 'Unlimited Class Membership') {
                            echo 'Unlimited';
                        } elseif ($membership['PlanName'] === 'Annual Class Membership') {
                            echo 'Annual';
                        } elseif ($membership['PlanName'] === '8 Class Membership') {
                            echo '8 Class';
                        } else {
                            echo htmlspecialchars($membership['PlanName']); // Fallback, though ideally all known plans are covered
                        }
                    } else {
                        echo '-';
                    }
                    ?>
                </div>
                <h6 class="mt-auto">Membership</h6>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                Upcoming Classes
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php if (empty($upcomingBookings)): ?>
                        <li class="list-group-item">No upcoming classes. Why not book one?</li>
                    <?php else: ?>
                        <?php foreach ($upcomingBookings as $booking): ?>
                            <li class="list-group-item">
                                <strong><?php echo htmlspecialchars($booking['ClassName']); ?></strong><br>
                                <small><?php echo htmlspecialchars(format_date($booking['SessionDate'])); ?> at <?php echo htmlspecialchars(format_time($booking['StartTime'])); ?></small>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                Health Stats
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush" id="health-stats-list">
                    <li class="list-group-item d-flex justify-content-between"><strong>Height:</strong> <?php echo htmlspecialchars($user['Height'] ?? 'N/A'); ?> cm</li>
                    <li class="list-group-item d-flex justify-content-between"><strong>Weight:</strong> <?php echo htmlspecialchars($user['Weight'] ?? 'N/A'); ?> kg</li>
                    <li class="list-group-item d-flex justify-content-between"><strong>BMI:</strong> <?php echo $bmi; ?> (<?php echo $bmiCategory; ?>)</li>
                </ul>
                <canvas id="bmiChart" class="mt-3"></canvas>
                <button id="update-stats-btn" class="btn btn-secondary mt-3">Update Stats</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Stats Modal -->
<div class="modal fade" id="updateStatsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Health Stats</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="updateStatsForm">
                    <div class="mb-3">
                        <label for="stats-height" class="form-label">Height (cm)</label>
                        <input type="number" class="form-control bg-light text-secondary" id="stats-height" name="height" required>
                    </div>
                    <div class="mb-3">
                        <label for="stats-weight" class="form-label">Weight (kg)</label>
                        <input type="number" class="form-control bg-light text-secondary" id="stats-weight" name="weight" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Complete Profile Modal -->
<div class="modal fade" id="completeProfileModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-white" style="background-color: #ff6b00;">
                <h5 class="modal-title"><i class="fas fa-user-check me-2"></i> Complete Your Profile</h5>
            </div>
            <div class="modal-body">
                <p>Welcome to KP Fitness! To provide you with the best personalized experience, please complete your profile details.</p>
                <form id="completeProfileForm">
                    <div class="mb-3">
                        <label for="cp-phone" class="form-label">Phone Number</label>
                        <input type="text" class="form-control bg-white text-dark" id="cp-phone" name="phone" placeholder="e.g. 01X-XXX XXXX" required>
                        <div class="invalid-feedback">Format: 01X-XXX XXXX or 01X-XXXX XXXX</div>
                    </div>
                    <div class="mb-3">
                        <label for="cp-dob" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control bg-white text-dark" id="cp-dob" name="dateOfBirth" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cp-height" class="form-label">Height (cm)</label>
                            <input type="number" class="form-control bg-white text-dark" id="cp-height" name="height" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="cp-weight" class="form-label">Weight (kg)</label>
                            <input type="number" class="form-control bg-white text-dark" id="cp-weight" name="weight" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="cp-gender" class="form-label">Gender</label>
                        <select class="form-select bg-white text-dark" id="cp-gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Save & Continue</button>
                        <button type="button" class="btn btn-outline-secondary" id="dismiss-profile-btn" data-bs-dismiss="modal">Set Up Later</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Chatbot code will be moved to client_footer.php -->

<script>
    window.dashboardConfig = {
        csrfToken: window.clientConfig.csrfToken, // Use global csrfToken
        userHeight: <?php echo json_encode($user['Height'] ?? 0); ?>,
        showCompleteProfile: <?php echo $showCompleteProfileModal ? 'true' : 'false'; ?>
    };
</script>
<script src="../assets/js/client-dashboard.js"></script>

<?php include 'includes/client_footer.php'; ?>