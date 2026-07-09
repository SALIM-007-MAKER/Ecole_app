<?php

declare(strict_types=1);

namespace Core\Cache;

/**
 * Cache persistant sur disque — seul backend réellement disponible dans cet
 * environnement (ni Redis ni APCu ne sont installés ici, voir
 * MULTI_TENANT_CACHE_QUEUE_IMPLEMENTATION_REPORT.md §"Hors périmètre").
 *
 * Une entrée = un fichier JSON `{key, value, expires_at}` nommé par
 * sha1($key). Le nom de clé original est conservé dans le contenu du
 * fichier (pas seulement dans le nom haché) pour permettre flushPrefix()/
 * stats() de filtrer par préfixe sans dictionnaire séparé à maintenir en
 * cohérence — au prix d'un scan du répertoire, acceptable au volume de
 * cette application (paramètres/branding/RBAC/dashboards par tenant, pas
 * des millions d'entrées).
 */
final class FileCache implements CacheInterface
{
    public function __construct(private readonly string $dir)
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }
    }

    private function pathFor(string $key): string
    {
        return $this->dir . '/' . sha1($key) . '.json';
    }

    public function get(string $key): mixed
    {
        $entry = $this->readEntry($this->pathFor($key));
        return $entry !== null ? $entry['value'] : null;
    }

    public function has(string $key): bool
    {
        return $this->readEntry($this->pathFor($key)) !== null;
    }

    public function put(string $key, mixed $value, int $ttlSeconds = 3600): void
    {
        $path = $this->pathFor($key);
        $payload = json_encode([
            'key'        => $key,
            'value'      => $value,
            'expires_at' => time() + max(1, $ttlSeconds),
        ], JSON_THROW_ON_ERROR);

        $tmp = $path . '.' . uniqid('', true) . '.tmp';
        file_put_contents($tmp, $payload);
        rename($tmp, $path); // écriture atomique — évite une lecture partielle par un autre process
    }

    public function forget(string $key): void
    {
        $path = $this->pathFor($key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function flushPrefix(string $prefix): void
    {
        foreach ($this->scanEntries() as $path => $entry) {
            if (str_starts_with($entry['key'], $prefix)) {
                unlink($path);
            }
        }
    }

    public function stats(string $prefix = ''): array
    {
        $count = 0;
        foreach ($this->scanEntries() as $entry) {
            if ($prefix === '' || str_starts_with($entry['key'], $prefix)) {
                $count++;
            }
        }
        return ['keys' => $count];
    }

    private function readEntry(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        try {
            $entry = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($entry) || !isset($entry['expires_at'], $entry['value'], $entry['key'])) {
            return null;
        }
        if ($entry['expires_at'] < time()) {
            @unlink($path);
            return null;
        }
        return $entry;
    }

    /** @return \Generator<string, array{key:string,value:mixed,expires_at:int}> chemin => entrée (expire automatiquement filtrées) */
    private function scanEntries(): \Generator
    {
        foreach (glob($this->dir . '/*.json') ?: [] as $path) {
            $entry = $this->readEntry($path);
            if ($entry !== null) {
                yield $path => $entry;
            }
        }
    }
}
