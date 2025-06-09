<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/functions.php';

// Remove the standalone function
// function sanitize_input($data) {
//     return htmlspecialchars(strip_tags(trim($data)));
// }

session_start();

// Create AttendanceSystem instance
$attendanceSystem = new AttendanceSystem();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $student_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $search = isset($_GET['search']) ? $attendanceSystem->sanitizeInput($_GET['search']) : '';
        $department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        if ($student_id) {
            // Get specific student
            $stmt = $pdo->prepare("
                SELECT s.*, d.department_name, c.course_name,
                       rc.rfid_uid, rc.card_status, rc.issue_date
                FROM students s
                LEFT JOIN departments d ON s.department_id = d.department_id
                LEFT JOIN courses c ON s.course_id = c.course_id
                LEFT JOIN rfid_cards rc ON s.student_id = rc.student_id
                WHERE s.student_id = ?
            ");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student) {
                echo json_encode(['success' => true, 'data' => $student]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Student not found']);
            }
        } else {
            // Get students list with search and filters
            $where_conditions = ['s.is_active = 1'];
            $params = [];
            
            if ($search) {
                $where_conditions[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_number LIKE ?)';
                $search_param = '%' . $search . '%';
                $params = array_merge($params, [$search_param, $search_param, $search_param]);
            }
            
            if ($department_id) {
                $where_conditions[] = 's.department_id = ?';
                $params[] = $department_id;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $sql = "
                SELECT s.student_id, s.student_number, s.first_name, s.last_name,
                       s.email, s.phone, s.year_level, s.enrollment_status,
                       d.department_name, c.course_name,
                       rc.rfid_uid, rc.card_status
                FROM students s
                LEFT JOIN departments d ON s.department_id = d.department_id
                LEFT JOIN courses c ON s.course_id = c.course_id
                LEFT JOIN rfid_cards rc ON s.student_id = rc.student_id
                WHERE {$where_clause}
                ORDER BY s.last_name, s.first_name
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $count_sql = "SELECT COUNT(*) as total FROM students s WHERE {$where_clause}";
            $stmt = $pdo->prepare($count_sql);
            $stmt->execute(array_slice($params, 0, -2));
            $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            echo json_encode([
                'success' => true,
                'data' => $students,
                'pagination' => [
                    'total' => (int)$total_records,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total_records
                ]
            ]);
        }
    }
    
} catch (Exception $e) {
    error_log('Student Data Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to retrieve student data'
    ]);
}
?>