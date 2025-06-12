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

$page_title = 'Edit Student';
$error_msg = '';
$success_msg = '';

$attendance = new AttendanceSystem();
$attendance->requireLogin();

// Check if student ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: students.php');
    exit();
}

$student_id = (int)$_GET['id'];

// Get student data
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get departments and courses for dropdowns
    $departments = $pdo->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name")->fetchAll();
    $courses = $pdo->query("SELECT * FROM courses WHERE is_active = 1 ORDER BY course_name")->fetchAll();
    
    // Get student details
    $stmt = $pdo->prepare("
        SELECT s.*, rc.rfid_uid, rc.card_status, rc.issue_date, rc.expiry_date 
        FROM students s
        LEFT JOIN rfid_cards rc ON s.student_id = rc.student_id
        WHERE s.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        header('Location: students.php');
        exit();
    }
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate inputs
        $student_number = trim($_POST['student_number']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        
        if (empty($student_number) || empty($first_name) || empty($last_name)) {
            $error_msg = "Student number, first name, and last name are required fields.";
        } else {
            // Check if student number already exists for another student
            $check_stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_number = ? AND student_id != ?");
            $check_stmt->execute([$student_number, $student_id]);
            if ($check_stmt->rowCount() > 0) {
                $error_msg = "Student number already exists for another student.";
            } else {
                // Handle file upload if a new image is provided
                $profile_image = $student['profile_image'] ?? '';
                $profile_image_path = $student['profile_image_path'] ?? '';
                
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['profile_image'];
                    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($fileExt, $allowedExts)) {
                        // Generate unique filename
                        $newFilename = uniqid('student_') . '.' . $fileExt;
                        $targetPath = '../uploads/students/' . $newFilename;
                        
                        // Ensure the uploads directory exists
                        if (!file_exists('../uploads/students/')) {
                            mkdir('../uploads/students/', 0777, true);
                        }
                        
                        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                            // Delete old image if it exists
                            if (!empty($profile_image_path) && file_exists('../' . $profile_image_path)) {
                                unlink('../' . $profile_image_path);
                            }
                            
                            $profile_image = $newFilename;
                            $profile_image_path = 'uploads/students/' . $newFilename;
                        } else {
                            $error_msg = 'Failed to upload profile image. Please try again.';
                        }
                    } elseif (!empty($file['name'])) {
                        $error_msg = 'Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.';
                    }
                }
                
                // Only proceed with the update if there are no file upload errors
                if (empty($error_msg)) {
                    // Update student record
                    $update_sql = "
                        UPDATE students SET 
                        student_number = ?,
                        first_name = ?,
                        last_name = ?,
                        middle_name = ?,
                        email = ?,
                        phone = ?,
                        date_of_birth = ?,
                        gender = ?,
                        address = ?,
                        department_id = ?,
                        course_id = ?,
                        year_level = ?,
                        enrollment_status = ?,
                        emergency_contact_name = ?,
                        emergency_contact_phone = ?,
                        guardian_name = ?,
                        guardian_phone = ?,
                        profile_image = ?,
                        profile_image_path = ?,
                        updated_at = NOW()
                        WHERE student_id = ?
                    ";
                    
                    $update_stmt = $pdo->prepare($update_sql);
                    $update_success = $update_stmt->execute([
                        $student_number,
                        $first_name,
                        $last_name,
                        $_POST['middle_name'] ?? null,
                        $email,
                        $_POST['phone'] ?? null,
                        $_POST['date_of_birth'] ?? null,
                        $_POST['gender'] ?? null,
                        $_POST['address'] ?? null,
                        !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
                        !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null,
                        $_POST['year_level'] ?? null,
                        $_POST['enrollment_status'] ?? 'Active',
                        $_POST['emergency_contact_name'] ?? null,
                        $_POST['emergency_contact_phone'] ?? null,
                        $_POST['guardian_name'] ?? null,
                        $_POST['guardian_phone'] ?? null,
                        $profile_image,
                        $profile_image_path,
                        $student_id
                    ]);
                    
                    if ($update_success) {
                        // Update the student array to reflect the changes
                        $student = array_merge($student, [
                            'profile_image' => $profile_image,
                            'profile_image_path' => $profile_image_path
                        ]);
                    }
                }
                
                // Handle RFID card update if provided
                if (empty($error_msg) && !empty($_POST['rfid_uid'])) {
                    $rfid_uid = trim($_POST['rfid_uid']);
                    
                    // Check if RFID already exists for another student
                    $check_rfid = $pdo->prepare("SELECT student_id FROM rfid_cards WHERE rfid_uid = ? AND student_id != ?");
                    $check_rfid->execute([$rfid_uid, $student_id]);
                    
                    if ($check_rfid->rowCount() > 0) {
                        $error_msg = "RFID card is already assigned to another student.";
                    } else {
                        // Check if student already has an RFID card
                        $check_student_rfid = $pdo->prepare("SELECT card_id FROM rfid_cards WHERE student_id = ?");
                        $check_student_rfid->execute([$student_id]);
                        
                        if ($check_student_rfid->rowCount() > 0) {
                            // Update existing RFID card
                            $update_rfid = $pdo->prepare("
                                UPDATE rfid_cards SET 
                                rfid_uid = ?,
                                card_status = ?,
                                issue_date = ?,
                                expiry_date = ?,
                                updated_at = NOW()
                                WHERE student_id = ?
                            ");
                            $update_rfid->execute([
                                $rfid_uid,
                                $_POST['card_status'] ?? 'Active',
                                $_POST['issue_date'] ?? date('Y-m-d'),
                                $_POST['expiry_date'] ?? null,
                                $student_id
                            ]);
                        } else {
                            // Insert new RFID card
                            $insert_rfid = $pdo->prepare("
                                INSERT INTO rfid_cards 
                                (rfid_uid, student_id, card_status, issue_date, expiry_date, created_at)
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ");
                            $insert_rfid->execute([
                                $rfid_uid,
                                $student_id,
                                $_POST['card_status'] ?? 'Active',
                                $_POST['issue_date'] ?? date('Y-m-d'),
                                $_POST['expiry_date'] ?? null
                            ]);
                        }
                    }
                }
                
                if (empty($error_msg)) {
                    $success_msg = "Student information updated successfully.";
                    
                    // Refresh student data
                    $stmt->execute([$student_id]);
                    $student = $stmt->fetch();
                    
                    // Update the student array with the latest data
                    if ($student) {
                        $student = array_merge($student, [
                            'profile_image' => $profile_image,
                            'profile_image_path' => $profile_image_path
                        ]);
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    $error_msg = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student - <?php echo APP_NAME; ?></title>
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
                    <a class="nav-link active" href="students.php">
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
                    <h2><i class="fas fa-user-edit me-2"></i>Edit Student</h2>
                    <div class="btn-toolbar">
                        <a href="students.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Students
                        </a>
                    </div>
                </div>

                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <h5 class="text-light mb-3"><i class="fas fa-user me-2"></i>Basic Information</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Student Number *</label>
                                        <input type="text" class="form-control" 
                                               name="student_number" value="<?php echo htmlspecialchars($student['student_number']); ?>" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">First Name *</label>
                                            <input type="text" class="form-control" 
                                                   name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">Last Name *</label>
                                            <input type="text" class="form-control" 
                                                   name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Middle Name</label>
                                        <input type="text" class="form-control" 
                                               name="middle_name" value="<?php echo htmlspecialchars($student['middle_name'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">Email</label>
                                            <input type="email" class="form-control" 
                                                   name="email" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">Phone</label>
                                            <input type="tel" class="form-control" 
                                                   name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">Date of Birth</label>
                                            <input type="date" class="form-control" 
                                                   name="date_of_birth" value="<?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">Gender</label>
                                            <select class="form-select" name="gender">
                                                <option value="">Select Gender</option>
                                                <option value="Male" <?php echo ($student['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo ($student['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                                <option value="Other" <?php echo ($student['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Profile Picture</label>
                                        <input type="file" class="form-control" name="profile_image" accept="image/*" onchange="previewImage(this)">
                                        <small class="text-muted">Leave blank to keep current image. Accepted formats: JPG, JPEG, PNG, GIF (Max: 2MB)</small>
                                        <div class="mt-2" id="imagePreview" style="display: none;">
                                            <img id="preview" src="#" alt="Preview" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                                        </div>
                                        <?php if (!empty($student['profile_image_path'])): ?>
                                        <div class="mt-2">
                                            <p class="text-muted mb-1">Current Photo:</p>
                                            <img src="../<?php echo htmlspecialchars($student['profile_image_path']); ?>" alt="Current Profile" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Address</label>
                                        <textarea class="form-control" 
                                                  name="address" rows="3"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                
                                <!-- Academic Information -->
                                <div class="col-md-6">
                                    <h5 class="text-light mb-3"><i class="fas fa-graduation-cap me-2"></i>Academic Information</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Department</label>
                                        <select class="form-select" name="department_id">
                                            <option value="">Select Department</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo $dept['department_id']; ?>" 
                                                        <?php echo ($student['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dept['department_name']); ?>
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
                                                        <?php echo ($student['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Year Level</label>
                                        <select class="form-select" name="year_level">
                                            <option value="">Select Year Level</option>
                                            <option value="1st Year" <?php echo ($student['year_level'] == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                            <option value="2nd Year" <?php echo ($student['year_level'] == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                            <option value="3rd Year" <?php echo ($student['year_level'] == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                            <option value="4th Year" <?php echo ($student['year_level'] == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                            <option value="5th Year" <?php echo ($student['year_level'] == '5th Year') ? 'selected' : ''; ?>>5th Year</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Enrollment Status</label>
                                        <select class="form-select" name="enrollment_status">
                                            <option value="Active" <?php echo ($student['enrollment_status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="Inactive" <?php echo ($student['enrollment_status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="Graduated" <?php echo ($student['enrollment_status'] == 'Graduated') ? 'selected' : ''; ?>>Graduated</option>
                                            <option value="Dropped" <?php echo ($student['enrollment_status'] == 'Dropped') ? 'selected' : ''; ?>>Dropped</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Emergency Contact Information -->
                                    <h5 class="text-light mb-3 mt-4"><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Emergency Contact Name</label>
                                        <input type="text" class="form-control" 
                                               name="emergency_contact_name" value="<?php echo htmlspecialchars($student['emergency_contact_name'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Emergency Contact Phone</label>
                                        <input type="tel" class="form-control" 
                                               name="emergency_contact_phone" value="<?php echo htmlspecialchars($student['emergency_contact_phone'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Guardian Name</label>
                                        <input type="text" class="form-control" 
                                               name="guardian_name" value="<?php echo htmlspecialchars($student['guardian_name'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label text-light">Guardian Phone</label>
                                        <input type="tel" class="form-control" 
                                               name="guardian_phone" value="<?php echo htmlspecialchars($student['guardian_phone'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- RFID Card Information -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h5 class="text-light mb-3"><i class="fas fa-id-card me-2"></i>RFID Card Information</h5>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label text-light">RFID UID</label>
                                    <input type="text" class="form-control" 
                                           name="rfid_uid" value="<?php echo htmlspecialchars($student['rfid_uid'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label text-light">Card Status</label>
                                    <select class="form-select" name="card_status">
                                        <option value="Active" <?php echo ($student['card_status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="Inactive" <?php echo ($student['card_status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="Lost" <?php echo ($student['card_status'] == 'Lost') ? 'selected' : ''; ?>>Lost</option>
                                        <option value="Damaged" <?php echo ($student['card_status'] == 'Damaged') ? 'selected' : ''; ?>>Damaged</option>
                                        <option value="Expired" <?php echo ($student['card_status'] == 'Expired') ? 'selected' : ''; ?>>Expired</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label text-light">Issue Date</label>
                                    <input type="date" class="form-control" 
                                           name="issue_date" value="<?php echo htmlspecialchars($student['issue_date'] ?? date('Y-m-d')); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label text-light">Expiry Date</label>
                                    <input type="date" class="form-control" 
                                           name="expiry_date" value="<?php echo htmlspecialchars($student['expiry_date'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                                <a href="students.php" class="btn btn-outline-secondary ms-2">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input) {
            const preview = document.getElementById('preview');
            const previewDiv = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewDiv.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>