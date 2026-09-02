<?php

namespace App\Controllers;

use App\models\TeacherCourses;
use App\models\Courses;
use App\models\User;
use App\Utils\ViewModel;

class TeacherCoursesController
{
    private TeacherCourses $teacherCourses;
    private Courses $courses;
    private User $user;
    private Check $auth;

    public function __construct()
    {
        $this->teacherCourses = new TeacherCourses();
        $this->courses = new Courses();
        $this->user = new User();
        $this->auth = new Check();
        $this->auth->unsetAll();
    }
    // Router::get('/api/teachers/courses', ['TeacherCoursesController', 'getTeachersCourses']);
    public function getTeachersCourses()
    {
        $authError = $this->auth->checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $assignments = $this->teacherCourses->all();
        return new ViewModel('teacher-courses/list', ['assignments' => $assignments]);
    }

    // Router::get('/api/teachers/{id}/courses', ['TeacherCoursesController', 'getTeacherCourses']);
    public function getTeacherCourses($teacher_id)
    {
        $teacher_id = (int) $teacher_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        // A teacher can view their own course list; anyone logged in can look up a teacher's courses.
        $courses = $this->teacherCourses->getByTeacher($teacher_id);
        return new ViewModel('teacher-courses/teacher', ['courses' => $courses, 'teacher_id' => $teacher_id]);
    }

    // Router::post('/api/teachers/{teacher_id}/courses/{course_id}', ['TeacherCoursesController', 'addTeacherCourse']);
    public function addTeacherCourse($teacher_id, $course_id)
    {
        $authError = $this->auth->checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $teacher_id = (int) $teacher_id;
        $course_id = (int) $course_id;

        $teacher = $this->user->find($teacher_id);
        if (!$teacher || $teacher['role'] !== 'teacher') {
            http_response_code(404);
            return new ViewModel('teacher-courses/teacher', ['error' => 'Teacher not found', 'teacher_id' => $teacher_id]);
        }

        if (!$this->courses->find($course_id)) {
            http_response_code(404);
            return new ViewModel('teacher-courses/teacher', ['error' => 'Course not found', 'teacher_id' => $teacher_id]);
        }

        if ($this->teacherCourses->exists($teacher_id, $course_id)) {
            http_response_code(422);
            return new ViewModel('teacher-courses/teacher', ['error' => 'This teacher is already assigned to this course', 'teacher_id' => $teacher_id]);
        }

        if (!$this->teacherCourses->create($teacher_id, $course_id)) {
            http_response_code(500);
            return new ViewModel('teacher-courses/teacher', ['error' => 'Internal Server Error', 'teacher_id' => $teacher_id]);
        }

        $_SESSION['flash'] = 'Course assigned successfully';
        header('Location: /api/teachers/' . $teacher_id . '/courses');
        exit;
    }

    // Router::delete('/api/teachers/{teacher_id}/courses/{course_id}', ['TeacherCoursesController', 'deleteTeacherCourse']);
    public function deleteTeacherCourse($teacher_id, $course_id)
    {
        $authError = $this->auth->checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $teacher_id = (int) $teacher_id;
        $course_id = (int) $course_id;

        if (!$this->teacherCourses->exists($teacher_id, $course_id)) {
            http_response_code(404);
            return new ViewModel('teacher-courses/teacher', ['error' => 'This assignment does not exist', 'teacher_id' => $teacher_id]);
        }

        if (!$this->teacherCourses->delete($teacher_id, $course_id)) {
            http_response_code(500);
            return new ViewModel('teacher-courses/teacher', ['error' => 'Internal Server Error', 'teacher_id' => $teacher_id]);
        }

        $_SESSION['flash'] = 'Course unassigned successfully';
        header('Location: /api/teachers/' . $teacher_id . '/courses');
        exit;
    }
}