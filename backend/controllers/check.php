<?php

namespace App\Controllers;

use App\models\User;
use App\Utils\ViewModel;

class Check
{
    private User $user;
    private ViewModel $viewModel;
    public function __construct()
    {
        $this->user = new User();
        $this->viewModel = new ViewModel('');
    }

    public function changeView($viewName, $data = [])
    {
        $this->viewModel->__construct($viewName, $data);
        return $this->viewModel;
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
            $this->changeView('users/login', ['error' => "user Not Logged in"])->render();
            return false;
        }
        $this->checkUserExistence($user['id']);
        if ($user['role'] !== 'teacher') {
            http_response_code(403);
            $this->changeView('dashboard', ['error' => "User Is Forbidden From Entering this page"])->render();
            return false;
        }
        return true;
    }

    public function checkStudentCredentials()
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            http_response_code(400);
            $this->changeView('users/login', ['error' => "user Not Logged in"])->render();
            return false;
        }
        $this->checkUserExistence($user['id']);
        if ($user['role'] !== 'student') {
            http_response_code(403);
            $this->changeView('dashboard', ['error' => "User Is Forbidden From Entering this page"])->render();
            return false;
        }
        return true;
    }

    public function checkUserExistence($user_id)
    {
        if (!$this->user->find($user_id)) {
            http_response_code(404);
            $this->changeView('users/signup', ['error' => 'no such user']);
            return false;
        }
        return true;
    }
}