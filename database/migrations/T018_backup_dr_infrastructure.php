<?php

/**
 * T018 — Phase 14.11 (Sauvegardes & Reprise après incident)
 *
 * Crée `platform_backups` (schéma EXACT du blueprint §19.4, `type` étendu
 * additivement avec 'differential' — "sauvegarde différentielle (si
 * prévue)" du blueprint 14.11, non listée dans le schéma original mais
 * explicitement demandée par cette phase) et `platform_restores`
 * (journalisation des restaurations — absente du blueprint, nécessaire pour
 * satisfaire "journalisation des restaurations" / "traçabilité des
 * opérations" de cette même phase, construite avec les mêmes conventions
 * de nommage que platform_backups).
 *
 * Idempotent, purement additif.
 */

return [
    'id'         => 'T018',
    'name'       => 'platform_backups (§19.4) + platform_restores',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $tables = $pdo->query("SHOW TABLES LIKE 'platform_backups'")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) {
            $pdo->exec(
                "CREATE TABLE `platform_backups` (
                    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                    `etablissement_id` INT UNSIGNED    NULL,
                    `type`             ENUM('global','tenant','pre_migration','manual','differential') NOT NULL,
                    `statut`           ENUM('pending','running','success','failed') NOT NULL DEFAULT 'pending',
                    `storage_path`     VARCHAR(500)    NOT NULL,
                    `size_bytes`       BIGINT UNSIGNED NULL,
                    `checksum`         VARCHAR(64)     NULL,
                    `encrypted`        TINYINT(1)      NOT NULL DEFAULT 0,
                    `triggered_by`     INT UNSIGNED    NULL,
                    `started_at`       DATETIME        NULL,
                    `completed_at`     DATETIME        NULL,
                    `duration_ms`      INT UNSIGNED    NULL,
                    `expires_at`       DATE            NULL,
                    `error_message`    TEXT            NULL,
                    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_etab`   (`etablissement_id`),
                    KEY `idx_type`   (`type`, `statut`),
                    KEY `idx_expire` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $tables2 = $pdo->query("SHOW TABLES LIKE 'platform_restores'")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables2)) {
            $pdo->exec(
                "CREATE TABLE `platform_restores` (
                    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                    `backup_id`        INT UNSIGNED    NOT NULL,
                    `etablissement_id` INT UNSIGNED    NULL,
                    `type`             ENUM('global_verify','tenant_live','selective') NOT NULL,
                    `statut`           ENUM('pending','running','success','failed') NOT NULL DEFAULT 'pending',
                    `rows_restored`    INT UNSIGNED    NULL,
                    `target_info`      VARCHAR(255)    NULL,
                    `triggered_by`     INT UNSIGNED    NULL,
                    `started_at`       DATETIME        NULL,
                    `completed_at`     DATETIME        NULL,
                    `duration_ms`      INT UNSIGNED    NULL,
                    `error_message`    TEXT            NULL,
                    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_backup` (`backup_id`),
                    KEY `idx_etab`   (`etablissement_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `platform_restores`");
        $pdo->exec("DROP TABLE IF EXISTS `platform_backups`");
    },
];
