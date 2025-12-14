<?php
define('PAGE_TITLE', 'Membership');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];

// --- Fetch Data for Display ---
// Get current membership
$stmt = $pdo->prepare("
    SELECT m.Type, m.Benefits, p.PaymentDate
    FROM users u
    JOIN membership m ON u.MembershipID = m.MembershipID
    LEFT JOIN payments p ON p.UserID = u.UserID AND p.MembershipID = m.MembershipID
    WHERE u.UserID = ? AND p.Status = 'completed'
    ORDER BY p.PaymentDate DESC LIMIT 1
");
$stmt->execute([$userId]);
$currentMembership = $stmt->fetch();

// Get all membership plans
$stmt = $pdo->prepare("SELECT * FROM membership WHERE IsActive = TRUE ORDER BY Cost");
$stmt->execute();
$membershipPlans = $stmt->fetchAll();

// Get payment history
$stmt = $pdo->prepare("SELECT p.PaymentDate, p.Amount, p.Status, m.Type FROM payments p JOIN membership m ON p.MembershipID = m.MembershipID WHERE p.UserID = ? ORDER BY p.PaymentDate DESC");
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
    <div class="card-header">
        Your Current Plan
    </div>
    <div class="card-body" id="current-plan-container">
        <?php if ($currentMembership): ?>
            <h3 class="card-title text-capitalize"><?php echo htmlspecialchars($currentMembership['Type']); ?> Plan</h3>
            <p><strong>Benefits:</strong> <?php echo htmlspecialchars($currentMembership['Benefits']); ?></p>
            <p class="text-muted">Active Since: <?php echo format_date($currentMembership['PaymentDate']); ?></p>
        <?php else: ?>
            <p>You do not have an active membership plan. Choose one below to get started!</p>
        <?php endif; ?>
    </div>
</div>

<!-- Available Plans -->
<div class="card mb-4">
    <div class="card-header">
        Choose Your Plan
    </div>
    <div class="card-body">
        <div class="row row-cols-1 row-cols-md-3 mb-3 text-center">
            <?php foreach($membershipPlans as $plan): ?>
                <div class="col">
                    <div class="card mb-4 rounded-3 shadow-sm">
                        <div class="card-header py-3">
                            <h4 class="my-0 fw-normal text-capitalize"><?php echo htmlspecialchars($plan['Type']); ?></h4>
                        </div>
                        <div class="card-body">
                            <h1 class="card-title pricing-card-title"><?php echo format_currency($plan['Cost']); ?><small class="text-muted fw-light">/<?php echo $plan['Duration']; ?> days</small></h1>
                            <ul class="list-unstyled mt-3 mb-4">
                                <?php 
                                $benefits = explode(',', $plan['Benefits']);
                                foreach ($benefits as $benefit) {
                                    echo '<li><i class="fas fa-check text-success me-2"></i>' . htmlspecialchars(trim($benefit)) . '</li>';
                                }
                                ?>
                            </ul>
                            <button type="button" class="w-100 btn btn-lg btn-outline-primary choose-plan-btn" data-bs-toggle="modal" data-bs-target="#paymentModal" data-membership-id="<?php echo $plan['MembershipID']; ?>">Choose Plan</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Payment History -->
<div class="card">
    <div class="card-header">
        Payment History
    </div>
    <div class="card-body" id="payment-history-container">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr><th>Date</th><th>Plan</th><th>Amount</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($paymentHistory)): ?>
                        <tr><td colspan="4" class="text-center">No payment history found.</td></tr>
                    <?php else: ?>
                        <?php foreach($paymentHistory as $payment): ?>
                            <tr>
                                <td><?php echo format_date($payment['PaymentDate']); ?></td>
                                <td class="text-capitalize"><?php echo htmlspecialchars($payment['Type']); ?></td>
                                <td><?php echo format_currency($payment['Amount']); ?></td>
                                <td><span class="badge bg-success text-capitalize"><?php echo htmlspecialchars($payment['Status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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

    document.querySelectorAll('.choose-plan-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            selectedMembershipId = e.target.dataset.membershipId;
        });
    });

    document.querySelectorAll('.payment-method-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            document.querySelectorAll('.payment-method-btn').forEach(btn => btn.classList.remove('active'));
            e.target.classList.add('active');
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
                        window.location.href = 'dashboard.php?payment=success';
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
