<?php
error_reporting(0); // Hide warnings
ini_set('display_errors', 0);

include_once(__DIR__ . '/../../controller/AssessmentTakesController.php');

header('Content-Type: application/json');

$requestType = isset($_POST['requestType']) ? $_POST['requestType'] : '';

// Instantiating the class we just fixed above
$controller = new AssessmentTakesController(); 

if ($requestType === "GetTakenAssessments") {
    
    $id = isset($_POST['level_id']) ? $_POST['level_id'] : 0;
    $filter = isset($_POST['filter']) ? $_POST['filter'] : 'all'; 

    $controller->GetTakenAssessments($id, $filter);

} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid Request Type"]);
}
?>