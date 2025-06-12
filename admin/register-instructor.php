<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

$attendance = new AttendanceSystem();
$attendance->requireLogin();

// Check if user has admin privileges
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Pagination settings
$limit = 10; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $limit;

// Search and filter
$search = isset($_GET['search']) ? $attendance->sanitizeInput($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? (int)$_GET['department'] : '';

// Build the query conditions
$conditions = ["u.role = 'teacher'"];  // Changed from "role = 'teacher'" to "u.role = 'teacher'"
$params = [];

if (!empty($search)) {
    $conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ? OR id.employee_id LIKE ?)";  // Added table aliases and employee_id search
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($department_filter)) {
    $conditions[] = "COALESCE(id.department_id, u.department_id) = ?";  // Updated to match the query in getTeachersPaginated
    $params[] = $department_filter;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
// Replace this line
$count_query = "SELECT COUNT(*) as total FROM users $where_clause";
// With this updated count query that includes the same table structure as getTeachersPaginated
$count_query = "SELECT COUNT(DISTINCT u.user_id) as total FROM users u 
               LEFT JOIN instructor_details id ON u.user_id = id.user_id
               LEFT JOIN departments d ON COALESCE(id.department_id, u.department_id) = d.department_id 
               LEFT JOIN instructor_courses ic ON u.user_id = ic.instructor_id
               LEFT JOIN courses c ON ic.course_id = c.course_id
               $where_clause";
               
$total_records = $attendance->executeCountQuery($count_query, $params);
$total_pages = ceil($total_records / $limit);


// Current code (lines 47-49)
$result = $attendance->getTeachersPaginated($conditions, $params, $limit, $offset);
$total_records = $result['total_records'];
$teachers = $result['teachers']; // This line is correct, but let's make sure it's working


$total_pages = ceil($total_records / $limit);

$message = '';
$error = '';

$departments = $attendance->getAllDepartments();
$all_courses = $attendance->executeQuery(
    "SELECT DISTINCT c.course_id, c.course_code, c.course_name 
     FROM courses c 
     WHERE c.is_active = 1 
     ORDER BY c.course_name", 
    [], 
    true
);

// The commented out line below was the problem - good that you removed it
// $teachers = $attendance->getUsersByRole('teacher');

// Handle course assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_courses'])) {
    $instructor_id = isset($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : 0;
    
    if ($instructor_id <= 0) {
        $error = 'Invalid instructor ID';
    } else {
        $assigned_courses = $_POST['assigned_courses'] ?? [];
        
        // First delete all existing course assignments for this instructor
        $sql = "DELETE FROM instructor_courses WHERE instructor_id = ?";
        $attendance->executeQuery($sql, [$instructor_id]);
        
        // Then insert new assignments
        if (!empty($assigned_courses)) {
            $values = array_fill(0, count($assigned_courses), '(?, ?)');
            $sql = "INSERT INTO instructor_courses (instructor_id, course_id) VALUES " . implode(',', $values);
            $params = [];
            foreach ($assigned_courses as $course_id) {
                $params[] = $instructor_id;
                $params[] = (int)$course_id;
            }
            if ($attendance->executeQuery($sql, $params)) {
                $message = 'Courses assigned successfully!';
            } else {
                $error = 'Failed to assign courses. Please try again.';
            }
        } else {
            $message = 'All courses have been unassigned from the instructor.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Initialize variables with default empty values
    $username = $attendance->sanitizeInput($_POST['username'] ?? '');
    $email = $attendance->sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = $attendance->sanitizeInput($_POST['full_name'] ?? '');
    $department_id = (int)($_POST['department_id'] ?? 0);
    $employee_id = $attendance->sanitizeInput($_POST['employee_id'] ?? '');
    $phone = $attendance->sanitizeInput($_POST['phone'] ?? '');
    $office_location = $attendance->sanitizeInput($_POST['office_location'] ?? '');
    $assigned_courses = $_POST['assigned_courses'] ?? [];
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'All required fields must be filled.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check if username or email already exists
        if ($attendance->userExists($username, $email)) {
            $error = 'Username or email already exists.';
        } else {
            // Create instructor account
            $instructor_id = $attendance->createInstructor($username, $email, $password, $full_name, $department_id, $employee_id, $phone, $office_location);
            
            if ($instructor_id) {
                // Assign courses to instructor
                if (!empty($assigned_courses)) {
                    $course_assignment_success = $attendance->assignCoursesToInstructor($instructor_id, $assigned_courses);
                    if ($course_assignment_success) {
                        $message = 'Instructor account created successfully and courses assigned!';
                    } else {
                        $message = 'Instructor account created successfully, but there was an issue assigning some courses.';
                    }
                } else {
                    $message = 'Instructor account created successfully!';
                }
                // Clear form data
                $_POST = array();
            } else {
                $error = 'Failed to create instructor account. Please try again.';
            }
        }
    }
}
// Replace the existing edit form handler with this updated version
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_instructor'])) {
    $instructor_id = isset($_POST['edit_instructor_id']) ? (int)$_POST['edit_instructor_id'] : 0;
    $full_name = $attendance->sanitizeInput($_POST['edit_full_name'] ?? '');
    $email = $attendance->sanitizeInput($_POST['edit_email'] ?? '');
    $department_id = (int)($_POST['edit_department_id'] ?? 0);
    $employee_id = $attendance->sanitizeInput($_POST['edit_employee_id'] ?? '');
    $phone = $attendance->sanitizeInput($_POST['edit_phone'] ?? '');
    $office_location = $attendance->sanitizeInput($_POST['edit_office_location'] ?? '');
    
    $response = array();
    
    if ($instructor_id <= 0) {
        $response['error'] = 'Invalid instructor ID';
    } elseif (empty($full_name) || empty($email)) {
        $response['error'] = 'Name and email are required fields.';
    } else {
        // Check if email is already taken by another user
        $sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ? AND role = 'teacher'";
        $existingUser = $attendance->executeQuery($sql, [$email, $instructor_id], true);
        
        if ($existingUser) {
            $response['error'] = 'Email address is already in use by another user.';
        } else {
            if ($attendance->updateInstructor($instructor_id, $full_name, $email, $department_id, $employee_id, $phone, $office_location)) {
                $response['success'] = 'Instructor updated successfully!';
            } else {
                $response['error'] = 'Failed to update instructor. Please try again.';
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle delete instructor form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_instructor'])) {
    $instructor_id = isset($_POST['delete_instructor']) ? (int)$_POST['delete_instructor'] : 0;
    
    if ($instructor_id <= 0) {
        $error = 'Invalid instructor ID';
    } else {
        if ($attendance->deleteInstructor($instructor_id)) {
            $message = 'Instructor deleted successfully!';
        } else {
            $error = 'Failed to delete instructor. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Instructor - <?php echo APP_NAME; ?></title>
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
        .card-header {
            background: rgba(0, 123, 255, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #007bff;
            color: #ffffff;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }
        .form-control::placeholder {
            color: #aaa;
        }
        .form-label {
            color: #ffffff;
            font-weight: 500;
        }
        .btn-primary {
            background: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background: #0056b3;
            border-color: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
            border-color: #6c757d;
        }
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border-color: rgba(40, 167, 69, 0.3);
            color: #d4edda;
        }
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            border-color: rgba(220, 53, 69, 0.3);
            color: #f8d7da;
        }
        .text-muted {
            color: #aaa !important;
        }
        .form-text {
            color: #aaa;
        }
        .course-selection {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
        }
        .course-item {
            padding: 5px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .course-item:last-child {
            border-bottom: none;
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
                    <a class="nav-link" href="courses.php">
                        <i class="fas fa-book me-2"></i>Subjects    
                    </a>
                    <a class="nav-link" href="departments.php">
                        <i class="fas fa-building me-2"></i>Departments
                    </a>
                    <a class="nav-link" href="events.php">
                        <i class="fas fa-calendar me-2"></i>Events
                    </a>
                    <a class="nav-link active" href="register-instructor.php">
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
                    
                    <hr class="text-secondary">
                    
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
                        <a class="nav-link text-light" href="activity-logs.php">
                            <i class="fas fa-history me-2"></i>Activity Logs
                        </a>
                    </div>
                    
                    <hr class="text-secondary">
                    
                    <a class="nav-link text-danger" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-chalkboard-teacher me-2"></i>Instructors Management</h2>
                    <!-- <div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInstructorModal">
                            <i class="fas fa-plus me-1"></i>Add Instructor
                        </button>
                    </div> -->
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" 
                                       placeholder="Employee ID, name, or email">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['department_id']; ?>" 
                                                <?php echo ($department_filter ?? '') == $dept['department_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Teachers Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>Teachers List
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($teachers)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No teachers found</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th>Employee ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Department</th>
                                            <th>Office Location</th>
                                            <th>Phone</th>
                                            <th>Assigned Courses</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <tr data-teacher-id="<?php echo $teacher['user_id']; ?>">
                                                <td><?php echo htmlspecialchars($teacher['employee_id'] ?? ''); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($teacher['full_name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['department_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['office_location'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['phone'] ?? ''); ?></td>
                                                <td>
                                                    <?php if (!empty($teacher['assigned_courses'])): ?>
                                                        <?php $courses = explode(', ', $teacher['assigned_courses']); ?>
                                                        <?php foreach ($courses as $course): ?>
                                                            <span class="badge bg-primary me-1"><?php echo htmlspecialchars($course); ?></span>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">No Courses</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#assignCoursesModal" 
                                                                data-teacher-id="<?php echo $teacher['user_id']; ?>">
                                                            <i class="fas fa-book-reader"></i> Assign Courses
                                                        </button>
                                                        <button type="button" class="btn btn-warning" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editInstructorModal" 
                                                                data-teacher-id="<?php echo $teacher['user_id']; ?>"
                                                                data-teacher-name="<?php echo htmlspecialchars($teacher['full_name']); ?>"
                                                                data-teacher-email="<?php echo htmlspecialchars($teacher['email']); ?>"
                                                                data-teacher-dept="<?php echo $teacher['department_id'] ?? ''; ?>"
                                                                data-teacher-empid="<?php echo htmlspecialchars($teacher['employee_id'] ?? ''); ?>"
                                                                data-teacher-phone="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>"
                                                                data-teacher-office="<?php echo htmlspecialchars($teacher['office_location'] ?? ''); ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <button type="button" class="btn btn-danger" 
                                                                onclick="deleteInstructor(<?php echo $teacher['user_id']; ?>, '<?php echo htmlspecialchars($teacher['full_name']); ?>')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Form validation
        document.getElementById('instructorForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Select/Deselect all courses functionality
        function toggleAllCourses() {
            const checkboxes = document.querySelectorAll('input[name="assigned_courses[]"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
        }
        
        // Add select all button
        document.addEventListener('DOMContentLoaded', function() {
            const courseSelection = document.querySelector('.course-selection');
            if (courseSelection && courseSelection.length > 1) {
                const selectAllBtn = document.createElement('button');
                selectAllBtn.type = 'button';
                selectAllBtn.className = 'btn btn-sm btn-outline-primary mb-2';
                selectAllBtn.innerHTML = '<i class="fas fa-check-square me-1"></i>Toggle All';
                selectAllBtn.onclick = toggleAllCourses;
                courseSelection.insertBefore(selectAllBtn, courseSelection.firstChild);
            }
        });
    </script>
</body>
</html>

<!-- Add Instructor Modal -->
<div class="modal fade" id="addInstructorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title">Add New Instructor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="instructorForm" method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username*</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email*</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password*</label>
                            <input type="password" class="form-control" name="password" id="password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password*</label>
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Full Name*</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employee ID</label>
                            <input type="text" class="form-control" name="employee_id">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>">
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Office Location</label>
                            <input type="text" class="form-control" name="office_location">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Instructor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Instructor Modal -->
<div class="modal fade" id="editInstructorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title">Edit Instructor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editInstructorForm" method="POST">
                <input type="hidden" name="edit_instructor" value="1">
                <div class="modal-body">
                    <input type="hidden" name="edit_instructor_id" id="edit_instructor_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name*</label>
                            <input type="text" class="form-control" name="edit_full_name" id="edit_full_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email*</label>
                            <input type="email" class="form-control" name="edit_email" id="edit_email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employee ID</label>
                            <input type="text" class="form-control" name="edit_employee_id" id="edit_employee_id">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="edit_department_id" id="edit_department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>">
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="edit_phone" id="edit_phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Office Location</label>
                            <input type="text" class="form-control" name="edit_office_location" id="edit_office_location">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Courses Modal -->
<div class="modal fade" id="assignCoursesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title">Assign Courses to Instructor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="instructor_id" id="modalInstructorId">
                    <p>Assigning courses for instructor: <strong id="instructorName"></strong></p>
                    
                    <div class="course-selection">
                        <?php foreach ($all_courses as $course): ?>
                            <div class="course-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="assigned_courses[]" 
                                           value="<?php echo $course['course_id']; ?>" 
                                           id="course_<?php echo $course['course_id']; ?>">
                                    <label class="form-check-label" for="course_<?php echo $course['course_id']; ?>">
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_courses" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Assignments
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit Instructor Modal Handler
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editInstructorModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const teacherId = button.getAttribute('data-teacher-id');
            const teacherName = button.getAttribute('data-teacher-name');
            const teacherEmail = button.getAttribute('data-teacher-email');
            const teacherDept = button.getAttribute('data-teacher-dept');
            const teacherEmpId = button.getAttribute('data-teacher-empid');
            const teacherPhone = button.getAttribute('data-teacher-phone');
            const teacherOffice = button.getAttribute('data-teacher-office');

            // Set values in the form
            this.querySelector('#edit_instructor_id').value = teacherId;
            this.querySelector('#edit_full_name').value = teacherName;
            this.querySelector('#edit_email').value = teacherEmail;
            this.querySelector('#edit_department_id').value = teacherDept || '';
            this.querySelector('#edit_employee_id').value = teacherEmpId || '';
            this.querySelector('#edit_phone').value = teacherPhone || '';
            this.querySelector('#edit_office_location').value = teacherOffice || '';
        });
    }

    // Handle edit form submission
    const editForm = document.getElementById('editInstructorForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading indicator
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                let result;
                try {
                    result = JSON.parse(data);
                } catch (e) {
                    console.error('Error parsing JSON:', data);
                    throw new Error('Invalid server response');
                }

                if (result.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editInstructorModal'));
                    modal.hide();
                    
                    // Show success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        ${result.success}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.card'));
                    
                    // Update the table row immediately
                    const departmentSelect = document.getElementById('edit_department_id');
                    const selectedOption = departmentSelect.options[departmentSelect.selectedIndex];
                    const departmentName = selectedOption ? selectedOption.text : '';
                    
                    updateTableRow(formData.get('edit_instructor_id'), {
                        full_name: formData.get('edit_full_name'),
                        email: formData.get('edit_email'),
                        employee_id: formData.get('edit_employee_id'),
                        department_id: formData.get('edit_department_id'),
                        department_name: departmentName,
                        phone: formData.get('edit_phone'),
                        office_location: formData.get('edit_office_location')
                    });

                    // Remove success message after 3 seconds
                    setTimeout(() => {
                        alertDiv.remove();
                    }, 3000);
                } else if (result.error) {
                    alert(result.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Only show error message if the update actually failed
                if (!document.querySelector('tr[data-teacher-id="' + formData.get('edit_instructor_id') + '"]')) {
                    alert('An error occurred while saving changes. Please try again.');
                }
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});

// Function to refresh the table content
function refreshTable() {
    const tableContainer = document.querySelector('.table-responsive');
    if (tableContainer) {
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTable = doc.querySelector('.table-responsive');
                if (newTable) {
                    tableContainer.innerHTML = newTable.innerHTML;
                }
            })
            .catch(error => console.error('Error refreshing table:', error));
    }
}

// Auto-refresh table every 30 seconds
setInterval(refreshTable, 30000);

// Replace the existing updateTableRow function with this updated version
function updateTableRow(teacherId, data) {
    const tableRow = document.querySelector(`tr[data-teacher-id="${teacherId}"]`);
    if (tableRow) {
        // Update employee ID
        tableRow.cells[0].textContent = data.employee_id || '';
        
        // Update name
        const nameCell = tableRow.cells[1].querySelector('strong');
        if (nameCell) nameCell.textContent = data.full_name;
        
        // Update email
        tableRow.cells[2].textContent = data.email;
        
        // Update department name
        tableRow.cells[3].textContent = data.department_name || '';
        
        // Update office location
        tableRow.cells[4].textContent = data.office_location || '';
        
        // Update phone
        tableRow.cells[5].textContent = data.phone || '';
        
        // Update the edit button data attributes
        const editButton = tableRow.querySelector('button[data-bs-target="#editInstructorModal"]');
        if (editButton) {
            editButton.setAttribute('data-teacher-name', data.full_name);
            editButton.setAttribute('data-teacher-email', data.email);
            editButton.setAttribute('data-teacher-dept', data.department_id);
            editButton.setAttribute('data-teacher-empid', data.employee_id);
            editButton.setAttribute('data-teacher-phone', data.phone);
            editButton.setAttribute('data-teacher-office', data.office_location);
        }
        return true;
    }
    return false;
}

// Update the edit form submission handler
const editForm = document.getElementById('editInstructorForm');
if (editForm) {
    editForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Show loading indicator
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            let result;
            try {
                result = JSON.parse(data);
            } catch (e) {
                console.error('Error parsing JSON:', data);
                throw new Error('Invalid server response');
            }

            if (result.success) {
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('editInstructorModal'));
                modal.hide();
                
                // Show success message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show';
                alertDiv.innerHTML = `
                    ${result.success}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.card'));
                
                // Get department name from select element
                const departmentSelect = document.getElementById('edit_department_id');
                const selectedOption = departmentSelect.options[departmentSelect.selectedIndex];
                const departmentName = selectedOption ? selectedOption.text : '';
                
                // Update the table row immediately
                const updated = updateTableRow(formData.get('edit_instructor_id'), {
                    full_name: formData.get('edit_full_name'),
                    email: formData.get('edit_email'),
                    employee_id: formData.get('edit_employee_id'),
                    department_id: formData.get('edit_department_id'),
                    department_name: departmentName,
                    phone: formData.get('edit_phone'),
                    office_location: formData.get('edit_office_location')
                });

                if (!updated) {
                    console.log('Row update failed, refreshing page...');
                    location.reload();
                    return;
                }

                // Remove success message after 3 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 3000);
            } else if (result.error) {
                alert(result.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Don't show error message if update was successful
            const rowExists = document.querySelector(`tr[data-teacher-id="${formData.get('edit_instructor_id')}"]`);
            if (!rowExists) {
                alert('An error occurred while saving changes. Please try again.');
            }
        })
        .finally(() => {
            // Restore button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
}
</script>

