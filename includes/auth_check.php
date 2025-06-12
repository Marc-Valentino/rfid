<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if user role is set
if (!isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit();
}

// Function to check specific role access
function requireRole($required_role) {
    if ($_SESSION['role'] !== $required_role) {
        header('Location: ../login.php');
        exit();
    }
}

// Function to check if user has admin privileges
function requireAdmin() {
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ../login.php');
        exit();
    }
}