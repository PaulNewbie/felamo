<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    http_response_code(405);
    echo json_encode([
        'status' => 405,
        'message' => 'POST method required.'
    ]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$session_id = trim($input['session_id'] ?? '');
$assessment_id = trim($input['assessment_id'] ?? '');

if (empty($assessment_id)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Assessment ID is required.'
    ]);
    exit;
}

if (empty($session_id)) {
    http_response_code(401);
    echo json_encode([
        'status' => 401,
        'message' => 'Session ID is required.'
    ]);
    exit;
}

$conn = (new db_connect())->connect();

// Validate session
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
$session_stmt->close();

$stmt = $conn->prepare("
    SELECT at.*, u.first_name, u.last_name 
    FROM assessment_takes AS at
    JOIN users AS u ON at.lrn = u.lrn
    WHERE at.assessment_id = ?
    ORDER BY points DESC
");

$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$result = $stmt->get_result();

$takes = [];
while ($row = $result->fetch_assoc()) {
    $takes[] = $row;
}

echo json_encode([
    'status' => 'success',
    'data' => $takes
]);
exit;
