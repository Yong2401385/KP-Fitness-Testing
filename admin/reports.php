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
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch report data: ' . $e->getMessage()];
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

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Reports & Analytics</h1>
</div>

<?php if (isset($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>


<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header">
                Monthly Revenue (Last 12 Months)
            </div>
            <div class="card-body">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                Most Popular Classes
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php foreach($popular_classes as $class): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php echo htmlspecialchars($class['ClassName']); ?>
                            <span class="badge bg-primary rounded-pill"><?php echo $class['booking_count']; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                Active Membership Distribution
            </div>
            <div class="card-body">
                <canvas id="membershipChart"></canvas>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Chart.js global settings
    Chart.defaults.font.family = 'Inter';
    Chart.defaults.color = '#888';

    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo $revenue_labels; ?>,
            datasets: [{
                label: 'Revenue (RM)',
                data: <?php echo $revenue_values; ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            scales: {
                y: { beginAtZero: true }
            },
            plugins: {
                legend: {
                    display: false
                }
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
                backgroundColor: ['#0d6efd', '#17a2b8', '#ffc107', '#fd7e14'],
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
            }
        }
    });
});
</script>

<?php include 'includes/admin_footer.php'; ?>
