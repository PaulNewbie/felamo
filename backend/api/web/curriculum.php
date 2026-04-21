<?php
include_once(__DIR__ . '/../../controller/CurriculumController.php');

if (!isset($_POST['requestType'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'requestType is missing',
        'raw_post' => $_POST,
        'raw_files' => $_FILES
    ]);
    exit;
}

$requestType = $_POST['requestType'];

$controller = new CurriculumController();

if ($requestType == "GetCurriculum") {
    $controller->GetCurriculum();
} elseif ($requestType == "EditCurriculum") {
    $curriculum = $_POST['curriculum'];
    $controller->EditCurriculum($curriculum);
} else {
    http_response_code(400);
    echo "Invalid or missing requestType.";
}
