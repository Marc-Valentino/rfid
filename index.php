<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

$attendance = new AttendanceSystem();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Student Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: white;
        }
        .landing-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
        }
        .feature-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #007bff;
        }
        .hero-section {
            padding: 4rem 0;
            text-align: center;
        }
        .hero-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .hero-subtitle {
            font-size: 1.2rem;
            opacity: 0.8;
            margin-bottom: 2rem;
        }
        .cta-buttons .btn {
            margin: 0 10px;
        }
        .section-title {
            position: relative;
            display: inline-block;
            margin-bottom: 2rem;
        }
        .section-title:after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 3px;
            background: #007bff;
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
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="landing-container">
            <!-- Hero Section -->
            <div class="hero-section">
                <div class="text-center mb-4">
                    <i class="fas fa-id-card-alt fa-4x text-primary mb-3"></i>
                    <h1 class="hero-title"><?php echo APP_NAME; ?></h1>
                    <p class="hero-subtitle">Modern RFID-based Student Attendance Tracking System</p>
                    <div class="cta-buttons">
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </a>
                        <a href="register.php" class="btn btn-outline-light">
                            <i class="fas fa-user-plus me-2"></i>Register
                        </a>
                    </div>
                </div>
            </div>

            <!-- Features Section -->
            <div class="row mt-5">
                <div class="col-12 mb-4">
                    <h2 class="section-title">Key Features</h2>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-fingerprint"></i>
                        </div>
                        <h4>RFID Authentication</h4>
                        <p class="text-white-50">Quick and secure attendance tracking using RFID card technology.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Real-time Analytics</h4>
                        <p class="text-white-50">Comprehensive dashboards with attendance statistics and reports.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h4>Automated Notifications</h4>
                        <p class="text-white-50">Instant alerts for absences and attendance patterns.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4>Multi-user Roles</h4>
                        <p class="text-white-50">Dedicated interfaces for administrators and instructors.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-export"></i>
                        </div>
                        <h4>Export Reports</h4>
                        <p class="text-white-50">Generate and download attendance reports in multiple formats.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>Secure System</h4>
                        <p class="text-white-50">Robust security measures to protect student data and privacy.</p>
                    </div>
                </div>
            </div>
            
            <!-- About Section -->
            <div class="row mt-5">
                <div class="col-12 mb-4">
                    <h2 class="section-title">About The System</h2>
                </div>
                <div class="col-12">
                    <p>The RFID Student Attendance System is a modern solution designed to streamline attendance tracking in educational institutions. Using RFID technology, the system provides a contactless, efficient way to record student attendance while offering powerful reporting and analytics tools.</p>
                    <p>Developed with both administrators and instructors in mind, the system offers intuitive interfaces for managing courses, students, and attendance records.</p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="row mt-5 pt-4 border-top border-secondary">
                <div class="col-12 text-center">
                    <p class="text-white-50">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>