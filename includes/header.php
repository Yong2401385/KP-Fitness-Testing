<?php
// This file assumes `config.php` is included before it.

// Get unread notifications count if a user is logged in
$unreadCount = 0;
if (is_logged_in()) {
    $unreadCount = get_unread_notifications_count($_SESSION['UserID']);
}

// Determine the current page to set the 'active' class on nav links
$currentPage = basename($_SERVER['PHP_SELF']);
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <!-- Custom CSS -->
    <link href="assets/css/custom.css" rel="stylesheet">
    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo SITE_URL; ?>/index.php">
            <i class="fas fa-dumbbell"></i> KP FITNESS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'about.php') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/about.php">About</a>
                </li>
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/dashboard.php">Dashboard</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage == 'login.php') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/login.php">Classes</a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center">
                <?php if (is_logged_in()): ?>
                    <a href="#" class="btn btn-outline-secondary me-2 position-relative" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $unreadCount; ?>
                                <span class="visually-hidden">unread messages</span>
                            </span>
                        <?php endif; ?>
                    </a>
                    
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-sm-inline mx-1"><?php echo htmlspecialchars(explode(' ', $_SESSION['FullName'])[0]); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/dashboard.php">Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout.php">Sign out</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-outline-light me-2">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-warning">
                        <i class="fas fa-user-plus"></i> Sign Up
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main class="container mt-4">
    <!-- Page content will be injected here -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
