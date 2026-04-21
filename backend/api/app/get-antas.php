<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestMethod !== "POST") {
    http_response_code(405);
    echo json_encode([
        'status' => 405,
        'message' => "$requestMethod method not allowed."
    ]);
    exit;
}

/*
note: update markahan is_done if passed the quiz
note: per video add is_done.

note: after submiting if no passed reset lang yung antas na is_done = false;
*/

$input = json_decode(file_get_contents("php://input"), true);
$session_id = trim($input['session_id'] ?? '');

if (empty($session_id)) {
    http_response_code(401);
    echo json_encode([
        'status' => 401,
        'message' => 'Session ID is required.'
    ]);
    exit;
}

$conn = (new db_connect())->connect();

// Validate session
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

// Get student's LRN
$lrn_stmt = $conn->prepare("SELECT lrn FROM users WHERE id = ?");
$lrn_stmt->bind_param("i", $user_id);
$lrn_stmt->execute();
$lrn_stmt->bind_result($student_lrn);
$lrn_stmt->fetch();
$lrn_stmt->close();

if (!$student_lrn) {
    http_response_code(404);
    echo json_encode([
        'status' => 404,
        'message' => 'Student not found.'
    ]);
    exit;
}

// Get teacher ID
$assign_stmt = $conn->prepare("SELECT s.teacher_id FROM student_teacher_assignments AS sta
JOIN sections AS s ON sta.section_id = s.id WHERE sta.student_lrn = ?");
$assign_stmt->bind_param("s", $student_lrn);
$assign_stmt->execute();
$assign_stmt->bind_result($teacher_id);
$assign_stmt->fetch();
$assign_stmt->close();

if (!$teacher_id) {
    http_response_code(404);
    echo json_encode([
        'status' => 404,
        'message' => 'No teacher assigned to this student.'
    ]);
    exit;
}

// Fetch all levels with aralin for this teacher
$join_stmt = $conn->prepare("
    SELECT 
        l.id AS level_id,
        l.teacher_id,
        l.level,
        a.id AS aralin_id,
        a.aralin_no,
        a.title AS aralin_title,
        a.summary,
        a.details,
        a.attachment_filename
    FROM levels l
    LEFT JOIN aralin a ON a.level_id = l.id
    WHERE l.teacher_id = ?
    ORDER BY l.level ASC, a.aralin_no ASC
");
$join_stmt->bind_param("i", $teacher_id);
$join_stmt->execute();
$result = $join_stmt->get_result();

$levels = [];

while ($row = $result->fetch_assoc()) {
    $level_id = $row['level_id'];

    if (!isset($levels[$level_id])) {
        $title = '';
        switch ($row['level']) {
            // case 1: $title = 'Panimulang Antas'; break;
            // case 2: $title = 'Madaling Antas'; break;
            // case 3: $title = 'Mahirap na Antas'; break;
            // case 4: $title = 'Huling Antas'; break;
            case 1: $title = 'Unang markahan'; break;
            case 2: $title = 'Pangalawang markahan'; break;
            case 3: $title = 'Pangatlong markahan'; break;
            case 4: $title = 'Ika apat na markahahn'; break;
        }

        $check_done_stmt = $conn->prepare("
            SELECT 1 FROM assessment_takes AS at 
            JOIN assessments AS a ON at.assessment_id = a.id
            WHERE a.level_id = ? AND at.lrn = ?
            LIMIT 1
        ");

        $check_done_stmt->bind_param("is", $level_id, $student_lrn);
        $check_done_stmt->execute();
        $check_done_stmt->store_result();
        $is_done = $check_done_stmt->num_rows > 0;
        $check_done_stmt->close();

        $levels[$level_id] = [
            'id' => $level_id,
            'teacher_id' => $row['teacher_id'],
            'level' => $row['level'],
            'title' => $title,
            'is_done' => $is_done,
            'aralins' => []
        ];
    }

    if (!empty($row['aralin_id'])) {
        $levels[$level_id]['aralins'][] = [
            'id' => $row['aralin_id'],
            'aralin_no' => $row['aralin_no'],
            'title' => $row['aralin_title'],
            'summary' => $row['summary'],
            'details' => $row['details'],
            'attachment_filename' => $row['attachment_filename']
        ];
    }
}

echo json_encode([
    'status' => 'success',
    'data' => array_values($levels)
]);
exit;
