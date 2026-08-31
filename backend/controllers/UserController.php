<?php

namespace App\Controllers;

use App\models\User;
use App\Utils\ViewModel;

class UserController
{
    private User $user;
    public function __construct()
    {
        $this->user = new User();
    }
    // Router::get('/api/users{role}', ['UserController', 'getAll']);
    public function getAll()
    {
        if ($_SESSION['user']['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('users/dashboard', ['error' => 'Forbidden']);
        }
        return $this->user->all();
    }

    // Router::get('/api/users/{id}', ['UserController', 'getById']);
    public function getById(string $user_id)
    {
        $user_id = (int) $user_id;
        $currentUser = $_SESSION['user'] ?? null;
        if ($currentUser === null) {
            header('Location: /api/login');
            exit;
        }

        if ($currentUser['id'] !== $user_id && $currentUser['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('users/forbidden');
        }

        $user = $this->user->find($user_id);
        if (!$user) {
            http_response_code(404);
            return new ViewModel('users/notFound');
        }
        return $user;
    }

    // Router::post('/api/users/login', ['UserController', 'login']);
    public function login()
    {
        $_SESSION['flash'] = null;
        $user = $this->user->findByEmail($_POST['email']);
        if (!$user || !password_verify($_POST['password'], $user['password'])) {
            return new ViewModel('users/login', ['error' => 'Invalid email or password']);
        }

        $_SESSION['user'] = ["id" => $user["id"], "role" => $user['role']];
        $_SESSION['flash'] = "Login Successful";
        header("Location: " . BASE_PATH . "/api/dashboard");
        exit;
    }

    // Router::post('/api/users/signin', ['UserController', 'signin']);
    public function signup()
    {
        $_SESSION['flash'] = null;
        $first_name = trim($_POST['first_name']) ?? null;
        $last_name = trim($_POST['last_name']) ?? null;
        $email = trim($_POST['email']) ?? null;
        $password = trim($_POST['password']) ?? null;
        $password_conf = trim($_POST['password_conf']) ?? null;
        $role = trim($_POST['role']) ?? null;

        if (!$first_name || !$last_name || !$email || !$password || !$password_conf || !$role) {
            http_response_code(422);
            return new ViewModel('users/signup', ['error' => 'One or more fields empty']);
        }

        if ($password !== $password_conf) {
            http_response_code(422);
            return new ViewModel('users/signup', ['error' => 'Passwords DO Not Match']);
        }

        if (strlen($first_name) < 1 || strlen($first_name) > 32 || strlen($last_name) < 1 || strlen($last_name) > 32) {
            http_response_code(422);
            return new ViewModel('users/signup', ['error' => 'Name has to be between 1 and 32 characters']);
        }

        if (!in_array($role, ['teacher', 'student'])) {
            http_response_code(422);
            return new ViewModel('users/signup', ['error' => 'Undefined Role']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            return new ViewModel('users/signup', ['error' => 'Invalid Email Format']);
        }

        if ($this->user->findByEmail($email)) {
            http_response_code(422);
            return new ViewModel('users/signup', ['error' => 'Email Already exists']);
        }

        if (!$this->user->create($first_name, $last_name, $email, password_hash($password, PASSWORD_DEFAULT), $role)) {
            http_response_code(500);
            return new ViewModel('users/signup', ['error' => 'Internal Server Error']);
        }
        $user = $this->user->findByEmail($email);
        $_SESSION['user'] = ['id' => $user['id'], 'role' => $user['role']];
        $_SESSION['flash'] = 'signin Successful';
        header("Location:  " . BASE_PATH . "/api/dashboard");
        exit;
    }

    // Router::put('/api/users/{id}', ['UserController', 'edit']);
    public function edit()
    {
        $currentUser = $_SESSION['user'] ?? null;
        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        $user_id = (int) $currentUser['id'];
        $first_name = $_POST['first_name'] ?? null;
        $last_name = $_POST['last_name'] ?? null;
        $email = $_POST['email'] ?? null;
        $password = $_POST['password'] ?? null;
        $password_conf = $_POST['password_conf'] ?? null;

        $user = $this->user->find($user_id);
        if (!$user) {
            header('Location: /api/login');
            exit;
        }

        if (!$first_name || !$last_name || !$email || !$password) {
            http_response_code(422);
            return new ViewModel('users/profile', ['error' => 'One or more fields empty']);
        }

        if ($password !== $password_conf) {
            http_response_code(422);
            return new ViewModel('users/profile', ['error' => 'Passwords DO Not Match']);
        }

        if (strlen($first_name) < 1 || strlen($first_name) > 32 || strlen($last_name) < 1 || strlen($last_name) > 32) {
            http_response_code(422);
            return new ViewModel('users/profile', ['error' => 'Name has to be between 1 and 32 characters']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            return new ViewModel('users/profile', ['error' => 'Invalid Email Format']);
        }

        $user_email = $this->user->findByEmail($email);
        if ($user_email && $user_email['id'] !== $user_id) {
            http_response_code(422);
            return new ViewModel('users/profile', ['error' => 'Email Already exists']);
        }

        if (!$this->user->edit($user_id, $first_name, $last_name, $email, password_hash($password, PASSWORD_DEFAULT))) {
            http_response_code(500);
            return new ViewModel('users/profile', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Edit Successful';
        header("Location: /api/profile");
        exit;
    }

    // Router::delete('/api/user/{id}', ['UserController', 'delete']);
    public function delete(string $user_id)
    {
        $user_id = (int) $user_id;
        if (!$user_id) {
            http_response_code(400);
            return new ViewModel('users/list', ['error' => 'No User ID Passed']);
        }

        if (!$this->user->find($user_id)) {
            http_response_code(404);
            return new ViewModel('users/list', ['error' => 'User Not Found']);
        }

        if ($_SESSION['user']['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('dashboard', ['error' => 'Forbidden']);
        }
        if (!$this->user->delete($user_id)) {
            http_response_code(500);
            return new ViewModel('users/list', ['error' => 'Internal Server Error']);
        }
        header("Location: /api/users/list");
        exit;
    }
}
