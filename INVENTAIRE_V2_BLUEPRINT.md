# INVENTAIRE_V2_BLUEPRINT.md
## Module Inventaire V2 — Blueprint Technique

**Date :** 2026-07-03  
**Version :** 2.0.0  
**Statut :** BLUEPRINT — Aucune implémentation  
**Préfixe tables :** `inv_*`  
**Préfixe routes :** `/v2/inventaire/`  
**Namespace :** `App\Modules\Inventaire`  

---

## 1. VISION MÉTIER

Le module Inventaire V2 gère l'intégralité du cycle de vie des biens matériels d'un établissement scolaire : de l'achat fournisseur jusqu'au suivi d'amortissement, en passant par les mouvements de stock, l'affectation au personnel et la maintenance préventive/corrective.

### 1.1 Types d'articles gérés

| Type | Description | Exemples |
|------|-------------|---------|
| `consommable` | Quantité décroissante, réapprovisionnement | Rames papier, stylos, cartouches |
| `durable` | Objet individuel suivi, affectable | Ordinateur, projecteur, mobilier |
| `equipement` | Durable + maintenance planifiée + amortissement | Serveurs, machines industrielles |

### 1.2 Workflows métier principaux

```
APPROVISIONNEMENT
  Commande (brouillon) → Validation → Envoi fournisseur
  → Réception partielle/totale → Entrée stock → Facture Finance

AFFECTATION
  Demande affectation → Sortie stock → Affectation active
  → Retour / Perte → Retour stock / Pénalité Finance

MAINTENANCE
  Planification → Intervention → Rapport → Clôture
  → Coût → Intégration Finance (optionnel)

INVENTAIRE PHYSIQUE
  Déclenchement session → Scan articles → Comptage
  → Calcul écarts → Ajustements stock → Clôture

ALERTES
  Cron détection → Seuil min/critique/maintenance due
  → AlerteStockDeclenchee → Notification Communication
  → Suggestion commande
```

---

## 2. BASE DE DONNÉES — 15 TABLES

### 2.1 Vue d'ensemble

```
inv_categories          — Arborescence catégories
inv_fournisseurs        — Catalogue fournisseurs
inv_articles            — Référentiel articles
inv_emplacements        — Entrepôts / salles / armoires
inv_stocks              — Niveaux de stock par article × emplacement
inv_commandes           — Bons de commande fournisseur
inv_commande_lignes     — Lignes de commande
inv_receptions          — Bons de réception
inv_reception_lignes    — Lignes de réception
inv_mouvements          — Journal universel de tous les mouvements
inv_affectations        — Affectation matériel → personnel
inv_maintenances        — Interventions maintenance
inv_amortissements      — Tableau d'amortissement (stub V3)
inv_inventaires         — Sessions d'inventaire physique
inv_inventaire_lignes   — Comptages par article × emplacement
```

### 2.2 DDL Complet

