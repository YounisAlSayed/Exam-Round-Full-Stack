<?php

namespace App\Controllers;

use App\models\Attempts;
use App\models\Exams;
use App\Utils\ViewModel;

class AttemptsController
{
    // Router::get('/api/attempts/student/{id}', ['AttemptsController', 'getStudentAttempt']);
    public function getStudentAttempt($student_id)
    {
        $student_id = (int) $student_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        if ((int) $currentUser['id'] !== $student_id && $currentUser['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('attempts/forbidden', []);
        }

        $attempts = Attempts::getByStudent($student_id);
        return new ViewModel('attempts/list', ['attempts' => $attempts, 'student_id' => $student_id]);
    }

    // Router::post('/api/attempts/student/{id}', ['AttemptsController', 'updateStudentAttempt']);
    // A student can only start/submit their OWN attempt (never on another student's behalf).
    // Body: exam_id (required), action = 'start' | 'submit' (defaults to 'start')
    public function updateStudentAttempt($student_id)
    {
        $student_id = (int) $student_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        if ((int) $currentUser['id'] !== $student_id) {
            http_response_code(403);
            return new ViewModel('attempts/forbidden', []);
        }

        $exam_id = (int) ($_POST['exam_id'] ?? 0);
        $action = $_POST['action'] ?? 'start';

        if (!$exam_id) {
            http_response_code(400);
            return new ViewModel('attempts/list', ['error' => 'exam_id is required', 'student_id' => $student_id]);
        }

        if (!in_array($action, ['start', 'submit'])) {
            http_response_code(400);
            return new ViewModel('attempts/list', ['error' => 'Invalid action', 'student_id' => $student_id]);
        }

        $exam = Exams::find($exam_id);
        if (!$exam) {
            http_response_code(404);
            return new ViewModel('attempts/list', ['error' => 'Exam not found', 'student_id' => $student_id]);
        }

        $existingAttempt = Attempts::findByExamAndStudent($exam_id, $student_id);

        if ($action === 'start') {
            if ($existingAttempt) {
                http_response_code(422);
                return new ViewModel('attempts/list', ['error' => 'Attempt already started for this exam', 'student_id' => $student_id]);
            }

            $attemptId = Attempts::start($exam_id, $student_id);

            if (!$attemptId) {
                http_response_code(500);
                return new ViewModel('attempts/list', ['error' => 'Internal Server Error', 'student_id' => $student_id]);
            }

            $_SESSION['flash'] = 'Attempt started';
            header('Location: /api/attempts/student/' . $student_id);
            exit;
        }

        // $action === 'submit'
        if (!$existingAttempt) {
            http_response_code(422);
            return new ViewModel('attempts/list', ['error' => 'No attempt has been started for this exam', 'student_id' => $student_id]);
        }

        if ($existingAttempt['submitted_at'] !== null) {
            http_response_code(422);
            return new ViewModel('attempts/list', ['error' => 'This attempt has already been submitted', 'student_id' => $student_id]);
        }

        if (!Attempts::submit((int) $existingAttempt['id'])) {
            http_response_code(500);
            return new ViewModel('attempts/list', ['error' => 'Internal Server Error', 'student_id' => $student_id]);
        }

        $_SESSION['flash'] = 'Attempt submitted';
        header('Location: /api/attempts/student/' . $student_id);
        exit;
    }

    // Router::delete('/api/attempts/{id}', ['AttemptsController', 'deleteStudentAttempt']);
    public function deleteStudentAttempt($attempt_id)
    {
        $authError = Check_user::checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $attempt_id = (int) $attempt_id;
        $attempt = Attempts::find($attempt_id);

        if (!$attempt) {
            http_response_code(404);
            return new ViewModel('attempts/not-found', ['id' => $attempt_id]);
        }

        if (!Attempts::delete($attempt_id)) {
            http_response_code(500);
            return new ViewModel('attempts/not-found', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Attempt deleted';
        header('Location: /api/attempts/student/' . $attempt['student_id']);
        exit;
    }
}