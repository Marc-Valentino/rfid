<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

$attendance = new AttendanceSystem();
$attendance->requireLogin();

// Check if user is an instructor
if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$instructor_id = $_SESSION['user_id'];

// Get instructor's students
$instructor_students = $attendance->getStudentsForInstructor($instructor_id);
$instructor_courses = $attendance->getInstructorCourses($instructor_id);

// Handle student actions (add, edit, delete)
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_student':
                // Add student and enroll them in the course
                $student_id = $_POST['student_id'];
                $course_id = $_POST['course_id'];
                
                // Check if enrollment already exists
                $check_query = "SELECT COUNT(*) FROM course_enrollments 
                               WHERE student_id = :student_id 
                               AND course_id = :course_id";
                $check_stmt = $attendance->getConnection()->prepare($check_query);
                $check_stmt->bindParam(':student_id', $student_id);
                $check_stmt->bindParam(':course_id', $course_id);
                $check_stmt->execute();
                
                if ($check_stmt->fetchColumn() == 0) {
                    // Insert into course_enrollments only if no existing enrollment
                    $query = "INSERT INTO course_enrollments (student_id, course_id, instructor_id, enrollment_date, enrollment_status)
                              VALUES (:student_id, :course_id, :instructor_id, CURDATE(), 'Active')";
                    $stmt = $attendance->getConnection()->prepare($query);
                    $stmt->bindParam(':student_id', $student_id);
                    $stmt->bindParam(':course_id', $course_id);
                    $stmt->bindParam(':instructor_id', $instructor_id);
                    $stmt->execute();
                }
                break;
            case 'edit_student':
                // Edit student logic here
                break;
            case 'delete_student':
                // Delete student logic here
                break;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - <?php echo APP_NAME; ?></title>
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
        .table-dark {
            background: rgba(255, 255, 255, 0.05);
        }
        .badge {
            border-radius: 20px;
        }
        .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.3);
        }
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
        }
        .modal-content {
            background: #2d2d2d;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #007bff;
            color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #007bff;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-chalkboard-teacher fa-2x text-primary mb-2"></i>
                    <h5>Instructor Panel</h5>
                    <small class="text-muted">Welcome, <?php echo $_SESSION['full_name']; ?></small>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="my-courses.php">
                        <i class="fas fa-book me-2"></i>My Courses
                    </a>
                    <a class="nav-link" href="scan-attendance.php">
                        <i class="fas fa-qrcode me-2"></i>Scan Attendance
                    </a>
                    <a class="nav-link active" href="manage-students.php">
                        <i class="fas fa-users me-2"></i>Manage Students
                    </a>
                    <a class="nav-link" href="attendance-reports.php">
                        <i class="fas fa-chart-bar me-2"></i>Attendance Reports
                    </a>
                    <a class="nav-link" href="class-sessions.php">
                        <i class="fas fa-calendar-alt me-2"></i>Class Sessions
                    </a>
                    
                    <hr class="text-secondary">
                    
                    <!-- Quick Access Section -->
                    <div class="mt-3">
                        <h6 class="text-uppercase text-muted small mb-2">
                            <span>Quick Actions</span>
                        </h6>
                        <a class="nav-link text-light" href="scan-attendance.php">
                            <i class="fas fa-qrcode me-2"></i>Quick Scan
                        </a>
                        <a class="nav-link text-light" href="class-sessions.php">
                            <i class="fas fa-plus me-2"></i>New Session
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
                    <h2><i class="fas fa-users me-2"></i>Manage Students</h2>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                            <i class="fas fa-plus me-2"></i>Add Student
                        </button>
                        <button class="btn btn-outline-light" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh
                        </button>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                <h4><?php echo count($instructor_students); ?></h4>
                                <p class="text-muted mb-0">Total Students</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-book fa-2x text-success mb-2"></i>
                                <h4><?php echo count($instructor_courses); ?></h4>
                                <p class="text-muted mb-0">My Courses</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-user-check fa-2x text-warning mb-2"></i>
                                <h4>0</h4>
                                <p class="text-muted mb-0">Active Today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-id-card fa-2x text-info mb-2"></i>
                                <h4>0</h4>
                                <p class="text-muted mb-0">RFID Assigned</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Students Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Students List
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($instructor_students)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Students Found</h5>
                                <p class="text-muted">You haven't added any students yet. Click "Add Student" to get started.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                                    <i class="fas fa-plus me-2"></i>Add Your First Student
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Course</th>
                                            <th>RFID Card</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($instructor_students as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-circle me-2" style="width: 32px; height: 32px; background: #007bff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold;">
                                                            <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                                                        </div>
                                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                <td><?php echo htmlspecialchars($student['course_name'] . ' (' . $student['course_code'] . ')'); ?></td>
                                                <td>
                                                    <?php if (!empty($student['rfid_uid'])): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-id-card me-1"></i>Assigned
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>Not Assigned
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $student['enrollment_status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($student['enrollment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editStudent(<?php echo htmlspecialchars($student['student_id']); ?>)" title="Edit Student">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-info" onclick="assignRFID(<?php echo htmlspecialchars($student['student_id']); ?>)" title="Assign RFID">
                                                            <i class="fas fa-id-card"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-warning" onclick="viewAttendance(<?php echo htmlspecialchars($student['student_id']); ?>)" title="View Attendance">
                                                            <i class="fas fa-chart-line"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteStudent(<?php echo htmlspecialchars($student['student_id']); ?>)" title="Delete Student">
                                                            <i class="fas fa-trash"></i>
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
    
    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Student to Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addStudentForm" method="POST">
                        <input type="hidden" name="action" value="add_student">
                        
                        <div class="mb-3">
                            <label class="form-label">Select Course</label>
                            <select name="course_id" class="form-select" required>
                                <?php foreach ($instructor_courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Select Student</label>
                            <select name="student_id" class="form-select" required>
                                <?php 
                                // Get all students not enrolled in instructor's courses
                                $available_students = $attendance->getAvailableStudents($instructor_id);
                                foreach ($available_students as $student): 
                                ?>
                                <option value="<?php echo $student['student_id']; ?>">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Add Student</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editStudent(studentId) {
            // Implement edit student functionality
            alert('Edit student functionality - Student ID: ' + studentId);
        }
        
        function assignRFID(studentId) {
            // Implement RFID assignment functionality
            alert('Assign RFID functionality - Student ID: ' + studentId);
        }
        
        function viewAttendance(studentId) {
            // Implement view attendance functionality
            alert('View attendance functionality - Student ID: ' + studentId);
        }
        
        function deleteStudent(studentId) {
            if (confirm('Are you sure you want to delete this student?')) {
                // Implement delete student functionality
                alert('Delete student functionality - Student ID: ' + studentId);
            }
        }
        
        // Auto-focus on student ID field when modal opens
        document.getElementById('addStudentModal').addEventListener('shown.bs.modal', function () {
            document.getElementById('student_id').focus();
        });
    </script>
</body>
</html>