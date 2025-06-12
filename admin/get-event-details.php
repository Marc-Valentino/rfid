<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get posted data
$data = $_POST;

// Set default response
$response = ['success' => false, 'message' => ''];

try {
    // Check if this is a request to get event details
    if (isset($data['event_id']) && !isset($data['action'])) {
        $stmt = $db->prepare("SELECT e.*, et.type_name, u.full_name as organizer_name 
                            FROM events e 
                            LEFT JOIN event_types et ON e.type_id = et.type_id 
                            LEFT JOIN users u ON e.organizer_id = u.user_id 
                            WHERE e.event_id = ?");
        $stmt->execute([$data['event_id']]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($event) {
            header('Content-Type: application/json');
            echo json_encode($event);
            exit;
        } else {
            throw new Exception('Event not found');
        }
    }
    
    // Check if this is a delete action
    if (isset($data['action']) && $data['action'] === 'delete') {
        if (empty($data['event_id'])) {
            throw new Exception('Event ID is required for deletion');
        }

        // Delete the event
        $stmt = $db->prepare("DELETE FROM events WHERE event_id = ?");
        $result = $stmt->execute([$data['event_id']]);

        if ($result && $stmt->rowCount() > 0) {
            $response = [
                'success' => true,
                'message' => 'Event deleted successfully'
            ];
        } else {
            throw new Exception('Failed to delete event or event not found');
        }
    } else {
        // This is a create/update action, validate required fields
        if (!isset($data['event_name'], $data['type_id'], $data['start_date'], 
            $data['end_date'], $data['organizer_id'])) {
            throw new Exception('All required fields are not provided');
        }

        // Parse dates
        $start_date = date('Y-m-d', strtotime($data['start_date']));
        $end_date = date('Y-m-d', strtotime($data['end_date']));

        if ($start_date >= $end_date) {
            throw new Exception('End date must be after start date');
        }

        // Check if we're editing or creating a new event
        $isEdit = !empty($data['event_id']);

        // Prepare the data
        $event_data = [
            'event_name' => $data['event_name'],
            'type_id' => $data['type_id'],
            'start_date' => $start_date,
            'end_date' => $end_date,
            'organizer_id' => $data['organizer_id'],
            'location' => $data['location'] ?? null,
            'description' => $data['description'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null
        ];

        if ($isEdit) {
            // Update existing event
            $sql = "UPDATE events SET 
                event_name = :event_name,
                type_id = :type_id,
                start_date = :start_date,
                end_date = :end_date,
                organizer_id = :organizer_id,
                location = :location,
                description = :description,
                contact_email = :contact_email,
                contact_phone = :contact_phone,
                updated_at = CURRENT_TIMESTAMP
                WHERE event_id = :event_id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':event_id', $data['event_id'], PDO::PARAM_INT);
            $stmt->bindParam(':event_name', $event_data['event_name'], PDO::PARAM_STR);
            $stmt->bindParam(':type_id', $event_data['type_id'], PDO::PARAM_INT);
            $stmt->bindParam(':start_date', $event_data['start_date'], PDO::PARAM_STR);
            $stmt->bindParam(':end_date', $event_data['end_date'], PDO::PARAM_STR);
            $stmt->bindParam(':organizer_id', $event_data['organizer_id'], PDO::PARAM_INT);
            $stmt->bindParam(':location', $event_data['location'], PDO::PARAM_STR);
            $stmt->bindParam(':description', $event_data['description'], PDO::PARAM_STR);
            $stmt->bindParam(':contact_email', $event_data['contact_email'], PDO::PARAM_STR);
            $stmt->bindParam(':contact_phone', $event_data['contact_phone'], PDO::PARAM_STR);
            
            $result = $stmt->execute();
            $response = [
                'success' => $result,
                'message' => $result ? 'Event updated successfully' : 'Failed to update event'
            ];
        } else {
            // Insert new event
            $sql = "INSERT INTO events (
                event_name, type_id, start_date, end_date, organizer_id,
                location, description, contact_email, contact_phone, created_at, updated_at
            ) VALUES (
                :event_name, :type_id, :start_date, :end_date, :organizer_id,
                :location, :description, :contact_email, :contact_phone, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )";

            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                ':event_name' => $event_data['event_name'],
                ':type_id' => $event_data['type_id'],
                ':start_date' => $event_data['start_date'],
                ':end_date' => $event_data['end_date'],
                ':organizer_id' => $event_data['organizer_id'],
                ':location' => $event_data['location'],
                ':description' => $event_data['description'],
                ':contact_email' => $event_data['contact_email'],
                ':contact_phone' => $event_data['contact_phone']
            ]);

            $response = [
                'success' => $result,
                'message' => $result ? 'Event created successfully' : 'Failed to create event',
                'event_id' => $result ? $db->lastInsertId() : null
            ];
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

// Set content type and return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
