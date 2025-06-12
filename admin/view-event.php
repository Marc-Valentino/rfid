<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Initialize AttendanceSystem
$attendance = new AttendanceSystem();
$attendance->requireLogin();

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid event ID';
    header('Location: events.php');
    exit();
}

$event_id = (int)$_GET['id'];

// Get event details
$sql = "SELECT e.*, et.type_name, u.full_name as organizer_name 
        FROM events e
        LEFT JOIN event_types et ON e.type_id = et.type_id
        LEFT JOIN users u ON e.organizer_id = u.user_id
        WHERE e.event_id = ?";
try {
    $stmt = $db->prepare($sql);
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        $_SESSION['error_message'] = 'Event not found';
        header('Location: events.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching event: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error loading event details';
    header('Location: events.php');
    exit();
}

if (!$event) {
    $_SESSION['error_message'] = 'Event not found';
    header('Location: events.php');
    exit();
}

// Get event days
$days_sql = "SELECT * FROM event_days WHERE event_id = ? ORDER BY event_date";
$days = $db->prepare($days_sql);
$days->execute([$event_id]);
$event_days = $days->fetchAll(PDO::FETCH_ASSOC);

// Get registration count
$reg_sql = "SELECT COUNT(*) as count FROM event_registrations WHERE event_id = ?";
$reg_count = $db->prepare($reg_sql);
$reg_count->execute([$event_id]);
$registration_count = $reg_count->fetch(PDO::FETCH_ASSOC)['count'];

// Get attendance summary
$attendance_sql = "SELECT 
                    COUNT(DISTINCT student_id) as total_students,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count
                   FROM event_attendance 
                   WHERE event_id = ?";
$attendance_summary = $db->prepare($attendance_sql);
$attendance_summary->execute([$event_id]);
$attendance = $attendance_summary->fetch(PDO::FETCH_ASSOC);

$page_title = 'View Event: ' . htmlspecialchars($event['event_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['event_name']) ?> - <?php echo APP_NAME; ?></title>
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
        .table {
            color: #fff;
        }
        .table th {
            border-color: rgba(255, 255, 255, 0.1);
        }
        .table td {
            border-color: rgba(255, 255, 255, 0.1);
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .modal-content {
            background: #2d2d2d;
            color: #fff;
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
                    <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                    <h5>EVENTRACK</h5>
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
                    <a class="nav-link active" href="events.php">
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
                        <a class="nav-link text-light" href="add-event.php">
                            <i class="fas fa-calendar-plus me-2"></i>Add Event
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
                    <h2><i class="fas fa-calendar me-2"></i>Event Details</h2>
                    <div class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('F d, Y - h:i A'); ?>
                    </div>
                </div>
                
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="events.php">Events</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($event['event_name']) ?></li>
                </ol>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4"><?= htmlspecialchars($event['event_name']) ?></h1>
        <div class="btn-group">
            <a href="edit-event.php?id=<?= $event_id ?>" class="btn btn-warning">
                <i class="fas fa-edit me-1"></i> Edit Event
            </a>
            <a href="event-attendance.php?event_id=<?= $event_id ?>" class="btn btn-primary">
                <i class="fas fa-clipboard-check me-1"></i> Take Attendance
            </a>
        </div>
    </div>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="events.php">Events</a></li>
        <li class="breadcrumb-item active"><?= htmlspecialchars($event['event_name']) ?></li>
    </ol>

    <div class="row">
        <!-- Event Details -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-1"></i> Event Information
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Basic Information</h5>
                            <p><strong>Event Type:</strong> <?= htmlspecialchars($event['type_name']) ?></p>
                            <p><strong>Organizer:</strong> <?= htmlspecialchars($event['organizer_name']) ?></p>
                            <p><strong>Location:</strong> <?= htmlspecialchars($event['location'] ?? 'N/A') ?></p>
                            <p><strong>Status:</strong> 
                                <?php
                                $now = new DateTime();
                                $start_date = new DateTime($event['start_date']);
                                $end_date = new DateTime($event['end_date']);
                                $status = '';
                                $badge = '';
                                
                                if ($now > $end_date) {
                                    $status = 'Completed';
                                    $badge = 'bg-secondary';
                                } elseif ($now >= $start_date && $now <= $end_date) {
                                    $status = 'Ongoing';
                                    $badge = 'bg-success';
                                } else {
                                    $status = 'Upcoming';
                                    $badge = 'bg-primary';
                                }
                                ?>
                                <span class="badge <?= $badge ?>"><?= $status ?></span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h5>Date & Time</h5>
                            <p><strong>Start Date:</strong> <?= date('F j, Y', strtotime($event['start_date'])) ?></p>
                            <p><strong>End Date:</strong> <?= date('F j, Y', strtotime($event['end_date'])) ?></p>
                            <?php if ($event['registration_deadline']): ?>
                                <p><strong>Registration Deadline:</strong> <?= date('F j, Y g:i A', strtotime($event['registration_deadline'])) ?></p>
                            <?php endif; ?>
                            <p><strong>Max Participants:</strong> 
                                <?= $event['max_participants'] ? number_format($event['max_participants']) : 'Unlimited' ?>
                                <?php if ($event['max_participants']): ?>
                                    (<?= number_format(($registration_count / $event['max_participants']) * 100, 1) ?>% full)
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if (!empty($event['description'])): ?>
                        <div class="mt-4">
                            <h5>Description</h5>
                            <div class="border p-3 bg-light rounded">
                                <?= nl2br(htmlspecialchars($event['description'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($event['contact_email']) || !empty($event['contact_phone'])): ?>
                        <div class="mt-4">
                            <h5>Contact Information</h5>
                            <ul class="list-unstyled">
                                <?php if (!empty($event['contact_email'])): ?>
                                    <li><i class="fas fa-envelope me-2"></i> 
                                        <a href="mailto:<?= htmlspecialchars($event['contact_email']) ?>">
                                            <?= htmlspecialchars($event['contact_email']) ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if (!empty($event['contact_phone'])): ?>
                                    <li><i class="fas fa-phone me-2"></i> <?= htmlspecialchars($event['contact_phone']) ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Event Days -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><i class="far fa-calendar-alt me-1"></i> Event Days</div>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDayModal">
                        <i class="fas fa-plus me-1"></i> Add Day
                    </button>
                </div>
                <div class="card-body">
                    <?php if (count($event_days) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Date</th>
                                        <th>Day Name</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($event_days as $day): ?>
                                        <tr>
                                            <td>Day <?= $day['day_number'] ?></td>
                                            <td><?= date('F j, Y', strtotime($day['event_date'])) ?></td>
                                            <td><?= $day['day_name'] ?></td>
                                            <td>
                                                <a href="event-attendance.php?event_id=<?= $event_id ?>&day_id=<?= $day['day_id'] ?>" 
                                                   class="btn btn-sm btn-info" title="View Attendance">
                                                    <i class="fas fa-clipboard-check"></i>
                                                </a>
                                                <button class="btn btn-sm btn-danger delete-day" 
                                                        data-id="<?= $day['day_id'] ?>" 
                                                        title="Delete Day">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">No event days have been added yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Stats and Actions -->
        <div class="col-md-4">
            <!-- Registration Stats -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-users me-1"></i> Registration Stats
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="display-4"><?= number_format($registration_count) ?></div>
                        <div class="text-muted">Total Registrations</div>
                    </div>
                    
                    <?php if ($event['max_participants']): ?>
                        <div class="progress mb-3" style="height: 20px;">
                            <?php 
                            $percentage = min(100, ($registration_count / $event['max_participants']) * 100);
                            ?>
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?= $percentage ?>%" 
                                 aria-valuenow="<?= $percentage ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                <?= number_format($percentage, 1) ?>%
                            </div>
                        </div>
                        <p class="text-center mb-0">
                            <strong><?= number_format($registration_count) ?></strong> of 
                            <strong><?= number_format($event['max_participants']) ?></strong> spots filled
                        </p>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <div class="d-grid gap-2">
                        <a href="event-registrations.php?event_id=<?= $event_id ?>" class="btn btn-primary">
                            <i class="fas fa-list me-1"></i> View All Registrations
                        </a>
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addRegistrationModal">
                            <i class="fas fa-user-plus me-1"></i> Add Registration
                        </button>
                        <a href="export-registrations.php?event_id=<?= $event_id ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-file-export me-1"></i> Export Registrations
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-clipboard-check me-1"></i> Attendance Summary
                </div>
                <div class="card-body">
                    <?php if ($attendance['total_students'] > 0): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Present</span>
                                <span><?= $attendance['present_count'] ?> (<?= round(($attendance['present_count'] / $attendance['total_students']) * 100) ?>%)</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?= ($attendance['present_count'] / $attendance['total_students']) * 100 ?>%" 
                                     aria-valuenow="<?= ($attendance['present_count'] / $attendance['total_students']) * 100 ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Late</span>
                                <span><?= $attendance['late_count'] ?> (<?= round(($attendance['late_count'] / $attendance['total_students']) * 100) ?>%)</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-warning" role="progressbar" 
                                     style="width: <?= ($attendance['late_count'] / $attendance['total_students']) * 100 ?>%" 
                                     aria-valuenow="<?= ($attendance['late_count'] / $attendance['total_students']) * 100 ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Absent</span>
                                <span><?= $attendance['absent_count'] ?> (<?= round(($attendance['absent_count'] / $attendance['total_students']) * 100) ?>%)</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-danger" role="progressbar" 
                                     style="width: <?= ($attendance['absent_count'] / $attendance['total_students']) * 100 ?>%" 
                                     aria-valuenow="<?= ($attendance['absent_count'] / $attendance['total_students']) * 100 ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <div class="display-6"><?= $attendance['total_students'] ?></div>
                            <div class="text-muted">Total Students Tracked</div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">No attendance records found for this event.</div>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <div class="d-grid gap-2">
                        <a href="event-attendance.php?event_id=<?= $event_id ?>" class="btn btn-primary">
                            <i class="fas fa-clipboard-check me-1"></i> Take Attendance
                        </a>
                        <?php if ($attendance['total_students'] > 0): ?>
                            <a href="export-attendance.php?event_id=<?= $event_id ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-file-export me-1"></i> Export Attendance
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-bolt me-1"></i> Quick Actions
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="send-reminder.php?event_id=<?= $event_id ?>" class="btn btn-outline-primary">
                            <i class="fas fa-envelope me-1"></i> Send Reminder
                        </a>
                        <a href="event-qrcode.php?event_id=<?= $event_id ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-qrcode me-1"></i> Generate QR Code
                        </a>
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteEventModal">
                            <i class="fas fa-trash-alt me-1"></i> Delete Event
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
            </div>
        </div>
    </div>

    <!-- Add Day Modal -->
<div class="modal fade" id="addDayModal" tabindex="-1" aria-labelledby="addDayModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addDayForm" method="post" action="api/add-event-day.php">
                <input type="hidden" name="event_id" value="<?= $event_id ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDayModalLabel">Add Event Day</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="event_date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="event_date" name="event_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="day_name" class="form-label">Day Name</label>
                        <input type="text" class="form-control" id="day_name" name="day_name" placeholder="E.g., Day 1, Workshop Day, etc.">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Day</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Registration Modal -->
<div class="modal fade" id="addRegistrationModal" tabindex="-1" aria-labelledby="addRegistrationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addRegistrationForm" method="post" action="api/add-registration.php">
                <input type="hidden" name="event_id" value="<?= $event_id ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="addRegistrationModalLabel">Add Registration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="student_id" class="form-label">Student <span class="text-danger">*</span></label>
                        <select class="form-select" id="student_id" name="student_id" required>
                            <option value="">-- Select Student --</option>
                            <?php
                            $students = $db->query("SELECT student_id, student_id as id, CONCAT(first_name, ' ', last_name) as name 
                                                 FROM students ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($students as $student):
                                // Check if already registered
                                $check = $db->prepare("SELECT 1 FROM event_registrations WHERE event_id = ? AND student_id = ?");
                                $check->execute([$event_id, $student['student_id']]);
                                if ($check->fetch()) continue; // Skip already registered students
                            ?>
                                <option value="<?= $student['student_id'] ?>">
                                    <?= htmlspecialchars($student['name']) ?> (ID: <?= $student['id'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="registration_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="registration_notes" name="registration_notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Register</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteEventModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this event? This action cannot be undone and will permanently delete:</p>
                <ul>
                    <li>All event details</li>
                    <li>All event days and schedules</li>
                    <li>All registration records</li>
                    <li>All attendance records</li>
                </ul>
                <p class="text-danger"><strong>This action cannot be undone!</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="delete-event.php" method="post" class="d-inline">
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i> Delete Event Permanently
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Day Confirmation Modal -->
<div class="modal fade" id="deleteDayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirm Day Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this event day? This will also delete all attendance records for this day.</p>
                <p class="text-danger"><strong>This action cannot be undone!</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteDayForm" method="post" action="api/delete-event-day.php" class="d-inline">
                    <input type="hidden" name="day_id" id="deleteDayId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i> Delete Day
                    </button>
                </form>
$(document).ready(function() {
    // Initialize datepicker for add day modal
    $('#event_date').flatpickr({
        minDate: 'today',
        dateFormat: 'Y-m-d',
        onChange: function(selectedDates, dateStr, instance) {
            if (dateStr) {
                // Auto-fill day name based on selected date
                const date = new Date(dateStr);
                const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                const dayName = dayNames[date.getDay()];
                
                // Only set if the field is empty or contains a day name
                const currentValue = $('#day_name').val();
                if (!currentValue || dayNames.some(day => currentValue.includes(day))) {
                    $('#day_name').val(dayName);
                }
            }
        }
    });
    
    // Handle delete day button click
    $('.delete-day').click(function() {
        const dayId = $(this).data('id');
        $('#deleteDayId').val(dayId);
        $('#deleteDayModal').modal('show');
    });
    
    // Handle form submissions with AJAX
    $('#addDayForm, #addRegistrationForm').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('danger', response.message || 'An error occurred');
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                showAlert('danger', 'An error occurred while processing your request');
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Handle delete day form submission
    $('#deleteDayForm').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...');
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    $('#deleteDayModal').modal('hide');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('danger', response.message || 'Failed to delete day');
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                showAlert('danger', 'An error occurred while processing your request');
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Initialize select2 for student selection
    $('#student_id').select2({
        dropdownParent: $('#addRegistrationModal'),
        placeholder: 'Search for a student...',
        width: '100%',
        allowClear: true
    });
});

// Show alert function
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    // Remove any existing alerts
    $('.alert-dismissible').alert('close');
    
    // Add new alert
    $('.container-fluid').prepend(alertHtml);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        $('.alert-dismissible').alert('close');
    }, 5000);
}
    </script>
    
    <script>
    // Handle delete confirmation
    document.addEventListener('DOMContentLoaded', function() {
        // Delete event confirmation
        const deleteEventBtn = document.getElementById('deleteEventBtn');
        if (deleteEventBtn) {
            deleteEventBtn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        }
        
        // Delete day confirmation
        const deleteDayForms = document.querySelectorAll('.delete-day-form');
        deleteDayForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to delete this event day? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    });
    </script>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</body>
</html>
