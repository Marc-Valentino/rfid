<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Initialize AttendanceSystem
$attendance = new AttendanceSystem();
$attendance->requireLogin();

$page_title = 'Manage Events';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get events with their details
try {
    $sql = "SELECT e.*, et.type_name, u.full_name as organizer_name, 
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as registrant_count
            FROM events e
            LEFT JOIN event_types et ON e.type_id = et.type_id
            LEFT JOIN users u ON e.organizer_id = u.user_id
            ORDER BY e.start_date DESC, e.end_date DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $events = [];
    error_log("Error fetching events: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?php echo APP_NAME; ?></title>
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
            color: #fff;
            background-color: rgba(0, 0, 0, 0.2);
            border-color: #444;
        }
        .table td {
            color: #f8f9fa;
            border-color: #444;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .table-striped > tbody > tr:nth-of-type(odd) > * {
            --bs-table-accent-bg: rgba(255, 255, 255, 0.05);
            color: #f8f9fa;
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
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
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
                        <i class="fas fa-book me-2"></i>Subjects
                    </a>
                    <a class="nav-link" href="departments.php">
                        <i class="fas fa-building me-2"></i>Departments
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
                    <h2><i class="fas fa-calendar me-2"></i>Manage Events</h2>
                    <div class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('F d, Y - h:i A'); ?>
                    </div>
                </div>
                
                

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="fas fa-calendar-alt me-1"></i> Events List</div>
            <a href="add-event.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add New Event
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="eventsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Registrants</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): 
                            $start = new DateTime($event['start_date']);
                            $end = new DateTime($event['end_date']);
                            $now = new DateTime();
                            $status = '';
                            $badge = '';
                            
                            if ($now > $end) {
                                $status = 'Completed';
                                $badge = 'bg-secondary';
                            } elseif ($now >= $start && $now <= $end) {
                                $status = 'Ongoing';
                                $badge = 'bg-success';
                            } else {
                                $status = 'Upcoming';
                                $badge = 'bg-primary';
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($event['event_name']) ?></td>
                            <td><?= htmlspecialchars($event['type_name']) ?></td>
                            <td>
                                <?= $start->format('M j, Y') ?>
                                <?= $end->format('M j, Y') != $start->format('M j, Y') ? ' - ' . $end->format('M j, Y') : '' ?>
                            </td>
                            <td><?= htmlspecialchars($event['location'] ?? 'N/A') ?></td>
                            <td class="text-center">
                                <span class="badge bg-primary"><?= $event['registrant_count'] ?></span>
                            </td>
                            <td><span class="badge <?= $badge ?>"><?= $status ?></span></td>
                            <td class="text-nowrap">
                                <a href="view-event.php?id=<?= $event['event_id'] ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit-event.php?id=<?= $event['event_id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-danger delete-event" data-id="<?= $event['event_id'] ?>" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteEventModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this event? This action cannot be undone and will also delete all associated registrations and attendance records.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete Event</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Delete event confirmation
    $('.delete-event').on('click', function(e) {
        e.preventDefault();
        const eventId = $(this).data('id');
        const eventName = $(this).data('name');
        
        $('#deleteEventId').val(eventId);
        $('#eventName').text(eventName);
        $('#deleteEventModal').modal('show');
    });
    
    // Handle delete button click
    $('#confirmDelete').off('click').on('click', function() {
        $.ajax({
            url: '../api/events.php',
            type: 'DELETE',
            data: { id: eventId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('danger', response.message || 'Failed to delete event');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while processing your request');
            },
            complete: function() {
                $('#deleteEventModal').modal('hide');
            }
            });
        });
    });
});
</script>
