<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// CORS headers
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include required files
require_once 'db_connection.php';
define('INCLUDED_FROM_OTHER_SCRIPT', true);
require_once 'session.php';

// Initialize response array
$response = array('success' => false, 'message' => '');

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        throw new Exception('User is not logged in');
    }
    
    $userId = $_SESSION['id'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle confirmation or dispute actions
        $input = json_decode(file_get_contents('php://input'), true);
        $action = isset($input['action']) ? $input['action'] : '';
        $ticketId = isset($input['ticketId']) ? (int)$input['ticketId'] : 0;
        
        if (!$ticketId || !$action) {
            throw new Exception('Missing required parameters');
        }
        
        // Verify CSRF token
        if (!checkCsrfToken()) {
            throw new Exception('Invalid CSRF token');
        }
        
        if ($action === 'confirm') {
            // Confirm ticket validity
            mysqli_begin_transaction($conn);
            
            try {
                // Check if this user is the buyer and confirmation is pending
                $check_query = "SELECT tc.*, t.Price, t.SellerID 
                               FROM ticket_confirmations tc 
                               JOIN tickets t ON tc.TicketID = t.TicketID 
                               WHERE tc.TicketID = ? AND tc.BuyerID = ? AND tc.Status = 'pending'";
                $stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($stmt, "ii", $ticketId, $userId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) === 0) {
                    throw new Exception('No pending confirmation found for this ticket');
                }
                
                $confirmation = mysqli_fetch_assoc($result);
                $sellerId = $confirmation['SellerID'];
                $price = $confirmation['Price'];
                
                // Update confirmation status
                $update_query = "UPDATE ticket_confirmations 
                                SET Status = 'confirmed', ConfirmationDate = NOW() 
                                WHERE TicketID = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "i", $ticketId);
                mysqli_stmt_execute($stmt);
                
                // Credit the seller
                $credit_query = "INSERT INTO balance (UserID, Balance) VALUES (?, ?) 
                                ON DUPLICATE KEY UPDATE Balance = Balance + ?";
                $stmt = mysqli_prepare($conn, $credit_query);
                mysqli_stmt_bind_param($stmt, "idd", $sellerId, $price, $price);
                mysqli_stmt_execute($stmt);
                
                // Log seller transaction
                $seller_log = "INSERT INTO transactions (UserID, Type, Amount, Details) 
                              VALUES (?, 'sale_confirmed', ?, ?)";
                $stmt = mysqli_prepare($conn, $seller_log);
                $details = "TicketID {$ticketId} - Confirmed by buyer";
                mysqli_stmt_bind_param($stmt, 'ids', $sellerId, $price, $details);
                mysqli_stmt_execute($stmt);
                
                mysqli_commit($conn);
                
                $response['success'] = true;
                $response['message'] = 'Ticket confirmed successfully. Seller has been credited.';
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                throw $e;
            }
            
        } elseif ($action === 'dispute') {
            // Create a dispute
            $reason = isset($input['reason']) ? trim($input['reason']) : '';
            
            if (empty($reason)) {
                throw new Exception('Dispute reason is required');
            }
            
            mysqli_begin_transaction($conn);
            
            try {
                // Check if this user is the buyer and confirmation is pending
                $check_query = "SELECT tc.*, t.SellerID 
                               FROM ticket_confirmations tc 
                               JOIN tickets t ON tc.TicketID = t.TicketID 
                               WHERE tc.TicketID = ? AND tc.BuyerID = ? AND tc.Status = 'pending'";
                $stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($stmt, "ii", $ticketId, $userId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) === 0) {
                    throw new Exception('No pending confirmation found for this ticket');
                }
                
                $confirmation = mysqli_fetch_assoc($result);
                $sellerId = $confirmation['SellerID'];
                
                // Update confirmation status to disputed
                $update_query = "UPDATE ticket_confirmations 
                                SET Status = 'disputed' 
                                WHERE TicketID = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "i", $ticketId);
                mysqli_stmt_execute($stmt);
                
                // Create dispute record
                $dispute_query = "INSERT INTO disputes (TicketID, BuyerID, SellerID, Reason) 
                                 VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $dispute_query);
                mysqli_stmt_bind_param($stmt, "iiis", $ticketId, $userId, $sellerId, $reason);
                mysqli_stmt_execute($stmt);
                
                mysqli_commit($conn);
                
                $response['success'] = true;
                $response['message'] = 'Dispute created successfully. Payment is on hold pending resolution.';
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                throw $e;
            }
        } else {
            throw new Exception('Invalid action');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get user's pending confirmations
        $query = "SELECT tc.*, t.TicketName, t.Location, t.Date, t.Time, t.Price, t.Currency,
                         u.Username as SellerName,
                         CASE 
                             WHEN tc.ExpiresAt < NOW() THEN 'expired'
                             ELSE tc.Status 
                         END as CurrentStatus
                  FROM ticket_confirmations tc 
                  JOIN tickets t ON tc.TicketID = t.TicketID 
                  JOIN users u ON tc.SellerID = u.ID
                  WHERE tc.BuyerID = ? 
                  ORDER BY tc.CreatedAt DESC";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $confirmations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Calculate days remaining for confirmation
            $eventDate = new DateTime($row['EventDate']);
            $expiresAt = new DateTime($row['ExpiresAt']);
            $now = new DateTime();
            
            $row['daysRemaining'] = max(0, $expiresAt->diff($now)->days);
            $row['canConfirm'] = ($row['CurrentStatus'] === 'pending' && $now < $expiresAt && $now > $eventDate);
            $row['canDispute'] = ($row['CurrentStatus'] === 'pending' && $now < $expiresAt && $now > $eventDate);
            
            $confirmations[] = $row;
        }
        
        $response['success'] = true;
        $response['confirmations'] = $confirmations;
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Ticket confirmation error: " . $e->getMessage());
}

// Send JSON response
echo json_encode($response);

// Close database connection
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>
