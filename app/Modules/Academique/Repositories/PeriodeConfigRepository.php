<?php

namespace App\Modules\Academique\Repositories;

use Core\Database;
use PDO;

class PeriodeConfigRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function all(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM `periodes_scolaires_config` ORDER BY ordre ASC, numero ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function find(int $numero): ?\stdClass
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `periodes_scolaires_config` WHERE numero = ?");
        $stmt->execute([$numero]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public function update(int $numero, array $data, int $userId): void
    {
        $data['updated_by'] = $userId;
        $sets   = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[]   = "`{$col}` = ?";
            $params[] = $val;
        }
        $params[] = $numero;

        $stmt = $this->pdo->prepare(
            "UPDATE `periodes_scolaires_config` SET " . implode(', ', $sets) . " WHERE numero = ?"
        );
        $stmt->execute($params);
    }

    public function count(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM `periodes_scolaires_config`")->fetchColumn();
    }
}
