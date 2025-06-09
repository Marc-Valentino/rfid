<?php
// Get the attendance type directly from POST
$attendanceType = $_POST['type']; // Will be either 'Time In' or 'Time Out'

// Validate attendance type
if (!in_array($attendanceType, ['Time In', 'Time Out'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid attendance type'
    ]);
    exit;
}

try {
    // Prepare the insert statement
    $query = "INSERT INTO attendance_records 
              (student_id, rfid_uid, reader_id, scan_time, attendance_type, attendance_date, location, verification_status, ip_address) 
              VALUES 
              (?, ?, ?, NOW(), ?, CURDATE(), ?, 'Verified', ?)";
              
    $stmt = $conn->prepare($query);
    
    // Bind parameters
    $stmt->bind_param("iissss", 
        $studentId,      // integer
        $rfidUid,       // string
        $readerId,      // integer
        $attendanceType, // string - will be either 'Time In' or 'Time Out'
        $location,      // string
        $ipAddress      // string
    );
    
    // Execute the statement
    if ($stmt->execute()) {
        // Success
        echo json_encode([
            'success' => true, 
            'message' => "Attendance recorded successfully",
            'type' => $attendanceType
        ]);
    } else {
        // Error
        throw new Exception($stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error recording attendance: ' . $e->getMessage()
    ]);
}