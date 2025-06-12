<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

$attendance = new AttendanceSystem();
$attendance->requireLogin();

$page_title = 'Add New Event';
$is_edit = false;
$event = [
    'event_name' => '',
    'description' => '',
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d'),
    'location' => '',
    'type_id' => '',
    'max_participants' => '',
    'registration_deadline' => '',
    'is_public' => 1,
    'contact_email' => '',
    'contact_phone' => ''
];

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get event types for dropdown
try {
    $stmt = $db->prepare("SELECT * FROM event_types WHERE is_active = 1 ORDER BY type_name");
    $stmt->execute();
    $event_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching event types: " . $e->getMessage());
    $event_types = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and process form data
    $event = array_merge($event, $_POST);
    $errors = [];
    
    // Basic validation
    if (empty($event['event_name'])) {
        $errors[] = 'Event name is required';
    }
    if (empty($event['start_date'])) {
        $errors[] = 'Start date is required';
    }
    if (empty($event['end_date'])) {
        $errors[] = 'End date is required';
    }
    if (!empty($event['end_date']) && $event['end_date'] < $event['start_date']) {
        $errors[] = 'End date cannot be before start date';
    }
    if (empty($event['type_id'])) {
        $errors[] = 'Event type is required';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Prepare event data
            $event_data = [
                'event_name' => $event['event_name'],
                'description' => $event['description'],
                'start_date' => $event['start_date'],
                'end_date' => $event['end_date'],
                'location' => $event['location'],
                'type_id' => $event['type_id'],
                'max_participants' => !empty($event['max_participants']) ? (int)$event['max_participants'] : null,
                'registration_deadline' => !empty($event['registration_deadline']) ? $event['registration_deadline'] : null,
                'is_public' => isset($event['is_public']) ? 1 : 0,
                'organizer_id' => $_SESSION['user_id'],
                'contact_email' => $event['contact_email'],
                'contact_phone' => $event['contact_phone']
            ];
            
            // Insert event
            $columns = implode(', ', array_keys($event_data));
            $placeholders = ':' . implode(', :', array_keys($event_data));
            $sql = "INSERT INTO events ($columns) VALUES ($placeholders)";
            $stmt = $db->prepare($sql);
            $stmt->execute($event_data);
            
            $event_id = $db->lastInsertId();
            
            // Create event days if it's a multi-day event
            if ($event['start_date'] !== $event['end_date']) {
                $start = new DateTime($event['start_date']);
                $end = new DateTime($event['end_date']);
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
                $day_number = 1;
                
                foreach ($period as $date) {
                    $day_sql = "INSERT INTO event_days (event_id, day_number, day_name, event_date) 
                               VALUES (:event_id, :day_number, :day_name, :event_date)";
                    $day_stmt = $db->prepare($day_sql);
                    $day_stmt->execute([
                        'event_id' => $event_id,
                        'day_number' => $day_number,
                        'day_name' => $date->format('l'),
                        'event_date' => $date->format('Y-m-d')
                    ]);
                    $day_number++;
                }
            } else {
                // Single day event
                $date = new DateTime($event['start_date']);
                $day_sql = "INSERT INTO event_days (event_id, day_number, day_name, event_date) 
                           VALUES (:event_id, 1, :day_name, :event_date)";
                $day_stmt = $db->prepare($day_sql);
                $day_stmt->execute([
                    'event_id' => $event_id,
                    'day_name' => $date->format('l'),
                    'event_date' => $date->format('Y-m-d')
                ]);
            }
            
            $db->commit();
            $_SESSION['success_message'] = 'Event created successfully!';
            header('Location: events.php');
            exit();
            
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Add New' ?> Event - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Add this CSS to fix dropdown text color */
        .form-select option {
            color: #000000;
        }
        /* Ensure dropdown text is black when opened */
        .form-select:focus option:checked {
            color: #000000;
            background: #e9ecef;
        }
        /* Fix for the dropdown arrow color in dark mode */
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        }
        /* Ensure form controls have proper text color in dark mode */
        .form-control, .form-select {
            color: #212529;
            background-color: #ffffff;
        }
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
        .form-label {
            color: #fff;
        }
        .form-text {
            color: #6c757d !important;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
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
                    <h2><i class="fas fa-calendar-plus me-2"></i><?= $is_edit ? 'Edit' : 'Add New' ?> Event</h2>
                    <div class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('F d, Y - h:i A'); ?>
                    </div>
                </div>
                
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="events.php">Events</a></li>
                    <li class="breadcrumb-item active"><?= $is_edit ? 'Edit' : 'Add New' ?></li>
                </ol>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-calendar-plus me-1"></i> Event Details
        </div>
        <div class="card-body">
            <form method="post" id="eventForm">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="event_name" class="form-label">Event Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="event_name" name="event_name" 
                               value="<?= htmlspecialchars($event['event_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="type_id" class="form-label">Event Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="type_id" name="type_id" required>
                            <option value="">-- Select Type --</option>
                            <?php foreach ($event_types as $type): ?>
                                <option value="<?= $type['type_id'] ?>" 
                                    <?= $event['type_id'] == $type['type_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['type_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?= $event['start_date'] ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?= $event['end_date'] ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location" 
                               value="<?= htmlspecialchars($event['location']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="max_participants" class="form-label">Maximum Participants</label>
                        <input type="number" class="form-control" id="max_participants" name="max_participants" 
                               min="1" value="<?= $event['max_participants'] ?>">
                        <div class="form-text">Leave blank for unlimited participants</div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="registration_deadline" class="form-label">Registration Deadline</label>
                        <input type="datetime-local" class="form-control" id="registration_deadline" 
                               name="registration_deadline" value="<?= $event['registration_deadline'] ?>">
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-4 pt-2">
                            <input class="form-check-input" type="checkbox" id="is_public" name="is_public" 
                                   value="1" <?= $event['is_public'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_public">
                                Public Event (Visible to all users)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="contact_email" class="form-label">Contact Email</label>
                        <input type="email" class="form-control" id="contact_email" name="contact_email" 
                               value="<?= htmlspecialchars($event['contact_email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="contact_phone" class="form-label">Contact Phone</label>
                        <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                               value="<?= htmlspecialchars($event['contact_phone']) ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($event['description']) ?></textarea>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="events.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Events
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Event
                    </button>
                </div>
            </form>
        </div>
    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery Validation Plugin -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.3/dist/jquery.validate.min.js"></script>
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Add custom validation method for date comparison
    $.validator.addMethod('greaterThanOrEqual', function(value, element, param) {
        if (!value || !$(param).val()) return true;
        var start = $(param).val();
        return new Date(value) >= new Date(start);
    });
    
    
$(document).ready(function() {
    // Initialize datepickers
    $('#start_date, #end_date, #registration_deadline').flatpickr({
        enableTime: false,
        dateFormat: 'Y-m-d',
        minDate: 'today'
    });
    
    // Set minimum end date based on start date
    $('#start_date').on('change', function() {
        $('#end_date').attr('min', $(this).val());
        if (new Date($('#end_date').val()) < new Date($(this).val())) {
            $('#end_date').val($(this).val());
        }
    });
    
    // Form validation
    $('#eventForm').validate({
        rules: {
            event_name: 'required',
            start_date: 'required',
            end_date: {
                required: true,
                greaterThanOrEqual: '#start_date'
            },
            type_id: 'required',
            contact_email: {
                email: true
            }
        },
        messages: {
            end_date: {
                greaterThanOrEqual: 'End date must be on or after start date'
            }
        },
        errorElement: 'div',
        errorPlacement: function(error, element) {
            error.addClass('invalid-feedback');
            element.closest('.form-group').append(error);
        },
        highlight: function(element, errorClass, validClass) {
            $(element).addClass('is-invalid');
        },
        unhighlight: function(element, errorClass, validClass) {
            $(element).removeClass('is-invalid');
        }
    });
    });
    
    // Handle form submission
    document.addEventListener('DOMContentLoaded', function() {
        // Add event listener for form submission
        const form = document.getElementById('eventForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Additional client-side validation can be added here
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
                }
                // If validation passes, the form will submit
                // If not, the jQuery Validation plugin will handle showing errors
            });
        }
    });
</script>

</body>
</html>
