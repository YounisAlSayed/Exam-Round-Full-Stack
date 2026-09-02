<?php

namespace App\Controllers;

use App\models\Attempts;
use App\models\StudentsAnswers;
use App\Utils\ViewModel;

class StudentsAnswersController
{
    private StudentsAnswers $studentsAnswers;
    private Attempts $attempts;
    private Check $auth;
    public function __construct()
    {
        $this->studentsAnswers = new StudentsAnswers();
        $this->attempts = new Attempts();
        $this->auth = new Check();
        $this->auth->unsetAll();
    }
    // Router::get('/api/students/{student_id}/exams/{exam_id}/answers', ['StudentsAnswersController', 'getStudentsExamAnswers']);
    public function getStudentExamAnswers($student_id, $exam_id)
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
            return new ViewModel('answers/forbidden', []);
        }

        $answers = $this->studentsAnswers->getByStudentExam($student_id, $exam_id);
        return new ViewModel('answers/list', ['answers' => $answers, 'student_id' => $student_id, 'exam_id' => $exam_id]);
    }

    // Router::post('/api/students/{student_id}/exams/{exam_id}/question/{question_id}/answers', ['StudentsAnswersController', 'addStudentExamAnswers']);
    public function addStudentExamAnswers($student_id, $exam_id, $question_id)
    {
        $student_id = (int) $student_id;
        $exam_id = (int) $exam_id;
        $question_id = (int) $question_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        // A student can only ever answer for themselves.
        if ((int) $currentUser['id'] !== $student_id && $currentUser['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('answers/forbidden', []);
        }

        $selected_choice_id = (int) ($_POST['selected_choice_id'] ?? 0);

        if (!$selected_choice_id) {
            http_response_code(400);
            return new ViewModel('answers/list', ['error' => 'selected_choice_id is required', 'student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        $attempt = $this->attempts->findByExamAndStudent($exam_id, $student_id);

        if (!$attempt) {
            http_response_code(422);
            return new ViewModel('answers/list', ['error' => 'No attempt has been started for this exam', 'student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        if ($attempt['submitted_at'] !== null) {
            http_response_code(422);
            return new ViewModel('answers/list', ['error' => 'This attempt has already been submitted', 'student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        if (!$this->studentsAnswers->questionBelongsToExam($exam_id, $question_id)) {
            http_response_code(404);
            return new ViewModel('answers/list', ['error' => 'This question does not belong to this exam', 'student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        if (!$this->studentsAnswers->choiceBelongsToQuestion($selected_choice_id, $question_id)) {
            http_response_code(422);
            return new ViewModel('answers/list', ['error' => 'That choice does not belong to this question', 'student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        if ($this->studentsAnswers->find($student_id, $exam_id, $question_id)) {
            http_response_code(422);
            return new ViewModel('answers/list', ['error' => 'This question has already been answered — use edit instead', 'student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        if (!$this->studentsAnswers->create($student_id, $exam_id, $question_id, $selected_choice_id)) {
            http_response_code(500);
            return new ViewModel('answers/list', ['error' => 'Internal Server Error', 'student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        $_SESSION['flash'] = 'Answer saved';
        header('Location: /api/students/' . $student_id . '/exams/' . $exam_id . '/answers');
        exit;
    }

    // Router::put('/api/students/{student_id}/exams/{exam_id}/questions/{question_id}/answers', ['StudentsAnswersController', 'edit']);
    public function edit($student_id, $exam_id, $question_id)
    {
        $student_id = (int) $student_id;
        $exam_id = (int) $exam_id;
        $question_id = (int) $question_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        if ($currentUser['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('answers/forbidden', []);
        }

        $attempt = $this->attempts->findByExamAndStudent($exam_id, $student_id);

        if (!$attempt) {
            http_response_code(422);
            return new ViewModel('answers/list', ['error' => 'No attempt has been started for this exam', 'student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        if ($attempt['submitted_at'] !== null) {
            http_response_code(422);
            return new ViewModel('answers/list', ['error' => 'This attempt has already been submitted', 'student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        if (!$this->studentsAnswers->find($student_id, $exam_id, $question_id)) {
            http_response_code(404);
            return new ViewModel('answers/list', ['error' => 'No existing answer to update — use add instead', 'student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        parse_str(file_get_contents('php://input'), $data);
        $selected_choice_id = (int) ($data['selected_choice_id'] ?? 0);

        if (!$selected_choice_id) {
            http_response_code(400);
            return new ViewModel('answers/list', ['error' => 'selected_choice_id is required', 'student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        if (!$this->studentsAnswers->choiceBelongsToQuestion($selected_choice_id, $question_id)) {
            http_response_code(422);
            return new ViewModel('answers/list', ['error' => 'That choice does not belong to this question', 'student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        if (!$this->studentsAnswers->edit($student_id, $exam_id, $question_id, $selected_choice_id)) {
            http_response_code(500);
            return new ViewModel('answers/list', ['error' => 'Internal Server Error', 'student_id' => $student_id, 'exam_id' => $exam_id]);
        }

        $_SESSION['flash'] = 'Answer updated';
        header('Location: /api/students/' . $student_id . '/exams/' . $exam_id . '/answers');
        exit;
    }
}