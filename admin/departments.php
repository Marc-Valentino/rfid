<?php
// Start session and include required files at the very top
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

$system = new AttendanceSystem();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Initialize variables
$departments = [];
$error_message = '';
$success_message = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

// Initialize department statistics
$total_departments = 0;
$active_departments = 0;
$inactive_departments = 0;

try {
    // Get all departments with counts
    $query = "SELECT d.*, 
                     (SELECT COUNT(*) FROM courses WHERE department_id = d.department_id) as course_count,
                     (SELECT COUNT(*) FROM users WHERE department_id = d.department_id) as user_count
              FROM departments d 
              ORDER BY d.department_name";
    
    // Use fetchAll to get all departments
    $departments = $system->executeQuery($query, [], true) ?: [];
    
    // Reset counters
    $total_departments = 0;
    $active_departments = 0;
    $inactive_departments = 0;
    
    // Process departments and count status
    if (is_array($departments)) {
        foreach ($departments as &$dept) {
            if (!is_array($dept)) {
                continue; // Skip non-array elements
            }
            
            // Ensure is_active is a boolean
            $dept['is_active'] = !empty($dept['is_active']);
            
            $total_departments++;
            if ($dept['is_active']) {
                $active_departments++;
            } else {
                $inactive_departments++;
            }
        }
        unset($dept); // Break the reference
    } else {
        // If result is not an array, log the error
        error_log("Unexpected result type from executeQuery: " . gettype($result));
        $departments = [];
    }
} catch (Exception $e) {
    error_log("Error fetching departments: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_department'])) {
        handleAddDepartment($system);
    } elseif (isset($_POST['update_department'])) {
        handleUpdateDepartment($system);
    }
}

// Handle department deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    handleDeleteDepartment($system, (int)$_GET['delete']);
}

/**
 * Handle adding a new department
 */
