<?php

namespace App\Controllers;

use App\models\Choices;
use App\models\Courses;
use App\models\Exam_question;
use App\models\Exams;
use App\models\Questions;

class QuestionController
{
    private Choices $choices;
    private Courses $courses;
    private Exam_question $exam_question;
    private Exams $exams;
    private Questions $questions;
    private Check $elp;
    public function __construct()
    {
        $this->choices = new Choices();
        $this->courses = new Courses();
        $this->exam_question = new Exam_question();
        $this->exams = new Exams();
        $this->questions = new Questions();
        $this->elp = new Check();
        $this->elp->unsetAll();
    }
    public function addQuestion($course_id)
    {
        $elp = $this->elp->checkTeacherCredentials();
        if ($elp !== null) {
            return $elp;
        }

        $course_id = (int) $course_id;
        $question = trim($_POST['question'] ?? '');
        $question_type = $_POST['question_type'] ?? '';
        $question_mark = isset($_POST['question_mark']) ? (float) $_POST['question_mark'] : 0;

        $exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : null;


        if (!$course_id || !$question || !in_array($question_type, ['mc', 't/f'], true) || $question_mark <= 0) {
            http_response_code(400);
            return $this->elp->changeView('dashboard', ['error' => 'Incomplete input']);
        }
        $course = $this->courses->find($course_id);

        if (!$course) {
            http_response_code(404);
            return $this->elp->changeView('dashboard', ['error' => 'Course not found']);
        }
        $choices = [];
        $correctIndex = null;

        if ($question_type === 'mc') {
            $choices = $_POST['choices'] ?? [];
            $correctIndex = isset($_POST['correct_choice']) ? (int) $_POST['correct_choice'] : null;

            if (count($choices) < 2 || count($choices) > 4) {
                http_response_code(400);
                return $this->elp->changeView('dashboard', ['error' => 'A multiple-choice question must have 2 to 4 choices.']);
            }

            foreach ($choices as $index => $choice) {
                $choices[$index] = trim((string) $choice);
                if ($choices[$index] === '') {
                    http_response_code(400);
                    return $this->elp->changeView('dashboard', ['error' => 'All choices must be filled in.']);
                }
            }

            if ($correctIndex === null || !isset($choices[$correctIndex])) {
                http_response_code(400);
                return $this->elp->changeView('dashboard', ['error' => 'Please select a correct answer.']);
            }
        } elseif ($question_type === 't/f') {
            $tf_correct = $_POST['tf_correct'] ?? null;

            if ($tf_correct !== 'True' && $tf_correct !== 'False') {
                http_response_code(400);
                return $this->elp->changeView('dashboard', ['error' => 'Please select True or False.']);
            }

            $choices = ['True', 'False'];
            $correctIndex = $tf_correct === 'True' ? 0 : 1;
        }

        $question_id = $this->questions->create($course_id, $question, $question_type);

        if (!$question_id) {
            http_response_code(500);
            return $this->elp->changeView('dashboard', ['error' => 'Failed to create question.']);
        }

        foreach ($choices as $index => $choice) {

            $isCorrect = ($index === $correctIndex) ? 1 : 0;
            $choice_id = $this->choices->create($question_id, $choice, $isCorrect);
            if (!$choice_id) {
                http_response_code(500);
                $_SESSION['error'] = 'Failed to create question choice.';
                if ($exam_id) {
                    header("Location: " . BASE_PATH . "/api/questions/create/courses/" . $course_id . "/view?exam_id=" . $exam_id);
                    exit;
                }

                return $this->elp->changeView('dashboard', ['error' => 'Failed to create choice.']);
            }
        }
        if ($exam_id) {
            $entry = $this->exam_question->create($exam_id, $question_id, $question_mark);
            if (!$entry) {
                http_response_code(500);
                $_SESSION['error'] = 'Failed to add question to exam.';
                header("Location: " . BASE_PATH . "/api/questions/create/courses/" . $course_id . "/view?exam_id=" . $exam_id);
                exit;
            }
        }

        $_SESSION['flash'] = 'Created the question successfully.';
        if ($exam_id) {
            header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
            exit;
        }
        header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
        exit;
    }

