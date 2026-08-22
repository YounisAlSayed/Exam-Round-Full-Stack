<?php

namespace App\Controllers;

use App\models\Exams;
use App\Utils\ViewModel;

class ExamsController
{
    // Router::get('/api/exams', ['ExamsController', 'getAll']);
    public function getAll()
    {
        return new ViewModel('exams/list', ['exams' => Exams::all()]);
    }

    // Router::get('/api/exams/{id}', ['ExamsController', 'getById']);
    public function getById($exam_id)
    {
        $exam_id = (int) $exam_id;
        $exam = Exams::find($exam_id);

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
        $exams = Exams::getByTeacher($teacher_id);
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

        $questions = Exams::getRandomQuestions($course_id, $count);
        return new ViewModel('exams/random-preview', ['questions' => $questions]);
    }

    // Router::get('/api/exams/course/{id}', ['ExamsController', 'getNextCourseExam']);
    public function getNextCourseExam($course_id)
    {
        $course_id = (int) $course_id;
        $exam = Exams::getNextCourseExam($course_id);

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
        $exam = Exams::find($exam_id);

        if (!$exam) {
            http_response_code(404);
            return new ViewModel('exams/not-found', ['id' => $exam_id]);
        }

        $questions = Exams::getExamQuestions($exam_id);
        return new ViewModel('exams/questions', ['exam' => $exam, 'questions' => $questions]);
    }

    // Router::post('/api/exams', ['ExamsController', 'add']);
    public function add()
    {
        $authError = Check_user::checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $currentUser = $_SESSION['user'];

        $title = $_POST['title'] ?? null;
        $courses_id = (int) ($_POST['courses_id'] ?? 0);
        $status = $_POST['status'] ?? 'not_ready';
        $total_marks = (int) ($_POST['total_marks'] ?? 0);
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $randomize_order = isset($_POST['randomize_order']) ? 1 : 0;

        if (!$title || !$courses_id || !$total_marks || !$start_date || !$end_date) {
            http_response_code(400);
            return new ViewModel('exams/create', ['error' => 'Missing required fields']);
        }

        if ($total_marks <= 0 || $total_marks > 100) {
            http_response_code(422);
            return new ViewModel('exams/create', ['error' => 'Total marks must be between 1 and 100']);
        }

        if (!in_array($status, ['not_ready', 'ready', 'in_progress', 'completed'])) {
            http_response_code(422);
            return new ViewModel('exams/create', ['error' => 'Invalid status']);
        }

        if (strtotime($end_date) <= strtotime($start_date)) {
            http_response_code(422);
            return new ViewModel('exams/create', ['error' => 'End date must be after start date']);
        }

        $examId = Exams::create(
            $title,
            $courses_id,
            $status,
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
        header('Location: /api/exams/' . $examId);
        exit;
    }

    // Router::post('/api/exams/course/{id}', ['ExamsController', 'setNextCourseExam']);
    public function setNextCourseExam($course_id)
    {
        $authError = Check_user::checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $course_id = (int) $course_id;
        $exam_id = (int) ($_POST['exam_id'] ?? 0);

        if (!$exam_id) {
            http_response_code(400);
            return new ViewModel('exams/list', ['error' => 'exam_id is required']);
        }

        $exam = Exams::find($exam_id);
        if (!$exam) {
            http_response_code(404);
            return new ViewModel('exams/list', ['error' => 'Exam not found']);
        }

        if (!Exams::setNextCourseExam($course_id, $exam_id)) {
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
        $authError = Check_user::checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $exam_id = (int) $exam_id;
        $exam = Exams::find($exam_id);

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

        if (!Exams::edit($exam_id, $title, $status, $total_marks, $start_date, $end_date, $randomize_order)) {
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
        $authError = Check_user::checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $exam_id = (int) $exam_id;

        if (!Exams::find($exam_id)) {
            http_response_code(404);
            return new ViewModel('exams/list', ['error' => 'Exam not found']);
        }

        if (!Exams::delete($exam_id)) {
            http_response_code(500);
            return new ViewModel('exams/list', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Exam deleted successfully';
        header('Location: /api/exams');
        exit;
    }
}