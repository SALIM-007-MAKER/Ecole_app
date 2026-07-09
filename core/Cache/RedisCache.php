<?php

declare(strict_types=1);

namespace Core\Cache;

/**
 * Adaptateur Redis — HORS PÉRIMÈTRE RÉEL de cette phase : l'extension
 * `redis` (ni `apcu`) n'est pas installée dans cet environnement WAMP
 * (vérifié : `php -m` ne la liste pas). Implémenter un client fonctionnel
 * sans pouvoir le tester contre un vrai serveur produirait du code invérifié
 * présenté comme fiable — même discipline que Core\Storage\S3StorageAdapter
 * (Phase 14.8). Respecte strictement CacheInterface pour qu'un remplacement
 * futur soit localisé à ce seul fichier.
 */
final class RedisCache implements CacheInterface
{
    public function __construct(private readonly string $host, private readonly int $port)
    {
    }

    private function notImplemented(): never
    {
        throw new \RuntimeException(
            "Adaptateur Redis non implémenté dans cet environnement (extension 'redis' absente). " .
            "Configurez CACHE_DRIVER=file, ou implémentez RedisCache contre un serveur réel avant de l'activer en production."
        );
    }

    public function get(string $key): mixed { $this->notImplemented(); }
    public function has(string $key): bool { $this->notImplemented(); }
    public function put(string $key, mixed $value, int $ttlSeconds = 3600): void { $this->notImplemented(); }
    public function forget(string $key): void { $this->notImplemented(); }
    public function flushPrefix(string $prefix): void { $this->notImplemented(); }
    public function stats(string $prefix = ''): array { $this->notImplemented(); }
}
