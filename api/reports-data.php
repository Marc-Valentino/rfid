<?php
// Prevent any output before headers
ob_start();

// Set proper JSON headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, log them instead

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Initialize AttendanceSystem
$attendance = new AttendanceSystem();

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

try {
    // Process GET parameters with proper sanitization
    $report_type = isset($_GET['type']) ? $attendance->sanitizeInput($_GET['type']) : 'daily';
    $date_from = isset($_GET['date_from']) ? $attendance->sanitizeInput($_GET['date_from']) : date('Y-m-d');
    $date_to = isset($_GET['date_to']) ? $attendance->sanitizeInput($_GET['date_to']) : date('Y-m-d');
    $department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
    
    switch ($report_type) {
        case 'daily':
            $sql = "
                SELECT 
                    s.student_number,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    d.department_name,
                    ar.attendance_date,
                    ar.first_time_in,
                    ar.last_time_out,
                    ar.total_hours,
                    ar.attendance_status,
                    ar.late_minutes
                FROM students s 
                LEFT JOIN departments d ON s.department_id = d.department_id
                LEFT JOIN daily_attendance_summary ar ON s.student_id = ar.student_id
                WHERE DATE(ar.attendance_date) = CURDATE()"; // This ensures only today's records are shown

            if ($department_id) {
                $sql .= " AND s.department_id = ?";
                $params[] = $department_id;
            }

            $sql .= " ORDER BY ar.attendance_date DESC, s.student_number";
            break;
            
        case 'monthly':
            $sql = "
                SELECT s.student_id, s.student_number,
                       CONCAT(s.first_name, ' ', s.last_name) as student_name,
                       d.department_name, mas.year, mas.month,
                       mas.total_days, mas.present_days, mas.absent_days,
                       mas.late_days, mas.attendance_percentage
                FROM monthly_attendance_stats mas
                JOIN students s ON mas.student_id = s.student_id
                LEFT JOIN departments d ON s.department_id = d.department_id
                WHERE CONCAT(mas.year, '-', LPAD(mas.month, 2, '0'), '-01') BETWEEN ? AND ?
            ";
            $params = [$date_from, $date_to];
            
            if ($department_id) {
                $sql .= " AND s.department_id = ?";
                $params[] = $department_id;
            }
            
            $sql .= " ORDER BY mas.year DESC, mas.month DESC, s.last_name";
            break;
            
        case 'summary':
            $sql = "
                SELECT 
                    COUNT(DISTINCT s.student_id) as total_students,
                    COUNT(DISTINCT CASE WHEN das.attendance_status = 'Present' THEN s.student_id END) as present_today,
                    COUNT(DISTINCT CASE WHEN das.attendance_status = 'Absent' THEN s.student_id END) as absent_today,
                    COUNT(DISTINCT CASE WHEN das.attendance_status = 'Late' THEN s.student_id END) as late_today,
                    AVG(das.total_hours) as avg_hours
                FROM students s
                LEFT JOIN daily_attendance_summary das ON s.student_id = das.student_id 
                    AND das.attendance_date = ?
                WHERE s.is_active = 1
            ";
            $params = [$date_from];
            
            if ($department_id) {
                $sql .= " AND s.department_id = ?";
                $params[] = $department_id;
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid report type']);
            exit;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($report_type === 'summary') {
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'report_type' => $report_type,
        'date_range' => ['from' => $date_from, 'to' => $date_to],
        'data' => $data
    ]);
    
} catch (Exception $e) {
    error_log('Reports Data Error: ' . $e->getMessage());
    ob_clean(); // Clear any previous output
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to generate report data'
    ]);
}

// Flush output buffer
ob_end_flush();
?>