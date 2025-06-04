<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'backend/db_connection.php';

// Function to get all available tickets
function getAvailableTickets($conn) {
    $tickets = array();
    
    try {
        // Check if the tickets table exists
        $table_check_query = "SHOW TABLES LIKE 'tickets'";
        $result = mysqli_query($conn, $table_check_query);
        
        if (!$result) {
            throw new Exception("Table check failed: " . mysqli_error($conn));
        }
        
        // If tickets table doesn't exist, return empty array
        if (mysqli_num_rows($result) == 0) {
            return array();
        }
        
        // Define the query to fetch tickets - using Username instead of Name
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
        while ($row = mysqli_fetch_assoc($result)) {
            $tickets[] = $row;
        }
    } catch (Exception $e) {
        // Log error instead of displaying it
        error_log("Error fetching tickets: " . $e->getMessage());
    }
    
    return $tickets;
}

// Get all available tickets
$tickets = getAvailableTickets($conn);

// Debug information - remove in production
$ticketCount = count($tickets);
$ticketsJson = json_encode($tickets);

// Close the database connection
if (isset($conn) && $conn) {
    mysqli_close($conn);
}

// Include the HTML content
include_once('index.html');

// Add JavaScript to inject tickets data from PHP
echo "<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ticketsFromServer = $ticketsJson;
        
        // Handle case where get-tickets.php is failing
        if (ticketsFromServer && ticketsFromServer.length > 0) {
            // Override the loadTickets function to use our PHP data
            const ticketsContainer = document.querySelector('.container');
            ticketsContainer.innerHTML = '';
            
            // Use the existing createTicketElement function from index.html
            if (typeof createTicketElement === 'function') {
                ticketsFromServer.forEach(ticket => {
                    const ticketElement = createTicketElement(ticket);
                    ticketsContainer.appendChild(ticketElement);
                });
                
                // Call the existing addEventListenersToTickets function if it exists
                if (typeof addEventListenersToTickets === 'function') {
                    addEventListenersToTickets();
                }
            } else {
                // Fallback if createTicketElement isn't defined yet
                setTimeout(() => {
                    window.ticketsFromPHP = ticketsFromServer;
                }, 500);
            }
        }
    });
</script>";
?>
