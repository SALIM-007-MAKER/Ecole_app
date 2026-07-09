-- ============================================================
-- Finance V2 — Phase 3.3 : Facturation
-- À exécuter UNE SEULE FOIS après finance_001_referentiel_frais.sql
-- Les tables V1 (frais_eleves, paiements) restent intactes.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Séquences (numérotation sécurisée — thread-safe)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_sequences` (
    `type`   VARCHAR(20)   NOT NULL COMMENT 'FCT | AVO | REC | DEP',
    `annee`  SMALLINT      NOT NULL COMMENT 'Année civile ex: 2026',
    `valeur` INT UNSIGNED  NOT NULL DEFAULT 0,
    PRIMARY KEY (`type`, `annee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Séquences Finance — incrémentées atomiquement';

-- ------------------------------------------------------------
-- 2. Factures
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_factures` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `numero`            VARCHAR(30)     NOT NULL UNIQUE
                        COMMENT 'FCT-AAAA-NNNN — immuable',
    `eleve_id`          INT UNSIGNED    NOT NULL,
    `annee_scolaire`    VARCHAR(9)      NOT NULL,
    `date_emission`     DATE            NOT NULL DEFAULT (CURRENT_DATE),
    `date_echeance`     DATE            NULL,
    `montant_ht`        DECIMAL(12,2)   NOT NULL DEFAULT 0.00
                        COMMENT 'Σ lignes avant remises',
    `montant_remise`    DECIMAL(12,2)   NOT NULL DEFAULT 0.00
                        COMMENT 'Σ remises accordées',
    `montant_penalite`  DECIMAL(12,2)   NOT NULL DEFAULT 0.00
                        COMMENT 'Σ pénalités appliquées',
    `montant_total`     DECIMAL(12,2)   NOT NULL DEFAULT 0.00
                        COMMENT 'montant_ht - remise + penalite',
    `montant_paye`      DECIMAL(12,2)   NOT NULL DEFAULT 0.00
                        COMMENT 'Mis à jour par Phase 3.4 (encaissements)',
    `statut`            ENUM(
                            'brouillon',
                            'emise',
                            'partiellement_payee',
                            'payee',
                            'en_retard',
                            'annulee',
                            'archive'
                        ) NOT NULL DEFAULT 'brouillon',
    `note`              TEXT            NULL,
    `emise_par`         INT UNSIGNED    NULL,
    `annulee_par`       INT UNSIGNED    NULL,
    `date_annulation`   DATE            NULL,
    `motif_annulation`  TEXT            NULL,
    `avoir_id`          INT UNSIGNED    NULL
                        COMMENT 'Avoir émis lors de l\'annulation si paiements existants',
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ff_eleve`    (`eleve_id`, `annee_scolaire`),
    KEY `idx_ff_statut`   (`statut`, `date_echeance`),
    KEY `idx_ff_annee`    (`annee_scolaire`),
    KEY `idx_ff_emetteur` (`emise_par`),
    CONSTRAINT `fk_ff_eleve`    FOREIGN KEY (`eleve_id`)   REFERENCES `eleves`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ff_emetteur` FOREIGN KEY (`emise_par`)  REFERENCES `users`(`id`)  ON DELETE SET NULL,
    CONSTRAINT `fk_ff_annuleur` FOREIGN KEY (`annulee_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Factures Finance V2';

-- ------------------------------------------------------------
-- 3. Lignes de facture
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_lignes_facture` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `facture_id`        INT UNSIGNED    NOT NULL,
    `frais_type_id`     INT UNSIGNED    NULL
                        COMMENT 'NULL = ligne libre sans référence au référentiel',
    `libelle`           VARCHAR(250)    NOT NULL,
    `quantite`          DECIMAL(8,2)    NOT NULL DEFAULT 1.00,
    `montant_unitaire`  DECIMAL(12,2)   NOT NULL,
    `montant_remise`    DECIMAL(12,2)   NOT NULL DEFAULT 0.00
                        COMMENT 'Remise directe sur cette ligne',
    `montant_total`     DECIMAL(12,2)   NOT NULL
                        COMMENT '(quantite × unitaire) - remise_ligne',
    `ordre`             TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_flf_facture`    (`facture_id`),
    KEY `idx_flf_frais_type` (`frais_type_id`),
    CONSTRAINT `fk_flf_facture`    FOREIGN KEY (`facture_id`)    REFERENCES `finance_factures`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_flf_frais_type` FOREIGN KEY (`frais_type_id`) REFERENCES `finance_frais_types`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lignes de facture';

-- ------------------------------------------------------------
-- 4. Remises appliquées à une facture (niveau facture entière)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_remises` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `facture_id`        INT UNSIGNED    NOT NULL,
    `libelle`           VARCHAR(250)    NOT NULL,
    `type_remise`       ENUM('pourcentage','montant_fixe','exoneration')
                        NOT NULL DEFAULT 'montant_fixe',
    `valeur`            DECIMAL(10,2)   NOT NULL
                        COMMENT '% ou montant selon type',
    `montant_calcule`   DECIMAL(12,2)   NOT NULL
                        COMMENT 'Montant effectivement soustrait',
    `regle_exo_id`      INT UNSIGNED    NULL,
    `justificatif`      VARCHAR(500)    NULL,
    `accordee_par`      INT UNSIGNED    NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fr_facture` (`facture_id`),
    CONSTRAINT `fk_fr_facture`  FOREIGN KEY (`facture_id`)   REFERENCES `finance_factures`(`id`)          ON DELETE CASCADE,
    CONSTRAINT `fk_fr_regle`    FOREIGN KEY (`regle_exo_id`) REFERENCES `finance_regles_exoneration`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fr_auteur`   FOREIGN KEY (`accordee_par`) REFERENCES `users`(`id`)                      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Remises accordées sur factures';

