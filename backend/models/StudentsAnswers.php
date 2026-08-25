<?php

namespace App\models;

use App\Utils\Database;

class StudentsAnswers
{
    public static function find(int $student_id, int $exam_id, int $question_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM student_answers
                WHERE student_id = :student_id AND exam_id = :exam_id AND question_id = :question_id";
        $statement = $pdo->prepare($sql);
        $statement->execute([
            'student_id' => $student_id,
            'exam_id' => $exam_id,
            'question_id' => $question_id,
        ]);
        return $statement->fetch();
    }

    public static function getByStudentExam(int $student_id, int $exam_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT sa.question_id, sa.selected_choice_id, q.question, c.choice_text, c.is_correct
                FROM student_answers sa
                INNER JOIN questions q ON q.id = sa.question_id
                INNER JOIN choices c ON c.id = sa.selected_choice_id
                WHERE sa.student_id = :student_id AND sa.exam_id = :exam_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id, 'exam_id' => $exam_id]);
        return $statement->fetchAll();
    }

    // The FK on choices is (id, question_id) — this confirms the choice actually
    // belongs to the question being answered, not just that the choice id exists.
    public static function choiceBelongsToQuestion(int $choice_id, int $question_id): bool
    {
        $pdo = Database::getInstance();
        $sql = "SELECT id FROM choices WHERE id = :choice_id AND question_id = :question_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['choice_id' => $choice_id, 'question_id' => $question_id]);
        return (bool) $statement->fetch();
    }

    public static function questionBelongsToExam(int $exam_id, int $question_id): bool
    {
        $pdo = Database::getInstance();
        $sql = "SELECT id FROM exam_questions WHERE exam_id = :exam_id AND question_id = :question_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['exam_id' => $exam_id, 'question_id' => $question_id]);
        return (bool) $statement->fetch();
    }

    public static function create(int $student_id, int $exam_id, int $question_id, int $selected_choice_id): bool
    {
        $pdo = Database::getInstance();
        $sql = "INSERT INTO student_answers (student_id, exam_id, question_id, selected_choice_id)
                VALUES (:student_id, :exam_id, :question_id, :selected_choice_id)";
        $statement = $pdo->prepare($sql);
        return $statement->execute([
            'student_id' => $student_id,
            'exam_id' => $exam_id,
            'question_id' => $question_id,
            'selected_choice_id' => $selected_choice_id,
        ]);
    }

    public static function edit(int $student_id, int $exam_id, int $question_id, int $selected_choice_id): bool
    {
        $pdo = Database::getInstance();
        $sql = "UPDATE student_answers SET selected_choice_id = :selected_choice_id
                WHERE student_id = :student_id AND exam_id = :exam_id AND question_id = :question_id";
        $statement = $pdo->prepare($sql);
        return $statement->execute([
            'selected_choice_id' => $selected_choice_id,
            'student_id' => $student_id,
            'exam_id' => $exam_id,
            'question_id' => $question_id,
        ]);
    }
}
