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
            $this->elp->changeView('dashboard', ['error' => 'Incomplete input'])->render();
        }
        $course = $this->courses->find($course_id);

        if (!$course) {
            http_response_code(404);
            $this->elp->changeView('dashboard', ['error' => 'Course not found'])->render();
        }
        $choices = [];
        $correctIndex = null;

        if ($question_type === 'mc') {
            $choices = $_POST['choices'] ?? [];
            $correctIndex = isset($_POST['correct_choice']) ? (int) $_POST['correct_choice'] : null;

            if (count($choices) < 2 || count($choices) > 4) {
                http_response_code(400);
                $this->elp->changeView('dashboard', ['error' => 'A multiple-choice question must have 2 to 4 choices.'])->render();
            }

            foreach ($choices as $index => $choice) {
                $choices[$index] = trim((string) $choice);
                if ($choices[$index] === '') {
                    http_response_code(400);
                    $this->elp->changeView('dashboard', ['error' => 'All choices must be filled in.'])->render();
                }
            }

            if ($correctIndex === null || !isset($choices[$correctIndex])) {
                http_response_code(400);
                $this->elp->changeView('dashboard', ['error' => 'Please select a correct answer.'])->render();
            }
        } elseif ($question_type === 't/f') {
            $tf_correct = $_POST['tf_correct'] ?? null;

            if ($tf_correct !== 'True' && $tf_correct !== 'False') {
                http_response_code(400);
                $this->elp->changeView('dashboard', ['error' => 'Please select True or False.'])->render();
            }

            $choices = ['True', 'False'];
            $correctIndex = $tf_correct === 'True' ? 0 : 1;
        }

        $question_id = $this->questions->create($course_id, $question, $question_type);

        if (!$question_id) {
            http_response_code(500);
            $this->elp->changeView('dashboard', ['error' => 'Failed to create question.'])->render();
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

                $this->elp->changeView('dashboard', ['error' => 'Failed to create choice.'])->render();
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
        $question_id = (int) $question_id;
        $update = $_GET['update'] ?? null;
        $exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : null;
        $course_id = $_GET['course_id'] ?? null;
        $elpErr = $this->elp->checkTeacherCredentials();
        if (!$elpErr) {
            return;
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
            $this->elp->changeView('questions/preview', ['question' => $question, 'questionChoices' => $questionChoices, 'exam_id' => $exam_id])->render();
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

            $correctIndex = isset($_POST['correct_choice']) ? (int) $_POST['correct_choice'] : null;
            $deleted_choice_ids = $_POST['deleted_choice_ids'] ?? [];
            if (count($choices) < 2 || count($choices) > 4) {
                http_response_code(400);
                $_SESSION['error'] = 'A multiple-choice question must have 2 to 4 choices.';
                header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
                exit;
            }

            foreach ($choices as $index => $choice) {
                $response = '';
                if ($choice['text'] === '') {
                    http_response_code(400);
                    $_SESSION['error'] = 'All choices must be filled in.';
                    header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
                    exit;
                }

                if (!empty($choice['id']) && !isset($deleted_choice_ids[$choice['id']])) {
                    $response = $this->choices->edit($choice['id'], $choice['text'], ($index === $correctIndex) ? 1 : 0);
                }

                if (is_array($choice) && isset($deleted_choice_ids[$choice['id']]) && $deleted_choice_ids[$choice['id']] === '1') {
                    foreach (array_keys($deleted_choice_ids) as $choiceId) {
                        $this->choices->delete((int) $choiceId);
                    }
                }

                if (!empty($choice['id'])) {
                    $response = $this->choices->create($question_id, $choice['text'], ($index === $correctIndex) ? 1 : 0);
                }

                if (!$response) {
                    http_response_code(500);
                    $_SESSION['error'] = 'Failed to update the choices.';
                    header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
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
            $choices = ['True', 'False'];
            $correctIndex = $tf_correct === 'True' ? 0 : 1;
            $questionChoices = $this->questions->getQuestionChoices($question_id);
            $trueChoiceId = $_POST['true_choice_id'] ?? null;
            $falseChoiceId = $_POST['false_choice_id'] ?? null;
            if (!$questionChoices) {
                http_response_code(500);
                $_SESSION['error'] = 'Failed to retrieve existing choices for the question.';
                header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
                exit;
            }
            foreach ($choices as $index => $choice) {

                $isCorrect = ($index === $correctIndex) ? 1 : 0;
                if ($choice === 'True') {
                    $choiceId = $trueChoiceId ?? null;
                } else {
                    $choiceId = $falseChoiceId ?? null;
                }
                if (!$choiceId) {
                    http_response_code(400);
                    $_SESSION['error'] = 'Choice ID for True/False not provided.';
                    header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
                    exit;
                }
                if (!$this->choices->edit($choiceId, $choice, $isCorrect)) {
                    http_response_code(500);
                    $_SESSION['error'] = 'Failed to update the True/False choices.';
                    header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
                    exit;
                }
            }
        }
        if (!$this->questions->update($question_id, $question_text, $question_type)) {
            http_response_code(500);
            if (!isset($_SESSION['error'])) {
                $_SESSION['error'] = 'Failed to update the question';
            }
            header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
            exit;
        }
        if (!$this->exam_question->updateMark($exam_id, $question_id, $question_mark)) {
            $this->elp->redirect($_SESSION['redirect_to']);
        }
        $_SESSION['flash'] = 'Updated the question successfully';
        header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
        exit;
    }

    // Router::delete('/api/questions/{id}', ['QuestionController', 'delete']);
    public function delete(string $question_id, string $course_id)
    {
        $question_id = (int) $question_id;
        $course_id = (int) $course_id;
        if (!$question_id || !$course_id) {
            http_response_code(400);
            $this->elp->changeView('courses/questions', ['error' => 'Question ID or Course ID not passed'])->render();
        }
        $elp = $this->elp->checkTeacherCredentials();
        if ($elp !== null)
            return $elp;

        if (!$this->questions->getByID($question_id)) {
            http_response_code(404);
            $this->elp->changeView('courses/questions', ['error' => 'Question Not Found', 'course_id' => $course_id])->render();
        }

        if (!$this->questions->delete($question_id)) {
            http_response_code(500);
            $this->elp->changeView('courses/questions', ['error' => 'Internal Server Error', 'course_id' => $course_id])->render();
        }
        $_SESSION['flash'] = 'Successfully deleted the question';
        header("Location: /api/course/questions?course_id=$course_id");
        exit;
    }

    public function autoGenerate($exam_id)
    {
        $course_id = (int) $_POST['course_id'] ?? null;;
        $exam_id = (int) $exam_id;
        $limit = (int) $_POST['limit'] ?? null;

        if (!$course_id) {
            http_response_code(400);
            $_SESSION['error'] = "Course ID Not Passed";
            header("Location: " . BASE_PATH . "/api/dashboard");
            exit;
        }
        $elpErr = $this->elp->checkTeacherCredentials();
        if (!$elpErr) {
            return;
        }
        if (!$this->courses->find($course_id)) {
            http_response_code(404);
            $_SESSION['error'] = "Course Not Found";
            header("Location: " . BASE_PATH . "/api/dashboard");
            exit;
        }

        $autoGeneratedQuestions = $this->questions->getAutoGenerated($course_id, $limit);

        $autoGeneratedQuestionsChoices = [];
        if (!$autoGeneratedQuestions) {
            $autoGeneratedQuestions = [];
        } else
            foreach ($autoGeneratedQuestions as $question) {
                $autoGeneratedQuestionsChoices[$question['question_id']] = $this->questions->getQuestionChoices($question['question_id']);
            }

        if (!$autoGeneratedQuestionsChoices)
            $autoGeneratedQuestionsChoices = [];

        $_SESSION['auto_generated'] = $autoGeneratedQuestions;
        $_SESSION['auto_generated_choices'] = $autoGeneratedQuestionsChoices;

        header("Location: " . BASE_PATH . "/api/exams/" . $exam_id . "/create/courses/" . $course_id . "?page=questions");
        exit;
    }
}