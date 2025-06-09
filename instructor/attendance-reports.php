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

// Get instructor's courses for filter dropdown
$instructor_courses = $attendance->getInstructorCourses($instructor_id);

// Get filter parameters
$selected_course = isset($_GET['course_id']) ? $_GET['course_id'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // First day of current month
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d'); // Today
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

// Get attendance data based on filters
$attendance_data = [];
$summary_stats = [];

if (!empty($selected_course)) {
    // Get attendance records for the selected course and date range
    $attendance_data = $attendance->getAttendanceReportData($instructor_id, $selected_course, $date_from, $date_to);
    $summary_stats = $attendance->getAttendanceSummaryStats($instructor_id, $selected_course, $date_from, $date_to);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.3);
        }
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
        }
        .chart-container {
            position: relative;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
        }
        .filter-card {
            background: rgba(0, 123, 255, 0.1);
            border: 1px solid rgba(0, 123, 255, 0.3);
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
                    <a class="nav-link" href="manage-students.php">
                        <i class="fas fa-users me-2"></i>Manage Students
                    </a>
                    <a class="nav-link active" href="attendance-reports.php">
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
                    <h2><i class="fas fa-chart-bar me-2"></i>Attendance Reports</h2>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-light" onclick="exportReport()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <button class="btn btn-outline-light" onclick="printReport()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>
                
                <!-- Filters Card -->
                <div class="card filter-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i>Report Filters
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="course_id" class="form-label">Course</label>
                                <select class="form-select" id="course_id" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($instructor_courses as $course): ?>
                                        <option value="<?php echo $course['course_id']; ?>" 
                                                <?php echo ($selected_course == $course['course_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-select" id="report_type" name="report_type">
                                    <option value="summary" <?php echo ($report_type == 'summary') ? 'selected' : ''; ?>>Summary</option>
                                    <option value="detailed" <?php echo ($report_type == 'detailed') ? 'selected' : ''; ?>>Detailed</option>
                                    <option value="daily" <?php echo ($report_type == 'daily') ? 'selected' : ''; ?>>Daily</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Generate
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($selected_course)): ?>
                    <!-- Summary Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                    <h4><?php echo isset($summary_stats['total_students']) ? $summary_stats['total_students'] : 0; ?></h4>
                                    <p class="text-muted mb-0">Total Students</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                                    <h4><?php echo isset($summary_stats['total_sessions']) ? $summary_stats['total_sessions'] : 0; ?></h4>
                                    <p class="text-muted mb-0">Total Sessions</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-percentage fa-2x text-warning mb-2"></i>
                                    <h4><?php echo isset($summary_stats['avg_attendance']) ? number_format($summary_stats['avg_attendance'], 1) : 0; ?>%</h4>
                                    <p class="text-muted mb-0">Avg Attendance</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x text-info mb-2"></i>
                                    <h4><?php echo isset($summary_stats['total_hours']) ? number_format($summary_stats['total_hours'], 1) : 0; ?></h4>
                                    <p class="text-muted mb-0">Total Hours</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts Row -->
                    <div class="row mb-4">
                        <div class="col-lg-8 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-line me-2"></i>Attendance Trend
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="attendanceChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-pie me-2"></i>Attendance Distribution
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="distributionChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance Data Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-table me-2"></i>Attendance Records
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($attendance_data)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-chart-bar fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Attendance Data Found</h5>
                                    <p class="text-muted">No attendance records found for the selected criteria.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-dark table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Student</th>
                                                <th>Student ID</th>
                                                <th>Time In</th>
                                                <th>Time Out</th>
                                                <th>Duration</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendance_data as $record): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-circle me-2" style="width: 32px; height: 32px; background: #007bff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold;">
                                                                <?php echo strtoupper(substr($record['first_name'], 0, 1)); ?>
                                                            </div>
                                                            <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($record['student_number']); ?></td>
                                                    <td>
                                                        <?php echo isset($record['first_time_in']) ? date('g:i A', strtotime($record['first_time_in'])) : '-'; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo isset($record['last_time_out']) ? date('g:i A', strtotime($record['last_time_out'])) : '-'; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        if (isset($record['total_hours']) && $record['total_hours'] > 0) {
                                                            echo number_format($record['total_hours'], 1) . ' hrs';
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo match($record['attendance_status']) {
                                                                'Present' => 'success',
                                                                'Late' => 'warning',
                                                                'Absent' => 'danger',
                                                                'Half Day' => 'info',
                                                                default => 'secondary'
                                                            };
                                                        ?>">
                                                            <?php echo $record['attendance_status']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No Course Selected -->
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-chart-bar fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">Select a Course to View Reports</h4>
                            <p class="text-muted">Choose a course from the filter above to generate attendance reports.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sample data for charts - replace with actual data from PHP
        const attendanceData = {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [{
                label: 'Attendance Rate',
                data: [85, 92, 78, 88],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4
            }]
        };
        
        const distributionData = {
            labels: ['Present', 'Late', 'Absent'],
            datasets: [{
                data: [75, 15, 10],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545']
            }]
        };
        
        // Initialize charts if canvas elements exist
        const attendanceCtx = document.getElementById('attendanceChart');
        const distributionCtx = document.getElementById('distributionChart');
        
        if (attendanceCtx) {
            new Chart(attendanceCtx, {
                type: 'line',
                data: attendanceData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#ffffff'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                color: '#ffffff'
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#ffffff'
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        }
                    }
                }
            });
        }
        
        if (distributionCtx) {
            new Chart(distributionCtx, {
                type: 'doughnut',
                data: distributionData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#ffffff'
                            }
                        }
                    }
                }
            });
        }
        
        function exportReport() {
            alert('Export functionality - to be implemented');
        }
        
        function printReport() {
            window.print();
        }
        
        // Set max date to today
        document.getElementById('date_to').max = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>