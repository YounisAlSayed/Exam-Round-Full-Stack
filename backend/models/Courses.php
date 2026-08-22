<?php

namespace App\models;

use App\Utils\Database;

class Courses
{
    public static function all()
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM courses";
        $statement = $pdo->prepare($sql);
        $statement->execute();
        return $statement->fetchAll();
    }

    public static function find(int $id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM courses WHERE id = :id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    public static function findByName(string $name)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM courses WHERE name = :name";
        $statement = $pdo->prepare($sql);
        $statement->execute(['name' => $name]);
        return $statement->fetch();
    }

    public static function getCourseStudents(int $course_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email
                FROM enrolment en
                INNER JOIN users u ON u.id = en.student_id
                WHERE en.courses_id = :course_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['course_id' => $course_id]);
        return $statement->fetchAll();
    }

    public static function getCourseTeachers(int $course_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email
                FROM teacher_courses tc
                INNER JOIN users u ON u.id = tc.teacher_id
                WHERE tc.courses_id = :course_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['course_id' => $course_id]);
        return $statement->fetchAll();
    }

    public static function getStudentCourses(int $student_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT c.name FROM enrolment e 
            INNER JOIN courses c ON e.course_id=c.id 
            WHERE e.student_id=:id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $student_id]);
        return $statement->fetchAll();
    }

    public static function getTeacherCourses(int $teacher_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT c.name FROM teacher_courses tc
            INNER JOIN courses c ON tc.course_id=c.id
            WHERE tc.teacher_id=:id;";

        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $teacher_id]);
        return $statement->fetchAll();
    }

    public static function create(string $name)
    {
        $pdo = Database::getInstance();
        $sql = "INSERT INTO courses (name) VALUES (:name)";
        $statement = $pdo->prepare($sql);
        $success = $statement->execute(['name' => $name]);

        if (!$success) {
            return false;
        }

        return (int) $pdo->lastInsertId();
    }

    public static function edit(int $id, string $name): bool
    {
        $pdo = Database::getInstance();
        $sql = "UPDATE courses SET name = :name WHERE id = :id";
        $statement = $pdo->prepare($sql);
        return $statement->execute(['name' => $name, 'id' => $id]);
    }

    public static function delete(int $id): bool
    {
        $pdo = Database::getInstance();
        $sql = "DELETE FROM courses WHERE id = :id";
        $statement = $pdo->prepare($sql);
        return $statement->execute(['id' => $id]);
    }
}