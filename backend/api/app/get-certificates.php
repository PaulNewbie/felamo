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

$lrn_stmt = $conn->prepare("SELECT lrn FROM users WHERE id = ?");
$lrn_stmt->bind_param("i", $user_id);
$lrn_stmt->execute();
$lrn_stmt->bind_result($student_lrn);
$lrn_stmt->fetch();
$lrn_stmt->close();

if (!$student_lrn) {
    http_response_code(404);
    echo json_encode([
        'status' => 404,
        'message' => 'Student not found.'
    ]);
    exit;
}

$level_stmt = $conn->prepare("
    SELECT DISTINCT l.level 
    FROM assessment_takes AS at
    JOIN assessments AS a ON at.assessment_id = a.id
    JOIN levels AS l ON a.level_id = l.id
    WHERE at.lrn = ?
");
$level_stmt->bind_param("s", $student_lrn);
$level_stmt->execute();
$result = $level_stmt->get_result();

$certificates = [];

while ($row = $result->fetch_assoc()) {
    $level = (int)$row['level'];
    if ($level >= 1 && $level <= 4) {
        $certificates[] = "White Green Elegant Professional Certificate.zip - {$level}.png";
    }
}

$level_stmt->close();

echo json_encode([
    'status' => 'success',
    'certificates' => $certificates
]);
exit;
