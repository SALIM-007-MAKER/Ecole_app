-- ============================================================
-- Migration : Domaine Récompenses — Vie Scolaire V2
-- Préfixe   : vs_
-- Tables    : vs_recompense_categories, vs_recompenses, vs_recompenses_historique
-- ============================================================

-- ── Référentiel catégories ───────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS vs_recompense_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(100) NOT NULL,
    code        VARCHAR(50)  NOT NULL UNIQUE,
    description TEXT,
    couleur     VARCHAR(7)   DEFAULT '#6366f1',
    actif       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO vs_recompense_categories (nom, code, description, couleur) VALUES
    ('Félicitations',              'felicitations',              'Félicitations pour comportement ou résultats excellents',    '#16a34a'),
    ('Encouragements',             'encouragements',             'Encouragements pour efforts notables',                       '#2563eb'),
    ('Tableau d''honneur',         'tableau_honneur',            'Inscription au tableau d''honneur de l''établissement',      '#7c3aed'),
    ('Excellence académique',      'excellence_academique',      'Distinction pour résultats académiques exceptionnels',       '#d97706'),
    ('Bon comportement',           'bon_comportement',           'Récompense pour comportement exemplaire',                    '#0891b2'),
    ('Esprit citoyen',             'esprit_citoyen',             'Reconnaissance d''un acte civique ou solidaire',             '#65a30d'),
    ('Participation exceptionnelle','participation_exceptionnelle','Valorisation d''une implication remarquable',              '#e11d48'),
    ('Autre',                      'autre',                      'Autre type de distinction',                                  '#64748b');

-- ── Récompenses ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS vs_recompenses (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    eleve_id         INT UNSIGNED NOT NULL,
    classe_id        INT UNSIGNED NOT NULL,
    annee_scolaire   VARCHAR(9)   NOT NULL,
    categorie_id     INT UNSIGNED NOT NULL,
    motif            TEXT         NOT NULL,
    niveau           ENUM('classe','etablissement','academique') NOT NULL DEFAULT 'classe',
    statut           ENUM('attribuee','validee','revoquee')      NOT NULL DEFAULT 'attribuee',
    date_attribution DATE         NOT NULL,
    attribue_par     INT UNSIGNED NOT NULL,
    valide_par       INT UNSIGNED NULL,
    valide_le        TIMESTAMP    NULL,
    revoque_par      INT UNSIGNED NULL,
    revoque_le       TIMESTAMP    NULL,
    motif_revocation TEXT         NULL,
    piece_jointe     VARCHAR(500) NULL,
    deleted_at       TIMESTAMP    NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_eleve    (eleve_id, annee_scolaire),
    INDEX idx_classe   (classe_id, annee_scolaire),
    INDEX idx_statut   (statut),
    INDEX idx_date     (date_attribution)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Historique des modifications ──────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS vs_recompenses_historique (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recompense_id   INT UNSIGNED NOT NULL,
    ancien_statut   VARCHAR(50)  NULL,
    nouveau_statut  VARCHAR(50)  NOT NULL,
    motif           TEXT         NULL,
    modifie_par     INT UNSIGNED NOT NULL,
    modifie_le      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_recompense (recompense_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
