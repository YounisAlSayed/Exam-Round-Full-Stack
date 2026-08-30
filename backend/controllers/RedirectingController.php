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
            $courses = Courses::getTeacherCourses((int) $user['id']);
            $nextExamSet = User::getTeacherNextExamSet((int) $user['id']);
        } else if ($user['role'] === 'student') {
            $courses = Courses::getStudentCourses((int) $user['id']);
            $nextExamSet = User::getStudentsNextExamSet((int) $user['id']);
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
        $_SESSION['flash'] = 'Logout Successful';
        return new ViewModel('users/login');
    }

    public function profile()
    {
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: ' . BASE_PATH . '/api/users/login');
            exit;
        }

        $user = User::find($currentUser['id']);
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

        return (new ViewModel('users/list', ['users' => User::all()]));
    }

    // ---------------------------------- questions ----------------------------------------
    public function showExamQuestions(string $exam_id)
    {
        $exam_id = (int) $exam_id;
        if (!$exam_id) {
            http_response_code(400);
            return (new ViewModel('exam/all', ['error' => 'No Exam ID passed']));
        }

        if (!Exams::find($exam_id)) {
            http_response_code(404);
            return (new ViewModel('exam/all', ['error' => 'Exam Not Found']));
        }
        return (new ViewModel('exam/questions', ['questions' => Questions::getExamQuestions($exam_id)]));
    }

    public function createQuestion()
    {
        return (new ViewModel('questions/create'));
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
        $examDetails = Exams::getExamFullDetails($exam_id);
        if (!$examDetails) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'Exam Not Found']);
        }
        $studentAttempt = '';
        $studentSelection = '';
        if ($user['role'] === 'student') {
            $studentAttempt = Attempts::findByExamAndStudent($exam_id, $user['id']);
            if (!$studentAttempt) {
                $studentAttempt = [];
            }

            $studentSelection = Exam_question::getStudentExamSelection($exam_id, $user['id']);
            if (!$studentSelection) {
                $studentSelection = [];
            }
        }
        $examQuestions = Questions::getExamQuestions($exam_id);
        $questionsChoices = [];
        foreach ($examQuestions as $question) {
            $questionsChoices[$question['question_id']] = Questions::getQuestionChoices($question['question_id']);
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
        if (!Exams::getById($exam_id)) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'Exam Not Found']);
        }
        $user = $_SESSION['user'];
        if ($user['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('dashboard', ['error' => 'User Does not have access to this page']);
        }

        $exam = Exams::getExamFullDetails($exam_id);
        if (!$exam) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'Exam Not found']);
        }

        $examQuestions = Questions::getExamQuestions($exam_id);
        if (!$examQuestions) {
            $examQuestions = [];
        }
        $questionsChoices = [];
        foreach ($examQuestions as $question) {
            $questionsChoices[$question['question_id']] = Questions::getQuestionChoices($question['question_id']);
        }

        $examAttemptStatus = Attempts::getExamAttemptStats($exam_id);
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

        $exam = Exams::getExamFullDetails($exam_id);
        if (!$exam) {
            http_response_code(404);
            header('Location: ' . BASE_PATH . '/api/dashboard');
            exit;
        }

        $student_id = (int) $_SESSION['user']['id'];
        $exam_id = (int) $exam_id;

        // Create the attempt row the FIRST time the student reaches this exam —
        // find-or-create, so navigating between pages never creates duplicates.
        $attempt = Attempts::findByExamAndStudent($exam_id, $student_id);
        if (!$attempt) {
            Attempts::start($exam_id, $student_id);
        } elseif ($attempt['submitted_at'] !== null) {
            // Already submitted — don't let them re-enter and re-answer
            http_response_code(403);
            return new ViewModel('dashboard', ['error' => 'You have already submitted this exam']);
        }

        $size = 2;
        $offset = (int)($page - 1) * $size;
        $questions = Questions::getExamQuestionSet($exam_id, $offset, $size);
        if (!$questions) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'No Questions Were Found For the Specified Exam']);
        }
        $choices = [];
        foreach ($questions as $question) {
            $choices[$question['id']] = Questions::getQuestionChoices($question['id']);
        }
        $totalQuestions = Questions::getExamQuestionCount($exam_id);
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
        $course = Courses::find($course_id);
        if (!$course) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'Course Not Found']);
        }
        $courseStudents = Courses::getCourseStudents($course_id) ?? [];
        $courseExams = Exams::getCourseExams($course_id) ?? [];
        $courseQuestions = Questions::getCourseQuestions($course_id) ?? [];

        return new ViewModel('courses/teacherCourse', ['course' => $course, 'courseStudents' => $courseStudents, 'courseExams' => $courseExams, 'courseQuestions' => $courseQuestions]);
    }

    public function examCreate($exam_id, $course_id)
    {
        $course_id = (int) $course_id;
        $exam_id = (int) $exam_id;
        if (!$course_id || !$exam_id) {
            http_response_code(400);
            return new ViewModel('dashboard', ['error' => 'Course ID was not passed']);
        }
        if (!isset($_SESSION['user'])) {
            http_response_code(400);
            return new ViewModel('users/login', ['error' => 'User is Not logged in']);
        }
        $auth = Check_user::checkTeacherCredentials();
        if ($auth !== null) {
            return $auth;
        }
        $course = Courses::find($course_id);
        if (!$course) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'Course not Found']);
        }

        $exam = Exams::getById($exam_id);
        if (!$exam) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'Exam Not Found']);
        }

        $courseQuestions = Questions::getCourseQuestions($course_id);
        if (!$courseQuestions) {
            $courseQuestions = [];
        }

        $questionsChoices = [];
        foreach ($courseQuestions as $question) {
            $questionsChoices[$question['id']] = Questions::getQuestionChoices($question['id']);
        }
        if (!$questionsChoices)
            $questionsChoices = [];

        $draft_questions = Questions::getQuestionsDraft($_SESSION['questions_draft_ids'] ?? null) ?? [];
        if (!$draft_questions)
            $draft_questions = [];
        $draft_questions_choices = [];
        foreach ($draft_questions as $question) {
            $draft_questions_choices[$question['id']] = Questions::getQuestionChoices($question['id']);
        }
        if (!$draft_questions_choices)
            $draft_questions_choices = [];

        $examQuestions = Questions::getExamQuestions($exam_id);
        $examQuestionsChices = [];
        if (!$examQuestions) {
            $examQuestions = [];
        } else {
            foreach ($examQuestions as $question) {
                $examQuestionsChices[$question['question_id']] = Questions::getQuestionChoices($question['question_id']);
            }
        }
        if (!$examQuestionsChices)
            $examQuestionsChices = [];
        return new ViewModel('exams/create', ['course_id' => $course_id, 'exam' => $exam, 'course' => $course, 'courseQuestions' => $courseQuestions, 'questionsChoices' => $questionsChoices, 'draftQuestions' => $draft_questions, 'draftQuestionsChoices' => $draft_questions_choices, 'examQuestions' => $examQuestions, 'examQuestionsChoices' => $examQuestionsChices]);
    }
}
