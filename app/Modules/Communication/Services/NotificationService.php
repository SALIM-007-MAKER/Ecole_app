<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Repositories\NotificationRepository;
use App\Modules\Communication\Events\NotificationCreated;
use App\Modules\Communication\Events\NotificationRead;
use App\Modules\Communication\Events\NotificationsBulkRead;
use Core\EventDispatcher;

class NotificationService
{
    private NotificationRepository $repo;

    public function __construct()
    {
        $this->repo = new NotificationRepository();
    }

    public function creer(
        int     $userId,
        string  $type,
        string  $titre,
        string  $corps,
        string  $moduleSource,
        ?string $entiteType  = null,
        ?int    $entiteId    = null,
        ?string $urlAction   = null,
        string  $priorite    = 'normale',
        ?string $expireAt    = null,
    ): int {
        $id = $this->repo->insert([
            'user_id'         => $userId,
            'type'            => $type,
            'titre'           => $titre,
            'corps'           => $corps,
            'module_source'   => $moduleSource,
            'entite_type'     => $entiteType,
            'entite_id'       => $entiteId,
            'url_action'      => $urlAction,
            'icone'           => $this->icone($type),
            'priorite'        => $priorite,
            'expire_at'       => $expireAt,
        ]);

        EventDispatcher::dispatch(new NotificationCreated($id, $userId, $type, $titre, $moduleSource));
        return $id;
    }

    public function marquerLu(int $notificationId, int $userId): void
    {
        $this->repo->markRead($notificationId, $userId);
        EventDispatcher::dispatch(new NotificationRead($notificationId, $userId));
    }

    public function marquerTousLus(int $userId, int $etablissementId = 1): int
    {
        $count = $this->repo->markAllRead($userId);
        if ($count > 0) {
            EventDispatcher::dispatch(new NotificationsBulkRead($userId, $count));
        }
        return $count;
    }

    public function compterNonLus(int $userId): int
    {
        return $this->repo->countUnread($userId);
    }

    public function lister(int $userId, int $page = 1, int $perPage = 20): array
    {
        $data  = $this->repo->findByUser($userId, $page, $perPage);
        $total = $this->repo->countByUser($userId);
        return [
            'data'     => $data,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    public function supprimer(int $notificationId, int $userId): void
    {
        $this->repo->delete($notificationId, $userId);
    }

    public function purgerExpires(): int
    {
        return $this->repo->deleteExpired();
    }

    private function icone(string $type): string
    {
        return match ($type) {
            'success' => 'check-circle',
            'warning' => 'alert-triangle',
            'alert'   => 'alert-circle',
            'message' => 'message-circle',
            'system'  => 'settings',
            default   => 'bell',
        };
    }
}
