<?php

namespace App\models;

use App\Utils\Database;
use PDO;

class Choices
{
    private PDO $pdo;
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    public function create(int $question_id, string $choice, int $correct)
    {
        $sql = 'INSERT INTO choices (question_id, choice_text, is_correct) VALUES (:question_id, :choice, :correct)';
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['question_id' => $question_id, 'choice' => $choice, 'correct' => $correct]);
    }

    public function getById($choice_id)
    {
        global $pdo;
        $sql = "SELECT * FROM choices WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $choice_id]);
        return $statement->fetch();
    }
    public function edit(int $choice_id, string $choice, int $correct)
    {
        global $pdo;
        $sql = 'UPDATE choices SET id=:id, choice=:choice, is_correct=:correct';
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['id' => $choice_id, 'choice' => $choice, 'correct' => $correct]);
    }

    public function reset(int $question_id)
    {
        global $pdo;
        $sql = 'UPDATE choices SET is_correct=null WHERE question_id=:question_id AND question_id=one_correct';
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['question_id' => $question_id]);
    }
}
