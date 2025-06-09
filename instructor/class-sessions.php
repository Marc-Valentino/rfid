<?php
session_start();
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_session':
                $course_id = $_POST['course_id'];
                $session_date = $_POST['session_date'];
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $room_location = $_POST['room_location'];
                $session_type = $_POST['session_type'];
                $description = $_POST['description'] ?? '';
                
                if ($attendance->createSession($instructor_id, $course_id, $session_date, $start_time, $end_time, $room_location, $session_type, $description)) {
                    $success_message = "Class session created successfully!";
                } else {
                    $error_message = "Failed to create session. Please try again.";
                }
                break;
                
            case 'update_session':
                $session_id = $_POST['session_id'];
                $session_status = $_POST['session_status'];
                
                if ($attendance->updateSessionStatus($session_id, $session_status)) {
                    $success_message = "Session updated successfully!";
                } else {
                    $error_message = "Failed to update session. Please try again.";
                }
                break;
                
            case 'delete_session':
                $session_id = $_POST['session_id'];
                
                if ($attendance->deleteSession($session_id)) {
                    $success_message = "Session deleted successfully!";
                } else {
                    $error_message = "Failed to delete session. Please try again.";
                }
                break;
        }
    }
}

// Get instructor's courses and sessions
$instructor_courses = $attendance->getInstructorCourses($instructor_id);
$upcoming_sessions = $attendance->getUpcomingSessions($instructor_id);
$today_sessions = $attendance->getTodaySessions($instructor_id);
$past_sessions = $attendance->getPastSessions($instructor_id);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Sessions - <?php echo APP_NAME; ?></title>
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
        .modal-content {
            background: #2d2d2d;
            border: 1px solid rgba(255, 255, 255, 0.2);
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
                    <a class="nav-link" href="attendance-reports.php">
                        <i class="fas fa-chart-bar me-2"></i>Attendance Reports
                    </a>
                    <a class="nav-link active" href="class-sessions.php">
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
                        <a class="nav-link text-light" href="#" data-bs-toggle="modal" data-bs-target="#newSessionModal">
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
                    <h2><i class="fas fa-calendar-alt me-2"></i>Class Sessions</h2>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newSessionModal">
                            <i class="fas fa-plus me-2"></i>New Session
                        </button>
                    </div>
                </div>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-day fa-2x text-primary mb-2"></i>
                                <h4><?php echo count($today_sessions); ?></h4>
                                <p class="text-muted mb-0">Today's Sessions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-week fa-2x text-success mb-2"></i>
                                <h4><?php echo count($upcoming_sessions); ?></h4>
                                <p class="text-muted mb-0">Upcoming</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-check fa-2x text-warning mb-2"></i>
                                <h4><?php echo count($past_sessions); ?></h4>
                                <p class="text-muted mb-0">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-book fa-2x text-info mb-2"></i>
                                <h4><?php echo count($instructor_courses); ?></h4>
                                <p class="text-muted mb-0">Courses</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sessions Tabs -->
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="sessionTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab">
                                    <i class="fas fa-clock me-2"></i>Upcoming Sessions
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="today-tab" data-bs-toggle="tab" data-bs-target="#today" type="button" role="tab">
                                    <i class="fas fa-calendar-day me-2"></i>Today's Sessions
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab">
                                    <i class="fas fa-history me-2"></i>Past Sessions
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="sessionTabsContent">
                            <!-- Upcoming Sessions -->
                            <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-dark table-striped">
                                        <thead>
                                            <tr>
                                                <th>Course</th>
                                                <th>Date & Time</th>
                                                <th>Location</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($upcoming_sessions)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">No upcoming sessions scheduled</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($upcoming_sessions as $session): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($session['course_name']); ?></td>
                                                        <td>
                                                            <?php echo date('M j, Y', strtotime($session['session_date'])); ?><br>
                                                            <small class="text-muted">
                                                                <?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>
                                                            </small>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($session['room_location']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $session['session_type'] == 'lecture' ? 'primary' : ($session['session_type'] == 'lab' ? 'info' : 'secondary'); ?>">
                                                                <?php echo ucfirst($session['session_type']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $session['session_status'] == 'Active' ? 'success' : 'warning'; ?>">
                                                                <?php echo $session['session_status']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-primary me-1" title="Edit" onclick="editSession(<?php echo $session['session_id']; ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-success me-1" title="Start Session" onclick="startSession(<?php echo $session['session_id']; ?>)">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" title="Cancel" onclick="cancelSession(<?php echo $session['session_id']; ?>)">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Today's Sessions -->
                            <div class="tab-pane fade" id="today" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-dark table-striped">
                                        <thead>
                                            <tr>
                                                <th>Course</th>
                                                <th>Time</th>
                                                <th>Location</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($today_sessions)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">No sessions scheduled for today</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($today_sessions as $session): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($session['course_name']); ?></td>
                                                        <td><?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?></td>
                                                        <td><?php echo htmlspecialchars($session['room_location']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $session['session_type'] == 'lecture' ? 'primary' : ($session['session_type'] == 'lab' ? 'info' : 'secondary'); ?>">
                                                                <?php echo ucfirst($session['session_type']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $session['session_status'] == 'Active' ? 'success' : ($session['session_status'] == 'Completed' ? 'secondary' : 'warning'); ?>">
                                                                <?php echo $session['session_status']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="scan-attendance.php?session_id=<?php echo $session['session_id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-qrcode"></i> Scan
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Past Sessions -->
                            <div class="tab-pane fade" id="past" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-dark table-striped">
                                        <thead>
                                            <tr>
                                                <th>Course</th>
                                                <th>Date & Time</th>
                                                <th>Location</th>
                                                <th>Type</th>
                                                <th>Attendance</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($past_sessions)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">No past sessions found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($past_sessions as $session): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($session['course_name']); ?></td>
                                                        <td>
                                                            <?php echo date('M j, Y', strtotime($session['session_date'])); ?><br>
                                                            <small class="text-muted">
                                                                <?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>
                                                            </small>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($session['room_location']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $session['session_type'] == 'lecture' ? 'primary' : ($session['session_type'] == 'lab' ? 'info' : 'secondary'); ?>">
                                                                <?php echo ucfirst($session['session_type']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $attendance_count = $session['attendance_count'] ?? 0;
                                                            $total_students = $session['total_students'] ?? 0;
                                                            $percentage = $total_students > 0 ? round(($attendance_count / $total_students) * 100) : 0;
                                                            echo $attendance_count . '/' . $total_students . ' (' . $percentage . '%)';
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <a href="attendance-reports.php?session_id=<?php echo $session['session_id']; ?>" class="btn btn-sm btn-outline-info" title="View Report">
                                                                <i class="fas fa-chart-bar"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Session Modal -->
    <div class="modal fade" id="newSessionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create New Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_session">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="course_id" class="form-label">Course</label>
                                <select class="form-select" id="course_id" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($instructor_courses as $course): ?>
                                        <option value="<?php echo $course['course_id']; ?>">
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="session_date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="session_date" name="session_date" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="room_location" class="form-label">Room/Location</label>
                                <input type="text" class="form-control" id="room_location" name="room_location" placeholder="e.g., Room A-101" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="session_type" class="form-label">Session Type</label>
                                <select class="form-select" id="session_type" name="session_type" required>
                                    <option value="">Select Type</option>
                                    <option value="lecture">Lecture</option>
                                    <option value="lab">Lab</option>
                                    <option value="tutorial">Tutorial</option>
                                    <option value="exam">Exam</option>
                                    <option value="seminar">Seminar</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Session description or notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Session
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Hidden forms for actions -->
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="actionType">
        <input type="hidden" name="session_id" id="sessionId">
        <input type="hidden" name="session_status" id="sessionStatus">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set minimum date to today
        document.getElementById('session_date').min = new Date().toISOString().split('T')[0];
        
        // Session management functions
        function editSession(sessionId) {
            // For now, just show an alert. You can implement a modal for editing later
            alert('Edit functionality will be implemented in a future update. Session ID: ' + sessionId);
        }
        
        function startSession(sessionId) {
            if (confirm('Are you sure you want to start this session?')) {
                document.getElementById('actionType').value = 'update_session';
                document.getElementById('sessionId').value = sessionId;
                document.getElementById('sessionStatus').value = 'Active';
                document.getElementById('actionForm').submit();
            }
        }
        
        function cancelSession(sessionId) {
            if (confirm('Are you sure you want to cancel this session?')) {
                document.getElementById('actionType').value = 'delete_session';
                document.getElementById('sessionId').value = sessionId;
                document.getElementById('actionForm').submit();
            }
        }
        
        // Auto-refresh every 60 seconds
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>