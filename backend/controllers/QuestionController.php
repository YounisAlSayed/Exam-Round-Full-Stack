<?php

namespace App\Controllers;

use App\models\Choices;
use App\models\Courses;
use App\models\Exam_question;
use App\models\Exams;
use App\models\Questions;

class QuestionController
{
    private Choices $choices;
    private Courses $courses;
    private Exam_question $exam_question;
    private Exams $exams;
    private Questions $questions;
    private Check $elp;
    public function __construct()
    {
        $this->choices = new Choices();
        $this->courses = new Courses();
        $this->exam_question = new Exam_question();
        $this->exams = new Exams();
        $this->questions = new Questions();
        $this->elp = new Check();
        $this->elp->unsetAll();
    }

    private function cleanQuestionIds(array $questionIds): array
    {
        $cleanIds = [];
        foreach ($questionIds as $questionId) {
            $questionId = (int) $questionId;
            if ($questionId > 0 && !in_array($questionId, $cleanIds, true)) {
                $cleanIds[] = $questionId;
            }
        }

        return $cleanIds;
    }

    private function cleanQuestionMarks(array $questionMarks): array
    {
        $cleanMarks = [];
        foreach ($questionMarks as $questionId => $mark) {
            $questionId = (int) $questionId;
            $mark = (float) $mark;
            if ($questionId > 0 && $mark > 0) {
                $cleanMarks[$questionId] = $mark;
            }
        }

        return $cleanMarks;
    }

    public function addQuestion($course_id)
    {
        $elp = $this->elp->checkTeacherCredentials();
        if ($elp !== null) {
            return $elp;
        }

        $course_id = (int) $course_id;
        $question = trim($_POST['question_text'] ?? $_POST['question'] ?? '');
        $question_type = $_POST['question_type'] ?? '';
        $question_mark = isset($_POST['question_mark']) ? (float) $_POST['question_mark'] : 0;

        $exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : null;
        $needsQuestionMark = $exam_id !== null && $exam_id > 0;


        if (!$course_id || !$question || !in_array($question_type, ['mc', 't/f'], true) || ($needsQuestionMark && $question_mark <= 0)) {
            http_response_code(400);
            $_SESSION['error'] = "Incomplete input";
            $this->elp->redirect('/api/dashboard');
        }
        $course = $this->courses->find($course_id);

        if (!$course) {
            http_response_code(404);
            $_SESSION['error'] = "Course not found";
            $this->elp->redirect('/api/dashboard');
        }
        $choices = [];

        if ($question_type === 'mc') {
            $postedChoices = $_POST['choices'] ?? [];

            if (count($postedChoices) < 2 || count($postedChoices) > 4) {
                http_response_code(400);
                $_SESSION['error'] = "A multiple-choice question must have 2 to 4 choices.";
                $this->elp->redirect('/api/dashboard');
            }

            foreach ($postedChoices as $choice) {
                $choiceText = is_array($choice) ? trim((string) ($choice['text'] ?? '')) : trim((string) $choice);
                $isCorrect = is_array($choice) ? !empty($choice['is_correct']) : false;

                if ($choiceText === '') {
                    http_response_code(400);
                    $_SESSION['error'] = 'All choices must be filled in.';
                    $this->elp->redirect('/api/dashboard');
                }

                $choices[] = [
                    'text' => $choiceText,
                    'is_correct' => $isCorrect ? 1 : 0,
                ];
            }

            $hasCorrectChoice = array_filter($choices, fn($choice) => (int) $choice['is_correct'] === 1);
            if (empty($hasCorrectChoice)) {
                http_response_code(400);
                $_SESSION['error'] = 'Please select a correct answer.';
                $this->elp->redirect('/api/dashboard');
            }
        } elseif ($question_type === 't/f') {
            $tf_correct = $_POST['tf_correct'] ?? null;

            if ($tf_correct !== 'True' && $tf_correct !== 'False') {
                http_response_code(400);
                $_SESSION['error'] = 'Please select True or False.';
                $this->elp->redirect('/api/dashboard');
            }

            $choices = [
                ['text' => 'True', 'is_correct' => $tf_correct === 'True' ? 1 : 0],
                ['text' => 'False', 'is_correct' => $tf_correct === 'False' ? 1 : 0],
            ];
        }

        $question_id = $this->questions->create($course_id, $question, $question_type);

        if (!$question_id) {
            http_response_code(500);
            $_SESSION['error'] = 'Failed to create question.';
            $this->elp->redirect('/api/dashboard');
        }

        foreach ($choices as $choice) {
            $choice_id = $this->choices->create($question_id, $choice['text'], (int) $choice['is_correct']);
            if (!$choice_id) {
                http_response_code(500);
                $_SESSION['error'] = 'Failed to create question choice.';
                if ($exam_id) {
                    $this->elp->redirect("/api/questions/create/courses/" . $course_id . "/view?exam_id=" . $exam_id);
                }

                $_SESSION['error'] = 'Failed to create choice.';
                $this->elp->redirect('/api/dashboard');
            }
        }
        if ($exam_id) {
            $entry = $this->exam_question->create($exam_id, $question_id, $question_mark);
            if (!$entry) {
                http_response_code(500);
                $_SESSION['error'] = 'Failed to add question to exam.';
                $this->elp->redirect("/api/questions/create/courses/" . $course_id . "/view?exam_id=" . $exam_id);
            }
        }

        $_SESSION['flash'] = 'Created the question successfully.';
        if ($exam_id) {
            $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
        }
        $this->elp->redirect("/api/courses/teacher/" . $course_id);
    }

