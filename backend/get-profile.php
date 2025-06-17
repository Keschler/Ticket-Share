<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set proper CORS headers - more permissive for local development
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
    $userId = null;
    $username = null;
    
    // Handle different request methods and data sources
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get POST data
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['username'])) {
            $username = trim($input['username']);
        }
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get URL parameter
        if (isset($_GET['username'])) {
            $username = trim($_GET['username']);
        }
    }
    
    // If no username provided, check if user is logged in and use their data
    if (empty($username)) {
        if (!isLoggedIn()) {
            throw new Exception('User is not logged in and no username provided');
        }
        $userId = $_SESSION['id'];
    } else {
        // Validate username format (basic validation)
        if (!preg_match('/^[a-zA-Z0-9_\-\.]{3,50}$/', $username)) {
            throw new Exception('Invalid username format');
        }
        
        // Get user ID from username using prepared statement to prevent SQL injection
        $userLookupQuery = "SELECT ID FROM users WHERE Username = ?";
        $stmt = mysqli_prepare($conn, $userLookupQuery);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare user lookup query: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "s", $username);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to execute user lookup query: " . mysqli_stmt_error($stmt));
        }
        
        $lookupResult = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($lookupResult) === 0) {
            mysqli_stmt_close($stmt);
            throw new Exception('User not found with username: ' . $username);
        }
        
        $lookupData = mysqli_fetch_assoc($lookupResult);
        $userId = $lookupData['ID'];
        mysqli_stmt_close($stmt);
    }
    
    if (!$userId) {
        throw new Exception('Unable to determine user ID');
    }
    
    // Get user profile information
    $userQuery = "SELECT Username, Email, Adress FROM users WHERE ID = ?";
    $stmt = mysqli_prepare($conn, $userQuery);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare user query: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $userId);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to execute user query: " . mysqli_stmt_error($stmt));
    }
    
    $userResult = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($userResult) === 0) {
        throw new Exception('User not found');
    }
    
    $userData = mysqli_fetch_assoc($userResult);
    mysqli_stmt_close($stmt);
    
    // Get user's sold tickets (where user is seller)
    $ticketsQuery = "SELECT t.TicketID, t.TicketName, t.Price, t.Location, t.Date, t.Time, t.Exp_Date_Time, t.Currency, t.SaleType, t.BuyerID,
                            tc.Status as ConfirmationStatus, tc.ExpiresAt as ConfirmationExpiry,
                            d.Status as DisputeStatus, 'seller' as UserRole
                     FROM tickets t 
                     LEFT JOIN ticket_confirmations tc ON t.TicketID = tc.TicketID 
                     LEFT JOIN disputes d ON t.TicketID = d.TicketID
                     WHERE t.SellerID = ? 
                     ORDER BY t.Exp_Date_Time DESC";
    $stmt = mysqli_prepare($conn, $ticketsQuery);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare tickets query: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $userId);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to execute tickets query: " . mysqli_stmt_error($stmt));
    }
     $ticketsResult = mysqli_stmt_get_result($stmt);
    $tickets = [];
    
    while ($ticket = mysqli_fetch_assoc($ticketsResult)) {
        $tickets[] = array(
            'id' => $ticket['TicketID'],
            'title' => $ticket['TicketName'],
            'description' => '', // Not available in current schema
            'price' => $ticket['Price'],
            'location' => $ticket['Location'],
            'eventDate' => $ticket['Date'],
            'eventTime' => $ticket['Time'],
            'expirationTime' => $ticket['Exp_Date_Time'],
            'currency' => $ticket['Currency'],
            'category' => $ticket['SaleType'], // Using SaleType as category
            'isSold' => !empty($ticket['BuyerID']),
            'confirmationStatus' => $ticket['ConfirmationStatus'],
            'confirmationExpiry' => $ticket['ConfirmationExpiry'],
            'disputeStatus' => $ticket['DisputeStatus'],
            'userRole' => $ticket['UserRole']
        );
    }
    
    mysqli_stmt_close($stmt);
    
    // Get user's purchased tickets (where user is buyer)
    $purchasedQuery = "SELECT t.TicketID, t.TicketName, t.Price, t.Location, t.Date, t.Time, t.Exp_Date_Time, t.Currency, t.SaleType,
                              tc.Status as ConfirmationStatus, tc.ExpiresAt as ConfirmationExpiry,
                              d.Status as DisputeStatus, 'buyer' as UserRole
                       FROM tickets t 
                       LEFT JOIN ticket_confirmations tc ON t.TicketID = tc.TicketID 
                       LEFT JOIN disputes d ON t.TicketID = d.TicketID
                       WHERE t.BuyerID = ? 
                       ORDER BY t.Exp_Date_Time DESC";
    $stmt = mysqli_prepare($conn, $purchasedQuery);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare purchased tickets query: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $userId);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to execute purchased tickets query: " . mysqli_stmt_error($stmt));
    }
    
    $purchasedResult = mysqli_stmt_get_result($stmt);
    
    while ($ticket = mysqli_fetch_assoc($purchasedResult)) {
        $tickets[] = array(
            'id' => $ticket['TicketID'],
            'title' => $ticket['TicketName'],
            'description' => '', // Not available in current schema
            'price' => $ticket['Price'],
            'location' => $ticket['Location'],
            'eventDate' => $ticket['Date'],
            'eventTime' => $ticket['Time'],
            'expirationTime' => $ticket['Exp_Date_Time'],
            'currency' => $ticket['Currency'],
            'category' => $ticket['SaleType'], // Using SaleType as category
            'isSold' => true, // Always true for purchased tickets
            'confirmationStatus' => $ticket['ConfirmationStatus'],
            'confirmationExpiry' => $ticket['ConfirmationExpiry'],
            'disputeStatus' => $ticket['DisputeStatus'],
            'userRole' => $ticket['UserRole']
        );
    }

    mysqli_stmt_close($stmt);
    
    // Prepare successful response
    $response['success'] = true;
    $response['user'] = array(
        'username' => $userData['Username'],
        'email' => $userData['Email'],
        'address' => $userData['Adress'] // Note: keeping the typo from the database
    );
    $response['tickets'] = $tickets;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    $response['debug'] = array(
        'user_logged_in' => isset($_SESSION['id']),
        'session_id' => session_id(),
        'session_data' => $_SESSION ?? null
    );
    error_log("Profile error: " . $e->getMessage());
}

// Send JSON response
echo json_encode($response);

// Close database connection
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>
