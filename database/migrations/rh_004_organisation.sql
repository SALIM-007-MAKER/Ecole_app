-- =============================================================================
-- RH V2 — Migration 04 : Structure Organisationnelle (Extension + Nouveaux domaines)
-- Phase 6.4 — SCOLARIS V2
-- Exécuter APRÈS rh_001_organisation.sql (rh_departements + rh_postes déjà créés)
-- =============================================================================

-- ─── 1. Extension rh_departements — soft delete + métadonnées ───────────────

ALTER TABLE rh_departements
    ADD COLUMN deleted_at       DATETIME NULL DEFAULT NULL AFTER updated_at,
    ADD COLUMN budget_centre    VARCHAR(30) NULL COMMENT 'Code centre de coût (Finance V2)' AFTER description,
    ADD COLUMN ordre_affichage  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Tri visuel organigramme' AFTER actif;

-- ─── 2. Extension rh_postes — soft delete + capacité + service ───────────────

ALTER TABLE rh_postes
    ADD COLUMN deleted_at       DATETIME NULL DEFAULT NULL AFTER updated_at,
    ADD COLUMN nb_occupants_max TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Nb maximal de titulaires simultanés' AFTER niveau,
    ADD COLUMN service_id       INT UNSIGNED NULL COMMENT 'FK rh_services.id — optionnel' AFTER departement_id;

-- ─── 3. Table rh_services — subdivision d'un département ────────────────────

CREATE TABLE IF NOT EXISTS rh_services (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    departement_id  INT UNSIGNED  NOT NULL,
    nom             VARCHAR(120)  NOT NULL,
    code            VARCHAR(30)   NOT NULL UNIQUE,
    description     TEXT,
    responsable_id  INT UNSIGNED  NULL COMMENT 'FK rh_employes.id',
    ordre_affichage TINYINT UNSIGNED NOT NULL DEFAULT 0,
    actif           TINYINT(1)    NOT NULL DEFAULT 1,
    deleted_at      DATETIME      NULL DEFAULT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_service_departement FOREIGN KEY (departement_id)
        REFERENCES rh_departements(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    KEY idx_service_departement (departement_id),
    KEY idx_service_actif       (actif),
    KEY idx_service_deleted     (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. Table rh_fonctions — fonctions transversales indépendantes ────────────

CREATE TABLE IF NOT EXISTS rh_fonctions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(120)  NOT NULL,
    code            VARCHAR(30)   NOT NULL UNIQUE,
    description     TEXT,
    niveau          TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=opérationnel 2=encadrement 3=direction',
    perimetre       ENUM('etablissement','departement','service','transversal') NOT NULL DEFAULT 'transversal',
    actif           TINYINT(1)    NOT NULL DEFAULT 1,
    deleted_at      DATETIME      NULL DEFAULT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fonction_actif   (actif),
    KEY idx_fonction_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 5. Table rh_employe_fonctions — affectation d'une fonction à un employé ─

CREATE TABLE IF NOT EXISTS rh_employe_fonctions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id      INT UNSIGNED  NOT NULL,
    fonction_id     INT UNSIGNED  NOT NULL,
    date_debut      DATE          NOT NULL,
    date_fin        DATE          NULL,
    principal       TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 = fonction principale',
    notes           TEXT,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ef_employe  FOREIGN KEY (employe_id)  REFERENCES rh_employes(id)  ON DELETE CASCADE,
    CONSTRAINT fk_ef_fonction FOREIGN KEY (fonction_id) REFERENCES rh_fonctions(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_employe_fonction_debut (employe_id, fonction_id, date_debut),
    KEY idx_ef_employe  (employe_id),
    KEY idx_ef_fonction (fonction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 6. Table rh_historique_org — journal des modifications structurelles ─────

CREATE TABLE IF NOT EXISTS rh_historique_org (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_entite     ENUM('departement','service','poste','fonction','affectation') NOT NULL,
    entite_id       INT UNSIGNED  NOT NULL,
    action          ENUM('creation','modification','archivage','restauration','affectation') NOT NULL,
    ancienne_valeur JSON          NULL,
    nouvelle_valeur JSON          NULL,
    modifie_par_id  INT UNSIGNED  NULL COMMENT 'FK users.id',
    modifie_par_nom VARCHAR(100)  NULL COMMENT 'Dénormalisé pour audit pérenne',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_horg_entite     (type_entite, entite_id),
    KEY idx_horg_action     (action),
    KEY idx_horg_created    (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 7. Ajout FK poste → service (après création rh_services) ─────────────────

ALTER TABLE rh_postes
    ADD CONSTRAINT fk_poste_service FOREIGN KEY (service_id)
        REFERENCES rh_services(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- ─── Seeds rh_services — services initiaux rattachés aux départements ─────────

INSERT INTO rh_services (departement_id, nom, code, description) VALUES
    (1, 'Direction Pédagogique',    'DIR_PED',  'Supervision pédagogique globale'),
    (2, 'Scolarité',                'SCOL',     'Inscriptions, dossiers élèves, suivi'),
    (2, 'Accueil & Communication',  'ACCUEIL',  'Réception, correspondance, affichage'),
    (4, 'Comptabilité Générale',    'COMPTA_G', 'Tenue des comptes, bilans, clôtures'),
    (4, 'Recouvrement',             'RECOUVR',  'Suivi paiements, impayés, relances'),
    (6, 'Maintenance',              'MAINT',    'Entretien des locaux et équipements'),
    (6, 'Informatique',             'INFO',     'Parc informatique, réseau, logiciels')
ON DUPLICATE KEY UPDATE nom = VALUES(nom);

-- ─── Seeds rh_fonctions — fonctions institutionnelles fondamentales ────────────

INSERT INTO rh_fonctions (nom, code, niveau, perimetre) VALUES
    ('Chef de département',     'CHEF_DEPT',    3, 'departement'),
    ('Responsable de service',  'RESP_SVC',     2, 'service'),
    ('Coordinateur pédagogique','COORD_PED',    2, 'transversal'),
    ('Tuteur',                  'TUTEUR',       1, 'transversal'),
    ('Délégué du personnel',    'DEL_PERS',     2, 'etablissement'),
    ('Référent sécurité',       'REF_SEC',      2, 'etablissement'),
    ('Référent numérique',      'REF_NUM',      1, 'transversal')
ON DUPLICATE KEY UPDATE nom = VALUES(nom);
