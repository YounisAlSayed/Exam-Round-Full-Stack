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