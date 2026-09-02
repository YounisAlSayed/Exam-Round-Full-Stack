<?php

namespace App\Controllers;

use App\models\Attempts;
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
    private Check $help;
    public function __construct()
    {
        $this->attempts = new Attempts();
        $this->courses = new Courses();
        $this->user = new User();
        $this->questions = new Questions();
        $this->exam_question = new Exam_question();
        $this->exams = new Exams();
        $this->help = new Check();
        $this->help->unsetAll();
    }
    public function dashboard()
    {
        $this->help->unsetAll();
        $courses = [];
        $nextExamSet = [];
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            http_response_code(404);
            $this->help->changeView('users/login', ['error' => 'User Not Logged in'])->render();
        }
        $user_id = $user['id'];
        if (!$user_id) {
            http_response_code(400);
            $this->help->changeView('users/login', ['error' => 'User ID Not Found'])->render();
        }
        if ($user['role'] === 'teacher') {
            $courses = $this->courses->getTeacherCourses((int) $user['id']);
            $nextExamSet = $this->user->getTeacherNextExamSet((int) $user['id']);
        } else if ($user['role'] === 'student') {
            $courses = $this->courses->getStudentCourses((int) $user['id']);
            $nextExamSet = $this->user->getStudentsNextExamSet((int) $user['id']);
        }
        $this->help->changeView('dashboard', ['courses' => $courses, 'nextExamSet' => $nextExamSet])->render();
    }

    // -------------------------------- user --------------------------------------
    public function login()
    {
        $this->help->unsetAll();
        $_SESSION['user'] = null;
        $this->help->changeView('users/login')->render();
    }

    public function signup()
    {
        $this->help->unsetAll();
        $this->help->changeView('users/signup')->render();
    }

    public function logout()
    {
        $this->help->unsetAll();
        $_SESSION['user'] = null;
        $this->help->changeView('users/login')->render();
    }

    public function profile()
    {
        $this->help->unsetAll();
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            $this->help->changeView('users/login')->render();
        }

        $user = $this->user->find($currentUser['id']);
        unset($user['password']);

        $this->help->changeView('users/profile', ['user' => $user])->render();
    }

    public function usersList()
    {
        $this->help->unsetAll();
        $currentUser = $_SESSION['user'] ?? null;

        $this->help->checkTeacherCredentials();
        $usersList = $this->user->all();
        if (!$usersList) {
            $usersList = [];
        }
        $this->help->changeView('users/list', ['usersList' => $usersList])->render();
    }

    // ---------------------------------- questions ----------------------------------------
    // public function showExamQuestions(string $exam_id)
    // {
    //     $this->help->unsetAll();
    //     $exam_id = (int) $exam_id;
    //     if (!$exam_id) {
    //         http_response_code(400);
    //         return (new ViewModel('exam/all', ['error' => 'No Exam ID passed']));
    //     }

    //     if (!$this->exams->find($exam_id)) {
    //         http_response_code(404);
    //         return (new ViewModel('exam/all', ['error' => 'Exam Not Found']));
    //     }
    //     return (new ViewModel('exam/questions', ['questions' => $this->questions->getExamQuestions($exam_id)]));
    // }

    public function createQuestion($course_id)
    {
        $this->help->unsetAll();
        $course_id = (int) $course_id;
        if (!$course_id) {
            http_response_code(400);
            $_SESSION['error'] = 'Course ID Not Passed';
            header("Location: " . BASE_PATH . "/api/courses/teacher/" . $course_id);
            exit;
        }
        $this->help->changeView('questions/preview', ['course_id' => $course_id])->render();
    }

    // ------------------------------ exam ----------------------------------

    public function studentExamDetails($exam_id)
    {
        $this->help->unsetAll();
        if (!isset($_SESSION['user'])) {
            http_response_code(400);
            $this->help->changeView("users/login", ['error' => 'User Not logged in'])->render();
        }
        if (!$exam_id) {
            http_response_code(400);
            $this->help->changeView("dashboard", ['error' => 'Exam ID not Passed'])->render();
        }
        $user = $_SESSION['user'];
        $examDetails = $this->exams->getExamFullDetails($exam_id);
        if (!$examDetails) {
            http_response_code(404);
            $this->help->changeView("dashboard", ['error' => 'Exam Not Found'])->render();
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

        $this->help->changeView('exams/studentExamDetails', ['examDetails' => $examDetails, 'studentAttempt' => $studentAttempt, 'studentSelection' => $studentSelection, 'questionsChoices' => $questionsChoices])->render();
    }

    public function teacherExamDetails($exam_id)
    {
        $this->help->unsetAll();
        if (!isset($_SESSION['user'])) {
            http_response_code(400);
            $this->help->changeView('users/login', ['error' => 'User Not Logged in'])->render();
        }
        if (!$exam_id) {
            http_response_code(400);
            $this->help->changeView('dashboard', ['error' => 'Exam ID Not Passed'])->render();
        }
        if (!$this->exams->getById($exam_id)) {
            http_response_code(404);
            $this->help->changeView('dashboard', ['error' => 'Exam Not Found'])->render();
        }
        $user = $_SESSION['user'];
        if ($user['role'] !== 'teacher') {
            http_response_code(403);
            $this->help->changeView('dashboard', ['error' => 'User Does not have access to this page'])->render();
        }

        $exam = $this->exams->getExamFullDetails($exam_id);
        if (!$exam) {
            http_response_code(404);
            $this->help->changeView('dashboard', ['error' => 'Exam Not found'])->render();
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

        $this->help->changeView('exams/teacherExamDetails', ['exam' => $exam, 'questions' => $examQuestions, 'questionsChoices' => $questionsChoices, 'examAttemptStatus' => $examAttemptStatus])->render();
    }

    public function examStart($exam_id, $page)
    {
        $this->help->unsetAll();
        if (!isset($_SESSION['user'])) {
            $this->help->redirect('/api/users/login');
        }

        if (!$exam_id || !$page) {
            http_response_code(400);
            $this->help->changeView('dashboard', ['error' => 'Exam is not selected'])->render();
        }

        $exam = $this->exams->getExamFullDetails($exam_id);
        if (!$exam) {
            http_response_code(404);
            $this->help->redirect("/api/dashboard");
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
            $this->help->changeView('dashboard', ['error' => 'You have already submitted this exam'])->render();
        }

        $size = 2;
        $offset = (int)($page - 1) * $size;
        $questions = $this->questions->getExamQuestionSet($exam_id, $offset, $size);
        if (!$questions) {
            http_response_code(404);
            $this->help->changeView('dashboard', ['error' => 'No Questions Were Found For the Specified Exam'])->render();
        }
        $choices = [];
        foreach ($questions as $question) {
            $choices[$question['id']] = $this->questions->getQuestionChoices($question['id']);
        }
        $totalQuestions = $this->questions->getExamQuestionCount($exam_id);
        $totalQuestions ?? 0;

        $this->help->changeView('exams/start', ['exam' => $exam, 'questions' => $questions, 'page' => $page, 'totalQuestions' => $totalQuestions, 'choices' => $choices])->render();
    }

    public function teacherCourse($course_id)
    {
        $this->help->unsetAll();
        if (!$course_id) {
            http_response_code(400);
            $this->help->changeView('dashboard', ['error' => 'Course Not Passed'])->render();
        }
        if (!isset($_SESSION['user'])) {
            http_response_code(400);
            $this->help->changeView('users/login', ['error' => 'user Not Logged in'])->render();
        }
        $user = $_SESSION['user'];
        if ($user['role'] !== 'teacher') {
            http_response_code(403);
            $this->help->changeView('dashboard', ['error' => 'Forbidden For None Teachers'])->render();
        }
        $course = $this->courses->find($course_id);
        if (!$course) {
            http_response_code(404);
            $this->help->changeView('dashboard', ['error' => 'Course Not Found'])->render();
        }
        $courseStudents = $this->courses->getCourseStudents($course_id) ?? [];
        $courseExams = $this->exams->getCourseExams($course_id) ?? [];
        $courseQuestions = $this->questions->getCourseQuestions($course_id) ?? [];

        $this->help->changeView('courses/teacherCourse', ['course' => $course, 'courseStudents' => $courseStudents, 'courseExams' => $courseExams, 'courseQuestions' => $courseQuestions])->render();
    }

    public function examCreate($exam_id)
    {
        $this->help->unsetAll();
        $course_id = (int) $_GET['course_id'] ?? null;
        $exam_id = (int) $exam_id;
        $page = $_GET['page'] ?? null;
        if (!$course_id || !$exam_id) {
            http_response_code(400);
            $this->help->changeView('dashboard', ['error' => 'Course ID was not passed'])->render();
        }
        if (!isset($_SESSION['user'])) {
            http_response_code(400);
            $this->help->changeView('users/login', ['error' => 'User is Not logged in'])->render();
        }
        $check = $this->help->checkTeacherCredentials();
        if (!$check) {
            return;
        }
        $course = $this->courses->find($course_id);
        if (!$course) {
            http_response_code(404);
            $this->help->changeView('dashboard', ['error' => 'Course not Found'])->render();
        }

        $exam = $this->exams->getById($exam_id);
        if (!$exam) {
            http_response_code(404);
            $this->help->changeView('dashboard', ['error' => 'Exam Not Found'])->render();
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
            $this->help->redirect("/api/exams/" . $exam_id . "/create/courses/" . $course_id . "?page=questions");
        }

        $this->help->changeView('exams/create', ['course_id' => $course_id, 'exam' => $exam, 'course' => $course, 'courseQuestions' => $courseQuestions, 'questionsChoices' => $questionsChoices, 'examQuestions' => $fullExamQuestions, 'examQuestionsChoices' => $fullExamQuestionsChoices, "page" => $page])->render();
    }
}