-- ============================================================
-- Phase 6.3 — Domaine Enseignants V2
-- Migration : rh_003_enseignants
-- ============================================================
-- IMPORTANT : Ne jamais DROP TABLE sur une table existante.
-- Ces tables coexistent avec la V1 (professeurs, enseignements).
-- ============================================================

-- Profil pédagogique d'un enseignant (spécialisation de rh_employes)
CREATE TABLE IF NOT EXISTS rh_enseignants (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id              INT UNSIGNED        NOT NULL,
    statut_pedagogique      ENUM(
                                'titulaire',
                                'vacataire',
                                'remplacant',
                                'stagiaire',
                                'contractuel'
                            )                   NOT NULL DEFAULT 'titulaire',
    specialite_principale   VARCHAR(150)        NULL,
    charge_horaire_max      TINYINT UNSIGNED    NOT NULL DEFAULT 18
                            COMMENT 'Heures hebdomadaires maximales',
    charge_horaire_actuelle TINYINT UNSIGNED    NOT NULL DEFAULT 0
                            COMMENT 'Heures hebdomadaires actuelles (mis à jour lors des affectations)',
    date_debut_enseignement DATE                NULL,
    notes_pedagogiques      TEXT                NULL,
    deleted_at              DATETIME            NULL,
    created_at              DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_rh_ens_employe
        FOREIGN KEY (employe_id) REFERENCES rh_employes(id) ON DELETE RESTRICT,

    -- Un employé ne peut avoir qu'un seul profil enseignant
    UNIQUE KEY uq_enseignant_employe (employe_id),

    INDEX idx_ens_statut      (statut_pedagogique),
    INDEX idx_ens_deleted_at  (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Profils pédagogiques des enseignants — Phase 6.3';

-- Matières qu'un enseignant est habilité à enseigner
CREATE TABLE IF NOT EXISTS rh_enseignant_matieres (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enseignant_id INT UNSIGNED    NOT NULL,
    matiere_id    INT UNSIGNED    NOT NULL,
    niveaux       VARCHAR(255)    NULL
                  COMMENT 'Niveaux séparés par virgule : primaire,moyen,secondaire',
    priorite      TINYINT UNSIGNED NOT NULL DEFAULT 1
                  COMMENT '1 = matière principale, 2 = secondaire',
    actif         TINYINT(1)      NOT NULL DEFAULT 1,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_em_enseignant
        FOREIGN KEY (enseignant_id) REFERENCES rh_enseignants(id) ON DELETE CASCADE,
    CONSTRAINT fk_em_matiere
        FOREIGN KEY (matiere_id)   REFERENCES matieres(id)        ON DELETE RESTRICT,

    UNIQUE KEY uq_enseignant_matiere (enseignant_id, matiere_id),
    INDEX idx_em_matiere (matiere_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Habilitations matières des enseignants — Phase 6.3';

-- Qualifications, certifications et expériences (historisées)
CREATE TABLE IF NOT EXISTS rh_enseignant_qualifications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enseignant_id   INT UNSIGNED NOT NULL,
    type            ENUM(
                        'diplome',
                        'certification',
                        'formation',
                        'experience',
                        'autre'
                    )            NOT NULL DEFAULT 'diplome',
    intitule        VARCHAR(200) NOT NULL,
    organisme       VARCHAR(150) NULL,
    date_obtention  DATE         NULL,
    date_expiration DATE         NULL,
    document_path   VARCHAR(500) NULL
                    COMMENT 'Réservé pour le module Documents (Phase 6.9)',
    notes           TEXT         NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_eq_enseignant
        FOREIGN KEY (enseignant_id) REFERENCES rh_enseignants(id) ON DELETE CASCADE,

    INDEX idx_eq_type (type),
    INDEX idx_eq_expiration (date_expiration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Qualifications et certifications des enseignants — Phase 6.3';
