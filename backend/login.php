<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set proper CORS headers for handling credentials
header('Access-Control-Allow-Origin: http://localhost');  // Use your actual domain in production
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Set secure cookie parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);

// Start session with regenerated ID for security
session_start();
session_regenerate_id(true);

// Include database connection
require_once 'db_connection.php';
require_once 'rate_limiter.php';

// Initialize response array
$response = array('success' => false, 'message' => '');

try {
    // Process login requesth
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        
        // Get and sanitize user inputs
        $login = isset($_POST['login']) ? sanitize_input($conn, $_POST['login']) : ''; // Username or email
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        // Validate input fields
        if (empty($login) || empty($password)) {
            $response['message'] = "Please enter both username/email and password";
        } else {
            // Check rate limiting based on IP address and username
            $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $rateLimitCheck = checkRateLimit($clientIP . '_' . $login);
            
            if (!$rateLimitCheck['allowed']) {
                $response['message'] = $rateLimitCheck['message'];
            } else {
                // Always perform a dummy password hash to prevent timing attacks
                $dummy_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
                $login_successful = false;
            
            // Check if user exists (using either username or email)
            // Updated column names to match actual DB structure
            $check_user = "SELECT ID, Username, Email, Password FROM users WHERE Username = ? OR Email = ?";
            $stmt = mysqli_prepare($conn, $check_user);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, "ss", $login, $login);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
            }
            
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
                
                // Verify password
                if (password_verify($password, $user['Password'])) {
                    $login_successful = true;
                    // Reset rate limit on successful login
                    resetRateLimit($clientIP . '_' . $login);
                    
                    // Password is correct, set up session
                    $_SESSION['loggedin'] = true;
                    $_SESSION['id'] = $user['ID'];
                    $_SESSION['username'] = $user['Username'];
                    $_SESSION['email'] = $user['Email'];
                    $_SESSION['last_active'] = time();
                    
                    // Set response
                    $response['success'] = true;
                    $response['message'] = "Login successful!";
                    $response['user'] = array(
                        'id' => $user['ID'],
                        'username' => $user['Username'],
                        'email' => $user['Email']
                    );
                }
            } else {
                // Perform dummy password verification to maintain consistent timing
                password_verify($password, $dummy_hash);
            }
            
            // Use generic error message for all failed login attempts
            if (!$login_successful) {
                // Record failed attempt for rate limiting
                recordFailedAttempt($clientIP . '_' . $login);
                
                // Add small delay to prevent timing attacks
                usleep(rand(100000, 300000)); // 0.1-0.3 seconds
                $response['message'] = "Invalid username/email or password";
            }
            }
            
            if (isset($stmt) && $stmt) {
                mysqli_stmt_close($stmt);
            }
        }
    } else {
        $response['message'] = "Invalid request method. Only POST is allowed.";
    }
} catch (Exception $e) {
    $response['message'] = "Server error: " . $e->getMessage();
    $response['error_details'] = $e->getTraceAsString();
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);

// Close database connection
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>
