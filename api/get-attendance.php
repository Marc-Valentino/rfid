<?php
// Disable error display in the output
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if database connection was successful
    if ($pdo === null) {
        throw new Exception("Database connection failed. Please check if MySQL server is running and database exists.");
    }
    
    // Initialize AttendanceSystem
    $attendance = new AttendanceSystem();
    
    // Get the date parameter
    $date = isset($_GET['date']) ? $attendance->sanitizeInput($_GET['date']) : date('Y-m-d');
    $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $where_conditions = ['DATE(ar.scan_time) = ?'];
    $params = [$date];
    
    if ($student_id) {
        $where_conditions[] = 'ar.student_id = ?';
        $params[] = $student_id;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Make sure limit and offset are integers
    $limit = (int)$limit;
    $offset = (int)$offset;
    
    $sql = "
        SELECT 
            ar.attendance_id,
            ar.scan_time,
            s.first_name,
            s.last_name,
            s.student_number,
            d.department_name,
            c.course_name,
            ar.attendance_type,
            ar.location,
            ar.verification_status
        FROM attendance_records ar
        INNER JOIN students s ON ar.student_id = s.student_id
        LEFT JOIN departments d ON s.department_id = d.department_id
        LEFT JOIN courses c ON s.course_id = c.course_id
        LEFT JOIN rfid_cards r ON s.student_id = r.student_id
        WHERE {$where_clause}
        ORDER BY ar.scan_time DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters with explicit types
    for ($i = 0; $i < count($params); $i++) {
        $paramType = PDO::PARAM_STR;
        if ($i >= count($params) - 2) { // Last two parameters (limit and offset)
            $paramType = PDO::PARAM_INT;
        }
        $stmt->bindValue($i + 1, $params[$i], $paramType);
    }
    
    $stmt->execute();
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $count_sql = "
        SELECT COUNT(*) as total
        FROM attendance_records ar
        WHERE " . implode(' AND ', $where_conditions);
    
    $count_params = array_slice($params, 0, count($params) - 2); // Remove limit and offset
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($count_params);
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'data' => $attendance_records,
        'pagination' => [
            'total' => (int)$total_records,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total_records
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Get Attendance Error: ' . $e->getMessage());
    
    // Provide more specific error messages
    $errorMessage = 'Failed to retrieve attendance data';
    $errorDetails = $e->getMessage();
    
    // Check for specific error types
    if (strpos($errorDetails, "Database connection failed") !== false) {
        $errorMessage = 'Database connection error. Please check if MySQL is running and database exists.';
    } else if (strpos($errorDetails, "does not exist") !== false) {
        $errorMessage = 'Database does not exist. Please check your database setup.';
    } else if (strpos($errorDetails, "Access denied") !== false) {
        $errorMessage = 'Database access denied. Please check your credentials.';
    } else if (strpos($errorDetails, "Table") !== false && strpos($errorDetails, "doesn't exist") !== false) {
        $errorMessage = 'Database table does not exist. Please check your database schema.';
    }
    
    echo json_encode([
        'success' => false, 
        'message' => $errorMessage,
        'error_details' => $errorDetails
    ]);
}
?>