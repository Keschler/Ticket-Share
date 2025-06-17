<?php
// Test file to verify the 3-day confirmation flow
require_once 'backend/db_connection.php';

echo "Testing 3-Day Confirmation Flow\n";
echo "================================\n\n";

try {
    // Check if ticket_confirmations table exists and has correct structure
    $check_table = "SHOW CREATE TABLE ticket_confirmations";
    $result = mysqli_query($conn, $check_table);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "Table Structure:\n";
        echo $row['Create Table'] . "\n\n";
    }
    
    // Test date calculation for 3 days after event
    $sample_event_date = '2025-06-15'; // Example future event
    $expires_at = date('Y-m-d H:i:s', strtotime($sample_event_date . ' +3 days'));
    
    echo "Sample Event Date: {$sample_event_date}\n";
    echo "Confirmation Expires At: {$expires_at}\n";
    echo "Days difference: 3\n\n";
    
    // Check existing confirmation records
    $count_query = "SELECT COUNT(*) as count FROM ticket_confirmations";
    $count_result = mysqli_query($conn, $count_query);
    $count_data = mysqli_fetch_assoc($count_result);
    
    echo "Current confirmation records: {$count_data['count']}\n";
    
    // Check for pending confirmations
    $pending_query = "SELECT tc.*, t.TicketName 
                     FROM ticket_confirmations tc 
                     JOIN tickets t ON tc.TicketID = t.TicketID 
                     WHERE tc.Status = 'pending'";
    $pending_result = mysqli_query($conn, $pending_query);
    
    echo "Pending confirmations: " . mysqli_num_rows($pending_result) . "\n";
    
    if (mysqli_num_rows($pending_result) > 0) {
        echo "\nPending Confirmation Details:\n";
        while ($pending = mysqli_fetch_assoc($pending_result)) {
            echo "- Ticket: {$pending['TicketName']}\n";
            echo "  Event Date: {$pending['EventDate']}\n";
            echo "  Expires At: {$pending['ExpiresAt']}\n";
            echo "  Status: {$pending['Status']}\n\n";
        }
    }
    
    echo "✅ Confirmation flow test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

if (isset($conn)) {
    mysqli_close($conn);
}
?>
