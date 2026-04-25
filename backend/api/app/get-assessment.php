<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    http_response_code(405);
    echo json_encode(['status' => 405, 'message' => "Method not allowed."]);
    exit;
}

$input      = json_decode(file_get_contents("php://input"), true);
$session_id = trim($input['session_id'] ?? '');
$aralin_id  = (int)($input['aralin_id']  ?? 0);

if (empty($aralin_id) || empty($session_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Session ID and Aralin ID are required.']);
    exit;
}

$conn = (new db_connect())->connect();

// ── 1. Resolve session → user_id ─────────────────────────────────────────────
$session_stmt = $conn->prepare("SELECT user_id FROM sessions WHERE id = ? AND expiration > NOW()");
$session_stmt->bind_param("s", $session_id);
$session_stmt->execute();
$session_row = $session_stmt->get_result()->fetch_assoc();
$session_stmt->close();

if (!$session_row) {
    echo json_encode(['status' => 401, 'message' => 'Invalid or expired session.']);
    exit;
}
$user_id = (int)$session_row['user_id'];

// ── 2. Resolve user_id → lrn ─────────────────────────────────────────────────
$lrn_stmt = $conn->prepare("SELECT lrn FROM users WHERE id = ?");
$lrn_stmt->bind_param("i", $user_id);
$lrn_stmt->execute();
$lrn_row = $lrn_stmt->get_result()->fetch_assoc();
$lrn_stmt->close();

if (!$lrn_row || empty($lrn_row['lrn'])) {
    echo json_encode(['status' => 'error', 'message' => 'User LRN not found.']);
    exit;
}
$lrn = $lrn_row['lrn'];

// ── 3. Get the assessment linked to THIS specific aralin ──────────────────────
$assess_stmt = $conn->prepare("SELECT id FROM assessments WHERE aralin_id = ? LIMIT 1");
$assess_stmt->bind_param("i", $aralin_id);
$assess_stmt->execute();
$assessment = $assess_stmt->get_result()->fetch_assoc();
$assess_stmt->close();

if (!$assessment) {
    echo json_encode(['status' => 'error', 'message' => 'Assessment not found for this lesson.']);
    exit;
}
$assessment_id = (int)$assessment['id'];

// ── 4. Check if student already PASSED THIS specific aralin's assessment ──────
$already_stmt = $conn->prepare(
    "SELECT id FROM assessment_results
     WHERE assessment_id = ? AND lrn = ? AND is_completed = 1
     LIMIT 1"
);
$already_stmt->bind_param("is", $assessment_id, $lrn);
$already_stmt->execute();
$already_stmt->store_result();
$already_passed = ($already_stmt->num_rows > 0);
$already_stmt->close();

if ($already_passed) {
    echo json_encode([
        'status'  => 'already_taken',
        'message' => 'Nasagutan mo na ang pagsusulit na ito.',
    ]);
    exit;
}

// ── 5. Check if aralin video was watched (BYPASSED FOR TESTING) ───────────────
$done_stmt = $conn->prepare(
    "SELECT COUNT(*) AS done, COALESCE(MAX(needs_rewatch), 0) AS needs_rewatch
     FROM student_aralin_progress
     WHERE aralin_id = ? AND user_id = ?"
);
$done_stmt->bind_param("ii", $aralin_id, $user_id);
$done_stmt->execute();
$done_row = $done_stmt->get_result()->fetch_assoc();
$done_stmt->close();

// TEMPORARILY COMMENTED OUT SO YOU CAN TEST QUIZZES INDEPENDENTLY
/*
if ((int)$done_row['done'] === 0) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Please watch the lesson video before taking this assessment.',
    ]);
    exit;
}
*/

if ((int)$done_row['needs_rewatch'] === 1) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'You must re-watch the video before retaking this assessment.',
    ]);
    exit;
}

// ── 6. Fetch the full assessment row for the response ────────────────────────
$full_assess_stmt = $conn->prepare("SELECT * FROM assessments WHERE id = ? LIMIT 1");
$full_assess_stmt->bind_param("i", $assessment_id);
$full_assess_stmt->execute();
$full_assessment = $full_assess_stmt->get_result()->fetch_assoc();
$full_assess_stmt->close();

// ── 7. Fetch ALL questions for THIS specific assessment ───────────────────────
// FIX: No more concept_group limits. It just fetches exactly what is in the DB!
$flutter_data = [
    'assessment'       => $full_assessment,
    'multiple_choices' => [],
    'true_or_false'    => [],
    'identification'   => [],
    'jumbled_words'    => [],
];

$q_stmt = $conn->prepare("SELECT * FROM questions WHERE assessment_id = ? ORDER BY RAND() LIMIT 15");
$q_stmt->bind_param("i", $assessment_id);
$q_stmt->execute();
$result = $q_stmt->get_result();

while ($q = $result->fetch_assoc()) {
    if ($q['type'] === 'multiple_choice') {
        $choices = json_decode($q['choices'], true) ?? [];
        $flutter_data['multiple_choices'][] = [
            'id'             => $q['id'],
            'question'       => $q['question_text'],
            'choice_a'       => $choices['A'] ?? '',
            'choice_b'       => $choices['B'] ?? '',
            'choice_c'       => $choices['C'] ?? '',
            'choice_d'       => $choices['D'] ?? '',
            'correct_answer' => $q['correct_answer'],
        ];
    } elseif ($q['type'] === 'true_false') {
        $flutter_data['true_or_false'][] = [
            'id'       => $q['id'],
            'question' => $q['question_text'],
            'answer'   => in_array(strtolower(trim($q['correct_answer'])), ['true', '1', 'tama']) ? 1 : 0,
        ];
    } elseif ($q['type'] === 'identification') {
        $flutter_data['identification'][] = [
            'id'       => $q['id'],
            'question' => $q['question_text'],
            'answer'   => $q['correct_answer'],
        ];
    } elseif ($q['type'] === 'jumbled_word' || $q['type'] === 'jumbled_words') {
        $flutter_data['jumbled_words'][] = [
            'id'       => $q['id'],
            'question' => $q['question_text'],
            'answer'   => $q['correct_answer'],
        ];
    }
}
$q_stmt->close();

echo json_encode(['status' => 'success', 'data' => $flutter_data]);