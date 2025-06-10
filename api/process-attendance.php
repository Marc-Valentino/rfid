<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$attendance = new AttendanceSystem();

// Verify instructor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['rfid_uid'])) {
    try {
        $rfid_uid = $attendance->sanitizeInput($input['rfid_uid']);
        $instructor_id = (int)$input['instructor_id'];
        $location = $input['location'] ?? 'Main Entrance';
        
        // Check if student exists and is enrolled in instructor's course
        $sql = "SELECT s.*, c.course_name, c.course_id, d.department_name 
                FROM students s 
                JOIN courses c ON s.course_id = c.course_id 
                JOIN departments d ON c.department_id = d.department_id
                JOIN instructor_courses ic ON c.course_id = ic.course_id 
                WHERE s.rfid_uid = ? AND ic.instructor_id = ?";
        
        $student = $attendance->executeQuery($sql, [$rfid_uid, $instructor_id], true);

        if (!$student) {
            echo json_encode([
                'success' => false,
                'message' => 'Student not found or not enrolled in your courses'
            ]);
            exit;
        }

        // Record attendance
        $sql = "INSERT INTO attendance (
                    student_id,
                    rfid_uid,
                    course_id,
                    scan_time,
                    attendance_type,
                    location,
                    verification_status,
                    recorded_by
                ) VALUES (?, ?, ?, NOW(), ?, ?, 'Verified', ?)";
        
        $attendance->executeQuery($sql, [
            $student['student_id'],
            $rfid_uid,
            $student['course_id'],
            'Time In',
            $location,
            $instructor_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Attendance recorded successfully',
            'data' => [
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'student_number' => $student['student_number'],
                'course_name' => $student['course_name'],
                'department_name' => $student['department_name']
            ]
        ]);
    } catch (Exception $e) {
        error_log("Error recording attendance: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error recording attendance. Please try again.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input data'
    ]);
}

require_once '../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);
$rfid_uid = $data['rfid_uid'] ?? '';
$course_id = $data['course_id'] ?? null;
$attendance_type = $data['attendance_type'] ?? 'Time In';

if (empty($rfid_uid)) {
    echo json_encode(['success' => false, 'message' => 'RFID UID is required']);
    exit;
}

// Get student information
$stmt = $conn->prepare("
    SELECT s.*, d.department_name, c.course_name 
    FROM students s 
    LEFT JOIN rfid_cards r ON s.student_id = r.student_id
    LEFT JOIN departments d ON s.department_id = d.department_id
    LEFT JOIN courses c ON s.course_id = c.course_id
    WHERE r.rfid_uid = ?
");
$stmt->bind_param("s", $rfid_uid);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit;
}

// Record attendance
$stmt = $conn->prepare("
    INSERT INTO attendance_records 
    (student_id, rfid_uid, attendance_type, attendance_date, course_id, location, verification_status) 
    VALUES (?, ?, ?, CURDATE(), ?, 'Classroom', 'Verified')
");
$stmt->bind_param("issi", $student['student_id'], $rfid_uid, $attendance_type, $course_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Attendance recorded successfully',
        'student_name' => $student['first_name'] . ' ' . $student['last_name'],
        'student_number' => $student['student_number']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error recording attendance']);
}