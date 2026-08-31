<?php

namespace App\models;

use App\Utils\Database;
use PDO;

class StudentsAnswers
{
    private PDO $pdo;
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    public function find(int $student_id, int $exam_id, int $question_id)
    {
        $sql = "SELECT * FROM student_answers
                WHERE student_id = :student_id AND exam_id = :exam_id AND question_id = :question_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'student_id' => $student_id,
            'exam_id' => $exam_id,
            'question_id' => $question_id,
        ]);
        return $statement->fetch();
    }

    public function getByStudentExam(int $student_id, int $exam_id)
    {
        $sql = "SELECT sa.question_id, sa.selected_choice_id, q.question, c.choice_text, c.is_correct
                FROM student_answers sa
                INNER JOIN questions q ON q.id = sa.question_id
                INNER JOIN choices c ON c.id = sa.selected_choice_id
                WHERE sa.student_id = :student_id AND sa.exam_id = :exam_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id, 'exam_id' => $exam_id]);
        return $statement->fetchAll();
    }

    // The FK on choices is (id, question_id) — this confirms the choice actually
    // belongs to the question being answered, not just that the choice id exists.
    public function choiceBelongsToQuestion(int $choice_id, int $question_id): bool
    {
        $sql = "SELECT id FROM choices WHERE id = :choice_id AND question_id = :question_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['choice_id' => $choice_id, 'question_id' => $question_id]);
        return (bool) $statement->fetch();
    }

    public function questionBelongsToExam(int $exam_id, int $question_id): bool
    {
        $sql = "SELECT id FROM exam_questions WHERE exam_id = :exam_id AND question_id = :question_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['exam_id' => $exam_id, 'question_id' => $question_id]);
        return (bool) $statement->fetch();
    }

    public function create(int $student_id, int $exam_id, int $question_id, int $selected_choice_id): bool
    {
        $sql = "INSERT INTO student_answers (student_id, exam_id, question_id, selected_choice_id)
                VALUES (:student_id, :exam_id, :question_id, :selected_choice_id)";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute([
            'student_id' => $student_id,
            'exam_id' => $exam_id,
            'question_id' => $question_id,
            'selected_choice_id' => $selected_choice_id,
        ]);
    }

    public function edit(int $student_id, int $exam_id, int $question_id, int $selected_choice_id): bool
    {
        $sql = "UPDATE student_answers SET selected_choice_id = :selected_choice_id
                WHERE student_id = :student_id AND exam_id = :exam_id AND question_id = :question_id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute([
            'selected_choice_id' => $selected_choice_id,
            'student_id' => $student_id,
            'exam_id' => $exam_id,
            'question_id' => $question_id,
        ]);
    }
}
