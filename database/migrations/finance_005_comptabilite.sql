-- ============================================================
-- PHASE 3.6 — Comptabilité V2
-- finance_005_comptabilite.sql
-- Plan comptable, exercices, écritures comptables (double-entrée)
-- ============================================================
-- Contraintes :
--   • Aucun DROP TABLE
--   • Aucun DELETE physique sur les écritures (immuables après validation)
--   • Débit = Crédit obligatoire (vérifié applicativement dans AccountingService)
--   • Toutes les FK utilisent des références internes au module finance
-- ============================================================

-- 1. Plan comptable (PCG adapté établissements scolaires)
CREATE TABLE IF NOT EXISTS `finance_comptes` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`      VARCHAR(10)  NOT NULL,
  `libelle`   VARCHAR(150) NOT NULL,
  `type`      ENUM('actif','passif','charge','produit') NOT NULL,
  `classe`    TINYINT UNSIGNED NOT NULL,
  `parent_id` INT UNSIGNED     NULL,
  `niveau`    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `actif`     TINYINT(1)       NOT NULL DEFAULT 1,
  `systeme`   TINYINT(1)       NOT NULL DEFAULT 0,
  `note`      TEXT             NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fc_code` (`code`),
  KEY `idx_fc_classe` (`classe`),
  KEY `idx_fc_type`   (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Journaux comptables (VTE / CAI / BNQ / ACH / OD)
CREATE TABLE IF NOT EXISTS `finance_journaux_comptables` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`    VARCHAR(5)   NOT NULL,
  `libelle` VARCHAR(80)  NOT NULL,
  `type`    ENUM('vente','achat','caisse','banque','operations_diverses') NOT NULL,
  `actif`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fjc_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Exercices comptables (années fiscales)
CREATE TABLE IF NOT EXISTS `finance_exercices` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `libelle`      VARCHAR(80)   NOT NULL,
  `date_debut`   DATE          NOT NULL,
  `date_fin`     DATE          NOT NULL,
  `statut`       ENUM('ouvert','cloture','reouvert') NOT NULL DEFAULT 'ouvert',
  `solde_report` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `note`         TEXT          NULL,
  `date_cloture` DATE          NULL,
  `cloture_par`  INT UNSIGNED  NULL,
  `ouvert_par`   INT UNSIGNED  NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fe_statut`     (`statut`),
  KEY `idx_fe_date_debut` (`date_debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Périodes comptables (mensuelles dans un exercice)
CREATE TABLE IF NOT EXISTS `finance_periodes_comptables` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exercice_id` INT UNSIGNED NOT NULL,
  `numero`      TINYINT UNSIGNED NOT NULL,
  `libelle`     VARCHAR(40)  NOT NULL,
  `date_debut`  DATE         NOT NULL,
  `date_fin`    DATE         NOT NULL,
  `statut`      ENUM('ouverte','cloturee') NOT NULL DEFAULT 'ouverte',
  `cloture_par` INT UNSIGNED NULL,
  `cloture_le`  TIMESTAMP    NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fpc_exercice_num` (`exercice_id`, `numero`),
  KEY `idx_fpc_exercice` (`exercice_id`),
  KEY `idx_fpc_dates`    (`date_debut`, `date_fin`),
  CONSTRAINT `fk_fpc_exercice` FOREIGN KEY (`exercice_id`)
    REFERENCES `finance_exercices` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Écritures comptables — en-tête (IMMUABLES après statut='valide')
CREATE TABLE IF NOT EXISTS `finance_ecritures` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero`        VARCHAR(20)  NOT NULL,
  `date_ecriture` DATE         NOT NULL,
  `journal_id`    INT UNSIGNED NOT NULL,
  `exercice_id`   INT UNSIGNED NOT NULL,
  `periode_id`    INT UNSIGNED NOT NULL,
  `libelle`       VARCHAR(200) NOT NULL,
  `reference`     VARCHAR(80)  NULL,
  `source`        VARCHAR(80)  NULL,
  `statut`        ENUM('brouillon','valide','extourne','cloture') NOT NULL DEFAULT 'valide',
  `extourne_de`   INT UNSIGNED NULL,
  `created_by`    INT UNSIGNED NOT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fec_numero`    (`numero`),
  KEY `idx_fec_exercice`        (`exercice_id`),
  KEY `idx_fec_periode`         (`periode_id`),
  KEY `idx_fec_journal`         (`journal_id`),
  KEY `idx_fec_reference`       (`reference`),
  KEY `idx_fec_statut`          (`statut`),
  KEY `idx_fec_date`            (`date_ecriture`),
  CONSTRAINT `fk_fec_journal`   FOREIGN KEY (`journal_id`)  REFERENCES `finance_journaux_comptables` (`id`),
  CONSTRAINT `fk_fec_exercice`  FOREIGN KEY (`exercice_id`) REFERENCES `finance_exercices` (`id`),
  CONSTRAINT `fk_fec_periode`   FOREIGN KEY (`periode_id`)  REFERENCES `finance_periodes_comptables` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Lignes d'écriture — détail (IMMUABLES)
CREATE TABLE IF NOT EXISTS `finance_lignes_ecriture` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `ecriture_id` INT UNSIGNED  NOT NULL,
  `compte_id`   INT UNSIGNED  NOT NULL,
  `libelle`     VARCHAR(200)  NOT NULL,
  `debit`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credit`      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `created_by`  INT UNSIGNED  NOT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fle_ecriture` (`ecriture_id`),
  KEY `idx_fle_compte`   (`compte_id`),
  CONSTRAINT `fk_fle_ecriture` FOREIGN KEY (`ecriture_id`) REFERENCES `finance_ecritures` (`id`),
  CONSTRAINT `fk_fle_compte`   FOREIGN KEY (`compte_id`)   REFERENCES `finance_comptes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Règles de mapping événement métier → comptes comptables
CREATE TABLE IF NOT EXISTS `finance_regles_comptables` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_source`        VARCHAR(40)  NOT NULL,
  `description`        VARCHAR(120) NOT NULL,
  `compte_debit_code`  VARCHAR(10)  NOT NULL,
  `compte_credit_code` VARCHAR(10)  NOT NULL,
  `journal_code`       VARCHAR(5)   NOT NULL,
  `actif`              TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_frc_type` (`type_source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEEDS — Journaux comptables
-- ============================================================
INSERT IGNORE INTO `finance_journaux_comptables` (`code`, `libelle`, `type`) VALUES
('VTE', 'Journal des ventes (scolarité)',       'vente'),
('CAI', 'Journal de caisse',                    'caisse'),
('BNQ', 'Journal de banque',                    'banque'),
('ACH', 'Journal des achats',                   'achat'),
('OD',  'Journal des opérations diverses',      'operations_diverses');

-- ============================================================
-- SEEDS — Plan comptable (PCG adapté établissements scolaires)
-- ============================================================
INSERT IGNORE INTO `finance_comptes` (`code`, `libelle`, `type`, `classe`, `niveau`, `systeme`) VALUES
-- ── Classe 1 : Comptes de capitaux ───────────────────────────────────────────
('1000', 'Capitaux propres',          'passif', 1, 1, 1),
('1010', 'Capital',                   'passif', 1, 2, 1),
('1100', 'Report à nouveau',          'passif', 1, 1, 1),
('1200', 'Résultat de l''exercice',   'passif', 1, 1, 1),
('1600', 'Emprunts et dettes',        'passif', 1, 1, 0),

-- ── Classe 4 : Comptes de tiers ──────────────────────────────────────────────
('4010', 'Fournisseurs',                         'passif', 4, 1, 0),
('4110', 'Élèves — créances scolarité',          'actif',  4, 1, 0),
('4190', 'Avoirs sur élèves',                    'passif', 4, 1, 1),
('4500', 'Subventions à recevoir',               'actif',  4, 1, 0),

-- ── Classe 5 : Comptes financiers ────────────────────────────────────────────
('5120', 'Banque',             'actif', 5, 1, 1),
('5300', 'Caisse principale',  'actif', 5, 1, 1),

-- ── Classe 6 : Comptes de charges ────────────────────────────────────────────
('6000', 'Comptes de charges',                       'charge', 6, 1, 1),
('6010', 'Achats de fournitures scolaires',          'charge', 6, 2, 0),
('6100', 'Services extérieurs',                      'charge', 6, 2, 0),
('6200', 'Autres services extérieurs',               'charge', 6, 2, 0),
('6300', 'Impôts et taxes',                          'charge', 6, 2, 0),
('6400', 'Charges de personnel',                     'charge', 6, 2, 0),
('6500', 'Autres charges de gestion courante',       'charge', 6, 2, 0),
('6600', 'Charges financières',                      'charge', 6, 2, 0),

-- ── Classe 7 : Comptes de produits ───────────────────────────────────────────
('7000', 'Comptes de produits',                      'produit', 7, 1, 1),
('7010', 'Frais de scolarité',                       'produit', 7, 2, 1),
('7020', 'Frais d''inscription',                     'produit', 7, 2, 0),
('7030', 'Frais de cantine',                         'produit', 7, 2, 0),
('7040', 'Frais d''internat',                        'produit', 7, 2, 0),
('7050', 'Frais de transport scolaire',              'produit', 7, 2, 0),
('7400', 'Subventions d''exploitation',              'produit', 7, 2, 0),
('7500', 'Produits divers',                          'produit', 7, 2, 0);

-- ============================================================
-- SEEDS — Règles de mapping événement → comptes
-- ============================================================
INSERT IGNORE INTO `finance_regles_comptables`
  (`type_source`, `description`, `compte_debit_code`, `compte_credit_code`, `journal_code`)
VALUES
-- Encaissements paiements (espèces / mobile money)
('payment_esp',
 'Paiement en espèces ou mobile money (ESP/OM/WAVE/MOOV) → Caisse débitée, Produits scolarité crédités',
 '5300', '7010', 'CAI'),

-- Encaissements paiements (chèque / virement / CB)
('payment_bnq',
 'Paiement bancaire (CHQ/VIR/CB) → Banque débitée, Produits scolarité crédités',
 '5120', '7010', 'BNQ'),

-- Encaissements par avoir
('payment_avoir',
 'Paiement par consommation d''avoir → Avoirs élèves débit, Produits scolarité crédités',
 '4190', '7010', 'OD'),

-- Remboursements (espèces)
('refund_esp',
 'Remboursement en espèces → Produits scolarité débit, Caisse créditée',
 '7010', '5300', 'CAI'),

-- Remboursements (bancaire)
('refund_bnq',
 'Remboursement bancaire → Produits scolarité débit, Banque créditée',
 '7010', '5120', 'BNQ'),

-- Annulation facture (avec avoir)
('invoice_cancelled',
 'Facture annulée → Produits débit, Avoirs élèves crédités',
 '7010', '4190', 'OD'),

-- Mouvements manuels de caisse — recette
('cash_recette_manuel',
 'Recette manuelle en caisse → Caisse débitée, Produits divers crédités',
 '5300', '7500', 'CAI'),

-- Mouvements manuels de caisse — décaissement
('cash_decaissement_manuel',
 'Décaissement manuel en caisse → Charges débitées, Caisse créditée',
 '6500', '5300', 'CAI'),

-- Dépenses validées (Phase décaissements)
('expense_validated',
 'Dépense validée → Charges débitées, Fournisseur ou Caisse crédité',
 '6010', '4010', 'ACH');

-- ============================================================
-- SEEDS — Exercice 2025-2026 (année scolaire courante)
-- ============================================================
INSERT IGNORE INTO `finance_exercices` (`id`, `libelle`, `date_debut`, `date_fin`, `statut`) VALUES
(1, 'Exercice 2025-2026', '2025-09-01', '2026-08-31', 'ouvert');

-- 12 périodes mensuelles pour l'exercice 2025-2026
INSERT IGNORE INTO `finance_periodes_comptables`
  (`exercice_id`, `numero`, `libelle`, `date_debut`, `date_fin`, `statut`)
VALUES
(1,  1, 'Septembre 2025', '2025-09-01', '2025-09-30', 'ouverte'),
(1,  2, 'Octobre 2025',   '2025-10-01', '2025-10-31', 'ouverte'),
(1,  3, 'Novembre 2025',  '2025-11-01', '2025-11-30', 'ouverte'),
(1,  4, 'Décembre 2025',  '2025-12-01', '2025-12-31', 'ouverte'),
(1,  5, 'Janvier 2026',   '2026-01-01', '2026-01-31', 'ouverte'),
(1,  6, 'Février 2026',   '2026-02-01', '2026-02-28', 'ouverte'),
(1,  7, 'Mars 2026',      '2026-03-01', '2026-03-31', 'ouverte'),
(1,  8, 'Avril 2026',     '2026-04-01', '2026-04-30', 'ouverte'),
(1,  9, 'Mai 2026',       '2026-05-01', '2026-05-31', 'ouverte'),
(1, 10, 'Juin 2026',      '2026-06-01', '2026-06-30', 'ouverte'),
(1, 11, 'Juillet 2026',   '2026-07-01', '2026-07-31', 'ouverte'),
(1, 12, 'Août 2026',      '2026-08-01', '2026-08-31', 'ouverte');

-- Ajouter 'ECR' à finance_sequences si non présent (colonne créée en finance_002)
INSERT IGNORE INTO `finance_sequences` (`type`, `annee`, `valeur`) VALUES ('ECR', YEAR(NOW()), 0);
