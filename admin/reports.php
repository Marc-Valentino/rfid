<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

$attendance = new AttendanceSystem();
$attendance->requireLogin();

$page_title = 'Attendance Reports';

// Get departments for filter
try {
    $database = new Database();
    $pdo = $database->getConnection();
    $departments = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name")->fetchAll();
} catch (Exception $e) {
    $error_msg = "Database error: " . $e->getMessage();
}

// Default report parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'daily';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;

// Get current time
$current_time = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
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
    </style>
    <!-- Add jsPDF library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
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
                    <a class="nav-link" href="rfid-cards.php">
                        <i class="fas fa-id-card me-2"></i>RFID Cards
                    </a>
                    <a class="nav-link active" href="reports.php">
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
                    <h2><i class="fas fa-chart-bar me-2"></i>Attendance Reports</h2>
                    <div class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('F d, Y - h:i A'); ?>
                    </div>
                </div>
                
                <!-- Report Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i>Report Filters
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Report Type</label>
                                <select class="form-select bg-dark text-light border-secondary" name="type" id="reportType">
                                    <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>Daily Attendance</option>
                                    <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly Summary</option>
                                    <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Attendance Overview</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">From Date</label>
                                <input type="date" class="form-control bg-dark text-light border-secondary"
                                       name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-3" id="toDateContainer">
                                <label class="form-label">To Date</label>
                                <input type="date" class="form-control bg-dark text-light border-secondary"
                                       name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Department</label>
                                <select class="form-select bg-dark text-light border-secondary" name="department_id">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo $department['department_id']; ?>"
                                                <?php echo $department_id == $department['department_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($department['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter me-1"></i>Generate Report
                                </button>
                                <a href="reports.php" class="btn btn-secondary">
                                    <i class="fas fa-sync-alt me-1"></i>Reset
                                </a>
                                <button type="button" class="btn btn-outline-secondary ms-2" id="printBtn">
                                    <i class="fas fa-file-pdf me-1"></i>View PDF
                                </button>
                                <button type="button" class="btn btn-outline-secondary ms-2" id="exportBtn">
                                    <i class="fas fa-download me-1"></i>Download PDF
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Report Content -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-table me-2"></i><span id="reportTitle">Attendance Report</span></h5>
                        <div>
                            <span class="badge bg-primary" id="reportDateRange">
                                <?php echo date('M d, Y', strtotime($date_from)); ?>
                                <?php if ($date_from != $date_to): ?>
                                    - <?php echo date('M d, Y', strtotime($date_to)); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-dark table-striped" id="reportTable">
                                <thead id="reportTableHead">
                                    <!-- Headers will be set dynamically -->
                                </thead>
                                <tbody id="reportTableBody">
                                    <tr>
                                        <td colspan="10" class="text-center py-4">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p class="mt-2">Loading report data...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize report on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadReportData();
            
            // Handle report type change
            document.getElementById('reportType').addEventListener('change', function() {
                const reportType = this.value;
                const toDateContainer = document.getElementById('toDateContainer');
                
                // Hide to_date for summary reports
                if (reportType === 'summary') {
                    toDateContainer.style.display = 'none';
                } else {
                    toDateContainer.style.display = 'block';
                }
            });
            
            // Trigger change event to set initial state
            document.getElementById('reportType').dispatchEvent(new Event('change'));
        });
        
        // Print to PDF functionality
        document.getElementById('printBtn').addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Add report header
            doc.setFontSize(18);
            doc.text('Attendance Report', 105, 15, { align: 'center' }); // Center align the title
            
            // Add date with proper alignment
            doc.setFontSize(12);
            const dateRange = document.getElementById('reportDateRange').textContent;
            
            // Create date line with proper spacing
            doc.text('Date:', 20, 25);
            doc.text(dateRange, -6, 20); // Reduced x position from 35 to 30 to bring date closer
            
            // Generate PDF with table
            doc.autoTable({
                html: '#reportTable',
                startY: 35,
                theme: 'grid',
                headStyles: {
                    fillColor: [136, 0, 0],
                    textColor: 255
                },
                styles: {
                    fontSize: 10,
                    cellPadding: 3
                }
            });
            
            // Open PDF in new window
            doc.output('dataurlnewwindow');
        });
        
        // Update the download button with the same date formatting
        document.getElementById('exportBtn').addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Add report header
            doc.setFontSize(18);
            doc.text('Attendance Report', 14, 15);
            
            // Add date range with proper spacing
            doc.setFontSize(12);
            const dateRange = document.getElementById('reportDateRange').textContent;
            doc.text('Date:', 14, 25);
            doc.text(dateRange, 35, 25); // Adjusted x position to align after "Date:"
            
            // Get current timestamp for filename
            const timestamp = new Date().toISOString().slice(0,10);
            
            // Generate PDF with table
            doc.autoTable({
                html: '#reportTable',
                startY: 35,
                theme: 'grid',
                headStyles: {
                    fillColor: [41, 128, 185],
                    textColor: 255
                },
                styles: {
                    fontSize: 10,
                    cellPadding: 3
                }
            });
            
            // Download PDF
            doc.save(`attendance_report_${timestamp}.pdf`);
        });
        
        // Load report data
        function loadReportData() {
            const reportType = document.getElementById('reportType').value;
            const dateFrom = document.querySelector('input[name="date_from"]').value;
            const dateTo = document.querySelector('input[name="date_to"]').value;
            const departmentId = document.querySelector('select[name="department_id"]').value;
            
            // Build query parameters based on report type
            let queryParams = `type=${reportType}&department_id=${departmentId}`;
            
            // Only add date parameters for non-daily reports
            if (reportType !== 'daily') {
                queryParams += `&date_from=${dateFrom}&date_to=${dateTo}`;
            }
            
            // Fetch report data
            fetch(`../api/reports-data.php?${queryParams}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Report Data:', data);
                    if (data.success) {
                        renderReportTable(reportType, data.data);
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Update the colspan in error messages
                    document.getElementById('reportTableBody').innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center text-danger py-4">
                                <i class="fas fa-exclamation-triangle me-2"></i>${error.message || 'Error loading report data.'}
                            </td>
                        </tr>
                    `;
                });
        }
        
        // Render report table based on type
        function renderReportTable(reportType, data) {
            const tableHead = document.getElementById('reportTableHead');
            const tableBody = document.getElementById('reportTableBody');
            
            // Clear previous content
            tableHead.innerHTML = '';
            tableBody.innerHTML = '';
            
            // Create table headers based on report type
            let headers = [];
            switch(reportType) {
                case 'daily':
                    // Remove 'Hours' and 'Late (min)' from headers
                    headers = ['Student ID', 'Name', 'Department', 'Date', 'Time In', 'Time Out', 'Status'];
                    break;
                case 'monthly':
                    headers = ['Student ID', 'Name', 'Department', 'Month', 'Total Days', 'Present', 'Absent', 'Late', 'Attendance %'];
                    break;
                case 'summary':
                    headers = ['Total Students', 'Present Today', 'Absent Today', 'Late Today', 'Avg. Hours'];
                    break;
            }
            
            // Add headers to table
            const headerRow = document.createElement('tr');
            headers.forEach(header => {
                const th = document.createElement('th');
                th.textContent = header;
                headerRow.appendChild(th);
            });
            tableHead.appendChild(headerRow);
            
            // Add data rows
            if (data.length === 0) {
                const emptyRow = document.createElement('tr');
                // Update the loading message colspan
                const emptyCell = document.createElement('td');
                emptyCell.colSpan = 7; // Update from 9 to 7
                emptyCell.className = 'text-center py-4';
                emptyCell.innerHTML = '<i class="fas fa-inbox fa-3x text-muted mb-3"></i><p class="text-muted">No data available for the selected criteria.</p>';
                emptyRow.appendChild(emptyCell);
                tableBody.appendChild(emptyRow);
                return;
            }
            
            // Render data based on report type
            switch(reportType) {
                case 'daily':
                    data.forEach(record => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td><strong>${record.student_number || 'N/A'}</strong></td>
                            <td>${record.student_name || 'N/A'}</td>
                            <td>${record.department_name || 'N/A'}</td>
                            <td>${formatDate(record.attendance_date)}</td>
                            <td>${record.first_time_in || 'N/A'}</td>
                            <td>${record.last_time_out || 'N/A'}</td>
                            <td>
                                <span class="badge ${getStatusBadgeClass(record.attendance_status)}">
                                    ${record.attendance_status}
                                </span>
                            </td>
                        `;
                        tableBody.appendChild(row);
                    });
                    break;
                    
                case 'monthly':
                    data.forEach(record => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td><strong>${record.student_number}</strong></td>
                            <td>${record.student_name}</td>
                            <td>${record.department_name || 'N/A'}</td>
                            <td>${getMonthName(record.month)} ${record.year}</td>
                            <td>${record.total_days}</td>
                            <td>${record.present_days}</td>
                            <td>${record.absent_days}</td>
                            <td>${record.late_days}</td>
                            <td>
                                <div class="progress bg-dark">
                                    <div class="progress-bar ${getAttendanceProgressClass(record.attendance_percentage)}" 
                                         role="progressbar" style="width: ${record.attendance_percentage}%" 
                                         aria-valuenow="${record.attendance_percentage}" aria-valuemin="0" aria-valuemax="100">
                                        ${record.attendance_percentage}%
                                    </div>
                                </div>
                            </td>
                        `;
                        tableBody.appendChild(row);
                    });
                    break;
                    
                case 'summary':
                    const record = data[0]; // Summary has only one row
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><strong>${record.total_students}</strong></td>
                        <td><span class="badge bg-success">${record.present_today}</span></td>
                        <td><span class="badge bg-danger">${record.absent_today}</span></td>
                        <td><span class="badge bg-warning">${record.late_today}</span></td>
                        <td><strong>${parseFloat(record.avg_hours).toFixed(2)} hrs</strong></td>
                    `;
                    tableBody.appendChild(row);
                    break;
            }
        }
        
        // Helper functions
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
        }
        
        function getMonthName(monthNum) {
            const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            return months[parseInt(monthNum) - 1];
        }
        
        function getStatusBadgeClass(status) {
            switch(status) {
                case 'Present': return 'bg-success';
                case 'Absent': return 'bg-danger';
                case 'Late': return 'bg-warning';
                default: return 'bg-secondary';
            }
        }
        
        function getAttendanceProgressClass(percentage) {
            if (percentage >= 90) return 'bg-success';
            if (percentage >= 75) return 'bg-info';
            if (percentage >= 50) return 'bg-warning';
            return 'bg-danger';
        }
    </script>
</body>
</html>
