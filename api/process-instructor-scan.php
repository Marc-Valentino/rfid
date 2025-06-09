<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$attendance = new AttendanceSystem();

// Check if user is logged in and is an instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$rfid = $data['rfid'] ?? '';
$course_id = $data['course_id'] ?? '';

if (empty($rfid) || empty($course_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Process the attendance
$result = $attendance->processAttendance($rfid, $course_id);

echo json_encode($result);