-- =============================================================================
-- RH V2 — Migration 05 : Contrats
-- Phase 6.5 — SCOLARIS V2
-- Exécuter APRÈS rh_002_employes.sql et rh_004_organisation.sql
-- =============================================================================

-- ─── 1. Table principale des contrats ────────────────────────────────────────

CREATE TABLE IF NOT EXISTS rh_contrats (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Liaisons obligatoires
    employe_id          INT UNSIGNED  NOT NULL,
    poste_id            INT UNSIGNED  NULL COMMENT 'FK rh_postes.id',
    departement_id      INT UNSIGNED  NULL COMMENT 'FK rh_departements.id',

    -- Identifiant lisible
    numero_contrat      VARCHAR(50)   NOT NULL UNIQUE COMMENT 'Format CNT-YYYY-NNNN',

    -- Caractéristiques
    type                ENUM('cdi','cdd','vacataire','stage','apprentissage','consultant','autre')
                        NOT NULL DEFAULT 'cdd',
    statut              ENUM('brouillon','actif','suspendu','expire','resilie')
                        NOT NULL DEFAULT 'brouillon',

    -- Dates
    date_debut          DATE          NOT NULL,
    date_fin            DATE          NULL   COMMENT 'NULL = durée indéterminée (CDI)',
    date_signature      DATE          NULL,

    -- Rémunération (prépare Phase 6.12 Paie)
    salaire_brut        DECIMAL(10,2) UNSIGNED NULL,
    devise              VARCHAR(3)    NOT NULL DEFAULT 'DZD',

    -- Texte libre
    motif_creation      TEXT          NULL,
    motif_fin           TEXT          NULL   COMMENT 'Renseigné à résiliation/expiration',
    notes               TEXT          NULL,

    -- Chaîne de renouvellement
    renouvelle_depuis   INT UNSIGNED  NULL   COMMENT 'FK rh_contrats.id — contrat précédent renouvelé',

    -- Traçabilité
    created_by          INT UNSIGNED  NULL   COMMENT 'FK users.id',
    updated_by          INT UNSIGNED  NULL   COMMENT 'FK users.id',

    -- Soft delete
    deleted_at          DATETIME      NULL,

    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_contrat_employe      FOREIGN KEY (employe_id)       REFERENCES rh_employes(id)     ON DELETE RESTRICT,
    CONSTRAINT fk_contrat_poste        FOREIGN KEY (poste_id)         REFERENCES rh_postes(id)       ON DELETE SET NULL,
    CONSTRAINT fk_contrat_departement  FOREIGN KEY (departement_id)   REFERENCES rh_departements(id) ON DELETE SET NULL,
    CONSTRAINT fk_contrat_renouvelle   FOREIGN KEY (renouvelle_depuis) REFERENCES rh_contrats(id)    ON DELETE SET NULL,

    KEY idx_contrat_employe   (employe_id),
    KEY idx_contrat_statut    (statut),
    KEY idx_contrat_type      (type),
    KEY idx_contrat_date_fin  (date_fin),
    KEY idx_contrat_deleted   (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. Table des avenants ────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS rh_contrat_avenants (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contrat_id      INT UNSIGNED  NOT NULL,
    numero          TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Numéro séquentiel par contrat',
    type            ENUM('salaire','poste','duree','horaire','teletravail','autre')
                    NOT NULL DEFAULT 'autre',
    objet           VARCHAR(200)  NOT NULL,
    description     TEXT,
    date_effet      DATE          NOT NULL,
    ancienne_valeur JSON          NULL,
    nouvelle_valeur JSON          NULL,
    created_by      INT UNSIGNED  NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_avenant_contrat FOREIGN KEY (contrat_id) REFERENCES rh_contrats(id) ON DELETE CASCADE,
    UNIQUE KEY uq_avenant_numero (contrat_id, numero),
    KEY idx_avenant_contrat    (contrat_id),
    KEY idx_avenant_date_effet (date_effet)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. Table des séquences par année (génération numéros contrats) ────────────

CREATE TABLE IF NOT EXISTS rh_contrat_sequences (
    annee   YEAR         NOT NULL,
    seq     INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialiser l'année courante
INSERT INTO rh_contrat_sequences (annee, seq) VALUES (YEAR(NOW()), 0)
ON DUPLICATE KEY UPDATE annee = annee;
