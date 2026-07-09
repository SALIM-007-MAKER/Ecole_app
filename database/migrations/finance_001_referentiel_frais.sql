-- ============================================================
-- Finance V2 — Phase 3.2 : Référentiel des frais
-- À exécuter UNE SEULE FOIS sur la base de données.
-- Toutes les tables V1 (frais_types, frais_eleves, paiements,
-- depenses, depenses_categories) restent intactes.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Catégories de frais
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_categories_frais` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(20)   NOT NULL UNIQUE
                  COMMENT 'Identifiant court immuable (ex: SCOL, TRANSP)',
    `nom`         VARCHAR(100)  NOT NULL,
    `description` TEXT          NULL,
    `couleur`     VARCHAR(7)    NOT NULL DEFAULT '#6366f1',
    `icone`       VARCHAR(50)   NOT NULL DEFAULT 'currency-dollar',
    `actif`       TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fcf_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catégories de frais Finance V2';

-- Données initiales
INSERT IGNORE INTO `finance_categories_frais` (`code`, `nom`, `couleur`, `icone`) VALUES
    ('SCOL',      'Frais scolaires',          '#6366f1', 'book-open'),
    ('TRANSP',    'Transport',                '#3b82f6', 'bus'),
    ('CANTINE',   'Cantine & restauration',   '#10b981', 'utensils'),
    ('ACTIVITES', 'Activités & sorties',       '#f59e0b', 'activity'),
    ('EXAMEN',    'Examens & concours',        '#ef4444', 'file-text'),
    ('ADMIN',     'Frais administratifs',      '#64748b', 'file');

-- ------------------------------------------------------------
-- 2. Types de frais V2
--    Coexiste avec la table V1 `frais_types` (non modifiée).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_frais_types` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `code`              VARCHAR(30)     NOT NULL UNIQUE
                        COMMENT 'Code immuable après création (ex: INSCRIPTION_2026)',
    `nom`               VARCHAR(150)    NOT NULL,
    `description`       TEXT            NULL,
    `categorie_id`      INT UNSIGNED    NULL,
    `montant_defaut`    DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    `devise`            VARCHAR(3)      NOT NULL DEFAULT 'XOF'
                        COMMENT 'ISO 4217 : XOF (FCFA), EUR, USD…',
    `periodicite`       ENUM('unique','mensuel','trimestriel','semestriel','annuel')
                        NOT NULL DEFAULT 'annuel',
    `est_obligatoire`   TINYINT(1)      NOT NULL DEFAULT 1
                        COMMENT '1 = frais obligatoire, 0 = optionnel',
    `niveaux_cibles`    JSON            NULL
                        COMMENT 'Tableau JSON de niveaux scolaires, NULL = tous niveaux',
    `annee_scolaire`    VARCHAR(9)      NULL
                        COMMENT 'Format AAAA-AAAA, NULL = applicable toutes années',
    `date_limite`       DATE            NULL
                        COMMENT 'Date limite de paiement par défaut',
    `peut_avoir_remise` TINYINT(1)      NOT NULL DEFAULT 1,
    `statut`            ENUM('actif','inactif','archive')
                        NOT NULL DEFAULT 'actif',
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fft_categorie`   (`categorie_id`),
    KEY `idx_fft_annee`       (`annee_scolaire`),
    KEY `idx_fft_statut`      (`statut`),
    KEY `idx_fft_obligatoire` (`est_obligatoire`),
    CONSTRAINT `fk_fft_categorie`
        FOREIGN KEY (`categorie_id`)
        REFERENCES `finance_categories_frais` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Types de frais Finance V2 — coexiste avec frais_types V1';

-- ------------------------------------------------------------
-- 3. Tarifs par niveau / classe (surcharge du montant_defaut)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_tarifs` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `frais_type_id` INT UNSIGNED    NOT NULL,
    `annee_scolaire` VARCHAR(9)     NOT NULL,
    `niveau`        VARCHAR(50)     NULL
                    COMMENT 'NULL = toutes les classes pour ce frais',
    `classe_id`     INT UNSIGNED    NULL
                    COMMENT 'NULL = tout le niveau (ou tous si niveau aussi NULL)',
    `montant`       DECIMAL(12,2)   NOT NULL,
    `devise`        VARCHAR(3)      NOT NULL DEFAULT 'XOF',
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tarif` (`frais_type_id`, `annee_scolaire`, `niveau`, `classe_id`),
    KEY `idx_tarif_frais` (`frais_type_id`),
    CONSTRAINT `fk_tarif_frais_type`
        FOREIGN KEY (`frais_type_id`)
        REFERENCES `finance_frais_types` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_tarif_classe`
        FOREIGN KEY (`classe_id`)
        REFERENCES `classes` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tarifs par niveau/classe — surcharge le montant_defaut';

-- ------------------------------------------------------------
-- 4. Historique des modifications (audit dédié Finance)
--    Complète l'AuditService général avec le détail Finance.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_historique` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `entite_type`   VARCHAR(50)     NOT NULL
                    COMMENT 'frais_type | categorie | tarif',
    `entite_id`     INT UNSIGNED    NOT NULL,
    `action`        VARCHAR(30)     NOT NULL
                    COMMENT 'create | update | activate | deactivate | archive | delete',
    `avant`         JSON            NULL,
    `apres`         JSON            NULL,
    `user_id`       INT UNSIGNED    NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fhist_entite`  (`entite_type`, `entite_id`),
    KEY `idx_fhist_user`    (`user_id`),
    KEY `idx_fhist_action`  (`action`),
    CONSTRAINT `fk_fhist_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historique audit Finance — référentiel frais';
