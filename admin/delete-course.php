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

$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($course_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
    exit();
}

try {
    // Start transaction
    $pdo = $system->getPdo();
    $pdo->beginTransaction();

    // First check if course exists
    $course = $system->executeQuery(
        "SELECT course_id FROM courses WHERE course_id = ?",
        [$course_id],
        true // Set fetchAll to true to get an array result
    );

    if ($course && count($course) > 0) {
        // Delete related attendance_records
        $system->executeQuery(
            "DELETE FROM attendance_records WHERE course_id = ?",
            [$course_id]
        );

        // Delete related course_enrollments
        $system->executeQuery(
            "DELETE FROM course_enrollments WHERE course_id = ?",
            [$course_id]
        );

        // Delete related instructor_courses
        $system->executeQuery(
            "DELETE FROM instructor_courses WHERE course_id = ?",
            [$course_id]
        );

        // Finally delete the course
        $result = $system->executeQuery(
            "DELETE FROM courses WHERE course_id = ?",
            [$course_id]
        );

        if ($result) {
            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'message' => 'Course permanently deleted successfully'
            ]);
        } else {
            throw new Exception('Failed to delete course');
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Course not found'
        ]);
    }
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}