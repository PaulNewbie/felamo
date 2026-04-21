<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestMethod !== "POST") {
    http_response_code(405);
    echo json_encode([
        'status' => 405,
        'message' => "$requestMethod method not allowed."
    ]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$session_id = trim($input['session_id'] ?? '');

if (empty($session_id)) {
    http_response_code(401);
    echo json_encode([
        'status' => 401,
        'message' => 'Session ID is required.'
    ]);
    exit;
}

$conn = (new db_connect())->connect();

$session_stmt = $conn->prepare("SELECT user_id FROM sessions WHERE id = ? AND expiration > NOW()");
$session_stmt->bind_param("s", $session_id);
$session_stmt->execute();
$session_stmt->store_result();

if ($session_stmt->num_rows === 0) {
    http_response_code(401);
    echo json_encode([
        'status' => 401,
        'message' => 'Invalid or expired session.'
    ]);
    exit;
}

$session_stmt->bind_result($user_id);
$session_stmt->fetch();
$session_stmt->close();

$current_avatar_id = null;
$user_stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($current_avatar_id);
$user_stmt->fetch();
$user_stmt->close();

$avatar_stmt = $conn->prepare("SELECT * FROM avatars");
$avatar_stmt->execute();
$avatar_result = $avatar_stmt->get_result();

$avatars = [];

while ($row = $avatar_result->fetch_assoc()) {
    $avatar_id = $row['id'];

    $check_stmt = $conn->prepare("SELECT 1 FROM user_avatars WHERE avatar_id = ? AND user_id = ? LIMIT 1");
    $check_stmt->bind_param("ii", $avatar_id, $user_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    $owned = $check_stmt->num_rows > 0;
    $check_stmt->close();

    $is_using = ($current_avatar_id == $avatar_id);

    $row['owned'] = $owned;
    $row['is_using'] = $is_using;

    $avatars[] = $row;
}

echo json_encode([
    'status' => 'success',
    'data' => $avatars
]);
exit;
