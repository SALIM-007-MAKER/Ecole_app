<?php

/**
 * T002 — Phase 14.2 (Infrastructure Tenant / Fondation) — Étape A3
 *
 * Crée le SCHÉMA RBAC multi-tenant (etab_roles, etab_permissions,
 * etab_role_permissions). Tables structurelles uniquement : aucune
 * connexion au système d'authentification/permissions actuel
 * (config/permissions.php reste la source de vérité en production).
 * Le branchement RBAC réel est prévu Phase 14.4 — hors périmètre ici.
 *
 * Purement additif — aucune table existante n'est modifiée.
 *
 * Voir MULTI_TENANT_V2_BLUEPRINT.md §8.2.
 */

return [
    'id'         => 'T002',
    'name'       => 'Schéma RBAC multi-tenant (etab_roles, etab_permissions, etab_role_permissions)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {

        // ── etab_roles (rôles système + custom par tenant) ───────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `etab_roles` (
                `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `etablissement_id` INT UNSIGNED NULL COMMENT 'NULL = rôle système global',
                `code`             VARCHAR(60)  NOT NULL,
                `nom`              VARCHAR(100) NOT NULL,
                `description`      TEXT         NULL,
                `is_system`        TINYINT(1)   NOT NULL DEFAULT 0,
                `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_etabrole_scope_code` (`etablissement_id`, `code`),
                KEY `idx_etabrole_etab` (`etablissement_id`),
                CONSTRAINT `fk_etabrole_etab` FOREIGN KEY (`etablissement_id`)
                    REFERENCES `etablissements`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── etab_permissions (catalogue système, non modifiable) ─────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `etab_permissions` (
                `id`      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                `code`    VARCHAR(120)  NOT NULL,
                `libelle` VARCHAR(255)  NOT NULL,
                `module`  VARCHAR(50)   NOT NULL,
                `action`  VARCHAR(50)   NOT NULL,

                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_etabperm_code` (`code`),
                KEY `idx_etabperm_module` (`module`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── etab_role_permissions (association rôle → permissions) ───────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `etab_role_permissions` (
                `role_id`       INT UNSIGNED NOT NULL,
                `permission_id` INT UNSIGNED NOT NULL,
                `granted_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `granted_by`    INT UNSIGNED NULL,

                PRIMARY KEY (`role_id`, `permission_id`),
                KEY `idx_etabrp_perm` (`permission_id`),
                CONSTRAINT `fk_etabrp_role` FOREIGN KEY (`role_id`)
                    REFERENCES `etab_roles`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_etabrp_perm` FOREIGN KEY (`permission_id`)
                    REFERENCES `etab_permissions`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_etabrp_granter` FOREIGN KEY (`granted_by`)
                    REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `etab_role_permissions`");
        $pdo->exec("DROP TABLE IF EXISTS `etab_permissions`");
        $pdo->exec("DROP TABLE IF EXISTS `etab_roles`");
    },
];
