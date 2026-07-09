<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use Core\Database;
use PDO;

class ThreadRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO com_threads
             (sujet, type, module_source, entite_type, entite_id, created_by, etablissement_id)
             VALUES (:sujet, :type, :module, :etype, :eid, :by, :etab)"
        );
        $st->execute([
            ':sujet'  => $data['sujet'],
            ':type'   => $data['type']          ?? 'direct',
            ':module' => $data['module_source'] ?? null,
            ':etype'  => $data['entite_type']   ?? null,
            ':eid'    => $data['entite_id']     ?? null,
            ':by'     => $data['created_by'],
            ':etab'   => $data['etablissement_id'] ?? 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM com_threads WHERE id = :id AND deleted_at IS NULL"
        );
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Retourne les threads où $userId est participant, non archivés */
    public function findForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $st = $this->pdo->prepare(
            "SELECT t.*, p.lu_at AS participant_lu_at
             FROM com_threads t
             INNER JOIN com_thread_participants p ON p.thread_id = t.id AND p.user_id = :uid
             WHERE t.deleted_at IS NULL AND p.archive = 0
             ORDER BY COALESCE(t.dernier_message_at, t.created_at) DESC
             LIMIT :limit OFFSET :offset"
        );
        $st->bindValue(':uid',    $userId,  PDO::PARAM_INT);
        $st->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateDernierMessage(int $threadId): void
    {
        $st = $this->pdo->prepare(
            "UPDATE com_threads SET dernier_message_at = NOW() WHERE id = :id"
        );
        $st->execute([':id' => $threadId]);
    }

    public function archive(int $id, int $userId): bool
    {
        // Archive uniquement pour ce participant
        $st = $this->pdo->prepare(
            "UPDATE com_thread_participants SET archive = 1 WHERE thread_id = :id AND user_id = :uid"
        );
        return $st->execute([':id' => $id, ':uid' => $userId]);
    }

    public function softDelete(int $id): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE com_threads SET deleted_at = NOW() WHERE id = :id"
        );
        return $st->execute([':id' => $id]);
    }
}
