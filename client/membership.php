<?php
define('PAGE_TITLE', 'Membership');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];
$feedback = [];

// Handle Purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_membership'])) {
    validate_csrf_token($_POST['csrf_token']);
    $membershipId = intval($_POST['membershipId']);

    try {
        $stmt = $pdo->prepare("SELECT * FROM membership WHERE MembershipID = ?");
        $stmt->execute([$membershipId]);
        $membership = $stmt->fetch();

        if ($membership) {
            $pdo->beginTransaction();
            // Create a 'completed' payment record
            $stmt = $pdo->prepare("INSERT INTO payments (UserID, MembershipID, Amount, PaymentMethod, Status) VALUES (?, ?, ?, 'credit_card', 'completed')");
            $stmt->execute([$userId, $membershipId, $membership['Cost']]);
            
            // Update the user's membership
            $stmt = $pdo->prepare("UPDATE users SET MembershipID = ? WHERE UserID = ?");
            $stmt->execute([$membershipId, $userId]);
            
            $pdo->commit();
            $feedback = ['type' => 'success', 'message' => 'Membership purchased successfully! You can now book classes.'];
        } else {
            $feedback = ['type' => 'danger', 'message' => 'Invalid membership plan selected.'];
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $feedback = ['type' => 'danger', 'message' => 'A database error occurred. Please try again.'];
    }
}


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


<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Current Plan -->
<div class="card mb-4">
    <div class="card-header">
        Your Current Plan
    </div>
    <div class="card-body">
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
        <div class="row">
            <?php foreach($membershipPlans as $plan): ?>
                <div class="col-lg-4 mb-4">
                    <div class="card h-100 text-center">
                        <div class="card-header">
                            <h4 class="my-0 fw-normal text-capitalize"><?php echo htmlspecialchars($plan['Type']); ?></h4>
                        </div>
                        <div class="card-body">
                            <h1 class="card-title pricing-card-title"><?php echo format_currency($plan['Cost']); ?><small class="text-muted fw-light">/<?php echo $plan['Duration']; ?> days</small></h1>
                            <ul class="list-unstyled mt-3 mb-4">
                                <?php 
                                $benefits = explode(',', $plan['Benefits']);
                                foreach ($benefits as $benefit) {
                                    echo '<li>' . htmlspecialchars(trim($benefit)) . '</li>';
                                }
                                ?>
                            </ul>
                            <form action="membership.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                <input type="hidden" name="membershipId" value="<?php echo $plan['MembershipID']; ?>">
                                <button type="submit" name="purchase_membership" class="w-100 btn btn-lg btn-outline-primary">Purchase Plan</button>
                            </form>
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
    <div class="card-body">
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

<?php include 'includes/client_footer.php'; ?>
