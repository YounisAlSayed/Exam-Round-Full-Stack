<?php

namespace App\models;

use App\Utils\Database;
use PDO;

class Courses
{
    private PDO $pdo;
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    public function all()
    {
        $sql = "SELECT * FROM courses";
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function find(int $id)
    {
        $sql = "SELECT * FROM courses WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    public function findByName(string $name)
    {
        $sql = "SELECT * FROM courses WHERE name = :name";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['name' => $name]);
        return $statement->fetch();
    }

    public function getCourseStudents(int $course_id)
    {
        $sql = "SELECT u.id AS student_id, u.first_name, u.last_name, u.email, en.enrolment_date
                FROM enrolment en
                INNER JOIN users u ON u.id = en.student_id
                WHERE en.course_id = :course_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['course_id' => $course_id]);
        return $statement->fetchAll();
    }

    public function getCourseTeachers(int $course_id)
    {
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email
                FROM teacher_courses tc
                INNER JOIN users u ON u.id = tc.teacher_id
                WHERE tc.courses_id = :course_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['course_id' => $course_id]);
        return $statement->fetchAll();
    }

    public function getStudentCourses(int $student_id)
    {
        global $pdo;
        $sql = "SELECT c.id, c.name FROM enrolment e 
            INNER JOIN courses c ON e.course_id=c.id 
            WHERE e.student_id=:id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $student_id]);
        return $statement->fetchAll();
    }

    public function getTeacherCourses(int $teacher_id)
    {
        $sql = "SELECT c.id, c.name FROM teacher_courses tc
            INNER JOIN courses c ON tc.course_id=c.id
            WHERE tc.teacher_id=:id;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $teacher_id]);
        return $statement->fetchAll();
    }

    public function create(string $name)
    {
        $sql = "INSERT INTO courses (name) VALUES (:name)";
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute(['name' => $name]);

        if (!$success) {
            return false;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function edit(int $id, string $name): bool
    {
        $sql = "UPDATE courses SET name = :name WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['name' => $name, 'id' => $id]);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM courses WHERE id = :id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['id' => $id]);
    }
}
