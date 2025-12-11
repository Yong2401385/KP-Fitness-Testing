<?php
// This file assumes `config.php` is included from the parent file.
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('PAGE_TITLE') ? PAGE_TITLE . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
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
    <a href="<?php echo SITE_URL . '/trainer/dashboard.php'; ?>" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="fas fa-user-shield fs-4 me-2"></i>
        <span class="fs-4">Trainer</span>
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link text-white <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="schedule.php" class="nav-link text-white <?= $current_page == 'schedule.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt me-2"></i> My Schedule
            </a>
        </li>
        <li>
            <a href="attendance.php" class="nav-link text-white <?= $current_page == 'attendance.php' ? 'active' : '' ?>">
                <i class="fas fa-clipboard-check me-2"></i> Attendance
            </a>
        </li>
        <li>
            <a href="profile.php" class="nav-link text-white <?= $current_page == 'profile.php' ? 'active' : '' ?>">
                <i class="fas fa-user-edit me-2"></i> My Profile
            </a>
        </li>
    </ul>
    <hr>
    <div class="sidebar-footer">
        <ul class="nav nav-pills flex-column">
            <li class="nav-item">
                <a href="../index.php" class="nav-link text-white" target="_blank">
                    <i class="fas fa-home me-2"></i> View Main Site
                </a>
            </li>
            <li class="nav-item">
                <a href="../logout.php" class="nav-link text-white">
                    <i class="fas fa-sign-out-alt me-2"></i> Sign out
                </a>
            </li>
        </ul>
    </div>
</div>

<main class="flex-grow-1 p-3">
    <!-- Page content will be injected here -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
