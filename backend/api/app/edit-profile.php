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

$first_name = trim($input['first_name'] ?? '');
$middle_name = trim($input['middle_name'] ?? '');
$last_name = trim($input['last_name'] ?? '');
$lrn = trim($input['lrn'] ?? '');
$birth_date = trim($input['birth_date'] ?? '');
$gender = trim($input['gender'] ?? '');
$email = trim($input['email'] ?? '');

$errors = [];

if (empty($first_name)) $errors[] = "First name is required.";
if (empty($last_name)) $errors[] = "Last name is required.";
if (empty($lrn)) $errors[] = "LRN is required.";
if (!preg_match('/^\d{10,}$/', $lrn)) $errors[] = "LRN must be at least 10 digits.";
if (empty($birth_date)) $errors[] = "Birth date is required.";
if (empty($gender) || !in_array($gender, ['Lalaki', 'Babae'])) $errors[] = "Valid gender is required.";
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";


if (!empty($errors)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Validation failed',
        'errors' => $errors
    ]);
    exit;
}

$check_stmt = $conn->prepare("SELECT id FROM users WHERE (email = ? OR lrn = ?) AND id != ?");
$check_stmt->bind_param("ssi", $email, $lrn, $user_id);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo json_encode([
        'status' => 400,
        'message' => 'Email or LRN already in use by another user.'
    ]);
    exit;
}

$update_stmt = $conn->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, lrn = ?, birth_date = ?, gender = ?, email = ? WHERE id = ?");
$update_stmt->bind_param("sssssssi", $first_name, $middle_name, $last_name, $lrn, $birth_date, $gender, $email, $user_id);


if ($update_stmt->execute()) {
    echo json_encode([
        'status' => 200,
        'message' => 'Profile updated successfully.'
    ]);
} else {
    echo json_encode([
        'status' => 400,
        'message' => 'Failed to update profile.'
    ]);
    http_response_code(400);
}
