<?php

/**
 * T046 — Schéma des jetons de vérification des bulletins (ADDITIF uniquement)
 *
 *   1. bulletins_v2.token_version (TINYINT UNSIGNED NOT NULL DEFAULT 1)
 *        1 = jeton hérité (ancien format, jamais accepté comme jeton officiel)
 *        2 = jeton HMAC de BulletinTokenService (seul format accepté par la vérification publique)
 *      Les lignes existantes reçoivent la valeur par défaut 1 : AUCUN jeton n'est modifié par cette migration.
 *   2. Table bulletin_tokens_retired : empreintes SHA-256 des anciens jetons retirés (jamais les jetons
 *      eux-mêmes), pour détecter l'usage d'un ancien QR sans l'accepter. Vide à la création.
 *
 * Cette migration ne touche à aucune donnée et ne génère aucun jeton. La rotation des jetons est une
 * opération distincte, manuelle et non automatique (database/manual/T047_rotate_bulletin_tokens.php).
 *
 * Idempotente. Retour arrière : supprime la colonne et la table SEULEMENT si elles ne contiennent pas
 * d'information à conserver (aucune ligne retirée, aucun jeton de version 2).
 */

return [
    'id'         => 'T046',
    'name'       => 'Bulletins : token_version et table bulletin_tokens_retired (schéma additif)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $hasColumn = (int)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bulletins_v2' AND COLUMN_NAME = 'token_version'"
        )->fetchColumn();

        if ($hasColumn === 0) {
            $pdo->exec(
                "ALTER TABLE `bulletins_v2`
                 ADD COLUMN `token_version` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `verification_token`"
            );
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `bulletin_tokens_retired` (
                `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `token_sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `bulletin_id`  INT UNSIGNED NULL,
                `motif`        VARCHAR(40) NOT NULL,
                `retire_le`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_btr_sha` (`token_sha256`),
                KEY `idx_btr_bulletin` (`bulletin_id`),
                CONSTRAINT `fk_btr_bulletin` FOREIGN KEY (`bulletin_id`)
                    REFERENCES `bulletins_v2` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'rollback' => function (PDO $pdo): void {
        $hasTable = $pdo->query("SHOW TABLES LIKE 'bulletin_tokens_retired'")->fetch(PDO::FETCH_NUM);
        if ($hasTable) {
            if ((int)$pdo->query("SELECT COUNT(*) FROM `bulletin_tokens_retired`")->fetchColumn() > 0) {
                throw new RuntimeException('Retour arrière refusé : bulletin_tokens_retired contient des jetons retirés.');
            }
        }
        $hasColumn = (int)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bulletins_v2' AND COLUMN_NAME = 'token_version'"
        )->fetchColumn();
        if ($hasColumn > 0 && (int)$pdo->query("SELECT COUNT(*) FROM `bulletins_v2` WHERE token_version > 1")->fetchColumn() > 0) {
            throw new RuntimeException('Retour arrière refusé : des bulletins portent un jeton de version 2.');
        }

        if ($hasTable) {
            $pdo->exec("DROP TABLE `bulletin_tokens_retired`");
        }
        if ($hasColumn > 0) {
            $pdo->exec("ALTER TABLE `bulletins_v2` DROP COLUMN `token_version`");
        }
    },
];
