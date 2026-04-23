<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestMethod !== "POST") {
    http_response_code(405);
    echo json_encode(['status' => 405, 'message' => "$requestMethod method not allowed."]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$session_id = trim($input['session_id'] ?? '');
$aralin_id = trim($input['aralin_id'] ?? ''); // CHANGED to look for aralin_id

if (empty($aralin_id) || empty($session_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Session ID and Aralin ID are required.']);
    exit;
}

$conn = (new db_connect())->connect();

// 1. Verify Session and User
$session_stmt = $conn->prepare("SELECT user_id FROM sessions WHERE id = ? AND expiration > NOW()");
$session_stmt->bind_param("s", $session_id);
$session_stmt->execute();
$session_result = $session_stmt->get_result();

if ($session_result->num_rows === 0) {
    echo json_encode(['status' => 401, 'message' => 'Invalid or expired session.']);
    exit;
}
$user = $session_result->fetch_assoc();
$user_id = $user['user_id'];

// 2. Check if THIS SPECIFIC Aralin (Lesson) is done
// Check if the student has watched this aralin
$done_q = $conn->prepare(
    "SELECT COUNT(*) as done, COALESCE(MAX(needs_rewatch), 0) AS needs_rewatch
     FROM done_aralin WHERE aralin_id = ? AND user_id = ?"
);
$done_q->bind_param("ii", $aralin_id, $user_id);
$done_q->execute();
$done_row = $done_q->get_result()->fetch_assoc();

if ((int)$done_row['done'] === 0) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Please watch the lesson video before taking this assessment.',
    ]);
    exit;
}

if ((int)$done_row['needs_rewatch'] === 1) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'You must re-watch the video before retaking this assessment.',
    ]);
    exit;
}

// Also guard against taking an already-passed quiz a second time
$already = $conn->prepare("SELECT id FROM assessment_takes WHERE assessment_id = ? AND lrn = ?");
$already->bind_param("is", $assessment_id, $lrn);
$already->execute();
$already->store_result();
if ($already->num_rows > 0) {
    echo json_encode([
        'status'  => 'already_taken',
        'message' => 'Nasagutan mo na ang pagsusulit na ito.',
    ]);
    exit;
}

// 3. Get Assessment Details linked to the Aralin
$stmt = $conn->prepare("SELECT * FROM assessments WHERE aralin_id = ? LIMIT 1"); // CHANGED from level_id
$stmt->bind_param("i", $aralin_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();

if (!$assessment) {
    echo json_encode(['status' => 'error', 'message' => 'Assessment not found for this lesson.']);
    exit;
}
$assessment_id = $assessment['id'];

// --- THE SMART QUIZ GENERATOR ---
$types = ['multiple_choice', 'true_false', 'identification', 'jumbled_word'];
$pool_by_type = ['multiple_choice' => [], 'true_false' => [], 'identification' => [], 'jumbled_word' => []];

$concept_stmt = $conn->prepare("SELECT type, concept_group_id FROM questions WHERE assessment_id = ? GROUP BY type, concept_group_id");

if (!$concept_stmt) {
    echo json_encode(['status' => 500, 'message' => 'SQL Error (questions): ' . $conn->error]);
    exit;
}

$concept_stmt->bind_param("i", $assessment_id);
$concept_stmt->execute();
$concept_result = $concept_stmt->get_result();

while ($row = $concept_result->fetch_assoc()) {
    $pool_by_type[$row['type']][] = $row['concept_group_id'];
}

$selected_concepts = [];

foreach ($types as $type) {
    shuffle($pool_by_type[$type]); 
    for ($i = 0; $i < 2; $i++) {
        if (!empty($pool_by_type[$type])) {
            $selected_concepts[] = array_pop($pool_by_type[$type]);
        }
    }
}

$remaining_pool = array_merge($pool_by_type['multiple_choice'], $pool_by_type['true_false'], $pool_by_type['identification'], $pool_by_type['jumbled_word']);
shuffle($remaining_pool);

$slots_left = 15 - count($selected_concepts);
for ($i = 0; $i < $slots_left; $i++) {
    if (!empty($remaining_pool)) {
        $selected_concepts[] = array_pop($remaining_pool);
    }
}

$flutter_data = [
    'assessment' => $assessment,
    'multiple_choices' => [],
    'true_or_false' => [],
    'identification' => [],
    'jumbled_words' => []
];

foreach ($selected_concepts as $concept_id) {
    $q_stmt = $conn->prepare("SELECT * FROM questions WHERE concept_group_id = ? ORDER BY RAND() LIMIT 1");
    $q_stmt->bind_param("i", $concept_id);
    $q_stmt->execute();
    $q = $q_stmt->get_result()->fetch_assoc();

    if ($q['type'] == 'multiple_choice') {
        $choices = json_decode($q['choices'], true);
        $flutter_data['multiple_choices'][] = [
            'id' => $q['id'],
            'question' => $q['question_text'],
            'choice_a' => $choices['A'] ?? '',
            'choice_b' => $choices['B'] ?? '',
            'choice_c' => $choices['C'] ?? '',
            'choice_d' => $choices['D'] ?? '',
            'correct_answer' => $q['correct_answer']
        ];
    } elseif ($q['type'] == 'true_false') {
        $flutter_data['true_or_false'][] = [
            'id' => $q['id'],
            'question' => $q['question_text'],
            'answer' => (strtolower($q['correct_answer']) == 'true') ? 1 : 0
        ];
    } elseif ($q['type'] == 'identification') {
        $flutter_data['identification'][] = [
            'id' => $q['id'],
            'question' => $q['question_text'],
            'answer' => $q['correct_answer']
        ];
    } elseif ($q['type'] == 'jumbled_word') {
        $flutter_data['jumbled_words'][] = [
            'id' => $q['id'],
            'question' => $q['question_text'],
            'answer' => $q['correct_answer']
        ];
    }
}

echo json_encode(['status' => 'success', 'data' => $flutter_data]);
?>