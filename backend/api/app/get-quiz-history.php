<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

$input      = json_decode(file_get_contents("php://input"), true);
$session_id = trim($input['session_id'] ?? '');
$aralin_id  = (int)($input['aralin_id'] ?? 0);

if (empty($session_id) || empty($aralin_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Session ID and Aralin ID are required.']);
    exit;
}

$conn = (new db_connect())->connect();

$sess = $conn->prepare("SELECT user_id FROM sessions WHERE id = ? AND expiration > NOW()");
$sess->bind_param("s", $session_id);
$sess->execute();
$sess_row = $sess->get_result()->fetch_assoc();
if (!$sess_row) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired session.']);
    exit;
}
$user_id = $sess_row['user_id'];

$uq = $conn->prepare("SELECT lrn FROM users WHERE id = ?");
$uq->bind_param("i", $user_id);
$uq->execute();
$lrn = $uq->get_result()->fetch_assoc()['lrn'] ?? null;
if (!$lrn) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit;
}

// Get assessment_id for this aralin
$aq = $conn->prepare("SELECT id FROM assessments WHERE aralin_id = ? LIMIT 1");
$aq->bind_param("i", $aralin_id);
$aq->execute();
$assessment = $aq->get_result()->fetch_assoc();
if (!$assessment) {
    echo json_encode(['status' => 'error', 'message' => 'No assessment found.']);
    exit;
}
$assessment_id = $assessment['id'];

// Check if student has a completed pass
$take = $conn->prepare(
    "SELECT id, points, total, created_at
     FROM assessment_takes
     WHERE assessment_id = ? AND lrn = ? AND is_completed = 1
     ORDER BY id DESC LIMIT 1"
);
$take->bind_param("is", $assessment_id, $lrn);
$take->execute();
$take_row = $take->get_result()->fetch_assoc();

if (!$take_row) {
    echo json_encode(['status' => 'not_completed', 'message' => 'Hindi pa natapos ang pagsusulit.']);
    exit;
}

// Pull answer log with question text
$ans = $conn->prepare(
    "SELECT aal.question_id, q.question_text, q.type, q.choices, aal.student_answer, aal.attempted_at
     FROM assessment_answer_log AS aal
     JOIN questions AS q ON aal.question_id = q.id
     WHERE aal.assessment_id = ? AND aal.lrn = ?
     ORDER BY aal.id ASC"
);
$ans->bind_param("is", $assessment_id, $lrn);
$ans->execute();
$result = $ans->get_result();

$answers = [];
while ($row = $result->fetch_assoc()) {
    $choices = null;
    if ($row['type'] === 'multiple_choice' && $row['choices']) {
        $choices = json_decode($row['choices'], true);
    }
    $answers[] = [
        'question_id'    => $row['question_id'],
        'question_text'  => $row['question_text'],
        'type'           => $row['type'],
        'choices'        => $choices,
        'student_answer' => $row['student_answer'],
    ];
}

echo json_encode([
    'status'        => 'success',
    'score'         => (int)$take_row['points'],
    'total'         => (int)$take_row['total'],
    'completed_at'  => $take_row['created_at'],
    'answers'       => $answers,
]);
?>