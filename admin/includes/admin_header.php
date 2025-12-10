<?php
// This file assumes `config.php` is included before it, typically from the parent file.

// Get unread notifications count
$unreadCount = get_unread_notifications_count($_SESSION['UserID']);
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('PAGE_TITLE') ? PAGE_TITLE . ' - ' . SITE_NAME : SITE_NAME . ' Admin'; ?></title>

    <!-- External Libraries -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <!-- Core Site and Admin Styles -->
    <style>
        :root {
            --primary-color: #ff6b00;
            --primary-hover: #ff8533;
            --dark-bg: #1a1a1a;
            --light-bg: #2d2d2d;
            --sidebar-bg: rgba(26, 26, 26, 0.95);
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
            display: flex;
        }

        /* --- Sidebar --- */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            z-index: 1001;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            text-align: center;
        }
        
        .sidebar-header .logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-color);
            text-decoration: none;
        }

        .sidebar-nav {
            flex-grow: 1;
            padding: 1rem 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: var(--text-dark);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .sidebar-nav a:hover {
            background: rgba(255, 107, 0, 0.05);
            color: var(--primary-color);
        }
        
        .sidebar-nav a.active {
            background: rgba(255, 107, 0, 0.1);
            border-left-color: var(--primary-color);
            color: var(--primary-color);
            font-weight: 600;
        }

        .sidebar-nav a i {
            width: 25px;
            margin-right: 1rem;
            text-align: center;
        }

        /* --- Main Content --- */
        .main-content {
            margin-left: 280px;
            flex-grow: 1;
            padding: 2rem;
            overflow-y: auto;
        }
        
        .page-header {
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }
        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
        }
        .page-header p {
            color: var(--text-dark);
            font-size: 1.1rem;
        }

        /* --- Responsive --- */
        @media (max-width: 992px) {
            body {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            .sidebar-header {
                border-bottom: none;
            }
            .sidebar-nav {
                display: none; /* Simple toggle can be added with JS if needed */
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<nav class="sidebar">
    <div>
        <div class="sidebar-header">
            <a href="<?php echo SITE_URL . '/admin/dashboard.php'; ?>" class="logo">
                <i class="fas fa-tools"></i> Admin
            </a>
        </div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="users.php" class="<?= $current_page == 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i> User Management
            </a>
            <a href="classes.php" class="<?= $current_page == 'classes.php' ? 'active' : '' ?>">
                <i class="fas fa-dumbbell"></i> Class Management
            </a>
            <a href="sessions.php" class="<?= $current_page == 'sessions.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-plus"></i> Session Scheduling
            </a>
            <a href="reports.php" class="<?= $current_page == 'reports.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i> Reports
            </a>
        </div>
    </div>
    <div class="sidebar-nav">
         <a href="../index.php" target="_blank">
            <i class="fas fa-home"></i> View Main Site
        </a>
        <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>

<main class="main-content">
    <!-- Page content will be injected here -->
