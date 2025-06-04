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
            $check_query = "SELECT TicketID, Price, SellerID FROM tickets WHERE TicketID = ? AND BuyerID IS NULL";
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
            error_log("Ticket found. Price: {$ticket_price}, SellerID: {$seller_id}");

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

            // Add money to seller's balance (create record if none)
            $seller_balance_query = "SELECT Balance FROM balance WHERE UserID = ?";
            $seller_balance_stmt = mysqli_prepare($conn, $seller_balance_query);
            if (!$seller_balance_stmt) {
                throw new Exception("Prepare failed (seller_balance_query): " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($seller_balance_stmt, "i", $seller_id);
            $exec_seller_balance = mysqli_stmt_execute($seller_balance_stmt);
            if (!$exec_seller_balance) {
                throw new Exception("Execute failed (seller_balance_query): " . mysqli_stmt_error($seller_balance_stmt));
            }
            $seller_balance_result = mysqli_stmt_get_result($seller_balance_stmt);
            if ($seller_balance_result === false) {
                throw new Exception("Get result failed (seller_balance_query): " . mysqli_error($conn));
            }
            $seller_balance_row_count = mysqli_num_rows($seller_balance_result);
            error_log("seller_balance_query returned rows: {$seller_balance_row_count}");

            if ($seller_balance_row_count === 0) {
                // Create balance record for seller
                $create_seller_balance = "INSERT INTO balance (UserID, Balance) VALUES (?, ?)";
                $create_stmt = mysqli_prepare($conn, $create_seller_balance);
                if (!$create_stmt) {
                    throw new Exception("Prepare failed (create_seller_balance): " . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($create_stmt, "id", $seller_id, $ticket_price);
                $exec_create = mysqli_stmt_execute($create_stmt);
                if (!$exec_create) {
                    throw new Exception("Execute failed (create_seller_balance): " . mysqli_stmt_error($create_stmt));
                }
                error_log("Seller balance record created with {$ticket_price}.");
            } else {
                // Update existing seller balance
                $credit_query = "UPDATE balance SET Balance = Balance + ? WHERE UserID = ?";
                $credit_stmt = mysqli_prepare($conn, $credit_query);
                if (!$credit_stmt) {
                    throw new Exception("Prepare failed (credit_query): " . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($credit_stmt, "di", $ticket_price, $seller_id);
                $exec_credit = mysqli_stmt_execute($credit_stmt);
                if (!$exec_credit) {
                    throw new Exception("Execute failed (credit_query): " . mysqli_stmt_error($credit_stmt));
                }
                error_log("Seller balance credited by {$ticket_price}.");
            }

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

            // Log transactions for buyer and seller
            $ticketDetail = 'TicketID ' . $ticket_id;

            $buyer_log  = "INSERT INTO transactions (UserID, Type, Amount, Details) VALUES (?, 'purchase', ?, ?)";
            $buyer_stmt = mysqli_prepare($conn, $buyer_log);
            if ($buyer_stmt) {
                mysqli_stmt_bind_param($buyer_stmt, 'ids', $buyer_id, $ticket_price, $ticketDetail);
                mysqli_stmt_execute($buyer_stmt);
                error_log("Buyer transaction logged: user {$buyer_id}, amount {$ticket_price}.");
            } else {
                error_log("Prepare failed (buyer_log): " . mysqli_error($conn));
            }

            $seller_log = "INSERT INTO transactions (UserID, Type, Amount, Details) VALUES (?, 'sale', ?, ?)";
            $seller_stmt = mysqli_prepare($conn, $seller_log);
            if ($seller_stmt) {
                mysqli_stmt_bind_param($seller_stmt, 'ids', $seller_id, $ticket_price, $ticketDetail);
                mysqli_stmt_execute($seller_stmt);
                error_log("Seller transaction logged: user {$seller_id}, amount {$ticket_price}.");
            } else {
                error_log("Prepare failed (seller_log): " . mysqli_error($conn));
            }

            // Commit transaction
            mysqli_commit($conn);
            error_log("Transaction committed successfully.");

            $response['success'] = true;
            $response['message'] = "Ticket purchased successfully! \${$ticket_price} has been deducted from your wallet.";
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

