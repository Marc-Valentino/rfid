<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

$system = new AttendanceSystem();

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!$system->isLoggedIn() || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($course_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
    exit();
}

try {
    // Get course details with instructor information
    $query = "SELECT c.*, ic.instructor_id 
              FROM courses c 
              LEFT JOIN instructor_courses ic ON c.course_id = ic.course_id AND ic.is_primary = 1
              WHERE c.course_id = ? AND c.is_active = 1";
    $result = $system->executeQuery($query, [$course_id], true);
    
    if ($result && count($result) > 0) {
        echo json_encode([
            'success' => true,
            'data' => $result[0]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Course not found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}