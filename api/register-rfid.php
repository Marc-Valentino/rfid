<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session for logging
session_start();

// Create an instance of AttendanceSystem
$attendanceSystem = new AttendanceSystem();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!$input || !isset($input['rfid_uid']) || !isset($input['first_name']) || 
    !isset($input['last_name']) || !isset($input['student_number'])) {
    echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
    exit;
}

// Sanitize inputs
$rfid_uid = $attendanceSystem->sanitizeInput($input['rfid_uid']);
$first_name = $attendanceSystem->sanitizeInput($input['first_name']);
$last_name = $attendanceSystem->sanitizeInput($input['last_name']);
$student_number = $attendanceSystem->sanitizeInput($input['student_number']);
$email = isset($input['email']) ? $attendanceSystem->sanitizeInput($input['email']) : null;
$phone = isset($input['phone']) ? $attendanceSystem->sanitizeInput($input['phone']) : null;
$department_id = isset($input['department_id']) ? (int)$input['department_id'] : null;
$course_id = isset($input['course_id']) ? (int)$input['course_id'] : null;
$year_level = isset($input['year_level']) ? $attendanceSystem->sanitizeInput($input['year_level']) : null;

try {
    // Create database connection
    $database = new Database();
    $pdo = $database->getConnection();
    $pdo->beginTransaction();
    
    // Check if student number already exists
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_number = ?");
    $stmt->execute([$student_number]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Student number already exists']);
        exit;
    }
    
    // Check if RFID card is already registered
    $stmt = $pdo->prepare("SELECT card_id FROM rfid_cards WHERE rfid_uid = ? AND student_id IS NOT NULL");
    $stmt->execute([$rfid_uid]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'RFID card is already assigned to another student']);
        exit;
    }
    
    // Insert new student
    $stmt = $pdo->prepare("INSERT INTO students 
        (student_number, first_name, last_name, email, phone, department_id, course_id, year_level, enrollment_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
    $stmt->execute([$student_number, $first_name, $last_name, $email, $phone, $department_id, $course_id, $year_level]);
    $student_id = $pdo->lastInsertId();
    
    // Check if RFID card exists but is unassigned
    $stmt = $pdo->prepare("SELECT card_id FROM rfid_cards WHERE rfid_uid = ?");
    $stmt->execute([$rfid_uid]);
    $card = $stmt->fetch();
    
    if ($card) {
        // Update existing card
        $stmt = $pdo->prepare("UPDATE rfid_cards SET 
            student_id = ?, card_status = 'Active', issue_date = CURRENT_DATE, 
            last_updated = NOW() WHERE rfid_uid = ?");
        $stmt->execute([$student_id, $rfid_uid]);
    } else {
        // Insert new card
        $stmt = $pdo->prepare("INSERT INTO rfid_cards 
            (rfid_uid, student_id, card_status, issue_date, last_updated) 
            VALUES (?, ?, 'Active', CURRENT_DATE, NOW())");
        $stmt->execute([$rfid_uid, $student_id]);
    }
    
    // Log activity
    $attendanceSystem->logActivity(null, 'REGISTER_STUDENT', 'students', $student_id, 
        null, ['student_number' => $student_number, 'rfid_uid' => $rfid_uid]);
    
    $pdo->commit();
    
    // Record attendance for the newly registered student
    $attendanceSystem->recordAttendance($student_id, $rfid_uid, 'Time In');
    
    echo json_encode([
        'success' => true,
        'message' => 'Student registered successfully and attendance recorded',
        'data' => [
            'student_id' => $student_id,
            'student_number' => $student_number,
            'student_name' => $first_name . ' ' . $last_name,
            'rfid_uid' => $rfid_uid
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('RFID Registration Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'System error occurred. Please try again.',
        'error' => $e->getMessage() // Remove in production
    ]);
}
?>