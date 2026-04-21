<?php
include_once(__DIR__ . '/../../controller/LevelsController.php');

$requestType = $_POST['requestType'];

$controller = new LevelsController();

if ($requestType == "GetLevels") {
    $id = $_POST['auth_user_id'];
    $controller->GetLevels($id);
} else {
    http_response_code(400);
    echo "Invalid or missing requestType.";
}
