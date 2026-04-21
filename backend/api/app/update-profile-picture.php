<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 405,
        'message' => 'Method Not Allowed'
    ]);
    exit;
}

$session_id = $_POST['session_id'] ?? '';

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

if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No file uploaded or upload error.'
    ]);
    exit;
}

// $uploadDir = __DIR__ . '/../../uploads/profile_pictures/';
// $uploadDir = __DIR__ . '../../storage/profile-pictures';
$uploadDir = __DIR__ . '/../../storage/profile-pictures/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$originalName = basename($_FILES['profile_picture']['name']);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

if (!in_array($extension, $allowedExtensions)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Only JPG, JPEG, and PNG files are allowed.'
    ]);
    exit;
}

$filename = uniqid('profile_') . '.' . $extension;
$targetPath = $uploadDir . $filename;
$relativePath = 'backend/storage/profile-pictures/' . $filename;

if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to move uploaded file.'
    ]);
    exit;
}

$update_stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
$update_stmt->bind_param("si", $relativePath, $user_id);

if ($update_stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Profile picture updated.',
        'profile_picture' => $relativePath
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database update failed.'
    ]);
}
