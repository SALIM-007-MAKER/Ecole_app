# FINANCE BLUEPRINT V2 — ecole_app
**Phase 3.1 — Conception du Module Finance avant implémentation**
Date : 2026-07-01
Statut : BLUEPRINT — aucun fichier de code à modifier
Dépendances : Core ✓ | Shared Services ✓ | Event System ✓ | RBAC ✓ | Scolarité ✓

---

## 0. Contexte & analyse du V1

### 0.1 État du module Finance V1

**Tables existantes (à NE JAMAIS supprimer) :**

| Table V1 | Lignes typiques | Rôle |
|----------|----------------|------|
| `frais_types` | Types de frais (inscription, mensualité…) | Référentiel |
| `frais_eleves` | Affectation frais par élève/année | Facturation implicite |
| `paiements` | Encaissements | Paiements |
| `depenses_categories` | Catégories de dépenses | Référentiel |
| `depenses` | Dépenses de l'établissement | Décaissements |

**Fonctionnalités V1 :**
- ✅ Types de frais + affectation par classe/tous
- ✅ Paiements avec modes (espèces/chèque/virement/carte)
- ✅ Dépenses par catégorie
- ✅ Tableau de bord recettes/dépenses
- ✅ Impayés, caisse journalière, rapports CSV

**Gaps V1 (ce que V2 doit apporter) :**
- ❌ Pas de factures numérotées ni de reçus
- ❌ Pas de remises, exonérations, pénalités
- ❌ Pas d'échéanciers multi-échéances
- ❌ Pas d'annulation/remboursement formalisés
- ❌ Pas de trop-perçus
- ❌ Pas de fournisseurs
- ❌ Pas de workflow validation/approbation des dépenses
- ❌ Pas de gestion de caisse (ouverture/fermeture journalière formalisée)
- ❌ Pas de comptabilité formelle (journal, grand livre, balance)
- ❌ Pas de projections financières

### 0.2 Règle de migration

```
RÈGLE ABSOLUE : Ne JAMAIS utiliser DROP TABLE ou ALTER TABLE destructif
sur frais_types, frais_eleves, paiements, depenses, depenses_categories.

Les tables V2 sont créées avec le préfixe finance_ et coexistent avec
les tables V1. Les routes V1 (/comptabilite, /paiements, /depenses)
restent fonctionnelles jusqu'à migration complète validée.
```

---

## 1. Architecture du module

### 1.1 Structure de fichiers

```
app/Modules/Finance/
├── Contracts/
│   ├── FacturationInterface.php
│   ├── EncaissementInterface.php
│   └── FinanceReportInterface.php
│
├── Controllers/
│   ├── DashboardController.php
│   ├── FraisController.php
│   ├── FactureController.php
│   ├── PaiementController.php
│   ├── DecaissementController.php
│   ├── CaisseController.php
│   ├── ComptabiliteController.php
│   └── RapportController.php
│
├── DTO/
│   ├── FraisTypeDTO.php
│   ├── FraisTypeFiltersDTO.php
│   ├── FactureDTO.php
│   ├── LigneFactureDTO.php
│   ├── EcheanceDTO.php
│   ├── RemiseDTO.php
│   ├── PaiementDTO.php
│   ├── PaiementFiltersDTO.php
│   ├── DecaissementDTO.php
│   ├── DecaissementFiltersDTO.php
│   ├── FournisseurDTO.php
│   ├── CaisseSessionDTO.php
│   ├── MouvementCaisseDTO.php
│   └── FinanceReportDTO.php
│
├── Events/
│   ├── FraisTypeCreated.php
│   ├── FraisTypeUpdated.php
│   ├── FraisTypeArchived.php
│   ├── FactureCreated.php
│   ├── FactureEmise.php
│   ├── FactureAnnulee.php
│   ├── FactureRelancee.php
│   ├── PaiementCreated.php
│   ├── PaiementAnnule.php
│   ├── TropPercuGenere.php
│   ├── RemboursementCreated.php
│   ├── DecaissementCreated.php
│   ├── DecaissementValidated.php
│   ├── DecaissementApproved.php
│   ├── DecaissementRejected.php
│   ├── CaisseOuverte.php
│   ├── CaisseFermee.php
│   └── ExerciceOuvert.php
│
├── Listeners/
│   ├── FinanceAuditHandler.php
│   ├── FactureHandler.php
│   ├── PaiementHandler.php
│   ├── DecaissementHandler.php
│   └── CaisseHandler.php
│
├── Models/
│   ├── FraisTypeModel.php
│   ├── CategorieFraisModel.php
│   ├── FactureModel.php
│   ├── LigneFactureModel.php
│   ├── EcheanceModel.php
│   ├── RemiseModel.php
│   ├── PaiementModel.php
│   ├── RecuModel.php
│   ├── TropPercuModel.php
│   ├── FournisseurModel.php
│   ├── DecaissementModel.php
│   ├── JustificatifModel.php
│   ├── CaisseSessionModel.php
│   ├── MouvementCaisseModel.php
│   ├── CompteModel.php
│   └── EcritureComptableModel.php
│
├── Policies/
│   ├── FraisPolicy.php
│   ├── FacturePolicy.php
│   ├── PaiementPolicy.php
│   ├── DecaissementPolicy.php
│   ├── CaissePolicy.php
│   └── ComptabilitePolicy.php
│
├── Repositories/
│   ├── FraisRepository.php
│   ├── FactureRepository.php
│   ├── PaiementRepository.php
│   ├── DecaissementRepository.php
│   ├── CaisseRepository.php
│   ├── ComptabiliteRepository.php
│   └── FinanceReportRepository.php
│
├── Services/
│   ├── FraisService.php
│   ├── FacturationService.php
│   ├── EncaissementService.php
│   ├── DecaissementService.php
│   ├── CaisseService.php
│   ├── ComptabiliteService.php
│   ├── FinanceReportService.php
│   └── RelanceService.php
│
├── ValueObjects/
│   ├── MontantValue.php
│   ├── RemisePourcentageValue.php
│   ├── NumeroFactureValue.php
│   └── NumeroRecuValue.php
│
├── Views/
│   ├── dashboard/
│   │   └── index.php
│   ├── frais/
│   │   ├── index.php, form.php, show.php
│   ├── factures/
│   │   ├── index.php, form.php, show.php, print.php
│   ├── paiements/
│   │   ├── index.php, form.php, show.php, recu.php
│   ├── decaissements/
│   │   ├── index.php, form.php, show.php, valider.php
│   ├── caisse/
│   │   ├── index.php, ouvrir.php, fermer.php, journal.php
│   ├── comptabilite/
│   │   ├── journal.php, grand_livre.php, balance.php
│   └── rapports/
│       ├── index.php, recettes.php, depenses.php, tresorerie.php
│
├── module.json
└── routes.php
```

---

## 2. Schéma de la base de données V2

### 2.1 Référentiel des frais

