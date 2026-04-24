<?php
session_start();
// Adjust the path to class.php based on where you place this script
require_once('../../class.php'); 

header('Content-Type: application/json');

$db = new global_class();
$conn = $db->conn;

$response = ['status' => 500, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    // 1. Basic File Validation
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 400, 'message' => 'File upload error code: ' . $file['error']]);
        exit;
    }

    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($fileExtension) !== 'csv') {
        echo json_encode(['status' => 400, 'message' => 'Invalid file type. Please upload a CSV file.']);
        exit;
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        echo json_encode(['status' => 500, 'message' => 'Failed to open the uploaded file.']);
        exit;
    }

    // 2. Start the Database Transaction
    // If anything fails in the try block, no data is saved to the database.
    $conn->begin_transaction();

try {
        // Skip the header row
        $header = fgetcsv($handle);

        $rowIndex = 2; // Track rows for precise error reporting (Row 1 is the header)
        
        $allowedTypes = ['multiple_choice', 'true_false', 'identification', 'jumbled_word'];
        $allowedDifficulties = ['easy', 'medium', 'hard'];

        if (!isset($_POST['assessment_id']) || !is_numeric($_POST['assessment_id'])) {
            throw new Exception("Missing or invalid Assessment ID.");
        }
        $assessment_id = $_POST['assessment_id'];

        // --- OPTIMIZATION: THE PRE-FETCH ---
        // Fetch all existing questions for this assessment ONCE
        $existingQuestions = [];
        $fetchStmt = $conn->prepare("SELECT question_text FROM `questions` WHERE `assessment_id` = ?");
        if ($fetchStmt) {
            $fetchStmt->bind_param("i", $assessment_id);
            $fetchStmt->execute();
            $result = $fetchStmt->get_result();
            while ($qRow = $result->fetch_assoc()) {
                // Store the text in lowercase as an array KEY for lightning-fast lookup
                $cleanText = strtolower(trim($qRow['question_text']));
                $existingQuestions[$cleanText] = true; 
            }
            $fetchStmt->close();
        }
        // ------------------------------------

        // Prepare the INSERT statement
        $stmt = $conn->prepare("INSERT INTO `questions` 
            (`assessment_id`, `concept_group_id`, `type`, `difficulty`, `question_text`, `choices`, `correct_answer`) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }

        $insertedCount = 0;
        $skippedCount = 0;

        // 3. Process the CSV row by row
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 6) {
                throw new Exception("Row $rowIndex is missing columns. Expected 6, found " . count($row));
            }

            $concept_group_id = trim($row[0]);
            $type = strtolower(trim($row[1]));
            $difficulty = strtolower(trim($row[2]));
            $question_text = trim($row[3]);
            $correct_answer = trim($row[4]);
            $raw_choices = isset($row[5]) ? trim($row[5]) : '';

            // Strict Data Validation
            if (!is_numeric($assessment_id) || !is_numeric($concept_group_id)) {
                throw new Exception("Row $rowIndex: Assessment ID and Concept Group ID must be numbers.");
            }
            if (!in_array($type, $allowedTypes)) {
                throw new Exception("Row $rowIndex: Invalid question type '$type'.");
            }
            if (!in_array($difficulty, $allowedDifficulties)) {
                throw new Exception("Row $rowIndex: Invalid difficulty '$difficulty'.");
            }
            if (empty($question_text) || empty($correct_answer)) {
                throw new Exception("Row $rowIndex: Question text and correct answer cannot be empty.");
            }

            // --- THE LAG-FREE DUPLICATE CHECK ---
            $checkText = strtolower($question_text);
            
            if (isset($existingQuestions[$checkText])) {
                // Question exists in our PHP memory array! Skip it instantly.
                $skippedCount++;
                $rowIndex++;
                continue; 
            }
            
            // If it's a new question, add it to our tracking array so we don't 
            // insert duplicates if the CSV file itself contains duplicate rows!
            $existingQuestions[$checkText] = true;
            // ------------------------------------

            // 4. Process Multiple Choice Options into JSON
            $choicesJSON = null;
            if ($type === 'multiple_choice') {
                if (empty($raw_choices)) {
                    throw new Exception("Row $rowIndex: Multiple choice questions must have choices.");
                }
                
                $choicesArray = [];
                $splitChoices = explode(',', $raw_choices);
                foreach ($splitChoices as $choice) {
                    $parts = explode(':', $choice, 2);
                    if (count($parts) === 2) {
                        $key = trim($parts[0]);
                        $val = trim($parts[1]);
                        $choicesArray[$key] = $val;
                    } else {
                        throw new Exception("Row $rowIndex: Choices format invalid. Expected 'A: Answer1, B: Answer2'. Got: $choice");
                    }
                }
                $choicesJSON = json_encode($choicesArray);
            }

            // 5. Bind parameters and execute the query
            $stmt->bind_param("iisssss", $assessment_id, $concept_group_id, $type, $difficulty, $question_text, $choicesJSON, $correct_answer);
            
            if (!$stmt->execute()) {
                throw new Exception("Row $rowIndex: Failed to insert data (" . $stmt->error . ")");
            }

            $insertedCount++;
            $rowIndex++;
        }

        // 6. If no errors occurred, commit the data!
        $conn->commit();
        
        // Log the activity
        if (isset($_SESSION['USERNAME'])) {
            $log_username = $_SESSION['USERNAME'];
            $dateToday = date('Y-m-d H:i:s');
            $questionsAdded = $rowIndex - 2;
            $activityDescription = "Bulk uploaded $questionsAdded questions via CSV.";
            
            $query_activity_log = $conn->prepare("INSERT INTO `activitylog` (`log_username`, `activity_description`, `date_added`) VALUES (?, ?, ?)");
            $query_activity_log->bind_param('sss', $log_username, $activityDescription, $dateToday);
            $query_activity_log->execute();
        }

        $response = [
            'status' => 200, 
            'message' => 'Success! Inserted ' . ($rowIndex - 2) . ' questions into the database.'
        ];

    } catch (Exception $e) {
        // Rollback the entire transaction if ANY row throws an exception
        $conn->rollback();
        $response = [
            'status' => 400, 
            'message' => 'Upload Cancelled. ' . $e->getMessage()
        ];
    }

    fclose($handle);
    echo json_encode($response);
    exit;
} else {
    echo json_encode(['status' => 400, 'message' => 'Invalid request method.']);
    exit;
}
?>