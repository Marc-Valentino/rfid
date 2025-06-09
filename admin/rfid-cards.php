<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Initialize AttendanceSystem
$attendance = new AttendanceSystem();
$attendance->requireLogin();

$page_title = 'RFID Cards Management';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Assign card to student
        if (isset($_POST['assign_card'])) {
            $student_id = (int)$_POST['student_id'];
            $rfid_uid = $attendance->sanitizeInput($_POST['rfid_uid']);
            
            // Check if card already exists
            $check_sql = "SELECT * FROM rfid_cards WHERE rfid_uid = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$rfid_uid]);
            
            if ($check_stmt->rowCount() > 0) {
                $card = $check_stmt->fetch();
                if ($card['student_id'] != $student_id) {
                    // Update existing card
                    $update_sql = "UPDATE rfid_cards SET student_id = ?, card_status = 'Active', 
                                   issue_date = CURRENT_DATE, last_updated = NOW() WHERE rfid_uid = ?";
                    $update_stmt = $pdo->prepare($update_sql);
                    $update_stmt->execute([$student_id, $rfid_uid]);
                    $success_msg = "RFID card reassigned successfully!";
                } else {
                    $error_msg = "This card is already assigned to this student.";
                }
            } else {
                // Insert new card
                $insert_sql = "INSERT INTO rfid_cards (rfid_uid, student_id, card_status, issue_date, last_updated) 
                              VALUES (?, ?, 'Active', CURRENT_DATE, NOW())";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute([$rfid_uid, $student_id]);
                $success_msg = "RFID card assigned successfully!";
            }
        }
        
        // Deactivate card
        if (isset($_POST['deactivate_card'])) {
            $rfid_uid = $attendance->sanitizeInput($_POST['rfid_uid']);
            
            $update_sql = "UPDATE rfid_cards SET card_status = 'Inactive', last_updated = NOW() WHERE rfid_uid = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$rfid_uid]);
            $success_msg = "RFID card deactivated successfully!";
        }
        
        // Activate card
        if (isset($_POST['activate_card'])) {
            $rfid_uid = $attendance->sanitizeInput($_POST['rfid_uid']);
            
            $update_sql = "UPDATE rfid_cards SET card_status = 'Active', last_updated = NOW() WHERE rfid_uid = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$rfid_uid]);
            $success_msg = "RFID card activated successfully!";
        }
        
        // Delete card
        if (isset($_POST['delete_card'])) {
            $rfid_uid = $attendance->sanitizeInput($_POST['rfid_uid']);
            
            $delete_sql = "DELETE FROM rfid_cards WHERE rfid_uid = ?";
            $delete_stmt = $pdo->prepare($delete_sql);
            $delete_stmt->execute([$rfid_uid]);
            $success_msg = "RFID card deleted successfully!";
        }
        
    } catch (Exception $e) {
        $error_msg = "Database error: " . $e->getMessage();
    }
}

