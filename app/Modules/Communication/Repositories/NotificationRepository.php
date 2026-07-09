<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use Core\Database;
use PDO;

class NotificationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO com_notifications
             (user_id, type, module_source, entite_type, entite_id, titre, corps,
              url_action, icone, priorite, expire_at, metadata, etablissement_id)
             VALUES
             (:uid, :type, :module, :etype, :eid, :titre, :corps,
              :url, :icone, :priorite, :expire, :meta, :etab)"
        );
        $st->execute([
            ':uid'      => $data['user_id'],
            ':type'     => $data['type']          ?? 'info',
            ':module'   => $data['module_source'] ?? 'communication',
            ':etype'    => $data['entite_type']   ?? null,
            ':eid'      => $data['entite_id']     ?? null,
            ':titre'    => $data['titre'],
            ':corps'    => $data['corps']         ?? null,
            ':url'      => $data['url_action']    ?? null,
            ':icone'    => $data['icone']         ?? 'bell',
            ':priorite' => $data['priorite']      ?? 'normale',
            ':expire'   => $data['expire_at']     ?? null,
            ':meta'     => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            ':etab'     => $data['etablissement_id'] ?? 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findByUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $st = $this->pdo->prepare(
            "SELECT * FROM com_notifications
             WHERE user_id = :uid
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $st->bindValue(':uid',    $userId,  PDO::PARAM_INT);
        $st->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByUser(int $userId): int
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM com_notifications WHERE user_id = :uid"
        );
        $st->execute([':uid' => $userId]);
        return (int) $st->fetchColumn();
    }

    public function countUnread(int $userId): int
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM com_notifications WHERE user_id = :uid AND lu = 0"
        );
        $st->execute([':uid' => $userId]);
        return (int) $st->fetchColumn();
    }

    public function markRead(int $id, int $userId): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE com_notifications SET lu = 1, lu_at = NOW()
             WHERE id = :id AND user_id = :uid"
        );
        return $st->execute([':id' => $id, ':uid' => $userId]);
    }

    public function markAllRead(int $userId): int
    {
        $st = $this->pdo->prepare(
            "UPDATE com_notifications SET lu = 1, lu_at = NOW()
             WHERE user_id = :uid AND lu = 0"
        );
        $st->execute([':uid' => $userId]);
        return $st->rowCount();
    }

    public function delete(int $id, int $userId): bool
    {
        $st = $this->pdo->prepare(
            "DELETE FROM com_notifications WHERE id = :id AND user_id = :uid"
        );
        return $st->execute([':id' => $id, ':uid' => $userId]);
    }

    public function deleteExpired(): int
    {
        $st = $this->pdo->prepare(
            "DELETE FROM com_notifications WHERE expire_at IS NOT NULL AND expire_at < NOW()"
        );
        $st->execute();
        return $st->rowCount();
    }
}
