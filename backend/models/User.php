<?php

namespace App\models;

use App\Utils\Database;
use PDO;

class User
{

    private PDO $pdo;
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    public function all()
    {
        $sql = "SELECT * FROM users";
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function getStudentsNextExamSet($student_id)
    {
        $sql = "SELECT ex.id as exam_id, ex.title, ex.total_marks, ex.start_date, ex.end_date, c.name as course_name
                FROM enrolment en
                INNER JOIN courses c ON c.id = en.course_id
                INNER JOIN exams ex ON ex.course_id = c.id
                WHERE en.student_id = :id
                AND ex.start_date > NOW()
                ORDER BY ex.start_date ASC
                LIMIT 5";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $student_id]);
        return $statement->fetchAll();
    }

    public function getTeacherNextExamSet($teacher_id)
    {
        $sql = "SELECT ex.id as exam_id, ex.title, ex.total_marks, ex.start_date, ex.start_date, ex.end_date, c.name
                FROM teacher_courses tc
                INNER JOIN courses c ON c.id = tc.course_id
                INNER JOIN exams ex ON ex.course_id = c.id
                WHERE tc.teacher_id = :id
                AND ex.end_date > NOW()
                ORDER BY ex.start_date ASC
                LIMIT 5";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $teacher_id]);
        return $statement->fetchAll();
    }

    public function find(int $id)
    {
        $sql = "SELECT * FROM users WHERE id=:id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        return $statement->fetch();
    }

    public function findByEmail(string $email)
    {
        $sql = "SELECT * FROM users WHERE email=:email";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(["email" => $email]);
        return $statement->fetch();;
    }

    public function create(string $first_name, string $last_name, string $email, string $password, string $role)
    {
        $sql = "INSERT INTO users(first_name, last_name, email, password, role) VALUES (:first_name, :last_name, :email, :password, :role)";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['first_name' => $first_name, 'last_name' => $last_name, 'email' => $email, 'password' => $password, 'role' => $role]);
    }

    public function edit(int $id, string $first_name, string $last_name, string  $email, string $password)
    {
        $sql = 'UPDATE users SET first_name=:first_name, last_name, email=:email, password=:password WHERE id=:id';
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['first_name' => $first_name, 'last_name' => $last_name, 'email' => $email, 'password' => $password, 'id' => $id]);
    }

    public function delete(int $id)
    {
        $sql = "DELETE FROM users WHERE id=:id";
        $statement = $this->pdo->prepare($sql);
        return $statement->execute(['id' => $id]);
    }
}
