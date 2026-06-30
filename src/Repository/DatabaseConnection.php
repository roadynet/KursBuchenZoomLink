<?php

namespace App\Repository;

use PDO;

final class DatabaseConnection
{
    private ?PDO $pdo = null;

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? '3306';
        $database = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? '';
        $user = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? '';
        $password = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? '';

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        $this->pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);

        return $this->pdo;
    }
}
