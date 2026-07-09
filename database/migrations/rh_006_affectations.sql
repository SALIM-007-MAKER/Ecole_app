-- ============================================================
-- Phase 6.6 — Domaine Affectations RH V2
-- Date : 2026-07-02
-- Règle : jamais DROP TABLE sur table existante
-- ============================================================

-- ── Table principale des affectations ────────────────────────
CREATE TABLE IF NOT EXISTS rh_affectations (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id     INT UNSIGNED NOT NULL,
    contrat_id     INT UNSIGNED NULL COMMENT 'Lien contrat actif — SET NULL si contrat expiré',
    poste_id       INT UNSIGNED NULL,
    departement_id INT UNSIGNED NULL,
    service_id     INT UNSIGNED NULL,
    responsable_id INT UNSIGNED NULL COMMENT 'Manager direct (autre employé)',

    -- Classification
    type   ENUM('principale','secondaire','temporaire') NOT NULL DEFAULT 'principale',
    statut ENUM('active','terminee','suspendue')        NOT NULL DEFAULT 'active',

    -- Période
    date_debut DATE NOT NULL,
    date_fin   DATE NULL COMMENT 'NULL = durée indéterminée',

    -- Préparation V3 multi-établissement
    etablissement_id INT UNSIGNED NULL COMMENT 'Réservé réseau V3 — NULL en V2',

    notes       TEXT NULL,
    created_by  INT UNSIGNED NOT NULL,
    updated_by  INT UNSIGNED NOT NULL,
    deleted_at  TIMESTAMP NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_affect_employe     FOREIGN KEY (employe_id)     REFERENCES rh_employes(id)     ON DELETE RESTRICT,
    CONSTRAINT fk_affect_contrat     FOREIGN KEY (contrat_id)     REFERENCES rh_contrats(id)     ON DELETE SET NULL,
    CONSTRAINT fk_affect_poste       FOREIGN KEY (poste_id)       REFERENCES rh_postes(id)       ON DELETE SET NULL,
    CONSTRAINT fk_affect_departement FOREIGN KEY (departement_id) REFERENCES rh_departements(id) ON DELETE SET NULL,
    CONSTRAINT fk_affect_service     FOREIGN KEY (service_id)     REFERENCES rh_services(id)     ON DELETE SET NULL,
    CONSTRAINT fk_affect_responsable FOREIGN KEY (responsable_id) REFERENCES rh_employes(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Affectations matières / classes (enseignants) ────────────
-- NOTE : matiere_id et classe_id pointent vers des tables V1 (matieres, classes)
--        FKs non déclarées pour préserver compatibilité V1/V2 (tables gérées hors du périmètre RH).
CREATE TABLE IF NOT EXISTS rh_affectation_matieres (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    affectation_id INT UNSIGNED NOT NULL,

    -- Références V1 (soft references — pas de FK pour compatibilité V1/V2)
    matiere_id  INT UNSIGNED NULL COMMENT 'Réf. table matieres V1',
    classe_id   INT UNSIGNED NULL COMMENT 'Réf. table classes V1',
    niveau      VARCHAR(50)  NULL COMMENT 'Ex : 6ème, 5ème, Terminale S',

    heures_hebdo DECIMAL(4,1) UNSIGNED NULL COMMENT 'Charge hebdomadaire en heures',

    date_debut DATE NOT NULL,
    date_fin   DATE NULL,

    notes      VARCHAR(500) NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_affmat_affectation FOREIGN KEY (affectation_id) REFERENCES rh_affectations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Historique des changements d'affectation ─────────────────
CREATE TABLE IF NOT EXISTS rh_historique_affectations (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    affectation_id INT UNSIGNED NOT NULL,

    type_changement ENUM(
        'creation',
        'modification',
        'transfert',
        'suspension',
        'reactivation',
        'cloture',
        'archivage'
    ) NOT NULL,

    ancienne_valeur JSON NULL,
    nouvelle_valeur JSON NULL,
    motif           VARCHAR(500) NULL,

    -- Dénormalisé pour préserver l'historique même si l'employé est supprimé
    modifie_par      INT UNSIGNED NOT NULL,
    modifie_par_nom  VARCHAR(100) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_haffect_affectation FOREIGN KEY (affectation_id) REFERENCES rh_affectations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Index de performance ──────────────────────────────────────
CREATE INDEX idx_affect_employe     ON rh_affectations (employe_id);
CREATE INDEX idx_affect_poste       ON rh_affectations (poste_id);
CREATE INDEX idx_affect_departement ON rh_affectations (departement_id);
CREATE INDEX idx_affect_statut      ON rh_affectations (statut);
CREATE INDEX idx_affect_deleted     ON rh_affectations (deleted_at);
CREATE INDEX idx_affmat_affect      ON rh_affectation_matieres (affectation_id);
CREATE INDEX idx_affhist_affect     ON rh_historique_affectations (affectation_id);
