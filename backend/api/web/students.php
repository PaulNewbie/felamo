<?php
// 1. Debugging: Show errors clearly
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Define the path to the controller
$controllerPath = __DIR__ . '/../../controller/StudentsController.php';

// 3. Check if the file actually exists before including it
if (!file_exists($controllerPath)) {
    // If not found, kill the script and show where it looked
    die(json_encode([
        'status' => 'error', 
        'message' => 'File not found! Looking for: ' . realpath(__DIR__ . '/../../') . '/controller/StudentsController.php'
    ]));
}

require_once($controllerPath);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

// 4. Check Request Type
if (!isset($_POST['requestType'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'requestType is missing']);
    exit;
}

$requestType = $_POST['requestType'];

// 5. Check if Class Exists
if (!class_exists('StudentsController')) {
    die(json_encode([
        'status' => 'error', 
        'message' => 'Class "StudentsController" not found inside StudentsController.php. Please check the class name.'
    ]));
}

$controller = new StudentsController();

if ($requestType == "GetStudents") {
    $auth_user_id = $_POST['auth_user_id'] ?? null;
    $is_super_admin = $_POST['is_super_admin'] ?? null;
    $section_id = $_POST['section_id'] ?? null;
    
    $controller->GetStudents($auth_user_id, $is_super_admin, $section_id);
} 
elseif ($requestType == "GetStudentsBySection") {
    $section_id = $_POST['section_id'];
    $controller->GetStudents(null, "false", $section_id);
}
elseif ($requestType == "InsertStudent") {
    $lrn = $_POST['lrn'] ?? '';
    $fname = $_POST['first_name'] ?? '';
    $mname = $_POST['middle_name'] ?? '';
    $lname = $_POST['last_name'] ?? '';
    $bdate = $_POST['birth_date'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $contact = $_POST['contact_no'] ?? '';
    $email = $_POST['email'] ?? '';
    $sectionId = $_POST['section_id'] ?? '';

    $result = $controller->InsertStudent($lrn, $fname, $mname, $lname, $bdate, $gender, $contact, $email, $sectionId);
    echo json_encode($result);
} 
else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Type: ' . $requestType]);
}
?>