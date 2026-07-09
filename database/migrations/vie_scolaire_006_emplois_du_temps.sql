-- ============================================================
-- Migration : Domaine Emplois du Temps — Vie Scolaire V2
-- Préfixe   : vs_edt_
-- Tables    : 6 tables
-- ============================================================

-- ── Plages horaires de référence ─────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS vs_edt_plages_horaires (
    id          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    libelle     VARCHAR(50)      NOT NULL,
    heure_debut TIME             NOT NULL,
    heure_fin   TIME             NOT NULL,
    ordre       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    actif       TINYINT(1)       NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO vs_edt_plages_horaires (libelle, heure_debut, heure_fin, ordre) VALUES
    ('7h30 – 8h30',   '07:30:00', '08:30:00', 1),
    ('8h30 – 9h30',   '08:30:00', '09:30:00', 2),
    ('9h30 – 10h30',  '09:30:00', '10:30:00', 3),
    ('10h30 – 11h30', '10:30:00', '11:30:00', 4),
    ('11h30 – 12h30', '11:30:00', '12:30:00', 5),
    ('14h00 – 15h00', '14:00:00', '15:00:00', 6),
    ('15h00 – 16h00', '15:00:00', '16:00:00', 7),
    ('16h00 – 17h00', '16:00:00', '17:00:00', 8),
    ('17h00 – 18h00', '17:00:00', '18:00:00', 9);

-- ── Salles ────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS vs_edt_salles (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom       VARCHAR(100) NOT NULL,
    code      VARCHAR(20)  NOT NULL UNIQUE,
    capacite  SMALLINT UNSIGNED DEFAULT 30,
    type      ENUM('cours','labo','sport','informatique','autre') NOT NULL DEFAULT 'cours',
    actif     TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Emplois du temps (en-tête) ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS vs_emplois_du_temps (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    classe_id       INT UNSIGNED NOT NULL,
    annee_scolaire  VARCHAR(9)   NOT NULL,
    periode_id      INT UNSIGNED NULL,
    semaine_type    VARCHAR(20)  NOT NULL DEFAULT 'standard',
    version         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    statut          ENUM('brouillon','publie','archive') NOT NULL DEFAULT 'brouillon',
    publie_par      INT UNSIGNED NULL,
    publie_le       TIMESTAMP    NULL,
    cree_par        INT UNSIGNED NOT NULL,
    deleted_at      TIMESTAMP    NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_edt_classe  (classe_id, annee_scolaire),
    INDEX idx_edt_statut  (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Créneaux ─────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS vs_edt_creneaux (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    emploi_du_temps_id  INT UNSIGNED     NOT NULL,
    classe_id           INT UNSIGNED     NOT NULL,
    matiere_id          INT UNSIGNED     NOT NULL,
    enseignant_id       INT UNSIGNED     NOT NULL,
    salle_id            INT UNSIGNED     NULL,
    jour                TINYINT UNSIGNED NOT NULL COMMENT '1=Lundi 6=Samedi',
    plage_id            TINYINT UNSIGNED NOT NULL,
    annee_scolaire      VARCHAR(9)       NOT NULL,
    type_cours          ENUM('cours','td','tp','sport','autre') NOT NULL DEFAULT 'cours',
    couleur             VARCHAR(7)       NULL,
    note                TEXT             NULL,
    deleted_at          TIMESTAMP        NULL,
    created_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_creneau_edt          (emploi_du_temps_id),
    INDEX idx_creneau_enseignant   (enseignant_id, jour, plage_id, annee_scolaire),
    INDEX idx_creneau_salle        (salle_id, jour, plage_id, annee_scolaire),
    INDEX idx_creneau_classe       (classe_id, jour, plage_id, annee_scolaire)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Versions (snapshots) ──────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS vs_edt_versions (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    emploi_du_temps_id  INT UNSIGNED NOT NULL,
    version             SMALLINT UNSIGNED NOT NULL,
    snapshot            JSON         NOT NULL,
    motif               VARCHAR(500) NULL,
    modifie_par         INT UNSIGNED NOT NULL,
    modifie_le          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_version_edt (emploi_du_temps_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Remplacements ponctuels ───────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS vs_edt_remplacements (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    creneau_id              INT UNSIGNED NOT NULL,
    date_remplacement       DATE         NOT NULL,
    enseignant_absent_id    INT UNSIGNED NOT NULL,
    remplacant_id           INT UNSIGNED NULL,
    matiere_remplacement_id INT UNSIGNED NULL,
    salle_remplacement_id   INT UNSIGNED NULL,
    statut                  ENUM('planifie','confirme','annule') NOT NULL DEFAULT 'planifie',
    motif_absence           VARCHAR(500) NULL,
    cree_par                INT UNSIGNED NOT NULL,
    deleted_at              TIMESTAMP    NULL,
    created_at              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_remplacement (creneau_id, date_remplacement),
    INDEX idx_remplacement_date (date_remplacement),
    INDEX idx_remplacement_absent (enseignant_absent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
