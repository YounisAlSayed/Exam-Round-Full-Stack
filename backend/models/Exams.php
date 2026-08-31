<?php

namespace App\models;

use App\Utils\Database;


class Exams
{
    public function __construct()
    {
        $pdo = Database::getInstance();
    }
    public static function all()
    {
        global $pdo;
        $sql = "SELECT * FROM exams";
        $statement = $pdo->prepare($sql);
        $statement->execute();
        return $statement->fetchAll();
    }

    public static function getCourseExamCount(int $course_id)
    {
        global $pdo;
        $sql = "SELECT COUNT(id) FROM exams WHERE course_id=:id";
        $statement = $pdo->prepare($sql);
        return $statement->execute(['id' => $course_id]);
    }
    public static function find(int $id)
    {
        global $pdo;
        $sql = "SELECT * FROM exams WHERE id = :id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    public static function getByTeacher(int $teacher_id)
    {
        global $pdo;
        $sql = "SELECT * FROM exams WHERE teacher_id = :teacher_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['teacher_id' => $teacher_id]);
        return $statement->fetchAll();
    }

    public static function getNextCourseExam(int $course_id)
    {
        global $pdo;
        $sql = "SELECT e.* FROM exams e
                INNER JOIN courses c ON c.next_exam_id = e.id
                WHERE c.id = :course_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['course_id' => $course_id]);
        return $statement->fetch();
    }

    public static function getExamQuestions(int $exam_id)
    {
        global $pdo;
        $sql = "SELECT q.id, q.question, eq.question_mark
                FROM exam_questions eq
                INNER JOIN questions q ON q.id = eq.question_id
                WHERE eq.exam_id = :exam_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['exam_id' => $exam_id]);
        return $statement->fetchAll();
    }

    public static function getRandomQuestions(int $course_id, int $count)
    {
        global $pdo;
        $sql = "SELECT * FROM questions WHERE course_id = :course_id ORDER BY RAND() LIMIT :count";
        $statement = $pdo->prepare($sql);
        $statement->bindValue(':course_id', $course_id, \PDO::PARAM_INT);
        $statement->bindValue(':count', $count, \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public static function create(
        string $title,
        int $course_id,
        int $total_marks,
        int $teacher_id,
        string $start_date,
        string $end_date,
        int $randomize_order
    ) {
        global $pdo;
        $sql = "INSERT INTO exams (title, course_id, total_marks, teacher_id, start_date, end_date, randomize_order)
                VALUES (:title, :course_id, :total_marks, :teacher_id, :start_date, :end_date, :randomize_order)";
        $statement = $pdo->prepare($sql);
        $success = $statement->execute([
            'title' => $title,
            'course_id' => $course_id,
            'total_marks' => $total_marks,
            'teacher_id' => $teacher_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'randomize_order' => $randomize_order,
        ]);

        if (!$success) {
            return false;
        }

        return (int) $pdo->lastInsertId();
    }

    public static function edit(
        int $id,
        string $title,
        string $status,
        int $total_marks,
        string $start_date,
        string $end_date,
        int $randomize_order
    ): bool {
        global $pdo;
        $sql = "UPDATE exams
                SET title = :title, status = :status, total_marks = :total_marks,
                    start_date = :start_date, end_date = :end_date, randomize_order = :randomize_order
                WHERE id = :id";
        $statement = $pdo->prepare($sql);
        return $statement->execute([
            'title' => $title,
            'status' => $status,
            'total_marks' => $total_marks,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'randomize_order' => $randomize_order,
            'id' => $id,
        ]);
    }

    public static function delete(int $id): bool
    {
        global $pdo;
        $sql = "DELETE FROM exams WHERE id = :id";
        $statement = $pdo->prepare($sql);
        return $statement->execute(['id' => $id]);
    }

    public static function setNextCourseExam(int $course_id, int $exam_id): bool
    {
        global $pdo;
        $sql = "UPDATE courses SET next_exam_id = :exam_id WHERE id = :course_id";
        $statement = $pdo->prepare($sql);
        return $statement->execute(['exam_id' => $exam_id, 'course_id' => $course_id]);
    }

    public static function getExamFullDetails($exam_id)
    {
        global $pdo;
        $sql = "SELECT e.id as exam_id, e.title, e.course_id, c.name as course_name, e.start_date, e.end_date, e.total_marks, e.status
        FROM exams e
        INNER JOIN courses c ON e.course_id = c.id
        WHERE e.id = :id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $exam_id]);
        return $statement->fetch();
    }

    public static function getTotalMarks($exam_id)
    {
        global $pdo;
        $sql = "SELECT total_marks FROM exams WHERE id = :id;";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $exam_id]);
        return $statement->fetch();
    }

    public static function getById($exam_id)
    {
        global $pdo;
        $sql = 'SELECT * FROM exams WHERE id = :id';
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $exam_id]);
        return $statement->fetch();
    }

    public static function getCourseExams($course_id)
    {
        global $pdo;
        $sql = 'SELECT * FROM exams WHERE course_id = :id';
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $course_id]);
        return $statement->fetchAll();
    }
}