```sql
-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_categories
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_categories (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(100) NOT NULL,
    description     TEXT,
    parent_id       INT UNSIGNED DEFAULT NULL,
    code            VARCHAR(20),
    couleur         VARCHAR(7) DEFAULT '#6366f1',
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at      DATETIME DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_cat_parent FOREIGN KEY (parent_id)
        REFERENCES inv_categories(id) ON DELETE SET NULL,
    INDEX idx_inv_cat_parent (parent_id),
    INDEX idx_inv_cat_etab (etablissement_id)
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
    INDEX idx_inv_fourn_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_emplacements
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_emplacements (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(100) NOT NULL,
    code            VARCHAR(20),
    description     TEXT,
    type            ENUM('entrepot','salle','bureau','armoire','autre') NOT NULL DEFAULT 'autre',
    parent_id       INT UNSIGNED DEFAULT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_empl_parent FOREIGN KEY (parent_id)
        REFERENCES inv_emplacements(id) ON DELETE SET NULL,
    INDEX idx_inv_empl_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_articles
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_articles (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference           VARCHAR(50) NOT NULL,
    designation         VARCHAR(200) NOT NULL,
    description         TEXT,
    categorie_id        INT UNSIGNED DEFAULT NULL,
    type                ENUM('consommable','durable','equipement') NOT NULL DEFAULT 'consommable',
    unite_mesure        VARCHAR(20) DEFAULT 'unité',
    seuil_alerte        DECIMAL(10,2) DEFAULT 0,
    seuil_critique      DECIMAL(10,2) DEFAULT 0,
    valeur_unitaire     DECIMAL(12,2) DEFAULT 0.00,
    fournisseur_id      INT UNSIGNED DEFAULT NULL,
    image               VARCHAR(255),
    barcode             VARCHAR(100),
    qr_data             TEXT,
    numero_serie        VARCHAR(100),
    localisation_defaut INT UNSIGNED DEFAULT NULL,
    garantie_mois       SMALLINT UNSIGNED DEFAULT 0,
    actif               TINYINT(1) NOT NULL DEFAULT 1,
    etablissement_id    INT UNSIGNED NOT NULL DEFAULT 1,
    created_by          INT UNSIGNED DEFAULT NULL,
    deleted_at          DATETIME DEFAULT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_inv_art_ref_etab (reference, etablissement_id),
    CONSTRAINT fk_inv_art_cat    FOREIGN KEY (categorie_id)        REFERENCES inv_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_inv_art_fourn  FOREIGN KEY (fournisseur_id)      REFERENCES inv_fournisseurs(id) ON DELETE SET NULL,
    CONSTRAINT fk_inv_art_empl   FOREIGN KEY (localisation_defaut) REFERENCES inv_emplacements(id) ON DELETE SET NULL,
    FULLTEXT INDEX ft_inv_art_search (designation, description, reference),
    INDEX idx_inv_art_type (type),
    INDEX idx_inv_art_etab (etablissement_id),
    INDEX idx_inv_art_actif (actif)
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
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    etablissement_id    INT UNSIGNED NOT NULL DEFAULT 1,

    UNIQUE KEY uq_inv_stock (article_id, emplacement_id, etablissement_id),
    CONSTRAINT fk_inv_stock_art  FOREIGN KEY (article_id)     REFERENCES inv_articles(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_stock_empl FOREIGN KEY (emplacement_id) REFERENCES inv_emplacements(id) ON DELETE RESTRICT,
    INDEX idx_inv_stock_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_commandes
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_commandes (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero                  VARCHAR(30) NOT NULL,
    fournisseur_id          INT UNSIGNED NOT NULL,
    date_commande           DATE NOT NULL,
    date_livraison_prevue   DATE,
    statut                  ENUM('brouillon','validee','envoyee','partiellement_recue','recue','annulee')
                            NOT NULL DEFAULT 'brouillon',
    total_ht                DECIMAL(12,2) DEFAULT 0.00,
    total_ttc               DECIMAL(12,2) DEFAULT 0.00,
    tva_taux                DECIMAL(5,2) DEFAULT 20.00,
    notes                   TEXT,
    created_by              INT UNSIGNED DEFAULT NULL,
    validated_by            INT UNSIGNED DEFAULT NULL,
    validated_at            DATETIME DEFAULT NULL,
    facture_finance_id      INT UNSIGNED DEFAULT NULL,
    etablissement_id        INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at              DATETIME DEFAULT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_inv_cmd_num_etab (numero, etablissement_id),
    CONSTRAINT fk_inv_cmd_fourn FOREIGN KEY (fournisseur_id) REFERENCES inv_fournisseurs(id) ON DELETE RESTRICT,
    INDEX idx_inv_cmd_statut (statut),
    INDEX idx_inv_cmd_etab (etablissement_id)
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

    CONSTRAINT fk_inv_cl_cmd FOREIGN KEY (commande_id) REFERENCES inv_commandes(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_cl_art FOREIGN KEY (article_id)  REFERENCES inv_articles(id)  ON DELETE RESTRICT,
    INDEX idx_inv_cl_cmd (commande_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_receptions
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_receptions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id     INT UNSIGNED NOT NULL,
    date_reception  DATE NOT NULL,
    bon_livraison   VARCHAR(100),
    notes           TEXT,
    created_by      INT UNSIGNED DEFAULT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_rec_cmd FOREIGN KEY (commande_id) REFERENCES inv_commandes(id) ON DELETE RESTRICT,
    INDEX idx_inv_rec_cmd (commande_id),
    INDEX idx_inv_rec_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_reception_lignes
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_reception_lignes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reception_id        INT UNSIGNED NOT NULL,
    commande_ligne_id   INT UNSIGNED NOT NULL,
    article_id          INT UNSIGNED NOT NULL,
    quantite_recue      DECIMAL(12,2) NOT NULL,
    emplacement_id      INT UNSIGNED NOT NULL,
    notes               TEXT,

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
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id              INT UNSIGNED NOT NULL,
    type                    ENUM('entree','sortie','transfert','ajustement',
                                 'consommation','affectation','retour_affectation')
                            NOT NULL,
    quantite                DECIMAL(12,2) NOT NULL,
    quantite_avant          DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantite_apres          DECIMAL(12,2) NOT NULL DEFAULT 0,
    emplacement_source_id   INT UNSIGNED DEFAULT NULL,
    emplacement_dest_id     INT UNSIGNED DEFAULT NULL,
    reference_type          VARCHAR(50) DEFAULT NULL,
    reference_id            INT UNSIGNED DEFAULT NULL,
    notes                   TEXT,
    created_by              INT UNSIGNED DEFAULT NULL,
    etablissement_id        INT UNSIGNED NOT NULL DEFAULT 1,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_mv_art  FOREIGN KEY (article_id)           REFERENCES inv_articles(id)    ON DELETE RESTRICT,
    CONSTRAINT fk_inv_mv_src  FOREIGN KEY (emplacement_source_id) REFERENCES inv_emplacements(id) ON DELETE SET NULL,
    CONSTRAINT fk_inv_mv_dst  FOREIGN KEY (emplacement_dest_id)   REFERENCES inv_emplacements(id) ON DELETE SET NULL,
    INDEX idx_inv_mv_article (article_id),
    INDEX idx_inv_mv_type (type),
    INDEX idx_inv_mv_etab (etablissement_id),
    INDEX idx_inv_mv_ref (reference_type, reference_id),
    INDEX idx_inv_mv_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_affectations
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_affectations (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id              INT UNSIGNED NOT NULL,
    user_id                 INT UNSIGNED NOT NULL,
    quantite                DECIMAL(12,2) NOT NULL DEFAULT 1,
    date_affectation        DATE NOT NULL,
    date_retour_prevue      DATE DEFAULT NULL,
    date_retour_effectif    DATE DEFAULT NULL,
    statut                  ENUM('en_cours','retournee','perdue') NOT NULL DEFAULT 'en_cours',
    emplacement_id          INT UNSIGNED DEFAULT NULL,
    notes                   TEXT,
    created_by              INT UNSIGNED DEFAULT NULL,
    etablissement_id        INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at              DATETIME DEFAULT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_aff_art  FOREIGN KEY (article_id)  REFERENCES inv_articles(id)    ON DELETE RESTRICT,
    CONSTRAINT fk_inv_aff_empl FOREIGN KEY (emplacement_id) REFERENCES inv_emplacements(id) ON DELETE SET NULL,
    INDEX idx_inv_aff_user (user_id),
    INDEX idx_inv_aff_art (article_id),
    INDEX idx_inv_aff_statut (statut),
    INDEX idx_inv_aff_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_maintenances
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_maintenances (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id      INT UNSIGNED NOT NULL,
    type            ENUM('preventive','corrective','revision') NOT NULL DEFAULT 'preventive',
    statut          ENUM('planifiee','en_cours','terminee','annulee') NOT NULL DEFAULT 'planifiee',
    date_planifiee  DATE NOT NULL,
    date_debut      DATE DEFAULT NULL,
    date_fin        DATE DEFAULT NULL,
    prestataire     VARCHAR(150),
    cout            DECIMAL(12,2) DEFAULT NULL,
    description     TEXT,
    rapport         TEXT,
    created_by      INT UNSIGNED DEFAULT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at      DATETIME DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_maint_art FOREIGN KEY (article_id) REFERENCES inv_articles(id) ON DELETE RESTRICT,
    INDEX idx_inv_maint_art (article_id),
    INDEX idx_inv_maint_statut (statut),
    INDEX idx_inv_maint_date (date_planifiee),
    INDEX idx_inv_maint_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_amortissements  (stub — V3)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_amortissements (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id                  INT UNSIGNED NOT NULL,
    valeur_achat                DECIMAL(12,2) NOT NULL,
    date_achat                  DATE NOT NULL,
    duree_amortissement_mois    SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    methode                     ENUM('lineaire','degressif') NOT NULL DEFAULT 'lineaire',
    valeur_residuelle           DECIMAL(12,2) DEFAULT 0.00,
    valeur_nette_comptable      DECIMAL(12,2) DEFAULT NULL,
    statut                      ENUM('en_cours','termine') NOT NULL DEFAULT 'en_cours',
    etablissement_id            INT UNSIGNED NOT NULL DEFAULT 1,
    created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_inv_amort_art (article_id, etablissement_id),
    CONSTRAINT fk_inv_amort_art FOREIGN KEY (article_id) REFERENCES inv_articles(id) ON DELETE RESTRICT,
    INDEX idx_inv_amort_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_inventaires  (sessions d'inventaire physique)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_inventaires (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(150) NOT NULL,
    description     TEXT,
    statut          ENUM('en_cours','termine','annule') NOT NULL DEFAULT 'en_cours',
    date_debut      DATE NOT NULL,
    date_fin        DATE DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_inv_inv_statut (statut),
    INDEX idx_inv_inv_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_inventaire_lignes
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_inventaire_lignes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventaire_id       INT UNSIGNED NOT NULL,
    article_id          INT UNSIGNED NOT NULL,
    emplacement_id      INT UNSIGNED NOT NULL,
    quantite_theorique  DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantite_comptee    DECIMAL(12,2) DEFAULT NULL,
    ecart               DECIMAL(12,2) GENERATED ALWAYS AS (quantite_comptee - quantite_theorique) STORED,
    statut              ENUM('a_compter','compte','valide') NOT NULL DEFAULT 'a_compter',
    notes               TEXT,
    counted_by          INT UNSIGNED DEFAULT NULL,
    counted_at          DATETIME DEFAULT NULL,

    CONSTRAINT fk_inv_il_inv  FOREIGN KEY (inventaire_id) REFERENCES inv_inventaires(id)   ON DELETE CASCADE,
    CONSTRAINT fk_inv_il_art  FOREIGN KEY (article_id)    REFERENCES inv_articles(id)       ON DELETE RESTRICT,
    CONSTRAINT fk_inv_il_empl FOREIGN KEY (emplacement_id) REFERENCES inv_emplacements(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_inv_il_session (inventaire_id, article_id, emplacement_id),
    INDEX idx_inv_il_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- TABLE : inv_alertes
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inv_alertes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id      INT UNSIGNED NOT NULL,
    type            ENUM('stock_min','stock_critique','maintenance_due',
                         'garantie_expiration','amortissement_fin') NOT NULL,
    valeur_seuil    DECIMAL(12,2) DEFAULT NULL,
    valeur_actuelle DECIMAL(12,2) DEFAULT NULL,
    statut          ENUM('active','acquittee','resolue') NOT NULL DEFAULT 'active',
    acquittee_by    INT UNSIGNED DEFAULT NULL,
    acquittee_at    DATETIME DEFAULT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_inv_alerte_art FOREIGN KEY (article_id) REFERENCES inv_articles(id) ON DELETE CASCADE,
    INDEX idx_inv_alerte_type (type),
    INDEX idx_inv_alerte_statut (statut),
    INDEX idx_inv_alerte_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. ARCHITECTURE DES SERVICES (10 services)

```
ArticleService
├── ajouterArticle(ArticleDTO, int userId, int etab): int
├── modifierArticle(int id, ArticleDTO, int userId): void
├── archiverArticle(int id, int userId): void
├── trouver(int id): ?array
├── lister(ArticleFiltersDTO, int etab): array
├── genererReference(int etab): string
├── genererBarcode(int id): string              — délègue BarcodeInterface
└── genererQrCode(int id): string               — délègue QrCodeInterface

