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
// $current_password = trim($input['password'] ?? '');
$new_password = trim($input['new_password'] ?? '');

// if (empty($session_id) || empty($current_password) || empty($new_password)) {
if (empty($session_id) || empty($new_password)) {
    http_response_code(400);
    echo json_encode([
        'status' => 400,
        'message' => 'Session ID, current password, and new password are required.'
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

$user_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->store_result();

if ($user_stmt->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'status' => 404,
        'message' => 'User not found.'
    ]);
    exit;
}

$user_stmt->bind_result($hashed_password);
$user_stmt->fetch();
$user_stmt->close();

// if (!password_verify($current_password, $hashed_password)) {
//     http_response_code(401);
//     echo json_encode([
//         'status' => 401,
//         'message' => 'Current password is incorrect.'
//     ]);
//     exit;
// }

$new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$update_stmt->bind_param("si", $new_hashed_password, $user_id);

if ($update_stmt->execute()) {
    echo json_encode([
        'status' => 200,
        'message' => 'Password updated successfully.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'message' => 'Failed to update password.'
    ]);
}

$update_stmt->close();
