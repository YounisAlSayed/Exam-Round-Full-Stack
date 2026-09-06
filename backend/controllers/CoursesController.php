<?php

namespace App\Controllers;

use App\models\Courses;
use App\models\Enrolment;
use App\models\Exams;
use App\models\TeacherCourses;

class CoursesController
{
    private Courses $courses;
    private TeacherCourses $teacher_courses;
    private Enrolment $enrolment;
    private Exams $exams;
    private Check $elp;
    public function __construct()
    {
        $this->courses = new Courses();
        $this->teacher_courses = new TeacherCourses();
        $this->enrolment = new Enrolment();
        $this->exams = new Exams();
        $this->elp = new Check();
        $this->elp->unsetAll();
    }

    public function getAll()
    {
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: ' . BASE_PATH . '/api/login');
            exit;
        }

        $allCourses = $this->courses->all();

        if (!$allCourses) {
            $allCourses = [];
        }

        $userCourses = [];
        if ($currentUser['role'] === 'teacher') {
            $userCourses = $this->courses->getTeacherCourses($currentUser['id']);
        } else if ($currentUser['role'] === 'student') {
            $userCourses = $this->courses->getStudentCourses($currentUser['id']);
        } else {
            return $this->elp->changeView('users/login', ['error' => "User Not Logged In"]);
        }

        $userCourseIds = array_column($userCourses, 'id');
        $otherCourses = array_filter($allCourses, function ($course) use ($userCourseIds) {
            return !in_array($course['id'], $userCourseIds);
        });

