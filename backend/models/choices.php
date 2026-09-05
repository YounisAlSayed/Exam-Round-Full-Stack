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
        $sql = 'INSERT IGNORE INTO choices (question_id, choice_text, is_correct) VALUES (:question_id, :choice, :correct)';
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['question_id' => $question_id, 'choice' => $choice, 'correct' => $correct]);
    }

    public function getById($choice_id)
    {
        $sql = "SELECT * FROM choices WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $choice_id]);
        return $statement->fetch();
    }
    public function edit(int $choice_id, string $choice, int $correct)
    {
        $sql = 'UPDATE choices SET choice_text=:choice, is_correct=:correct WHERE id=:id';
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['id' => $choice_id, 'choice' => $choice, 'correct' => $correct]);
    }

    public function reset(int $question_id)
    {
        $sql = 'UPDATE choices SET is_correct=0 WHERE question_id=:question_id AND is_correct=1';
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['question_id' => $question_id]);
    }

    public function delete(int $choiceId)
    {
        $sql = 'DELETE FROM choices WHERE id=:id';
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['id' => $choiceId]);
    }

    public function deleteByQuestionId(int $question_id)
    {
        $sql = 'DELETE FROM choices WHERE question_id=:question_id';
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['question_id' => $question_id]);
    }
}
