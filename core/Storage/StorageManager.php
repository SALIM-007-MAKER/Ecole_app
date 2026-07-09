<?php

declare(strict_types=1);

namespace Core\Storage;

/**
 * Fabrique de l'adaptateur de stockage actif + convention de chemins
 * tenant — MULTI_TENANT_V2_BLUEPRINT.md §12.2/§12.3.
 */
final class StorageManager
{
    private static ?StorageInterface $instance = null;

    public static function driver(): StorageInterface
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = require ROOT_PATH . '/config/storage.php';

        self::$instance = match ($config['driver']) {
            's3'    => new S3StorageAdapter((string)($config['s3']['bucket'] ?? ''), (string)($config['s3']['region'] ?? '')),
            default => new LocalStorageAdapter($config['local']['base_dir'], (string)$config['signing_key']),
        };

        return self::$instance;
    }

    /** Réinitialise l'instance mémorisée (utilisé par les tests pour injecter une config différente). */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Construit un chemin tenant conforme à la convention §12.2 :
     * {etablissement_id}/{module}/{context}/{filename}
     */
    public static function pathFor(int $etablissementId, string $module, string $context, string $filename): string
    {
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?? $filename;
        return "{$etablissementId}/{$module}/{$context}/{$safeFilename}";
    }
}
