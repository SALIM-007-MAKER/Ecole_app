<?php

declare(strict_types=1);

namespace Core\Storage;

/**
 * Adaptateur disque local — MULTI_TENANT_V2_BLUEPRINT.md §12.3.
 *
 * Stocke sous ROOT_PATH/storage/tenants/... c'est-à-dire HORS du webroot
 * (public/), conformément au blueprint ("Sécurité : .htaccess deny all +
 * token signé en PHP") — un fichier ne peut donc jamais être atteint par une
 * URL directe, uniquement via url() (signature HMAC + expiration) et une
 * route de service qui la vérifie.
 *
 * Isolation : toute méthode rejette un $path contenant '..' ou commençant
 * par '/' — aucune traversée hors de storage/tenants/ n'est possible.
 */
final class LocalStorageAdapter implements StorageInterface
{
    public function __construct(
        private readonly string $baseDir,
        private readonly string $signingKey,
    ) {
    }

    private function resolve(string $path): string
    {
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            throw new \InvalidArgumentException("Chemin de stockage invalide : {$path}");
        }
        return $this->baseDir . '/' . $path;
    }

    public function put(string $path, string $content): string
    {
        $abs = $this->resolve($path);
        $dir = dirname($abs);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Impossible de créer le répertoire de stockage : {$dir}");
        }
        if (file_put_contents($abs, $content) === false) {
            throw new \RuntimeException("Échec de l'écriture du fichier : {$path}");
        }
        return $path;
    }

    public function get(string $path): string
    {
        $abs = $this->resolve($path);
        if (!is_file($abs)) {
            throw new \RuntimeException("Fichier introuvable : {$path}");
        }
        $content = file_get_contents($abs);
        if ($content === false) {
            throw new \RuntimeException("Échec de la lecture du fichier : {$path}");
        }
        return $content;
    }

    /**
     * URL signée (HMAC-SHA256, expire après $expiresIn secondes) — §12.3.
     * Aucune route HTTP ne consomme ce token pour l'instant (aucun module
     * appelant réel en dehors du branding, qui garde son propre mécanisme
     * de service public) ; la génération/vérification est néanmoins
     * implémentée et testée pour être prête dès qu'un module consommateur
     * (Documents, Bibliothèque...) sera activé — voir rapport §"Hors périmètre".
     */
    public function url(string $path, int $expiresIn = 3600): string
    {
        $expires = time() + $expiresIn;
        $signature = self::sign($path, $expires, $this->signingKey);
        return sprintf('/storage/serve?path=%s&expires=%d&sig=%s', rawurlencode($path), $expires, $signature);
    }

    public static function sign(string $path, int $expires, string $key): string
    {
        return hash_hmac('sha256', $path . '|' . $expires, $key);
    }

    public static function verify(string $path, int $expires, string $signature, string $key): bool
    {
        if ($expires < time()) {
            return false;
        }
        return hash_equals(self::sign($path, $expires, $key), $signature);
    }

    public function delete(string $path): void
    {
        $abs = $this->resolve($path);
        if (is_file($abs)) {
            unlink($abs);
        }
    }

    public function exists(string $path): bool
    {
        return is_file($this->resolve($path));
    }

    public function size(string $path): int
    {
        $abs = $this->resolve($path);
        return is_file($abs) ? (int)filesize($abs) : 0;
    }
}
