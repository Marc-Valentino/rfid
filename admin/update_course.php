<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Initialize system
$system = new AttendanceSystem();

// Check if user is logged in and is admin
if (!$system->isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    $course_name = $_POST['course_name'] ?? '';
    $course_code = $_POST['course_code'] ?? '';
    $department_id = $_POST['department_id'] ?? null;
    $instructor_id = $_POST['instructor_id'] ?? null;
    $credits = $_POST['credits'] ?? 3;
    $semester = $_POST['semester'] ?? '';
    $academic_year = $_POST['academic_year'] ?? '';

    try {
        // Start transaction
        $pdo = $system->getPdo();
        $pdo->beginTransaction();
        
        // Check if course code already exists for other courses
        $check = $system->executeQuery(
            "SELECT course_id FROM courses WHERE course_code = ? AND course_id != ?",
            [$course_code, $course_id],
            true  // Set fetchAll to true
        );

        if ($check && count($check) > 0) {  // Check if array is not empty
            $_SESSION['error'] = "Course code already exists!";
        } else {
            $result = $system->executeQuery(
                "UPDATE courses SET 
                course_name = ?, 
                course_code = ?, 
                department_id = ?, 
                credits = ?, 
                semester = ?, 
                academic_year = ?,
                updated_at = CURRENT_TIMESTAMP 
                WHERE course_id = ?",
                [$course_name, $course_code, $department_id, $credits, $semester, $academic_year, $course_id]
            );

            if ($result) {
                // Update instructor assignment
                // First, remove existing primary instructor
                $system->executeQuery(
                    "UPDATE instructor_courses SET is_primary = 0 WHERE course_id = ?",
                    [$course_id]
                );
                
                // Then, if instructor is selected, add or update the assignment
                if (!empty($instructor_id)) {
                    // Check if relationship already exists
                    $check = $system->executeQuery(
                        "SELECT * FROM instructor_courses WHERE instructor_id = ? AND course_id = ?",
                        [$instructor_id, $course_id],
                        true // Set fetchAll to true to get an array result
                    );
                    
                    if (count($check) > 0) { // Use count() instead of rowCount()
                        // Update existing relationship
                        $system->executeQuery(
                            "UPDATE instructor_courses SET is_primary = 1 WHERE instructor_id = ? AND course_id = ?",
                            [$instructor_id, $course_id]
                        );
                    } else {
                        // Create new relationship
                        $system->executeQuery(
                            "INSERT INTO instructor_courses (instructor_id, course_id, is_primary) VALUES (?, ?, 1)",
                            [$instructor_id, $course_id]
                        );
                    }
                }
                
                $pdo->commit();
                $_SESSION['success'] = "Course updated successfully!";
            } else {
                $pdo->rollBack();
                $_SESSION['error'] = "Error updating course!";
            }
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
}

header('Location: courses.php');
exit();
?>

<!-- In the courses.php table row -->
<td>
    <button class="btn btn-primary btn-sm" onclick="editCourse(<?php echo $course['course_id']; ?>)">
        <i class="fas fa-edit"></i>
    </button>
    <button class="btn btn-danger btn-sm" onclick="deleteCourse(<?php echo $course['course_id']; ?>)">
        <i class="fas fa-trash"></i>
    </button>
</td>