<?php
// --------------------------------------------------
// backend/get-tickets.php
// --------------------------------------------------

// 1) Prevent session.php from emitting its “status check” JSON:
define('INCLUDED_FROM_OTHER_SCRIPT', true);

// 2) Set JSON header (no other HTML or whitespace must appear)
header('Content-Type: application/json');

// 3) Enable error logging, but do NOT echo any errors or warnings to the output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 4) Include database and session (session.php will NOT send its own JSON now)
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/session.php';

// 5) Initialize the default response
$response = [
    'success' => false,
    'message' => '',
    'tickets' => []
];

try {
    // 6) Ensure user is logged in (session.php has already started the session)
    if (!isLoggedIn()) {
        throw new Exception('User is not logged in');
    }
    $userId = (int) getCurrentUserId();
    if ($userId <= 0) {
        throw new Exception('Invalid user ID');
    }

    // 7) Query only those tickets that have NOT been sold yet (BuyerID IS NULL)
    $sql = "
        SELECT
            TicketID,
            TicketName       AS eventName,
            DATE_FORMAT(Date, '%Y-%m-%d') AS date,
            TIME_FORMAT(Time, '%H:%i')     AS time,
            Location         AS location,
            Price            AS price,
            ImageURL         AS image,
            (SELECT Username 
               FROM users 
              WHERE users.ID = tickets.SellerID
            ) AS sellerName
        FROM tickets
        WHERE BuyerID IS NULL
        ORDER BY Exp_Date_Time DESC
    ";
    $result = mysqli_query($conn, $sql);
    if ($result === false) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    // 8) Fetch into array
    $tickets = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tickets[] = $row;
    }

    // 9) Build the successful response
    $response['success'] = true;
    $response['message'] = count($tickets) . ' tickets found';
    $response['tickets'] = $tickets;

} catch (Exception $e) {
    // 10) On any exception, return success=false plus the message
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['tickets'] = [];
}

// 11) Echo exactly one JSON object (no extra whitespace, no HTML)
echo json_encode($response, JSON_PRETTY_PRINT);
exit;

