<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$attendance = new AttendanceSystem();
$attendance->logout();

header('Location: login.php');
exit();
?>