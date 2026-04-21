<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class StudentTeacherAssignmentsController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function test()
    {
        echo "login";
    }

    public function GetAssignedStudents($section_id)
    {
        $q = $this->conn->prepare("
        SELECT sta.*, u.first_name, u.middle_name, u.last_name, u.birth_date, u.gender, u.email AS student_email, u.contact_no
        FROM `student_teacher_assignments` AS sta 
        LEFT JOIN `users` AS u ON sta.student_lrn = u.lrn
        WHERE sta.section_id = ?
    ");

        if (!$q) {
            echo json_encode([
                'status' => 'error',
                'message' => 'SQL prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        $q->bind_param("i", $section_id);

        if ($q->execute()) {
            $result = $q->get_result();
            $students = [];

            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'success',
                'data' => $students
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Execute failed: ' . $q->error
            ]);
        }

        $q->close();
    }


    // public function AssignStudent($lrn, $section_id)
    // {
    //     $stmt = $this->conn->prepare("
    //         INSERT INTO `student_teacher_assignments` (`section_id`, `student_lrn`)
    //         VALUES (?, ?)
    //     ");

    //     if (!$stmt) {
    //         echo json_encode([
    //             'status' => 'error',
    //             'message' => 'Failed to prepare statement.'
    //         ]);
    //         return;
    //     }

    //     $stmt->bind_param("is", $section_id, $lrn);

    //     if ($stmt->execute()) {
    //         echo json_encode([
    //             'status' => 'success',
    //             'message' => 'Student assigned successfully.',
    //         ]);
    //     } else {
    //         echo json_encode([
    //             'status' => 'error',
    //             'message' => 'Insert failed: ' . $stmt->error
    //         ]);
    //     }

    //     $stmt->close();
    // }


    public function AssignStudent($post)
    {
        $section_id = $post['section_id'] ?? null;
        $lrn = trim($post['lrn'] ?? '');
        $first_name = trim($post['first_name'] ?? '');
        $middle_name = trim($post['middle_name'] ?? '');
        $last_name = trim($post['last_name'] ?? '');
        $birth_date = trim($post['birth_date'] ?? '');
        $gender = trim($post['gender'] ?? '');
        $contact_no = trim($post['contact_no'] ?? '');
        $email = trim($post['email'] ?? '');
        $password = $post['password'] ?? '';

        $errors = [];

        if (empty($first_name)) $errors[] = "First name is required.";
        if (empty($last_name)) $errors[] = "Last name is required.";

        // if (empty($lrn) || !preg_match('/^\d{12,}$/', $lrn)) $errors[] = "LRN must be at least 12 digits.";
        if (empty($lrn) || !preg_match('/^\d{12}$/', $lrn)) {
            $errors[] = "LRN must be exactly 12 digits.";
        }

        if (empty($birth_date)) $errors[] = "Birth date is required.";
        if (empty($gender) || !in_array($gender, ['Lalaki', 'Babae'])) $errors[] = "Valid gender is required.";
        // if (empty($contact_no) || !filter_var($contact_no, FILTER_VALIDATE_EMAIL)) $errors[] = "Contact no is required.";

        if (empty($contact_no) || !ctype_digit($contact_no) || strlen($contact_no) !== 11) {
            $errors[] = "Contact no must be exactly 11 digits.";
        }        

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
        if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
        if (empty($section_id)) $errors[] = "Section ID is required.";

        if (!empty($errors)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $errors
            ]);
            return;
        }

        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ? OR lrn = ?");
        $stmt->bind_param("ss", $email, $lrn);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Email or LRN already exists.'
            ]);
            return;
        }

        $stmt->close();

        $stmt = $this->conn->prepare("SELECT 1 FROM student_teacher_assignments WHERE section_id = ? AND student_lrn = ?");
        $stmt->bind_param("is", $section_id, $lrn);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Student is already assigned to this section.'
            ]);
            return;
        }

        $stmt->close();

        $stmt = $this->conn->prepare("
        INSERT INTO `student_teacher_assignments` (`section_id`, `student_lrn`)
        VALUES (?, ?)
    ");

        if (!$stmt) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to prepare assignment statement.'
            ]);
            return;
        }

        $stmt->bind_param("is", $section_id, $lrn);

        if (!$stmt->execute()) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Insert failed: ' . $stmt->error
            ]);
            return;
        }

        $stmt->close();

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->conn->prepare("
        INSERT INTO users (first_name, middle_name, last_name, lrn, birth_date, gender, email, contact_no, password, points)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");
        $stmt->bind_param("sssssssss", $first_name, $middle_name, $last_name, $lrn, $birth_date, $gender, $email, $contact_no, $hashed_password);

        if (!$stmt->execute()) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to register user: ' . $stmt->error
            ]);
            return;
        }

        $user_id = $stmt->insert_id;
        $stmt->close();


        echo json_encode([
            'status' => 'success',
            'message' => 'Student assigned successfully.',
        ]);
    }


    public function ImportLrns($lrnArray, $section_id)
    {
        $successCount = 0;
        $skippedCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($lrnArray as $lrn) {
            $checkStmt = $this->conn->prepare("
            SELECT 1 FROM `student_teacher_assignments`
            WHERE `student_lrn` = ?
        ");

            if (!$checkStmt) {
                $failCount++;
                $errors[] = "Failed to prepare SELECT for LRN $lrn";
                continue;
            }

            $checkStmt->bind_param("s", $lrn);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                $skippedCount++;
                $checkStmt->close();
                continue;
            }

            $checkStmt->close();

            $insertStmt = $this->conn->prepare("
            INSERT INTO `student_teacher_assignments` (`section_id`, `student_lrn`)
            VALUES (?, ?)
        ");

            if (!$insertStmt) {
                $failCount++;
                $errors[] = "Failed to prepare INSERT for LRN $lrn";
                continue;
            }

            $insertStmt->bind_param("is", $section_id, $lrn);

            if ($insertStmt->execute()) {
                $successCount++;
            } else {
                $failCount++;
                $errors[] = "Insert failed for LRN $lrn: " . $insertStmt->error;
            }

            $insertStmt->close();
        }

        echo json_encode([
            'status' => 'success',
            'message' => "$successCount assigned, $skippedCount skipped (already exists), $failCount failed.",
            'errors' => $errors
        ]);
    }

    public function ImportStudents($students, $section_id)
    {
        $successCount = 0;
        $skippedCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($students as $index => $student) {
            $lrn = trim($student['lrn'] ?? '');
            $first_name = trim($student['first_name'] ?? '');
            $middle_name = trim($student['middle_name'] ?? '');
            $last_name = trim($student['last_name'] ?? '');
            $birth_date = trim($student['birth_date'] ?? '');
            $gender = trim($student['gender'] ?? '');
            $email = trim($student['email'] ?? '');
            $password = $student['password'] ?? '';

            $studentErrors = [];

            if (empty($first_name)) $studentErrors[] = "First name is required.";
            if (empty($last_name)) $studentErrors[] = "Last name is required.";
            if (empty($lrn) || !preg_match('/^\d{12,}$/', $lrn)) $studentErrors[] = "LRN must be at least 12 digits.";
            if (empty($birth_date)) $studentErrors[] = "Birth date is required.";
            if (empty($gender) || !in_array($gender, ['Lalaki', 'Babae'])) $studentErrors[] = "Valid gender is required.";
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $studentErrors[] = "Valid email is required.";
            if (empty($password) || strlen($password) < 6) $studentErrors[] = "Password must be at least 6 characters.";

            if (!empty($studentErrors)) {
                $failCount++;
                $errors[] = [
                    'lrn' => $lrn,
                    'index' => $index,
                    'errors' => $studentErrors
                ];
                continue;
            }

            $checkUserStmt = $this->conn->prepare("SELECT id FROM users WHERE email = ? OR lrn = ?");
            $checkUserStmt->bind_param("ss", $email, $lrn);
            $checkUserStmt->execute();
            $checkUserStmt->store_result();

            if ($checkUserStmt->num_rows > 0) {
                $skippedCount++;
                $errors[] = [
                    'lrn' => $lrn,
                    'index' => $index,
                    'errors' => ["Email or LRN already exists."]
                ];
                $checkUserStmt->close();
                continue;
            }

            $checkUserStmt->close();

            $checkAssignStmt = $this->conn->prepare("SELECT 1 FROM student_teacher_assignments WHERE section_id = ? AND student_lrn = ?");
            $checkAssignStmt->bind_param("is", $section_id, $lrn);
            $checkAssignStmt->execute();
            $checkAssignStmt->store_result();

            if ($checkAssignStmt->num_rows > 0) {
                $skippedCount++;
                $errors[] = [
                    'lrn' => $lrn,
                    'index' => $index,
                    'errors' => ["Already assigned to section."]
                ];
                $checkAssignStmt->close();
                continue;
            }

            $checkAssignStmt->close();

            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $insertUserStmt = $this->conn->prepare("
            INSERT INTO users (first_name, middle_name, last_name, lrn, birth_date, gender, email, password, points)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
            $insertUserStmt->bind_param("ssssssss", $first_name, $middle_name, $last_name, $lrn, $birth_date, $gender, $email, $hashed_password);

            if (!$insertUserStmt->execute()) {
                $failCount++;
                $errors[] = [
                    'lrn' => $lrn,
                    'index' => $index,
                    'errors' => ["User insert failed: " . $insertUserStmt->error]
                ];
                $insertUserStmt->close();
                continue;
            }

            $insertUserStmt->close();

            $insertAssignStmt = $this->conn->prepare("INSERT INTO student_teacher_assignments (section_id, student_lrn) VALUES (?, ?)");
            $insertAssignStmt->bind_param("is", $section_id, $lrn);

            if (!$insertAssignStmt->execute()) {
                $failCount++;
                $errors[] = [
                    'lrn' => $lrn,
                    'index' => $index,
                    'errors' => ["Assignment insert failed: " . $insertAssignStmt->error]
                ];
                $insertAssignStmt->close();
                continue;
            }

            $insertAssignStmt->close();
            $successCount++;
        }

        echo json_encode([
            'status' => 'done',
            'message' => "$successCount imported, $skippedCount skipped, $failCount failed.",
            'errors' => $errors
        ]);
    }
}
