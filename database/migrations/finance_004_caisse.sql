-- =============================================================================
-- PHASE 3.5 — DOMAINE CAISSE
-- finance_004_caisse.sql
-- Tables : finance_sessions_caisse, finance_mouvements_caisse, finance_journaux_caisse
-- À exécuter APRÈS finance_003_paiements.sql
-- =============================================================================

-- Sessions de caisse
CREATE TABLE IF NOT EXISTS `finance_sessions_caisse` (
  `id`                   INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
  `numero`               VARCHAR(20)      NOT NULL UNIQUE COMMENT 'CSE-YYYY-NNNN',
  `caissier_id`          INT UNSIGNED     NOT NULL,
  `statut`               ENUM('ouverte','en_activite','fermee','annulee') NOT NULL DEFAULT 'ouverte',
  `date_ouverture`       DATE             NOT NULL,
  `heure_ouverture`      TIME             NOT NULL,
  `date_fermeture`       DATE             NULL,
  `heure_fermeture`      TIME             NULL,
  `solde_initial`        DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
  `total_recettes`       DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
  `total_decaissements`  DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
  `solde_theorique`      DECIMAL(15,2)    NOT NULL DEFAULT 0.00,
  `solde_reel`           DECIMAL(15,2)    NULL,
  `ecart`                DECIMAL(15,2)    NULL,
  `note_ouverture`       TEXT             NULL,
  `note_fermeture`       TEXT             NULL,
  `ouvert_par`           INT UNSIGNED     NOT NULL,
  `ferme_par`            INT UNSIGNED     NULL,
  `created_at`           TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`           TIMESTAMP        NULL,
  CONSTRAINT `fk_fsc_caissier`  FOREIGN KEY (`caissier_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fsc_ouvert`    FOREIGN KEY (`ouvert_par`)  REFERENCES `users`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fsc_ferme`     FOREIGN KEY (`ferme_par`)   REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_fsc_caissier` (`caissier_id`),
  INDEX `idx_fsc_statut`   (`statut`),
  INDEX `idx_fsc_date`     (`date_ouverture`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mouvements de caisse
-- Règle : aucun DELETE physique — soft-cancel via statut='annule' + mouvement inverse
CREATE TABLE IF NOT EXISTS `finance_mouvements_caisse` (
  `id`               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  `session_id`       INT UNSIGNED  NOT NULL,
  `type`             ENUM('recette','decaissement','correction','ouverture','fermeture','annulation') NOT NULL,
  `sens`             ENUM('credit','debit') NOT NULL,
  `montant`          DECIMAL(15,2) NOT NULL,
  `libelle`          VARCHAR(255)  NOT NULL,
  `reference`        VARCHAR(100)  NULL,
  `paiement_id`      INT UNSIGNED  NULL COMMENT 'Lié à finance_paiements si source=paiement',
  `source`           ENUM('manuel','paiement','decaissement','systeme') NOT NULL DEFAULT 'manuel',
  `statut`           ENUM('actif','annule') NOT NULL DEFAULT 'actif',
  `annule_par`       INT UNSIGNED  NULL,
  `motif_annulation` TEXT          NULL,
  `date_annulation`  TIMESTAMP     NULL,
  `created_by`       INT UNSIGNED  NULL,
  `created_at`       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_fmc_session`   FOREIGN KEY (`session_id`)  REFERENCES `finance_sessions_caisse`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_fmc_paiement`  FOREIGN KEY (`paiement_id`) REFERENCES `finance_paiements`(`id`)        ON DELETE SET NULL,
  CONSTRAINT `fk_fmc_annule`    FOREIGN KEY (`annule_par`)  REFERENCES `users`(`id`)                    ON DELETE SET NULL,
  CONSTRAINT `fk_fmc_creator`   FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)                    ON DELETE SET NULL,
  INDEX `idx_fmc_session`   (`session_id`),
  INDEX `idx_fmc_type`      (`type`),
  INDEX `idx_fmc_statut`    (`statut`),
  INDEX `idx_fmc_paiement`  (`paiement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journaux de caisse (résumé / rapprochement quotidien par session)
CREATE TABLE IF NOT EXISTS `finance_journaux_caisse` (
  `id`              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  `date_journee`    DATE          NOT NULL,
  `session_id`      INT UNSIGNED  NULL,
  `solde_ouverture` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_entrees`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_sorties`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `solde_cloture`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `statut`          ENUM('ouvert','clos','rapproche') NOT NULL DEFAULT 'ouvert',
  `note`            TEXT          NULL,
  `created_at`      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_fjc_date_session` (`date_journee`, `session_id`),
  CONSTRAINT `fk_fjc_session` FOREIGN KEY (`session_id`) REFERENCES `finance_sessions_caisse`(`id`) ON DELETE SET NULL,
  INDEX `idx_fjc_date`   (`date_journee`),
  INDEX `idx_fjc_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
