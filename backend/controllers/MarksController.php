<?php

namespace App\Controllers;

use App\models\Marks;
use App\Utils\ViewModel;

class MarksController
{
    private Marks $marks;
    public function __construct()
    {
        $this->marks = new Marks();
    }
    // Router::get('/api/marks/student/{id}/average', ['MarksController', 'getStudentMarks']);
    public function getStudentMarks($student_id)
    {
        $student_id = (int) $student_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        if ((int) $currentUser['id'] !== $student_id && $currentUser['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('marks/forbidden', []);
        }

        $average = $this->marks->getStudentAverage($student_id);
        return new ViewModel('marks/student-average', ['average' => $average['average'], 'student_id' => $student_id]);
    }

    // Router::get('/api/marks/course/{id}/average', ['MarksController', 'getCourseAverage']);
    public function getCourseAverage($course_id)
    {
        $course_id = (int) $course_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        $average = $this->marks->getCourseAverage($course_id);
        return new ViewModel('marks/course-average', ['average' => $average['average'], 'course_id' => $course_id]);
    }

    // Router::get('/api/marks/student/{student_id}/course/{course_id}', ['MarksController', 'getStudentCourseMarks']);
    public function getStudentCourseMarks($student_id, $course_id)
    {
        $student_id = (int) $student_id;
        $course_id = (int) $course_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        if ((int) $currentUser['id'] !== $student_id && $currentUser['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('marks/forbidden', []);
        }

        $marks = $this->marks->getStudentCourseMarks($student_id, $course_id);
        return new ViewModel('marks/student-course', ['marks' => $marks]);
    }

    // Router::get('/api/marks/student/{student_id}/exam/{exam_id}', ['MarksController', 'getStudentExamMark']);
    public function getStudentExamMark($student_id, $exam_id)
    {
        $student_id = (int) $student_id;
        $exam_id = (int) $exam_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        if ((int) $currentUser['id'] !== $student_id && $currentUser['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('marks/forbidden', []);
        }

        $mark = $this->marks->find($student_id, $exam_id);

        if (!$mark) {
            http_response_code(404);
            return new ViewModel('marks/not-found', ['student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        return new ViewModel('marks/student-exam', ['mark' => $mark]);
    }

    // Router::post('/api/marks/student/{student_id}/exam/{exam_id}', ['MarksController', 'addStudentMark']);
    public function addStudentMark($student_id, $exam_id)
    {
        $authError = Check_user::checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $student_id = (int) $student_id;
        $exam_id = (int) $exam_id;
        $student_mark = $_POST['student_mark'] ?? null;

        if ($student_mark === null || !is_numeric($student_mark)) {
            http_response_code(400);
            return new ViewModel('marks/add', ['error' => 'A numeric mark is required']);
        }

        $student_mark = (int) $student_mark;

        if ($student_mark < 0 || $student_mark > 100) {
            http_response_code(422);
            return new ViewModel('marks/add', ['error' => 'Mark must be between 0 and 100']);
        }

        if ($this->marks->find($student_id, $exam_id)) {
            http_response_code(422);
            return new ViewModel('marks/add', ['error' => 'A mark for this student/exam already exists — use edit instead']);
        }

        if (!$this->marks->create($student_id, $exam_id, $student_mark)) {
            http_response_code(500);
            return new ViewModel('marks/add', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Mark recorded successfully';
        header('Location: /api/marks/student/' . $student_id . '/exam/' . $exam_id);
        exit;
    }

    // Router::put('/api/marks/student/{student_id}/exam/{exam_id}', ['MarksController', 'edit']);
    public function edit($student_id, $exam_id)
    {
        $authError = Check_user::checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $student_id = (int) $student_id;
        $exam_id = (int) $exam_id;

        if (!$this->marks->find($student_id, $exam_id)) {
            http_response_code(404);
            return new ViewModel('marks/not-found', ['student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        parse_str(file_get_contents('php://input'), $data);
        $student_mark = $data['student_mark'] ?? null;

        if ($student_mark === null || !is_numeric($student_mark)) {
            http_response_code(400);
            return new ViewModel('marks/student-exam', ['error' => 'A numeric mark is required']);
        }

        $student_mark = (int) $student_mark;

        if ($student_mark < 0 || $student_mark > 100) {
            http_response_code(422);
            return new ViewModel('marks/student-exam', ['error' => 'Mark must be between 0 and 100']);
        }

        if (!$this->marks->edit($student_id, $exam_id, $student_mark)) {
            http_response_code(500);
            return new ViewModel('marks/student-exam', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Mark updated successfully';
        header('Location: /api/marks/student/' . $student_id . '/exam/' . $exam_id);
        exit;
    }
}
