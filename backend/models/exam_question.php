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
}
