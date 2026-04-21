<?php
include_once(__DIR__ . '/../../controller/LeaderBoardsController.php');

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

$controller = new LeaderBoardsController();

if ($requestType == "GetLeaderBoards") {
    $teacher_id = $_POST['teacher_id'];
    $section_id = $_POST['section_id'];
    $controller->GetLeaderBoards($teacher_id, $section_id);
} else {
    http_response_code(400);
    echo "Invalid or missing requestType.";
}
