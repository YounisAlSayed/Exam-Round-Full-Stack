<?php

namespace App\Controllers;

use App\models\Attempts;
use App\models\Choices;
use App\models\Courses;
use App\models\Exam_question;
use App\models\Exams;
use App\models\StudentsAnswers;
use App\Utils\ViewModel;

class ExamsController
{
    private Exams $exams;
    private Courses $courses;
    private Attempts $attempts;
    private Choices $choices;
    private Exam_question $exam_question;
    private StudentsAnswers $studentsAnswers;
    private Check_user $auth;
    public function __construct()
    {
        $this->exams = new Exams();
        $this->courses = new Courses();
        $this->attempts = new Attempts();
        $this->choices = new Choices();
        $this->exam_question = new Exam_question();
        $this->studentsAnswers = new StudentsAnswers();
        $this->auth = new Check_user();
    }
    // Router::get('/api/exams', ['ExamsController', 'getAll']);
    public function getAll()
    {
        return new ViewModel('exams/list', ['exams' => $this->exams->all()]);
    }

    // Router::get('/api/exams/{id}', ['ExamsController', 'getById']);
    public function getById($exam_id)
    {
        $exam_id = (int) $exam_id;
        $exam = $this->exams->find($exam_id);

        if (!$exam) {
            http_response_code(404);
            return new ViewModel('exams/not-found', ['id' => $exam_id]);
        }

        return new ViewModel('exams/show', ['exam' => $exam]);
    }

    // Router::get('/api/exams/teacher/{id}', ['ExamsController', 'getTeacherExams']);
    public function getTeacherExams($teacher_id)
    {
        $teacher_id = (int) $teacher_id;
        $exams = $this->exams->getByTeacher($teacher_id);
        return new ViewModel('exams/list', ['exams' => $exams]);
    }

    // Router::get('/api/exams/random', ['ExamsController', 'generateRandom']);
    // GET has no side effects here — this previews N random questions from a course.
    // Expects ?course_id=X&count=Y as query params.
    public function generateRandom()
    {
        $course_id = (int) ($_GET['course_id'] ?? 0);
        $count = (int) ($_GET['count'] ?? 10);

        if (!$course_id || $count < 1) {
            http_response_code(400);
            return new ViewModel('exams/random-preview', ['error' => 'course_id and count are required']);
        }

        $questions = $this->exams->getRandomQuestions($course_id, $count);
        return new ViewModel('exams/random-preview', ['questions' => $questions]);
    }

    // Router::get('/api/exams/course/{id}', ['ExamsController', 'getNextCourseExam']);
    public function getNextCourseExam($course_id)
    {
        $course_id = (int) $course_id;
        $exam = $this->exams->getNextCourseExam($course_id);

        if (!$exam) {
            http_response_code(404);
            return new ViewModel('exams/not-found', ['id' => null, 'error' => 'No upcoming exam set for this course']);
        }

        return new ViewModel('exams/show', ['exam' => $exam]);
    }

    // Router::get('/api/exams/{id}/questions', ['ExamsController', 'getExamQuestions']);
    public function getExamQuestions($exam_id)
    {
        $exam_id = (int) $exam_id;
        $exam = $this->exams->find($exam_id);

        if (!$exam) {
            http_response_code(404);
            return new ViewModel('exams/not-found', ['id' => $exam_id]);
        }

        $questions = $this->exams->getExamQuestions($exam_id);
        return new ViewModel('exams/questions', ['exam' => $exam, 'questions' => $questions]);
    }

    // Router::post('/api/exams', ['ExamsController', 'add']);
    public function create($course_id)
    {
        if (!$course_id) {
            http_response_code(400);
            return new ViewModel('dashboard', ['error' => 'course ID was not Passed']);
        }
        $course_id = (int) $course_id;
        $authError = $this->auth->checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }
        if (!$this->courses->find($course_id)) {
            http_response_code(404);
            return new ViewModel('dashboard', ['error' => 'Course Not Found']);
        }

        $currentUser = $_SESSION['user'];

        $title = $_POST['title'] ?? null;
        $total_marks = (int) ($_POST['total_marks'] ?? 0);
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $randomize_order = isset($_POST['randomize']) ? 1 : 0;

        if (!$title || !$course_id || !$total_marks || !$start_date || !$end_date) {
            http_response_code(400);
            return new ViewModel('exams/create', ['error' => 'Missing required fields']);
        }

        if ($total_marks <= 0 || $total_marks > 100) {
            http_response_code(422);
            return new ViewModel('exams/create', ['error' => 'Total marks must be between 1 and 100']);
        }

        if (strtotime($end_date) <= strtotime($start_date)) {
            http_response_code(422);
            return new ViewModel('exams/create', ['error' => 'End date must be after start date']);
        }

        $examId = $this->exams->create(
            $title,
            $course_id,
            $total_marks,
            (int) $currentUser['id'],
            $start_date,
            $end_date,
            $randomize_order
        );

