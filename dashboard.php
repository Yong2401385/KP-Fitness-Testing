<?php
require_once 'includes/config.php';

// First, check if the user is logged in. If not, redirect to login page.
if (!is_logged_in()) {
    redirect('login.php');
}

// Get the user's role from the session
$role = get_user_role();

// Redirect based on the user's role
switch ($role) {
    case 'admin':
        redirect('admin/dashboard.php');
        break;
    case 'trainer':
        redirect('trainer/dashboard.php');
        break;
    case 'client':
        redirect('client/dashboard.php');
        break;
    default:
        // If for some reason the role is not set or invalid, log them out.
        redirect('logout.php');
        break;
}

// No HTML is needed in this file as its only purpose is to redirect.
?>
