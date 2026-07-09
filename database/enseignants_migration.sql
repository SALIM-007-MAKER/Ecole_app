-- ============================================================
-- Migration : Module Enseignants
-- À exécuter APRÈS classes_matieres_migration.sql
-- ============================================================

-- ── Colonnes supplémentaires sur professeurs ─────────────────
ALTER TABLE `professeurs`
    ADD COLUMN `grade`            VARCHAR(100)  NULL AFTER `specialite`,
    ADD COLUMN `date_recrutement` DATE          NULL AFTER `grade`,
    ADD COLUMN `adresse`          TEXT          NULL AFTER `date_recrutement`,
    ADD COLUMN `photo`            VARCHAR(255)  NULL AFTER `adresse`;

-- ── Renseigner les professeurs de test ───────────────────────
UPDATE `professeurs` SET
    grade            = 'Professeur certifié (PES)',
    date_recrutement = '2015-09-01'
WHERE id = 1;

UPDATE `professeurs` SET
    grade            = 'Professeur certifié (PES)',
    date_recrutement = '2013-09-01'
WHERE id = 2;

UPDATE `professeurs` SET
    grade            = "Professeur de l'enseignement moyen (PEM)",
    date_recrutement = '2018-09-01'
WHERE id = 3;

UPDATE `professeurs` SET
    grade            = "Professeur de l'enseignement moyen (PEM)",
    date_recrutement = '2019-09-01'
WHERE id = 4;

UPDATE `professeurs` SET
    grade            = 'Professeur certifié (PES)',
    date_recrutement = '2020-09-01'
WHERE id = 5;

UPDATE `professeurs` SET
    grade            = 'Maître formateur',
    date_recrutement = '2010-09-01'
WHERE id = 6;

UPDATE `professeurs` SET
    grade            = 'Professeur contractuel',
    date_recrutement = '2022-09-01'
WHERE id = 7;

UPDATE `professeurs` SET
    grade            = 'Maître assistant',
    date_recrutement = '2021-09-01'
WHERE id = 8;

-- ── Répertoire photos (créé par PHP au premier upload) ───────
-- public/uploads/professeurs/ sera créé automatiquement par le contrôleur
