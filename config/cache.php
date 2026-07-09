<?php

/**
 * Configuration du cache multi-tenant — Phase 14.9.
 * Voir MULTI_TENANT_V2_BLUEPRINT.md, TenantCache.
 *
 * CACHE_DRIVER=file (défaut, seul driver réellement fonctionnel dans cet
 * environnement) | redis (stub non fonctionnel — voir Core\Cache\RedisCache).
 */
return [
    'driver' => $_ENV['CACHE_DRIVER'] ?? 'file',

    'file' => [
        'dir' => ROOT_PATH . '/storage/cache',
    ],

    'redis' => [
        'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
        'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
    ],

    // TTL par défaut (secondes) par catégorie de donnée mise en cache.
    'ttl' => [
        'branding'  => 3600,
        'settings'  => 3600,
        'rbac'      => 300,
        'dashboard' => 120,
        'rapports'  => 300,
    ],
];
