-- ═══════════════════════════════════════════════════════════════════════════════
-- MIGRATION : Création de la table periodes_scolaires (Phase 2.1 — Académique V2)
--
-- Prérequis : ecole_app.sql doit exister.
-- Idempotent : CREATE TABLE IF NOT EXISTS — sûr à ré-exécuter.
-- AUCUN DROP — conforme aux contraintes de migration V2.
--
-- Table V1 `periodes` : INTACTE — aucune modification.
-- Table V2 `periodes_scolaires` : entièrement nouvelle, coexiste avec V1.
--
-- Machine d'états :
--   ouverte ──► fermee ──► verrouillee
--                  │
--                  └──► archivee
--   (déverrouillage : verrouillee ──► fermee — admin uniquement)
-- ═══════════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

CREATE TABLE IF NOT EXISTS `periodes_scolaires` (
    `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,

    `annee_scolaire`        VARCHAR(9)    NOT NULL
                            COMMENT 'Format YYYY-YYYY (ex: 2025-2026)',

    `type_periode`          ENUM('trimestre','semestre','custom')
                            NOT NULL DEFAULT 'trimestre',

    `numero`                TINYINT UNSIGNED NOT NULL DEFAULT 1
                            COMMENT '1, 2 ou 3 (trimestre) / 1 ou 2 (semestre)',

    `nom`                   VARCHAR(80)   NOT NULL
                            COMMENT 'Ex: Trimestre 1 — 2025-2026',

    `date_debut`            DATE          NULL,
    `date_fin`              DATE          NULL,

    `statut`                ENUM('ouverte','fermee','verrouillee','archivee')
                            NOT NULL DEFAULT 'ouverte',

    `is_active`             TINYINT(1)    NOT NULL DEFAULT 0
                            COMMENT '1 = période active par défaut pour cette année (max 1 par annee_scolaire)',

    `notes_saisie_ouverte`  TINYINT(1)    NOT NULL DEFAULT 1
                            COMMENT '0 = saisie de notes bloquée même si statut=ouverte',

    `ordre`                 TINYINT UNSIGNED NOT NULL DEFAULT 0
                            COMMENT 'Ordre d\'affichage dans les listes',

    `verrouille_par`        INT UNSIGNED  NULL
                            COMMENT 'ID utilisateur ayant verrouillé',
    `verrouille_le`         DATETIME      NULL,

    `created_by`            INT UNSIGNED  NULL,
    `created_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    UNIQUE KEY `uq_ps_annee_type_num` (`annee_scolaire`, `type_periode`, `numero`),

    INDEX `idx_ps_annee`   (`annee_scolaire`),
    INDEX `idx_ps_statut`  (`statut`),
    INDEX `idx_ps_active`  (`is_active`),
    INDEX `idx_ps_ordre`   (`annee_scolaire`, `ordre`, `numero`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Périodes scolaires V2 — trimestres/semestres avec cycle de vie complet';
