<?php

/**
 * T072 — Double authentification (TOTP) des opérateurs de la plateforme
 *
 * 1. `platform_operators` : + `totp_secret` (secret CHIFFRÉ avec une clé dérivée de APP_KEY, jamais en clair), `totp_enabled_at` (NULL = 2FA
 *    inactive), `totp_last_step` (dernier pas de 30 s accepté : un code ne peut pas être rejoué).
 * 2. `platform_operator_recovery_codes` : codes de secours à usage unique, stockés HACHÉS (HMAC-SHA256), `used_at` renseigné à l'emploi.
 *
 * Additive et idempotente : aucune ligne n'est modifiée, aucun opérateur n'a la 2FA tant qu'il ne l'a pas activée. Retour arrière : retire
 * la table des codes et les trois colonnes (les secrets enregistrés sont perdus : chaque opérateur devra se réinscrire).
 */

return [
    'id'         => 'T072',
    'name'       => 'Double authentification TOTP des opérateurs — secret chiffré, anti-rejeu, codes de secours hachés',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $colExiste = static function (string $table, string $colonne) use ($pdo): bool {
            $s = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $s->execute([$table, $colonne]);
            return (int)$s->fetchColumn() > 0;
        };
        $ajouts = [
            'totp_secret'     => 'ADD COLUMN `totp_secret` VARCHAR(255) NULL',
            'totp_enabled_at' => 'ADD COLUMN `totp_enabled_at` DATETIME NULL',
            'totp_last_step'  => 'ADD COLUMN `totp_last_step` BIGINT UNSIGNED NULL',
        ];
        foreach ($ajouts as $colonne => $definition) {
            if (!$colExiste('platform_operators', $colonne)) {
                $pdo->exec("ALTER TABLE `platform_operators` $definition");
            }
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `platform_operator_recovery_codes` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `operator_id` INT UNSIGNED NOT NULL,
                `code_hash`   CHAR(64) NOT NULL,
                `used_at`     DATETIME NULL,
                `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_porc_lookup` (`operator_id`, `code_hash`),
                CONSTRAINT `fk_porc_operator` FOREIGN KEY (`operator_id`) REFERENCES `platform_operators`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Codes de secours 2FA des opérateurs — hachés, à usage unique'
        ");
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `platform_operator_recovery_codes`');
        foreach (['totp_secret', 'totp_enabled_at', 'totp_last_step'] as $colonne) {
            $s = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $s->execute(['platform_operators', $colonne]);
            if ((int)$s->fetchColumn() > 0) {
                $pdo->exec("ALTER TABLE `platform_operators` DROP COLUMN `$colonne`");
            }
        }
    },
];
