<?php

/**
 * T015 — Phase 14.9 (Cache & Queue Multi-Tenant)
 *
 * Crée `jobs` : file d'attente de tâches asynchrones, tenant-scopée
 * (`etablissement_id` NULL autorisé pour une tâche plateforme non liée à un
 * tenant précis — ex. maintenance globale — mais toute tâche métier réelle
 * le renseigne). Chaque ligne transporte tout ce qu'un worker doit savoir
 * pour rejouer le TenantContext correct avant d'exécuter la tâche
 * (Core\Queue\QueueWorker::processNext()).
 *
 * Idempotent, purement additif.
 */

return [
    'id'         => 'T015',
    'name'       => 'jobs (file d\'attente tenant-scopée)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $tables = $pdo->query("SHOW TABLES LIKE 'jobs'")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) {
            $pdo->exec(
                "CREATE TABLE `jobs` (
                    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `etablissement_id`  INT UNSIGNED    NULL,
                    `type`              VARCHAR(150)    NOT NULL,
                    `payload`           JSON            NOT NULL,
                    `status`            ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
                    `attempts`          TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    `max_attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 3,
                    `run_at`            DATETIME        NOT NULL,
                    `started_at`        DATETIME        NULL,
                    `completed_at`      DATETIME        NULL,
                    `duration_ms`       INT UNSIGNED    NULL,
                    `error`             TEXT            NULL,
                    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_etab`          (`etablissement_id`),
                    KEY `idx_status_runat`  (`status`, `run_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `jobs`");
    },
];
