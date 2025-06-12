<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'rfid_attendance_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('APP_NAME', 'EVENTRACK');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/rfid');
define('BASE_URL', 'http://localhost/rfid'); // Add this line after APP_URL

// Time settings
define('TIME_ZONE', 'Asia/Manila');
date_default_timezone_set(TIME_ZONE);

// RFID scan settings
define('SCAN_TIMEOUT', 500); // milliseconds
define('MIN_UID_LENGTH', 8);
define('MAX_UID_LENGTH', 12);

// Security Settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// RFID Settings
define('RFID_TIMEOUT', 30); // seconds
define('ATTENDANCE_GRACE_PERIOD', 15); // minutes

// Time Settings
define('WORKING_HOURS_START', '08:00:00');
define('WORKING_HOURS_END', '17:00:00');
define('TIMEZONE', 'Asia/Manila');

// Attendance Time Settings
define('TIME_IN_START', '07:00:00');
define('TIME_IN_END', '10:00:00');
define('TIME_OUT_START', '16:00:00');
define('TIME_OUT_END', '19:00:00');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'database.php';
?>