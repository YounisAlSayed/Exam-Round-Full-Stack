<?php

namespace App\Controllers;

use App\models\User;
use App\Utils\ViewModel;

class Check
{
    private User $user;
    public function __construct()
    {
        $this->user = new User();
    }
    public function unsetAll()
    {
        unset($_SESSION['error']);
        unset($_SESSION['flash']);
        unset($_SESSION['redirect_place']);
    }

    public function redirect($endpoint = null)
    {
        header("Location: " . BASE_PATH . ($endpoint ?? '/api/dashboard'));
        exit;
    }
    public function checkTeacherCredentials()
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            http_response_code(400);
            return new ViewModel('users/login', ['error' => "user Not Logged in"]);
        }
        $this->checkUserExistence($user['id']);
        if ($user['role'] !== 'teacher') {
            http_response_code(403);
            $_SESSION['error'] = "User Is Forbidden From Entering this page";
            header("Location: " . BASE_PATH . "/api/dashboard");
            exit;
        }
    }

    public function checkStudentCredentials()
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            http_response_code(400);
            return new ViewModel('users/login', ['error' => "user Not Logged in"]);
        }
        $this->checkUserExistence($user['id']);
        if ($user['role'] !== 'student') {
            http_response_code(403);
            $_SESSION['error'] = "User Is Forbidden From Entering this page";
            header("Location: " . BASE_PATH . "/api/dashboard");
            exit;
        }
    }

    public function checkUserExistence($user_id)
    {
        if (!$this->user->find($user_id)) {
            http_response_code(404);
            $_SESSION['error'] = "User Not Found";
            header("Location: " . BASE_PATH . "/api/login");
            exit;
        }
    }
}