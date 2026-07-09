<?php

declare(strict_types=1);

namespace Core\Storage;

/**
 * Adaptateur S3-compatible (AWS / OVH / Scaleway / MinIO) — §12.3.
 *
 * HORS PÉRIMÈTRE RÉEL de cette phase : cet environnement local (WAMP) ne
 * dispose d'aucune credential S3, d'aucun bucket, d'aucun accès réseau
 * sortant vérifiable. Implémenter un client S3 fonctionnel sans pouvoir le
 * tester contre un vrai bucket produirait du code invérifié présenté comme
 * fiable — ce que la discipline de ce projet interdit explicitement.
 *
 * Ce stub respecte strictement StorageInterface (le contrat que
 * StorageManager et tout appelant utilisent) afin qu'un remplacement futur
 * par une implémentation réelle (ex: via aws/aws-sdk-php) soit un
 * changement localisé à ce seul fichier — zéro impact sur les appelants.
 * Voir TENANT_STORAGE_QUOTA_IMPLEMENTATION_REPORT.md §"Hors périmètre".
 */
final class S3StorageAdapter implements StorageInterface
{
    public function __construct(
        private readonly string $bucket,
        private readonly string $region,
    ) {
    }

    private function notImplemented(): never
    {
        throw new \RuntimeException(
            "Adaptateur S3 non implémenté dans cet environnement (aucune credential/bucket disponible). " .
            "Configurez STORAGE_DRIVER=local, ou implémentez S3StorageAdapter contre un bucket réel avant de l'activer en production."
        );
    }

    public function put(string $path, string $content): string { $this->notImplemented(); }
    public function get(string $path): string { $this->notImplemented(); }
    public function url(string $path, int $expiresIn = 3600): string { $this->notImplemented(); }
    public function delete(string $path): void { $this->notImplemented(); }
    public function exists(string $path): bool { $this->notImplemented(); }
    public function size(string $path): int { $this->notImplemented(); }
}