function handleAddDepartment($system) {
    $department_name = trim($_POST['department_name'] ?? '');
    $department_code = trim(strtoupper($_POST['department_code'] ?? ''));
    $head_of_department = trim($_POST['head_of_department'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Input validation
    $errors = [];
    if (empty($department_name)) {
        $errors[] = "Department name is required";
    }
    if (empty($department_code)) {
        $errors[] = "Department code is required";
    } elseif (strlen($department_code) > 10) {
        $errors[] = "Department code must be 10 characters or less";
    }

    if (empty($errors)) {
        try {
            $result = $system->executeQuery(
                "INSERT INTO departments (department_name, department_code, head_of_department, is_active, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, NOW(), NOW())",
                [$department_name, $department_code, $head_of_department, $is_active]
            );
            
            if ($result) {
                $_SESSION['success'] = "Department added successfully!";
                header("Location: departments.php");
                exit();
            } else {
                $errors[] = "Error adding department";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                $errors[] = "A department with this code already exists";
            } else {
                $errors[] = "Error adding department: " . $e->getMessage();
                error_log("Department Add Error: " . $e->getMessage());
            }
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        // Store form data in session to repopulate form
        $_SESSION['form_data'] = [
            'department_name' => $department_name,
            'department_code' => $department_code,
            'head_of_department' => $head_of_department,
            'is_active' => $is_active
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

/**
 * Handle updating a department
 */
function handleUpdateDepartment($system) {
    try {
        // Get and validate department ID
        $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
        if (!$department_id || $department_id <= 0) {
            throw new Exception("Invalid department ID");
        }
        
        // Sanitize and validate input
        $department_name = trim($_POST['department_name'] ?? '');
        $department_code = trim(strtoupper($_POST['department_code'] ?? ''));
        $head_of_department = trim($_POST['head_of_department'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Input validation
        $errors = [];
        
        if (empty($department_name)) {
            $errors[] = "Department name is required";
        } elseif (strlen($department_name) > 100) {
            $errors[] = "Department name must be 100 characters or less";
        }
        
        if (empty($department_code)) {
            $errors[] = "Department code is required";
        } elseif (strlen($department_code) > 10) {
            $errors[] = "Department code must be 10 characters or less";
        } elseif (!preg_match('/^[A-Z0-9]+$/', $department_code)) {
            $errors[] = "Department code can only contain uppercase letters and numbers";
        }
        
        if (strlen($head_of_department) > 100) {
            $errors[] = "Head of department name must be 100 characters or less";
        }
        
        // If there are validation errors, throw an exception
        if (!empty($errors)) {
            throw new Exception(implode("<br>", $errors));
        }
        
        // Check if department exists and is not a duplicate
        $existing = $system->executeQuery(
            "SELECT COUNT(*) as count FROM departments WHERE department_code = ? AND department_id != ?",
            [$department_code, $department_id],
            true
        );
        
        if ($existing && $existing[0]['count'] > 0) {
            throw new Exception("A department with this code already exists");
        }
        
        // Update the department
        $result = $system->executeQuery(
            "UPDATE departments SET 
                department_name = ?, 
                department_code = ?, 
                head_of_department = ?, 
                is_active = ?,
                updated_at = NOW()
            WHERE department_id = ?",
            [$department_name, $department_code, $head_of_department, $is_active, $department_id]
        );
        
        if ($result === false) {
            throw new Exception("Failed to update department. Please try again.");
        }
        
        // Clear any previous form data
        if (isset($_SESSION['form_data'])) {
            unset($_SESSION['form_data']);
        }
        
        // Set success message and redirect
        $_SESSION['success'] = "Department updated successfully!";
        
    } catch (Exception $e) {
        // Store error message and form data in session
        $_SESSION['error'] = $e->getMessage();
        $_SESSION['form_data'] = [
            'department_id' => $department_id ?? 0,
            'department_name' => $department_name ?? '',
            'department_code' => $department_code ?? '',
            'head_of_department' => $head_of_department ?? '',
            'is_active' => $is_active ?? 0
        ];
    }
    
    // Redirect back to the form or department list
    $redirect = isset($department_id) ? "?edit=$department_id" : '';
    header("Location: " . $_SERVER['PHP_SELF'] . $redirect);
    exit();
}

/**
 * Handle deleting a department
 */
function handleDeleteDepartment($system, $department_id) {
    try {
        // First check if there are any students assigned to this department
        $students = $system->executeQuery(
            "SELECT COUNT(*) as count FROM students WHERE department_id = ?",
            [$department_id]
        );
        
        if (is_array($students) && $students[0]['count'] > 0) {
            $_SESSION['error'] = "Cannot delete department. There are students assigned to it.";
            header("Location: departments.php");
            exit();
        }
        
        // If no students, proceed with deletion
        $result = $system->executeQuery(
            "DELETE FROM departments WHERE department_id = ?",
            [$department_id]
        );
        
        if ($result) {
            $_SESSION['success'] = "Department deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting department";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting department: " . $e->getMessage();
        error_log("Department Delete Error: " . $e->getMessage());
    }
    
    header("Location: departments.php");
    exit();
}

// Clear session messages after retrieving them
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - <?php echo APP_NAME; ?> - Event Management</title>
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
            margin-bottom: 20px;
        }
        .card-header {
            background: rgba(0, 123, 255, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }
        .table {
            color: #fff;
            margin-bottom: 0;
        }
        .table th {
            font-weight: 500;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .table td {
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }
        .badge {
            font-weight: 500;
            border-radius: 20px;
            padding: 5px 10px;
        }
        .btn-outline-primary {
            border-color: #0d6efd;
            color: #0d6efd;
        }
        .btn-outline-primary:hover {
            background-color: #0d6efd;
            color: #fff;
        }
        .btn-outline-danger {
            border-color: #dc3545;
            color: #dc3545;
        }
        .btn-outline-danger:hover {
            background-color: #dc3545;
            color: #fff;
        }
        /* Modal styling */
        .modal-content {
            background: #fff;
            color: #212529;
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .modal-header {
            border-bottom: 1px solid #eee;
            padding: 1rem 1.5rem;
        }
        .modal-body {
            padding: 1.5rem;
        }
        .modal-footer {
            border-top: 1px solid #eee;
            padding: 1rem 1.5rem;
        }
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            border: 1px solid #ced4da;
        }
        .form-control:focus, .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .invalid-feedback {
            font-size: 0.85rem;
        }
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            font-weight: 600;
            color: #495057;
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .table td {
            vertical-align: middle;
        }
        .badge {
            font-weight: 500;
            padding: 0.5em 0.75em;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .btn i {
            margin-right: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center mb-4">
                    <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                    <h5>EVENTRACK</h5>
                    <small class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></small>
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
                    <a class="nav-link active" href="departments.php">
                        <i class="fas fa-building me-2"></i>Departments
                    </a>
                    <a class="nav-link" href="events.php">
                        <i class="fas fa-calendar me-2"></i>Events
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
                        <a class="nav-link active text-light" href="scan-rfid.php">
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
                    <h2><i class="fas fa-building me-2"></i>Departments</h2>
                    <div class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('l, F j, Y'); ?>
                    </div>
                </div>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card summary-card bg-primary bg-opacity-10 border-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Departments</h6>
                                        <h3 class="mb-0"><?php echo $total_departments; ?></h3>
                                    </div>
                                    <div class="bg-primary bg-opacity-25 p-3 rounded-circle">
                                        <i class="fas fa-building fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card summary-card bg-success bg-opacity-10 border-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Active</h6>
                                        <h3 class="mb-0"><?php echo $active_departments; ?></h3>
                                    </div>
                                    <div class="bg-success bg-opacity-25 p-3 rounded-circle">
                                        <i class="fas fa-check-circle fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card summary-card bg-warning bg-opacity-10 border-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Inactive</h6>
                                        <h3 class="mb-0"><?php echo $inactive_departments; ?></h3>
                                    </div>
                                    <div class="bg-warning bg-opacity-25 p-3 rounded-circle">
                                        <i class="fas fa-exclamation-circle fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

<?php
// Departments are already loaded at the top of the file with counts
// No need to query again

// Handle department addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_department'])) {
    handleAddDepartment($system);
}

// Handle department update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_department'])) {
    handleUpdateDepartment($system);
}

// Handle department deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $department_id = intval($_GET['delete']);
    
    try {
        // First check if there are any students assigned to this department
        $check = $system->executeQuery(
            "SELECT COUNT(*) as count FROM students WHERE department_id = ?",
            [$department_id]
        )->fetch();
        
        if ($check['count'] > 0) {
            $_SESSION['error'] = "Cannot delete department: There are students assigned to this department";
        } else {
            $result = $system->executeQuery(
                "DELETE FROM departments WHERE department_id = ?",
                [$department_id]
            );
            
            if ($result) {
                $_SESSION['success'] = "Department deleted successfully!";
                header("Location: departments.php");
                exit();
            } else {
                $_SESSION['error'] = "Error deleting department";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: department.php");
    exit();
}

// Get all departments with counts
$departments = [];
try {
    $query = "SELECT d.*, 
                (SELECT COUNT(*) FROM courses c WHERE c.department_id = d.department_id) as course_count,
                (SELECT COUNT(*) FROM users u WHERE u.department_id = d.department_id) as user_count
              FROM departments d
              ORDER BY d.department_name";
    
    $departments = $system->executeQuery($query, [], true);
    
    if ($departments === false) {
        $error_message = "Error executing query";
        $departments = [];
    }
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    $error_message = "Error loading departments: " . $e->getMessage();
    $departments = [];
}
?>

    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-building me-2"></i>Department List
        </h5>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
            <i class="fas fa-plus me-1"></i> Add Department
        </button>
    </div>
    <div class="card-body">
        <?php if (empty($departments)): ?>
            <div class="text-center py-5">
                <i class="fas fa-building fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No departments found</h5>
                <p class="text-muted mb-4">Get started by adding your first department</p>
                <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                    <i class="fas fa-plus me-1"></i> Add Department
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="departmentsTable">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Department</th>
                            <th style="width: 100px;">Code</th>
                            <th>Head of Department</th>
                            <th style="width: 120px;" class="text-center">Courses/Users</th>
                            <th style="width: 120px;" class="text-center">Status</th>
                            <th style="width: 120px;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count = 1; ?>
                        <?php foreach ($departments as $dept): 
                            $course_count = $dept['course_count'] ?? 0;
                            $user_count = $dept['user_count'] ?? 0;
                        ?>
                            <tr class="department-row">
                                <td class="text-muted"><?php echo $count++; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 p-2 rounded me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-building text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0 fw-semibold"><?php echo htmlspecialchars($dept['department_name']); ?></h6>
                                            <small class="text-muted">ID: <?php echo $dept['department_id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2">
                                        <i class="fas fa-hashtag me-1"></i><?php echo htmlspecialchars($dept['department_code']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($dept['head_of_department'])): ?>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-secondary bg-opacity-10 p-2 rounded me-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                                <i class="fas fa-user-tie text-secondary"></i>
                                            </div>
                                            <div>
                                                <div class="fw-medium"><?php echo htmlspecialchars($dept['head_of_department']); ?></div>
                                                <small class="text-muted">Head of Department</small>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted">
                                            <i class="fas fa-user-slash me-1"></i> Not assigned
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-3">
                                        <div class="text-center">
                                            <div class="fw-bold"><?php echo $course_count; ?></div>
                                            <small class="text-muted">Courses</small>
                                        </div>
                                        <div class="vr"></div>
                                        <div class="text-center">
                                            <div class="fw-bold"><?php echo $user_count; ?></div>
                                            <small class="text-muted">Users</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill d-inline-flex align-items-center px-3 py-2 bg-<?php echo $dept['is_active'] ? 'success' : 'danger'; ?>-subtle text-<?php echo $dept['is_active'] ? 'success' : 'danger'; ?> border border-<?php echo $dept['is_active'] ? 'success' : 'danger'; ?>-subtle">
                                        <i class="fas fa-circle me-2" style="font-size: 6px;"></i>
                                        <?php echo $dept['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" 
                                                class="btn btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editDepartmentModal"
                                                data-bs-placement="top"
                                                title="Edit Department"
                                                data-id="<?php echo $dept['department_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($dept['department_name']); ?>"
                                                data-code="<?php echo htmlspecialchars($dept['department_code']); ?>"
                                                data-head="<?php echo htmlspecialchars($dept['head_of_department'] ?? ''); ?>"
                                                data-status="<?php echo $dept['is_active'] ? '1' : '0'; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-outline-danger delete-department"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteDepartmentModal"
                                                data-bs-placement="top"
                                                title="Delete Department"
                                                data-id="<?php echo $dept['department_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($dept['department_name']); ?>">
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

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDepartmentModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>Add New Department
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="department_name" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark text-light border-secondary">
                                <i class="fas fa-building"></i>
                            </span>
                            <input type="text" class="form-control bg-dark text-light border-secondary" id="department_name" name="department_name" required 
                                   value="<?php echo htmlspecialchars($_SESSION['form_data']['department_name'] ?? ''); ?>">
                        </div>
                        <div class="invalid-feedback">Please enter department name</div>
                    </div>
                    <div class="mb-3">
                        <label for="department_code" class="form-label">Department Code <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark text-light border-secondary">
                                <i class="fas fa-hashtag"></i>
                            </span>
                            <input type="text" class="form-control text-uppercase bg-dark text-light border-secondary" id="department_code" name="department_code" required
                                   value="<?php echo htmlspecialchars($_SESSION['form_data']['department_code'] ?? ''); ?>">
                        </div>
                        <div class="invalid-feedback">Please enter department code</div>
                    </div>
                    <div class="mb-3">
                        <label for="head_of_department" class="form-label">
                            <i class="fas fa-user-tie me-1"></i>Head of Department
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark text-light border-secondary">
                                <i class="fas fa-user-tie"></i>
                            </span>
                            <input type="text" class="form-control bg-dark text-light border-secondary" id="head_of_department" name="head_of_department"
                                   value="<?php echo htmlspecialchars($_SESSION['form_data']['head_of_department'] ?? ''); ?>"
                                   placeholder="Optional">
                        </div>
                    </div>
                    <div class="form-check form-switch mb-3 ps-5">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" 
                               <?php echo isset($_SESSION['form_data']) ? ($_SESSION['form_data']['is_active'] ? 'checked' : '') : 'checked'; ?>>
                        <label class="form-check-label" for="is_active">Active Department</label>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="submit" name="add_department" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Add Department
                    </button>
                </div>
                <?php unset($_SESSION['form_data']); ?>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border border-primary">
            <div class="modal-header border-primary">
                <h5 class="modal-title" id="editDepartmentModalLabel">
                    <i class="fas fa-edit me-2 text-primary"></i>Edit Department
                </h5>
                <div class="d-flex align-items-center">
                    <span id="edit_status_badge" class="badge rounded-pill me-2"></span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <form action="" method="post" id="editDepartmentForm" class="needs-validation" novalidate>
                <input type="hidden" name="department_id" id="edit_department_id">
                <input type="hidden" name="update_department" value="1">
                <div class="modal-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $_SESSION['error']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            <?php unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <h6 class="text-muted mb-3">Department Information</h6>
                        <div class="mb-3">
                            <label for="edit_department_name" class="form-label">
                                <i class="fas fa-building me-1"></i>Department Name <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark text-light border-secondary">
                                    <i class="fas fa-building"></i>
                                </span>
                                <input type="text" class="form-control bg-dark text-light border-secondary" 
                                       id="edit_department_name" name="department_name" required
                                       placeholder="Enter department name"
                                       value="<?php echo htmlspecialchars($_SESSION['form_data']['department_name'] ?? ''); ?>">
                            </div>
                            <div class="invalid-feedback">Please enter a valid department name</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_department_code" class="form-label">
                                <i class="fas fa-hashtag me-1"></i>Department Code <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark text-light border-secondary">
                                    <i class="fas fa-hashtag"></i>
                                </span>
                                <input type="text" class="form-control text-uppercase bg-dark text-light border-secondary" 
                                       id="edit_department_code" name="department_code" required
                                       placeholder="Enter department code (e.g., CS, IT)"
                                       value="<?php echo htmlspecialchars($_SESSION['form_data']['department_code'] ?? ''); ?>">
                            </div>
                            <div class="invalid-feedback">Please enter a valid department code</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="edit_head_of_department" class="form-label">
                                <i class="fas fa-user-tie me-1"></i>Head of Department
                                <small class="text-muted ms-1">(Optional)</small>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark text-light border-secondary">
                                    <i class="fas fa-user-tie"></i>
                                </span>
                                <input type="text" class="form-control bg-dark text-light border-secondary" 
                                       id="edit_head_of_department" name="head_of_department"
                                       placeholder="Enter head of department's name"
                                       value="<?php echo htmlspecialchars($_SESSION['form_data']['head_of_department'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="edit_is_active" 
                                   name="is_active" value="1" 
                                   <?php echo isset($_SESSION['form_data']) ? ($_SESSION['form_data']['is_active'] ? 'checked' : '') : 'checked'; ?>>
                            <label class="form-check-label" for="edit_is_active">
                                <i class="fas fa-toggle-on me-1"></i>Department Status
                            </label>
                            <div class="form-text text-muted">Toggle to activate or deactivate this department</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="submit" name="update_department" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
                <?php unset($_SESSION['form_data']); ?>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteDepartmentModal" tabindex="-1" aria-labelledby="deleteDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border border-danger">
            <div class="modal-header border-danger">
                <h5 class="modal-title" id="deleteDepartmentModalLabel">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>Delete Department
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the department <strong id="deleteDepartmentName"></strong>?</p>
                <p class="text-warning"><i class="fas fa-exclamation-circle me-2"></i>This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-danger">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                    <i class="fas fa-trash-alt me-1"></i> Delete
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="notificationToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="fas fa-circle-info me-2 text-primary"></i>
            <strong class="me-auto" id="toastTitle">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastMessage">
            <!-- Message will be inserted here -->
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    // Function to show toast notification
    function showToast(title, message, type = 'info') {
        const toastEl = document.getElementById('notificationToast');
        const toastTitle = document.getElementById('toastTitle');
        const toastMessage = document.getElementById('toastMessage');
        
        // Set toast style based on type
        const toast = bootstrap.Toast.getOrCreateInstance(toastEl);
        const toastHeader = toastEl.querySelector('.toast-header');
        
        // Remove previous color classes
        toastHeader.className = 'toast-header';
        
        // Set new color based on type
        let iconClass = 'fa-circle-info';
        switch(type) {
            case 'success':
                toastHeader.classList.add('bg-success', 'text-white');
                iconClass = 'fa-circle-check';
                break;
            case 'error':
                toastHeader.classList.add('bg-danger', 'text-white');
                iconClass = 'fa-circle-exclamation';
                break;
            case 'warning':
                toastHeader.classList.add('bg-warning', 'text-dark');
                iconClass = 'fa-triangle-exclamation';
                break;
            default:
                toastHeader.classList.add('bg-primary', 'text-white');
        }
        
        // Set icon
        toastHeader.querySelector('i').className = `fas ${iconClass} me-2`;
        
        // Set content
        toastTitle.textContent = title;
        toastMessage.innerHTML = message;
        
        // Show toast
        toast.show();
    }

    // Function to handle department deletion with confirmation
    function confirmDelete(event, element) {
        event.preventDefault();
        const deleteUrl = element.getAttribute('href');
        const deptName = element.getAttribute('data-name') || 'this department';
        
        // Show confirmation dialog
        if (confirm(`Are you sure you want to delete ${deptName}? This action cannot be undone.`)) {
            // Show loading state
            element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            element.disabled = true;
            
            // Perform delete
            fetch(deleteUrl, { method: 'GET' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Success', `Successfully deleted ${deptName}`, 'success');
                        // Reload the page after a short delay
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        throw new Error(data.message || 'Failed to delete department');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error', error.message || 'Failed to delete department', 'error');
                    element.innerHTML = '<i class="fas fa-trash"></i>';
                    element.disabled = false;
                });
        }
        return false;
    }

    // Handle delete confirmation modal
    const deleteModal = document.getElementById('deleteDepartmentModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const departmentId = button.getAttribute('data-id');
            const departmentName = button.getAttribute('data-name');
            
            // Update the modal content
            const modalTitle = deleteModal.querySelector('.modal-title');
            const departmentNameSpan = deleteModal.querySelector('#deleteDepartmentName');
            const deleteButton = deleteModal.querySelector('#confirmDeleteBtn');
            
            departmentNameSpan.textContent = departmentName;
            deleteButton.href = `departments.php?delete=${departmentId}`;
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Initialize toast
        const toastEl = document.getElementById('notificationToast');
        const toast = new bootstrap.Toast(toastEl, { autohide: true, delay: 5000 });

        // Form validation for add department
        const addDeptForm = document.querySelector('#addDepartmentModal form');
        if (addDeptForm) {
            addDeptForm.addEventListener('submit', function(event) {
                if (!addDeptForm.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                // Convert department code to uppercase
                const deptCode = document.getElementById('department_code');
                if (deptCode) {
                    deptCode.value = deptCode.value.toUpperCase();
                }
                
                addDeptForm.classList.add('was-validated');
            }, false);
        }

        // Form validation and submission for edit department
        const editDeptForm = document.querySelector('#editDepartmentForm');
        if (editDeptForm) {
            editDeptForm.addEventListener('submit', function(event) {
                event.preventDefault();
                
                // Convert department code to uppercase
                const editDeptCode = document.getElementById('edit_department_code');
                if (editDeptCode) {
                    editDeptCode.value = editDeptCode.value.trim().toUpperCase();
                }
                
                // Validate form
                if (!this.checkValidity()) {
                    event.stopPropagation();
                    this.classList.add('was-validated');
                    return;
                }
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
                
                // Submit the form
                this.submit();
            }, false);
        }

        // Handle edit modal show event
        const editModal = document.getElementById('editDepartmentModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function(event) {
                try {
                    // Get the button that triggered the modal
                    const button = event.relatedTarget;
                    
                    // Extract info from data-* attributes
                    const id = button.getAttribute('data-id');
                    const name = button.getAttribute('data-name');
                    const code = button.getAttribute('data-code');
                    const head = button.getAttribute('data-head') || '';
                    const status = button.getAttribute('data-status');
                    const isActive = status === '1' || status === 'true';
                    
                    console.log('Department data:', { id, name, code, head, status, isActive });
                    
                    // Update the modal title
                    const modalTitle = document.querySelector('#editDepartmentModal .modal-title');
                    if (modalTitle) {
                        modalTitle.innerHTML = `<i class="fas fa-edit me-2 text-primary"></i>Edit Department: ${name}`;
                    }
                    
                    // Set form values using the correct element IDs
                    const form = document.getElementById('editDepartmentForm');
                    if (!form) {
                        console.error('Edit form not found!');
                        return;
                    }
                    
                    // Clear any previous values and validation
                    form.reset();
                    form.classList.remove('was-validated');
                    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                    document.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
                    
                    // Set the form values
                    document.getElementById('edit_department_id').value = id;
                    document.getElementById('edit_department_name').value = name;
                    document.getElementById('edit_department_code').value = code;
                    document.getElementById('edit_head_of_department').value = head;
                    document.getElementById('edit_is_active').checked = isActive;
                    
                    // Add visual feedback for the active status
                    const statusBadge = document.getElementById('edit_status_badge');
                    if (statusBadge) {
                        statusBadge.className = `badge rounded-pill bg-${isActive ? 'success' : 'danger'}-subtle text-${isActive ? 'success' : 'danger'} border border-${isActive ? 'success' : 'danger'}-subtle`;
                        statusBadge.innerHTML = `<i class="fas fa-circle me-1" style="font-size: 6px;"></i> ${isActive ? 'Active' : 'Inactive'}`;
                    }
                    
                    // Focus on first input field after modal is shown
                    setTimeout(() => {
                        const firstInput = document.getElementById('edit_department_name');
                        if (firstInput) {
                            firstInput.focus();
                            firstInput.select();
                        }
                    }, 100);
                    
                    // Initialize tooltips
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                    
                } catch (error) {
                    console.error('Error in edit modal show event:', error);
                    showToast('Error', 'Failed to open edit form. Please try again.', 'error');
                }
            });
        }
        
        // Handle edit form submission
        const editForm = document.getElementById('editDepartmentForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
                
                // Validate form
                if (!this.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.add('was-validated');
                    
                    // Find first invalid field and focus on it
                    const firstInvalid = this.querySelector(':invalid');
                    if (firstInvalid) {
                        firstInvalid.focus();
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    return;
                }
                
                // Convert department code to uppercase
                const codeInput = this.querySelector('#edit_department_code');
                if (codeInput) {
                    codeInput.value = codeInput.value.trim().toUpperCase();
                }
                
                // Update the form action to include the department ID
                const deptId = this.querySelector('#edit_department_id').value;
                if (deptId) {
                    this.action = `?edit=${deptId}`;
                }
                
                // If validation passes, the form will submit normally
                // The server will handle the submission and redirect back
            });
        }
        
        // Initialize DataTable if it exists
        $(document).ready(function() {
            console.log('Document ready');
            
            // Initialize DataTable if it exists
            if ($.fn.DataTable.isDataTable('#departmentsTable')) {
                const table = $('#departmentsTable').DataTable();
                
                // Re-initialize tooltips when DataTable is redrawn
                table.on('draw', function() {
                    $('[data-bs-toggle="tooltip"]').tooltip();
                });
            }
            
            // Handle delete department button click
            document.querySelectorAll('.delete-department').forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this department? This action cannot be undone.')) {
                        e.preventDefault();
                        return;
                    }
                    
                    const deleteUrl = this.getAttribute('href');
                    const deptName = this.getAttribute('data-name');
                    
                    document.getElementById('deleteDepartmentName').textContent = deptName;
                    document.getElementById('confirmDeleteBtn').setAttribute('href', deleteUrl);
                    
                    const deleteModal = new bootstrap.Modal(document.getElementById('deleteDepartmentModal'));
                    deleteModal.show();
                    
                    e.preventDefault();
                });
            });
        });
        
        // Form validation
        var forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
        
        // Add department code validation (must be 2-10 characters, letters and numbers only)
        var departmentCodeInput = document.getElementById('department_code');
        if (departmentCodeInput) {
            departmentCodeInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
                this.value = this.value.replace(/[^A-Z0-9]/g, '');
                
                if (this.value.length > 10) {
                    this.value = this.value.slice(0, 10);
                }
                
                if (this.value.length < 2) {
                    this.setCustomValidity('Department code must be at least 2 characters');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
        
        // Edit department code validation
        var editDepartmentCodeInput = document.getElementById('edit_department_code');
        if (editDepartmentCodeInput) {
            editDepartmentCodeInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
                this.value = this.value.replace(/[^A-Z0-9]/g, '');
                
                if (this.value.length > 10) {
                    this.value = this.value.slice(0, 10);
                }
                
                if (this.value.length < 2) {
                    this.setCustomValidity('Department code must be at least 2 characters');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
        
        setTimeout(function() {
            $('.alert').fadeTo(500, 0).slideUp(500, function(){
                $(this).remove(); 
            });
        }, 5000);
    });
</script>
