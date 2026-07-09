<?php
declare(strict_types=1);

namespace App\Modules\Portals\Shared\Widgets;

use Core\Database;

class MessageWidget extends BaseWidget
{
    public function getId(): string { return 'shared_messages'; }

    public function getTitle(): string { return 'Messages'; }

    public function getIcon(): string { return 'mail'; }

    public function getDefaultSize(): string { return 'sm'; }

    public function getDefaultOrder(): int { return 2; }

    public function isRefreshable(): bool { return true; }

    public function getRefreshInterval(): int { return 120; }

    public function getTemplate(): string { return 'Portals::Shared/Widgets/messages'; }

    public function getPermissions(): array { return ['communication.view']; }

    public function getData(int $etablissementId, int $userId, array $config = []): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM comm_messages
                 WHERE recipient_id = :uid AND etablissement_id = :etab
                 AND lu = 0 AND deleted_at IS NULL'
            );
            $stmt->execute([':uid' => $userId, ':etab' => $etablissementId]);
            $unread = (int)$stmt->fetchColumn();

            $msgStmt = $pdo->prepare(
                'SELECT id, expediteur_id, sujet, created_at, lu
                 FROM comm_messages
                 WHERE recipient_id = :uid AND etablissement_id = :etab AND deleted_at IS NULL
                 ORDER BY created_at DESC LIMIT 5'
            );
            $msgStmt->execute([':uid' => $userId, ':etab' => $etablissementId]);

            return [
                'unread'      => $unread,
                'recent'      => $msgStmt->fetchAll(\PDO::FETCH_ASSOC),
                'compose_url' => '/v2/portals/messages/nouveau',
                'inbox_url'   => '/v2/portals/messages',
            ];
        } catch (\Throwable) {
            return ['unread' => 0, 'recent' => [], 'compose_url' => '#', 'inbox_url' => '#'];
        }
    }
}
