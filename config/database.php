<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'matrimony');

// Create database connection
function connectDB() {
    try {
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if (!$conn) {
            throw new Exception("Connection failed: " . mysqli_connect_error());
        }
        
        // Set charset to ensure proper handling of special characters
        mysqli_set_charset($conn, "utf8mb4");
        
        return $conn;
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
}

// Close database connection
function closeDB($conn) {
    if ($conn) {
        mysqli_close($conn);
    }
}
?>
