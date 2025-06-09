<?php
require_once __DIR__ . '/../config/config.php';

class AttendanceSystem {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // Authentication Functions
    public function login($username, $password) {
        try {
            $query = "SELECT user_id, username, password_hash, full_name, role, is_active 
                     FROM users WHERE username = :username AND is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch();
                if (password_verify($password, $user['password_hash'])) {
                    // Update last login
                    $this->updateLastLogin($user['user_id']);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    private function updateLastLogin($user_id) {
        $query = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
    }

    public function logout() {
        session_destroy();
        return true;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['login_time']) && 
               (time() - $_SESSION['login_time']) < SESSION_TIMEOUT;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . BASE_URL . 'login.php');
            exit();
        }
    }

    // Student Functions
    public function getStudentByRFID($rfid_uid) {
        try {
            $query = "SELECT s.*, r.card_status, d.department_name, c.course_name 
                     FROM students s 
                     JOIN rfid_cards r ON s.student_id = r.student_id 
                     LEFT JOIN departments d ON s.department_id = d.department_id
                     LEFT JOIN courses c ON s.course_id = c.course_id
                     WHERE r.rfid_uid = :rfid_uid AND r.card_status = 'Active' AND s.is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':rfid_uid', $rfid_uid);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get student error: " . $e->getMessage());
            return false;
        }
    }
    
    public function checkRFIDCardExists($rfid_uid) {
        try {
            $query = "SELECT r.*, s.first_name, s.last_name, s.student_number 
                     FROM rfid_cards r 
                     JOIN students s ON r.student_id = s.student_id 
                     WHERE r.rfid_uid = :rfid_uid AND r.card_status = 'Active'"; 
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':rfid_uid', $rfid_uid);
            $stmt->execute();
            
            // Return boolean true if card exists, false otherwise
            return ($stmt->rowCount() > 0);
        } catch (Exception $e) {
            error_log("Check RFID card exists error: " . $e->getMessage());
            return false;
        }
    }

    public function getAllStudents($limit = 50, $offset = 0) {
        try {
            $query = "SELECT s.*, d.department_name, c.course_name, r.rfid_uid, r.card_status
                     FROM students s 
                     LEFT JOIN departments d ON s.department_id = d.department_id
                     LEFT JOIN courses c ON s.course_id = c.course_id
                     LEFT JOIN rfid_cards r ON s.student_id = r.student_id
                     WHERE s.is_active = 1 
                     ORDER BY s.last_name, s.first_name
                     LIMIT :limit OFFSET :offset";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get students error: " . $e->getMessage());
            return [];
        }
    }

    // Attendance Functions
    public function recordAttendance($student_id, $rfid_uid, $attendance_type = 'Time In', $reader_id = 1) {
        try {
            $this->db->beginTransaction();
            
            // Insert attendance record
            $query = "INSERT INTO attendance_records 
                     (student_id, rfid_uid, reader_id, attendance_type, attendance_date, location, ip_address) 
                     VALUES (:student_id, :rfid_uid, :reader_id, :attendance_type, CURDATE(), 'Main Entrance', :ip_address)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':rfid_uid', $rfid_uid);
            $stmt->bindParam(':reader_id', $reader_id);
            $stmt->bindParam(':attendance_type', $attendance_type);
            $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
            $stmt->execute();
            
            // Update daily summary
            $this->updateDailySummary($student_id);
            
            // Update RFID card last used
            $this->updateRFIDLastUsed($rfid_uid);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Record attendance error: " . $e->getMessage());
            return false;
        }
    }
    
    public function recordAttendanceByRFID($rfid_uid, $reader_id = 1, $attendance_type = 'Time In') {
        try {
            // Check if reader exists, if not create it
            $query = "SELECT COUNT(*) FROM rfid_readers WHERE reader_id = :reader_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':reader_id', $reader_id);
            $stmt->execute();
            
            if ($stmt->fetchColumn() == 0) {
                // Reader doesn't exist, create it
                $insertQuery = "INSERT INTO rfid_readers 
                               (reader_id, reader_name, reader_location, reader_type, reader_status) 
                               VALUES 
                               (:reader_id, 'Default Reader', 'Main Entrance', 'USB', 'Online')";
                $insertStmt = $this->db->prepare($insertQuery);
                $insertStmt->bindParam(':reader_id', $reader_id);
                $insertStmt->execute();
            }
            
            // Get student information from RFID UID
            $student = $this->getStudentByRFID($rfid_uid);
            
            if (!$student) {
                return [
                    'success' => false,
                    'message' => 'Student not found for this RFID card'
                ];
            }
            
            // Check for recent scan to prevent duplicates (within 30 seconds)
            $query = "SELECT * FROM attendance_records 
                     WHERE rfid_uid = :rfid_uid 
                     AND scan_time > DATE_SUB(NOW(), INTERVAL 30 SECOND)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':rfid_uid', $rfid_uid);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $recent_scan = $stmt->fetch();
                return [
                    'success' => true,
                    'data' => [
                        'student_id' => $student['student_id'],
                        'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                        'student_number' => $student['student_number'],
                        'rfid_uid' => $rfid_uid,
                        'attendance_id' => $recent_scan['attendance_id'],
                        'scan_time' => $recent_scan['scan_time'],
                        'attendance_type' => $recent_scan['attendance_type']
                    ],
                    'message' => 'Recent scan detected. Using previous record.'
                ];
            }
            
            // Record attendance
            $this->db->beginTransaction();
            
            // Insert attendance record
            $query = "INSERT INTO attendance_records 
                     (student_id, rfid_uid, reader_id, attendance_type, attendance_date, location, ip_address) 
                     VALUES (:student_id, :rfid_uid, :reader_id, :attendance_type, CURDATE(), 'Main Entrance', :ip_address)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':student_id', $student['student_id']);
            $stmt->bindParam(':rfid_uid', $rfid_uid);
            $stmt->bindParam(':reader_id', $reader_id);
            $stmt->bindParam(':attendance_type', $attendance_type);
            $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
            $stmt->execute();
            
            $attendance_id = $this->db->lastInsertId();
            
            // Update daily summary
            $this->updateDailySummary($student['student_id']);
            
            // Update RFID card last used
            $this->updateRFIDLastUsed($rfid_uid);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'data' => [
                    'student_id' => $student['student_id'],
                    'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                    'student_number' => $student['student_number'],
                    'rfid_uid' => $rfid_uid,
                    'attendance_id' => $attendance_id,
                    'scan_time' => date('Y-m-d H:i:s'),
                    'attendance_type' => $attendance_type
                ],
                'message' => 'Attendance recorded successfully'
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Record attendance by RFID error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error recording attendance: ' . $e->getMessage()
            ];
        }
    }

    // Change from private to public
    public function updateDailySummary($student_id) {
        $query = "INSERT INTO daily_attendance_summary 
                 (student_id, attendance_date, first_time_in, attendance_status) 
                 SELECT :student_id, CURDATE(), MIN(scan_time), 'Present'
                 FROM attendance_records 
                 WHERE student_id = :student_id2 AND attendance_date = CURDATE()
                 ON DUPLICATE KEY UPDATE 
                 last_time_out = (SELECT MAX(scan_time) FROM attendance_records 
                                 WHERE student_id = :student_id3 AND attendance_date = CURDATE()),
                 total_hours = TIMESTAMPDIFF(MINUTE, first_time_in, 
                            (SELECT MAX(scan_time) FROM attendance_records 
                             WHERE student_id = :student_id4 AND attendance_date = CURDATE())) / 60.0";                             
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':student_id2', $student_id);
            $stmt->bindParam(':student_id3', $student_id);
            $stmt->bindParam(':student_id4', $student_id);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Update daily summary error: " . $e->getMessage());
            return false;
        }
    }

    private function updateRFIDLastUsed($rfid_uid) {
        $query = "UPDATE rfid_cards SET last_used = NOW() WHERE rfid_uid = :rfid_uid";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':rfid_uid', $rfid_uid);
        $stmt->execute();
    }

    public function getTodayAttendance($limit = 50) {
        try {
            $query = "SELECT ar.*, s.student_number, s.first_name, s.last_name, 
                            d.department_name, c.course_name
                     FROM attendance_records ar
                     JOIN students s ON ar.student_id = s.student_id
                     LEFT JOIN departments d ON s.department_id = d.department_id
                     LEFT JOIN courses c ON s.course_id = c.course_id
                     WHERE ar.attendance_date = CURDATE()
                     ORDER BY ar.scan_time DESC
                     LIMIT :limit";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get today attendance error: " . $e->getMessage());
            return [];
        }
    }

    // Utility Functions
    public function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }

    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public function formatDateTime($datetime) {
        return date('M d, Y h:i A', strtotime($datetime));
    }

    public function formatTime($time) {
        return date('h:i A', strtotime($time));
    }

    // Add these methods to the AttendanceSystem class

    
    public function executeQuery($query, $params = [], $fetchAll = false) {
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            // For SELECT queries
            if (stripos(trim($query), 'SELECT') === 0) {
                if ($fetchAll) {
                    return $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    return $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
            
            // For non-SELECT queries (INSERT, UPDATE, DELETE)
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Query execution error: " . $e->getMessage());
            return false;
        }
    }

    public function executeCountQuery($query, $params = []) {
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'];
        } catch (Exception $e) {
            error_log("Count query error: " . $e->getMessage());
            return 0;
        }
    }

    public function getTeachersPaginated($conditions = [], $params = [], $limit = 10, $offset = 0) {
        try {
            // Make sure role='teacher' is in the conditions
            if (!in_array("u.role = 'teacher'", $conditions)) {
                $conditions[] = "u.role = 'teacher'";
            }
            
            $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
            
            $query = "SELECT 
                u.user_id, 
                u.username,
                u.full_name, 
                COALESCE(id.employee_id, '') as employee_id,
                u.email, 
                COALESCE(id.department_id, u.department_id) as department_id,
                d.department_name,
                COALESCE(id.office_location, u.office_location) as office_location,
                COALESCE(id.phone, u.phone) as phone,
                GROUP_CONCAT(DISTINCT c.course_name SEPARATOR ', ') as assigned_courses
            FROM users u 
            LEFT JOIN instructor_details id ON u.user_id = id.user_id
            LEFT JOIN departments d ON COALESCE(id.department_id, u.department_id) = d.department_id 
            LEFT JOIN instructor_courses ic ON u.user_id = ic.instructor_id
            LEFT JOIN courses c ON ic.course_id = c.course_id 
            $where_clause
            GROUP BY u.user_id 
            ORDER BY u.full_name 
            LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key + 1, $value);
            }
            
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $teachers = $stmt->fetchAll();
            
            // Use the same table joins for the count query
            $count_query = "SELECT COUNT(DISTINCT u.user_id) as total FROM users u 
                           LEFT JOIN instructor_details id ON u.user_id = id.user_id
                           LEFT JOIN departments d ON COALESCE(id.department_id, u.department_id) = d.department_id 
                           LEFT JOIN instructor_courses ic ON u.user_id = ic.instructor_id
                           LEFT JOIN courses c ON ic.course_id = c.course_id
                           $where_clause";
            $count_stmt = $this->db->prepare($count_query);
            foreach ($params as $key => $value) {
                $count_stmt->bindValue($key + 1, $value);
            }
            $count_stmt->execute();
            $total_records = $count_stmt->fetch()['total'];
            
            return [
                'teachers' => $teachers,
                'total_records' => $total_records
            ];
            
        } catch (Exception $e) {
            error_log("Get teachers error: " . $e->getMessage());
            return [
                'teachers' => [],
                'total_records' => 0
            ];
        }
    }

    public function createSession($instructor_id, $course_id, $session_date, $start_time, $end_time, $room_location, $session_type, $description = '') {
        try {
            $query = "INSERT INTO class_sessions (course_id, instructor_id, session_date, start_time, end_time, room_location, session_type, description, session_status, created_at) 
                     VALUES (:course_id, :instructor_id, :session_date, :start_time, :end_time, :room_location, :session_type, :description, 'Scheduled', NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':course_id', $course_id);
            $stmt->bindParam(':instructor_id', $instructor_id);
            $stmt->bindParam(':session_date', $session_date);
            $stmt->bindParam(':start_time', $start_time);
            $stmt->bindParam(':end_time', $end_time);
            $stmt->bindParam(':room_location', $room_location);
            $stmt->bindParam(':session_type', $session_type);
            $stmt->bindParam(':description', $description);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Create session error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUpcomingSessions($instructor_id) {
        try {
            $query = "SELECT cs.*, c.course_name, c.course_code 
                     FROM class_sessions cs
                     JOIN courses c ON cs.course_id = c.course_id
                     WHERE cs.instructor_id = :instructor_id 
                     AND cs.session_date >= CURDATE()
                     AND cs.session_status IN ('Scheduled', 'Active')
                     ORDER BY cs.session_date ASC, cs.start_time ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':instructor_id', $instructor_id);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get upcoming sessions error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getPastSessions($instructor_id) {
        try {
            $query = "SELECT cs.*, c.course_name, c.course_code,
                     COUNT(DISTINCT ar.student_id) as attendance_count,
                     (SELECT COUNT(*) FROM students s WHERE s.course_id = cs.course_id) as total_students
                     FROM class_sessions cs
                     JOIN courses c ON cs.course_id = c.course_id
                     LEFT JOIN attendance_records ar ON cs.session_id = ar.session_id AND ar.status = 'present'
                     WHERE cs.instructor_id = :instructor_id 
                     AND (cs.session_date < CURDATE() OR cs.session_status = 'Completed')
                     GROUP BY cs.session_id
                     ORDER BY cs.session_date DESC, cs.start_time DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':instructor_id', $instructor_id);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get past sessions error: " . $e->getMessage());
            return [];
        }
    }
    
    public function updateSessionStatus($session_id, $status) {
        try {
            $query = "UPDATE class_sessions SET session_status = :status, updated_at = NOW() WHERE session_id = :session_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':session_id', $session_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Update session status error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteSession($session_id) {
        try {
            // First check if there are any attendance records for this session
            $check_query = "SELECT COUNT(*) FROM attendance_records WHERE session_id = :session_id";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':session_id', $session_id);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                // If there are attendance records, just mark as cancelled instead of deleting
                return $this->updateSessionStatus($session_id, 'Cancelled');
            } else {
                // If no attendance records, safe to delete
                $query = "DELETE FROM class_sessions WHERE session_id = :session_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':session_id', $session_id);
                return $stmt->execute();
            }
        } catch (Exception $e) {
            error_log("Delete session error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getSessionById($session_id) {
        try {
            $query = "SELECT cs.*, c.course_name, c.course_code 
                     FROM class_sessions cs
                     JOIN courses c ON cs.course_id = c.course_id
                     WHERE cs.session_id = :session_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':session_id', $session_id);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get session by ID error: " . $e->getMessage());
            return false;
        }
    }

    public function getStudentsForInstructor($instructor_id) {
        try {
            $query = "SELECT DISTINCT 
                    s.*, 
                    c.course_name,
                    c.course_code,
                    d.department_name,
                    r.rfid_uid,
                    r.card_status,
                    ce.enrollment_status,
                    ce.enrollment_date
                 FROM students s
                 INNER JOIN course_enrollments ce ON s.student_id = ce.student_id
                 INNER JOIN courses c ON ce.course_id = c.course_id
                 LEFT JOIN departments d ON s.department_id = d.department_id
                 LEFT JOIN rfid_cards r ON s.student_id = r.student_id
                 WHERE ce.instructor_id = :instructor_id
                 AND ce.enrollment_status = 'Active'
                 ORDER BY s.last_name, s.first_name";
                 
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':instructor_id', $instructor_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get students for instructor error: " . $e->getMessage());
            return [];
        }
    }
    
    public function addStudentToCourse($student_data) {
        try {
            $this->db->beginTransaction();
            
            // Insert student
            $query = "INSERT INTO students (student_number, first_name, last_name, email, course_id, enrollment_status)
                     VALUES (:student_number, :first_name, :last_name, :email, :course_id, 'Active')";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':student_number', $student_data['student_id']);
            
            // Split full name into first and last name
            $name_parts = explode(' ', $student_data['full_name'], 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
            
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':email', $student_data['email']);
            $stmt->bindParam(':course_id', $student_data['course_id']);
            $stmt->execute();
            
            $student_id = $this->db->lastInsertId();
            
            // Add RFID card if provided
            if (!empty($student_data['rfid_card'])) {
                $this->assignRFIDToStudent($student_id, $student_data['rfid_card']);
            }
            
            $this->db->commit();
            return $student_id;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Add student error: " . $e->getMessage());
            return false;
        }
    }
    
    public function assignRFIDToStudent($student_id, $rfid_uid) {
        try {
            // Check if RFID is already assigned
            $check_query = "SELECT student_id FROM rfid_cards WHERE rfid_uid = :rfid_uid";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':rfid_uid', $rfid_uid);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                return false; // RFID already assigned
            }
            
            $query = "INSERT INTO rfid_cards (rfid_uid, student_id, card_status, issue_date)
                     VALUES (:rfid_uid, :student_id, 'Active', CURDATE())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':rfid_uid', $rfid_uid);
            $stmt->bindParam(':student_id', $student_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Assign RFID error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateStudent($student_id, $student_data) {
        try {
            // Split full name into first and last name
            $name_parts = explode(' ', $student_data['full_name'], 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
            
            $query = "UPDATE students SET 
                     student_number = :student_number,
                     first_name = :first_name,
                     last_name = :last_name,
                     email = :email,
                     course_id = :course_id,
                     updated_at = NOW()
                     WHERE student_id = :student_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':student_number', $student_data['student_id']);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':email', $student_data['email']);
            $stmt->bindParam(':course_id', $student_data['course_id']);
            $stmt->bindParam(':student_id', $student_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Update student error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteStudent($student_id) {
        try {
            // Soft delete - set is_active to 0
            $query = "UPDATE students SET is_active = 0, updated_at = NOW() WHERE student_id = :student_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':student_id', $student_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Delete student error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getStudentAttendanceHistory($student_id, $limit = 50) {
        try {
            $query = "SELECT ar.*, c.course_name, c.course_code
                     FROM attendance_records ar
                     LEFT JOIN courses c ON ar.course_id = c.course_id
                     WHERE ar.student_id = :student_id
                     ORDER BY ar.scan_time DESC
                     LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get student attendance error: " . $e->getMessage());
            return [];
        }
    }

    public function getInstructorCourses($instructor_id) {
        try {
            $query = "SELECT c.*, d.department_name, 
                                COUNT(DISTINCT s.student_id) as enrolled_students
                         FROM courses c
                         JOIN instructor_courses ic ON c.course_id = ic.course_id
                         LEFT JOIN departments d ON c.department_id = d.department_id
                         LEFT JOIN students s ON c.course_id = s.course_id AND s.is_active = 1
                         WHERE ic.instructor_id = :instructor_id AND c.is_active = 1
                         GROUP BY c.course_id
                         ORDER BY c.course_name";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':instructor_id', $instructor_id);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get instructor courses error: " . $e->getMessage());
            return [];
        }
    }

    public function getAllCourses() {
        try {
            $query = "SELECT c.*, d.department_name, 
                  COALESCE(u.full_name, 'Not Assigned') as instructor_name 
                  FROM courses c 
                  LEFT JOIN departments d ON c.department_id = d.department_id 
                  LEFT JOIN course_enrollments ce ON c.course_id = ce.course_id
                  LEFT JOIN users u ON ce.instructor_id = u.user_id AND u.role = 'teacher'
                  WHERE c.is_active = 1 
                  ORDER BY c.course_name";
        
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAllCourses: " . $e->getMessage());
            return [];
        }
    }

    public function assignCoursesToInstructor($instructor_id, $course_ids) {
        try {
            $this->db->beginTransaction();
            
            // First, delete all current course assignments for this instructor
            $query = "DELETE FROM instructor_courses WHERE instructor_id = :instructor_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':instructor_id', $instructor_id);
            $stmt->execute();
            
            // Then assign the new courses
            if (!empty($course_ids)) {
                $query = "INSERT INTO instructor_courses (instructor_id, course_id, is_primary) 
                         VALUES (:instructor_id, :course_id, 1)";
                $stmt = $this->db->prepare($query);
                
                foreach ($course_ids as $course_id) {
                    $stmt->bindParam(':instructor_id', $instructor_id);
                    $stmt->bindParam(':course_id', $course_id);
                    $stmt->execute();
                }
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Assign courses to instructor error: " . $e->getMessage());
            return false;
        }
    }

    public function getTodaySessions($instructor_id) {
        try {
            $today = date('Y-m-d');
            $query = "SELECT cs.*, c.course_name, c.course_code,
                                COUNT(DISTINCT ar.student_id) as present_students
                         FROM class_sessions cs
                         JOIN courses c ON cs.course_id = c.course_id
                         LEFT JOIN attendance_records ar ON cs.session_id = ar.session_id 
                                AND DATE(ar.scan_time) = :today
                         WHERE c.instructor_id = :instructor_id 
                                AND DATE(cs.session_date) = :today
                         GROUP BY cs.session_id
                         ORDER BY cs.start_time";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':instructor_id', $instructor_id);
            $stmt->bindParam(':today', $today);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get today sessions error: " . $e->getMessage());
            return [];
        }
    }

    public function getRecentAttendanceForInstructor($instructor_id, $limit = 10) {
        try {
            $query = "SELECT ar.*, s.first_name, s.last_name, s.student_number,
                                c.course_name, c.course_code
                         FROM attendance_records ar
                         JOIN students s ON ar.student_id = s.student_id
                         JOIN courses c ON ar.course_id = c.course_id
                         WHERE c.instructor_id = :instructor_id
                         ORDER BY ar.scan_time DESC
                         LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':instructor_id', $instructor_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get recent attendance error: " . $e->getMessage());
            return [];
        }
    }

    public function getTotalStudentsForInstructor($instructor_id) {
        try {
            $query = "SELECT COUNT(DISTINCT s.student_id) as total
                     FROM students s
                     INNER JOIN instructor_courses ic ON s.course_id = ic.course_id
                     WHERE ic.instructor_id = :instructor_id AND s.is_active = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':instructor_id', $instructor_id);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result ? $result['total'] : 0;
        } catch (Exception $e) {
            error_log("Get total students error: " . $e->getMessage());
            return 0;
        }
    }
    
    public function getAllDepartments() {
        try {
            $query = "SELECT * FROM departments WHERE is_active = 1";
            return $this->executeQuery($query, [], true);
        } catch (PDOException $e) {
            error_log("Error in getAllDepartments: " . $e->getMessage());
            return [];
        }
    }

    public function userExists($username, $email) {
        try {
            $query = "SELECT COUNT(*) FROM users WHERE username = :username OR email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Check user exists error: " . $e->getMessage());
            return false;
        }
    }

    public function createInstructor($username, $email, $password, $full_name, $department_id, $employee_id, $phone, $office_location) {
        try {
            $this->db->beginTransaction();
            
            // Create the user record with all details
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (username, email, password_hash, full_name, role, department_id, employee_id, phone, office_location) 
                     VALUES (:username, :email, :password_hash, :full_name, 'teacher', :department_id, :employee_id, :phone, :office_location)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':department_id', $department_id);
            $stmt->bindParam(':employee_id', $employee_id);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':office_location', $office_location);
            $stmt->execute();
            
            $user_id = $this->db->lastInsertId();
            
            // Also create the instructor details record for backward compatibility
            $query = "INSERT INTO instructor_details (user_id, employee_id, department_id, phone, office_location) 
                     VALUES (:user_id, :employee_id, :department_id, :phone, :office_location)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':employee_id', $employee_id);
            $stmt->bindParam(':department_id', $department_id);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':office_location', $office_location);
            $stmt->execute();
            
            $this->db->commit();
            return $user_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Create instructor error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAttendanceReportData($instructor_id, $course_id, $date_from, $date_to) {
        try {
            $query = "SELECT 
                        ar.attendance_id,
                        ar.scan_time,
                        ar.scan_type,
                        ar.status,
                        s.student_id,
                        s.full_name as student_name,
                        s.student_number,
                        c.course_name,
                        c.course_code,
                        DATE(ar.scan_time) as attendance_date,
                        TIME(ar.scan_time) as scan_time_only
                     FROM attendance_records ar
                     JOIN students s ON ar.student_id = s.student_id
                     JOIN courses c ON ar.course_id = c.course_id
                     WHERE c.instructor_id = :instructor_id
                     AND ar.course_id = :course_id
                     AND DATE(ar.scan_time) BETWEEN :date_from AND :date_to
                     ORDER BY ar.scan_time DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':instructor_id', $instructor_id);
            $stmt->bindParam(':course_id', $course_id);
            $stmt->bindParam(':date_from', $date_from);
            $stmt->bindParam(':date_to', $date_to);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get attendance report data error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getAttendanceSummaryStats($instructor_id, $course_id, $date_from, $date_to) {
        try {
            // Get total students in the course
            $query1 = "SELECT COUNT(DISTINCT s.student_id) as total_students
                      FROM students s 
                      JOIN courses c ON s.course_id = c.course_id
                      WHERE c.instructor_id = :instructor_id AND c.course_id = :course_id";
            
            $stmt1 = $this->db->prepare($query1);
            $stmt1->bindParam(':instructor_id', $instructor_id);
            $stmt1->bindParam(':course_id', $course_id);
            $stmt1->execute();
            $total_students = $stmt1->fetchColumn();
            
            // Get total sessions (unique dates with attendance records)
            $query2 = "SELECT COUNT(DISTINCT DATE(ar.scan_time)) as total_sessions
                      FROM attendance_records ar
                      JOIN courses c ON ar.course_id = c.course_id
                      WHERE c.instructor_id = :instructor_id 
                      AND ar.course_id = :course_id
                      AND DATE(ar.scan_time) BETWEEN :date_from AND :date_to";
            
            $stmt2 = $this->db->prepare($query2);
            $stmt2->bindParam(':instructor_id', $instructor_id);
            $stmt2->bindParam(':course_id', $course_id);
            $stmt2->bindParam(':date_from', $date_from);
            $stmt2->bindParam(':date_to', $date_to);
            $stmt2->execute();
            $total_sessions = $stmt2->fetchColumn();
            
            // Get average attendance percentage
            $query3 = "SELECT 
                        COUNT(DISTINCT CONCAT(ar.student_id, '-', DATE(ar.scan_time))) as present_count,
                        COUNT(DISTINCT DATE(ar.scan_time)) * :total_students as total_possible
                      FROM attendance_records ar
                      JOIN courses c ON ar.course_id = c.course_id
                      WHERE c.instructor_id = :instructor_id 
                      AND ar.course_id = :course_id
                      AND DATE(ar.scan_time) BETWEEN :date_from AND :date_to
                      AND ar.status = 'present'";
            
            $stmt3 = $this->db->prepare($query3);
            $stmt3->bindParam(':instructor_id', $instructor_id);
            $stmt3->bindParam(':course_id', $course_id);
            $stmt3->bindParam(':total_students', $total_students);
            $stmt3->bindParam(':date_from', $date_from);
            $stmt3->bindParam(':date_to', $date_to);
            $stmt3->execute();
            $attendance_data = $stmt3->fetch();
            
            $average_attendance = 0;
            if ($attendance_data['total_possible'] > 0) {
                $average_attendance = round(($attendance_data['present_count'] / $attendance_data['total_possible']) * 100, 2);
            }
            
            // Calculate total hours (assuming each session is typically 1-2 hours)
            $total_hours = $total_sessions * 2; // Assuming 2 hours per session
            
            return [
                'total_students' => $total_students,
                'total_sessions' => $total_sessions,
                'average_attendance' => $average_attendance,
                'total_hours' => $total_hours
            ];
            
        } catch (Exception $e) {
            error_log("Get attendance summary stats error: " . $e->getMessage());
            return [
                'total_students' => 0,
                'total_sessions' => 0,
                'average_attendance' => 0,
                'total_hours' => 0
            ];
        }
    }
    
    // Add this method before the closing brace of the AttendanceSystem class (around line 750)
    
    public function getLastInsertId() {
        return $this->db->lastInsertId();
    }
    
    // Method to get the PDO connection
    public function getPdo() {
        return $this->db;
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    public function logActivity($user_id, $action, $table_name, $record_id, $old_values = null, $new_values = null) {
        try {
            // Check if activity_logs table exists, if not, create it
            $check_table = "SHOW TABLES LIKE 'activity_logs'";
            $result = $this->db->query($check_table);
            
            if ($result->rowCount() == 0) {
                // Create the activity_logs table if it doesn't exist
                $create_table = "CREATE TABLE activity_logs (
                    log_id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NULL,
                    action VARCHAR(50) NOT NULL,
                    table_name VARCHAR(50) NOT NULL,
                    record_id INT NULL,
                    old_values JSON NULL,
                    new_values JSON NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_action (action),
                    INDEX idx_table_name (table_name),
                    INDEX idx_created_at (created_at)
                )";
                $this->db->exec($create_table);
            }
            
            $query = "INSERT INTO activity_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at) 
                     VALUES (:user_id, :action, :table_name, :record_id, :old_values, :new_values, :ip_address, :user_agent, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':user_id', $user_id);
            $stmt->bindValue(':action', $action);
            $stmt->bindValue(':table_name', $table_name);
            $stmt->bindValue(':record_id', $record_id);
            $stmt->bindValue(':old_values', $old_values ? json_encode($old_values) : null);
            $stmt->bindValue(':new_values', $new_values ? json_encode($new_values) : null);
            $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
            $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Log activity error: " . $e->getMessage());
            return false;
        }
    }
    public function updateInstructor($instructor_id, $full_name, $email, $department_id, $employee_id, $phone, $office_location) {
    try {
        $this->db->beginTransaction();

        // Update users table
        $sql = "UPDATE users 
                SET full_name = ?, 
                    email = ? 
                WHERE user_id = ? AND role = 'teacher'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$full_name, $email, $instructor_id]);

        // Check if instructor details exist
        $sql = "SELECT COUNT(*) FROM instructor_details WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$instructor_id]);
        $exists = $stmt->fetchColumn() > 0;

        if ($exists) {
            // Update existing record
            $sql = "UPDATE instructor_details 
                    SET employee_id = ?,
                        department_id = ?,
                        phone = ?,
                        office_location = ?
                    WHERE user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$employee_id, $department_id, $phone, $office_location, $instructor_id]);
        } else {
            // Insert new record
            $sql = "INSERT INTO instructor_details 
                    (user_id, employee_id, department_id, phone, office_location)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$instructor_id, $employee_id, $department_id, $phone, $office_location]);
        }

        $this->db->commit();
        return true;
    } catch (PDOException $e) {
        $this->db->rollBack();
        error_log("Error updating instructor: " . $e->getMessage());
        return false;
    }
}

