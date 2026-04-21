<?php
include(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class NotificationsController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function GetCreatedNotification($created_by)
    {
        $q = $this->conn->prepare("
        SELECT n.title, n.description, s.section_name
        FROM `notifications` AS n
        JOIN sections AS s ON n.section_id = s.id
        WHERE n.created_by = ?");

        if (!$q) {
            echo json_encode([
                'status' => 'error',
                'message' => 'SQL prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        $q->bind_param("i", $created_by);

        if ($q->execute()) {
            $result = $q->get_result();
            $notification = [];

            while ($row = $result->fetch_assoc()) {
                $notification[] = $row;
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'success',
                'data' => $notification
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Execute failed: ' . $q->error
            ]);
        }

        $q->close();
    }

    public function CreateNotification($title, $description, $created_by, $section_id)
    {
        $q = $this->conn->prepare("
        INSERT INTO notifications (title, description, section_id, created_by)
        VALUES (?, ?, ?, ?)
    ");

        if (!$q) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Prepare failed: ' . $this->conn->error
            ]);
            return;
        }

        $q->bind_param("ssii", $title, $description, $section_id, $created_by);

        if ($q->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => "Notification created.",
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => "Execute failed: " . $q->error,
            ]);
        }

        $q->close();
    }
}
