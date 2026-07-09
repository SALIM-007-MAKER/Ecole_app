<?php

/**
 * T001 — Phase 14.2 (Infrastructure Tenant / Fondation) — Étape A1
 *
 * Crée les tables de la couche PLATFORM : registre des établissements
 * (tenants), plans SaaS, opérateurs plateforme.
 * Purement additif — aucune table existante n'est modifiée.
 *
 * Voir MULTI_TENANT_V2_BLUEPRINT.md §4.2, §4.4, §4.5.
 */

return [
    'id'         => 'T001',
    'name'       => 'Tables Platform (etablissements, platform_plans, platform_operators)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {

        // ── etablissements (registre des tenants) — §4.2 ─────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `etablissements` (
                `id`                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,

                `slug`                VARCHAR(60)     NOT NULL,
                `nom`                 VARCHAR(200)    NOT NULL,
                `nom_court`           VARCHAR(60)     NOT NULL,
                `code_etablissement`  VARCHAR(30)     NULL,
                `type`                ENUM('ecole_primaire','college','lycee',
                                             'universite','formation_pro','groupe_scolaire') NOT NULL,
                `pays`                CHAR(2)         NOT NULL DEFAULT 'DZ',
                `wilaya`              VARCHAR(100)    NULL,
                `commune`             VARCHAR(100)    NULL,
                `adresse`             TEXT            NULL,
                `telephone`           VARCHAR(20)     NULL,
                `email`               VARCHAR(191)    NULL,
                `site_web`            VARCHAR(255)    NULL,

                `plan_id`             INT UNSIGNED    NULL,
                `plan_expires_at`     DATE            NULL,
                `storage_quota_mb`    INT UNSIGNED    NOT NULL DEFAULT 1024,
                `max_users`           SMALLINT UNSIGNED NOT NULL DEFAULT 50,
                `max_eleves`          SMALLINT UNSIGNED NOT NULL DEFAULT 500,

                `statut`              ENUM('trial','active','suspended','cancelled') NOT NULL DEFAULT 'trial',
                `trial_ends_at`       DATE            NULL,
                `suspended_at`        DATETIME        NULL,
                `suspension_reason`   VARCHAR(255)    NULL,

                `activated_at`        DATETIME        NULL,
                `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `deleted_at`          DATETIME        NULL,

                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_etab_slug` (`slug`),
                UNIQUE KEY `uq_etab_code` (`code_etablissement`),
                KEY `idx_etab_statut` (`statut`),
                KEY `idx_etab_plan`   (`plan_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── platform_plans (plans SaaS) — §4.4 ───────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `platform_plans` (
                `id`                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `code`                VARCHAR(30)     NOT NULL,
                `nom`                 VARCHAR(100)    NOT NULL,
                `description`         TEXT            NULL,

                `max_users`           SMALLINT UNSIGNED NOT NULL DEFAULT 50,
                `max_eleves`          SMALLINT UNSIGNED NOT NULL DEFAULT 500,
                `storage_quota_mb`    INT UNSIGNED    NOT NULL DEFAULT 1024,
                `api_calls_per_day`   INT UNSIGNED    NOT NULL DEFAULT 10000,
                `modules_inclus`      JSON            NOT NULL,

                `prix_mensuel`        DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
                `prix_annuel`         DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
                `devise`              CHAR(3)         NOT NULL DEFAULT 'DZD',

                `actif`               TINYINT(1)      NOT NULL DEFAULT 1,
                `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_plan_code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── platform_operators (admins SaaS) — §4.5 ──────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `platform_operators` (
                `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `user_id`     INT UNSIGNED    NOT NULL,
                `niveau`      ENUM('support','admin','super_admin') NOT NULL DEFAULT 'support',
                `actif`       TINYINT(1)      NOT NULL DEFAULT 1,
                `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_operator_user` (`user_id`),
                KEY `idx_operator_user` (`user_id`),
                CONSTRAINT `fk_platform_operator_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // FK etablissements.plan_id → platform_plans.id (ajoutée après coup :
        // platform_plans doit exister en premier).
        $fkExists = $pdo->query("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'etablissements'
              AND CONSTRAINT_NAME = 'fk_etab_plan'
        ")->fetchColumn();

        if (!$fkExists) {
            $pdo->exec("
                ALTER TABLE `etablissements`
                ADD CONSTRAINT `fk_etab_plan` FOREIGN KEY (`plan_id`)
                    REFERENCES `platform_plans`(`id`) ON DELETE SET NULL
            ");
        }
    },

    'rollback' => function (PDO $pdo): void {
        try {
            $pdo->exec("ALTER TABLE `etablissements` DROP FOREIGN KEY `fk_etab_plan`");
        } catch (\PDOException) {
            // Contrainte déjà absente — ignorer.
        }
        $pdo->exec("DROP TABLE IF EXISTS `platform_operators`");
        $pdo->exec("DROP TABLE IF EXISTS `platform_plans`");
        $pdo->exec("DROP TABLE IF EXISTS `etablissements`");
    },
];
