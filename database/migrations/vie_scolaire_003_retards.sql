-- =============================================================================
-- Migration VS-003 — Domaine Retards (Vie Scolaire V2)
-- Phase 5.3 — Coexiste avec les tables V1 (aucune modification)
-- =============================================================================

-- Table principale des retards
CREATE TABLE IF NOT EXISTS vs_retards (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    eleve_id        INT UNSIGNED    NOT NULL,
    classe_id       INT UNSIGNED    NOT NULL,
    annee_scolaire  VARCHAR(9)      NOT NULL,
    date_retard     DATE            NOT NULL,
    heure_prevue    TIME            NULL     COMMENT 'Heure de début attendue (peut être NULL si appel journalier sans heure)',
    heure_arrivee   TIME            NOT NULL COMMENT 'Heure réelle d\'arrivée',
    duree_minutes   SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Calculé : heure_arrivee - heure_prevue en minutes',
    statut          ENUM('non_justifie','en_attente','justifie','refuse') NOT NULL DEFAULT 'non_justifie',
    appel_id        INT UNSIGNED    NULL     COMMENT 'FK vs_appels — NULL si saisie manuelle',
    presence_id     INT UNSIGNED    NULL     COMMENT 'FK vs_presences — NULL si saisie manuelle',
    saisie_par      INT UNSIGNED    NOT NULL,
    observation     TEXT            NULL,
    deleted_at      DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_retard_eleve_date  (eleve_id, date_retard),
    KEY idx_retard_classe_date (classe_id, date_retard),
    KEY idx_retard_statut      (statut, deleted_at),
    KEY idx_retard_annee       (annee_scolaire, classe_id),
    KEY idx_retard_presence    (presence_id),
    KEY idx_retard_soft_delete (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Justifications des retards
CREATE TABLE IF NOT EXISTS vs_justifications_retards (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    retard_id            INT UNSIGNED NOT NULL,
    motif_description    TEXT         NULL     COMMENT 'Motif textuel libre (ou concat avec motif_code)',
    fichier_justificatif VARCHAR(500) NULL,
    statut               ENUM('en_attente','validee','refusee') NOT NULL DEFAULT 'en_attente',
    soumis_par           INT UNSIGNED NOT NULL,
    soumis_le            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valide_par           INT UNSIGNED NULL,
    valide_le            DATETIME     NULL,
    motif_refus          TEXT         NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_justif_retard (retard_id),
    KEY idx_justif_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
