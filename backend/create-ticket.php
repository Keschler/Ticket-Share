<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// For CORS - allow cross-origin requests
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Set secure cookie parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

// If this is a preflight request, return early
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Content-Length: 0');
    header('Content-Type: text/plain');
    exit(0);
}

// Include database connection
require_once 'db_connection.php';
require_once 'session.php';

// Initialize response array
$response = array('success' => false, 'message' => '');

// Helper function to sanitize inputs

try {
    // First, check if the tickets table exists
    $table_check_query = "SHOW TABLES LIKE 'tickets'";
    $result = mysqli_query($conn, $table_check_query);
    
    if (!$result) {
        throw new Exception("Table check failed: " . mysqli_error($conn));
    }
    
    if (mysqli_num_rows($result) == 0) {
        // Create tickets table if it doesn't exist
        $create_tickets_table = "CREATE TABLE `tickets` (
            `TicketID` INT(11) NOT NULL AUTO_INCREMENT,
            `TicketName` VARCHAR(100) NOT NULL,
            `Date` DATE NOT NULL,
            `Time` TIME NOT NULL,
            `Location` VARCHAR(100) NOT NULL,
            `Price` FLOAT,
            `Exp_Date_Time` DATETIME NOT NULL,
            `ImageURL` VARCHAR(255),
            `SellerID` INT(11),
            `BuyerID` INT(11),
            `SaleType` VARCHAR(50),
            `Currency` VARCHAR(10),
            `Confirmed` BOOLEAN DEFAULT FALSE,
            PRIMARY KEY (`TicketID`),
            FOREIGN KEY (`SellerID`) REFERENCES `users`(`ID`) ON DELETE SET NULL,
            FOREIGN KEY (`BuyerID`) REFERENCES `users`(`ID`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!mysqli_query($conn, $create_tickets_table)) {
            throw new Exception("Failed to create tickets table: " . mysqli_error($conn));
        }
        
        $response['message'] = "Tickets table created successfully.";
    }
    
    // Process ticket creation form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Check if user is logged in
        if (!isLoggedIn()) {
            $response['message'] = "You must be logged in to create a ticket.";
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        // Verify CSRF token
        if (!checkCsrfToken()) {
            $response['message'] = "Invalid CSRF token.";
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        // Get the seller ID from the session
        $sellerID = $_SESSION['id'];
        
        if (!$sellerID) {
            $response['message'] = "User ID not found in session.";
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        // Check if the request is JSON
        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
        if (strpos($contentType, 'application/json') !== false) {
            // Get and parse JSON input
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            // Extract values from JSON
            $ticketName = isset($data['eventName']) ? sanitize_input($conn, $data['eventName']) : '';
            $date = isset($data['date']) ? sanitize_input($conn, $data['date']) : '';
            $time = isset($data['time']) ? sanitize_input($conn, $data['time']) : '';
            $location = isset($data['location']) ? sanitize_input($conn, $data['location']) : '';
            $price = isset($data['price']) ? floatval($data['price']) : null;
            $expiration = isset($data['expiration']) ? sanitize_input($conn, $data['expiration']) : '';
            $imageURL = isset($data['imageURL']) ? sanitize_input($conn, $data['imageURL']) : '';
            $currency = isset($data['currency']) ? sanitize_input($conn, $data['currency']) : '$';
            $saleType = 'Buy It Now'; // Fixed sale type
        } else {
            // Handle form data (multipart/form-data or application/x-www-form-urlencoded)
            $ticketName = isset($_POST['eventName']) ? sanitize_input($conn, $_POST['eventName']) : '';
            $date = isset($_POST['date']) ? sanitize_input($conn, $_POST['date']) : '';
            $time = isset($_POST['time']) ? sanitize_input($conn, $_POST['time']) : '';
            $location = isset($_POST['location']) ? sanitize_input($conn, $_POST['location']) : '';
            $price = isset($_POST['price']) ? floatval($_POST['price']) : null;
            $expiration = isset($_POST['expiration']) ? sanitize_input($conn, $_POST['expiration']) : '';
            $imageURL = isset($_POST['imageURL']) ? sanitize_input($conn, $_POST['imageURL']) : '';
            $currency = isset($_POST['currency']) ? sanitize_input($conn, $_POST['currency']) : '$';
            $saleType = 'Buy It Now'; // Fixed sale type
        }
        
        // Validate required fields
        if (empty($ticketName) || empty($date) || empty($time) || empty($location) || empty($expiration) || $price === null) {
            $response['message'] = "All required fields must be filled.";
        } else {
            // Prepare SQL statement
            $insert_ticket = "INSERT INTO tickets (TicketName, Date, Time, Location, Price, Exp_Date_Time, ImageURL, SellerID, SaleType, Currency) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_ticket);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, "ssssdsssss", $ticketName, $date, $time, $location, $price, $expiration, $imageURL, $sellerID, $saleType, $currency);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
            }
            
            $ticketID = mysqli_insert_id($conn);
            
            $response['success'] = true;
            $response['message'] = "Ticket created successfully!";
            $response['ticketID'] = $ticketID;
            
            mysqli_stmt_close($stmt);
        }
    } else {
        $response['message'] = "Invalid request method. Only POST is allowed.";
    }
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
    $response['error_details'] = $e->getTraceAsString();
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);

// Close database connection
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>
