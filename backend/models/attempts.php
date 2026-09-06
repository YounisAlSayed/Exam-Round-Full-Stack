<?php

namespace App\models;

use App\Utils\Database;
use PDO;

class Attempts
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    public function find(int $id)
    {
        $sql = "SELECT * FROM attempts WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    public function findByExamAndStudent(int $exam_id, int $student_id)
    {
        $sql = "SELECT * FROM attempts WHERE exam_id = :exam_id AND student_id = :student_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['exam_id' => $exam_id, 'student_id' => $student_id]);
        return $statement->fetch();
    }

    public function getByStudent(int $student_id)
    {
        $sql = "SELECT a.*, e.title AS exam_title
                FROM attempts a
                INNER JOIN exams e ON e.id = a.exam_id
                WHERE a.student_id = :student_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id]);
        return $statement->fetchAll();
    }

    public function start(int $exam_id, int $student_id)
    {
        $sql = "INSERT INTO attempts (exam_id, student_id, started_at) VALUES (:exam_id, :student_id, NOW())";
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute(['exam_id' => $exam_id, 'student_id' => $student_id]);

        if (!$success) {
            return false;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function submit(int $id): bool
    {
        $sql = "UPDATE attempts SET submitted_at = NOW() WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['id' => $id]);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM attempts WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['id' => $id]);
    }

    public function updateSubmitted($exam_id, $student_id, $exam_mark = 0)
    {
        $sql = "UPDATE attempts SET submitted_at = NOW(), exam_mark = :exam_mark
            WHERE exam_id = :exam_id AND student_id = :student_id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['exam_mark' => $exam_mark, 'exam_id' => $exam_id, 'student_id' => $student_id]);
    }

    public function getExamAttemptStats(int $exam_id)
    {
        $sql = "SELECT
                (SELECT COUNT(*) FROM attempts WHERE exam_id = :id1) AS total_attempts,
                (SELECT COUNT(*) FROM enrolment en
                    INNER JOIN exams ex ON ex.course_id = en.course_id
                    WHERE ex.id = :id2) AS total_enrolled_students,
                (SELECT AVG(exam_mark) FROM attempts
                    WHERE exam_id = :id3 AND submitted_at IS NOT NULL) AS average_mark";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id1' => $exam_id, 'id2' => $exam_id, 'id3' => $exam_id]);
        return $statement->fetch();
    }

    public static function orderQuestions(array $questions, array $attempt, bool $randomize): array
    {
        $seed = $attempt['id'] . ':' . $attempt['student_id'] . ':' . $attempt['exam_id'] . ':' . $attempt['started_at'];
        usort($questions, static function ($left, $right) use ($seed, $randomize) {
            if (!$randomize) {
                return (int) $left['question_id'] <=> (int) $right['question_id'];
            }
            return strcmp(hash('sha256', $seed . ':' . $left['question_id']), hash('sha256', $seed . ':' . $right['question_id']));
        });
        return $questions;
    }

    public function getSavedAnswers(int $exam_id, int $student_id): array
    {
        $statement = $this->pdo->prepare('SELECT question_id, selected_choice_id FROM student_answers WHERE exam_id = :exam_id AND student_id = :student_id');
        $statement->execute(['exam_id' => $exam_id, 'student_id' => $student_id]);
        return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function saveAnswers(int $exam_id, int $student_id, array $answers, bool $submit): array
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('SELECT total_marks, end_date FROM exams WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $exam_id]);
            $exam = $statement->fetch();

            $statement = $this->pdo->prepare('SELECT * FROM attempts WHERE exam_id = :exam_id AND student_id = :student_id FOR UPDATE');
            $statement->execute(['exam_id' => $exam_id, 'student_id' => $student_id]);
            $attempt = $statement->fetch();

            if (!$exam || !$attempt) {
                throw new \DomainException('Start the exam before saving answers.');
            }
            // Repeated submits must never replace a completed score with zero.
            if ($attempt['submitted_at'] !== null) {
                $this->pdo->commit();
                return ['submitted' => true, 'mark' => (float) $attempt['exam_mark']];
            }

            $validate = $this->pdo->prepare('SELECT c.id
            FROM choices c
            INNER JOIN exam_questions eq ON eq.question_id = c.question_id
            WHERE eq.exam_id = :exam_id AND eq.question_id = :question_id AND c.id = :choice_id');

            $save = $this->pdo->prepare('INSERT INTO student_answers (student_id, exam_id, question_id, selected_choice_id) VALUES
            (:student_id, :exam_id, :question_id, :choice_id) ON DUPLICATE KEY UPDATE selected_choice_id = VALUES(selected_choice_id)');

            foreach ($answers as $questionId => $choiceId) {
                if (!is_int($questionId) || $questionId <= 0 || !is_int($choiceId) || $choiceId <= 0) {
                    throw new \DomainException('Invalid answer. Please reopen the exam.');
                }
                $params = ['exam_id' => $exam_id, 'question_id' => $questionId, 'choice_id' => $choiceId];
                $validate->execute($params);
                if (!$validate->fetch()) {
                    throw new \DomainException('The selected choice does not belong to this exam question.');
                }
                $params['student_id'] = $student_id;
                $save->execute($params);
            }

            $submit = $submit || time() >= strtotime($exam['end_date']);
            $mark = (float) $attempt['exam_mark'];
            if ($submit) {
                $statement = $this->pdo->prepare('SELECT COALESCE(SUM(eq.question_mark), 0) AS possible, COALESCE(SUM(CASE WHEN c.is_correct = 1 THEN eq.question_mark ELSE 0 END), 0) AS earned
                    FROM exam_questions eq
                    LEFT JOIN student_answers sa ON sa.exam_id = eq.exam_id AND sa.question_id = eq.question_id AND sa.student_id = :student_id
                    LEFT JOIN choices c ON c.id = sa.selected_choice_id AND c.question_id = eq.question_id
                    WHERE eq.exam_id = :exam_id');
                $statement->execute(['student_id' => $student_id, 'exam_id' => $exam_id]);
                $totals = $statement->fetch();

                $mark = (float) $totals['possible'] > 0
                    ? round((float) $totals['earned'] / (float) $totals['possible'] * (float) $exam['total_marks'], 2)
                    : 0.0;
                $statement = $this->pdo->prepare('UPDATE attempts SET submitted_at = NOW(), exam_mark = :mark WHERE id = :id');
                $statement->execute(['mark' => $mark, 'id' => $attempt['id']]);
            }
            $this->pdo->commit();
            return ['submitted' => $submit, 'mark' => $mark];
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
