<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$system = new AttendanceSystem();

// Check if user is logged in and is admin
if (!$system->isLoggedIn() || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get department ID from query string
$department_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($department_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid department ID']);
    exit();
}

try {
    // Get department data
    $result = $system->executeQuery(
        "SELECT * FROM departments WHERE department_id = ?",
        [$department_id]
    );
    
    // If result is an array, get the first element, otherwise use the result as is
    $department = is_array($result) ? ($result[0] ?? null) : $result;
    
    if ($department && !empty($department['department_id'])) {
        echo json_encode($department);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Department not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error fetching department data: ' . $e->getMessage()]);
}
?>