public function deleteInstructor($instructor_id) {
    try {
        // First delete course assignments
        $query = "DELETE FROM instructor_courses WHERE instructor_id = :instructor_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':instructor_id', $instructor_id);
        $stmt->execute();
        
        // Then delete the instructor
        $query = "DELETE FROM users WHERE user_id = :instructor_id AND role = 'teacher'";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':instructor_id', $instructor_id);
        
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Delete instructor error: " . $e->getMessage());
        return false;
    }
}
    
    public function getUsersByRole($role) {
        try {
            $query = "SELECT * FROM users WHERE role = ? AND is_active = 1";
            return $this->executeQuery($query, [$role], true);
        } catch (PDOException $e) {
            error_log("Error in getUsersByRole: " . $e->getMessage());
            return [];
        }
    }

    public function getAvailableStudents($instructor_id) {
        try {
            $query = "SELECT s.* 
                     FROM students s 
                     WHERE s.student_id NOT IN (
                         SELECT ce.student_id 
                         FROM course_enrollments ce 
                         WHERE ce.instructor_id = :instructor_id
                         AND ce.enrollment_status = 'Active'
                     )
                     AND s.is_active = 1
                     ORDER BY s.last_name, s.first_name";
                     
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':instructor_id', $instructor_id);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get available students error: " . $e->getMessage());
            return [];
        }
    }

    public function processInstructorScan($rfid_uid, $instructor_id, $attendance_type = 'Auto') {
        try {
            // Check if the RFID card is registered
            $sql = "SELECT s.*, c.course_name 
                FROM students s 
                LEFT JOIN courses c ON s.course_id = c.course_id 
                WHERE s.rfid_uid = ?";
            $student = $this->executeQuery($sql, [$rfid_uid], true);

            if (!$student) {
                return [
                    'success' => false,
                    'message' => 'Unregistered RFID card'
                ];
            }

            // Record the attendance
            $sql = "INSERT INTO attendance (
                    student_id, 
                    rfid_uid, 
                    scan_time, 
                    attendance_type, 
                    verification_status,
                    recorded_by
                ) VALUES (?, ?, NOW(), ?, 'Verified', ?)";
        
            $this->executeQuery($sql, [
                $student['student_id'],
                $rfid_uid,
                $attendance_type,
                $instructor_id
            ]);

            return [
                'success' => true,
                'message' => 'Attendance recorded successfully',
                'data' => [
                    'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                    'student_number' => $student['student_number'],
                    'course_name' => $student['course_name']
                ]
            ];
        } catch (Exception $e) {
            error_log("Error processing instructor scan: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error processing attendance'
            ];
        }
    }

    public function getRecentScans($instructor_id, $limit = 10) {
        $query = "SELECT a.*, s.first_name, s.last_name, c.course_name 
              FROM attendance_logs a 
              JOIN students s ON a.student_id = s.student_id
              JOIN courses c ON a.course_id = c.course_id
              WHERE c.instructor_id = ?
              ORDER BY a.scan_time DESC 
              LIMIT ?";
              
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $instructor_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function processAttendance($student_id, $course_id = null) {
        try {
            // Get current timestamp
            $now = date('Y-m-d H:i:s');
            $today = date('Y-m-d');
            
            // Check if student exists
            if (is_string($student_id) && strlen($student_id) >= 8) {
                // If RFID is provided instead of student_id
                $student = $this->getStudentByRFID($student_id);
                if (!$student) {
                    return [
                        'success' => false,
                        'message' => 'Invalid RFID card'
                    ];
                }
                $student_id = $student['student_id'];
            } else {
                $student = $this->getStudentByID($student_id);
                if (!$student) {
                    return [
                        'success' => false,
                        'message' => 'Invalid student ID'
                    ];
                }
            }

            // Check course enrollment if course_id is provided
            if ($course_id && !$this->isStudentEnrolled($student_id, $course_id)) {
                return [
                    'success' => false,
                    'message' => 'Student not enrolled in this course'
                ];
            }
            
            // Check if student already has an attendance record for today
            $query = "SELECT * FROM attendance_logs 
                     WHERE student_id = ? 
                     AND course_id = ?
                     AND DATE(scan_time) = ?
                     ORDER BY scan_time DESC 
                     LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iis", $student_id, $course_id, $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $last_record = $result->fetch_assoc();
            
            // Determine scan type (IN/OUT)
            $scan_type = (!$last_record || $last_record['scan_type'] === 'OUT') ? 'IN' : 'OUT';
            
            // Insert new attendance record
            $query = "INSERT INTO attendance_logs 
                     (student_id, course_id, scan_time, scan_type, status) 
                     VALUES (?, ?, ?, ?, 'present')";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iiss", $student_id, $course_id, $now, $scan_type);
            
            if ($stmt->execute()) {
                // Update daily summary
                $this->updateDailySummary($student_id);
                
                return [
                    'success' => true,
                    'message' => "Attendance " . $scan_type . " recorded successfully",
                    'data' => [
                        'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                        'student_number' => $student['student_number'],
                        'scan_type' => $scan_type,
                        'scan_time' => $now
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Failed to record attendance"
                ];
            }
        } catch (Exception $e) {
            error_log("Process attendance error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ];
        }
    }

    // Add this helper method if it doesn't exist
    private function getStudentByID($student_id) {
        try {
            $query = "SELECT * FROM students WHERE student_id = ? AND is_active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Get student by ID error: " . $e->getMessage());
            return false;
        }
    }

    // Add this helper method if it doesn't exist
    private function isStudentEnrolled($student_id, $course_id) {
        try {
            $query = "SELECT COUNT(*) as enrolled 
                 FROM course_enrollments 
                 WHERE student_id = ? 
                 AND course_id = ? 
                 AND enrollment_status = 'Active'";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $student_id, $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            return $row['enrolled'] > 0;
        } catch (Exception $e) {
            error_log("Check student enrollment error: " . $e->getMessage());
            return false;
        }
    }

    public function getLastAttendanceRecord($rfid_uid) {
    try {
        $stmt = $this->db->prepare("
            SELECT attendance_type 
            FROM attendance_records 
            WHERE rfid_uid = ? 
            ORDER BY scan_time DESC 
            LIMIT 1
        ");
        $stmt->execute([$rfid_uid]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}
}
?>
