-- ============================================================
-- Phase 1.6 — Migration Référentiel Pédagogique (Matières V2)
-- SCOLARIS V2 — À exécuter AVANT d'activer le module
-- ============================================================
-- SAFE : ALTER TABLE ADD COLUMN IF NOT EXISTS (MySQL 8.0+)
-- Ne supprime aucune colonne existante.
-- ============================================================

ALTER TABLE `matieres`
    ADD COLUMN IF NOT EXISTS `filiere`  VARCHAR(50) DEFAULT NULL     COMMENT 'Série/filière optionnelle (Scientifique, Littéraire…)',
    ADD COLUMN IF NOT EXISTS `niveaux`  TEXT        DEFAULT NULL     COMMENT 'Niveaux concernés — CSV (1AM,2AM,1AS…)',
    ADD COLUMN IF NOT EXISTS `actif`    TINYINT(1)  NOT NULL DEFAULT 1 COMMENT 'Actif=1 / Archivé=0',
    ADD COLUMN IF NOT EXISTS `couleur`  VARCHAR(7)  DEFAULT NULL     COMMENT 'Couleur hexadécimale UI (#3B82F6)';

-- Marquer toutes les matières existantes comme actives si la colonne vient d'être ajoutée
UPDATE `matieres` SET `actif` = 1 WHERE `actif` IS NULL;

-- Index pour les recherches fréquentes
CREATE INDEX IF NOT EXISTS `idx_matieres_actif`   ON `matieres` (`actif`);
CREATE INDEX IF NOT EXISTS `idx_matieres_filiere` ON `matieres` (`filiere`);
