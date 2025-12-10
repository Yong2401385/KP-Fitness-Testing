<?php
define('PAGE_TITLE', 'Reports & Analytics');
require_once '../includes/config.php';
require_admin();

// --- Fetch Report Data ---
try {
    // Revenue data (last 12 months)
    $revenue_data = $pdo->query("
        SELECT 
            DATE_FORMAT(PaymentDate, '%Y-%m') as month,
            SUM(Amount) as revenue
        FROM payments 
        WHERE Status = 'completed' AND PaymentDate >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(PaymentDate, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Popular classes (all time)
    $popular_classes = $pdo->query("
        SELECT c.ClassName, COUNT(r.ReservationID) as booking_count 
        FROM classes c 
        JOIN sessions s ON c.ClassID = s.ClassID 
        JOIN reservations r ON s.SessionID = r.SessionID 
        GROUP BY c.ClassID 
        ORDER BY booking_count DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Membership distribution
    $membership_dist = $pdo->query("
        SELECT m.Type, COUNT(u.UserID) as member_count 
        FROM users u
        JOIN membership m ON u.MembershipID = m.MembershipID
        WHERE u.Role = 'client' AND u.IsActive = TRUE
        GROUP BY u.MembershipID
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $feedback = ['type' => 'error', 'message' => 'Could not fetch report data: ' . $e->getMessage()];
    $revenue_data = $popular_classes = $membership_dist = [];
}

// Prepare data for Chart.js
$revenue_labels = json_encode(array_column($revenue_data, 'month'));
$revenue_values = json_encode(array_column($revenue_data, 'revenue'));

$pop_class_labels = json_encode(array_column($popular_classes, 'ClassName'));
$pop_class_values = json_encode(array_column($popular_classes, 'booking_count'));

$membership_labels = json_encode(array_column($membership_dist, 'Type'));
$membership_values = json_encode(array_column($membership_dist, 'member_count'));


include 'includes/admin_header.php';
?>

<style>
.card {
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
}
.card-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 1.5rem;
}
.charts-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}
.list-card ul {
    list-style: none;
}
.list-card li {
    display: flex;
    justify-content: space-between;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border-color);
}
.list-card li:last-child {
    border-bottom: none;
}
.list-card .value {
    font-weight: 700;
    color: var(--primary-color);
}
@media (max-width: 992px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-header">
    <h1>Reports & Analytics</h1>
    <p>Insights into your business performance.</p>
</div>

<?php if (isset($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?>">
        <?php echo $feedback['message']; ?>
    </div>
<?php endif; ?>


<div class="charts-grid">
    <div class="card">
        <h2 class="card-title">Monthly Revenue (Last 12 Months)</h2>
        <canvas id="revenueChart"></canvas>
    </div>
    <div class="card list-card">
        <h2 class="card-title">Most Popular Classes</h2>
        <ul>
            <?php foreach($popular_classes as $class): ?>
                <li>
                    <span><?php echo htmlspecialchars($class['ClassName']); ?></span>
                    <span class="value"><?php echo $class['booking_count']; ?> Bookings</span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="card">
    <h2 class="card-title">Active Membership Distribution</h2>
    <div style="max-width: 400px; margin: auto;">
        <canvas id="membershipChart"></canvas>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo $revenue_labels; ?>,
            datasets: [{
                label: 'Revenue (RM)',
                data: <?php echo $revenue_values; ?>,
                borderColor: 'var(--primary-color)',
                backgroundColor: 'rgba(255, 107, 0, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Membership Chart
    const membershipCtx = document.getElementById('membershipChart').getContext('2d');
    new Chart(membershipCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo $membership_labels; ?>,
            datasets: [{
                data: <?php echo $membership_values; ?>,
                backgroundColor: ['#ff6b00', '#ff8533', '#ffa666', '#ffc499'],
                borderColor: 'var(--light-bg)',
            }]
        },
    });
});
</script>

<?php include 'includes/admin_footer.php'; ?>
