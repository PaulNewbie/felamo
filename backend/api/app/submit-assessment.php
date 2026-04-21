<?php

use function PHPSTORM_META\type;

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
    echo json_encode(['status' => 'error', 'message' => 'Session ID and assessment ID are required.']);
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
    echo json_encode(['status' => 'error', 'message' => 'User LRN not found.']);
    exit;
}

$conn->begin_transaction();

$check_stmt = $conn->prepare("SELECT id FROM assessment_takes WHERE assessment_id = ? AND lrn = ?");
$check_stmt->bind_param("is", $assessment_id, $lrn);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'You have already taken this assessment.']);
    $check_stmt->close();
    $conn->rollback();
    exit;
}
$check_stmt->close();

try {
    $total_points = 0;
    $total_questions = 0;

    // Count points for each type
    $types = [
        'multiple_choices' => $multiple_choices,
        'true_or_false' => $true_or_false,
        'identifications' => $identification,
        'jumbled_words' => $jumbled_words
    ];

    foreach ($types as $type => $items) {
        foreach ($items as $item) {
            $total_questions++;
            $question_id = (int)($item['question_id'] ?? 0);
            $answer = trim($item['answer'] ?? '');

            $column = ($type === 'multiple_choices') ? 'correct_answer' : 'answer';
            $stmt = $conn->prepare("SELECT $column FROM $type WHERE id = ?");
            $stmt->bind_param("i", $question_id);
            $stmt->execute();
            $stmt->bind_result($correct_answer);
            $stmt->fetch();
            $stmt->close();

            $is_correct = strtoupper($answer) === strtoupper($correct_answer) ? 1 : 0;
            if ($type === 'true_or_false') {
                $is_correct = ((int)$answer === (int)$correct_answer) ? 1 : 0;
            }

            if ($is_correct) $total_points++;
        }
    }

    $passing_score = $total_questions * 0.75;
    $is_pass = $total_points >= $passing_score;

    $level_stmt = $conn->prepare("
        SELECT l.level, l.id
        FROM assessments AS a
        JOIN levels AS l ON a.level_id = l.id
        WHERE a.id = ?
    ");
    $level_stmt->bind_param("i", $assessment_id);
    $level_stmt->execute();
    $level_stmt->bind_result($level, $level_id);
    $level_stmt->fetch();
    $level_stmt->close();

    if (!$is_pass) {
        $delete_stmt = $conn->prepare("
            DELETE da FROM done_aralin AS da
            JOIN aralin AS a ON a.id = da.aralin_id
            WHERE da.user_id = ? AND a.level_id = ?
        ");
        $delete_stmt->bind_param("ii", $user_id, $level_id);
        $delete_stmt->execute();
        $delete_stmt->close();

        $conn->commit();
        echo json_encode([
            'status' => 'failed',
            'message' => 'Assessment failed. Records for this level have been reset.',
            'raw_points' => $total_points,
            'required_to_pass' => ceil($passing_score)
        ]);
        exit;
    }

    $stmtTake = $conn->prepare("
        INSERT INTO assessment_takes (assessment_id, lrn, created_at, updated_at, points, total)
        VALUES (?, ?, NOW(), NOW(), ?, ?)
    ");
    $stmtTake->bind_param("isii", $assessment_id, $lrn, $total_points, $total_questions);
    $stmtTake->execute();
    $take_id = $stmtTake->insert_id;
    $stmtTake->close();

    $bonus_points = 0;
    switch ((int)$level) {
        case 1:
            if ($total_points <= 5) $bonus_points = 10;
            elseif ($total_points <= 10) $bonus_points = 35;
            elseif ($total_points <= 14) $bonus_points = 50;
            else $bonus_points = 100;
            break;
        case 2:
            if ($total_points <= 9) $bonus_points = 35;
            elseif ($total_points <= 19) $bonus_points = 75;
            else $bonus_points = 100;
            break;
        case 3:
            if ($total_points <= 12) $bonus_points = 35;
            elseif ($total_points <= 24) $bonus_points = 75;
            else $bonus_points = 100;
            break;
        case 4:
            if ($total_points <= 14) $bonus_points = 35;
            elseif ($total_points <= 29) $bonus_points = 75;
            else $bonus_points = 100;
            break;
    }

    // Update user points
    $updateUserPoints = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $updateUserPoints->bind_param("ii", $bonus_points, $user_id);
    $updateUserPoints->execute();
    $updateUserPoints->close();

    $insertLog = $conn->prepare("INSERT INTO assessment_takes_log ('assessment_id', 'lrn') VALUES (?,?)");
    $insertLog->bind_param("is", $take_id, $lrn);
    $insertLog->execute();
    $insertLog->close();

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Assessment submitted successfully.',
        'take_id' => $take_id,
        'raw_points' => $total_points,
        'bonus_points' => $bonus_points,
        'total_points' => $current_total_points + $bonus_points
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Submission failed.', 'error' => $e->getMessage()]);
}
