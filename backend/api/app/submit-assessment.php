<?php
// Safety net to show exact errors in terminal instead of crashing
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
$assessment_id = $input['assessment_id'] ?? 0;

if (empty($session_id) || empty($assessment_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Session ID and Assessment ID are required.']);
    exit;
}

$conn = (new db_connect())->connect();

// 1. Get User ID and LRN
$session_stmt = $conn->prepare("SELECT user_id FROM sessions WHERE id = ? AND expiration > NOW()");
$session_stmt->bind_param("s", $session_id);
$session_stmt->execute();
$session_result = $session_stmt->get_result();

if ($session_result->num_rows === 0) {
    echo json_encode(['status' => 401, 'message' => 'Invalid or expired session.']);
    exit;
}
$user_id = $session_result->fetch_assoc()['user_id'];

$user_stmt = $conn->prepare("SELECT lrn FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$lrn = $user_stmt->get_result()->fetch_assoc()['lrn'];

// 2. Gather All Submitted Answers
$all_answers = [];
if (!empty($input['multiple_choices'])) {
    foreach ($input['multiple_choices'] as $ans) $all_answers[] = $ans;
}
if (!empty($input['true_or_false'])) {
    foreach ($input['true_or_false'] as $ans) $all_answers[] = $ans;
}
if (!empty($input['identification'])) {
    foreach ($input['identification'] as $ans) $all_answers[] = $ans;
}
if (!empty($input['jumbled_words'])) {
    foreach ($input['jumbled_words'] as $ans) $all_answers[] = $ans;
}

$total_items = count($all_answers); // Get total questions answered

// 3. The Smart Grader Logic
$score = 0;
foreach ($all_answers as $item) {
    $q_id = $item['question_id'];
    $user_answer = $item['answer'];

    $q_stmt = $conn->prepare("SELECT type, correct_answer, choices FROM questions WHERE id = ?");
    $q_stmt->bind_param("i", $q_id);
    $q_stmt->execute();
    $q_res = $q_stmt->get_result();

    if ($q_res->num_rows > 0) {
        $q = $q_res->fetch_assoc();
        $correct = trim($q['correct_answer']);

        if ($q['type'] == 'multiple_choice') {
            $choices = json_decode($q['choices'], true);
            $selected_text = $choices[strtoupper($user_answer)] ?? '';
            
            if (strtolower(trim($selected_text)) === strtolower($correct)) {
                $score++;
            }
        } elseif ($q['type'] == 'true_false') {
            $db_is_true = (strtolower($correct) === 'true' || $correct == '1' || strtolower($correct) == 'tama') ? 1 : 0;
            if ((int)$user_answer === $db_is_true) {
                $score++;
            }
        } else {
            if (strtolower(trim($user_answer)) === strtolower($correct)) {
                $score++;
            }
        }
    }
}

// 4. Calculate Percentage & Enforce the 80% Rule
$percentage = ($total_items > 0) ? ($score / $total_items) * 100 : 0;
$passing_grade = 80;

if ($percentage >= $passing_grade) {
    // --- PASSED ---
    
    // Check if they already passed this before (prevent duplicate reward exploits)
    $check_pass_stmt = $conn->prepare("SELECT id FROM assessment_takes WHERE assessment_id = ? AND lrn = ?");
    $check_pass_stmt->bind_param("is", $assessment_id, $lrn);
    $check_pass_stmt->execute();
    $already_passed = $check_pass_stmt->get_result()->num_rows > 0;

    if (!$already_passed) {
        // First time passing: Record it
        $insert_stmt = $conn->prepare("INSERT INTO assessment_takes (assessment_id, lrn, points, total) VALUES (?, ?, ?, ?)");
        $insert_stmt->bind_param("isii", $assessment_id, $lrn, $score, $total_items);
        $insert_stmt->execute();

        // Award Bonus Points
        $bonus_points = 35; // Taho reward
        $total_points_earned = $score + $bonus_points;

        $points_stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $points_stmt->bind_param("ii", $total_points_earned, $user_id);
        $points_stmt->execute();
    } else {
        // If they are just retaking an already passed quiz for fun, no new bonus points
        $bonus_points = 0; 
    }

    echo json_encode([
        'status' => 'success',
        'raw_points' => $score,
        'bonus_points' => $bonus_points
    ]);

} else {
    // --- FAILED (< 80%) ---
    
    // Find the aralin_id for this assessment
    $ar_stmt = $conn->prepare("SELECT aralin_id FROM assessments WHERE id = ? LIMIT 1");
    $ar_stmt->bind_param("i", $assessment_id);
    $ar_stmt->execute();
    $aralin_id = $ar_stmt->get_result()->fetch_assoc()['aralin_id'];

    // Update done_aralin to force a re-watch before they can fetch questions again
    // (Requires adding a 'needs_rewatch' boolean column to done_aralin, defaulting to 0)
    $rewatch_stmt = $conn->prepare("UPDATE done_aralin SET needs_rewatch = 1 WHERE user_id = ? AND aralin_id = ?");
    $rewatch_stmt->bind_param("ii", $user_id, $aralin_id);
    $rewatch_stmt->execute();

    echo json_encode([
        'status' => 'failed',
        'raw_points' => $score,
        'message' => 'Hindi nakamit ang 80%.'
    ]);
}
?>