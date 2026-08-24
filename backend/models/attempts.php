<?php

namespace App\models;

use App\Utils\Database;

class Attempts
{
    public static function find(int $id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM attempts WHERE id = :id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    public static function findByExamAndStudent(int $exam_id, int $student_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM attempts WHERE exam_id = :exam_id AND student_id = :student_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['exam_id' => $exam_id, 'student_id' => $student_id]);
        return $statement->fetch();
    }

    public static function getByStudent(int $student_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT a.*, e.title AS exam_title
                FROM attempts a
                INNER JOIN exams e ON e.id = a.exam_id
                WHERE a.student_id = :student_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id]);
        return $statement->fetchAll();
    }

    public static function start(int $exam_id, int $student_id)
    {
        $pdo = Database::getInstance();
        $sql = "INSERT INTO attempts (exam_id, student_id, started_at) VALUES (:exam_id, :student_id, NOW())";
        $statement = $pdo->prepare($sql);
        $success = $statement->execute(['exam_id' => $exam_id, 'student_id' => $student_id]);

        if (!$success) {
            return false;
        }

        return (int) $pdo->lastInsertId();
    }

    public static function submit(int $id): bool
    {
        $pdo = Database::getInstance();
        $sql = "UPDATE attempts SET submitted_at = NOW() WHERE id = :id";
        $statement = $pdo->prepare($sql);
        return $statement->execute(['id' => $id]);
    }

    public static function delete(int $id): bool
    {
        $pdo = Database::getInstance();
        $sql = "DELETE FROM attempts WHERE id = :id";
        $statement = $pdo->prepare($sql);
        return $statement->execute(['id' => $id]);
    }

    public static function updateSubmitted($exam_id, $student_id, $exam_mark = 0)
    {
        $pdo = Database::getInstance();
        $sql = "UPDATE attempt SET submitted_at = NOW(), exam_mark = :exam_mark
            WHERE exam_id = :exam_id AND student_id = :student_id";
        $statement = $pdo->prepare($sql);
        return $statement->execute(['exam_mark' => $exam_mark, 'exam_id' => $exam_id, 'student_id' => $student_id]);
    }
}
