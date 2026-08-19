-- ============================================================
-- Phase 6.9 — Domaine Évaluations RH V2
-- Tables : rh_campagnes_evaluation, rh_criteres_evaluation,
--          rh_campagne_criteres, rh_evaluations,
--          rh_evaluation_criteres, rh_evaluation_historique,
--          rh_plans_developpement
-- ============================================================

-- Campagnes d'évaluation
CREATE TABLE IF NOT EXISTS rh_campagnes_evaluation (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                    VARCHAR(50)  NOT NULL,
    libelle                 VARCHAR(255) NOT NULL,
    description             TEXT         NULL,
    annee                   YEAR         NOT NULL,
    periode                 ENUM('S1','S2','annuelle','trimestrielle','ad_hoc') DEFAULT 'annuelle',
    date_debut              DATE         NOT NULL,
    date_fin                DATE         NOT NULL,
    date_limite_auto_eval   DATE         NULL,
    date_limite_eval        DATE         NULL,
    statut                  ENUM('brouillon','active','cloturee','archivee') DEFAULT 'brouillon',
    created_by              INT UNSIGNED NOT NULL,
    created_at              DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              DATETIME     NULL,
    UNIQUE KEY uq_campagne_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Référentiel de critères
CREATE TABLE IF NOT EXISTS rh_criteres_evaluation (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50)  NOT NULL,
    libelle     VARCHAR(255) NOT NULL,
    description TEXT         NULL,
    categorie   ENUM('competence','comportement','resultat','objectif') DEFAULT 'competence',
    type_employe ENUM('tous','enseignant','administratif','comptable','direction') DEFAULT 'tous',
    poids       DECIMAL(5,2) DEFAULT 1.00,
    note_min    TINYINT      DEFAULT 0,
    note_max    TINYINT      DEFAULT 5,
    actif       TINYINT(1)   DEFAULT 1,
    ordre       TINYINT      DEFAULT 0,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_critere_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Critères associés à une campagne
CREATE TABLE IF NOT EXISTS rh_campagne_criteres (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campagne_id    INT UNSIGNED NOT NULL,
    critere_id     INT UNSIGNED NOT NULL,
    poids_override DECIMAL(5,2) NULL,
    obligatoire    TINYINT(1)   DEFAULT 1,
    ordre          TINYINT      DEFAULT 0,
    UNIQUE KEY uq_cc (campagne_id, critere_id),
    FOREIGN KEY (campagne_id) REFERENCES rh_campagnes_evaluation(id) ON DELETE CASCADE,
    FOREIGN KEY (critere_id)  REFERENCES rh_criteres_evaluation(id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Évaluations (une par employé par campagne)
CREATE TABLE IF NOT EXISTS rh_evaluations (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campagne_id             INT UNSIGNED NOT NULL,
    employe_id              INT UNSIGNED NOT NULL,
    affectation_id          INT UNSIGNED NULL,
    evaluateur_id           INT UNSIGNED NULL,
    evaluateur_nom          VARCHAR(120) NULL,
    statut                  ENUM('brouillon','en_auto_evaluation','en_evaluation','soumise','validee','publiee','archivee') DEFAULT 'brouillon',
    score_auto_eval         DECIMAL(5,2) NULL,
    score_evaluateur        DECIMAL(5,2) NULL,
    score_final             DECIMAL(5,2) NULL,
    mention                 VARCHAR(60)  NULL,
    commentaire_auto_eval   TEXT         NULL,
    commentaire_evaluateur  TEXT         NULL,
    commentaire_validation  TEXT         NULL,
    date_auto_eval          DATETIME     NULL,
    date_evaluation         DATETIME     NULL,
    date_validation         DATETIME     NULL,
    date_publication        DATETIME     NULL,
    valide_par              INT UNSIGNED NULL,
    valide_par_nom          VARCHAR(120) NULL,
    publie_par              INT UNSIGNED NULL,
    publie_par_nom          VARCHAR(120) NULL,
    created_by              INT UNSIGNED NOT NULL,
    updated_by              INT UNSIGNED NOT NULL,
    created_at              DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              DATETIME     NULL,
    UNIQUE KEY uq_eval (campagne_id, employe_id),
    FOREIGN KEY (campagne_id)    REFERENCES rh_campagnes_evaluation(id) ON DELETE RESTRICT,
    FOREIGN KEY (employe_id)     REFERENCES rh_employes(id)             ON DELETE RESTRICT,
    FOREIGN KEY (affectation_id) REFERENCES rh_affectations(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scores par critère
CREATE TABLE IF NOT EXISTS rh_evaluation_criteres (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evaluation_id           INT UNSIGNED NOT NULL,
    critere_id              INT UNSIGNED NOT NULL,
    note_auto_eval          DECIMAL(4,2) NULL,
    note_evaluateur         DECIMAL(4,2) NULL,
    commentaire_auto_eval   TEXT         NULL,
    commentaire_evaluateur  TEXT         NULL,
    UNIQUE KEY uq_ec (evaluation_id, critere_id),
    FOREIGN KEY (evaluation_id) REFERENCES rh_evaluations(id)         ON DELETE CASCADE,
    FOREIGN KEY (critere_id)    REFERENCES rh_criteres_evaluation(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique des transitions
CREATE TABLE IF NOT EXISTS rh_evaluation_historique (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evaluation_id    INT UNSIGNED NOT NULL,
    statut_avant     VARCHAR(30)  NULL,
    statut_apres     VARCHAR(30)  NOT NULL,
    action           VARCHAR(60)  NOT NULL,
    commentaire      TEXT         NULL,
    effectue_par     INT UNSIGNED NOT NULL,
    effectue_par_nom VARCHAR(120) NOT NULL,
    created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluation_id) REFERENCES rh_evaluations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plans de développement
CREATE TABLE IF NOT EXISTS rh_plans_developpement (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT UNSIGNED NOT NULL,
    employe_id    INT UNSIGNED NOT NULL,
    objectif      TEXT         NOT NULL,
    actions       TEXT         NULL,
    ressources    TEXT         NULL,
    echeance      DATE         NULL,
    statut        ENUM('en_cours','realise','abandonne') DEFAULT 'en_cours',
    progression   TINYINT      DEFAULT 0,
    commentaire   TEXT         NULL,
    created_by    INT UNSIGNED NOT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluation_id) REFERENCES rh_evaluations(id) ON DELETE CASCADE,
    FOREIGN KEY (employe_id)    REFERENCES rh_employes(id)    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed : référentiel de critères ──────────────────────────────────────────

INSERT INTO rh_criteres_evaluation (code, libelle, categorie, type_employe, poids, note_max, ordre) VALUES
-- Compétences professionnelles (tous)
('maitrise_poste',      'Maîtrise du poste',             'competence',   'tous',           2.00, 5, 1),
('qualite_travail',     'Qualité du travail',             'competence',   'tous',           2.00, 5, 2),
('productivite',        'Productivité',                   'resultat',     'tous',           1.50, 5, 3),
-- Comportementales (tous)
('ponctualite',         'Ponctualité & assiduité',        'comportement', 'tous',           1.50, 5, 4),
('esprit_equipe',       'Esprit d''équipe',               'comportement', 'tous',           1.00, 5, 5),
('communication',       'Communication',                  'comportement', 'tous',           1.00, 5, 6),
('initiative',          'Initiative & autonomie',         'comportement', 'tous',           1.00, 5, 7),
('respect_reglement',   'Respect du règlement',           'comportement', 'tous',           1.00, 5, 8),
-- Enseignants spécifiques
('pedagogie',           'Qualité pédagogique',            'competence',   'enseignant',     2.00, 5, 9),
('preparation_cours',   'Préparation des cours',          'competence',   'enseignant',     1.50, 5, 10),
('suivi_eleves',        'Suivi des élèves',               'resultat',     'enseignant',     1.50, 5, 11),
('resultats_classe',    'Résultats de la classe',         'resultat',     'enseignant',     2.00, 5, 12),
-- Administratifs spécifiques
('gestion_dossiers',    'Gestion des dossiers',           'competence',   'administratif',  1.50, 5, 9),
('accueil_service',     'Accueil & service',              'comportement', 'administratif',  1.50, 5, 10),
-- Comptables spécifiques
('rigueur_comptable',   'Rigueur comptable',              'competence',   'comptable',      2.00, 5, 9),
('respect_delais',      'Respect des délais comptables',  'resultat',     'comptable',      1.50, 5, 10),
-- Direction spécifique
('leadership',          'Leadership & management',        'competence',   'direction',      2.00, 5, 9),
('prise_decision',      'Prise de décision',              'resultat',     'direction',      1.50, 5, 10);
