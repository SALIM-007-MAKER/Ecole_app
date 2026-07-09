<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Repositories;

use Core\Database;
use PDO;

class AuteurRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO biblio_auteurs (nom, prenom, biographie, nationalite, etablissement_id)
             VALUES (:nom, :prenom, :bio, :nat, :etab)"
        );
        $st->execute([
            ':nom'   => $data['nom'],
            ':prenom'=> $data['prenom'] ?? null,
            ':bio'   => $data['biographie'] ?? null,
            ':nat'   => $data['nationalite'] ?? null,
            ':etab'  => $data['etablissement_id'] ?? 1,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->pdo->prepare(
            "UPDATE biblio_auteurs SET nom=:nom, prenom=:prenom, biographie=:bio, nationalite=:nat WHERE id=:id"
        )->execute([
            ':nom'   => $data['nom'],
            ':prenom'=> $data['prenom'] ?? null,
            ':bio'   => $data['biographie'] ?? null,
            ':nat'   => $data['nationalite'] ?? null,
            ':id'    => $id,
        ]);
    }

    public function softDelete(int $id): void
    {
        $this->pdo->prepare("UPDATE biblio_auteurs SET deleted_at=NOW() WHERE id=:id")->execute([':id' => $id]);
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM biblio_auteurs WHERE id=:id AND deleted_at IS NULL");
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(int $etablissementId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM biblio_auteurs WHERE etablissement_id=:etab AND deleted_at IS NULL ORDER BY nom, prenom"
        );
        $st->execute([':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
