<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Initialize database and attendance system
$database = new Database();
$pdo = $database->getConnection();
$attendance = new AttendanceSystem();

// Check if user is logged in
$attendance->requireLogin();

// Get parameters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
$instructor_id = $_SESSION['user_id'];

header('Content-Type: application/json');

try {
    // Build the query to get recent attendance records
    $query = "
        SELECT 
            ar.record_id,
            ar.attendance_date,
            ar.attendance_type,
            ar.status,
            ar.rfid_uid,
            ar.verification_status,
            ar.location,
            ar.notes,
            s.student_id,
            CONCAT(s.first_name, ' ', s.last_name) AS student_name,
            s.student_number,
            c.course_id,
            c.course_name,
            c.course_code
        FROM attendance_records ar
        LEFT JOIN students s ON ar.student_id = s.student_id
        LEFT JOIN courses c ON ar.course_id = c.course_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Filter by course if specified
    if ($course_id) {
        $query .= " AND ar.course_id = ?";
        $params[] = $course_id;
    }
    
    // Filter by instructor if needed
    if (!empty($instructor_id)) {
        $query .= " AND c.instructor_id = ?";
        $params[] = $instructor_id;
    }
    
    // Order and limit
    $query .= " ORDER BY ar.attendance_date DESC LIMIT ?";
    $params[] = $limit;
    
    // Prepare and execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    // Fetch all records
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $formattedRecords = [];
    foreach ($records as $record) {
        $formattedRecords[] = [
            'id' => $record['record_id'],
            'date' => $record['attendance_date'],
            'student_name' => $record['student_name'],
            'student_number' => $record['student_number'],
            'course' => $record['course_name'] . ' (' . $record['course_code'] . ')',
            'type' => ucfirst($record['attendance_type']),
            'status' => $record['status'],
            'rfid_uid' => $record['rfid_uid']
        ];
    }
    
    // Return the results
    echo json_encode([
        'success' => true,
        'data' => $formattedRecords
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in get-recent-scans.php: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching recent scans.',
        'error' => $e->getMessage()
    ]);
}
}