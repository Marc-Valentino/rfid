<?php
// Turn off PHP error display in production
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET'); // Allow both POST and GET
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session for logging
session_start();

// Create an instance of AttendanceSystem
$attendanceSystem = new AttendanceSystem();

// Accept both POST and GET requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['rfid_uid'])) {
        echo json_encode(['success' => false, 'message' => 'RFID UID is required']);
        exit;
    }
    
    $rfid_uid = $attendanceSystem->sanitizeInput($input['rfid_uid']);
    $reader_id = isset($input['reader_id']) ? (int)$input['reader_id'] : 1;
    $attendance_type = isset($input['attendance_type']) ? $attendanceSystem->sanitizeInput($input['attendance_type']) : null;
    $event_id = isset($input['event_id']) ? (int)$input['event_id'] : null;

    // Verify attendance type is valid
    if (!in_array($attendance_type, ['Time In', 'Time Out'])) {
        // If not specified, determine based on last record
        $lastAttendance = $attendanceSystem->getLastAttendanceRecord($rfid_uid);
        $attendance_type = (!$lastAttendance || $lastAttendance['attendance_type'] === 'Time Out') ? 'Time In' : 'Time Out';
    }

    // Check if the RFID card exists
    $cardExists = $attendanceSystem->checkRFIDCardExists($rfid_uid);
    
    if ($cardExists) {
        try {
            // Record attendance with the determined type and event ID
            $result = $attendanceSystem->recordAttendanceByRFID($rfid_uid, $reader_id, $attendance_type, $event_id);
            
            if ($result['success']) {
                // Get complete student info including photo path
                $student = $attendanceSystem->getStudentByRFID($rfid_uid);
                
                $response = [
                    'success' => true,
                    'rfid_uid' => $rfid_uid,
                    'student_id' => $result['student_id'],
                    'student_name' => $result['student_name'],
                    'student_number' => $result['student_number'] ?? '',
                    'department_name' => $result['department_name'] ?? 'N/A',
                    'course_name' => $result['course_name'] ?? 'N/A',
                    'attendance_type' => $attendance_type,
                    'attendance_time' => date('Y-m-d H:i:s'),
                    'photo_path' => !empty($student['profile_image_path']) ? 
                        (strpos($student['profile_image_path'], 'http') === 0 ? 
                            $student['profile_image_path'] : 
                            (BASE_URL . 'uploads/profiles/' . $student['profile_image_path']))
                        : (BASE_URL . 'assets/img/default-avatar.png'),
                    'message' => 'Attendance recorded successfully'
                ];
                
                // If this is for an event, add event details to the response
                if ($event_id) {
                    try {
                        $db = new Database();
                        $conn = $db->getConnection();
                        $stmt = $conn->prepare("
                            SELECT e.event_id, e.event_name, e.start_date, e.end_date, 
                                   et.type_name as event_type, e.location as event_location
                            FROM events e
                            LEFT JOIN event_types et ON e.type_id = et.type_id
                            WHERE e.event_id = ?
                        ");
                        $stmt->execute([$event_id]);
                        $event = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($event) {
                            $response['event'] = [
                                'event_id' => $event['event_id'],
                                'event_name' => $event['event_name'],
                                'event_type' => $event['event_type'] ?? 'General',
                                'event_date' => date('F j, Y', strtotime($event['start_date'])),
                                'event_location' => $event['event_location'] ?? 'TBD',
                                'start_time' => date('g:i A', strtotime($event['start_date'])),
                                'end_time' => $event['end_date'] ? date('g:i A', strtotime($event['end_date'])) : null
                            ];
                            
                            if ($event['end_date'] && $event['end_date'] !== $event['start_date']) {
                                $response['event']['event_date'] .= ' - ' . date('F j, Y', strtotime($event['end_date']));
                            }
                            
                            $response['message'] = 'Event attendance recorded successfully';
                        }
                    } catch (Exception $e) {
                        // If there's an error fetching event details, just log it and continue
                        error_log("Error fetching event details: " . $e->getMessage());
                    }
                }
                
                echo json_encode($response);
            } else {
                throw new Exception($result['message']);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error recording attendance: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'status' => 'unregistered',
            'rfid_uid' => $rfid_uid,
            'message' => 'Unregistered RFID card'
        ]);
    }
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET requests
    if (!isset($_GET['rfid_uid'])) {
        echo json_encode(['success' => false, 'message' => 'RFID UID is required']);
        exit;
    }
    
    $rfid_uid = $attendanceSystem->sanitizeInput($_GET['rfid_uid']);
    $mode = isset($_GET['mode']) ? $attendanceSystem->sanitizeInput($_GET['mode']) : 'attendance';
    $event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;
    
    // Handle different modes for GET requests
    if ($mode === 'read_only') {
        // Just return the RFID UID without recording attendance
        echo json_encode([
            'success' => true,
            'rfid_uid' => $rfid_uid,
            'message' => 'RFID card read successfully in read-only mode'
        ]);
        exit;
    }
    
    // Check if the RFID card exists in the database
    $cardExists = $attendanceSystem->checkRFIDCardExists($rfid_uid);
    
    if ($cardExists) {
        // Card exists, record attendance with event ID if provided
        $result = $attendanceSystem->recordAttendanceByRFID($rfid_uid, 1, 'Time In', $event_id);
        
        if ($result['success']) {
            // Get complete student info including photo path
            $student = $attendanceSystem->getStudentByRFID($rfid_uid);
            
            $response = [
                'success' => true,
                'rfid_uid' => $rfid_uid,
                'student_id' => $result['student_id'],
                'student_name' => $result['student_name'],
                'student_number' => $result['student_number'] ?? '',
                'attendance_id' => $result['attendance_id'],
                'attendance_time' => $result['attendance_time'],
                'attendance_type' => 'Time In',
                'photo_path' => !empty($student['profile_image_path']) ? 
                    (strpos($student['profile_image_path'], 'http') === 0 ? 
                        $student['profile_image_path'] : 
                        (BASE_URL . 'uploads/profiles/' . $student['profile_image_path']))
                    : (BASE_URL . 'assets/img/default-avatar.png'),
                'message' => 'Attendance recorded successfully'
            ];
            
            // If this is for an event, add event details to the response
            if ($event_id) {
                try {
                    $db = new Database();
                    $conn = $db->getConnection();
                    $stmt = $conn->prepare("
                        SELECT e.event_id, e.event_name, e.start_date, e.end_date, 
                               et.type_name as event_type, e.location as event_location
                        FROM events e
                        LEFT JOIN event_types et ON e.type_id = et.type_id
                        WHERE e.event_id = ?
                    ");
                    $stmt->execute([$event_id]);
                    $event = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($event) {
                        $response['event'] = [
                            'event_id' => $event['event_id'],
                            'event_name' => $event['event_name'],
                            'event_type' => $event['event_type'] ?? 'General',
                            'event_date' => date('F j, Y', strtotime($event['start_date'])),
                            'event_location' => $event['event_location'] ?? 'TBD',
                            'start_time' => date('g:i A', strtotime($event['start_date'])),
                            'end_time' => $event['end_date'] ? date('g:i A', strtotime($event['end_date'])) : null
                        ];
                        
                        if ($event['end_date'] && $event['end_date'] !== $event['start_date']) {
                            $response['event']['event_date'] .= ' - ' . date('F j, Y', strtotime($event['end_date']));
                        }
                        
                        $response['message'] = 'Event attendance recorded successfully';
                    }
                } catch (Exception $e) {
                    // If there's an error fetching event details, just log it and continue
                    error_log("Error fetching event details: " . $e->getMessage());
                }
            }
            
            echo json_encode($response);
        } else {
            // Return error response
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }
    } else {
        // Card doesn't exist, return unregistered status
        echo json_encode([
            'success' => false,
            'status' => 'unregistered',
            'rfid_uid' => $rfid_uid,
            'message' => 'Unregistered RFID card'
        ]);
    }
} 
else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Process RFID scan function is now handled by the AttendanceSystem class
