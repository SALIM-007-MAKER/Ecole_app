-- ═══════════════════════════════════════════════════════════════════════════════
-- MIGRATION : Création de la table inscriptions (Phase 1.4 — Scolarité V2)
--
-- Prérequis : ecole_app.sql, eleves et classes doivent exister.
-- Idempotent : CREATE TABLE IF NOT EXISTS — sûr à ré-exécuter.
-- AUCUN DROP — conforme aux contraintes de migration V2.
--
-- Règle métier clé :
--   Un élève ne peut avoir qu'une seule inscription ACTIVE (en_attente ou validee)
--   par année scolaire. Contrôlé par l'application (InscriptionService::inscrire()).
-- ═══════════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

CREATE TABLE IF NOT EXISTS `inscriptions` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `eleve_id`         INT UNSIGNED  NOT NULL
                       COMMENT 'Référence vers eleves.id',
    `classe_id`        INT UNSIGNED  NULL DEFAULT NULL
                       COMMENT 'Classe cible (peut être NULL si en_attente)',
    `annee_scolaire`   VARCHAR(9)    NOT NULL
                       COMMENT 'Année scolaire au format YYYY-YYYY (ex: 2025-2026)',
    `statut`           ENUM('en_attente','validee','rejetee','annulee')
                       NOT NULL DEFAULT 'en_attente'
                       COMMENT 'Cycle : en_attente → validee/rejetee ; validee → annulee',
    `motif_rejet`      TEXT          NULL
                       COMMENT 'Renseigné lors du rejet ou de l\'annulation',
    `notes`            TEXT          NULL
                       COMMENT 'Observations internes (non visible parent/élève)',
    `inscription_par`  INT UNSIGNED  NULL DEFAULT NULL
                       COMMENT 'ID de l\'utilisateur ayant créé l\'inscription',
    `valide_par`       INT UNSIGNED  NULL DEFAULT NULL
                       COMMENT 'ID de l\'utilisateur ayant validé ou rejeté',
    `valide_le`        DATETIME      NULL DEFAULT NULL
                       COMMENT 'Horodatage de la validation ou du rejet',
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                       ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_insc_eleve`
        FOREIGN KEY (`eleve_id`)  REFERENCES `eleves`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_insc_classe`
        FOREIGN KEY (`classe_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL,

    INDEX `idx_insc_eleve`        (`eleve_id`),
    INDEX `idx_insc_annee`        (`annee_scolaire`),
    INDEX `idx_insc_statut`       (`statut`),
    INDEX `idx_insc_eleve_annee`  (`eleve_id`, `annee_scolaire`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Inscriptions scolaires V2 — une par élève par année scolaire (statut actif)';
