<?php

declare(strict_types=1);

namespace Core\Tenant;

use Core\Cache\CacheInterface;
use Core\Cache\CacheManager;

/**
 * Cache préfixé par établissement — MULTI_TENANT_V2_BLUEPRINT.md (Phase 14.9).
 *
 * Garantie d'isolation : TOUTE clé passe par key(), qui préfixe
 * systématiquement `tenant:{etablissementId}:{category}:` — il est
 * structurellement impossible d'écrire ou de lire une clé sans fournir
 * explicitement l'établissement concerné (pas de méthode "globale" qui
 * accepterait une clé nue). flush() n'efface jamais qu'un seul tenant
 * (flushPrefix borné à son propre préfixe) — jamais tous les tenants à la
 * fois par erreur.
 */
final class TenantCache
{
    public function __construct(private readonly CacheInterface $driver)
    {
    }

    public static function make(): self
    {
        return new self(CacheManager::driver());
    }

    private function key(int $etablissementId, string $category, string $key): string
    {
        return "tenant:{$etablissementId}:{$category}:{$key}";
    }

    private function tenantPrefix(int $etablissementId): string
    {
        return "tenant:{$etablissementId}:";
    }

    public function get(int $etablissementId, string $category, string $key): mixed
    {
        return $this->driver->get($this->key($etablissementId, $category, $key));
    }

    public function put(int $etablissementId, string $category, string $key, mixed $value, int $ttlSeconds = 3600): void
    {
        $this->driver->put($this->key($etablissementId, $category, $key), $value, $ttlSeconds);
    }

    /** Lit depuis le cache, ou calcule via $resolver et met en cache le résultat. */
    public function remember(int $etablissementId, string $category, string $key, int $ttlSeconds, \Closure $resolver): mixed
    {
        $cacheKey = $this->key($etablissementId, $category, $key);
        if ($this->driver->has($cacheKey)) {
            return $this->driver->get($cacheKey);
        }
        $value = $resolver();
        $this->driver->put($cacheKey, $value, $ttlSeconds);
        return $value;
    }

    public function forget(int $etablissementId, string $category, string $key): void
    {
        $this->driver->forget($this->key($etablissementId, $category, $key));
    }

    /** Invalide UNE catégorie pour UN tenant (ex: toutes les clés "branding" de l'établissement 42) — jamais les autres tenants. */
    public function forgetCategory(int $etablissementId, string $category): void
    {
        $this->driver->flushPrefix("tenant:{$etablissementId}:{$category}:");
    }

    /** Invalide TOUT le cache d'UN tenant — utilisé, par ex., à la désactivation d'un établissement. */
    public function flushTenant(int $etablissementId): void
    {
        $this->driver->flushPrefix($this->tenantPrefix($etablissementId));
    }

    /** @return array{keys:int} nombre de clés actives pour ce tenant (toutes catégories confondues, ou une seule si précisée) */
    public function stats(int $etablissementId, ?string $category = null): array
    {
        $prefix = $category !== null
            ? "tenant:{$etablissementId}:{$category}:"
            : $this->tenantPrefix($etablissementId);
        return $this->driver->stats($prefix);
    }

    /**
     * Invalide TOUS les tenants d'un coup — réservé aux tests (repartir d'un
     * cache vide entre deux scénarios). Ne JAMAIS exposer/appeler en dehors
     * d'un contexte de test : c'est la seule méthode de cette classe qui
     * n'est pas bornée à un etablissement_id explicite.
     */
    public function flushAllForTests(): void
    {
        $this->driver->flushPrefix('tenant:');
    }
}
