<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

//note: add validation if all videos are done || antas is !is_done

$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestMethod !== "POST") {
    http_response_code(405);
    echo json_encode([
        'status' => 405,
        'message' => "$requestMethod method not allowed."
    ]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$session_id = trim($input['session_id'] ?? '');
$level_id = trim($input['level_id'] ?? '');

$errors = [];

if (empty($level_id)) $errors[] = "Level Id is required.";

if (!empty($errors)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Validation failed.',
        'errors' => $errors
    ]);
    exit;
}

if (empty($session_id)) {
    http_response_code(401);
    echo json_encode([
        'status' => 401,
        'message' => 'Session ID is required.'
    ]);
    exit;
}

$conn = (new db_connect())->connect();

$session_stmt = $conn->prepare("SELECT user_id FROM sessions WHERE id = ? AND expiration > NOW()");
$session_stmt->bind_param("s", $session_id);
$session_stmt->execute();
$session_stmt->store_result();

if ($session_stmt->num_rows === 0) {
    http_response_code(401);
    echo json_encode([
        'status' => 401,
        'message' => 'Invalid or expired session.'
    ]);
    exit;
}

$session_stmt->bind_result($user_id);
$session_stmt->fetch();
$session_stmt->close();

$user_stmt = $conn->prepare("SELECT lrn FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($lrn);
$user_stmt->fetch();
$user_stmt->close();

if (!$lrn) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not found or missing LRN.'
    ]);
    exit;
}


// note:
$level_stmt = $conn->prepare("SELECT * FROM levels WHERE id = ?");
$level_stmt->bind_param("i", $level_id);
$level_stmt->execute();
$level_result = $level_stmt->get_result();
$level = $level_result->fetch_assoc();
$level_stmt->close();

if (!$level) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid level.'
    ]);
    exit;
}
$markahan = $level['level'];


$stmt = $conn->prepare("SELECT * FROM assessments WHERE level_id = ? LIMIT 1");
$stmt->bind_param("i", $level_id);
$stmt->execute();
$result = $stmt->get_result();
$assessment = $result->fetch_assoc();

if (!$assessment) {
    echo json_encode(['status' => 'success', 'data' => null]);
    exit;
}

$assessment_id = $assessment['id'];

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
    exit;
}
$check_stmt->close();


// note:
$total_aralin_stmt = $conn->prepare("SELECT COUNT(*) FROM aralin WHERE level_id = ?");
$total_aralin_stmt->bind_param("i", $level_id);
$total_aralin_stmt->execute();
$total_aralin_stmt->bind_result($total_aralin);
$total_aralin_stmt->fetch();
$total_aralin_stmt->close();

$done_aralin_stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM done_aralin AS da
    JOIN aralin AS a ON da.aralin_id = a.id
    WHERE a.level_id = ? AND da.user_id = ?
");
$done_aralin_stmt->bind_param("is", $level_id, $user_id);
$done_aralin_stmt->execute();
$done_aralin_stmt->bind_result($done_aralin);
$done_aralin_stmt->fetch();
$done_aralin_stmt->close();

if ($done_aralin < $total_aralin) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Please complete all lessons before taking this assessment.'
    ]);
    exit;
}

// $stmtMultipleChoice = $conn->prepare("
//     SELECT * FROM multiple_choices 
//     WHERE assessment_id = ? 
//     ORDER BY RAND() 
//     LIMIT 5
// ");
// $stmtMultipleChoice->bind_param("i", $assessment_id);
// $stmtMultipleChoice->execute();
// $multiResult = $stmtMultipleChoice->get_result();

// $multiple_choices = [];
// while ($row = $multiResult->fetch_assoc()) {
//     $multiple_choices[] = $row;
// }
// $stmtMultipleChoice->close();

// $stmtTorF = $conn->prepare("
//     SELECT * FROM true_or_false 
//     WHERE assessment_id = ? 
//     ORDER BY RAND() 
//     LIMIT 5
// ");
// $stmtTorF->bind_param("i", $assessment_id);
// $stmtTorF->execute();
// $tResult = $stmtTorF->get_result();

