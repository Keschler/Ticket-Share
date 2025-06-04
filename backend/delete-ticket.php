<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// For CORS - allow cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Include database connection
require_once 'db_connection.php';

// Initialize response array
$response = array('success' => false, 'message' => '');

try {
    // Get the raw POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception("Invalid JSON data received");
    }
    
    // Extract required fields
    $ticketId = isset($data['ticketId']) ? intval($data['ticketId']) : 0;
    $userId = isset($data['userId']) ? intval($data['userId']) : 0;
    
    if ($ticketId <= 0 || $userId <= 0) {
        throw new Exception("Invalid ticket ID or user ID");
    }
    
    // Check if the tickets table exists
    $table_check_query = "SHOW TABLES LIKE 'tickets'";
    $result = mysqli_query($conn, $table_check_query);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        throw new Exception("Tickets table doesn't exist");
    }
    
    // First, verify that the ticket belongs to the user
    $verify_query = "SELECT TicketID, SellerID FROM tickets WHERE TicketID = ? AND SellerID = ?";
    $verify_stmt = mysqli_prepare($conn, $verify_query);
    
    if (!$verify_stmt) {
        throw new Exception("Failed to prepare verification query: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($verify_stmt, "ii", $ticketId, $userId);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    
    if (mysqli_num_rows($verify_result) == 0) {
        throw new Exception("Ticket not found or you don't have permission to delete it");
    }
    
    // Delete the ticket
    $delete_query = "DELETE FROM tickets WHERE TicketID = ? AND SellerID = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_query);
    
    if (!$delete_stmt) {
        throw new Exception("Failed to prepare delete query: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($delete_stmt, "ii", $ticketId, $userId);
    
    if (!mysqli_stmt_execute($delete_stmt)) {
        throw new Exception("Failed to delete ticket: " . mysqli_stmt_error($delete_stmt));
    }
    
    if (mysqli_stmt_affected_rows($delete_stmt) > 0) {
        $response['success'] = true;
        $response['message'] = "Ticket deleted successfully";
    } else {
        throw new Exception("No ticket was deleted. Please check the ticket ID and try again.");
    }
    
    mysqli_stmt_close($delete_stmt);
    mysqli_stmt_close($verify_stmt);
    
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
