<?php
// API endpoint for event management
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

// Check if user is logged in and has appropriate permissions
if (!$attendanceSystem->isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get request method and input data
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Handle different request methods
switch ($method) {
    case 'GET':
        // Get all events or a specific event
        if (isset($_GET['event_id'])) {
            getEvent($attendanceSystem, $_GET['event_id']);
        } else {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $status = isset($_GET['status']) ? $_GET['status'] : null; // upcoming, ongoing, past, all
            getEvents($attendanceSystem, $page, $limit, $status);
        }
        break;
        
    case 'POST':
        // Create a new event
        if (empty($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No input data provided']);
            exit();
        }
        createEvent($attendanceSystem, $input);
        break;
        
    case 'PUT':
        // Update an existing event
        if (empty($input) || !isset($_GET['event_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Event ID and data are required']);
            exit();
        }
        updateEvent($attendanceSystem, (int)$_GET['event_id'], $input);
        break;
        
    case 'DELETE':
        // Delete an event
        if (!isset($_GET['event_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Event ID is required']);
            exit();
        }
        deleteEvent($attendanceSystem, (int)$_GET['event_id']);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}

/**
 * Get list of events with pagination and filtering
 */
function getEvents($attendanceSystem, $page = 1, $limit = 10, $status = null) {
    try {
        $offset = ($page - 1) * $limit;
        $params = [':limit' => $limit, ':offset' => $offset];
        
        $whereClause = "";
        $currentDate = date('Y-m-d');
        
        if ($status === 'upcoming') {
            $whereClause = "WHERE e.start_date > :current_date";
            $params[':current_date'] = $currentDate;
        } elseif ($status === 'ongoing') {
            $whereClause = "WHERE e.start_date <= :current_date AND e.end_date >= :current_date2";
            $params[':current_date'] = $currentDate;
            $params[':current_date2'] = $currentDate;
        } elseif ($status === 'past') {
            $whereClause = "WHERE e.end_date < :current_date";
            $params[':current_date'] = $currentDate;
        }
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM events e $whereClause";
        $countStmt = $attendanceSystem->db->prepare($countQuery);
        foreach ($params as $key => $value) {
            if ($key !== ':limit' && $key !== ':offset') {
                $countStmt->bindValue($key, $value);
            }
        }
        $countStmt->execute();
        $total = $countStmt->fetch()['total'];
        
        // Get events with type and organizer info
        $query = "SELECT e.*, et.type_name, u.full_name as organizer_name 
                 FROM events e 
                 LEFT JOIN event_types et ON e.type_id = et.type_id 
                 LEFT JOIN users u ON e.organizer_id = u.user_id 
                 $whereClause 
                 ORDER BY e.start_date DESC, e.event_name ASC 
                 LIMIT :limit OFFSET :offset";
        
        $stmt = $attendanceSystem->db->prepare($query);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get event days for each event
        foreach ($events as &$event) {
            $event['days'] = getEventDays($attendanceSystem, $event['event_id']);
            $event['participant_count'] = getEventParticipantCount($attendanceSystem, $event['event_id']);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $events,
            'pagination' => [
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve events: ' . $e->getMessage()]);
    }
}

/**
 * Get a single event by ID
 */
function getEvent($attendanceSystem, $eventId) {
    try {
        $query = "SELECT e.*, et.type_name, u.full_name as organizer_name 
                 FROM events e 
                 LEFT JOIN event_types et ON e.type_id = et.type_id 
                 LEFT JOIN users u ON e.organizer_id = u.user_id 
                 WHERE e.event_id = :event_id";
        
        $stmt = $attendanceSystem->db->prepare($query);
        $stmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        $stmt->execute();
        
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Event not found']);
            return;
        }
        
        // Get event days
        $event['days'] = getEventDays($attendanceSystem, $eventId);
        
        // Get participant count
        $event['participant_count'] = getEventParticipantCount($attendanceSystem, $eventId);
        
        echo json_encode(['success' => true, 'data' => $event]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve event: ' . $e->getMessage()]);
    }
}

/**
 * Create a new event
 */
function createEvent($attendanceSystem, $data) {
    try {
        $attendanceSystem->db->beginTransaction();
        
        // Validate required fields
        $required = ['event_name', 'start_date', 'end_date', 'type_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("$field is required");
            }
        }
        
        // Prepare event data
        $eventData = [
            'event_name' => $data['event_name'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'location' => $data['location'] ?? null,
            'type_id' => $data['type_id'],
            'max_participants' => $data['max_participants'] ?? null,
            'registration_deadline' => $data['registration_deadline'] ?? null,
            'is_public' => $data['is_public'] ?? 1,
            'organizer_id' => $_SESSION['user_id'],
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null
        ];
        
        // Insert event
        $fields = implode(', ', array_keys($eventData));
        $placeholders = ':' . implode(', :', array_keys($eventData));
        
        $query = "INSERT INTO events ($fields) VALUES ($placeholders)";
        $stmt = $attendanceSystem->db->prepare($query);
        
        foreach ($eventData as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        $stmt->execute();
        $eventId = $attendanceSystem->db->lastInsertId();
        
        // Process event days if provided
        if (!empty($data['days']) && is_array($data['days'])) {
            createEventDays($attendanceSystem, $eventId, $data['days']);
        } else {
            // Create a single day event by default
            createSingleEventDay($attendanceSystem, $eventId, $data['start_date']);
        }
        
        $attendanceSystem->db->commit();
        
        // Return the created event
        getEvent($attendanceSystem, $eventId);
        
    } catch (Exception $e) {
        $attendanceSystem->db->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Failed to create event: ' . $e->getMessage()]);
    }
}

/**
 * Update an existing event
 */
function updateEvent($attendanceSystem, $eventId, $data) {
    try {
        $attendanceSystem->db->beginTransaction();
        
        // Check if event exists
        $checkStmt = $attendanceSystem->db->prepare("SELECT event_id FROM events WHERE event_id = ?");
        $checkStmt->execute([$eventId]);
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception('Event not found');
        }
        
        // Prepare update fields
        $updates = [];
        $params = [':event_id' => $eventId];
        
        $allowedFields = [
            'event_name', 'description', 'start_date', 'end_date', 'location',
            'type_id', 'max_participants', 'registration_deadline', 'is_public',
            'organizer_id', 'contact_email', 'contact_phone'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            throw new Exception('No valid fields to update');
        }
        
        // Update event
        $query = "UPDATE events SET " . implode(', ', $updates) . " WHERE event_id = :event_id";
        $stmt = $attendanceSystem->db->prepare($query);
        $stmt->execute($params);
        
        // Update event days if provided
        if (isset($data['days']) && is_array($data['days'])) {
            // Delete existing days
            $delStmt = $attendanceSystem->db->prepare("DELETE FROM event_days WHERE event_id = ?");
            $delStmt->execute([$eventId]);
            
            // Add new days
            if (!empty($data['days'])) {
                createEventDays($attendanceSystem, $eventId, $data['days']);
            } else {
                // If no days provided, ensure at least one day exists
                createSingleEventDay($attendanceSystem, $eventId, $data['start_date'] ?? date('Y-m-d'));
            }
        }
        
        $attendanceSystem->db->commit();
        
        // Return the updated event
        getEvent($attendanceSystem, $eventId);
        
    } catch (Exception $e) {
        $attendanceSystem->db->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Failed to update event: ' . $e->getMessage()]);
    }
}

/**
 * Delete an event
 */
function deleteEvent($attendanceSystem, $eventId) {
    try {
        // Start transaction
        $attendanceSystem->db->beginTransaction();
        
        // Check if event exists
        $checkStmt = $attendanceSystem->db->prepare("SELECT event_id FROM events WHERE event_id = ?");
        $checkStmt->execute([$eventId]);
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception('Event not found');
        }
        
        // Delete related records first (attendance, days, registrations)
        $tables = ['event_attendance', 'event_registrations', 'event_days'];
        
        foreach ($tables as $table) {
            $stmt = $attendanceSystem->db->prepare("DELETE FROM $table WHERE event_id = ?");
            $stmt->execute([$eventId]);
        }
        
        // Delete the event
        $stmt = $attendanceSystem->db->prepare("DELETE FROM events WHERE event_id = ?");
        $stmt->execute([$eventId]);
        
        $attendanceSystem->db->commit();
        
        echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
        
    } catch (Exception $e) {
        $attendanceSystem->db->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete event: ' . $e->getMessage()]);
    }
}

/**
 * Helper function to create event days
 */
function createEventDays($attendanceSystem, $eventId, $days) {
    foreach ($days as $day) {
        $dayData = [
            'event_id' => $eventId,
            'day_number' => $day['day_number'] ?? null,
            'day_name' => $day['day_name'] ?? null,
            'event_date' => $day['event_date'],
            'is_active' => $day['is_active'] ?? 1
        ];
        
        $fields = implode(', ', array_keys($dayData));
        $placeholders = ':' . implode(', :', array_keys($dayData));
        
        $query = "INSERT INTO event_days ($fields) VALUES ($placeholders)";
        $stmt = $attendanceSystem->db->prepare($query);
        
        foreach ($dayData as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        $stmt->execute();
    }
}

/**
 * Helper function to create a single event day
 */
function createSingleEventDay($attendanceSystem, $eventId, $eventDate) {
    $dayData = [
        'event_id' => $eventId,
        'day_number' => 1,
        'day_name' => 'Day 1',
        'event_date' => $eventDate,
        'is_active' => 1
    ];
    
    $fields = implode(', ', array_keys($dayData));
    $placeholders = ':' . implode(', :', array_keys($dayData));
    
    $query = "INSERT INTO event_days ($fields) VALUES ($placeholders)";
    $stmt = $attendanceSystem->db->prepare($query);
    
    foreach ($dayData as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    
    $stmt->execute();
}

/**
 * Get event days for a specific event
 */
function getEventDays($attendanceSystem, $eventId) {
    $query = "SELECT * FROM event_days WHERE event_id = :event_id ORDER BY event_date ASC";
    $stmt = $attendanceSystem->db->prepare($query);
    $stmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get participant count for an event
 */
function getEventParticipantCount($attendanceSystem, $eventId) {
    $query = "SELECT COUNT(*) as count FROM event_registrations WHERE event_id = :event_id";
    $stmt = $attendanceSystem->db->prepare($query);
    $stmt->bindParam(':event_id', $eventId, PDO::PARAM_INT);
    $stmt->execute();
    
    return (int)$stmt->fetch()['count'];
}
?>
