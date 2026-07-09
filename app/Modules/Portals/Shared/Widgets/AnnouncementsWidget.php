<?php
declare(strict_types=1);

namespace App\Modules\Portals\Shared\Widgets;

use Core\Database;

class AnnouncementsWidget extends BaseWidget
{
    public function getId(): string { return 'shared_announcements'; }

    public function getTitle(): string { return 'Annonces'; }

    public function getIcon(): string { return 'megaphone'; }

    public function getDefaultSize(): string { return 'md'; }

    public function getDefaultOrder(): int { return 4; }

    public function getTemplate(): string { return 'Portals::Shared/Widgets/announcements'; }

    public function getPermissions(): array { return ['communication.view']; }

    public function getData(int $etablissementId, int $userId, array $config = []): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT id, titre, contenu, priorite, created_at
                 FROM comm_annonces
                 WHERE etablissement_id = :etab
                   AND (destinataires = "tous" OR FIND_IN_SET(:uid, destinataires_ids))
                   AND deleted_at IS NULL
                   AND (date_expiration IS NULL OR date_expiration >= CURDATE())
                 ORDER BY priorite DESC, created_at DESC
                 LIMIT 5'
            );
            $stmt->execute([':etab' => $etablissementId, ':uid' => $userId]);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $items = [];
        }

        return [
            'items'    => $items,
            'all_url'  => '/v2/portals/annonces',
        ];
    }
}
