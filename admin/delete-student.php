<?php
// Start the session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../config/config.php';
require_once '../includes/auth_validate.php';
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if this is an AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Direct access not allowed']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in']);
    exit();
}

// Check if student_id is provided
if (!isset($_POST['student_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit();
}

// Validate student ID
$student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
if (!$student_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Student ID']);
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // First, check if the student exists
    $check = $db->prepare("SELECT student_id FROM students WHERE student_id = ?");
    $check->execute([$student_id]);
    
    if ($check->rowCount() === 0) {
        throw new Exception('Student not found');
    }
    
    // Delete related records (order matters due to foreign key constraints)
    $tables = ['attendance_records', 'daily_attendance_summary', 'course_enrollments', 'rfid_cards'];
    
    foreach ($tables as $table) {
        $stmt = $db->prepare("DELETE FROM {$table} WHERE student_id = ?");
        $stmt->execute([$student_id]);
    }
    
    // Finally delete the student
    $stmt = $db->prepare("DELETE FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    
    // Log the deletion
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, user_agent) 
                        VALUES (?, 'DELETE', 'students', ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'], 
        $student_id, 
        $_SERVER['REMOTE_ADDR'] ?? 'unknown', 
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Commit transaction
    $db->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Student deleted successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting student: ' . $e->getMessage()
    ]);
}
