<?php
include_once(__DIR__ . '/../../controller/AssessmentsController.php');

$requestType = $_POST['requestType'];

$controller = new AssesmentsController();

if ($requestType == "GetAssessment") {
    $level_id = $_POST['level_id'];
    $controller->GetAssessment($level_id);
} elseif ($requestType == "CreateAssessment") {
    $level_id = $_POST['level_id'];
    $assessment_id = $_POST['assessment_id'] ?? null;
    $title = $_POST['title'];
    $description = $_POST['description'];

    $controller->CreateAssessment($level_id, $assessment_id, $title, $description);
} elseif ($requestType == "InsertMultipleChoice") {
    $assessment_id = $_POST['assessment_id'];
    $question = $_POST['question'];
    $choice_a = $_POST['choice_a'];
    $choice_b = $_POST['choice_b'];
    $choice_c = $_POST['choice_c'];
    $choice_d = $_POST['choice_d'];
    $correct_answer = $_POST['answer'];

    $controller->InsertMultipleChoice($assessment_id, $question, $choice_a, $choice_b, $choice_c, $choice_d, $correct_answer);
} elseif ($requestType == "InsertTrueOrFalse") {
    $assessment_id = $_POST['assessment_id'];
    $question = $_POST['question'];
    $correct_answer = $_POST['answer'];
    $answer = ($correct_answer == "true" ? 1 : 0);
    $controller->InsertTrueOrFalse($assessment_id, $question, $answer);
} elseif ($requestType == "InsertIdentification") {
    $assessment_id = $_POST['assessment_id'];
    $question = $_POST['question'];
    $correct_answer = $_POST['answer'];
    $controller->InsertIdentification($assessment_id, $question, $correct_answer);
} elseif ($requestType == "InsertJumbledWords") {
    $assessment_id = $_POST['assessment_id'];
    $question = $_POST['question'];
    $correct_answer = $_POST['answer'];
    $controller->InsertJumbledWords($assessment_id, $question, $correct_answer);
} elseif ($requestType == "GetMultiQuestions") {
    $assessment_id = $_POST['assessment_id'];
    $controller->GetMultipleChoiceQuestions($assessment_id);
} elseif ($requestType == "GetTrueOrFalseQuestions") {
    $assessment_id = $_POST['assessment_id'];
    $controller->GetTrueOrFalseQuestions($assessment_id);
} elseif ($requestType == "GetIdentificationQuestions") {
    $assessment_id = $_POST['assessment_id'];
    $controller->GetIdentificationQuestions($assessment_id);
} elseif ($requestType == "GetJumbledWordsQuestions") {
    $assessment_id = $_POST['assessment_id'];
    $controller->GetJumbledWordsQuestions($assessment_id);
} elseif ($requestType == "ImportMultipleChoices") {
    $questions = json_decode($_POST['questions'], true);
    $assessment_id = $_POST['assessment_id'];

    $controller->ImportMultipleChoices($assessment_id, $questions);
} elseif ($requestType == "ImportTrueOrFalse") {
    $questions = json_decode($_POST['questions'], true);
    $assessment_id = $_POST['assessment_id'];

    $controller->ImportTrueOrFalse($assessment_id, $questions);
} elseif ($requestType == "ImportIdentification") {
    $questions = json_decode($_POST['questions'], true);
    $assessment_id = $_POST['assessment_id'];

    $controller->ImportIdentification($assessment_id, $questions);
} elseif ($requestType == "ImportJumbledWords") {
    $questions = json_decode($_POST['questions'], true);
    $assessment_id = $_POST['assessment_id'];

    $controller->ImportJumbledWords($assessment_id, $questions);
} else {
    http_response_code(400);
    echo "Invalid or missing requestType.";
}
