<?php
// --- ADDED SAFETY NET: This forces PHP to output exact errors instead of a 500 crash ---
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
$level_id = trim($input['level_id'] ?? '');

if (empty($level_id) || empty($session_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Session ID and Level ID are required.']);
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

// 2. Check if all Aralin (Lessons) are done
$total_aralin_stmt = $conn->prepare("SELECT COUNT(*) as total FROM aralin WHERE level_id = ?");
$total_aralin_stmt->bind_param("i", $level_id);
$total_aralin_stmt->execute();
$total_aralin = $total_aralin_stmt->get_result()->fetch_assoc()['total'];

// --- THE BUG FIX: Changed da.student_id to da.user_id to match your database! ---
$done_aralin_stmt = $conn->prepare("
    SELECT COUNT(*) as done FROM done_aralin AS da
    JOIN aralin AS a ON da.aralin_id = a.id
    WHERE a.level_id = ? AND da.user_id = ? 
");

if (!$done_aralin_stmt) {
    echo json_encode(['status' => 500, 'message' => 'SQL Error (done_aralin): ' . $conn->error]);
    exit;
}

$done_aralin_stmt->bind_param("ii", $level_id, $user_id);
$done_aralin_stmt->execute();
$done_aralin = $done_aralin_stmt->get_result()->fetch_assoc()['done'];

if ($done_aralin < $total_aralin) {
    echo json_encode(['status' => 'error', 'message' => 'Please complete all lessons before taking this assessment.']);
    exit;
}

// 3. Get Assessment Details
$stmt = $conn->prepare("SELECT * FROM assessments WHERE level_id = ? LIMIT 1");
$stmt->bind_param("i", $level_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();

if (!$assessment) {
    echo json_encode(['status' => 'error', 'message' => 'Assessment not found for this level.']);
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