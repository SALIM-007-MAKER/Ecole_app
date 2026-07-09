<?php
declare(strict_types=1);

namespace App\Modules\Portals\Contracts;

interface SearchHandlerInterface
{
    /** Identifiant du module source (ex: 'scolarite', 'documents') */
    public function getModule(): string;

    /** Portails qui ont accès à ce handler */
    public function getPortals(): array;

    /** Permissions requises pour que les résultats de ce handler soient visibles */
    public function getPermissions(): array;

    /** Priorité d'affichage (plus petite = plus haut) */
    public function getPriority(): int;

    /**
     * Retourne des tableaux compatibles avec SearchResultDTO::fromArray().
     * Ne jamais lever d'exception : retourner [] en cas d'échec.
     */
    public function search(string $query, int $etablissementId, int $userId, int $limit): array;
}
