<?php
// Auto-confirmation cron job
// This script should be run daily via cron job to auto-confirm expired tickets

require_once __DIR__ . '/db_connection.php';

try {
    // Find expired pending confirmations
    $query = "SELECT tc.*, t.Price, t.SellerID 
              FROM ticket_confirmations tc 
              JOIN tickets t ON tc.TicketID = t.TicketID 
              WHERE tc.Status = 'pending' AND tc.ExpiresAt < NOW()";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        mysqli_begin_transaction($conn);
        
        try {
            while ($confirmation = mysqli_fetch_assoc($result)) {
                $ticketId = $confirmation['TicketID'];
                $sellerId = $confirmation['SellerID'];
                $price = $confirmation['Price'];
                
                // Update confirmation status to auto-confirmed
                $update_query = "UPDATE ticket_confirmations 
                                SET Status = 'auto_confirmed', ConfirmationDate = NOW() 
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
                              VALUES (?, 'sale_auto_confirmed', ?, ?)";
                $stmt = mysqli_prepare($conn, $seller_log);
                $details = "TicketID {$ticketId} - Auto-confirmed after 3-day confirmation period expired";
                mysqli_stmt_bind_param($stmt, 'ids', $sellerId, $price, $details);
                mysqli_stmt_execute($stmt);
                
                error_log("Auto-confirmed ticket {$ticketId} for seller {$sellerId} - 3-day confirmation period expired");
            }
            
            mysqli_commit($conn);
            echo "Auto-confirmation completed successfully\n";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Auto-confirmation failed: " . $e->getMessage());
            echo "Auto-confirmation failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "No tickets to auto-confirm\n";
    }
    
} catch (Exception $e) {
    error_log("Auto-confirmation script error: " . $e->getMessage());
    echo "Script error: " . $e->getMessage() . "\n";
}

if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>
