<?php
declare(strict_types=1);

namespace App\Modules\Portals\Contracts;

interface WidgetInterface
{
    public function getId(): string;

    public function getTitle(): string;

    public function getIcon(): string;

    /** Portails où ce widget est disponible : ['admin','direction', ...] */
    public function getPortals(): array;

    /** Permissions nécessaires pour afficher ce widget */
    public function getPermissions(): array;

    /** xs | sm | md | lg | xl */
    public function getDefaultSize(): string;

    public function getDefaultOrder(): int;

    public function isRefreshable(): bool;

    /** Secondes entre chaque refresh auto (0 = pas de refresh) */
    public function getRefreshInterval(): int;

    /**
     * Fournit les données à injecter dans le template.
     * Ne jamais lever d'exception visible — retourner ['error'=>true] en cas d'échec.
     */
    public function getData(int $etablissementId, int $userId, array $config = []): array;

    /** Chemin de vue ex: 'Portals::Shared/Widgets/notifications' */
    public function getTemplate(): string;
}
