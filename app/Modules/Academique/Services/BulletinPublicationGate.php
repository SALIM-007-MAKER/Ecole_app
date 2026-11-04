<?php

declare(strict_types=1);

namespace App\Modules\Academique\Services;

/**
 * Interrupteur de la publication des bulletins (BULLETIN_PUBLICATION_ENABLED).
 *
 * Fermé par défaut : tant qu'il n'est pas explicitement ouvert, AUCUN bulletin ne peut être publié ni archivé,
 * quelles que soient les permissions de l'utilisateur. Il doit rester fermé jusqu'à la revue des droits RBAC
 * (chantier distinct) : une attribution ultérieure de `academique.bulletin.admin` ne doit pas ouvrir la
 * publication sans décision explicite.
 */
final class BulletinPublicationGate
{
    public const ENV_VAR = 'BULLETIN_PUBLICATION_ENABLED';

    public static function isOpen(?array $env = null): bool
    {
        $v = $env !== null ? ($env[self::ENV_VAR] ?? '') : ($_ENV[self::ENV_VAR] ?? getenv(self::ENV_VAR));
        return is_string($v) && in_array(strtolower(trim($v)), ['1', 'true', 'yes', 'on'], true);
    }
}
