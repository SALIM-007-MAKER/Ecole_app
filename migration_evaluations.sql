-- ============================================================
-- Phase 2.3 — Évaluations V2
-- Migration : CREATE TABLE IF NOT EXISTS (jamais DROP)
-- Les FK référencent les tables V1 (matieres, classes, professeurs)
-- et les tables V2 (periodes_scolaires, types_evaluations)
-- ============================================================

CREATE TABLE IF NOT EXISTS `evaluations` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `periode_scolaire_id`   INT UNSIGNED NOT NULL COMMENT 'FK → periodes_scolaires V2',
    `type_evaluation_id`    INT UNSIGNED NOT NULL COMMENT 'FK → types_evaluations V2',
    `matiere_id`            INT UNSIGNED NOT NULL COMMENT 'FK → matieres V1',
    `classe_id`             INT UNSIGNED NOT NULL COMMENT 'FK → classes V1',
    `enseignant_id`         INT UNSIGNED NULL     COMMENT 'FK → professeurs V1, optionnel',
    `libelle`               VARCHAR(120) NOT NULL COMMENT 'Intitulé de l\'évaluation',
    `description`           TEXT NULL,
    `date_evaluation`       DATE NULL,
    `coefficient`           DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT 'Coefficient spécifique (écrase le défaut du type)',
    `note_max`              DECIMAL(5,2) NOT NULL DEFAULT 20.00 COMMENT 'Barème de cette évaluation',
    `statut`                ENUM('brouillon','publiee','verrouillee','archivee') NOT NULL DEFAULT 'brouillon',
    `notes_saisie_ouverte`  TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Géré automatiquement par le cycle de vie',
    `verrouille_par`        INT UNSIGNED NULL,
    `verrouille_le`         DATETIME NULL,
    `created_by`            INT UNSIGNED NULL,
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ev_periode`      (`periode_scolaire_id`),
    INDEX `idx_ev_type`         (`type_evaluation_id`),
    INDEX `idx_ev_matiere`      (`matiere_id`),
    INDEX `idx_ev_classe`       (`classe_id`),
    INDEX `idx_ev_enseignant`   (`enseignant_id`),
    INDEX `idx_ev_statut`       (`statut`),
    INDEX `idx_ev_date`         (`date_evaluation`),
    INDEX `idx_ev_classe_per`   (`classe_id`, `periode_scolaire_id`),
    CONSTRAINT `fk_ev_periode`    FOREIGN KEY (`periode_scolaire_id`) REFERENCES `periodes_scolaires` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ev_type`       FOREIGN KEY (`type_evaluation_id`)  REFERENCES `types_evaluations`  (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ev_matiere`    FOREIGN KEY (`matiere_id`)          REFERENCES `matieres`            (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ev_classe`     FOREIGN KEY (`classe_id`)           REFERENCES `classes`             (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ev_enseignant` FOREIGN KEY (`enseignant_id`)       REFERENCES `professeurs`         (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
