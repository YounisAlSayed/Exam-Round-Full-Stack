<?php

namespace App\models;

use App\Utils\Database;

class Questions
{
    public static function all(int $course_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM questions WHERE course_id=:course_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['course_id' => $course_id]);
        return $statement->fetchAll();
    }

    public static function getByID(int $id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM questions WHERE id=:id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    public static function getExamQuestions(int $exam_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT q.id, q.course_id, q.question, eq.question_mark FROM questions q JOIN exam_questions eq ON q.id = eq.question_id WHERE eq.exam_id=:id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $exam_id]);
        return $statement->fetch();
    }

    public static function getQuestionChoices(int $question_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM choices WHERE question_id=:id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $question_id]);
        return $statement->fetchAll();
    }

    public static function create(int $course_id, string $question)
    {
        $pdo = Database::getInstance();
        $sql = "INSERT INTO questions (course_id, question) VALUES (:course_id, :question)";
        $statement = $pdo->prepare($sql);
        $statement->execute(['course_id' => $course_id, 'question' => $question]);
        return (int) $pdo->lastInsertId();
    }

    public static function update($question_id, $question)
    {
        $pdo = Database::getInstance();
    }

    public static function delete(int $question_id)
    {
        $pdo = Database::getInstance();
        $sql = 'DELETE FROM questions WHERE id=:id';
        $statement = $pdo->prepare($sql);
        return $statement->execute(['id' => $question_id]);
    }
}