FournisseurService
├── ajouter(FournisseurDTO, int userId, int etab): int
├── modifier(int id, FournisseurDTO, int userId): void
├── bloquer(int id, int userId): void
├── lister(int etab): array
└── trouver(int id): ?array

CommandeService
├── creerCommande(CommandeDTO, int userId, int etab): int
├── ajouterLigne(int commandeId, CommandeLigneDTO): void
├── validerCommande(int id, int userId): void       — dispatche CommandeValidee → Finance
├── envoyerFournisseur(int id): void
├── annulerCommande(int id, int userId): void
├── calculerTotaux(int id): void
└── lister(int etab, ?string statut): array

ReceptionService
├── recevoirLivraison(ReceptionDTO, int userId, int etab): int
│     → crée inv_receptions + inv_reception_lignes
│     → met à jour quantite_recue sur commande_lignes
│     → met à jour statut commande (partiellement_recue / recue)
│     → délègue StockService::entree() pour chaque ligne
└── historiqueCommande(int commandeId): array

StockService
├── entree(int articleId, decimal qte, int emplacementId, string refType, int refId, int userId): void
│     → upsert inv_stocks + insert inv_mouvements(entree)
├── sortie(int articleId, decimal qte, int emplacementId, string refType, int refId, int userId): void
│     → update inv_stocks + insert inv_mouvements(sortie)
│     → vérifie stock suffisant (lève exception sinon)
├── transferer(int articleId, decimal qte, int src, int dst, int userId): void
│     → sortie(src) + entree(dst) + mouvements(transfert)
├── ajuster(int articleId, decimal qteNouvelle, int emplacementId, int userId, string motif): void
│     → calcule écart + insert inv_mouvements(ajustement)
├── stockTotal(int articleId, int etab): decimal
├── stockParEmplacement(int articleId, int etab): array
└── detecterAlertes(int etab): void
      → compare stock à seuil_alerte / seuil_critique
      → dispatche StockAlerte si nouveau dépassement

