<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_connection.php';

$response = ['success' => false, 'ticket' => null, 'message' => ''];

try {
    if (!$conn) {
        throw new Exception('DB connection failed: ' . mysqli_connect_error());
    }

    $ticketId = isset($_GET['ticketId']) ? (int)$_GET['ticketId'] : 0;
    if (!$ticketId) {
        throw new Exception('Missing ticketId');
    }

    $query = "SELECT
                t.TicketID,
                t.TicketName AS eventName,
                DATE_FORMAT(t.Date,'%Y-%m-%d') AS date,
                TIME_FORMAT(t.Time,'%H:%i') AS time,
                t.Location AS location,
                t.Price AS price,
                DATE_FORMAT(t.Exp_Date_Time,'%Y-%m-%d %H:%i:%s') AS expiration,
                t.ImageURL AS image,
                t.SellerID,
                t.BuyerID,
                t.SaleType,
                t.Currency AS currency,
                u.Username AS sellerName
              FROM tickets t
              LEFT JOIN users u ON t.SellerID = u.ID
              WHERE t.TicketID = $ticketId";

    $res = mysqli_query($conn, $query);
    if (!$res) {
        throw new Exception('Query failed: ' . mysqli_error($conn));
    }

    if (mysqli_num_rows($res) === 0) {
        throw new Exception('Ticket not found');
    }

    $ticket = mysqli_fetch_assoc($res);
    $response['success'] = true;
    $response['ticket'] = $ticket;
    $response['message'] = 'Ticket fetched';
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
} finally {
    if ($conn) {
        mysqli_close($conn);
    }
    echo json_encode($response);
}
?>
