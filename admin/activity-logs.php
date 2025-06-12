<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

$attendance = new AttendanceSystem();
$attendance->requireLogin();

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Handle clear logs action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    try {
        // First, get the count before deletion for the success message
        $count_query = "SELECT COUNT(*) as total FROM activity_logs";
        $count_result = $attendance->getPdo()->query($count_query)->fetch(PDO::FETCH_ASSOC);
        $deleted_count = $count_result['total'];
        
        // Delete all logs
        $delete_query = "TRUNCATE TABLE activity_logs";
        $attendance->getPdo()->exec($delete_query);
        
        // Log this action
        $attendance->logActivity(
            $_SESSION['user_id'],
            'clear',
            'activity_logs',
            null,
            ['count' => $deleted_count],
            null
        );
        
        // Redirect to avoid form resubmission
        $_SESSION['success_message'] = "Successfully cleared $deleted_count log entries.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error clearing logs: " . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Set page title
$page_title = 'Activity Logs';

// Initialize filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total logs count
$total_logs = $attendance->getPdo()->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

// Build the base query
$query = "SELECT al.*, u.username, u.full_name 
          FROM activity_logs al 
          LEFT JOIN users u ON al.user_id = u.user_id 
          WHERE 1=1";
$params = [];

// Apply filters
if (isset($_GET['action']) && !empty($_GET['action'])) {
    $query .= " AND al.action = :action";
    $params[':action'] = $_GET['action'];
}

if (isset($_GET['table_name']) && !empty($_GET['table_name'])) {
    $query .= " AND al.table_name = :table_name";
    $params[':table_name'] = $_GET['table_name'];
}

if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $query .= " AND al.user_id = :user_id";
    $params[':user_id'] = (int)$_GET['user_id'];
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $query .= " AND DATE(al.created_at) >= :date_from";
    $params[':date_from'] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $query .= " AND DATE(al.created_at) <= :date_to";
    $params[':date_to'] = $_GET['date_to'];
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM ($query) as count_table";
$stmt = $attendance->getPdo()->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_logs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_logs / $per_page);

// Add sorting and pagination
$query .= " ORDER BY al.created_at DESC LIMIT :offset, :per_page";
$params[':offset'] = $offset;
$params[':per_page'] = $per_page;

