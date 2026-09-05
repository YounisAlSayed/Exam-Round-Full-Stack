<?php

namespace App\models;

use App\Utils\Database;
use PDO;

class Exams
{
    private PDO $pdo;
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    public function all()
    {
        $sql = "SELECT * FROM exams";
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function getCourseExamCount(int $course_id)
    {
        $sql = "SELECT COUNT(id) FROM exams WHERE course_id=:id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['id' => $course_id]);
    }
    public function find(int $id)
    {
        $sql = "SELECT * FROM exams WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        $res = $statement->fetch();
        if (!$res) {
            http_response_code(404);
            $_SESSION['error'] = "Exam Not Found";
            $_SESSION['redirect'] = '/api/dashboard';
            return false;
        }
        return $res;
    }

    public function getByTeacher(int $teacher_id)
    {
        $sql = "SELECT * FROM exams WHERE teacher_id = :teacher_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['teacher_id' => $teacher_id]);
        return $statement->fetchAll();
    }

    public function getNextCourseExam(int $course_id)
    {
        $sql = "SELECT e.* FROM exams e
                INNER JOIN courses c ON c.next_exam_id = e.id
                WHERE c.id = :course_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['course_id' => $course_id]);
        return $statement->fetch();
    }

    public function getExamQuestions(int $exam_id)
    {
        $sql = "SELECT q.id, q.question, eq.question_mark
                FROM exam_questions eq
                INNER JOIN questions q ON q.id = eq.question_id
                WHERE eq.exam_id = :exam_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['exam_id' => $exam_id]);
        return $statement->fetchAll();
    }

    public function getRandomQuestions(int $course_id, int $count)
    {
        $sql = "SELECT * FROM questions WHERE course_id = :course_id ORDER BY RAND() LIMIT :count";
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':course_id', $course_id, \PDO::PARAM_INT);
        $statement->bindValue(':count', $count, \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function create(
        string $title,
        int $course_id,
        int $total_marks,
        int $teacher_id,
        string $start_date,
        string $end_date,
        int $randomize_order
    ) {
        $sql = "INSERT INTO exams (title, course_id, total_marks, teacher_id, start_date, end_date, randomize_order)
                VALUES (:title, :course_id, :total_marks, :teacher_id, :start_date, :end_date, :randomize_order)";
        $statement = $this->pdo->prepare($sql);
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

        return (int) $this->pdo->lastInsertId();
    }

    public function edit(
        int $id,
        string $title,
        string $status,
        int $total_marks,
        string $start_date,
        string $end_date,
        int $randomize_order
    ): bool {
        $sql = "UPDATE exams
                SET title = :title, status = :status, total_marks = :total_marks,
                    start_date = :start_date, end_date = :end_date, randomize_order = :randomize_order
                WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
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

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM exams WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['id' => $id]);
    }

    public function setNextCourseExam(int $course_id, int $exam_id): bool
    {
        $sql = "UPDATE courses SET next_exam_id = :exam_id WHERE id = :course_id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['exam_id' => $exam_id, 'course_id' => $course_id]);
    }

    public function getExamFullDetails($exam_id)
    {
        $sql = "SELECT e.id as exam_id, e.title, e.course_id, c.name as course_name, e.start_date, e.end_date, e.total_marks, e.status
        FROM exams e
        INNER JOIN courses c ON e.course_id = c.id
        WHERE e.id = :id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $exam_id]);
        return $statement->fetch();
    }

    public function getTotalMarks($exam_id)
    {
        $sql = "SELECT total_marks FROM exams WHERE id = :id;";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $exam_id]);
        return $statement->fetch();
    }

    public function getById($exam_id)
    {
        $sql = 'SELECT * FROM exams WHERE id = :id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $exam_id]);
        return $statement->fetch();
    }

    public function getCourseExams($course_id)
    {
        $sql = 'SELECT * FROM exams WHERE course_id = :id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $course_id]);
        return $statement->fetchAll();
    }

    public function removeQuestion($question_id, $exam_id)
    {
        $sql = "DELETE FROM exam_questions WHERE question_id=:question_id AND exam_id=:exam_id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['question_id' => $question_id, 'exam_id' => $exam_id]);
    }
}
