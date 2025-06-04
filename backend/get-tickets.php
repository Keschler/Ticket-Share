<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// For CORS - allow cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Include database connection
require_once 'db_connection.php';

// Initialize response array
$response = array('success' => false, 'tickets' => array(), 'message' => '');

try {
    // Check if the tickets table exists
    $table_check_query = "SHOW TABLES LIKE 'tickets'";
    $result = mysqli_query($conn, $table_check_query);
    
    if (!$result) {
        throw new Exception("Table check failed: " . mysqli_error($conn));
    }
    
    // If tickets table doesn't exist, return empty array
    if (mysqli_num_rows($result) == 0) {
        $response['message'] = "Tickets table doesn't exist.";
        echo json_encode($response);
        exit;
    }
    
    // Define the query to fetch tickets
    $query = "SELECT 
                t.TicketID,
                t.TicketName AS eventName,
                DATE_FORMAT(t.Date, '%Y-%m-%d') AS date,
                TIME_FORMAT(t.Time, '%H:%i') AS time,
                t.Location AS location,
                t.Price AS price,
                DATE_FORMAT(t.Exp_Date_Time, '%Y-%m-%d %H:%i:%s') AS expiration,
                t.ImageURL AS image,
                t.SellerID,
                t.BuyerID,
                t.SaleType,
                t.Currency AS currency,
                u.Username AS sellerName
              FROM tickets t
              LEFT JOIN users u ON t.SellerID = u.ID
              WHERE t.BuyerID IS NULL
              ORDER BY t.Exp_Date_Time DESC";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        throw new Exception("Query failed: " . mysqli_error($conn));
    }
    
    // Fetch all tickets from the result
    $tickets = array();
    while ($row = mysqli_fetch_assoc($result)) {
        // Add ticket data to tickets array
        $tickets[] = $row;
    }
    
    $response['success'] = true;
    $response['tickets'] = $tickets;
    $response['message'] = "Tickets fetched successfully.";
    
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
} finally {
    // Close database connection
    if (isset($conn) && $conn) {
        mysqli_close($conn);
    }
    
    // Return response as JSON
    echo json_encode($response);
}
?>
