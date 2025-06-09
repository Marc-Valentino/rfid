<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

$system = new AttendanceSystem();

// Check if user is logged in and is admin
if (!$system->isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Check for session messages
$success_message = $_SESSION['success'] ?? null;
$error_message = $_SESSION['error'] ?? null;

// Clear session messages after retrieving them
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Handle course addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $course_name = $_POST['course_name'] ?? '';
    $course_code = $_POST['course_code'] ?? '';
    $department_id = $_POST['department_id'] ?? null;
    $instructor_id = $_POST['instructor_id'] ?? null;
    $credits = $_POST['credits'] ?? 3;
    $semester = $_POST['semester'] ?? null;
    $academic_year = $_POST['academic_year'] ?? null;

    try {
        // First, insert the course without instructor_id
        $result = $system->executeQuery(
            "INSERT INTO courses (course_name, course_code, department_id, credits, semester, academic_year) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [$course_name, $course_code, $department_id, $credits, $semester, $academic_year]
        );
        
        if ($result) {
            // Get the newly inserted course ID using the database connection's lastInsertId method
            // Change this line:
            
            
            // To this:
            $course_id = $system->getLastInsertId();
            
            // If instructor is selected, create the relationship in instructor_courses table
            if (!empty($instructor_id)) {
                $instructor_result = $system->executeQuery(
                    "INSERT INTO instructor_courses (instructor_id, course_id, is_primary) 
                     VALUES (?, ?, 1)",
                    [$instructor_id, $course_id]
                );
                
                if (!$instructor_result) {
                    // Log the error but don't show to user since course was created
                    error_log("Error assigning instructor to course");
                }
            }
            
            $success_message = "Course added successfully!";
        } else {
            $error_message = "Error adding course";
        }
    } catch (PDOException $e) {
        $error_message = "Error adding course: " . $e->getMessage();
    }
}

// Add update course handling code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_course'])) {
    $course_id = intval($_POST['course_id'] ?? 0);
    $course_name = trim($_POST['course_name'] ?? '');
    $course_code = trim($_POST['course_code'] ?? '');
    $department_id = intval($_POST['department_id'] ?? 0);
    $credits = intval($_POST['credits'] ?? 3);
    $semester = trim($_POST['semester'] ?? '');
    $academic_year = trim($_POST['academic_year'] ?? '');

    if ($course_id > 0 && !empty($course_name) && !empty($course_code)) {
        try {
            $result = $system->executeQuery(
                "UPDATE courses 
                SET course_name = ?, 
                    course_code = ?, 
                    department_id = ?, 
                    credits = ?, 
                    semester = ?, 
                    academic_year = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE course_id = ? AND is_active = 1",
                [
                    $course_name, 
                    $course_code, 
                    $department_id, 
                    $credits, 
                    $semester, 
                    $academic_year, 
                    $course_id
                ]
            );
            
            if ($result) {
                $success_message = "Course updated successfully!";
            } else {
                $error_message = "No changes were made to the course";
            }
        } catch (PDOException $e) {
            error_log("Error updating course: " . $e->getMessage());
            $error_message = "Error updating course. Please try again.";
        }
    } else {
        $error_message = "Invalid course data provided";
    }
}

// Fetch all departments and instructors
$departments = $system->getAllDepartments();
$instructors = $system->getUsersByRole('teacher');

