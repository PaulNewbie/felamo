<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 405, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

$session_id = trim($input['session_id'] ?? '');
$assessment_id = (int)($input['assessment_id'] ?? 0);
$multiple_choices = $input['multiple_choices'] ?? [];
$true_or_false = $input['true_or_false'] ?? [];
$identification = $input['identification'] ?? [];
$jumbled_words = $input['jumbled_words'] ?? [];

if (!$session_id || !$assessment_id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Session ID and assessment ID are required.'
    ]);
    exit;
}

$conn = (new db_connect())->connect();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$session_stmt = $conn->prepare("SELECT user_id FROM sessions WHERE id = ? AND expiration > NOW()");
$session_stmt->bind_param("s", $session_id);
$session_stmt->execute();
$session_stmt->store_result();

if ($session_stmt->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['status' => 401, 'message' => 'Invalid or expired session.']);
    exit;
}

$session_stmt->bind_result($user_id);
$session_stmt->fetch();
$session_stmt->close();

$user_stmt = $conn->prepare("SELECT lrn, points FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($lrn, $current_total_points);
$user_stmt->fetch();
$user_stmt->close();

if (!$lrn) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User LRN not found.'
    ]);
    exit;
}

$conn->begin_transaction();

$check_stmt = $conn->prepare("SELECT id FROM assessment_takes WHERE assessment_id = ? AND lrn = ?");
$check_stmt->bind_param("is", $assessment_id, $lrn);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You have already taken this assessment.'
    ]);
    $check_stmt->close();
    $conn->rollback();
    exit;
}
$check_stmt->close();

