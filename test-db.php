<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "Database connection successful!";
    } else {
        echo "Failed to connect to database.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>