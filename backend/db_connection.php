<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Database configuration
$db_host = "pzquwmxgtrbfoiyvclkhsnda.duckdns.org";
$db_port = 1200;    
$db_user = "remoteuser";
$db_pass = "your_strong_password";
$db_name = "users";


// Create database connection with proper error handling
try {
    // Set connection timeout to prevent hanging
    $conn = mysqli_init();
    mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    
    // First connect without specifying a database
    if (!mysqli_real_connect($conn, $db_host, $db_user, $db_pass, "", $db_port)) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }

    // Check if the database exists
    $db_check_query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db_name'";
    $result = mysqli_query($conn, $db_check_query);
    
    if (!$result) {
        throw new Exception("Database check failed: " . mysqli_error($conn));
    }
    
    if (mysqli_num_rows($result) == 0) {
        // Database doesn't exist, create it
        $create_db_query = "CREATE DATABASE `$db_name`";
        if (!mysqli_query($conn, $create_db_query)) {
            throw new Exception("Failed to create database: " . mysqli_error($conn));
        }
    }
    
    // Select the database
    if (!mysqli_select_db($conn, $db_name)) {
        throw new Exception("Failed to select database: " . mysqli_error($conn));
    }
    
    // Check if the users table exists
    $table_check_query = "SHOW TABLES LIKE 'users'";
    $result = mysqli_query($conn, $table_check_query);
    
    if (!$result) {
        throw new Exception("Table check failed: " . mysqli_error($conn));
    }
    
    if (mysqli_num_rows($result) == 0) {
        // Create users table
        $create_users_table = "CREATE TABLE `users` (
            `ID` INT(11) NOT NULL AUTO_INCREMENT,
            `Username` VARCHAR(50) NOT NULL,
            `Password` VARCHAR(255) NOT NULL,
            `Email` VARCHAR(100) NOT NULL,
            `PhoneNumber` VARCHAR(20) UNIQUE,
            `Adress` VARCHAR(255),
            `TicketID` INT(11),
            `Favorites` TEXT,
            PRIMARY KEY (`ID`),
            UNIQUE KEY `Username` (`Username`),
            UNIQUE KEY `Email` (`Email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!mysqli_query($conn, $create_users_table)) {
            throw new Exception("Failed to create users table: " . mysqli_error($conn));
        }
    }
    
    // Check if the balance table exists
    $balance_check_query = "SHOW TABLES LIKE 'balance'";
    $result = mysqli_query($conn, $balance_check_query);
    
    if (!$result) {
        throw new Exception("Balance table check failed: " . mysqli_error($conn));
    }
    
    if (mysqli_num_rows($result) == 0) {
        // Create balance table
        $create_balance_table = "CREATE TABLE `balance` (
            `BalanceID` INT(11) NOT NULL AUTO_INCREMENT,
            `UserID` INT(11) NOT NULL,
            `Balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (`BalanceID`),
            UNIQUE KEY `UserID` (`UserID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!mysqli_query($conn, $create_balance_table)) {
            throw new Exception("Failed to create balance table: " . mysqli_error($conn));
        }
    }

    // Set charset to ensure proper character encoding
    if (!mysqli_set_charset($conn, "utf8mb4")) {
        throw new Exception("Error setting charset: " . mysqli_error($conn));
    }
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Database connection error: " . $e->getMessage());
    
    // Set a global variable to indicate connection failure
    $db_connection_error = $e->getMessage();
    $conn = false;
    
    // Only output JSON and exit if this file is being accessed directly
    if (basename($_SERVER['PHP_SELF']) === 'db_connection.php') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Database connection error: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Function to sanitize user inputs
function sanitize_input($conn, $data) {
    if (!$conn) {
        throw new Exception("Invalid database connection");
    }
    if (is_array($data)) {
        return array_map(function($item) use ($conn) {
            return mysqli_real_escape_string($conn, trim(htmlspecialchars($item)));
        }, $data);
    }
    return mysqli_real_escape_string($conn, trim(htmlspecialchars($data)));
}
?>