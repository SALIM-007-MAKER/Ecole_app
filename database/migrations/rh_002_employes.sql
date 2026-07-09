-- =============================================================================
-- RH V2 — Migration 02 : Employés + Contacts d'urgence
-- Phase 6.2 — SCOLARIS V2
-- Exécuter APRÈS rh_001_organisation.sql
-- =============================================================================

-- Table 3 : Employés (entité centrale RH)
CREATE TABLE IF NOT EXISTS rh_employes (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- Liens V1 (nullables — zéro régression V1)
    user_id          INT UNSIGNED  NULL     COMMENT 'FK users.id — compte système, optionnel',
    professeur_id    INT UNSIGNED  NULL     COMMENT 'FK professeurs.id — uniquement enseignants V1',
    -- Identification RH
    matricule        VARCHAR(30)   NOT NULL UNIQUE COMMENT 'Format YYYY-NNNNN',
    type_personnel   ENUM('enseignant','administratif','support','direction','technique') NOT NULL,
    -- Identité
    nom              VARCHAR(100)  NOT NULL,
    prenom           VARCHAR(100)  NOT NULL,
    date_naissance   DATE          NULL,
    lieu_naissance   VARCHAR(150)  NULL,
    genre            ENUM('M','F','autre') NULL,
    nationalite      VARCHAR(80)   NOT NULL DEFAULT 'Algérienne',
    -- Pièce d'identité
    cni_numero       VARCHAR(30)   NULL,
    cni_expiration   DATE          NULL,
    -- Coordonnées
    adresse          TEXT          NULL,
    telephone        VARCHAR(20)   NULL,
    email_pro        VARCHAR(150)  NULL,
    email_perso      VARCHAR(150)  NULL,
    -- Photo
    photo            VARCHAR(255)  NULL,
    -- Situation professionnelle
    departement_id   INT UNSIGNED  NULL     COMMENT 'FK rh_departements.id',
    poste_id         INT UNSIGNED  NULL     COMMENT 'FK rh_postes.id',
    date_entree      DATE          NULL,
    date_sortie      DATE          NULL,
    motif_sortie     TEXT          NULL,
    -- Statut emploi
    statut           ENUM('actif','inactif','suspendu','conge','retraite','demissionnaire') NOT NULL DEFAULT 'actif',
    -- Informations complémentaires
    diplome          VARCHAR(200)  NULL,
    specialite       VARCHAR(150)  NULL,
    notes            TEXT          NULL     COMMENT 'Notes internes RH (confidentielles)',
    -- Audit
    cree_par_id      INT UNSIGNED  NULL,
    deleted_at       DATETIME      NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user          (user_id),
    KEY idx_professeur    (professeur_id),
    KEY idx_statut        (statut),
    KEY idx_type          (type_personnel),
    KEY idx_departement   (departement_id),
    KEY idx_deleted       (deleted_at),
    KEY idx_email_pro     (email_pro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 4 : Contacts d'urgence
CREATE TABLE IF NOT EXISTS rh_contacts_urgence (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id      INT UNSIGNED  NOT NULL,
    nom_complet     VARCHAR(200)  NOT NULL,
    lien            VARCHAR(80)   NOT NULL COMMENT 'ex: conjoint, père, mère, frère, ami',
    telephone       VARCHAR(20)   NOT NULL,
    telephone2      VARCHAR(20)   NULL,
    email           VARCHAR(150)  NULL,
    principal       TINYINT(1)    NOT NULL DEFAULT 0,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_employe (employe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
