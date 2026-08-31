<?php

namespace App\models;

use App\Utils\Database;
use PDO;

class Marks
{
    private PDO $pdo;
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    public function find(int $student_id, int $exam_id)
    {
        $sql = "SELECT * FROM marks WHERE student_id = :student_id AND exam_id = :exam_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id, 'exam_id' => $exam_id]);
        return $statement->fetch();
    }

    public function getStudentAverage(int $student_id)
    {
        $sql = "SELECT AVG(student_mark) AS average FROM marks WHERE student_id = :student_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id]);
        return $statement->fetch();
    }

    public function getCourseAverage(int $course_id)
    {
        $sql = "SELECT AVG(m.student_mark) AS average
                FROM marks m
                INNER JOIN exams e ON e.id = m.exam_id
                WHERE e.courses_id = :course_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['course_id' => $course_id]);
        return $statement->fetch();
    }

    public function getStudentCourseMarks(int $student_id, int $course_id)
    {
        $sql = "SELECT m.*, e.title AS exam_title
                FROM marks m
                INNER JOIN exams e ON e.id = m.exam_id
                WHERE m.student_id = :student_id AND e.courses_id = :course_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id, 'course_id' => $course_id]);
        return $statement->fetchAll();
    }

    public function create(int $student_id, int $exam_id, int $student_mark): bool
    {
        $sql = "INSERT INTO marks (student_id, exam_id, student_mark) VALUES (:student_id, :exam_id, :student_mark)";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute([
            'student_id' => $student_id,
            'exam_id' => $exam_id,
            'student_mark' => $student_mark,
        ]);
    }

    public function edit(int $student_id, int $exam_id, int $student_mark): bool
    {
        $sql = "UPDATE marks SET student_mark = :student_mark WHERE student_id = :student_id AND exam_id = :exam_id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute([
            'student_mark' => $student_mark,
            'student_id' => $student_id,
            'exam_id' => $exam_id,
        ]);
    }
}
