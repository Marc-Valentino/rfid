<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

$attendance = new AttendanceSystem();
$attendance->requireLogin();

if ($_SESSION['role'] !== 'teacher') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$course_id = isset($_GET['course_id']) ? $_GET['course_id'] : null;
$instructor_id = $_SESSION['user_id'];

try {
    $sql = "
        SELECT 
            ar.scan_time,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            s.student_number,
            c.course_name,
            ar.attendance_type
        FROM attendance_records ar
        JOIN students s ON ar.student_id = s.student_id
        JOIN courses c ON ar.course_id = c.course_id
        WHERE ar.course_id = ? 
        AND c.instructor_id = ?
        ORDER BY ar.scan_time DESC
        LIMIT 10
    ";
    
    $stmt = $attendance->pdo->prepare($sql);
    $stmt->execute([$course_id, $instructor_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($records);
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error']);
}