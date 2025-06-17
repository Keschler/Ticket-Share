<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
$response = ['success' => false, 'message' => ''];

// Log entry into script
error_log("==== buy-ticket.php called ====");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Request method is POST");

    // Check if user is logged in
    if (!isLoggedIn()) {
        $response['message'] = "User not logged in.";
        error_log("User not logged in.");
        echo json_encode($response);
        exit;
    }
    $buyer_id = $_SESSION['id'];
    error_log("Logged in as user ID: {$buyer_id}");

    // Verify CSRF token
    if (!checkCsrfToken()) {
        $response['message'] = "Invalid CSRF token.";
        error_log("CSRF check failed.");
        echo json_encode($response);
        exit;
    }
    error_log("CSRF token verified.");

    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    error_log("Raw JSON input: " . $json);

    // Check if required data is provided
    if (isset($data['ticketId'])) {
        $ticket_id = intval($data['ticketId']);
        error_log("ticketId received: {$ticket_id}");
        
        try {
            // Begin transaction
            mysqli_begin_transaction($conn);
            error_log("Transaction started.");

            // Check if ticket exists and is available
            $check_query = "SELECT TicketID, Price, SellerID, Date FROM tickets WHERE TicketID = ? AND BuyerID IS NULL";
            $stmt = mysqli_prepare($conn, $check_query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, "i", $ticket_id);
            $exec_check = mysqli_stmt_execute($stmt);
            if (!$exec_check) {
                throw new Exception("Execute failed (check_query): " . mysqli_stmt_error($stmt));
            }
            $result = mysqli_stmt_get_result($stmt);
            if ($result === false) {
                throw new Exception("Get result failed (check_query): " . mysqli_error($conn));
            }
            $row_count = mysqli_num_rows($result);
            error_log("check_query returned rows: {$row_count}");

            if ($row_count === 0) {
                throw new Exception("Ticket not available or already sold.");
            }
            
            $ticket = mysqli_fetch_assoc($result);
            $ticket_price = floatval($ticket['Price']);
            $seller_id = intval($ticket['SellerID']);
            $event_date = $ticket['Date']; // Add this line to get event date early
            error_log("Ticket found. Price: {$ticket_price}, SellerID: {$seller_id}, EventDate: {$event_date}");

            // Check buyer balance
            $balance_query = "SELECT Balance FROM balance WHERE UserID = ?";
            $balance_stmt = mysqli_prepare($conn, $balance_query);
            if (!$balance_stmt) {
                throw new Exception("Prepare failed (balance_query): " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($balance_stmt, "i", $buyer_id);
            $exec_balance = mysqli_stmt_execute($balance_stmt);
            if (!$exec_balance) {
                throw new Exception("Execute failed (balance_query): " . mysqli_stmt_error($balance_stmt));
            }
            $balance_result = mysqli_stmt_get_result($balance_stmt);
            if ($balance_result === false) {
                throw new Exception("Get result failed (balance_query): " . mysqli_error($conn));
            }
            $balance_row_count = mysqli_num_rows($balance_result);
            error_log("balance_query returned rows: {$balance_row_count}");

            if ($balance_row_count === 0) {
                throw new Exception("No wallet found. Please add money to your wallet first.");
            }

            $balance_row = mysqli_fetch_assoc($balance_result);
            $buyer_balance = floatval($balance_row['Balance']);
            error_log("Buyer balance: {$buyer_balance}");

            if ($buyer_balance < $ticket_price) {
                $shortfall = $ticket_price - $buyer_balance;
                throw new Exception("Insufficient funds. You need $" . number_format($shortfall, 2) . " more in your wallet.");
            }

            // Deduct money from buyer's balance
            $deduct_query = "UPDATE balance SET Balance = Balance - ? WHERE UserID = ?";
            $deduct_stmt = mysqli_prepare($conn, $deduct_query);
            if (!$deduct_stmt) {
                throw new Exception("Prepare failed (deduct_query): " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($deduct_stmt, "di", $ticket_price, $buyer_id);
            $exec_deduct = mysqli_stmt_execute($deduct_stmt);
            if (!$exec_deduct) {
                throw new Exception("Execute failed (deduct_query): " . mysqli_stmt_error($deduct_stmt));
            }
            error_log("Buyer balance deducted by {$ticket_price}.");

            // Update ticket with buyer information
            $update_query = "UPDATE tickets SET BuyerID = ? WHERE TicketID = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            if (!$update_stmt) {
                throw new Exception("Prepare failed (update_query): " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($update_stmt, "ii", $buyer_id, $ticket_id);
            $exec_update = mysqli_stmt_execute($update_stmt);
            if (!$exec_update) {
                throw new Exception("Execute failed (update_query): " . mysqli_stmt_error($update_stmt));
            }
            error_log("Ticket {$ticket_id} updated with BuyerID = {$buyer_id}.");

            // Create ticket confirmation record with 3-day expiry after event
            error_log("Raw event_date from database: '{$event_date}' (type: " . gettype($event_date) . ")");
            
            // Parse and validate the event date with robust fallback
            if (empty($event_date)) {
                error_log("Event date is empty, using current date as fallback");
                $event_datetime = new DateTime();
            } else {
                // Convert to string if it's not already
                $event_date_str = (string)$event_date;
                error_log("Event date as string: '{$event_date_str}'");
                
                $event_datetime = null;
                
                // Try different parsing methods
                try {
                    // Method 1: Direct DateTime construction
                    $event_datetime = new DateTime($event_date_str);
                    $year = (int)$event_datetime->format('Y');
                    
                    // Check if year is reasonable
                    if ($year < 1900 || $year > 2100) {
                        throw new Exception("Invalid year: {$year}");
                    }
                    
                    error_log("Successfully parsed with DateTime constructor, year: {$year}");
                    
                } catch (Exception $e) {
                    error_log("DateTime constructor failed: " . $e->getMessage());
                    $event_datetime = null;
                }
                
                // Method 2: Try specific formats if first method failed
                if ($event_datetime === null) {
                    $formats = [
                        'Y-m-d', 
                        'Y-m-d H:i:s', 
                        'd-m-Y', 
                        'm-d-Y',
                        'Y/m/d',
                        'd/m/Y',
                        'm/d/Y'
                    ];
                    
                    foreach ($formats as $format) {
                        $test_date = DateTime::createFromFormat($format, $event_date_str);
                        if ($test_date !== false) {
                            $year = (int)$test_date->format('Y');
                            if ($year >= 1900 && $year <= 2100) {
                                $event_datetime = $test_date;
                                error_log("Successfully parsed with format '{$format}', year: {$year}");
                                break;
                            }
                        }
                    }
                }
                
                // Method 3: Fallback to current date if all parsing failed
                if ($event_datetime === null) {
                    error_log("All date parsing failed, using current date as fallback");
                    $event_datetime = new DateTime();
                }
            }
            
            // Add 3 days to the event date
            $expiry_datetime = clone $event_datetime;
            $expiry_datetime->add(new DateInterval('P3D')); // Add 3 days
            
            $expires_at = $expiry_datetime->format('Y-m-d H:i:s');
            $event_date_formatted = $event_datetime->format('Y-m-d');
            
            // Final validation
            $expiry_year = (int)$expiry_datetime->format('Y');
            if ($expiry_year < 2020 || $expiry_year > 2030) {
                error_log("Expiry year still invalid: {$expiry_year}, using current date + 3 days");
                $expiry_datetime = new DateTime();
                $expiry_datetime->add(new DateInterval('P3D'));
                $expires_at = $expiry_datetime->format('Y-m-d H:i:s');
                $event_date_formatted = date('Y-m-d');
            }
            
            error_log("Final dates - Event: '{$event_date_formatted}', Expiry: '{$expires_at}'");
            
            $confirmation_query = "INSERT INTO ticket_confirmations (TicketID, BuyerID, SellerID, EventDate, ExpiresAt) VALUES (?, ?, ?, ?, ?)";
            $confirmation_stmt = mysqli_prepare($conn, $confirmation_query);
            if (!$confirmation_stmt) {
                throw new Exception("Prepare failed (confirmation_query): " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($confirmation_stmt, "iiiss", $ticket_id, $buyer_id, $seller_id, $event_date_formatted, $expires_at);
            $exec_confirmation = mysqli_stmt_execute($confirmation_stmt);
            if (!$exec_confirmation) {
                throw new Exception("Execute failed (confirmation_query): " . mysqli_stmt_error($confirmation_stmt));
            }
            error_log("Ticket confirmation record created. Payment will be held until buyer confirms or 3 days after event ({$expires_at}).");

            // Log transactions for buyer (seller will be credited later upon confirmation)
            $ticketDetail = 'TicketID ' . $ticket_id . ' - Payment held pending confirmation';

            $buyer_log  = "INSERT INTO transactions (UserID, Type, Amount, Details) VALUES (?, 'purchase', ?, ?)";
            $buyer_stmt = mysqli_prepare($conn, $buyer_log);
            if ($buyer_stmt) {
                mysqli_stmt_bind_param($buyer_stmt, 'ids', $buyer_id, $ticket_price, $ticketDetail);
                mysqli_stmt_execute($buyer_stmt);
                error_log("Buyer transaction logged: user {$buyer_id}, amount {$ticket_price}.");
            } else {
                error_log("Prepare failed (buyer_log): " . mysqli_error($conn));
            }

            // Commit transaction
            mysqli_commit($conn);
            error_log("Transaction committed successfully.");

            $response['success'] = true;
            $response['message'] = "Ticket purchased successfully! \${$ticket_price} has been deducted from your wallet. Payment will be held until you confirm the ticket validity or 3 days after the event.";
            $response['amountPaid'] = number_format($ticket_price, 2);

        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            error_log("Transaction rolled back due to error: " . $e->getMessage());
            $response['message'] = "Error: " . $e->getMessage();
        }
    } else {
        $response['message'] = "Missing required data.";
        error_log("Missing ticketId in POST data.");
    }
} else {
    $response['message'] = "Invalid request method.";
    error_log("Request method is not POST: " . $_SERVER['REQUEST_METHOD']);
}

// Close database connection
if (isset($conn) && $conn) {
    mysqli_close($conn);
    error_log("Database connection closed.");
}

// Return response as JSON
echo json_encode($response);
?>