AffectationService
├── affecter(AffectationDTO, int userId): int
│     → sort du stock (type=affectation)
│     → insert inv_affectations
│     → dispatche AffectationCreee
├── retourner(int id, int userId): void
│     → update inv_affectations statut=retournee
│     → rentre en stock (type=retour_affectation)
│     → dispatche AffectationRetournee
├── declarerPerdue(int id, int userId): void
│     → update statut=perdue → dispatche AffectationPerdue
├── listerParUser(int userId): array
└── listerActives(int etab): array

MaintenanceService
├── planifier(MaintenanceDTO, int userId, int etab): int
├── commencer(int id, int userId): void
├── terminer(int id, string rapport, ?decimal cout, int userId): void
│     → dispatche MaintenanceTerminee → Documents (rapport)
├── annuler(int id, int userId): void
└── prochaines(int etab, int jours = 30): array

InventairePhysiqueService
├── demarrer(InventaireDTO, int userId, int etab): int
│     → génère les lignes théoriques depuis inv_stocks
├── compterArticle(int sessionId, int articleId, int emplacementId, decimal qte, int userId): void
├── validerLigne(int ligneId, int userId): void
├── terminer(int sessionId, int userId): array       — retourne rapport écarts
│     → ajuste stocks via StockService::ajuster()
│     → dispatche InventaireTermine
└── rapport(int sessionId): array

AlerteService
├── detecter(int etab): int                          — cron, retourne nb alertes créées
├── acquitter(int id, int userId): void
├── resoudre(int id, int userId): void
└── listerActives(int etab): array

AmortissementService (stub V3)
├── creer(int articleId, AmortissementDTO): int
├── calculerVNC(int articleId): decimal              — valeur nette comptable
├── calculerTableau(int articleId): array            — tableau annuel
└── calculerTous(int etab): void                     — batch → dispatche AmortissementCalcule → Finance
```

---

## 4. REPOSITORIES (10 repositories)

| Repository | Méthodes clés |
|-----------|---------------|
| `ArticleRepository` | `insert`, `update`, `softDelete`, `findById`, `findByRef`, `search(FiltersDTO)`, `suggestions`, `findBelowSeuil(etab)` |
| `FournisseurRepository` | `insert`, `update`, `softDelete`, `findById`, `findAll(etab)`, `findByStatut` |
| `CommandeRepository` | `insert`, `update`, `findById`, `findAll(etab, ?statut)`, `updateStatut`, `updateTotaux` |
| `CommandeLigneRepository` | `insert`, `findByCommande`, `updateQteRecue` |
| `ReceptionRepository` | `insert`, `findByCommande` |
| `StockRepository` | `upsert(articleId, emplacementId, delta)`, `findByArticle(etab)`, `totalArticle(etab)`, `findBelowSeuil(etab)` |
| `MouvementRepository` | `insert`, `findByArticle`, `findByReference`, `historiqueUser` |
| `AffectationRepository` | `insert`, `update`, `findById`, `findByUser`, `findActives(etab)`, `countActiveUser` |
| `MaintenanceRepository` | `insert`, `update`, `findById`, `findByArticle`, `findProchaines(etab, jours)` |
| `InventaireRepository` | `insert`, `update`, `findById`, `insertLigne`, `updateLigne`, `findLignes(sessionId)`, `findEcarts` |
| `AlerteRepository` | `insert`, `update`, `findActives(etab)`, `existeActive(articleId, type)` |
| `AmortissementRepository` | `insert`, `update`, `findByArticle`, `findAll(etab)` |

---

## 5. DTOs (10 DTOs)

```php
ArticleDTO {
    string $reference
    string $designation
    ?string $description
    ?int $categorieId
    string $type   // consommable | durable | equipement
    string $uniteMesure
    float $seuilAlerte
    float $seuilCritique
    float $valeurUnitaire
    ?int $fournisseurId
    ?string $image
    ?int $localisationDefaut
    int $garantieMois
    static fromRequest(array): self
}

ArticleFiltersDTO {
    ?string $terme
    ?string $type
    ?int $categorieId
    ?int $fournisseurId
    bool $actifSeulement = true
    bool $enAlerteSeulement = false
    string $tri = 'designation'
    int $page = 1
    int $perPage = 30
    static fromRequest(array): self
}

