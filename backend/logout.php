<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// For CORS - allow cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Start session
session_start();

// Initialize response array
$response = array('success' => false, 'message' => '');

try {
    // Check if user is logged in
    if (isset($_SESSION['loggedin'])) {
        // Unset all session variables
        $_SESSION = array();

        // If it's desired to kill the session, also delete the session cookie.
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Finally, destroy the session
        session_destroy();
        
        $response['success'] = true;
        $response['message'] = "You have been successfully logged out.";
    } else {
        $response['message'] = "No active session to log out from.";
    }
} catch (Exception $e) {
    $response['message'] = "Logout error: " . $e->getMessage();
    $response['error_details'] = $e->getTraceAsString();
}

// Send JSON response
echo json_encode($response);
?>
