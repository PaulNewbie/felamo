<?php
include_once(__DIR__ . '/../../controller/HomeController.php');

$requestType = $_POST['requestType'];

$controller = new HomeController();

if ($requestType == "LoadDashboard") {
    $isSuperAdmin = $_POST['is_super_admin'];

    if ($isSuperAdmin == "true") {
        $controller->LoadDashboard();
    } else {
        // is teacher
        $hidden_user_id = $_POST['hidden_user_id'];
        $controller->LoadTeacherDashboard($hidden_user_id);
    }
} else {
    http_response_code(400);
    echo "Invalid or missing requestType.";
}
