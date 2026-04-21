<?php
// File: backend/api/web/update_profile.php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include(__DIR__ . '/../../db/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// 1. Get POST Data
$user_id = $_POST['user_id'] ?? 0;
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$new_password = trim($_POST['new_password'] ?? '');

// 2. Validation
if (empty($user_id) || empty($first_name) || empty($last_name) || empty($email)) {
    echo json_encode(['status' => 'error', 'message' => 'First Name, Last Name, and Email are required.']);
    exit;
}

$conn = (new db_connect())->connect();

// 3. Check for Duplicate Email (excluding current user)
$check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$check->bind_param("si", $email, $user_id);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Email is already taken.']);
    exit;
}

// 4. Update Logic
if (!empty($new_password)) {
    // Update WITH Password
    $hashed_pw = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, password = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $first_name, $last_name, $email, $hashed_pw, $user_id);
} else {
    // Update WITHOUT Password
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
    $stmt->bind_param("sssi", $first_name, $last_name, $email, $user_id);
}

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
}
?>