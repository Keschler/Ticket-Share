<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit(0);

require_once 'db_connection.php';
require_once 'session.php';

$response = ['success' => false, 'message' => ''];

try {
    if (!$conn)
        throw new Exception('DB-connect failed: ' . mysqli_connect_error());
    if (!isLoggedIn())
        throw new Exception('User not logged in');

    $userId = (int) $_SESSION['id'];
    if ($userId <= 0)
        throw new Exception('Invalid user ID');

    /* ----------  GET  ---------- */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // 1. Rohdaten holen
        $res = mysqli_query($conn, "SELECT Favorites FROM users WHERE ID = $userId");
        if (!$res || !mysqli_num_rows($res))
            throw new Exception('User not found');
        $favRaw = mysqli_fetch_assoc($res)['Favorites'] ?? '';

        // 2. In Integer-Array verwandeln
        $favIds = [];
        if ($favRaw !== '' && $favRaw !== null) {
            $decoded = json_decode($favRaw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $favIds = is_array($decoded) ? $decoded : [(int) $decoded];
            } elseif (is_numeric($favRaw)) {
                $favIds = [(int) $favRaw];
            } else {
                foreach (explode(',', $favRaw) as $p)
                    if (is_numeric(trim($p)))
                        $favIds[] = (int) $p;
            }
        }

        // 3. Ticket-Details laden
        $favorites = [];
        if ($favIds) {
            $idList = implode(',', array_map('intval', $favIds)); // bereits integer-geprüft
            $q = "
              SELECT t.TicketID,
                     t.TicketName AS eventName,
                     DATE_FORMAT(t.Date,'%Y-%m-%d') AS date,
                     TIME_FORMAT(t.Time,'%H:%i') AS time,
                     t.Location AS location,
                     t.Price AS price,
                     t.ImageURL AS image,
                     u.Username AS sellerName
              FROM tickets t
              LEFT JOIN users u ON t.SellerID = u.ID
              WHERE t.TicketID IN ($idList)";

            $ticketRes = mysqli_query($conn, $q);
            if ($ticketRes) {
                while ($row = mysqli_fetch_assoc($ticketRes)) {
                    $favorites[] = $row;
                }
            }
        }

        $response = [
            'success' => true,
            'favorites' => $favorites,
            'message' => count($favorites) . ' favorites found'
        ];
    }

    /* ----------  POST  ---------- */ elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!checkCsrfToken())
            throw new Exception('Invalid CSRF token');

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $data['action'] ?? '';
        $ticketId = (int) ($data['ticketId'] ?? 0);

        if (!$ticketId)
            throw new Exception('Missing ticketId');

        // aktuelle Liste holen
        $res = mysqli_query($conn, "SELECT Favorites FROM users WHERE ID = $userId");
        $cur = $res ? mysqli_fetch_assoc($res)['Favorites'] : '[]';
        $fav = json_decode($cur ?: '[]', true) ?: [];

        if ($action === 'add') {
            if (!in_array($ticketId, $fav))
                $fav[] = $ticketId;
            $msg = 'Ticket added';
        } elseif ($action === 'remove') {
            $fav = array_values(array_filter($fav, fn($id) => $id != $ticketId));
            $msg = 'Ticket removed';
        } else {
            throw new Exception('Action must be add/remove');
        }

        $ok = mysqli_query(
            $conn,
            "UPDATE users SET Favorites = '" . mysqli_real_escape_string($conn, json_encode($fav)) . "' WHERE ID = $userId"
        );

        if (!$ok)
            throw new Exception('Update failed: ' . mysqli_error($conn));

        $response = ['success' => true, 'message' => $msg];
    } else
        throw new Exception('Unsupported method');

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['favorites'] = $response['favorites'] ?? [];
    error_log('Favorites error: ' . $e->getMessage());
}

echo json_encode($response);
?>