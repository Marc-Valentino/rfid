<nav class="col-md-3 col-lg-2 d-md-block bg-black sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/students.php">
                    <i class="fas fa-users me-2"></i>Students
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'register-instructor.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/register-instructor.php">
                    <i class="fas fa-chalkboard-teacher me-2"></i>Instructors
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/attendance.php">
                    <i class="fas fa-clock me-2"></i>Attendance
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'rfid-cards.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/rfid-cards.php">
                    <i class="fas fa-id-card me-2"></i>RFID Cards
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/reports.php">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/settings.php">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
            </li>
        </ul>
        
        <hr class="text-secondary">
        
        <div class="mt-4">
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                <span>Quick Actions</span>
            </h6>
            <ul class="nav flex-column mb-2">
                <li class="nav-item">
                    <a class="nav-link text-light" href="<?php echo BASE_URL; ?>/admin/add-student.php">
                        <i class="fas fa-user-plus me-2"></i>Add Student
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-light" href="<?php echo BASE_URL; ?>/admin/scan-rfid.php">
                        <i class="fas fa-wifi me-2"></i>Scan RFID
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-light <?php echo basename($_SERVER['PHP_SELF']) == 'activity-logs.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/activity-logs.php">
                        <i class="fas fa-history me-2"></i>Activity Logs
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
