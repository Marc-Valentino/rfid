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

$page_title = 'Add Student';
$errors = [];
$success = false;

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Get departments and courses
$departments = $pdo->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name")->fetchAll();
$courses = $pdo->query("SELECT * FROM courses WHERE is_active = 1 ORDER BY course_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate input
    $student_number = trim($_POST['student_number'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $department_id = $_POST['department_id'] ?? '';
    $course_id = $_POST['course_id'] ?? '';
    $year_level = $_POST['year_level'] ?? '';
    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $guardian_phone = trim($_POST['guardian_phone'] ?? '');
    
    // Validation
    if (empty($student_number)) $errors[] = 'Student number is required';
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    
    // Check if student number already exists
    if (!empty($student_number)) {
        $check_stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_number = ?");
        $check_stmt->execute([$student_number]);
        if ($check_stmt->fetch()) {
            $errors[] = 'Student number already exists';
        }
    }
    
    // Check if email already exists
    if (!empty($email)) {
        $check_stmt = $pdo->prepare("SELECT student_id FROM students WHERE email = ?");
        $check_stmt->execute([$email]);
        if ($check_stmt->fetch()) {
            $errors[] = 'Email already exists';
        }
    }
    
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO students (student_number, first_name, last_name, middle_name, email, phone, 
                                        date_of_birth, gender, address, department_id, course_id, year_level,
                                        emergency_contact_name, emergency_contact_phone, guardian_name, guardian_phone)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $student_number, $first_name, $last_name, $middle_name, $email, $phone,
                $date_of_birth ?: null, $gender ?: null, $address, 
                $department_id ?: null, $course_id ?: null, $year_level ?: null,
                $emergency_contact_name, $emergency_contact_phone, $guardian_name, $guardian_phone
            ]);
            
            $success = true;
            
            // Initialize AttendanceSystem
            $attendanceSystem = new AttendanceSystem();
            
            // Log the action using the instance
            $attendanceSystem->logActivity($_SESSION['user_id'], 'CREATE', 'students', $pdo->lastInsertId(), null, [
                'student_number' => $student_number,
                'name' => $first_name . ' ' . $last_name
            ]);
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student - <?php echo APP_NAME; ?></title>
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
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            color: #ffffff;
        }
        .form-control::placeholder {
            color: #aaa;
        }
        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
        }
        .btn-outline-secondary {
            border-color: rgba(255, 255, 255, 0.2);
            color: #ccc;
        }
        .btn-outline-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            color: #fff;
        }
        .alert {
            border-radius: 10px;
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
                        <a class="nav-link active text-light" href="add-student.php">
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
                    <h2><i class="fas fa-user-plus me-2"></i>Add New Student</h2>
                    <div class="btn-toolbar">
                        <a href="students.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Students
                        </a>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>Student added successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <h5 class="text-light mb-3"><i class="fas fa-user me-2"></i>Basic Information</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Student Number *</label>
                                        <input type="text" class="form-control" 
                                               name="student_number" value="<?php echo htmlspecialchars($_POST['student_number'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">First Name *</label>
                                            <input type="text" class="form-control" 
                                                   name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">Last Name *</label>
                                            <input type="text" class="form-control" 
                                                   name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Middle Name</label>
                                        <input type="text" class="form-control" 
                                               name="middle_name" value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">Email</label>
                                            <input type="email" class="form-control" 
                                                   name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">Phone</label>
                                            <input type="tel" class="form-control" 
                                                   name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">Date of Birth</label>
                                            <input type="date" class="form-control" 
                                                   name="date_of_birth" value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">Gender</label>
                                            <select class="form-select" name="gender">
                                                <option value="">Select Gender</option>
                                                <option value="Male" <?php echo ($_POST['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo ($_POST['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                                <option value="Other" <?php echo ($_POST['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Address</label>
                                        <textarea class="form-control" 
                                                  name="address" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                
                                <!-- Academic Information -->
                                <div class="col-md-6">
                                    <h5 class="text-light mb-3"><i class="fas fa-graduation-cap me-2"></i>Academic Information</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Department</label>
                                        <select class="form-select" name="department_id">
                                            <option value="">Select Department</option>
                                            <?php foreach ($departments as $department): ?>
                                                <option value="<?php echo $department['department_id']; ?>" 
                                                    <?php echo ($_POST['department_id'] ?? '') == $department['department_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($department['department_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Course</label>
                                        <select class="form-select" name="course_id">
                                            <option value="">Select Course</option>
                                            <?php foreach ($courses as $course): ?>
                                                <option value="<?php echo $course['course_id']; ?>" 
                                                    <?php echo ($_POST['course_id'] ?? '') == $course['course_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Year Level</label>
                                        <select class="form-select" name="year_level">
                                            <option value="">Select Year Level</option>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <option value="<?php echo $i; ?>" 
                                                    <?php echo ($_POST['year_level'] ?? '') == $i ? 'selected' : ''; ?>>
                                                    Year <?php echo $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Emergency Contact Information -->
                                    <h5 class="text-light mb-3 mt-4"><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Emergency Contact Name</label>
                                        <input type="text" class="form-control" 
                                               name="emergency_contact_name" value="<?php echo htmlspecialchars($_POST['emergency_contact_name'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Emergency Contact Phone</label>
                                        <input type="tel" class="form-control" 
                                               name="emergency_contact_phone" value="<?php echo htmlspecialchars($_POST['emergency_contact_phone'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Guardian Name</label>
                                        <input type="text" class="form-control" 
                                               name="guardian_name" value="<?php echo htmlspecialchars($_POST['guardian_name'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Guardian Phone</label>
                                        <input type="tel" class="form-control" 
                                               name="guardian_phone" value="<?php echo htmlspecialchars($_POST['guardian_phone'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Student
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>