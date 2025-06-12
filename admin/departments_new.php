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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_department'])) {
        // Handle add department
        $department_name = trim($_POST['department_name'] ?? '');
        $department_code = trim(strtoupper($_POST['department_code'] ?? ''));
        $head_of_department = trim($_POST['head_of_department'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($department_name) || empty($department_code)) {
            $error_message = "Department name and code are required";
        } else {
            // Check if department code already exists
            $existing = $system->executeQuery("SELECT * FROM departments WHERE department_code = ?", [$department_code]);
            if (!empty($existing)) {
                $error_message = "Department code already exists";
            } else {
                $result = $system->executeQuery(
                    "INSERT INTO departments (department_name, department_code, head_of_department, is_active) VALUES (?, ?, ?, ?)",
                    [$department_name, $department_code, $head_of_department, $is_active]
                );
                
                if ($result !== false) {
                    $_SESSION['success'] = "Department added successfully";
                    header('Location: departments.php');
                    exit();
                } else {
                    $error_message = "Failed to add department";
                }
            }
        }
    } elseif (isset($_POST['update_department'])) {
        // Handle update department
        $department_id = intval($_POST['department_id'] ?? 0);
        $department_name = trim($_POST['department_name'] ?? '');
        $department_code = trim(strtoupper($_POST['department_code'] ?? ''));
        $head_of_department = trim($_POST['head_of_department'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($department_id <= 0) {
            $error_message = "Invalid department ID";
        } elseif (empty($department_name) || empty($department_code)) {
            $error_message = "Department name and code are required";
        } else {
            // Check if department code already exists for another department
            $existing = $system->executeQuery(
                "SELECT * FROM departments WHERE department_code = ? AND department_id != ?", 
                [$department_code, $department_id]
            );
            
            if (!empty($existing)) {
                $error_message = "Department code already exists";
            } else {
                $result = $system->executeQuery(
                    "UPDATE departments SET department_name = ?, department_code = ?, head_of_department = ?, is_active = ? WHERE department_id = ?",
                    [$department_name, $department_code, $head_of_department, $is_active, $department_id]
                );
                
                if ($result !== false) {
                    $_SESSION['success'] = "Department updated successfully";
                    header('Location: departments.php');
                    exit();
                } else {
                    $error_message = "Failed to update department";
                }
            }
        }
    }
}

// Handle delete department
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $department_id = intval($_GET['delete']);
    
    // First check if there are any students assigned to this department
    $students = $system->executeQuery("SELECT COUNT(*) as count FROM students WHERE department_id = ?", [$department_id]);
    $student_count = $students[0]['count'] ?? 0;
    
    if ($student_count > 0) {
        $error_message = "Cannot delete department with assigned students";
    } else {
        $result = $system->executeQuery("DELETE FROM departments WHERE department_id = ?", [$department_id]);
        if ($result !== false) {
            $_SESSION['success'] = "Department deleted successfully";
            header('Location: departments.php');
            exit();
        } else {
            $error_message = "Failed to delete department";
        }
    }
}

// Get all departments
$departments = $system->executeQuery("SELECT * FROM departments ORDER BY department_name");

// Get department statistics
$total_departments = count($departments);
$active_departments = 0;
$inactive_departments = 0;

