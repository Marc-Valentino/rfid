<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$attendance = new AttendanceSystem();
$attendance->requireLogin();

if ($_SESSION['role'] !== 'teacher') {
    exit('Unauthorized');
}

$instructor_id = $_SESSION['user_id'];
$recent_scans = $attendance->getRecentScans($instructor_id, 10);

foreach ($recent_scans as $scan):
?>
    <tr>
        <td><?php echo date('h:i:s A', strtotime($scan['scan_time'])); ?></td>
        <td><?php echo htmlspecialchars($scan['student_name']); ?></td>
        <td><?php echo htmlspecialchars($scan['course_name']); ?></td>
        <td><?php echo $scan['scan_type']; ?></td>
        <td>
            <span class="badge bg-<?php echo $scan['status'] === 'Success' ? 'success' : 'danger'; ?>">
                <?php echo $scan['status']; ?>
            </span>
        </td>
    </tr>
<?php endforeach; ?>