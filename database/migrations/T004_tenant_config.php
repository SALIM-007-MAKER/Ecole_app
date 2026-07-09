<?php

/**
 * T004 — Phase 14.2 (Infrastructure Tenant / Fondation) — Étape A4
 *
 * Crée les tables de configuration par établissement : paramètres
 * génériques (etab_settings), cache de branding dénormalisé
 * (etablissement_branding), domaines personnalisés (etablissement_domains).
 * Tables structurelles uniquement — SettingsService/BrandingService et
 * l'UI d'administration sont Phase 14.5, la vérification DNS/SSL est
 * Phase 14.7. Toutes deux hors périmètre ici.
 *
 * Purement additif — aucune table existante n'est modifiée.
 *
 * Voir MULTI_TENANT_V2_BLUEPRINT.md §9.2, §23.2, §11.1.
 */

return [
    'id'         => 'T004',
    'name'       => 'Tables configuration tenant (etab_settings, etablissement_branding, etablissement_domains)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {

        // ── etab_settings (clé/valeur par section, par établissement) — §9.2 ─
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `etab_settings` (
                `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `etablissement_id` INT UNSIGNED    NOT NULL,
                `section`          VARCHAR(60)     NOT NULL,
                `cle`              VARCHAR(100)    NOT NULL,
                `valeur`           TEXT            NULL,
                `type`             ENUM('string','integer','boolean','json','datetime') NOT NULL DEFAULT 'string',
                `modifiable_ui`    TINYINT(1)      NOT NULL DEFAULT 1,
                `updated_by`       INT UNSIGNED    NULL,
                `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_etab_setting` (`etablissement_id`, `section`, `cle`),
                KEY `idx_etab_section` (`etablissement_id`, `section`),
                CONSTRAINT `fk_etabset_etab`    FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_etabset_updater` FOREIGN KEY (`updated_by`)       REFERENCES `users`(`id`)          ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── etablissement_branding (cache dénormalisé) — §23.2 ───────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `etablissement_branding` (
                `etablissement_id` INT UNSIGNED    NOT NULL,
                `logo_url`         VARCHAR(500)    NULL,
                `logo_dark_url`    VARCHAR(500)    NULL,
                `favicon_url`      VARCHAR(500)    NULL,
                `primary_color`    CHAR(7)         NOT NULL DEFAULT '#6366f1',
                `secondary_color`  CHAR(7)         NOT NULL DEFAULT '#0ea5e9',
                `app_name`         VARCHAR(100)    NOT NULL DEFAULT 'EduNova',
                `welcome_message`  TEXT            NULL,
                `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (`etablissement_id`),
                CONSTRAINT `fk_etabbrand_etab` FOREIGN KEY (`etablissement_id`)
                    REFERENCES `etablissements`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── etablissement_domains (domaines personnalisés) — §11.1 ──────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `etablissement_domains` (
                `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `etablissement_id` INT UNSIGNED    NOT NULL,
                `domain`           VARCHAR(255)    NOT NULL,
                `type`             ENUM('custom','subdomain') NOT NULL DEFAULT 'subdomain',
                `verified`         TINYINT(1)      NOT NULL DEFAULT 0,
                `ssl_status`       ENUM('pending','issued','error') NOT NULL DEFAULT 'pending',
                `ssl_expires_at`   DATE            NULL,
                `verified_at`      DATETIME        NULL,
                `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_etabdomain_domain` (`domain`),
                KEY `idx_etabdomain_etab` (`etablissement_id`),
                CONSTRAINT `fk_etabdomain_etab` FOREIGN KEY (`etablissement_id`)
                    REFERENCES `etablissements`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `etablissement_domains`");
        $pdo->exec("DROP TABLE IF EXISTS `etablissement_branding`");
        $pdo->exec("DROP TABLE IF EXISTS `etab_settings`");
    },
];
