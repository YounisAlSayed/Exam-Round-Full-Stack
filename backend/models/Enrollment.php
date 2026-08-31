<?php

namespace App\models;

use App\Utils\Database;

$pdo = Database::getInstance();
class Enrolment
{
    public static function find(int $id)
    {
        global $pdo;
        $sql = "SELECT * FROM enrolment WHERE id = :id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    public static function getByStudent(int $student_id)
    {
        global $pdo;
        $sql = "SELECT en.id, en.courses_id, c.name AS course_name
                FROM enrolment en
                INNER JOIN courses c ON c.id = en.courses_id
                WHERE en.student_id = :student_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id]);
        return $statement->fetchAll();
    }

    public static function exists(int $student_id, int $courses_id)
    {
        global $pdo;
        $sql = "SELECT * FROM enrolment WHERE student_id = :student_id AND courses_id = :courses_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id, 'courses_id' => $courses_id]);
        return $statement->fetch();
    }

    public static function create(int $student_id, int $courses_id)
    {
        global $pdo;
        $sql = "INSERT INTO enrolment (student_id, courses_id) VALUES (:student_id, :courses_id)";
        $statement = $pdo->prepare($sql);
        $success = $statement->execute(['student_id' => $student_id, 'courses_id' => $courses_id]);

        if (!$success) {
            return false;
        }

        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id): bool
    {
        global $pdo;
        $sql = "DELETE FROM enrolment WHERE id = :id";
        $statement = $pdo->prepare($sql);
        return $statement->execute(['id' => $id]);
    }
}