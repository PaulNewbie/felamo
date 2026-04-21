<?php
// Disable error display to prevent text from breaking the HTML layout
ini_set('display_errors', 0);
error_reporting(E_ALL);

// CRITICAL FIX: Use include_once to prevent "Cannot redeclare class" crash
include_once(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class SectionController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    // --- For API (JSON response) ---
    public function GetSectionsByTeacher($teacher_id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM sections WHERE teacher_id = ?");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'SQL prepare failed: ' . $this->conn->error]);
            return;
        }

        $stmt->bind_param("i", $teacher_id);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $sections = [];

            while ($row = $result->fetch_assoc()) {
                $sections[] = $row;
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Sections retrieved successfully.',
                'data' => $sections
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Execute failed: ' . $stmt->error
            ]);
        }

        $stmt->close();
    }

    // --- For Dropdown (Returns MySQL Object) ---
    public function GetSectionsResult($teacher_id = null)
    {
        if ($teacher_id) {
            $stmt = $this->conn->prepare("SELECT * FROM sections WHERE teacher_id = ?");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            return $stmt->get_result();
        } else {
            // Super Admin gets ALL sections
            return $this->conn->query("SELECT * FROM sections");
        }
    }

    public function AssignSectionToTeacher($teacher_id, $section_name)
    {
        $stmt = $this->conn->prepare("INSERT INTO sections (teacher_id, section_name) VALUES (?, ?)");

        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'SQL prepare failed']);
            return;
        }

        $stmt->bind_param("is", $teacher_id, $section_name);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Section assigned successfully.',
                'section_id' => $stmt->insert_id
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Insert failed: ' . $stmt->error
            ]);
        }

        $stmt->close();
    }
}