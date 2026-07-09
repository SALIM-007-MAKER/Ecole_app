-- ============================================================
-- Vie Scolaire V2 — Migration 001 : Domaine Absences
-- Phase 5.1 — 2026-07-01
-- Préfixe tables : vs_
-- ============================================================

-- Référentiel des motifs d'absence
CREATE TABLE IF NOT EXISTS vs_motifs_absence (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                    VARCHAR(20)  NOT NULL UNIQUE,
    libelle                 VARCHAR(100) NOT NULL,
    categorie               ENUM('maladie','familial','transport','scolaire','autre') NOT NULL,
    necessite_justificatif  BOOLEAN      NOT NULL DEFAULT TRUE,
    compte_comme_absence    BOOLEAN      NOT NULL DEFAULT TRUE,
    actif                   BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données initiales motifs
INSERT INTO vs_motifs_absence (code, libelle, categorie, necessite_justificatif, compte_comme_absence) VALUES
    ('MALADIE',      'Maladie',               'maladie',   TRUE,  TRUE),
    ('MEDECIN',      'Rendez-vous médical',   'maladie',   TRUE,  TRUE),
    ('DEUIL',        'Deuil familial',        'familial',  FALSE, FALSE),
    ('FAMILLE',      'Raison familiale',      'familial',  TRUE,  TRUE),
    ('TRANSPORT',    'Problème de transport', 'transport', FALSE, TRUE),
    ('ACT_SCOLAIRE', 'Activité scolaire',     'scolaire',  FALSE, FALSE),
    ('AUTRE',        'Autre motif',           'autre',     TRUE,  TRUE)
ON DUPLICATE KEY UPDATE libelle = VALUES(libelle);

-- Table principale des absences
CREATE TABLE IF NOT EXISTS vs_absences (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    eleve_id        INT UNSIGNED NOT NULL,
    classe_id       INT UNSIGNED NOT NULL,
    annee_scolaire  VARCHAR(9)   NOT NULL,
    date_absence    DATE         NOT NULL,
    heure_debut     TIME         NULL,
    heure_fin       TIME         NULL,
    duree_heures    DECIMAL(4,2) NULL         COMMENT 'Calculé ou saisi manuellement',
    type            ENUM('absence','retard','dispense') NOT NULL DEFAULT 'absence',
    statut          ENUM('non_justifiee','en_attente','justifiee','refusee') NOT NULL DEFAULT 'non_justifiee',
    saisie_par      INT UNSIGNED NOT NULL,
    observation     TEXT         NULL,
    deleted_at      DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_abs_eleve_date   (eleve_id,  date_absence),
    KEY idx_abs_classe_date  (classe_id, date_absence),
    KEY idx_abs_statut       (statut,    date_absence),
    KEY idx_abs_annee        (annee_scolaire, classe_id),
    KEY idx_abs_soft_delete  (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Justifications d'absence
CREATE TABLE IF NOT EXISTS vs_justifications_absences (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    absence_id              INT UNSIGNED NOT NULL,
    motif_id                INT UNSIGNED NULL,
    description             TEXT         NULL,
    fichier_justificatif    VARCHAR(500) NULL         COMMENT 'Chemin relatif via UploadService',
    statut                  ENUM('en_attente','validee','refusee') NOT NULL DEFAULT 'en_attente',
    soumis_par              INT UNSIGNED NOT NULL,
    soumis_le               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valide_par              INT UNSIGNED NULL,
    valide_le               DATETIME     NULL,
    motif_refus             TEXT         NULL,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_justif_absence (absence_id),
    KEY idx_justif_statut        (statut, soumis_le),
    KEY idx_justif_valide_par    (valide_par)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contraintes de clés étrangères (après création des tables)
ALTER TABLE vs_justifications_absences
    ADD CONSTRAINT fk_justif_absence
        FOREIGN KEY (absence_id) REFERENCES vs_absences (id) ON DELETE RESTRICT;

ALTER TABLE vs_justifications_absences
    ADD CONSTRAINT fk_justif_motif
        FOREIGN KEY (motif_id) REFERENCES vs_motifs_absence (id) ON DELETE SET NULL;
