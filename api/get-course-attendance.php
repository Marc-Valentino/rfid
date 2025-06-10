<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$date = $_GET['date'] ?? date('Y-m-d');
$course_id = $_GET['course_id'] ?? null;

try {
    $sql = "
        SELECT 
            ar.scan_time,
            ar.attendance_type,
            s.first_name,
            s.last_name,
            s.student_number,
            d.department_name,
            c.course_name
        FROM attendance_records ar
        INNER JOIN students s ON ar.student_id = s.student_id
        LEFT JOIN departments d ON s.department_id = d.department_id
        LEFT JOIN courses c ON s.course_id = c.course_id
        WHERE DATE(ar.scan_time) = ?
    ";

    if ($course_id) {
        $sql .= " AND ar.course_id = ?";
    }

    $sql .= " ORDER BY ar.scan_time DESC";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    if ($course_id) {
        $stmt->bind_param("si", $date, $course_id);
    } else {
        $stmt->bind_param("s", $date);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $records = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $records
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}