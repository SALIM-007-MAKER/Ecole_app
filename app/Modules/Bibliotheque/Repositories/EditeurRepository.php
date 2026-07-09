<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Repositories;

use Core\Database;
use PDO;

class EditeurRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO biblio_editeurs (nom, adresse, site_web, email, etablissement_id)
             VALUES (:nom, :adresse, :site, :email, :etab)"
        );
        $st->execute([
            ':nom'    => $data['nom'],
            ':adresse'=> $data['adresse'] ?? null,
            ':site'   => $data['site_web'] ?? null,
            ':email'  => $data['email'] ?? null,
            ':etab'   => $data['etablissement_id'] ?? 1,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->pdo->prepare(
            "UPDATE biblio_editeurs SET nom=:nom, adresse=:adresse, site_web=:site, email=:email WHERE id=:id"
        )->execute([
            ':nom'    => $data['nom'],
            ':adresse'=> $data['adresse'] ?? null,
            ':site'   => $data['site_web'] ?? null,
            ':email'  => $data['email'] ?? null,
            ':id'     => $id,
        ]);
    }

    public function softDelete(int $id): void
    {
        $this->pdo->prepare("UPDATE biblio_editeurs SET deleted_at=NOW() WHERE id=:id")->execute([':id' => $id]);
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM biblio_editeurs WHERE id=:id AND deleted_at IS NULL");
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(int $etablissementId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM biblio_editeurs WHERE etablissement_id=:etab AND deleted_at IS NULL ORDER BY nom"
        );
        $st->execute([':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
