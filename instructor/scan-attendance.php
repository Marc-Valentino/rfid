<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

// Initialize Database and AttendanceSystem
$database = new Database();
$pdo = $database->getConnection();
$attendance = new AttendanceSystem();

// Check if user is logged in and is an instructor
$attendance->requireLogin();
if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$instructor_id = $_SESSION['user_id'];
$instructor_courses = $attendance->getInstructorCourses($instructor_id);

function getAttendanceSettings($pdo) {
    try {
        // Check if table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'attendance_settings'")->rowCount() > 0;
        
        if (!$tableExists) {
            // Create default settings if table doesn't exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_settings (
                setting_id INT AUTO_INCREMENT PRIMARY KEY,
                time_in_start TIME DEFAULT '08:00:00',
                time_in_closing TIME DEFAULT '12:00:00',
                time_out_start TIME DEFAULT '13:00:00',
                time_out_closing TIME DEFAULT '17:00:00',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Insert default settings
            $pdo->exec("INSERT INTO attendance_settings (time_in_start, time_in_closing, time_out_start, time_out_closing) 
                       VALUES ('08:00:00', '12:00:00', '13:00:00', '17:00:00')");
        }
        
        // Get current settings
        $stmt = $pdo->query("SELECT * FROM attendance_settings ORDER BY setting_id DESC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting attendance settings: " . $e->getMessage());
        // Return default settings if there's an error
        return [
            'time_in_start' => '08:00:00',
            'time_in_closing' => '12:00:00',
            'time_out_start' => '13:00:00',
            'time_out_closing' => '17:00:00'
        ];
    }
}

function determineAttendanceType($pdo) {
    try {
        $currentTime = date('H:i:s');
        $settings = getAttendanceSettings($pdo);
        
        if (strtotime($currentTime) >= strtotime($settings['time_in_start']) && 
            strtotime($currentTime) <= strtotime($settings['time_in_closing'])) {
            return 'Time In';
        } elseif (strtotime($currentTime) >= strtotime($settings['time_out_start']) && 
                strtotime($currentTime) <= strtotime($settings['time_out_closing'])) {
            return 'Time Out';
        }
    } catch (Exception $e) {
        error_log("Error determining attendance type: " . $e->getMessage());
    }
    
    // Default fallback
    $hour = (int)date('H');
    return ($hour < 12) ? 'Time In' : 'Time Out';
}

// Set default attendance type
$attendance_type = determineAttendanceType($pdo);

// Handle RFID scan POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rfid_uid'])) {
    header('Content-Type: application/json');
    
    try {
        $rfid = trim($_POST['rfid_uid']);
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $attendance_type = isset($_POST['attendance_type']) ? $_POST['attendance_type'] : $attendance_type;
        $reader_id = isset($_POST['reader_id']) ? intval($_POST['reader_id']) : 1; // Default reader ID
        
        if (empty($rfid)) {
            throw new Exception('RFID UID is required');
        }
        
        // Get student info with error handling
        $stmt = $pdo->prepare("SELECT s.student_id, CONCAT(s.first_name, ' ', s.last_name) as name, s.student_number, c.course_code 
                              FROM students s 
                              LEFT JOIN courses c ON s.course_id = c.course_id 
                              WHERE s.rfid_uid = ?");
        $stmt->execute([$rfid]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            throw new Exception('Student not found with this RFID.', 404);
        }
        
        // Check if attendance already recorded for today and type
        $checkStmt = $pdo->prepare("SELECT * FROM attendance_records 
                                   WHERE student_id = ? 
                                   AND course_id = ? 
                                   AND attendance_type = ? 
                                   AND DATE(attendance_date) = CURDATE()");
        $checkStmt->execute([$student['student_id'], $course_id, $attendance_type]);
        
        if ($checkStmt->rowCount() > 0) {
            throw new Exception('Attendance already recorded for this session', 409);
        }
        
        // Insert attendance record with all required fields
        $stmt = $pdo->prepare("
            INSERT INTO attendance_records 
            (student_id, rfid_uid, reader_id, course_id, attendance_date, attendance_type, location, verification_status, created_at) 
            VALUES (?, ?, ?, ?, NOW(), ?, 'Classroom', 'Verified', NOW())
        ");
        
        $result = $stmt->execute([
            $student['student_id'],
            $rfid,
            $reader_id,
            $course_id,
            $attendance_type
        ]);
        
        if (!$result) {
            throw new Exception('Failed to record attendance in database');
        }
        
        // Get the inserted record for response
        $record_id = $pdo->lastInsertId();
        
        // Get course name
        $courseName = 'N/A';
        if ($course_id > 0) {
            $stmt = $pdo->prepare("SELECT course_name FROM courses WHERE course_id = ?");
            $stmt->execute([$course_id]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);
            $courseName = $course ? $course['course_name'] : 'N/A';
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance recorded successfully!',
            'attendance_type' => $attendance_type,
            'timestamp' => date('Y-m-d H:i:s'),
            'student' => [
                'id' => $student['student_id'],
                'name' => $student['name'],
                'student_number' => $student['student_number'],
                'course' => $courseName
            ],
            'record_id' => $record_id
        ]);
        
    } catch (Exception $e) {
        http_response_code($e->getCode() ?: 500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'status' => 'error',
            'code' => $e->getCode() ?: 500
        ]);
    }
    exit;
}

// Function to get recent scans from the API
function getRecentScans($pdo, $limit = 10, $course_id = null) {
    global $instructor_id;
    
    // Build the URL for the API endpoint
    $url = 'get-recent-scans.php?' . http_build_query([
        'limit' => $limit,
        'course_id' => $course_id,
        'instructor_id' => $instructor_id
    ]);
    
    // Initialize cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check for successful response
    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        if ($result && $result['success'] && isset($result['data'])) {
            return $result['data'];
        }
    }
    
    // Fallback to direct database query if API fails
    try {
        $query = "
            SELECT 
                ar.record_id as id,
                ar.attendance_date as date,
                ar.attendance_type as type,
                ar.status,
                ar.rfid_uid,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.student_number,
                c.course_name,
                c.course_code,
                CONCAT(c.course_name, ' (', c.course_code, ')') as course
            FROM attendance_records ar
            LEFT JOIN students s ON ar.student_id = s.student_id
            LEFT JOIN courses c ON ar.course_id = c.course_id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($course_id) {
            $query .= " AND ar.course_id = ?";
            $params[] = $course_id;
        }
        
        $query .= " ORDER BY ar.attendance_date DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the results to match the API response
        $formattedScans = [];
        foreach ($scans as $scan) {
            $formattedScans[] = [
                'id' => $scan['id'],
                'date' => $scan['date'],
                'student_name' => $scan['student_name'],
                'student_number' => $scan['student_number'],
                'course' => $scan['course'],
                'type' => ucfirst($scan['type']),
                'status' => $scan['status'],
                'rfid_uid' => $scan['rfid_uid']
            ];
        }
        
        return $formattedScans;
        
    } catch (PDOException $e) {
        error_log("Error in fallback getRecentScans: " . $e->getMessage());
        return [];
    }
}

