<?php
$servername = "localhost";
$username   = "u240756803_felamov3";     
$password   = "hehcE6-fotcab-viskaj";          
$dbname     = "u240756803_felamov3";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>