// Execute the query
$stmt = $attendance->getPdo()->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique actions and tables for filters
$actions = $attendance->getPdo()->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$tables = $attendance->getPdo()->query("SELECT DISTINCT table_name FROM activity_logs WHERE table_name IS NOT NULL ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?> - Event Management</title>
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
            font-weight: 500;
            padding: 5px 10px;
        }
        .form-control, .form-select {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .form-control:focus, .form-select:focus {
            background-color: rgba(255, 255, 255, 0.15);
            color: #fff;
            border-color: #007bff;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }
        .modal-content {
            background: #2d2d2d;
            color: #fff;
        }
        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        pre {
            background: #1e1e1e;
            color: #9cdcfe;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #333;
            max-height: 400px;
            overflow-y: auto;
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
                    <small class="text-muted">Welcome, <?php echo $_SESSION['full_name']; ?></small>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="students.php">
                        <i class="fas fa-users me-2"></i>Students
                    </a>
                    <a class="nav-link" href="courses.php">
                        <i class="fas fa-book me-2"></i>Courses
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
                    <div class="d-flex align-items-center">
                        <h2 class="mb-0"><i class="fas fa-history me-2"></i>Activity Logs</h2>
                        
                    </div>
                    <div class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('F j, Y, g:i a'); ?>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        <?php unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        <?php unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Total Logs</h6>
                                <h3 class="mb-0"><?php echo number_format($total_logs); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Actions</h6>
                                <h3 class="mb-0"><?php echo count($actions); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Tables</h6>
                                <h3 class="mb-0"><?php echo count($tables); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i>Filter Logs
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-3">
                                <label for="action" class="form-label">Action</label>
                                <select name="action" id="action" class="form-select">
                                    <option value="">All Actions</option>
                                    <?php foreach ($actions as $action): ?>
                                        <option value="<?php echo htmlspecialchars($action); ?>" <?php echo (isset($_GET['action']) && $_GET['action'] === $action) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(ucfirst($action)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="table_name" class="form-label">Table</label>
                                <select name="table_name" id="table_name" class="form-select">
                                    <option value="">All Tables</option>
                                    <?php foreach ($tables as $table): ?>
                                        <option value="<?php echo htmlspecialchars($table); ?>" <?php echo (isset($_GET['table_name']) && $_GET['table_name'] === $table) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($table); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="user_id" class="form-label">User ID</label>
                                <input type="number" class="form-control" id="user_id" name="user_id" 
                                       value="<?php echo htmlspecialchars($_GET['user_id'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i> Filter
                                </button>
                                <a href="activity-logs.php" class="btn btn-secondary">
                                    <i class="fas fa-undo me-1"></i> Reset
                                </a>
                                <button type="button" class="btn btn-danger btn-sm ms-3" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                            <i class="fas fa-trash-alt me-1"></i>Clear Logs
                        </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Logs Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Activity Logs
                        </h5>
                        <div class="text-muted small">
                            Showing <?php echo count($logs); ?> of <?php echo number_format($total_logs); ?> logs
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($logs)): ?>
                            <div class="alert alert-info m-4">No activity logs found.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Timestamp</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Table</th>
                                            <th>Record ID</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td><?php echo $log['log_id']; ?></td>
                                                <td>
                                                    <span title="<?php echo htmlspecialchars($log['created_at']); ?>">
                                                        <?php echo date('M j, Y', strtotime($log['created_at'])); ?>
                                                        <small class="d-block text-muted"><?php echo date('h:i A', strtotime($log['created_at'])); ?></small>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($log['user_id']): ?>
                                                        <span title="User ID: <?php echo $log['user_id']; ?>">
                                                            <?php echo !empty($log['full_name']) ? htmlspecialchars($log['full_name']) : 
                                                                  (!empty($log['username']) ? htmlspecialchars($log['username']) : 'User #' . $log['user_id']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">System</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $badge_class = 'bg-secondary';
                                                    if (in_array(strtolower($log['action']), ['create', 'insert', 'add'])) {
                                                        $badge_class = 'bg-success';
                                                    } elseif (in_array(strtolower($log['action']), ['update', 'edit', 'modify'])) {
                                                        $badge_class = 'bg-primary';
                                                    } elseif (in_array(strtolower($log['action']), ['delete', 'remove'])) {
                                                        $badge_class = 'bg-danger';
                                                    } elseif (in_array(strtolower($log['action']), ['login', 'logout', 'auth'])) {
                                                        $badge_class = 'bg-info';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>">
                                                        <?php echo htmlspecialchars(ucfirst($log['action'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $log['table_name'] ? htmlspecialchars($log['table_name']) : '-'; ?></td>
                                                <td><?php echo $log['record_id'] ? $log['record_id'] : '-'; ?></td>
                                                <td>
                                                    <?php if ($log['old_values'] || $log['new_values']): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                                data-bs-target="#logDetailsModal" 
                                                                data-action="<?php echo htmlspecialchars($log['action']); ?>"
                                                                data-table="<?php echo htmlspecialchars($log['table_name']); ?>"
                                                                data-old-values='<?php echo htmlspecialchars($log['old_values']); ?>'
                                                                data-new-values='<?php echo htmlspecialchars($log['new_values']); ?>'
                                                                data-ip="<?php echo htmlspecialchars($log['ip_address']); ?>"
                                                                data-user-agent="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                                            <i class="fas fa-eye me-1"></i> View
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">No details</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="card-footer">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center mb-0">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                                                        <i class="fas fa-angle-double-left"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                        <i class="fas fa-angle-left"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>

                                            <?php
                                            $start_page = max(1, $page - 2);
                                            $end_page = min($total_pages, $start_page + 4);
                                            $start_page = max(1, $end_page - 4);
                                            
                                            for ($i = $start_page; $i <= $end_page; $i++):
                                                $is_active = $i == $page ? 'active' : '';
                                            ?>
                                                <li class="page-item <?php echo $is_active; ?>">
                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                        <i class="fas fa-angle-right"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                                        <i class="fas fa-angle-double-right"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Log Details Modal -->
                <div class="modal fade" id="logDetailsModal" tabindex="-1" aria-labelledby="logDetailsModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Log Details</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Action</small>
                                            <span id="modal-action" class="fw-bold"></span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Table</small>
                                            <span id="modal-table" class="fw-bold"></span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-2">
                                            <small class="text-muted d-block">IP Address</small>
                                            <span id="modal-ip" class="fw-bold"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <small class="text-muted d-block mb-1">User Agent</small>
                                    <div id="modal-user-agent" class="bg-dark p-2 rounded small"></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-dark bg-opacity-25">
                                                <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Old Values</h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <pre id="modal-old-values" class="m-0">
                                                    <code class="language-json"></code>
                                                </pre>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-dark bg-opacity-25">
                                                <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>New Values</h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <pre id="modal-new-values" class="m-0">
                                                    <code class="language-json"></code>
                                                </pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i> Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Clear Logs Confirmation Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1" aria-labelledby="clearLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clearLogsModalLabel">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>Confirm Clear Logs
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to clear all activity logs? This action cannot be undone.</p>
                <p class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>Warning: This will permanently delete all log entries.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <form id="clearLogsForm" method="post" style="display: inline;">
                    <input type="hidden" name="clear_logs" value="1">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i>Clear All Logs
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Handle log details modal
    var logDetailsModal = document.getElementById('logDetailsModal');
    if (logDetailsModal) {
        logDetailsModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            
            // Extract info from data-* attributes
            var action = button.getAttribute('data-action');
            var table = button.getAttribute('data-table') || 'N/A';
            var oldValues = button.getAttribute('data-old-values');
            var newValues = button.getAttribute('data-new-values');
            var ip = button.getAttribute('data-ip') || 'N/A';
            var userAgent = button.getAttribute('data-user-agent') || 'N/A';

            // Update the modal's content
            document.getElementById('modal-action').textContent = action;
            document.getElementById('modal-table').textContent = table;
            document.getElementById('modal-ip').textContent = ip;
            document.getElementById('modal-user-agent').textContent = userAgent;

            // Format and display JSON data
            function formatJson(jsonString) {
                try {
                    return JSON.stringify(JSON.parse(jsonString), null, 2);
                } catch (e) {
                    return jsonString || 'No data';
                }
            }

            document.querySelector('#modal-old-values code').textContent = formatJson(oldValues);
            document.querySelector('#modal-new-values code').textContent = formatJson(newValues);

            // Apply syntax highlighting if available
            if (typeof hljs !== 'undefined') {
                document.querySelectorAll('pre code').forEach((block) => {
                    hljs.highlightBlock(block);
                });
            }
        });
    }

    // Auto-refresh logs every 30 seconds
    let refreshTimer;
    const refreshInterval = 30000; // 30 seconds
    
    function setupAutoRefresh() {
        if (refreshTimer) clearTimeout(refreshTimer);
        refreshTimer = setTimeout(() => {
            if (!document.hidden) {
                window.location.reload();
            } else {
                setupAutoRefresh(); // Reset timer if tab is not active
            }
        }, refreshInterval);
    }

    // Start auto-refresh when page loads
    setupAutoRefresh();
    
    // Reset timer on user activity
    document.addEventListener('mousemove', setupAutoRefresh);
    document.addEventListener('keydown', setupAutoRefresh);
    
    // Handle tab visibility changes
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            window.location.reload();
        }
    });
</script>
</body>
</html>