// Get recent scans for the table
$recentScans = getRecentScans($pdo, 10);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Attendance - <?php echo APP_NAME; ?></title>
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
        .scanner-card {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(0, 123, 255, 0.3);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
        }
        .scanner-card:hover {
            border-color: #007bff;
            background: rgba(0, 123, 255, 0.1);
        }
        .attendance-mode {
            padding: 10px 20px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .attendance-mode.active {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }

        .scanner-icon {
            font-size: 4rem;
            color: #007bff;
            margin-bottom: 20px;
        }
        .scan-animation {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
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
                    <a class="nav-link active" href="scan-attendance.php">
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
                    <h2><i class="fas fa-qrcode me-2"></i>Attendance Scanner</h2>
                </div>

                <!-- Course Selection -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-book me-2"></i>Select Course</h5>
                    </div>
                    <div class="card-body">
                </div>

                <!-- Scanner Interface -->
                <div class="row justify-content-center mb-4">
                    <div class="col-md-10 col-lg-8">
                        <div class="scanner-card">
                            <!-- Scanner Header -->
                            <div class="text-center mb-4">
                                <h3 class="text-white"><i class="fas fa-id-card me-2"></i>RFID Attendance Scanner</h3>
                                <p class="text-muted">Scan student ID cards to record attendance</p>
                            </div>
                            
                            <!-- Attendance Mode Toggle -->
                            <div class="btn-group w-100 mb-4" role="group" aria-label="Attendance Type">
                                <input type="radio" class="btn-check" name="attendanceType" id="timeInRadio" autocomplete="off" checked>
                                <label class="btn btn-outline-success" for="timeInRadio">
                                    <i class="fas fa-sign-in-alt me-2"></i>Time In
                                </label>
                                
                                <input type="radio" class="btn-check" name="attendanceType" id="timeOutRadio" autocomplete="off">
                                <label class="btn btn-outline-danger" for="timeOutRadio">
                                    <i class="fas fa-sign-out-alt me-2"></i>Time Out
                                </label>
                            </div>
                            
                            <!-- Scanner Status -->
                            <div id="scannerStatus" class="text-center py-4">
                                <div class="scanner-icon">
                                    <i class="fas fa-rss"></i>
                                </div>
                                <h4 class="text-light mb-2" id="scannerMessage">Ready to Scan</h4>
                                <p class="text-muted mb-0" id="scannerSubMessage">Place the RFID card near the reader</p>
                            </div>
                            
                            <!-- Scanner Animation -->
                            <div class="scanner-animation mb-4">
                                <div class="scanner-line"></div>
                            </div>
                            
                            <!-- Manual Input -->
                            <div class="input-group mb-3">
                                <span class="input-group-text bg-dark border-dark text-white">
                                    <i class="fas fa-keyboard"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg bg-dark text-white border-dark" 
                                       id="manualRFID" placeholder="Or enter RFID manually" autocomplete="off">
                                <button class="btn btn-primary" type="button" id="manualSubmit">
                                    <i class="fas fa-paper-plane me-1"></i> Submit
                                </button>
                            </div>
                            
                            <!-- Course Selection -->
                            <div class="mb-3">
                                <label for="courseSelect" class="form-label text-light">Select Course</label>
                                <select class="form-select bg-dark text-white border-dark" id="courseSelect">
                                    <?php foreach ($instructor_courses as $course): ?>
                                        <option value="<?php echo $course['course_id']; ?>">
                                            <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Scan Result -->
                            <div id="scanResult" class="mt-4"></div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent Scans</h5>
                        <button onclick="loadRecentScans()" class="btn btn-sm btn-primary">
                            <i class="fas fa-sync-alt me-2"></i>Refresh
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover" id="recentScansTable">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Student</th>
                                        <th>ID Number</th>
                                        <th>Course</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentScans)): ?>
                                        <?php foreach ($recentScans as $scan): ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($scan['attendance_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($scan['student_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($scan['student_number'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($scan['course_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge <?php echo getBadgeClass($scan['attendance_type'] ?? 'N/A'); ?>">
                                                        <?php echo htmlspecialchars($scan['attendance_type'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td><span class="badge <?php echo getStatusBadgeClass($scan['status'] ?? 'N/A'); ?>">
                                                        <?php echo htmlspecialchars($scan['status'] ?? 'N/A'); ?>
                                                    </span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No recent scans found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="text-muted">Showing <?php echo min(10, count($recentScans)); ?> most recent scans</span>
                            <button class="btn btn-sm btn-outline-light" onclick="loadRecentScans()">
                                <i class="fas fa-sync-alt me-1"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        // Initialize attendance type and other variables
        let currentAttendanceType = '<?php echo $attendance_type; ?>';
        let isScanning = false;
        let scannerPort = null;
        let scanInterval = null;
        const SCAN_INTERVAL_MS = 1000; // 1 second between scans
        let currentCourseId = 0; // Will be set when a course is selected
        
        // Set active button based on current attendance type
        document.addEventListener('DOMContentLoaded', function() {
            updateActiveButton();
            loadRecentScans();
            
            // Focus on RFID input when modal is shown
            const rfidInput = document.getElementById('rfidInput');
            if (rfidInput) {
                rfidInput.focus();
                
                // Add event listener for Enter key
                rfidInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        processManualRFID();
                    }
                });
            }
        });
        
        // Function to update the scanner status UI
        function updateScannerStatus(status, message = '') {
            const scanStatus = document.getElementById('scanStatus');
            const statusIcon = document.getElementById('statusIcon');
            const scanButton = document.getElementById('scanButton');
            
            // Update status text
            if (scanStatus) {
                scanStatus.textContent = message || status.charAt(0).toUpperCase() + status.slice(1);
                
                // Update status classes
                scanStatus.className = '';
                if (status === 'scanning') scanStatus.classList.add('text-primary');
                else if (status === 'success') scanStatus.classList.add('text-success');
                else if (status === 'error') scanStatus.classList.add('text-danger');
            }
            
            // Update icon
            if (statusIcon) {
                let iconClass = 'fa-wifi';
                if (status === 'scanning') iconClass = 'fa-spinner fa-spin';
                else if (status === 'success') iconClass = 'fa-check-circle';
                else if (status === 'error') iconClass = 'fa-times-circle';
                
                statusIcon.className = 'fas ' + iconClass;
            }
            
            // Update button state
            if (scanButton) {
                if (status === 'scanning') {
                    scanButton.disabled = true;
                    scanButton.innerHTML = '<i class="fas fa-stop me-2"></i> Stop Scanning';
                    scanButton.classList.remove('btn-primary');
                    scanButton.classList.add('btn-warning');
                } else {
                    scanButton.disabled = false;
                    scanButton.innerHTML = '<i class="fas fa-play me-2"></i> Start Scanning';
                    scanButton.classList.remove('btn-warning');
                    scanButton.classList.add('btn-primary');
                }
            }
        }
        
        // Toggle RFID scanning
        function toggleRFIDScan() {
            if (isScanning) {
                stopRFIDScan();
            } else {
                startRFIDScan();
            }
        }
        
        function startRFIDScan() {
            const courseSelect = document.getElementById('courseSelect');
            if (!courseSelect || !courseSelect.value) {
                showAlert('Please select a course first', 'warning');
                return;
            }
            
                }
            } catch (error) {
                console.log('WebUSB error:', error);
                // Fall back to manual input
            }
        }
        
        // Set up RFID reader (placeholder for actual implementation)
        function setupRFIDReader(device) {
            // Implementation for specific RFID reader would go here
            console.log('Setting up RFID reader:', device);
            updateScannerStatus('ready', 'RFID Reader Connected', 'Scan a card to record attendance');
        }
        
        // Update scanner status UI
        function updateScannerStatus(status, message, subMessage) {
            scannerMessage.textContent = message;
            scannerSubMessage.textContent = subMessage;
            scannerIcon.className = 'fas fa-spinner fa-spin';
            
            if (status === 'ready') {
                scannerIcon.className = 'fas fa-rss';
            } else if (status === 'scanning') {
                scannerIcon.className = 'fas fa-spinner fa-spin';
            } else if (status === 'success') {
                scannerIcon.className = 'fas fa-check-circle';
            } else if (status === 'error') {
                scannerIcon.className = 'fas fa-exclamation-triangle';
            }
        }
        
        // Handle manual RFID submission
        function handleManualSubmit() {
            const rfid = manualRFID.value.trim();
            
            if (!rfid) {
                showToast('Please enter an RFID number', 'warning');
                return;
            }
            
            processRFIDScan(rfid);
        }
        
        // Handle RFID input from keyboard (for barcode scanners)
        function handleRFIDInput(e) {
            const rfid = e.target.value.trim();
            
            if (rfid && rfid.length >= 8) {
                processRFIDScan(rfid);
            }
        }
        
        // Process RFID scan
        async function processRFIDScan(rfid) {
            // Prevent multiple rapid scans
            const now = Date.now();
            if (now - lastScanTime < SCAN_COOLDOWN) {
                return;
            }
            
            lastScanTime = now;
            isScanning = false;
            
            // Update UI
            updateScannerStatus('scanning', 'Processing Card...', 'Please wait');
            scannerIcon.className = 'fas fa-spinner fa-spin';
            
            // Get current course ID
            const courseId = courseSelect ? courseSelect.value : null;
            
            try {
                // Call the rfid-scan.php API
                const response = await fetch('../api/rfid-scan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        rfid_uid: rfid,
                        attendance_type: currentAttendanceType,
                        course_id: courseId,
                        reader_id: 1 // Default reader ID
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Successfully recorded attendance
                    handleScanSuccess(result);
                } else if (result.status === 'unregistered') {
                    // Unregistered card
                    handleUnregisteredCard(rfid);
                } else {
                    // Other error
                    throw new Error(result.message || 'Failed to process RFID scan');
                }
            } catch (error) {
                console.error('Error processing RFID scan:', error);
                updateScannerStatus('error', 'Scan Failed', error.message || 'Please try again');
                showToast('Error: ' + (error.message || 'Failed to process scan'), 'error');
            } finally {
                // Re-enable scanning after a delay
                setTimeout(() => {
                    isScanning = true;
                    manualRFID.value = ''; // Clear the input field
                    
                    // Reset status if not already changed
                    if (!document.querySelector('.scanner-status.visible')) {
                    </div>
                </div>
            `;
            
            scanResult.innerHTML = resultHTML;
            scanResult.classList.add('show');
            
            showToast('Unregistered Card', 'This card is not registered in the system', 'warning');
        }
        
        // Handle successful scan
        function handleScanSuccess(data) {
            // Update UI with success message
            updateScannerStatus('success', 'Attendance Recorded', 'Successfully processed');
            
            // Show success message in the result area
            const resultHTML = `
                <div class="alert alert-success">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle fa-2x me-3"></i>
                        <div>
                            <h5 class="mb-1">${currentAttendanceType} Recorded</h5>
                            <p class="mb-1">${data.student_name || 'Unknown Student'}</p>
                            <p class="mb-0 small">${data.student_number || ''} • ${new Date().toLocaleTimeString()}</p>
                        </div>
                    </div>
                </div>
            `;
            
            scanResult.innerHTML = resultHTML;
            scanResult.classList.add('show');
            
            // Show success toast
            showToast(
                `${currentAttendanceType} Recorded`,
                `${data.student_name || 'Student'} (${data.student_number || 'N/A'})`,
                'success'
            );
            
            // Toggle attendance type for next scan
            toggleAttendanceType();
            
            // Load updated recent scans
            loadRecentScans();
        }
        
        // Toggle between Time In and Time Out
        function toggleAttendanceType() {
            currentAttendanceType = currentAttendanceType === 'Time In' ? 'Time Out' : 'Time In';
            
            // Update radio buttons
            if (currentAttendanceType === 'Time In') {
                timeInRadio.checked = true;
                timeOutRadio.checked = false;
            } else {
                timeInRadio.checked = false;
                timeOutRadio.checked = true;
            }
            
            // Update scanner status
            updateScannerStatus('ready', 'Ready to Scan', `Mode: ${currentAttendanceType}`);
        }
        
        // Set attendance type
        function setAttendanceType(type) {
            currentAttendanceType = type;
            updateScannerStatus('ready', 'Ready to Scan', `Mode: ${type}`);
        }
        
        // Show toast notification
        function showToast(title, message, type = 'info') {
            // You can implement a toast notification system here
            // For now, we'll just log to console
            console.log(`[${type.toUpperCase()}] ${title}: ${message}`);
            
            // Example with Toastify.js (uncomment if you have it included)
            /*
            Toastify({
                text: `<strong>${title}</strong><br>${message}`,
                duration: 3000,
                gravity: 'top',
                position: 'right',
                backgroundColor: type === 'success' ? '#28a745' : 
                                type === 'error' ? '#dc3545' : 
                                type === 'warning' ? '#ffc107' : '#17a2b8',
                stopOnFocus: true,
                className: 'toast-message',
                escapeMarkup: false
            }).showToast();
            */
        }
        
        // Load recent scans
        async function loadRecentScans() {
            try {
                const response = await fetch('get-recent-scans.php');
                const data = await response.json();
                
                if (data.success && data.scans) {
                    updateRecentScansTable(data.scans);
                }
            } catch (error) {
                console.error('Error loading recent scans:', error);
            }
        }
        
        // Update recent scans table
        function updateRecentScansTable(scans) {
            const tbody = document.querySelector('#recentScansTable tbody');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            
            scans.forEach(scan => {
                const row = document.createElement('tr');
                const date = new Date(scan.scan_time);
                const timeString = date.toLocaleTimeString();
                const dateString = date.toLocaleDateString();
                
                // Determine badge class based on attendance type
                const badgeClass = scan.attendance_type === 'Time In' ? 'bg-success' : 'bg-danger';
                
                row.innerHTML = `
                    <td>${scan.student_name || 'N/A'}</td>
                    <td>${scan.student_number || 'N/A'}</td>
                    <td>${scan.course_code || 'N/A'}</td>
                    <td><span class="badge ${badgeClass}">${scan.attendance_type || 'N/A'}</span></td>
                    <td>${timeString}</td>
                    <td>${dateString}</td>
                `;
                
                tbody.appendChild(row);
            });
        }
        
        // Register a new card (placeholder function)
        function registerCard(rfid) {
            // This would typically open a modal or redirect to a registration page
            showToast('Card Registration', `Redirecting to register card: ${rfid}`, 'info');
            // window.location.href = `register-card.php?rfid=${encodeURIComponent(rfid)}`;
        }
        
        // Initialize scanner when page loads
        document.addEventListener('DOMContentLoaded', () => {
            // Enable scanning
            isScanning = true;
            
            // Load initial recent scans
            loadRecentScans();
            
            // Set up manual RFID input handling
            const manualRFID = document.getElementById('manualRFID');
            if (manualRFID) {
                manualRFID.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        const rfid = manualRFID.value.trim();
                        if (rfid) {
                            processRFIDScan(rfid);
                            manualRFID.value = '';
                        }
                    }
                });
            }
            
            // Set up manual submit button
            const manualSubmit = document.getElementById('manualSubmit');
            if (manualSubmit) {
                manualSubmit.addEventListener('click', () => {
                    const rfid = manualRFID.value.trim();
                    if (rfid) {
                        processRFIDScan(rfid);
                        manualRFID.value = '';
                    }
                });
            }
        });
        
        // Stop RFID scanning
        function stopRFIDScan() {
            isScanning = false;
            updateScannerStatus('ready', 'Ready to Scan', 'Place the RFID card near the reader');
        }
        
        // Process RFID scan result
        async function processRFIDScan(rfid) {
            if (!rfid) {
                updateScannerStatus('error', 'Invalid RFID', 'Please enter a valid RFID number');
                return;
            }
            
            const courseId = courseSelect ? courseSelect.value : null;
            if (!courseId) {
                showToast('Error', 'Please select a course first', 'error');
                return;
            }
            
            // Prevent multiple rapid scans
            const now = Date.now();
            if (now - lastScanTime < SCAN_COOLDOWN) {
                return;
            }
            
            lastScanTime = now;
            isScanning = false;
            
            // Update UI
            updateScannerStatus('scanning', 'Processing Card...', 'Please wait');
            scannerIcon.className = 'fas fa-spinner fa-spin';
            
            try {
                // Call the rfid-scan.php API
                const response = await fetch('../api/rfid-scan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        rfid_uid: rfid,
                        attendance_type: currentAttendanceType,
                        course_id: courseId,
                        reader_id: 1 // Default reader ID
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Successfully recorded attendance
                    handleScanSuccess(result);
                } else if (result.status === 'unregistered') {
                    // Unregistered card
                    handleUnregisteredCard(rfid);
                } else {
                    // Other error
                    throw new Error(result.message || 'Failed to process RFID scan');
                }
                
                // Update UI with success
                updateScannerStatus('success', `Recorded ${currentAttendanceType} for ${data.student.name}`);
                
                // Add to recent scans
                addToRecentScans({
                    id: data.record_id,
                    student_name: data.student.name,
                    student_number: data.student.student_number,
                    type: currentAttendanceType,
                    time: data.timestamp
                });
                
            } catch (error) {
                console.error('Error processing RFID:', error);
                updateScannerStatus('error', error.message || 'Failed to process RFID');
            }
            if (!courseSelect.value) {
                showToast('Please select a course first!', 'error');
                return;
            }
            
            // Show scanning animation
            scanStatus.innerHTML = `
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h4 class="text-light mb-3">Processing...</h4>
                <p class="text-muted">Please wait while we process the RFID card.</p>
            `;
            
            try {
                const response = await fetch('scan-attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `rfid_uid=${encodeURIComponent(rfidUid)}&course_id=${encodeURIComponent(courseSelect.value)}&attendance_type=${encodeURIComponent(currentAttendanceType)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    const icon = currentAttendanceType === 'Time In' ? 'sign-in-alt' : 'sign-out-alt';
                    const color = currentAttendanceType === 'Time In' ? 'success' : 'danger';
                    
                    scanStatus.innerHTML = `
                        <i class="fas fa-${icon} scanner-icon text-${color} fa-beat"></i>
                        <h4 class="text-light mb-3">${currentAttendanceType} Recorded</h4>
                        <p class="text-muted">RFID: ${rfidUid}</p>
                        <p class="text-muted">Time: ${new Date().toLocaleTimeString()}</p>
                    `;
                    
                    showToast(result.message, 'success');
                    
                    // Reset after 3 seconds
                    setTimeout(() => {
                        scanStatus.innerHTML = `
                            <i class="fas fa-${icon} scanner-icon text-${color}"></i>
                            <h4 class="text-light mb-4">Ready to Scan ${currentAttendanceType}</h4>
                            <p class="text-muted">Please scan an RFID card to record ${currentAttendanceType.toLowerCase()}.</p>
                        `;
                    }, 3000);
                    
                } else {
                    // Show error message
                    scanStatus.innerHTML = `
                        <i class="fas fa-exclamation-triangle scanner-icon text-warning"></i>
                        <h4 class="text-light mb-3">Error</h4>
                        <p class="text-muted">${result.message || 'Failed to process RFID card.'}</p>
                    `;
                    
                    showToast(result.message || 'Failed to process RFID card.', 'error');
                    
                    // Reset after 3 seconds
                    setTimeout(() => {
                        const icon = currentAttendanceType === 'Time In' ? 'sign-in-alt' : 'sign-out-alt';
                        const color = currentAttendanceType === 'Time In' ? 'success' : 'danger';
                        scanStatus.innerHTML = `
                            <i class="fas fa-${icon} scanner-icon text-${color}"></i>
                            <h4 class="text-light mb-4">Ready to Scan ${currentAttendanceType}</h4>
                            <p class="text-muted">Please scan an RFID card to record ${currentAttendanceType.toLowerCase()}.</p>
                        `;
                    }, 3000);
                }
                
            } catch (error) {
                console.error('Error:', error);
                scanStatus.innerHTML = `
                    <i class="fas fa-exclamation-triangle scanner-icon text-danger"></i>
                    <h4 class="text-light mb-3">Error</h4>
                    <p class="text-muted">Failed to process request. Please try again.</p>
                `;
                
                showToast('Failed to process request. Please try again.', 'error');
                
                // Reset after 3 seconds
                setTimeout(() => {
                    const icon = currentAttendanceType === 'Time In' ? 'sign-in-alt' : 'sign-out-alt';
                    const color = currentAttendanceType === 'Time In' ? 'success' : 'danger';
                    scanStatus.innerHTML = `
                        <i class="fas fa-${icon} scanner-icon text-${color}"></i>
                        <h4 class="text-light mb-4">Ready to Scan ${currentAttendanceType}</h4>
                        <p class="text-muted">Please scan an RFID card to record ${currentAttendanceType.toLowerCase()}.</p>
                    `;
                }, 3000);
            }
        }
        
        // Function to load recent scans
        async function loadRecentScans() {
            try {
                const courseId = document.getElementById('courseSelect')?.value || '';
                const url = `get-recent-scans.php?limit=10${courseId ? `&course_id=${courseId}` : ''}`;
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success && Array.isArray(data.data)) {
                    updateRecentScansTable(data.data);
                } else {
                    console.error('Invalid data format from server:', data);
                    showToast('Failed to load recent scans: Invalid data format', 'error');
                }
            } catch (error) {
                console.error('Error loading recent scans:', error);
                showToast('Failed to load recent scans. Please try again.', 'error');
            }
        }
        
        // Function to update the recent scans table
        function updateRecentScansTable(scans) {
            const tbody = document.querySelector('#recentScansTable tbody');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            
            if (!scans || scans.length === 0) {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td colspan="6" class="text-center py-3">No recent scans found</td>';
                tbody.appendChild(tr);
                return;
            }
            
            scans.forEach(scan => {
                const tr = document.createElement('tr');
                tr.className = 'align-middle';
                tr.innerHTML = `
                    <td class="text-nowrap">${formatDateTime(scan.date)}</td>
                    <td>${scan.student_name || 'N/A'}</td>
                    <td class="text-nowrap">${scan.student_number || 'N/A'}</td>
                    <td class="small">${scan.course || 'N/A'}</td>
                    <td class="text-nowrap">
                        <span class="badge ${getBadgeClass(scan.type)}">
                            <i class="fas ${scan.type === 'Time In' ? 'fa-sign-in-alt' : 'fa-sign-out-alt'} me-1"></i>
                            ${scan.type || 'N/A'}
                        </span>
                    </td>
                    <td>
                        <span class="badge ${getStatusBadgeClass(scan.status)}">
                            ${scan.status || 'Pending'}
                        </span>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
        
        // Function to get badge class based on attendance type
        function getBadgeClass(type) {
            const typeLower = (type || '').toLowerCase();
            switch(typeLower) {
                case 'time in':
                    return 'bg-success bg-opacity-10 text-success border border-success border-opacity-25';
                case 'time out':
                    return 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25';
                default:
                    return 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25';
            }
        }
        
        // Function to get status badge class
        function getStatusBadgeClass(status) {
            const statusLower = (status || '').toLowerCase();
            switch(statusLower) {
                case 'present':
                    return 'bg-success bg-opacity-10 text-success border border-success border-opacity-25';
                case 'late':
                    return 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25';
                case 'absent':
                    return 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25';
                case 'excused':
                    return 'bg-info bg-opacity-10 text-info border border-info border-opacity-25';
                default:
                    return 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25';
            }
        }
        
        // Function to show alert messages
        function showAlert(message, type = 'info') {
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show mt-3`;
            alertDiv.role = 'alert';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            // Add to the page
            const container = document.querySelector('.container-fluid');
            if (container) {
                container.insertBefore(alertDiv, container.firstChild);
                
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alertDiv);
                    bsAlert.close();
                }, 5000);
            }
        }
        // Format datetime for display
        function formatDateTime(datetimeString) {
            if (!datetimeString) return 'N/A';
            try {
                const date = new Date(datetimeString);
                if (isNaN(date.getTime())) return 'Invalid date';
                
                // Format time (e.g., '2:30 PM')
                const timeStr = date.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
                
                // Format date (e.g., 'Mar 15, 2023')
                const dateStr = date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                
                return `${dateStr}<br><small class="text-muted">${timeStr}</small>`;
            } catch (e) {
                console.error('Error formatting date:', e);
                return 'Invalid date';
            }
        }
        
        // Function for manual scan test
        function manualScanTest() {
            const manualUid = prompt('Enter RFID UID for testing (e.g., 3870578740):', '');
            if (manualUid) {
                processRFIDScan(manualUid.trim());
            }
            if (manualUid) {
                processRFIDScan(manualUid);
            }
        }
        
        // Function to show toast notifications
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toastify-content ${type} shadow`;
            toast.innerHTML = `
                <div class="toastify-body">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} me-2"></i>
                    ${message}
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Add show class
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            // Remove after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 3000);
        }
        
        // Add CSS for toast notifications
        const style = document.createElement('style');
        style.textContent = `
            .toastify-content {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
                z-index: 9999;
                max-width: 350px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                display: flex;
                align-items: center;
            }
            .toastify-content.show {
                opacity: 1;
                transform: translateX(0);
            }
            .toastify-content.success {
                background: #28a745;
            }
            .toastify-content.error {
                background: #dc3545;
            }
            .toastify-content.info {
                background: #17a2b8;
            }
            .toastify-body {
                display: flex;
                align-items: center;
            }
            .toastify-body i {
                margin-right: 8px;
                font-size: 1.2em;
            }
            .scanner-icon {
                font-size: 4rem;
                margin-bottom: 1rem;
            }
            .fa-beat {
                animation: fa-beat 1s infinite;
            }
            @keyframes fa-beat {
                0% { transform: scale(1); }
                50% { transform: scale(1.2); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
        
        /* Scanner Card Styles */
        .scanner-card {
            background: #2d2d2d;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border: 1px solid #444;
            position: relative;
            overflow: hidden;
        }
        
        /* Scanner Animation */
        .scanner-animation {
            width: 100%;
            height: 200px;
            background: #1a1a1a;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
            border: 2px solid #444;
            margin: 1.5rem 0;
        }
        
        .scanner-line {
            position: absolute;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, transparent, #007bff, transparent);
            box-shadow: 0 0 10px #007bff, 0 0 20px #007bff;
            top: 0;
            animation: scan 2s linear infinite;
        }
        
        @keyframes scan {
            0% { top: 0; opacity: 0; }
            5% { opacity: 1; }
            95% { opacity: 1; }
            100% { top: 100%; opacity: 0; }
        }
        
        /* Scanner Status */
        #scanStatus {
            transition: all 0.3s ease;
            padding: 1.5rem;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.2);
            margin-bottom: 1.5rem;
        }
        
        .scanner-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            text-shadow: 0 0 15px rgba(0, 123, 255, 0.5);
            transition: all 0.3s ease;
        }
        
        /* Mode Buttons */
        .attendance-mode {
            padding: 0.6rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .attendance-mode.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
        }
        
        .attendance-mode:not(.active):hover {
            background: #333;
            color: #fff;
        }
        
        /* Scan Result */
        #scanResult {
            transition: all 0.3s ease;
            max-height: 0;
            overflow: hidden;
            opacity: 0;
        }
        
        #scanResult.show {
            max-height: 1000px;
            opacity: 1;
        }
        
        /* Form Styles */
        .form-control, .form-select {
            background-color: #2d2d2d;
            border: 1px solid #444;
            color: #fff;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: #333;
            color: #fff;
            border-color: #007bff;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }
        
        .form-label {
            color: #aaa;
            margin-bottom: 0.5rem;
        }
        
        /* Badges */
        .badge {
            font-weight: 500;
            padding: 0.5em 0.8em;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .scanner-card {
                padding: 1.5rem;
            }
            
            .scanner-animation {
                height: 150px;
            }
            
            .btn-group {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-group .btn {
                width: 100%;
                margin: 0.25rem 0;
                border-radius: 0.375rem !important;
            }
        }
    </script>
</body>
</html>