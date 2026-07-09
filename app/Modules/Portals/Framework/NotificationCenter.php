<?php
declare(strict_types=1);

namespace App\Modules\Portals\Framework;

use Core\Database;

/**
 * Lecture des notifications depuis le module Communication.
 * Dégradation gracieuse si la table comm_notifications n'existe pas encore.
 */
class NotificationCenter
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getUnreadCount(int $userId, int $etablissementId): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM comm_notifications
                 WHERE recipient_id = :uid AND etablissement_id = :etab AND lu = 0 AND deleted_at IS NULL'
            );
            $stmt->execute([':uid' => $userId, ':etab' => $etablissementId]);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function getRecent(int $userId, int $etablissementId, int $limit = 10): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, type, titre, message, lien, lu, created_at
                 FROM comm_notifications
                 WHERE recipient_id = :uid AND etablissement_id = :etab AND deleted_at IS NULL
                 ORDER BY created_at DESC LIMIT :lim'
            );
            $stmt->bindValue(':uid',  $userId,          \PDO::PARAM_INT);
            $stmt->bindValue(':etab', $etablissementId, \PDO::PARAM_INT);
            $stmt->bindValue(':lim',  $limit,           \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getAll(int $userId, int $etablissementId, int $page = 1, int $perPage = 20): array
    {
        try {
            $offset = ($page - 1) * $perPage;

            $countStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM comm_notifications
                 WHERE recipient_id = :uid AND etablissement_id = :etab AND deleted_at IS NULL'
            );
            $countStmt->execute([':uid' => $userId, ':etab' => $etablissementId]);
            $total = (int)$countStmt->fetchColumn();

            $stmt = $this->pdo->prepare(
                'SELECT id, type, titre, message, lien, lu, created_at
                 FROM comm_notifications
                 WHERE recipient_id = :uid AND etablissement_id = :etab AND deleted_at IS NULL
                 ORDER BY created_at DESC
                 LIMIT :lim OFFSET :off'
            );
            $stmt->bindValue(':uid',  $userId,          \PDO::PARAM_INT);
            $stmt->bindValue(':etab', $etablissementId, \PDO::PARAM_INT);
            $stmt->bindValue(':lim',  $perPage,         \PDO::PARAM_INT);
            $stmt->bindValue(':off',  $offset,          \PDO::PARAM_INT);
            $stmt->execute();

            return [
                'data'    => $stmt->fetchAll(\PDO::FETCH_ASSOC),
                'total'   => $total,
                'unread'  => $this->getUnreadCount($userId, $etablissementId),
                'page'    => $page,
                'perPage' => $perPage,
                'pages'   => (int)ceil($total / $perPage),
            ];
        } catch (\Throwable) {
            return ['data' => [], 'total' => 0, 'unread' => 0, 'page' => 1, 'perPage' => $perPage, 'pages' => 1];
        }
    }

    public function markRead(int $notifId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE comm_notifications SET lu = 1, lu_at = NOW()
                 WHERE id = :id AND recipient_id = :uid'
            );
            $stmt->execute([':id' => $notifId, ':uid' => $userId]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function markAllRead(int $userId, int $etablissementId): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE comm_notifications SET lu = 1, lu_at = NOW()
                 WHERE recipient_id = :uid AND etablissement_id = :etab AND lu = 0 AND deleted_at IS NULL'
            );
            $stmt->execute([':uid' => $userId, ':etab' => $etablissementId]);
            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }
}
