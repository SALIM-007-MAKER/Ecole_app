-- =============================================================================
-- RH V2 — Migration 01 : Organisation (Départements + Postes)
-- Phase 6.2 — SCOLARIS V2
-- Exécuter AVANT rh_002_employes.sql
-- =============================================================================

-- Table 1 : Départements
CREATE TABLE IF NOT EXISTS rh_departements (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(100)  NOT NULL,
    code            VARCHAR(20)   NOT NULL UNIQUE,
    description     TEXT,
    responsable_id  INT UNSIGNED  NULL COMMENT 'FK rh_employes.id — ajouté après import employés',
    parent_id       INT UNSIGNED  NULL COMMENT 'Self-référentiel pour sous-départements',
    actif           TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_parent (parent_id),
    KEY idx_actif  (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2 : Postes / Fonctions
CREATE TABLE IF NOT EXISTS rh_postes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    intitule        VARCHAR(150)  NOT NULL,
    code            VARCHAR(30)   NOT NULL UNIQUE,
    departement_id  INT UNSIGNED  NULL,
    categorie       ENUM('enseignant','administratif','support','direction','technique') NOT NULL DEFAULT 'administratif',
    niveau          TINYINT       NOT NULL DEFAULT 1 COMMENT '1=junior 2=confirmé 3=senior 4=chef',
    description     TEXT,
    actif           TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_departement (departement_id),
    KEY idx_categorie   (categorie),
    KEY idx_actif       (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeds postes fondateurs
INSERT INTO rh_postes (intitule, code, categorie, niveau) VALUES
    ('Directeur(trice)',                          'DIR',       'direction',      4),
    ('Directeur(trice) adjoint(e)',               'DIR_ADJ',   'direction',      3),
    ('Secrétaire de direction',                   'SEC_DIR',   'administratif',  2),
    ('Secrétaire',                                'SEC',       'administratif',  1),
    ('Comptable',                                 'COMPTA',    'administratif',  2),
    ('Professeur certifié (PES)',                 'ENS_PES',   'enseignant',     3),
    ('Professeur de l''enseignement moyen (PEM)', 'ENS_PEM',   'enseignant',     2),
    ('Professeur contractuel',                    'ENS_CONT',  'enseignant',     1),
    ('Vacataire',                                 'ENS_VAC',   'enseignant',     1),
    ('Maître formateur',                          'ENS_MF',    'enseignant',     4),
    ('Surveillant général',                       'SURV',      'support',        2),
    ('Agent d''entretien',                        'ENTRET',    'support',        1),
    ('Informaticien',                             'INFOR',     'technique',      2)
ON DUPLICATE KEY UPDATE intitule = VALUES(intitule);

-- Seeds départements fondateurs
INSERT INTO rh_departements (nom, code, description) VALUES
    ('Direction',                 'DIR',    'Direction générale de l''établissement'),
    ('Administration & Secrétariat', 'ADMIN', 'Secrétariat, accueil, gestion administrative'),
    ('Corps Enseignant',          'ENS',    'Enseignants toutes matières confondues'),
    ('Comptabilité & Finance',    'FIN',    'Gestion financière et comptable'),
    ('Vie Scolaire',              'VS',     'Surveillants, éducateurs, encadrement élèves'),
    ('Services Techniques',       'TECH',   'Entretien, maintenance, informatique')
ON DUPLICATE KEY UPDATE nom = VALUES(nom);
