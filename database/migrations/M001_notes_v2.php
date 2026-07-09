<?php

/**
 * M001 — Migration sécurisée de la table notes vers le schéma V2
 *
 * Problème : ecole_app.sql crée notes(matiere_id, trimestre, ...) = schéma V1.
 *            academique_migration.sql tente CREATE TABLE IF NOT EXISTS notes
 *            (controle_id, absent, ...) = schéma V2 → silencieusement ignoré si V1 existe.
 *
 * Stratégie :
 *   1. Détecter le schéma actuel via INFORMATION_SCHEMA.
 *   2. Si V2 (controle_id présent)   → rien à faire.
 *   3. Si V1 (matiere_id, pas de controle_id) :
 *      a. Renommer notes → notes_v1_backup (préserve les données).
 *      b. Créer la table V2.
 *      c. NB : les données V1 ne sont pas migrables automatiquement car
 *         V1 n'a pas de controle_id. Elles restent dans notes_v1_backup.
 *   4. Si absente → créer V2 directement.
 *
 * Idempotent : peut être relancé sans risque.
 */

return [
    'id'         => 'M001',
    'name'       => 'Migration sécurisée notes → schéma V2 (controle_id)',
    'reversible' => false,

    'run' => function (PDO $pdo): void {

        $db = $pdo->query("SELECT DATABASE()")->fetchColumn();

        // ── Vérifier si la table notes existe ────────────────────────────────
        $exists = (bool)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = '{$db}' AND TABLE_NAME = 'notes'"
        )->fetchColumn();

        if ($exists) {
            // ── Détecter le schéma actuel ─────────────────────────────────────
            $cols = $pdo->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = '{$db}' AND TABLE_NAME = 'notes'"
            )->fetchAll(PDO::FETCH_COLUMN);

            if (in_array('controle_id', $cols, true)) {
                // Déjà V2 — vérifier et ajouter la colonne updated_at si absente
                if (!in_array('updated_at', $cols, true)) {
                    $pdo->exec(
                        "ALTER TABLE `notes`
                         ADD COLUMN `updated_at` TIMESTAMP NOT NULL
                         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                         AFTER `created_at`"
                    );
                }
                return; // Rien d'autre à faire
            }

            // ── V1 détectée — sauvegarder avant transformation ────────────────
            // Supprimer l'ancien backup s'il existe (idempotence)
            $pdo->exec("DROP TABLE IF EXISTS `notes_v1_backup`");

            // RENAME préserve toutes les données + contraintes
            $pdo->exec("RENAME TABLE `notes` TO `notes_v1_backup`");

            // Supprimer les FK de l'ancienne table dans notes_v1_backup pour ne pas bloquer
            // (les FKs ont migré avec la table — pas de conflit)
        }

        // ── Créer la table V2 ────────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE `notes` (
                `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                `eleve_id`     INT UNSIGNED  NOT NULL,
                `controle_id`  INT UNSIGNED  NOT NULL,
                `note`         DECIMAL(5,2)  NULL     COMMENT 'NULL si élève absent',
                `absent`       TINYINT(1)    NOT NULL DEFAULT 0,
                `appreciation` VARCHAR(255)  NULL,
                `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_note_eleve_controle` (`eleve_id`, `controle_id`),
                INDEX `idx_note_controle` (`controle_id`),
                INDEX `idx_note_eleve`    (`eleve_id`),
                CONSTRAINT `fk_note_eleve`    FOREIGN KEY (`eleve_id`)    REFERENCES `eleves`(`id`)    ON DELETE CASCADE,
                CONSTRAINT `fk_note_controle` FOREIGN KEY (`controle_id`) REFERENCES `controles`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Notes des élèves liées aux contrôles (schéma V2)'
        ");
    },
];
