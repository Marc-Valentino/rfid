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
        .modal-content {
            background-color: #2d2d2d;
            color: #ffffff;
        }
        .modal-header {
            border-bottom: 1px solid #444;
        }
        .modal-footer {
            border-top: 1px solid #444;
        }
        .form-control, .form-select, .form-control:focus, .form-select:focus {
            background-color: #3d3d3d;
            color: #ffffff;
            border: 1px solid #555;
        }
        .form-control:disabled, .form-control[readonly] {
            background-color: #2d2d2d;
            color: #cccccc;
        }
        .form-label {
            color: #ffffff;
        }
        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
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
        .detail-item {
            padding: 10px;
            border-radius: 5px;
            background-color: rgba(255, 255, 255, 0.03);
            margin-bottom: 10px;
        }
        .detail-item h6 {
            font-size: 0.8rem;
            margin-bottom: 4px;
        }
        .detail-item p {
            margin: 0;
            font-size: 0.95rem;
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
                                        <th>Organizer</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Location</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                        <td><?php echo htmlspecialchars($event['type_name']); ?></td>
                                        <td><?php echo htmlspecialchars($event['organizer_name']); ?></td>
                                        <td><?php echo date('F d, Y h:i A', strtotime($event['start_date'])); ?></td>
                                        <td><?php echo date('F d, Y h:i A', strtotime($event['end_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($event['location'] ?? 'N/A'); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary me-1" onclick="viewEvent(<?php echo $event['event_id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning me-1" onclick="editEvent(<?php echo $event['event_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $event['event_id']; ?>, '<?php echo htmlspecialchars($event['event_name']); ?>')">
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

            <!-- Toast Container -->
            <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
                <div id="toastContainer" class="toast-container">
                    <!-- Toasts will be inserted here -->
                </div>
            </div>

            <!-- View Event Details Modal -->
            <div class="modal fade" id="viewEventModal" tabindex="-1" aria-labelledby="viewEventModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content bg-dark text-light">
                        <div class="modal-header border-secondary">
                            <h5 class="modal-title" id="viewEventModalLabel">Event Details</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h4 id="viewEventName" class="text-primary mb-4"></h4>
                                    <p id="viewDescription" class="mb-4"></p>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="detail-item mb-3">
                                        <h6 class="text-muted mb-1">Event Type</h6>
                                        <p id="viewEventType" class="mb-0"></p>
                                    </div>
                                    
                                    <div class="detail-item mb-3">
                                        <h6 class="text-muted mb-1">Organizer</h6>
                                        <p id="viewOrganizer" class="mb-0"></p>
                                    </div>
                                    
                                    <div class="detail-item mb-3">
                                        <h6 class="text-muted mb-1">Start Date & Time</h6>
                                        <p id="viewStartDate" class="mb-0"></p>
                                    </div>
                                    
                                    <div class="detail-item mb-3">
                                        <h6 class="text-muted mb-1">End Date & Time</h6>
                                        <p id="viewEndDate" class="mb-0"></p>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="detail-item mb-3">
                                        <h6 class="text-muted mb-1">Location</h6>
                                        <p id="viewLocation" class="mb-0"></p>
                                    </div>
                                    
                                    <div class="detail-item mb-3">
                                        <h6 class="text-muted mb-1">Max Participants</h6>
                                        <p id="viewMaxParticipants" class="mb-0"></p>
                                    </div>
                                    
                                    <div class="detail-item mb-3">
                                        <h6 class="text-muted mb-1">Status</h6>
                                        <p id="viewIsActive" class="mb-0"></p>
                                    </div>
                                    
                                    <div class="detail-item mb-3">
                                        <h6 class="text-muted mb-1">Visibility</h6>
                                        <p id="viewIsPublic" class="mb-0"></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <h6 class="text-muted mb-1">Created At</h6>
                                        <p id="viewCreatedAt" class="mb-0"></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <h6 class="text-muted mb-1">Last Updated</h6>
                                        <p id="viewUpdatedAt" class="mb-0"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Event Modal -->
            <div class="modal fade" id="eventDetailsModal" tabindex="-1" aria-labelledby="eventDetailsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="eventDetailsModalLabel">Event Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="eventForm" action="process-event.php" method="POST">
                                <input type="hidden" name="event_id" id="eventId">
                                <input type="hidden" name="action" id="action">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="eventName" class="form-label">Event Name</label>
                                        <input type="text" class="form-control" id="eventName" name="event_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="eventType" class="form-label">Event Type</label>
                                        <select class="form-select" id="eventType" name="type_id" required>
                                            <?php
                                            try {
                                                $sql = "SELECT type_id, type_name FROM event_types ORDER BY type_name";
                                                $stmt = $db->prepare($sql);
                                                $stmt->execute();
                                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                    echo '<option value="' . $row['type_id'] . '">' . htmlspecialchars($row['type_name']) . '</option>';
                                                }
                                            } catch (PDOException $e) {
                                                error_log("Error fetching event types: " . $e->getMessage());
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="startDate" class="form-label">Start Date</label>
                                        <input type="datetime-local" class="form-control" id="startDate" name="start_date" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="endDate" class="form-label">End Date</label>
                                        <input type="datetime-local" class="form-control" id="endDate" name="end_date" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="organizer" class="form-label">Organizer</label>
                                        <select class="form-select" id="organizer" name="organizer_id" required>
                                            <option value="">Select Organizer</option>
                                            <?php
                                            try {
                                                $sql = "SELECT user_id, full_name FROM users WHERE role = 'instructor' OR role = 'admin' ORDER BY full_name";
                                                $stmt = $db->prepare($sql);
                                                $stmt->execute();
                                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                    echo '<option value="' . $row['user_id'] . '">' . 
                                                         htmlspecialchars($row['full_name']) . ' (ID: ' . $row['user_id'] . ')' . 
                                                         '</option>';
                                                }
                                            } catch (PDOException $e) {
                                                error_log("Error fetching organizers: " . $e->getMessage());
                                                echo '<option value="">Error loading organizers</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="location" class="form-label">Location</label>
                                        <input type="text" class="form-control" id="location" name="location" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" form="eventForm" class="btn btn-primary">Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content bg-dark text-light">
                        <div class="modal-header border-secondary">
                            <h5 class="modal-title" id="deleteEventModalLabel">Confirm Delete</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete the event: <strong id="deleteEventName"></strong>?</p>
                            <p class="text-danger"><small>This action cannot be undone.</small></p>
                            <input type="hidden" id="deleteEventId" value="">
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="confirmDelete">
                                <i class="fas fa-trash me-1"></i> Delete Event
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast Container -->
            <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
                <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header bg-success text-white">
                        <strong class="me-auto">Success</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body bg-light text-dark">
                        <?= $_SESSION['success_message'] ?? 'Operation completed successfully!' ?>
                    </div>
                </div>
            </div>

            <!-- Scripts -->
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
            <script>
                // Function to show toast notification
                function showToast(type, message) {
                    const toastContainer = document.getElementById('toastContainer');
                    const toastId = 'toast-' + Date.now();
                    const icon = type === 'success' ? 
                                 'check-circle' : 
                                 type === 'error' ? 'exclamation-circle' : 'info-circle';
                    
                    const toast = document.createElement('div');
                    toast.className = `toast align-items-center text-white bg-${type} border-0 show`;
                    toast.role = 'alert';
                    toast.setAttribute('aria-live', 'assertive');
                    toast.setAttribute('aria-atomic', 'true');
                    toast.id = toastId;
                    
                    toast.innerHTML = `
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-${icon} me-2"></i>${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    `;
                    
                    toastContainer.appendChild(toast);
                    
                    // Auto-remove toast after 5 seconds
                    setTimeout(() => {
                        const bsToast = new bootstrap.Toast(toast);
                        bsToast.hide();
                        toast.addEventListener('hidden.bs.toast', function() {
                            toast.remove();
                        });
                    }, 5000);
                }

                // Initialize modals
                let viewEventModal = null;
                let eventDetailsModal = null;
                let deleteEventModal = null;
                
                document.addEventListener('DOMContentLoaded', function() {
                    viewEventModal = new bootstrap.Modal(document.getElementById('viewEventModal'));
                    eventDetailsModal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
                    deleteEventModal = new bootstrap.Modal(document.getElementById('deleteEventModal'));
                    
                    // Initialize tooltips
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                });

                // Function to populate view modal
                function viewEvent(eventId) {
                    $.ajax({
                        url: 'get-event-details.php',
                        type: 'POST',
                        data: { event_id: eventId },
                        success: function(response) {
                            try {
                                if (response && typeof response === 'object' && response.event_id) {
                                    // Set basic information
                                    $('#viewEventName').text(response.event_name || 'N/A');
                                    $('#viewDescription').text(response.description || 'No description provided');
                                    
                                    // Set event type and organizer
                                    $('#viewEventType').text(response.type_name || 'N/A');
                                    $('#viewOrganizer').text(response.organizer_name || 'N/A');
                                    
                                    // Format and set dates
                                    $('#viewStartDate').text(formatDateTime(response.start_date) || 'N/A');
                                    $('#viewEndDate').text(formatDateTime(response.end_date) || 'N/A');
                                    
                                    // Set other fields
                                    $('#viewLocation').text(response.location || 'Not specified');
                                    $('#viewMaxParticipants').text(
                                        response.max_participants > 0 ? response.max_participants : 'Unlimited'
                                    );
                                    
                                    // Set status and visibility badges
                                    $('#viewIsActive').html(
                                        response.is_active == 1 ? 
                                        '<span class="badge bg-success">Active</span>' : 
                                        '<span class="badge bg-secondary">Inactive</span>'
                                    );
                                    
                                    $('#viewIsPublic').html(
                                        response.is_public == 1 ? 
                                        '<span class="badge bg-info">Public</span>' : 
                                        '<span class="badge bg-warning">Private</span>'
                                    );
                                    
                                    // Set timestamps
                                    $('#viewCreatedAt').text(formatDateTime(response.created_at) || 'N/A');
                                    $('#viewUpdatedAt').text(formatDateTime(response.updated_at) || 'N/A');
                                    
                                    // Show the view modal
                                    if (viewEventModal) {
                                        viewEventModal.show();
                                    }
                                } else if (response && response.error) {
                                    alert('Error: ' + response.error);
                                } else {
                                    alert('Error: Invalid event data received');
                                }
                            } catch (e) {
                                console.error('Error processing response:', e);
                                alert('Error: Failed to process event data');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', error);
                            alert('Error: Failed to fetch event details. Status: ' + status);
                        }
                    });
                }

                // Helper function to format date for display
                function formatDateTime(dateTimeString) {
                    if (!dateTimeString) return 'N/A';
                    const options = { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit', 
                        minute: '2-digit',
                        hour12: true
                    };
                    return new Date(dateTimeString).toLocaleDateString('en-US', options);
                }

                // Function to populate edit modal
                function editEvent(eventId) {
                    // If called from view modal's edit button
                    if (!eventId) {
                        eventId = $('#editFromViewBtn').data('event-id');
                    }
                    
                    $.ajax({
                        url: 'get-event-details.php',
                        type: 'POST',
                        data: { event_id: eventId },
                        success: function(response) {
                            try {
                                if (response && typeof response === 'object' && response.event_id) {
                                    // Set form fields in edit modal
                                    $('#eventId').val(response.event_id);
                                    $('#eventName').val(response.event_name);
                                    $('#eventType').val(response.type_id);
                                    $('#startDate').val(formatDateTimeForInput(response.start_date));
                                    $('#endDate').val(formatDateTimeForInput(response.end_date));
                                    $('#organizer').val(response.organizer_id);
                                    $('#location').val(response.location || '');
                                    $('#description').val(response.description || '');
                                    
                                    // Show the edit modal
                                    if (eventDetailsModal) {
                                        eventDetailsModal.show();
                                    }
                                } else if (response && response.error) {
                                    alert('Error: ' + response.error);
                                } else {
                                    alert('Error: Invalid event data received');
                                }
                            } catch (e) {
                                console.error('Error processing response:', e);
                                alert('Error: Failed to process event data');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', error);
                            alert('Error: Failed to fetch event details. Status: ' + status);
                        }
                    });
                }

                // Helper function to format date for datetime-local input
                function formatDateTimeForInput(dateTimeString) {
                    if (!dateTimeString) return '';
                    const date = new Date(dateTimeString);
                    return date.toISOString().slice(0, 16);
                }

                // Delete confirmation function
                function confirmDelete(eventId, eventName) {
                    $('#deleteEventId').val(eventId);
                    $('#deleteEventName').text(eventName);
                    
                    if (deleteEventModal) {
                        deleteEventModal.show();
                    }
                }

                // Delete confirmation handler
                $(document).on('click', '#confirmDelete', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const eventId = $('#deleteEventId').val();
                    const deleteBtn = $(this);
                    const originalBtnText = deleteBtn.html();
                    
                    if (!eventId) {
                        showToast('error', 'No event selected for deletion');
                        return false;
                    }
                    
                    // Show loading state
                    deleteBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...');
                    
                    // Perform AJAX delete
                    $.ajax({
                        url: 'process-event.php',
                        type: 'POST',
                        data: {
                            event_id: eventId,
                            action: 'delete'
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                showToast('success', response.message || 'Event deleted successfully');
                                // Close modal and refresh the page after a short delay
                                const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteEventModal'));
                                if (deleteModal) {
                                    deleteModal.hide();
                                }
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                showToast('error', response.message || 'Failed to delete event');
                                deleteBtn.prop('disabled', false).html(originalBtnText);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', error);
                            showToast('error', 'An error occurred while deleting the event');
                            deleteBtn.prop('disabled', false).html(originalBtnText);
                        }
                    });
                    
                    return false;
                });

                // Update form submission to use toast notifications
                $(document).ready(function() {
                    $('#eventForm').on('submit', function(e) {
                        e.preventDefault();
                        
                        // Get form data
                        const formData = $(this).serialize();
                        const isEdit = $('#action').val() === 'edit';
                        
                        // Validate required fields
                        const organizerId = $('#organizer').val();
                        if (!organizerId) {
                            showToast('error', 'Please select an organizer');
                            return false;
                        }
                        
                        // Show loading state
                        const submitBtn = $(this).find('button[type="submit"]');
                        const originalBtnText = submitBtn.html();
                        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
                        
                        // Submit form data
                        $.ajax({
                            url: 'process-event.php',
                            type: 'POST',
                            data: formData,
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    showToast('success', response.message || (isEdit ? 'Event updated successfully' : 'Event created successfully'));
                                    // Close modal and refresh the page after a short delay
                                    if (eventDetailsModal) {
                                        eventDetailsModal.hide();
                                    }
                                    setTimeout(() => location.reload(), 1000);
                                } else {
                                    showToast('error', response.message || 'An error occurred while saving the event');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX Error:', error);
                                let errorMessage = 'Failed to save event';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMessage = xhr.responseJSON.message;
                                } else if (xhr.statusText) {
                                    errorMessage += ': ' + xhr.statusText;
                                }
                                showToast('error', errorMessage);
                            },
                            complete: function() {
                                // Restore button state
                                submitBtn.prop('disabled', false).html(originalBtnText);
                            }
                        });
                        
                        return false;
                    });
                    
                    // Reset form when modal is closed
                    $('#eventDetailsModal').on('hidden.bs.modal', function () {
                        $('#eventForm')[0].reset();
                        $('#eventForm :input').prop('disabled', false);
                        $('#eventForm button[type="submit"]').show();
                    });
                });

                $(document).ready(function() {
                    // Show success toast if there's a success message
                    <?php if (isset($_SESSION['success_message'])): ?>
                        var toastEl = document.getElementById('successToast');
                        var toast = new bootstrap.Toast(toastEl);
                        toast.show();
                        
                        // Clear the success message from session
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                });
            </script>
        </div>
    </div>
</div>

</body>
</html>
