<?php

/**
 * T064 — Opérateurs de la plateforme indépendants des comptes d'établissement + journal d'audit global (append-only)
 *
 * 1. `platform_operators` : l'opérateur dispose de sa propre identité (email, nom, mot de passe haché, changement de mot de passe
 *    obligatoire, dernière connexion). `user_id` devient NULLABLE : un nouvel opérateur n'a AUCUN lien avec `users`. Les lignes existantes
 *    (liées à un compte d'établissement, sans mot de passe propre) sont conservées telles quelles mais ne peuvent plus se connecter :
 *    la connexion /platform exige désormais `password_hash`. Aucun compte n'est créé ici (aucun identifiant par défaut) : le premier
 *    opérateur se crée avec `php database/create-operator.php`.
 * 2. `platform_audit_logs` : journal global des opérations de la plateforme. Ajout seul : deux déclencheurs refusent tout UPDATE/DELETE.
 *    (TRUNCATE/DROP ne passent pas par les déclencheurs : en production, retirer ces droits à l'utilisateur MySQL de l'application,
 *    voir docs/deploiement/OPERATEUR_PLATEFORME.md.)
 * 3. `platform_backups.triggered_by` / `platform_restores.triggered_by` : avant T064 la valeur est un `users.id`, après T064 un
 *    `platform_operators.id`. Une colonne `triggered_by_kind` ('user' | 'operator') lève toute ambiguïté : les anciennes lignes prennent
 *    'user' (valeur par défaut, exacte pour elles), les nouvelles 'operator'. Aucune donnée n'est modifiée ni supprimée.
 *
 * Idempotente, purement additive (hors passage de user_id à NULLABLE). NON APPLIQUÉE À LA BASE RÉELLE sans sauvegarde vérifiée.
 * Retour arrière : retire les déclencheurs, la table d'audit et les colonnes ajoutées (user_id reste NULLABLE).
 */

