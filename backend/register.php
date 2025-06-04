<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// For CORS - allow cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Set secure cookie parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);

// Start session
session_start();

// Include database connection
require_once 'db_connection.php';
require_once 'rate_limiter.php';

// Initialize response array
$response = array('success' => false, 'message' => '');

try {
    // Process form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        
        // Get and sanitize form data
        $username = isset($_POST['username']) ? sanitize_input($conn, $_POST['username']) : '';
        $email = isset($_POST['email']) ? sanitize_input($conn, $_POST['email']) : '';
        $address = isset($_POST['address']) ? sanitize_input($conn, $_POST['address']) : '';
        $phone = isset($_POST['phone']) ? sanitize_input($conn, $_POST['phone']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Check rate limiting for registration attempts
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimitCheck = checkRateLimit('register_' . $clientIP, 3, 1800); // 3 attempts per 30 minutes
        
        if (!$rateLimitCheck['allowed']) {
            $response['message'] = $rateLimitCheck['message'];
        } elseif (empty($username) || empty($email) || empty($password) || empty($phone) || empty($address)) {
            $response['message'] = "All fields are required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = "Invalid email format";
        } elseif (!preg_match('/^\+?[0-9\s\-\.()]{5,25}$/', $phone)) {
            $response['message'] = "Invalid phone number format";
        } elseif (strlen($password) < 8) {
            $response['message'] = "Password must be at least 8 characters long";
        } elseif ($password !== $confirm_password) {
            $response['message'] = "Passwords do not match";
        } else {
            try {
                // Check if username already exists
                $check_username = "SELECT ID FROM users WHERE Username = ?";
                $stmt = mysqli_prepare($conn, $check_username);
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($stmt, "s", $username);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
                }
                
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) > 0) {
                    $response['message'] = "Registration failed. Please try with different credentials.";
                } else {
                    // Check if email already exists
                    mysqli_stmt_close($stmt);
                    $check_email = "SELECT ID FROM users WHERE Email = ?";
                    $stmt = mysqli_prepare($conn, $check_email);
                    
                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . mysqli_error($conn));
                    }
                    
                    mysqli_stmt_bind_param($stmt, "s", $email);
                    
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
                    }
                    
                    mysqli_stmt_store_result($stmt);
                    
                    if (mysqli_stmt_num_rows($stmt) > 0) {
                        $response['message'] = "Registration failed. Please try with different credentials.";
                    } else {
                        // Check if phone already exists
                        mysqli_stmt_close($stmt);
                        $check_phone = "SELECT ID FROM users WHERE PhoneNumber = ?";
                        $stmt = mysqli_prepare($conn, $check_phone);
                        if (!$stmt) {
                            throw new Exception("Prepare failed: " . mysqli_error($conn));
                        }
                        mysqli_stmt_bind_param($stmt, "s", $phone);
                        if (!mysqli_stmt_execute($stmt)) {
                            throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
                        }
                        mysqli_stmt_store_result($stmt);
                        if (mysqli_stmt_num_rows($stmt) > 0) {
                            $response['message'] = "Registration failed. Please try with different credentials.";
                        } else {
                            // Hash the password
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            // Set default value for TicketID (using 0 as default since it's likely a foreign key)
                            $ticketID = 0;
                            $favorites = null; // Default empty list
                            // Insert user into database - matching the actual DB structure with all required fields
                            mysqli_stmt_close($stmt);
                            $insert_user = "INSERT INTO users (Username, Password, Email, PhoneNumber, Adress, TicketID, Favorites) VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $stmt = mysqli_prepare($conn, $insert_user);
                            if (!$stmt) {
                                throw new Exception("Prepare failed: " . mysqli_error($conn));
                            }
                            mysqli_stmt_bind_param($stmt, "sssssis", $username, $hashed_password, $email, $phone, $address, $ticketID, $favorites);
                            if (!mysqli_stmt_execute($stmt)) {
                                throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
                            }
                            
                            // Get the new user ID
                            $userId = mysqli_insert_id($conn);
                            
                            // Create a balance record for the new user with 0 balance
                            mysqli_stmt_close($stmt);
                            $create_balance = "INSERT INTO balance (UserID, Balance) VALUES (?, 0)";
                            $stmt = mysqli_prepare($conn, $create_balance);
                            
                            if (!$stmt) {
                                throw new Exception("Balance record prepare failed: " . mysqli_error($conn));
                            }
                            
                            mysqli_stmt_bind_param($stmt, "i", $userId);
                            
                            if (!mysqli_stmt_execute($stmt)) {
                                // Not critical, just log the error
                                error_log("Failed to create balance record: " . mysqli_stmt_error($stmt));
                            }
                            
                            // Reset rate limit on successful registration
                            resetRateLimit('register_' . $clientIP);
                            
                            $response['success'] = true;
                            $response['message'] = "Registration successful! You can now log in.";
                            $response['userId'] = $userId; 
                        }
                    }
                }
                
                if (isset($stmt) && $stmt) {
                    mysqli_stmt_close($stmt);
                }
            } catch (Exception $e) {
                $response['message'] = "Database error: " . $e->getMessage();
                $response['error_details'] = $e->getTraceAsString();
            }
            
            // Record failed registration attempt if not successful
            if (!$response['success'] && isset($clientIP)) {
                recordFailedAttempt('register_' . $clientIP);
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

