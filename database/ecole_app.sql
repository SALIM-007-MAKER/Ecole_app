-- ═══════════════════════════════════════════════════════════════════════════
-- Ecole App — Script de création de la base de données
-- MySQL 8.0+
-- ═══════════════════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS `ecole_app`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `ecole_app`;

-- ─── Utilisateurs (administrateurs, professeurs) ──────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `nom`        VARCHAR(100)   NOT NULL,
    `email`      VARCHAR(191)   NOT NULL UNIQUE,
    `password`   VARCHAR(255)   NOT NULL,
    `role`       ENUM('admin','directeur','professeur','secretaire','user') NOT NULL DEFAULT 'user',
    `actif`      TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Classes ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `classes` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `nom`           VARCHAR(50)   NOT NULL,
    `niveau`        VARCHAR(30)   NOT NULL COMMENT 'Ex: 1ère AS, 2ème AM',
    `annee_scolaire` VARCHAR(9)  NOT NULL COMMENT 'Ex: 2024-2025',
    `max_eleves`    TINYINT UNSIGNED NOT NULL DEFAULT 35,
    `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Élèves ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `eleves` (
    `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `matricule`    VARCHAR(20)    NOT NULL UNIQUE,
    `nom`          VARCHAR(100)   NOT NULL,
    `prenom`       VARCHAR(100)   NOT NULL,
    `date_naissance` DATE         NOT NULL,
    `sexe`         ENUM('M','F')  NOT NULL,
    `adresse`      TEXT           NULL,
    `telephone`    VARCHAR(20)    NULL,
    `email`        VARCHAR(191)   NULL,
    `classe_id`    INT UNSIGNED   NULL,
    `photo`        VARCHAR(255)   NULL,
    `actif`        TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_classe` (`classe_id`),
    KEY `idx_nom`    (`nom`, `prenom`),
    CONSTRAINT `fk_eleve_classe`
        FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Professeurs ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `professeurs` (
    `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED   NULL,
    `nom`          VARCHAR(100)   NOT NULL,
    `prenom`       VARCHAR(100)   NOT NULL,
    `specialite`   VARCHAR(100)   NOT NULL,
    `telephone`    VARCHAR(20)    NULL,
    `email`        VARCHAR(191)   NULL,
    `actif`        TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_prof_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Matières ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `matieres` (
    `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `nom`          VARCHAR(100)   NOT NULL,
    `coefficient`  DECIMAL(3,1)   NOT NULL DEFAULT 1.0,
    `created_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Enseignements (professeur → matière → classe) ───────────────────────
CREATE TABLE IF NOT EXISTS `enseignements` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `professeur_id` INT UNSIGNED  NOT NULL,
    `matiere_id`    INT UNSIGNED  NOT NULL,
    `classe_id`     INT UNSIGNED  NOT NULL,
    `annee_scolaire` VARCHAR(9)  NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ens` (`professeur_id`, `matiere_id`, `classe_id`, `annee_scolaire`),
    CONSTRAINT `fk_ens_prof`    FOREIGN KEY (`professeur_id`) REFERENCES `professeurs`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ens_matiere` FOREIGN KEY (`matiere_id`)    REFERENCES `matieres`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_ens_classe`  FOREIGN KEY (`classe_id`)     REFERENCES `classes`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Notes ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notes` (
    `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `eleve_id`     INT UNSIGNED   NOT NULL,
    `matiere_id`   INT UNSIGNED   NOT NULL,
    `trimestre`    TINYINT(1)     NOT NULL COMMENT '1, 2 ou 3',
    `note`         DECIMAL(5,2)   NOT NULL,
    `type_note`    ENUM('devoir','composition','oral','tp') NOT NULL DEFAULT 'devoir',
    `date_note`    DATE           NOT NULL,
    `observation`  TEXT           NULL,
    `created_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_eleve_mat` (`eleve_id`, `matiere_id`),
    CONSTRAINT `fk_note_eleve`   FOREIGN KEY (`eleve_id`)   REFERENCES `eleves`(`id`)   ON DELETE CASCADE,
    CONSTRAINT `fk_note_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Absences ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `absences` (
    `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `eleve_id`     INT UNSIGNED   NOT NULL,
    `date_absence` DATE           NOT NULL,
    `justifiee`    TINYINT(1)     NOT NULL DEFAULT 0,
    `motif`        TEXT           NULL,
    `nb_heures`    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_abs_eleve` (`eleve_id`),
    KEY `idx_abs_date`  (`date_absence`),
    CONSTRAINT `fk_abs_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Données de test ─────────────────────────────────────────────────────

-- Administrateur par défaut (mot de passe: Admin@2024)
INSERT INTO `users` (`nom`, `email`, `password`, `role`) VALUES
('Administrateur', 'admin@ecole.dz', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- NOTE: Mot de passe "password" (hash bcrypt). CHANGER EN PRODUCTION !

-- Classes
INSERT INTO `classes` (`nom`, `niveau`, `annee_scolaire`) VALUES
('A', '1ère AS', '2024-2025'),
('B', '1ère AS', '2024-2025'),
('A', '2ème AS', '2024-2025'),
('A', '3ème AS', '2024-2025');

-- Matières
INSERT INTO `matieres` (`nom`, `coefficient`) VALUES
('Mathématiques', 5.0),
('Physique', 4.0),
('Arabe', 3.0),
('Français', 3.0),
('Anglais', 2.0),
('Histoire-Géographie', 2.0),
('Sciences Naturelles', 3.0),
('Philosophie', 2.0);