FournisseurDTO {
    string $nom; ?string $code; ?string $email; ?string $telephone
    ?string $adresse; ?string $siteWeb; ?string $rib
    int $delaiLivraisonJ; ?string $conditionsPaiement
    static fromRequest(array): self
}

CommandeDTO {
    int $fournisseurId
    string $dateCommande
    ?string $dateLivraisonPrevue
    float $tvaTaux
    ?string $notes
    array $lignes   // CommandeLigneDTO[]
    static fromRequest(array): self
}

CommandeLigneDTO {
    int $articleId
    float $quantiteCommandee
    float $prixUnitaireHt
    float $tvaTaux
    ?string $notes
    static fromArray(array): self
}

ReceptionDTO {
    int $commandeId
    string $dateReception
    ?string $bonLivraison
    ?string $notes
    array $lignes   // [{commande_ligne_id, article_id, quantite_recue, emplacement_id}]
    static fromRequest(array): self
}

AffectationDTO {
    int $articleId
    int $userId
    float $quantite
    string $dateAffectation
    ?string $dateRetourPrevue
    ?int $emplacementId
    ?string $notes
    int $etablissementId
    static fromRequest(array): self
}

MaintenanceDTO {
    int $articleId
    string $type
    string $datePlanifiee
    ?string $prestataire
    ?string $description
    int $etablissementId
    static fromRequest(array): self
}

InventaireDTO {
    string $nom
    ?string $description
    string $dateDebut
    int $etablissementId
    static fromRequest(array): self
}

AmortissementDTO {
    float $valeurAchat
    string $dateAchat
    int $dureeAmortissementMois
    string $methode  // lineaire | degressif
    float $valeurResiduelle
    static fromRequest(array): self
}
```

---

## 6. ÉVÉNEMENTS (20 événements)

```
ArticleAjoute(articleId, reference, designation, userId, etablissementId)
ArticleModifie(articleId, designation, userId)
ArticleArchive(articleId, designation, userId)

FournisseurAjoute(fournisseurId, nom, userId, etablissementId)
FournisseurBloque(fournisseurId, nom, userId)

CommandeCreee(commandeId, fournisseurId, totalHt, userId, etablissementId)
CommandeValidee(commandeId, fournisseurId, totalTtc, userId, etablissementId)
CommandeRecue(commandeId, fournisseurId, receptionId, etablissementId)

StockEntree(articleId, quantite, emplacementId, refType, refId, userId, etablissementId)
StockSortie(articleId, quantite, emplacementId, refType, refId, userId, etablissementId)
StockTransfert(articleId, quantite, srcId, dstId, userId, etablissementId)
StockAjustement(articleId, ancienneQte, nouvelleQte, emplacementId, userId, etablissementId)
StockAlerte(articleId, type, valeurActuelle, seuil, etablissementId)

AffectationCreee(affectationId, articleId, userId, quantite, etablissementId)
AffectationRetournee(affectationId, articleId, userId, etablissementId)
AffectationPerdue(affectationId, articleId, userId, etablissementId)

MaintenanceCreee(maintenanceId, articleId, type, datePlanifiee, etablissementId)
MaintenanceTerminee(maintenanceId, articleId, cout, rapport, etablissementId)

InventaireTermine(inventaireId, nom, nbEcarts, nbAjustements, createdBy, etablissementId)

AmortissementCalcule(articleId, valeurNette, montantDotation, etablissementId)
```

---

## 7. LISTENERS (5 listeners)

### AuditListener
Écoute les 20 événements → `AuditService::log/logCreate` pour chaque action.

### FinanceIntegrationListener
| Événement | Action Finance |
|-----------|----------------|
| `CommandeValidee` | Crée bon de commande + pré-facture fournisseur dans module Finance |
| `CommandeRecue` | Marque facture fournisseur comme "reçue" + déclenche paiement |
| `AmortissementCalcule` | Crée écriture comptable dotation amortissement |
| `AffectationPerdue` | Crée note de débit si valeur article > 0 |

### RHIntegrationListener
| Événement | Action RH |
|-----------|-----------|
| `AffectationCreee` | Lie l'affectation à l'employé RH (cross-référence) |
| `AffectationRetournee` | Met à jour fiche employé |

### DocumentsIntegrationListener
| Événement | Action Documents |
|-----------|-----------------|
| `CommandeValidee` | Crée dossier documents pour la commande |
| `MaintenanceTerminee` | Archive le rapport de maintenance dans Documents |

### CommunicationListener
| Événement | Notification |
|-----------|-------------|
| `StockAlerte` | Notification responsable stock / admin |
| `MaintenanceCreee` | Notification responsable article |
| `AffectationCreee` | Notification utilisateur affecté |

---

## 8. POLICIES (3 policies)

### InventairePolicy
```php
canView(array $user): bool                  // biblio.view
canSearch(array $user): bool
canManageArticles(array $user): bool
canManageFournisseurs(array $user): bool
canManageCommandes(array $user): bool
canManageStocks(array $user): bool
canManageAffectations(array $user): bool
canMaintenance(array $user): bool
canInventaire(array $user): bool
canAnalytics(array $user): bool
canAdmin(array $user): bool
```

### CommandePolicy
```php
canValider(array $user, array $commande): bool
    // Règle : seul admin/directeur peut valider; commande doit être en statut 'brouillon'
canAnnuler(array $user, array $commande): bool
    // Règle : annulation possible seulement si statut < 'recue'
```

### AffectationPolicy
```php
canAffecter(array $user, array $article, int $stockDisponible): bool
    // Règle : stock > quantite demandée; article actif
