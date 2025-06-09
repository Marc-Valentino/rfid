<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$attendance = new AttendanceSystem();
$error_message = '';
$success_message = '';

// Check if already logged in
if ($attendance->isLoggedIn()) {
    // Role-based redirection
    if ($_SESSION['role'] === 'teacher') {
        header('Location: instructor/dashboard.php');
    } else {
        header('Location: admin/dashboard.php');
    }
    exit();
}

// Add user function (extracted from admin/users.php to avoid path conflicts)
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

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $attendance->sanitizeInput($_POST['username']);
    $email = $attendance->sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = $attendance->sanitizeInput($_POST['full_name']);
    $role = isset($_POST['role']) ? $_POST['role'] : 'teacher';
    $employee_id = $attendance->sanitizeInput($_POST['employee_id']);
    $department_id = $attendance->sanitizeInput($_POST['department_id']);
    $phone = $attendance->sanitizeInput($_POST['phone']);
    $office_location = $attendance->sanitizeInput($_POST['office_location']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error_message = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Check if username or email already exists
        try {
            $db = (new Database())->getConnection();
            $check_query = "SELECT COUNT(*) FROM users WHERE username = :username OR email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                $error_message = 'Username or email already exists.';
            } else {
                // Create user account
                if (addUser($username, $email, $password, $full_name, $role)) {
                    // Add instructor details if role is teacher
                    if ($role === 'teacher') {
                        $instructor_query = "INSERT INTO instructor_details (user_id, employee_id, department_id, phone, office_location) 
                                           SELECT user_id, :employee_id, :department_id, :phone, :office_location 
                                           FROM users WHERE username = :username";
                        $instructor_stmt = $db->prepare($instructor_query);
                        $instructor_stmt->bindParam(':employee_id', $employee_id);
                        $instructor_stmt->bindParam(':department_id', $department_id);
                        $instructor_stmt->bindParam(':phone', $phone);
                        $instructor_stmt->bindParam(':office_location', $office_location);
                        $instructor_stmt->bindParam(':username', $username);
                        $instructor_stmt->execute();
                    }
                    $error_message = ''; // Clear any error messages
                    $success_message = 'Account created successfully! You can now login.';
                    $_POST = array(); // Clear the form
                } else {
                    $success_message = ''; // Clear any success messages
                    $error_message = 'Failed to create account. Please try again.';
                }
            }
        } catch (Exception $e) {
            $error_message = 'An error occurred. Please try again.';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}

// Get departments for dropdown
try {
    $db = (new Database())->getConnection();
    $dept_query = "SELECT department_id, department_name FROM departments ORDER BY department_name";
    $dept_stmt = $db->query($dept_query);
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    $departments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .register-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 10px;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #007bff;
            color: white;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 10px;
        }
        .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #007bff;
            color: white;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .form-select option {
            background: #2d2d2d;
            color: white;
        }
        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .btn-secondary {
            background: linear-gradient(45deg, #6c757d, #495057);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .text-white {
            color: white !important;
        }
        .alert {
            border-radius: 10px;
        }
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #ff6b6b;
        }
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.5);
            color: #51cf66;
        }
        /* Navigation bar styles */
        .navbar {
            background: rgba(26, 26, 26, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .navbar-brand {
            font-weight: 700;
            color: white;
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            transition: color 0.3s;
        }
        .nav-link:hover {
            color: #007bff;
        }
        .navbar-toggler {
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-id-card-alt me-2"></i><?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="register-container">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                        <h3 class="text-white"><?php echo APP_NAME; ?></h3>
                        <p class="text-white-50">Create your account</p>
                    </div>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success_message; ?>
                        </div>
                    <?php elseif ($error_message): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label text-white">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="Enter username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label text-white">
                                    <i class="fas fa-envelope me-2"></i>Email
                                </label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="Enter email address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label text-white">
                                <i class="fas fa-id-card me-2"></i>Full Name
                            </label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   placeholder="Enter your full name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="employee_id" class="form-label text-white">
                                    <i class="fas fa-id-badge me-2"></i>Employee ID
                                </label>
                                <input type="text" class="form-control" id="employee_id" name="employee_id" 
                                       placeholder="Enter employee ID" value="<?php echo isset($_POST['employee_id']) ? htmlspecialchars($_POST['employee_id']) : ''; ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="department_id" class="form-label text-white">
                                    <i class="fas fa-building me-2"></i>Department
                                </label>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['department_id']; ?>" 
                                            <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label text-white">
                                    <i class="fas fa-phone me-2"></i>Phone
                                </label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       placeholder="Enter phone number" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="office_location" class="form-label text-white">
                                    <i class="fas fa-map-marker-alt me-2"></i>Office Location
                                </label>
                                <input type="text" class="form-control" id="office_location" name="office_location" 
                                       placeholder="Enter office location" value="<?php echo isset($_POST['office_location']) ? htmlspecialchars($_POST['office_location']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label text-white">
                                <i class="fas fa-user-tag me-2"></i>Role
                            </label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="teacher" <?php echo (isset($_POST['role']) && $_POST['role'] == 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                                <option value="staff" <?php echo (isset($_POST['role']) && $_POST['role'] == 'staff') ? 'selected' : ''; ?>>Staff</option>
                                <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label text-white">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter password" required>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <label for="confirm_password" class="form-label text-white">
                                    <i class="fas fa-lock me-2"></i>Confirm Password
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm password" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-user-plus me-2"></i>Register
                                </button>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="index.php" class="btn btn-secondary w-100">
                                    <i class="fas fa-home me-2"></i>Back to Home
                                </a>
                            </div>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <small class="text-white-50">
                            Already have an account? <a href="login.php" class="text-primary">Sign in here</a>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Show/hide instructor fields based on role
        document.getElementById('role').addEventListener('change', function() {
            const instructorFields = document.querySelectorAll('.instructor-field');
            instructorFields.forEach(field => {
                field.style.display = this.value === 'teacher' ? 'block' : 'none';
                const inputs = field.querySelectorAll('input, select');
                inputs.forEach(input => {
                    input.required = this.value === 'teacher';
                });
            });
        });
    </script>
</body>
</html>