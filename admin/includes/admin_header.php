<?php
// This file assumes `config.php` is included before it, typically from the parent file.
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('PAGE_TITLE') ? PAGE_TITLE . ' - ' . SITE_NAME : SITE_NAME . ' Admin'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- External Libraries -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../assets/css/custom.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
</head>
<body class="dashboard-layout">

<div class="d-flex flex-column flex-shrink-0 p-3 text-white bg-dark sidebar">
    <a href="<?php echo SITE_URL . '/admin/dashboard.php'; ?>" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="fas fa-tools fs-4 me-2"></i>
        <span class="fs-4">Admin</span>
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link text-white <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt me-2"></i> <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="users.php" class="nav-link text-white <?= $current_page == 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users-cog me-2"></i> <span>User Management</span>
            </a>
        </li>
        <li>
            <a href="activities.php" class="nav-link text-white <?= $current_page == 'activities.php' ? 'active' : '' ?>">
                <i class="fas fa-dumbbell me-2"></i> <span>Activity Management</span>
            </a>
        </li>
        <li>
            <a href="sessions.php" class="nav-link text-white <?= $current_page == 'sessions.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-plus me-2"></i> <span>Session Scheduling</span>
            </a>
        </li>
        <li>
            <a href="reports.php" class="nav-link text-white <?= $current_page == 'reports.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-line me-2"></i> <span>Reports</span>
            </a>
        </li>
        <li>
            <a href="realtime_activity.php" class="nav-link text-white <?= $current_page == 'realtime_activity.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-area me-2"></i> <span>Real-Time Activity</span>
            </a>
        </li>
        <li>
            <a href="#" class="nav-link text-white" id="sidebarNotifBtn">
                <i class="fas fa-bell me-2"></i> 
                <span>Notifications</span>
                <span class="badge bg-danger ms-2 d-none" id="sidebarNotifBadge">0</span>
            </a>
        </li>
    </ul>
    <hr>
    <div class="sidebar-footer">
        <ul class="nav nav-pills flex-column">
            <li class="nav-item">
                <a href="../index.php" class="nav-link text-white" target="_blank">
                    <i class="fas fa-home me-2"></i> <span>View Main Site</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../logout.php" class="nav-link text-white">
                    <i class="fas fa-sign-out-alt me-2"></i> <span>Sign out</span>
                </a>
            </li>
        </ul>
        <hr>
        <div class="text-center">
            <button class="btn btn-outline-secondary" id="sidebarToggle">
                <i class="fas fa-arrows-alt-h"></i>
            </button>
        </div>
    </div>
</div>

<main class="flex-grow-1 p-3 main-content">
    <!-- Page content will be injected here -->