        return $this->elp->changeView('courses/list', [
            'courses' => array_values($otherCourses),
            'userCourses' => $userCourses
        ]);
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
            return $this->elp->changeView('courses/not-found', ['id' => $course_id]);
        }

        $students = $this->courses->getCourseStudents($course_id);
        return $this->elp->changeView('courses/students', ['course' => $course, 'students' => $students]);
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
            return $this->elp->changeView('courses/not-found', ['id' => $course_id]);
        }

        $teachers = $this->courses->getCourseTeachers($course_id);
        return $this->elp->changeView('courses/teachers', ['course' => $course, 'teachers' => $teachers]);
    }

    // Router::post('/api/courses', ['CoursesController', 'add']);
    public function add()
    {
        $elpError = $this->elp->checkTeacherCredentials();
        if ($elpError !== null) {
            return $elpError;
        }

        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            http_response_code(400);
            $_SESSION['error'] = 'Course name is required';
            return $this->elp->redirect('/api/courses/list');
        }

        if (strlen($name) > 100) {
            http_response_code(422);
            $_SESSION['error'] = 'Course name must be 100 characters or fewer';
            return $this->elp->redirect('/api/courses/list');
        }

        if ($this->courses->findByName($name)) {
            http_response_code(422);
            $_SESSION['error'] = 'A course with this name already exists';
            return $this->elp->redirect('/api/courses/list');
        }

        $courseId = $this->courses->create($name);

        if (!$courseId) {
            return $this->elp->redirect('/api/courses/list');
        }

        $_SESSION['flash'] = 'Course created successfully';
        $this->elp->redirect("/api/courses/list");
    }

    // Router::put('/api/courses/{id}', ['CoursesController', 'edit']);
    public function edit($course_id)
    {
        $elpError = $this->elp->checkTeacherCredentials();
        if ($elpError !== null) {
            return $elpError;
        }

        $course_id = (int) $course_id;
        $course = $this->courses->find($course_id);

        if (!$course) {
            http_response_code(404);
            $_SESSION['error'] = "Course Not Found";
            return $this->elp->redirect('/api/courses/list');
        }

        $name = $_POST['name'] ?? '';

        if ($name === '') {
            http_response_code(400);
            $_SESSION['error'] = "Course name is required";
            return $this->elp->redirect('/api/courses/list');
        }

        if (strlen($name) > 100) {
            http_response_code(422);
            $_SESSION['error'] = "Course name must be 100 characters or fewer";
            return $this->elp->redirect('/api/courses/list');
        }

        $existing = $this->courses->findByName($name);
        if ($existing && (int) $existing['id'] !== $course_id) {
            http_response_code(422);
            $_SESSION['error'] = 'A course with this name already exists';
            return $this->elp->redirect('/api/courses/list');
        }

        if (!$this->courses->edit($course_id, $name)) {
            http_response_code(500);
            $_SESSION['error'] = 'Internal Server Error';
            return $this->elp->redirect('/api/courses/list');
        }

        $_SESSION['flash'] = 'Course updated successfully';
        $this->elp->redirect("/api/courses/list");
    }

    // Router::delete('/api/courses/{id}', ['CoursesController', 'delete']);
    public function delete($course_id)
    {
        $elpError = $this->elp->checkTeacherCredentials();
        if ($elpError !== null) {
            return $elpError;
        }

        $course_id = (int) $course_id;

        if (!$this->courses->find($course_id)) {
            http_response_code(404);
            return $this->elp->changeView('courses/not-found', ['id' => $course_id]);
        }

        if (!$this->courses->delete($course_id)) {
            http_response_code(500);
            return $this->elp->changeView('courses/not-found', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Course deleted successfully';
        $this->elp->redirect("/api/users/list");
    }

    public function addToCourse($course_id)
    {
        if (!$course_id) {
            http_response_code(400);
            $_SESSION['error'] = "course ID not passed";
            $this->elp->redirect('/api/courses/list');
        }
        $user = $_SESSION['user'] ?? null;

        if (!$user) {
            http_response_code(404);
            $_SESSION['error'] = "User Not Logged In";
            $this->elp->redirect('/api/login');
        }
        $res = null;
        if ($user['role'] === 'teacher') {
            $res = !$this->teacher_courses->create($user['id'], $course_id);
        } else if ($user['role'] === 'student') {
            $res = $this->enrolment->create($user['id'], $course_id);
        } else {
            http_response_code(403);
            $_SESSION['error'] = "User For bidden from entering";
            $this->elp->redirect('/api/login');
        }
        if (!$res) {
            http_response_code(500);
            $_SESSION['error'] = "Internal Server Error";
            $this->elp->redirect('/api/courses/list');
        }
        $this->elp->redirect('/api/course/list');
    }

    public function removeFromCourse($course_id)
    {
        if (!$course_id) {
            http_response_code(400);
            $_SESSION['error'] = "course id not passed";
            $this->elp->redirect('/api/course/list');
        }

        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            http_response_code(403);
            $_SESSION['error'] = "user Not logged in";
            $this->elp->redirect('/api/login');
        }

        $res = null;
        if ($user['role'] === 'teacher') {
            $res = $this->teacher_courses->delete($user['id'], $course_id);
        } else if ($user['role'] === 'student') {
            $res = $this->enrolment->delete($user['id'], $course_id);
        } else {
            http_response_code(403);
            $_SESSION['error'] = "User Forbidden";
            $this->elp->redirect('/api/login');
        }

        if (!$res) {
            http_response_code(500);
            $_SESSION['error'] = "Internal Server Error";
            $this->elp->redirect('/api/courses/list');
        }
        $this->elp->redirect('/api/courses/list');
    }

    public function studentCourseDetails($course_id)
    {
        if (!$course_id) {
            http_response_code(400);
            $_SESSION['error'] = "Course ID not passed";
            $this->elp->redirect('/api/dashboard');
        }
        $course = $this->courses->find($course_id) ?? [];
        $exams = $this->exams->getReadyCourseExams($course_id);
        if (!$exams) {
            http_response_code(500);
            $_SESSION['error'] = "Internal Server Error";
            $this->elp->redirect('/api/courses/list');
        }

        return $this->elp->changeView('courses/studentCourse', ['exams' => $exams, 'course' => $course]);
    }
}
