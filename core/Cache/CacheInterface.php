<?php

declare(strict_types=1);

namespace Core\Cache;

/** Contrat de cache clé/valeur générique — MULTI_TENANT_V2_BLUEPRINT.md (Phase 14.9). */
interface CacheInterface
{
    public function get(string $key): mixed;

    public function has(string $key): bool;

    public function put(string $key, mixed $value, int $ttlSeconds = 3600): void;

    public function forget(string $key): void;

    /** Supprime toutes les clés commençant par $prefix. */
    public function flushPrefix(string $prefix): void;

    /** @return array{keys:int} statistiques sommaires (nombre de clés actives). */
    public function stats(string $prefix = ''): array;
}
