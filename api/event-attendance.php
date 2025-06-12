<?php
// API endpoint for event attendance management
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../includes/functions.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session and check authentication
session_start();
$attendanceSystem = new AttendanceSystem();

// Check if user is logged in
if (!$attendanceSystem->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get request method and input data
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Handle different request methods
switch ($method) {
    case 'GET':
        // Get attendance records
        if (isset($_GET['event_id'])) {
            $eventId = (int)$_GET['event_id'];
            $dayId = isset($_GET['day_id']) ? (int)$_GET['day_id'] : null;
            $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            
            getEventAttendance($attendanceSystem, $eventId, $dayId, $studentId, $page, $limit);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Event ID is required']);
        }
        break;
        
    case 'POST':
        // Record attendance via RFID scan or manual entry
        if (empty($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No input data provided']);
            exit();
        }
        
        if (isset($input['rfid_uid'])) {
            // Handle RFID scan
            recordAttendanceByRFID($attendanceSystem, $input);
        } else {
            // Handle manual attendance entry
            recordManualAttendance($attendanceSystem, $input);
        }
        break;
        
    case 'PUT':
        // Update attendance record
        if (empty($input) || !isset($_GET['attendance_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Attendance ID is required']);
            exit();
        }
        updateAttendance($attendanceSystem, (int)$_GET['attendance_id'], $input);
        break;
        
    case 'DELETE':
        // Delete attendance record
        if (!isset($_GET['attendance_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Attendance ID is required']);
            exit();
        }
        deleteAttendance($attendanceSystem, (int)$_GET['attendance_id']);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}

/**
 * Get attendance records for an event
 */
function getEventAttendance($attendanceSystem, $eventId, $dayId = null, $studentId = null, $page = 1, $limit = 20) {
    try {
        $offset = ($page - 1) * $limit;
        $params = [':event_id' => $eventId];
        $where = ["a.event_id = :event_id"];
        
        // Add filters
        if ($dayId !== null) {
            $where[] = "a.day_id = :day_id";
            $params[':day_id'] = $dayId;
        }
        
        if ($studentId !== null) {
            $where[] = "a.student_id = :student_id";
            $params[':student_id'] = $studentId;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total 
                      FROM event_attendance a
                      $whereClause";
        $countStmt = $attendanceSystem->db->prepare($countQuery);
        
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        
        $countStmt->execute();
        $total = $countStmt->fetch()['total'];
        
        // Get attendance records with student and event info
        $query = "SELECT 
                    a.*, 
                    s.first_name, s.last_name, s.student_number, s.profile_image,
                    d.department_name, c.course_name,
                    ed.day_name, ed.event_date,
                    e.event_name, e.location
                 FROM event_attendance a
                 JOIN students s ON a.student_id = s.student_id
                 LEFT JOIN departments d ON s.department_id = d.department_id
                 LEFT JOIN courses c ON s.course_id = c.course_id
                 LEFT JOIN event_days ed ON a.day_id = ed.day_id
                 LEFT JOIN events e ON a.event_id = e.event_id
                 $whereClause
                 ORDER BY a.time_in DESC
                 LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $stmt = $attendanceSystem->db->prepare($query);
        
        // Bind parameters with proper types
        foreach ($params as $key => $value) {
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $paramType);
        }
        
        $stmt->execute();
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the response
        $result = [
            'success' => true,
            'data' => $attendance,
            'pagination' => [
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ];
        
        // Add summary stats if no specific student filter
        if ($studentId === null) {
            $result['summary'] = getAttendanceSummary($attendanceSystem, $eventId, $dayId);
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve attendance: ' . $e->getMessage()]);
    }
}

/**
 * Record attendance via RFID scan
 */
function recordAttendanceByRFID($attendanceSystem, $data) {
    try {
        $rfidUid = $data['rfid_uid'] ?? '';
        $eventId = $data['event_id'] ?? null;
        $dayId = $data['day_id'] ?? null;
        
        if (empty($rfidUid) || empty($eventId)) {
            throw new Exception('RFID UID and Event ID are required');
        }
        
        // Get student by RFID
        $student = $attendanceSystem->getStudentByRFID($rfidUid);
        
        if (!$student) {
            throw new Exception('Student not found or RFID card not registered');
        }
        
        // If day_id not provided, use the first active day of the event
        if (empty($dayId)) {
            $dayStmt = $attendanceSystem->db->prepare(
                "SELECT day_id FROM event_days 
                 WHERE event_id = :event_id AND is_active = 1 
                 ORDER BY event_date ASC LIMIT 1"
            );
            $dayStmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
            $dayStmt->execute();
            
            if ($dayStmt->rowCount() === 0) {
                throw new Exception('No active days found for this event');
            }
            
            $dayId = $dayStmt->fetch()['day_id'];
        }
        
        // Check if student is already registered for the event
        $regStmt = $attendanceSystem->db->prepare(
            "SELECT registration_id FROM event_registrations 
             WHERE event_id = :event_id AND student_id = :student_id"
        );
        $regStmt->execute([
            ':event_id' => $eventId,
            ':student_id' => $student['student_id']
        ]);
        
        if ($regStmt->rowCount() === 0) {
            // Auto-register the student if not already registered
            $registerStmt = $attendanceSystem->db->prepare(
                "INSERT INTO event_registrations 
                 (event_id, student_id, registration_status, attendance_status) 
                 VALUES (:event_id, :student_id, 'Confirmed', 'Registered')"
            );
            $registerStmt->execute([
                ':event_id' => $eventId,
                ':student_id' => $student['student_id']
            ]);
        }
        
        // Check if attendance already recorded for today
        $currentDate = date('Y-m-d');
        $checkStmt = $attendanceSystem->db->prepare(
            "SELECT attendance_id, time_out FROM event_attendance 
             WHERE event_id = :event_id AND day_id = :day_id AND student_id = :student_id 
             AND DATE(time_in) = :current_date 
             ORDER BY time_in DESC LIMIT 1"
        );
        $checkStmt->execute([
            ':event_id' => $eventId,
            ':day_id' => $dayId,
            ':student_id' => $student['student_id'],
            ':current_date' => $currentDate
        ]);
        
        $now = date('Y-m-d H:i:s');
        
        if ($checkStmt->rowCount() > 0) {
            $record = $checkStmt->fetch();
            
            // If last record has no time out, update it (time out)
            if (empty($record['time_out'])) {
                $updateStmt = $attendanceSystem->db->prepare(
                    "UPDATE event_attendance SET time_out = :time_out 
                     WHERE attendance_id = :attendance_id"
                );
                $updateStmt->execute([
                    ':time_out' => $now,
                    ':attendance_id' => $record['attendance_id']
                ]);
                
                $attendanceType = 'Time Out';
            } else {
                // Create new time in record
                $attendanceType = 'Time In';
                createAttendanceRecord($attendanceSystem, [
                    'event_id' => $eventId,
                    'day_id' => $dayId,
                    'student_id' => $student['student_id'],
                    'time_in' => $now,
                    'attendance_status' => 'Present',
                    'recorded_by' => $_SESSION['user_id'] ?? null
                ]);
            }
        } else {
            // Create new time in record
            $attendanceType = 'Time In';
            createAttendanceRecord($attendanceSystem, [
                'event_id' => $eventId,
                'day_id' => $dayId,
                'student_id' => $student['student_id'],
                'time_in' => $now,
                'attendance_status' => 'Present',
                'recorded_by' => $_SESSION['user_id'] ?? null
            ]);
        }
        
        // Get event and day info for response
        $eventStmt = $attendanceSystem->db->prepare(
            "SELECT e.event_name, ed.day_name, ed.event_date 
             FROM events e 
             LEFT JOIN event_days ed ON e.event_id = ed.event_id 
             WHERE e.event_id = :event_id AND (ed.day_id = :day_id OR :day_id IS NULL)
             LIMIT 1"
        );
        $eventStmt->execute([
            ':event_id' => $eventId,
            ':day_id' => $dayId
        ]);
        $eventInfo = $eventStmt->fetch();
        
        // Prepare response
        $response = [
            'success' => true,
            'message' => "$attendanceType recorded successfully",
            'data' => [
                'student_id' => $student['student_id'],
                'first_name' => $student['first_name'],
                'last_name' => $student['last_name'],
                'student_number' => $student['student_number'],
                'profile_image' => $student['profile_image'],
                'department_name' => $student['department_name'] ?? null,
                'course_name' => $student['course_name'] ?? null,
                'event_name' => $eventInfo['event_name'] ?? null,
                'day_name' => $eventInfo['day_name'] ?? null,
                'event_date' => $eventInfo['event_date'] ?? null,
                'time' => $now,
                'attendance_type' => $attendanceType
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Failed to record attendance: ' . $e->getMessage()]);
    }
}

/**
 * Record manual attendance
 */
function recordManualAttendance($attendanceSystem, $data) {
    try {
        // Validate required fields
        $required = ['event_id', 'student_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("$field is required");
            }
        }
        
        // If day_id not provided, use the first active day of the event
        if (empty($data['day_id'])) {
            $dayStmt = $attendanceSystem->db->prepare(
                "SELECT day_id FROM event_days 
                 WHERE event_id = :event_id AND is_active = 1 
                 ORDER BY event_date ASC LIMIT 1"
            );
            $dayStmt->bindParam(':event_id', $data['event_id'], PDO::PARAM_INT);
            $dayStmt->execute();
            
            if ($dayStmt->rowCount() === 0) {
                throw new Exception('No active days found for this event');
            }
            
            $data['day_id'] = $dayStmt->fetch()['day_id'];
        }
        
        // Check if student exists
        $studentStmt = $attendanceSystem->db->prepare(
            "SELECT student_id, first_name, last_name, student_number, profile_image 
             FROM students WHERE student_id = :student_id"
        );
        $studentStmt->bindParam(':student_id', $data['student_id'], PDO::PARAM_INT);
        $studentStmt->execute();
        
        if ($studentStmt->rowCount() === 0) {
            throw new Exception('Student not found');
        }
        
        $student = $studentStmt->fetch();
        
        // Check if student is already registered for the event
        $regStmt = $attendanceSystem->db->prepare(
            "SELECT registration_id FROM event_registrations 
             WHERE event_id = :event_id AND student_id = :student_id"
        );
        $regStmt->execute([
            ':event_id' => $data['event_id'],
            ':student_id' => $data['student_id']
        ]);
        
        if ($regStmt->rowCount() === 0) {
            // Auto-register the student if not already registered
            $registerStmt = $attendanceSystem->db->prepare(
                "INSERT INTO event_registrations 
                 (event_id, student_id, registration_status, attendance_status) 
                 VALUES (:event_id, :student_id, 'Confirmed', 'Registered')"
            );
            $registerStmt->execute([
                ':event_id' => $data['event_id'],
                ':student_id' => $data['student_id']
            ]);
        }
        
        // Set default values
        $now = date('Y-m-d H:i:s');
        $attendanceData = [
            'event_id' => $data['event_id'],
            'day_id' => $data['day_id'],
            'student_id' => $data['student_id'],
            'time_in' => $data['time_in'] ?? $now,
            'time_out' => $data['time_out'] ?? null,
            'attendance_status' => $data['attendance_status'] ?? 'Present',
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $_SESSION['user_id'] ?? null
        ];
        
        // Create attendance record
        $attendanceId = createAttendanceRecord($attendanceSystem, $attendanceData);
        
        // Get event and day info for response
        $eventStmt = $attendanceSystem->db->prepare(
            "SELECT e.event_name, ed.day_name, ed.event_date 
             FROM events e 
             LEFT JOIN event_days ed ON e.event_id = ed.event_id 
             WHERE e.event_id = :event_id AND ed.day_id = :day_id"
        );
        $eventStmt->execute([
            ':event_id' => $data['event_id'],
            ':day_id' => $data['day_id']
        ]);
        $eventInfo = $eventStmt->fetch();
        
        // Prepare response
        $response = [
            'success' => true,
            'message' => 'Attendance recorded successfully',
            'data' => [
                'attendance_id' => $attendanceId,
                'student_id' => $student['student_id'],
                'first_name' => $student['first_name'],
                'last_name' => $student['last_name'],
                'student_number' => $student['student_number'],
                'profile_image' => $student['profile_image'],
                'event_name' => $eventInfo['event_name'] ?? null,
                'day_name' => $eventInfo['day_name'] ?? null,
                'event_date' => $eventInfo['event_date'] ?? null,
                'time_in' => $attendanceData['time_in'],
                'time_out' => $attendanceData['time_out'],
                'attendance_status' => $attendanceData['attendance_status']
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Failed to record attendance: ' . $e->getMessage()]);
    }
}

/**
 * Update an attendance record
 */
function updateAttendance($attendanceSystem, $attendanceId, $data) {
    try {
        // Check if attendance record exists
        $checkStmt = $attendanceSystem->db->prepare(
            "SELECT * FROM event_attendance WHERE attendance_id = :attendance_id"
        );
        $checkStmt->bindParam(':attendance_id', $attendanceId, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception('Attendance record not found');
        }
        
        $currentRecord = $checkStmt->fetch();
        
        // Prepare update fields
        $updates = [];
        $params = [':attendance_id' => $attendanceId];
        
        $allowedFields = [
            'time_in', 'time_out', 'attendance_status', 'notes', 'day_id'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            throw new Exception('No valid fields to update');
        }
        
        // Add updated_by and updated_at
        $updates[] = 'updated_by = :updated_by';
        $params[':updated_by'] = $_SESSION['user_id'] ?? null;
        
        // Update attendance record
        $query = "UPDATE event_attendance SET " . implode(', ', $updates) . " 
                 WHERE attendance_id = :attendance_id";
        
        $stmt = $attendanceSystem->db->prepare($query);
        $stmt->execute($params);
        
        // Get updated record with student and event info
        $updatedStmt = $attendanceSystem->db->prepare(
            "SELECT a.*, s.first_name, s.last_name, s.student_number, s.profile_image,
                    e.event_name, ed.day_name, ed.event_date
             FROM event_attendance a
             JOIN students s ON a.student_id = s.student_id
             LEFT JOIN events e ON a.event_id = e.event_id
             LEFT JOIN event_days ed ON a.day_id = ed.day_id
             WHERE a.attendance_id = :attendance_id"
        );
        $updatedStmt->bindParam(':attendance_id', $attendanceId, PDO::PARAM_INT);
        $updatedStmt->execute();
        
        $updatedRecord = $updatedStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance updated successfully',
            'data' => $updatedRecord
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Failed to update attendance: ' . $e->getMessage()]);
    }
}

/**
 * Delete an attendance record
 */
function deleteAttendance($attendanceSystem, $attendanceId) {
    try {
        // Check if attendance record exists
        $checkStmt = $attendanceSystem->db->prepare(
            "SELECT * FROM event_attendance WHERE attendance_id = :attendance_id"
        );
        $checkStmt->bindParam(':attendance_id', $attendanceId, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception('Attendance record not found');
        }
        
        // Delete the record
        $deleteStmt = $attendanceSystem->db->prepare(
            "DELETE FROM event_attendance WHERE attendance_id = :attendance_id"
        );
        $deleteStmt->bindParam(':attendance_id', $attendanceId, PDO::PARAM_INT);
        $deleteStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance record deleted successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Failed to delete attendance: ' . $e->getMessage()]);
    }
}

/**
 * Helper function to create an attendance record
 */
function createAttendanceRecord($attendanceSystem, $data) {
    $fields = array_keys($data);
    $placeholders = ':' . implode(', :', $fields);
    $fieldsStr = implode(', ', $fields);
    
    $query = "INSERT INTO event_attendance ($fieldsStr) VALUES ($placeholders)";
    $stmt = $attendanceSystem->db->prepare($query);
    
    foreach ($data as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    
    $stmt->execute();
    
    return $attendanceSystem->db->lastInsertId();
}

/**
 * Get attendance summary for an event
 */
function getAttendanceSummary($attendanceSystem, $eventId, $dayId = null) {
    try {
        $params = [':event_id' => $eventId];
        $dayCondition = $dayId ? "AND day_id = :day_id" : "";
        
        if ($dayId) {
            $params[':day_id'] = $dayId;
        }
        
        // Get total registered students
        $registeredStmt = $attendanceSystem->db->prepare(
            "SELECT COUNT(DISTINCT student_id) as total 
             FROM event_registrations 
             WHERE event_id = :event_id"
        );
        $registeredStmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        $registeredStmt->execute();
        $registered = $registeredStmt->fetch()['total'];
        
        // Get present count
        $presentStmt = $attendanceSystem->db->prepare(
            "SELECT COUNT(DISTINCT student_id) as total 
             FROM event_attendance 
             WHERE event_id = :event_id 
             AND attendance_status = 'Present'
             $dayCondition"
        );
        $presentStmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        if ($dayId) {
            $presentStmt->bindParam(':day_id', $dayId, PDO::PARAM_INT);
        }
        $presentStmt->execute();
        $present = $presentStmt->fetch()['total'];
        
        // Get absent count
        $absent = max(0, $registered - $present);
        
        // Get late count
        $lateStmt = $attendanceSystem->db->prepare(
            "SELECT COUNT(DISTINCT student_id) as total 
             FROM event_attendance 
             WHERE event_id = :event_id 
             AND attendance_status = 'Late'
             $dayCondition"
        );
        $lateStmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        if ($dayId) {
            $lateStmt->bindParam(':day_id', $dayId, PDO::PARAM_INT);
        }
        $lateStmt->execute();
        $late = $lateStmt->fetch()['total'];
        
        // Get recent check-ins (last 5)
        $recentStmt = $attendanceSystem->db->prepare(
            "SELECT a.*, s.first_name, s.last_name, s.student_number, s.profile_image
             FROM event_attendance a
             JOIN students s ON a.student_id = s.student_id
             WHERE a.event_id = :event_id
             $dayCondition
             ORDER BY a.time_in DESC
             LIMIT 5"
        );
        $recentStmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        if ($dayId) {
            $recentStmt->bindParam(':day_id', $dayId, PDO::PARAM_INT);
        }
        $recentStmt->execute();
        $recentCheckIns = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'registered' => (int)$registered,
            'present' => (int)$present,
            'absent' => (int)$absent,
            'late' => (int)$late,
            'recent_check_ins' => $recentCheckIns
        ];
        
    } catch (Exception $e) {
        // Return empty summary on error
        return [
            'registered' => 0,
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'recent_check_ins' => []
        ];
    }
}
?>