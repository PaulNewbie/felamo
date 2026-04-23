<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    http_response_code(405);
    echo json_encode(['status' => 405, 'message' => 'Method not allowed.']);
    exit;
}

$input         = json_decode(file_get_contents("php://input"), true);
$session_id    = trim($input['session_id']    ?? '');
$assessment_id = (int)($input['assessment_id'] ?? 0);

if (empty($session_id) || empty($assessment_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Session ID and Assessment ID are required.']);
    exit;
}

$conn = (new db_connect())->connect();

// ── 1. Resolve user ──────────────────────────────────────────────────────────
$sess = $conn->prepare("SELECT user_id FROM sessions WHERE id = ? AND expiration > NOW()");
$sess->bind_param("s", $session_id);
$sess->execute();
$sess_row = $sess->get_result()->fetch_assoc();
if (!$sess_row) {
    echo json_encode(['status' => 401, 'message' => 'Invalid or expired session.']);
    exit;
}
$user_id = $sess_row['user_id'];

$uq = $conn->prepare("SELECT lrn, points FROM users WHERE id = ?");
$uq->bind_param("i", $user_id);
$uq->execute();
$user = $uq->get_result()->fetch_assoc();
if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit;
}
$lrn = $user['lrn'];

// ── 2. Block retakes if already passed ───────────────────────────────────────
$already_done = $conn->prepare(
    "SELECT id FROM assessment_takes WHERE assessment_id = ? AND lrn = ? AND is_completed = 1"
);
$already_done->bind_param("is", $assessment_id, $lrn);
$already_done->execute();
$already_done->store_result();
if ($already_done->num_rows > 0) {
    echo json_encode(['status' => 'already_taken', 'message' => 'Nasagutan mo na ang pagsusulit na ito.']);
    exit;
}
$already_done->close();

// ── 3. Collect answers ───────────────────────────────────────────────────────
$all_answers = array_merge(
    $input['multiple_choices'] ?? [],
    $input['true_or_false']    ?? [],
    $input['identification']   ?? [],
    $input['jumbled_words']    ?? []
);
$total_items = count($all_answers);

if ($total_items === 0) {
    echo json_encode(['status' => 'error', 'message' => 'No answers submitted.']);
    exit;
}

// ── 4. Grade ──────────────────────────────────────────────────────────────────
$score = 0;
foreach ($all_answers as $item) {
    $q_id        = (int)($item['question_id'] ?? 0);
    $user_answer = trim((string)($item['answer'] ?? ''));

    $qstmt = $conn->prepare("SELECT type, correct_answer, choices FROM questions WHERE id = ?");
    $qstmt->bind_param("i", $q_id);
    $qstmt->execute();
    $q = $qstmt->get_result()->fetch_assoc();
    $qstmt->close();
    if (!$q) continue;

    $correct = trim($q['correct_answer']);

    if ($q['type'] === 'multiple_choice') {
        $choices       = json_decode($q['choices'], true) ?? [];
        $selected_text = $choices[strtoupper($user_answer)] ?? '';
        if (strtolower($selected_text) === strtolower($correct)) $score++;
    } elseif ($q['type'] === 'true_false') {
        $db_is_true = in_array(strtolower($correct), ['true', '1', 'tama']) ? 1 : 0;
        if ((int)$user_answer === $db_is_true) $score++;
    } else {
        if (strtolower($user_answer) === strtolower($correct)) $score++;
    }
}

// ── 5. Get aralin_id from assessment ─────────────────────────────────────────
$aq = $conn->prepare("SELECT aralin_id FROM assessments WHERE id = ? LIMIT 1");
$aq->bind_param("i", $assessment_id);
$aq->execute();
$aralin_row = $aq->get_result()->fetch_assoc();
$aq->close();
$aralin_id = $aralin_row['aralin_id'] ?? null;

// ── 6. Count attempts (from log table) ───────────────────────────────────────
$attempt_q = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM assessment_takes_log WHERE assessment_id = ? AND lrn = ?"
);
$attempt_q->bind_param("is", $assessment_id, $lrn);
$attempt_q->execute();
$attempt_cnt = (int)($attempt_q->get_result()->fetch_assoc()['cnt'] ?? 0) + 1;
$attempt_q->close();

// Always log this attempt regardless of pass/fail
$log_stmt = $conn->prepare(
    "INSERT INTO assessment_takes_log (assessment_id, lrn, score, total, attempted_at)
     VALUES (?, ?, ?, ?, NOW())"
);
$log_stmt->bind_param("isii", $assessment_id, $lrn, $score, $total_items);
$log_stmt->execute();
$log_stmt->close();

// ── 7. Pass / Fail ────────────────────────────────────────────────────────────
$pass_threshold = 0.80;
$passed = ($total_items > 0) && ($score / $total_items) >= $pass_threshold;

if ($passed) {
    // Check first pass (no completed record yet)
    $prev = $conn->prepare(
        "SELECT id FROM assessment_takes WHERE assessment_id = ? AND lrn = ?"
    );
    $prev->bind_param("is", $assessment_id, $lrn);
    $prev->execute();
    $prev->store_result();
    $first_pass = ($prev->num_rows === 0);
    $prev->close();

    if ($first_pass) {
        // Insert official pass record
        $ins = $conn->prepare(
            "INSERT INTO assessment_takes (assessment_id, lrn, points, total, is_completed, created_at)
             VALUES (?, ?, ?, ?, 1, NOW())"
        );
        $ins->bind_param("isii", $assessment_id, $lrn, $score, $total_items);
        $ins->execute();
        $ins->close();

        // Save every answer for history view
        $ans_stmt = $conn->prepare(
            "INSERT INTO assessment_answer_log (assessment_id, lrn, question_id, student_answer, attempted_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        foreach ($all_answers as $item) {
            $q_id        = (int)($item['question_id'] ?? 0);
            $user_answer = trim((string)($item['answer'] ?? ''));
            $ans_stmt->bind_param("isis", $assessment_id, $lrn, $q_id, $user_answer);
            $ans_stmt->execute();
        }
        $ans_stmt->close();

        // Award bonus points
        $bonus = 35;
        $upd = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $upd->bind_param("ii", $bonus, $user_id);
        $upd->execute();
        $upd->close();
    } else {
        $bonus = 0;
    }

    // Clear rewatch flag
    if ($aralin_id) {
        $clr = $conn->prepare(
            "UPDATE done_aralin SET needs_rewatch = 0 WHERE user_id = ? AND aralin_id = ?"
        );
        $clr->bind_param("ii", $user_id, $aralin_id);
        $clr->execute();
        $clr->close();
    }

    echo json_encode([
        'status'       => 'success',
        'raw_points'   => $score,
        'total_items'  => $total_items,
        'bonus_points' => $bonus ?? 0,
        'first_pass'   => $first_pass,
        'attempts'     => $attempt_cnt,
        'is_completed' => true,
    ]);

} else {
    // FAILED — set rewatch flag so they must watch the video again
    if ($aralin_id) {
        $rw = $conn->prepare(
            "UPDATE done_aralin SET needs_rewatch = 1 WHERE user_id = ? AND aralin_id = ?"
        );
        $rw->bind_param("ii", $user_id, $aralin_id);
        $rw->execute();
        $rw->close();
    }

    echo json_encode([
        'status'       => 'failed',
        'raw_points'   => $score,
        'total_items'  => $total_items,
        'percentage'   => round(($score / $total_items) * 100),
        'attempts'     => $attempt_cnt,
        'is_completed' => false,
        'message'      => 'Hindi nakamit ang 80%. Pakitingnan muli ang aralin bago muling sumubok.',
    ]);
}