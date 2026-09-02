<?php

namespace App\Controllers;

use App\models\Attempts;
use App\Utils\ViewModel;
use App\models\User;
use App\models\Questions;
use App\models\Courses;
use App\models\Exam_question;
use App\models\Exams;

class RedirectingController
{
    private Attempts $attempts;
    private Courses $courses;
    private User $user;
    private Questions $questions;
    private Exam_question $exam_question;
    private Exams $exams;
    private Check $auth;
    private ViewModel $viewModel;
    public function __construct()
    {
        $this->attempts = new Attempts();
        $this->courses = new Courses();
        $this->user = new User();
        $this->questions = new Questions();
        $this->exam_question = new Exam_question();
        $this->exams = new Exams();
        $this->auth = new Check();
        $this->viewModel = new ViewModel("");
        $this->auth->unsetAll();
    }
    public function dashboard()
    {
        $courses = [];
        $nextExamSet = [];
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            http_response_code(404);
            return new ViewModel('users/login', ['error' => 'User has to be logged in']);
        }
        $user_id = $user['id'];
        if (!$user_id) {
            http_response_code(400);
            return new ViewModel('users/login', ['error' => 'User ID is not passed']);
        }
        if ($user['role'] === 'teacher') {
            $courses = $this->courses->getTeacherCourses((int) $user['id']);
            $nextExamSet = $this->user->getTeacherNextExamSet((int) $user['id']);
        } else if ($user['role'] === 'student') {
            $courses = $this->courses->getStudentCourses((int) $user['id']);
            $nextExamSet = $this->user->getStudentsNextExamSet((int) $user['id']);
        }
        return (new ViewModel('dashboard', ['courses' => $courses, 'nextExamSet' => $nextExamSet]));
    }

    // -------------------------------- user --------------------------------------
    public function login()
    {
        $_SESSION['user'] = null;
        return (new ViewModel('users/login'));
    }

    public function signup()
    {
        return (new ViewModel('users/signup'));
    }

    public function logout()
    {
        $_SESSION['user'] = null;
        return new ViewModel('users/login');
    }

    public function profile()
    {
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: ' . BASE_PATH . '/api/users/login');
            exit;
        }

        $user = $this->user->find($currentUser['id']);
        unset($user['password']);

        return (new ViewModel('users/profile', ['user' => $user]));
    }

    public function usersList()
    {
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        if ($currentUser['role'] !== 'teacher') {
            http_response_code(403);
            return (new ViewModel('users/forbidden', []));
        }

        return (new ViewModel('users/list', ['users' => $this->user->all()]));
    }

    // ---------------------------------- questions ----------------------------------------
    public function showExamQuestions(string $exam_id)
    {
        $exam_id = (int) $exam_id;
        if (!$exam_id) {
            http_response_code(400);
            return (new ViewModel('exam/all', ['error' => 'No Exam ID passed']));
        }

        if (!$this->exams->find($exam_id)) {
            http_response_code(404);
            return (new ViewModel('exam/all', ['error' => 'Exam Not Found']));
        }
        return (new ViewModel('exam/questions', ['questions' => $this->questions->getExamQuestions($exam_id)]));
    }

    public function createQuestion($course_id)
    {
        $course_id = (int) $course_id;
        if (!$course_id) {
            http_response_code(400);
            $_SESSION['error'] = 'Course ID Not Passed';
            header("Location: " . BASE_PATH . "/api/courses/" . $course_id . '/teacher');
            exit;
        }
        return (new ViewModel('questions/preview', ['course_id' => $course_id]));
    }

    public function updateQuestion()
    {
        return (new ViewModel('questions/update'));
    }

    // ------------------------------ exam ----------------------------------

    public function studentExamDetails($exam_id)
    {
        if (!isset($_SESSION['user'])) {
            http_response_code(400);
            return new ViewModel('users/login', ['error' => 'User Not logged in']);
        }
        if (!$exam_id) {
            http_response_code(400);
            return new ViewModel('dashboard', ['error' => 'Exam ID Not Passed']);
        }
        $user = $_SESSION['user'];
        $examDetails = $this->exams->getExamFullDetails($exam_id);
        if (!$examDetails) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'Exam Not Found']);
        }
        $studentAttempt = '';
        $studentSelection = '';
        if ($user['role'] === 'student') {
            $studentAttempt = $this->attempts->findByExamAndStudent($exam_id, $user['id']);
            if (!$studentAttempt) {
                $studentAttempt = [];
            }

            $studentSelection = $this->exam_question->getStudentExamSelection($exam_id, $user['id']);
            if (!$studentSelection) {
                $studentSelection = [];
            }
        }
        $examQuestions = $this->questions->getExamQuestions($exam_id);
        $questionsChoices = [];
        foreach ($examQuestions as $question) {
            $questionsChoices[$question['question_id']] = $this->questions->getQuestionChoices($question['question_id']);
        }

        return new ViewModel('exams/studentExamDetails', ['examDetails' => $examDetails, 'studentAttempt' => $studentAttempt, 'studentSelection' => $studentSelection, 'questionsChoices' => $questionsChoices]);
    }

    public function teacherExamDetails($exam_id)
    {
        if (!isset($_SESSION['user'])) {
            http_response_code(400);
            return new ViewModel('users/login', ['error' => 'User Not Logged in']);
        }
        if (!$exam_id) {
            http_response_code(400);
            return new ViewModel('dashboard', ['error' => 'Exam ID Not Passed']);
        }
        if (!$this->exams->getById($exam_id)) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'Exam Not Found']);
        }
        $user = $_SESSION['user'];
        if ($user['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('dashboard', ['error' => 'User Does not have access to this page']);
        }

        $exam = $this->exams->getExamFullDetails($exam_id);
        if (!$exam) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'Exam Not found']);
        }

        $examQuestions = $this->questions->getExamQuestions($exam_id);
        if (!$examQuestions) {
            $examQuestions = [];
        }
        $questionsChoices = [];
        foreach ($examQuestions as $question) {
            $questionsChoices[$question['question_id']] = $this->questions->getQuestionChoices($question['question_id']);
        }

        $examAttemptStatus = $this->attempts->getExamAttemptStats($exam_id);
        if (!$examAttemptStatus)
            $examAttemptStatus = [];
        return new ViewModel('exams/teacherExamDetails', ['exam' => $exam, 'questions' => $examQuestions, 'questionsChoices' => $questionsChoices, 'examAttemptStatus' => $examAttemptStatus]);
    }

    public function examStart($exam_id, $page)
    {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_PATH . '/api/users/login');
            exit;
        }

        if (!$exam_id || !$page) {
            http_response_code(400);
            return new ViewModel('dashboard', ['error' => 'Exam is not selected']);
        }

        $exam = $this->exams->getExamFullDetails($exam_id);
        if (!$exam) {
            http_response_code(404);
            header('Location: ' . BASE_PATH . '/api/dashboard');
            exit;
        }

        $student_id = (int) $_SESSION['user']['id'];
        $exam_id = (int) $exam_id;

        // Create the attempt row the FIRST time the student reaches this exam —
        // find-or-create, so navigating between pages never creates duplicates.
        $attempt = $this->attempts->findByExamAndStudent($exam_id, $student_id);
        if (!$attempt) {
            $this->attempts->start($exam_id, $student_id);
        } elseif ($attempt['submitted_at'] !== null) {
            // Already submitted — don't let them re-enter and re-answer
            http_response_code(403);
            return new ViewModel('dashboard', ['error' => 'You have already submitted this exam']);
        }

        $size = 2;
        $offset = (int)($page - 1) * $size;
        $questions = $this->questions->getExamQuestionSet($exam_id, $offset, $size);
        if (!$questions) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'No Questions Were Found For the Specified Exam']);
        }
        $choices = [];
        foreach ($questions as $question) {
            $choices[$question['id']] = $this->questions->getQuestionChoices($question['id']);
        }
        $totalQuestions = $this->questions->getExamQuestionCount($exam_id);
        $totalQuestions ?? 0;

        return new ViewModel('exams/start', ['exam' => $exam, 'questions' => $questions, 'page' => $page, 'totalQuestions' => $totalQuestions, 'choices' => $choices]);
    }

    public function teacherCourse($course_id)
    {
        if (!$course_id) {
            http_response_code(400);
            return new ViewModel('dashboard', ['error' => 'Course Not Passed']);
        }
        if (!isset($_SESSION['user'])) {
            http_response_code(400);
            return new ViewModel('users/login', ['error' => 'user Not Logged in']);
        }
        $user = $_SESSION['user'];
        if ($user['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('dashboard', ['error' => 'Forbidden For None Teachers']);
        }
        $course = $this->courses->find($course_id);
        if (!$course) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'Course Not Found']);
        }
        $courseStudents = $this->courses->getCourseStudents($course_id) ?? [];
        $courseExams = $this->exams->getCourseExams($course_id) ?? [];
        $courseQuestions = $this->questions->getCourseQuestions($course_id) ?? [];

        return new ViewModel('courses/teacherCourse', ['course' => $course, 'courseStudents' => $courseStudents, 'courseExams' => $courseExams, 'courseQuestions' => $courseQuestions]);
    }

    public function examCreate($exam_id)
    {
        $course_id = (int) $_GET['course_id'] ?? null;
        $exam_id = (int) $exam_id;
        $page = $_GET['page'] ?? null;
        if (!$course_id || !$exam_id) {
            http_response_code(400);
            return new ViewModel('dashboard', ['error' => 'Course ID was not passed']);
        }
        if (!isset($_SESSION['user'])) {
            http_response_code(400);
            return new ViewModel('users/login', ['error' => 'User is Not logged in']);
        }
        $auth = $this->auth->checkTeacherCredentials();
        if ($auth !== null) {
            return $auth;
        }
        $course = $this->courses->find($course_id);
        if (!$course) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'Course not Found']);
        }

        $exam = $this->exams->getById($exam_id);
        if (!$exam) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'Exam Not Found']);
        }

        $courseQuestions = $this->questions->getCourseQuestions($course_id);
        if (!$courseQuestions) {
            $courseQuestions = [];
        }

        $questionsChoices = [];
        foreach ($courseQuestions as $question) {
            $questionsChoices[$question['id']] = $this->questions->getQuestionChoices($question['id']);
        }
        if (!$questionsChoices)
            $questionsChoices = [];

        $examQuestions = $this->questions->getExamQuestions($exam_id);
        $examQuestionsChices = [];
        if (!$examQuestions) {
            $examQuestions = [];
        } else {
            foreach ($examQuestions as $question) {
                $examQuestionsChices[$question['question_id']] = $this->questions->getQuestionChoices($question['question_id']);
            }
        }
        if (!$examQuestionsChices)
            $examQuestionsChices = [];

        $fullExamQuestions = [...$examQuestions, ...($_SESSION['auto_generated'] ?? [])];
        unset($_SESSION['auto_generated']);
        if (!$fullExamQuestions)
            $fullExamQuestions = [];
        $fullExamQuestionsChoices = [];
        foreach ($fullExamQuestions as $question) {
            $fullExamQuestionsChoices[$question['question_id']] = $this->questions->getQuestionChoices($question['question_id']);
        }
        if (!$fullExamQuestionsChoices)
            $fullExamQuestionsChoices = [];

        if (count($fullExamQuestions) > count($courseQuestions)) {
            http_response_code(400);
            $_SESSION['error'] = 'Cannot generate questions more that the course bank has to offer';
            header("Location: " . BASE_PATH . "/api/exams/" . $exam_id . "/create/courses/" . $course_id . "?page=questions");
            exit;
        }
        return new ViewModel('exams/create', ['course_id' => $course_id, 'exam' => $exam, 'course' => $course, 'courseQuestions' => $courseQuestions, 'questionsChoices' => $questionsChoices, 'examQuestions' => $fullExamQuestions, 'examQuestionsChoices' => $fullExamQuestionsChoices, "page" => $page]);
    }
}