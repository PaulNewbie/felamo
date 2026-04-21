<?php
// backend/controller/StudentsController.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

include_once(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class StudentsController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    // --- GET STUDENTS ---
    public function GetStudents($user_id, $isSuperAdmin, $sectionId)
    {
        $hasSectionFilter = !empty($sectionId);
        $sql = "";
        $types = "";
        $params = [];

        // 1. BUILD THE QUERY
        $sql = "SELECT u.*, sta.teacher_id, sta.student_lrn, s.section_name
                FROM student_teacher_assignments AS sta
                LEFT JOIN users AS u ON sta.student_lrn = u.lrn
                LEFT JOIN sections AS s ON sta.section_id = s.id";

        if ($hasSectionFilter) {
            // If viewing a specific section, we ONLY need to filter by Section ID.
            // This works for both Super Admin and Teacher.
            $sql .= " WHERE s.id = ?";
            $types .= "i";
            $params[] = $sectionId;
        } 
        else {
            // If NO section is selected (General View)
            if ($isSuperAdmin === "true") {
                // Super Admin sees ALL students (No WHERE clause needed)
            } else {
                // Teacher sees ONLY their students
                $sql .= " WHERE s.teacher_id = ?";
                $types .= "i";
                $params[] = $user_id;
            }
        }

        // 2. PREPARE & EXECUTE
        $q = $this->conn->prepare($sql);
        
        if (!$q) {
            echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $this->conn->error]);
            return;
        }

        // Bind parameters dynamically if any exist
        if (!empty($params)) {
            $q->bind_param($types, ...$params);
        }

        if ($q->execute()) {
            $result = $q->get_result();
            $students = [];
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $students]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Execute Error: ' . $q->error]);
        }
        $q->close();
    }

    // --- INSERT STUDENT ---
    public function InsertStudent($lrn, $fname, $mname, $lname, $bdate, $gender, $contact, $email, $sectionId)
    {
        // 1. Get Teacher ID
        $qSection = $this->conn->prepare("SELECT teacher_id FROM sections WHERE id = ?");
        $qSection->bind_param("i", $sectionId);
        $qSection->execute();
        $res = $qSection->get_result();
        
        if ($res->num_rows === 0) return ['status' => 'error', 'message' => 'Section not found'];
        $teacherId = $res->fetch_assoc()['teacher_id'];
        $qSection->close();

        // 2. Check Exists
        $qCheck = $this->conn->prepare("SELECT id FROM users WHERE lrn = ?");
        $qCheck->bind_param("s", $lrn);
        $qCheck->execute();
        if ($qCheck->get_result()->num_rows > 0) return ['status' => 'error', 'message' => 'LRN already exists'];
        $qCheck->close();

        // 3. Insert User
        $password = password_hash($lrn, PASSWORD_DEFAULT);
        $status = 1;

        $stmt = $this->conn->prepare("INSERT INTO users (lrn, first_name, middle_name, last_name, birth_date, gender, contact_no, email, password, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) return ['status' => 'error', 'message' => 'Prepare Error: ' . $this->conn->error];

        $stmt->bind_param("sssssssssi", $lrn, $fname, $mname, $lname, $bdate, $gender, $contact, $email, $password, $status);

        if ($stmt->execute()) {
            $stmt->close();
            // 4. Assign
            $stmtAssign = $this->conn->prepare("INSERT INTO student_teacher_assignments (student_lrn, section_id, teacher_id) VALUES (?, ?, ?)");
            $stmtAssign->bind_param("sii", $lrn, $sectionId, $teacherId);
            $stmtAssign->execute();
            return ['status' => 'success', 'message' => 'Student Added!'];
        } else {
            return ['status' => 'error', 'message' => 'DB Error: ' . $stmt->error];
        }
    }
}
?>