        if (!$examId) {
            http_response_code(500);
            return new ViewModel('exams/create', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Exam created successfully';
        header('Location: ' . BASE_PATH . '/api/exams/' . $examId . '/create/courses/' . $course_id);
        exit;
    }

    // Router::post('/api/exams/course/{id}', ['ExamsController', 'setNextCourseExam']);
    public function setNextCourseExam($course_id)
    {
        $authError = $this->auth->checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $course_id = (int) $course_id;
        $exam_id = (int) ($_POST['exam_id'] ?? 0);

        if (!$exam_id) {
            http_response_code(400);
            return new ViewModel('exams/list', ['error' => 'exam_id is required']);
        }

        $exam = $this->exams->find($exam_id);
        if (!$exam) {
            http_response_code(404);
            return new ViewModel('exams/list', ['error' => 'Exam not found']);
        }

        if (!$this->exams->setNextCourseExam($course_id, $exam_id)) {
            http_response_code(500);
            return new ViewModel('exams/list', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Next exam updated';
        header('Location: /api/exams/course/' . $course_id);
        exit;
    }

    // Router::put('/api/exams/{id}', ['ExamsController', 'edit']);
    public function edit($exam_id)
    {
        $authError = $this->auth->checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $exam_id = (int) $exam_id;
        $exam = $this->exams->find($exam_id);

        if (!$exam) {
            http_response_code(404);
            return new ViewModel('exams/edit', ['error' => 'Exam not found']);
        }

        parse_str(file_get_contents('php://input'), $data);

        $title = $data['title'] ?? null;
        $status = $data['status'] ?? null;
        $total_marks = (int) ($data['total_marks'] ?? 0);
        $start_date = $data['start_date'] ?? null;
        $end_date = $data['end_date'] ?? null;
        $randomize_order = isset($data['randomize_order']) ? 1 : 0;

        if (!$title || !$status || !$total_marks || !$start_date || !$end_date) {
            http_response_code(400);
            return new ViewModel('exams/edit', ['error' => 'Missing required fields', 'exam' => $exam]);
        }

        if ($total_marks <= 0 || $total_marks > 100) {
            http_response_code(422);
            return new ViewModel('exams/edit', ['error' => 'Total marks must be between 1 and 100', 'exam' => $exam]);
        }

        if (!in_array($status, ['not_ready', 'ready', 'in_progress', 'completed'])) {
            http_response_code(422);
            return new ViewModel('exams/edit', ['error' => 'Invalid status', 'exam' => $exam]);
        }

        if (strtotime($end_date) <= strtotime($start_date)) {
            http_response_code(422);
            return new ViewModel('exams/edit', ['error' => 'End date must be after start date', 'exam' => $exam]);
        }

        if (!$this->exams->edit($exam_id, $title, $status, $total_marks, $start_date, $end_date, $randomize_order)) {
            http_response_code(500);
            return new ViewModel('exams/edit', ['error' => 'Internal Server Error', 'exam' => $exam]);
        }

        $_SESSION['flash'] = 'Exam updated successfully';
        header('Location: /api/exams/' . $exam_id);
        exit;
    }

    // Router::delete('/api/exams/{id}', ['ExamsController', 'delete']);
    public function delete($exam_id)
    {
        $authError = $this->auth->checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $exam_id = (int) $exam_id;

        if (!$this->exams->find($exam_id)) {
            http_response_code(404);
            return new ViewModel('exams/list', ['error' => 'Exam not found']);
        }

        if (!$this->exams->delete($exam_id)) {
            http_response_code(500);
            return new ViewModel('exams/list', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Exam deleted successfully';
        header('Location: /api/exams');
        exit;
    }

    public function saveProgress($exam_id)
    {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_PATH . '/api/login');
            exit;
        }

        $action = $_POST['action'] ?? 'next';
        $currentPage = (int)($_POST['current_page'] ?? 1);
        $totalPages = (int)($_POST['total_pages'] ?? 1);

        // Save answers to session
        if (!isset($_SESSION['exam_answers'])) {
            $_SESSION['exam_answers'] = [];
        }
        $examAnswers = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $question_id = (int)str_replace('question_', '', $key);
                $_SESSION['exam_answers'][$exam_id][$question_id] = (int) $value;
            }
        }

        // Redirect
        if ($action === 'previous' && $currentPage > 1) {
            $nextPage = $currentPage - 1;
        } elseif ($action === 'next' && $currentPage < $totalPages) {
            $nextPage = $currentPage + 1;
        } else {
            $nextPage = $currentPage;
        }

        header('Location: ' . BASE_PATH . '/api/exams/' . $exam_id . '/start/' . $nextPage);
        exit;
    }

    public function submitExam($exam_id)
    {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_PATH . '/api/login');
            exit;
        }

        // Get saved answers from session
        $answers = $_SESSION['exam_answers'][$exam_id] ?? [];
        $student_id = $_SESSION['user']['id'];

        // Process and save answers
        $totalMarks = 0;
        $earnedMarks = 0;

        foreach ($answers as $questionId => $choiceId) {
            // Save student answer
            $this->studentsAnswers->create($student_id, $exam_id, $questionId, $choiceId);

            // Check if correct
            $choice = $this->choices->getById($choiceId);
            if ($choice && $choice['is_correct']) {
                $question = $this->exam_question->getQuestionMark($exam_id, $questionId);
                $earnedMarks += $question['question_mark'];
            }
        }

        // Get total marks
        $totalMarks = ($this->exams->getTotalMarks($exam_id))['total_marks'];

        // Save attempt
        $this->attempts->updateSubmitted($exam_id, $student_id, $earnedMarks);

        // Clear session answers
        unset($_SESSION['exam_answers'][$exam_id]);

        $_SESSION['flash'] = 'Exam submitted successfully!';
        header('Location: ' . BASE_PATH . '/api/exams/' . $exam_id . '/details');
        exit;
    }
}