```sql
-- Catégories de frais (plus riches que V1 depenses_categories)
CREATE TABLE IF NOT EXISTS finance_categories_frais (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    code        VARCHAR(20)   NOT NULL UNIQUE,  -- ex: SCOL, TRANSP, CANTINE
    nom         VARCHAR(100)  NOT NULL,
    description TEXT          NULL,
    couleur     VARCHAR(7)    NOT NULL DEFAULT '#6366f1',
    icone       VARCHAR(50)   NOT NULL DEFAULT 'currency-dollar',
    actif       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Types de frais V2 (enrichit frais_types V1 — ne le remplace pas)
CREATE TABLE IF NOT EXISTS finance_frais_types (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    categorie_id    INT UNSIGNED   NULL,
    code            VARCHAR(20)    NOT NULL UNIQUE,
    nom             VARCHAR(150)   NOT NULL,
    description     TEXT           NULL,
    montant_defaut  DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    periodicite     ENUM('unique','mensuel','trimestriel','semestriel','annuel') NOT NULL DEFAULT 'annuel',
    est_obligatoire TINYINT(1)     NOT NULL DEFAULT 1,
    niveaux_cibles  JSON           NULL COMMENT 'Liste de niveaux JSON, NULL = tous',
    annee_scolaire  VARCHAR(9)     NULL COMMENT 'NULL = applicable toutes années',
    peut_avoir_remise TINYINT(1)   NOT NULL DEFAULT 1,
    actif           TINYINT(1)     NOT NULL DEFAULT 1,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fft_categorie (categorie_id),
    KEY idx_fft_annee     (annee_scolaire),
    CONSTRAINT fk_fft_categorie FOREIGN KEY (categorie_id) REFERENCES finance_categories_frais(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tarifs par niveau/classe (surcharge du montant_defaut)
CREATE TABLE IF NOT EXISTS finance_tarifs (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    frais_type_id   INT UNSIGNED   NOT NULL,
    niveau          VARCHAR(50)    NULL COMMENT 'NULL = toutes classes du type',
    classe_id       INT UNSIGNED   NULL COMMENT 'NULL = tout le niveau',
    annee_scolaire  VARCHAR(9)     NOT NULL,
    montant         DECIMAL(12,2)  NOT NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tarif (frais_type_id, annee_scolaire, niveau, classe_id),
    CONSTRAINT fk_tarif_type   FOREIGN KEY (frais_type_id) REFERENCES finance_frais_types(id) ON DELETE CASCADE,
    CONSTRAINT fk_tarif_classe FOREIGN KEY (classe_id)     REFERENCES classes(id)             ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Règles d'exonération (qui peut être exonéré et pourquoi)
CREATE TABLE IF NOT EXISTS finance_regles_exoneration (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    frais_type_id   INT UNSIGNED   NOT NULL,
    code            VARCHAR(30)    NOT NULL,  -- ex: BOURSIER, PERSONNEL, ORPHELIN
    libelle         VARCHAR(150)   NOT NULL,
    remise_pct      DECIMAL(5,2)   NOT NULL DEFAULT 100.00, -- 100 = exonération totale
    justificatif_requis TINYINT(1) NOT NULL DEFAULT 1,
    actif           TINYINT(1)     NOT NULL DEFAULT 1,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_exo_type FOREIGN KEY (frais_type_id) REFERENCES finance_frais_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 Facturation

```sql
-- Factures (document émis par l'établissement à l'élève)
CREATE TABLE IF NOT EXISTS finance_factures (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    numero          VARCHAR(30)    NOT NULL UNIQUE,  -- Format: FCT-2025-0001
    eleve_id        INT UNSIGNED   NOT NULL,
    annee_scolaire  VARCHAR(9)     NOT NULL,
    date_emission   DATE           NOT NULL,
    date_echeance   DATE           NULL,
    montant_ht      DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    montant_remise  DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    montant_penalite DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    montant_total   DECIMAL(12,2)  NOT NULL DEFAULT 0.00, -- HT - remise + pénalité
    montant_paye    DECIMAL(12,2)  NOT NULL DEFAULT 0.00, -- calculé depuis paiements
    statut          ENUM('brouillon','emise','partiellement_payee','payee','annulee','en_retard') NOT NULL DEFAULT 'brouillon',
    note            TEXT           NULL,
    emise_par       INT UNSIGNED   NULL,
    annulee_par     INT UNSIGNED   NULL,
    date_annulation DATE           NULL,
    motif_annulation TEXT          NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fact_eleve   (eleve_id, annee_scolaire),
    KEY idx_fact_statut  (statut, date_echeance),
    KEY idx_fact_annee   (annee_scolaire),
    CONSTRAINT fk_fact_eleve    FOREIGN KEY (eleve_id)   REFERENCES eleves(id) ON DELETE RESTRICT,
    CONSTRAINT fk_fact_emetteur FOREIGN KEY (emise_par)  REFERENCES users(id)  ON DELETE SET NULL,
    CONSTRAINT fk_fact_annuleur FOREIGN KEY (annulee_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lignes de facture (1 ligne = 1 type de frais)
CREATE TABLE IF NOT EXISTS finance_lignes_facture (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    facture_id      INT UNSIGNED   NOT NULL,
    frais_type_id   INT UNSIGNED   NULL,
    libelle         VARCHAR(200)   NOT NULL,
    quantite        DECIMAL(6,2)   NOT NULL DEFAULT 1.00,
    montant_unitaire DECIMAL(12,2) NOT NULL,
    montant_remise  DECIMAL(12,2)  NOT NULL DEFAULT 0.00, -- remise sur cette ligne
    montant_total   DECIMAL(12,2)  NOT NULL, -- (quantite * unitaire) - remise_ligne
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lf_facture (facture_id),
    CONSTRAINT fk_lf_facture    FOREIGN KEY (facture_id)    REFERENCES finance_factures(id)    ON DELETE CASCADE,
    CONSTRAINT fk_lf_frais_type FOREIGN KEY (frais_type_id) REFERENCES finance_frais_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Remises appliquées à une facture
CREATE TABLE IF NOT EXISTS finance_remises (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    facture_id      INT UNSIGNED   NOT NULL,
    libelle         VARCHAR(200)   NOT NULL,
    type_remise     ENUM('pourcentage','montant_fixe','exoneration') NOT NULL,
    valeur          DECIMAL(10,2)  NOT NULL, -- % ou montant selon type
    montant_calcule DECIMAL(12,2)  NOT NULL, -- montant final appliqué
    regle_exo_id    INT UNSIGNED   NULL,
    justificatif    VARCHAR(255)   NULL,     -- fichier pièce justificative
    accordee_par    INT UNSIGNED   NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_rem_facture FOREIGN KEY (facture_id)    REFERENCES finance_factures(id)          ON DELETE CASCADE,
    CONSTRAINT fk_rem_regle   FOREIGN KEY (regle_exo_id)  REFERENCES finance_regles_exoneration(id) ON DELETE SET NULL,
    CONSTRAINT fk_rem_auteur  FOREIGN KEY (accordee_par)  REFERENCES users(id)                      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Échéanciers (plan de paiement sur une facture)
CREATE TABLE IF NOT EXISTS finance_echeanciers (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    facture_id      INT UNSIGNED   NOT NULL,
    nb_echeances    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ech_facture (facture_id),
    CONSTRAINT fk_ech_facture FOREIGN KEY (facture_id) REFERENCES finance_factures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Échéances individuelles d'un échéancier
CREATE TABLE IF NOT EXISTS finance_echeances (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    echeancier_id   INT UNSIGNED   NOT NULL,
    numero_ordre    TINYINT UNSIGNED NOT NULL,
    date_echeance   DATE           NOT NULL,
    montant_du      DECIMAL(12,2)  NOT NULL,
    montant_paye    DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    statut          ENUM('en_attente','partiel','paye','en_retard','annule') NOT NULL DEFAULT 'en_attente',
    penalite        DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ech_date   (date_echeance, statut),
    CONSTRAINT fk_ech_echeancier FOREIGN KEY (echeancier_id) REFERENCES finance_echeanciers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.3 Encaissements

```sql
-- Paiements V2 (lie à une facture et optionnellement à une échéance)
CREATE TABLE IF NOT EXISTS finance_paiements (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    numero_recu     VARCHAR(30)    NOT NULL UNIQUE, -- Format: REC-2025-0001
    facture_id      INT UNSIGNED   NOT NULL,
    echeance_id     INT UNSIGNED   NULL,
    eleve_id        INT UNSIGNED   NOT NULL,
    annee_scolaire  VARCHAR(9)     NOT NULL,
    montant         DECIMAL(12,2)  NOT NULL,
    date_paiement   DATE           NOT NULL,
    mode_paiement   ENUM('especes','cheque','virement','carte','mobile_money') NOT NULL DEFAULT 'especes',
    reference       VARCHAR(150)   NULL,
    caisse_id       INT UNSIGNED   NULL,  -- session de caisse d'encaissement
    encaisse_par    INT UNSIGNED   NULL,
    note            TEXT           NULL,
    statut          ENUM('valide','annule') NOT NULL DEFAULT 'valide',
    annule_par      INT UNSIGNED   NULL,
    date_annulation DATE           NULL,
    motif_annulation TEXT          NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fp_facture  (facture_id),
    KEY idx_fp_eleve    (eleve_id, annee_scolaire),
    KEY idx_fp_date     (date_paiement, statut),
    KEY idx_fp_caisse   (caisse_id),
    CONSTRAINT fk_fp_facture    FOREIGN KEY (facture_id)  REFERENCES finance_factures(id)        ON DELETE RESTRICT,
    CONSTRAINT fk_fp_echeance   FOREIGN KEY (echeance_id) REFERENCES finance_echeances(id)       ON DELETE SET NULL,
    CONSTRAINT fk_fp_eleve      FOREIGN KEY (eleve_id)    REFERENCES eleves(id)                  ON DELETE RESTRICT,
    CONSTRAINT fk_fp_caisse     FOREIGN KEY (caisse_id)   REFERENCES finance_sessions_caisse(id) ON DELETE SET NULL,
    CONSTRAINT fk_fp_encaisseur FOREIGN KEY (encaisse_par) REFERENCES users(id)                  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trop-perçus (paiement > reste dû)
CREATE TABLE IF NOT EXISTS finance_trop_percus (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    paiement_id     INT UNSIGNED   NOT NULL,
    eleve_id        INT UNSIGNED   NOT NULL,
    montant         DECIMAL(12,2)  NOT NULL,
    statut          ENUM('en_attente','impute','rembourse') NOT NULL DEFAULT 'en_attente',
    impute_sur      INT UNSIGNED   NULL COMMENT 'facture_id sur laquelle imputé',
    rembourse_le    DATE           NULL,
    note            TEXT           NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_tp_paiement FOREIGN KEY (paiement_id) REFERENCES finance_paiements(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tp_eleve    FOREIGN KEY (eleve_id)    REFERENCES eleves(id)             ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Remboursements
CREATE TABLE IF NOT EXISTS finance_remboursements (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    trop_percu_id   INT UNSIGNED   NULL,
    eleve_id        INT UNSIGNED   NOT NULL,
    montant         DECIMAL(12,2)  NOT NULL,
    date_remboursement DATE        NOT NULL,
    mode            ENUM('especes','cheque','virement','carte','mobile_money') NOT NULL DEFAULT 'especes',
    reference       VARCHAR(150)   NULL,
    motif           TEXT           NOT NULL,
    traite_par      INT UNSIGNED   NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_rb_trop_percu FOREIGN KEY (trop_percu_id) REFERENCES finance_trop_percus(id) ON DELETE SET NULL,
    CONSTRAINT fk_rb_eleve      FOREIGN KEY (eleve_id)      REFERENCES eleves(id)              ON DELETE RESTRICT,
    CONSTRAINT fk_rb_traite     FOREIGN KEY (traite_par)    REFERENCES users(id)               ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.4 Décaissements

```sql
-- Fournisseurs
CREATE TABLE IF NOT EXISTS finance_fournisseurs (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    code            VARCHAR(20)    NOT NULL UNIQUE,
    nom             VARCHAR(150)   NOT NULL,
    contact         VARCHAR(100)   NULL,
    telephone       VARCHAR(30)    NULL,
    email           VARCHAR(191)   NULL,
    adresse         TEXT           NULL,
    iban            VARCHAR(50)    NULL,
    actif           TINYINT(1)     NOT NULL DEFAULT 1,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dépenses V2 (enrichies vs V1)
CREATE TABLE IF NOT EXISTS finance_decaissements (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    numero          VARCHAR(30)    NOT NULL UNIQUE, -- Format: DEP-2025-0001
    categorie_id    INT UNSIGNED   NULL,
    fournisseur_id  INT UNSIGNED   NULL,
    libelle         VARCHAR(255)   NOT NULL,
    montant         DECIMAL(12,2)  NOT NULL,
    date_depense    DATE           NOT NULL,
    date_echeance   DATE           NULL,
    mode_paiement   ENUM('especes','cheque','virement','carte','mobile_money') NOT NULL DEFAULT 'especes',
    reference       VARCHAR(150)   NULL,
    note            TEXT           NULL,
    statut          ENUM('brouillon','soumis','valide','approuve','paye','rejete','annule') NOT NULL DEFAULT 'brouillon',
    caisse_id       INT UNSIGNED   NULL,
    saisi_par       INT UNSIGNED   NULL,
    valide_par      INT UNSIGNED   NULL,
    date_validation DATE           NULL,
    approuve_par    INT UNSIGNED   NULL,
    date_approbation DATE          NULL,
    motif_rejet     TEXT           NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fd_date     (date_depense),
    KEY idx_fd_statut   (statut),
    KEY idx_fd_cat      (categorie_id),
    CONSTRAINT fk_fd_categorie    FOREIGN KEY (categorie_id)   REFERENCES depenses_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_fd_fournisseur  FOREIGN KEY (fournisseur_id) REFERENCES finance_fournisseurs(id) ON DELETE SET NULL,
    CONSTRAINT fk_fd_saisi        FOREIGN KEY (saisi_par)      REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_fd_valide       FOREIGN KEY (valide_par)     REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_fd_approuve     FOREIGN KEY (approuve_par)   REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_fd_caisse       FOREIGN KEY (caisse_id)      REFERENCES finance_sessions_caisse(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Justificatifs des dépenses
CREATE TABLE IF NOT EXISTS finance_justificatifs (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    decaissement_id INT UNSIGNED   NOT NULL,
    nom_fichier     VARCHAR(255)   NOT NULL,
    chemin          VARCHAR(500)   NOT NULL, -- relatif ROOT_PATH via UploadService
    mime_type       VARCHAR(100)   NOT NULL,
    taille          INT UNSIGNED   NOT NULL,
    uploade_par     INT UNSIGNED   NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_jus_dec    FOREIGN KEY (decaissement_id) REFERENCES finance_decaissements(id) ON DELETE CASCADE,
    CONSTRAINT fk_jus_auteur FOREIGN KEY (uploade_par)    REFERENCES users(id)                  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.5 Caisse

```sql
-- Sessions de caisse (ouverture / fermeture journalière)
CREATE TABLE IF NOT EXISTS finance_sessions_caisse (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    date_session    DATE           NOT NULL,
    solde_ouverture DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    solde_theorique DECIMAL(12,2)  NOT NULL DEFAULT 0.00, -- calculé à la fermeture
    solde_reel      DECIMAL(12,2)  NULL,                   -- saisi à la fermeture
    ecart           DECIMAL(12,2)  NULL,                   -- reel - theorique
    statut          ENUM('ouverte','fermee','rapprochee') NOT NULL DEFAULT 'ouverte',
    ouverte_par     INT UNSIGNED   NULL,
    fermee_par      INT UNSIGNED   NULL,
    note_fermeture  TEXT           NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_caisse_date (date_session),
    CONSTRAINT fk_caisse_ouvert FOREIGN KEY (ouverte_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_caisse_ferme  FOREIGN KEY (fermee_par)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mouvements de caisse (log de chaque entrée/sortie)
CREATE TABLE IF NOT EXISTS finance_mouvements_caisse (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    caisse_id       INT UNSIGNED   NOT NULL,
    type            ENUM('entree','sortie') NOT NULL,
    montant         DECIMAL(12,2)  NOT NULL,
    libelle         VARCHAR(255)   NOT NULL,
    reference_type  VARCHAR(50)    NULL,  -- 'paiement' | 'decaissement' | 'ajustement'
    reference_id    INT UNSIGNED   NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mc_caisse (caisse_id),
    CONSTRAINT fk_mc_caisse FOREIGN KEY (caisse_id) REFERENCES finance_sessions_caisse(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.6 Comptabilité (Phase 3.1.6 — optionnelle V2, requise V2.1)

```sql
-- Plan comptable simplifié (SYSCOHADA adapté contexte scolaire)
CREATE TABLE IF NOT EXISTS finance_comptes (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    code            VARCHAR(10)    NOT NULL UNIQUE,  -- ex: 411, 512, 604
    libelle         VARCHAR(200)   NOT NULL,
    type_compte     ENUM('actif','passif','charge','produit','capital') NOT NULL,
    parent_code     VARCHAR(10)    NULL,
    actif           TINYINT(1)     NOT NULL DEFAULT 1,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Exercices comptables
CREATE TABLE IF NOT EXISTS finance_exercices (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    annee_scolaire  VARCHAR(9)     NOT NULL UNIQUE,  -- ex: 2025-2026
    date_debut      DATE           NOT NULL,
    date_fin        DATE           NOT NULL,
    statut          ENUM('ouvert','cloture') NOT NULL DEFAULT 'ouvert',
    cloture_par     INT UNSIGNED   NULL,
    date_cloture    TIMESTAMP      NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_ex_cloture FOREIGN KEY (cloture_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Écritures comptables (journal)
CREATE TABLE IF NOT EXISTS finance_ecritures (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    exercice_id     INT UNSIGNED   NOT NULL,
    date_ecriture   DATE           NOT NULL,
    libelle         VARCHAR(255)   NOT NULL,
    compte_debit    VARCHAR(10)    NOT NULL,
    compte_credit   VARCHAR(10)    NOT NULL,
    montant         DECIMAL(12,2)  NOT NULL,
    reference_type  VARCHAR(50)    NULL,  -- 'paiement' | 'decaissement'
    reference_id    INT UNSIGNED   NULL,
    saisie_par      INT UNSIGNED   NULL,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ec_exercice (exercice_id, date_ecriture),
    KEY idx_ec_comptes  (compte_debit, compte_credit),
    CONSTRAINT fk_ec_exercice FOREIGN KEY (exercice_id) REFERENCES finance_exercices(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ec_saisie   FOREIGN KEY (saisie_par)  REFERENCES users(id)             ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.7 Résumé des tables V2

| Table | Domaine | Préfixe | Dépend de |
|-------|---------|---------|-----------|
| `finance_categories_frais` | Référentiel | ✅ finance_ | — |
| `finance_frais_types` | Référentiel | ✅ finance_ | finance_categories_frais |
| `finance_tarifs` | Référentiel | ✅ finance_ | finance_frais_types, classes |
| `finance_regles_exoneration` | Référentiel | ✅ finance_ | finance_frais_types |
| `finance_factures` | Facturation | ✅ finance_ | eleves (V1) |
| `finance_lignes_facture` | Facturation | ✅ finance_ | finance_factures, finance_frais_types |
| `finance_remises` | Facturation | ✅ finance_ | finance_factures, finance_regles_exoneration |
| `finance_echeanciers` | Facturation | ✅ finance_ | finance_factures |
| `finance_echeances` | Facturation | ✅ finance_ | finance_echeanciers |
| `finance_paiements` | Encaissement | ✅ finance_ | finance_factures, eleves, finance_sessions_caisse |
| `finance_trop_percus` | Encaissement | ✅ finance_ | finance_paiements, eleves |
| `finance_remboursements` | Encaissement | ✅ finance_ | finance_trop_percus, eleves |
| `finance_fournisseurs` | Décaissement | ✅ finance_ | — |
| `finance_decaissements` | Décaissement | ✅ finance_ | depenses_categories (V1!), finance_fournisseurs |
| `finance_justificatifs` | Décaissement | ✅ finance_ | finance_decaissements |
| `finance_sessions_caisse` | Caisse | ✅ finance_ | users |
| `finance_mouvements_caisse` | Caisse | ✅ finance_ | finance_sessions_caisse |
| `finance_comptes` | Comptabilité | ✅ finance_ | — |
| `finance_exercices` | Comptabilité | ✅ finance_ | users |
| `finance_ecritures` | Comptabilité | ✅ finance_ | finance_exercices |

**Total : 20 tables V2 — toutes préfixées `finance_` — aucune table V1 modifiée.**

---

## 3. Services — responsabilités

### 3.1 FraisService

**Responsabilité :** Gestion du référentiel (catégories, types, tarifs, règles d'exonération)

```
Méthodes :
+ creerCategorie(FraisTypeDTO) : int
+ modifierCategorie(int, FraisTypeDTO) : void
+ archiverCategorie(int) : void
+ creerTypeFrais(FraisTypeDTO) : int
+ modifierTypeFrais(int, FraisTypeDTO) : void
+ archiverTypeFrais(int) : void  -- garde si affectations existantes
+ definirTarif(int fraisTypeId, array params) : void
+ creerRegleExoneration(int fraisTypeId, array params) : int

Règles métier :
- Un type de frais avec des factures générées ne peut pas être supprimé, seulement archivé
- Le code d'un type de frais est unique et immuable
- Un tarif par niveau/classe surcharge le montant_defaut
```

### 3.2 FacturationService — implémente FacturationInterface

**Responsabilité :** Création et cycle de vie des factures

```
Méthodes :
+ genererFactureIndividuelle(int eleveId, array fraisTypeIds, string annee, ?int userId) : int
+ genererFacturesClasse(int classeId, string annee, ?int userId) : array  -- retourne [eleveId => factureId]
+ genererFacturesMasse(string annee, ?string niveau, ?int userId) : array
+ appliquerRemise(int factureId, RemiseDTO) : void
+ appliquerExoneration(int factureId, int regleId, ?string justificatif) : void
+ creerEcheancier(int factureId, int nbEcheances) : void
+ emettre(int factureId, int userId) : void  -- statut brouillon → emise
+ annuler(int factureId, int userId, string motif) : void
+ relancer(int factureId, int userId) : void  -- pour les impayés
+ recalculerMontants(int factureId) : void  -- recalcule après remise
+ calculerPenalites(int factureId) : float   -- délègue à AcademicCalculationService?

Règles métier :
- On ne peut pas modifier une facture à statut 'payee' ou 'annulee'
- Une annulation de facture annule également les paiements associés (statut)
- Le montant_total = Σ lignes - remises + pénalités
- Une facture ne peut être émise que si montant_total > 0
- La numérotation est séquentielle et non réutilisable : FCT-{annee}-{seq:04d}
- Un trop-perçu ne peut pas être appliqué à une facture annulée
```

### 3.3 EncaissementService — implémente EncaissementInterface

**Responsabilité :** Saisie et gestion des paiements

```
Méthodes :
+ enregistrerPaiement(PaiementDTO) : int
+ annulerPaiement(int paiementId, int userId, string motif) : void
+ imputerTropPercu(int tropPercuId, int factureId) : void
+ rembourserTropPercu(int tropPercuId, int userId, array params) : void
+ recalculerStatutFacture(int factureId) : void  -- PRIVATE, appelé après chaque paiement
+ recalculerStatutEcheance(int echeanceId) : void

Règles métier :
- Un paiement ne peut jamais être supprimé, seulement annulé (avec motif, traçabilité complète)
- Si montant paiement > reste dû → TropPercuGenere event
- Le numéro de reçu est séquentiel et unique : REC-{annee}-{seq:04d}
- Un paiement annulé recalcule immédiatement le statut de la facture
- Un paiement en espèces doit imputer la session de caisse ouverte du jour
- Si aucune caisse ouverte → avertissement (pas d'erreur bloquante en V2, bloquant en V2.1)
```

### 3.4 DecaissementService

**Responsabilité :** Gestion des dépenses avec workflow de validation

```
Méthodes :
+ soumettre(DecaissementDTO) : int
+ valider(int decId, int userId) : void    -- secretaire → comptable
+ approuver(int decId, int userId) : void  -- comptable → directeur
+ rejeter(int decId, int userId, string motif) : void
+ payer(int decId, int userId, array params) : void
+ annuler(int decId, int userId, string motif) : void
+ ajouterJustificatif(int decId, array file, int userId) : void  -- via UploadService

Workflow :
brouillon → soumis → valide → approuve → paye
                   ↘ rejete
                             ↘ annule

Règles métier :
- Un décaissement approuvé doit avoir au moins un justificatif (si montant > seuil)
- Un décaissement payé en espèces doit imputer la session de caisse du jour
- La numérotation : DEP-{annee}-{seq:04d}
- Seuil d'approbation directeur configurable (paramètre système)
```

### 3.5 CaisseService

**Responsabilité :** Sessions et mouvements de caisse

```
Méthodes :
+ ouvrirSession(float soldeInitial, int userId, ?string date) : int
+ fermerSession(int sessionId, float soldeReel, int userId, ?string note) : void
+ enregistrerMouvement(int sessionId, string type, float montant, string libelle, ...) : void
+ calculerSoldeTheorique(int sessionId) : float
+ rapprocher(int sessionId, int userId) : void

Règles métier :
- Une seule session peut être ouverte par journée
- La fermeture calcule le solde théorique (solde_ouverture + entrées - sorties)
- L'écart (solde_reel - solde_theorique) est enregistré et généré en alerte si > seuil
- Un rapprochement clôture définitivement la session (statut: rapprochee)
- Toute tentative d'ouverture si session ouverte → exception métier
```

### 3.6 ComptabiliteService

**Responsabilité :** Journal comptable et états financiers (Phase 3.1.6)

```
Méthodes :
+ creerEcriture(EcritureData) : int
+ genererEcritureDepuisPaiement(int paiementId) : void  -- auto sur PaiementCreated
+ genererEcritureDepuisDecaissement(int decId) : void   -- auto sur DecaissementApproved
+ getJournal(int exerciceId, ?DateRange) : array
+ getGrandLivre(string compte, int exerciceId) : array
+ getBalance(int exerciceId) : array
+ ouvrirExercice(string annee, int userId) : int
+ cloturerExercice(int exerciceId, int userId) : void   -- irréversible, droits admin

Règles métier :
- Chaque paiement génère automatiquement une écriture (débit 411, crédit 512)
- Chaque décaissement payé génère une écriture (débit 6XX, crédit 512)
- On ne peut pas modifier une écriture (journal immuable)
- Un exercice clôturé est figé
```

### 3.7 FinanceReportService — implémente FinanceReportInterface

**Responsabilité :** Tableaux de bord et rapports

```
Méthodes :
+ dashboardFinance(string annee) : array
+ recettesParPeriode(string annee, ?string granularite) : array
+ depensesParCategorie(string annee) : array
+ impayes(string annee, array filters) : array
+ tauxRecouvrement(string annee) : array
+ tresorerie(int sessionCaisseId) : array
+ projectionRecettes(string annee) : array   -- basé sur frais affectés non payés
+ projectionDepenses(string annee) : array   -- basé sur décaissements récurrents
+ exportCsv(string rapport, array filters) : string
+ exportPdf(string rapport, array filters) : string  -- HTML A4

Règles métier :
- Taux de recouvrement = montant_paye / montant_total_facture * 100
- Projection = montant restant dû sur factures non annulées
- Les stats excluent toujours les paiements annulés et les factures annulées
```

### 3.8 RelanceService

**Responsabilité :** Relances automatiques des impayés

```
Méthodes :
+ relancerImpayes(string annee, int delaiJours) : array  -- retourne [factureId]
+ genererLettreDRelance(int factureId) : string          -- HTML

Règles métier :
- Une relance n'est générée que si statut facture = 'en_retard' et date_echeance passée
- Notification parent via NotificationService::notify()
- Un délai minimum entre deux relances de la même facture (configurable)
```

---

## 4. Contracts (interfaces)

### 4.1 FacturationInterface

```php
interface FacturationInterface
{
    public function genererFactureIndividuelle(int $eleveId, array $fraisTypeIds, string $annee, ?int $userId): int;
    public function genererFacturesClasse(int $classeId, string $annee, ?int $userId): array;
    public function genererFacturesMasse(string $annee, ?string $niveau, ?int $userId): array;
    public function appliquerRemise(int $factureId, RemiseDTO $remise): void;
    public function emettre(int $factureId, int $userId): void;
    public function annuler(int $factureId, int $userId, string $motif): void;
}
```

### 4.2 EncaissementInterface

```php
interface EncaissementInterface
{
    public function enregistrerPaiement(PaiementDTO $dto): int;
    public function annulerPaiement(int $paiementId, int $userId, string $motif): void;
    public function imputerTropPercu(int $tropPercuId, int $factureId): void;
}
```

### 4.3 FinanceReportInterface

```php
interface FinanceReportInterface
{
    public function dashboardFinance(string $annee): array;
    public function impayes(string $annee, array $filters): array;
    public function tauxRecouvrement(string $annee): array;
    public function exportCsv(string $rapport, array $filters): string;
}
```

---

## 5. Value Objects

| Value Object | Règles de validation | Usage |
|-------------|---------------------|-------|
| `MontantValue` | > 0.00, max 2 décimales | Tout montant financier |
| `RemisePourcentageValue` | 0 < x ≤ 100 | Remises en % |
| `NumeroFactureValue` | regex FCT-YYYY-NNNN | Numéros de facture |
| `NumeroRecuValue` | regex REC-YYYY-NNNN | Numéros de reçu |

---

## 6. Événements & Handlers

### 6.1 Catalogue des événements

| Événement | Propriétés | Déclencheur |
|-----------|-----------|------------|
| `FraisTypeCreated` | fraisTypeId, nom, montant, createdById | FraisService::creerTypeFrais() |
| `FraisTypeUpdated` | fraisTypeId, avant, apres, updatedById | FraisService::modifierTypeFrais() |
| `FraisTypeArchived` | fraisTypeId, archivedById | FraisService::archiverTypeFrais() |
| `FactureCreated` | factureId, eleveId, montant, annee, createdById | FacturationService::genererFacture*() |
| `FactureEmise` | factureId, eleveId, montant, emiseById | FacturationService::emettre() |
| `FactureAnnulee` | factureId, eleveId, motif, annuleById | FacturationService::annuler() |
| `FactureRelancee` | factureId, eleveId, nbRelances, relanceById | RelanceService::relancerImpayes() |
| `PaiementCreated` | paiementId, factureId, eleveId, montant, mode, encaisseById | EncaissementService::enregistrerPaiement() |
| `PaiementAnnule` | paiementId, factureId, eleveId, montant, motif, annuleById | EncaissementService::annulerPaiement() |
| `TropPercuGenere` | tropPercuId, paiementId, eleveId, montant | EncaissementService::enregistrerPaiement() |
| `RemboursementCreated` | remboursementId, eleveId, montant, traiteById | EncaissementService::rembourserTropPercu() |
| `DecaissementCreated` | decId, montant, libelle, saisiById | DecaissementService::soumettre() |
| `DecaissementValidated` | decId, valideById | DecaissementService::valider() |
| `DecaissementApproved` | decId, approuveById, montant | DecaissementService::approuver() |
| `DecaissementRejected` | decId, rejeteById, motif | DecaissementService::rejeter() |
| `CaisseOuverte` | sessionId, soldeInitial, ouvertById, date | CaisseService::ouvrirSession() |
| `CaisseFermee` | sessionId, soldeTheorique, soldeReel, ecart, fermeById | CaisseService::fermerSession() |
| `ExerciceOuvert` | exerciceId, annee, ouvertById | ComptabiliteService::ouvrirExercice() |

**Total : 18 événements Finance**

### 6.2 Handlers

| Handler | Événements écoutés | Actions |
|---------|-------------------|---------|
| `FinanceAuditHandler` | Tous les 18 | AuditService::log() |
| `FactureHandler` | FactureEmise, FactureRelancee | NotificationService parent + updateStatut |
| `PaiementHandler` | PaiementCreated, TropPercuGenere | NotificationService parent + ComptabiliteService::genererEcriture() |
| `DecaissementHandler` | DecaissementValidated, DecaissementApproved | NotificationService comptable/directeur |
| `CaisseHandler` | CaisseOuverte, CaisseFermee | StatsCacheHandler::invalidate() |

---

## 7. RBAC — Permissions

### 7.1 Permissions Finance V2 (à ajouter dans config/permissions.php)

```
finance.dashboard.view            — Voir le tableau de bord financier
finance.frais.view                — Voir le référentiel des frais
finance.frais.manage              — Créer/modifier les types de frais et tarifs
finance.frais.admin               — Archiver et gérer les règles d'exonération
finance.factures.view             — Voir les factures
finance.factures.create           — Générer des factures individuelles
finance.factures.masse            — Générer des factures en masse (classe/tous)
finance.factures.emettre          — Émettre/relancer des factures
finance.factures.annuler          — Annuler des factures
finance.factures.remise           — Accorder des remises/exonérations
finance.paiements.view            — Voir les paiements
finance.paiements.view.own        — Voir uniquement ses propres paiements (parent)
finance.paiements.create          — Enregistrer un paiement
finance.paiements.annuler         — Annuler un paiement
finance.paiements.trop_percu      — Gérer les trop-perçus et remboursements
finance.decaissements.view        — Voir les dépenses
finance.decaissements.create      — Soumettre une dépense
finance.decaissements.valider     — Valider une dépense (niveau 1)
finance.decaissements.approuver   — Approuver une dépense (niveau 2 — directeur)
finance.decaissements.rejeter     — Rejeter une dépense
finance.caisse.view               — Voir la caisse
finance.caisse.ouvrir             — Ouvrir une session de caisse
finance.caisse.fermer             — Fermer une session de caisse
finance.caisse.rapprocher         — Rapprocher une session
finance.comptabilite.view         — Voir le journal comptable
finance.comptabilite.saisir       — Saisir des écritures manuelles
finance.comptabilite.exercice     — Ouvrir/clôturer un exercice
finance.rapports.view             — Voir les rapports
finance.rapports.export           — Exporter les données
```

**Total : 28 permissions Finance**

### 7.2 Attribution par rôle

| Rôle | Permissions Finance |
|------|-------------------|
| `admin` | Toutes (28) |
| `directeur` | dashboard.view, frais.view/manage, factures.view/create/masse/emettre/annuler/remise, paiements.view/create/annuler/trop_percu, decaissements.view/create/valider/approuver/rejeter, caisse.view/ouvrir/fermer/rapprocher, comptabilite.view/exercice, rapports.view/export |
| `secretaire` | dashboard.view, frais.view, factures.view/create, paiements.view/create, decaissements.view/create, caisse.view/ouvrir/fermer, rapports.view |
| `comptable` | dashboard.view, frais.view, factures.view/emettre, paiements.view/create/annuler/trop_percu, decaissements.view/valider/approuver, caisse.view/ouvrir/fermer/rapprocher, comptabilite.view/saisir, rapports.view/export |
| `parent` | paiements.view.own |
| `enseignant` | — |
| `eleve` | — |

---

## 8. Routes V2

```
Préfixe : /v2/finance
Namespace : Finance\Controllers\

GET    /v2/finance                                    → DashboardController@index
GET    /v2/finance/dashboard                          → DashboardController@index

# Référentiel frais
GET    /v2/finance/frais                              → FraisController@index
GET    /v2/finance/frais/create                       → FraisController@create
POST   /v2/finance/frais                              → FraisController@store
GET    /v2/finance/frais/{id}                         → FraisController@show
GET    /v2/finance/frais/{id}/edit                    → FraisController@edit
POST   /v2/finance/frais/{id}                         → FraisController@update
POST   /v2/finance/frais/{id}/archiver                → FraisController@archiver
GET    /v2/finance/frais/categories                   → FraisController@categories
POST   /v2/finance/frais/categories                   → FraisController@storeCategorie

# Factures
GET    /v2/finance/factures                           → FactureController@index
GET    /v2/finance/factures/create                    → FactureController@create
POST   /v2/finance/factures                           → FactureController@store
GET    /v2/finance/factures/masse                     → FactureController@masseForm
POST   /v2/finance/factures/masse                     → FactureController@massStore
GET    /v2/finance/factures/{id}                      → FactureController@show
GET    /v2/finance/factures/{id}/print                → FactureController@print
POST   /v2/finance/factures/{id}/emettre              → FactureController@emettre
POST   /v2/finance/factures/{id}/annuler              → FactureController@annuler
POST   /v2/finance/factures/{id}/relancer             → FactureController@relancer
POST   /v2/finance/factures/{id}/remise               → FactureController@appliquerRemise
POST   /v2/finance/factures/{id}/echeancier           → FactureController@creerEcheancier

# Paiements
GET    /v2/finance/paiements                          → PaiementController@index
GET    /v2/finance/paiements/create                   → PaiementController@create
POST   /v2/finance/paiements                          → PaiementController@store
GET    /v2/finance/paiements/{id}                     → PaiementController@show
GET    /v2/finance/paiements/{id}/recu                → PaiementController@recu
POST   /v2/finance/paiements/{id}/annuler             → PaiementController@annuler
GET    /v2/finance/paiements/trop-percus              → PaiementController@tropPercus
POST   /v2/finance/paiements/trop-percus/{id}/imputer → PaiementController@imputer

# Décaissements
GET    /v2/finance/decaissements                      → DecaissementController@index
GET    /v2/finance/decaissements/create               → DecaissementController@create
POST   /v2/finance/decaissements                      → DecaissementController@store
GET    /v2/finance/decaissements/{id}                 → DecaissementController@show
POST   /v2/finance/decaissements/{id}/valider         → DecaissementController@valider
POST   /v2/finance/decaissements/{id}/approuver       → DecaissementController@approuver
POST   /v2/finance/decaissements/{id}/rejeter         → DecaissementController@rejeter
POST   /v2/finance/decaissements/{id}/payer           → DecaissementController@payer
POST   /v2/finance/decaissements/{id}/justificatif    → DecaissementController@uploadJustificatif
GET    /v2/finance/fournisseurs                       → DecaissementController@fournisseurs
POST   /v2/finance/fournisseurs                       → DecaissementController@storeFournisseur

# Caisse
GET    /v2/finance/caisse                             → CaisseController@index
POST   /v2/finance/caisse/ouvrir                      → CaisseController@ouvrir
POST   /v2/finance/caisse/{id}/fermer                 → CaisseController@fermer
POST   /v2/finance/caisse/{id}/rapprocher             → CaisseController@rapprocher
GET    /v2/finance/caisse/{id}/journal                → CaisseController@journal

# Comptabilité
GET    /v2/finance/comptabilite/journal               → ComptabiliteController@journal
GET    /v2/finance/comptabilite/grand-livre           → ComptabiliteController@grandLivre
GET    /v2/finance/comptabilite/balance               → ComptabiliteController@balance

# Rapports
GET    /v2/finance/rapports                           → RapportController@index
GET    /v2/finance/rapports/recettes                  → RapportController@recettes
GET    /v2/finance/rapports/depenses                  → RapportController@depenses
GET    /v2/finance/rapports/impayes                   → RapportController@impayes
GET    /v2/finance/rapports/tresorerie                → RapportController@tresorerie
GET    /v2/finance/rapports/export/csv/{type}         → RapportController@exportCsv
GET    /v2/finance/rapports/export/pdf/{type}         → RapportController@exportPdf
```

**Total : 50 routes V2 Finance**

---

## 9. Diagrammes de flux

### 9.1 Flux Facturation individuelle

```
[Secrétaire] → creerFacture(eleveId, [fraisTypeIds], annee)
      │
      ▼
FraisRepository::tarifPourEleve(eleveId, fraisTypeId, annee)
      │  (retourne montant personnalisé ou montant_defaut)
      ▼
FacturationService::genererFactureIndividuelle()
      │
      ├── Crée finance_factures (statut: brouillon, numero: FCT-YYYY-NNNN)
      │
      ├── Crée N finance_lignes_facture (1 par fraisTypeId)
      │
      ├── recalculerMontants() → montant_total = Σ lignes
      │
      ├── EventDispatcher::dispatch(FactureCreated)
      │       └── FinanceAuditHandler::log()
      │
      └── return factureId
```

### 9.2 Flux Paiement

```
[Comptable] → enregistrerPaiement(PaiementDTO)
      │
      ▼
Validation :
  - facture existe et statut ∉ {annulee}
  - montant > 0
  - session caisse ouverte (si espèces)
      │
      ▼
EncaissementService::enregistrerPaiement()
      │
      ├── Génère numero_recu : REC-YYYY-NNNN
      │
      ├── Insère finance_paiements
      │
      ├── Si echeance_id fourni → recalculerStatutEcheance()
      │
      ├── recalculerStatutFacture() :
      │       montant_paye = Σ paiements.valides
      │       Si paye >= total → statut = 'payee'
      │       Si 0 < paye < total → statut = 'partiellement_payee'
      │
      ├── Si montant_paye > montant_total :
      │       trop_percu = paiement - reste_dû
      │       Insère finance_trop_percus
      │       EventDispatcher::dispatch(TropPercuGenere)
      │
      ├── Si session caisse ouverte :
      │       CaisseService::enregistrerMouvement(sessionId, 'entree', montant, ...)
      │
      ├── EventDispatcher::dispatch(PaiementCreated)
      │       ├── FinanceAuditHandler::log()
      │       ├── PaiementHandler → NotificationService::onPaiement(eleveId, montant, fraisNom)
      │       └── PaiementHandler → ComptabiliteService::genererEcritureDepuisPaiement()
      │
      └── return paiementId
```

### 9.3 Flux Décaissement avec workflow

```
[Secrétaire] → soumettre(DecaissementDTO)
      │
      ├── statut: brouillon → soumis
      ├── EventDispatcher(DecaissementCreated)
      │
[Comptable] → valider(decId)
      │
      ├── Vérifie: justificatif présent si montant > seuil
      ├── statut: soumis → valide
      ├── EventDispatcher(DecaissementValidated)
      │       └── NotificationHandler → notify Directeur
      │
[Directeur] → approuver(decId) — si montant > seuil_approbation
      │
      ├── statut: valide → approuve
      ├── EventDispatcher(DecaissementApproved)
      │
[Comptable] → payer(decId, {mode, reference, caisse_id})
      │
      ├── statut: approuve → paye
      ├── CaisseService::enregistrerMouvement('sortie')
      ├── ComptabiliteService::genererEcritureDepuisDecaissement()
      └── EventDispatcher(DecaissementPaye — optionnel)
```

### 9.4 Flux Caisse journalière

```
09h00 — [Secrétaire] → ouvrirSession(soldeInitial=50000, date='2026-07-01')
              ├── Vérifie: aucune session ouverte pour ce jour
              ├── Crée finance_sessions_caisse (statut: ouverte)
              └── EventDispatcher(CaisseOuverte)

Durant la journée :
  → Chaque PaiementCreated (espèces) → MouvementCaisse(entree, montant)
  → Chaque DecaissementPaye (espèces) → MouvementCaisse(sortie, montant)

17h00 — [Secrétaire] → fermerSession(sessionId, soldeReel=75000)
              ├── solde_theorique = 50000 + Σentrees - Σsorties
              ├── ecart = soldeReel - soldeTheorique
              ├── Si |ecart| > seuil → flag alerte
              ├── statut: ouverte → fermee
              └── EventDispatcher(CaisseFermee)

[Comptable] → rapprocher(sessionId) → statut: fermee → rapprochee
```

---

## 10. Dépendances

### 10.1 Dépendances du module Finance V2

```
Finance V2 dépend de :
  ✅ Core (Controller, Router, Database, Event, EventDispatcher, View)
  ✅ App\Services\AuditService      — audit trail complet
  ✅ App\Services\NotificationService — alertes parent + personnel
  ✅ App\Services\UploadService     — justificatifs dépenses
  ✅ Tables V1 eleves               — référence élèves
  ✅ Tables V1 classes              — génération masse
  ✅ Tables V1 depenses_categories  — réutilisées par finance_decaissements
  ✅ Tables V1 users                — encaisseur, validateur, approbateur

Finance V2 N'A PAS de dépendance vers :
  ❌ App\Modules\Scolarite  (lecture V1 directe uniquement)
  ❌ App\Modules\Academique (domaines indépendants)
```

### 10.2 Ce dont Finance V2 a besoin que Scolarité V2 expose (via events)

| Événement Scolarité | Réaction Finance |
|--------------------|-----------------|
| `InscriptionValidee` | Déclencher génération facture d'inscription automatique |
| `EleveArchived` | Marquer toutes factures impayées comme "dossier archivé" |
| `SchoolYearChanged` | Recalculer les tarifs pour la nouvelle année |

Ces abonnements seront câblés dans `config/events.php` dans un handler Finance dédié.

---

## 11. Migration V1 → V2

### 11.1 Principe de coexistence

```
PHASE 1 (V2 en parallèle — état actuel) :
  Routes V1 /comptabilite, /paiements, /depenses → actives
  Routes V2 /v2/finance/* → actives (après fix INF-C-001/002)
  Tables V1 frais_types, frais_eleves, paiements, depenses → inchangées
  Tables V2 finance_* → nouvelles, remplissage progressif

PHASE 2 (migration des données) :
  Script migration_finance_v1_to_v2.sql :
    INSERT INTO finance_frais_types SELECT ...    FROM frais_types     (mapping des colonnes)
    INSERT INTO finance_factures    SELECT ...    FROM frais_eleves    (1 facture par frais_eleve)
    INSERT INTO finance_paiements   SELECT ...    FROM paiements       (1:1 avec numero REC auto)

PHASE 3 (bascule) :
  1. Validation des données migrées (comptages, sommes)
  2. Désactivation routes V1 Finance (enabled: false dans routing spécifique)
  3. Redirection /comptabilite → /v2/finance (HTTP 301)

PHASE 4 (nettoyage — V3 uniquement) :
  Éventuelle suppression des routes V1 Finance (pas des tables)
```

### 11.2 Script de migration (squelette)

```sql
-- migration_finance_v1_to_v2.sql
-- À EXÉCUTER une seule fois, en dehors des heures de cours

-- 1. Importer les catégories → finance_categories_frais
INSERT IGNORE INTO finance_categories_frais (code, nom)
VALUES
    ('SCOL',     'Frais scolaires'),
    ('TRANSP',   'Transport'),
    ('CANTINE',  'Cantine'),
    ('ACTIVITES','Activités'),
    ('ADMIN',    'Administration');

-- 2. Importer les types de frais V1 → finance_frais_types
INSERT IGNORE INTO finance_frais_types
    (code, nom, montant_defaut, periodicite, est_obligatoire, actif)
SELECT
    UPPER(REPLACE(REPLACE(SUBSTRING(nom, 1, 15), ' ', '_'), '\'', '')),
    nom,
    montant_defaut,
    periodicite,
    1,
    actif
FROM frais_types;

-- 3. Créer une facture par entrée frais_eleves (si non déjà migrée)
-- (script plus complexe — génération numéros FCT-YYYY-NNNN séquentiel)
-- Statut mappé : en_attente → emise, partiel → partiellement_payee, paye → payee

-- 4. Importer les paiements V1 → finance_paiements
-- (nécessite d'abord que les factures soient créées)
```

### 11.3 Risques de migration

| Risque | Impact | Mitigation |
|--------|--------|-----------|
| `frais_eleves` sans facture référente | Perte de données | Script de vérification pré-migration |
| Paiements orphelins (frais_eleve_id = NULL) | Affectation impossible | Créer une facture "générique" par élève/année |
| Numérotation FCT/REC en conflit | Aucun (nouvelles séquences) | Séquences partent de 0001 dans V2 |
| Soldes incohérents après migration | Taux recouvrement erroné | Vérification : Σpaiements_v1 = Σpaiements_v2 par élève |

---

## 12. Règles métier consolidées

### 12.1 Facturation

1. Le numéro de facture est séquentiel, unique, non réutilisable : `FCT-{AAAA}-{NNNN:04d}`
2. Une facture n'est modifiable qu'à statut `brouillon`
3. On ne peut pas émettre une facture de montant nul
4. Toute remise doit être justifiée (libellé + accordée_par)
5. Une exonération totale bloque la génération d'un trop-perçu sur cette facture
6. Un échéancier est invariable une fois émis (pas de modification de nb_echeances)
7. Les pénalités sont calculées en % du montant restant dû × nb jours de retard × taux journalier

### 12.2 Encaissement

8. Un paiement ne peut jamais être supprimé, seulement annulé avec motif
9. L'annulation d'un paiement recalcule immédiatement le statut de la facture
10. Si paiement > reste dû → trop-perçu automatique
11. Le numéro de reçu est séquentiel et unique : `REC-{AAAA}-{NNNN:04d}`
12. Un remboursement n'est possible que sur un trop-perçu à statut `en_attente`

### 12.3 Décaissement

13. Tout décaissement > seuil (configurable, défaut: 50 000 F) requiert approbation du directeur
14. Aucune dépense ne peut être payée sans validation préalable
15. Le numéro de dépense est séquentiel : `DEP-{AAAA}-{NNNN:04d}`
16. Les justificatifs sont stockés via UploadService (type: 'justification')

### 12.4 Caisse

17. Une seule session de caisse ouverte par journée
18. La fermeture de caisse est irréversible (seul le rapprochement peut suivre)
19. Tout paiement en espèces impute la session de caisse du jour
20. Un écart de caisse > seuil (configurable) génère une alerte NotificationService

### 12.5 Comptabilité

21. Le journal comptable est immuable (aucune modification d'écriture)
22. Un exercice clôturé est figé (aucune écriture possible)
23. Les écritures sont générées automatiquement : débit 411 (Clients) / crédit 512 (Banque) pour les paiements

---

## 13. Ordre de développement recommandé

### Phase 3.1.1 — Infrastructure (pré-requis absolus)

**À faire AVANT de commencer Finance :**
1. Corriger INF-C-001 (Application ne charge pas les routes modules)
2. Corriger INF-C-002 (Router namespace hardcodé)
3. Corriger INF-C-003 (permissions V2 absentes de config/permissions.php)
4. Corriger AN-C-003 (namespace AuditService)
5. Ajouter les 28 permissions Finance à `config/permissions.php`
6. Ajouter Finance à `config/modules.php`

**Effort : ~5h**

### Phase 3.1.2 — Référentiel frais

- Tables : `finance_categories_frais`, `finance_frais_types`, `finance_tarifs`, `finance_regles_exoneration`
- Services : `FraisService`
- Events : `FraisTypeCreated`, `FraisTypeUpdated`, `FraisTypeArchived`
- Controller : `FraisController` (CRUD + tarifs)
- Vues : `frais/index.php`, `frais/form.php`, `frais/show.php`
- Tests : `FraisServiceTest` (unitaire, Stub repository)

**Effort estimé : 2 jours**

### Phase 3.1.3 — Facturation

- Tables : `finance_factures`, `finance_lignes_facture`, `finance_remises`, `finance_echeanciers`, `finance_echeances`
- Services : `FacturationService` (implémente `FacturationInterface`)
- Value Objects : `NumeroFactureValue`, `MontantValue`
- Events : `FactureCreated`, `FactureEmise`, `FactureAnnulee`
- Controller : `FactureController` (CRUD + emettre + annuler + echéancier)
- Vues : `factures/index.php`, `factures/form.php`, `factures/show.php`, `factures/print.php`
- Tests : `FacturationServiceTest`

**Effort estimé : 3 jours**

### Phase 3.1.4 — Encaissements

- Tables : `finance_paiements`, `finance_trop_percus`, `finance_remboursements`
- Services : `EncaissementService` (implémente `EncaissementInterface`)
- Value Objects : `NumeroRecuValue`
- Events : `PaiementCreated`, `PaiementAnnule`, `TropPercuGenere`, `RemboursementCreated`
- Controller : `PaiementController`
- Vues : `paiements/index.php`, `paiements/form.php`, `paiements/show.php`, `paiements/recu.php`
- Tests : `EncaissementServiceTest`

**Effort estimé : 2 jours**

### Phase 3.1.5 — Décaissements

- Tables : `finance_fournisseurs`, `finance_decaissements`, `finance_justificatifs`
- Services : `DecaissementService`
- Events : `DecaissementCreated`, `DecaissementValidated`, `DecaissementApproved`, `DecaissementRejected`
- Controller : `DecaissementController`
- Vues : `decaissements/index.php`, `decaissements/form.php`, `decaissements/show.php`, `decaissements/valider.php`
- Tests : `DecaissementServiceTest`

**Effort estimé : 2 jours**

### Phase 3.1.6 — Caisse

- Tables : `finance_sessions_caisse`, `finance_mouvements_caisse`
- Services : `CaisseService`
- Events : `CaisseOuverte`, `CaisseFermee`
- Controller : `CaisseController`
- Vues : `caisse/index.php`, `caisse/ouvrir.php`, `caisse/fermer.php`, `caisse/journal.php`
- Tests : `CaisseServiceTest`

**Effort estimé : 1.5 jours**

### Phase 3.1.7 — Reporting & Dashboard

- Services : `FinanceReportService` (implémente `FinanceReportInterface`), `RelanceService`
- Repositories : `FinanceReportRepository` (SQL agrégations)
- Controller : `RapportController`, `DashboardController`
- Vues : `dashboard/index.php`, `rapports/*.php`
- Tests : `FinanceReportServiceTest`

**Effort estimé : 2 jours**

### Phase 3.1.8 — Comptabilité (optionnel V2, requis V2.1)

- Tables : `finance_comptes`, `finance_exercices`, `finance_ecritures`
- Services : `ComptabiliteService`
- Events : `ExerciceOuvert`, intégration PaiementHandler → genererEcriture
- Controller : `ComptabiliteController`
- Vues : `comptabilite/journal.php`, `grand_livre.php`, `balance.php`

**Effort estimé : 3 jours**

### Phase 3.1.9 — Module Freeze Finance

- Audit complet (architecture, calculs, sécurité, performances)
- Script migration V1 → V2
- FINANCE_MODULE_FREEZE.md

**Effort estimé : 1 jour**

---

## 14. Risques

| Risque | Probabilité | Impact | Mitigation |
|--------|------------|--------|-----------|
| INF-C-001/002/003 non corrigés | Certaine si non traités | Critique — Finance inaccessible | Corriger en priorité absolue avant Phase 3.1.2 |
| Numérotation FCT/REC concurrente | Faible (1 seul utilisateur caisse en général) | Moyen — doublons de numéros | Utiliser une table `finance_sequences` avec SELECT FOR UPDATE |
| Trop-perçu non détecté | Faible | Moyen — argent non rendu | Test unitaire EncaissementService::calculerTropPercu() |
| Statut facture incohérent | Moyenne (triggers manqués) | Haute — bilan faux | recalculerStatutFacture() appelé systématiquement |
| Caisse ouverte en doublon | Faible (UNIQUE KEY date) | Faible — rejeté par DB | Vérification applicative en amont de la requête SQL |
| Dépense sans validation payée | Éliminé par design | Critique — fraude | Enum statut strict + Policy |
| Performances sur Σ paiements | Moyen (N factures × M paiements) | Moyen | Index idx_fp_facture sur finance_paiements |

---

## 15. Checklist de validation du blueprint

```
Architecture
  [x] 7 sous-domaines définis
  [x] 8 services avec responsabilités isolées
  [x] 3 interfaces (Contracts)
  [x] 4 Value Objects
  [x] 14+ DTOs identifiés
  [x] 6 Policies RBAC
  [x] 18 événements / 5 handlers
  [x] 50 routes /v2/finance/*
  [x] 20 tables SQL (préfixe finance_, CREATE IF NOT EXISTS)
  [x] 28 permissions RBAC

Règles métier
  [x] 23 règles métier explicites
  [x] Numérotation séquentielle FCT/REC/DEP
  [x] Workflow décaissement brouillon→soumis→valide→approuve→paye
  [x] Gestion trop-perçu automatique
  [x] Caisse : 1 session max par jour

Intégration
  [x] Dépendances identifiées (Core, Shared Services, tables V1)
  [x] Abonnements aux events Scolarité V2
  [x] Pas de couplage direct vers App\Modules\Academique
  [x] UploadService pour justificatifs

Migration
  [x] Tables V1 inchangées
  [x] Coexistence routes V1/V2 définie
  [x] Script migration squelette
  [x] Risques de migration identifiés

Ordre de développement
  [x] 9 sous-phases ordonnées
  [x] Pré-requis INF-C-001/002/003 en tête
  [x] Effort estimé par sous-phase (~17 jours total)
```

---

*Blueprint produit — ecole_app Phase 3.1 Finance V2*
*Prochaine action : corriger les 4 bloquants d'infrastructure (INF-C-001, INF-C-002, INF-C-003, AN-C-003), puis démarrer Phase 3.1.1*
