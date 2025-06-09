<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'rfid_attendance_system';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            // Check if MySQL server is running first
            $temp_conn = @new PDO(
                "mysql:host=" . $this->host,
                $this->username,
                $this->password,
                array(PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
            
            // Check if database exists
            $stmt = $temp_conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$this->db_name}'");
            if (!$stmt->fetch()) {
                error_log("Database '{$this->db_name}' does not exist");
                throw new PDOException("Database '{$this->db_name}' does not exist. Please create it first.");
            }
            
            // Connect to the specific database
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                array(PDO::ATTR_TIMEOUT => 5) // Add connection timeout
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Test the connection with a simple query
            $this->conn->query("SELECT 1");
            
            return $this->conn;
        } catch(PDOException $exception) {
            // Log the error with more details
            $error_message = "Database connection error: " . $exception->getMessage();
            error_log($error_message . " in " . $exception->getFile() . " on line " . $exception->getLine());
            
            // Check for specific error conditions
            if (strpos($exception->getMessage(), "Access denied") !== false) {
                error_log("Database access denied - check username and password");
            } else if (strpos($exception->getMessage(), "Connection refused") !== false) {
                error_log("Database connection refused - check if MySQL server is running");
            }
            
            // Return null instead of throwing to allow graceful handling
            return null;
        }
    }

    public function closeConnection() {
        $this->conn = null;
    }
}
?>