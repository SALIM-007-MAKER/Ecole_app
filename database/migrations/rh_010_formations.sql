-- ============================================================
-- Phase 6.10 — Domaine Formations & Compétences RH V2
-- Tables : rh_formations_organismes, rh_formations_catalogue,
--          rh_formations_sessions, rh_formations_inscriptions,
--          rh_formations_presences, rh_certifications,
--          rh_employe_certifications, rh_competences,
--          rh_employe_competences, rh_formation_competences
-- ============================================================

-- Organismes de formation (internes & externes)
CREATE TABLE IF NOT EXISTS rh_formations_organismes (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(50)  NOT NULL,
    nom          VARCHAR(255) NOT NULL,
    type         ENUM('interne','externe','certifiant','universite') DEFAULT 'externe',
    contact_nom  VARCHAR(120) NULL,
    contact_email VARCHAR(120) NULL,
    contact_tel  VARCHAR(30)  NULL,
    site_web     VARCHAR(255) NULL,
    pays         VARCHAR(60)  NULL,
    ville        VARCHAR(100) NULL,
    actif        TINYINT(1)   DEFAULT 1,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_organisme_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogue de formations
CREATE TABLE IF NOT EXISTS rh_formations_catalogue (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                 VARCHAR(50)  NOT NULL,
    titre                VARCHAR(255) NOT NULL,
    description          TEXT         NULL,
    type                 ENUM('interne','externe','e_learning','certification','coaching','seminaire') DEFAULT 'interne',
    duree_heures         DECIMAL(6,1) NOT NULL DEFAULT 0,
    niveau               ENUM('debutant','intermediaire','avance','expert') DEFAULT 'debutant',
    modalite             ENUM('presentiel','distanciel','hybride') DEFAULT 'presentiel',
    organisme_id         INT UNSIGNED NULL,
    formateur_principal  VARCHAR(120) NULL,
    cout_unitaire        DECIMAL(10,2) DEFAULT 0.00,
    devise               VARCHAR(10)  DEFAULT 'XAF',
    max_participants     TINYINT      DEFAULT 20,
    prerequis            TEXT         NULL,
    objectifs            TEXT         NULL,
    actif                TINYINT(1)   DEFAULT 1,
    created_by           INT UNSIGNED NOT NULL,
    created_at           DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at           DATETIME     NULL,
    UNIQUE KEY uq_formation_code (code),
    FOREIGN KEY (organisme_id) REFERENCES rh_formations_organismes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions de formation
CREATE TABLE IF NOT EXISTS rh_formations_sessions (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formation_id     INT UNSIGNED NOT NULL,
    code_session     VARCHAR(60)  NOT NULL,
    lieu             VARCHAR(255) NULL,
    date_debut       DATE         NOT NULL,
    date_fin         DATE         NOT NULL,
    heure_debut      TIME         DEFAULT '08:00:00',
    heure_fin        TIME         DEFAULT '17:00:00',
    statut           ENUM('planifiee','ouverte','en_cours','terminee','annulee') DEFAULT 'planifiee',
    max_participants TINYINT      DEFAULT 20,
    nb_inscrits      TINYINT      DEFAULT 0,
    formateur_nom    VARCHAR(120) NULL,
    cout_total       DECIMAL(10,2) DEFAULT 0.00,
    financeur        ENUM('etablissement','organisme','personnel','mixte') DEFAULT 'etablissement',
    commentaire      TEXT         NULL,
    created_by       INT UNSIGNED NOT NULL,
    created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       DATETIME     NULL,
    UNIQUE KEY uq_session_code (code_session),
    FOREIGN KEY (formation_id) REFERENCES rh_formations_catalogue(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inscriptions aux sessions
CREATE TABLE IF NOT EXISTS rh_formations_inscriptions (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id            INT UNSIGNED NOT NULL,
    employe_id            INT UNSIGNED NOT NULL,
    statut                ENUM('inscrit','confirme','present','absent','valide','annule') DEFAULT 'inscrit',
    date_inscription      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    date_confirmation     DATETIME     NULL,
    date_validation       DATETIME     NULL,
    note_evaluation       DECIMAL(4,2) NULL,
    commentaire_evaluation TEXT         NULL,
    attestation_delivree  TINYINT(1)   DEFAULT 0,
    created_by            INT UNSIGNED NOT NULL,
    updated_by            INT UNSIGNED NOT NULL,
    created_at            DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inscription (session_id, employe_id),
    FOREIGN KEY (session_id)  REFERENCES rh_formations_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (employe_id)  REFERENCES rh_employes(id)             ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Présences (pour formations multi-jours)
CREATE TABLE IF NOT EXISTS rh_formations_presences (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inscription_id INT UNSIGNED NOT NULL,
    date           DATE         NOT NULL,
    present        TINYINT(1)   DEFAULT 1,
    commentaire    TEXT         NULL,
    created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_presence (inscription_id, date),
    FOREIGN KEY (inscription_id) REFERENCES rh_formations_inscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogue certifications
CREATE TABLE IF NOT EXISTS rh_certifications (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                VARCHAR(50)  NOT NULL,
    libelle             VARCHAR(255) NOT NULL,
    description         TEXT         NULL,
    organisme_id        INT UNSIGNED NULL,
    duree_validite_mois INT          NULL,
    renouvelable        TINYINT(1)   DEFAULT 1,
    formation_id        INT UNSIGNED NULL,
    actif               TINYINT(1)   DEFAULT 1,
    created_at          DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_certification_code (code),
    FOREIGN KEY (organisme_id) REFERENCES rh_formations_organismes(id) ON DELETE SET NULL,
    FOREIGN KEY (formation_id) REFERENCES rh_formations_catalogue(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Certifications des employés
CREATE TABLE IF NOT EXISTS rh_employe_certifications (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id          INT UNSIGNED NOT NULL,
    certification_id    INT UNSIGNED NOT NULL,
    date_obtention      DATE         NOT NULL,
    date_expiration     DATE         NULL,
    statut              ENUM('valide','expiree','a_renouveler') DEFAULT 'valide',
    session_id          INT UNSIGNED NULL,
    reference_certificat VARCHAR(100) NULL,
    fichier_certificat  VARCHAR(255) NULL,
    notes               TEXT         NULL,
    created_by          INT UNSIGNED NOT NULL,
    created_at          DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employe_id)       REFERENCES rh_employes(id)       ON DELETE RESTRICT,
    FOREIGN KEY (certification_id) REFERENCES rh_certifications(id) ON DELETE RESTRICT,
    FOREIGN KEY (session_id)       REFERENCES rh_formations_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogue de compétences
CREATE TABLE IF NOT EXISTS rh_competences (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50)  NOT NULL,
    libelle     VARCHAR(255) NOT NULL,
    description TEXT         NULL,
    categorie   ENUM('technique','comportementale','management','pedagogique','transversale') DEFAULT 'technique',
    actif       TINYINT(1)   DEFAULT 1,
    ordre       TINYINT      DEFAULT 0,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_competence_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compétences des employés
CREATE TABLE IF NOT EXISTS rh_employe_competences (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id      INT UNSIGNED NOT NULL,
    competence_id   INT UNSIGNED NOT NULL,
    niveau          ENUM('debutant','intermediaire','avance','expert') DEFAULT 'debutant',
    date_acquisition DATE         NULL,
    session_id      INT UNSIGNED NULL,
    valide_par      INT UNSIGNED NULL,
    valide_par_nom  VARCHAR(120) NULL,
    notes           TEXT         NULL,
    created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_emp_comp (employe_id, competence_id),
    FOREIGN KEY (employe_id)    REFERENCES rh_employes(id)    ON DELETE RESTRICT,
    FOREIGN KEY (competence_id) REFERENCES rh_competences(id) ON DELETE RESTRICT,
    FOREIGN KEY (session_id)    REFERENCES rh_formations_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lien formation ↔ compétences développées
CREATE TABLE IF NOT EXISTS rh_formation_competences (
    formation_id  INT UNSIGNED NOT NULL,
    competence_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (formation_id, competence_id),
    FOREIGN KEY (formation_id)  REFERENCES rh_formations_catalogue(id) ON DELETE CASCADE,
    FOREIGN KEY (competence_id) REFERENCES rh_competences(id)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed : compétences de base ───────────────────────────────────────────────

INSERT INTO rh_competences (code, libelle, categorie, ordre) VALUES
-- Techniques
('bureautique',      'Bureautique (Office, G-Suite)',          'technique',       1),
('gestion_projet',   'Gestion de projet',                     'technique',       2),
('comptabilite',     'Comptabilité & finances',               'technique',       3),
('informatique',     'Informatique & outils numériques',      'technique',       4),
('legislation_rh',   'Législation du travail',                'technique',       5),
-- Pédagogie
('pedagogie_active', 'Pédagogie active',                      'pedagogique',     6),
('numerique_educ',   'Numérique éducatif',                    'pedagogique',     7),
('evaluation_eleves','Évaluation des apprentissages',         'pedagogique',     8),
-- Comportementales
('communication',    'Communication professionnelle',          'comportementale', 9),
('leadership',       'Leadership & management d''équipe',     'comportementale', 10),
('gestion_conflit',  'Gestion des conflits',                  'comportementale', 11),
('service_client',   'Relation client & accueil',             'comportementale', 12),
-- Management
('management_rh',    'Management des ressources humaines',    'management',      13),
('pilotage_perf',    'Pilotage de la performance',            'management',      14),
-- Transversales
('securite',         'Sécurité & prévention',                 'transversale',    15),
('qualite',          'Qualité & amélioration continue',       'transversale',    16);

-- Seed : organisme interne
INSERT INTO rh_formations_organismes (code, nom, type, actif) VALUES
('INTERNE', 'Formation interne établissement', 'interne', 1);
