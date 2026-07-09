<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use Core\Database;
use PDO;

class GroupeRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO com_groupes (nom, description, type, criteres, etablissement_id, created_by)
             VALUES (:nom, :desc, :type, :crit, :etab, :by)"
        );
        $st->execute([
            ':nom'  => $data['nom'],
            ':desc' => $data['description'] ?? null,
            ':type' => $data['type']        ?? 'manuel',
            ':crit' => isset($data['criteres']) ? json_encode($data['criteres']) : null,
            ':etab' => $data['etablissement_id'] ?? 1,
            ':by'   => $data['created_by']  ?? 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE com_groupes SET nom = :nom, description = :desc, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL"
        );
        return $st->execute([':id' => $id, ':nom' => $data['nom'], ':desc' => $data['description'] ?? null]);
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM com_groupes WHERE id = :id AND deleted_at IS NULL"
        );
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(int $etablissementId = 1): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM com_groupes WHERE etablissement_id = :etab AND deleted_at IS NULL ORDER BY nom ASC"
        );
        $st->execute([':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function softDelete(int $id): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE com_groupes SET deleted_at = NOW(), actif = 0 WHERE id = :id"
        );
        return $st->execute([':id' => $id]);
    }

    public function membres(int $groupeId): array
    {
        $st = $this->pdo->prepare(
            "SELECT m.*, u.prenom, u.nom, u.email, u.role
             FROM com_groupe_membres m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.groupe_id = :gid"
        );
        $st->execute([':gid' => $groupeId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function memberUserIds(int $groupeId): array
    {
        $st = $this->pdo->prepare(
            "SELECT user_id FROM com_groupe_membres WHERE groupe_id = :gid"
        );
        $st->execute([':gid' => $groupeId]);
        return array_column($st->fetchAll(PDO::FETCH_ASSOC), 'user_id');
    }

    public function insertMembre(int $groupeId, int $userId, int $addedBy): void
    {
        $st = $this->pdo->prepare(
            "INSERT IGNORE INTO com_groupe_membres (groupe_id, user_id, added_by) VALUES (:gid, :uid, :by)"
        );
        $st->execute([':gid' => $groupeId, ':uid' => $userId, ':by' => $addedBy]);
    }

    public function deleteMembre(int $groupeId, int $userId): bool
    {
        $st = $this->pdo->prepare(
            "DELETE FROM com_groupe_membres WHERE groupe_id = :gid AND user_id = :uid"
        );
        return $st->execute([':gid' => $groupeId, ':uid' => $userId]);
    }
}
