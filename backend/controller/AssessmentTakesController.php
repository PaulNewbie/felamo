<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once(__DIR__ . '/../db/db.php'); // Ensure path is correct
date_default_timezone_set('Asia/Manila');

// FIX 1: Renamed class to match the filename and API call
class AssessmentTakesController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function GetTakenAssessments($level_id, $filter)
    {
        // Force collation match on the atl.lrn join condition to fix the illegal mix error
        $sql = "
            SELECT 
                at.lrn, 
                at.points, 
                at.assessment_id, 
                at.created_at, 
                u.first_name, 
                u.last_name, 
                at.total,
                a.assessment_title, 
                COUNT(atl.id) AS total_attempts
            FROM assessment_results AS at 
            JOIN assessments AS a ON at.assessment_id = a.id
            JOIN aralin AS ar ON a.aralin_id = ar.id
            JOIN users AS u ON at.lrn = u.lrn
            LEFT JOIN assessment_attempt_logs AS atl ON at.assessment_id = atl.assessment_id AND at.lrn = atl.lrn COLLATE utf8mb4_general_ci
            WHERE ar.level_id = ?
        ";

        if ($filter === "PASSED") {
            $sql .= " AND at.points >= (at.total * 0.5)";
        } elseif ($filter === "FAILED") {
            $sql .= " AND at.points < (at.total * 0.5)";
        }

        $sql .= " GROUP BY at.lrn, at.assessment_id";

        $q = $this->conn->prepare($sql);

        if (!$q) {
            echo json_encode([
                'status' => 'error',
                'message' => 'SQL prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        $q->bind_param("i", $level_id);

        if ($q->execute()) {
            $result = $q->get_result();
            $taken_assessments = [];

            while ($row = $result->fetch_assoc()) {
                $row['student_name'] = $row['first_name'] . ' ' . $row['last_name'];
                $taken_assessments[] = $row;
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'success',
                'data' => $taken_assessments
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Execute failed: ' . $q->error
            ]);
        }

        $q->close();
    }
}
?>