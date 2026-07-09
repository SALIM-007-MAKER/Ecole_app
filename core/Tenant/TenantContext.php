<?php

declare(strict_types=1);

namespace Core\Tenant;

/**
 * Singleton PHP portant l'établissement (tenant) résolu pour la requête
 * en cours. Voir MULTI_TENANT_V2_BLUEPRINT.md §5.1.
 *
 * IMPORTANT (Phase 14.2-14.3) : aucun contrôleur ni Bootstrap n'appelle
 * TenantContext::set() aujourd'hui — TenantMiddleware n'est pas activé
 * dans le pipeline HTTP (prévu Phase 14.4+). Les tables V1 portent
 * désormais etablissement_id (Phase 14.3) et les modèles filtrent
 * automatiquement dessus via current() QUAND le contexte est positionné
 * (tests, futurs appels), mais restent inchangés quand il ne l'est pas
 * (100% du trafic de production actuel). require()/id() lèvent toujours
 * une exception si non résolu — à ne jamais appeler depuis du code de
 * production tant que le middleware n'est pas activé.
 */
final class TenantContext
{
    private static ?int $etablissementId = null;
    private static ?Etablissement $etablissement = null;

    private function __construct()
    {
    }

    public static function set(int $etablissementId, ?Etablissement $etablissement = null): void
    {
        self::$etablissementId = $etablissementId;
        self::$etablissement   = $etablissement;
    }

    public static function isSet(): bool
    {
        return self::$etablissementId !== null;
    }

    public static function id(): int
    {
        return self::require();
    }

    public static function require(): int
    {
        if (self::$etablissementId === null) {
            throw TenantException::notResolved();
        }
        return self::$etablissementId;
    }

    public static function get(): ?Etablissement
    {
        return self::$etablissement;
    }

    /**
     * Variante non-levante de require() : retourne null si le tenant n'est
     * pas résolu, au lieu de lever une exception. Utilisée par le code
     * "tenant-ready mais pas encore activé" (Core\Model, Phase 14.3) pour
     * appliquer un filtre automatiquement QUAND le contexte est disponible,
     * sans jamais casser les appels existants qui tournent sans contexte.
     */
    public static function current(): ?int
    {
        return self::$etablissementId;
    }

    /** Réservé aux tests. */
    public static function clear(): void
    {
        self::$etablissementId = null;
        self::$etablissement   = null;
    }
}
