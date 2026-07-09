-- =============================================================================
-- Migration : vie_scolaire_002_presences.sql
-- Module    : Vie Scolaire V2 — Domaine Présences
-- Tables    : vs_appels, vs_presences, vs_presences_historique
-- =============================================================================

-- ── Sessions d'appel ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vs_appels (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    classe_id       INT UNSIGNED     NOT NULL,
    matiere_id      INT UNSIGNED     NULL,             -- NULL = appel journalier
    enseignant_id   INT UNSIGNED     NOT NULL,
    annee_scolaire  VARCHAR(9)       NOT NULL,
    date_appel      DATE             NOT NULL,
    heure_debut     TIME             NULL,
    heure_fin       TIME             NULL,
    type_appel      ENUM('journalier','seance') NOT NULL DEFAULT 'journalier',
    statut          ENUM('brouillon','valide')  NOT NULL DEFAULT 'brouillon',
    valide_par      INT UNSIGNED     NULL,
    valide_le       DATETIME         NULL,
    observation     TEXT             NULL,
    deleted_at      DATETIME         NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Un seul appel par classe + date + heure (empêche doublon)
    UNIQUE KEY uk_appel_classe_date_heure (classe_id, date_appel, heure_debut),
    KEY idx_appel_classe_date    (classe_id, date_appel),
    KEY idx_appel_enseignant     (enseignant_id, date_appel),
    KEY idx_appel_statut         (statut, date_appel),
    KEY idx_appel_annee          (annee_scolaire, classe_id),
    KEY idx_appel_soft_delete    (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Pointage individuel ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vs_presences (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appel_id        INT UNSIGNED     NOT NULL,
    eleve_id        INT UNSIGNED     NOT NULL,
    statut          ENUM('present','absent','retard','dispense','sortie_anticipee')
                                     NOT NULL DEFAULT 'present',
    heure_arrivee   TIME             NULL,             -- pour retards / sorties anticipées
    retard_minutes  SMALLINT UNSIGNED NULL,
    observation     TEXT             NULL,
    absence_id      INT UNSIGNED     NULL,             -- FK vs_absences si statut=absent
    saisie_par      INT UNSIGNED     NOT NULL,
    modifie_par     INT UNSIGNED     NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Un seul enregistrement par élève et par appel
    UNIQUE KEY uk_presence_appel_eleve (appel_id, eleve_id),
    KEY idx_presence_eleve_appel (eleve_id, appel_id),
    KEY idx_presence_statut      (statut),
    KEY idx_presence_absence     (absence_id),

    CONSTRAINT fk_presence_appel
        FOREIGN KEY (appel_id) REFERENCES vs_appels (id) ON DELETE RESTRICT,
    CONSTRAINT fk_presence_absence
        FOREIGN KEY (absence_id) REFERENCES vs_absences (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Historique des corrections ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vs_presences_historique (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    presence_id     INT UNSIGNED     NOT NULL,
    appel_id        INT UNSIGNED     NOT NULL,
    eleve_id        INT UNSIGNED     NOT NULL,
    ancien_statut   ENUM('present','absent','retard','dispense','sortie_anticipee') NOT NULL,
    nouveau_statut  ENUM('present','absent','retard','dispense','sortie_anticipee') NOT NULL,
    motif           TEXT             NULL,
    modifie_par     INT UNSIGNED     NOT NULL,
    modifie_le      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_hist_presence (presence_id),
    KEY idx_hist_appel    (appel_id),
    KEY idx_hist_eleve    (eleve_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
