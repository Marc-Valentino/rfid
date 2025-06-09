<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$attendance = new AttendanceSystem();

try {
    $instructor_id = $_SESSION['user_id'];
    
    $sql = "SELECT a.*, 
            s.first_name, s.last_name, s.student_number,
            c.course_name
            FROM attendance a
            JOIN students s ON a.student_id = s.student_id
            JOIN courses c ON s.course_id = c.course_id
            JOIN instructor_courses ic ON c.course_id = ic.course_id
            WHERE ic.instructor_id = ?
            ORDER BY a.scan_time DESC
            LIMIT 10";
    
    $scans = $attendance->executeQuery($sql, [$instructor_id]);
    
    echo json_encode([
        'success' => true,
        'scans' => $scans
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving scans'
    ]);
}