<?php

/**
 * T013 — Phase 14.8 (Stockage & Quotas)
 *
 * 1. Crée `api_uploads` : registre des fichiers écrits via
 *    Core\Storage\StorageInterface, utilisé pour calculer l'usage disque
 *    par tenant (blueprint §12.4 : SUM(size_bytes) WHERE etablissement_id).
 *    Suppression logique (deleted_at) pour conserver l'historique
 *    ("historique" demandé en Phase 14.8) sans fausser le recalcul d'usage
 *    (qui exclut les lignes supprimées).
 *
 * 2. Ajoute `max_documents` / `max_attachments` à `etablissements` —
 *    extension des quotas déjà présents (storage_quota_mb, max_users,
 *    max_eleves existent depuis la Phase 14.2, T001/T002) pour couvrir les
 *    dimensions supplémentaires demandées en 14.8. NULL = illimité, même
 *    convention que le reste de la table.
 *
 * Idempotent, purement additif.
 */

return [
    'id'         => 'T013',
    'name'       => 'api_uploads (ledger stockage) + etablissements.max_documents/max_attachments',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $tables = $pdo->query("SHOW TABLES LIKE 'api_uploads'")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) {
            $pdo->exec(
                "CREATE TABLE `api_uploads` (
                    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `etablissement_id`  INT UNSIGNED    NOT NULL,
                    `module`            VARCHAR(60)     NOT NULL,
                    `context`           VARCHAR(60)     NOT NULL,
                    `path`              VARCHAR(500)    NOT NULL,
                    `size_bytes`        BIGINT UNSIGNED NOT NULL,
                    `mime_type`         VARCHAR(120)    NULL,
                    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `deleted_at`        DATETIME        NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_etab`         (`etablissement_id`),
                    KEY `idx_etab_active`  (`etablissement_id`, `deleted_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $columns = $pdo->query(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etablissements'"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('max_documents', $columns, true)) {
            $pdo->exec("ALTER TABLE `etablissements` ADD COLUMN `max_documents` SMALLINT UNSIGNED NULL AFTER `max_eleves`");
        }
        if (!in_array('max_attachments', $columns, true)) {
            $pdo->exec("ALTER TABLE `etablissements` ADD COLUMN `max_attachments` SMALLINT UNSIGNED NULL AFTER `max_documents`");
        }
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `api_uploads`");
        try { $pdo->exec("ALTER TABLE `etablissements` DROP COLUMN `max_documents`"); } catch (\PDOException) {}
        try { $pdo->exec("ALTER TABLE `etablissements` DROP COLUMN `max_attachments`"); } catch (\PDOException) {}
    },
];
