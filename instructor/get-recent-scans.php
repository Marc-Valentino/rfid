<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

$attendance = new AttendanceSystem();
$attendance->requireLogin();

$course_id = isset($_GET['course_id']) ? $_GET['course_id'] : null;
$instructor_id = $_SESSION['user_id'];

// Get recent attendance records
$records = $attendance->getRecentAttendance($course_id, $instructor_id);

header('Content-Type: application/json');
echo json_encode($records);

public function processStudentAttendance($rfid_uid, $course_id, $attendance_type) {
    try {
        // Get student information
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE rfid_uid = ?");
        $stmt->execute([$rfid_uid]);
        $student = $stmt->fetch();

        if (!$student) {
            return [
                'success' => false,
                'message' => 'Invalid RFID card or student not found'
            ];
        }

        // Check if student is enrolled in the course
        $stmt = $this->pdo->prepare("SELECT * FROM course_enrollments WHERE student_id = ? AND course_id = ?");
        $stmt->execute([$student['student_id'], $course_id]);
        if (!$stmt->fetch()) {
            return [
                'success' => false,
                'message' => 'Student is not enrolled in this course'
            ];
        }

        // Record attendance
        $stmt = $this->pdo->prepare("
            INSERT INTO attendance_records (student_id, course_id, attendance_type, scan_time) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$student['student_id'], $course_id, $attendance_type]);

        return [
            'success' => true,
            'student_name' => $student['first_name'] . ' ' . $student['last_name'],
            'student_number' => $student['student_number'],
            'attendance_type' => $attendance_type
        ];

    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

public function getRecentAttendance($course_id, $instructor_id) {
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
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$course_id, $instructor_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}