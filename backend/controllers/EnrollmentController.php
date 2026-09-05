<?php

namespace App\Controllers;

use App\models\Enrolment;
use App\models\Courses;

class EnrollmentController
{
    private Enrolment $enrolment;
    private Courses $courses;
    private Check $elp;

    public function __construct()
    {
        $this->enrolment = new Enrolment();
        $this->courses = new Courses();
        $this->elp->unsetAll();
    }
    // Router::get('/api/enrollment/students/{id}', ['EnrollmentController', 'getStudentEnrollments']);
    // public function getStudentEnrollments($student_id)
    // {
    //     $student_id = (int) $student_id;
    //     $currentUser = $_SESSION['user'] ?? null;

    //     if ($currentUser === null) {
    //         header('Location: /api/users/login');
    //         exit;
    //     }

    //     if ((int) $currentUser['id'] !== $student_id && $currentUser['role'] !== 'teacher') {
    //         http_response_code(403);
    //         return new ViewModel('enrollment/forbidden', []);
    //     }

    //     $enrollments = $this->enrolment->getByStudent($student_id);
    //     return new ViewModel('dashboard', ['enrollments' => $enrollments, 'student_id' => $student_id]);
    // }

    // Router::post('/api/enrolment/students/{id}', ['EnrollmentController', 'enrollStudent']);
    // A student may enroll themself, or a teacher may enroll any student.
    public function enrollStudent($student_id)
    {
        $student_id = (int) $student_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            $this->elp->redirect("/api/users/login");
        }

        if ((int) $currentUser['id'] !== $student_id && $currentUser['role'] !== 'teacher') {
            http_response_code(403);
            return $this->elp->changeView("dashboard", ['error' => "User if Forbidden From doing the action"]);
        }

        $courses_id = (int) ($_POST['courses_id'] ?? 0);

        if (!$courses_id) {
            http_response_code(400);
            return $this->elp->changeView('dashboard', ['error' => 'courses_id is required', 'student_id' => $student_id]);
        }

        if (!$this->courses->find($courses_id)) {
            http_response_code(404);
            return $this->elp->changeView('dashboard', ['error' => 'Course not found', 'student_id' => $student_id]);
        }

        if ($this->enrolment->exists($student_id, $courses_id)) {
            http_response_code(422);
            return $this->elp->changeView('dashboard', ['error' => 'Already enrolled in this course', 'student_id' => $student_id]);
        }

        if (!$this->enrolment->create($student_id, $courses_id)) {
            http_response_code(500);
            return $this->elp->changeView('dashboard', ['error' => 'Internal Server Error', 'student_id' => $student_id]);
        }

        $_SESSION['flash'] = 'Enrolled successfully';
        $this->elp->redirect("/api/dashboard");
    }

    // Router::delete('/api/enrolment/{id}', ['EnrollmentController', 'deleteStudentEnrollment']);
    public function deleteStudentEnrollment($enrolment_id)
    {
        $enrolment_id = (int) $enrolment_id;
        $currentUser = $_SESSION['user'] ?? null;

        if ($currentUser === null) {
            $this->elp->redirect("/api/users/login");
        }

        $enrollment = $this->enrolment->find($enrolment_id);

        if (!$enrollment) {
            http_response_code(404);
            return $this->elp->changeView('dashboard', ['id' => $enrolment_id]);
        }

        if ((int) $currentUser['id'] !== (int) $enrollment['student_id'] && $currentUser['role'] !== 'teacher') {
            http_response_code(403);
            return $this->elp->changeView('dashboard', []);
        }

        if (!$this->enrolment->delete($enrolment_id)) {
            http_response_code(500);
            return $this->elp->changeView('dashboard', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Enrollment removed successfully';
        $this->elp->redirect("/api/dashboard");
    }
}
