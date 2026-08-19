-- ============================================================
-- Migration : Module Académique — Notes, Contrôles, Bulletins
-- À exécuter APRÈS classes_matieres_migration.sql
-- ============================================================

USE `ecole_app`;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. Périodes scolaires ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `periodes` (
    `id`             INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    `nom`            VARCHAR(50)     NOT NULL,
    `type`           ENUM('trimestre','semestre') NOT NULL DEFAULT 'trimestre',
    `annee_scolaire` VARCHAR(9)      NOT NULL COMMENT 'Format : 2025-2026',
    `date_debut`     DATE            NULL,
    `date_fin`       DATE            NULL,
    `actif`          TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_periode_annee` (`annee_scolaire`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 2. Contrôles / Évaluations ────────────────────────────────
CREATE TABLE IF NOT EXISTS `controles` (
    `id`             INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    `libelle`        VARCHAR(100)    NOT NULL,
    `type`           ENUM('controle','devoir','examen','tp','oral') NOT NULL DEFAULT 'controle',
    `coefficient`    DECIMAL(4,2)    NOT NULL DEFAULT 1.00,
    `note_max`       DECIMAL(5,2)    NOT NULL DEFAULT 20.00,
    `matiere_id`     INT UNSIGNED    NOT NULL,
    `classe_id`      INT UNSIGNED    NOT NULL,
    `periode_id`     INT UNSIGNED    NOT NULL,
    `date_controle`  DATE            NULL,
    `created_at`     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ctrl_context` (`classe_id`, `matiere_id`, `periode_id`),
    CONSTRAINT `fk_ctrl_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ctrl_classe`  FOREIGN KEY (`classe_id`)  REFERENCES `classes`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_ctrl_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 3. Notes des élèves ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notes` (
    `id`           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    `eleve_id`     INT UNSIGNED  NOT NULL,
    `controle_id`  INT UNSIGNED  NOT NULL,
    `note`         DECIMAL(5,2)  NULL     COMMENT 'NULL = non encore saisie',
    `absent`       TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 = absent => compte comme 0',
    `appreciation` VARCHAR(255)  NULL,
    `created_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_note` (`eleve_id`, `controle_id`),
    CONSTRAINT `fk_note_eleve`    FOREIGN KEY (`eleve_id`)    REFERENCES `eleves`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_note_controle` FOREIGN KEY (`controle_id`) REFERENCES `controles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 4. Cache : moyennes par matière ──────────────────────────
CREATE TABLE IF NOT EXISTS `moyennes_matieres` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `eleve_id`    INT UNSIGNED NOT NULL,
    `matiere_id`  INT UNSIGNED NOT NULL,
    `classe_id`   INT UNSIGNED NOT NULL,
    `periode_id`  INT UNSIGNED NOT NULL,
    `moyenne`     DECIMAL(5,2) NULL,
    `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_mm` (`eleve_id`, `matiere_id`, `classe_id`, `periode_id`),
    CONSTRAINT `fk_mm_eleve`   FOREIGN KEY (`eleve_id`)   REFERENCES `eleves`(`id`)   ON DELETE CASCADE,
    CONSTRAINT `fk_mm_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mm_classe`  FOREIGN KEY (`classe_id`)  REFERENCES `classes`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_mm_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 5. Cache : moyennes générales et classement ───────────────
CREATE TABLE IF NOT EXISTS `moyennes_generales` (
    `id`               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    `eleve_id`         INT UNSIGNED  NOT NULL,
    `classe_id`        INT UNSIGNED  NOT NULL,
    `periode_id`       INT UNSIGNED  NOT NULL,
    `moyenne_generale` DECIMAL(5,2)  NULL,
    `rang`             SMALLINT UNSIGNED NULL,
    `mention`          VARCHAR(30)   NULL,
    `updated_at`       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_mg` (`eleve_id`, `classe_id`, `periode_id`),
    CONSTRAINT `fk_mg_eleve`   FOREIGN KEY (`eleve_id`)   REFERENCES `eleves`(`id`)   ON DELETE CASCADE,
    CONSTRAINT `fk_mg_classe`  FOREIGN KEY (`classe_id`)  REFERENCES `classes`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_mg_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Données : 3 trimestres 2025-2026 ──────────────────────────
INSERT INTO `periodes` (`nom`, `type`, `annee_scolaire`, `date_debut`, `date_fin`, `actif`) VALUES
('Trimestre 1', 'trimestre', '2025-2026', '2025-09-15', '2025-12-20', 1),
('Trimestre 2', 'trimestre', '2025-2026', '2026-01-07', '2026-03-27', 1),
('Trimestre 3', 'trimestre', '2025-2026', '2026-04-05', '2026-06-25', 1);

-- ── Contrôles de démonstration (Classe 1 = Seconde-A, Trimestre 1) ──
INSERT INTO `controles` (`libelle`, `type`, `coefficient`, `note_max`, `matiere_id`, `classe_id`, `periode_id`, `date_controle`) VALUES
('Contrôle 1',      'controle', 1.00, 20, 1, 1, 1, '2025-10-15'),  -- Maths
('Devoir maison',   'devoir',   0.50, 20, 1, 1, 1, '2025-11-01'),  -- Maths
('Examen T1',       'examen',   2.00, 20, 1, 1, 1, '2025-12-10'),  -- Maths
('Contrôle 1',      'controle', 1.00, 20, 2, 1, 1, '2025-10-20'),  -- Physique
('Examen T1',       'examen',   2.00, 20, 2, 1, 1, '2025-12-12'),  -- Physique
('Contrôle 1',      'controle', 1.00, 20, 4, 1, 1, '2025-10-18'),  -- Français
('Examen T1',       'examen',   2.00, 20, 4, 1, 1, '2025-12-15'),  -- Français
('Contrôle 1',      'controle', 1.00, 20, 3, 1, 1, '2025-10-17'),  -- Arabe
('Examen T1',       'examen',   2.00, 20, 3, 1, 1, '2025-12-11');  -- Arabe

-- ── Notes de démonstration ────────────────────────────────────
-- Khalid Benali (eleve_id=1, classe_id=1)
INSERT INTO `notes` (`eleve_id`, `controle_id`, `note`) VALUES
(1,1,14.50),(1,2,16.00),(1,3,13.00),   -- Maths
(1,4,12.00),(1,5,11.50),               -- Physique
(1,6,15.00),(1,7,14.00),               -- Français
(1,8,13.00),(1,9,12.50);               -- Arabe

-- Amira Meziane (eleve_id=2, classe_id=1)
INSERT INTO `notes` (`eleve_id`, `controle_id`, `note`) VALUES
(2,1,17.00),(2,2,18.00),(2,3,16.50),   -- Maths
(2,4,15.50),(2,5,16.00),               -- Physique
(2,6,18.00),(2,7,17.50),               -- Français
(2,8,16.00),(2,9,17.00);               -- Arabe

-- Sara Boukerrou (eleve_id=4, classe_id=1)
INSERT INTO `notes` (`eleve_id`, `controle_id`, `note`) VALUES
(4,1,10.00),(4,2,11.00),(4,3,9.50),    -- Maths
(4,4,13.00),(4,5,12.00),               -- Physique
(4,6,14.00),(4,7,13.50),               -- Français
(4,8,11.00),(4,9,10.00);               -- Arabe
