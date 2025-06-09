<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$attendance = new AttendanceSystem();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['rfid_uid']) && isset($input['instructor_id'])) {
    $rfid_uid = $attendance->sanitizeInput($input['rfid_uid']);
    $instructor_id = (int)$input['instructor_id'];
    $attendance_type = $input['attendance_type'] ?? 'Auto';

    try {
        // Check if student exists and is enrolled in instructor's course
        $sql = "SELECT s.*, c.course_name 
                FROM students s 
                LEFT JOIN courses c ON s.course_id = c.course_id 
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
                    scan_time, 
                    attendance_type, 
                    verification_status,
                    recorded_by
                ) VALUES (?, ?, NOW(), ?, 'Verified', ?)";
        
        $attendance->executeQuery($sql, [
            $student['student_id'],
            $rfid_uid,
            $attendance_type,
            $instructor_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Attendance recorded successfully',
            'data' => [
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'student_number' => $student['student_number'],
                'course_name' => $student['course_name']
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error recording attendance'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input'
    ]);
}