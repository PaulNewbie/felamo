<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

require_once(__DIR__ . '/../../controller/SendEmailController.php');

$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestMethod === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);

    $email = trim($input['email'] ?? '');
    $lrn = trim($input['lrn'] ?? '');
    $first_name = trim($input['first_name'] ?? '');
    $last_name = trim($input['last_name'] ?? '');

    if (empty($lrn) || empty($email) || empty($first_name) || empty($last_name)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Email, LRN, first name, and last name are required.'
        ]);
        exit;
    }

    $conn = (new db_connect())->connect();

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR lrn = ?");
    $stmt->bind_param("ss", $email, $lrn);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode([
            'status' => 400,
            'message' => 'Email or LRN already exists.'
        ]);
        http_response_code(400);
        exit;
    }

    $otp = rand(100000, 999999);

    $SendEmail = new SendEmailController();
    ob_start();
    $SendEmail->SendCode($email, $otp, $first_name, $last_name);
    $result = ob_get_clean();

    if ($result === "200") {
        echo json_encode([
            'status' => 'success',
            'message' => 'OTP has been sent to your email.',
            'otp' => $otp
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to send OTP. Please try again later.'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => $requestMethod . ' method not allowed.'
    ]);
    http_response_code(405);
}
