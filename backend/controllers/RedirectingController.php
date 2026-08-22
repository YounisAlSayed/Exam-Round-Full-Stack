<?php

namespace App\Controllers;

use App\Utils\ViewModel;
use App\models\User;
use App\models\Questions;
use App\models\Courses;
use App\models\Exams;

class RedirectingController
{
    public function dashboard()
    {
        $courses = [];
        $nextExamSet = [];
        $user = $_SESSION['user'];
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
}