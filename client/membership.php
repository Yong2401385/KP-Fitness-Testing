<?php
define('PAGE_TITLE', 'Membership');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];

// --- Fetch Data for Display ---
// Get current membership
$stmt = $pdo->prepare("
    SELECT u.MembershipID, m.PlanName, m.Benefits, u.MembershipStartDate, u.MembershipEndDate, u.AutoRenew, nm.PlanName as NextPlanName
    FROM users u
    JOIN membership m ON u.MembershipID = m.MembershipID
    LEFT JOIN membership nm ON u.NextMembershipID = nm.MembershipID
    WHERE u.UserID = ?
");
$stmt->execute([$userId]);
$currentMembership = $stmt->fetch();

$daysLeft = 0;
$classesLeft = null;
$currentPlanBenefits = [];

if ($currentMembership) {
    $endDate = new DateTime($currentMembership['MembershipEndDate']);
    $today = new DateTime();
    $daysLeft = $today > $endDate ? 0 : $today->diff($endDate)->days;

    // Logic for 8 Class Membership
    if (stripos($currentMembership['PlanName'], '8 Class') !== false) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM reservations r 
            JOIN sessions s ON r.SessionID = s.SessionID 
            WHERE r.UserID = ? 
            AND r.Status IN ('booked', 'attended', 'Done', 'Rated')
            AND s.SessionDate BETWEEN ? AND ?
        ");
        $stmt->execute([$userId, $currentMembership['MembershipStartDate'], $currentMembership['MembershipEndDate']]);
        $usedClasses = $stmt->fetchColumn();
        $classesLeft = max(0, 8 - $usedClasses);
    }

    // Define Benefits for Current Plan Display
    if (stripos($currentMembership['PlanName'], 'Annual') !== false) {
        $currentPlanBenefits = [
            "Two months FREE",
            "Unlimited Classes",
            "Save RM 233/year",
            "Priority booking",
            "Access to all Perks",
            "Full AI Planner (All Goals & Levels)",
            "Save Unlimited Workout Plans",
            "2-Week Recurring Booking"
        ];
    } elseif (stripos($currentMembership['PlanName'], 'Unlimited') !== false) {
        $currentPlanBenefits = [
            "Unlimited Classes",
            "Access to all Perks",
            "Priority booking",
            "Free fitness assessment",
            "Full AI Planner (All Goals & Levels)",
            "Save Unlimited Workout Plans",
            "2-Week Recurring Booking"
        ];
    } else {
        $currentPlanBenefits = [
            "8 Classes per Cycle",
            "Cancel Anytime",
            "Expert Coaches",
            "Full Gym Access",
            "Basic AI Planner",
            "Standard Booking"
        ];
    }
}


// Get all membership plans
$stmt = $pdo->prepare("SELECT * FROM membership WHERE IsActive = TRUE AND Type != 'onetime' ORDER BY Cost");
$stmt->execute();
$membershipPlans = $stmt->fetchAll();

// Get payment history
$stmt = $pdo->prepare("SELECT p.PaymentID, p.PaymentDate, p.Amount, p.Status, p.PaymentType, p.Description, p.PaymentMethod FROM payments p WHERE p.UserID = ? ORDER BY p.PaymentDate DESC");
$stmt->execute([$userId]);
$paymentHistory = $stmt->fetchAll();


include 'includes/client_header.php';
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Membership</h1>
</div>

<div id="feedback-toast" class="toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 p-3" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
        <div class="toast-body"></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
</div>

