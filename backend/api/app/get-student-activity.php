
<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    http_response_code(405);
    echo json_encode(['status' => 405, 'message' => 'POST method required.']);
    exit;
}

$input      = json_decode(file_get_contents("php://input"), true);
$session_id = trim($input['session_id'] ?? '');

if (empty($session_id)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Session ID is required.']);
    exit;
}

$conn = (new db_connect())->connect();

// 1. Resolve session → user_id
$sess = $conn->prepare("SELECT user_id FROM sessions WHERE id = ? AND expiration > NOW()");
$sess->bind_param("s", $session_id);
$sess->execute();
$sess_row = $sess->get_result()->fetch_assoc();
$sess->close();

if (!$sess_row) {
    http_response_code(401);
    echo json_encode(['status' => 401, 'message' => 'Invalid or expired session.']);
    exit;
}
$user_id = (int)$sess_row['user_id'];

// 2. Resolve user_id → lrn
$uq = $conn->prepare("SELECT lrn FROM users WHERE id = ?");
$uq->bind_param("i", $user_id);
$uq->execute();
$lrn = $uq->get_result()->fetch_assoc()['lrn'] ?? null;
$uq->close();

if (!$lrn) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit;
}

// 3. Aggregate all activity types into one unified feed
$sql = "
    SELECT
        'login'       AS activity_type,
        'Nag-login'   AS activity_label,
        CASE type
            WHEN '7th streak login' THEN 'Nakumpleto ang 7-araw na streak!'
            ELSE 'Matagumpay na nag-login ngayon'
        END           AS activity_detail,
        points        AS points_earned,
        CAST(date AS DATETIME) AS activity_date
    FROM daily_login
    WHERE user_id = ?

    UNION ALL

    SELECT
        'video'                    AS activity_type,
        'Nanood ng Bidyo'          AS activity_label,
        CONCAT('Aralin ', ar.aralin_no, ': ', ar.aralin_title) AS activity_detail,
        50                         AS points_earned,
        sap.completed_at           AS activity_date
    FROM student_aralin_progress AS sap
    JOIN aralin AS ar ON sap.aralin_id = ar.id
    WHERE sap.user_id = ?
      AND sap.video_reward_claimed = 1

    UNION ALL

    SELECT
        'quiz'                              AS activity_type,
        'Pumasa sa Pagsusulit'              AS activity_label,
        CONCAT(
            a.assessment_title,
            ' — ', atr.points, '/', atr.total, ' (',
            ROUND((atr.points / atr.total) * 100), '%)'
        )                                   AS activity_detail,
        35                                  AS points_earned,
        atr.created_at                      AS activity_date
    FROM assessment_results AS atr
    JOIN assessments AS a  ON atr.assessment_id = a.id
    WHERE atr.lrn        = ?
      AND atr.is_completed = 1

    ORDER BY activity_date DESC
    LIMIT 100
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Query prepare failed: ' . $conn->error]);
    exit;
}

// Three separate bind params — one per UNION block
$stmt->bind_param("iss", $user_id, $user_id, $lrn);
$stmt->execute();
$result = $stmt->get_result();

$activities = [];
while ($row = $result->fetch_assoc()) {
    $activities[] = $row;
}
$stmt->close();

echo json_encode([
    'status' => 'success',
    'data'   => $activities,
    'total'  => count($activities),
]);
exit;