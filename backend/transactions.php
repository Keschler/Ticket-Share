<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_connection.php';
define('INCLUDED_FROM_OTHER_SCRIPT', true);
require_once 'session.php';

$response = ['success' => false, 'transactions' => []];

try {
    if (!isLoggedIn()) {
        throw new Exception('User is not logged in');
    }

    $userId = $_SESSION['id'];
    $query = "SELECT Type, Amount, Details, Timestamp FROM transactions WHERE UserID = ? ORDER BY Timestamp DESC LIMIT 50";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query');
    }
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $response['transactions'][] = [
            'type' => $row['Type'],
            'amount' => number_format($row['Amount'], 2, '.', ''),
            'details' => $row['Details'],
            'time' => $row['Timestamp']
        ];
    }

    $response['success'] = true;
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
