<?php
define('PAGE_TITLE', 'Membership');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];

// --- Fetch Data for Display ---
// Get current membership
$stmt = $pdo->prepare("
    SELECT m.PlanName, m.Benefits, u.MembershipStartDate, u.MembershipEndDate
    FROM users u
    JOIN membership m ON u.MembershipID = m.MembershipID
    WHERE u.UserID = ?
");
$stmt->execute([$userId]);
$currentMembership = $stmt->fetch();

if ($currentMembership) {
    $endDate = new DateTime($currentMembership['MembershipEndDate']);
    $today = new DateTime();
    $daysLeft = $today > $endDate ? 0 : $today->diff($endDate)->days;
}


// Get all membership plans
$stmt = $pdo->prepare("SELECT * FROM membership WHERE IsActive = TRUE AND Type != 'onetime' ORDER BY Cost");
$stmt->execute();
$membershipPlans = $stmt->fetchAll();

// Get payment history
$stmt = $pdo->prepare("SELECT p.PaymentDate, p.Amount, p.Status, m.PlanName FROM payments p JOIN membership m ON p.MembershipID = m.MembershipID WHERE p.UserID = ? ORDER BY p.PaymentDate DESC");
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
        <?php if ($currentMembership && $currentMembership['PlanName'] !== 'Annual Class Membership'): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#membershipPlansModal">Upgrade</button>
        <?php endif; ?>
    </div>
    <div class="card-body" id="current-plan-container">
        <?php if ($currentMembership): ?>
            <h3 class="card-title text-capitalize">
                <?php echo htmlspecialchars(str_replace(' Class Membership', '', $currentMembership['PlanName'])); ?>
            </h3>
            <p><strong>Benefits:</strong> <?php echo htmlspecialchars($currentMembership['Benefits']); ?></p>
            <div class="row">
                <div class="col-md-4">
                    <strong>Active Date:</strong> <?php echo htmlspecialchars(format_date($currentMembership['MembershipStartDate'])); ?>
                </div>
                <div class="col-md-4">
                    <strong>Expire Date:</strong> <?php echo htmlspecialchars(format_date($currentMembership['MembershipEndDate'])); ?>
                </div>
                <div class="col-md-4">
                    <strong>Days Left:</strong> <span class="badge bg-success"><?php echo $daysLeft; ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center p-4">
                <p class="lead">You do not have an active membership plan.</p>
                <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#membershipPlansModal">Subscribe Membership</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Membership History -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Membership History</h5>
    </div>
    <div class="card-body" id="payment-history-container">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr><th>Date</th><th>Plan</th><th>Amount</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($paymentHistory)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No membership history found.</td></tr>
                    <?php else: ?>
                        <?php foreach($paymentHistory as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(format_date($payment['PaymentDate'])); ?></td>
                                <td class="text-capitalize"><?php echo htmlspecialchars(str_replace(' Class Membership', '', $payment['PlanName'])); ?></td>
                                <td><?php echo htmlspecialchars(format_currency($payment['Amount'])); ?></td>
                                <td><span class="badge bg-success text-capitalize"><?php echo htmlspecialchars($payment['Status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Membership Plans Modal -->
<div class="modal fade" id="membershipPlansModal" tabindex="-1" aria-labelledby="membershipPlansModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="membershipPlansModalLabel">Choose Your Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row row-cols-1 row-cols-md-3 g-4">
                    <?php foreach($membershipPlans as $plan): ?>
                        <div class="col">
                            <div class="pricing-card h-100">
                                <div class="membership-name"><?php echo htmlspecialchars(str_replace(' Class Membership', '', $plan['PlanName'])); ?></div>
                                <div class="price"><?php echo htmlspecialchars(format_currency($plan['Cost'])); ?></div>
                                <div class="price-freq">/<?php echo $plan['Duration'] >= 365 ? 'year' : 'month'; ?></div>
                                <button type="button" class="w-100 btn btn-join choose-plan-btn" data-membership-id="<?php echo $plan['MembershipID']; ?>">Choose Plan</button>
                                <hr>
                                <ul class="benefits list-unstyled">
                                    <?php 
                                    $benefits = explode(',', $plan['Benefits']);
                                    foreach ($benefits as $benefit) {
                                        echo '<li><i class="fas fa-check-circle text-success me-2"></i>' . htmlspecialchars(trim($benefit)) . '</li>';
                                    }
                                    ?>
                                </ul>
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
document.addEventListener('DOMContentLoaded', () => {
    let selectedMembershipId = null;
    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    const membershipPlansModal = new bootstrap.Modal(document.getElementById('membershipPlansModal'));

    document.querySelectorAll('.choose-plan-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            selectedMembershipId = e.currentTarget.dataset.membershipId;
            membershipPlansModal.hide();
            paymentModal.show();
        });
    });

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

        setTimeout(() => {
            const body = new FormData();
            body.append('membershipId', selectedMembershipId);
            body.append('csrf_token', '<?php echo get_csrf_token(); ?>');

            fetch('../api/purchase_membership.php', {
                method: 'POST',
                body: body
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('payment-loading').classList.add('d-none');
                document.getElementById('payment-success').classList.remove('d-none');
                if (data.success) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    // In a real app, you would show an error message in the modal
                    window.location.href = 'membership.php?payment=failed';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                window.location.href = 'membership.php?payment=failed';
            });
        }, 3000);
    });
});
</script>

<?php include 'includes/client_footer.php'; ?>
