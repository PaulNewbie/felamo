<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include(__DIR__ . '/../../db/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$user_id    = (int)($_POST['user_id'] ?? 0);
$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name']  ?? '');
$email      = trim($_POST['email']      ?? '');
$new_password = trim($_POST['new_password'] ?? '');

// --- Validation ---
if (empty($user_id)) {
    echo json_encode(['status' => 'error', 'message' => 'User ID is required.']);
    exit;
}

if (empty($first_name) || empty($last_name)) {
    echo json_encode(['status' => 'error', 'message' => 'First name and last name are required.']);
    exit;
}

// 1. Email format
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
    exit;
}

// 2. Password length if changing
if (!empty($new_password) && strlen($new_password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters.']);
    exit;
}

$conn = (new db_connect())->connect();

// 3. Check email not taken by another user
$check = $conn->prepare("SELECT id FROM web_users WHERE email = ? AND id != ? LIMIT 1");
$check->bind_param("si", $email, $user_id);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'That email is already used by another account.']);
    exit;
}
$check->close();

// --- Update ---
if (!empty($new_password)) {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        "UPDATE web_users SET first_name = ?, last_name = ?, email = ?, password = ? WHERE id = ?"
    );
    $stmt->bind_param("ssssi", $first_name, $last_name, $email, $hashed, $user_id);
} else {
    $stmt = $conn->prepare(
        "UPDATE web_users SET first_name = ?, last_name = ?, email = ? WHERE id = ?"
    );
    $stmt->bind_param("sssi", $first_name, $last_name, $email, $user_id);
}

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
}