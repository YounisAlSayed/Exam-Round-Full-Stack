<?php

namespace App\Controllers;

use App\models\Courses;
use App\Utils\ViewModel;

class CoursesController
{
    // Router::get('/api/courses/{id}/students', ['CoursesController', 'getCourseStudents']);
    public function getCourseStudents($course_id)
    {
        $course_id = (int) $course_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        $course = Courses::find($course_id);
        if (!$course) {
            http_response_code(404);
            return new ViewModel('courses/not-found', ['id' => $course_id]);
        }

        $students = Courses::getCourseStudents($course_id);
        return new ViewModel('courses/students', ['course' => $course, 'students' => $students]);
    }

    // Router::get('/api/courses/{id}/teachers', ['CoursesController', 'getCourseTeachers']);
    public function getCourseTeachers($course_id)
    {
        $course_id = (int) $course_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        $course = Courses::find($course_id);
        if (!$course) {
            http_response_code(404);
            return new ViewModel('courses/not-found', ['id' => $course_id]);
        }

        $teachers = Courses::getCourseTeachers($course_id);
        return new ViewModel('courses/teachers', ['course' => $course, 'teachers' => $teachers]);
    }

    // Router::post('/api/courses', ['CoursesController', 'add']);
    public function add()
    {
        $authError = Check_user::checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            http_response_code(400);
            return new ViewModel('courses/create', ['error' => 'Course name is required']);
        }

        if (strlen($name) > 100) {
            http_response_code(422);
            return new ViewModel('courses/create', ['error' => 'Course name must be 100 characters or fewer']);
        }

        if (Courses::findByName($name)) {
            http_response_code(422);
            return new ViewModel('courses/create', ['error' => 'A course with this name already exists']);
        }

        $courseId = Courses::create($name);

        if (!$courseId) {
            http_response_code(500);
            return new ViewModel('courses/create', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Course created successfully';
        header('Location: /api/courses/' . $courseId . '/students');
        exit;
    }

    // Router::put('/api/courses/{id}', ['CoursesController', 'edit']);
    public function edit($course_id)
    {
        $authError = Check_user::checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $course_id = (int) $course_id;
        $course = Courses::find($course_id);

        if (!$course) {
            http_response_code(404);
            return new ViewModel('courses/not-found', ['id' => $course_id]);
        }

        parse_str(file_get_contents('php://input'), $data);
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            http_response_code(400);
            return new ViewModel('courses/edit', ['error' => 'Course name is required', 'course' => $course]);
        }

        if (strlen($name) > 100) {
            http_response_code(422);
            return new ViewModel('courses/edit', ['error' => 'Course name must be 100 characters or fewer', 'course' => $course]);
        }

        $existing = Courses::findByName($name);
        if ($existing && (int) $existing['id'] !== $course_id) {
            http_response_code(422);
            return new ViewModel('courses/edit', ['error' => 'A course with this name already exists', 'course' => $course]);
        }

        if (!Courses::edit($course_id, $name)) {
            http_response_code(500);
            return new ViewModel('courses/edit', ['error' => 'Internal Server Error', 'course' => $course]);
        }

        $_SESSION['flash'] = 'Course updated successfully';
        header('Location: /api/courses/' . $course_id . '/students');
        exit;
    }

    // Router::delete('/api/courses/{id}', ['CoursesController', 'delete']);
    public function delete($course_id)
    {
        $authError = Check_user::checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $course_id = (int) $course_id;

        if (!Courses::find($course_id)) {
            http_response_code(404);
            return new ViewModel('courses/not-found', ['id' => $course_id]);
        }

        if (!Courses::delete($course_id)) {
            http_response_code(500);
            return new ViewModel('courses/not-found', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Course deleted successfully';
        header('Location: /api/users/list');
        exit;
    }
}