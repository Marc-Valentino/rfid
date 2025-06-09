<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

$system = new AttendanceSystem();

// Check if user is logged in and is admin
if (!$system->isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and get input values
    $course_name = trim($_POST['course_name'] ?? '');
    $course_code = trim($_POST['course_code'] ?? '');
    $department_id = $_POST['department_id'] ? intval($_POST['department_id']) : null;
    $credits = intval($_POST['credits'] ?? 3);
    $semester = trim($_POST['semester'] ?? '');
    $academic_year = trim($_POST['academic_year'] ?? '');

    try {
        // Check if course code already exists
        $check = $system->executeQuery(
            "SELECT course_id FROM courses WHERE course_code = ? AND is_active = 1",
            [$course_code]
        );

        if ($check->rowCount() > 0) {
            $_SESSION['error'] = "Course code already exists!";
        } else {
            // Insert the course with only the columns that exist in the database
            $result = $system->executeQuery(
                "INSERT INTO courses (
                    course_name, 
                    course_code, 
                    department_id, 
                    credits, 
                    semester, 
                    academic_year,
                    is_active
                ) VALUES (?, ?, ?, ?, ?, ?, 1)",
                [
                    $course_name,
                    $course_code,
                    $department_id,
                    $credits,
                    $semester,
                    $academic_year
                ]
            );

            if ($result) {
                $_SESSION['success'] = "Course added successfully!";
            } else {
                $_SESSION['error'] = "Error adding course!";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }

    header('Location: courses.php');
    exit();
}

// If not POST request, redirect back to courses page
header('Location: courses.php');
exit();