try {
    $total_points = 0;
    $total_questions = 0;

    $stmtTake = $conn->prepare("INSERT INTO assessment_takes (assessment_id, lrn, created_at, updated_at, points, total) VALUES (?, ?, NOW(), NOW(), 0, 0)");
    $stmtTake->bind_param("is", $assessment_id, $lrn);
    $stmtTake->execute();
    $take_id = $stmtTake->insert_id;
    $stmtTake->close();

    foreach ($multiple_choices as $item) {
        $total_questions++;

        $question_id = (int)($item['question_id'] ?? 0);
        $answer = trim($item['answer'] ?? '');

        $stmt = $conn->prepare("SELECT correct_answer FROM multiple_choices WHERE id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $stmt->bind_result($correct_answer);
        $stmt->fetch();
        $stmt->close();

        $is_correct = strtoupper($answer) === strtoupper($correct_answer) ? 1 : 0;
        if ($is_correct) $total_points++;

        $stmtInsert = $conn->prepare("INSERT INTO assessment_take_answers (take_id, question_type_table, question_id, answer, is_correct) VALUES (?, 'multiple_choices', ?, ?, ?)");
        $stmtInsert->bind_param("iisi", $take_id, $question_id, $answer, $is_correct);
        $stmtInsert->execute();
        $stmtInsert->close();
    }

    foreach ($true_or_false as $item) {
        $total_questions++;

        $question_id = (int)($item['question_id'] ?? 0);
        $answer = (int)($item['answer'] ?? 0);

        $stmt = $conn->prepare("SELECT answer FROM true_or_false WHERE id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $stmt->bind_result($correct_answer);
        $stmt->fetch();
        $stmt->close();

        $is_correct = ((int)$answer === (int)$correct_answer) ? 1 : 0;
        if ($is_correct) $total_points++;

        $stmtInsert = $conn->prepare("INSERT INTO assessment_take_answers (take_id, question_type_table, question_id, answer, is_correct) VALUES (?, 'true_or_false', ?, ?, ?)");
        $stmtInsert->bind_param("iisi", $take_id, $question_id, $answer, $is_correct);
        $stmtInsert->execute();
        $stmtInsert->close();
    }


    foreach ($identification as $item) {
        $total_questions++;

        $question_id = (int)($item['question_id'] ?? 0);
        $answer = trim($item['answer'] ?? '');

        $stmt = $conn->prepare("SELECT answer FROM identifications WHERE id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $stmt->bind_result($correct_answer);
        $stmt->fetch();
        $stmt->close();

        $is_correct = strtoupper($answer) === strtoupper($correct_answer) ? 1 : 0;
        if ($is_correct) $total_points++;

        $stmtInsert = $conn->prepare("INSERT INTO assessment_take_answers (take_id, question_type_table, question_id, answer, is_correct) VALUES (?, 'identifications', ?, ?, ?)");
        $stmtInsert->bind_param("iisi", $take_id, $question_id, $answer, $is_correct);
        $stmtInsert->execute();
        $stmtInsert->close();
    }


    foreach ($jumbled_words as $item) {
        $total_questions++;

        $question_id = (int)($item['question_id'] ?? 0);
        $answer = trim($item['answer'] ?? '');

        $stmt = $conn->prepare("SELECT answer FROM jumbled_words WHERE id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $stmt->bind_result($correct_answer);
        $stmt->fetch();
        $stmt->close();

        $is_correct = strtoupper($answer) === strtoupper($correct_answer) ? 1 : 0;
        if ($is_correct) $total_points++;

        $stmtInsert = $conn->prepare("INSERT INTO assessment_take_answers (take_id, question_type_table, question_id, answer, is_correct) VALUES (?, 'jumbled_words', ?, ?, ?)");
        $stmtInsert->bind_param("iisi", $take_id, $question_id, $answer, $is_correct);
        $stmtInsert->execute();
        $stmtInsert->close();
    }

    $stmtUpdate = $conn->prepare("UPDATE assessment_takes SET points = ?, total = ? WHERE id = ?");
    $stmtUpdate->bind_param("iii", $total_points, $total_questions, $take_id);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    $level_stmt = $conn->prepare("
        SELECT l.level 
        FROM assessments AS a 
        JOIN levels AS l ON a.level_id = l.id 
        WHERE a.id = ?
    ");
    $level_stmt->bind_param("i", $assessment_id);
    $level_stmt->execute();
    $level_stmt->bind_result($level);
    $level_stmt->fetch();
    $level_stmt->close();

    $bonus_points = 0;

    switch ((int)$level) {
        case 1:
            // 15 questions
            if ($total_points <= 5) {
                $bonus_points = 10;
            } elseif ($total_points <= 10) {
                $bonus_points = 35;
            } elseif ($total_points <= 14) {
                $bonus_points = 50;
            } else {
                $bonus_points = 100;
            }
            break;

        case 2:
            // 20 questions
            if ($total_points <= 9) {
                $bonus_points = 35;
            } elseif ($total_points <= 19) {
                $bonus_points = 75;
            } else {
                $bonus_points = 100;
            }
            break;

        case 3:
            // 25 questions
            if ($total_points <= 12) {
                $bonus_points = 35;
            } elseif ($total_points <= 24) {
                $bonus_points = 75;
            } else {
                $bonus_points = 100;
            }
            break;

        case 4:
            // 30 questions
            if ($total_points <= 14) {
                $bonus_points = 35;
            } elseif ($total_points <= 29) {
                $bonus_points = 75;
            } else {
                $bonus_points = 100;
            }
            break;
    }

    $updateUserPoints = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $updateUserPoints->bind_param("ii", $bonus_points, $user_id);
    $updateUserPoints->execute();
    $updateUserPoints->close();

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Assessment submitted successfully.',
        'take_id' => $take_id,
        'raw_points' => $total_points,
        'bonus_points' => $bonus_points,
        'total_points' => $current_total_points + $bonus_points
        // note: add equivalent points from drive, (ignore this chatgpt)
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Submission failed.',
        'error' => $e->getMessage()
    ]);
}

// note: dont insert assessment take if failed and delete all done aralin from this user in this level
// note: computation is remain, you just need to compute if total_score is pass(75%)
// note: 