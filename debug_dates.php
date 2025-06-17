<?php
// Debug date format in tickets table
require_once 'backend/db_connection.php';

echo "Checking date formats in tickets table:\n";
echo "=====================================\n\n";

try {
    // Get a sample of tickets to see date formats
    $query = "SELECT TicketID, TicketName, Date, Time, Exp_Date_Time FROM tickets LIMIT 5";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        echo "Sample tickets found:\n";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "Ticket ID: {$row['TicketID']}\n";
            echo "Name: {$row['TicketName']}\n";
            echo "Date: '{$row['Date']}' (type: " . gettype($row['Date']) . ")\n";
            echo "Time: '{$row['Time']}'\n";
            echo "Exp_Date_Time: '{$row['Exp_Date_Time']}'\n";
            
            // Try to parse the date
            if (!empty($row['Date'])) {
                $test_date = new DateTime($row['Date']);
                echo "Parsed Date: " . $test_date->format('Y-m-d H:i:s') . "\n";
                echo "Year: " . $test_date->format('Y') . "\n";
            }
            echo "---\n";
        }
    } else {
        echo "No tickets found in database.\n";
    }
    
    // Check table structure
    echo "\nTable structure:\n";
    $structure = mysqli_query($conn, "DESCRIBE tickets");
    if ($structure) {
        while ($col = mysqli_fetch_assoc($structure)) {
            if (strpos(strtolower($col['Field']), 'date') !== false || strpos(strtolower($col['Field']), 'time') !== false) {
                echo "{$col['Field']}: {$col['Type']} (Null: {$col['Null']}, Default: {$col['Default']})\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

if (isset($conn)) {
    mysqli_close($conn);
}
?>
