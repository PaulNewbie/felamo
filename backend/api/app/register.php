<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include(__DIR__ . '/../../db/db.php');

$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestMethod === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);

    $first_name = trim($input['first_name'] ?? '');
    $middle_name = trim($input['middle_name'] ?? '');
    $last_name = trim($input['last_name'] ?? '');
    $lrn = trim($input['lrn'] ?? '');
    $birth_date = trim($input['birth_date'] ?? '');
    $gender = trim($input['gender'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';

    $errors = [];

    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (empty($lrn)) $errors[] = "LRN is required.";
    if (!preg_match('/^\d{10,}$/', $lrn)) $errors[] = "LRN must be at least 10 digits.";
    if (empty($birth_date)) $errors[] = "Birth date is required.";
    if (empty($gender) || !in_array($gender, ['Lalaki', 'Babae'])) $errors[] = "Valid gender is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    if (!empty($errors)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $errors
        ]);
        exit;
    }

    $conn = (new db_connect())->connect();

    $lrn_check_stmt = $conn->prepare("SELECT 1 FROM student_teacher_assignments WHERE student_lrn = ? LIMIT 1");
    $lrn_check_stmt->bind_param("s", $lrn);
    $lrn_check_stmt->execute();
    $lrn_check_stmt->store_result();

    if ($lrn_check_stmt->num_rows === 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'LRN is not assigned to any teacher / section.'
        ]);
        http_response_code(400);
        exit;
    }

    $lrn_check_stmt->close();

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR lrn = ?");
    $stmt->bind_param("ss", $email, $lrn);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode([
            'status' => 400,
            'message' => 'Email or LRN already exists.'
        ]);
        http_response_code(400);
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO users (first_name, middle_name, last_name, lrn, birth_date, gender, email, password, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
    $stmt->bind_param("ssssssss", $first_name, $middle_name, $last_name, $lrn, $birth_date, $gender, $email, $hashed_password);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $session_id = bin2hex(random_bytes(32));
        $expiration = date('Y-m-d H:i:s', strtotime('+7 days'));

        $session_stmt = $conn->prepare("INSERT INTO sessions (id, user_id, expiration) VALUES (?, ?, ?)");
        $session_stmt->bind_param("sis", $session_id, $user_id, $expiration);

        if ($session_stmt->execute()) {
            $teacher_info = null;

            $teacher_stmt = $conn->prepare("
                SELECT a.name, a.grade_level, s.section_name
                FROM student_teacher_assignments AS sta
                JOIN sections AS s ON sta.section_id = s.id
                JOIN admin AS a ON s.teacher_id = a.id
                WHERE sta.student_lrn = ?
                LIMIT 1
            ");

            if (!$teacher_stmt) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Teacher query prepare failed: ' . $conn->error
                ]);
                exit;
            }

            $teacher_stmt->bind_param("s", $lrn);
            $teacher_stmt->execute();
            $teacher_result = $teacher_stmt->get_result();

            if ($row = $teacher_result->fetch_assoc()) {
                $teacher_info = [
                    'name' => $row['name'],
                    'grade' => $row['grade_level'],
                    'section' => $row['section_name']
                ];
            }

            echo json_encode([
                'status' => 200,
                'message' => 'Registration successful.',
                'session' => [
                    'id' => $session_id,
                    'user_id' => $user_id,
                    'expires_at' => $expiration
                ],
                'teacher' => $teacher_info
            ]);
        } else {
            echo json_encode([
                'status' => 400,
                'message' => 'User registered, but failed to create session.'
            ]);
        }
        exit;
    } else {
        echo json_encode([
            'status' => 400,
            'message' => 'Failed to register: ' . $stmt->error
        ]);
        http_response_code(400);
        exit;
    }
} else {
    echo json_encode([
        'status' => 405,
        'message' => $requestMethod . ' method not allowed.'
    ]);
    http_response_code(405);
}
