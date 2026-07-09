-- ─────────────────────────────────────────────────────────────────────────────
-- Phase 6.8 — Domaine Congés & Absences du Personnel RH V2
-- ─────────────────────────────────────────────────────────────────────────────
-- Tables : rh_types_conges, rh_soldes_conges, rh_conges,
--          rh_conges_historique, rh_conges_justificatifs
-- ─────────────────────────────────────────────────────────────────────────────

-- Table 1 : Référentiel des types de congés
CREATE TABLE IF NOT EXISTS rh_types_conges (
    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    code                    VARCHAR(30)     NOT NULL,
    libelle                 VARCHAR(100)    NOT NULL,
    description             TEXT            NULL,
    duree_max_jours         SMALLINT UNSIGNED NULL     COMMENT 'NULL = illimité',
    is_paye                 TINYINT(1)      NOT NULL DEFAULT 1,
    necessite_justificatif  TINYINT(1)      NOT NULL DEFAULT 0,
    necessite_approbation   TINYINT(1)      NOT NULL DEFAULT 1,
    debit_solde             TINYINT(1)      NOT NULL DEFAULT 1  COMMENT '0 = ne décompte pas le solde annuel',
    actif                   TINYINT(1)      NOT NULL DEFAULT 1,
    ordre                   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME        NULL     ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2 : Soldes de congés par employé / type / année civile
CREATE TABLE IF NOT EXISTS rh_soldes_conges (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    employe_id          INT UNSIGNED    NOT NULL,
    type_conge_id       INT UNSIGNED    NOT NULL,
    annee               YEAR            NOT NULL,
    solde_initial       DECIMAL(5,1)    NOT NULL DEFAULT 0.0,
    solde_pris          DECIMAL(5,1)    NOT NULL DEFAULT 0.0,
    solde_en_attente    DECIMAL(5,1)    NOT NULL DEFAULT 0.0,
    updated_at          DATETIME        NULL     ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_employe_type_annee (employe_id, type_conge_id, annee),
    CONSTRAINT fk_solde_employe    FOREIGN KEY (employe_id)    REFERENCES rh_employes(id)     ON DELETE RESTRICT,
    CONSTRAINT fk_solde_type_conge FOREIGN KEY (type_conge_id) REFERENCES rh_types_conges(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 3 : Demandes de congés / absences
CREATE TABLE IF NOT EXISTS rh_conges (
    id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    employe_id              INT UNSIGNED    NOT NULL,
    type_conge_id           INT UNSIGNED    NOT NULL,
    contrat_id              INT UNSIGNED    NULL,
    affectation_id          INT UNSIGNED    NULL,
    date_debut              DATE            NOT NULL,
    date_fin                DATE            NOT NULL,
    duree_jours             DECIMAL(4,1)    NOT NULL DEFAULT 0.0 COMMENT 'Jours ouvrables calculés',
    duree_heures            DECIMAL(5,1)    NULL                 COMMENT 'Pour permissions < 1 journée',
    statut                  ENUM('brouillon','soumis','approuve','rejete','annule','en_cours','termine')
                                            NOT NULL DEFAULT 'brouillon',
    motif                   TEXT            NULL,
    motif_rejet             TEXT            NULL,
    motif_annulation        TEXT            NULL,
    approuve_par            INT UNSIGNED    NULL,
    approuve_par_nom        VARCHAR(100)    NULL,
    date_approbation        DATETIME        NULL,
    annule_par              INT UNSIGNED    NULL,
    annule_par_nom          VARCHAR(100)    NULL,
    date_annulation         DATETIME        NULL,
    date_retour_effectif    DATE            NULL,
    commentaire_retour      TEXT            NULL,
    impact_paie             TINYINT(1)      NOT NULL DEFAULT 0   COMMENT 'Préparation Phase 6.12',
    created_by              INT UNSIGNED    NOT NULL,
    updated_by              INT UNSIGNED    NOT NULL,
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME        NULL     ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              DATETIME        NULL,
    PRIMARY KEY (id),
    KEY idx_employe   (employe_id),
    KEY idx_dates     (date_debut, date_fin),
    KEY idx_statut    (statut),
    KEY idx_deleted   (deleted_at),
    CONSTRAINT fk_conge_employe     FOREIGN KEY (employe_id)     REFERENCES rh_employes(id)     ON DELETE RESTRICT,
    CONSTRAINT fk_conge_type        FOREIGN KEY (type_conge_id)  REFERENCES rh_types_conges(id) ON DELETE RESTRICT,
    CONSTRAINT fk_conge_contrat     FOREIGN KEY (contrat_id)     REFERENCES rh_contrats(id)     ON DELETE SET NULL,
    CONSTRAINT fk_conge_affectation FOREIGN KEY (affectation_id) REFERENCES rh_affectations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 4 : Historique complet des transitions de statut
CREATE TABLE IF NOT EXISTS rh_conges_historique (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    conge_id            INT UNSIGNED    NOT NULL,
    statut_avant        VARCHAR(20)     NOT NULL,
    statut_apres        VARCHAR(20)     NOT NULL,
    action              VARCHAR(50)     NOT NULL,
    commentaire         TEXT            NULL,
    effectue_par        INT UNSIGNED    NOT NULL,
    effectue_par_nom    VARCHAR(100)    NOT NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conge (conge_id),
    CONSTRAINT fk_hist_conge FOREIGN KEY (conge_id) REFERENCES rh_conges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 5 : Pièces justificatives
CREATE TABLE IF NOT EXISTS rh_conges_justificatifs (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    conge_id        INT UNSIGNED    NOT NULL,
    nom_fichier     VARCHAR(255)    NOT NULL,
    nom_original    VARCHAR(255)    NOT NULL,
    type_mime       VARCHAR(100)    NULL,
    taille_octets   INT UNSIGNED    NULL,
    uploaded_by     INT UNSIGNED    NOT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conge (conge_id),
    CONSTRAINT fk_just_conge FOREIGN KEY (conge_id) REFERENCES rh_conges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- Seed : Types de congés par défaut
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO rh_types_conges
    (code, libelle, duree_max_jours, is_paye, necessite_justificatif, necessite_approbation, debit_solde, ordre)
VALUES
    ('annuel',              'Congé annuel',             30,   1, 0, 1, 1, 1),
    ('maladie',             'Congé maladie',            NULL, 1, 1, 0, 1, 2),
    ('maternite',           'Congé maternité',          98,   1, 1, 0, 0, 3),
    ('paternite',           'Congé paternité',          10,   1, 0, 1, 0, 4),
    ('exceptionnel',        'Congé exceptionnel',       5,    1, 1, 1, 1, 5),
    ('permission',          'Permission d''absence',    1,    1, 0, 1, 1, 6),
    ('mission',             'Mission',                  NULL, 1, 0, 1, 0, 7),
    ('recuperation',        'Récupération',             NULL, 1, 0, 1, 1, 8),
    ('absence_injustifiee', 'Absence injustifiée',      NULL, 0, 0, 0, 1, 9)
ON DUPLICATE KEY UPDATE
    libelle         = VALUES(libelle),
    duree_max_jours = VALUES(duree_max_jours),
    is_paye         = VALUES(is_paye),
    debit_solde     = VALUES(debit_solde);
