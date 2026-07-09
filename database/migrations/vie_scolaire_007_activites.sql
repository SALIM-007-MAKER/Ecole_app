-- ============================================================
-- Phase 5.7 — Domaine Activités Scolaires V2
-- Préfixe : vs_
-- Aucun DROP TABLE — ajout uniquement
-- ============================================================

-- 1. Catégories d'activités (référentiel configurable)
CREATE TABLE IF NOT EXISTS vs_activite_categories (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom       VARCHAR(100) NOT NULL,
    code      VARCHAR(50)  NOT NULL,
    couleur   VARCHAR(7)   NOT NULL DEFAULT '#8B5CF6',
    icone     VARCHAR(50)  NULL,
    actif     TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_activite_cat_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO vs_activite_categories (nom, code, couleur, icone) VALUES
    ('Club',                'club',          '#8B5CF6', 'users'),
    ('Association',         'association',   '#3B82F6', 'heart-handshake'),
    ('Événement',           'evenement',     '#10B981', 'calendar-days'),
    ('Sortie pédagogique',  'sortie',        '#F59E0B', 'map-pin'),
    ('Compétition',         'competition',   '#EF4444', 'trophy'),
    ('Cérémonie',           'ceremonie',     '#6366F1', 'star'),
    ('Conférence',          'conference',    '#14B8A6', 'mic'),
    ('Atelier',             'atelier',       '#F97316', 'wrench');

-- 2. Activités
CREATE TABLE IF NOT EXISTS vs_activites (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categorie_id     INT UNSIGNED  NOT NULL,
    titre            VARCHAR(200)  NOT NULL,
    description      TEXT          NULL,
    lieu             VARCHAR(200)  NULL,
    date_activite    DATE          NOT NULL,
    heure_debut      TIME          NOT NULL,
    heure_fin        TIME          NOT NULL,
    capacite_max     SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    annee_scolaire   VARCHAR(9)    NOT NULL,
    statut           ENUM('brouillon','publie','en_cours','termine','annule')
                     NOT NULL DEFAULT 'brouillon',
    organisateur_id  INT UNSIGNED  NULL COMMENT 'user_id organisateur',
    cree_par         INT UNSIGNED  NOT NULL,
    publie_par       INT UNSIGNED  NULL,
    publie_le        DATETIME      NULL,
    termine_le       DATETIME      NULL,
    annule_par       INT UNSIGNED  NULL,
    annule_le        DATETIME      NULL,
    motif_annulation TEXT          NULL,
    deleted_at       DATETIME      NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vs_act_categorie FOREIGN KEY (categorie_id) REFERENCES vs_activite_categories(id),
    INDEX idx_vs_act_date        (date_activite),
    INDEX idx_vs_act_statut      (statut),
    INDEX idx_vs_act_annee       (annee_scolaire),
    INDEX idx_vs_act_deleted     (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Classes liées à une activité (N:N)
CREATE TABLE IF NOT EXISTS vs_activite_classes (
    activite_id INT UNSIGNED NOT NULL,
    classe_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (activite_id, classe_id),
    CONSTRAINT fk_vs_actcl_activite FOREIGN KEY (activite_id) REFERENCES vs_activites(id) ON DELETE CASCADE,
    CONSTRAINT fk_vs_actcl_classe   FOREIGN KEY (classe_id)   REFERENCES classes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Responsables d'une activité (enseignants N:N)
CREATE TABLE IF NOT EXISTS vs_activite_responsables (
    activite_id    INT UNSIGNED NOT NULL,
    enseignant_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (activite_id, enseignant_id),
    CONSTRAINT fk_vs_actresp_activite   FOREIGN KEY (activite_id)   REFERENCES vs_activites(id) ON DELETE CASCADE,
    CONSTRAINT fk_vs_actresp_enseignant FOREIGN KEY (enseignant_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Inscriptions élèves
CREATE TABLE IF NOT EXISTS vs_activite_inscriptions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activite_id     INT UNSIGNED NOT NULL,
    eleve_id        INT UNSIGNED NOT NULL,
    statut          ENUM('inscrit','liste_attente','annule','present','absent')
                    NOT NULL DEFAULT 'inscrit',
    date_inscription DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    inscrit_par     INT UNSIGNED NULL,
    annule_par      INT UNSIGNED NULL,
    annule_le       DATETIME    NULL,
    motif_annulation VARCHAR(500) NULL,
    note            VARCHAR(500) NULL,
    deleted_at      DATETIME    NULL,
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vs_inscr_eleve_activite (activite_id, eleve_id, deleted_at),
    CONSTRAINT fk_vs_inscr_activite FOREIGN KEY (activite_id) REFERENCES vs_activites(id),
    INDEX idx_vs_inscr_statut   (statut),
    INDEX idx_vs_inscr_eleve    (eleve_id),
    INDEX idx_vs_inscr_deleted  (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Historique des modifications (immutable)
CREATE TABLE IF NOT EXISTS vs_activite_historique (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activite_id INT UNSIGNED NOT NULL,
    action      VARCHAR(100) NOT NULL,
    description TEXT         NULL,
    data_json   JSON         NULL,
    user_id     INT UNSIGNED NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vs_acthist_activite FOREIGN KEY (activite_id) REFERENCES vs_activites(id),
    INDEX idx_vs_acthist_activite (activite_id),
    INDEX idx_vs_acthist_action   (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
