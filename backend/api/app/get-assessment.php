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

$input     = json_decode(file_get_contents("php://input"), true);
$session_id = trim($input['session_id'] ?? '');
$aralin_id  = (int)($input['aralin_id'] ?? 0);

if (empty($aralin_id) || empty($session_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Session ID and Aralin ID are required.']);
    exit;
}

$conn = (new db_connect())->connect();

// ── 1. Resolve session → user_id ─────────────────────────────────────────────
$session_stmt = $conn->prepare("SELECT user_id FROM sessions WHERE id = ? AND expiration > NOW()");
$session_stmt->bind_param("s", $session_id);
$session_stmt->execute();
$session_result = $session_stmt->get_result();
if ($session_result->num_rows === 0) {
    echo json_encode(['status' => 401, 'message' => 'Invalid or expired session.']);
    exit;
}
$user_id = $session_result->fetch_assoc()['user_id'];

// ── 2. Resolve user_id → lrn ─────────────────────────────────────────────────
$lrn_stmt = $conn->prepare("SELECT lrn FROM users WHERE id = ?");
$lrn_stmt->bind_param("i", $user_id);
$lrn_stmt->execute();
$lrn_row = $lrn_stmt->get_result()->fetch_assoc();
if (!$lrn_row || empty($lrn_row['lrn'])) {
    echo json_encode(['status' => 'error', 'message' => 'User LRN not found.']);
    exit;
}
$lrn = $lrn_row['lrn'];

// ── 3. Check if aralin video was watched ──────────────────────────────────────
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

// ── 4. Get assessment linked to aralin ────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM assessments WHERE aralin_id = ? LIMIT 1");
$stmt->bind_param("i", $aralin_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();
if (!$assessment) {
    echo json_encode(['status' => 'error', 'message' => 'Assessment not found for this lesson.']);
    exit;
}
$assessment_id = $assessment['id'];

// ── 5. NOW check if already passed — variables exist here ─────────────────────
$already = $conn->prepare(
    "SELECT id FROM assessment_takes WHERE assessment_id = ? AND lrn = ? AND is_completed = 1"
);
$already->bind_param("is", $assessment_id, $lrn);
$already->execute();
$already->store_result();
if ($already->num_rows > 0) {
    // Return 'already_taken' so Flutter shows history instead of quiz
    echo json_encode([
        'status'  => 'already_taken',
        'message' => 'Nasagutan mo na ang pagsusulit na ito.',
    ]);
    exit;
}

// ── 6. Build quiz (unchanged from your original logic) ────────────────────────
$types = ['multiple_choice', 'true_false', 'identification', 'jumbled_word'];
$pool_by_type = ['multiple_choice' => [], 'true_false' => [], 'identification' => [], 'jumbled_word' => []];

$concept_stmt = $conn->prepare(
    "SELECT type, concept_group_id FROM questions WHERE assessment_id = ? GROUP BY type, concept_group_id"
);
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

$remaining_pool = array_merge(
    $pool_by_type['multiple_choice'],
    $pool_by_type['true_false'],
    $pool_by_type['identification'],
    $pool_by_type['jumbled_word']
);
shuffle($remaining_pool);
$slots_left = 15 - count($selected_concepts);
for ($i = 0; $i < $slots_left; $i++) {
    if (!empty($remaining_pool)) {
        $selected_concepts[] = array_pop($remaining_pool);
    }
}

$flutter_data = [
    'assessment'      => $assessment,
    'multiple_choices' => [],
    'true_or_false'   => [],
    'identification'  => [],
    'jumbled_words'   => [],
];

foreach ($selected_concepts as $concept_id) {
    $q_stmt = $conn->prepare(
        "SELECT * FROM questions WHERE concept_group_id = ? ORDER BY RAND() LIMIT 1"
    );
    $q_stmt->bind_param("i", $concept_id);
    $q_stmt->execute();
    $q = $q_stmt->get_result()->fetch_assoc();
    if (!$q) continue;

    if ($q['type'] === 'multiple_choice') {
        $choices = json_decode($q['choices'], true);
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
            'answer'   => (strtolower($q['correct_answer']) === 'true') ? 1 : 0,
        ];
    } elseif ($q['type'] === 'identification') {
        $flutter_data['identification'][] = [
            'id'       => $q['id'],
            'question' => $q['question_text'],
            'answer'   => $q['correct_answer'],
        ];
    } elseif ($q['type'] === 'jumbled_word') {
        $flutter_data['jumbled_words'][] = [
            'id'       => $q['id'],
            'question' => $q['question_text'],
            'answer'   => $q['correct_answer'],
        ];
    }
}

echo json_encode(['status' => 'success', 'data' => $flutter_data]);