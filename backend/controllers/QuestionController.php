<?php

namespace App\Controllers;

use App\Controllers\Check_user;
use App\models\Choices;
use App\models\Courses;
use App\models\Exam_question;
use App\models\Exams;
use App\models\Questions;
use App\Utils\ViewModel;

class QuestionController
{
    private Choices $choices;
    private Courses $courses;
    private Exam_question $exam_question;
    private Exams $exams;
    private Questions $questions;
    private Check_user $auth;
    public function __construct()
    {
        $this->choices = new Choices();
        $this->courses = new Courses();
        $this->exam_question = new Exam_question();
        $this->exams = new Exams();
        $this->questions = new Questions();
        $this->auth = new Check_user();
    }
    public function addQuestion($course_id)
    {
        $authError = $this->auth->checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $course_id = (int) $course_id;
        $question = trim($_POST['question'] ?? '');
        $question_type = $_POST['question_type'] ?? '';
        $question_mark = isset($_POST['question_mark']) ? (float) $_POST['question_mark'] : 0;

        $exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : null;

        if ($course_id <= 0 || $question === '' || !in_array($question_type, ['mc', 't/f'], true) || $question_mark <= 0) {
            http_response_code(400);
            return new ViewModel('dashboard', ['error' => 'Incomplete input']);
        }
        $course = $this->courses->find($course_id);

        if (!$course) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'Course not found']);
        }
        $choices = [];
        $correctIndex = null;

        if ($question_type === 'mc') {
            $choices = $_POST['choices'] ?? [];
            $correctIndex = isset($_POST['correct_choice']) ? (int) $_POST['correct_choice'] : null;

            if (count($choices) < 2 || count($choices) > 4) {
                http_response_code(400);
                return new ViewModel('dashboard', ['error' => 'A multiple-choice question must have 2 to 4 choices.']);
            }

            foreach ($choices as $index => $choice) {
                $choices[$index] = trim((string) $choice);
                if ($choices[$index] === '') {
                    http_response_code(400);
                    return new ViewModel('dashboard', ['error' => 'All choices must be filled in.']);
                }
            }

            if ($correctIndex === null || !isset($choices[$correctIndex])) {
                http_response_code(400);
                return new ViewModel('dashboard', ['error' => 'Please select a correct answer.']);
            }
        } elseif ($question_type === 't/f') {
            $tf_correct = strtolower($_POST['tf_correct']) ?? null;

            if ($tf_correct !== 'true' && $tf_correct !== 'false') {
                http_response_code(400);
                return new ViewModel('dashboard', ['error' => 'Please select True or False.']);
            }

            $choices = ['True', 'False'];
            $correctIndex = $tf_correct === 'true' ? 0 : 1;
        }

        $question_id = $this->questions->create($course_id, $question, $question_type);

        if (!$question_id) {
            http_response_code(500);
            return new ViewModel('dashboard', ['error' => 'Failed to create question.']);
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

                return new ViewModel('dashboard', ['error' => 'Failed to create choice.']);
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
        header("Location: " . BASE_PATH . "/api/dashboard");
        exit;
    }

    // Router::put('/api/questions/{id}', ['QuestionController', 'editQuestion']);
    public function editQuestion($question_id)
    {
        unset($_SESSION['error']);
        $question_id = (int) $question_id;
        $update = $_GET['update'] ?? null;
        $exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : null;
        $course_id = $_GET['course_id'] ?? null;
        $auth = $this->auth->checkTeacherCredentials();
        if ($auth !== null) {
            return $auth;
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
            return new ViewModel('questions/preview', ['question' => $question, 'questionChoices' => $questionChoices, 'exam_id' => $exam_id]);
        }
        if (!$exam_id) {
            http_response_code(400);
            $_SESSION['error'] = 'Exam ID not passed';
            header("Location: " . BASE_PATH . "/api/courses/teacher/" . $course_id);
            exit;
        }
        $question_text = trim($_POST['question_text'] ?? '');
        $question_mark = isset($_POST['question_mark']) ?? 1;
        $question_type = $_POST['question_type'] ?? 'mc';
        if (!$this->questions->update($question_id, $question_text, $question_type)) {
            http_response_code(500);
            if (!isset($_SESSION['error'])) {
                $_SESSION['error'] = 'Failed to update the question';
            }
            header("Location: " . BASE_PATH . "/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
            exit;
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
            return (new ViewModel('courses/questions', ['error' => 'Question ID or Course ID not passed']));
        }
        $auth = $this->auth->checkTeacherCredentials();
        if ($auth !== null)
            return $auth;

        if (!$this->questions->getByID($question_id)) {
            http_response_code(500);
            return (new ViewModel('courses/questions', ['error' => 'Internal Server error', 'course_id' => $course_id]));
        }

        if (!$this->questions->delete($question_id)) {
            http_response_code(500);
            return new ViewModel('courses/questions', ['error' => 'Internal Server Error', 'course_id' => $course_id]);
        }
        $_SESSION['flash'] = 'Successfully deleted the question';
        header("Location: /api/course/questions?course_id=$course_id");
        exit;
    }

    public function autoGenerate($exam_id)
    {
        $_SESSION['error'] = null;
        $course_id = (int) $_POST['course_id'] ?? null;;
        $exam_id = (int) $exam_id;
        $limit = (int) $_POST['limit'] ?? null;

        if (!$course_id) {
            http_response_code(400);
            $_SESSION['error'] = "Course ID Not Passed";
            header("Location: " . BASE_PATH . "/api/dashboard");
            exit;
        }
        $auth = $this->auth->checkTeacherCredentials();
        if ($auth !== null) {
            return $auth;
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