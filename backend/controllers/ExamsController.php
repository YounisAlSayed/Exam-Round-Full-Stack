<?php

namespace App\Controllers;

use App\models\Attempts;
use App\models\Choices;
use App\models\Courses;
use App\models\Exam_question;
use App\models\Exams;
use App\models\StudentsAnswers;

class ExamsController
{
    private Exams $exams;
    private Courses $courses;
    private Attempts $attempts;
    private Choices $choices;
    private Exam_question $exam_question;
    private StudentsAnswers $studentsAnswers;
    private Check $elp;
    public function __construct()
    {
        $this->exams = new Exams();
        $this->courses = new Courses();
        $this->attempts = new Attempts();
        $this->choices = new Choices();
        $this->exam_question = new Exam_question();
        $this->studentsAnswers = new StudentsAnswers();
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

    // Router::get('/api/exams/random', ['ExamsController', 'generateRandom']);
    // GET has no side effects here — this previews N random questions from a course.
    // Expects ?course_id=X&count=Y as query params.
    // public function generateRandom()
    // {
    //     $course_id = (int) ($_GET['course_id'] ?? 0);
    //     $count = (int) ($_GET['count'] ?? 10);

    //     if (!$course_id || $count < 1) {
    //         http_response_code(400);
    //         return new ViewModel('exams/random-preview', ['error' => 'course_id and count are required']);
    //     }

    //     $questions = $this->exams->getRandomQuestions($course_id, $count);
    //     return new ViewModel('exams/random-preview', ['questions' => $questions]);
    // }

    // // Router::get('/api/exams/course/{id}', ['ExamsController', 'getNextCourseExam']);
    // public function getNextCourseExam($course_id)
    // {
    //     $course_id = (int) $course_id;
    //     $exam = $this->exams->getNextCourseExam($course_id);

    //     if (!$exam) {
    //         http_response_code(404);
    //         return new ViewModel('exams/not-found', ['id' => null, 'error' => 'No upcoming exam set for this course']);
    //     }

    //     return new ViewModel('exams/show', ['exam' => $exam]);
    // }

    // Router::get('/api/exams/{id}/questions', ['ExamsController', 'getExamQuestions']);
    // public function getExamQuestions($exam_id)
    // {
    //     $exam_id = (int) $exam_id;
    //     $exam = $this->exams->find($exam_id);

    //     if (!$exam) {
    //         http_response_code(404);
    //         return new ViewModel('exams/not-found', ['id' => $exam_id]);
    //     }

    //     $questions = $this->exams->getExamQuestions($exam_id);
    //     return new ViewModel('exams/questions', ['exam' => $exam, 'questions' => $questions]);
    // }

    // Router::post('/api/exams', ['ExamsController', 'add']);
    public function create($course_id)
    {
        if (!$course_id) {
            http_response_code(400);
            return $this->elp->changeView('dashboard', ['error' => 'course ID was not Passed']);
        }
        $course_id = (int) $course_id;
        $elpError = $this->elp->checkTeacherCredentials();
        if (!$elpError) {
            return $elpError;
        }
        if (!$this->courses->find($course_id)) {
            http_response_code(404);
            return $this->elp->changeView('dashboard', ['error' => 'Course Not Found']);
        }

        $currentUser = $_SESSION['user'];

        $title = $_POST['title'] ?? null;
        $total_marks = (int) ($_POST['total_marks'] ?? 0);
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $randomize_order = isset($_POST['randomize']) ? 1 : 0;

        if (!$title || !$course_id || !$total_marks || !$start_date || !$end_date) {
            http_response_code(400);
            return $this->elp->changeView('exams/create', ['error' => 'Missing required fields']);
        }

        if ($total_marks <= 0 || $total_marks > 100) {
            http_response_code(422);
            return $this->elp->changeView('exams/create', ['error' => 'Total marks must be between 1 and 100']);
        }

        if (strtotime($end_date) <= strtotime($start_date)) {
            http_response_code(422);
            return $this->elp->changeView('exams/create', ['error' => 'End date must be after start date']);
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
        header('Location: ' . BASE_PATH . '/api/exams/preview/' . $examId . '?course_id=' . $course_id);
        exit;
    }

    // Router::post('/api/exams/course/{id}', ['ExamsController', 'setNextCourseExam']);
    // public function setNextCourseExam($course_id)
    // {
    //     $elpError = $this->elp->checkTeacherCredentials();
    //     if (!$elpError) {
    //         return $elpError;
    //     }

    //     $course_id = (int) $course_id;
    //     $exam_id = (int) ($_POST['exam_id'] ?? 0);

    //     if (!$exam_id) {
    //         http_response_code(400);
    //         return new ViewModel('exams/list', ['error' => 'exam_id is required']);
    //     }

    //     $exam = $this->exams->find($exam_id);
    //     if (!$exam) {
    //         http_response_code(404);
    //         return new ViewModel('exams/list', ['error' => 'Exam not found']);
    //     }

    //     if (!$this->exams->setNextCourseExam($course_id, $exam_id)) {
    //         http_response_code(500);
    //         return new ViewModel('exams/list', ['error' => 'Internal Server Error']);
    //     }

    //     $_SESSION['flash'] = 'Next exam updated';
    //     header('Location: /api/exams/course/' . $course_id);
    //     exit;
    // }

    // Router::put('/api/exams/{id}', ['ExamsController', 'edit']);
    public function edit($exam_id)
    {
        $elpError = $this->elp->checkTeacherCredentials();
        if (!$elpError) {
            return $elpError;
        }

        $exam_id = (int) $exam_id;
        $exam = $this->exams->find($exam_id);

        if (!$exam) {
            http_response_code(404);
            return $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $exam['course_id'] . "&page=questions&error=ExamNotFound");
        }

        $title = $_POST['title'] ?? null;
        $status = $_POST['exam_status'] ?? null;
        $total_marks = (int) ($_POST['total_marks'] ?? 0);
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $randomize_order = isset($_POST['randomize_order']) ? 1 : 0;

        if (!$title || !$status || !$total_marks || !$start_date || !$end_date) {
            http_response_code(400);
            return $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $exam['course_id'] . "&page=questions&error=EmptyFields");
        }

        if ($total_marks <= 0 || $total_marks > 100) {
            http_response_code(422);
            return $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $exam['course_id'] . "&page=questions&error=IncorrectTotalMark");
        }