canRetourner(array $user, array $affectation): bool
    // Règle : affectation en_cours; user = owner OU gestionnaire
```

---

## 9. RBAC — 12 PERMISSIONS

```php
'inventaire.view'               // Consulter articles et stocks
'inventaire.search'             // Rechercher dans le catalogue
'inventaire.manage_articles'    // CRUD articles + catégories + fournisseurs
'inventaire.manage_commandes'   // Créer/valider commandes fournisseurs
'inventaire.manage_stocks'      // Mouvements manuels, ajustements, transferts
'inventaire.manage_affectations'// Affecter/retourner matériel
'inventaire.manage_maintenance' // Planifier/clôturer maintenances
'inventaire.inventaire_physique'// Sessions inventaire physique
'inventaire.amortissement'      // Voir/calculer amortissements
'inventaire.analytics'          // Tableaux de bord et rapports
'inventaire.export'             // Export CSV/PDF
'inventaire.admin'              // Configuration module
```

### Matrice Rôles × Permissions

| Permission | admin | directeur | comptable | secretaire | enseignant | parent | eleve |
|-----------|:-----:|:---------:|:---------:|:----------:|:----------:|:------:|:-----:|
| view | ✓ | ✓ | ✓ | ✓ | — | — | — |
| search | ✓ | ✓ | ✓ | ✓ | — | — | — |
| manage_articles | ✓ | ✓ | — | ✓ | — | — | — |
| manage_commandes | ✓ | ✓ | ✓ | — | — | — | — |
| manage_stocks | ✓ | ✓ | — | ✓ | — | — | — |
| manage_affectations | ✓ | ✓ | — | ✓ | — | — | — |
| manage_maintenance | ✓ | ✓ | — | ✓ | — | — | — |
| inventaire_physique | ✓ | ✓ | — | ✓ | — | — | — |
| amortissement | ✓ | ✓ | ✓ | — | — | — | — |
| analytics | ✓ | ✓ | ✓ | — | — | — | — |
| export | ✓ | ✓ | ✓ | ✓ | — | — | — |
| admin | ✓ | — | — | — | — | — | — |

---

## 10. ROUTES (62 routes)

```
/* ── Articles ── */
GET    /v2/inventaire/articles                     ArticleController::index
GET    /v2/inventaire/articles/search              ArticleController::search
GET    /v2/inventaire/articles/create              ArticleController::create
POST   /v2/inventaire/articles                     ArticleController::store
GET    /v2/inventaire/articles/{id}                ArticleController::show
GET    /v2/inventaire/articles/{id}/edit           ArticleController::edit
POST   /v2/inventaire/articles/{id}/update         ArticleController::update
POST   /v2/inventaire/articles/{id}/archive        ArticleController::archive
GET    /v2/inventaire/articles/{id}/barcode        ArticleController::barcode
GET    /v2/inventaire/articles/{id}/qrcode         ArticleController::qrCode
GET    /v2/inventaire/articles/{id}/stock          ArticleController::stock
GET    /v2/inventaire/articles/{id}/historique     ArticleController::historique

/* ── Catégories ── */
GET    /v2/inventaire/categories                   CategorieController::index
POST   /v2/inventaire/categories                   CategorieController::store
POST   /v2/inventaire/categories/{id}/update       CategorieController::update
POST   /v2/inventaire/categories/{id}/archive      CategorieController::archive

/* ── Fournisseurs ── */
GET    /v2/inventaire/fournisseurs                 FournisseurController::index
GET    /v2/inventaire/fournisseurs/create          FournisseurController::create
POST   /v2/inventaire/fournisseurs                 FournisseurController::store
GET    /v2/inventaire/fournisseurs/{id}            FournisseurController::show
GET    /v2/inventaire/fournisseurs/{id}/edit       FournisseurController::edit
POST   /v2/inventaire/fournisseurs/{id}/update     FournisseurController::update
POST   /v2/inventaire/fournisseurs/{id}/bloquer    FournisseurController::bloquer

/* ── Commandes ── */
GET    /v2/inventaire/commandes                    CommandeController::index
GET    /v2/inventaire/commandes/create             CommandeController::create
POST   /v2/inventaire/commandes                    CommandeController::store
GET    /v2/inventaire/commandes/{id}               CommandeController::show
GET    /v2/inventaire/commandes/{id}/edit          CommandeController::edit
POST   /v2/inventaire/commandes/{id}/update        CommandeController::update
POST   /v2/inventaire/commandes/{id}/valider       CommandeController::valider
POST   /v2/inventaire/commandes/{id}/envoyer       CommandeController::envoyer
POST   /v2/inventaire/commandes/{id}/annuler       CommandeController::annuler

/* ── Réceptions ── */
GET    /v2/inventaire/receptions/create/{commandeId} ReceptionController::create
POST   /v2/inventaire/receptions/{commandeId}      ReceptionController::store
GET    /v2/inventaire/receptions/{id}              ReceptionController::show

/* ── Stocks ── */
GET    /v2/inventaire/stocks                       StockController::index
GET    /v2/inventaire/stocks/alertes               StockController::alertes
POST   /v2/inventaire/stocks/entree                StockController::entree
POST   /v2/inventaire/stocks/sortie                StockController::sortie
POST   /v2/inventaire/stocks/transfert             StockController::transfert
POST   /v2/inventaire/stocks/ajustement            StockController::ajustement
GET    /v2/inventaire/stocks/mouvements            StockController::mouvements