// $true_or_false = [];
// while ($row = $tResult->fetch_assoc()) {
//     $row['answer'] = (int)$row['answer'];
//     $true_or_false[] = $row;
// }
// $stmtTorF->close();

// $stmtIdent = $conn->prepare("
//     SELECT * FROM identifications 
//     WHERE assessment_id = ? 
//     ORDER BY RAND() 
//     LIMIT 5
// ");
// $stmtIdent->bind_param("i", $assessment_id);
// $stmtIdent->execute();
// $iResult = $stmtIdent->get_result();

// $identification = [];
// while ($row = $iResult->fetch_assoc()) {
//     $identification[] = $row;
// }
// $stmtIdent->close();

// $stmtJumbled = $conn->prepare("
//     SELECT * FROM jumbled_words 
//     WHERE assessment_id = ? 
//     ORDER BY RAND() 
//     LIMIT 5
// ");
// $stmtJumbled->bind_param("i", $assessment_id);
// $stmtJumbled->execute();
// $jResult = $stmtJumbled->get_result();

// $jumbled_words = [];
// while ($row = $jResult->fetch_assoc()) {
//     $jumbled_words[] = $row;
// }
// $stmtJumbled->close();

// $responseData = [
//     'assessment' => $assessment,
//     'multiple_choices' => $multiple_choices,
//     'true_or_false' => $true_or_false,
//     'identification' => $identification,
//     'jumbled_words' => $jumbled_words
// ];

// if ($markahan == 1) {
//     unset($responseData['jumbled_words']);
// } elseif ($markahan == 2) {
//     unset($responseData['identification']);
// } elseif ($markahan == 3) {
//     unset($responseData['true_or_false']);
// } elseif ($markahan == 4) {
//     unset($responseData['multiple_choices']);
// }

// echo json_encode([
//     'status' => 'success',
//     'data' => $responseData
// ]);

switch ($markahan) {
    case 1:
        $totalQuestions = 15;
        $excludeType = 'jumbled_words';
        break;
    case 2:
        $totalQuestions = 20;
        $excludeType = 'identification';
        break;
    case 3:
        $totalQuestions = 25;
        $excludeType = 'true_or_false';
        break;
    case 4:
        $totalQuestions = 30;
        $excludeType = 'multiple_choices';
        break;
    default:
        $totalQuestions = 15;
        $excludeType = 'jumbled_words';
}

$questionTables = [
    'multiple_choices' => 'multiple_choices',
    'true_or_false' => 'true_or_false',
    'identification' => 'identifications',
    'jumbled_words' => 'jumbled_words'
];

// Remove the excluded one
unset($questionTables[$excludeType]);

// Compute base limit and distribute extras fairly
$totalTypes = count($questionTables); // should be 3
$baseLimit = intdiv($totalQuestions, $totalTypes);
$remainder = $totalQuestions % $totalTypes;

// Assign limits per type
$limits = [];
$index = 0;
foreach ($questionTables as $key => $table) {
    $extra = ($index < $remainder) ? 1 : 0;
    $limits[$key] = $baseLimit + $extra;
    $index++;
}

// Fetch helper
function fetchQuestions($conn, $table, $assessment_id, $limit)
{
    $stmt = $conn->prepare("
        SELECT * FROM {$table}
        WHERE assessment_id = ?
        ORDER BY RAND()
        LIMIT ?
    ");
    $stmt->bind_param("ii", $assessment_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        if ($table === 'true_or_false') {
            $row['answer'] = (int)$row['answer'];
        }
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

// Fetch questions dynamically
$responseData = ['assessment' => $assessment];

foreach ($questionTables as $key => $table) {
    $responseData[$key] = fetchQuestions($conn, $table, $assessment_id, $limits[$key]);
}

echo json_encode([
    'status' => 'success',
    'data' => $responseData
]);
