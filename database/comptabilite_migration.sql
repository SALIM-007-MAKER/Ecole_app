-- ═══════════════════════════════════════════════════════════════════════════════
-- Module Comptabilité — ecole_app
-- ═══════════════════════════════════════════════════════════════════════════════

-- ─── Types de frais scolaires ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `frais_types` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `nom`            VARCHAR(100)  NOT NULL,
    `description`    TEXT          NULL,
    `montant_defaut` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `periodicite`    ENUM('unique','mensuel','trimestriel','annuel') NOT NULL DEFAULT 'annuel',
    `actif`          TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Affectation des frais aux élèves ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `frais_eleves` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `eleve_id`       INT UNSIGNED  NOT NULL,
    `frais_type_id`  INT UNSIGNED  NOT NULL,
    `annee_scolaire` VARCHAR(9)    NOT NULL,
    `montant`        DECIMAL(10,2) NOT NULL,
    `echeance`       DATE          NULL,
    `statut`         ENUM('en_attente','partiel','paye') NOT NULL DEFAULT 'en_attente',
    `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE  KEY `uq_frais_eleve` (`eleve_id`, `frais_type_id`, `annee_scolaire`),
    INDEX         `idx_fe_annee`  (`annee_scolaire`),
    INDEX         `idx_fe_statut` (`statut`),
    CONSTRAINT `fk_fe_eleve` FOREIGN KEY (`eleve_id`)      REFERENCES `eleves`(`id`)      ON DELETE CASCADE,
    CONSTRAINT `fk_fe_type`  FOREIGN KEY (`frais_type_id`) REFERENCES `frais_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Paiements ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `paiements` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `eleve_id`       INT UNSIGNED  NOT NULL,
    `frais_eleve_id` INT UNSIGNED  NULL COMMENT 'Frais spécifique concerné (optionnel)',
    `annee_scolaire` VARCHAR(9)    NOT NULL,
    `montant`        DECIMAL(10,2) NOT NULL,
    `date_paiement`  DATE          NOT NULL,
    `mode_paiement`  ENUM('especes','cheque','virement','carte') NOT NULL DEFAULT 'especes',
    `reference`      VARCHAR(100)  NULL COMMENT 'N° de chèque, bordereau virement…',
    `note`           TEXT          NULL,
    `encaisse_par`   INT UNSIGNED  NULL,
    `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_p_date`    (`date_paiement`),
    INDEX `idx_p_eleve`   (`eleve_id`, `annee_scolaire`),
    INDEX `idx_p_annee`   (`annee_scolaire`),
    CONSTRAINT `fk_p_eleve`      FOREIGN KEY (`eleve_id`)       REFERENCES `eleves`(`id`)      ON DELETE RESTRICT,
    CONSTRAINT `fk_p_frais`      FOREIGN KEY (`frais_eleve_id`) REFERENCES `frais_eleves`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_p_encaisseur` FOREIGN KEY (`encaisse_par`)   REFERENCES `users`(`id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Catégories de dépenses ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `depenses_categories` (
    `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nom`     VARCHAR(100) NOT NULL,
    `couleur` VARCHAR(7)   NOT NULL DEFAULT '#6c757d',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Dépenses ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `depenses` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `categorie_id` INT UNSIGNED  NULL,
    `libelle`      VARCHAR(255)  NOT NULL,
    `montant`      DECIMAL(10,2) NOT NULL,
    `date_depense` DATE          NOT NULL,
    `mode_paiement` ENUM('especes','cheque','virement','carte') NOT NULL DEFAULT 'especes',
    `reference`    VARCHAR(100)  NULL,
    `note`         TEXT          NULL,
    `saisi_par`    INT UNSIGNED  NULL,
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_d_date`      (`date_depense`),
    INDEX `idx_d_categorie` (`categorie_id`),
    CONSTRAINT `fk_d_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `depenses_categories`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_d_user`      FOREIGN KEY (`saisi_par`)    REFERENCES `users`(`id`)               ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Données de référence ─────────────────────────────────────────────────────
INSERT IGNORE INTO `frais_types` (`nom`, `description`, `montant_defaut`, `periodicite`) VALUES
('Frais d\'inscription',   'Inscription annuelle',           5000.00, 'unique'),
('Mensualité scolaire',    'Frais mensuels d\'enseignement', 3000.00, 'mensuel'),
('Transport scolaire',     'Navette domicile-école',         2000.00, 'mensuel'),
('Cantine scolaire',       'Repas du midi',                  1500.00, 'mensuel'),
('Activités sportives',    'EPS et activités extérieures',   500.00,  'trimestriel'),
('Fournitures scolaires',  'Manuels et fournitures',         2500.00, 'annuel');

INSERT IGNORE INTO `depenses_categories` (`nom`, `couleur`) VALUES
('Salaires et charges',        '#4e73df'),
('Fournitures et matériel',    '#1cc88a'),
('Infrastructure',             '#36b9cc'),
('Services et utilités',       '#f6c23e'),
('Activités pédagogiques',     '#e74a3b'),
('Divers',                     '#858796');

-- ─── Données de test ──────────────────────────────────────────────────────────
-- Frais affectés aux élèves (3 premiers élèves)
SET @annee = _utf8mb4'2025-2026' COLLATE utf8mb4_unicode_ci;
SET @inscription_id = (SELECT id FROM frais_types WHERE nom LIKE '%inscription%' LIMIT 1);
SET @mensualite_id  = (SELECT id FROM frais_types WHERE nom LIKE '%Mensualit%'  LIMIT 1);
SET @admin_id       = (SELECT id FROM users WHERE role IN ('admin','directeur','comptable','secretaire') LIMIT 1);

INSERT IGNORE INTO `frais_eleves` (eleve_id, frais_type_id, annee_scolaire, montant, echeance, statut)
SELECT e.id, @inscription_id, @annee, 5000.00, DATE_ADD(CONCAT(YEAR(CURDATE()), '-09-30'), INTERVAL 0 DAY), 'en_attente'
FROM eleves e WHERE e.actif = 1 LIMIT 6;

INSERT IGNORE INTO `frais_eleves` (eleve_id, frais_type_id, annee_scolaire, montant, echeance, statut)
SELECT e.id, @mensualite_id, @annee, 30000.00, DATE_ADD(CONCAT(YEAR(CURDATE()), '-06-30'), INTERVAL 0 DAY), 'en_attente'
FROM eleves e WHERE e.actif = 1 LIMIT 6;

-- Quelques paiements
INSERT IGNORE INTO `paiements` (eleve_id, frais_eleve_id, annee_scolaire, montant, date_paiement, mode_paiement, encaisse_par)
SELECT fe.eleve_id, fe.id, @annee, 5000.00, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'especes', @admin_id
FROM frais_eleves fe JOIN frais_types ft ON ft.id = fe.frais_type_id
WHERE ft.nom LIKE '%inscription%' AND fe.annee_scolaire = @annee LIMIT 3;

INSERT IGNORE INTO `paiements` (eleve_id, frais_eleve_id, annee_scolaire, montant, date_paiement, mode_paiement, encaisse_par)
SELECT fe.eleve_id, fe.id, @annee, 15000.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'especes', @admin_id
FROM frais_eleves fe JOIN frais_types ft ON ft.id = fe.frais_type_id
WHERE ft.nom LIKE '%Mensualit%' AND fe.annee_scolaire = @annee LIMIT 2;

-- Mettre à jour les statuts
UPDATE frais_eleves fe SET statut = CASE
    WHEN COALESCE((SELECT SUM(p.montant) FROM paiements p WHERE p.frais_eleve_id = fe.id), 0) >= fe.montant THEN 'paye'
    WHEN COALESCE((SELECT SUM(p.montant) FROM paiements p WHERE p.frais_eleve_id = fe.id), 0) > 0            THEN 'partiel'
    ELSE 'en_attente'
END;

-- Quelques dépenses
SET @cat1 = (SELECT id FROM depenses_categories WHERE nom LIKE '%Salaire%' LIMIT 1);
SET @cat2 = (SELECT id FROM depenses_categories WHERE nom LIKE '%Fourniture%' LIMIT 1);
SET @cat3 = (SELECT id FROM depenses_categories WHERE nom LIKE '%Service%' LIMIT 1);

INSERT IGNORE INTO `depenses` (categorie_id, libelle, montant, date_depense, mode_paiement, saisi_par) VALUES
(@cat1, 'Salaires du personnel — Juin',        120000.00, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'virement', @admin_id),
(@cat2, 'Achat fournitures de bureau',            3500.00, DATE_SUB(CURDATE(), INTERVAL 8 DAY),  'especes',  @admin_id),
(@cat3, 'Facture électricité',                    8000.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY),  'cheque',   @admin_id),
(@cat3, 'Internet et téléphonie',                 2500.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY),  'virement', @admin_id);
