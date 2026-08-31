<?php

namespace App\models;

use App\Utils\Database;
use PDO;

class Enrolment
{
    private PDO $pdo;
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    public function find(int $id)
    {
        $sql = "SELECT * FROM enrolment WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    public function getByStudent(int $student_id)
    {
        $sql = "SELECT en.id, en.courses_id, c.name AS course_name
                FROM enrolment en
                INNER JOIN courses c ON c.id = en.courses_id
                WHERE en.student_id = :student_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id]);
        return $statement->fetchAll();
    }

    public function exists(int $student_id, int $courses_id)
    {
        $sql = "SELECT * FROM enrolment WHERE student_id = :student_id AND courses_id = :courses_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id, 'courses_id' => $courses_id]);
        return $statement->fetch();
    }

    public function create(int $student_id, int $courses_id)
    {
        $sql = "INSERT INTO enrolment (student_id, courses_id) VALUES (:student_id, :courses_id)";
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute(['student_id' => $student_id, 'courses_id' => $courses_id]);

        if (!$success) {
            return false;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM enrolment WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['id' => $id]);
    }
}
