<?php
session_start();
require_once '../config/config.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Otherwise redirect to login page
header('Location: ../login.php');
exit();
?>