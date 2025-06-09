<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Initialize AttendanceSystem
$attendance = new AttendanceSystem();

// Check if user is logged in
$attendance->requireLogin();

try {
    // Establish database connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );

    // Start transaction
    $pdo->beginTransaction();

    // Get today's date
    $today = date('Y-m-d');

    // First, get the records that will be deleted for logging
    $stmt = $pdo->prepare("SELECT * FROM attendance_records WHERE DATE(attendance_date) = ?");
    $stmt->execute([$today]);
    $deletedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Delete from attendance_records for today
    $stmt = $pdo->prepare("DELETE FROM attendance_records WHERE DATE(attendance_date) = ?");
    $deleted1 = $stmt->execute([$today]);

    // Delete from daily_attendance_summary for today
    $stmt = $pdo->prepare("DELETE FROM daily_attendance_summary WHERE DATE(attendance_date) = ?");
    $deleted2 = $stmt->execute([$today]);

    // Log the action with more detailed information
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs 
        (user_id, action, table_name, old_values, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $userId = $_SESSION['user_id'] ?? null;
    $action = 'DELETE';
    $tableName = 'attendance_records,daily_attendance_summary';
    $oldValues = json_encode([
        'date' => $today,
        'records_deleted' => count($deletedRecords),
        'deleted_records' => $deletedRecords
    ]);
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];

    $stmt->execute([
        $userId,
        $action,
        $tableName,
        $oldValues,
        $ipAddress,
        $userAgent
    ]);

    // Commit transaction only if both deletes were successful
    if ($deleted1 && $deleted2) {
        $pdo->commit();
        $_SESSION['success_msg'] = "Today's attendance records have been cleared successfully.";
    } else {
        throw new Exception("Failed to delete some records");
    }

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_msg'] = "Error: " . $e->getMessage();
}

// Redirect back to attendance page
header('Location: attendance.php');
exit();
?>