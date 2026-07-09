<?php

namespace Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = require ROOT_PATH . '/config/database.php';
        $conn   = $config['connections'][$config['default']];

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $conn['driver'],
            $conn['host'],
            $conn['port'],
            $conn['database'],
            $conn['charset']
        );

        try {
            $this->pdo = new PDO($dsn, $conn['username'], $conn['password'], $conn['options']);
        } catch (PDOException $e) {
            Logger::error('Connexion DB échouée : ' . $e->getMessage());
            throw new \RuntimeException('Erreur de connexion à la base de données.');
        }
    }

    public static function getInstance(): static
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    // Empêcher le clonage et la désérialisation
    private function __clone() {}
    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize singleton.');
    }
}
