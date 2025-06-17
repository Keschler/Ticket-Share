<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// If running via CLI, force a GET request and fake a logged-in user with ID 6
if (php_sapi_name() === 'cli') {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SESSION = ['id' => 6];
    $userId = 6;
} else {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Credentials: true');
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    require_once 'db_connection.php';
    require_once 'session.php';

    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'User not logged in'], JSON_PRETTY_PRINT);
        exit;
    }

    $userId = (int) $_SESSION['id'];
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID'], JSON_PRETTY_PRINT);
        exit;
    }
}

$response = ['success' => false, 'message' => ''];

try {
    // When running from CLI, we still need a database connection
    if (php_sapi_name() === 'cli') {
        require_once 'db_connection.php';
        if (!$conn) {
            throw new Exception('DB-connect failed: ' . mysqli_connect_error());
        }
    }

    /* ----------  GET  ---------- */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 1. Fetch raw Favorites field
        $res = mysqli_query($conn, "SELECT Favorites FROM users WHERE ID = $userId");
        if (!$res || !mysqli_num_rows($res)) {
            throw new Exception('User not found');
        }
        $favRaw = mysqli_fetch_assoc($res)['Favorites'] ?? '';

        // 2. Convert to integer array
        $favIds = [];
        if ($favRaw !== '' && $favRaw !== null) {
            $decoded = json_decode($favRaw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $favIds = is_array($decoded) ? $decoded : [(int)$decoded];
            } elseif (is_numeric($favRaw)) {
                $favIds = [(int)$favRaw];
            } else {
                foreach (explode(',', $favRaw) as $p) {
                    if (is_numeric(trim($p))) {
                        $favIds[] = (int)$p;
                    }
                }
            }
        }

        // 3. Load ticket details
        $favorites = [];
        if ($favIds) {
            $idList = implode(',', array_map('intval', $favIds));
            $q = "
              SELECT t.TicketID,
                     t.TicketName AS eventName,
                     DATE_FORMAT(t.Date,'%Y-%m-%d') AS date,
                     TIME_FORMAT(t.Time,'%H:%i') AS time,
                     t.Location AS location,
                     t.Price AS price,
                     t.ImageURL AS image,
                     t.BuyerID,
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
            'success'   => true,
            'favorites' => $favorites,
            'message'   => count($favorites) . ' favorites found'
        ];
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
        exit;
    }

    /* ----------  POST  ---------- */
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!checkCsrfToken()) {
            throw new Exception('Invalid CSRF token');
        }

        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $action   = $data['action'] ?? '';
        $ticketId = (int)($data['ticketId'] ?? 0);
        if (!$ticketId) {
            throw new Exception('Missing ticketId');
        }

        // Fetch current favorites list
        $res = mysqli_query($conn, "SELECT Favorites FROM users WHERE ID = $userId");
        $cur = $res ? mysqli_fetch_assoc($res)['Favorites'] : '[]';
        $fav = json_decode($cur ?: '[]', true) ?: [];

        if ($action === 'add') {
            if (!in_array($ticketId, $fav)) {
                $fav[] = $ticketId;
            }
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
        if (!$ok) {
            throw new Exception('Update failed: ' . mysqli_error($conn));
        }

        $response = ['success' => true, 'message' => $msg];
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
        exit;
    }

    else {
        throw new Exception('Unsupported method');
    }

} catch (Exception $e) {
    $response['success']   = false;
    $response['message']   = $e->getMessage();
    $response['favorites'] = $response['favorites'] ?? [];
    error_log('Favorites error: ' . $e->getMessage());
    echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
    exit;
}
?>