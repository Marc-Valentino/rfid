<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

$attendance = new AttendanceSystem();
$attendance->requireLogin();

// Check if user has admin privileges
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$instructor_id = isset($_GET['instructor_id']) ? (int)$_GET['instructor_id'] : 0;
if ($instructor_id <= 0) {
    http_response_code(400);
    exit('Invalid instructor ID');
}

// Use getInstructorCourses instead of getInstructorAssignedCourses
$courses = $attendance->getInstructorCourses($instructor_id);

// Extract just the course IDs from the result
$course_ids = array_map(function($course) {
    return (int)$course['course_id'];
}, $courses);

header('Content-Type: application/json');
echo json_encode($course_ids);