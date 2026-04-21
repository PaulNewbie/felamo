<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

date_default_timezone_set('Asia/Manila');

include(__DIR__ . '/../../db/db.php');
require_once(__DIR__ . '/../../controller/SendEmailController.php');

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
$email = trim($input['email'] ?? '');

if (empty($email)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Validation failed.',
        'errors' => ['Email is required.']
    ]);
    exit;
}

$conn = (new db_connect())->connect();

$stmt = $conn->prepare("
    SELECT users.*, avatars.filename AS avatar_file_name 
    FROM users 
    LEFT JOIN avatars ON users.avatar = avatars.id 
    WHERE users.email = ?
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if (!$user = $result->fetch_assoc()) {
    echo json_encode([
        'status' => 404,
        'message' => 'No user found with that email.'
    ]);
    exit;
}

$otp = rand(100000, 999999);
$expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$userType = "user";
$otpType  = "forgot_password";
$otpStr   = (string) $otp;

$insertOtpSql = "INSERT INTO user_otps (email, user_type, otp_type, otp, expiration_date)
                 VALUES (?, ?, ?, ?, ?)";
$insertOtpStmt = $conn->prepare($insertOtpSql);
$insertOtpStmt->bind_param("sssss", $email, $userType, $otpType, $otpStr, $expires);

$otpInserted = $insertOtpStmt->execute();

$SendEmail = new SendEmailController();
$sendEmailResult = $SendEmail->SendForgotPasswordCode($email, $otp, $user['first_name'], $user['last_name']);

if ($otpInserted && $sendEmailResult === "200") {
    echo json_encode([
        'status' => 200,
        'message' => 'OTP has been sent to your email.',
        'dev_action' => 'go to otp verification, keep the input email for next API'
    ]);
} else {
    echo json_encode([
        'status' => 500,
        'message' => 'Failed to send OTP. Please try again.'
    ]);
}