-- ------------------------------------------------------------
-- 5. Pénalités (log des pénalités appliquées)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_penalites` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `facture_id`    INT UNSIGNED    NOT NULL,
    `montant`       DECIMAL(12,2)   NOT NULL,
    `motif`         TEXT            NOT NULL,
    `nb_jours`      SMALLINT UNSIGNED NULL
                    COMMENT 'Nombre de jours de retard',
    `applique_par`  INT UNSIGNED    NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fp_facture` (`facture_id`),
    CONSTRAINT `fk_fp_facture` FOREIGN KEY (`facture_id`) REFERENCES `finance_factures`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fp_user`   FOREIGN KEY (`applique_par`) REFERENCES `users`(`id`)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pénalités de retard appliquées sur factures';

-- ------------------------------------------------------------
-- 6. Échéanciers
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_echeanciers` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `facture_id`    INT UNSIGNED    NOT NULL,
    `nb_echeances`  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ech_facture` (`facture_id`),
    CONSTRAINT `fk_ech_facture` FOREIGN KEY (`facture_id`) REFERENCES `finance_factures`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='En-têtes d\'échéanciers';

-- ------------------------------------------------------------
-- 7. Échéances individuelles
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_echeances` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `echeancier_id`     INT UNSIGNED    NOT NULL,
    `numero_ordre`      TINYINT UNSIGNED NOT NULL,
    `date_echeance`     DATE            NOT NULL,
    `montant_du`        DECIMAL(12,2)   NOT NULL,
    `montant_paye`      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `statut`            ENUM('en_attente','partiel','paye','en_retard','annule')
                        NOT NULL DEFAULT 'en_attente',
    `penalite`          DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fec_date`       (`date_echeance`, `statut`),
    KEY `idx_fec_echeancier` (`echeancier_id`),
    CONSTRAINT `fk_fec_echeancier` FOREIGN KEY (`echeancier_id`) REFERENCES `finance_echeanciers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Échéances individuelles des plans de paiement';

-- ------------------------------------------------------------
-- 8. Avoirs (notes de crédit)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_avoirs` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `numero`            VARCHAR(30)     NOT NULL UNIQUE
                        COMMENT 'AVO-AAAA-NNNN — immuable',
    `facture_origine_id` INT UNSIGNED   NOT NULL
                        COMMENT 'Facture annulée à l\'origine de cet avoir',
    `eleve_id`          INT UNSIGNED    NOT NULL,
    `annee_scolaire`    VARCHAR(9)      NOT NULL,
    `montant`           DECIMAL(12,2)   NOT NULL,
    `motif`             TEXT            NOT NULL,
    `statut`            ENUM('emis','utilise','rembourse','annule')
                        NOT NULL DEFAULT 'emis',
    `utilise_sur`       INT UNSIGNED    NULL
                        COMMENT 'facture_id sur laquelle l\'avoir a été imputé',
    `emis_par`          INT UNSIGNED    NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fa_eleve`   (`eleve_id`, `annee_scolaire`),
    KEY `idx_fa_facture` (`facture_origine_id`),
    CONSTRAINT `fk_fa_facture` FOREIGN KEY (`facture_origine_id`) REFERENCES `finance_factures`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_fa_eleve`   FOREIGN KEY (`eleve_id`)           REFERENCES `eleves`(`id`)           ON DELETE RESTRICT,
    CONSTRAINT `fk_fa_emetteur` FOREIGN KEY (`emis_par`)          REFERENCES `users`(`id`)            ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Avoirs (notes de crédit) Finance V2';

-- FK rétroactive : finance_factures.avoir_id → finance_avoirs.id
ALTER TABLE `finance_factures`
    ADD CONSTRAINT `fk_ff_avoir`
        FOREIGN KEY (`avoir_id`) REFERENCES `finance_avoirs`(`id`) ON DELETE SET NULL;
