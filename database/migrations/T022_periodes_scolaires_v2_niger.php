<?php

/**
 * T022 — Tables periodes_scolaires + periodes_scolaires_config (Académique V2)
 *
 * Crée les tables du module Académique V2 dédiées aux périodes scolaires,
 * préconfigurées au calendrier trimestriel officiel du Niger (voir
 * NIGER_SCHOOL_PERIOD_CONFIGURATION_REPORT.md). Le module `academique`
 * reste désactivé (config/modules.php) — cette migration ne fait que
 * préparer le schéma, elle n'active aucune route.
 *
 * Idempotente : CREATE TABLE IF NOT EXISTS + seed via ON DUPLICATE KEY.
 */

return [
    'id'         => 'T022',
    'name'       => 'Périodes scolaires V2 — schéma + configuration par défaut Niger',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `periodes_scolaires` (
                `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                `annee_scolaire`        VARCHAR(9)    NOT NULL,
                `type_periode`          ENUM('trimestre','semestre','custom') NOT NULL DEFAULT 'trimestre',
                `numero`                TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `nom`                   VARCHAR(80)   NOT NULL,
                `date_debut`            DATE          NULL,
                `date_fin`              DATE          NULL,
                `statut`                ENUM('preparation','ouverte','cloturee','archivee') NOT NULL DEFAULT 'preparation',
                `is_active`             TINYINT(1)    NOT NULL DEFAULT 0,
                `notes_saisie_ouverte`  TINYINT(1)    NOT NULL DEFAULT 1,
                `ordre`                 TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `verrouille_par`        INT UNSIGNED  NULL,
                `verrouille_le`         DATETIME      NULL,
                `genere_automatiquement` TINYINT(1)   NOT NULL DEFAULT 0,
                `created_by`            INT UNSIGNED  NULL,
                `created_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_ps_annee_type_num` (`annee_scolaire`, `type_periode`, `numero`),
                INDEX `idx_ps_annee`   (`annee_scolaire`),
                INDEX `idx_ps_statut`  (`statut`),
                INDEX `idx_ps_active`  (`is_active`),
                INDEX `idx_ps_ordre`   (`annee_scolaire`, `ordre`, `numero`),
                INDEX `idx_ps_dates`   (`annee_scolaire`, `date_debut`, `date_fin`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Périodes scolaires V2 — système trimestriel Niger par défaut'
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `periodes_scolaires_config` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `numero`      TINYINT UNSIGNED NOT NULL,
                `nom_defaut`  VARCHAR(80) NOT NULL,
                `mois_debut`  TINYINT UNSIGNED NOT NULL,
                `jour_debut`  TINYINT UNSIGNED NOT NULL,
                `mois_fin`    TINYINT UNSIGNED NOT NULL,
                `jour_fin`    TINYINT UNSIGNED NOT NULL,
                `ordre`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `updated_by`  INT UNSIGNED NULL,
                `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_psc_numero` (`numero`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Modèle par défaut configurable — préconfiguré Niger'
        ");

        $pdo->exec("
            INSERT INTO `periodes_scolaires_config`
                (`numero`, `nom_defaut`, `mois_debut`, `jour_debut`, `mois_fin`, `jour_fin`, `ordre`)
            VALUES
                (1, 'Premier trimestre',   10, 1, 12, 31, 1),
                (2, 'Deuxième trimestre',   1, 1,  3, 31, 2),
                (3, 'Troisième trimestre',  4, 1,  6, 30, 3)
            ON DUPLICATE KEY UPDATE numero = numero
        ");
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `periodes_scolaires_config`");
        $pdo->exec("DROP TABLE IF EXISTS `periodes_scolaires`");
    },
];
