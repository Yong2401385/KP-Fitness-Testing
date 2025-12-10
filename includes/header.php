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
    
    <!-- External Libraries -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <!-- Core Site Styles -->
    <style>
        :root {
            --primary-color: #ff6b00;
            --primary-hover: #ff8533;
            --dark-bg: #1a1a1a;
            --light-bg: #2d2d2d;
            --text-light: #ffffff;
            --text-dark: #cccccc;
            --border-color: rgba(255, 107, 0, 0.2);
            --success-color: #51cf66;
            --error-color: #ff6b6b;
            --info-color: #17a2b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--dark-bg) 0%, var(--light-bg) 100%);
            color: var(--text-light);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* --- Global Navigation --- */
        .navbar {
            position: sticky;
            top: 0;
            width: 100%;
            background: rgba(26, 26, 26, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .logo i {
            margin-right: 0.5rem;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: var(--text-light);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            position: relative;
        }

        .nav-links a:hover, .nav-links a.active {
            color: var(--primary-color);
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .nav-links a:hover::after, .nav-links a.active::after {
            width: 100%;
        }
        
        .auth-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .user-menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(45, 45, 45, 0.8);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            text-decoration: none;
            color: var(--text-light);
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .notification-btn {
            position: relative;
            background: rgba(45, 45, 45, 0.8);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem;
            color: #ffffff;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--primary-color);
            color: var(--text-light);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-light);
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* --- Utility Classes --- */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: var(--text-light);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 0, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: var(--text-light);
            border: 2px solid var(--primary-color);
        }
        
        .btn-secondary:hover {
            background: var(--primary-color);
            color: var(--text-light);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            font-weight: 500;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            border-color: rgba(220, 53, 69, 0.3);
            color: var(--error-color);
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border-color: rgba(40, 167, 69, 0.3);
            color: var(--success-color);
        }
        
        .alert-info {
            background: rgba(23, 162, 184, 0.1);
            border-color: rgba(23, 162, 184, 0.3);
            color: var(--info-color);
        }

        @media (max-width: 992px) {
            .nav-links {
                display: none; /* Hide on medium screens and below */
            }
            .mobile-menu-toggle {
                display: block; /* Show hamburger icon */
            }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <a href="<?php echo SITE_URL; ?>/index.php" class="logo">
            <i class="fas fa-dumbbell"></i> KP FITNESS
        </a>
        
        <ul class="nav-links" id="navLinks">
            <li><a href="<?php echo SITE_URL; ?>/index.php" class="<?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>">Home</a></li>
            <li><a href="<?php echo SITE_URL; ?>/about.php" class="<?php echo ($currentPage == 'about.php') ? 'active' : ''; ?>">About</a></li>
            
            <?php if (is_logged_in()): ?>
                <li><a href="<?php echo SITE_URL; ?>/dashboard.php" class="<?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a></li>
            <?php else: ?>
                 <li><a href="<?php echo SITE_URL; ?>/login.php" class="<?php echo ($currentPage == 'login.php') ? 'active' : ''; ?>">Classes</a></li>
            <?php endif; ?>
        </ul>
        
        <div class="auth-buttons">
            <?php if (is_logged_in()): ?>
                <a href="#" class="notification-btn" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="notification-badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="<?php echo SITE_URL; ?>/dashboard.php" class="user-menu-item">
                    <div class="user-avatar"><?php echo substr($_SESSION['FullName'], 0, 1); ?></div>
                    <span><?php echo htmlspecialchars(explode(' ', $_SESSION['FullName'])[0]); ?></span>
                </a>
                
                <a href="<?php echo SITE_URL; ?>/logout.php" class="btn btn-secondary" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-secondary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
                <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Sign Up
                </a>
            <?php endif; ?>
        </div>

        <button class="mobile-menu-toggle" id="mobileMenuToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</nav>

<main class="container">
    <!-- Page content will be injected here -->
