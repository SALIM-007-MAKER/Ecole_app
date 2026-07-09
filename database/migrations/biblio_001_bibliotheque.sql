-- =============================================================================
-- MODULE BIBLIOTHÈQUE V2 — Migration biblio_001
-- Phase 9.2 — 14 tables biblio_*
-- =============================================================================

-- ── 1. Auteurs ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS biblio_auteurs (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom               VARCHAR(100) NOT NULL,
    prenom            VARCHAR(100),
    biographie        TEXT,
    nationalite       VARCHAR(60),
    etablissement_id  INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at        DATETIME,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_auteur_etab (etablissement_id),
    INDEX idx_auteur_nom  (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Éditeurs ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS biblio_editeurs (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom               VARCHAR(150) NOT NULL,
    adresse           VARCHAR(255),
    site_web          VARCHAR(255),
    email             VARCHAR(150),
    etablissement_id  INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at        DATETIME,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_editeur_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Catégories (arborescence) ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS biblio_categories (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom               VARCHAR(100) NOT NULL,
    description       TEXT,
    parent_id         INT UNSIGNED,
    ordre             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    couleur           VARCHAR(7),
    etablissement_id  INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at        DATETIME,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES biblio_categories(id) ON DELETE SET NULL,
    INDEX idx_cat_etab   (etablissement_id),
    INDEX idx_cat_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Tags ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS biblio_tags (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom               VARCHAR(60) NOT NULL,
    couleur           VARCHAR(7),
    etablissement_id  INT UNSIGNED NOT NULL DEFAULT 1,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tag_nom_etab (nom, etablissement_id),
    INDEX idx_tag_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Ouvrages ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS biblio_ouvrages (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    isbn                 VARCHAR(13),
    isbn13               VARCHAR(13),
    titre                VARCHAR(255) NOT NULL,
    sous_titre           VARCHAR(255),
    resume               TEXT,
    annee_edition        YEAR,
    nombre_pages         SMALLINT UNSIGNED,
    langue               VARCHAR(10) NOT NULL DEFAULT 'fr',
    image_couverture     VARCHAR(500),
    type                 ENUM('livre','revue','bd','manuel','periodique','numerique','autre') NOT NULL DEFAULT 'livre',
    cote                 VARCHAR(50),
    localisation_defaut  VARCHAR(100),
    editeur_id           INT UNSIGNED,
    statut               ENUM('actif','archive') NOT NULL DEFAULT 'actif',
    etablissement_id     INT UNSIGNED NOT NULL DEFAULT 1,
    created_by           INT UNSIGNED,
    deleted_at           DATETIME,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (editeur_id) REFERENCES biblio_editeurs(id) ON DELETE SET NULL,
    INDEX idx_ouvrage_etab   (etablissement_id),
    INDEX idx_ouvrage_isbn   (isbn13),
    INDEX idx_ouvrage_statut (statut),
    FULLTEXT idx_ouvrage_ft  (titre, sous_titre, resume)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. Ouvrage ↔ Auteurs ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS biblio_ouvrage_auteurs (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ouvrage_id           INT UNSIGNED NOT NULL,
    auteur_id            INT UNSIGNED NOT NULL,
    ordre                TINYINT UNSIGNED NOT NULL DEFAULT 1,
    type_contribution    ENUM('auteur','traducteur','illustrateur','directeur') NOT NULL DEFAULT 'auteur',
    FOREIGN KEY (ouvrage_id) REFERENCES biblio_ouvrages(id)  ON DELETE CASCADE,
    FOREIGN KEY (auteur_id)  REFERENCES biblio_auteurs(id)   ON DELETE CASCADE,
    UNIQUE KEY uk_ouvrage_auteur (ouvrage_id, auteur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. Ouvrage ↔ Catégories ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS biblio_ouvrage_categories (
    ouvrage_id    INT UNSIGNED NOT NULL,
    categorie_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (ouvrage_id, categorie_id),
    FOREIGN KEY (ouvrage_id)   REFERENCES biblio_ouvrages(id)    ON DELETE CASCADE,
    FOREIGN KEY (categorie_id) REFERENCES biblio_categories(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 8. Ouvrage ↔ Tags ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS biblio_ouvrage_tags (
    ouvrage_id  INT UNSIGNED NOT NULL,
    tag_id      INT UNSIGNED NOT NULL,
    PRIMARY KEY (ouvrage_id, tag_id),
    FOREIGN KEY (ouvrage_id) REFERENCES biblio_ouvrages(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)     REFERENCES biblio_tags(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 9. Exemplaires ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS biblio_exemplaires (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ouvrage_id         INT UNSIGNED NOT NULL,
    numero_inventaire  VARCHAR(30) NOT NULL,
    code_barre         VARCHAR(50),
    qr_data            VARCHAR(500),
    localisation       VARCHAR(100),
    statut             ENUM('disponible','emprunte','reserve','en_reparation','perdu','retire') NOT NULL DEFAULT 'disponible',
    etat               ENUM('bon','use','deteriore') NOT NULL DEFAULT 'bon',
    notes              TEXT,
    etablissement_id   INT UNSIGNED NOT NULL DEFAULT 1,
    created_by         INT UNSIGNED,
    deleted_at         DATETIME,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ouvrage_id) REFERENCES biblio_ouvrages(id) ON DELETE CASCADE,
    UNIQUE KEY uk_exemplaire_num (numero_inventaire, etablissement_id),
    INDEX idx_exemplaire_ouvrage (ouvrage_id),
    INDEX idx_exemplaire_statut  (statut),
    INDEX idx_exemplaire_etab    (etablissement_id),
    INDEX idx_exemplaire_barre   (code_barre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 10. Emprunts ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS biblio_emprunts (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exemplaire_id         INT UNSIGNED NOT NULL,
    user_id               INT UNSIGNED NOT NULL,
    date_emprunt          DATE NOT NULL,
    date_retour_prevue    DATE NOT NULL,
    date_retour_effectif  DATE,
    statut                ENUM('en_cours','en_retard','retourne','perdu') NOT NULL DEFAULT 'en_cours',
    prolongations         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    notes                 TEXT,
    created_by            INT UNSIGNED,
    etablissement_id      INT UNSIGNED NOT NULL DEFAULT 1,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exemplaire_id) REFERENCES biblio_exemplaires(id) ON DELETE RESTRICT,
    INDEX idx_emprunt_user       (user_id),
    INDEX idx_emprunt_exemplaire (exemplaire_id),
    INDEX idx_emprunt_statut     (statut),
    INDEX idx_emprunt_echeance   (date_retour_prevue, statut),
    INDEX idx_emprunt_etab       (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 11. Réservations ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS biblio_reservations (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ouvrage_id          INT UNSIGNED NOT NULL,
    user_id             INT UNSIGNED NOT NULL,
    position_file       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    date_reservation    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_disponibilite  DATETIME,
    date_expiration     DATETIME,
    statut              ENUM('en_attente','disponible','confirmee','annulee','expiree') NOT NULL DEFAULT 'en_attente',
    notes               TEXT,
    etablissement_id    INT UNSIGNED NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ouvrage_id) REFERENCES biblio_ouvrages(id) ON DELETE CASCADE,
    INDEX idx_reservation_user    (user_id),
    INDEX idx_reservation_ouvrage (ouvrage_id),
    INDEX idx_reservation_statut  (statut),
    INDEX idx_reservation_etab    (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 12. Pénalités ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS biblio_penalites (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    emprunt_id        INT UNSIGNED NOT NULL,
    user_id           INT UNSIGNED NOT NULL,
    type              ENUM('retard','perte','degradation') NOT NULL,
    montant           DECIMAL(8,2) NOT NULL,
    statut            ENUM('en_attente','payee','annulee') NOT NULL DEFAULT 'en_attente',
    jours_retard      SMALLINT UNSIGNED,
    facture_id        INT UNSIGNED,
    notes             TEXT,
    etablissement_id  INT UNSIGNED NOT NULL DEFAULT 1,
    created_by        INT UNSIGNED,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (emprunt_id) REFERENCES biblio_emprunts(id) ON DELETE RESTRICT,
    INDEX idx_penalite_user    (user_id),
    INDEX idx_penalite_emprunt (emprunt_id),
    INDEX idx_penalite_statut  (statut),
    INDEX idx_penalite_etab    (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 13. Sessions d'inventaire ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS biblio_inventaires (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom                 VARCHAR(150) NOT NULL,
    description         TEXT,
    date_debut          DATE NOT NULL,
    date_fin_prevue     DATE,
    date_fin_effective  DATE,
    statut              ENUM('en_cours','termine','annule') NOT NULL DEFAULT 'en_cours',
    created_by          INT UNSIGNED,
    etablissement_id    INT UNSIGNED NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inventaire_statut (statut),
    INDEX idx_inventaire_etab   (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 14. Lignes d'inventaire ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS biblio_inventaire_lignes (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventaire_id    INT UNSIGNED NOT NULL,
    exemplaire_id    INT UNSIGNED NOT NULL,
    statut_constate  ENUM('present','manquant','deteriore','perdu') NOT NULL DEFAULT 'present',
    notes            TEXT,
    scanned_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    scanned_by       INT UNSIGNED,
    FOREIGN KEY (inventaire_id) REFERENCES biblio_inventaires(id)  ON DELETE CASCADE,
    FOREIGN KEY (exemplaire_id) REFERENCES biblio_exemplaires(id)  ON DELETE RESTRICT,
    UNIQUE KEY uk_ligne_inventaire (inventaire_id, exemplaire_id),
    INDEX idx_ligne_inventaire (inventaire_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