    public function previewQuestion($question_id)
    {
        $authError = $this->elp->checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $selected_questions = $_SESSION['selected_questions'] ?? [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $selected_questions = [];
        }
        if (isset($_POST['selected_questions']) && is_array($_POST['selected_questions'])) {
            $selected_questions = $this->cleanQuestionIds($_POST['selected_questions']);
        }

        $questionMarks = $_SESSION['selected_question_marks'] ?? [];
        if (isset($_POST['question_marks']) && is_array($_POST['question_marks'])) {
            $questionMarks = array_replace($questionMarks, $this->cleanQuestionMarks($_POST['question_marks']));
        }

        $question_id = (int) $question_id;
        $exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
        $question = $this->questions->getByID($question_id);
        if (!$question) {
            $_SESSION['error'] = 'Question not found.';
            $this->elp->redirect('/api/dashboard');
        }

        if (!isset($questionMarks[$question_id])) {
            $existingExamQuestion = $exam_id ? $this->exam_question->getQuestionMark($exam_id, $question_id) : null;
            $questionMarks[$question_id] = $existingExamQuestion['question_mark'] ?? 2;
        }

        $_SESSION['selected_questions'] = $selected_questions;
        $_SESSION['selected_question_marks'] = $questionMarks;

        $choices = $this->questions->getQuestionChoices($question_id) ?: [];
        return $this->elp->changeView('questions/view', [
            'question' => $question,
            'choices' => $choices,
            'selected_questions' => $selected_questions,
            'questionMarks' => $questionMarks,
            'exam_id' => $exam_id,
            'course_id' => (int) ($question['course_id'] ?? 0),
        ]);
    }
    public function editQuestion($question_id)
    {
        $authError = $this->elp->checkTeacherCredentials();
        if ($authError !== null) {
            return $authError;
        }

        $question_id = (int) $question_id;
        $exam_id = (int) ($_GET['exam_id'] ?? 0);
        $soul = $_GET['soul'] ?? 0;
        if ($exam_id <= 0 && !$soul) {
            http_response_code(400);
            $_SESSION['error'] = "Exam ID Not Passed";
            $this->elp->redirect('/api/dashboard');
        }
        $exam = $this->exams->find($exam_id);
        if (!$exam) {
            http_response_code(404);
            $_SESSION['error'] = 'Exam not found.';
            $this->elp->redirect('/api/dashboard');
        }
        if ((int) $exam['teacher_id'] !== (int) $_SESSION['user']['id']) {
            http_response_code(403);
            $_SESSION['error'] = 'You can only edit questions in your own exams.';
            $this->elp->redirect('/api/dashboard');
        }
        $question = $this->questions->getQuestionDetails($question_id, $exam_id);
        if (!$question) {
            http_response_code(404);
            $_SESSION['error'] = 'This question is no longer part of the exam. Please reopen the exam.';
            $this->elp->redirect('/api/dashboard');
        }
        $course_id = (int) $question['course_id'];
        $questionChoices = $this->questions->getQuestionChoices($question_id) ?: [];
        if (empty($_GET['update']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->elp->changeView('questions/preview', [
                'question' => $question,
                'questionChoices' => $questionChoices,
                'exam_id' => $exam_id,
                'course_id' => $course_id,
            ]);
        }

        $question_text = is_string($_POST['question_text'] ?? null) ? trim($_POST['question_text']) : '';
        $question_type = $_POST['question_type'] ?? '';
        $question_mark = filter_var($_POST['question_mark'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);
        $error = null;
        $choices = [];
        if ($question_text === '' || !in_array($question_type, ['mc', 't/f'], true) || $question_mark === false) {
            $error = 'Enter question text, a valid type, and marks between 1 and 100.';
        } elseif ($question_type === 'mc') {
            $postedChoices = $_POST['choices'] ?? [];
            if (!is_array($postedChoices) || count($postedChoices) < 2 || count($postedChoices) > 4) {
                $error = 'A multiple-choice question must have 2 to 4 choices.';
            } else {
                foreach ($postedChoices as $choice) {
                    if (!is_array($choice) || !is_string($choice['text'] ?? null) || trim($choice['text']) === '') {
                        $error = 'All choices must be filled in.';
                        break;
                    }
                    $choiceId = !empty($choice['id']) ? filter_var($choice['id'], FILTER_VALIDATE_INT, [
                        'options' => ['min_range' => 1],
                    ]) : null;
                    if ($choiceId === false) {
                        $error = 'Invalid choice.';
                        break;
                    }
                    $choices[] = [
                        'id' => $choiceId,
                        'text' => trim($choice['text']),
                        'is_correct' => !empty($choice['is_correct']) ? 1 : 0,
                    ];
                }
                if ($error === null && array_sum(array_column($choices, 'is_correct')) !== 1) {
                    $error = 'Please select exactly one correct answer.';
                }
            }
        } else {
            $correct = $_POST['tf_correct'] ?? null;
            if (!in_array($correct, ['True', 'False'], true)) {
                $error = 'Please select True or False.';
            } else {
                foreach (['True', 'False'] as $text) {
                    $choiceId = null;
                    if ($question['question_type'] === 't/f') {
                        foreach ($questionChoices as $existingChoice) {
                            if (strcasecmp($existingChoice['choice_text'], $text) === 0) {
                                $choiceId = (int) $existingChoice['id'];
                                break;
                            }
                        }
                    }
                    $choices[] = ['id' => $choiceId, 'text' => $text, 'is_correct' => $text === $correct ? 1 : 0];
                }
            }
        }

        if ($error !== null) {
            http_response_code(400);
            $_SESSION['error'] = $error;
            $this->elp->redirect('/api/dashboard');
        }

        try {
            $savedId = $this->questions->updateForExam(
                $question_id,
                $exam_id,
                (int) $_SESSION['user']['id'],
                $question_text,
                $question_type,
                $question_mark,
                $choices
            );
        } catch (\DomainException $error) {
            http_response_code(409);
            $_SESSION['error'] = $error->getMessage();
            $this->elp->redirect('/api/dashboard');
        } catch (\Throwable $error) {
            http_response_code(500);
            $_SESSION['error'] = "Failed to save the question. No changes were saved.";
            $this->elp->redirect('/api/dashboard');
        }

        unset($_SESSION['selected_questions'], $_SESSION['selected_question_marks']);
        $_SESSION['flash'] = $savedId !== $question_id
            ? 'Saved a new question for this exam. Other exams keep the original question.'
            : 'Updated the question successfully.';
        $this->elp->redirect('/api/exams/preview/' . $exam_id . '?course_id=' . $course_id . '&page=questions');
    }

    // Router::delete('/api/questions/{id}', ['QuestionController', 'delete']);
    public function delete(string $question_id)
    {
        $question_id = (int) $question_id;
        $course_id = $_GET['course_id'] ?? null;
        $exam_id = $_GET['exam_id'] ?? null;
        $full = $_GET['full'] ?? null;

        if (!$question_id) {
            http_response_code(400);
            return $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
        }
        $elp = $this->elp->checkTeacherCredentials();
        if ($elp !== null)
            return $elp;

        $question = $this->questions->getByID($question_id);
        if (!$question) {
            http_response_code(404);
            $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
        }

        if ($full) {
            if (!$this->questions->delete($question_id)) {
                $this->elp->redirect('/api/courses/teacher/' . $question['course_id'] . "?page=questions");
            }

            $_SESSION['flash'] = "Successfully Deleted the Question Fully";
            $this->elp->redirect('/api/courses/teacher/' . $question['course_id'] . "?page=questions");
        }

        if (!$this->exams->removeQuestion($question_id, $exam_id)) {
            http_response_code(500);
            $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
        }
        $_SESSION['flash'] = 'Successfully deleted the question';
        $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
    }

    public function questionBank($exam_id)
    {
        $authError = $this->elp->checkTeacherCredentials();
        if ($authError !== null)
            return $authError;

        $exam_id = (int) $exam_id;
        if (!$exam_id) {
            http_response_code(400);
            $_SESSION['error'] = 'Exam ID not Passed';
            $this->elp->redirect("/api/dashboard");
        }
        $exam = $this->exams->find($exam_id);
        if (!$exam) {
            $this->elp->redirect($_SESSION['redirect']);
        }
        $title = isset($_POST['title']) ? $_POST['title'] : null;
        $status = isset($_POST['exam_status']) ? $_POST['exam_status'] : null;
        $marks = isset($_POST['total_marks']) ? $_POST['total_marks'] : null;
        $start = isset($_POST['start_date']) ? $_POST['start_date'] : null;
        $end = isset($_POST['end_date']) ? $_POST['end_date'] : null;
        $randomize = isset($_POST['randomize_order']) ? 1 : 0;

        if ($title && $status && $status && $marks && $start && $end)
            $this->exams->edit($exam_id, $title, $status, $marks, $start, $end, $randomize);

        $course_id = (int) $exam['course_id'];
        $examQuestions = $this->questions->getExamQuestions($exam_id) ?: [];
        $existingQuestionIds = [];
        $existingQuestionMarks = [];
        foreach ($examQuestions as $examQuestion) {
            $questionId = (int) $examQuestion['question_id'];
            $existingQuestionIds[] = $questionId;
            $existingQuestionMarks[$questionId] = (float) $examQuestion['question_mark'];
        }

        $selected_questions = isset($_SESSION['selected_questions']) ? $_SESSION['selected_questions'] : $existingQuestionIds;
        $questionMarks = array_replace($existingQuestionMarks, $_SESSION['selected_question_marks'] ?? []);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $selected_questions = [];
            if (isset($_POST['question_ids']) && is_array($_POST['question_ids'])) {
                $selected_questions = $this->cleanQuestionIds($_POST['question_ids']);
            }

            if (isset($_POST['selected_questions']) && is_array($_POST['selected_questions'])) {
                $selected_questions = $this->cleanQuestionIds($_POST['selected_questions']);
            }
        }

        if (isset($_POST['question_marks']) && is_array($_POST['question_marks'])) {
            $questionMarks = array_replace($questionMarks, $this->cleanQuestionMarks($_POST['question_marks']));
        }

        $selectQuestionId = isset($_GET['select']) ? (int) $_GET['select'] : 0;
        $removeQuestionId = isset($_GET['remove']) ? (int) $_GET['remove'] : 0;

        if ($selectQuestionId > 0 && !in_array($selectQuestionId, $selected_questions, true)) {
            $selected_questions[] = $selectQuestionId;
        }

        if ($selectQuestionId > 0 && isset($_POST['question_mark'])) {
            $questionMarks[$selectQuestionId] = (float) $_POST['question_mark'];
        }

        if ($removeQuestionId > 0) {
            $selected_questions = array_values(array_filter(
                $selected_questions,
                fn($questionId) => (int) $questionId !== $removeQuestionId
            ));
            unset($questionMarks[$removeQuestionId]);
        }

        $_SESSION['selected_questions'] = $selected_questions;
        $_SESSION['selected_question_marks'] = $questionMarks;

        $questions = $this->questions->getCourseQuestions($course_id) ?: [];

        if (($_POST['bank_action'] ?? '') === 'proceed') {
            $courseQuestionIds = array_map(fn($question) => (int) $question['id'], $questions);

            foreach ($existingQuestionIds as $existingQuestionId) {
                if (!in_array($existingQuestionId, $selected_questions, true)) {
                    $this->exams->removeQuestion($existingQuestionId, $exam_id);
                }
            }

            foreach ($selected_questions as $selectedQuestionId) {
                if (!in_array($selectedQuestionId, $courseQuestionIds, true)) {
                    continue;
                }

                $questionMark = (float) ($questionMarks[$selectedQuestionId] ?? 2);
                if ($questionMark <= 0 || $questionMark > 100) {
                    http_response_code(422);
                    return $this->elp->changeView('questions/bank', [
                        'questions' => $questions,
                        'exam_id' => $exam_id,
                        'course_id' => $course_id,
                        'selected_questions' => $selected_questions,
                        'questionMarks' => $questionMarks,
                        'error' => 'Question marks must be between 1 and 100.',
                    ]);
                }

                $existingQuestion = $this->exam_question->getQuestionMark($exam_id, $selectedQuestionId);
                if ($existingQuestion) {
                    $this->exam_question->updateMark($exam_id, $selectedQuestionId, $questionMark);
                } else {
                    $this->exam_question->create($exam_id, $selectedQuestionId, $questionMark);
                }
            }

            unset($_SESSION['selected_questions'], $_SESSION['selected_question_marks']);
            $_SESSION['flash'] = 'Question bank updated successfully.';
            $this->elp->redirect("/api/exams/preview/" . $exam_id . "?course_id=" . $course_id . "&page=questions");
        }

        return $this->elp->changeView('questions/bank', [
            'questions' => $questions,
            'exam_id' => $exam_id,
            'course_id' => $course_id,
            'selected_questions' => $selected_questions,
            'questionMarks' => $questionMarks,
        ]);
    }
}