    // Router::put('/api/questions/{id}', ['QuestionController', 'editQuestion']);
    public function editQuestion($question_id)
    {
        $isCorrect = 0;
        $question_id = (int) $question_id;
        $update = $_GET['update'] ?? null;
        $exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : null;
        $course_id = $_GET['course_id'] ?? null;
        $elpErr = $this->elp->checkTeacherCredentials();
        if ($elpErr !== null) {
            return $elpErr;
        }
        $question = $this->questions->getQuestionDetails($question_id, $exam_id);
        if (!$question) {
            http_response_code(404);
            $_SESSION['error'] = 'Question not found';
            header("Location: " . BASE_PATH . "/api/courses/teacher/" . $course_id);
            exit;
        }
        $course_id = $question['course_id'];
        $questionChoices = $this->questions->getQuestionChoices($question_id);
        if (!$questionChoices) {
            $questionChoices = [];
        }
        if (!$update) {
            return $this->elp->changeView('questions/preview', ['question' => $question, 'questionChoices' => $questionChoices, 'exam_id' => $exam_id]);
        }
        if (!$exam_id) {
            http_response_code(400);
            $_SESSION['error'] = 'Exam ID not passed';
            header("Location: " . BASE_PATH . "/api/courses/teacher/" . $course_id);
            exit;
        }
        $question_text = trim($_POST['question_text'] ?? '');
        $question_mark = isset($_POST['question_mark']) ? (int) $_POST['question_mark'] : 1;
        $question_type = $_POST['question_type'] ?? 'mc';

        if ($question['question_type'] !== $question_type) {
            $this->choices->deleteByQuestionId($question_id);
        } else {
            $this->choices->reset($question_id);
        }
        if ($question_type === 'mc') {
            $choices = $_POST['choices'] ?? [];
            if (count($choices) < 2) {
                http_response_code(400);
                $this->elp->redirect("/api/questions/update/" . $question['question_id'] . "?exam_id=" . $exam_id);
            }
            $correctIndex = isset($_POST['correct_choice']) ? (int) $_POST['correct_choice'] : null;
            $deleted_choice_ids = $_POST['deleted_choice_ids'] ?? [];

            foreach ($deleted_choice_ids as $choiceId => $deleted) {
                if ($deleted === '1') {
                    $this->choices->delete((int) $choiceId);
                }
            }
            if (count($choices) < 2 || count($choices) > 4) {
                http_response_code(400);
                $_SESSION['error'] = 'A multiple-choice question must have 2 to 4 choices.';
                header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id .
                    "&page=questions");
                exit;
            }

            foreach ($choices as $index => $choice) {
                $response = '';
                if ($choice['text'] === '') {
                    http_response_code(400);
                    $_SESSION['error'] = 'All choices must be filled in.';
                    header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id .
                        "&page=questions");
                    exit;
                }
                $isCorrect = !empty($choice['is_correct']) ? 1 : 0;
                if (!empty($choice['id']) && !isset($deleted_choice_ids[$choice['id']])) {
                    $response = $this->choices->edit($choice['id'], $choice['text'], $isCorrect);
                }

                if (!empty($choice['new'])) {
                    $response = $this->choices->create($question_id, $choice['text'], $isCorrect);
                }

                if (!$response) {
                    http_response_code(500);
                    $_SESSION['error'] = 'Failed to update the choices.';
                    header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id .
                        "&page=questions");
                    exit;
                }
            }
        } else if ($question_type === 't/f') {

            $tf_correct = $_POST['tf_correct'] ?? null;

            if ($tf_correct !== 'True' && $tf_correct !== 'False') {
                http_response_code(400);
                $_SESSION['error'] = 'Please select True or False.';

                header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
                exit;
            }

            $trueChoiceId = !empty($_POST['true_choice_id']) ? (int) $_POST['true_choice_id'] : null;

            $falseChoiceId = !empty($_POST['false_choice_id']) ? (int) $_POST['false_choice_id'] : null;

            $choices = ['True' => $trueChoiceId, 'False' => $falseChoiceId];

            foreach ($choices as $choiceText => $choiceId) {

                $isCorrect = ($choiceText === $tf_correct) ? 1 : 0;

                if ($choiceId) {
                    $response = $this->choices->edit($choiceId, $choiceText, $isCorrect);
                } else {
                    $response = $this->choices->create($question_id, $choiceText, $isCorrect);
                }

                if (!$response) {
                    http_response_code(500);
                    $_SESSION['error'] = 'Failed to update True/False choices.';

                    header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id .
                        "?course_id=" . $course_id . "&page=questions");
                    exit;
                }
            }
        }
        if (!$this->questions->update($question_id, $question_text, $question_type)) {
            http_response_code(500);
            if (!isset($_SESSION['error'])) {
                $_SESSION['error'] = 'Failed to update the question';
            }
            header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id .
                "&page=questions");
            exit;
        }
        if (!$this->exam_question->updateMark($exam_id, $question_id, $question_mark)) {
            $this->elp->redirect($_SESSION['redirect_to']);
        }
        $_SESSION['flash'] = 'Updated the question successfully';
        header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id .
            "&page=questions");
        exit;
    }

    // Router::delete('/api/questions/{id}', ['QuestionController', 'delete']);
    public function delete(string $question_id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->elp->changeView('dashboard');
        }
        $question_id = (int) $question_id;
        $course_id = $_GET['course_id'] ?? null;
        $exam_id = $_GET['exam_id'] ?? null;
        if (!$question_id) {
            http_response_code(400);
            return $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
        }
        $elp = $this->elp->checkTeacherCredentials();
        if ($elp !== null)
            return $elp;

        if (!$this->questions->getByID($question_id)) {
            http_response_code(404);
            $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
        }

        if (!$this->exams->removeQuestion($question_id, $exam_id)) {
            http_response_code(500);
            $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
        }
        $_SESSION['flash'] = 'Successfully deleted the question';
        $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
    }

    public function questionBank($exam_id)
    {
        $authError = $this->elp->checkTeacherCredentials();
        if ($authError !== null)
            return $authError;

        $exam_id = (int) $exam_id;
        if (!$exam_id) {
            http_response_code(400);
            $_SESSION['error'] = 'Exam ID not Passed';
            $this->elp->redirect("/api/dashboard");
        }
        $exam = $this->exams->find($exam_id);
        if (!$exam) {
            $this->elp->redirect($_SESSION['redirect']);
        }
        $course_id = $exam['course_id'];
        $questions = $this->questions->getCourseQuestions($course_id);
        return $this->elp->changeView('questions/bank', ['questions' => $questions, 'exam_id' => $exam_id, 'course_id' => $exam['course_id']]);
    }
}
