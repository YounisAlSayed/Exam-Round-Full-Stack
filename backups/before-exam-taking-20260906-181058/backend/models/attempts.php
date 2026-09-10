<?php

namespace App\models;

use App\Utils\Database;
use PDO;

class Attempts
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    public function find(int $id)
    {
        $sql = "SELECT * FROM attempts WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    public function findByExamAndStudent(int $exam_id, int $student_id)
    {
        $sql = "SELECT * FROM attempts WHERE exam_id = :exam_id AND student_id = :student_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['exam_id' => $exam_id, 'student_id' => $student_id]);
        return $statement->fetch();
    }

    public function getByStudent(int $student_id)
    {
        $sql = "SELECT a.*, e.title AS exam_title
                FROM attempts a
                INNER JOIN exams e ON e.id = a.exam_id
                WHERE a.student_id = :student_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id]);
        return $statement->fetchAll();
    }

    public function start(int $exam_id, int $student_id)
    {
        $sql = "INSERT INTO attempts (exam_id, student_id, started_at) VALUES (:exam_id, :student_id, NOW())";
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute(['exam_id' => $exam_id, 'student_id' => $student_id]);

        if (!$success) {
            return false;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function submit(int $id): bool
    {
        $sql = "UPDATE attempts SET submitted_at = NOW() WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['id' => $id]);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM attempts WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['id' => $id]);
    }

    public function updateSubmitted($exam_id, $student_id, $exam_mark = 0)
    {
        $sql = "UPDATE attempts SET submitted_at = NOW(), exam_mark = :exam_mark
            WHERE exam_id = :exam_id AND student_id = :student_id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['exam_mark' => $exam_mark, 'exam_id' => $exam_id, 'student_id' => $student_id]);
    }

    public function getExamAttemptStats(int $exam_id)
    {
        $sql = "SELECT
                (SELECT COUNT(*) FROM attempts WHERE exam_id = :id1) AS total_attempts,
                (SELECT COUNT(*) FROM enrolment en
                    INNER JOIN exams ex ON ex.course_id = en.course_id
                    WHERE ex.id = :id2) AS total_enrolled_students,
                (SELECT AVG(exam_mark) FROM attempts
                    WHERE exam_id = :id3 AND submitted_at IS NOT NULL) AS average_mark";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id1' => $exam_id, 'id2' => $exam_id, 'id3' => $exam_id]);
        return $statement->fetch();
    }
}
