<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Initialize AttendanceSystem
$attendance = new AttendanceSystem();

// Establish database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in
$attendance->requireLogin();

// Get page title from config 
$page_title = "RFID Scanner";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Scanner - <?php echo APP_NAME; ?> - Event Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

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
            padding: 8px 12px;
            font-size: 0.85rem;
            font-weight: 500;
            border-radius: 20px;
        }
        
        .bg-success {
            background-color: #28a745 !important;
        }
        
        .bg-info {
            background-color: #17a2b8 !important;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #0056b3, #004085);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }
        .scanner-card {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(0, 123, 255, 0.3);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            min-height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .scanner-icon {
            font-size: 4rem;
            color: #007bff;
            margin-bottom: 20px;
        }
        .alert {
            border-radius: 15px;
            border: none;
        }
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
                
        .attendance-mode.active {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        
        .btn-group .attendance-mode {
            padding: 10px 20px;
            font-weight: bold;
        }
        
        /* Remove yellow indicator */
        .type-indicator {
            display: none;
        }
        
        /* Toast Notification Styles */
        .toastify {
            padding: 12px 20px;
            color: #ffffff;
            font-size: 14px;
            border-radius: 8px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .toastify.success {
            background: linear-gradient(to right, #28a745, #20c997);
        }
        
        .toastify.error {
            background: linear-gradient(to right, #dc3545, #c82333);
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
                        <a class="nav-link active text-light" href="scan-rfid.php">
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
                    <h2><i class="fas fa-wifi me-2"></i>RFID Scanner</h2>
                    <div class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('F d, Y - h:i A'); ?>
                    </div>
                </div>

                <!-- Scanner Interface -->
                <div class="row justify-content-center mb-4">
                    <div class="col-md-8">
                        
                        </div>
                        
                        <!-- Scanner Card -->
                        <div class="scanner-card">
                            <!-- Add Attendance Mode Buttons -->
                            <div class="btn-group mb-4" role="group" aria-label="Attendance Mode">
                                <button type="button" class="btn btn-success attendance-mode active" data-mode="Time In">
                                    <i class="fas fa-sign-in-alt me-2"></i>Time In
                                </button>
                                <button type="button" class="btn btn-danger attendance-mode" data-mode="Time Out">
                                    <i class="fas fa-sign-out-alt me-2"></i>Time Out
                                </button>
                            </div>
                            
                            <div id="scanStatus">
                                <i class="fas fa-spinner fa-spin scanner-icon text-primary"></i>
                                <h4 class="text-light mb-4">Initializing RFID Scanner...</h4>
                                <p class="text-muted">The system will automatically detect when an RFID reader is connected.</p>
                                <div class="spinner-border text-primary mt-3" role="status"></div>
                            </div>
                            <div id="scanResult" class="d-none">
                                <!-- Scan results will appear here -->
                            </div>
                        </div>
                        
                        <!-- Attendance Records Table -->
                        <div class="card mt-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-clipboard-list me-2"></i>Attendance Records
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
                                    <div class="input-group" style="width: 250px;">
                                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                        <input type="date" id="attendanceDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" />
                                    </div>
                                    <div class="input-group" style="width: 350px;">
                                        <span class="input-group-text"><i class="fas fa-calendar-day"></i></span>
                                        <select class="form-select" id="eventSelect">
                                            <option value="">-- All Events --</option>
                                            <?php
                                            // Fetch active events
                                            $eventsQuery = $pdo->query("SELECT event_id, event_name, start_date, end_date 
                                                                    FROM events 
                                                                    WHERE is_active = 1 AND end_date >= CURDATE() 
                                                                    ORDER BY start_date");
                                            while ($event = $eventsQuery->fetch(PDO::FETCH_ASSOC)) {
                                                $startDate = date('M d', strtotime($event['start_date']));
                                                $endDate = date('M d, Y', strtotime($event['end_date']));
                                                echo "<option value='{$event['event_id']}'>
                                                        {$event['event_name']} ($startDate - $endDate)
                                                    </option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="d-flex ms-auto gap-2">
                                        <button id="refreshBtn" class="btn btn-outline-light" title="Refresh">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        <button id="exportBtn" class="btn btn-outline-light" title="Export to Excel">
                                            <i class="fas fa-file-export"></i>
                                        </button>
                                        <div class="btn-group" role="group">
                                            <button id="prevPage" class="btn btn-outline-light" title="Previous Page">
                                                <i class="fas fa-chevron-left"></i>
                                            </button>
                                            <button id="nextPage" class="btn btn-outline-light" title="Next Page">
                                                <i class="fas fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-dark">
                                            <tr>
                                                <th width="4%" class="text-nowrap">#</th>
                                                <th width="10%" class="text-nowrap">Student ID</th>
                                                <th width="18%" class="text-nowrap">Name</th>
                                                <th width="12%" class="text-nowrap">Course</th>
                                                <th width="10%" class="text-nowrap">RFID UID</th>
                                                <th width="10%" class="text-nowrap">Photo</th>
                                                <th width="12%" class="text-nowrap">Time In</th>
                                                <th width="12%" class="text-nowrap">Time Out</th>
                                                <th width="12%" class="text-nowrap">Event</th>
                                                <th width="10%" class="text-nowrap">Status</th>
                                                <th width="5%" class="text-center text-nowrap">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="attendanceRecords" class="table-light text-dark">
                                            <!-- Attendance records will be loaded here via JavaScript -->
                                            
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="9" class="text-end">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div class="text-muted small">
                                                            Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalRecords">0</span> records
                                                        </div>
                                                        <div class="btn-group">
                                                            <select id="pageSize" class="form-select form-select-sm" style="width: auto;">
                                                                <option value="10">10 per page</option>
                                                                <option value="25">25 per page</option>
                                                                <option value="50">50 per page</option>
                                                                <option value="100">100 per page</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Update the JavaScript section at the bottom of the file -->
                        <script>
                            // Function to check if RFID reader is connected and start scanning
                            function checkRFIDReaderAndScan() {
                                const scanStatus = document.getElementById('scanStatus');
                                const scanResult = document.getElementById('scanResult');

                                // Show scanning status
                                scanStatus.innerHTML = `
                                    <i class="fas fa-wifi scanner-icon text-primary"></i>
                                    <h4 class="text-light mb-4">RFID Scanner Ready</h4>
                                    <p class="text-muted">Scan an RFID card to record attendance</p>
                                    <div class="spinner-border text-primary mt-3" role="status">
                                        <span class="visually-hidden">Scanning...</span>
                                    </div>
                                `;
                                scanResult.classList.add('d-none');

                                // Start the scanning process
                                startRFIDScan();
                            }

                            // Function to start RFID scanning
                            function startRFIDScan() {
                                const scanStatus = document.getElementById('scanStatus');
                                const scanResult = document.getElementById('scanResult');

                                // Reset UI
                                scanStatus.innerHTML = '<i class="fas fa-spinner fa-spin scanner-icon text-primary"></i>' +
                                              '<h4 class="text-light mb-4">Scanning in Progress...</h4>' +
                                              '<p class="text-muted">Please place the RFID card on the reader.</p>' +
                                              '<div class="spinner-border text-primary mt-3" role="status"></div>';
                                scanResult.innerHTML = '';
                                scanResult.classList.add('d-none');

                                // Initialize RFID scanning with callback
                                if (typeof window.rfidReader !== 'undefined' && typeof window.rfidReader.initializeRFIDScan === 'function') {
                                    window.rfidReader.initializeRFIDScan('attendance', function(result) {
                                        console.log('RFID Scan Result:', result); // Debug logging
                                        handleScanResult(result);
                                    });
                                } else {
                                    // For testing purposes, you can manually input an RFID UID
                                    const manualUid = prompt('Enter RFID UID for testing (or cancel for simulation):', '');
                                    
                                    if (manualUid) {
                                        // Use the manually entered UID to query the API directly
                                        fetch('../api/rfid-scan.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json'
                                            },
                                            body: JSON.stringify({
                                                rfid_uid: manualUid,
                                                attendance_type: 'Time In'
                                            })
                                        })
                                        .then(response => response.json())
                                        .then(data => {
                                            console.log('API Response:', data);
                                            handleScanResult(data);
                                            // Refresh attendance records after scan
                                            loadAttendanceData();
                                        })
                                        .catch(error => {
                                            console.error('Error:', error);
                                            handleScanResult({
                                                success: false,
                                                message: 'Error connecting to server',
                                                error: error.message
                                            });
                                        });
                                    } else {
                                        // Use the rfid-reader.js simulation
                                        if (typeof window.rfidReader !== 'undefined' && typeof window.rfidReader.simulateRFIDScan === 'function') {
                                            window.rfidReader.simulateRFIDScan(scanStatus, scanResult, function(result) {
                                                handleScanResult(result);
                                                // Refresh attendance records after scan
                                                loadAttendanceData();
                                            });
                                        } else {
                                            // Fallback to local simulation if rfid-reader.js is not available
                                            setTimeout(() => {
                                                // Simulate different scenarios
                                                const scenarios = [
                                                    // Registered student
                                                    {
                                                        success: true,
                                                        message: 'RFID scan completed successfully',
                                                        data: {
                                                            rfid_uid: Math.random() < 0.5 ? '3870578740' : '3871243332', // Randomly select from known UIDs
                                                            student_name: 'Test Student',
                                                            student_number: '2023001',
                                                            attendance_type: 'Time In'
                                                        }
                                                    },
                                                    // Unregistered card
                                                    {
                                                        success: false,
                                                        message: 'Unregistered RFID card',
                                                        rfid_uid: Math.floor(Math.random() * 9000000000) + 1000000000, // Random UID
                                                        status: 'unregistered'
                                                    }
                                                ];
                                                
                                                // Randomly select a scenario
                                                const scenario = scenarios[Math.floor(Math.random() * scenarios.length)];
                                                handleScanResult(scenario);
                                                // Refresh attendance records after scan
                                                loadAttendanceData();
                                            }, 3000);
                                        }
                                    }
                                }
                            }

                            // Function to handle scan results
                            function handleScanResult(result) {
                                const scanStatus = document.getElementById('scanStatus');
                                const scanResult = document.getElementById('scanResult');

                                // Update scan result display
                                scanResult.classList.remove('d-none');

                                if (result.status === 'unregistered') {
                                    // Show registration form in modal for unregistered card
                                    document.getElementById('rfidUidDisplay').textContent = `RFID UID: ${result.rfid_uid}`;

                                    const formHtml = `
                                        <div class="alert alert-info mb-3">
                                            <h5><i class="fas fa-info-circle me-2"></i>Unregistered RFID Card</h5>
                                            <p>This card is not registered in the system. Please complete the registration form below.</p>
                                        </div>
                                        <div class="card bg-dark">
                                            <div class="card-header bg-primary text-white">
                                                <h5 class="mb-0">Student Registration Form</h5>
                                            </div>
                                            <div class="card-body">
                                                <form id="rfidRegistrationForm" enctype="multipart/form-data">
                                                    <input type="hidden" name="rfid_uid" value="${result.rfid_uid}">

                                                    <div class="row mb-3">
                                                        <div class="col-md-6 mb-3 mb-md-0">
                                                            <label for="student_number" class="form-label">Student ID/Number *</label>
                                                            <input type="text" class="form-control" id="student_number" name="student_number" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="year_level" class="form-label">Year Level</label>
                                                            <select class="form-select" id="year_level" name="year_level">
                                                                <option value="">Select Year Level</option>
                                                                <option value="1st Year">1st Year</option>
                                                                <option value="2nd Year">2nd Year</option>
                                                                <option value="3rd Year">3rd Year</option>
                                                                <option value="4th Year">4th Year</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6 mb-3 mb-md-0">
                                                            <label for="first_name" class="form-label">First Name *</label>
                                                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="last_name" class="form-label">Last Name *</label>
                                                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="profile_image" class="form-label">Profile Picture</label>
                                                        <div class="input-group">
                                                            <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*" onchange="previewImage(this)">
                                                        </div>
                                                        <small class="text-muted">Max size: 2MB. Allowed formats: JPG, JPEG, PNG, GIF</small>
                                                        <div class="mt-2 text-center" id="imagePreviewContainer" style="display: none;">
                                                            <img id="imagePreview" src="#" alt="Preview" class="img-thumbnail" style="max-height: 200px;">
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6 mb-3 mb-md-0">
                                                            <label for="email" class="form-label">Email</label>
                                                            <input type="email" class="form-control" id="email" name="email">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="phone" class="form-label">Phone</label>
                                                            <input type="text" class="form-control" id="phone" name="phone">
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6 mb-3 mb-md-0">
                                                            <label for="department_id" class="form-label">Department</label>
                                                            <select class="form-select" id="department_id" name="department_id">
                                                                <option value="">Select Department</option>
                                                                <!-- Departments will be loaded dynamically -->
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="course_id" class="form-label">Course</label>
                                                            <select class="form-select" id="course_id" name="course_id">
                                                                <option value="">Select Course</option>
                                                                <!-- Courses will be loaded dynamically -->
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-12">
                                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                                <button type="button" class="btn btn-secondary me-md-2" onclick="cancelRegistration()">
                                                                    <i class="fas fa-times me-1"></i> Cancel
                                                                </button>
                                                                <button type="submit" class="btn btn-primary">
                                                                    <i class="fas fa-save me-1"></i> Save Student
                                                                </button>
                                                            </div>
                                                            <div class="mt-3 text-muted small">
                                                                <i class="fas fa-info-circle me-1"></i>
                                                                After saving, you can scan the card again to record attendance.
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                                <div id="registrationMessage" class="mt-3 d-none"></div>
                                            </div>
                                        </div>
                                    `;
                                    
                                    // Set the form HTML and show modal
                                    document.getElementById('scanResult').innerHTML = formHtml;
                                    registrationModal.show();
                                    
                                    // Load departments and courses
                                    loadDepartmentsAndCourses();
                                    
                                    // Add form submit event listener
                                    const form = document.getElementById('rfidRegistrationForm');
                                    if (form) {
                                        form.addEventListener('submit', function(e) {
                                            e.preventDefault();
                                            registerStudent(this);
                                        });
                                    }
                                } else {
                                    // Regular scan result (success or error)
                                    scanResult.innerHTML = `
                                        <div class="alert alert-${result.success ? 'success' : 'danger'} mb-3">
                                            <h4 class="alert-heading">
                                                <i class="fas fa-${result.success ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                                                ${result.success ? 'Scan Successful!' : 'Scan Failed!'}
                                            </h4>
                                            <p class="mb-2">${result.message}</p>
                                            ${result.data && result.data.student_name ? `<p class="mb-1"><strong>Student:</strong> ${result.data.student_name}</p>` : ''}
                                            ${result.data && result.data.student_number ? `<p class="mb-1"><strong>ID:</strong> ${result.data.student_number}</p>` : ''}
                                            ${result.data && result.data.attendance_type ? `<p class="mb-0"><strong>Type:</strong> ${result.data.attendance_type}</p>` : ''}
                                            ${result.rfid_uid || (result.data && result.data.rfid_uid) ? `<p class="mb-0"><strong>RFID UID:</strong> ${result.rfid_uid || result.data.rfid_uid}</p>` : ''}
                                        </div>
                                    `;
                                    
                                    // Reset scan status and show auto-restart message
                                    scanStatus.innerHTML = `
                                        <i class="fas fa-wifi scanner-icon"></i>
                                        <h4 class="text-light mb-4">RFID Scanner Ready</h4>
                                        <p class="text-muted mb-4">The scanner will automatically detect the next card</p>
                                    `;
                                    
                                    // Record attendance in the database
                                    if (result.success || result.rfid_uid) {
                                        // Extract the RFID UID from the result
                                        const rfidUid = result.rfid_uid || 
                                                      (result.data && result.data.rfid_uid ? result.data.rfid_uid : null);
                                        
                                        if (rfidUid) {
                                            console.log('Sending attendance data with RFID UID:', rfidUid);
                                            
                                            // Send attendance data to the server
                                            fetch('../api/rfid-scan.php', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json'
                                                },
                                                body: JSON.stringify({
                                                    rfid_uid: rfidUid.toString(),
                                                    attendance_type: document.querySelector('.attendance-mode.active').dataset.mode // Get selected mode
                                                })
                                            })
                                            .then(response => response.json())
                                            .then(data => {
                                                console.log('Attendance recorded:', data);
                                                if (!data.success) {
                                                    console.error('Error recording attendance:', data.message);
                                                }
                                                
                                                // Refresh the attendance records table
                                                loadAttendanceData();
                                                
                                                // Automatically restart scanning after a successful scan (after 3 seconds)
                                                setTimeout(() => {
                                                    checkRFIDReaderAndScan();
                                                }, 3000);
                                            })
                                            .catch(error => {
                                                console.error('Error recording attendance:', error);
                                                // Still restart scanning even if there was an error
                                                setTimeout(() => {
                                                    checkRFIDReaderAndScan();
                                                }, 3000);
                                            });
                                        } else {
                                            console.error('No RFID UID found in scan result');
                                            // Restart scanning even if no RFID UID was found
                                            setTimeout(() => {
                                                checkRFIDReaderAndScan();
                                            }, 3000);
                                        }
                                    } else {
                                        // Restart scanning even if the scan wasn't successful
                                        setTimeout(() => {
                                            checkRFIDReaderAndScan();
                                        }, 3000);
                                    }
                                }
                                
                                // Update recent scans table
                                updateRecentScans(result);
                            }
                            
                            // Function to load departments and courses
                            function loadDepartmentsAndCourses() {
                                // Load departments
                                fetch('../api/get-departments.php')
                                    .then(response => response.json())
                                    .then(data => {
                                        const deptSelect = document.getElementById('department_id');
                                        if (deptSelect) {
                                            deptSelect.innerHTML = '<option value="">Select Department</option>';
                                            data.forEach(dept => {
                                                deptSelect.innerHTML += `<option value="${dept.department_id}">${dept.department_name}</option>`;
                                            });
                                        }
                                    });
                                    
                                // Load courses
                                fetch('../api/get-courses.php')
                                    .then(response => response.json())
                                    .then(data => {
                                        const courseSelect = document.getElementById('course_id');
                                        if (courseSelect) {
                                            courseSelect.innerHTML = '<option value="">Select Course</option>';
                                            data.forEach(course => {
                                                courseSelect.innerHTML += `<option value="${course.course_id}">${course.course_code} - ${course.course_name}</option>`;
                                            });
                                        }
                                    });
                            }
                            
                            // Function to register a student
                            function registerStudent(form) {
                                const formData = new FormData(form);
                                const registrationData = new FormData();
                                
                                // Convert FormData to object and add to registrationData
                                const formDataObj = {};
                                formData.forEach((value, key) => {
                                    formDataObj[key] = value;
                                });
                                
                                // Add all form fields to FormData
                                for (const key in formDataObj) {
                                    registrationData.append(key, formDataObj[key]);
                                }
                                
                                // Show loading state
                                const submitBtn = form.querySelector('button[type="submit"]');
                                const originalBtnText = submitBtn.innerHTML;
                                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Registering...';
                                submitBtn.disabled = true;

                                // Show success flag
                                let registrationSuccessful = false;

                                // Validate image file if present
                                const fileInput = document.getElementById('profile_image');
                                if (fileInput.files.length > 0) {
                                    const file = fileInput.files[0];
                                    const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                                    const maxSize = 2 * 1024 * 1024; // 2MB
                                    
                                    if (!validTypes.includes(file.type)) {
                                        showRegistrationMessage('Invalid file type. Please upload a JPG, PNG, or GIF image.', 'danger');
                                        submitBtn.innerHTML = originalBtnText;
                                        submitBtn.disabled = false;
                                        return false;
                                    }
                                    
                                    if (file.size > maxSize) {
                                        showRegistrationMessage('File is too large. Maximum size is 2MB.', 'danger');
                                        submitBtn.innerHTML = originalBtnText;
                                        submitBtn.disabled = false;
                                        return false;
                                    }
                                    
                                    // Add the file to the form data
                                    registrationData.append('profile_image', file);
                                }

                                // Send registration request with FormData
                                fetch('../api/register-rfid.php', {
                                    method: 'POST',
                                    body: registrationData
                                })
                                .then(async response => {
                                    const responseContent = await response.text();
                                    
                                    // First try to parse as JSON
                                    try {
                                        const data = JSON.parse(responseContent);
                                        if (data.success) {
                                            registrationSuccessful = true;
                                        }
                                        return data;
                                    } catch (e) {
                                        // If not JSON, check if the response looks successful
                                        if (response.ok) {
                                            registrationSuccessful = true;
                                            return {
                                                success: true,
                                                message: "Registration successful",
                                                data: {
                                                    student_name: `${registrationData.first_name || ''} ${registrationData.last_name || ''}`.trim()
                                                }
                                            };
                                        }
                                        throw new Error(responseContent || 'Registration failed');
                                    }
                                })
                                .then(data => {
                                    // Show success message
                                    Toastify({
                                        text: `✅ Registration Successful!\nStudent: ${data.data?.student_name || `${registrationData.first_name || ''} ${registrationData.last_name || ''}`.trim()}`,
                                        duration: 5000,
                                        gravity: "top",
                                        position: "right",
                                        className: "success",
                                        close: true
                                    }).showToast();

                                    // Reset scanner UI
                                    const scanStatus = document.getElementById('scanStatus');
                                    const scanResult = document.getElementById('scanResult');
                                    
                                    // Show success message in the scanner UI
                                    scanStatus.innerHTML = `
                                        <i class="fas fa-check-circle scanner-icon text-success"></i>
                                        <h4 class="text-light mb-4">Registration Successful!</h4>
                                        <p class="text-muted">${data.message || 'Student registered successfully'}</p>
                                        <p class="text-light">${data.data?.student_name || ''}</p>
                                    `;
                                    
                                    // Clear the registration form
                                    // Clear the registration form and hide it
                                    scanResult.classList.add('d-none');
                                    form.reset();

                                    // Play success sound if available
                                    if (typeof playSound === 'function') {
                                        playSound('success');
                                    }

                                    // Refresh the page after a short delay to return to scanning mode
                                    setTimeout(() => {
                                        window.location.reload();
                                    }, 2000);
                                })
                                .catch(error => {
                                    console.error('Registration error:', error);
                                    Toastify({
                                        text: `❌ Registration Failed!\nPlease try again`,
                                        duration: 4000,
                                        gravity: "top",
                                        position: "right",
                                        className: "error",
                                        close: true
                                    }).showToast();
                                    
                                    // Play error sound if available
                                    if (typeof playSound === 'function') {
                                        playSound('error');
                                    }
                                })
                                .finally(() => {
                                    // Reset button state
                                    submitBtn.innerHTML = originalBtnText;
                                    submitBtn.disabled = false;
                                });
    const registrationData = new FormData();
    
    // Convert FormData to object and add to registrationData
    const formDataObj = {};
    formData.forEach((value, key) => {
        formDataObj[key] = value;
    });
    
    // Add all form fields to FormData
    for (const key in formDataObj) {
        registrationData.append(key, formDataObj[key]);
    }
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Registering...';
    submitBtn.disabled = true;

    // Show success flag
    let registrationSuccessful = false;

    // Validate image file if present
    const fileInput = document.getElementById('profile_image');
    if (fileInput.files.length > 0) {
        const file = fileInput.files[0];
        const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
        const maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!validTypes.includes(file.type)) {
            showRegistrationMessage('Invalid file type. Please upload a JPG, PNG, or GIF image.', 'danger');
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
            return false;
        }
        
        if (file.size > maxSize) {
            showRegistrationMessage('File is too large. Maximum size is 2MB.', 'danger');
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
            return false;
        }
        
        // Add the file to the form data
        registrationData.append('profile_image', file);
    }

    // Send registration request with FormData
    fetch('../api/register-rfid.php', {
        method: 'POST',
        body: registrationData
    })
    .then(async response => {
        const responseContent = await response.text();
        
        // First try to parse as JSON
        try {
            const data = JSON.parse(responseContent);
            if (data.success) {
                registrationSuccessful = true;
            }
            return data;
        } catch (e) {
            // If not JSON, check if the response looks successful
            if (response.ok) {
                registrationSuccessful = true;
                return {
                    success: true,
                    message: "Registration successful",
                    data: {
                        student_name: `${registrationData.first_name || ''} ${registrationData.last_name || ''}`.trim()
                    }
                };
            }
            throw new Error(responseContent || 'Registration failed');
        }
    })
    .then(data => {
        // Show success message
        Toastify({
            text: `✅ Registration Successful!\nStudent: ${data.data?.student_name || `${registrationData.first_name || ''} ${registrationData.last_name || ''}`.trim()}`,
            duration: 5000,
            gravity: "top",
            position: "right",
            className: "success",
            close: true
        }).showToast();

        // Reset scanner UI
        const scanStatus = document.getElementById('scanStatus');
        const scanResult = document.getElementById('scanResult');
        
        // Show success message in the scanner UI
        scanStatus.innerHTML = `
            <i class="fas fa-check-circle scanner-icon text-success"></i>
            <h4 class="text-light mb-4">Registration Successful!</h4>
            <p class="text-muted">${data.message || 'Student registered successfully'}</p>
            <p class="text-light">${data.data?.student_name || ''}</p>
        `;
        
        // Clear the registration form
        // Clear the registration form and hide it
        scanResult.classList.add('d-none');
        form.reset();

        // Play success sound if available
        if (typeof playSound === 'function') {
            playSound('success');
        }

        // Refresh the page after a short delay to return to scanning mode
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    })
    .catch(error => {
        console.error('Registration error:', error);
        Toastify({
            text: `❌ Registration Failed!\nPlease try again`,
            duration: 4000,
            gravity: "top",
            position: "right",
            className: "error",
            close: true
        }).showToast();
        
        // Play error sound if available
        if (typeof playSound === 'function') {
            playSound('error');
        }
    })
    .finally(() => {
        // Reset button state
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
    });

    return false; // Prevent form submission
}
    
        // Function to cancel registration
        function cancelRegistration() {
            // Reset the scan UI
            const scanStatus = document.getElementById('scanStatus');
            const scanResult = document.getElementById('scanResult');
            
            scanStatus.innerHTML = '<i class="fas fa-wifi scanner-icon"></i>' + 
                                  '<h4 class="text-light mb-4">RFID Scanner Ready</h4>' + 
                                  '<p class="text-muted mb-4">Click \'Start Scanning\' to begin reading RFID cards</p>' + 
                                  '<button class="btn btn-primary btn-lg" onclick="startRFIDScan()">' + 
                                  '<i class="fas fa-play me-2"></i>Start Scanning</button>';
            
            scanResult.innerHTML = '';
            scanResult.classList.add('d-none');
        }
    
        // Function to update recent scans table
        function updateRecentScans(scan) {
            // After updating the recent scans, refresh the attendance table
            loadAttendanceData();
        }
        
        // Variables for pagination
        let currentPage = 0;
        let pageSize = 10;
        let totalRecords = 0;
        
        // Function to load attendance data with photo and event information
        function loadAttendanceData() {
        const attendanceDate = document.getElementById('attendanceDate').value;
        const eventId = document.getElementById('eventSelect').value;
        const tbody = document.getElementById('attendanceRecords');
        const offset = currentPage * pageSize;
        
        // Show loading indicator
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 mb-0">Loading attendance records...</p>
                </td>
            </tr>`;
        
        // Build API URL with parameters
        let apiUrl = `../api/get-attendance.php?date=${attendanceDate}&limit=${pageSize}&offset=${offset}`;
        if (eventId) {
            apiUrl += `&event_id=${eventId}`;
        }
        
        // Fetch attendance data from API
        fetch(apiUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                tbody.innerHTML = '';
                
                if (data.success && data.data && data.data.length > 0) {
                    data.data.forEach((record, index) => {
                        const row = document.createElement('tr');
                        const scanTime = record.scan_time ? new Date(record.scan_time).toLocaleTimeString() : 'N/A';
                        const timeOut = record.time_out ? new Date(record.time_out).toLocaleTimeString() : '-';
                        const studentName = record.first_name || record.last_name 
                            ? `${record.first_name || ''} ${record.last_name || ''}`.trim() 
                            : 'Unknown';
                        
                        // Determine badge color based on status
                        let statusClass = 'bg-secondary';
                        let statusText = 'Pending';
                        
                        if (record.status === 'present') {
                            statusClass = 'bg-success';
                            statusText = 'Present';
                        } else if (record.status === 'absent') {
                            statusClass = 'bg-danger';
                            statusText = 'Absent';
                        } else if (record.status === 'late') {
                            statusClass = 'bg-warning';
                            statusText = 'Late';
                        }
                        
                        // Create photo URL - adjust the path as per your application
                        const photoUrl = record.photo_path 
                            ? `../${record.photo_path}` 
                            : '../assets/img/default-avatar.png';
                            
                        // Create event name or use default
                        const eventName = record.event_name || 'Regular Attendance';
                        
                        row.innerHTML = `
                            <td>${offset + index + 1}</td>
                            <td>${record.student_number || 'N/A'}</td>
                            <td class="text-nowrap">${studentName}</td>
                            <td>${record.course_name || 'N/A'}</td>
                            <td><small class="text-muted">${record.rfid_uid || 'N/A'}</small></td>
                            <td class="text-center">
                                <img src="${photoUrl}" alt="${studentName}" 
                                     class="rounded-circle" 
                                     style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #dee2e6;">
                            </td>
                            <td>${scanTime}</td>
                            <td>${timeOut}</td>
                            <td>${eventName}</td>
                            <td><span class="badge ${statusClass}">${statusText}</span></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary" title="View Details" onclick="viewRecordDetails(${record.id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        `;
                        
                        tbody.appendChild(row);
                    });
                } else {
                    // Show no records found message
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="11" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                                    <h5 class="mb-2">No Attendance Records Found</h5>
                                    <p class="mb-0">No attendance records found for the selected date and event.</p>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading attendance data:', error);
                
                // Check if it's a network error
                if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="11" class="text-center py-4">
                                <div class="text-danger">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                                    <h5 class="mb-2">Network Error</h5>
                                    <p class="mb-0">Unable to connect to the server. Please check your internet connection and try again.</p>
                                </div>
                            </td>
                        </tr>
                    `;
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="11" class="text-center py-4">
                                <div class="text-danger">
                                    <i class="fas fa-exclamation-circle fa-2x mb-3"></i>
                                    <h5 class="mb-2">Error Loading Data</h5>
                                    <p class="mb-0">An error occurred while loading attendance records. Please try again later.</p>
                                    <small class="text-muted">${error.message || 'Unknown error'}</small>
                                </div>
                            </td>
                        </tr>
                    `;
                }
                
                // Update pagination controls if they exist
                updatePaginationControls();
            });
            
                // Update the record count display
                updateRecordCount(data.total || (data.data ? data.data.length : 0), offset, pageSize);
                
                // Update pagination controls
                updatePaginationControls();
            });
        }
        
        // Function to update pagination controls
        function updatePaginationControls() {
            const prevBtn = document.getElementById('prevPage');
            const nextBtn = document.getElementById('nextPage');
            const pageSizeSelect = document.getElementById('pageSize');
            
            if (prevBtn) prevBtn.disabled = currentPage === 0;
            if (nextBtn) nextBtn.disabled = (currentPage + 1) * pageSize >= totalRecords;
            if (pageSizeSelect) pageSizeSelect.value = pageSize;
        }
        
        // Function to update the record count display
        function updateRecordCount(total, offset, limit) {
            totalRecords = total;
            const from = total > 0 ? offset + 1 : 0;
            const to = Math.min(offset + limit, total);
            
            const showingFrom = document.getElementById('showingFrom');
            const showingTo = document.getElementById('showingTo');
            const totalRecordsEl = document.getElementById('totalRecords');
            
            if (showingFrom) showingFrom.textContent = from;
            if (showingTo) showingTo.textContent = to;
            if (totalRecordsEl) totalRecordsEl.textContent = total;
            
            // Update pagination controls
            updatePaginationControls();
        }
        
        // Event Listeners for Pagination
        document.addEventListener('DOMContentLoaded', function() {
            // Previous page button
            const prevBtn = document.getElementById('prevPage');
            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    if (currentPage > 0) {
                        currentPage--;
                        loadAttendanceData();
                    }
                });
            }
            
            // Next page button
            const nextBtn = document.getElementById('nextPage');
            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    if ((currentPage + 1) * pageSize < totalRecords) {
                        currentPage++;
                        loadAttendanceData();
                    }
                });
            }
            
            // Page size selector
            const pageSizeSelect = document.getElementById('pageSize');
            if (pageSizeSelect) {
                pageSizeSelect.addEventListener('change', function() {
                    pageSize = parseInt(this.value);
                    currentPage = 0; // Reset to first page
                    loadAttendanceData();
                });
            }
            
            // Date change handler
            const dateInput = document.getElementById('attendanceDate');
            if (dateInput) {
                dateInput.addEventListener('change', function() {
                    currentPage = 0; // Reset to first page
                    loadAttendanceData();
                });
            }
            
            // Event selector change handler
            const eventSelect = document.getElementById('eventSelect');
            if (eventSelect) {
                eventSelect.addEventListener('change', function() {
                    currentPage = 0; // Reset to first page
                    loadAttendanceData();
                });
            }
            
            // Refresh button
            const refreshBtn = document.getElementById('refreshBtn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    loadAttendanceData();
                });
            }
            
            // Initial load of attendance data
            loadAttendanceData();
        });
    }
    
    // Function to process RFID scan from keyboard input
    function processRFIDScan(rfidUid) {
        console.log('Processing RFID scan:', rfidUid);
        
        // Get the selected event ID if any
        const eventSelect = document.getElementById('eventSelect');
        const eventId = eventSelect ? eventSelect.value : null;
        
        // Show scanning status
        const scanStatus = document.getElementById('scanStatus');
        const scanResult = document.getElementById('scanResult');
        
        scanStatus.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary mb-2" role="status">
                    <span class="visually-hidden">Scanning...</span>
                </div>
                <p class="mb-0">Scanning RFID Card...</p>
            </div>
        `;
        
        // Prepare the request data
        const requestData = {
            rfid_uid: rfidUid,
            attendance_type: 'Time In',
            event_id: eventId ? parseInt(eventId) : null
        };
        
        console.log('Sending request with data:', requestData);
        
        // Send the request to the server
        fetch('../api/rfid-scan.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`HTTP error! status: ${response.status}, body: ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('RFID scan response:', data);
            
            if (data.success) {
                // Show success message
                scanStatus.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        ${data.message || 'Attendance recorded successfully'}
                    </div>
                `;
                
                // Show student info if available
                const studentData = data.data || data; // Handle both nested and flat response
                if (studentData.student_name) {
                    scanResult.innerHTML = `
                        <div class="card bg-dark">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <img src="${studentData.photo_path || studentData.profile_image_path || '../assets/img/default-avatar.png'}" 
                                         class="rounded-circle" 
                                         style="width: 100px; height: 100px; object-fit: cover;" 
                                         alt="Student Photo"
                                         onerror="this.src='../assets/img/default-avatar.png'">
                                </div>
                                <h5 class="mb-1">${studentData.student_name}</h5>
                                <p class="text-muted mb-1">${studentData.student_number || 'N/A'}</p>
                                <p class="mb-1">${studentData.department_name || ''} ${studentData.course_name ? '• ' + studentData.course_name : ''}</p>
                                <div class="badge bg-primary">${studentData.attendance_type || 'Time In'}</div>
                            </div>
                        </div>
                    `;
                }
            } else if (data.status === 'unregistered' || (data.success === false && data.message && data.message.toLowerCase().includes('not registered'))) {
                // Show registration form for unregistered card
                handleScanResult({
                    status: 'unregistered',
                    rfid_uid: rfidUid,
                    message: data.message || 'This card is not registered in the system.',
                    success: false
                });
                return; // Exit early
            } else {
                // Show error message
                scanStatus.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        ${data.message || 'Error processing RFID card'}
                    </div>
                `;
            }
            
            // Reload attendance data to show the new record
            loadAttendanceData();
        })
        .catch(error => {
            console.error('Error processing RFID scan:', error);
            
            // Check if it's a network error
            if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                scanStatus.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-danger py-4">
                            <i class="fas fa-wifi fa-2x mb-2"></i><br>
                            Network Error: Could not connect to server<br>
                            <small class="text-muted">Please check your internet connection and try again</small>
                        </td>
                    </tr>
                `;
                
                // Add retry button
                const retryBtn = document.createElement('button');
                retryBtn.className = 'btn btn-primary mt-3';
                retryBtn.innerHTML = '<i class="fas fa-sync-alt me-2"></i>Retry';
                retryBtn.onclick = loadAttendanceData;
                
                const td = tbody.querySelector('td');
                if (td) {
                    const div = document.createElement('div');
                    div.className = 'mt-3';
                    div.appendChild(retryBtn);
                    td.appendChild(div);
                }
            } else if (error.message.includes('Invalid JSON')) {
                // Handle JSON parsing errors
                scanStatus.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-danger py-4">
                            <i class="fas fa-exclamation-circle fa-2x mb-2"></i><br>
                            Error: Invalid response from server<br>
                            <small class="text-muted">Please check PHP error logs for details</small>
                        </td>
                    </tr>
                `;
            } else {
                // For other errors, show a more generic message
                scanStatus.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-danger py-4">
                            <i class="fas fa-exclamation-circle fa-2x mb-2"></i><br>
                            Error processing RFID scan<br>
                            <small class="text-muted">${error.message}</small>
                        </td>
                    </tr>
                `;
            }
        });
    }
    
    // Initialize NFC keyboard input capture when the page loads 
    document.addEventListener('DOMContentLoaded', function() { 
        // Load attendance data first
        loadAttendanceData();
        
        // Set up pagination and date change listeners
        document.getElementById('nextPage').addEventListener('click', function() {
            if ((currentPage + 1) * pageSize < totalRecords) {
                currentPage++;
                loadAttendanceData();
            }
        });
        
        document.getElementById('prevPage').addEventListener('click', function() {
            if (currentPage > 0) {
                currentPage--;
                loadAttendanceData();
            }
        });
        
        document.getElementById('attendanceDate').addEventListener('change', function() {
            currentPage = 0; // Reset to first page when date changes
            loadAttendanceData();
        });
        
        // Load and initialize RFID reader with fallback
        function loadRFIDReader() {
            console.log('Loading RFID reader...');
            
            // First try to load the external reader script
            if (typeof window.rfidReader === 'undefined') {
                console.log('External RFID reader not found, creating script element...');
                const script = document.createElement('script');
                script.src = '../assets/js/rfid-reader.js?v=' + new Date().getTime();
                script.onload = function() {
                    console.log('RFID Reader script loaded, initializing...');
                    initializeRFIDReader();
                };
                script.onerror = function() {
                    console.warn('Failed to load external RFID reader, using fallback implementation');
                    initializeFallbackRFIDReader();
                };
                document.head.appendChild(script);
            } else {
                console.log('RFID Reader already loaded, initializing...');
                initializeRFIDReader();
            }
        }
        
        // Start the RFID reader initialization
        loadRFIDReader();
            
            function initializeRFIDReader() {
                console.log('Initializing RFID reader...');
                
                // Try the object method first
                if (typeof window.rfidReader !== 'undefined' && 
                    typeof window.rfidReader.initializeNFCKeyboardCapture === 'function') {
                    try {
                        window.rfidReader.initializeNFCKeyboardCapture();
                        console.log('USB NFC reader capture initialized via object');
                        return;
                    } catch (e) {
                        console.error('Error initializing RFID reader via object:', e);
                        // Fall through to next method if this fails
                    }
                }
                
                // Then try the global function
                if (typeof window.initializeNFCKeyboardCapture === 'function') {
                    try {
                        window.initializeNFCKeyboardCapture();
                        console.log('USB NFC reader capture initialized directly');
                        return;
                    } catch (e) {
                        console.error('Error initializing RFID reader directly:', e);
                        // Fall through to fallback implementation
                    }
                }
                
                // If we get here, no reader was found, so initialize fallback
                console.warn('No RFID reader found, initializing fallback implementation');
                initializeFallbackRFIDReader();
            }
            
            function initializeFallbackRFIDReader() {
                console.log('Initializing fallback RFID reader...');
                
                // Update the UI to show fallback is active
                const scanStatus = document.getElementById('scanStatus');
                if (scanStatus) {
                    scanStatus.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-keyboard me-2"></i>
                            Keyboard input mode active. Scan an RFID card or enter the UID manually.
                            <div class="mt-2">
                                <button class="btn btn-sm btn-outline-primary" onclick="manualScanTest()">
                                    <i class="fas fa-keyboard me-1"></i> Enter UID Manually
                                </button>
                            </div>
                        </div>
                    `;
                }
                
                // Initialize keyboard input capture for direct UID entry
                let rfidInput = '';
                let lastKeyTime = 0;
                const RFID_TIMEOUT = 100; // Time in ms to consider a sequence complete
                
                // Remove any existing keypress listeners to prevent duplicates
                document.removeEventListener('keypress', handleKeyPress);
                
                // Initialize the NFC keyboard capture function
                window.initializeNFCKeyboardCapture = function() {
                    console.log('Fallback keyboard capture initialized');
                    // The keypress handler is already set up above
                };
                
                // Add new keypress listener
                document.addEventListener('keypress', handleKeyPress);
                
                function handleKeyPress(e) {
                    const currentTime = new Date().getTime();
                    
                    // If there's a significant delay since the last keypress, reset the input
                    if (currentTime - lastKeyTime > RFID_TIMEOUT) {
                        rfidInput = '';
                    }
                    
                    // Add the key to the input
                    rfidInput += String.fromCharCode(e.which);
                    lastKeyTime = currentTime;
                    
                    // Process the input after a short delay to allow the complete UID to be entered
                    clearTimeout(window.rfidInputTimeout);
                    window.rfidInputTimeout = setTimeout(() => {
                        // Only process if we have a valid UID (typically 8-10 digits)
                        if (rfidInput.length >= 8 && rfidInput.length <= 10 && /^\d+$/.test(rfidInput)) {
                            console.log('Processing manual RFID input:', rfidInput);
                            processRFIDScan(rfidInput);
                        }
                    }, RFID_TIMEOUT + 50);
                }
                
                // Call the initialization
                window.initializeNFCKeyboardCapture();
                    
                    window.initializeNFCKeyboardCapture = function() {
                        let rfidInput = '';
                        let lastKeyTime = 0;
                        const RFID_TIMEOUT = 500; // Time in ms to consider a sequence complete
                        
                        document.addEventListener('keypress', function(e) {
                            const currentTime = new Date().getTime();
                            
                            // If there's a significant delay since the last keypress, reset the input
                            if (currentTime - lastKeyTime > RFID_TIMEOUT) {
                                rfidInput = '';
                            }
                            
                            // Update the last key time
                            lastKeyTime = currentTime;
                            
                            // Add the character to our input buffer
                            if (e.key !== 'Enter') {
                                rfidInput += e.key;
                            } else if (rfidInput.length > 0) {
                                // Process the complete RFID input when Enter is pressed
                                console.log('NFC card detected:', rfidInput);
                                simulateWithRealUID(rfidInput); // Call our new function instead
                                rfidInput = ''; // Reset for next scan
                                e.preventDefault(); // Prevent form submission if in a form
                            }
                        });
                        
                        console.log('NFC keyboard capture initialized');
                    };
                    
                    // Call the newly defined function
                    window.initializeNFCKeyboardCapture();
                }
            }
            
            // Load initial attendance data 
            loadAttendanceData(); 
        }); 

        // Keyboard input handler for RFID scanning
        document.addEventListener('keypress', function(e) {
            // Only process if we're not already in a scan and the key is a number or letter
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
                return;
            }
            
            // Start the scan timer if not already started
            if (!window.scanStartTime) {
                window.scanStartTime = Date.now();
                window.scanInput = '';
            }
            
            // Reset the timer
            clearTimeout(window.scanTimer);
            
            // Add the key to the input buffer
            window.scanInput += e.key;
            
            // Set a timeout to process the input if no more keys are pressed
            window.scanTimer = setTimeout(function() {
                // Check if we have a valid RFID UID (typically 8-10 digits)
                if (window.scanInput.length >= 8 && /^\d+$/.test(window.scanInput)) {
                    console.log('Processing RFID scan from keyboard input:', window.scanInput);
                    processRFIDScan(window.scanInput);
                }
                
                // Reset the scan state
                window.scanStartTime = null;
                window.scanInput = '';
            }, 100); // 100ms delay to capture the entire RFID UID
        });
        
        // The main processRFIDScan function handles the API response and UI updates
        // This ensures all scan responses are processed consistently
        
        // Initialize the scanner UI
        const scanStatus = document.getElementById('scanStatus');
        scanStatus.innerHTML = `
            <div class="text-center">
                <i class="fas fa-rfid fa-3x text-primary mb-3"></i>
                <h4 class="text-light mb-3">Ready to Scan</h4>
                <p class="text-muted">Place an RFID card near the reader or type the UID</p>
            </div>
        `;
        
        // Clear any previous scan results
        const scanResult = document.getElementById('scanResult');
        scanResult.innerHTML = '';
        // Helper function to show error messages
        function showError(message) {
            const scanStatus = document.getElementById('scanStatus');
            if (scanStatus) {
                scanStatus.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        ${message}
                    </div>
                `;
            } else {
                // Fallback to alert if DOM element not found
                alert('Error: ' + message);
            }
        }    
                // Reset after error
                setTimeout(() => {
                    scanStatus.innerHTML = `
                        <div class="text-center">
                            <i class="fas fa-rfid fa-3x text-primary mb-3"></i>
                            <h4 class="text-light mb-3">Ready to Scan</h4>
                            <p class="text-muted">Place an RFID card near the reader</p>
                        </div>
                    `;
                }, 5000);
            });
        }
        
        // Function to manually enter a UID for testing
        function manualScanTest() {
            const manualUid = prompt('Enter RFID UID for testing (8-10 digits):', '3870578740');
            
            if (manualUid && /^\d{8,10}$/.test(manualUid)) {
                console.log('Processing manual RFID input:', manualUid);
                processRFIDScan(manualUid);
            } else if (manualUid) {
                alert('Please enter a valid 8-10 digit UID');
            }
            scanStatus.innerHTML = '<i class="fas fa-spinner fa-spin scanner-icon text-primary"></i>' +
                          '<h4 class="text-light mb-4">Processing Scan...</h4>' +
                          '<p class="text-muted">Processing real RFID UID: ' + rfidUid + '</p>' +
                          '<div class="spinner-border text-primary mt-3" role="status"></div>';
            
            // Send the RFID UID to the API to check if it's registered
            fetch('../api/rfid-scan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    rfid_uid: rfidUid,
                    check_only: true // Just check if registered without recording attendance
                })
            })
            .then(response => response.json())
            .then(data => {
                setTimeout(() => {
                    if (data.success) {
                        // Registered student
                        const scenario = {
                            success: true,
                            message: 'RFID scan completed successfully',
                            data: {
                                rfid_uid: rfidUid, // Use the actual scanned UID
                                student_name: data.student_name || 'Student',
                                student_number: data.student_number || '',
                                attendance_type: 'Time In'
                            }
                        };
                        handleScanResult(scenario);
                    } else {
                        // Unregistered card
                        const scenario = {
                            success: false,
                            message: 'Unregistered RFID card',
                            rfid_uid: rfidUid, // Use the actual scanned UID
                            status: 'unregistered'
                        };
                        handleScanResult(scenario);
                    }
                    // Refresh attendance records after scan
                    loadAttendanceData();
                }, 2000); // Keep the 2-second delay for simulation effect
            })
            .catch(error => {
                console.error('Error checking RFID:', error);
                // Handle as unregistered in case of error
                setTimeout(() => {
                    const scenario = {
                        success: false,
                        message: 'Error checking RFID card',
                        rfid_uid: rfidUid, // Use the actual scanned UID
                        status: 'unregistered'
                    };
                    handleScanResult(scenario);
                    // Refresh attendance records after scan
                    loadAttendanceData();
                }, 2000);
            });
        }
    </script>
    
</body>
</html>