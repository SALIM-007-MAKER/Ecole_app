-- ═══════════════════════════════════════════════════════════════════════════
-- Ecole App — Migration : Module d'authentification complet
-- À exécuter APRÈS ecole_app.sql
-- Date: 2026-06-24
-- ═══════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

-- ─── 1. Mise à jour de la table users ────────────────────────────────────────

-- Convertir anciens rôles avant de modifier l'ENUM
UPDATE `users` SET `role` = 'enseignant' WHERE `role` = 'professeur';
UPDATE `users` SET `role` = 'secretaire' WHERE `role` = 'user';

ALTER TABLE `users`
    MODIFY COLUMN `role` ENUM('admin','directeur','secretaire','comptable','enseignant','parent','eleve')
        NOT NULL DEFAULT 'secretaire',
    ADD COLUMN `prenom`              VARCHAR(100) NULL        AFTER `nom`,
    ADD COLUMN `telephone`           VARCHAR(20)  NULL        AFTER `email`,
    ADD COLUMN `photo`               VARCHAR(255) NULL        AFTER `telephone`,
    ADD COLUMN `derniere_connexion`  DATETIME     NULL,
    ADD COLUMN `reset_token`         VARCHAR(100) NULL,
    ADD COLUMN `reset_token_expires` DATETIME     NULL;

-- ─── 2. Table de réinitialisation de mot de passe ────────────────────────────

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(191)  NOT NULL,
    `token`      VARCHAR(100)  NOT NULL UNIQUE,
    `expires_at` DATETIME      NOT NULL,
    `used`       TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pr_email` (`email`),
    KEY `idx_pr_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. Table des permissions ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `permissions` (
    `id`      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `code`    VARCHAR(100)  NOT NULL UNIQUE,
    `libelle` VARCHAR(200)  NOT NULL,
    `module`  VARCHAR(50)   NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role`          ENUM('admin','directeur','secretaire','comptable','enseignant','parent','eleve') NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role`, `permission_id`),
    CONSTRAINT `fk_rp_perm`
        FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. Données des permissions ───────────────────────────────────────────────

INSERT INTO `permissions` (`code`, `libelle`, `module`) VALUES
-- Élèves
('eleves.view',         'Consulter les élèves',          'eleves'),
('eleves.create',       'Ajouter un élève',              'eleves'),
('eleves.edit',         'Modifier un élève',             'eleves'),
('eleves.delete',       'Supprimer un élève',            'eleves'),
-- Enseignants
('enseignants.view',    'Consulter les enseignants',     'enseignants'),
('enseignants.create',  'Ajouter un enseignant',         'enseignants'),
('enseignants.edit',    'Modifier un enseignant',        'enseignants'),
('enseignants.delete',  'Supprimer un enseignant',       'enseignants'),
-- Classes
('classes.view',        'Consulter les classes',         'classes'),
('classes.create',      'Créer une classe',              'classes'),
('classes.edit',        'Modifier une classe',           'classes'),
('classes.delete',      'Supprimer une classe',          'classes'),
-- Notes
('notes.view',          'Consulter toutes les notes',    'notes'),
('notes.create',        'Saisir une note',               'notes'),
('notes.edit',          'Modifier une note',             'notes'),
('notes.delete',        'Supprimer une note',            'notes'),
('notes.view_own',      'Consulter ses propres notes',   'notes'),
-- Absences
('absences.view',       'Consulter toutes les absences', 'absences'),
('absences.create',     'Enregistrer une absence',       'absences'),
('absences.edit',       'Modifier une absence',          'absences'),
('absences.view_own',   'Consulter ses propres absences','absences'),
-- Utilisateurs
('users.view',          'Consulter les utilisateurs',    'users'),
('users.create',        'Créer un utilisateur',          'users'),
('users.edit',          'Modifier un utilisateur',       'users'),
('users.delete',        'Supprimer un utilisateur',      'users'),
-- Rapports
('rapports.view',       'Consulter les rapports',        'rapports');

-- ─── 5. Affectation des permissions par rôle ─────────────────────────────────

-- Admin : toutes les permissions
INSERT INTO `role_permissions` (`role`, `permission_id`)
SELECT 'admin', `id` FROM `permissions`;

-- Directeur : tout sauf supprimer des utilisateurs
INSERT INTO `role_permissions` (`role`, `permission_id`)
SELECT 'directeur', `id` FROM `permissions`
WHERE `code` NOT IN ('users.delete');

-- Secrétaire
INSERT INTO `role_permissions` (`role`, `permission_id`)
SELECT 'secretaire', `id` FROM `permissions`
WHERE `code` IN (
    'eleves.view','eleves.create','eleves.edit',
    'classes.view',
    'absences.view','absences.create','absences.edit',
    'rapports.view'
);

-- Comptable
INSERT INTO `role_permissions` (`role`, `permission_id`)
SELECT 'comptable', `id` FROM `permissions`
WHERE `code` IN ('eleves.view','classes.view','enseignants.view','rapports.view');

-- Enseignant
INSERT INTO `role_permissions` (`role`, `permission_id`)
SELECT 'enseignant', `id` FROM `permissions`
WHERE `code` IN (
    'eleves.view','classes.view',
    'notes.view','notes.create','notes.edit',
    'absences.view','absences.create','absences.edit'
);

-- Parent (données propres uniquement)
INSERT INTO `role_permissions` (`role`, `permission_id`)
SELECT 'parent', `id` FROM `permissions`
WHERE `code` IN ('notes.view_own','absences.view_own');

-- Élève (données propres uniquement)
INSERT INTO `role_permissions` (`role`, `permission_id`)
SELECT 'eleve', `id` FROM `permissions`
WHERE `code` IN ('notes.view_own','absences.view_own');

-- ─── 6. Utilisateurs de test (mot de passe : "password") ─────────────────────
-- IMPORTANT : Changer ces mots de passe avant toute mise en production !

INSERT INTO `users` (`nom`, `prenom`, `email`, `password`, `role`, `actif`) VALUES
('Benali',    'Karim',   'directeur@ecole.dz',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'directeur',  1),
('Meziane',   'Fatima',  'secretaire@ecole.dz', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'secretaire', 1),
('Hadj',      'Omar',    'comptable@ecole.dz',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'comptable',  1),
('Boudjemaa', 'Amina',   'enseignant@ecole.dz', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'enseignant', 1),
('Slimani',   'Nadia',   'parent@ecole.dz',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'parent',     1),
('Amar',      'Yassine', 'eleve@ecole.dz',      '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'eleve',      1);
