-- ============================================================
-- Finance V2 — Phase 3.4 : Encaissements (Paiements)
-- À exécuter après finance_002_facturation.sql
-- Les tables V1 (paiements, frais_eleves) restent intactes.
-- ============================================================

-- ------------------------------------------------------------
-- 0. Stub finance_regles_exoneration (nécessaire pour la FK
--    de finance_remises créée en Phase 3.3)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_regles_exoneration` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(20)   NOT NULL UNIQUE,
    `nom`         VARCHAR(150)  NOT NULL,
    `description` TEXT          NULL,
    `type_remise` ENUM('pourcentage','montant_fixe','exoneration')
                  NOT NULL DEFAULT 'pourcentage',
    `valeur`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `niveaux`     JSON          NULL,
    `actif`       TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Règles exonération — stub Phase 3.4, enrichi en Phase 3.8';

-- ------------------------------------------------------------
-- 1. Modes de paiement (table de référence)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_modes_paiement` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(20)   NOT NULL UNIQUE,
    `nom`         VARCHAR(100)  NOT NULL,
    `description` VARCHAR(250)  NULL,
    `icone`       VARCHAR(50)   NULL DEFAULT 'credit-card',
    `actif`       TINYINT(1)    NOT NULL DEFAULT 1,
    `ordre`       TINYINT       NOT NULL DEFAULT 99,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Modes de paiement disponibles';

INSERT IGNORE INTO `finance_modes_paiement` (`code`,`nom`,`icone`,`actif`,`ordre`) VALUES
('ESP',   'Espèces',           'banknote',       1, 1),
('CHQ',   'Chèque',            'receipt',        1, 2),
('VIR',   'Virement bancaire', 'arrow-right-left',1,3),
('CB',    'Carte bancaire',    'credit-card',    1, 4),
('OM',    'Orange Money',      'smartphone',     1, 5),
('WAVE',  'Wave',              'waves',          1, 6),
('MOOV',  'Moov Money',        'smartphone',     1, 7),
('AVOIR', 'Avoir / Crédit',    'badge-percent',  1, 8);

-- ------------------------------------------------------------
-- 2. Paiements
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_paiements` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `numero`            VARCHAR(30)     NOT NULL UNIQUE
                        COMMENT 'PAI-AAAA-NNNN — immuable',
    `facture_id`        INT UNSIGNED    NOT NULL,
    `mode_paiement_id`  INT UNSIGNED    NULL,
    `montant`           DECIMAL(12,2)   NOT NULL
                        COMMENT 'Montant brut encaissé',
    `montant_applique`  DECIMAL(12,2)   NOT NULL
                        COMMENT 'Montant imputé sur la facture (≤ montant)',
    `reference_externe` VARCHAR(200)    NULL
                        COMMENT 'N° chèque, réf virement, réf mobile…',
    `date_paiement`     DATE            NOT NULL,
    `statut`            ENUM(
                            'initie',
                            'valide',
                            'complete',
                            'annule',
                            'rembourse'
                        ) NOT NULL DEFAULT 'initie',
    `echeance_id`       INT UNSIGNED    NULL
                        COMMENT 'Échéance ciblée (nullable = facture entière)',
    `avoir_id`          INT UNSIGNED    NULL
                        COMMENT 'Avoir utilisé comme mode de paiement',
    `note`              TEXT            NULL,
    `encaisse_par`      INT UNSIGNED    NULL,
    `valide_par`        INT UNSIGNED    NULL,
    `date_validation`   DATE            NULL,
    `annule_par`        INT UNSIGNED    NULL,
    `date_annulation`   DATE            NULL,
    `motif_annulation`  TEXT            NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fp_facture`   (`facture_id`, `statut`),
    KEY `idx_fp_date`      (`date_paiement`, `statut`),
    KEY `idx_fp_mode`      (`mode_paiement_id`),
    KEY `idx_fp_echeance`  (`echeance_id`),
    CONSTRAINT `fk_fpaie_facture` FOREIGN KEY (`facture_id`)      REFERENCES `finance_factures`(`id`)         ON DELETE RESTRICT,
    CONSTRAINT `fk_fp_mode`      FOREIGN KEY (`mode_paiement_id`) REFERENCES `finance_modes_paiement`(`id`)   ON DELETE SET NULL,
    CONSTRAINT `fk_fp_echeance`  FOREIGN KEY (`echeance_id`)      REFERENCES `finance_echeances`(`id`)        ON DELETE SET NULL,
    CONSTRAINT `fk_fp_avoir`     FOREIGN KEY (`avoir_id`)         REFERENCES `finance_avoirs`(`id`)           ON DELETE SET NULL,
    CONSTRAINT `fk_fp_caissier`  FOREIGN KEY (`encaisse_par`)     REFERENCES `users`(`id`)                    ON DELETE SET NULL,
    CONSTRAINT `fk_fp_valideur`  FOREIGN KEY (`valide_par`)       REFERENCES `users`(`id`)                    ON DELETE SET NULL,
    CONSTRAINT `fk_fp_annuleur`  FOREIGN KEY (`annule_par`)       REFERENCES `users`(`id`)                    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Paiements Finance V2';

