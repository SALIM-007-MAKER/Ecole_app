-- ============================================================
-- Migration : Module Classes & Matières
-- À exécuter APRÈS eleves_migration.sql
-- ============================================================

-- ── Mise à jour de la table matieres ────────────────────────
ALTER TABLE `matieres`
    ADD COLUMN `volume_horaire` TINYINT UNSIGNED NOT NULL DEFAULT 2
        COMMENT 'Heures par semaine' AFTER `coefficient`,
    ADD COLUMN `description`   TEXT NULL AFTER `volume_horaire`;

-- volume horaire des matières existantes
UPDATE `matieres` SET `volume_horaire` = 5 WHERE `nom` = 'Mathématiques';
UPDATE `matieres` SET `volume_horaire` = 4 WHERE `nom` = 'Physique';
UPDATE `matieres` SET `volume_horaire` = 5 WHERE `nom` = 'Arabe';
UPDATE `matieres` SET `volume_horaire` = 4 WHERE `nom` = 'Français';
UPDATE `matieres` SET `volume_horaire` = 3 WHERE `nom` = 'Anglais';
UPDATE `matieres` SET `volume_horaire` = 2 WHERE `nom` = 'Histoire-Géographie';
UPDATE `matieres` SET `volume_horaire` = 3 WHERE `nom` = 'Sciences Naturelles';
UPDATE `matieres` SET `volume_horaire` = 2 WHERE `nom` = 'Philosophie';

-- colonne responsable_id ajoutée APRÈS l'insertion des professeurs
-- (voir plus bas)

-- ── Mise à jour de la table classes ─────────────────────────
ALTER TABLE `classes`
    ADD COLUMN `description` TEXT NULL AFTER `max_eleves`;

-- ── Professeurs de test ──────────────────────────────────────
-- Lie le compte enseignant existant au premier professeur
INSERT INTO `professeurs` (`nom`, `prenom`, `specialite`, `telephone`, `email`, `actif`) VALUES
('Khelifi',   'Mohamed',     'Mathématiques',      '0550001111', 'khelifi@ecole.dz',   1),
('Bensalem',  'Farida',      'Physique',            '0550002222', 'bensalem@ecole.dz',  1),
('Cheikh',    'Abdelkader',  'Langue Arabe',        '0550003333', 'cheikh@ecole.dz',    1),
('Moussaoui', 'Leila',       'Langue Française',    '0550004444', 'moussaoui@ecole.dz', 1),
('Boukadoum', 'Samir',       'Langue Anglaise',     '0550005555', 'boukadoum@ecole.dz', 1),
('Chouaib',   'Nadia',       'Sciences Naturelles', '0550006666', 'chouaib@ecole.dz',   1),
('Terki',     'Kamel',       'Histoire-Géographie', '0550007777', 'terki@ecole.dz',     1),
('Zerrouk',   'Samia',       'Philosophie',         '0550008888', 'zerrouk@ecole.dz',   1);

-- Lier le compte utilisateur enseignant au professeur 1
UPDATE `professeurs`
SET `user_id` = (SELECT `id` FROM `users` WHERE `role` = 'enseignant' LIMIT 1)
WHERE `id` = 1;

-- ── Colonne responsable_id sur matieres ──────────────────────
ALTER TABLE `matieres`
    ADD COLUMN `responsable_id` INT UNSIGNED NULL AFTER `volume_horaire`,
    ADD CONSTRAINT `fk_mat_resp`
        FOREIGN KEY (`responsable_id`) REFERENCES `professeurs`(`id`) ON DELETE SET NULL;

-- Affecter les responsables
UPDATE `matieres` SET `responsable_id` = 1 WHERE `nom` = 'Mathématiques';
UPDATE `matieres` SET `responsable_id` = 2 WHERE `nom` = 'Physique';
UPDATE `matieres` SET `responsable_id` = 3 WHERE `nom` = 'Arabe';
UPDATE `matieres` SET `responsable_id` = 4 WHERE `nom` = 'Français';
UPDATE `matieres` SET `responsable_id` = 5 WHERE `nom` = 'Anglais';
UPDATE `matieres` SET `responsable_id` = 7 WHERE `nom` = 'Histoire-Géographie';
UPDATE `matieres` SET `responsable_id` = 6 WHERE `nom` = 'Sciences Naturelles';
UPDATE `matieres` SET `responsable_id` = 8 WHERE `nom` = 'Philosophie';

-- ── Enseignements de test (prof × matière × classe) ─────────
-- Classe 1 (1ère AS A)
INSERT INTO `enseignements` (`professeur_id`, `matiere_id`, `classe_id`, `annee_scolaire`) VALUES
(1, 1, 1, '2024-2025'),   -- Khelifi / Maths / Seconde-A
(2, 2, 1, '2024-2025'),   -- Bensalem / Physique / Seconde-A
(3, 3, 1, '2024-2025'),   -- Cheikh / Arabe / Seconde-A
(4, 4, 1, '2024-2025'),   -- Moussaoui / Français / Seconde-A
(5, 5, 1, '2024-2025'),   -- Boukadoum / Anglais / Seconde-A
(7, 6, 1, '2024-2025'),   -- Terki / Hist-Géo / Seconde-A
-- Classe 2 (1ère AS B)
(1, 1, 2, '2024-2025'),   -- Khelifi / Maths / Seconde-B
(2, 2, 2, '2024-2025'),   -- Bensalem / Physique / Seconde-B
(3, 3, 2, '2024-2025'),   -- Cheikh / Arabe / Seconde-B
-- Classe 3 (2ème AS A)
(1, 1, 3, '2024-2025'),   -- Khelifi / Maths / 2AS-A
(4, 4, 3, '2024-2025'),   -- Moussaoui / Français / 2AS-A
(8, 8, 3, '2024-2025');   -- Zerrouk / Philosophie / 2AS-A

-- ── Permissions pour les matières ───────────────────────────
INSERT IGNORE INTO `permissions` (`code`, `libelle`, `module`) VALUES
('matieres.view',   'Voir la liste des matières', 'matieres'),
('matieres.create', 'Ajouter une matière',        'matieres'),
('matieres.edit',   'Modifier une matière',        'matieres'),
('matieres.delete', 'Supprimer une matière',       'matieres');

-- Rôles admin et directeur
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT r.role, p.id
FROM (SELECT 'admin' AS role UNION SELECT 'directeur') r
CROSS JOIN `permissions` p
WHERE p.code IN ('matieres.view','matieres.create','matieres.edit','matieres.delete');

-- Rôle enseignant et secrétaire : lecture seule
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT r.role, p.id
FROM (SELECT 'enseignant' AS role UNION SELECT 'secretaire' UNION SELECT 'comptable') r
CROSS JOIN `permissions` p
WHERE p.code = 'matieres.view';
