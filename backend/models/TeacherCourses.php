<?php

namespace App\models;

use App\Utils\Database;

class TeacherCourses
{
    public static function all()
    {
        $pdo = Database::getInstance();
        $sql = "SELECT tc.id, tc.teacher_id, tc.courses_id,
                    u.first_name, u.last_name, c.name AS course_name
                FROM teacher_courses tc
                INNER JOIN users u ON u.id = tc.teacher_id
                INNER JOIN courses c ON c.id = tc.courses_id";
        $statement = $pdo->prepare($sql);
        $statement->execute();
        return $statement->fetchAll();
    }

    public static function getByTeacher(int $teacher_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT tc.id, tc.courses_id, c.name AS course_name
                FROM teacher_courses tc
                INNER JOIN courses c ON c.id = tc.courses_id
                WHERE tc.teacher_id = :teacher_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['teacher_id' => $teacher_id]);
        return $statement->fetchAll();
    }

    public static function exists(int $teacher_id, int $courses_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM teacher_courses WHERE teacher_id = :teacher_id AND courses_id = :courses_id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['teacher_id' => $teacher_id, 'courses_id' => $courses_id]);
        return $statement->fetch();
    }

    public static function create(int $teacher_id, int $courses_id): bool
    {
        $pdo = Database::getInstance();
        $sql = "INSERT INTO teacher_courses (teacher_id, courses_id) VALUES (:teacher_id, :courses_id)";
        $statement = $pdo->prepare($sql);
        return $statement->execute(['teacher_id' => $teacher_id, 'courses_id' => $courses_id]);
    }

    public static function delete(int $teacher_id, int $courses_id): bool
    {
        $pdo = Database::getInstance();
        $sql = "DELETE FROM teacher_courses WHERE teacher_id = :teacher_id AND courses_id = :courses_id";
        $statement = $pdo->prepare($sql);
        return $statement->execute(['teacher_id' => $teacher_id, 'courses_id' => $courses_id]);
    }
}