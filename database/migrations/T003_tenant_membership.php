<?php

/**
 * T003 — Phase 14.2 (Infrastructure Tenant / Fondation) — Étape A2
 *
 * Crée les tables d'appartenance multi-établissements : un utilisateur
 * (table `users`, inchangée) peut appartenir à N établissements et y tenir
 * un rôle différent. Tables structurelles uniquement — la bascule du login
 * / school-picker est Phase 14.6, hors périmètre ici.
 *
 * Purement additif — aucune table existante n'est modifiée.
 *
 * Voir MULTI_TENANT_V2_BLUEPRINT.md §4.3, §7.1, §8.2.
 */

return [
    'id'         => 'T003',
    'name'       => 'Tables appartenance multi-établissements (user_etablissements, user_roles_etab)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {

        // ── user_etablissements (membership) — §4.3 ──────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_etablissements` (
                `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id`          INT UNSIGNED    NOT NULL,
                `etablissement_id` INT UNSIGNED    NOT NULL,
                `is_primary`       TINYINT(1)      NOT NULL DEFAULT 0,
                `joined_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `left_at`          DATETIME        NULL,
                `invited_by`       INT UNSIGNED    NULL,

                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_userEtab` (`user_id`, `etablissement_id`),
                KEY `idx_ue_user` (`user_id`),
                KEY `idx_ue_etab` (`etablissement_id`),
                CONSTRAINT `fk_ue_user`    FOREIGN KEY (`user_id`)          REFERENCES `users`(`id`)          ON DELETE CASCADE,
                CONSTRAINT `fk_ue_etab`    FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_ue_inviter` FOREIGN KEY (`invited_by`)       REFERENCES `users`(`id`)          ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── user_roles_etab (rôle d'un utilisateur DANS un établissement) ────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_roles_etab` (
                `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id`          INT UNSIGNED    NOT NULL,
                `etablissement_id` INT UNSIGNED    NOT NULL,
                `role_id`          INT UNSIGNED    NOT NULL,
                `assigned_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `assigned_by`      INT UNSIGNED    NULL,

                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_userRoleEtab` (`user_id`, `etablissement_id`, `role_id`),
                KEY `idx_ure_user` (`user_id`),
                KEY `idx_ure_etab` (`etablissement_id`),
                KEY `idx_ure_role` (`role_id`),
                CONSTRAINT `fk_ure_user`     FOREIGN KEY (`user_id`)          REFERENCES `users`(`id`)          ON DELETE CASCADE,
                CONSTRAINT `fk_ure_etab`     FOREIGN KEY (`etablissement_id`) REFERENCES `etablissements`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_ure_role`     FOREIGN KEY (`role_id`)          REFERENCES `etab_roles`(`id`)     ON DELETE CASCADE,
                CONSTRAINT `fk_ure_assigner` FOREIGN KEY (`assigned_by`)      REFERENCES `users`(`id`)          ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `user_roles_etab`");
        $pdo->exec("DROP TABLE IF EXISTS `user_etablissements`");
    },
];
