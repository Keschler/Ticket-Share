<?php
// Simple test to check what manage-favorites.php returns
session_start();

// Mock a logged-in user session for testing
$_SESSION['loggedin'] = true;
$_SESSION['id'] = 1; // Test with user ID 1
$_SESSION['csrf_token'] = 'test_token';

// Set request method
$_SERVER['REQUEST_METHOD'] = 'GET';

echo "Testing favorites response:\n";
echo "=========================\n";

// Capture the output from manage-favorites.php
ob_start();
include 'backend/manage-favorites.php';
$output = ob_get_clean();

echo "Raw output: " . $output . "\n";
echo "=========================\n";

// Try to decode the JSON
$data = json_decode($output, true);
if ($data) {
    echo "Parsed JSON:\n";
    echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    echo "Message: " . ($data['message'] ?? 'none') . "\n";
    echo "Has favorites property: " . (isset($data['favorites']) ? 'yes' : 'no') . "\n";
    if (isset($data['favorites'])) {
        echo "Favorites count: " . count($data['favorites']) . "\n";
    }
    if (isset($data['error_details'])) {
        echo "Error details: " . json_encode($data['error_details']) . "\n";
    }
} else {
    echo "Failed to parse JSON response\n";
    echo "JSON Error: " . json_last_error_msg() . "\n";
}
?>
