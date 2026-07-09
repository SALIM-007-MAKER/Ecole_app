# BIBLIOTHÈQUE V2 — BLUEPRINT
**Phase :** 9.1  
**Date :** 2026-07-03  
**Statut :** BLUEPRINT — aucune implémentation  
**Auteur :** SCOLARIS V2  
**Module suivant dans la roadmap :** Module 9 / Phase 9.2

---

## Sommaire

1. [Vue d'ensemble](#1-vue-densemble)
2. [Modèle de données — 14 tables](#2-modèle-de-données)
3. [Architecture des services — 8 services](#3-architecture-des-services)
4. [Repositories — 10](#4-repositories)
5. [DTOs — 7](#5-dtos)
6. [Modèles (computed properties)](#6-modèles)
7. [Policies — 2](#7-policies)
8. [Events — 18](#8-events)
9. [Listeners — 3](#9-listeners)
10. [RBAC — 11 permissions](#10-rbac)
11. [Controllers — 9](#11-controllers)
12. [Routes — 57](#12-routes)
13. [Vues — 14](#13-vues)
14. [Workflows métier](#14-workflows-métier)
15. [Intégrations cross-module](#15-intégrations-cross-module)
16. [Codes-barres & QR — Architecture](#16-codes-barres--qr--architecture)
17. [Recherche avancée](#17-recherche-avancée)
18. [Statistiques & Analytics](#18-statistiques--analytics)
19. [Préparation SaaS / Multi-établissements](#19-préparation-saas--multi-établissements)
20. [Préparation Mobile / API publique](#20-préparation-mobile--api-publique)
21. [Configuration métier](#21-configuration-métier)
22. [Ordre d'implémentation — Phases 9.2 → 9.10](#22-ordre-dimplémentation)
23. [module.json cible](#23-modulejson-cible)
24. [Dette technique anticipée](#24-dette-technique-anticipée)

---

## 1. Vue d'ensemble

### Positionnement

Le module Bibliothèque V2 est le 9e module de la roadmap SCOLARIS V2. Il gère l'ensemble du cycle de vie des ressources documentaires de l'établissement : catalogage, exemplaires physiques, emprunts, retours, réservations, pénalités de retard, inventaires, codes-barres/QR, recherche avancée et analytics.

### Namespace

```
App\Modules\Bibliotheque\
```

### Préfixe tables SQL

```
biblio_*
```

### Préfixe routes

```
/v2/bibliotheque/
```

### Dépendances

| Module | Nature | Obligatoire |
|---|---|---|
| Core (Database, Event, Listener, Controller) | Infrastructure | Oui |
| Scolarité (élèves, inscriptions) | Vérification emprunteur | Non (gracieux) |
| Finance (factures, paiements) | Pénalités facturées | Non (gracieux) |
| Communication (notifications) | Rappels, alertes retard | Non (gracieux) |
| Documents (reçus numériques) | Reçu d'emprunt en PDF | Non (gracieux) |
| RH (employés) | Emprunteurs enseignants/staff | Non (gracieux) |

---

## 2. Modèle de données

### 14 tables `biblio_*`

---

### 2.1 `biblio_auteurs`
```sql
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
```

---

### 2.2 `biblio_editeurs`
```sql
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
```

---

### 2.3 `biblio_categories`

Arborescence auto-référentielle (Dewey simplifié ou CDU).

```sql
CREATE TABLE IF NOT EXISTS biblio_categories (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom               VARCHAR(100) NOT NULL,
    description       TEXT,
    parent_id         INT UNSIGNED,
    ordre             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    couleur           VARCHAR(7),        -- #RRGGBB pour UI
    etablissement_id  INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at        DATETIME,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES biblio_categories(id) ON DELETE SET NULL,
    INDEX idx_cat_etab      (etablissement_id),
    INDEX idx_cat_parent    (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.4 `biblio_tags`
```sql
CREATE TABLE IF NOT EXISTS biblio_tags (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom               VARCHAR(60) NOT NULL,
    couleur           VARCHAR(7),
    etablissement_id  INT UNSIGNED NOT NULL DEFAULT 1,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tag_nom_etab (nom, etablissement_id),
    INDEX idx_tag_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.5 `biblio_ouvrages`

Table centrale du catalogue.

```sql
CREATE TABLE IF NOT EXISTS biblio_ouvrages (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    isbn                 VARCHAR(13),       -- ISBN-10 ou ISBN-13
    isbn13               VARCHAR(13),       -- ISBN-13 normalisé
    titre                VARCHAR(255) NOT NULL,
    sous_titre           VARCHAR(255),
    resume               TEXT,
    annee_edition        YEAR,
    nombre_pages         SMALLINT UNSIGNED,
    langue               VARCHAR(10) NOT NULL DEFAULT 'fr',
    image_couverture     VARCHAR(500),      -- chemin relatif stockage
    type                 ENUM('livre','revue','bd','manuel','periodique','numerique','autre') NOT NULL DEFAULT 'livre',
    cote                 VARCHAR(50),       -- Classification Dewey/CDU
    localisation_defaut  VARCHAR(100),      -- Rayon/étagère par défaut
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
```

---

### 2.6 `biblio_ouvrage_auteurs`
```sql
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
```

---

### 2.7 `biblio_ouvrage_categories`
```sql
CREATE TABLE IF NOT EXISTS biblio_ouvrage_categories (
    ouvrage_id    INT UNSIGNED NOT NULL,
    categorie_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (ouvrage_id, categorie_id),
    FOREIGN KEY (ouvrage_id)   REFERENCES biblio_ouvrages(id)    ON DELETE CASCADE,
    FOREIGN KEY (categorie_id) REFERENCES biblio_categories(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.8 `biblio_ouvrage_tags`
```sql
CREATE TABLE IF NOT EXISTS biblio_ouvrage_tags (
    ouvrage_id  INT UNSIGNED NOT NULL,
    tag_id      INT UNSIGNED NOT NULL,
    PRIMARY KEY (ouvrage_id, tag_id),
    FOREIGN KEY (ouvrage_id) REFERENCES biblio_ouvrages(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)     REFERENCES biblio_tags(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.9 `biblio_exemplaires`

Copies physiques d'un ouvrage, identifiées individuellement.

```sql
CREATE TABLE IF NOT EXISTS biblio_exemplaires (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ouvrage_id         INT UNSIGNED NOT NULL,
    numero_inventaire  VARCHAR(30) NOT NULL,   -- Ex: BIB-2024-0001 (unique dans étab)
    code_barre         VARCHAR(50),             -- EAN-13 / Code39 / libre
    qr_data            VARCHAR(500),            -- JSON encodé pour QR
    localisation       VARCHAR(100),            -- Rayon/étagère précis pour cet exemplaire
    statut             ENUM('disponible','emprunte','reserve','en_reparation','perdu','retire')
                           NOT NULL DEFAULT 'disponible',
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
    INDEX idx_exemplaire_etab    (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.10 `biblio_emprunts`

Machine d'états : `en_cours` → `retourne` | `en_retard` → `retourne` | `perdu`.

```sql
CREATE TABLE IF NOT EXISTS biblio_emprunts (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exemplaire_id         INT UNSIGNED NOT NULL,
    user_id               INT UNSIGNED NOT NULL,   -- L'emprunteur
    date_emprunt          DATE NOT NULL,
    date_retour_prevue    DATE NOT NULL,
    date_retour_effectif  DATE,
    statut                ENUM('en_cours','en_retard','retourne','perdu')
                              NOT NULL DEFAULT 'en_cours',
    prolongations         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    notes                 TEXT,
    created_by            INT UNSIGNED,             -- Bibliothécaire ayant créé l'emprunt
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
```

---

### 2.11 `biblio_reservations`

Queue FIFO par ouvrage. Un utilisateur ne peut avoir qu'une réservation active par ouvrage.

```sql
CREATE TABLE IF NOT EXISTS biblio_reservations (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ouvrage_id          INT UNSIGNED NOT NULL,
    user_id             INT UNSIGNED NOT NULL,
    position_file       SMALLINT UNSIGNED NOT NULL DEFAULT 1,  -- Position dans la file
    date_reservation    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_disponibilite  DATETIME,                              -- Quand exemplaire disponible
    date_expiration     DATETIME,                              -- Délai de confirmation (48h)
    statut              ENUM('en_attente','disponible','confirmee','annulee','expiree')
                            NOT NULL DEFAULT 'en_attente',
    notes               TEXT,
    etablissement_id    INT UNSIGNED NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ouvrage_id) REFERENCES biblio_ouvrages(id) ON DELETE CASCADE,
    UNIQUE KEY uk_reservation_active (ouvrage_id, user_id, statut),
    INDEX idx_reservation_user    (user_id),
    INDEX idx_reservation_ouvrage (ouvrage_id),
    INDEX idx_reservation_statut  (statut),
    INDEX idx_reservation_etab    (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.12 `biblio_penalites`

```sql
CREATE TABLE IF NOT EXISTS biblio_penalites (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    emprunt_id        INT UNSIGNED NOT NULL,
    user_id           INT UNSIGNED NOT NULL,
    type              ENUM('retard','perte','degradation') NOT NULL,
    montant           DECIMAL(8,2) NOT NULL,
    statut            ENUM('en_attente','payee','annulee') NOT NULL DEFAULT 'en_attente',
    jours_retard      SMALLINT UNSIGNED,          -- Renseigné si type = 'retard'
    facture_id        INT UNSIGNED,                -- FK → finance_factures.id (nullable)
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
```

---

### 2.13 `biblio_inventaires`

Sessions d'inventaire physique (comptage/vérification du stock).

```sql
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
```

---

### 2.14 `biblio_inventaire_lignes`

Lignes de scan pour une session d'inventaire.

```sql
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
```

---

### Récapitulatif tables

| # | Table | Rôle | Soft Delete |
|---|---|---|---|
| 1 | `biblio_auteurs` | Référentiel auteurs | ✓ |
| 2 | `biblio_editeurs` | Référentiel éditeurs | ✓ |
| 3 | `biblio_categories` | Arborescence catégories | ✓ |
| 4 | `biblio_tags` | Tags libres | — |
| 5 | `biblio_ouvrages` | Catalogue | ✓ |
| 6 | `biblio_ouvrage_auteurs` | N:M ouvrage↔auteur | — |
| 7 | `biblio_ouvrage_categories` | N:M ouvrage↔catégorie | — |
| 8 | `biblio_ouvrage_tags` | N:M ouvrage↔tag | — |
| 9 | `biblio_exemplaires` | Copies physiques | ✓ |
| 10 | `biblio_emprunts` | Emprunts/retours | — |
| 11 | `biblio_reservations` | File d'attente | — |
| 12 | `biblio_penalites` | Amendes | — |
| 13 | `biblio_inventaires` | Sessions d'inventaire | — |
| 14 | `biblio_inventaire_lignes` | Lignes de scan | — |

**Total : 14 tables — migration unique : `biblio_001_bibliotheque.sql`**

---

## 3. Architecture des services

### 3.1 `CatalogueService`

Gestion du catalogue (ouvrages + référentiels auteurs/éditeurs/catégories/tags).

```
Méthodes :
  ajouterOuvrage(OuvrageDTO $dto, int $userId): int
  modifierOuvrage(int $id, OuvrageDTO $dto, int $userId): void
  archiverOuvrage(int $id, int $userId): void        -- soft delete
  trouver(int $id): ?array
  trouverParIsbn(string $isbn): ?array
  lister(OuvrageFiltersDTO $filters): array

  -- Référentiels
  ajouterAuteur(array $data): int
  modifierAuteur(int $id, array $data): void
  ajouterEditeur(array $data): int
  ajouterCategorie(array $data): int
  treeCategories(int $etablissementId): array        -- arborescence récursive
  trouvterOuCreerTag(string $nom, int $etablissementId): int

  -- Dispatche : OuvrageAjoute / OuvrageModifie / OuvrageArchive
```

---

### 3.2 `ExemplaireService`

Gestion des copies physiques, génération identifiants.

```
Méthodes :
  ajouter(ExemplaireDTO $dto, int $userId): int
  modifier(int $id, ExemplaireDTO $dto, int $userId): void
  archiver(int $id, int $userId): void
  changerStatut(int $id, string $nouveauStatut, int $userId, ?string $notes): void
  trouver(int $id): ?array
  trouverParBarcode(string $code): ?array
  listerParOuvrage(int $ouvrageId): array
  compterDisponibles(int $ouvrageId, int $etablissementId): int
  genererNumeroInventaire(int $etablissementId): string  -- BIB-AAAA-NNNN

  -- Génération codes
  genererQrData(int $exemplaire_id): string              -- JSON pour QR
  genererBarcodeSvg(int $exemplaire_id): string          -- via BarcodeService
  genererQrSvg(int $exemplaire_id): string               -- via QrCodeService

  -- Dispatche : ExemplaireAjoute / ExemplaireStatutChange
```

---

### 3.3 `EmpruntService`

Machine d'états des emprunts. Cœur métier.

```
Méthodes :
  creerEmprunt(EmpruntDTO $dto, int $createdBy): int
    -- Vérifie : exemplaire disponible, quota non dépassé, pénalités bloquantes
    -- Transition exemplaire : disponible → emprunte
    -- Résout réservation existante si applicable
    -- Dispatche : EmpruntCree

  retournerEmprunt(int $empruntId, int $userId): void
    -- Transition exemplaire : emprunte → disponible
    -- Calcul jours de retard → PenaliteService::calculerRetard()
    -- Notifie prochaine réservation si file non vide
    -- Dispatche : EmpruntRetourne

  declarerPerdu(int $empruntId, int $userId, ?string $notes): void
    -- Transition exemplaire : emprunte → perdu
    -- Crée pénalité type=perte via PenaliteService
    -- Dispatche : EmpruntPerdu

  prolongerEmprunt(int $empruntId, int $userId): void
    -- Vérifie : prolongations < max (config)
    -- Étend date_retour_prevue (+N jours config)
    -- Dispatche : EmpruntProlonge

  detectionRetards(int $etablissementId): int
    -- Appelé par cron — retourne le nombre de retards détectés
    -- UPDATE statut → en_retard WHERE date_retour_prevue < NOW() AND statut = 'en_cours'
    -- Dispatche : EmpruntEnRetard (1 event par emprunt)

  trouver(int $id): ?array
  listerEncours(int $etablissementId): array
  listerEnRetard(int $etablissementId): array
  historiqueUser(int $userId): array
```

---

### 3.4 `ReservationService`

File d'attente FIFO avec expiration automatique.

```
Méthodes :
  creerReservation(ReservationDTO $dto): int
    -- Vérifie : pas de réservation active pour cet ouvrage+user
    -- Si exemplaire disponible NOW : passe directement en statut 'disponible'
    -- Sinon : calcule position_file (MAX(position_file)+1)
    -- Dispatche : ReservationCree

  confirmerReservation(int $id, int $userId): void
    -- Statut disponible → confirmee
    -- Dispatche : ReservationConfirmee

  annulerReservation(int $id, int $userId): void
    -- Statut * → annulee
    -- Réorganise la file (décrémente positions suivantes)
    -- Dispatche : ReservationAnnulee

  notifierProchainEnFile(int $ouvrageId, int $etablissementId): void
    -- Sélectionne next en_attente → statut=disponible, date_disponibilite=NOW, date_expiration=+48h
    -- Dispatche : ReservationDisponible

  expirerReservations(int $etablissementId): int
    -- Cron — expire les disponible dont date_expiration < NOW()
    -- Dispatche : ReservationExpiree
    -- Appelle notifierProchainEnFile() pour chaque ouvrage concerné

  trouver(int $id): ?array
  listerParUser(int $userId): array
  listerParOuvrage(int $ouvrageId): array
```

---

### 3.5 `PenaliteService`

Calcul et gestion des amendes. Intégration Finance optionnelle.

```
Méthodes :
  calculerRetard(int $empruntId): ?int
    -- Calcule les jours de retard après retour (date_retour_effectif - date_retour_prevue)
    -- Retourne le montant en centimes ou null si aucun retard

  creerPenaliteRetard(int $empruntId, int $joursRetard, int $createdBy): int
    -- montant = joursRetard * tarifJournalier (config)
    -- Crée l'enregistrement en statut='en_attente'
    -- Si Finance activé : génère facture via InvoiceService + stocke facture_id
    -- Dispatche : PenaliteCreee

  creerPenalitePerte(int $empruntId, int $createdBy): int
    -- montant = valeur remplacement ouvrage (config ou valeur saisie)
    -- Dispatche : PenaliteCreee

  payerManuellement(int $penaliteId, int $userId): void
    -- Paiement hors Finance (espèces directement à la bibliothèque)
    -- statut → payee
    -- Dispatche : PenalitePayee

  annuler(int $penaliteId, int $userId, string $motif): void
    -- statut → annulee
    -- Dispatche : PenaliteAnnulee

  listerParUser(int $userId): array
  listerImpayees(int $etablissementId): array
  aDesImpayeesBloquantes(int $userId, int $etablissementId): bool
    -- Vérifie si l'user a des pénalités > PENALITE_MAX_IMPAYEES (config)
```

---

### 3.6 `InventaireService`

Gestion des sessions d'inventaire physique.

```
Méthodes :
  lancerSession(InventaireDTO $dto, int $userId): int
    -- Refuse si une session est déjà en_cours
    -- Dispatche : InventaireLance

  scannerExemplaire(int $sessionId, string $codeOuNumero, string $statutConstate, int $userId): void
    -- Cherche exemplaire par code_barre ou numero_inventaire
    -- Insert/update ligne inventaire
    -- Met à jour statut si deteriore ou perdu

  terminerSession(int $sessionId, int $userId): array
    -- Marque les exemplaires non scannés comme 'manquants' dans les lignes
    -- Retourne le rapport {scanned: N, manquants: N, deteriores: N}
    -- Dispatche : InventaireTermine

  trouver(int $id): ?array
  lister(int $etablissementId): array
  rapport(int $sessionId): array
```

---

### 3.7 `RechercheService`

Recherche avancée multi-critères dans le catalogue.

```
Méthodes :
  rechercher(RechercheDTO $dto): array
    -- FULLTEXT sur biblio_ouvrages (titre, sous_titre, resume)
    -- Filtres : type, categorie_id, langue, annee_min, annee_max, auteur_id, editeur_id, tag_ids[], disponible_seulement
    -- Retourne ouvrages avec nb_exemplaires_total + nb_exemplaires_disponibles
    -- Tri : pertinence FULLTEXT / titre / date_edition / popularite (nb_emprunts)

  suggestions(string $terme, int $limit = 5): array
    -- Autocomplétion titre/isbn pour le champ de recherche
    -- LIKE '%terme%' sur titre et isbn13

  ouvragesSimilaires(int $ouvrageId, int $limit = 5): array
    -- Basé sur catégories et auteurs partagés
```

---

### 3.8 `BiblioAnalyticsService`

Statistiques et tableaux de bord.

```
Méthodes :
  dashboard(int $etablissementId): BiblioMetrics
    -- totalOuvrages, totalExemplaires, exemplairesDisponibles
    -- empruntsEnCours, empruntsEnRetard, reservationsEnAttente
    -- penalitesImpayees (montant total)

  ouvragesLesPlusEmpruntes(int $etablissementId, int $periode = 30, int $limit = 10): array
  tauxRotation(int $etablissementId): float       -- emprunts / exemplaires sur 30j
  tauxRetard(int $etablissementId): float          -- % emprunts en retard
  empruntsParMois(int $etablissementId, int $mois = 12): array
  empruntsParCategorie(int $etablissementId): array
  emprunteursActifs(int $etablissementId, int $limit = 10): array
  delaiMoyenRetour(int $etablissementId): float   -- jours
  amendesCollectees(int $etablissementId, int $annee): array
  exemplairesParStatut(int $etablissementId): array
  export(string $format, int $etablissementId): string  -- CSV / PDF
```

---

## 4. Repositories

| # | Repository | Tables cibles | Méthodes clés |
|---|---|---|---|
| 1 | `OuvrageRepository` | biblio_ouvrages + liaisons | findById, findByIsbn, search(filters), findWithDetails, insert, update, softDelete |
| 2 | `ExemplaireRepository` | biblio_exemplaires | findById, findByOuvrage, findByBarcode, findByNumeroInventaire, findDisponibles(ouvrageId), countDisponibles, insert, update, updateStatut, softDelete |
| 3 | `EmpruntRepository` | biblio_emprunts | findById, findByUser, findByExemplaire, findEnCours, findEnRetard, insertEmprunt, updateStatut, updateRetour, markOverdue |
| 4 | `ReservationRepository` | biblio_reservations | findById, findByUser, findByOuvrage, findEnAttente(ouvrageId), findNextInQueue(ouvrageId), countInQueue, insert, updateStatut, reordonnerFile |
| 5 | `PenaliteRepository` | biblio_penalites | findById, findByEmprunt, findByUser, findImpayees, findImpayeesBloquantes(userId), totalImpayeesUser, insert, updateStatut |
| 6 | `InventaireRepository` | biblio_inventaires + lignes | findById, findActive, findAll, insertSession, terminerSession, insertLigne, updateLigne, findLignes(sessionId) |
| 7 | `AuteurRepository` | biblio_auteurs | findById, findAll, search(terme), insert, update, softDelete |
| 8 | `EditeurRepository` | biblio_editeurs | findById, findAll, search(terme), insert, update, softDelete |
| 9 | `CategorieRepository` | biblio_categories | findById, findAll, findTree, findByParent, insert, update, softDelete |
| 10 | `TagRepository` | biblio_tags | findAll, findByNom, findOrCreate, insert |

---

## 5. DTOs

### `OuvrageDTO`
```php
public function __construct(
    public readonly string  $titre,
    public readonly ?string $sousTitre,
    public readonly ?string $isbn,
    public readonly ?string $resume,
    public readonly ?int    $anneeEdition,
    public readonly ?int    $nombrePages,
    public readonly string  $langue,
    public readonly string  $type,           // livre/revue/bd/manuel/periodique/numerique/autre
    public readonly ?string $cote,
    public readonly ?string $localisationDefaut,
    public readonly ?int    $editeurId,
    public readonly ?string $imageCouverture,
    public readonly array   $auteurIds,      // [int, ...]
    public readonly array   $categorieIds,   // [int, ...]
    public readonly array   $tagIds,         // [int, ...]
)
```

### `OuvrageFiltersDTO`
```php
public function __construct(
    public readonly ?string $terme,          // Recherche fulltext
    public readonly ?int    $categorieId,
    public readonly ?int    $auteurId,
    public readonly ?int    $editeurId,
    public readonly ?string $langue,
    public readonly ?string $type,
    public readonly ?int    $anneeMin,
    public readonly ?int    $anneeMax,
    public readonly array   $tagIds,
    public readonly bool    $disponibleSeulement,
    public readonly string  $tri,            // pertinence/titre/date/popularite
    public readonly int     $page,
    public readonly int     $perPage,
)
```

### `ExemplaireDTO`
```php
public function __construct(
    public readonly int     $ouvrageId,
    public readonly string  $numeroInventaire,
    public readonly ?string $codeBarre,
    public readonly ?string $localisation,
    public readonly string  $etat,           // bon/use/deteriore
    public readonly ?string $notes,
)
```

### `EmpruntDTO`
```php
public function __construct(
    public readonly int     $exemplaireId,
    public readonly int     $userId,
    public readonly string  $dateRetourPrevue,  // Y-m-d
    public readonly ?string $notes,
    public readonly int     $etablissementId,
)
```

### `ReservationDTO`
```php
public function __construct(
    public readonly int     $ouvrageId,
    public readonly int     $userId,
    public readonly ?string $notes,
    public readonly int     $etablissementId,
)
```

### `PenaliteDTO`
```php
public function __construct(
    public readonly int     $empruntId,
    public readonly int     $userId,
    public readonly string  $type,       // retard/perte/degradation
    public readonly float   $montant,
    public readonly ?int    $joursRetard,
    public readonly ?string $notes,
    public readonly int     $etablissementId,
)
```

### `RechercheDTO`
```php
public function __construct(
    public readonly ?string $terme,
    public readonly ?int    $categorieId,
    public readonly ?int    $auteurId,
    public readonly ?int    $editeurId,
    public readonly ?string $langue,
    public readonly ?string $type,
    public readonly ?int    $anneeMin,
    public readonly ?int    $anneeMax,
    public readonly array   $tagIds,
    public readonly bool    $disponibleSeulement,
    public readonly string  $tri,
    public readonly int     $page,
    public readonly int     $perPage,
)
```

### `InventaireDTO`
```php
public function __construct(
    public readonly string  $nom,
    public readonly ?string $description,
    public readonly string  $dateDebut,
    public readonly ?string $datFinPrevue,
)
```

---

## 6. Modèles

Les modèles encapsulent uniquement des computed properties sur un tableau de données brutes.

### `OuvrageModel`

```php
public static function statutDisponibilite(int $nbDisponibles, int $nbTotal): string
    // 'disponible' | 'emprunte' | 'epuise' | 'reserve' | 'archive'

public static function isbn13Formate(?string $isbn): string
    // "978-2-07-036024-0" (tirets)

public static function labelType(string $type): string
    // 'Livre' | 'Revue' | 'BD' | ...
```

### `ExemplaireModel`

```php
public static function estDisponible(string $statut): bool
public static function labelStatut(string $statut): string
    // 'Disponible' | 'Emprunté' | 'Réservé' | 'En réparation' | 'Perdu' | 'Retiré'
public static function labelEtat(string $etat): string
public static function genererNumeroInventaire(int $etablissementId, int $sequence): string
    // BIB-2026-00042
```

### `EmpruntModel`

```php
public static function estEnRetard(string $dateRetourPrevue, ?string $dateRetourEffectif): bool
public static function joursRetard(string $dateRetourPrevue, ?string $dateRetourEffectif): int
public static function montantPenaliteEstime(int $joursRetard, float $tarifJournalier): float
public static function labelStatut(string $statut): string
    // 'En cours' | 'En retard' | 'Retourné' | 'Perdu'
public static function peutEtreProlonge(int $prolongations, int $maxProlongations): bool
```

### `ReservationModel`

```php
public static function estExpiree(?string $dateExpiration): bool
public static function delaiConfirmationRestant(?string $dateExpiration): string  // "23h 14m"
public static function labelStatut(string $statut): string
```

### `PenaliteModel`

```php
public static function estPayee(string $statut): bool
public static function labelType(string $type): string  // 'Retard' | 'Perte' | 'Dégradation'
public static function labelStatut(string $statut): string
public static function montantFormate(float $montant): string  // "2,50 €"
```

---

## 7. Policies

### `BiblioPolicy`

```php
canView(array $user): bool           // biblio.view
canSearch(array $user): bool         // biblio.search
canBorrow(array $user): bool         // biblio.borrow
canReserve(array $user): bool        // biblio.reserve
canManageLoans(array $user): bool    // biblio.manage_loans
canManageCatalogue(array $user): bool   // biblio.manage_catalogue
canManageExemplaires(array $user): bool // biblio.manage_exemplaires
canManagePenalties(array $user): bool   // biblio.manage_penalties
canInventory(array $user): bool      // biblio.inventory
canAnalytics(array $user): bool      // biblio.analytics
canAdmin(array $user): bool          // biblio.admin
```

### `EmpruntPolicy`

```php
// Vérifie les règles métier au-delà des permissions RBAC
canBorrow(array $user, array $exemplaire, int $etablissementId): bool
    // Exemplaire statut === 'disponible'
    // User n'a pas dépassé le quota d'emprunts simultanés
    // User n'a pas de pénalités bloquantes (> seuil config)
    // Inscription active (si module Scolarité activé et user = élève)

canReserve(array $user, array $ouvrage, int $etablissementId): bool
    // Pas de réservation active déjà en file pour cet ouvrage+user
    // Pas de pénalités bloquantes
```

---

## 8. Events

18 événements — tous sous `App\Modules\Bibliotheque\Events\` — conformes au contrat `Core\Event` (`parent::__construct()` + `toArray(): array`).

| # | Classe | Propriétés principales | Déclencheur |
|---|---|---|---|
| 1 | `OuvrageAjoute` | ouvrageId, titre, isbn, userId | CatalogueService::ajouterOuvrage |
| 2 | `OuvrageModifie` | ouvrageId, titre, userId | CatalogueService::modifierOuvrage |
| 3 | `OuvrageArchive` | ouvrageId, titre, userId | CatalogueService::archiverOuvrage |
| 4 | `ExemplaireAjoute` | exemplaireId, ouvrageId, numeroInventaire, userId | ExemplaireService::ajouter |
| 5 | `ExemplaireStatutChange` | exemplaireId, ouvrageId, ancienStatut, nouveauStatut, userId | ExemplaireService::changerStatut |
| 6 | `EmpruntCree` | empruntId, exemplaireId, ouvrageId, userId, dateRetourPrevue, createdBy, etablissementId | EmpruntService::creerEmprunt |
| 7 | `EmpruntRetourne` | empruntId, exemplaireId, ouvrageId, userId, dateRetourEffectif, joursRetard, etablissementId | EmpruntService::retournerEmprunt |
| 8 | `EmpruntEnRetard` | empruntId, exemplaireId, ouvrageId, userId, dateRetourPrevue, joursRetard, etablissementId | EmpruntService::detectionRetards |
| 9 | `EmpruntProlonge` | empruntId, userId, nouvelleDateRetour, nombreProlongation | EmpruntService::prolongerEmprunt |
| 10 | `EmpruntPerdu` | empruntId, exemplaireId, ouvrageId, userId, createdBy | EmpruntService::declarerPerdu |
| 11 | `ReservationCree` | reservationId, ouvrageId, userId, positionFile, etablissementId | ReservationService::creerReservation |
| 12 | `ReservationDisponible` | reservationId, ouvrageId, userId, dateExpiration, etablissementId | ReservationService::notifierProchainEnFile |
| 13 | `ReservationConfirmee` | reservationId, ouvrageId, userId | ReservationService::confirmerReservation |
| 14 | `ReservationAnnulee` | reservationId, ouvrageId, userId, raison | ReservationService::annulerReservation |
| 15 | `ReservationExpiree` | reservationId, ouvrageId, userId | ReservationService::expirerReservations |
| 16 | `PenaliteCreee` | penaliteId, empruntId, userId, type, montant, etablissementId | PenaliteService::creerPenalite* |
| 17 | `PenalitePayee` | penaliteId, empruntId, userId, montant, mode | PenaliteService::payerManuellement |
| 18 | `InventaireTermine` | inventaireId, nom, nbScanned, nbManquants, nbDeterióres, createdBy | InventaireService::terminerSession |

> **Note :** `PenaliteAnnulee` est géré comme changement de statut sans event dédié (logged par AuditListener via PenalitePayee avec mode='annulation').

---

## 9. Listeners

### `AuditListener`

Écoute les 18 événements et appelle `AuditService::log()` / `AuditService::logCreate()`.

```
Gestion :
  OuvrageAjoute      → logCreate(userId, 'ouvrage_ajoute', 'bibliotheque', ouvrageId)
  OuvrageArchive     → log(userId, 'ouvrage_archive', 'bibliotheque', ...)
  ExemplaireAjoute   → logCreate(...)
  EmpruntCree        → logCreate(userId, 'emprunt_cree', 'bibliotheque', empruntId, {...})
  EmpruntRetourne    → log(...)
  EmpruntEnRetard    → log(0, 'emprunt_retard_detecte', ...)
  EmpruntPerdu       → log(...)
  PenaliteCreee      → logCreate(...)
  InventaireTermine  → log(...)
  default            → null
```

### `FinanceIntegrationListener`

Écoute `Finance\Events\PaymentCompleted`. Si le paiement référence une `facture_id` liée à une `biblio_penalite` → appelle `PenaliteService::marquerPayeeViaFinance(int $penaliteId)`.

```php
namespace App\Modules\Bibliotheque\Listeners;

class FinanceIntegrationListener implements Listener
{
    public function handle(Event $event): void
    {
        // Cherche si facture_id correspond à une pénalité biblio
        // Si oui → PenaliteService::marquerPayeeViaFinance($penaliteId)
        // Dispatche PenalitePayee
    }
}
```

### `BiblioNotificationHandler`

Écoute les événements internes qui doivent déclencher des communications (si Communication activé).

```
EmpruntEnRetard      → Communication::CrossModuleListener picks up this event
ReservationDisponible→ Communication::CrossModuleListener picks up this event
PenaliteCreee        → Communication::CrossModuleListener picks up this event
```

> **Architecture** : Les notifications sont déclenchées par `Communication\Listeners\CrossModuleListener` qui écoute `EmpruntEnRetard`, `ReservationDisponible`, `PenaliteCreee`. Aucun couplage direct depuis Bibliothèque vers Communication.

---

## 10. RBAC

### 11 permissions `biblio.*`

| Permission | Sémantique |
|---|---|
| `biblio.view` | Consulter le catalogue et les fiches ouvrages |
| `biblio.search` | Recherche avancée + autocomplétion |
| `biblio.borrow` | Être emprunteur (avoir des emprunts en son nom) |
| `biblio.reserve` | Créer des réservations |
| `biblio.manage_loans` | Créer/gérer des emprunts pour autrui (bibliothécaire) |
| `biblio.manage_catalogue` | CRUD ouvrages + référentiels |
| `biblio.manage_exemplaires` | CRUD exemplaires, gestion statuts |
| `biblio.manage_penalties` | Créer/annuler/encaisser des pénalités |
| `biblio.inventory` | Lancer et gérer des sessions d'inventaire |
| `biblio.analytics` | Dashboard statistiques + exports |
| `biblio.admin` | Administration complète (config, cron manuel) |

### Attribution par rôle

| Rôle | view | search | borrow | reserve | manage_loans | manage_catalogue | manage_exemplaires | manage_penalties | inventory | analytics | admin |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| admin | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| directeur | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| secretaire | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | ✓ | — | — | — |
| enseignant | ✓ | ✓ | ✓ | ✓ | — | — | — | — | — | — | — |
| comptable | ✓ | ✓ | — | — | — | — | — | ✓ | — | ✓ | — |
| parent | ✓ | ✓ | — | ✓ | — | — | — | — | — | — | — |
| eleve | ✓ | ✓ | ✓ | ✓ | — | — | — | — | — | — | — |

> **Note :** `biblio.borrow` signifie "peut être l'emprunteur" — la création physique du prêt est toujours réalisée par un agent avec `biblio.manage_loans`. Pour les élèves, `manage_loans` est absent : ils ne peuvent pas créer d'emprunts eux-mêmes depuis l'interface.

---

## 11. Controllers

9 controllers — tous thin (appels de services uniquement, aucun SQL, aucun dispatch direct).

| Controller | Chemin | Responsabilité |
|---|---|---|
| `CatalogueController` | Controllers/CatalogueController.php | CRUD ouvrages |
| `ExemplaireController` | Controllers/ExemplaireController.php | CRUD exemplaires, QR, barcode |
| `EmpruntController` | Controllers/EmpruntController.php | Emprunts, retours, pertes, prolongations |
| `ReservationController` | Controllers/ReservationController.php | Réservations, confirmations, annulations |
| `PenaliteController` | Controllers/PenaliteController.php | Consultation et paiement pénalités |
| `InventaireController` | Controllers/InventaireController.php | Sessions d'inventaire, scan |
| `AnalyticsController` | Controllers/AnalyticsController.php | Dashboard stats, export |
| `ReferentielController` | Controllers/ReferentielController.php | Auteurs, éditeurs, catégories, tags |
| `MonCompteController` | Controllers/MonCompteController.php | Espace personnel emprunteur |

---

## 12. Routes

**57 routes — préfixe `/v2/bibliotheque/`**

```php
use App\Modules\Bibliotheque\Controllers\{
    CatalogueController, ExemplaireController, EmpruntController,
    ReservationController, PenaliteController, InventaireController,
    AnalyticsController, ReferentielController, MonCompteController,
};

// ── CATALOGUE ────────────────────────────────────────────────────────
Router::get('/v2/bibliotheque/catalogue',            [CatalogueController::class, 'index']);
Router::get('/v2/bibliotheque/catalogue/search',     [CatalogueController::class, 'search']);
Router::get('/v2/bibliotheque/ouvrages/create',      [CatalogueController::class, 'create']);
Router::post('/v2/bibliotheque/ouvrages',            [CatalogueController::class, 'store']);
Router::get('/v2/bibliotheque/ouvrages/{id}',        [CatalogueController::class, 'show']);
Router::get('/v2/bibliotheque/ouvrages/{id}/edit',   [CatalogueController::class, 'edit']);
Router::put('/v2/bibliotheque/ouvrages/{id}',        [CatalogueController::class, 'update']);
Router::delete('/v2/bibliotheque/ouvrages/{id}',     [CatalogueController::class, 'archive']);

// ── EXEMPLAIRES ──────────────────────────────────────────────────────
Router::get('/v2/bibliotheque/ouvrages/{id}/exemplaires',       [ExemplaireController::class, 'index']);
Router::post('/v2/bibliotheque/ouvrages/{id}/exemplaires',      [ExemplaireController::class, 'store']);
Router::put('/v2/bibliotheque/exemplaires/{id}',                [ExemplaireController::class, 'update']);
Router::delete('/v2/bibliotheque/exemplaires/{id}',             [ExemplaireController::class, 'archive']);
Router::get('/v2/bibliotheque/exemplaires/{id}/qr',             [ExemplaireController::class, 'qrCode']);
Router::get('/v2/bibliotheque/exemplaires/{id}/barcode',        [ExemplaireController::class, 'barcode']);
Router::post('/v2/bibliotheque/exemplaires/{id}/statut',        [ExemplaireController::class, 'changerStatut']);

// ── EMPRUNTS ─────────────────────────────────────────────────────────
Router::get('/v2/bibliotheque/emprunts',                 [EmpruntController::class, 'index']);
Router::get('/v2/bibliotheque/emprunts/en-retard',       [EmpruntController::class, 'enRetard']);
Router::get('/v2/bibliotheque/emprunts/create',          [EmpruntController::class, 'create']);
Router::post('/v2/bibliotheque/emprunts',                [EmpruntController::class, 'store']);
Router::get('/v2/bibliotheque/emprunts/{id}',            [EmpruntController::class, 'show']);
Router::post('/v2/bibliotheque/emprunts/{id}/retour',    [EmpruntController::class, 'retour']);
Router::post('/v2/bibliotheque/emprunts/{id}/prolonger', [EmpruntController::class, 'prolonger']);
Router::post('/v2/bibliotheque/emprunts/{id}/perdu',     [EmpruntController::class, 'declarerPerdu']);

// ── RÉSERVATIONS ─────────────────────────────────────────────────────
Router::get('/v2/bibliotheque/reservations',                      [ReservationController::class, 'index']);
Router::get('/v2/bibliotheque/reservations/mes',                  [ReservationController::class, 'mes']);
Router::post('/v2/bibliotheque/reservations',                     [ReservationController::class, 'store']);
Router::get('/v2/bibliotheque/reservations/{id}',                 [ReservationController::class, 'show']);
Router::post('/v2/bibliotheque/reservations/{id}/confirmer',      [ReservationController::class, 'confirmer']);
Router::post('/v2/bibliotheque/reservations/{id}/annuler',        [ReservationController::class, 'annuler']);

// ── PÉNALITÉS ────────────────────────────────────────────────────────
Router::get('/v2/bibliotheque/penalites',                  [PenaliteController::class, 'index']);
Router::get('/v2/bibliotheque/penalites/mes',              [PenaliteController::class, 'mes']);
Router::get('/v2/bibliotheque/penalites/{id}',             [PenaliteController::class, 'show']);
Router::post('/v2/bibliotheque/penalites/{id}/payer',      [PenaliteController::class, 'payer']);
Router::post('/v2/bibliotheque/penalites/{id}/annuler',    [PenaliteController::class, 'annuler']);

// ── INVENTAIRE ───────────────────────────────────────────────────────
Router::get('/v2/bibliotheque/inventaires',                  [InventaireController::class, 'index']);
Router::post('/v2/bibliotheque/inventaires',                 [InventaireController::class, 'store']);
Router::get('/v2/bibliotheque/inventaires/{id}',             [InventaireController::class, 'show']);
Router::post('/v2/bibliotheque/inventaires/{id}/scan',       [InventaireController::class, 'scan']);
Router::post('/v2/bibliotheque/inventaires/{id}/terminer',   [InventaireController::class, 'terminer']);

// ── ANALYTICS ────────────────────────────────────────────────────────
Router::get('/v2/bibliotheque/analytics',         [AnalyticsController::class, 'dashboard']);
Router::get('/v2/bibliotheque/analytics/export',  [AnalyticsController::class, 'export']);

// ── MON COMPTE (espace emprunteur) ───────────────────────────────────
Router::get('/v2/bibliotheque/mon-compte',            [MonCompteController::class, 'index']);
Router::get('/v2/bibliotheque/mon-historique',        [MonCompteController::class, 'historique']);
Router::get('/v2/bibliotheque/mon-compte/penalites',  [MonCompteController::class, 'mesPenalites']);

// ── RÉFÉRENTIELS — Auteurs ────────────────────────────────────────────
Router::get('/v2/bibliotheque/auteurs',         [ReferentielController::class, 'indexAuteurs']);
Router::post('/v2/bibliotheque/auteurs',        [ReferentielController::class, 'storeAuteur']);
Router::put('/v2/bibliotheque/auteurs/{id}',    [ReferentielController::class, 'updateAuteur']);
Router::delete('/v2/bibliotheque/auteurs/{id}', [ReferentielController::class, 'archiveAuteur']);

// ── RÉFÉRENTIELS — Éditeurs ───────────────────────────────────────────
Router::get('/v2/bibliotheque/editeurs',         [ReferentielController::class, 'indexEditeurs']);
Router::post('/v2/bibliotheque/editeurs',        [ReferentielController::class, 'storeEditeur']);
Router::put('/v2/bibliotheque/editeurs/{id}',    [ReferentielController::class, 'updateEditeur']);
Router::delete('/v2/bibliotheque/editeurs/{id}', [ReferentielController::class, 'archiveEditeur']);

// ── RÉFÉRENTIELS — Catégories ─────────────────────────────────────────
Router::get('/v2/bibliotheque/categories',         [ReferentielController::class, 'indexCategories']);
Router::post('/v2/bibliotheque/categories',        [ReferentielController::class, 'storeCategorie']);
Router::put('/v2/bibliotheque/categories/{id}',    [ReferentielController::class, 'updateCategorie']);
Router::delete('/v2/bibliotheque/categories/{id}', [ReferentielController::class, 'archiveCategorie']);

// ── RÉFÉRENTIELS — Tags ───────────────────────────────────────────────
Router::get('/v2/bibliotheque/tags',   [ReferentielController::class, 'indexTags']);
Router::post('/v2/bibliotheque/tags',  [ReferentielController::class, 'storeTag']);

// ── CRON (internes, appelés par tâche planifiée) ──────────────────────
Router::post('/v2/bibliotheque/cron/retards',               [EmpruntController::class,     'cronRetards']);
Router::post('/v2/bibliotheque/cron/reservations-expirer',  [ReservationController::class, 'cronExpirer']);
Router::post('/v2/bibliotheque/cron/rappels',               [EmpruntController::class,     'cronRappels']);
```

**Total : 57 routes**

> Les 3 routes `/cron/*` sont protégées par une clé secrète (`CRON_SECRET`) en plus du RBAC `biblio.admin`.

---

## 13. Vues

14 vues sous `app/Modules/Bibliotheque/Views/` — Tailwind CSS CDN + Lucide Icons.

| # | Vue | Description |
|---|---|---|
| 1 | `catalogue/index.php` | Liste paginée + filtres sidebar (type, catégorie, langue, disponibilité) |
| 2 | `catalogue/show.php` | Fiche ouvrage complète : infos, auteurs, exemplaires (statuts), réservation CTA |
| 3 | `catalogue/form.php` | Formulaire create/edit : ISBN lookup, tags multi-select, upload couverture |
| 4 | `exemplaires/index.php` | Tableau exemplaires d'un ouvrage, statuts colorés, QR/barcode download |
| 5 | `emprunts/index.php` | Liste tous emprunts (admin) avec filtres statut + scan de code-barres |
| 6 | `emprunts/create.php` | Checkout form : recherche exemplaire par barcode/numéro, sélection utilisateur |
| 7 | `emprunts/show.php` | Détail emprunt : timeline, boutons retour/prolongation/perte |
| 8 | `reservations/index.php` | Liste des réservations (admin + filtre par ouvrage/statut) |
| 9 | `penalites/index.php` | Liste pénalités avec totaux et actions payer/annuler |
| 10 | `inventaire/index.php` | Sessions d'inventaire passées et en cours |
| 11 | `inventaire/show.php` | Interface de scan : champ barcode JS, progression temps réel, rapport |
| 12 | `analytics/dashboard.php` | 6 KPIs + 4 graphiques Chart.js (emprunts/mois, top ouvrages, catégories, statuts) |
| 13 | `referentiels/index.php` | Onglets Auteurs / Éditeurs / Catégories (tree) / Tags — CRUD inline |
| 14 | `mon-compte/index.php` | Espace personnel : emprunts en cours, réservations, pénalités, historique |

---

## 14. Workflows métier

### 14.1 Checkout (Emprunt)

```
1. Bibliothécaire scanne ou saisit code_barre / numero_inventaire
2. ExemplaireService::trouverParBarcode() → identifie l'exemplaire
3. BiblioPolicy (EmpruntPolicy)::canBorrow() :
   a. Exemplaire statut = 'disponible' ?
   b. User quota non dépassé (max_emprunts_simultanes config) ?
   c. User n'a pas de pénalités bloquantes > seuil ?
   d. Si élève : inscription active (Scolarité) ?
4. EmpruntService::creerEmprunt(EmpruntDTO) :
   a. INSERT biblio_emprunts (statut='en_cours')
   b. UPDATE biblio_exemplaires SET statut='emprunte'
   c. Si réservation 'confirmee' existante → UPDATE statut='consommee' (archivée)
   d. DISPATCH EmpruntCree
5. Retourner reçu (optionnel, via Documents si activé)
```

### 14.2 Retour

```
1. Bibliothécaire scanne l'exemplaire
2. EmpruntService::retournerEmprunt() :
   a. UPDATE emprunts SET statut='retourne', date_retour_effectif=TODAY
   b. Calcul jours_retard = MAX(0, TODAY - date_retour_prevue)
   c. Si jours_retard > 0 → PenaliteService::creerPenaliteRetard()
   d. UPDATE exemplaires SET statut='disponible'
   e. DISPATCH EmpruntRetourne
   f. ReservationService::notifierProchainEnFile(ouvrageId)
      → Si réservation en_attente existe → statut='disponible', DISPATCH ReservationDisponible
```

### 14.3 Détection des retards (cron quotidien)

```
1. POST /v2/bibliotheque/cron/retards (appelé par tâche planifiée, ex: 23h)
2. EmpruntService::detectionRetards(etablissementId) :
   a. SELECT emprunts WHERE statut='en_cours' AND date_retour_prevue < TODAY
   b. Pour chaque : UPDATE statut='en_retard'
   c. DISPATCH EmpruntEnRetard (→ Communication picks up → SMS+email parents/user)
3. Communication::CrossModuleListener handle EmpruntEnRetard → router('emprunt_en_retard')
```

### 14.4 Rappel avant échéance (cron J-3)

```
1. POST /v2/bibliotheque/cron/rappels
2. SELECT emprunts WHERE statut='en_cours' AND date_retour_prevue = TODAY + 3 days
3. Pour chaque : Communication::NotificationService::creer() via event EmpruntRappelEcheance
   (ou directement : RoutageService si Communication activé)
```

### 14.5 Réservation → file d'attente

```
1. User demande réservation (ouvrage_id)
2. Vérification :
   a. Pas déjà réservation active pour cet user+ouvrage
   b. EmpruntPolicy::canReserve() → pas de pénalités bloquantes
3. Si un exemplaire est disponible NOW :
   → Réservation statut='disponible', date_expiration = +48h
   → DISPATCH ReservationDisponible → notification immédiate
4. Sinon :
   → position_file = MAX(position_file) + 1 pour cet ouvrage
   → statut='en_attente'
   → DISPATCH ReservationCree
5. Lors d'un retour (cf. 14.2) :
   → notifierProchainEnFile() déclenche transition du prochain en file
```

### 14.6 Inventaire

```
1. Bibliothécaire lance session (nom, date)
2. InventaireService::lancerSession() — refuse si session en_cours existante
3. Scans : POST /v2/bibliotheque/inventaires/{id}/scan
   → lookup par code_barre ou numero_inventaire
   → INSERT/UPDATE biblio_inventaire_lignes
   → Si statut_constate = 'perdu' → ExemplaireService::changerStatut('perdu')
4. InventaireService::terminerSession() :
   → Les exemplaires non scannés → ligne 'manquant'
   → Rapport final : présents / manquants / détériorés / perdus
   → DISPATCH InventaireTermine
```

### 14.7 Pénalité liée à Finance

```
1. PenaliteService::creerPenaliteRetard() calculé lors du retour
2. Si module Finance activé (enabled=true) :
   a. Appel InvoiceService::creer() avec item "Pénalité biblio retard - N jours"
   b. Stocke facture_id dans biblio_penalites.facture_id
3. DISPATCH PenaliteCreee → Communication notifie l'emprunteur
4. Paiement via Finance → DISPATCH Finance\PaymentCompleted
5. BiblioFinanceIntegrationListener l'écoute → PenaliteService::marquerPayeeViaFinance()
```

---

## 15. Intégrations cross-module

### Scolarité

| Point d'intégration | Méthode | Fallback si désactivé |
|---|---|---|
| Vérification inscription active de l'élève avant emprunt | `SELECT COUNT(*) FROM inscriptions WHERE eleve_id = ? AND annee_scolaire_id = ? AND statut = 'inscrit'` | Gracieux : si table absente → bypass |
| Récupération de la classe/niveau de l'élève (analytics) | `eleves.classe_id` JOIN | Gracieux |

### Finance

| Point d'intégration | Sens | Description |
|---|---|---|
| Création facture pénalité | Biblio → Finance | `InvoiceService::creer()` si Finance enabled |
| Paiement reçu | Finance → Biblio | `FinanceIntegrationListener` écoute `PaymentCompleted` |
| Rapprochement comptable pénalité | Finance | `biblio_penalites.facture_id` FK |

### Communication

Les events suivants seront ajoutés au `CrossModuleListener` de Communication lors de l'activation du module Bibliothèque :

| Event Biblio | Type communication | Destinataires |
|---|---|---|
| `EmpruntEnRetard` | Alerte (SMS+email si haute priorité) | Emprunteur + parents si élève |
| `ReservationDisponible` | Info (push+email) | Réservataire |
| `PenaliteCreee` | Alerte (email) | Emprunteur |
| `EmpruntRappelEcheance` | Rappel (push+email) | Emprunteur |

Configuration à ajouter dans `config/events.php` lors de Phase 9.2 :

```php
// ── MODULE BIBLIOTHÈQUE (CrossModuleListener) ──
App\Modules\Bibliotheque\Events\EmpruntEnRetard::class       => [CrossModuleListener::class],
App\Modules\Bibliotheque\Events\ReservationDisponible::class => [CrossModuleListener::class],
App\Modules\Bibliotheque\Events\PenaliteCreee::class         => [CrossModuleListener::class],
```

Et dans `RoutageService::resoudreDestinataires()` :
```php
$type === 'emprunt_en_retard'   => $this->parentsEtEleveDeUser((int)($data['user_id'] ?? 0)),
$type === 'reservation_dispo'   => [(int)($data['user_id'] ?? 0)],
$type === 'penalite_biblio'     => [(int)($data['user_id'] ?? 0)],
```

### Documents

| Point d'intégration | Description |
|---|---|
| Reçu d'emprunt numérique | `EmpruntCree` → DocumentService::creer() avec PDF reçu (V3) |
| Ouvrage numérique | `biblio_ouvrages.type='numerique'` → lien vers `doc_documents.id` (V3) |

### RH

| Point d'intégration | Description |
|---|---|
| Enseignants comme emprunteurs | `users` table partagée — `user_id` dans `biblio_emprunts` peut être un employé |
| Vérification contrat actif | `EmpruntPolicy` peut vérifier `rh_contrats.statut='actif'` si RH activé |

---

## 16. Codes-barres & QR — Architecture

### Principe

L'architecture prépare les interfaces en V2 (stubs SVG simples) pour une intégration V3 avec une vraie librairie (ex: `picqer/php-barcode-generator`, `endroid/qr-code`).

### Interface `BarcodeInterface`

```php
namespace App\Modules\Bibliotheque\Contracts;

interface BarcodeInterface
{
    public function generateSvg(string $code, string $type = 'C128'): string;
    public function generatePng(string $code, string $type = 'C128'): string;
    public function disponible(): bool;
}
```

### Interface `QrCodeInterface`

```php
namespace App\Modules\Bibliotheque\Contracts;

interface QrCodeInterface
{
    public function generateSvg(string $data, int $size = 200): string;
    public function generatePng(string $data, int $size = 200): string;
    public function disponible(): bool;
}
```

### Implémentation V2 — `SimpleBarcodeService`

```
- Génère un SVG minimaliste (lignes verticales proportionnelles à la valeur ASCII)
- Suffisant pour affichage HTML + impression
- Retourne un SVG `<svg>` inline
- disponible() → true toujours
```

### Implémentation V2 — `SimpleQrCodeService`

```
- Génère un SVG QR simplifié via matrice de pixels
- OU : encode en URL de service externe comme fallback (configurable)
- qr_data dans biblio_exemplaires = JSON encodé : {"id":42,"num":"BIB-2026-0001","titre":"...", "ouvrage_id":7}
```

### Données QR par exemplaire

```json
{
  "module": "bibliotheque",
  "exemplaire_id": 42,
  "numero_inventaire": "BIB-2026-0001",
  "ouvrage_id": 7,
  "titre": "Le Petit Prince",
  "isbn13": "9782070360246",
  "etablissement_id": 1
}
```

### Workflow scan mobile (V3)

```
1. App mobile scanne QR
2. Décode JSON → extrait exemplaire_id
3. Appelle GET /v2/bibliotheque/exemplaires/{id} → statut + ouvrage
4. Propose : Emprunter / Retourner / Voir fiche
```

---

## 17. Recherche avancée

### Moteur SQL (V2)

```sql
SELECT o.*,
       COUNT(DISTINCT e.id) AS nb_exemplaires,
       SUM(CASE WHEN e.statut = 'disponible' THEN 1 ELSE 0 END) AS nb_disponibles,
       MATCH(o.titre, o.sous_titre, o.resume) AGAINST (:terme IN BOOLEAN MODE) AS relevance
FROM biblio_ouvrages o
LEFT JOIN biblio_exemplaires e ON e.ouvrage_id = o.id AND e.deleted_at IS NULL
WHERE o.deleted_at IS NULL
  AND o.etablissement_id = :etab
  [AND o.type = :type]
  [AND EXISTS (SELECT 1 FROM biblio_ouvrage_categories oc WHERE oc.ouvrage_id = o.id AND oc.categorie_id = :cat)]
  [AND :terme IS NULL OR MATCH(o.titre, o.sous_titre, o.resume) AGAINST (:terme IN BOOLEAN MODE)]
GROUP BY o.id
HAVING [:disponible_seulement → nb_disponibles > 0]
ORDER BY [relevance DESC | o.titre | o.annee_edition | nb_emprunts DESC]
LIMIT :per_page OFFSET :offset
```

### Filtres disponibles

- `terme` : recherche fulltext (titre + sous_titre + résumé)
- `type` : livre / revue / bd / manuel / périodique / numérique / autre
- `categorie_id` : catégorie (inclut les sous-catégories via arborescence)
- `auteur_id` : filtrage par auteur
- `editeur_id` : filtrage par éditeur
- `langue` : fr / en / ar / ...
- `annee_min` / `annee_max` : fourchette d'année d'édition
- `tag_ids[]` : filtrage multi-tags (ET logique)
- `disponible_seulement` : uniquement les ouvrages avec ≥ 1 exemplaire disponible
- `tri` : pertinence / titre / date / popularité

### Autocomplétion (AJAX)

```
GET /v2/bibliotheque/catalogue/search?q=harry&suggest=1
→ Retourne JSON [{id, titre, isbn13, auteurs, nb_disponibles}] limit=5
```

---

## 18. Statistiques & Analytics

### KPIs principaux (dashboard)

| KPI | Source |
|---|---|
| Total ouvrages (actifs) | `COUNT(biblio_ouvrages WHERE statut='actif')` |
| Total exemplaires | `COUNT(biblio_exemplaires WHERE deleted_at IS NULL)` |
| Exemplaires disponibles | `COUNT(biblio_exemplaires WHERE statut='disponible')` |
| Emprunts en cours | `COUNT(biblio_emprunts WHERE statut='en_cours')` |
| Emprunts en retard | `COUNT(biblio_emprunts WHERE statut='en_retard')` |
| Réservations en attente | `COUNT(biblio_reservations WHERE statut='en_attente')` |
| Pénalités impayées (montant) | `SUM(biblio_penalites WHERE statut='en_attente')` |

### Graphiques (Chart.js)

1. **Emprunts par mois** (12 derniers mois) — Line chart
2. **Top 10 ouvrages** les plus empruntés — Horizontal bar
3. **Répartition par catégorie** — Donut chart
4. **Statuts exemplaires** — Pie chart (disponible/emprunté/réservé/réparation/perdu/retiré)

### Exports

- CSV : liste complète emprunts, pénalités, inventaire
- PDF : rapport mensuel, bilan annuel

---

## 19. Préparation SaaS / Multi-établissements

- `etablissement_id` présent sur toutes les 14 tables
- Toutes les queries filtrent par `etablissement_id`
- `numero_inventaire` UNIQUE par `(numero_inventaire, etablissement_id)` — pas de collision inter-étab
- Catégories et tags scopés par établissement (possibilité de partage V3 via `etablissement_id = 0`)
- Configuration métier (quotas, tarifs) stockée par établissement dans `parametres` V2 ou constantes module
- `BiblioAnalyticsService` filtre systématiquement par `etablissement_id`

---

## 20. Préparation Mobile / API publique

### Endpoints JSON (déjà prêts)

Les controllers retournent `$this->json()` pour les mutations. Les vues HTML restent accessibles en `Accept: text/html`. En V3, un middleware `Accept: application/json` retournera directement JSON pour tous les endpoints.

### API Catalogue public (lecture)

```
GET /v2/bibliotheque/catalogue → JSON si ?format=json
GET /v2/bibliotheque/ouvrages/{id} → JSON fiche ouvrage
GET /v2/bibliotheque/catalogue/search?q=... → JSON résultats
```

### Workflow mobile (V3)

```
1. Scan QR/barcode → identification exemplaire
2. Vérification disponibilité en temps réel
3. Demande emprunt / retour via API
4. Notifications push via Communication module
```

### Pagination API

Toutes les listes retournent :
```json
{
  "data": [...],
  "meta": {"total": 142, "page": 1, "per_page": 20, "last_page": 8}
}
```

---

## 21. Configuration métier

Paramètres configurables par établissement (via `parametres` V2 ou constantes de fallback) :

| Paramètre | Valeur par défaut | Description |
|---|---|---|
| `biblio.max_emprunts_simultanes` | 3 | Quota emprunts par emprunteur |
| `biblio.duree_emprunt_jours` | 14 | Durée standard d'un emprunt |
| `biblio.max_prolongations` | 2 | Nombre de prolongations autorisées |
| `biblio.duree_prolongation_jours` | 7 | Durée de chaque prolongation |
| `biblio.delai_confirmation_reservation_h` | 48 | Heures pour confirmer une réservation disponible |
| `biblio.tarif_penalite_retard_jour` | 0.50 | Montant en € par jour de retard |
| `biblio.valeur_remplacement_defaut` | 25.00 | Valeur de remplacement si ouvrage perdu |
| `biblio.penalites_bloquantes_seuil` | 3 | Nombre de pénalités impayées bloquant les emprunts |
| `biblio.rappel_avant_echeance_jours` | 3 | Jours avant échéance pour le rappel cron |

---

## 22. Ordre d'implémentation

### Phase 9.2 — Catalogue + Référentiels
**Livrables :** `biblio_auteurs`, `biblio_editeurs`, `biblio_categories`, `biblio_tags`, `biblio_ouvrages`, `biblio_ouvrage_*`
- OuvrageRepository, AuteurRepository, EditeurRepository, CategorieRepository, TagRepository
- CatalogueService, OuvrageDTO, OuvrageFiltersDTO
- CatalogueController (8 actions), ReferentielController (14 actions)
- Vues : `catalogue/index`, `catalogue/show`, `catalogue/form`, `referentiels/index`
- Events : OuvrageAjoute, OuvrageModifie, OuvrageArchive

### Phase 9.3 — Exemplaires + Codes-barres/QR
**Livrables :** `biblio_exemplaires`
- ExemplaireRepository, ExemplaireService, ExemplaireDTO
- BarcodeInterface + SimpleBarcodeService, QrCodeInterface + SimpleQrCodeService
- ExemplaireController (7 actions)
- Vue : `exemplaires/index`
- Events : ExemplaireAjoute, ExemplaireStatutChange

### Phase 9.4 — Emprunts + Machine d'états
**Livrables :** `biblio_emprunts`
- EmpruntRepository, EmpruntService, EmpruntDTO, EmpruntPolicy
- EmpruntController (8 actions + 1 cron)
- Vues : `emprunts/index`, `emprunts/create`, `emprunts/show`
- Events : EmpruntCree, EmpruntRetourne, EmpruntEnRetard, EmpruntProlonge, EmpruntPerdu

### Phase 9.5 — Réservations + File FIFO
**Livrables :** `biblio_reservations`
- ReservationRepository, ReservationService, ReservationDTO
- ReservationController (6 actions + 1 cron)
- Vue : `reservations/index`
- Events : ReservationCree, ReservationDisponible, ReservationConfirmee, ReservationAnnulee, ReservationExpiree

### Phase 9.6 — Pénalités + Intégration Finance
**Livrables :** `biblio_penalites`
- PenaliteRepository, PenaliteService, PenaliteDTO
- FinanceIntegrationListener (écoute PaymentCompleted)
- PenaliteController (5 actions)
- Vue : `penalites/index`
- Events : PenaliteCreee, PenalitePayee

### Phase 9.7 — Inventaire
**Livrables :** `biblio_inventaires`, `biblio_inventaire_lignes`
- InventaireRepository, InventaireService, InventaireDTO
- InventaireController (5 actions)
- Vues : `inventaire/index`, `inventaire/show`
- Events : InventaireLance, InventaireTermine

### Phase 9.8 — Recherche avancée + Analytics + Mon Compte
**Livrables :** Aucune nouvelle table
- RechercheService, BiblioAnalyticsService, RechercheDTO
- AnalyticsController (2 actions), MonCompteController (3 actions)
- Vues : `analytics/dashboard`, `mon-compte/index`
- Intégration Communication events config (CrossModuleListener extensions)

### Phase 9.9 — Integration Review
- Audit architecture complet
- Vérification RBAC, events, routes, sécurité
- Tests : workflows checkout/retour/retard/réservation/pénalité
- Corrections
- Rapport : `BIBLIOTHEQUE_INTEGRATION_REVIEW.md`

### Phase 9.10 — Module Freeze
- Score cible : ≥ 8.5/10
- Rapport : `BIBLIOTHEQUE_MODULE_FREEZE.md`
- Version : 2.0.0
- `enabled: false` → documentation pour activation

---

## 23. module.json cible

```json
{
  "name": "Bibliotheque",
  "slug": "bibliotheque",
  "version": "2.0.0",
  "description": "Module Bibliothèque V2 — catalogue, exemplaires, emprunts, réservations, pénalités, inventaire, codes-barres/QR, recherche avancée, analytics",
  "namespace": "App\\Modules\\Bibliotheque",
  "enabled": false,
  "routes": "app/Modules/Bibliotheque/routes.php",
  "phase": "9.10",
  "depends": [],
  "migrations_pending": [
    "Phase 9.2 — biblio_001_bibliotheque.sql (14 tables)"
  ],
  "controllers": [
    "Controllers/CatalogueController.php",
    "Controllers/ExemplaireController.php",
    "Controllers/EmpruntController.php",
    "Controllers/ReservationController.php",
    "Controllers/PenaliteController.php",
    "Controllers/InventaireController.php",
    "Controllers/AnalyticsController.php",
    "Controllers/ReferentielController.php",
    "Controllers/MonCompteController.php"
  ],
  "services": [
    "Services/CatalogueService.php",
    "Services/ExemplaireService.php",
    "Services/EmpruntService.php",
    "Services/ReservationService.php",
    "Services/PenaliteService.php",
    "Services/InventaireService.php",
    "Services/RechercheService.php",
    "Services/BiblioAnalyticsService.php"
  ],
  "contracts": [
    "Contracts/BarcodeInterface.php",
    "Contracts/QrCodeInterface.php"
  ],
  "repositories": [
    "Repositories/OuvrageRepository.php",
    "Repositories/ExemplaireRepository.php",
    "Repositories/EmpruntRepository.php",
    "Repositories/ReservationRepository.php",
    "Repositories/PenaliteRepository.php",
    "Repositories/InventaireRepository.php",
    "Repositories/AuteurRepository.php",
    "Repositories/EditeurRepository.php",
    "Repositories/CategorieRepository.php",
    "Repositories/TagRepository.php"
  ],
  "dto": [
    "DTO/OuvrageDTO.php",
    "DTO/OuvrageFiltersDTO.php",
    "DTO/ExemplaireDTO.php",
    "DTO/EmpruntDTO.php",
    "DTO/ReservationDTO.php",
    "DTO/PenaliteDTO.php",
    "DTO/RechercheDTO.php",
    "DTO/InventaireDTO.php"
  ],
  "models": [
    "Models/OuvrageModel.php",
    "Models/ExemplaireModel.php",
    "Models/EmpruntModel.php",
    "Models/ReservationModel.php",
    "Models/PenaliteModel.php"
  ],
  "policies": [
    "Policies/BiblioPolicy.php",
    "Policies/EmpruntPolicy.php"
  ],
  "events": [
    "Events/OuvrageAjoute.php",
    "Events/OuvrageModifie.php",
    "Events/OuvrageArchive.php",
    "Events/ExemplaireAjoute.php",
    "Events/ExemplaireStatutChange.php",
    "Events/EmpruntCree.php",
    "Events/EmpruntRetourne.php",
    "Events/EmpruntEnRetard.php",
    "Events/EmpruntProlonge.php",
    "Events/EmpruntPerdu.php",
    "Events/ReservationCree.php",
    "Events/ReservationDisponible.php",
    "Events/ReservationConfirmee.php",
    "Events/ReservationAnnulee.php",
    "Events/ReservationExpiree.php",
    "Events/PenaliteCreee.php",
    "Events/PenalitePayee.php",
    "Events/InventaireTermine.php"
  ],
  "listeners": [
    "Listeners/AuditListener.php",
    "Listeners/FinanceIntegrationListener.php",
    "Listeners/BiblioNotificationHandler.php"
  ],
  "permissions": [
    "biblio.view",
    "biblio.search",
    "biblio.borrow",
    "biblio.reserve",
    "biblio.manage_loans",
    "biblio.manage_catalogue",
    "biblio.manage_exemplaires",
    "biblio.manage_penalties",
    "biblio.inventory",
    "biblio.analytics",
    "biblio.admin"
  ],
  "sql_tables": [
    "biblio_auteurs",
    "biblio_editeurs",
    "biblio_categories",
    "biblio_tags",
    "biblio_ouvrages",
    "biblio_ouvrage_auteurs",
    "biblio_ouvrage_categories",
    "biblio_ouvrage_tags",
    "biblio_exemplaires",
    "biblio_emprunts",
    "biblio_reservations",
    "biblio_penalites",
    "biblio_inventaires",
    "biblio_inventaire_lignes"
  ],
  "sql_migrations": [
    "database/migrations/biblio_001_bibliotheque.sql"
  ],
  "modules_to_modify": [
    "config/modules.php — ajout clé 'bibliotheque' (enabled: false)",
    "config/events.php — ajout 18 events propres + FinanceIntegrationListener + CrossModuleListener extensions",
    "config/permissions.php — ajout 11 permissions biblio.* par rôle"
  ],
  "cross_module_events_consumed": [
    "Finance\\Events\\PaymentCompleted → FinanceIntegrationListener",
    "Communication\\CrossModuleListener ← EmpruntEnRetard, ReservationDisponible, PenaliteCreee"
  ],
  "author": "SCOLARIS V2",
  "created_at": "2026-07-03"
}
```

---

## 24. Dette technique anticipée

| Ref | Description | Sévérité | Phase cible |
|---|---|---|---|
| DT-BIB-001 | `SimpleBarcodeService` = SVG approximatif — provider réel (picqer/php-barcode-generator) requis pour impression physique | Majeure | V3 |
| DT-BIB-002 | `SimpleQrCodeService` = SVG simplifié — endroid/qr-code ou bacon/bacon-qr-code en V3 | Majeure | V3 |
| DT-BIB-003 | Recherche fulltext MySQL — passage à Elasticsearch/Meilisearch pour recherche élastique en V3 | Mineure | V3 |
| DT-BIB-004 | Catalogue non partageable inter-établissements (chaque étab a son propre catalogue) — référentiel commun en V3 | Mineure | V3 |
| DT-BIB-005 | `CatalogueService` sans lookup ISBN externe (Open Library / BnF API) — saisi manuellement en V2 | Mineure | V3 |
| DT-BIB-006 | Reçu d'emprunt non généré automatiquement (Documents module requis) | Mineure | V3 |
| DT-BIB-007 | Ouvrage numérique (type='numerique') non lié à Documents.doc_documents | Mineure | V3 |
| DT-BIB-008 | Cron routes protégées par `CRON_SECRET` header — à migrer vers vrai scheduler en V3 | Mineure | V3 |

---

## Métriques Blueprint

| Métrique | Valeur |
|---|---|
| Tables SQL | 14 (`biblio_*`) |
| Migration unique | `biblio_001_bibliotheque.sql` |
| Services | 8 |
| Repositories | 10 |
| DTOs | 8 |
| Modèles | 5 |
| Policies | 2 |
| Events | 18 |
| Listeners | 3 |
| Controllers | 9 |
| Routes | 57 |
| Vues | 14 |
| Permissions | 11 (`biblio.*`) |
| Phases d'implémentation | 9 (9.2 → 9.10) |
| Intégrations modules | 5 (Scolarité, Finance, Communication, Documents, RH) |
| Contrats (interfaces) | 2 (BarcodeInterface, QrCodeInterface) |

---

*Blueprint SCOLARIS V2 — Module Bibliothèque — Phase 9.1 — 2026-07-03*
