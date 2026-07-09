<?php
declare(strict_types=1);

namespace App\Modules\Portals\Parent\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class MessagesParentWidget extends BaseWidget
{
    public function getId(): string    { return 'parent_messages'; }
    public function getTitle(): string { return 'Messagerie'; }
    public function getIcon(): string  { return 'mail'; }
    public function getPortals(): array { return ['parent']; }
    public function getPermissions(): array { return ['communication.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 6; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 60; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/parent/messages'; }

    public function getData(int $etab, int $userId, array $config): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT m.id, m.sujet, m.created_at, m.lu,
                        CONCAT(u.prenom," ",u.nom) AS expediteur_nom
                 FROM comm_messages m
                 JOIN users u ON u.id = m.expediteur_id
                 WHERE m.destinataire_id=? AND m.deleted_at IS NULL
                 ORDER BY m.created_at DESC LIMIT 5'
            );
            $stmt->execute([$userId]);
            $msgs   = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $nonLus = count(array_filter($msgs, fn($m) => !$m['lu']));
            return ['messages' => $msgs, 'non_lus' => $nonLus];
        } catch (\Throwable) {
            return ['messages' => [], 'non_lus' => 0];
        }
    }
}
