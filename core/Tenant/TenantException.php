<?php

declare(strict_types=1);

namespace Core\Tenant;

/**
 * Levée quand le tenant courant n'a pas pu être résolu, ou est résolu
 * dans un état non exploitable (suspendu, plan expiré, introuvable).
 *
 * Voir MULTI_TENANT_V2_BLUEPRINT.md §2.2 RÈGLE 4 et §6.2.
 */
class TenantException extends \RuntimeException
{
    public static function notResolved(): self
    {
        return new self('Aucun établissement (tenant) résolu pour cette requête.', 500);
    }

    public static function notFound(string $identifier): self
    {
        return new self("Établissement introuvable : {$identifier}", 404);
    }

    public static function suspended(string $slug): self
    {
        return new self("Établissement suspendu : {$slug}", 503);
    }

    public static function planExpired(string $slug): self
    {
        return new self("Abonnement expiré pour l'établissement : {$slug}", 402);
    }
}
