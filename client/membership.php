<?php
define('PAGE_TITLE', 'Membership');
require_once '../includes/config.php';
require_client();

$userId = $_SESSION['UserID'];
$feedback = [];

// Handle Purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_membership'])) {
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
            $feedback = ['type' => 'error', 'message' => 'Invalid membership plan selected.'];
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $feedback = ['type' => 'error', 'message' => 'A database error occurred. Please try again.'];
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
<style>
.card { background: var(--light-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
.card-title { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); margin-bottom: 1.5rem; }
.plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
.plan-card { text-align: center; padding: 2rem; border: 2px solid var(--border-color); border-radius: 12px; transition: all 0.3s ease; }
.plan-card:hover { transform: translateY(-5px); border-color: var(--primary-color); }
.plan-card h3 { font-size: 1.8rem; color: var(--primary-color); }
.plan-card .price { font-size: 2.5rem; font-weight: 800; margin: 1rem 0; }
.plan-card .duration { color: var(--text-dark); margin-bottom: 1.5rem; }
.plan-card ul { list-style: none; margin-bottom: 2rem; color: var(--text-dark); }
.plan-card li { padding: 0.5rem 0; }
.history-table { width: 100%; border-collapse: collapse; }
.history-table th, .history-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
.history-table th { color: var(--primary-color); }
.status-badge { padding: 0.3rem 0.6rem; border-radius: 6px; font-size: 0.8rem; font-weight: 700; text-transform: capitalize; color: white; }
.status-completed { background-color: var(--success-color); }
</style>

<div class="page-header">
    <h1>Membership</h1>
    <p>Manage your membership plan and payment history.</p>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?>"><?php echo $feedback['message']; ?></div>
<?php endif; ?>

<!-- Current Plan -->
<div class="card">
    <h2 class="card-title">Your Current Plan</h2>
    <?php if ($currentMembership): ?>
        <h3 style="font-size: 1.8rem; text-transform: capitalize;"><?php echo htmlspecialchars($currentMembership['Type']); ?> Plan</h3>
        <p><strong>Benefits:</strong> <?php echo htmlspecialchars($currentMembership['Benefits']); ?></p>
        <p><strong>Active Since:</strong> <?php echo format_date($currentMembership['PaymentDate']); ?></p>
    <?php else: ?>
        <p>You do not have an active membership plan. Choose one below to get started!</p>
    <?php endif; ?>
</div>

<!-- Available Plans -->
<div class="card">
    <h2 class="card-title">Choose Your Plan</h2>
    <div class="plans-grid">
        <?php foreach($membershipPlans as $plan): ?>
            <div class="plan-card">
                <h3 style="text-transform: capitalize;"><?php echo htmlspecialchars($plan['Type']); ?></h3>
                <div class="price"><?php echo format_currency($plan['Cost']); ?></div>
                <div class="duration"><?php echo $plan['Duration']; ?> days</div>
                <ul>
                    <?php 
                    $benefits = explode(',', $plan['Benefits']);
                    foreach ($benefits as $benefit) {
                        echo '<li>' . htmlspecialchars(trim($benefit)) . '</li>';
                    }
                    ?>
                </ul>
                <form action="membership.php" method="POST">
                    <input type="hidden" name="membershipId" value="<?php echo $plan['MembershipID']; ?>">
                    <button type="submit" name="purchase_membership" class="btn btn-primary">Purchase Plan</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Payment History -->
<div class="card">
    <h2 class="card-title">Payment History</h2>
    <table class="history-table">
        <thead>
            <tr><th>Date</th><th>Plan</th><th>Amount</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php if(empty($paymentHistory)): ?>
                <tr><td colspan="4" style="text-align: center;">No payment history found.</td></tr>
            <?php else: ?>
                <?php foreach($paymentHistory as $payment): ?>
                    <tr>
                        <td><?php echo format_date($payment['PaymentDate']); ?></td>
                        <td style="text-transform: capitalize;"><?php echo htmlspecialchars($payment['Type']); ?></td>
                        <td><?php echo format_currency($payment['Amount']); ?></td>
                        <td><span class="status-badge status-<?php echo strtolower($payment['Status']); ?>"><?php echo htmlspecialchars($payment['Status']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/client_footer.php'; ?>