/* ── Affectations ── */
GET    /v2/inventaire/affectations                 AffectationController::index
GET    /v2/inventaire/affectations/create          AffectationController::create
POST   /v2/inventaire/affectations                 AffectationController::store
GET    /v2/inventaire/affectations/{id}            AffectationController::show
POST   /v2/inventaire/affectations/{id}/retourner  AffectationController::retourner
POST   /v2/inventaire/affectations/{id}/perdu      AffectationController::declarerPerdu
GET    /v2/inventaire/affectations/user/{userId}   AffectationController::parUser

/* ── Maintenances ── */
GET    /v2/inventaire/maintenances                 MaintenanceController::index
GET    /v2/inventaire/maintenances/create          MaintenanceController::create
POST   /v2/inventaire/maintenances                 MaintenanceController::store
GET    /v2/inventaire/maintenances/{id}            MaintenanceController::show
POST   /v2/inventaire/maintenances/{id}/commencer  MaintenanceController::commencer
POST   /v2/inventaire/maintenances/{id}/terminer   MaintenanceController::terminer
POST   /v2/inventaire/maintenances/{id}/annuler    MaintenanceController::annuler

/* ── Inventaires physiques ── */
GET    /v2/inventaire/inventaires-physiques        InvPhysiqueController::index
POST   /v2/inventaire/inventaires-physiques        InvPhysiqueController::store
GET    /v2/inventaire/inventaires-physiques/{id}   InvPhysiqueController::show
POST   /v2/inventaire/inventaires-physiques/{id}/compter  InvPhysiqueController::compter
POST   /v2/inventaire/inventaires-physiques/{id}/terminer InvPhysiqueController::terminer
GET    /v2/inventaire/inventaires-physiques/{id}/rapport  InvPhysiqueController::rapport

/* ── Alertes ── */
GET    /v2/inventaire/alertes                      AlerteController::index
POST   /v2/inventaire/alertes/{id}/acquitter       AlerteController::acquitter
GET    /v2/inventaire/alertes/cron/detecter        AlerteController::cron

/* ── Analytics ── */
GET    /v2/inventaire/analytics                    AnalyticsController::dashboard
GET    /v2/inventaire/analytics/export             AnalyticsController::export
GET    /v2/inventaire/analytics/amortissements     AnalyticsController::amortissements
```

---

## 11. CONTROLLERS (11 controllers)

| Controller | Actions |
|-----------|---------|
| `ArticleController` | 12 actions |
| `CategorieController` | 4 actions |
| `FournisseurController` | 7 actions |
| `CommandeController` | 9 actions |
| `ReceptionController` | 3 actions |
| `StockController` | 7 actions |
| `AffectationController` | 7 actions |
| `MaintenanceController` | 7 actions |
| `InvPhysiqueController` | 6 actions |
| `AlerteController` | 3 actions |
| `InventaireAnalyticsController` | 3 actions |

---

## 12. INTERFACES & CONTRATS

### BarcodeInterface (réutilisation pattern Bibliothèque)
```php
interface BarcodeInterface {
    public function generate(string $data, string $format = 'CODE128'): string; // base64 PNG
    public function decodeFromString(string $barcode): ?string;
}
```

### QrCodeInterface
```php
interface QrCodeInterface {
    public function generate(array $data, int $size = 200): string; // base64 PNG
    public function decode(string $qrCode): ?array;
}
```

### AmortissementInterface (stub V3)
```php
interface AmortissementInterface {
    public function calculerDotationAnnuelle(float $valeurAchat, int $dureeAns, string $methode): float;
    public function calculerVNC(float $valeurAchat, float $dotationsAccumulees): float;
    public function tableauComplet(array $parametres): array;
}
```

### StockMovementInterface
```php
interface StockMovementInterface {
    public function entree(int $articleId, float $quantite, int $emplacementId, array $context): void;
    public function sortie(int $articleId, float $quantite, int $emplacementId, array $context): void;
    public function transfert(int $articleId, float $quantite, int $src, int $dst, array $context): void;
}
```

---

## 13. INTÉGRATIONS INTER-MODULES

### 13.1 Finance
```
CommandeValidee   →  FinanceIntegrationListener
                  →  Crée invoice_draft fournisseur (module Finance)
                  →  Référence croisée: commande.facture_finance_id

CommandeRecue     →  FinanceIntegrationListener
                  →  Valide la facture fournisseur (statut 'reçu')

AmortissementCalcule → FinanceIntegrationListener
                  →  Crée écriture comptable: 68 Dotations / 28 Amortissements

AffectationPerdue →  FinanceIntegrationListener (si valeur_unitaire > 0)
                  →  Crée note de débit utilisateur (optionnel)
```

### 13.2 RH
```
AffectationCreee  →  RHIntegrationListener
                  →  Ajoute l'article dans le profil équipement de l'employé RH
                  →  Endpoint: RH\Services\EmployeService::ajouterEquipement()

AffectationRetournee → RHIntegrationListener
                  →  Retire l'article du profil employé
```

### 13.3 Documents
```
CommandeValidee   →  DocumentsIntegrationListener
                  →  Crée dossier Documents pour la commande

MaintenanceTerminee → DocumentsIntegrationListener
                  →  Archive le rapport PDF dans Documents
                  →  Tags: ['maintenance', 'inventaire', article_id]
```

### 13.4 Communication
```
StockAlerte       →  CommunicationListener
                  →  Notification push: "Stock critique : [article]"
                  →  Destinataire: rôle inventaire.manage_stocks

MaintenanceCreee  →  CommunicationListener
                  →  Notification: "Maintenance planifiée : [article] le [date]"

AffectationCreee  →  CommunicationListener
                  →  Notification: "Matériel affecté : [article]"
                  →  Destinataire: utilisateur affecté