-- ------------------------------------------------------------
-- 3. Reçus de paiement
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_recus` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `numero`        VARCHAR(30)     NOT NULL UNIQUE
                    COMMENT 'REC-AAAA-NNNN — immuable',
    `paiement_id`   INT UNSIGNED    NOT NULL,
    `facture_id`    INT UNSIGNED    NOT NULL,
    `eleve_id`      INT UNSIGNED    NOT NULL,
    `montant`       DECIMAL(12,2)   NOT NULL,
    `date_emission` DATE            NOT NULL DEFAULT (CURRENT_DATE),
    `emis_par`      INT UNSIGNED    NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_recu_paiement` (`paiement_id`),
    KEY `idx_recu_facture` (`facture_id`),
    KEY `idx_recu_eleve`   (`eleve_id`),
    CONSTRAINT `fk_recu_paiement` FOREIGN KEY (`paiement_id`) REFERENCES `finance_paiements`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_recu_facture`  FOREIGN KEY (`facture_id`)  REFERENCES `finance_factures`(`id`)  ON DELETE RESTRICT,
    CONSTRAINT `fk_recu_eleve`    FOREIGN KEY (`eleve_id`)    REFERENCES `eleves`(`id`)             ON DELETE RESTRICT,
    CONSTRAINT `fk_recu_emetteur` FOREIGN KEY (`emis_par`)    REFERENCES `users`(`id`)              ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Reçus de paiement Finance V2';

-- ------------------------------------------------------------
-- 4. Trop-perçus
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_trop_percus` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `paiement_id`      INT UNSIGNED    NOT NULL,
    `facture_id`       INT UNSIGNED    NOT NULL,
    `eleve_id`         INT UNSIGNED    NOT NULL,
    `montant_excedent` DECIMAL(12,2)   NOT NULL,
    `statut`           ENUM('en_attente','restitue','impute','annule')
                       NOT NULL DEFAULT 'en_attente',
    `note`             TEXT            NULL,
    `traite_par`       INT UNSIGNED    NULL,
    `date_traitement`  DATE            NULL,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                       ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tp_eleve`   (`eleve_id`),
    KEY `idx_tp_facture` (`facture_id`),
    CONSTRAINT `fk_tp_paiement` FOREIGN KEY (`paiement_id`) REFERENCES `finance_paiements`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_tp_facture`  FOREIGN KEY (`facture_id`)  REFERENCES `finance_factures`(`id`)  ON DELETE RESTRICT,
    CONSTRAINT `fk_tp_eleve`    FOREIGN KEY (`eleve_id`)    REFERENCES `eleves`(`id`)             ON DELETE RESTRICT,
    CONSTRAINT `fk_tp_traiteur` FOREIGN KEY (`traite_par`)  REFERENCES `users`(`id`)              ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Trop-perçus Finance V2';

-- ------------------------------------------------------------
-- 5. Remboursements
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_remboursements` (
    `id`                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `paiement_id`             INT UNSIGNED    NOT NULL
                              COMMENT 'Paiement faisant l\'objet du remboursement',
    `trop_percu_id`           INT UNSIGNED    NULL
                              COMMENT 'Trop-perçu à l\'origine (si applicable)',
    `eleve_id`                INT UNSIGNED    NOT NULL,
    `montant`                 DECIMAL(12,2)   NOT NULL,
    `mode_remboursement`      VARCHAR(20)     NOT NULL DEFAULT 'ESP',
    `reference_remboursement` VARCHAR(200)    NULL,
    `motif`                   TEXT            NOT NULL,
    `statut`                  ENUM('initie','valide','complete','annule')
                              NOT NULL DEFAULT 'initie',
    `realise_par`             INT UNSIGNED    NULL,
    `valide_par`              INT UNSIGNED    NULL,
    `date_realisation`        DATE            NULL,
    `created_at`              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rmbrs_paiement` (`paiement_id`),
    KEY `idx_rmbrs_eleve`    (`eleve_id`),
    CONSTRAINT `fk_rmbrs_paiement`     FOREIGN KEY (`paiement_id`)   REFERENCES `finance_paiements`(`id`)    ON DELETE RESTRICT,
    CONSTRAINT `fk_rmbrs_trop_percu`   FOREIGN KEY (`trop_percu_id`) REFERENCES `finance_trop_percus`(`id`)  ON DELETE SET NULL,
    CONSTRAINT `fk_rmbrs_eleve`        FOREIGN KEY (`eleve_id`)      REFERENCES `eleves`(`id`)               ON DELETE RESTRICT,
    CONSTRAINT `fk_rmbrs_auteur`       FOREIGN KEY (`realise_par`)   REFERENCES `users`(`id`)                ON DELETE SET NULL,
    CONSTRAINT `fk_rmbrs_valideur`     FOREIGN KEY (`valide_par`)    REFERENCES `users`(`id`)                ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Remboursements Finance V2';
