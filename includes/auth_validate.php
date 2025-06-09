<?php
// Only start session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    // Store the requested URL for redirection after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: ../login.php');
    exit();
}

// Check if user session hasn't expired (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    // Clear session data
    session_unset();
    session_destroy();
    header('Location: ../login.php?msg=expired');
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Function to check user role/permissions
function check_access($required_role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] != $required_role) {
        header('Location: ../error.php?code=403');
        exit();
    }
}