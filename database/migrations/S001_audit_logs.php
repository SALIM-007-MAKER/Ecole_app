<?php

/**
 * S001 — Table audit_logs
 * Nouvelle table — sûr (CREATE IF NOT EXISTS).
 * Requise par AuditService pour fonctionner en mode DB plutôt qu'en dégradation.
 */

return [
    'id'         => 'S001',
    'name'       => 'Créer la table audit_logs',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `audit_logs` (
                `id`         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
                `user_id`    INT UNSIGNED     NULL COMMENT 'NULL = action système ou connexion échouée',
                `action`     VARCHAR(50)      NOT NULL
                             COMMENT 'create|update|delete|login|login_failed|logout|export|import|permission_denied',
                `module`     VARCHAR(50)      NOT NULL
                             COMMENT 'eleves|notes|absences|comptabilite|auth|parametres|...',
                `entite`     VARCHAR(100)     NULL COMMENT 'Nom de la table cible',
                `entite_id`  INT UNSIGNED     NULL COMMENT 'PK de l enregistrement affecté',
                `avant`      JSON             NULL COMMENT 'État avant (update/delete)',
                `apres`      JSON             NULL COMMENT 'État après  (create/update)',
                `ip`         VARCHAR(45)      NULL,
                `user_agent` VARCHAR(500)     NULL,
                `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_al_user`   (`user_id`),
                INDEX `idx_al_module` (`module`, `action`),
                INDEX `idx_al_entite` (`entite`, `entite_id`),
                INDEX `idx_al_date`   (`created_at`),
                CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Journal immuable de toutes les actions utilisateurs'
        ");
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `audit_logs`");
    },
];
