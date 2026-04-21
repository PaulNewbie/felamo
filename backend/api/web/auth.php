<?php
// 1. Force Disable Errors (Prevents warnings from breaking JSON)
ini_set('display_errors', 0);
error_reporting(0);

// 2. Start Output Buffering
ob_start();

// 3. Include Controller
include_once(__DIR__ . '/../../controller/AuthController.php');

// 4. Force Disable Errors AGAIN
ini_set('display_errors', 0);
error_reporting(0);

// 5. Initialize Controller & Request
$controller = new AuthController();
$requestType = $_POST['requestType'] ?? '';

// 6. CLEAR EVERYTHING buffered so far (Crucial Step)
ob_clean(); 

// 7. Set JSON Header
header('Content-Type: application/json');

if ($requestType == "Login") {
    $controller->Login($_POST);
    exit;
} 
elseif ($requestType == "GetProfileDetails") {
    $id = $_POST['auth_user_id'] ?? 0;
    $controller->GetUser2($id);
    exit;
} 
elseif ($requestType == "EditUser") {
    $id = $_POST['auth_user_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';
    $controller->UpdateUser($id, $name, $email, $newPassword);
    exit;
} 
elseif ($requestType == "UploadProfilePicture") {
    $id = $_POST['auth_user_id'] ?? 0;
    if (isset($_FILES['profile_picture'])) {
        $controller->UpdateProfilePicture($id, $_FILES['profile_picture']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No file received.']);
    }
    exit;
}
elseif ($requestType == "SendOTP") {
    $email = $_POST['email'] ?? '';
    $controller->SendForGotPasswordOtp($email);
    exit;
} 
elseif ($requestType == "LoginUsingOtp") {
    $email = $_POST['email'] ?? '';
    $otp = $_POST['otp'] ?? '';
    $controller->LoginUsingOtp($email, $otp);
    exit;
} 
else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing requestType']);
    exit;
}
// DO NOT ADD CLOSING PHP TAG HERE