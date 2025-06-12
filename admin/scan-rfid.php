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

function determineAttendanceType($pdo) {
    // Get current time
    $currentTime = date('H:i:s');
    
    // Get attendance settings
    $stmt = $pdo->query("SELECT * FROM attendance_settings ORDER BY setting_id DESC LIMIT 1");
    $settings = $stmt->fetch();
    
    if (strtotime($currentTime) >= strtotime($settings['time_in_start']) && 
        strtotime($currentTime) <= strtotime($settings['time_in_closing'])) {
        return 'Time In';
    } elseif (strtotime($currentTime) >= strtotime($settings['time_out_start']) && 
              strtotime($currentTime) <= strtotime($settings['time_out_closing'])) {
        return 'Time Out';
    } else {
        return determineDefaultAttendanceType($currentTime);
    }
}

// Add a helper function to determine default type based on time of day
function determineDefaultAttendanceType($currentTime) {
    $hour = (int)date('H', strtotime($currentTime));
    return ($hour < 12) ? 'Time In' : 'Time Out';
}

// When processing RFID scan:
$attendance_type = determineAttendanceType($pdo);
// Use this value when inserting into attendance_records

if (isset($_POST['rfid'])) {
    $rfid = $_POST['rfid'];
    
    // Get student info
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE rfid_uid = ?");
    $stmt->execute([$rfid]);
    $student = $stmt->fetch();
    
    if ($student) {
        // Check last attendance type
        $lastType = getLastAttendanceType($pdo, $student['student_id']);
        
        // Determine new attendance type
        if ($lastType === null || $lastType === 'Time Out') {
            $attendanceType = 'Time In';
        } else {
            $attendanceType = 'Time Out';
        }
        
        // Insert new attendance record
        $stmt = $pdo->prepare("
            INSERT INTO attendance_records 
            (student_id, attendance_date, attendance_type, location) 
            VALUES (?, NOW(), ?, 'Main Entrance')
        ");
        $stmt->execute([$student['student_id'], $attendanceType]);
    }
}
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
                        <!-- Event Selection Dropdown -->
                        <div class="mb-4">
                            
                                <?php
                                // Fetch active events from the database
                                $eventsQuery = $pdo->query("SELECT event_id, event_name, start_date, end_date FROM events WHERE is_active = 1 AND end_date >= CURDATE() ORDER BY start_date");
                                while ($event = $eventsQuery->fetch(PDO::FETCH_ASSOC)) {
                                    $startDate = new DateTime($event['start_date']);
                                    $endDate = new DateTime($event['end_date']);
                                    echo "<option value='" . $event['event_id'] . "'>" . 
                                         htmlspecialchars($event['event_name']) . " (" . 
                                         $startDate->format('M j') . " - " . 
                                         $endDate->format('M j, Y') . 
                                         ")</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Please select an event before scanning.</div>
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
                        
                        <!-- Update the JavaScript section at the bottom of the file -->
                        <script>
                            // Function to check if RFID reader is connected and start scanning
                            function checkRFIDReaderAndScan() {
                                const scanStatus = document.getElementById('scanStatus');
                                const scanResult = document.getElementById('scanResult');
                                
                                // Reset UI for initial state
                                scanStatus.innerHTML = '<i class="fas fa-spinner fa-spin scanner-icon text-primary"></i>' +
                                              '<h4 class="text-light mb-4">Checking for RFID Reader...</h4>' +
                                              '<p class="text-muted">Please ensure your RFID reader is connected.</p>' +
                                              '<div class="spinner-border text-primary mt-3" role="status"></div>';
                                scanResult.innerHTML = '';
                                scanResult.classList.add('d-none');
                                
                                // Check if RFID reader is available and start scanning automatically
                                if (typeof window.rfidReader !== 'undefined' && typeof window.rfidReader.initializeRFIDScan === 'function') {
                                    console.log('RFID Reader detected, starting scan automatically');
                                    // Update UI to show scanning in progress
                                    scanStatus.innerHTML = '<i class="fas fa-spinner fa-spin scanner-icon text-primary"></i>' +
                                              '<h4 class="text-light mb-4">Scanning in Progress...</h4>' +
                                              '<p class="text-muted">Please place the RFID card on the reader.</p>' +
                                              '<div class="spinner-border text-primary mt-3" role="status"></div>';
                                    
                                    // Initialize RFID scanning with callback
                                    window.rfidReader.initializeRFIDScan('attendance', function(result) {
                                        console.log('RFID Scan Result:', result);
                                        handleScanResult(result);
                                    });
                                } else {
                                    // RFID reader not detected, show fallback options
                                    scanStatus.innerHTML = '<i class="fas fa-exclamation-circle scanner-icon text-warning"></i>' +
                                              '<h4 class="text-light mb-4">RFID Reader Not Detected</h4>' +
                                              '<p class="text-muted mb-4">Please connect your RFID reader or use manual input for testing.</p>' +
                                              '<button class="btn btn-primary me-2" onclick="checkRFIDReaderAndScan()">' +
                                              '<i class="fas fa-sync-alt me-2"></i>Check Again</button>' +
                                              '<button class="btn btn-secondary" onclick="manualScanTest()">' +
                                              '<i class="fas fa-keyboard me-2"></i>Manual Test</button>';
                                }
                            }
                            
                            // Function for manual testing when no reader is available
                            function manualScanTest() {
                                const manualUid = prompt('Enter RFID UID for testing:', '3870578740');
                                
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
                                        // Ensure the rfid_uid is included in the result
                                        if (!data.rfid_uid && manualUid) {
                                            data.rfid_uid = manualUid;
                                        }
                                        handleScanResult(data);
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
                                    // Fallback to simulation if no manual UID is provided
                                    simulateScan();
                                }
                            }
                            
                            // Function to simulate a scan with random scenarios
                            function simulateScan() {
                                const scanStatus = document.getElementById('scanStatus');
                                
                                // Show scanning in progress
                                scanStatus.innerHTML = '<i class="fas fa-spinner fa-spin scanner-icon text-primary"></i>' +
                                              '<h4 class="text-light mb-4">Simulating Scan...</h4>' +
                                              '<p class="text-muted">This is a test simulation.</p>' +
                                              '<div class="spinner-border text-primary mt-3" role="status"></div>';
                                
                                setTimeout(() => {
                                    // Simulate different scenarios
                                    const scenarios = [
                                        // Registered student
                                        {
                                            success: true,
                                            message: 'RFID scan completed successfully',
                                            data: {
                                                rfid_uid: '3870578740', // Add this line to include the UID
                                                student_name: 'Test Student',
                                                student_number: '2023001',
                                                attendance_type: 'Time In'
                                            }
                                        },
                                        // Unregistered card
                                        {
                                            success: false,
                                            message: 'Unregistered RFID card',
                                            rfid_uid: '3871243332',
                                            status: 'unregistered'
                                        }
                                    ];
                                    
                                    // Randomly select a scenario
                                    const scenario = scenarios[Math.floor(Math.random() * scenarios.length)];
                                    handleScanResult(scenario);
                                }, 2000);
                            }
                            
                            // Function to handle scan results
                            function handleScanResult(result) {
                                const scanStatus = document.getElementById('scanStatus');
                                const scanResult = document.getElementById('scanResult');
                                
                                // Update scan result display
                                scanResult.classList.remove('d-none');
                                
                                // Get current attendance mode and event info
                                const currentMode = document.querySelector('.attendance-mode.active').dataset.mode;
                                const eventSelect = document.getElementById('eventSelect');
                                const eventName = eventSelect.options[eventSelect.selectedIndex]?.text.split(' (')[0] || 'Selected Event';
                                
                                // Handle unregistered card
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
                                                    
                                                    <!-- Profile Picture Upload -->
                                                    <div class="row mb-4">
                                                        <div class="col-12 text-center">
                                                            <div class="mb-3">
                                                                <div class="position-relative d-inline-block">
                                                                    <img id="profileImagePreview" src="../assets/img/default-avatar.png" 
                                                                         class="rounded-circle border border-4 border-primary" 
                                                                         style="width: 150px; height: 150px; object-fit: cover;"
                                                                         alt="Profile Picture">
                                                                    <label for="profile_image" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2" style="cursor: pointer;">
                                                                        <i class="fas fa-camera"></i>
                                                                    </label>
                                                                    <input type="file" class="d-none" id="profile_image" name="profile_image" accept="image/*" onchange="previewImage(this)">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
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
                                                                    <i class="fas fa-times me-2"></i>Cancel
                                                                </button>
                                                                <button type="submit" class="btn btn-primary">
                                                                    <i class="fas fa-save me-2"></i>Register
                                                                </button>
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
                                    
                                    // Add event listener for form submission
                                    document.getElementById('rfidRegistrationForm').addEventListener('submit', function(e) {
                                        e.preventDefault();
                                        registerStudent(this);
                                    });
                                } else {
                                    // Regular scan result (success or error)
                                    const isEvent = result.event_id || result.event_name;
                                    const eventInfo = isEvent ? `
                                        <div class="alert ${result.success ? 'alert-info' : 'alert-warning'} mb-3">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-calendar-check fa-2x me-3"></i>
                                                <div>
                                                    <h5 class="mb-1">${result.event_name || 'Event Attendance'}</h5>
                                                    ${result.event_date ? `<p class="mb-0">${result.event_date}</p>` : ''}
                                                </div>
                                            </div>
                                        </div>
                                    ` : '';
                                    
                                    scanResult.innerHTML = `
                                        ${eventInfo}
                                        <div class="alert alert-${result.success ? 'success' : 'danger'} mb-3">
                                            <h4 class="alert-heading">
                                                <i class="fas fa-${result.success ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                                                ${result.success ? 'Success!' : 'Error'}
                                            </h4>
                                            <p class="mb-2">
                                                ${result.message || (result.success ? 
                                                    (isEvent ? 'Event attendance recorded successfully' : 'Scan processed successfully') : 
                                                    'An error occurred')}
                                            </p>
                                            ${result.student_name ? `
                                                <hr>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p class="mb-1"><strong>Student ID:</strong> ${result.student_id || 'N/A'}</p>
                                                        <p class="mb-1"><strong>Name:</strong> ${result.student_name}</p>
                                                        <p class="mb-1"><strong>Department:</strong> ${result.department_name || 'N/A'}</p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p class="mb-1"><strong>${isEvent ? 'Event' : 'Course'}:</strong> ${isEvent ? (result.event_name || 'N/A') : (result.course_name || 'N/A')}</p>
                                                        <p class="mb-1"><strong>Time:</strong> ${new Date().toLocaleString()}</p>
                                                        <p class="mb-1"><strong>Status:</strong> ${result.attendance_type || 'N/A'}</p>
                                                    </div>
                                                </div>
                                            ` : ''}
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
                            
                            // Start the automatic scanning process when the page loads
                            document.addEventListener('DOMContentLoaded', function() {
                                // Start checking for RFID reader and scanning automatically
                                checkRFIDReaderAndScan();
                            });
                            
                            // Rest of your existing functions (updateRecentScans, loadDepartmentsAndCourses, etc.)
                            // ...
                            
                            let currentAttendanceMode = 'Time In';

                            // Add event listeners for attendance mode buttons
                            document.querySelectorAll('.attendance-mode').forEach(button => {
                                button.addEventListener('click', function() {
                                    // Remove active class from all buttons
                                    document.querySelectorAll('.attendance-mode').forEach(btn => {
                                        btn.classList.remove('active');
                                    });
                                    
                                    // Add active class to clicked button
                                    this.classList.add('active');
                                    
                                    // Update current mode
                                    currentAttendanceMode = this.dataset.mode;
                                    
                                    // Update the scan status message
                                    const scanStatus = document.getElementById('scanStatus');
                                    scanStatus.innerHTML = `
                                        <i class="fas fa-wifi scanner-icon"></i>
                                        <h4 class="text-light mb-4">Ready for ${currentAttendanceMode}</h4>
                                        <p class="text-muted">Please scan your RFID card</p>
                                    `;
                                });
                            });
                            
                            // Update the processRFIDScan function
function processRFIDScan(rfidUid) {
    // Get the selected event ID
    const eventSelect = document.getElementById('eventSelect');
    const eventId = eventSelect.value;
    
    if (!eventId) {
        // Show error if no event is selected
        const scanResult = document.getElementById('scanResult');
        scanResult.classList.remove('d-none');
        scanResult.innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Event Required</strong><br>
                Please select an event before scanning an RFID card.
            </div>
        `;
        // Scroll to the error message
        scanResult.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return Promise.reject('No event selected');
    }
    
    // Get the current attendance mode from the active button
    const currentMode = document.querySelector('.attendance-mode.active').dataset.mode;
    const attendanceType = currentMode === 'Time In' ? 'Time In' : 'Time Out';
    
    // Show scanning status
    const scanStatus = document.getElementById('scanStatus');
    scanStatus.innerHTML = `
        <i class="fas fa-spinner fa-spin scanner-icon"></i>
        <h4 class="text-light mb-4">Processing ${attendanceType}...</h4>
        <p class="text-muted">Please wait while we process your request.</p>
    `;
    
    // Show the scan result container
    const scanResult = document.getElementById('scanResult');
    scanResult.classList.remove('d-none');
    scanResult.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Processing RFID scan, please wait...</p>
        </div>
    `;
    
    // Make the API request
    return fetch('../api/rfid-scan.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            rfid_uid: rfidUid,
            attendance_type: attendanceType,
            reader_id: 1,
            event_id: eventId  // Include the selected event ID
        })
    })
    .then(response => response.json())
    .then(data => {
        handleScanResult(data);
        loadAttendanceData(); // Refresh the attendance table
    })
    .catch(error => {
        console.error('Error:', error);
        handleScanError(error);
    });
}
function registerStudent(form) {
    const formData = new FormData(form);
    const registrationData = {};
    
    formData.forEach((value, key) => {
        registrationData[key] = value;
    });
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Registering...';
    submitBtn.disabled = true;

    // Send registration request
    fetch('../api/register-rfid.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(registrationData)
    })
    .then(response => {
        // First check if the response is ok (status in the range 200-299)
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        // Only show success message if we get a success response
        if (data.success) {
            // Show success message
            Toastify({
                text: `✅ Registration Successful!\nStudent: ${registrationData.first_name} ${registrationData.last_name}`,
                duration: 3000,
                gravity: "top",
                position: "right",
                className: "success",
                close: true
            }).showToast();

            // Reset scanner UI
            const scanStatus = document.getElementById('scanStatus');
            const scanResult = document.getElementById('scanResult');
            
            // Show temporary success message
            scanStatus.innerHTML = `
                <i class="fas fa-check-circle scanner-icon text-success"></i>
                <h4 class="text-light mb-4">Registration Successful!</h4>
                <p class="text-muted">Student: ${registrationData.first_name} ${registrationData.last_name}</p>
            `;

            // Clear the registration form and hide it
            scanResult.classList.add('d-none');
            form.reset();

            // Return to scanning mode after delay
            setTimeout(() => {
                // Reset scanner state to ready
                scanStatus.innerHTML = `
                    <i class="fas fa-wifi scanner-icon"></i>
                    <h4 class="text-light mb-4">RFID Scanner Ready</h4>
                    <p class="text-muted">Please scan another card</p>
                `;
                
                // Refresh attendance records
                loadAttendanceData();
                
                // Restart scanning
                checkRFIDReaderAndScan();
            }, 2000);
        } else {
            // If the server returned success: false
            throw new Error(data.message || 'Registration failed');
        }
    })
    .catch(error => {
        console.error('Registration error:', error);
        // Only show error toast if the registration actually failed
        Toastify({
            text: `❌ Registration Failed!\n${error.message || 'Please try again'}`,
            duration: 4000,
            gravity: "top",
            position: "right",
            className: "error",
            close: true
        }).showToast();
    })
    .finally(() => {
        // Reset button state
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
    });

    return false; // Prevent form submission
}
    
        // Add helper function for scanner state reset
        function resetScannerState() {
            const scanStatus = document.getElementById('scanStatus');
            const scanResult = document.getElementById('scanResult');
            
            scanStatus.innerHTML = `
                <i class="fas fa-wifi scanner-icon"></i>
                <h4 class="text-light mb-4">RFID Scanner Ready</h4>
                <p class="text-muted">Please scan your RFID card</p>
            `;
            
            scanResult.classList.add('d-none');
        }
                        </script>
                    </div>
                </div>

                <!-- Attendance Records Table -->
                <div class="card">
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
                                        <th width="5%">#</th>
                                        <th width="10%">Student ID</th>
                                        <th width="20%">Name</th>
                                        <th width="15%">Course</th>
                                        <th width="15%">RFID UID</th>
                                        <th width="15%">Time In</th>
                                        <th width="15%">Time Out</th>
                                        <th width="10%">Status</th>
                                        <th width="5%" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="attendanceRecords">
                                    <!-- Attendance records will be loaded here via JavaScript -->
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p class="mt-2 mb-0">Loading attendance data...</p>
                                        </td>
                                    </tr>
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
            </div>
        </div>
    </div>

    <!-- Custom JavaScript for RFID scanning -->
    <!-- Registration Modal -->
    <div class="modal fade" id="registrationModal" tabindex="-1" aria-labelledby="registrationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="registrationModalLabel">
                        <i class="fas fa-user-plus me-2"></i>Register New Student
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="scanStatus" class="text-center mb-4">
                        <i class="fas fa-user-plus fa-4x text-info mb-3"></i>
                        <h4 class="text-light">New RFID Card Detected</h4>
                        <p class="text-muted mb-0" id="rfidUidDisplay"></p>
                    </div>
                    <div id="scanResult">
                        <!-- Registration form will be inserted here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .scanner-card {
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }
        .form-label {
            color: #e0e0e0;
        }
    </style>

    <!-- Update the JavaScript section at the bottom of the file -->
    <script>
        // Initialize registration modal
        let registrationModal;
        
        // Initialize modal when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            registrationModal = new bootstrap.Modal(document.getElementById('registrationModal'));
            
            // Close modal when clicking outside
            document.getElementById('registrationModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    registrationModal.hide();
                }
            });
        });
        
        // Function to preview selected image
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const previewContainer = document.getElementById('imagePreviewContainer');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.src = '#';
                previewContainer.style.display = 'none';
            }
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
                                                <i class="fas fa-times me-2"></i>Cancel
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Register
                                            </button>
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
                    if (data.success) {
                        const departmentSelect = document.getElementById('department_id');
                        data.departments.forEach(dept => {
                            const option = document.createElement('option');
                            option.value = dept.department_id;
                            option.textContent = dept.department_name;
                            departmentSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error loading departments:', error));
            
            // Load courses
            fetch('../api/get-courses.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const courseSelect = document.getElementById('course_id');
                        data.courses.forEach(course => {
                            const option = document.createElement('option');
                            option.value = course.course_id;
                            option.textContent = course.course_name;
                            courseSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error loading courses:', error));
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
        
        // Function to load attendance data
    // Function to load attendance data
function loadAttendanceData() {
    const attendanceDate = document.getElementById('attendanceDate').value;
    const tbody = document.getElementById('attendanceRecords');
    const offset = currentPage * pageSize;
    
    // Show loading indicator
    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="text-center text-muted py-4">
                <i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>
                Loading attendance records...
            </td>
        </tr>
    `;
    
    // Fetch attendance data from API
    fetch(`../api/get-attendance.php?date=${attendanceDate}&limit=${pageSize}&offset=${offset}`)
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = '';
            
            if (data.success && data.data && data.data.length > 0) {
                data.data.forEach(record => {
                    const row = document.createElement('tr');
                    const scanTime = new Date(record.scan_time).toLocaleTimeString();
                    const studentName = `${record.first_name} ${record.last_name}`;
                    
                    // Determine badge color based on attendance type
                    const badgeClass = record.attendance_type === 'Time In' ? 'bg-success' : 'bg-danger';
                    
                    row.innerHTML = `
                        <td>${scanTime}</td>
                        <td>${studentName}</td>
                        <td>${record.student_number}</td>
                        <td>${record.department_name || 'N/A'}</td>
                        <td>${record.course_name || 'N/A'}</td>
                        <td>
                            <span class="badge ${badgeClass}">
                                ${record.attendance_type || 'N/A'}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-success">Verified</span>
                        </td>
                    `;
                    
                    tbody.appendChild(row);
                });
            } else {
                // Show no records found message
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-2"></i><br>
                            No attendance records found for this date
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
                        <td colspan="7" class="text-center text-danger py-4">
                            <i class="fas fa-wifi-slash fa-2x mb-2"></i><br>
                            Network error: Unable to connect to server<br>
                            <small class="text-muted">Please check your network connection</small>
                        </td>
                    </tr>
                `;
            } else if (error.message.includes('Invalid JSON')) {
                // Handle JSON parsing errors
                tbody.innerHTML = `
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
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-danger py-4">
                            <i class="fas fa-exclamation-circle fa-2x mb-2"></i><br>
                            Error loading attendance data<br>
                            <small class="text-muted">${error.message}</small>
                        </td>
                    </tr>
                `;
            }
        });
}
        
        // Event listeners for pagination
        document.getElementById('prevPage').addEventListener('click', function() {
            if (currentPage > 0) {
                currentPage--;
                loadAttendanceData();
            }
        });
        
        document.getElementById('nextPage').addEventListener('click', function() {
            if ((currentPage + 1) * pageSize < totalRecords) {
                currentPage++;
                loadAttendanceData();
            }
        });
        
        // Event listener for date change
        document.getElementById('attendanceDate').addEventListener('change', function() {
            currentPage = 0; // Reset to first page when date changes
            loadAttendanceData();
        });
        
        // Initialize NFC keyboard input capture when the page loads 
        document.addEventListener('DOMContentLoaded', function() { 
            // Load RFID reader script and initialize capture
            if (typeof window.rfidReader === 'undefined') {
                // Create script element to load rfid-reader.js
                const script = document.createElement('script');
                script.src = '../assets/js/rfid-reader.js?v=' + new Date().getTime(); // Add cache-busting parameter
                script.onload = function() {
                    console.log('RFID Reader detected, starting scan automatically');
                    initializeCapture();
                };
                document.head.appendChild(script);
            } else {
                // RFID reader script already loaded, initialize capture directly
                console.log('RFID Reader detected, starting scan automatically');
                initializeCapture();
            }
            
            function initializeCapture() {
                // Try the object method first
                if (typeof window.rfidReader !== 'undefined' && 
                    typeof window.rfidReader.initializeNFCKeyboardCapture === 'function') {
                    window.rfidReader.initializeNFCKeyboardCapture();
                    console.log('USB NFC reader capture initialized via object');
                } 
                // Then try the global function
                else if (typeof window.initializeNFCKeyboardCapture === 'function') {
                    window.initializeNFCKeyboardCapture();
                    console.log('USB NFC reader capture initialized directly');
                }
                // Finally, define the function if it doesn't exist
                else {
                    console.warn('NFC reader capture function not found, defining it now');
                    // Use the processRFIDScan function from rfid-reader.js if available
                    const processFunction = (typeof window.rfidReader !== 'undefined' && 
                                           typeof window.rfidReader.processRFIDScan === 'function') ?
                                           window.rfidReader.processRFIDScan : processRFIDScan;
                    
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

        // Function to process RFID scan from keyboard input
        function processRFIDScan(rfidUid) {
            console.log('Processing RFID scan:', rfidUid);
            
            // Send the RFID UID to the API
            fetch('../api/rfid-scan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    rfid_uid: rfidUid,
                    attendance_type: 'Time In'
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('RFID scan result:', data);
                // Handle the response - you can customize this part
                if (data.success) {
                    // Check if data has nested structure or direct properties
                    const studentName = data.data ? data.data.student_name : data.student_name;
                    alert('Attendance recorded for ' + studentName);
                    // Reload the page to show updated data after a short delay
                    setTimeout(() => {
                        // Reload attendance data instead of full page refresh
                        loadAttendanceData();
                    }, 1500);
                } else if (data.status === 'unregistered') {
                    alert('Unregistered RFID card: ' + rfidUid);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('RFID Scan Error:', error);
                alert('Error communicating with the server');
            });
        }
        
        // Function to simulate scan with a real UID
        function simulateWithRealUID(rfidUid) {
            console.log('Simulating scan with real UID:', rfidUid);
            const scanStatus = document.getElementById('scanStatus');
            
            // Show scanning in progress
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