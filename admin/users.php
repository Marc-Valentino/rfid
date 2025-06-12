<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

$attendance = new AttendanceSystem();
$attendance->requireLogin();

// Handle CRUD operations
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $full_name = trim($_POST['full_name']);
                $role = $_POST['role'];
                
                if (!$attendance->userExists($username, $email)) {
                    if (addUser($username, $email, $password, $full_name, $role)) {
                        $message = 'User added successfully!';
                    } else {
                        $error = 'Failed to add user.';
                    }
                } else {
                    $error = 'Username or email already exists.';
                }
                break;
                
            case 'edit':
                $user_id = $_POST['user_id'];
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $full_name = trim($_POST['full_name']);
                $role = $_POST['role'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (updateUser($user_id, $username, $email, $full_name, $role, $is_active)) {
                    $message = 'User updated successfully!';
                } else {
                    $error = 'Failed to update user.';
                }
                break;
                
            case 'delete':
                $user_id = $_POST['user_id'];
                if (deleteUser($user_id)) {
                    $message = 'User deleted successfully!';
                } else {
                    $error = 'Failed to delete user.';
                }
                break;
                
            case 'reset_password':
                $user_id = $_POST['user_id'];
                $new_password = $_POST['new_password'];
                if (resetUserPassword($user_id, $new_password)) {
                    $message = 'Password reset successfully!';
                } else {
                    $error = 'Failed to reset password.';
                }
                break;
        }
    }
}

// Get all users
$users = getAllUsers();

// Helper functions for user management
function getAllUsers() {
    global $attendance;
    try {
        $db = $attendance->db ?? (new Database())->getConnection();
        $query = "SELECT user_id, username, email, full_name, role, is_active, created_at, last_login 
                 FROM users ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get all users error: " . $e->getMessage());
        return [];
    }
}

function addUser($username, $email, $password, $full_name, $role) {
    try {
        $db = (new Database())->getConnection();
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO users (username, email, password_hash, full_name, role) 
                 VALUES (:username, :email, :password_hash, :full_name, :role)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':role', $role);
        
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Add user error: " . $e->getMessage());
        return false;
    }
}

function updateUser($user_id, $username, $email, $full_name, $role, $is_active) {
    try {
        $db = (new Database())->getConnection();
        
        $query = "UPDATE users SET username = :username, email = :email, full_name = :full_name, 
                 role = :role, is_active = :is_active, updated_at = NOW() 
                 WHERE user_id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':is_active', $is_active);
        
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Update user error: " . $e->getMessage());
        return false;
    }
}

function deleteUser($user_id) {
    try {
        $db = (new Database())->getConnection();
        
        // Permanent delete - remove the user from database
        $query = "DELETE FROM users WHERE user_id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Delete user error: " . $e->getMessage());
        return false;
    }
}

function resetUserPassword($user_id, $new_password) {
    try {
        $db = (new Database())->getConnection();
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET password_hash = :password_hash, updated_at = NOW() 
                 WHERE user_id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':password_hash', $password_hash);
        
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Reset password error: " . $e->getMessage());
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
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
            background: #2d2d2d;
            border: 1px solid #444;
        }
        .modal-header {
            border-bottom: 1px solid #444;
        }
        .modal-footer {
            border-top: 1px solid #444;
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
        .btn-outline-light:hover {
            background: #fff;
            color: #000;
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
                    <a class="nav-link active" href="users.php">
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
                    <h2><i class="fas fa-user-cog me-2"></i>User Management</h2>
                    <div class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('F d, Y - h:i A'); ?>
                    </div>
                </div>
                
                <!-- Alert Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                <h4><?php echo count($users); ?></h4>
                                <p class="text-muted mb-0">Total Users</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-user-shield fa-2x text-success mb-2"></i>
                                <h4><?php echo count(array_filter($users, function($u) { return $u['role'] == 'admin'; })); ?></h4>
                                <p class="text-muted mb-0">Administrators</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-chalkboard-teacher fa-2x text-info mb-2"></i>
                                <h4><?php echo count(array_filter($users, function($u) { return $u['role'] == 'teacher'; })); ?></h4>
                                <p class="text-muted mb-0">Teachers</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-user-check fa-2x text-warning mb-2"></i>
                                <h4><?php echo count(array_filter($users, function($u) { return $u['is_active'] == 1; })); ?></h4>
                                <p class="text-muted mb-0">Active Users</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Users Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>All Users
                        </h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus me-2"></i>Add New User
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No users found</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Last Login</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['user_id']; ?></td>
                                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge <?php 
                                                        echo $user['role'] == 'admin' ? 'bg-danger' : 
                                                            ($user['role'] == 'teacher' ? 'bg-success' : 'bg-info'); 
                                                    ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $user['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                                onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-warning" 
                                                                onclick="resetPassword(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                            <i class="fas fa-key"></i>
                                                        </button>
                                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
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
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Administrator</option>
                                <option value="teacher">Teacher</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="admin">Administrator</option>
                                <option value="teacher">Teacher</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" id="reset_user_id">
                        <p>Reset password for user: <strong id="reset_username"></strong></p>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <p>Are you sure you want to delete user: <strong id="delete_username"></strong>?</p>
                        <p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Warning: This action will permanently delete the user account and cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_is_active').checked = user.is_active == 1;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
        
        function resetPassword(userId, username) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_username').textContent = username;
            
            new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
        }
        
        function deleteUser(userId, username) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_username').textContent = username;
            
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }
    </script>
</body>
</html>