```

### 13.5 Bibliothèque (cross-référence architecturale)
- Articles de type `durable` représentant des livres → clé `biblio_ouvrage_id` (optionnel en V3)
- Partage du pattern `BarcodeInterface` et `QrCodeInterface`
- Pas d'intégration directe V2 (modules indépendants)

---

## 14. MODÈLES MÉTIER

### InvArticleModel
```php
class InvArticleModel {
    public static function valeurStockTotal(array $stocks): float
    public static function estEnAlerte(float $stockTotal, float $seuilAlerte): bool
    public static function estCritique(float $stockTotal, float $seuilCritique): bool
    public static function referenceAuto(int $etab, int $sequence): string
    public static function labelType(string $type): string
}
```

### InvCommandeModel
```php
class InvCommandeModel {
    public static function calculerTotalHt(array $lignes): float
    public static function calculerTotalTtc(float $totalHt, float $tvaTaux): float
    public static function numeroAuto(int $etab, int $annee, int $sequence): string
    public static function peutEtreAnnulee(string $statut): bool
    public static function peutEtreValidee(string $statut): bool
}
```

### InvAmortissementModel
```php
class InvAmortissementModel {
    public static function dotationLineaire(float $valeur, int $dureeAns): float
    public static function vnc(float $valeurAchat, float $dotationsAccumulees, float $residuelle): float
    public static function tableauLineaire(float $valeur, int $dureeAns, float $residuelle): array
}
```

---

## 15. ANALYTICS

### Dashboard KPIs
```
- Valeur totale stock (SUM articles × stocks)
- Nombre articles en alerte / critique
- Commandes en attente / en cours
- Affectations actives
- Maintenances planifiées 30 prochains jours
- Taux de rotation stock (mouvements / stock moyen)
- Top 10 articles les plus mouvementés
- Évolution stock par mois (graphe)
- Articles sans mouvement depuis 90 jours
```

### Exports
- CSV : liste articles + stocks
- PDF : bon de commande, rapport inventaire physique, fiche article
- JSON : API publique

---

## 16. PRÉPARATION SAAS / MULTI-ÉTABLISSEMENTS / MOBILE / API

### SaaS & Multi-établissements
- `etablissement_id` sur toutes les tables sans exception
- Toutes les requêtes filtrées par `etablissement_id`
- `module.json` avec `enabled: false` par défaut

### API Publique (V3)
```
GET  /api/v1/inventaire/articles          → lister avec filtres
GET  /api/v1/inventaire/articles/{id}     → détail article
GET  /api/v1/inventaire/stocks            → niveaux de stock
POST /api/v1/inventaire/mouvements        → enregistrer mouvement (clé API)
```
Headers: `X-API-Key`, `X-Etablissement-Id`, `Content-Type: application/json`

### Application Mobile
- Endpoints `POST /v2/inventaire/articles/{id}/scan` (lecture barcode)
- `GET /v2/inventaire/articles/scan/{barcode}` → find by barcode
- `POST /v2/inventaire/inventaires-physiques/{id}/compter` → offline-capable
- Réponses JSON systématiques sur les endpoints de scan

### Barcode / QR Architecture
```
BarcodeService::generate(reference, format='CODE128') → PNG base64
QrCodeService::generate(['article_id' => x, 'ref' => 'xxx', 'etab' => y]) → PNG base64
Route GET /v2/inventaire/articles/{id}/barcode → image PNG
Route GET /v2/inventaire/articles/{id}/qrcode  → image PNG
Scan: POST /v2/inventaire/articles/scan → {'barcode':'...'} → article JSON
```

---

## 17. PLAN D'IMPLÉMENTATION — 7 SOUS-PHASES

| Phase | Domaine | Tables | Services | Events |
|-------|---------|--------|----------|--------|
| **10.2** | Articles + Catégories + Fournisseurs + Emplacements | 4 | ArticleService, FournisseurService | 5 |
| **10.3** | Achats — Commandes + Réceptions | 4 | CommandeService, ReceptionService | 5 |
| **10.4** | Stocks — Mouvements + Transferts + Ajustements | 1 | StockService | 5 |
| **10.5** | Affectations | 1 | AffectationService | 3 |
| **10.6** | Maintenance | 1 | MaintenanceService | 2 |
| **10.7** | Inventaire physique + Alertes + Amortissements + Analytics | 4 | InventairePhysiqueService, AlerteService, AmortissementService (stub) | 3 |
| **10.8** | Integration Review + Freeze | — | — | — |

---

## 18. DETTE TECHNIQUE PRÉVISIONNELLE

| ID | Description | Priorité V3 |
|----|-------------|-------------|
| DT-I-001 | AmortissementService stub — calcul réel non implémenté | Haute |
| DT-I-002 | BarcodeService / QrCodeService stubs | Haute |
| DT-I-003 | API publique REST non exposée | Haute |
| DT-I-004 | Race condition sur StockService::sortie (CHECK CONSTRAINT vs app) | Moyenne |
| DT-I-005 | nextSequence commandes via COUNT (pas gap-safe) | Faible |
| DT-I-006 | Lien Bibliothèque → article `biblio_ouvrage_id` non implémenté | Faible |

---

## 19. RÉCAPITULATIF

| Dimension | Valeur |
|-----------|--------|
| Tables | 15 (`inv_*`) |
| Services | 10 |
| Repositories | 12 |
| DTOs | 10 |
| Models | 3 |
| Policies | 3 |
| Interfaces | 4 |
| Events | 20 |
| Listeners | 5 |
| Controllers | 11 |
| Routes | 62 |
| Permissions | 12 |
| Intégrations | Finance, RH, Documents, Communication, Bibliothèque (arch.) |

---

*Blueprint produit — Phase 10.1 — SCOLARIS V2*  
*Aucune implémentation — 2026-07-03*
