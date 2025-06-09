<?php
session_start();

// Check if user is logged in and is an instructor
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

// Include database connection
require_once '../config/database.php';
global $conn;

// Verify database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? "Connection variable not set"));
}

// Get instructor's courses
$instructor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT c.* 
    FROM courses c
    INNER JOIN instructor_courses ic ON c.course_id = ic.course_id
    WHERE ic.instructor_id = ? AND c.is_active = 1
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Attendance Scanner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #1a1a1a; color: #fff; }
        .card { background: #2a2a2a; border: none; }
        .card-header { background: #333; color: #fff; }
        .scanner-icon { font-size: 3rem; margin-bottom: 1rem; }
        .table { color: #fff; }
        .table-dark { background: #2a2a2a; }
        .badge { font-size: 0.9em; }
        .btn-group .btn { border: 1px solid #444; }
        .btn-group .btn.active { background: #007bff; border-color: #007bff; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12 p-4">
                <!-- Scanner Interface -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-wifi me-2"></i>RFID Scanner
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <!-- Course Selection -->
                        <div class="mb-4">
                            <select id="courseSelect" class="form-select bg-dark text-light mb-3">
                                <option value="">Select Course</option>
                                <?php foreach($courses as $course): ?>
                                    <option value="<?= $course['course_id'] ?>"><?= $course['course_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Attendance Mode Buttons -->
                        <div class="btn-group mb-4" role="group">
                            <button type="button" class="btn btn-outline-primary attendance-mode active" data-mode="Time In">
                                <i class="fas fa-sign-in-alt me-2"></i>Time In
                            </button>
                            <button type="button" class="btn btn-outline-primary attendance-mode" data-mode="Time Out">
                                <i class="fas fa-sign-out-alt me-2"></i>Time Out
                            </button>
                        </div>

                        <!-- Scanner Status -->
                        <div id="scanStatus">
                            <i class="fas fa-wifi scanner-icon text-primary"></i>
                            <h4 class="text-light mb-4">RFID Scanner Ready</h4>
                            <p class="text-muted">Please scan your RFID card</p>
                        </div>

                        <!-- Scan Result -->
                        <div id="scanResult" class="d-none"></div>
                    </div>
                </div>

                <!-- Attendance Records -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-list me-2"></i>Attendance Records
                        </h5>
                        <input type="date" id="attendanceDate" class="form-control bg-dark text-light" style="width: auto;" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-dark table-striped">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Student Name</th>
                                        <th>Student Number</th>
                                        <th>Department</th>
                                        <th>Course</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="attendanceRecords">
                                    <!-- Attendance records will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let currentPage = 0;
        const pageSize = 10;

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Load initial attendance data
            loadAttendanceData();
            
            // Initialize RFID scanning
            initializeRFIDScanning();

            // Add event listeners
            document.getElementById('attendanceDate').addEventListener('change', function() {
                currentPage = 0;
                loadAttendanceData();
            });

            document.getElementById('courseSelect').addEventListener('change', loadAttendanceData);

            // Add listeners for attendance mode buttons
            document.querySelectorAll('.attendance-mode').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelector('.attendance-mode.active').classList.remove('active');
                    this.classList.add('active');
                });
            });
        });

        function initializeRFIDScanning() {
            let rfidInput = '';
            let lastKeyTime = 0;
            const RFID_TIMEOUT = 500;

            document.addEventListener('keypress', function(e) {
                const currentTime = new Date().getTime();
                if (currentTime - lastKeyTime > RFID_TIMEOUT) {
                    rfidInput = '';
                }
                lastKeyTime = currentTime;

                if (e.key !== 'Enter') {
                    rfidInput += e.key;
                } else if (rfidInput) {
                    processRFIDScan(rfidInput);
                    rfidInput = '';
                }
            });
        }

        function processRFIDScan(rfidUid) {
            const courseId = document.getElementById('courseSelect').value;
            if (!courseId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Course Not Selected',
                    text: 'Please select a course before scanning.',
                });
                return;
            }

            const attendanceType = document.querySelector('.attendance-mode.active').dataset.mode;
            
            fetch('../api/rfid-scan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    rfid_uid: rfidUid,
                    attendance_type: attendanceType,
                    course_id: courseId
                })
            })
            .then(response => response.json())
            .then(data => {
                handleScanResult(data);
                loadAttendanceData();
            })
            .catch(error => {
                console.error('Error:', error);
                handleScanError(error);
            });
        }

        function handleScanResult(result) {
            const scanStatus = document.getElementById('scanStatus');
            const scanResult = document.getElementById('scanResult');
            
            scanResult.classList.remove('d-none');
            
            if (result.success) {
                scanResult.innerHTML = `
                    <div class="alert alert-success mb-3">
                        <h4 class="alert-heading">
                            <i class="fas fa-check-circle me-2"></i>Success!
                        </h4>
                        <p class="mb-0">${result.message}</p>
                        ${result.data.student_name ? `<p class="mb-0"><strong>Student:</strong> ${result.data.student_name}</p>` : ''}
                        ${result.data.student_number ? `<p class="mb-0"><strong>ID:</strong> ${result.data.student_number}</p>` : ''}
                        ${result.data.attendance_type ? `<p class="mb-0"><strong>Type:</strong> ${result.data.attendance_type}</p>` : ''}
                    </div>
                `;
            } else {
                scanResult.innerHTML = `
                    <div class="alert alert-danger mb-3">
                        <h4 class="alert-heading">
                            <i class="fas fa-exclamation-circle me-2"></i>Error
                        </h4>
                        <p class="mb-0">${result.message}</p>
                    </div>
                `;
            }

            // Reset scanner status after 3 seconds
            setTimeout(() => {
                scanStatus.innerHTML = `
                    <i class="fas fa-wifi scanner-icon text-primary"></i>
                    <h4 class="text-light mb-4">RFID Scanner Ready</h4>
                    <p class="text-muted">Please scan your RFID card</p>
                `;
                scanResult.classList.add('d-none');
            }, 3000);
        }

        function loadAttendanceData() {
            const attendanceDate = document.getElementById('attendanceDate').value;
            const courseId = document.getElementById('courseSelect').value;
            const tbody = document.getElementById('attendanceRecords');
            
            // Show loading indicator
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>
                        Loading attendance records...
                    </td>
                </tr>
            `;
            
            fetch(`../api/get-attendance.php?date=${attendanceDate}&course_id=${courseId}`)
                .then(response => response.json())
                .then(data => {
                    tbody.innerHTML = '';
                    
                    if (data.success && data.data && data.data.length > 0) {
                        data.data.forEach(record => {
                            const row = document.createElement('tr');
                            const scanTime = new Date(record.scan_time).toLocaleTimeString();
                            const studentName = `${record.first_name} ${record.last_name}`;
                            const badgeClass = record.attendance_type === 'Time In' ? 'bg-success' : 'bg-danger';
                            
                            row.innerHTML = `
                                <td>${scanTime}</td>
                                <td>${studentName}</td>
                                <td>${record.student_number}</td>
                                <td>${record.department_name || 'N/A'}</td>
                                <td>${record.course_name || 'N/A'}</td>
                                <td><span class="badge ${badgeClass}">${record.attendance_type}</span></td>
                                <td><span class="badge bg-success">Verified</span></td>
                            `;
                            tbody.appendChild(row);
                        });
                    } else {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                    No attendance records found
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading attendance:', error);
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center text-danger py-4">
                                <i class="fas fa-exclamation-circle fa-2x mb-2"></i><br>
                                Error loading attendance records
                            </td>
                        </tr>
                    `;
                });
        }
    </script>
</body>
</html>