<?php

namespace App\models;

use App\Utils\Database;
use PDO;

class TeacherCourses
{
    private PDO $pdo;
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    public function all()
    {
        $sql = "SELECT tc.id, tc.teacher_id, tc.courses_id,
                    u.first_name, u.last_name, c.name AS course_name
                FROM teacher_courses tc
                INNER JOIN users u ON u.id = tc.teacher_id
                INNER JOIN courses c ON c.id = tc.courses_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function getByTeacher(int $teacher_id)
    {
        $sql = "SELECT tc.id, tc.courses_id, c.name AS course_name
                FROM teacher_courses tc
                INNER JOIN courses c ON c.id = tc.courses_id
                WHERE tc.teacher_id = :teacher_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['teacher_id' => $teacher_id]);
        return $statement->fetchAll();
    }

    public function exists(int $teacher_id, int $courses_id)
    {
        $sql = "SELECT * FROM teacher_courses WHERE teacher_id = :teacher_id AND courses_id = :courses_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['teacher_id' => $teacher_id, 'courses_id' => $courses_id]);
        return $statement->fetch();
    }

    public function create(int $teacher_id, int $courses_id): bool
    {
        $sql = "INSERT INTO teacher_courses (teacher_id, courses_id) VALUES (:teacher_id, :courses_id)";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['teacher_id' => $teacher_id, 'courses_id' => $courses_id]);
    }

    public function delete(int $teacher_id, int $courses_id): bool
    {
        $sql = "DELETE FROM teacher_courses WHERE teacher_id = :teacher_id AND courses_id = :courses_id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['teacher_id' => $teacher_id, 'courses_id' => $courses_id]);
    }
}
