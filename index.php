<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

include('backend/controller/TestController.php');

$testDb = new TestController();

echo $testDb->test();

if (isset($_SESSION['id'])) {
    header("Location: pages/home.php");
    exit;
} else {
    // go to login page
    header("Location: login.php");
    exit;
}

exit;
