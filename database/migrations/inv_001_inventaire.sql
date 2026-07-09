-- =============================================================================
-- INVENTAIRE V2 — Migration SQL
-- Module : App\Modules\Inventaire
-- Tables : 16 (préfixe inv_*)
-- =============================================================================

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_categories
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_categories (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom              VARCHAR(100) NOT NULL,
    description      TEXT,
    parent_id        INT UNSIGNED DEFAULT NULL,
    code             VARCHAR(20),
    couleur          VARCHAR(7) DEFAULT '#6366f1',
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at       DATETIME DEFAULT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_cat_parent FOREIGN KEY (parent_id)
        REFERENCES inv_categories(id) ON DELETE SET NULL,
    INDEX idx_inv_cat_parent (parent_id),
    INDEX idx_inv_cat_etab   (etablissement_id),
    INDEX idx_inv_cat_del    (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_fournisseurs
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_fournisseurs (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom                 VARCHAR(150) NOT NULL,
    code                VARCHAR(30),
    email               VARCHAR(150),
    telephone           VARCHAR(20),
    adresse             TEXT,
    site_web            VARCHAR(200),
    rib                 VARCHAR(34),
    delai_livraison_j   TINYINT UNSIGNED DEFAULT 7,
    conditions_paiement VARCHAR(100),
    statut              ENUM('actif','inactif','bloque') NOT NULL DEFAULT 'actif',
    notes               TEXT,
    etablissement_id    INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at          DATETIME DEFAULT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_inv_fourn_statut (statut),
    INDEX idx_inv_fourn_etab   (etablissement_id),
    INDEX idx_inv_fourn_del    (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_emplacements
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_emplacements (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom              VARCHAR(100) NOT NULL,
    code             VARCHAR(20),
    description      TEXT,
    type             ENUM('entrepot','salle','bureau','armoire','autre') NOT NULL DEFAULT 'autre',
    parent_id        INT UNSIGNED DEFAULT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_empl_parent FOREIGN KEY (parent_id)
        REFERENCES inv_emplacements(id) ON DELETE SET NULL,
    INDEX idx_inv_empl_etab   (etablissement_id),
    INDEX idx_inv_empl_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_articles
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_articles (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference            VARCHAR(50) NOT NULL,
    designation          VARCHAR(200) NOT NULL,
    description          TEXT,
    categorie_id         INT UNSIGNED DEFAULT NULL,
    type                 ENUM('consommable','durable','equipement') NOT NULL DEFAULT 'consommable',
    unite_mesure         VARCHAR(20) DEFAULT 'unité',
    seuil_alerte         DECIMAL(10,2) DEFAULT 0,
    seuil_critique       DECIMAL(10,2) DEFAULT 0,
    valeur_unitaire      DECIMAL(12,2) DEFAULT 0.00,
    fournisseur_id       INT UNSIGNED DEFAULT NULL,
    image                VARCHAR(255),
    barcode              VARCHAR(100),
    qr_data              TEXT,
    numero_serie         VARCHAR(100),
    localisation_defaut  INT UNSIGNED DEFAULT NULL,
    garantie_mois        SMALLINT UNSIGNED DEFAULT 0,
    actif                TINYINT(1) NOT NULL DEFAULT 1,
    etablissement_id     INT UNSIGNED NOT NULL DEFAULT 1,
    created_by           INT UNSIGNED DEFAULT NULL,
    deleted_at           DATETIME DEFAULT NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_inv_art_ref_etab (reference, etablissement_id),
    CONSTRAINT fk_inv_art_cat    FOREIGN KEY (categorie_id)       REFERENCES inv_categories(id)   ON DELETE SET NULL,
    CONSTRAINT fk_inv_art_fourn  FOREIGN KEY (fournisseur_id)     REFERENCES inv_fournisseurs(id) ON DELETE SET NULL,
    CONSTRAINT fk_inv_art_empl   FOREIGN KEY (localisation_defaut) REFERENCES inv_emplacements(id) ON DELETE SET NULL,
    FULLTEXT INDEX ft_inv_art_search (designation, description, reference),
    INDEX idx_inv_art_type    (type),
    INDEX idx_inv_art_etab    (etablissement_id),
    INDEX idx_inv_art_actif   (actif),
    INDEX idx_inv_art_barcode (barcode),
    INDEX idx_inv_art_del     (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_stocks
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_stocks (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id          INT UNSIGNED NOT NULL,
    emplacement_id      INT UNSIGNED NOT NULL,
    quantite_disponible DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantite_reservee   DECIMAL(12,2) NOT NULL DEFAULT 0,
    etablissement_id    INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_inv_stock (article_id, emplacement_id, etablissement_id),
    CONSTRAINT fk_inv_stock_art  FOREIGN KEY (article_id)     REFERENCES inv_articles(id)    ON DELETE CASCADE,
    CONSTRAINT fk_inv_stock_empl FOREIGN KEY (emplacement_id) REFERENCES inv_emplacements(id) ON DELETE RESTRICT,
    INDEX idx_inv_stock_etab     (etablissement_id),
    INDEX idx_inv_stock_article  (article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_commandes
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_commandes (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero                VARCHAR(30) NOT NULL,
    fournisseur_id        INT UNSIGNED NOT NULL,
    date_commande         DATE NOT NULL,
    date_livraison_prevue DATE DEFAULT NULL,
    statut                ENUM('brouillon','validee','envoyee','partiellement_recue','recue','annulee')
                          NOT NULL DEFAULT 'brouillon',
    total_ht              DECIMAL(12,2) DEFAULT 0.00,
    total_ttc             DECIMAL(12,2) DEFAULT 0.00,
    tva_taux              DECIMAL(5,2) DEFAULT 20.00,
    notes                 TEXT,
    created_by            INT UNSIGNED DEFAULT NULL,
    validated_by          INT UNSIGNED DEFAULT NULL,
    validated_at          DATETIME DEFAULT NULL,
    facture_finance_id    INT UNSIGNED DEFAULT NULL,
    etablissement_id      INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at            DATETIME DEFAULT NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_inv_cmd_num_etab (numero, etablissement_id),
    CONSTRAINT fk_inv_cmd_fourn FOREIGN KEY (fournisseur_id) REFERENCES inv_fournisseurs(id) ON DELETE RESTRICT,
    INDEX idx_inv_cmd_statut (statut),
    INDEX idx_inv_cmd_etab   (etablissement_id),
    INDEX idx_inv_cmd_del    (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_commande_lignes
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_commande_lignes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id         INT UNSIGNED NOT NULL,
    article_id          INT UNSIGNED NOT NULL,
    quantite_commandee  DECIMAL(12,2) NOT NULL,
    quantite_recue      DECIMAL(12,2) NOT NULL DEFAULT 0,
    prix_unitaire_ht    DECIMAL(12,2) NOT NULL DEFAULT 0,
    tva_taux            DECIMAL(5,2) DEFAULT 20.00,
    total_ht            DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes               TEXT,

    CONSTRAINT fk_inv_cl_cmd FOREIGN KEY (commande_id) REFERENCES inv_commandes(id)  ON DELETE CASCADE,
    CONSTRAINT fk_inv_cl_art FOREIGN KEY (article_id)  REFERENCES inv_articles(id)   ON DELETE RESTRICT,
    INDEX idx_inv_cl_cmd (commande_id),
    INDEX idx_inv_cl_art (article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_receptions
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_receptions (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id      INT UNSIGNED NOT NULL,
    date_reception   DATE NOT NULL,
    bon_livraison    VARCHAR(100),
    notes            TEXT,
    created_by       INT UNSIGNED DEFAULT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_rec_cmd FOREIGN KEY (commande_id) REFERENCES inv_commandes(id) ON DELETE RESTRICT,
    INDEX idx_inv_rec_cmd  (commande_id),
    INDEX idx_inv_rec_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_reception_lignes
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_reception_lignes (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reception_id      INT UNSIGNED NOT NULL,
    commande_ligne_id INT UNSIGNED NOT NULL,
    article_id        INT UNSIGNED NOT NULL,
    quantite_recue    DECIMAL(12,2) NOT NULL,
    emplacement_id    INT UNSIGNED NOT NULL,
    notes             TEXT,

    CONSTRAINT fk_inv_rl_rec  FOREIGN KEY (reception_id)      REFERENCES inv_receptions(id)      ON DELETE CASCADE,
    CONSTRAINT fk_inv_rl_cl   FOREIGN KEY (commande_ligne_id) REFERENCES inv_commande_lignes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_inv_rl_art  FOREIGN KEY (article_id)        REFERENCES inv_articles(id)        ON DELETE RESTRICT,
    CONSTRAINT fk_inv_rl_empl FOREIGN KEY (emplacement_id)    REFERENCES inv_emplacements(id)    ON DELETE RESTRICT,
    INDEX idx_inv_rl_rec (reception_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_mouvements  (journal universel)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_mouvements (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id            INT UNSIGNED NOT NULL,
    type                  ENUM('entree','sortie','transfert','ajustement',
                               'consommation','affectation','retour_affectation') NOT NULL,
    quantite              DECIMAL(12,2) NOT NULL,
    quantite_avant        DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantite_apres        DECIMAL(12,2) NOT NULL DEFAULT 0,
    emplacement_source_id INT UNSIGNED DEFAULT NULL,
    emplacement_dest_id   INT UNSIGNED DEFAULT NULL,
    reference_type        VARCHAR(50) DEFAULT NULL,
    reference_id          INT UNSIGNED DEFAULT NULL,
    notes                 TEXT,
    created_by            INT UNSIGNED DEFAULT NULL,
    etablissement_id      INT UNSIGNED NOT NULL DEFAULT 1,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_mv_art FOREIGN KEY (article_id)            REFERENCES inv_articles(id)    ON DELETE RESTRICT,
    CONSTRAINT fk_inv_mv_src FOREIGN KEY (emplacement_source_id) REFERENCES inv_emplacements(id) ON DELETE SET NULL,
    CONSTRAINT fk_inv_mv_dst FOREIGN KEY (emplacement_dest_id)   REFERENCES inv_emplacements(id) ON DELETE SET NULL,
    INDEX idx_inv_mv_article (article_id),
    INDEX idx_inv_mv_type    (type),
    INDEX idx_inv_mv_etab    (etablissement_id),
    INDEX idx_inv_mv_ref     (reference_type, reference_id),
    INDEX idx_inv_mv_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_affectations
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_affectations (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id           INT UNSIGNED NOT NULL,
    user_id              INT UNSIGNED NOT NULL,
    quantite             DECIMAL(12,2) NOT NULL DEFAULT 1,
    date_affectation     DATE NOT NULL,
    date_retour_prevue   DATE DEFAULT NULL,
    date_retour_effectif DATE DEFAULT NULL,
    statut               ENUM('en_cours','retournee','perdue') NOT NULL DEFAULT 'en_cours',
    emplacement_id       INT UNSIGNED DEFAULT NULL,
    notes                TEXT,
    created_by           INT UNSIGNED DEFAULT NULL,
    etablissement_id     INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at           DATETIME DEFAULT NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_aff_art  FOREIGN KEY (article_id)   REFERENCES inv_articles(id)    ON DELETE RESTRICT,
    CONSTRAINT fk_inv_aff_empl FOREIGN KEY (emplacement_id) REFERENCES inv_emplacements(id) ON DELETE SET NULL,
    INDEX idx_inv_aff_user   (user_id),
    INDEX idx_inv_aff_art    (article_id),
    INDEX idx_inv_aff_statut (statut),
    INDEX idx_inv_aff_etab   (etablissement_id),
    INDEX idx_inv_aff_del    (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_maintenances
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_maintenances (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id       INT UNSIGNED NOT NULL,
    type             ENUM('preventive','corrective','revision') NOT NULL DEFAULT 'preventive',
    statut           ENUM('planifiee','en_cours','terminee','annulee') NOT NULL DEFAULT 'planifiee',
    date_planifiee   DATE NOT NULL,
    date_debut       DATE DEFAULT NULL,
    date_fin         DATE DEFAULT NULL,
    prestataire      VARCHAR(150),
    cout             DECIMAL(12,2) DEFAULT NULL,
    description      TEXT,
    rapport          TEXT,
    created_by       INT UNSIGNED DEFAULT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at       DATETIME DEFAULT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_maint_art FOREIGN KEY (article_id) REFERENCES inv_articles(id) ON DELETE RESTRICT,
    INDEX idx_inv_maint_art    (article_id),
    INDEX idx_inv_maint_statut (statut),
    INDEX idx_inv_maint_date   (date_planifiee),
    INDEX idx_inv_maint_etab   (etablissement_id),
    INDEX idx_inv_maint_del    (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_amortissements  (stub V3)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_amortissements (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id               INT UNSIGNED NOT NULL,
    valeur_achat             DECIMAL(12,2) NOT NULL,
    date_achat               DATE NOT NULL,
    duree_amortissement_mois SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    methode                  ENUM('lineaire','degressif') NOT NULL DEFAULT 'lineaire',
    valeur_residuelle        DECIMAL(12,2) DEFAULT 0.00,
    valeur_nette_comptable   DECIMAL(12,2) DEFAULT NULL,
    statut                   ENUM('en_cours','termine') NOT NULL DEFAULT 'en_cours',
    etablissement_id         INT UNSIGNED NOT NULL DEFAULT 1,
    created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_inv_amort_art (article_id, etablissement_id),
    CONSTRAINT fk_inv_amort_art FOREIGN KEY (article_id) REFERENCES inv_articles(id) ON DELETE RESTRICT,
    INDEX idx_inv_amort_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_inventaires  (sessions inventaire physique)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_inventaires (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom              VARCHAR(150) NOT NULL,
    description      TEXT,
    statut           ENUM('en_cours','termine','annule') NOT NULL DEFAULT 'en_cours',
    date_debut       DATE NOT NULL,
    date_fin         DATE DEFAULT NULL,
    created_by       INT UNSIGNED DEFAULT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_inv_inv_statut (statut),
    INDEX idx_inv_inv_etab   (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_inventaire_lignes
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_inventaire_lignes (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventaire_id      INT UNSIGNED NOT NULL,
    article_id         INT UNSIGNED NOT NULL,
    emplacement_id     INT UNSIGNED NOT NULL,
    quantite_theorique DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantite_comptee   DECIMAL(12,2) DEFAULT NULL,
    ecart              DECIMAL(12,2) DEFAULT NULL,
    statut             ENUM('a_compter','compte','valide') NOT NULL DEFAULT 'a_compter',
    notes              TEXT,
    counted_by         INT UNSIGNED DEFAULT NULL,
    counted_at         DATETIME DEFAULT NULL,

    CONSTRAINT fk_inv_il_inv  FOREIGN KEY (inventaire_id)  REFERENCES inv_inventaires(id)   ON DELETE CASCADE,
    CONSTRAINT fk_inv_il_art  FOREIGN KEY (article_id)     REFERENCES inv_articles(id)       ON DELETE RESTRICT,
    CONSTRAINT fk_inv_il_empl FOREIGN KEY (emplacement_id) REFERENCES inv_emplacements(id)  ON DELETE RESTRICT,
    UNIQUE KEY uq_inv_il_session (inventaire_id, article_id, emplacement_id),
    INDEX idx_inv_il_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_alertes
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_alertes (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id       INT UNSIGNED NOT NULL,
    type             ENUM('stock_min','stock_critique','maintenance_due',
                          'garantie_expiration','amortissement_fin') NOT NULL,
    valeur_seuil     DECIMAL(12,2) DEFAULT NULL,
    valeur_actuelle  DECIMAL(12,2) DEFAULT NULL,
    statut           ENUM('active','acquittee','resolue') NOT NULL DEFAULT 'active',
    acquittee_by     INT UNSIGNED DEFAULT NULL,
    acquittee_at     DATETIME DEFAULT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_alerte_art FOREIGN KEY (article_id) REFERENCES inv_articles(id) ON DELETE CASCADE,
    INDEX idx_inv_alerte_type   (type),
    INDEX idx_inv_alerte_statut (statut),
    INDEX idx_inv_alerte_etab   (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
