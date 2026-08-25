<?php

namespace App\models;

use App\Utils\Database;

class Exam_question
{
    public static function create(int $exam_id, int $question_id, float $question_mark)
    {
        $pdo = Database::getInstance();
        $sql = 'INSERT INTO exam_questions (exam_id, question_id, question_mark) VALUES (:exam_id, :question_id, :question_mark)';
        $statement = $pdo->prepare($sql);
        return $statement->execute(['exam_id' => $exam_id, 'question_id' => $question_id, 'question_mark' => $question_mark]);
    }

    public static function getQuestionMark($exam_id, $question_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM exam_questions WHERE exam_id = :exam_id AND question_id = :question_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['exam_id' => $exam_id, 'question_id' => $question_id]);
        return $statement->fetch();
    }

    public static function getStudentExamSelection($exam_id, $student_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT 
            q.id as question_id,
            q.question as question_text,
            eq.question_mark,
            sa.selected_choice_id,
            eq.question_mark,
            c.id as choice_id,
            c.choice_text,
            c.is_correct,
                CASE 
                    WHEN sa.selected_choice_id = c.id THEN 1 
                    ELSE 0 
                END as is_selected
            FROM exam_questions eq
            INNER JOIN questions q ON eq.question_id = q.id
            INNER JOIN choices c ON eq.question_id = c.question_id
            LEFT JOIN student_answers sa ON eq.exam_id = sa.exam_id 
            AND eq.question_id = sa.question_id 
            AND sa.selected_choice_id = c.id
            WHERE eq.exam_id = :exam_id AND sa.student_id = :student_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['exam_id' => $exam_id, 'student_id' => $student_id]);
        return $statement->fetchAll();
    }
}
