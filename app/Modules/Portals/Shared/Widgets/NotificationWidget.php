<?php
declare(strict_types=1);

namespace App\Modules\Portals\Shared\Widgets;

use App\Modules\Portals\Framework\NotificationCenter;

class NotificationWidget extends BaseWidget
{
    public function getId(): string { return 'shared_notifications'; }

    public function getTitle(): string { return 'Notifications'; }

    public function getIcon(): string { return 'bell'; }

    public function getDefaultSize(): string { return 'sm'; }

    public function getDefaultOrder(): int { return 1; }

    public function isRefreshable(): bool { return true; }

    public function getRefreshInterval(): int { return 60; }

    public function getTemplate(): string { return 'Portals::Shared/Widgets/notifications'; }

    public function getData(int $etablissementId, int $userId, array $config = []): array
    {
        $center = new NotificationCenter();
        return [
            'unread'       => $center->getUnreadCount($userId, $etablissementId),
            'recent'       => $center->getRecent($userId, $etablissementId, 5),
            'view_all_url' => '/v2/portals/notifications',
        ];
    }
}
