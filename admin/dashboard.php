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
        <div class="card text-white bg-primary">
            <div class="card-body d-flex align-items-center">
                <i class="fas fa-users fs-3 me-3"></i>
                <div>
                    <div class="fs-4 fw-bold"><?php echo number_format($totalUsers); ?></div>
                    <div class="small">Total Users</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body d-flex align-items-center">
                <i class="fas fa-user-tie fs-3 me-3"></i>
                <div>
                    <div class="fs-4 fw-bold"><?php echo number_format($totalTrainers); ?></div>
                    <div class="small">Trainers</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card text-white bg-secondary">
            <div class="card-body d-flex align-items-center">
                <i class="fas fa-user-friends fs-3 me-3"></i>
                <div>
                    <div class="fs-4 fw-bold"><?php echo number_format($totalClients); ?></div>
                    <div class="small">Clients</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card text-dark bg-light">
            <div class="card-body d-flex align-items-center">
                <i class="fas fa-dumbbell fs-3 me-3"></i>
                <div>
                    <div class="fs-4 fw-bold"><?php echo number_format($totalClasses); ?></div>
                    <div class="small">Active Classes</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card text-white bg-dark">
            <div class="card-body d-flex align-items-center">
                <i class="fas fa-calendar-alt fs-3 me-3"></i>
                <div>
                    <div class="fs-4 fw-bold"><?php echo number_format($totalSessionsThisMonth); ?></div>
                    <div class="small">Sessions This Month</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body d-flex align-items-center">
                <i class="fas fa-money-bill-wave fs-3 me-3"></i>
                <div>
                    <div class="fs-4 fw-bold"><?php echo format_currency($monthlyRevenue); ?></div>
                    <div class="small">Revenue This Month</div>
                </div>
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
        <a href="users.php" class="text-decoration-none">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-users-cog fs-1 text-primary mb-2"></i>
                    <h5 class="card-title">Manage Users</h5>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 mb-3">
        <a href="classes.php" class="text-decoration-none">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-dumbbell fs-1 text-info mb-2"></i>
                    <h5 class="card-title">Manage Classes</h5>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 mb-3">
        <a href="sessions.php" class="text-decoration-none">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-calendar-plus fs-1 text-success mb-2"></i>
                    <h5 class="card-title">Schedule Sessions</h5>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 mb-3">
        <a href="reports.php" class="text-decoration-none">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-chart-line fs-1 text-danger mb-2"></i>
                    <h5 class="card-title">View Reports</h5>
                </div>
            </div>
        </a>
    </div>
</div>


<?php include 'includes/admin_footer.php'; ?>
