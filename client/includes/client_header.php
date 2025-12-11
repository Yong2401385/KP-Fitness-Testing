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
    <style>
        body {
            min-height: 100vh;
            display: flex;
        }
        .sidebar {
            width: 260px;
            flex-shrink: 0;
        }
    </style>
</head>
<body>

<div class="d-flex flex-column flex-shrink-0 p-3 text-white bg-dark sidebar">
    <a href="<?php echo SITE_URL . '/client/dashboard.php'; ?>" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="fas fa-user-circle fs-4 me-2"></i>
        <span class="fs-4">Client</span>
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link text-white <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="booking.php" class="nav-link text-white <?= $current_page == 'booking.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-plus me-2"></i> Book Classes
            </a>
        </li>
        <li>
            <a href="membership.php" class="nav-link text-white <?= $current_page == 'membership.php' ? 'active' : '' ?>">
                <i class="fas fa-id-card me-2"></i> Membership
            </a>
        </li>
        <li>
            <a href="workout_planner.php" class="nav-link text-white <?= $current_page == 'workout_planner.php' ? 'active' : '' ?>">
                <i class="fas fa-robot me-2"></i> AI Workout Planner
            </a>
        </li>
    </ul>
    <hr>
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
            <strong class="mx-1"><?php echo htmlspecialchars(explode(' ', $_SESSION['FullName'])[0]); ?></strong>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
            <li><a class="dropdown-item" href="../index.php" target="_blank">View Main Site</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../logout.php">Sign out</a></li>
        </ul>
    </div>
</div>

<main class="flex-grow-1 p-3">
    <!-- Page content will be injected here -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
