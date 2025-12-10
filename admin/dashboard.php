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

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    font-size: 2.5rem;
    color: var(--primary-color);
}

.stat-info .stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-light);
}

.stat-info .stat-label {
    color: var(--text-dark);
    font-size: 0.9rem;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.action-card {
    background: var(--light-bg);
    border: 2px solid var(--border-color);
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
    color: var(--primary-color);
    text-decoration: none;
    transition: all 0.3s ease;
}

.action-card:hover {
    background: var(--primary-color);
    color: var(--text-light);
    transform: translateY(-5px);
}

.action-card i {
    font-size: 2rem;
    margin-bottom: 1rem;
    display: block;
}

.action-card span {
    font-weight: 600;
    font-size: 1.1rem;
}
</style>

<div class="page-header">
    <h1>Admin Dashboard</h1>
    <p>Overview of the KP Fitness system.</p>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error"><?php echo $error_message; ?></div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <div class="stat-number"><?php echo number_format($totalUsers); ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
        <div class="stat-info">
            <div class="stat-number"><?php echo number_format($totalTrainers); ?></div>
            <div class="stat-label">Trainers</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
        <div class="stat-info">
            <div class="stat-number"><?php echo number_format($totalClients); ?></div>
            <div class="stat-label">Clients</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-dumbbell"></i></div>
        <div class="stat-info">
            <div class="stat-number"><?php echo number_format($totalClasses); ?></div>
            <div class="stat-label">Active Classes</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-info">
            <div class="stat-number"><?php echo number_format($totalSessionsThisMonth); ?></div>
            <div class="stat-label">Sessions This Month</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-info">
            <div class="stat-number"><?php echo format_currency($monthlyRevenue); ?></div>
            <div class="stat-label">Revenue This Month</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="page-header">
    <h2>Quick Actions</h2>
</div>
<div class="quick-actions">
    <a href="users.php" class="action-card">
        <i class="fas fa-users-cog"></i>
        <span>Manage Users</span>
    </a>
    <a href="classes.php" class="action-card">
        <i class="fas fa-dumbbell"></i>
        <span>Manage Classes</span>
    </a>
    <a href="sessions.php" class="action-card">
        <i class="fas fa-calendar-plus"></i>
        <span>Schedule Sessions</span>
    </a>
    <a href="reports.php" class="action-card">
        <i class="fas fa-chart-line"></i>
        <span>View Reports</span>
    </a>
</div>


<?php include 'includes/admin_footer.php'; ?>