return [
    'id'         => 'T064',
    'name'       => 'Opérateurs plateforme indépendants (identité propre) + journal d\'audit global append-only',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $colExiste = static function (string $table, string $colonne) use ($pdo): bool {
            $s = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $s->execute([$table, $colonne]);
            return (int)$s->fetchColumn() > 0;
        };
        $indexExiste = static function (string $table, string $index) use ($pdo): bool {
            $s = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
            $s->execute([$table, $index]);
            return (int)$s->fetchColumn() > 0;
        };

        // 1. platform_operators : identité propre -------------------------------------------------------------------------------
        $ajouts = [
            'email'                => 'ADD COLUMN `email` VARCHAR(191) NULL AFTER `user_id`',
            'nom'                  => 'ADD COLUMN `nom` VARCHAR(150) NULL AFTER `email`',
            'password_hash'        => 'ADD COLUMN `password_hash` VARCHAR(255) NULL AFTER `nom`',
            'must_change_password' => 'ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_hash`',
            'password_changed_at'  => 'ADD COLUMN `password_changed_at` DATETIME NULL AFTER `must_change_password`',
            'last_login_at'        => 'ADD COLUMN `last_login_at` DATETIME NULL AFTER `password_changed_at`',
        ];
        foreach ($ajouts as $colonne => $ddl) {
            if (!$colExiste('platform_operators', $colonne)) {
                $pdo->exec("ALTER TABLE `platform_operators` {$ddl}");
            }
        }
        if (!$indexExiste('platform_operators', 'uq_operator_email')) {
            $pdo->exec('ALTER TABLE `platform_operators` ADD UNIQUE KEY `uq_operator_email` (`email`)');
        }
        $pdo->exec('ALTER TABLE `platform_operators` MODIFY COLUMN `user_id` INT UNSIGNED NULL');

        // 2. platform_audit_logs : journal global en ajout seul --------------------------------------------------------------------
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `platform_audit_logs` (
                `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `occurred_at`      DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                `actor_type`       ENUM('operator','system','anonymous') NOT NULL DEFAULT 'operator',
                `operator_id`      INT UNSIGNED    NULL COMMENT 'platform_operators.id au moment de l\\'action (sans clé étrangère : le journal ne doit jamais être modifié en cascade)',
                `actor_email`      VARCHAR(191)    NULL COMMENT 'Instantané (email de l\\'opérateur, ou email saisi pour une connexion anonyme)',
                `actor_niveau`     VARCHAR(20)     NULL COMMENT 'Instantané du niveau (support/admin/super_admin) ou system',
                `etablissement_id` INT UNSIGNED    NULL COMMENT 'Établissement concerné, sans clé étrangère',
                `action`           VARCHAR(80)     NOT NULL,
                `entity_type`      VARCHAR(60)     NULL,
                `entity_id`        BIGINT UNSIGNED NULL,
                `resultat`         ENUM('success','failure','denied') NOT NULL DEFAULT 'success',
                `ancienne_valeur`  JSON            NULL,
                `nouvelle_valeur`  JSON            NULL,
                `ip`               VARCHAR(45)     NULL,
                `user_agent`       VARCHAR(500)    NULL,
                PRIMARY KEY (`id`),
                KEY `idx_pal_date` (`occurred_at`),
                KEY `idx_pal_action` (`action`, `occurred_at`),
                KEY `idx_pal_etab` (`etablissement_id`, `occurred_at`),
                KEY `idx_pal_operator` (`operator_id`, `occurred_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Journal d\\'audit global de la plateforme — ajout seul (UPDATE/DELETE refusés par déclencheur)'"
        );
        $pdo->exec('DROP TRIGGER IF EXISTS `trg_platform_audit_no_update`');
        $pdo->exec(
            "CREATE TRIGGER `trg_platform_audit_no_update` BEFORE UPDATE ON `platform_audit_logs` FOR EACH ROW
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'platform_audit_logs est en ajout seul : modification interdite'"
        );
        $pdo->exec('DROP TRIGGER IF EXISTS `trg_platform_audit_no_delete`');
        $pdo->exec(
            "CREATE TRIGGER `trg_platform_audit_no_delete` BEFORE DELETE ON `platform_audit_logs` FOR EACH ROW
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'platform_audit_logs est en ajout seul : suppression interdite'"
        );

        // 3. triggered_by : nature de l'identifiant ----------------------------------------------------------------------------------
        foreach (['platform_backups', 'platform_restores'] as $table) {
            if (!$colExiste($table, 'triggered_by_kind')) {
                $pdo->exec(
                    "ALTER TABLE `{$table}` ADD COLUMN `triggered_by_kind` ENUM('user','operator') NOT NULL DEFAULT 'user'
                     COMMENT 'Nature de triggered_by : user = users.id (avant T064), operator = platform_operators.id (depuis T064)' AFTER `triggered_by`"
                );
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec('DROP TRIGGER IF EXISTS `trg_platform_audit_no_update`');
        $pdo->exec('DROP TRIGGER IF EXISTS `trg_platform_audit_no_delete`');
        $pdo->exec('DROP TABLE IF EXISTS `platform_audit_logs`');
        foreach (['platform_backups', 'platform_restores'] as $table) {
            $s = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $s->execute([$table, 'triggered_by_kind']);
            if ((int)$s->fetchColumn() > 0) {
                $pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `triggered_by_kind`");
            }
        }
        // Les colonnes d'identité de platform_operators ne sont retirées que si aucun opérateur indépendant n'existe (sinon perte de comptes).
        $independants = (int)$pdo->query('SELECT COUNT(*) FROM `platform_operators` WHERE `user_id` IS NULL OR `password_hash` IS NOT NULL')->fetchColumn();
        if ($independants === 0) {
            $pdo->exec('ALTER TABLE `platform_operators` DROP INDEX `uq_operator_email`');
            foreach (['email', 'nom', 'password_hash', 'must_change_password', 'password_changed_at', 'last_login_at'] as $col) {
                $pdo->exec("ALTER TABLE `platform_operators` DROP COLUMN `{$col}`");
            }
            $pdo->exec('ALTER TABLE `platform_operators` MODIFY COLUMN `user_id` INT UNSIGNED NOT NULL');
        }
    },
];
