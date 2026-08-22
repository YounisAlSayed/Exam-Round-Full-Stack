<?php

namespace App\models;

use App\Utils\Database;

class Marks
{
    public static function find(int $student_id, int $exam_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM marks WHERE student_id = :student_id AND exam_id = :exam_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id, 'exam_id' => $exam_id]);
        return $statement->fetch();
    }

    public static function getStudentAverage(int $student_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT AVG(student_mark) AS average FROM marks WHERE student_id = :student_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id]);
        return $statement->fetch();
    }

    public static function getCourseAverage(int $course_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT AVG(m.student_mark) AS average
                FROM marks m
                INNER JOIN exams e ON e.id = m.exam_id
                WHERE e.courses_id = :course_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['course_id' => $course_id]);
        return $statement->fetch();
    }

    public static function getStudentCourseMarks(int $student_id, int $course_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT m.*, e.title AS exam_title
                FROM marks m
                INNER JOIN exams e ON e.id = m.exam_id
                WHERE m.student_id = :student_id AND e.courses_id = :course_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['student_id' => $student_id, 'course_id' => $course_id]);
        return $statement->fetchAll();
    }

    public static function create(int $student_id, int $exam_id, int $student_mark): bool
    {
        $pdo = Database::getInstance();
        $sql = "INSERT INTO marks (student_id, exam_id, student_mark) VALUES (:student_id, :exam_id, :student_mark)";
        $statement = $pdo->prepare($sql);
        return $statement->execute([
            'student_id' => $student_id,
            'exam_id' => $exam_id,
            'student_mark' => $student_mark,
        ]);
    }

    public static function edit(int $student_id, int $exam_id, int $student_mark): bool
    {
        $pdo = Database::getInstance();
        $sql = "UPDATE marks SET student_mark = :student_mark WHERE student_id = :student_id AND exam_id = :exam_id";
        $statement = $pdo->prepare($sql);
        return $statement->execute([
            'student_mark' => $student_mark,
            'student_id' => $student_id,
            'exam_id' => $exam_id,
        ]);
    }
}