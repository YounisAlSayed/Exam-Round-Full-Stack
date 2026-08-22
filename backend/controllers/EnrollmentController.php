<?php

namespace App\Controllers;

use App\models\Enrolment;
use App\models\Courses;
use App\Utils\ViewModel;

class EnrollmentController
{
    // Router::get('/api/enrollment/students/{id}', ['EnrollmentController', 'getStudentEnrollments']);
    public function getStudentEnrollments($student_id)
    {
        $student_id = (int) $student_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        if ((int) $currentUser['id'] !== $student_id && $currentUser['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('enrollment/forbidden', []);
        }

        $enrollments = Enrolment::getByStudent($student_id);
        return new ViewModel('enrollment/list', ['enrollments' => $enrollments, 'student_id' => $student_id]);
    }

    // Router::post('/api/enrolment/students/{id}', ['EnrollmentController', 'enrollStudent']);
    // A student may enroll themself, or a teacher may enroll any student.
    public function enrollStudent($student_id)
    {
        $student_id = (int) $student_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        if ((int) $currentUser['id'] !== $student_id && $currentUser['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('enrollment/forbidden', []);
        }

        $courses_id = (int) ($_POST['courses_id'] ?? 0);

        if (!$courses_id) {
            http_response_code(400);
            return new ViewModel('enrollment/list', ['error' => 'courses_id is required', 'student_id' => $student_id]);
        }

        if (!Courses::find($courses_id)) {
            http_response_code(404);
            return new ViewModel('enrollment/list', ['error' => 'Course not found', 'student_id' => $student_id]);
        }

        if (Enrolment::exists($student_id, $courses_id)) {
            http_response_code(422);
            return new ViewModel('enrollment/list', ['error' => 'Already enrolled in this course', 'student_id' => $student_id]);
        }

        if (!Enrolment::create($student_id, $courses_id)) {
            http_response_code(500);
            return new ViewModel('enrollment/list', ['error' => 'Internal Server Error', 'student_id' => $student_id]);
        }

        $_SESSION['flash'] = 'Enrolled successfully';
        header('Location: /api/enrollment/students/' . $student_id);
        exit;
    }

    // Router::delete('/api/enrolment/{id}', ['EnrollmentController', 'deleteStudentEnrollment']);
    public function deleteStudentEnrollment($enrolment_id)
    {
        $enrolment_id = (int) $enrolment_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            header('Location: /api/users/login');
            exit;
        }

        $enrollment = Enrolment::find($enrolment_id);

        if (!$enrollment) {
            http_response_code(404);
            return new ViewModel('enrollment/not-found', ['id' => $enrolment_id]);
        }

        if ((int) $currentUser['id'] !== (int) $enrollment['student_id'] && $currentUser['role'] !== 'teacher') {
            http_response_code(403);
            return new ViewModel('enrollment/forbidden', []);
        }

        if (!Enrolment::delete($enrolment_id)) {
            http_response_code(500);
            return new ViewModel('enrollment/not-found', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Enrollment removed successfully';
        header('Location: /api/enrollment/students/' . $enrollment['student_id']);
        exit;
    }
}