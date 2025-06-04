<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set proper CORS headers - more permissive for local development
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    
    // Get user's tickets
    $ticketsQuery = "SELECT ID, Title, Description, Price, Location, EventDate, EventTime, ExpirationTime, Currency, Category FROM tickets WHERE SellerID = ? ORDER BY CreationTime DESC";
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
            'id' => $ticket['ID'],
            'title' => $ticket['Title'],
            'description' => $ticket['Description'],
            'price' => $ticket['Price'],
            'location' => $ticket['Location'],
            'eventDate' => $ticket['EventDate'],
            'eventTime' => $ticket['EventTime'],
            'expirationTime' => $ticket['ExpirationTime'],
            'currency' => $ticket['Currency'],
            'category' => $ticket['Category']
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
