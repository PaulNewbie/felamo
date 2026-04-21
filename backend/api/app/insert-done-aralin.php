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
$aralin_id = trim($input['aralin_id'] ?? '');

$errors = [];

if (empty($aralin_id)) $errors[] = "Aralin ID is required.";
if (empty($session_id)) $errors[] = "Session ID is required.";

if (!empty($errors)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Validation failed.',
        'errors' => $errors
    ]);
    exit;
}

$conn = (new db_connect())->connect();

$session_stmt = $conn->prepare("SELECT user_id FROM sessions WHERE id = ? AND expiration > NOW()");
$session_stmt->bind_param("s", $session_id);
$session_stmt->execute();
$session_stmt->bind_result($user_id);
$session_stmt->fetch();
$session_stmt->close();

if (empty($user_id)) {
    http_response_code(401);
    echo json_encode([
        'status' => 401,
        'message' => 'Invalid or expired session.'
    ]);
    exit;
}

$aralin_stmt = $conn->prepare("SELECT id FROM aralin WHERE id = ?");
$aralin_stmt->bind_param("i", $aralin_id);
$aralin_stmt->execute();
$aralin_stmt->store_result();

if ($aralin_stmt->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'status' => 404,
        'message' => 'Aralin not found.'
    ]);
    exit;
}
$aralin_stmt->close();

$check_stmt = $conn->prepare("SELECT id FROM done_aralin WHERE user_id = ? AND aralin_id = ?");
$check_stmt->bind_param("ii", $user_id, $aralin_id);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo json_encode([
        'status' => 'info',
        'message' => 'Aralin already marked as done.'
    ]);
    exit;
}
$check_stmt->close();

$insert_stmt = $conn->prepare("INSERT INTO done_aralin (user_id, aralin_id, completed_at) VALUES (?, ?, NOW())");
$insert_stmt->bind_param("ii", $user_id, $aralin_id);

$update_points_stmt = $conn->prepare("UPDATE users SET points = points + 100 WHERE id = ?");
$update_points_stmt->bind_param("i", $user_id);
$update_points_stmt->execute();
$update_points_stmt->close();

if ($insert_stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Aralin marked as done.',
        'points_received' => 100,
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to mark aralin as done.'
    ]);
}