foreach ($departments as $dept) {
    if ($dept['is_active']) {
        $active_departments++;
    } else {
        $inactive_departments++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - <?php echo APP_NAME; ?></title>
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
        }
        .table {
            color: #fff;
            margin-bottom: 0;
        }
        .table th {
            border-color: rgba(255, 255, 255, 0.1);
        }
        .table td {
            border-color: rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .modal-content {
            background: #2d2d2d;
            color: #fff;
        }
        .form-control, .form-select {
            background-color: #1a1a1a;
            border: 1px solid #444;
            color: #fff;
        }
        .form-control:focus, .form-select:focus {
            background-color: #1a1a1a;
            color: #fff;
            border-color: #007bff;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }
        .btn-outline-primary {
            color: #007bff;
            border-color: #007bff;
        }
        .btn-outline-primary:hover {
            background-color: #007bff;
            color: #fff;
        }
        .btn-outline-danger {
            color: #dc3545;
            border-color: #dc3545;
        }
        .btn-outline-danger:hover {
            background-color: #dc3545;
            color: #fff;
        }
        .badge {
            font-weight: 500;
            padding: 0.4em 0.8em;
        }
        .summary-card {
            transition: transform 0.3s;
            cursor: pointer;
        }
        .summary-card:hover {
            transform: translateY(-5px);
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
                        <i class="fas fa-book me-2"></i>Courses
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
                </nav>
                
                <div class="mt-auto pt-3">
                    <a href="../logout.php" class="btn btn-outline-danger btn-sm w-100">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
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
                
                <!-- Departments Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Department List
                        </h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                            <i class="fas fa-plus me-1"></i> Add Department
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($departments)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-building fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No departments found</h5>
                                <p class="text-muted">Add your first department by clicking the button above</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle" id="departmentsTable">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Department Name</th>
                                            <th>Code</th>
                                            <th>Head of Department</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($departments as $index => $dept): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?php echo htmlspecialchars($dept['department_code']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo !empty($dept['head_of_department']) ? htmlspecialchars($dept['head_of_department']) : 'N/A'; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $dept['is_active'] ? 'success' : 'warning'; ?>">
                                                        <?php echo $dept['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-outline-primary edit-department" 
                                                            data-id="<?php echo $dept['department_id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($dept['department_name']); ?>"
                                                            data-code="<?php echo htmlspecialchars($dept['department_code']); ?>"
                                                            data-head="<?php echo htmlspecialchars($dept['head_of_department'] ?? ''); ?>"
                                                            data-status="<?php echo $dept['is_active']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-department"
                                                            data-id="<?php echo $dept['department_id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($dept['department_name']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
                <form id="addDepartmentForm" method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="department_name" class="form-label">Department Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="department_name" name="department_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="department_code" class="form-label">Department Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="department_code" name="department_code" maxlength="10" required>
                            <div class="form-text">Maximum 10 characters (e.g., CS, IT, ENG)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="head_of_department" class="form-label">Head of Department</label>
                            <input type="text" class="form-control" id="head_of_department" name="head_of_department">
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                        <button type="submit" name="add_department" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Department Modal -->
    <div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDepartmentModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Department
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editDepartmentForm" method="POST" action="">
                    <input type="hidden" name="department_id" id="edit_department_id" value="">
                    <div class="modal-body">
                        <div id="editFormErrors" class="alert alert-danger d-none">
                            <ul class="mb-0" id="editErrorList"></ul>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_department_name" class="form-label">Department Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_department_name" name="department_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_department_code" class="form-label">Department Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="edit_department_code" name="department_code" maxlength="10" required>
                            <div class="form-text">Maximum 10 characters (e.g., CS, IT, ENG)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_head_of_department" class="form-label">Head of Department</label>
                            <input type="text" class="form-control" id="edit_head_of_department" name="head_of_department">
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" value="1">
                            <label class="form-check-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                        <button type="submit" name="update_department" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteDepartmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteDeptName"></strong>? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i> Delete
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Handle edit department button click
        document.querySelectorAll('.edit-department').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const code = this.getAttribute('data-code');
                const head = this.getAttribute('data-head');
                const status = this.getAttribute('data-status');
                
                document.getElementById('edit_department_id').value = id;
                document.getElementById('edit_department_name').value = name;
                document.getElementById('edit_department_code').value = code;
                document.getElementById('edit_head_of_department').value = head || '';
                document.getElementById('edit_is_active').checked = status === '1';
                
                // Show the modal
                var editModal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
                editModal.show();
            });
        });

        // Handle delete department button click
        let deleteUrl = '';
        document.querySelectorAll('.delete-department').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('deleteDeptName').textContent = name;
                deleteUrl = `departments.php?delete=${id}`;
                
                // Show the modal
                var deleteModal = new bootstrap.Modal(document.getElementById('deleteDepartmentModal'));
                deleteModal.show();
            });
        });

        // Handle confirm delete button click
        document.getElementById('confirmDeleteBtn').addEventListener('click', function(e) {
            e.preventDefault();
            if (deleteUrl) {
                window.location.href = deleteUrl;
            }
        });

        // Auto-uppercase department code
        const deptCodeInputs = document.querySelectorAll('input[name="department_code"]');
        deptCodeInputs.forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
        });

        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
    </script>
</body>
</html>
