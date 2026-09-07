<?php

namespace App\Controllers;

use App\models\Attempts;
use App\models\Choices;
use App\models\Courses;
use App\models\Exam_question;
use App\models\Exams;
use App\models\Questions;
use App\models\StudentsAnswers;

class ExamsController
{
    private Exams $exams;
    private Courses $courses;
    private Attempts $attempts;
    private Choices $choices;
    private Exam_question $exam_question;
    private StudentsAnswers $studentsAnswers;
    private Questions $questions;
    private Check $elp;
    public function __construct()
    {
        $this->exams = new Exams();
        $this->courses = new Courses();
        $this->attempts = new Attempts();
        $this->choices = new Choices();
        $this->exam_question = new Exam_question();
        $this->studentsAnswers = new StudentsAnswers();
        $this->questions = new Questions();
        $this->elp = new Check();
        $this->elp->unsetAll();
    }

    // Router::get('/api/exams/teacher/{id}', ['ExamsController', 'getTeacherExams']);
    public function getTeacherExams($teacher_id)
    {
        $teacher_id = (int) $teacher_id;
        $exams = $this->exams->getByTeacher($teacher_id);
        return $this->elp->changeView('exams/list', ['exams' => $exams]);
    }

    // Router::post('/api/exams', ['ExamsController', 'add']);
    public function create($course_id)
    {
        if (!$course_id) {
            http_response_code(400);
            $_SESSION['error'] = 'course ID was not Passed';
            $this->elp->redirect('/api/dashboard');
        }
        $course_id = (int) $course_id;
        $elpError = $this->elp->checkTeacherCredentials();
        if ($elpError !== null) {
            return $elpError;
        }
        if (!$this->courses->find($course_id)) {
            http_response_code(404);
            $_SESSION['error'] = 'Course Not Found';
            $this->elp->redirect('/api/dashboard');
        }

        $currentUser = $_SESSION['user'];

        $title = $_POST['title'] ?? null;
        $total_marks = (int) ($_POST['total_marks'] ?? 0);
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $randomize_order = isset($_POST['randomize']) ? 1 : 0;

        if (!$title || !$course_id || !$total_marks || !$start_date || !$end_date) {
            http_response_code(400);
            $_SESSION['error'] =  'Missing required fields';
            $this->elp->redirect('/api/dashboard');
        }

        if ($total_marks <= 0 || $total_marks > 100) {
            http_response_code(422);
            $_SESSION['error'] = 'Total marks must be between 1 and 100';
            $this->elp->redirect('/api/dashboard');
        }

        if (strtotime($end_date) <= strtotime($start_date)) {
            http_response_code(422);
            $_SESSION['error'] = 'End date must be after start date, start: ' . strtotime($start_date) . " end: " . strtotime($end_date);
            $this->elp->redirect('/api/dashboard');
        }

        $examId = $this->exams->create(
            $title,
            $course_id,
            $total_marks,
            (int) $currentUser['id'],
            $start_date,
            $end_date,
            $randomize_order
        );

        if (!$examId) {
            http_response_code(500);
            return $this->elp->changeView('exams/create', ['error' => 'Internal Server Error']);
        }

        $_SESSION['flash'] = 'Exam created successfully';
        $this->elp->redirect('/api/exams/preview/' . $examId . '?course_id=' . $course_id);
    }

    // Router::put('/api/exams/{id}', ['ExamsController', 'edit']);
    public function edit($exam_id)
    {
        $elpError = $this->elp->checkTeacherCredentials();
        if ($elpError !== null) {
            return $elpError;
        }

        $exam_id = (int) $exam_id;
        $exam = $this->exams->find($exam_id);

        if (!$exam) {
            http_response_code(404);
            $_SESSION['error'] = "Exam Not Found";
            $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $exam['course_id'] . "&page=questions");
        }

        $title = $_POST['title'] ?? null;
        $status = $_POST['exam_status'] ?? null;
        $total_marks = (int) ($_POST['total_marks'] ?? 0);
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $randomize_order = isset($_POST['randomize_order']) ? 1 : 0;

