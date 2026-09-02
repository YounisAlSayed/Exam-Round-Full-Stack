<?php

namespace App\Controllers;

use App\models\Courses;

class CoursesController
{
    private Courses $courses;
    private Check $elp;
    public function __construct()
    {
        $this->courses = new Courses();
        $this->elp = new Check();
        $this->elp->unsetAll();
    }

    public function getAll()
    {
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            $this->elp->redirect("/api/users/login");
        }

        $courses = $this->courses->all();
        $this->elp->changeView('courses/list', ['courses' => $courses])->render();
    }
    // Router::get('/api/courses/{id}/students', ['CoursesController', 'getCourseStudents']);
    public function getCourseStudents($course_id)
    {
        $course_id = (int) $course_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            $this->elp->redirect('/api/users/login');
        }

        $course = $this->courses->find($course_id);
        if (!$course) {
            http_response_code(404);
            $this->elp->changeView('courses/not-found', ['id' => $course_id])->render();
        }

        $students = $this->courses->getCourseStudents($course_id);
        $this->elp->changeView('courses/students', ['course' => $course, 'students' => $students])->render();
    }

    // Router::get('/api/courses/{id}/teachers', ['CoursesController', 'getCourseTeachers']);
    public function getCourseTeachers($course_id)
    {
        $course_id = (int) $course_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            $this->elp->redirect("/api/users/login");
        }

        $course = $this->courses->find($course_id);
        if (!$course) {
            http_response_code(404);
            $this->elp->changeView('courses/not-found', ['id' => $course_id])->render();
        }

        $teachers = $this->courses->getCourseTeachers($course_id);
        $this->elp->changeView('courses/teachers', ['course' => $course, 'teachers' => $teachers])->render();
    }

    // Router::post('/api/courses', ['CoursesController', 'add']);
    public function add()
    {
        $elpError = $this->elp->checkTeacherCredentials();
        if (!$elpError) {
            return;
        }

        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            http_response_code(400);
            $this->elp->changeView('courses/create', ['error' => 'Course name is required'])->render();
        }

        if (strlen($name) > 100) {
            http_response_code(422);
            $this->elp->changeView('courses/create', ['error' => 'Course name must be 100 characters or fewer'])->render();
        }

        if ($this->courses->findByName($name)) {
            http_response_code(422);
            $this->elp->changeView('courses/create', ['error' => 'A course with this name already exists'])->render();
        }

        $courseId = $this->courses->create($name);

        if (!$courseId) {
            http_response_code(500);
            $this->elp->changeView('courses/create', ['error' => 'Internal Server Error'])->render();
        }

        $_SESSION['flash'] = 'Course created successfully';
        $this->elp->redirect("/api/courses/" . $courseId . "/students");
    }

    // Router::put('/api/courses/{id}', ['CoursesController', 'edit']);
    public function edit($course_id)
    {
        $elpError = $this->elp->checkTeacherCredentials();
        if (!$elpError) {
            return;
        }

        $course_id = (int) $course_id;
        $course = $this->courses->find($course_id);

        if (!$course) {
            http_response_code(404);
            $this->elp->changeView('courses/not-found', ['id' => $course_id])->render();
        }

        $name = $_POST['name'] ?? '';

        if ($name === '') {
            http_response_code(400);
            $this->elp->changeView('courses/edit', ['error' => 'Course name is required', 'course' => $course])->render();
        }

        if (strlen($name) > 100) {
            http_response_code(422);
            $this->elp->changeView('courses/edit', ['error' => 'Course name must be 100 characters or fewer', 'course' => $course])->render();
        }

        $existing = $this->courses->findByName($name);
        if ($existing && (int) $existing['id'] !== $course_id) {
            http_response_code(422);
            $this->elp->changeView('courses/edit', ['error' => 'A course with this name already exists', 'course' => $course])->render();
        }

        if (!$this->courses->edit($course_id, $name)) {
            http_response_code(500);
            $this->elp->changeView('courses/edit', ['error' => 'Internal Server Error', 'course' => $course])->render();
        }

        $_SESSION['flash'] = 'Course updated successfully';
        $this->elp->redirect("/api/courses/" . $course_id . "/students'");
    }

    // Router::delete('/api/courses/{id}', ['CoursesController', 'delete']);
    public function delete($course_id)
    {
        $elpError = $this->elp->checkTeacherCredentials();
        if (!$elpError) {
            return;
        }

        $course_id = (int) $course_id;

        if (!$this->courses->find($course_id)) {
            http_response_code(404);
            $this->elp->changeView('courses/not-found', ['id' => $course_id])->render();
        }

        if (!$this->courses->delete($course_id)) {
            http_response_code(500);
            $this->elp->changeView('courses/not-found', ['error' => 'Internal Server Error'])->render();
        }

        $_SESSION['flash'] = 'Course deleted successfully';
        $this->elp->redirect("/api/users/list");
    }
}