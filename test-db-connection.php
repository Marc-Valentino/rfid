<?php
require_once 'config/database.php';

echo "<h1>Testing Database Connection</h1>";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "<p style='color:green'>Database connection successful!</p>";
        
        // Test if tables exist
        $tables = ['attendance_records', 'students', 'departments', 'courses', 'rfid_cards'];
        echo "<h2>Checking Tables:</h2>";
        echo "<ul>";
        
        foreach ($tables as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<li style='color:green'>Table '$table' exists</li>";
            } else {
                echo "<li style='color:red'>Table '$table' does not exist</li>";
            }
        }
        
        echo "</ul>";
    } else {
        echo "<p style='color:red'>Database connection failed!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>