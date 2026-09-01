<?php

namespace App\Controllers;

use App\models\Courses;
use App\Controllers\Check_user;
use App\Utils\ViewModel;

class CoursesController
{
    private Courses $courses;
    private Check_user $auth;
    public function __construct()
    {
        $this->courses = new Courses();
        $this->auth = new Check_user();
    }
    // Router::get('/api/courses/{id}/students', ['CoursesController', 'getCourseStudents']);
    public function getCourseStudents($course_id)
    {
        $course_id = (int) $course_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        $course = $this->courses->find($course_id);
        if (!$course) {
            http_response_code(404);
            return new ViewModel('courses/not-found', ['id' => $course_id]);
        }

        $students = $this->courses->getCourseStudents($course_id);
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

        $course = $this->courses->find($course_id);
        if (!$course) {
            http_response_code(404);
            return new ViewModel('courses/not-found', ['id' => $course_id]);
        }

        $teachers = $this->courses->getCourseTeachers($course_id);
        return new ViewModel('courses/teachers', ['course' => $course, 'teachers' => $teachers]);
    }

    // Router::post('/api/courses', ['CoursesController', 'add']);
    public function add()
    {
        $authError = $this->auth->checkTeacherCredentials();
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

        if ($this->courses->findByName($name)) {
            http_response_code(422);
            return new ViewModel('courses/create', ['error' => 'A course with this name already exists']);
        }

        $courseId = $this->courses->create($name);

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
        $authError = $this->auth->checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $course_id = (int) $course_id;
        $course = $this->courses->find($course_id);

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

        $existing = $this->courses->findByName($name);
        if ($existing && (int) $existing['id'] !== $course_id) {
            http_response_code(422);
            return new ViewModel('courses/edit', ['error' => 'A course with this name already exists', 'course' => $course]);
        }

        if (!$this->courses->edit($course_id, $name)) {
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
        $authError = $this->auth->checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $course_id = (int) $course_id;

        if (!$this->courses->find($course_id)) {
            http_response_code(404);
            return new ViewModel('courses/not-found', ['id' => $course_id]);
        }

        if (!$this->courses->delete($course_id)) {
            http_response_code(500);
            return new ViewModel('courses/not-found', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Course deleted successfully';
        header('Location: /api/users/list');
        exit;
    }
}