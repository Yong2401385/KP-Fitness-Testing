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
    
    <!-- External Libraries & Core Styles -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--dark-bg) 0%, var(--light-bg) 100%);
            color: var(--text-light);
            line-height: 1.6;
            display: flex;
        }
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            position: fixed; top: 0; left: 0; height: 100vh;
            display: flex; flex-direction: column; z-index: 1001;
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
        .sidebar-nav { flex-grow: 1; padding: 1rem 0; }
        .sidebar-nav a {
            display: flex; align-items: center; padding: 1rem 1.5rem;
            color: var(--text-dark); text-decoration: none;
            transition: all 0.3s ease; border-left: 4px solid transparent;
        }
        .sidebar-nav a:hover { background: rgba(255, 107, 0, 0.05); color: var(--primary-color); }
        .sidebar-nav a.active {
            background: rgba(255, 107, 0, 0.1);
            border-left-color: var(--primary-color);
            color: var(--primary-color);
            font-weight: 600;
        }
        .sidebar-nav a i { width: 25px; margin-right: 1rem; text-align: center; }
        .main-content { margin-left: 280px; flex-grow: 1; padding: 2rem; overflow-y: auto; }
        .page-header { border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem; margin-bottom: 2rem; }
        .page-header h1 { font-size: 2.5rem; font-weight: 700; }
        .page-header p { color: var(--text-dark); font-size: 1.1rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); color: var(--text-light); }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.8rem; }
        .alert { padding: 1rem; border-radius: 8px; margin: 1.5rem 0; font-weight: 500; border: 1px solid transparent; }
        .alert-error { background: rgba(220, 53, 69, 0.1); border-color: rgba(220, 53, 69, 0.3); color: #ff6b6b; }
    </style>
</head>
<body>

<nav class="sidebar">
    <div>
        <div class="sidebar-header">
            <a href="<?php echo SITE_URL . '/trainer/dashboard.php'; ?>" class="logo">
                <i class="fas fa-user-shield"></i> Trainer
            </a>
        </div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="schedule.php" class="<?= $current_page == 'schedule.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> My Schedule
            </a>
            <a href="attendance.php" class="<?= $current_page == 'attendance.php' ? 'active' : '' ?>">
                <i class="fas fa-clipboard-check"></i> Attendance
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
