<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set proper CORS headers for handling credentials only if this is the main script
if (!defined('INCLUDED_FROM_OTHER_SCRIPT')) {
    header('Access-Control-Allow-Origin: http://localhost');  // Use your actual domain in production
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');
}

// Set secure cookie parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

// Set session to use cookies only
ini_set('session.use_only_cookies', 1);

// Start or resume session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if one doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize response array
$response = array('success' => false, 'message' => '');

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

// Function to get current user ID
function getCurrentUserId() {
    return isset($_SESSION['id']) ? $_SESSION['id'] : null;
}

// Function to get current username
function getCurrentUsername() {
    return isset($_SESSION['username']) ? $_SESSION['username'] : null;
}

// Function to get user session data
function getUserSessionData() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id'       => getCurrentUserId(),
        'username' => getCurrentUsername(),
        'email'    => isset($_SESSION['email']) ? $_SESSION['email'] : null,
    ];
}

// Function to verify CSRF token
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Function to check CSRF token for POST/PUT/DELETE requests
function checkCsrfToken() {
    // Skip CSRF check for GET and OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        return true;
    }
    
    // Get JSON input
    $jsonInput = file_get_contents("php://input");
    $data = json_decode($jsonInput, true);
    
    // Check if CSRF token is present and valid
    if (!isset($data['csrf_token']) || !verifyCsrfToken($data['csrf_token'])) {
        return false;
    }
    
    return true;
}

// Handle session status check only if this file is accessed directly
// (i.e., when the request targets session.php itself)
$scriptName = basename($_SERVER['SCRIPT_NAME']);
$selfName   = basename(__FILE__);
if ($scriptName === $selfName && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $response = array('success' => false, 'message' => '');
    
    if (isLoggedIn()) {
        $response['success']    = true;
        $response['message']    = 'User is logged in';
        $response['user']       = getUserSessionData();
        $response['csrf_token'] = $_SESSION['csrf_token']; // Include CSRF token in response
    } else {
        $response['message'] = 'User is not logged in';
    }
    
    echo json_encode($response);
    exit;
}
?>