// Get RFID cards with student info
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get filter values
    $status = isset($_GET['status']) ? $attendance->sanitizeInput($_GET['status']) : '';
    $search = isset($_GET['search']) ? $attendance->sanitizeInput($_GET['search']) : '';
    
    // Build query
    $where_conditions = [];
    $params = [];
    
    if ($status) {
        $where_conditions[] = "r.card_status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $where_conditions[] = "(r.rfid_uid LIKE ? OR s.student_number LIKE ? OR 
                              s.first_name LIKE ? OR s.last_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $sql = "
        SELECT r.*, s.student_id, s.student_number, s.first_name, s.last_name, 
               d.department_name, c.course_name
        FROM rfid_cards r
        LEFT JOIN students s ON r.student_id = s.student_id
        LEFT JOIN departments d ON s.department_id = d.department_id
        LEFT JOIN courses c ON s.course_id = c.course_id
        $where_clause
        ORDER BY r.last_updated DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rfid_cards = $stmt->fetchAll();
    
    // Get students for dropdown
    $students_sql = "SELECT student_id, student_number, first_name, last_name 
                    FROM students WHERE is_active = 1 ORDER BY last_name, first_name";
    $students = $pdo->query($students_sql)->fetchAll();
    
} catch (Exception $e) {
    $error_msg = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Cards - <?php echo APP_NAME; ?></title>
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
            background: rgba(45, 45, 45, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .form-select {
            background-color: rgba(0, 0, 0, 0.8);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-select:focus {
            background-color: rgba(0, 0, 0, 0.9);
            color: #ffffff;
            border-color: #007bff;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }

        .form-select option {
            background-color: #1a1a1a;
            color: #ffffff;
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
                    <i class="fas fa-id-card-alt fa-2x text-primary mb-2"></i>
                    <h5>RFID System</h5>
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
                    <a class="nav-link" href="register-instructor.php">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Instructors
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-user-cog me-2"></i>Users
                    </a>
                    <a class="nav-link" href="attendance.php">
                        <i class="fas fa-clock me-2"></i>Attendance
                    </a>
                    <a class="nav-link active" href="rfid-cards.php">
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
                    <h2><i class="fas fa-id-card me-2"></i>RFID Cards Management</h2>
                    <div class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('F d, Y - h:i A'); ?>
                    </div>
                </div>
                
                <?php if (isset($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="d-flex justify-content-end mb-4">
                    <button type="button" class="btn btn-outline-secondary me-2" id="printBtn">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                    <button type="button" class="btn btn-outline-secondary me-2" id="exportBtn">
                        <i class="fas fa-file-export me-1"></i>Export
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignCardModal">
                        <i class="fas fa-plus me-1"></i>Assign New Card
                    </button>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="Active" <?php echo $status == 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo $status == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="Lost" <?php echo $status == 'Lost' ? 'selected' : ''; ?>>Lost</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control"
                                       name="search" value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="RFID UID, Student ID or Name">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                                <a href="rfid-cards.php" class="btn btn-secondary">
                                    <i class="fas fa-sync-alt me-1"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- RFID Cards Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>RFID Cards</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($rfid_cards)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-id-card fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No RFID cards found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>RFID UID</th>
                                            <th>Student</th>
                                            <th>Department</th>
                                            <th>Course</th>
                                            <th>Status</th>
                                            <th>Issue Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rfid_cards as $card): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($card['rfid_uid']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($card['student_id']): ?>
                                                        <strong><?php echo htmlspecialchars($card['first_name'] . ' ' . $card['last_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($card['student_number']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($card['department_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($card['course_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge <?php 
                                                        if ($card['card_status'] == 'Active') echo 'bg-success';
                                                        elseif ($card['card_status'] == 'Inactive') echo 'bg-secondary';
                                                        else echo 'bg-danger';
                                                    ?>">
                                                        <?php echo htmlspecialchars($card['card_status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($card['issue_date'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary edit-card"
                                                                data-bs-toggle="modal" data-bs-target="#assignCardModal"
                                                                data-rfid="<?php echo $card['rfid_uid']; ?>"
                                                                data-student="<?php echo $card['student_id'] ?? ''; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        
                                                        <?php if ($card['card_status'] == 'Active'): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="rfid_uid" value="<?php echo $card['rfid_uid']; ?>">
                                                                <button type="submit" name="deactivate_card" class="btn btn-outline-warning"
                                                                        onclick="return confirm('Are you sure you want to deactivate this card?')">
                                                                    <i class="fas fa-ban"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="rfid_uid" value="<?php echo $card['rfid_uid']; ?>">
                                                                <button type="submit" name="activate_card" class="btn btn-outline-success"
                                                                        onclick="return confirm('Are you sure you want to activate this card?')">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="rfid_uid" value="<?php echo $card['rfid_uid']; ?>">
                                                            <button type="submit" name="delete_card" class="btn btn-outline-danger"
                                                                    onclick="return confirm('Are you sure you want to delete this card? This action cannot be undone.')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Assign Card Modal -->
    <div class="modal fade" id="assignCardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignCardModalTitle">Assign RFID Card</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="assignCardForm" method="POST">
                        <div class="mb-3">
                            <label for="rfid_uid" class="form-label">RFID UID</label>
                            <div class="input-group">
                                <input type="text" class="form-control" 
                                       id="rfid_uid" name="rfid_uid" required>
                                <button type="button" class="btn btn-outline-primary" id="scanRfidBtn">
                                    <i class="fas fa-wifi"></i> Scan
                                </button>
                            </div>
                            <small class="form-text text-muted">Enter the RFID card's unique identifier or click Scan to use a reader.</small>
                        </div>
                        <div class="mb-3">
                            <label for="student_id" class="form-label">Student</label>
                            <select class="form-select" id="student_id" name="student_id" required>
                                <option value="">Select Student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['student_id']; ?>">
                                        <?php echo htmlspecialchars($student['student_number'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="assign_card" value="1">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="assignCardForm" class="btn btn-primary">Assign Card</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- RFID Scanner Modal -->
    <div class="modal fade" id="rfidScannerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-wifi"></i> RFID Scanner
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="scanStatus"></div>
                    <div id="scanResult" class="d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="startScanning">Start Scanning</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Print functionality
        document.getElementById('printBtn').addEventListener('click', function() {
            window.print();
        });
        
        // Export to CSV functionality
        document.getElementById('exportBtn').addEventListener('click', function() {
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = '../api/student-data.php';
            form.target = '_blank';
            
            const status = document.querySelector('select[name="status"]').value;
            const search = document.querySelector('input[name="search"]').value;
            
            const statusField = document.createElement('input');
            statusField.type = 'hidden';
            statusField.name = 'status';
            statusField.value = status;
            form.appendChild(statusField);
            
            const searchField = document.createElement('input');
            searchField.type = 'hidden';
            searchField.name = 'search';
            searchField.value = search;
            form.appendChild(searchField);
            
            const exportField = document.createElement('input');
            exportField.type = 'hidden';
            exportField.name = 'export_rfid';
            exportField.value = 'csv';
            form.appendChild(exportField);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        });
        
        // Edit card functionality
        document.querySelectorAll('.edit-card').forEach(button => {
            button.addEventListener('click', function() {
                const rfidUid = this.getAttribute('data-rfid');
                const studentId = this.getAttribute('data-student');
                
                document.getElementById('rfid_uid').value = rfidUid;
                document.getElementById('student_id').value = studentId;
                document.getElementById('assignCardModalTitle').textContent = 'Edit RFID Card Assignment';
            });
        });
        
        // Reset modal on close
        document.getElementById('assignCardModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('assignCardForm').reset();
            document.getElementById('assignCardModalTitle').textContent = 'Assign RFID Card';
        });
        
        // RFID Scan button
        document.getElementById('scanRfidBtn').addEventListener('click', function() {
            const scanModal = new bootstrap.Modal(document.getElementById('rfidScannerModal'));
            scanModal.show();
            
            window.addEventListener('rfidScanned', function(e) {
                document.getElementById('rfid_uid').value = e.detail.rfidUid;
                scanModal.hide();
            }, { once: true });
        });
        
        // Initialize RFID scanning
        function initializeRFIDScan() {
            const scanStatus = document.getElementById('scanStatus');
            const scanResult = document.getElementById('scanResult');
            
            scanStatus.innerHTML = '<i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i><p>Scanning... Please place the RFID card on the reader.</p>';
            
            fetch('../api/rfid-scan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    rfid_uid: 'simulated_scan',
                    mode: 'read_only'
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        scanStatus.innerHTML = '<i class="fas fa-check-circle fa-3x text-success mb-3"></i><p>Card detected!</p>';
                        scanResult.innerHTML = `<div class="alert alert-success">RFID UID: ${data.rfid_uid}</div>`;
                        scanResult.classList.remove('d-none');
                        
                        window.dispatchEvent(new CustomEvent('rfidScanned', {
                            detail: { rfidUid: data.rfid_uid }
                        }));
                    } else {
                        scanStatus.innerHTML = '<i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i><p>Error: ' + data.message + '</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    scanStatus.innerHTML = '<i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i><p>Error connecting to RFID reader.</p>';
                });
        }
        
        // Handle Start Scanning button click
        document.getElementById('startScanning').addEventListener('click', function() {
            initializeRFIDScan();
        });
    </script>
    <script src="../assets/js/rfid-reader.js"></script>
</body>
</html>