<!-- Current Plan -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Current Membership Plan</h5>
        <div>
            <?php if ($currentMembership): ?>
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#membershipPlansModal">Upgrade / Downgrade</button>
            <?php endif; ?>
            <?php if ($currentMembership && ($currentMembership['AutoRenew'] || stripos($currentMembership['PlanName'], '8 Class') !== false)): ?>
                <button class="btn btn-outline-danger" id="cancel-membership-btn">Cancel Membership</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body" id="current-plan-container">
        <?php if ($currentMembership): ?>
            <h3 class="card-title text-capitalize text-primary fw-bold">
                <?php echo htmlspecialchars($currentMembership['PlanName']); ?>
            </h3>
            <?php if (!empty($currentMembership['NextPlanName'])): ?>
                <div class="alert alert-info py-2 mt-2">
                    <small><i class="fas fa-clock me-1"></i> Scheduled Downgrade: <strong><?php echo htmlspecialchars($currentMembership['NextPlanName']); ?></strong> starts on <?php echo htmlspecialchars(format_date($currentMembership['MembershipEndDate'])); ?></small>
                </div>
            <?php endif; ?>
            
            <div class="mt-3 mb-3">
                <strong>Benefits:</strong>
                <ul class="list-unstyled mt-2 ms-2">
                    <?php foreach($currentPlanBenefits as $benefit): ?>
                        <li><i class="fas fa-check text-success me-2"></i> <?php echo $benefit; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <strong>Active Date:</strong> <?php echo htmlspecialchars(format_date($currentMembership['MembershipStartDate'])); ?>
                </div>
                <div class="col-md-4">
                    <strong>Expire Date:</strong> <?php echo htmlspecialchars(format_date($currentMembership['MembershipEndDate'])); ?>
                </div>
                <div class="col-md-4">
                    <?php if ($classesLeft !== null): ?>
                        <strong>Classes Left:</strong> <span class="badge bg-success"><?php echo $classesLeft; ?></span>
                    <?php else: ?>
                        <strong>Days Left:</strong> <span class="badge bg-success"><?php echo $daysLeft; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$currentMembership['AutoRenew'] && $daysLeft > 0): ?>
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i> Your membership will expire on <?php echo htmlspecialchars(format_date($currentMembership['MembershipEndDate'])); ?>.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center p-4">
                <p class="lead">You do not have an active membership plan.</p>
                <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#membershipPlansModal">Subscribe Membership</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Payment History -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Payment History</h5>
    </div>
    <div class="card-body" id="payment-history-container">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr><th>Date</th><th>Payment</th><th>Description</th><th>Amount</th><th>Status</th><th>Details</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($paymentHistory)): ?>
                        <tr><td colspan="6" class="text-center text-muted">No payment history found.</td></tr>
                    <?php else: ?>
                        <?php foreach($paymentHistory as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(format_date($payment['PaymentDate'])); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($payment['PaymentType'] ?? 'Membership'); ?></span></td>
                                <td><?php echo htmlspecialchars($payment['Description'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars(format_currency($payment['Amount'])); ?></td>
                                <td>
                                    <?php if ($payment['Status'] == 'completed'): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php elseif ($payment['Status'] == 'refunded'): ?>
                                        <span class="badge bg-info">Refunded</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><?php echo htmlspecialchars($payment['Status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary view-payment-btn" 
                                        data-id="<?php echo $payment['PaymentID']; ?>"
                                        data-date="<?php echo htmlspecialchars(format_date($payment['PaymentDate'])); ?>"
                                        data-amount="<?php echo htmlspecialchars(format_currency($payment['Amount'])); ?>"
                                        data-method="<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $payment['PaymentMethod']))); ?>"
                                        data-status="<?php echo htmlspecialchars(ucfirst($payment['Status'])); ?>"
                                        data-type="<?php echo htmlspecialchars($payment['PaymentType'] ?? 'Membership'); ?>"
                                        data-desc="<?php echo htmlspecialchars($payment['Description'] ?? '-'); ?>"
                                    >
                                        Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>Transaction ID:</strong> <span class="text-secondary" id="detail-id"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>Date:</strong> <span class="text-secondary" id="detail-date"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>Type:</strong> <span class="text-secondary" id="detail-type"></span>
                    </div>
                    <div class="list-group-item">
                        <strong>Description:</strong><br>
                        <span class="text-secondary" id="detail-desc"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>Payment Method:</strong> <span class="text-secondary" id="detail-method"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>Status:</strong> <span class="text-secondary" id="detail-status"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between bg-light">
                        <strong class="h5 mb-0">Total Amount:</strong> <span class="h5 mb-0 text-primary" id="detail-amount"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Confirmation Modal -->
<div class="modal fade" id="cancelMembershipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Cancel Membership</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel your membership auto-renewal?</p>
                <p class="text-muted small">You will retain your benefits until the end of your current billing cycle (<span id="cancel-end-date"><?php echo htmlspecialchars(format_date($currentMembership['MembershipEndDate'] ?? '')); ?></span>).</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Membership</button>
                <button type="button" class="btn btn-danger" id="confirm-cancel-btn">Confirm Cancellation</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Increase Modal Width */
    .modal-xl {
        --bs-modal-width: 95vw;
        max-width: 1600px;
    }

    /* New Membership Card Styles */
    .plan-card {
        border-radius: 12px;
        padding: 2.5rem; /* Increased padding for larger look */
        position: relative;
        background: #fff; 
        border: 1px solid rgba(0,0,0,0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .plan-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(255, 107, 0, 0.2);
    }
    .plan-badge {
        position: absolute;
        top: -15px;
        left: 50%;
        transform: translateX(-50%);
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.9rem;
        text-transform: uppercase;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        z-index: 2;
        width: max-content;
    }
    .badge-popular {
        background: #ff6b00; /* Primary Orange */
        color: #fff;
    }
    .badge-saving {
        background: #2ecc71; /* Green */
        color: #fff;
    }
    .plan-header {
        text-align: center;
        margin-bottom: 2rem;
        border-bottom: 1px solid rgba(0,0,0,0.1);
        padding-bottom: 1rem;
    }
    .plan-price {
        font-size: 3rem; /* Larger price */
        font-weight: 800;
        color: #ff6b00;
    }
    .plan-period {
        font-size: 1rem;
        opacity: 0.7;
        display: block;
        margin-top: -5px;
        color: #666;
    }
    .plan-features {
        list-style: none;
        padding: 0;
        text-align: left;
        margin: 0;
        flex-grow: 1; /* Pushes content to fill height */
    }
    .plan-features li {
        margin-bottom: 1.2rem;
        display: flex;
        align-items: center;
        font-size: 1rem;
        color: #333;
    }
    .check-icon {
        color: #ff6b00;
        margin-right: 15px;
        background: rgba(255, 107, 0, 0.1);
        border-radius: 50%;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        flex-shrink: 0;
    }
    .btn-join {
        background: linear-gradient(135deg, #ff6b00, #ff6600);
        color: white;
        border: none;
        border-radius: 50px;
        font-weight: bold;
        padding: 12px 0;
        font-size: 1.1rem;
        transition: all 0.3s ease;
    }
    .btn-join:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(255, 107, 0, 0.3);
        color: white;
    }
</style>

<!-- Membership Plans Modal -->
<div class="modal fade" id="membershipPlansModal" tabindex="-1" aria-labelledby="membershipPlansModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-dark pt-0 pb-5 px-4">
                <div class="text-center mb-5">
                    <h2 class="fw-bold text-white">Choose Your Plan</h2>
                    <p class="lead text-white">Unlock your potential with our flexible membership options</p>
                </div>
                <div class="row row-cols-1 row-cols-lg-3 g-4 justify-content-center">
                    <?php foreach($membershipPlans as $plan): 
                        // Skip if current plan (Upgrade/Downgrade logic)
                        if ($currentMembership && $plan['MembershipID'] == $currentMembership['MembershipID']) {
                            continue;
                        }

                        // Determine badge
                        $badge = '';
                        $badgeClass = '';
                        if (stripos($plan['PlanName'], 'Unlimited') !== false) {
                            $badge = 'MOST POPULAR';
                            $badgeClass = 'badge-popular';
                        } elseif (stripos($plan['PlanName'], 'Annual') !== false) {
                            $badge = 'HUGE SAVINGS';
                            $badgeClass = 'badge-saving';
                        }
                        
                        // Override display price and subText based on plan name
                        $displayPrice = htmlspecialchars(format_currency($plan['Cost']));
                        $displaySubText = '*Pay per ' . ($plan['Duration'] >= 30 ? 'month' : 'class');
                        if ($plan['Duration'] >= 365) $displaySubText = '*One time purchase';
                        elseif ($plan['Duration'] >= 30) $displaySubText = '*Autopay every 4 weeks';

                        if (stripos($plan['PlanName'], '8 Class') !== false) {
                            $displayPrice = 'RM199.00';
                            $displaySubText = '*Autopay every 4 weeks';
                        } elseif (stripos($plan['PlanName'], 'Unlimited') !== false) {
                            $displayPrice = 'RM289.00';
                            $displaySubText = '*Autopay every 4 weeks';
                        } elseif (stripos($plan['PlanName'], 'Annual') !== false) {
                            $displayPrice = 'RM2,899.00';
                            $displaySubText = '*One time purchase';
                        }
                        
                        // Use full plan name
                        $planFullName = $plan['PlanName'];

                        // Define Custom Benefits logic merging Marketing Image + System Logic
                        $displayBenefits = [];
                        if (stripos($plan['PlanName'], 'Annual') !== false) {
                            $displayBenefits = [
                                "Two months FREE",
                                "Unlimited Classes",
                                "Save RM 233/year",
                                "Priority booking",
                                "Access to all Perks",
                                "<strong>Full AI Planner (All Goals & Levels)</strong>",
                                "<strong>Save Unlimited Workout Plans</strong>",
                                "<strong>2-Week Recurring Booking</strong>"
                            ];
                        } elseif (stripos($plan['PlanName'], 'Unlimited') !== false) {
                            $displayBenefits = [
                                "Unlimited Classes",
                                "Access to all Perks",
                                "Priority booking",
                                "Free fitness assessment",
                                "<strong>Full AI Planner (All Goals & Levels)</strong>",
                                "<strong>Save Unlimited Workout Plans</strong>",
                                "<strong>2-Week Recurring Booking</strong>"
                            ];
                        } else {
                            // Default / 8 Class
                            $displayBenefits = [
                                "8 Classes per Cycle",
                                "Cancel Anytime",
                                "Expert Coaches",
                                "Full Gym Access",
                                "Basic AI Planner",
                                "Standard Booking"
                            ];
                        }
                    ?>
                        <div class="col">
                            <div class="card plan-card">
                                <?php if ($badge): ?>
                                    <div class="plan-badge <?= $badgeClass ?>"><?= $badge ?></div>
                                <?php endif; ?>
                                
                                <div class="card-body d-flex flex-column p-0">
                                    <div class="plan-header">
                                        <h4 class="fw-bold mb-3 text-uppercase text-primary"><?= htmlspecialchars($planFullName) ?></h4>
                                        <div class="plan-price"><?= $displayPrice ?></div>
                                        <small class="plan-period"><?= $displaySubText ?></small>
                                    </div>

                                    <button type="button" class="btn btn-join w-100 mb-4 choose-plan-btn" data-membership-id="<?= $plan['MembershipID'] ?>">JOIN TODAY</button>

                                    <ul class="plan-features">
                                        <?php 
                                        foreach ($displayBenefits as $benefit) {
                                            // Allow HTML tags like <strong>
                                            echo '<li><div class="check-icon"><i class="fas fa-check"></i></div>' . $benefit . '</li>';
                                        }
                                        ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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
                    <p>Redirecting you to the dashboard...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.membershipConfig = {
        csrfToken: '<?php echo get_csrf_token(); ?>'
    };
</script>
<script src="../assets/js/client-membership.js"></script>

<?php include 'includes/client_footer.php'; ?>
