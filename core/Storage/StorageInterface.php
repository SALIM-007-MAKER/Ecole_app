<?php

declare(strict_types=1);

namespace Core\Storage;

/**
 * Interface de stockage multi-tenant — MULTI_TENANT_V2_BLUEPRINT.md §12.1.
 *
 * Tout chemin ($path) est TOUJOURS relatif à un tenant et suit la convention
 * §12.2 : {etablissement_id}/{module}/{context}/{filename} — c'est
 * l'appelant (TenantQuotaService / services métier) qui construit ce chemin
 * via StorageManager::pathFor(), jamais une valeur arbitraire fournie par
 * une requête HTTP.
 */
interface StorageInterface
{
    /** Écrit du contenu à $path et retourne le chemin final (peut différer si collision). */
    public function put(string $path, string $content): string;

    public function get(string $path): string;

    /** URL d'accès (signée pour 'local'/'s3' — voir LocalStorageAdapter::url()). */
    public function url(string $path, int $expiresIn = 3600): string;

    public function delete(string $path): void;

    public function exists(string $path): bool;

    public function size(string $path): int;
}
