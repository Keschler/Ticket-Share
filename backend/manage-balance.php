<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_connection.php';
$INCLUDED_FROM_OTHER_SCRIPT = true;
require_once 'session.php';


try {
    // Verify database connection
    if (!$conn) {
        throw new Exception('Database connection failed: ' . mysqli_connect_error());
    }
    
    // Check if session is started
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new Exception('Session not active');
    }
    
    // Log session status for debugging
    error_log("Session Status - ID: " . (isset($_SESSION['id']) ? $_SESSION['id'] : 'Not set') . 
              ", Active: " . (session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No'));

    // Check if balance table exists, create if not
    $table_check_query = "SHOW TABLES LIKE 'balance'";
    $result = mysqli_query($conn, $table_check_query);
    
    if (!$result) {
        throw new Exception('Failed to check balance table: ' . mysqli_error($conn));
    }
    
    if (mysqli_num_rows($result) == 0) {
        // Create balance table - but it seems to already exist with different column names
        $create_balance_table = "CREATE TABLE `balance` (
            `BalanceID` INT(11) NOT NULL AUTO_INCREMENT,
            `UserID` INT(11) NOT NULL,
            `Balance` DECIMAL(10,2) DEFAULT 0.00,
            `LastUpdated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`BalanceID`),
            UNIQUE KEY `unique_user` (`UserID`),
            FOREIGN KEY (`UserID`) REFERENCES `users`(`ID`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!mysqli_query($conn, $create_balance_table)) {
            throw new Exception("Failed to create balance table: " . mysqli_error($conn));
        }
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $response = array('success' => false, 'message' => '');

    switch ($method) {
        case 'GET':
            // Get user balance
            if (!isLoggedIn()) {
                throw new Exception('User is not logged in');
            }
            
            $userId = $_SESSION['id'];
            
            // Validate user ID
            if (!is_numeric($userId) || $userId <= 0) {
                throw new Exception('Invalid user ID');
            }
            
            // Get or create balance record for user
            $query = "SELECT Balance FROM balance WHERE UserID = ?";
            $stmt = mysqli_prepare($conn, $query);
            
            if (!$stmt) {
                throw new Exception('Failed to prepare balance query: ' . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, "i", $userId);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to execute balance query: ' . mysqli_stmt_error($stmt));
            }
            
            $result = mysqli_stmt_get_result($stmt);
            
            if (!$result) {
                throw new Exception('Failed to get balance result: ' . mysqli_stmt_error($stmt));
            }
            
            if (mysqli_num_rows($result) == 0) {
                // Create initial balance record with 0.00
                $insert_query = "INSERT INTO balance (UserID, Balance) VALUES (?, 0.00)";
                $insert_stmt = mysqli_prepare($conn, $insert_query);
                
                if (!$insert_stmt) {
                    throw new Exception('Failed to prepare balance insert: ' . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($insert_stmt, "i", $userId);
                
                if (!mysqli_stmt_execute($insert_stmt)) {
                    throw new Exception('Failed to create balance record: ' . mysqli_stmt_error($insert_stmt));
                }
                
                $balance = 0.00;
                error_log("Created new balance record for user ID: $userId");
            } else {
                $row = mysqli_fetch_assoc($result);
                if (!$row) {
                    throw new Exception('Failed to fetch balance data');
                }
                $balance = $row['Balance'];
            }
            
            $response['success'] = true;
            $response['balance'] = number_format($balance, 2, '.', '');
            break;

        case 'POST':
            // Add money to balance
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isLoggedIn()) {
                throw new Exception('User is not logged in');
            }
            
            // Verify CSRF token
            if (!checkCsrfToken()) {
                throw new Exception('Invalid CSRF token');
            }
            
            if (!isset($input['amount'])) {
                throw new Exception('Amount is required');
            }
            
            $userId = $_SESSION['id'];
            $amount = floatval($input['amount']);
            
            // Validate amount
            if ($amount <= 0) {
                throw new Exception('Amount must be greater than 0');
            }
            
            if ($amount > 1000) {
                throw new Exception('Maximum deposit amount is $1000 per transaction');
            }
            
            // Check if balance record exists
            $check_query = "SELECT Balance FROM balance WHERE UserID = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, "i", $userId);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) == 0) {
                // Create new balance record
                $insert_query = "INSERT INTO balance (UserID, Balance) VALUES (?, ?)";
                $insert_stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($insert_stmt, "id", $userId, $amount);
                $success = mysqli_stmt_execute($insert_stmt);
            } else {
                // Update existing balance
                $update_query = "UPDATE balance SET Balance = Balance + ? WHERE UserID = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "di", $amount, $userId);
                $success = mysqli_stmt_execute($update_stmt);
            }
            
            if ($success) {
                // Get updated balance
                $get_balance_query = "SELECT Balance FROM balance WHERE UserID = ?";
                $get_stmt = mysqli_prepare($conn, $get_balance_query);
                mysqli_stmt_bind_param($get_stmt, "i", $userId);
                mysqli_stmt_execute($get_stmt);
                $balance_result = mysqli_stmt_get_result($get_stmt);
                $balance_row = mysqli_fetch_assoc($balance_result);
                
                $response['success'] = true;
                $response['message'] = "Successfully added $" . number_format($amount, 2) . " to your balance";
                $response['newBalance'] = number_format($balance_row['Balance'], 2, '.', '');
            } else {
                throw new Exception('Failed to update balance');
            }
            break;

        case 'PUT':
            // Withdraw money from balance
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isLoggedIn()) {
                throw new Exception('User is not logged in');
            }
            
            // Verify CSRF token
            if (!checkCsrfToken()) {
                throw new Exception('Invalid CSRF token');
            }
            
            if (!isset($input['amount'])) {
                throw new Exception('Amount is required');
            }
            
            $userId = $_SESSION['id'];
            $amount = floatval($input['amount']);
            
            // Validate amount
            if ($amount <= 0) {
                throw new Exception('Amount must be greater than 0');
            }
            
            if ($amount < 1) {
                throw new Exception('Minimum withdrawal amount is $1');
            }
            
            // Get current balance
            $balance_query = "SELECT Balance FROM balance WHERE UserID = ?";
            $balance_stmt = mysqli_prepare($conn, $balance_query);
            mysqli_stmt_bind_param($balance_stmt, "i", $userId);
            mysqli_stmt_execute($balance_stmt);
            $balance_result = mysqli_stmt_get_result($balance_stmt);
            
            if (mysqli_num_rows($balance_result) == 0) {
                throw new Exception('No balance record found');
            }
            
            $balance_row = mysqli_fetch_assoc($balance_result);
            $currentBalance = $balance_row['Balance'];
            
            if ($amount > $currentBalance) {
                throw new Exception('Insufficient funds. Current balance: $' . number_format($currentBalance, 2));
            }
            
            // Update balance
            $update_query = "UPDATE balance SET Balance = Balance - ? WHERE UserID = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "di", $amount, $userId);
            $success = mysqli_stmt_execute($update_stmt);
            
            if ($success) {
                $newBalance = $currentBalance - $amount;
                $response['success'] = true;
                $response['message'] = "Successfully withdrew $" . number_format($amount, 2) . " from your balance";
                $response['newBalance'] = number_format($newBalance, 2, '.', '');
            } else {
                throw new Exception('Failed to update balance');
            }
            break;

        default:
            throw new Exception('Unsupported method');
    }

} catch (Exception $e) {
    // Log detailed error information
    error_log("Balance Management Error: " . $e->getMessage());
    error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("User Session: " . (isset($_SESSION['id']) ? $_SESSION['id'] : 'No session'));
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['error_details'] = [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'method' => $_SERVER['REQUEST_METHOD'],
        'user_id' => isset($_SESSION['id']) ? $_SESSION['id'] : null,
        'timestamp' => date('Y-m-d H:i:s')
    ];
} catch (mysqli_sql_exception $e) {
    // Log database-specific errors
    error_log("Database Error in Balance Management: " . $e->getMessage());
    error_log("SQL Error Code: " . $e->getCode());
    
    $response['success'] = false;
    $response['message'] = 'Database error occurred. Please try again.';
    $response['error_details'] = [
        'type' => 'database_error',
        'code' => $e->getCode(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
} catch (Error $e) {
    // Log fatal errors
    error_log("Fatal Error in Balance Management: " . $e->getMessage());
    error_log("Error File: " . $e->getFile() . " Line: " . $e->getLine());
    
    $response['success'] = false;
    $response['message'] = 'A system error occurred. Please try again.';
    $response['error_details'] = [
        'type' => 'fatal_error',
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

echo json_encode($response);
?>