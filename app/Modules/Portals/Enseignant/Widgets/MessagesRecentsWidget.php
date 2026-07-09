<?php
declare(strict_types=1);

namespace App\Modules\Portals\Enseignant\Widgets;

use App\Modules\Portals\Shared\Widgets\BaseWidget;
use Core\Database;

class MessagesRecentsWidget extends BaseWidget
{
    public function getId(): string    { return 'enseignant_messages_recents'; }
    public function getTitle(): string { return 'Messages récents'; }
    public function getIcon(): string  { return 'mail'; }
    public function getPortals(): array { return ['enseignant']; }
    public function getPermissions(): array { return ['communication.view']; }
    public function getDefaultSize(): string { return 'sm'; }
    public function getDefaultOrder(): int   { return 7; }
    public function isRefreshable(): bool    { return true; }
    public function getRefreshInterval(): int { return 60; }
    public function getTemplate(): string { return 'Portals::Shared/Widgets/enseignant/messages_recents'; }

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
                 ORDER BY m.created_at DESC LIMIT 8'
            );
            $stmt->execute([$userId]);
            $msgs   = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $nonLus = array_sum(array_column($msgs, 'lu') === array_map(fn($m) => !$m['lu'], $msgs) ? [] : array_map(fn($m) => (int)!$m['lu'], $msgs));
            return ['messages' => $msgs, 'non_lus' => $nonLus];
        } catch (\Throwable) {
            return ['messages' => [], 'non_lus' => 0];
        }
    }
}
