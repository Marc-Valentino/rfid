<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

$page_title = 'System Settings';
$success_msg = '';
$error_msg = '';

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

// Get current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Change Password
        if (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            // Validate input
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error_msg = 'All password fields are required.';
            } elseif ($new_password !== $confirm_password) {
                $error_msg = 'New passwords do not match.';
            } elseif (strlen($new_password) < 8) {
                $error_msg = 'Password must be at least 8 characters long.';
            } elseif (!preg_match('/[A-Z]/', $new_password) || 
                      !preg_match('/[a-z]/', $new_password) || 
                      !preg_match('/[0-9]/', $new_password)) {
                $error_msg = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
            } else {
                // Verify current password
                if (password_verify($current_password, $current_user['password_hash'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    
                    // Log the password change
                    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, 'password_change', 'users', ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
                    
                    $success_msg = 'Password changed successfully!';
                } else {
                    $error_msg = 'Current password is incorrect.';
                }
            }
        }

        // Update Profile
        if (isset($_POST['update_profile'])) {
            $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
            $full_name = trim($_POST['full_name']);

            if (!$email) {
                $error_msg = 'Please enter a valid email address.';
            } elseif (empty($full_name)) {
                $error_msg = 'Full name is required.';
            } else {
                // Check if email exists for other users
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $stmt->execute([$email, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $error_msg = 'Email address is already in use.';
                } else {
                    // Update profile
                    $stmt = $pdo->prepare("UPDATE users SET email = ?, full_name = ? WHERE user_id = ?");
                    $stmt->execute([$email, $full_name, $_SESSION['user_id']]);
                    $success_msg = 'Profile updated successfully!';
                }
            }
        }

        // Update System Settings
        if (isset($_POST['update_system_settings']) && $current_user['role'] === 'admin') {
            $working_hours_start = $_POST['working_hours_start'];
            $working_hours_end = $_POST['working_hours_end'];
            $attendance_grace_period = (int)$_POST['attendance_grace_period'];
            $auto_logout_enabled = isset($_POST['auto_logout_enabled']) ? 'true' : 'false';
            $email_notifications = isset($_POST['email_notifications']) ? 'true' : 'false';
            $session_timeout = (int)$_POST['session_timeout'];

            // Update system settings
            $settings_to_update = [
                'working_hours_start' => $working_hours_start,
                'working_hours_end' => $working_hours_end,
                'attendance_grace_period' => $attendance_grace_period,
                'auto_logout_enabled' => $auto_logout_enabled,
                'email_notifications' => $email_notifications,
                'session_timeout' => $session_timeout
            ];

            foreach ($settings_to_update as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }

            $success_msg = 'System settings updated successfully!';
        }
        
    } catch (Exception $e) {
        $error_msg = 'Error: ' . $e->getMessage();
    }
}

// Get current time
$current_time = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
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
        .form-control {
            background-color: #2d2d2d;
            border: 1px solid #333;
            color: #ffffff;
        }
        
        .form-control:focus {
            background-color: #363636;
            border-color: #0d6efd;
            color: #ffffff;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .form-control::placeholder {
            color: #6c757d;
        }
        
        .form-control:disabled,
        .form-control[readonly] {
            background-color:rgba(183, 156, 156, 0.1);
            color:rgb(246, 250, 253);
        }
        .form-check-input {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }
        .form-check-input:checked {
            background-color: #007bff;
            border-color: #007bff;
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
                    <a class="nav-link active" href="settings.php">
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
                    <h2><i class="fas fa-cog me-2"></i>System Settings</h2>
                    <div class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('F d, Y - h:i A'); ?>
                    </div>
                </div>
                
                <?php if ($success_msg): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Change Password -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <small class="text-muted">Must be at least 8 characters long and contain uppercase, lowercase, and numbers</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Settings -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user me-2"></i>Profile Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Role</label>
                                        <input type="text" class="form-control" value="<?php echo ucfirst($current_user['role']); ?>" readonly>
                                    </div>
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php if ($current_user['role'] === 'admin'): ?>
                    <!-- System Settings (Admin Only) -->
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-cogs me-2"></i>System Configuration
                                    <span class="badge bg-warning ms-2">Admin Only</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="working_hours_start" class="form-label">Working Hours Start</label>
                                                <input type="time" class="form-control" id="working_hours_start" name="working_hours_start" value="<?php echo $settings['working_hours_start'] ?? '08:00'; ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label for="working_hours_end" class="form-label">Working Hours End</label>
                                                <input type="time" class="form-control" id="working_hours_end" name="working_hours_end" value="<?php echo $settings['working_hours_end'] ?? '17:00'; ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label for="attendance_grace_period" class="form-label">Grace Period (minutes)</label>
                                                <input type="number" class="form-control" id="attendance_grace_period" name="attendance_grace_period" value="<?php echo $settings['attendance_grace_period'] ?? '15'; ?>" min="0">
                                                <small class="text-muted">Late arrival tolerance in minutes</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="session_timeout" class="form-label">Session Timeout (seconds)</label>
                                                <input type="number" class="form-control" id="session_timeout" name="session_timeout" value="<?php echo $settings['session_timeout'] ?? '3600'; ?>" min="60">
                                                <small class="text-muted">Auto logout after inactivity</small>
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="auto_logout_enabled" name="auto_logout_enabled" <?php echo ($settings['auto_logout_enabled'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="auto_logout_enabled">
                                                        <i class="fas fa-sign-out-alt me-1"></i>Enable Auto Logout
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="email_notifications" name="email_notifications" <?php echo ($settings['email_notifications'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="email_notifications">
                                                        <i class="fas fa-envelope me-1"></i>Enable Email Notifications
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <hr>
                                    <button type="submit" name="update_system_settings" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Update System Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- System Information -->
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>System Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <i class="fas fa-server fa-2x text-primary mb-2"></i>
                                            <h6>PHP Version</h6>
                                            <p class="text-muted"><?php echo phpversion(); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <i class="fas fa-database fa-2x text-success mb-2"></i>
                                            <h6>Database</h6>
                                            <p class="text-muted">MySQL/MariaDB</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <i class="fas fa-calendar fa-2x text-info mb-2"></i>
                                            <h6>Last Login</h6>
                                            <p class="text-muted"><?php echo date('M d, Y H:i', strtotime($current_user['last_login'] ?? 'now')); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <i class="fas fa-shield-alt fa-2x text-warning mb-2"></i>
                                            <h6>Security Level</h6>
                                            <p class="text-muted">High</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            
            // You can add visual feedback here
            console.log('Password strength:', strength);
        });
        
        function checkPasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            return strength;
        }
        
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const passwordForm = this.querySelector('input[name="change_password"]');
                if (passwordForm) {
                    const newPassword = this.querySelector('#new_password').value;
                    const confirmPassword = this.querySelector('#confirm_password').value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('New passwords do not match!');
                        return false;
                    }
                }
            });
        });
    </script>
</body>
</html>