<?php
// 1. SETUP
error_reporting(E_ALL);
ini_set('display_errors', 0); 
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

// 2. DATABASE CONNECTION
$servername = "localhost";
$username   = "u240756803_felamov3";     
$password   = "hehcE6-fotcab-viskaj";          
$dbname     = "u240756803_felamov3";

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed: ' . $e->getMessage()]);
    exit;
}

// ---------------------------------------------------------
// 3. MAIN LOGIC (TARGETING 'admin' TABLE)
// ---------------------------------------------------------
if (isset($_POST['requestType'])) {
    $requestType = $_POST['requestType'];

    try {
        // --- GET ALL TEACHERS ---
        if ($requestType === 'GetAllTeachers') {
            
            // We select from 'admin' table where role is 'teacher'
            $sql = "SELECT id, name, email, role, is_active FROM admin WHERE role = 'teacher' ORDER BY id DESC";
            $result = $conn->query($sql);
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
        }

        // --- INSERT TEACHER ---
        elseif ($requestType === 'InsertTeacher') {
            $name     = trim($_POST['name'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (empty($name) || empty($email) || empty($password)) {
                throw new Exception("All fields are required.");
            }

            // Check Duplicate in ADMIN table
            $check = $conn->prepare("SELECT id FROM admin WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception("Email already exists in Admin/Teacher list.");
            }
            $check->close();

            // Insert into ADMIN table
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'teacher'; 
            $isActive = 1;

            // The 'admin' table has a 'name' column, so we use it directly
            $stmt = $conn->prepare("INSERT INTO admin (name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $name, $email, $hash, $role, $isActive);
            
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Teacher Added Successfully!']);
            } else {
                throw new Exception("Insert Failed: " . $stmt->error);
            }
            $stmt->close();
        } 
        else {
            throw new Exception("Invalid Request Type");
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No Request Type']);
}
$conn->close();
?>