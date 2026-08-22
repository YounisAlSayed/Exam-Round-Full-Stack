<?php

namespace App\Controllers;

use App\Controllers\Check_user;
use App\models\Choices;
use App\models\Exam_question;
use App\models\Questions;
use App\Utils\ViewModel;

class QuestionController
{
    // Router::post('/api/questions', ['QuestionController', 'addQuestion']);
    public function addQuestion()
    {
        $course_id = (int) $_POST['course_id'];
        $question = $_POST['question'];

        $choices = $_POST['choices'] ?? [];
        $correct = (int) $_POST['correct'];

        $exam_id = (int) $_POST['exam_id'];
        $question_mark = (float) $_POST['question_mark'];

        $authError = Check_user::checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }
        if (!$course_id || !$question || !$choices || $correct < 0 || !$exam_id || !$question_mark) {
            http_response_code(400);
            return new ViewModel('questions/create/display', ['error' => 'Incomplete input']);
        }
        $question_id = Questions::create($course_id, $question);
        if (!$question_id) {
            http_response_code(500);
            return new ViewModel('questions/create/display', ['error' => 'Internal Server Error']);
        }
        if (!Exam_question::create($exam_id, $question_id, $question_mark)) {
            http_response_code(500);
            return new ViewModel('questions/create/display', ['error' => 'Internal Server Error']);
        }
        foreach ($choices as $index => $choice) {
            if (!Choices::create($question_id, $choice, $index === $correct ? 1 : 0)) {
                http_response_code(500);
                return (new ViewModel('questions/create/display', ['error' => 'Internal Server Error']));
            }
        }
        $_SESSION['flash'] = "Created the question Successfully";
        header("Location: /api/questions/create/display");
        exit;
    }

    // Router::post('/api/questions/{id}/choices', ['QuestionController', 'updateQuestionChoices']);
    public function addQuestionChoices(string $question_id)
    {
        $question_id = (int) $question_id;
        $exam_id = (int) $_GET['exam_id'] ?? null;
        if (!$question_id || !$exam_id) {
            http_response_code(400);
            return (new ViewModel('exam/questions', ['error' => 'Question ID not passed']));
        }
        $auth = Check_user::checkTeacherCredentials();
        if ($auth !== null) {
            return $auth;
        }

        $choices = $_POST['choices'];
        foreach ($choices as $choice) {
            if (!Choices::create($question_id, $choice['text'], $choice['is_correct'])) {
                http_response_code(500);
                return new ViewModel(['questions/choices/create'], ['error' => 'Internal Server Error']);
            }
        }
        $_SESSION['flash'] = 'Added the question choices successfully';
        header("Location: /api/exam/questions?exam_id=$exam_id");
        exit;
    }

    // Router::put('/api/questions/{id}', ['QuestionController', 'editQuestion']);
    public function editQuestion($question_id)
    {
        $question_id = (int) $question_id;
        $course_id = (int) $_POST['course_id'] ?? null;
        $question = $_POST['question'] ?? null;
        $auth = Check_user::checkTeacherCredentials();
        if ($auth !== null) {
            return $auth;
        }
        if (!$question_id || !$course_id) {
            http_response_code(400);
            return new ViewModel('dashboard', ['error', 'Question ID or course ID not passed']);
        }
        if (!$question) {
            http_response_code(400);
            return new ViewModel('questions/edit', ['error' => 'Please fill the Question Text', 'question_id' => $question_id]);
        }
        if (!Questions::update($question_id, $question)) {
            http_response_code(500);
            return new ViewModel('questions/edit', ['error' => 'Internal Server Error']);
        }
        $_SESSION['flash'] = 'Updated the question successfully';
        header("Location: /api/questions/course?$course_id");
        exit;
    }

    // Router::put('/api/questions/{id}/choices', ['QuestionController', 'editQuestionChoices']);
    public function editQuestionChoices($question_id)
    {
        $auth = Check_user::checkTeacherCredentials();
        if ($auth !== null) {
            return $auth;
        }

        $question_id = (int) $question_id;
        $course_id = (int) $_GET['course_id'] ?? null;
        if (!$question_id || !$course_id) {
            http_response_code(400);
            return new ViewModel('dashboard', ['error' => 'Question ID was not passed or Course ID']);
        }

        $choices = $_POST['choices'];
        if (!Choices::reset($question_id)) {
            http_response_code(500);
            return new ViewModel('questions/choices', ['error' => 'Internal Server Error', 'question_id' => $question_id]);
        }
        foreach ($choices as $choice) {
            if (!Choices::edit($choice['id'], $choice['text'], $choice['is_correct'])) {
                http_response_code(500);
                return new ViewModel('questions/choices', ['error' => 'Internal Server Error', 'question_id' => $question_id]);
            }
        }

        $_SESSION['flash'] = 'successfully updated the question choices';
        header("Location: /api/questions/course?course_id=$course_id");
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
        $auth = Check_user::checkTeacherCredentials();
        if ($auth !== null)
            return $auth;

        if (!Questions::getByID($question_id)) {
            http_response_code(500);
            return (new ViewModel('courses/questions', ['error' => 'Internal Server error', 'course_id' => $course_id]));
        }

        if (!Questions::delete($question_id)) {
            http_response_code(500);
            return new ViewModel('courses/questions', ['error' => 'Internal Server Error', 'course_id' => $course_id]);
        }
        $_SESSION['flash'] = 'Successfully deleted the question';
        header("Location: /api/course/questions?course_id=$course_id");
        exit;
    }
}