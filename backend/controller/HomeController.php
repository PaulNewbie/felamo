<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include(__DIR__ . '/../db/db.php');
date_default_timezone_set('Asia/Manila');

class HomeController extends db_connect
{
    public function __construct()
    {
        $this->connect();
    }

    public function test()
    {
        echo "login";
    }

    public function LoadDashboard()
    {

        // video uploaded per level
        $vidUploadedCountStmt = $this->conn->prepare("
        SELECT l.level, COUNT(a.id) AS video_count
        FROM `levels` AS l
        LEFT JOIN `aralin` AS a ON a.level_id = l.id
        GROUP BY l.level
        ORDER BY l.level ASC
    ");

        $vidUploadedCountStmt->execute();
        $vidUploadedCountResult = $vidUploadedCountStmt->get_result();

        $vidUploadedCountArray = [];
        while ($row = $vidUploadedCountResult->fetch_assoc()) {
            $vidUploadedCountArray[] = $row;
        }
        // 



        // number of resigtered users count
        $registeredUsersCountStmt = $this->conn->prepare("
        SELECT COUNT(*) AS count FROM `users`");

        $registeredUsersCountStmt->execute();
        $registeredUsersCountResult = $registeredUsersCountStmt->get_result();
        $registeredUsersCount = $registeredUsersCountResult->fetch_assoc();

        // number of registers users count (web)
        $registeredWebUsersCountStmt = $this->conn->prepare("
        SELECT COUNT(*) AS count FROM `admin`");

        $registeredWebUsersCountStmt->execute();
        $registeredWebUsersCountResult = $registeredWebUsersCountStmt->get_result();
        $registeredWebUsersCount = $registeredWebUsersCountResult->fetch_assoc();

        echo json_encode([
            'status' => 'success',
            'message' => 'Dashboard Loaded',
            'data' => [
                "vid_uploaded_count" => $vidUploadedCountArray,
                "users_count" => $registeredUsersCount,
                "web_users_count" => $registeredWebUsersCount
            ]
        ]);
    }


    public function LoadTeacherDashboard($id)
    {
        $statsSql = "
    SELECT 
        s.teacher_id,
        COUNT(DISTINCT s.id) AS section_count,
        COUNT(sta.student_lrn) AS total_students
    FROM sections AS s
    LEFT JOIN student_teacher_assignments AS sta ON sta.section_id = s.id
    WHERE s.teacher_id = ?
    GROUP BY s.teacher_id
";

        $stmt = $this->conn->prepare($statsSql);

        if (!$stmt) {
            die("SQL error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = $result->fetch_assoc();

        $SECTION_COUNT = (int)($data['section_count'] ?? 0);
        $TOTAL_STUDENT_COUNT = (int)($data['total_students'] ?? 0);




        // -----
        $levelStatsSql = "
        SELECT 
            l.id,
            l.level,
            COUNT(CASE WHEN at.points >= (at.total * 0.5) THEN 1 END) AS passed_count,
            COUNT(CASE WHEN at.points < (at.total * 0.5) THEN 1 END) AS failed_count
        FROM `levels` AS l
        LEFT JOIN `assessments` AS a ON a.level_id = l.id
        LEFT JOIN `assessment_takes` AS at ON at.assessment_id = a.id
        WHERE l.level BETWEEN 1 AND 4 AND l.teacher_id = ?
        GROUP BY l.level
        ORDER BY l.level ASC
    ";

        $levelStatsStmt = $this->conn->prepare($levelStatsSql);

        if (!$levelStatsStmt) {
            die("SQL error: " . $this->conn->error);
        }

        $levelStatsStmt->bind_param("i", $id);
        $levelStatsStmt->execute();
        $levelStatsResult = $levelStatsStmt->get_result();

        $levelStats = [];
        while ($row = $levelStatsResult->fetch_assoc()) {
            $levelStats[] = [
                'id' => (int)$row['id'],
                'level' => (int)$row['level'],
                'passed_count' => (int)($row['passed_count'] ?? 0),
                'failed_count' => (int)($row['failed_count'] ?? 0)
            ];
        }
        // -----


        // -------
        $levelsQuery = "
SELECT id, level 
FROM levels 
WHERE teacher_id = ? AND level BETWEEN 1 AND 4
ORDER BY level ASC
";
        $levelsStmt = $this->conn->prepare($levelsQuery);
        $levelsStmt->bind_param("i", $id);
        $levelsStmt->execute();
        $levelsResult = $levelsStmt->get_result();

        $levels = [];
        while ($row = $levelsResult->fetch_assoc()) {
            $levels[$row['id']] = [
                'level' => $row['level'],
                'completed_students' => 0
            ];
        }

        $levelIds = array_keys($levels);
        if (empty($levelIds)) {
            $completedStats = [];
        } else {
            $placeholders = implode(',', array_fill(0, count($levelIds), '?'));
            $types = str_repeat('i', count($levelIds));

            $aralinQuery = "SELECT id, level_id FROM aralin WHERE level_id IN ($placeholders)";
            $aralinStmt = $this->conn->prepare($aralinQuery);
            $aralinStmt->bind_param($types, ...$levelIds);
            $aralinStmt->execute();
            $aralinResult = $aralinStmt->get_result();

            $aralinPerLevel = [];
            while ($row = $aralinResult->fetch_assoc()) {
                $aralinPerLevel[$row['level_id']][] = $row['id'];
            }

            $doneResult = $this->conn->query("SELECT user_id, aralin_id FROM done_aralin");
            $donePerUser = [];
            while ($row = $doneResult->fetch_assoc()) {
                $donePerUser[$row['user_id']][] = $row['aralin_id'];
            }

            foreach ($levels as $levelId => &$data) {
                $required = $aralinPerLevel[$levelId] ?? [];
                if (empty($required)) continue;

                $requiredSet = array_unique($required);
                sort($requiredSet);

                $count = 0;
                foreach ($donePerUser as $userAralin) {
                    $userSet = array_intersect($requiredSet, $userAralin ?? []);
                    sort($userSet);
                    if (count($userSet) === count($requiredSet)) {
                        $count++;
                    }
                }

                $data['completed_students'] = $count;
            }

            $completedStats = array_map(fn($item) => [
                'level' => $item['level'],
                'count' => $item['completed_students']
            ], array_values($levels));
        }

        // -------



        echo json_encode([
            'status' => 'success',
            'message' => 'Teacher Dashboard Loaded',
            'data' => [
                "level_stats" => $levelStats,
                "completed_stats" => $completedStats,
                "section_count" => $SECTION_COUNT,
                "total_students" => $TOTAL_STUDENT_COUNT
            ]
        ]);
    }
}
