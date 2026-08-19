-- ============================================================
-- Finance V2 — Phase décaissements : Dépenses & Fournisseurs
-- À exécuter UNE SEULE FOIS sur la base de données.
-- La table V1 `depenses`/`depenses_categories` reste intacte —
-- ce module est net-new en V2, aucune donnée V1 n'est modifiée.
-- Colonnes origine/migration_source/migration_meta présentes par
-- cohérence avec finance_factures/finance_paiements (T033), pour
-- une éventuelle migration future des dépenses V1 — non peuplées
-- aujourd'hui.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Catégories de dépenses (V2-native, ne dépend pas de
--    `depenses_categories` V1)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_categories_depenses` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(20)   NOT NULL UNIQUE
                  COMMENT 'Identifiant court immuable (ex: SALAIRES, FOURNITURES)',
    `nom`         VARCHAR(100)  NOT NULL,
    `description` TEXT          NULL,
    `couleur`     VARCHAR(7)    NOT NULL DEFAULT '#64748b',
    `icone`       VARCHAR(50)   NOT NULL DEFAULT 'receipt',
    `actif`       TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fcd_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catégories de dépenses Finance V2';

INSERT IGNORE INTO `finance_categories_depenses` (`code`, `nom`, `couleur`, `icone`) VALUES
    ('SALAIRES',    'Salaires et charges',        '#4e73df', 'users'),
    ('FOURNITURES', 'Fournitures et matériel',    '#1cc88a', 'package'),
    ('INFRA',       'Infrastructure',             '#36b9cc', 'building'),
    ('SERVICES',    'Services et utilités',       '#f6c23e', 'plug'),
    ('PEDAGOGIE',   'Activités pédagogiques',     '#e74a3b', 'graduation-cap'),
    ('DIVERS',      'Divers',                     '#858796', 'more-horizontal');

-- ------------------------------------------------------------
-- 2. Fournisseurs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_fournisseurs` (
    `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `code`       VARCHAR(20)    NOT NULL UNIQUE,
    `nom`        VARCHAR(150)   NOT NULL,
    `contact`    VARCHAR(100)   NULL,
    `telephone`  VARCHAR(30)    NULL,
    `email`      VARCHAR(191)   NULL,
    `adresse`    TEXT           NULL,
    `iban`       VARCHAR(50)    NULL,
    `actif`      TINYINT(1)     NOT NULL DEFAULT 1,
    `created_by` INT UNSIGNED   NULL,
    `created_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ff_actif` (`actif`),
    CONSTRAINT `fk_ff_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fournisseurs Finance V2';

-- ------------------------------------------------------------
-- 3. Dépenses / Décaissements — workflow de validation
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_decaissements` (
    `id`                INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `numero`            VARCHAR(30)    NOT NULL UNIQUE COMMENT 'Format DEP-AAAA-NNNN',
    `categorie_id`      INT UNSIGNED   NULL,
    `fournisseur_id`    INT UNSIGNED   NULL,
    `libelle`           VARCHAR(255)   NOT NULL,
    `description`       TEXT           NULL,
    `montant`           DECIMAL(12,2)  NOT NULL,
    `devise`            VARCHAR(3)     NOT NULL DEFAULT 'XOF',
    `date_depense`      DATE           NOT NULL,
    `date_echeance`     DATE           NULL,
    `mode_paiement_id`  INT UNSIGNED   NULL COMMENT 'FK finance_modes_paiement — renseigné au paiement',
    `reference_externe` VARCHAR(150)   NULL,
    `note`              TEXT           NULL,
    `statut`            ENUM('brouillon','soumis','valide','approuve','paye','rejete','annule')
                        NOT NULL DEFAULT 'brouillon',
    `caisse_session_id` INT UNSIGNED   NULL COMMENT 'Session de caisse imputée si payé en espèces',
    `saisi_par`         INT UNSIGNED   NULL,
    `valide_par`        INT UNSIGNED   NULL,
    `date_validation`   DATETIME       NULL,
    `approuve_par`      INT UNSIGNED   NULL,
    `date_approbation`  DATETIME       NULL,
    `paye_par`          INT UNSIGNED   NULL,
    `date_paiement`     DATETIME       NULL,
    `motif_rejet`       TEXT           NULL,
    `annule_par`        INT UNSIGNED   NULL,
    `date_annulation`   DATETIME       NULL,
    `motif_annulation`  TEXT           NULL,
    `origine`           ENUM('operationnelle','migration_v1') NOT NULL DEFAULT 'operationnelle',
    `migration_source`  VARCHAR(100)   NULL,
    `migration_meta`    JSON           NULL,
    `created_at`        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        TIMESTAMP      NULL,
    PRIMARY KEY (`id`),
    KEY `idx_fd_date`      (`date_depense`),
    KEY `idx_fd_statut`    (`statut`),
    KEY `idx_fd_categorie` (`categorie_id`),
    KEY `idx_fd_fournisseur` (`fournisseur_id`),
    KEY `idx_fd_origine`   (`origine`),
    KEY `idx_fd_deleted`   (`deleted_at`),
    CONSTRAINT `fk_fd_categorie`    FOREIGN KEY (`categorie_id`)     REFERENCES `finance_categories_depenses`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fd_fournisseur`  FOREIGN KEY (`fournisseur_id`)   REFERENCES `finance_fournisseurs`(`id`)        ON DELETE SET NULL,
    CONSTRAINT `fk_fd_mode`         FOREIGN KEY (`mode_paiement_id`) REFERENCES `finance_modes_paiement`(`id`)      ON DELETE SET NULL,
    CONSTRAINT `fk_fd_caisse`       FOREIGN KEY (`caisse_session_id`) REFERENCES `finance_sessions_caisse`(`id`)    ON DELETE SET NULL,
    CONSTRAINT `fk_fd_saisi`        FOREIGN KEY (`saisi_par`)        REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fd_valide`       FOREIGN KEY (`valide_par`)       REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fd_approuve`     FOREIGN KEY (`approuve_par`)     REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fd_paye`         FOREIGN KEY (`paye_par`)         REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fd_annule`       FOREIGN KEY (`annule_par`)       REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Dépenses/décaissements Finance V2 — workflow brouillon→soumis→valide→approuve→paye';

-- ------------------------------------------------------------
-- 4. Justificatifs (pièces jointes — stockage via UploadService,
--    type 'justification' déjà existant)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_justificatifs` (
    `id`              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `decaissement_id` INT UNSIGNED   NOT NULL,
    `nom_fichier`     VARCHAR(255)   NOT NULL,
    `chemin`          VARCHAR(500)   NOT NULL COMMENT 'Relatif à ROOT_PATH via UploadService',
    `mime_type`       VARCHAR(100)   NOT NULL,
    `taille`          INT UNSIGNED   NOT NULL,
    `uploade_par`     INT UNSIGNED   NULL,
    `created_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fj_decaissement` (`decaissement_id`),
    CONSTRAINT `fk_fj_decaissement` FOREIGN KEY (`decaissement_id`) REFERENCES `finance_decaissements`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fj_uploade_par` FOREIGN KEY (`uploade_par`)      REFERENCES `users`(`id`)                 ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Justificatifs des décaissements Finance V2';

-- La numérotation DEP-AAAA-NNNN réutilise la table `finance_sequences`
-- déjà existante (mécanisme partagé avec FCT/PAI/REC/CSE/ECR) — aucune
-- nouvelle table de séquence nécessaire.
