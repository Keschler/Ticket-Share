<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// For CORS - allow cross-origin requests
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Include database connection and session
require_once 'db_connection.php';
require_once 'session.php';

// Initialize response array
$response = array('success' => false, 'message' => '');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is logged in
    if (!isLoggedIn()) {
        $response['message'] = "User not logged in.";
        echo json_encode($response);
        exit;
    }
    
    // Verify CSRF token
    if (!checkCsrfToken()) {
        $response['message'] = "Invalid CSRF token.";
        echo json_encode($response);
        exit;
    }

    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Check if required data is provided
    if (isset($data['ticketId'])) {
        $ticket_id = $data['ticketId'];
        $buyer_id = $_SESSION['id'];
        
        try {
            // Begin transaction
            mysqli_begin_transaction($conn);
            
            // Check if ticket exists and is available
            $check_query = "SELECT TicketID, Price, SellerID FROM tickets WHERE TicketID = ? AND BuyerID IS NULL";
            $stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($stmt, "i", $ticket_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) === 0) {
                throw new Exception("Ticket not available or already sold.");
            }
            
            $ticket = mysqli_fetch_assoc($result);
            $ticket_price = $ticket['Price'];
            $seller_id = $ticket['SellerID'];
            
            // Check if buyer has sufficient balance
            $balance_query = "SELECT Balance FROM balance WHERE UserID = ?";
            $balance_stmt = mysqli_prepare($conn, $balance_query);
            mysqli_stmt_bind_param($balance_stmt, "i", $buyer_id);
            mysqli_stmt_execute($balance_stmt);
            $balance_result = mysqli_stmt_get_result($balance_stmt);
            
            if (mysqli_num_rows($balance_result) === 0) {
                throw new Exception("No wallet found. Please add money to your wallet first.");
            }
            
            $balance_row = mysqli_fetch_assoc($balance_result);
            $buyer_balance = $balance_row['Balance'];
            
            if ($buyer_balance < $ticket_price) {
                throw new Exception("Insufficient funds. You need $" . number_format($ticket_price - $buyer_balance, 2) . " more in your wallet.");
            }
            
            // Deduct money from buyer's balance
            $deduct_query = "UPDATE balance SET Balance = Balance - ? WHERE UserID = ?";
            $deduct_stmt = mysqli_prepare($conn, $deduct_query);
            mysqli_stmt_bind_param($deduct_stmt, "di", $ticket_price, $buyer_id);
            
            if (!mysqli_stmt_execute($deduct_stmt)) {
                throw new Exception("Error processing payment: " . mysqli_error($conn));
            }
            
            // Add money to seller's balance (create balance record if doesn't exist)
            $seller_balance_query = "SELECT Balance FROM balance WHERE UserID = ?";
            $seller_balance_stmt = mysqli_prepare($conn, $seller_balance_query);
            mysqli_stmt_bind_param($seller_balance_stmt, "i", $seller_id);
            mysqli_stmt_execute($seller_balance_stmt);
            $seller_balance_result = mysqli_stmt_get_result($seller_balance_stmt);
            
            if (mysqli_num_rows($seller_balance_result) === 0) {
                // Create balance record for seller
                $create_seller_balance = "INSERT INTO balance (UserID, Balance) VALUES (?, ?)";
                $create_stmt = mysqli_prepare($conn, $create_seller_balance);
                mysqli_stmt_bind_param($create_stmt, "id", $seller_id, $ticket_price);
                if (!mysqli_stmt_execute($create_stmt)) {
                    throw new Exception("Error crediting seller: " . mysqli_error($conn));
                }
            } else {
                // Update existing seller balance
                $credit_query = "UPDATE balance SET Balance = Balance + ? WHERE UserID = ?";
                $credit_stmt = mysqli_prepare($conn, $credit_query);
                mysqli_stmt_bind_param($credit_stmt, "di", $ticket_price, $seller_id);
                if (!mysqli_stmt_execute($credit_stmt)) {
                    throw new Exception("Error crediting seller: " . mysqli_error($conn));
                }
            }
            
            // Update ticket with buyer information
            $update_query = "UPDATE tickets SET BuyerID = ? WHERE TicketID = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "ii", $buyer_id, $ticket_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                // Commit transaction
                mysqli_commit($conn);
                
                $response['success'] = true;
                $response['message'] = "Ticket purchased successfully! $" . number_format($ticket_price, 2) . " has been deducted from your wallet.";
                $response['amountPaid'] = number_format($ticket_price, 2);
            } else {
                throw new Exception("Error updating ticket: " . mysqli_error($conn));
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $response['message'] = "Error: " . $e->getMessage();
        }
    } else {
        $response['message'] = "Missing required data.";
    }
} else {
    $response['message'] = "Invalid request method.";
}

// Close database connection
mysqli_close($conn);

// Return response as JSON
echo json_encode($response);
?>
