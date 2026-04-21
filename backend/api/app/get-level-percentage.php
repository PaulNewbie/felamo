<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

function send_error($code, $message) {
    http_response_code($code);
    echo json_encode(['status' => $code, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    send_error(405, $_SERVER['REQUEST_METHOD'] . " method not allowed.");
}

$input = json_decode(file_get_contents("php://input"), true);
$session_id = trim($input['session_id'] ?? '');

if (empty($session_id)) {
    send_error(400, 'Session ID is required.');
}

$conn = (new db_connect())->connect();

$session_stmt = $conn->prepare("SELECT user_id FROM sessions WHERE id = ? AND expiration > NOW()");
$session_stmt->bind_param("s", $session_id);
$session_stmt->execute();
$session_stmt->store_result();

if ($session_stmt->num_rows === 0) {
    send_error(401, 'Invalid or expired session.');
}

$session_stmt->bind_result($user_id);
$session_stmt->fetch();
$session_stmt->close();

$user_stmt = $conn->prepare("SELECT lrn FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if (!$user) {
    send_error(404, 'User not found.');
}

$lrn = $user['lrn'];

$sec_stmt = $conn->prepare("SELECT section_id FROM student_teacher_assignments WHERE student_lrn = ?");
$sec_stmt->bind_param("s", $lrn);
$sec_stmt->execute();
$sec_stmt->bind_result($section_id);
$sec_stmt->fetch();
$sec_stmt->close();

if (empty($section_id)) {
    send_error(404, 'Student section not found.');
}

$teacher_stmt = $conn->prepare("SELECT teacher_id FROM sections WHERE id = ?");
$teacher_stmt->bind_param("i", $section_id);
$teacher_stmt->execute();
$teacher_stmt->bind_result($teacher_id);
$teacher_stmt->fetch();
$teacher_stmt->close();

if (empty($teacher_id)) {
    send_error(404, 'Teacher not found for section.');
}

$level_stmt = $conn->prepare("SELECT id FROM levels WHERE teacher_id = ?");
$level_stmt->bind_param("i", $teacher_id);
$level_stmt->execute();
$levels_result = $level_stmt->get_result();
$level_stmt->close();

$total_levels = 0;
$taken_count = 0;

while ($level = $levels_result->fetch_assoc()) {
    $level_id = $level['id'];
    $total_levels++;

    $assess_stmt = $conn->prepare("SELECT id FROM assessments WHERE level_id = ? LIMIT 1");
    $assess_stmt->bind_param("i", $level_id);
    $assess_stmt->execute();
    $assess_result = $assess_stmt->get_result()->fetch_assoc();
    $assess_stmt->close();

    if (!$assess_result) {
        continue;
    }

    $assessment_id = $assess_result['id'];

    $check_stmt = $conn->prepare("SELECT id FROM assessment_takes WHERE assessment_id = ? AND lrn = ? LIMIT 1");
    $check_stmt->bind_param("ii", $assessment_id, $lrn);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $taken_count++;
    }
    $check_stmt->close();
}

$percentage = $total_levels > 0 ? round(($taken_count / $total_levels) * 100) : 0;

echo json_encode([
    'status' => 'success',
    'percentage' => $percentage
]);
exit;