        if (!in_array($status, ['not_ready', 'ready', 'in_progress', 'completed'])) {
            http_response_code(422);
            return $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $exam['course_id'] . "&page=questions&error=UndefinedStatus");
        }

        if (strtotime($end_date) <= strtotime($start_date)) {
            http_response_code(422);
            return $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $exam['course_id'] . "&page=questions&error=EndDateLess");
        }

        if (!$this->exams->edit($exam_id, $title, $status, $total_marks, $start_date, $end_date, $randomize_order)) {
            http_response_code(500);
            return $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $exam['course_id'] . "&page=questions&error=editDBError");
        }

        $_SESSION['flash'] = 'Exam updated successfully';
        header('Location: ' . BASE_PATH . '/api/courses/teacher/' . $exam['course_id']);
        exit;
    }

    // Router::delete('/api/exams/{id}', ['ExamsController', 'delete']);
    public function delete($exam_id)
    {
        $elpError = $this->elp->checkTeacherCredentials();
        if (!$elpError) {
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
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_PATH . '/api/login');
            exit;
        }

        $action = $_POST['action'] ?? 'next';
        $currentPage = (int)($_POST['current_page'] ?? 1);
        $totalPages = (int)($_POST['total_pages'] ?? 1);

        // Save answers to session
        if (!isset($_SESSION['exam_answers'])) {
            $_SESSION['exam_answers'] = [];
        }
        $examAnswers = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $question_id = (int)str_replace('question_', '', $key);
                $_SESSION['exam_answers'][$exam_id][$question_id] = (int) $value;
            }
        }

        // Redirect
        if ($action === 'previous' && $currentPage > 1) {
            $nextPage = $currentPage - 1;
        } elseif ($action === 'next' && $currentPage < $totalPages) {
            $nextPage = $currentPage + 1;
        } else {
            $nextPage = $currentPage;
        }

        header('Location: ' . BASE_PATH . '/api/exams/' . $exam_id . '/start/' . $nextPage);
        exit;
    }

    public function submitExam($exam_id)
    {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_PATH . '/api/login');
            exit;
        }

        // Get saved answers from session
        $answers = $_SESSION['exam_answers'][$exam_id] ?? [];
        $student_id = $_SESSION['user']['id'];

        // Process and save answers
        $totalMarks = 0;
        $earnedMarks = 0;

        foreach ($answers as $questionId => $choiceId) {
            // Save student answer
            $this->studentsAnswers->create($student_id, $exam_id, $questionId, $choiceId);

            // Check if correct
            $choice = $this->choices->getById($choiceId);
            if ($choice && $choice['is_correct']) {
                $question = $this->exam_question->getQuestionMark($exam_id, $questionId);
                $earnedMarks += $question['question_mark'];
            }
        }

        // Get total marks
        $totalMarks = ($this->exams->getTotalMarks($exam_id))['total_marks'];

        // Save attempt
        $this->attempts->updateSubmitted($exam_id, $student_id, $earnedMarks);

        // Clear session answers
        unset($_SESSION['exam_answers'][$exam_id]);

        $_SESSION['flash'] = 'Exam submitted successfully!';
        header('Location: ' . BASE_PATH . '/api/exams/' . $exam_id . '/details');
        exit;
    }
}
