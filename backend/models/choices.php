<?php

namespace App\models;

use App\Utils\Database;

class Choices
{
    public static function create(int $question_id, string $choice, int $correct)
    {
        $pdo = Database::getInstance();
        $sql = 'INSERT INTO choices (question_id, choice_text, is_correct) VALUES (:question_id, :choice, :correct)';
        $statement = $pdo->prepare($sql);
        return $statement->execute(['question_id' => $question_id, 'choice' => $choice, 'correct' => $correct]);
    }

    public static function getById($choice_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM choices WHERE id = :id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $choice_id]);
        return $statement->fetch();
    }
    public static function edit(int $choice_id, string $choice, int $correct)
    {
        $pdo = Database::getInstance();
        $sql = 'UPDATE choices SET id=:id, choice=:choice, is_correct=:correct';
        $statement = $pdo->prepare($sql);
        return $statement->execute(['id' => $choice_id, 'choice' => $choice, 'correct' => $correct]);
    }

    public static function reset(int $question_id)
    {
        $pdo = Database::getInstance();
        $sql = 'UPDATE choices SET is_correct=null WHERE question_id=:question_id AND question_id=one_correct';
        $statement = $pdo->prepare($sql);
        return $statement->execute(['question_id' => $question_id]);
    }
}