        if (!$title || !$status || !$total_marks || !$start_date || !$end_date) {
            http_response_code(400);
            $_SESSION['error'] = "Please Fill All fields";
            $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $exam['course_id'] . "&page=questions");
        }

        if ($total_marks <= 0 || $total_marks > 100) {
            http_response_code(422);
            $_SESSION['error'] = "Incorrect Total Mark";
            $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $exam['course_id'] . "&page=questions");
        }

        if (!in_array($status, ['not_ready', 'ready', 'in_progress', 'completed'])) {
            http_response_code(422);
            $_SESSION['error'] = "Undefined Status";
            $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $exam['course_id'] . "&page=questions");
        }

        if (strtotime($end_date) <= strtotime($start_date)) {
            http_response_code(422);
            $_SESSION['error'] = "End Date Cannot be less that start date";
            $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $exam['course_id'] . "&page=questions");
        }

        $examQuestionsCount = $this->questions->getExamQuestionCount($exam_id);
        if ($exam['status'] === 'not_ready' && $status === 'not_ready' && $examQuestionsCount > 0)
            $status = 'ready';
        if ($examQuestionsCount <= 0)
            $status = "not_ready";

        if (!$this->exams->edit($exam_id, $title, $status, $total_marks, $start_date, $end_date, $randomize_order)) {
            http_response_code(500);
            $_SESSION['error'] = "Internal Server Error";
            return $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $exam['course_id'] . "&page=questions");
        }

        $_SESSION['flash'] = 'Exam updated successfully';
        $this->elp->redirect('/api/courses/teacher/' . $exam['course_id']);
    }

    // Router::delete('/api/exams/{id}', ['ExamsController', 'delete']);
    public function delete($exam_id)
    {
        $elpError = $this->elp->checkTeacherCredentials();
        if ($elpError !== null) {
            return $elpError;
        }

        $exam_id = (int) $exam_id;
        $course_id = $_GET['course_id'];

        if (!$this->exams->find($exam_id)) {
            http_response_code(404);
            $_SESSION['error'] = 'Exam not found';
            $this->elp->redirect("/api/courses/teacher/" . $course_id);
        }

        if (!$this->exams->delete($exam_id)) {
            http_response_code(500);
            $_SESSION['error'] = 'Internal Server Error';
            $this->elp->redirect("/api/courses/teacher/" . $course_id);
        }

        $_SESSION['flash'] = 'Exam deleted successfully';

        $this->elp->redirect("/api/courses/teacher/" . $course_id);
    }

    public function saveProgress($exam_id)
    {
        return $this->saveAttemptAnswers((int) $exam_id, false);
    }

    public function submitExam($exam_id)
    {
        return $this->saveAttemptAnswers((int) $exam_id, true);
    }

    private function saveAttemptAnswers(int $exam_id, bool $submit)
    {
        // $authError = $this->elp->checkStudentCredentials();
        // if ($authError !== null) {
        //     return $authError;
        // }
        $student_id = (int) $_SESSION['user']['id'];
        $answers = [];
        foreach ($_POST as $key => $value) {
            if (preg_match('/^question_([1-9][0-9]*)$/', $key, $matches)) {
                $choiceId = filter_var($value, FILTER_VALIDATE_INT);
                if ($choiceId === false) {
                    http_response_code(400);
                    $_SESSION['error'] = 'Invalid answer.';
                    return $this->elp->redirect('/api/dashboard');
                }
                $answers[(int) $matches[1]] = $choiceId;
            }
        }

        try {
            $result = $this->attempts->saveAnswers($exam_id, $student_id, $answers, $submit);
        } catch (\DomainException $error) {
            http_response_code(400);
            $_SESSION['error'] = $error->getMessage();
            return $this->elp->redirect('/api/dashboard');
        } catch (\Throwable $error) {
            http_response_code(500);
            $_SESSION['error'] = 'Could not save the exam. Please go back and try again.';
            return $this->elp->redirect('/api/dashboard');
        }

        if ($result['submitted']) {
            unset($_SESSION['exam_taking'][$student_id][$exam_id], $_SESSION['exam_answers'][$exam_id]);
            $_SESSION['flash'] = 'Exam submitted successfully!';
            $this->elp->redirect('/api/exams/' . $exam_id . '/details/' . ($_SESSION['user']['role'] === 'student' ? 'student' : 'teacher'));
        }
        $state = $_SESSION['exam_taking'][$student_id][$exam_id] ?? null;
        $totalPages = $state ? max(1, (int) ceil(count($state['questions']) / $state['page_size'])) : 1;
        $page = max(1, min((int) ($_POST['current_page'] ?? 1), $totalPages));
        $action = $_POST['action'] ?? 'next';
        if ($action === 'next') {
            $page = min($page + 1, $totalPages);
        } elseif ($action === 'previous') {
            $page = max(1, $page - 1);
        }
        $this->elp->redirect('/api/exams/' . $exam_id . '/start/' . $page);
    }
}
