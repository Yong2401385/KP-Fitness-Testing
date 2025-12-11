<?php
define('PAGE_TITLE', 'Admin Dashboard');
require_once '../includes/config.php';
require_admin(); // Ensure only admins can access this page

// Get dashboard statistics
try {
    // Total users (excluding admins)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE Role != 'admin'");
    $stmt->execute();
    $totalUsers = $stmt->fetchColumn();
    
    // Total trainers
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE Role = 'trainer'");
    $stmt->execute();
    $totalTrainers = $stmt->fetchColumn();
    
    // Total clients
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE Role = 'client'");
    $stmt->execute();
    $totalClients = $stmt->fetchColumn();
    
    // Total active classes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE IsActive = TRUE");
    $stmt->execute();
    $totalClasses = $stmt->fetchColumn();
    
    // Total sessions this month
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE MONTH(SessionDate) = MONTH(CURRENT_DATE()) AND YEAR(SessionDate) = YEAR(CURRENT_DATE())");
    $stmt->execute();
    $totalSessionsThisMonth = $stmt->fetchColumn();
    
    // Total revenue this month
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(Amount), 0) FROM payments WHERE MONTH(PaymentDate) = MONTH(CURRENT_DATE()) AND YEAR(PaymentDate) = YEAR(CURRENT_DATE()) AND Status = 'completed'");
    $stmt->execute();
    $monthlyRevenue = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $error_message = 'Error loading dashboard data: ' . $e->getMessage();
    // Initialize stats to 0 on error
    $totalUsers = $totalTrainers = $totalClients = $totalClasses = $totalSessionsThisMonth = $monthlyRevenue = 0;
}

include 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Admin Dashboard</h1>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="row">
    <div class="col-md-4 mb-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div>
                <div class="fs-4 fw-bold"><?php echo number_format($totalUsers); ?></div>
                <div class="small text-muted">Total Users</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
            <div>
                <div class="fs-4 fw-bold"><?php echo number_format($totalTrainers); ?></div>
                <div class="small text-muted">Trainers</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
            <div>
                <div class="fs-4 fw-bold"><?php echo number_format($totalClients); ?></div>
                <div class="small text-muted">Clients</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-dumbbell"></i></div>
            <div>
                <div class="fs-4 fw-bold"><?php echo number_format($totalClasses); ?></div>
                <div class="small text-muted">Active Classes</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div>
                <div class="fs-4 fw-bold"><?php echo number_format($totalSessionsThisMonth); ?></div>
                <div class="small text-muted">Sessions This Month</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <div class="fs-4 fw-bold"><?php echo format_currency($monthlyRevenue); ?></div>
                <div class="small text-muted">Revenue This Month</div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h2 class="h2">Quick Actions</h2>
</div>
<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card quick-action-card h-100 position-relative">
            <div class="card-body">
                <i class="fas fa-users-cog fs-1 mb-2"></i>
                <h5 class="card-title">Manage Users</h5>
                <a href="users.php" class="stretched-link"></a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card quick-action-card h-100 position-relative">
            <div class="card-body">
                <i class="fas fa-dumbbell fs-1 mb-2"></i>
                <h5 class="card-title">Manage Classes</h5>
                <a href="classes.php" class="stretched-link"></a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card quick-action-card h-100 position-relative">
            <div class="card-body">
                <i class="fas fa-calendar-plus fs-1 mb-2"></i>
                <h5 class="card-title">Schedule Sessions</h5>
                <a href="sessions.php" class="stretched-link"></a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card quick-action-card h-100 position-relative">
            <div class="card-body">
                <i class="fas fa-chart-line fs-1 mb-2"></i>
                <h5 class="card-title">View Reports</h5>
                <a href="reports.php" class="stretched-link"></a>
            </div>
        </div>
    </div>
</div>


<?php include 'includes/admin_footer.php'; ?>
