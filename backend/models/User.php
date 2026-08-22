<?php

namespace App\models;

use App\Utils\Database;

class User
{
    public static function all()
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM users";
        $statement = $pdo->prepare($sql);
        $statement->execute();
        return $statement->fetchAll();
    }

    public static function getStudentsNextExamSet($student_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT ex.title, ex.total_marks, ex.start_date, ex.end_date, c.name
                FROM enrolment en
                INNER JOIN courses c ON c.id = en.course_id
                INNER JOIN exams ex ON ex.course_id = c.id
                WHERE en.student_id = :id
                ORDER BY ex.start_date ASC
                LIMIT 5";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $student_id]);
        return $statement->fetchAll();
    }

    public static function getTeacherNextExamSet($teacher_id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT ex.title, ex.total_marks, ex.start_date, ex.start_date, ex.end_date, c.name
                FROM teacher_courses tc
                INNER JOIN courses c ON c.id = tc.course_id
                INNER JOIN exams ex ON ex.course_id = c.id
                WHERE tc.teacher_id = :id
                ORDER BY ex.start_date ASC
                LIMIT 5";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $teacher_id]);
        return $statement->fetchAll();
    }

    public static function find(int $id)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM users WHERE id=:id";
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    public static function findByEmail(string $email)
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM users WHERE email=:email";
        $statement = $pdo->prepare($sql);
        $statement->execute(["email" => $email]);
        return $statement->fetch();;
    }

    public static function create(string $first_name, string $last_name, string $email, string $password, string $role)
    {
        $pdo = Database::getInstance();
        $sql = "INSERT INTO users(first_name, last_name, email, password, role) VALUES (:first_name, :last_name, :email, :password, :role)";
        $statement = $pdo->prepare($sql);
        return $statement->execute(['first_name' => $first_name, 'last_name' => $last_name, 'email' => $email, 'password' => $password, 'role' => $role]);
    }

    public static function edit(int $id, string $name, string  $email, string $password)
    {
        $pdo = Database::getInstance();
        $sql = 'UPDATE users SET name=:name, email=:email, password=:password WHERE id=:id';
        $statement = $pdo->prepare($sql);
        return $statement->execute(['name' => $name, 'email' => $email, 'password' => $password, 'id' => $id]);
    }

    public static function delete(int $id)
    {
        $pdo = Database::getInstance();
        $sql = "DELETE FROM users WHERE id=:id";
        $statement = $pdo->prepare($sql);
        return $statement->execute(['id' => $id]);
    }
}