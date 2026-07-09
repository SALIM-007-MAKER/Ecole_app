-- =============================================================================
-- Migration VS-004 — Domaine Discipline (Vie Scolaire V2)
-- Phase 5.4 — Coexiste avec les tables V1 (aucune modification)
-- =============================================================================

-- Catégories d'incidents (référentiel)
CREATE TABLE IF NOT EXISTS vs_discipline_categories (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            VARCHAR(50)  NOT NULL,
    nom             VARCHAR(100) NOT NULL,
    description     TEXT         NULL,
    gravite_defaut  ENUM('mineur','moyen','grave','tres_grave') NOT NULL DEFAULT 'mineur',
    actif           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_categorie_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeds — catégories standards
INSERT INTO vs_discipline_categories (code, nom, gravite_defaut) VALUES
    ('VIOLENCE',      'Violence physique',              'grave'),
    ('INCIVILITE',    'Incivilité / Irrespect',         'moyen'),
    ('FRAUDE',        'Fraude / Triche',                'grave'),
    ('PERTURBATION',  'Perturbation de cours',          'mineur'),
    ('ABSENTEISME',   'Absentéisme répété',             'moyen'),
    ('DEGRADATION',   'Dégradation de matériel',        'moyen'),
    ('HARCELEMENT',   'Harcèlement / Intimidation',     'tres_grave'),
    ('RETARDS',       'Retards répétés',                'mineur'),
    ('REFUS',         'Refus d\'obtempérer',            'moyen'),
    ('AUTRE',         'Autre comportement',             'mineur')
ON DUPLICATE KEY UPDATE nom = VALUES(nom);

-- Dossier disciplinaire (un par élève par année scolaire)
CREATE TABLE IF NOT EXISTS vs_dossiers_discipline (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    eleve_id        INT UNSIGNED NOT NULL,
    classe_id       INT UNSIGNED NOT NULL,
    annee_scolaire  VARCHAR(9)   NOT NULL,
    statut          ENUM('ouvert','en_cours','clos','appel') NOT NULL DEFAULT 'ouvert',
    nb_incidents    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    note_interne    TEXT         NULL,
    cree_par        INT UNSIGNED NOT NULL,
    clos_par        INT UNSIGNED NULL,
    clos_le         DATETIME     NULL,
    deleted_at      DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_dossier_eleve_annee (eleve_id, annee_scolaire),
    KEY idx_dossier_classe_annee (classe_id, annee_scolaire),
    KEY idx_dossier_statut (statut, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Incidents disciplinaires (liés à un dossier)
CREATE TABLE IF NOT EXISTS vs_incidents_discipline (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dossier_id      INT UNSIGNED NOT NULL,
    categorie_id    INT UNSIGNED NOT NULL,
    gravite         ENUM('mineur','moyen','grave','tres_grave') NOT NULL,
    description     TEXT         NOT NULL,
    date_incident   DATE         NOT NULL,
    heure_incident  TIME         NULL,
    lieu            VARCHAR(200) NULL,
    matiere_id      INT UNSIGNED NULL COMMENT 'Matière concernée, si applicable',
    signale_par     INT UNSIGNED NOT NULL,
    statut          ENUM('ouvert','traite','classe') NOT NULL DEFAULT 'ouvert',
    piece_jointe    VARCHAR(500) NULL,
    deleted_at      DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_incident_dossier  (dossier_id, statut),
    KEY idx_incident_date     (date_incident),
    KEY idx_incident_gravite  (gravite)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sanctions disciplinaires
CREATE TABLE IF NOT EXISTS vs_sanctions_discipline (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dossier_id      INT UNSIGNED NOT NULL,
    incident_id     INT UNSIGNED NULL COMMENT 'NULL = sanction portant sur le dossier global',
    type_sanction   ENUM('avertissement','blame','exclusion_cours','exclusion_temp',
                         'exclusion_def','travaux','conseil','mesure_educative')
                    NOT NULL,
    description     TEXT         NULL,
    motif           TEXT         NOT NULL,
    date_sanction   DATE         NOT NULL,
    date_debut      DATE         NULL,
    date_fin        DATE         NULL,
    duree_jours     SMALLINT UNSIGNED NULL,
    statut          ENUM('prononcee','effective','executee','levee','appelee')
                    NOT NULL DEFAULT 'prononcee',
    prononce_par    INT UNSIGNED NOT NULL,
    valide_par      INT UNSIGNED NULL,
    valide_le       DATETIME     NULL,
    deleted_at      DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sanction_dossier (dossier_id, statut),
    KEY idx_sanction_date    (date_sanction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique des changements de statut des sanctions
CREATE TABLE IF NOT EXISTS vs_sanctions_historique (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sanction_id     INT UNSIGNED NOT NULL,
    ancien_statut   VARCHAR(30)  NOT NULL,
    nouveau_statut  VARCHAR(30)  NOT NULL,
    motif           TEXT         NULL,
    modifie_par     INT UNSIGNED NOT NULL,
    modifie_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sh_sanction (sanction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appels de sanctions
CREATE TABLE IF NOT EXISTS vs_appels_discipline (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sanction_id     INT UNSIGNED NOT NULL,
    dossier_id      INT UNSIGNED NOT NULL,
    description     TEXT         NOT NULL,
    piece_jointe    VARCHAR(500) NULL,
    statut          ENUM('depose','examine','accepte','rejete') NOT NULL DEFAULT 'depose',
    depose_par      INT UNSIGNED NOT NULL,
    depose_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    examine_par     INT UNSIGNED NULL,
    examine_le      DATETIME     NULL,
    decision        TEXT         NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_appel_sanction (sanction_id),
    KEY idx_appel_dossier (dossier_id, statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
