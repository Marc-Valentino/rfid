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

// Get instructor's courses
$instructor_courses = $attendance->getInstructorCourses($instructor_id);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - <?php echo APP_NAME; ?></title>
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
        .course-card {
            transition: transform 0.2s;
        }
        .course-card:hover {
            transform: translateY(-5px);
        }
        .course-stats {
            background: rgba(0, 123, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
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
                    <a class="nav-link active" href="dashboard.php">
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
                    <div>
                        <h2><i class="fas fa-book me-2"></i>My Courses</h2>
                        <p class="text-muted mb-0">Manage and view your assigned courses</p>
                    </div>
                    <div>
                        <button class="btn btn-outline-primary me-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <a href="scan-attendance.php" class="btn btn-primary">
                            <i class="fas fa-qrcode me-2"></i>Quick Scan
                        </a>
                    </div>
                </div>

                <!-- Course Statistics -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="course-stats text-center">
                            <i class="fas fa-book fa-2x text-primary mb-2"></i>
                            <h4><?php echo count($instructor_courses); ?></h4>
                            <p class="text-muted mb-0">Total Courses</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="course-stats text-center">
                            <i class="fas fa-users fa-2x text-success mb-2"></i>
                            <h4><?php echo array_sum(array_column($instructor_courses, 'enrolled_students')); ?></h4>
                            <p class="text-muted mb-0">Total Students</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="course-stats text-center">
                            <i class="fas fa-calendar-check fa-2x text-warning mb-2"></i>
                            <h4><?php echo count(array_filter($instructor_courses, function($course) { return $course['is_active']; })); ?></h4>
                            <p class="text-muted mb-0">Active Courses</p>
                        </div>
                    </div>
                </div>

                <!-- Courses Grid -->
                <?php if (empty($instructor_courses)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-book fa-3x text-muted mb-3"></i>
                            <h5>No Courses Assigned</h5>
                            <p class="text-muted">You don't have any courses assigned yet. Contact your administrator for course assignments.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($instructor_courses as $course): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card course-card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <i class="fas fa-book me-2"></i>
                                            <?php echo htmlspecialchars($course['course_code']); ?>
                                        </h6>
                                        <span class="badge bg-<?php echo $course['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $course['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                                        <p class="card-text text-muted">
                                            <i class="fas fa-building me-2"></i>
                                            <?php echo htmlspecialchars($course['department_name'] ?? 'No Department'); ?>
                                        </p>
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <div class="border-end">
                                                    <h6 class="text-primary"><?php echo $course['enrolled_students']; ?></h6>
                                                    <small class="text-muted">Students</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <h6 class="text-info"><?php echo $course['credits'] ?? 'N/A'; ?></h6>
                                                <small class="text-muted">Credits</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <div class="btn-group w-100" role="group">
                                            <a href="scan-attendance.php?course_id=<?php echo $course['course_id']; ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-qrcode"></i> Scan
                                            </a>
                                            <a href="attendance-reports.php?course_id=<?php echo $course['course_id']; ?>" 
                                               class="btn btn-outline-info btn-sm">
                                                <i class="fas fa-chart-bar"></i> Reports
                                            </a>
                                            <a href="manage-students.php?course_id=<?php echo $course['course_id']; ?>" 
                                               class="btn btn-outline-success btn-sm">
                                                <i class="fas fa-users"></i> Students
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>