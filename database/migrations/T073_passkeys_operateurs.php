<?php

/**
 * T073 — Passkeys (WebAuthn) des opérateurs de la plateforme
 *
 * `platform_operator_passkeys` : une ligne par passkey ou clé de sécurité enregistrée. On stocke l'identifiant du credential (base64url, sensible
 * à la casse), la clé PUBLIQUE (PEM : aucune donnée secrète n'est conservée), l'algorithme, le compteur de signatures et la date d'usage.
 *
 * Additive et idempotente : aucune ligne existante n'est modifiée, aucun opérateur n'a de passkey tant qu'il n'en a pas enregistré une.
 * Retour arrière : supprime la table (chaque opérateur garde son TOTP et ses codes de secours).
 */

return [
    'id'         => 'T073',
    'name'       => 'Passkeys WebAuthn des opérateurs — second facteur par clé publique, compteur de signatures',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `platform_operator_passkeys` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `operator_id`   INT UNSIGNED NOT NULL,
                `credential_id` VARCHAR(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `public_key`    TEXT NOT NULL,
                `alg`           SMALLINT NOT NULL,
                `sign_count`    INT UNSIGNED NOT NULL DEFAULT 0,
                `nom`           VARCHAR(80) NOT NULL,
                `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_used_at`  DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_pop_credential` (`credential_id`),
                KEY `idx_pop_operator` (`operator_id`),
                CONSTRAINT `fk_pop_operator` FOREIGN KEY (`operator_id`) REFERENCES `platform_operators`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Passkeys WebAuthn des opérateurs — clé publique uniquement'
        ");
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `platform_operator_passkeys`');
    },
];
