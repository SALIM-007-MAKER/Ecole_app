<?php

/**
 * Configuration du stockage multi-tenant — Phase 14.8.
 * Voir MULTI_TENANT_V2_BLUEPRINT.md §12.3.
 *
 * STORAGE_DRIVER=local (défaut, seul driver réellement fonctionnel dans cet
 * environnement de développement) | s3 (stub non fonctionnel — voir
 * Core\Storage\S3StorageAdapter).
 */
return [
    'driver' => $_ENV['STORAGE_DRIVER'] ?? 'local',

    'local' => [
        // Hors du webroot (public/) — accès uniquement via URL signée.
        'base_dir' => ROOT_PATH . '/storage/tenants',
    ],

    's3' => [
        'bucket'            => $_ENV['AWS_BUCKET'] ?? null,
        'region'            => $_ENV['AWS_REGION'] ?? null,
        'access_key_id'     => $_ENV['AWS_ACCESS_KEY_ID'] ?? null,
        'secret_access_key' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null,
    ],

    // Clé de signature des URLs temporaires (LocalStorageAdapter::url()).
    'signing_key' => $_ENV['STORAGE_SIGNING_KEY'] ?? $_ENV['APP_KEY'] ?? 'edunova-default-storage-key-change-in-production',
];
