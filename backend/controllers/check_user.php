<?php

namespace App\Controllers;

use App\Utils\ViewModel;

class Check_user
{
    public static function checkTeacherCredentials()
    {
        $user = $_SESSION['user'];
        if (!$user) {
            http_response_code(400);
            return new ViewModel('users/login', ['error' => "user Not Logged in"]);
        }
        if ($user['role'] !== 'teacher') {
            http_response_code(403);
            $_SESSION['error'] = "User Is Forbidden From Entering this page";
            header("Location: " . BASE_PATH . "/api/dashboard");
            exit;
        }
    }
}
