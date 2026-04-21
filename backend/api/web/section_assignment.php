<?php
include_once(__DIR__ . '/../../controller/SectionController.php');

$requestType = $_POST['requestType'];

$controller = new SectionController();

if ($requestType == "GetAssignedSections") {
    $teacher_id = $_POST['teacher_id'];
    $controller->GetSectionsByTeacher($teacher_id);
} elseif ($requestType == "AssignSection") {
    $teacher_id = $_POST['teacher_id'];
    $section_name = $_POST['section_name'];

    $controller->AssignSectionToTeacher($teacher_id, $section_name);
} else {
    http_response_code(400);
    echo "Invalid or missing requestType.";
}
