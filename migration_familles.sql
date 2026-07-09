-- ============================================================
-- Phase 1.5 — Migration Familles / Parents
-- SCOLARIS V2 — À exécuter AVANT d'activer le module
-- ============================================================
-- Contrainte : CREATE TABLE IF NOT EXISTS — ne jamais DROP
-- ============================================================

-- Table principale : une famille = un foyer / responsable légal
CREATE TABLE IF NOT EXISTS `familles` (
  `id`                        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `nom`                       VARCHAR(100)    NOT NULL,
  `adresse`                   VARCHAR(255)    DEFAULT NULL,
  `code_postal`               VARCHAR(10)     DEFAULT NULL,
  `ville`                     VARCHAR(100)    DEFAULT NULL,
  `telephone`                 VARCHAR(20)     DEFAULT NULL,
  `email`                     VARCHAR(150)    DEFAULT NULL,
  `contact_urgence_nom`       VARCHAR(100)    DEFAULT NULL,
  `contact_urgence_telephone` VARCHAR(20)     DEFAULT NULL,
  `contact_urgence_lien`      VARCHAR(50)     DEFAULT NULL,
  `notes`                     TEXT            DEFAULT NULL,
  `actif`                     TINYINT(1)      NOT NULL DEFAULT 1,
  `created_by`                INT UNSIGNED    DEFAULT NULL,
  `created_at`                DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_familles_nom`  (`nom`),
  KEY `idx_familles_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de liaison : N élèves ↔ N familles (plusieurs responsables par élève)
CREATE TABLE IF NOT EXISTS `familles_eleves` (
  `id`                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `famille_id`          INT UNSIGNED    NOT NULL,
  `eleve_id`            INT UNSIGNED    NOT NULL,
  `lien_parente`        ENUM('pere','mere','tuteur','autre') NOT NULL DEFAULT 'autre',
  `est_responsable_legal` TINYINT(1)   NOT NULL DEFAULT 1,
  `est_contact_principal` TINYINT(1)   NOT NULL DEFAULT 0,
  `est_contact_urgence`   TINYINT(1)   NOT NULL DEFAULT 0,
  `ordre`               TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_famille_eleve` (`famille_id`, `eleve_id`),
  KEY `idx_fe_eleve`   (`eleve_id`),
  KEY `idx_fe_famille` (`famille_id`),
  CONSTRAINT `fk_fe_famille` FOREIGN KEY (`famille_id`)
      REFERENCES `familles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fe_eleve` FOREIGN KEY (`eleve_id`)
      REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vérification : table eleves.parent_id reste INCHANGÉE (V1 compat)
-- Ne pas ajouter de FK sur eleves.parent_id → familles
