<?php
<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

$attendance = new AttendanceSystem();
$attendance->requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = [
        'success' => false,
        'message' => '',
        'student_name' => '',
        'student_number' => '',
        'attendance_type' => ''
    ];

    $rfid_uid = isset($_POST['rfid_uid']) ? $attendance->sanitizeInput($_POST['rfid_uid']) : '';
    $course_id = isset($_POST['course_id']) ? $_POST['course_id'] : null;
    $attendance_type = isset($_POST['attendance_type']) ? $_POST['attendance_type'] : 'Time In';

    if (!empty($rfid_uid)) {
        $student = $attendance->getStudentByRFID($rfid_uid);
        
        if ($student) {
            // Process the attendance
            $result = $attendance->processAttendance(
                $student['student_id'], 
                $course_id,
                $attendance_type
            );
            
            $response = [
                'success' => $result['success'],
                'message' => $result['message'],
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'student_number' => $student['student_number'],
                'attendance_type' => $attendance_type
            ];
        } else {
            $response['message'] = 'Invalid RFID card or student not found';
        }
    } else {
        $response['message'] = 'No RFID data received';
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}