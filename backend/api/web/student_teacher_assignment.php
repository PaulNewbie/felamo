<?php
include_once(__DIR__ . '/../../controller/StudentTeacherAssignmentsController.php');

$requestType = $_POST['requestType'];

$controller = new StudentTeacherAssignmentsController();

if ($requestType == "GetAssignedStudents") {
    $section_id = $_POST['section_id'];
    $controller->GetAssignedStudents($section_id);
} elseif ($requestType == "AssignStudent") {
    // $lrn = $_POST['lrn'];
    // $section_id = $_POST['section_id'];

    $controller->AssignStudent($_POST);
} elseif ($requestType == "ImportStudent") {
    // $lrns = $_POST['lrnArray'];
    $students = $_POST['students'];
    $section_id = $_POST['section_id'];

    $controller->ImportStudents($students, $section_id);
} else {
    http_response_code(400);
    echo "Invalid or missing requestType.";
}
