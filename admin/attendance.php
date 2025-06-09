<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get date filter
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;

// Get attendance records
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Build query
    $where_conditions = ["ar.attendance_date = ?"];
    $params = [$date];
    
    if ($student_id) {
        $where_conditions[] = "ar.student_id = ?";
        $params[] = $student_id;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $sql = "
        SELECT ar.attendance_id, ar.student_id, ar.attendance_type, ar.scan_time,
               ar.location, ar.verification_status, ar.notes,
               s.student_number, s.first_name, s.last_name,
               d.department_name, c.course_name
        FROM attendance_records ar
        JOIN students s ON ar.student_id = s.student_id
        LEFT JOIN departments d ON s.department_id = d.department_id
        LEFT JOIN courses c ON s.course_id = c.course_id
        WHERE {$where_clause}
        ORDER BY ar.scan_time DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll();
    
    // Get summary for the day
    $summary_sql = "SELECT
        COUNT(DISTINCT s.student_id) as total_students,
        SUM(CASE WHEN das.attendance_status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN das.attendance_status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN das.attendance_status = 'Late' THEN 1 ELSE 0 END) as late_count
    FROM students s
    LEFT JOIN daily_attendance_summary das ON s.student_id = das.student_id AND das.attendance_date = ?
    WHERE s.is_active = 1";
    
    $summary_stmt = $pdo->prepare($summary_sql);
    $summary_stmt->execute([$date]);
    $summary = $summary_stmt->fetch();
    
    // Get students for filter
    $students_sql = "SELECT student_id, student_number, first_name, last_name FROM students WHERE is_active = 1 ORDER BY last_name, first_name";
    $students = $pdo->query($students_sql)->fetchAll();
    
} catch (Exception $e) {
    $error_msg = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - <?php echo APP_NAME; ?></title>
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
                        <i class="fas fa-book me-2"></i>Courses
                    </a>
                    <a class="nav-link" href="register-instructor.php">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Instructors
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-user-cog me-2"></i>Users
                    </a>
                    <a class="nav-link active" href="attendance.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-clock me-2"></i>Attendance Management</h2>
                    <div class="text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('F d, Y', strtotime($date)); ?>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                <h4><?php echo $summary['total_students'] ?? 0; ?></h4>
                                <p class="text-muted mb-0">Total Students</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-user-check fa-2x text-success mb-2"></i>
                                <h4><?php echo $summary['present_count'] ?? 0; ?></h4>
                                <p class="text-muted mb-0">Present</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-user-times fa-2x text-danger mb-2"></i>
                                <h4><?php echo $summary['absent_count'] ?? 0; ?></h4>
                                <p class="text-muted mb-0">Absent</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-user-clock fa-2x text-warning mb-2"></i>
                                <h4><?php echo $summary['late_count'] ?? 0; ?></h4>
                                <p class="text-muted mb-0">Late</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <!-- Add this button after the Filter button in the Filters card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="date" value="<?php echo $date; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Student</label>
                                <select class="form-select" name="student_id">
                                    <option value="">All Students</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['student_id']; ?>" 
                                                <?php echo $student_id == $student['student_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['student_number'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <a href="scan-rfid.php" class="btn btn-success">
                                        <i class="fas fa-wifi"></i> Scan Attendance
                                    </a>
                                </div>
                            </div>
                            <!-- Add Clear Attendance Button -->
                            <!-- <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <a href="clear_daily_attendance.php" class="btn btn-danger">
                                        <i class="fas fa-trash-alt"></i> Clear Today's Data
                                    </a>
                                </div>
                            </div> -->
                        </form>
                    </div>
                </div>

                <!-- Attendance Records -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Attendance Records
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($attendance_records)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No attendance records found for this date</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Department</th>
                                            <th>Type</th>
                                            <th>Time</th>
                                            <th>Location</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_records as $record): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($record['student_number']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['department_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php 
                                                        $type = $record['attendance_type'];
                                                        $badgeClass = ($type == 'Time In') ? 'bg-success' : 'bg-info';
                                                    ?>
                                                    <span class="badge <?php echo $badgeClass; ?>">
                                                        <?php echo htmlspecialchars($type); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('h:i A', strtotime($record['scan_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($record['location'] ?? 'Main Campus'); ?></td>
                                                <td>
                                                    <span class="badge bg-success">Verified</span>
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
        // Auto-refresh functionality
        function refreshAttendanceData() {
            // Get current filter values
            const currentDate = document.querySelector('input[name="date"]').value;
            const currentStudent = document.querySelector('select[name="student_id"]').value;
            
            // Construct the URL with current filters
            const refreshUrl = 'attendance.php?date=' + currentDate + 
                              (currentStudent ? '&student_id=' + currentStudent : '');
            
            // Fetch the page content
            fetch(refreshUrl)
                .then(response => response.text())
                .then(html => {
                    // Create a temporary DOM element to parse the HTML
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Update only the attendance records table and statistics
                    const newTable = doc.querySelector('.card-body .table-responsive');
                    const currentTable = document.querySelector('.card-body .table-responsive');
                    if (newTable && currentTable) {
                        currentTable.innerHTML = newTable.innerHTML;
                    }
                    
                    // Update statistics cards
                    const newStats = doc.querySelectorAll('.row.mb-4 .card-body');
                    const currentStats = document.querySelectorAll('.row.mb-4 .card-body');
                    if (newStats.length === currentStats.length) {
                        for (let i = 0; i < newStats.length; i++) {
                            currentStats[i].innerHTML = newStats[i].innerHTML;
                        }
                    }
                })
                .catch(error => console.error('Error refreshing data:', error));
        }
        
        // Set up auto-refresh every 30 seconds
        setInterval(refreshAttendanceData, 30000);
        
        // Add a visual indicator for the refresh
        const refreshIndicator = document.createElement('div');
        refreshIndicator.className = 'position-fixed bottom-0 end-0 p-3';
        refreshIndicator.innerHTML = `
            <div class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-sync-alt me-2"></i> Refreshing attendance data...
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        document.body.appendChild(refreshIndicator);
        
        // Show refresh indicator when refreshing
        setInterval(() => {
            const toastEl = document.querySelector('.toast');
            const toast = new bootstrap.Toast(toastEl, {delay: 2000});
            toast.show();
            refreshAttendanceData();
        }, 30000);
    </script>
</body>
</html>