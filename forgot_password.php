<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$attendance = new AttendanceSystem();
$error_message = '';
$success_message = '';
$step = 1; // Step 1: Enter username, Step 2: Enter new password

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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == '1') {
        // Step 1: Verify username
        $username = $attendance->sanitizeInput($_POST['username']);
        
        if (empty($username)) {
            $error_message = 'Please enter your username.';
        } else {
            // Check if username exists
            try {
                $db = (new Database())->getConnection();
                $query = "SELECT user_id, username, email FROM users WHERE username = :username AND is_active = 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch();
                    $_SESSION['reset_user_id'] = $user['user_id'];
                    $_SESSION['reset_username'] = $user['username'];
                    $step = 2;
                    $success_message = 'Username verified! Please enter your new password.';
                } else {
                    $error_message = 'Username not found or account is inactive.';
                }
            } catch (Exception $e) {
                $error_message = 'An error occurred. Please try again.';
                error_log("Password reset error: " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == '2') {
        // Step 2: Update password
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($new_password) || empty($confirm_password)) {
            $error_message = 'Please fill in all password fields.';
            $step = 2;
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'Passwords do not match.';
            $step = 2;
        } elseif (strlen($new_password) < 6) {
            $error_message = 'Password must be at least 6 characters long.';
            $step = 2;
        } else {
            // Update password
            try {
                $db = (new Database())->getConnection();
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $query = "UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password_hash', $password_hash);
                $stmt->bindParam(':user_id', $_SESSION['reset_user_id']);
                
                if ($stmt->execute()) {
                    // Clear reset session data
                    unset($_SESSION['reset_user_id']);
                    unset($_SESSION['reset_username']);
                    
                    $success_message = 'Password updated successfully! You can now login with your new password.';
                    $step = 3; // Success step
                } else {
                    $error_message = 'Failed to update password. Please try again.';
                    $step = 2;
                }
            } catch (Exception $e) {
                $error_message = 'An error occurred. Please try again.';
                error_log("Password update error: " . $e->getMessage());
                $step = 2;
            }
        }
    }
}

// Check if we're in step 2 and have session data
if (isset($_SESSION['reset_user_id']) && !isset($_POST['step'])) {
    $step = 2;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .forgot-password-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
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
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step.active {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
        }
        .step.completed {
            background: linear-gradient(45deg, #28a745, #1e7e34);
            color: white;
        }
        .step.inactive {
            background: rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="forgot-password-container">
                    <div class="text-center mb-4">
                        <i class="fas fa-key fa-3x text-primary mb-3"></i>
                        <h3 class="text-white"><?php echo APP_NAME; ?></h3>
                        <p class="text-white-50">Reset your password</p>
                    </div>
                    
                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step <?php echo ($step >= 1) ? (($step > 1) ? 'completed' : 'active') : 'inactive'; ?>">
                            1
                        </div>
                        <div class="step <?php echo ($step >= 2) ? (($step > 2) ? 'completed' : 'active') : 'inactive'; ?>">
                            2
                        </div>
                        <div class="step <?php echo ($step >= 3) ? 'completed' : 'inactive'; ?>">
                            3
                        </div>
                    </div>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($step == 1): ?>
                        <!-- Step 1: Enter Username -->
                        <form method="POST">
                            <input type="hidden" name="step" value="1">
                            <div class="mb-4">
                                <label for="username" class="form-label text-white">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="Enter your username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                                <small class="text-white-50 mt-2 d-block">
                                    Enter your username to verify your account
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-arrow-right me-2"></i>Verify Username
                            </button>
                        </form>
                    <?php elseif ($step == 2): ?>
                        <!-- Step 2: Enter New Password -->
                        <form method="POST">
                            <input type="hidden" name="step" value="2">
                            <div class="mb-3">
                                <label class="form-label text-white">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['reset_username']); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label text-white">
                                    <i class="fas fa-lock me-2"></i>New Password
                                </label>
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       placeholder="Enter new password" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label text-white">
                                    <i class="fas fa-lock me-2"></i>Confirm New Password
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm new password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-save me-2"></i>Update Password
                            </button>
                        </form>
                    <?php elseif ($step == 3): ?>
                        <!-- Step 3: Success -->
                        <div class="text-center">
                            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                            <h5 class="text-white mb-3">Password Updated Successfully!</h5>
                            <p class="text-white-50 mb-4">Your password has been updated. You can now login with your new password.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <a href="login.php" class="btn btn-secondary w-100">
                            <i class="fas fa-arrow-left me-2"></i>Back to Login
                        </a>
                    </div>
                    
                    <?php if ($step == 1): ?>
                        <div class="text-center mt-3">
                            <small class="text-white-50">
                                Remember your password? <a href="login.php" class="text-primary">Sign in here</a>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        <?php if ($step == 2): ?>
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>