// Fetch all courses with department and instructor details
try {
    $query = "SELECT c.*, d.department_name, 
              GROUP_CONCAT(DISTINCT u.full_name) as instructor_names
              FROM courses c 
              LEFT JOIN departments d ON c.department_id = d.department_id
              LEFT JOIN instructor_courses ic ON c.course_id = ic.course_id
              LEFT JOIN users u ON ic.instructor_id = u.user_id AND u.role = 'teacher'
              WHERE c.is_active = 1 
              GROUP BY c.course_id";

    $courses = $system->executeQuery($query, [], true);

    if ($courses === false) {
        $error_message = "Error executing query";
        $courses = [];
    }
} catch (PDOException $e) {
    error_log("Error fetching courses: " . $e->getMessage());
    $error_message = "Error fetching courses: " . $e->getMessage();
    $courses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #1a1a1a;
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: linear-gradient(180deg, #2d2d2d 0%, #1a1a1a 100%);
            min-height: 100vh;
            border-right: 1px solid #333;
        }
        .nav-link {
            color: #ccc;
            border-radius: 8px;
            margin: 2px 0;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(0, 123, 255, 0.2);
            color: #007bff;
        }
        .card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
        }
        .table {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
        }
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.05);
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.1);
        }
        .modal-content {
            background: #2d2d2d;
            color: #fff;
        }
        .modal-header {
            border-bottom: 1px solid #333;
        }
        .modal-footer {
            border-top: 1px solid #333;
        }
        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-id-card-alt fa-2x text-primary mb-2"></i>
                    <h5>RFID System</h5>
                    <small class="text-muted">Welcome, <?php echo $_SESSION['full_name']; ?></small>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="students.php">
                        <i class="fas fa-users me-2"></i>Students
                    </a>
                    <a class="nav-link active" href="courses.php">
                        <i class="fas fa-book me-2"></i>Courses
                    </a>
                    <a class="nav-link" href="register-instructor.php">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Instructors
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-user-cog me-2"></i>Users
                    </a>
                    <a class="nav-link" href="attendance.php">
                        <i class="fas fa-clock me-2"></i>Attendance
                    </a>
                    <a class="nav-link" href="rfid-cards.php">
                        <i class="fas fa-id-card me-2"></i>RFID Cards
                    </a>
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar me-2"></i>Reports
                    </a>
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                    
                    <!-- Quick Access Section -->
                    <div class="mt-3">
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-3 mb-2 text-muted">
                            <span>Quick Actions</span>
                        </h6>
                        <a class="nav-link text-light" href="add-student.php">
                            <i class="fas fa-user-plus me-2"></i>Add Student
                        </a>
                        <a class="nav-link text-light" href="scan-rfid.php">
                            <i class="fas fa-wifi me-2"></i>Scan RFID
                        </a>
                    </div>
                    
                    <hr class="text-secondary">
                    
                    <a class="nav-link text-danger" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </nav>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Courses</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                        <i class="fas fa-plus me-2"></i>Add Course
                    </button>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Courses Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Department</th>
                                        <th>Instructor</th>
                                        <th>Credits</th>
                                        <th>Semester</th>
                                        <th>Academic Year</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($courses)): ?>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                                <td><?php echo htmlspecialchars($course['department_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($course['instructor_names'] ?? 'Not Assigned'); ?></td>
                                                <td><?php echo htmlspecialchars($course['credits']); ?></td>
                                                <td><?php echo htmlspecialchars($course['semester'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($course['academic_year'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary me-1" onclick="editCourse(<?php echo $course['course_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteCourse(<?php echo $course['course_id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No courses found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1" aria-labelledby="addCourseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCourseModalLabel">Add New Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="course_name" class="form-label">Course Name</label>
                            <input type="text" class="form-control" id="course_name" name="course_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="course_code" class="form-label">Course Code</label>
                            <input type="text" class="form-control" id="course_code" name="course_code" required>
                        </div>
                        <div class="mb-3">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-control" id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>">
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="instructor_id" class="form-label">Instructor</label>
                            <select class="form-control" id="instructor_id" name="instructor_id">
                                <option value="">Select Instructor</option>
                                <?php foreach ($instructors as $instructor): ?>
                                    <option value="<?php echo $instructor['user_id']; ?>">
                                        <?php echo htmlspecialchars($instructor['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="credits" class="form-label">Credits</label>
                            <input type="number" class="form-control" id="credits" name="credits" value="3" required>
                        </div>
                        <div class="mb-3">
                            <label for="semester" class="form-label">Semester</label>
                            <select class="form-control" id="semester" name="semester" required>
                                <option value="First">First</option>
                                <option value="Second">Second</option>
                                <option value="Second">Third</option>
                                <option value="Second">Fourth</option>
                                <option value="Summer">Summer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="academic_year" class="form-label">Academic Year</label>
                            <select class="form-control" id="academic_year" name="academic_year" required>
                                <?php
                                $currentYear = date('Y');
                                for ($i = 0; $i < 5; $i++) {
                                    $yearStart = $currentYear + $i;
                                    $yearEnd = $yearStart + 1;
                                    $yearRange = $yearStart . '-' . $yearEnd;
                                    echo "<option value=\"$yearRange\">$yearRange</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="add_course" class="btn btn-primary">Add Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div class="modal fade" id="editCourseModal" tabindex="-1" aria-labelledby="editCourseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCourseModalLabel">Edit Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="update_course.php">
                    <div class="modal-body">
                        <input type="hidden" id="edit_course_id" name="course_id">
                        <div class="mb-3">
                            <label for="edit_course_name" class="form-label">Course Name</label>
                            <input type="text" class="form-control" id="edit_course_name" name="course_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_course_code" class="form-label">Course Code</label>
                            <input type="text" class="form-control" id="edit_course_code" name="course_code" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_department_id" class="form-label">Department</label>
                            <select class="form-control" id="edit_department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>">
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_instructor_id" class="form-label">Instructor</label>
                            <select class="form-control" id="edit_instructor_id" name="instructor_id">
                                <option value="">Select Instructor</option>
                                <?php foreach ($instructors as $instructor): ?>
                                    <option value="<?php echo $instructor['user_id']; ?>">
                                        <?php echo htmlspecialchars($instructor['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_credits" class="form-label">Credits</label>
                            <input type="number" class="form-control" id="edit_credits" name="credits" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_semester" class="form-label">Semester</label>
                            <select class="form-control" id="edit_semester" name="semester" required>
                                <option value="First">First</option>
                                <option value="Second">Second</option>
                                <option value="Summer">Summer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_academic_year" class="form-label">Academic Year</label>
                            <select class="form-control" id="edit_academic_year" name="academic_year" required>
                                <?php
                                $currentYear = date('Y');
                                for ($i = 0; $i < 5; $i++) {
                                    $yearStart = $currentYear + $i;
                                    $yearEnd = $yearStart + 1;
                                    $yearRange = $yearStart . '-' . $yearEnd;
                                    echo "<option value=\"$yearRange\">$yearRange</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Update Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    // Initialize Bootstrap components
    document.addEventListener('DOMContentLoaded', function() {
        // Make sure Bootstrap is properly loaded
        if (typeof bootstrap === 'undefined') {
            console.error('Bootstrap is not loaded!');
        } else {
            console.log('Bootstrap is loaded successfully');
        }
    });
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <script>
    function editCourse(courseId) {
        console.log('Edit course clicked for ID:', courseId); // Debug log
        
        // Clear previous values to avoid data leakage between edits
        document.getElementById('edit_course_id').value = '';
        document.getElementById('edit_course_name').value = '';
        document.getElementById('edit_course_code').value = '';
        document.getElementById('edit_department_id').value = '';
        document.getElementById('edit_instructor_id').value = ''; // Clear instructor field
        document.getElementById('edit_credits').value = '';
        document.getElementById('edit_semester').value = '';
        document.getElementById('edit_academic_year').value = '';
        
        // Show the modal first to improve perceived performance
        const editModal = document.getElementById('editCourseModal');
        if (!editModal) {
            console.error('Edit modal element not found');
            return;
        }
        
        const modal = new bootstrap.Modal(editModal);
        modal.show();
        
        // Then fetch the data
        fetch(`get_course.php?course_id=${courseId}`)
            .then(response => {
                console.log('Response status:', response.status); // Debug log
                if (!response.ok) {
                    throw new Error(`Network response was not ok: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Course data received:', data); // Debug log
                
                if (data.success) {
                    const course = data.data;
                    
                    // Fill in the form fields
                    document.getElementById('edit_course_id').value = course.course_id;
                    document.getElementById('edit_course_name').value = course.course_name;
                    document.getElementById('edit_course_code').value = course.course_code;
                    document.getElementById('edit_department_id').value = course.department_id;
                    document.getElementById('edit_instructor_id').value = course.instructor_id || ''; // Set instructor
                    document.getElementById('edit_credits').value = course.credits;
                    document.getElementById('edit_semester').value = course.semester;
                    
                    // Handle academic year dropdown
                    const academicYearSelect = document.getElementById('edit_academic_year');
                    
                    // First try to find and select the matching option
                    let foundMatch = false;
                    for (let i = 0; i < academicYearSelect.options.length; i++) {
                        if (academicYearSelect.options[i].value === course.academic_year) {
                            academicYearSelect.selectedIndex = i;
                            foundMatch = true;
                            break;
                        }
                    }
                    
                    // If no match found and we have a value, add it as a new option
                    if (!foundMatch && course.academic_year) {
                        const newOption = new Option(course.academic_year, course.academic_year);
                        academicYearSelect.add(newOption);
                        newOption.selected = true;
                    }
                } else {
                    console.error('Error in data:', data.message);
                    alert('Error fetching course details: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Error fetching course details. Please try again.');
            });
    }

    function deleteCourse(courseId) {
        if (confirm('Are you sure you want to delete this course?')) {
            // Improved error handling
            fetch('delete-course.php?id=' + courseId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting course. Please try again.');
                });
        }
    }
    </script>
</body>
</html>

                