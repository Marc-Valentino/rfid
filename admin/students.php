<?php
// Start the session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../config/config.php';
require_once '../includes/auth_validate.php';
require_once '../config/database.php';

// Initialize database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get students with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';

// Build query
$where_conditions = ["s.is_active = 1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($department_filter)) {
    $where_conditions[] = "s.department_id = ?";
    $params[] = $department_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM students s WHERE $where_clause";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get students
$sql = "SELECT s.*, d.department_name, c.course_name, 
               rc.rfid_uid, rc.card_status
        FROM students s 
        LEFT JOIN departments d ON s.department_id = d.department_id
        LEFT JOIN courses c ON s.course_id = c.course_id
        LEFT JOIN rfid_cards rc ON s.student_id = rc.student_id
        WHERE $where_clause
        ORDER BY s.student_number ASC
        LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get departments for filter
$dept_sql = "SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name";
$departments = $db->query($dept_sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Management - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #007bff;
            color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .btn-primary {
            background: #007bff;
            border-color: #007bff;
        }
        .btn-outline-primary {
            color: #007bff;
            border-color: #007bff;
        }
        .btn-outline-primary:hover {
            background: #007bff;
            border-color: #007bff;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
            margin: 0 2px;
        }

        .btn-danger {
            color: #fff;
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .btn-danger:hover {
            color: #fff;
            background-color: #c82333;
            border-color: #bd2130;
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
                    <a class="nav-link " href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link active" href="students.php">
                        <i class="fas fa-users me-2"></i>Students
                    </a>
                    <a class="nav-link" href="courses.php">
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
                    </div>
                    
                    <hr class="text-secondary">
                    
                    <a class="nav-link text-danger" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-users me-2"></i>Students Management</h2>
                    <div>
                        <a href="add-student.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Add Student
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" 
                                       name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Student number, name, or email">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['department_id']; ?>" 
                                                <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
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

                <!-- Students Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>Students List
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($students)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No students found</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th>Student #</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Department</th>
                                            <th>Course</th>
                                            <th>Year Level</th>
                                            <th>RFID Status</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($student['department_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($student['year_level'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if ($student['rfid_uid']): ?>
                                                        <span class="badge <?php echo $student['card_status'] == 'Active' ? 'bg-success' : 'bg-warning'; ?>">
                                                            <?php echo $student['card_status']; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">No Card</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $student['enrollment_status'] == 'Active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $student['enrollment_status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="edit-student.php?id=<?php echo $student['student_id']; ?>" 
                                                           class="btn btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="#" class="btn btn-outline-info" title="View Details"
                                                           data-bs-toggle="modal" data-bs-target="#studentModal"
                                                           data-student-id="<?php echo $student['student_id']; ?>"
                                                           data-student-number="<?php echo htmlspecialchars($student['student_number']); ?>"
                                                           data-first-name="<?php echo htmlspecialchars($student['first_name']); ?>"
                                                           data-last-name="<?php echo htmlspecialchars($student['last_name']); ?>"
                                                           data-email="<?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?>"
                                                           data-department="<?php echo htmlspecialchars($student['department_name'] ?? 'N/A'); ?>"
                                                           data-course="<?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?>"
                                                           data-year-level="<?php echo htmlspecialchars($student['year_level'] ?? 'N/A'); ?>"
                                                           data-enrollment-status="<?php echo htmlspecialchars($student['enrollment_status']); ?>"
                                                           data-rfid-status="<?php echo $student['rfid_uid'] ? $student['card_status'] : 'No Card'; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <form method="POST" class="d-inline" onsubmit="return confirmDelete(event)">
                                                            <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                                            <button type="submit" name="delete" class="btn btn-outline-danger btn-sm" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Students pagination" class="mt-3">
                                    <ul class="pagination justify-content-center">
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link bg-dark border-secondary text-light" 
                                                   href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Student Details Modal -->
    <div class="modal fade" id="studentModal" tabindex="-1" aria-labelledby="studentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-light border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="studentModalLabel">Student Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-12 text-center mb-3">
                            <i class="fas fa-user-graduate fa-4x text-primary"></i>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Student Number:</div>
                        <div class="col-md-8" id="modal-student-number"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Full Name:</div>
                        <div class="col-md-8" id="modal-full-name"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Email:</div>
                        <div class="col-md-8" id="modal-email"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Department:</div>
                        <div class="col-md-8" id="modal-department"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Course:</div>
                        <div class="col-md-8" id="modal-course"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Year Level:</div>
                        <div class="col-md-8" id="modal-year-level"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Status:</div>
                        <div class="col-md-8">
                            <span class="badge" id="modal-status-badge"></span>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">RFID Status:</div>
                        <div class="col-md-8">
                            <span class="badge" id="modal-rfid-badge"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-primary" id="modal-edit-link">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Student Modal Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const studentModal = document.getElementById('studentModal');
            if (studentModal) {
                studentModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const studentId = button.getAttribute('data-student-id');
                    const studentNumber = button.getAttribute('data-student-number');
                    const firstName = button.getAttribute('data-first-name');
                    const lastName = button.getAttribute('data-last-name');
                    const email = button.getAttribute('data-email');
                    const department = button.getAttribute('data-department');
                    const course = button.getAttribute('data-course');
                    const yearLevel = button.getAttribute('data-year-level');
                    const enrollmentStatus = button.getAttribute('data-enrollment-status');
                    const rfidStatus = button.getAttribute('data-rfid-status');
                    
                    // Set modal content
                    document.getElementById('modal-student-number').textContent = studentNumber;
                    document.getElementById('modal-full-name').textContent = firstName + ' ' + lastName;
                    document.getElementById('modal-email').textContent = email;
                    document.getElementById('modal-department').textContent = department;
                    document.getElementById('modal-course').textContent = course;
                    document.getElementById('modal-year-level').textContent = yearLevel;
                    
                    // Set status badge
                    const statusBadge = document.getElementById('modal-status-badge');
                    statusBadge.textContent = enrollmentStatus;
                    statusBadge.className = 'badge ' + (enrollmentStatus === 'Active' ? 'bg-success' : 'bg-secondary');
                    
                    // Set RFID badge
                    const rfidBadge = document.getElementById('modal-rfid-badge');
                    rfidBadge.textContent = rfidStatus;
                    if (rfidStatus === 'Active') {
                        rfidBadge.className = 'badge bg-success';
                    } else if (rfidStatus === 'No Card') {
                        rfidBadge.className = 'badge bg-danger';
                    } else {
                        rfidBadge.className = 'badge bg-warning';
                    }
                    
                    // Set edit link
                    document.getElementById('modal-edit-link').href = 'edit-student.php?id=' + studentId;
                });
            }
        });

    </script>
</body>
</html>

<?php
// Handle delete action
if (isset($_POST['delete']) && isset($_POST['student_id'])) {
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    if ($student_id) {
        try {
            $db->beginTransaction();
            
            // Delete related records first (order matters due to foreign key constraints)
            $tables = ['attendance_records', 'daily_attendance_summary', 'course_enrollments', 'rfid_cards'];
            foreach ($tables as $table) {
                $stmt = $db->prepare("DELETE FROM {$table} WHERE student_id = ?");
                $stmt->execute([$student_id]);
            }
            
            // Finally delete the student
            $stmt = $db->prepare("DELETE FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            
            // Log the deletion
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, user_agent) 
                                VALUES (?, 'DELETE', 'students', ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $student_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            
            $db->commit();
            $_SESSION['success'] = "Student has been deleted successfully!";
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Failed to delete student: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Invalid student ID provided";
    }
    
    header('Location: students.php');
    exit();
}
