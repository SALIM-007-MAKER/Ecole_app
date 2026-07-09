-- ═══════════════════════════════════════════════════════════════════════════
-- Ecole App — Migration : Module Gestion des Élèves
-- À exécuter APRÈS auth_migration.sql
-- ═══════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

-- ─── 1. Mise à jour de la table eleves ───────────────────────────────────────

-- Ajouter le lien vers le parent responsable
ALTER TABLE `eleves`
    ADD COLUMN `parent_id` INT UNSIGNED NULL AFTER `classe_id`,
    ADD CONSTRAINT `fk_eleve_parent`
        FOREIGN KEY (`parent_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Index de recherche plein-texte
ALTER TABLE `eleves`
    ADD INDEX `idx_eleve_parent` (`parent_id`),
    ADD INDEX `idx_eleve_actif`  (`actif`);

-- ─── 2. Répertoire public uploads ────────────────────────────────────────────
-- Le dossier public/uploads/eleves/ sera créé automatiquement par le contrôleur

-- ─── 3. Données de test ───────────────────────────────────────────────────────

INSERT INTO `eleves`
    (`matricule`, `nom`, `prenom`, `date_naissance`, `sexe`,
     `adresse`, `telephone`, `email`, `classe_id`, `actif`)
VALUES
('2024-0001', 'Benali',    'Khalid',   '2007-03-15', 'M',
 '12 Rue de la Paix, Alger',       '0550123456', 'khalid.benali@edu.dz',    1, 1),
('2024-0002', 'Meziane',   'Amira',    '2007-07-22', 'F',
 '5 Cité Universitaire, Blida',    '0551234567', 'amira.meziane@edu.dz',    1, 1),
('2024-0003', 'Hadj',      'Ismail',   '2008-01-10', 'M',
 '78 Avenue Ahmed Ghermoul',       '0552345678', 'ismail.hadj@edu.dz',      2, 1),
('2024-0004', 'Boukerrou', 'Sara',     '2007-11-30', 'F',
 '3 Rue Didouche Mourad, Alger',   '0553456789', 'sara.boukerrou@edu.dz',   1, 1),
('2024-0005', 'Chabane',   'Riad',     '2008-06-05', 'M',
 '9 Cité El Badr, Constantine',    '0554567890', 'riad.chabane@edu.dz',     3, 1),
('2024-0006', 'Aissaoui',  'Nour',     '2007-09-18', 'F',
 '25 Rue Larbi Ben Mhidi',         '0555678901', 'nour.aissaoui@edu.dz',    2, 1),
('2024-0007', 'Belkacem',  'Youcef',   '2008-04-25', 'M',
 '47 Cité Les Sources, Annaba',    '0556789012', 'youcef.belkacem@edu.dz',  4, 1),
('2024-0008', 'Talbi',     'Malika',   '2007-12-03', 'F',
 '16 Rue Colonel Amirouche, Oran', '0557890123', 'malika.talbi@edu.dz',     3, 1),
('2024-0009', 'Amrani',    'Sofiane',  '2007-05-20', 'M',
 '33 Boulevard des Martyrs',       '0558901234', 'sofiane.amrani@edu.dz',   4, 1),
('2024-0010', 'Khelil',    'Yasmine',  '2008-08-14', 'F',
 '2 Cité Palmier, Tizi Ouzou',     '0559012345', 'yasmine.khelil@edu.dz',   2, 1);
