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
    }
    public function dashboard()
    {
        $courses = [];
        $nextExamSet = [];
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            http_response_code(404);
            $_SESSION['error'] = 'User Not Logged in';
            return $this->help->redirect('/api/login');
        }
        $user_id = $user['id'];
        if (!$user_id) {
            http_response_code(400);
            $_SESSION['error'] = 'User ID Not Found';
            return $this->help->redirect('/api/login');
        }
        if ($user['role'] === 'teacher') {
            $courses = $this->courses->getTeacherCourses((int) $user['id']);
            $nextExamSet = $this->user->getTeacherNextExamSet((int) $user['id']);
        } else if ($user['role'] === 'student') {
            $courses = $this->courses->getStudentCourses((int) $user['id']);
            $nextExamSet = $this->user->getStudentsNextExamSet((int) $user['id']);
        }
        return $this->help->changeView('dashboard', ['courses' => $courses, 'nextExamSet' => $nextExamSet]);
    }

    // -------------------------------- user --------------------------------------
    public function login()
    {
        $_SESSION['user'] = null;
        return $this->help->changeView('users/login');
    }

    public function signup()
    {
        return $this->help->changeView('users/signup');
    }

    public function logout()
    {
        $_SESSION['user'] = null;
        return $this->help->changeView('users/login');
    }

    public function profile()
    {
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            return $this->help->changeView('users/login');
        }

        $user = $this->user->find($currentUser['id']);
        unset($user['password']);

        return $this->help->changeView('users/profile', ['user' => $user]);
    }

    // public function usersList()
    // {
    //     $currentUser = $_SESSION['user'] ?? null;

    //     $authError = $this->help->checkTeacherCredentials();
    //     if ($authError !== null) {
    //         return $authError;
    //     }
    //     $usersList = $this->user->all();
    //     if ($usersList !== null) {
    //         $usersList = [];
    //     }
    //     return $this->help->changeView('users/list', ['usersList' => $usersList]);
    // }

    // ---------------------------------- questions ----------------------------------------
    public function createQuestion($course_id)
    {
        $course_id = (int) $course_id;
        if (!$course_id) {
            http_response_code(400);
            $_SESSION['error'] = 'Course ID Not Passed';
            header("Location: " . BASE_PATH . "/api/courses/teacher/" . $course_id);
            exit;
        }
        return $this->help->changeView('questions/preview', ['course_id' => $course_id]);
    }

    // ------------------------------ exam ----------------------------------

    public function studentExamDetails($exam_id)
    {
        if (!isset($_SESSION['user'])) {
            http_response_code(400);
            return $this->help->changeView("users/login", ['error' => 'User Not logged in']);
        }
        if (!$exam_id) {
            http_response_code(400);
            $_SESSION['error'] = 'Exam ID not Passed';
            $this->help->redirect("/api/dashboard");
        }
        $user = $_SESSION['user'];
        $examDetails = $this->exams->getExamFullDetails($exam_id);
        if (!$examDetails) {
            http_response_code(404);
            $_SESSION['error'] = 'Exam Not Found';
            $this->help->redirect("/api/dashboard");
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

        return $this->help->changeView('exams/studentExamDetails', ['examDetails' => $examDetails, 'studentAttempt' => $studentAttempt, 'studentSelection' => $studentSelection, 'questionsChoices' => $questionsChoices]);
    }

    public function teacherExamDetails($exam_id)
    {
        if (!isset($_SESSION['user'])) {
            http_response_code(400);
            $_SESSION['error'] = 'User Not Logged in';
            $this->help->redirect('users/login');
        }
        if (!$exam_id) {
            http_response_code(400);
            $_SESSION['error'] = 'Exam ID Not Passed';
            $this->help->redirect('/api/dashboard');
        }
        if (!$this->exams->getById($exam_id)) {
            http_response_code(404);
            $_SESSION['error'] = 'Exam Not Found';
            $this->help->redirect('/api/dashboard');
        }
        $user = $_SESSION['user'];
        if ($user['role'] !== 'teacher') {
            http_response_code(403);
            $_SESSION['error'] = 'User Does not have access to this page';
            $this->help->redirect('/api/dashboard');
        }

        $exam = $this->exams->getExamFullDetails($exam_id);
        if (!$exam) {
            http_response_code(404);
            $_SESSION['error'] = 'Exam Not found';
            $this->help->redirect('/api/dashboard');
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

        return $this->help->changeView('exams/teacherExamDetails', ['exam' => $exam, 'questions' => $examQuestions, 'questionsChoices' => $questionsChoices, 'examAttemptStatus' => $examAttemptStatus]);
    }

    public function examStart($exam_id, $page)
    {
        // $authError = $this->help->checkStudentCredentials();
        // if ($authError !== null) {
        //     return $authError;
        // }
        $exam_id = (int) $exam_id;
        $student_id = (int) $_SESSION['user']['id'];
        $exam = $this->exams->getById($exam_id);
        if (!$exam) {
            http_response_code(404);
            $_SESSION['error'] = "Exam not found.";
            return $this->help->redirect('/api/dashboard');
        }

        $attempt = $this->attempts->findByExamAndStudent($exam_id, $student_id);
        if ($attempt && $attempt['submitted_at'] !== null) {
            $_SESSION['error'] = "User Already took the exam";
            $this->help->redirect('/api/dashboard');
        }
        // if (time() < strtotime($exam['start_date']) || $exam['status'] === 'not_ready') {
        //     http_response_code(403);
        //     $_SESSION['error'] = "This exam is not available yet.";
        //     return $this->help->redirect('/api/dashboard');
        // }
        if (!$attempt) {
            if (time() >= strtotime($exam['end_date'])) {
                $this->help->redirect('/api/exams/' . $exam_id . '/details/student');
            }
            $this->attempts->start($exam_id, $student_id);
            $attempt = $this->attempts->findByExamAndStudent($exam_id, $student_id);
        }

        // Cache public question data per attempt; navigation never reloads question rows.
        $state = $_SESSION['exam_taking'][$student_id][$exam_id] ?? null;
        if (!$state || (int) $state['attempt_id'] !== (int) $attempt['id']) {
            $questions = Attempts::orderQuestions(
                $this->questions->getExamQuestions($exam_id),
                $attempt,
                !empty($exam['randomize_order'])
            );
            if (!$questions) {
                http_response_code(404);
                $_SESSION['error'] = "No questions were found for this exam.";
                return $this->help->redirect('/api/dashboard');
            }
            $questions = array_map(static fn($question) => [
                'id' => (int) $question['question_id'],
                'question' => $question['question_text'],
                'question_mark' => (float) $question['question_mark'],
            ], $questions);

            $state = [
                'attempt_id' => (int) $attempt['id'],
                'questions' => $questions,
                'choices' => $this->questions->getExamChoiceOptions($exam_id),
                'page_size' => 2,
            ];
            $_SESSION['exam_taking'][$student_id][$exam_id] = $state;
        }
        $totalPages = max(1, (int) ceil(count($state['questions']) / $state['page_size']));
        $page = max(1, min((int) $page, $totalPages));
        return $this->help->changeView('exams/start', [
            'exam' => $exam,
            'questions' => $state['questions'],
            'choices' => $state['choices'],
            'page' => $page,
            'pageSize' => $state['page_size'],
            'totalQuestions' => count($state['questions']),
            'savedAnswers' => $this->attempts->getSavedAnswers($exam_id, $student_id),
        ]);
    }

    public function teacherCourse($course_id)
    {
        if (!$course_id) {
            http_response_code(400);
            return $this->help->changeView('dashboard', ['error' => 'Course Not Passed']);
        }
        if (!isset($_SESSION['user'])) {
            http_response_code(400);
            return $this->help->changeView('users/login', ['error' => 'user Not Logged in']);
        }
        $user = $_SESSION['user'];
        if ($user['role'] !== 'teacher') {
            http_response_code(403);
            return $this->help->changeView('dashboard', ['error' => 'Forbidden For None Teachers']);
        }
        $course = $this->courses->find($course_id);
        if (!$course) {
            http_response_code(404);
            return $this->help->changeView('dashboard', ['error' => 'Course Not Found']);
        }
        $courseStudents = $this->courses->getCourseStudents($course_id) ?? [];
        $courseExams = $this->exams->getCourseExams($course_id) ?? [];
        $courseQuestions = $this->questions->getCourseQuestions($course_id) ?? [];

        return $this->help->changeView('courses/teacherCourse', ['course' => $course, 'courseStudents' => $courseStudents, 'courseExams' => $courseExams, 'courseQuestions' => $courseQuestions]);
    }

    public function examCreate($exam_id)
    {
        $course_id = (int) $_GET['course_id'] ?? null;
        $exam_id = (int) $exam_id;
        $page = $_GET['page'] ?? null;
        if (!$course_id || !$exam_id) {
            http_response_code(400);
            return $this->help->changeView('dashboard', ['error' => 'Course ID was not passed']);
        }
        if (!isset($_SESSION['user'])) {
            http_response_code(400);
            return $this->help->changeView('users/login', ['error' => 'User is Not logged in']);
        }
        $check = $this->help->checkTeacherCredentials();
        if ($check !== null) {
            return $check;
        }
        $course = $this->courses->find($course_id);
        if (!$course) {
            http_response_code(404);
            return $this->help->changeView('dashboard', ['error' => 'Course not Found']);
        }

        $exam = $this->exams->getById($exam_id);
        if (!$exam) {
            http_response_code(404);
            return $this->help->changeView('dashboard', ['error' => 'Exam Not Found']);
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

        return $this->help->changeView('exams/create', ['course_id' => $course_id, 'exam' => $exam, 'course' => $course, 'courseQuestions' => $courseQuestions, 'questionsChoices' => $questionsChoices, 'examQuestions' => $fullExamQuestions, 'examQuestionsChoices' => $fullExamQuestionsChoices, "page" => $page]);
    }
}
