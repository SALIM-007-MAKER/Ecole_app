# DOCUMENTS V2 BLUEPRINT — SCOLARIS
**Phase 7.1 — Conception du Module Documents V2**
Date : 2026-07-02
Statut : BLUEPRINT — aucun fichier de code à créer ou modifier
Dépendances : Core ✓ | Shared Services ✓ | Event System ✓ | RBAC ✓ | RH ✓ | Finance ✓ | VieScolaire ✓

---

## 0. Contexte & Positionnement

### 0.1 Problème actuel

SCOLARIS V2 a plusieurs implémentations documentaires silotées et incompatibles :

| Implémentation existante | Table(s) | Scope | Statut |
|---|---|---|---|
| `rh_documents` (RH V2) | `rh_documents`, `rh_document_versions`, `rh_document_historique` | Dossiers employés uniquement | **FROZEN** |
| `justifications` (VieScolaire) | `vs_absences.justification_fichier` | Pièces jointes absences | Colonne unique |
| `DocumentGenere` Event (V1) | — | Bulletins/reçus PDF | Event seul, pas de stockage |
| `UploadService` (Shared) | — | Avatars, photos, imports CSV | Service partagé, pas de registry |

Le module Documents V2 est une **plateforme transversale unifiée**. Il ne remplace pas les tables `rh_documents` existantes (FROZEN) — il les **absorbe progressivement** via bridge pattern.

### 0.2 Principes fondamentaux

```
RÈGLE 1 : Aucune modification des tables rh_documents, rh_document_versions,
           rh_document_historique — elles restent la source de vérité RH.

RÈGLE 2 : Le module Documents ne contient aucune logique métier.
           Il ne sait pas ce qu'est un "contrat" ou une "facture".
           Il gère des fichiers avec métadonnées, arborescences, partages.

RÈGLE 3 : Les modules métier (RH, Finance…) référencent les documents via
           (module_source, entite_type, entite_id) — pattern polymorphe.

RÈGLE 4 : Un document a toujours un owner module. Les partages sont
           des copies de permission, jamais des transferts de propriété.
```

### 0.3 Modules consommateurs prévus

| Module | Usage principal | Entites référencées |
|---|---|---|
| RH | Contrats signés, diplômes, certifications, sanctions | employe, contrat, evaluation, formation |
| Finance | Devis, factures PDF, contrats fournisseurs, RIB | facture, fournisseur, decaissement |
| Académique | Bulletins archivés, feuilles de notes, attestations | eleve, bulletin, evaluation |
| Vie Scolaire | Justificatifs absences, autorisations sorties, sanctions | absence, discipline, activite |
| Scolarité | Dossiers d'inscription, pièces d'identité élèves | eleve, inscription, famille |
| Bibliothèque (V3) | Catalogue numérique, ressources pédagogiques | livre, ressource |
| Inventaire (V3) | Fiches techniques, garanties, BL | materiel, fournisseur |
| Communication (V3) | Circulaires, lettres officielles, PV | message, evenement |

---

## 1. Architecture du module

### 1.1 Namespace & position

```
App\Modules\Documents\
```

Clé `config/modules.php` : `'documents'`
Préfixe tables SQL : `doc_`
Préfixe routes : `/v2/documents`
Préfixe permissions RBAC : `document.` et `folder.`

### 1.2 Structure de fichiers

```
app/Modules/Documents/
│
├── module.json
├── routes.php
│
├── Controllers/
│   ├── DocumentController.php        ← CRUD documents
│   ├── FolderController.php          ← Arborescence
│   ├── TrashController.php           ← Corbeille
│   ├── ShareController.php           ← Partages
│   ├── SearchController.php          ← Recherche plein texte
│   ├── TagController.php             ← Tags
│   ├── PreviewController.php         ← Prévisualisation
│   ├── SignatureController.php       ← Signatures (stub)
│   └── AdminController.php           ← Quotas, catégories
│
├── Services/
│   ├── DocumentService.php           ← Orchestrateur principal
│   ├── FolderService.php             ← Gestion arborescence
│   ├── StorageService.php            ← Abstraction stockage
│   ├── VersioningService.php         ← Gestion versions
│   ├── ShareService.php              ← Partages contrôlés
│   ├── TagService.php                ← Gestion tags
│   ├── QuotaService.php              ← Contrôle quotas
│   ├── SearchService.php             ← FULLTEXT MySQL
│   ├── PreviewService.php            ← Génération previews
│   └── SignatureService.php          ← Préparation signatures (stub)
│
├── Repositories/
│   ├── DocumentRepository.php
│   ├── FolderRepository.php
│   ├── VersionRepository.php
│   ├── ShareRepository.php
│   ├── TagRepository.php
│   ├── TrashRepository.php
│   ├── HistoriqueRepository.php
│   └── QuotaRepository.php
│
├── DTO/
│   ├── DocumentDTO.php
│   ├── DocumentFiltersDTO.php
│   ├── FolderDTO.php
│   ├── ShareDTO.php
│   ├── TagDTO.php
│   ├── SearchDTO.php
│   └── QuotaDTO.php
│
├── Models/
│   ├── DocumentModel.php             ← Constantes (types, statuts, conf.)
│   ├── FolderModel.php
│   └── ShareModel.php
│
├── Policies/
│   ├── DocumentPolicy.php
│   ├── FolderPolicy.php
│   └── SharePolicy.php
│
├── Events/
│   ├── DocumentUploaded.php
│   ├── DocumentVersioned.php
│   ├── DocumentArchived.php
│   ├── DocumentRestored.php
│   ├── DocumentTrashed.php
│   ├── DocumentPurged.php
│   ├── DocumentShared.php
│   ├── DocumentShareRevoked.php
│   ├── DocumentTagged.php
│   ├── DocumentMoved.php
│   ├── DocumentExpired.php
│   ├── DocumentSignatureRequested.php
│   ├── DocumentSigned.php
│   ├── FolderCreated.php
│   ├── FolderDeleted.php
│   └── QuotaExceeded.php
│
├── Listeners/
│   ├── AuditListener.php
│   ├── NotificationListener.php
│   ├── QuotaListener.php
│   └── SearchIndexListener.php
│
└── Views/
    ├── index.php
    ├── show.php
    ├── create.php
    ├── edit.php
    ├── folder.php
    ├── search.php
    ├── trash.php
    ├── shares.php
    └── admin/
        ├── quotas.php
        └── categories.php
```

---

## 2. Base de données

### 2.1 Vue d'ensemble des tables

| Table | Rôle | Lignes estimées |
|---|---|---|
| `doc_categories` | Référentiel types documentaires | ~50 |
| `doc_folders` | Arborescence logique | ~200 |
| `doc_documents` | Registre principal | ~10 000+ |
| `doc_versions` | Historique versions | ~15 000+ |
| `doc_tags` | Référentiel tags | ~100 |
| `doc_document_tags` | Pivot document↔tag | ~30 000+ |
| `doc_partages` | Partages contrôlés | ~5 000+ |
| `doc_historique` | Journal actions | ~50 000+ |
| `doc_corbeille` | Métadonnées corbeille | ~1 000+ |
| `doc_quotas` | Quotas par module/entité | ~50 |
| `doc_signatures` | Préparation signatures | ~500+ |

Total : **11 tables** — préfixe `doc_`

### 2.2 Schémas SQL

#### `doc_categories`
```sql
CREATE TABLE doc_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50)  NOT NULL UNIQUE,          -- 'contrat', 'diplome', 'facture'…
    libelle     VARCHAR(100) NOT NULL,
    module_hint VARCHAR(30)  NULL,                     -- module recommandé (non restrictif)
    icone       VARCHAR(50)  NULL,                     -- nom icône Lucide
    couleur     VARCHAR(20)  NULL DEFAULT 'slate',
    ordre       SMALLINT     NOT NULL DEFAULT 0,
    actif       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `doc_folders`
```sql
CREATE TABLE doc_folders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id       INT UNSIGNED NULL,                  -- NULL = racine
    nom             VARCHAR(200) NOT NULL,
    description     TEXT         NULL,
    module_source   VARCHAR(30)  NOT NULL,              -- 'rh','finance','scolarite'…
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,  -- SaaS ready
    icone           VARCHAR(50)  NULL,
    couleur         VARCHAR(20)  NULL DEFAULT 'slate',
    ordre           SMALLINT     NOT NULL DEFAULT 0,
    created_by      INT UNSIGNED NOT NULL,
    deleted_at      DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (parent_id) REFERENCES doc_folders(id) ON DELETE RESTRICT,
    INDEX idx_parent    (parent_id),
    INDEX idx_module    (module_source),
    INDEX idx_etab      (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `doc_documents`
```sql
CREATE TABLE doc_documents (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folder_id           INT UNSIGNED NULL,              -- NULL = racine module
    categorie_id        INT UNSIGNED NULL,
    titre               VARCHAR(255) NOT NULL,
    description         TEXT         NULL,

    -- Polymorphisme source
    module_source       VARCHAR(30)  NOT NULL,          -- 'rh','finance','scolarite'…
    entite_type         VARCHAR(60)  NULL,              -- 'employe','facture','eleve'…
    entite_id           INT UNSIGNED NULL,              -- FK logique (non enforced)

    -- Stockage physique
    chemin_stockage     VARCHAR(500) NOT NULL,          -- relatif ROOT_PATH
    mime_type           VARCHAR(100) NOT NULL,
    extension           VARCHAR(10)  NOT NULL,
    taille_octets       INT UNSIGNED NOT NULL DEFAULT 0,
    checksum_sha256     CHAR(64)     NULL,              -- intégrité

    -- Métadonnées
    confidentialite     ENUM('public','interne','confidentiel','secret') NOT NULL DEFAULT 'interne',
    statut              ENUM('brouillon','actif','expire','archive','corbeille') NOT NULL DEFAULT 'actif',
    date_emission       DATE         NULL,
    date_expiration     DATE         NULL,
    alerte_jours        SMALLINT     NOT NULL DEFAULT 30,
    reference_externe   VARCHAR(100) NULL,
    emetteur            VARCHAR(100) NULL,
    notes               TEXT         NULL,
    metadata            JSON         NULL,              -- données libres par module

    -- Versioning
    version_courante    SMALLINT UNSIGNED NOT NULL DEFAULT 1,

    -- SaaS
    etablissement_id    INT UNSIGNED NOT NULL DEFAULT 1,

    -- Signatures (préparation)
    signature_requise   TINYINT(1)   NOT NULL DEFAULT 0,
    signature_statut    ENUM('non_requis','en_attente','partiel','complet') NOT NULL DEFAULT 'non_requis',

    -- Audit
    created_by          INT UNSIGNED NOT NULL,
    updated_by          INT UNSIGNED NULL,
    archived_by         INT UNSIGNED NULL,
    archived_at         DATETIME     NULL,
    deleted_at          DATETIME     NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (folder_id)     REFERENCES doc_folders(id)     ON DELETE SET NULL,
    FOREIGN KEY (categorie_id)  REFERENCES doc_categories(id)  ON DELETE SET NULL,

    INDEX idx_folder        (folder_id),
    INDEX idx_module        (module_source, entite_type, entite_id),
    INDEX idx_statut        (statut),
    INDEX idx_conf          (confidentialite),
    INDEX idx_expiration    (date_expiration, statut),
    INDEX idx_etab          (etablissement_id),

    FULLTEXT INDEX ft_search (titre, description, reference_externe, notes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `doc_versions`
```sql
CREATE TABLE doc_versions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id     INT UNSIGNED NOT NULL,
    numero          SMALLINT UNSIGNED NOT NULL,         -- 1, 2, 3…
    chemin_stockage VARCHAR(500) NOT NULL,
    taille_octets   INT UNSIGNED NOT NULL DEFAULT 0,
    checksum_sha256 CHAR(64)     NULL,
    mime_type       VARCHAR(100) NOT NULL,
    notes           TEXT         NULL,
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (document_id) REFERENCES doc_documents(id) ON DELETE CASCADE,
    UNIQUE KEY uq_doc_version (document_id, numero),
    INDEX idx_doc (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `doc_tags`
```sql
CREATE TABLE doc_tags (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(50)  NOT NULL,
    couleur         VARCHAR(20)  NOT NULL DEFAULT 'slate',
    module_source   VARCHAR(30)  NULL,                  -- NULL = global
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_nom_etab (nom, etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `doc_document_tags`
```sql
CREATE TABLE doc_document_tags (
    document_id INT UNSIGNED NOT NULL,
    tag_id      INT UNSIGNED NOT NULL,
    created_by  INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (document_id, tag_id),
    FOREIGN KEY (document_id) REFERENCES doc_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)      REFERENCES doc_tags(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `doc_partages`
```sql
CREATE TABLE doc_partages (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id         INT UNSIGNED NOT NULL,
    destinataire_type   ENUM('user','role','module','externe') NOT NULL,
    destinataire_id     INT UNSIGNED NULL,              -- NULL si 'externe'
    destinataire_email  VARCHAR(150) NULL,              -- si 'externe'
    permission          ENUM('lecture','telechargement','commentaire') NOT NULL DEFAULT 'lecture',
    token_acces         CHAR(64)     NULL,              -- lien sécurisé externe
    date_expiration     DATETIME     NULL,
    notifie             TINYINT(1)   NOT NULL DEFAULT 0,
    created_by          INT UNSIGNED NOT NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at          DATETIME     NULL,
    revoked_by          INT UNSIGNED NULL,

    FOREIGN KEY (document_id) REFERENCES doc_documents(id) ON DELETE CASCADE,
    INDEX idx_document  (document_id),
    INDEX idx_dest      (destinataire_type, destinataire_id),
    INDEX idx_token     (token_acces),
    INDEX idx_actif     (revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `doc_historique`
```sql
CREATE TABLE doc_historique (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    action      VARCHAR(50)  NOT NULL,                  -- 'upload','version','archive'…
    details     JSON         NULL,
    ip          VARCHAR(45)  NULL,
    user_agent  VARCHAR(300) NULL,
    created_by  INT UNSIGNED NOT NULL,
    created_by_nom VARCHAR(100) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (document_id) REFERENCES doc_documents(id) ON DELETE CASCADE,
    INDEX idx_document (document_id),
    INDEX idx_action   (action),
    INDEX idx_date     (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `doc_corbeille`
```sql
CREATE TABLE doc_corbeille (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id     INT UNSIGNED NOT NULL UNIQUE,
    raison          TEXT         NULL,
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    purge_avant     DATETIME     NULL,                  -- auto-purge date

    FOREIGN KEY (document_id) REFERENCES doc_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `doc_quotas`
```sql
CREATE TABLE doc_quotas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_source   VARCHAR(30)  NOT NULL,
    entite_type     VARCHAR(60)  NULL,                  -- NULL = quota global module
    entite_id       INT UNSIGNED NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    quota_octets    BIGINT UNSIGNED NOT NULL,           -- 0 = illimité
    utilise_octets  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_quota (module_source, entite_type, entite_id, etablissement_id),
    INDEX idx_module (module_source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `doc_signatures`
```sql
-- Préparation uniquement — implémentation V3
CREATE TABLE doc_signatures (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id         INT UNSIGNED NOT NULL,
    version_id          INT UNSIGNED NULL,
    signataire_type     ENUM('user','externe') NOT NULL,
    signataire_id       INT UNSIGNED NULL,
    signataire_email    VARCHAR(150) NULL,
    signataire_nom      VARCHAR(100) NULL,
    statut              ENUM('en_attente','signe','refuse','expire') NOT NULL DEFAULT 'en_attente',
    token_signature     CHAR(64)     NULL,
    hash_document       CHAR(64)     NULL,              -- SHA-256 du contenu à signer
    signed_at           DATETIME     NULL,
    ip_signataire       VARCHAR(45)  NULL,
    metadata            JSON         NULL,
    created_by          INT UNSIGNED NOT NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at          DATETIME     NULL,

    FOREIGN KEY (document_id) REFERENCES doc_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (version_id)  REFERENCES doc_versions(id)  ON DELETE SET NULL,
    INDEX idx_document  (document_id),
    INDEX idx_token     (token_signature),
    INDEX idx_statut    (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 3. Services

### 3.1 `DocumentService` — Orchestrateur principal

**Responsabilité** : CRUD documents, versioning, archivage, corbeille, restauration.

```php
namespace App\Modules\Documents\Services;

class DocumentService
{
    // Dépendances injectées par constructeur
    private DocumentRepository  $repo;
    private VersionRepository   $versions;
    private HistoriqueRepository $historique;
    private StorageService      $storage;
    private QuotaService        $quota;
    private EventDispatcher     $dispatcher;  // statique

    // ── Interface publique figée (V2.0) ──────────────────────────────────────

    /**
     * Upload initial d'un document.
     * @param  array  $file      $_FILES['fichier']
     * @param  DocumentDTO $dto
     * @param  int    $userId
     * @param  string $userName
     * @return int    $documentId
     * @throws QuotaExceededException
     * @throws \InvalidArgumentException si DTO invalide
     * @throws \RuntimeException si upload échoue
     */
    public function uploader(array $file, DocumentDTO $dto, int $userId, string $userName): int;

    /**
     * Nouvelle version d'un document existant.
     * Incrémente version_courante, crée doc_versions, historique.
     */
    public function nouvelleVersion(int $documentId, array $file, string $notes, int $userId, string $userName): int;

    /**
     * Mise à jour des métadonnées seules (sans nouveau fichier).
     */
    public function mettreAJourMetadonnees(int $documentId, DocumentDTO $dto, int $userId, string $userName): void;

    /**
     * Archiver un document (statut → 'archive').
     * @throws \RuntimeException si déjà archivé
     */
    public function archiver(int $documentId, int $userId, string $userName, ?string $raison = null): void;

    /**
     * Déplacer vers la corbeille (soft delete, statut → 'corbeille').
     * Enregistre dans doc_corbeille avec date purge_avant = now + 30j.
     * @throws \RuntimeException si document en corbeille ou archive
     */
    public function corbeille(int $documentId, int $userId, string $userName, ?string $raison = null): void;

    /**
     * Restaurer depuis la corbeille ou l'archive.
     * Statut recalculé : 'expire' si date_expiration < today, sinon 'actif'.
     */
    public function restaurer(int $documentId, int $userId, string $userName): void;

    /**
     * Purge physique définitive (admin uniquement).
     * Supprime fichier disque + enregistrements DB (CASCADE).
     * Dispatch DocumentPurged pour audit.
     */
    public function purger(int $documentId, int $userId): void;

    /**
     * Déplacer vers un autre dossier.
     */
    public function deplacer(int $documentId, ?int $newFolderId, int $userId): void;

    /**
     * Vérifier les expirations (batch cron).
     * Passe statut 'actif' → 'expire' pour les docs date_expiration < today.
     * Dispatch DocumentExpired par document.
     * @return int nombre de documents expirés
     */
    public function verifierExpirations(): int;

    // ── Lectures ─────────────────────────────────────────────────────────────

    public function trouver(int $documentId): array;
    public function lister(DocumentFiltersDTO $filters): array;
    public function compter(DocumentFiltersDTO $filters): int;
    public function listerVersions(int $documentId): array;
    public function listerHistorique(int $documentId): array;
    public function listerCorbeille(DocumentFiltersDTO $filters): array;
}
```

### 3.2 `StorageService` — Abstraction stockage

**Responsabilité** : isolation complète du système de fichiers. Prépare la migration vers S3/cloud.

```php
class StorageService
{
    // Répertoire de base : storage/documents/{module}/{annee}/{mois}/
    // Nommage : {prefix}_{random16hex}.{ext}

    public function stocker(array $file, string $module, string $prefix = ''): StorageResult;
    // → StorageResult { chemin, mimeType, extension, tailleOctets, checksumSha256 }

    public function supprimer(string $cheminRelatif): bool;

    public function url(string $cheminRelatif): string;
    // → /uploads/serve/documents/{hash} (route sécurisée avec contrôle permission)

    public function existe(string $cheminRelatif): bool;

    public function taille(string $cheminRelatif): int;

    // Config MIME autorisés par module (étend UploadService::TYPES)
    public static array $mimeParModule = [
        'rh'         => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp',
                         'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'finance'    => ['application/pdf', 'image/jpeg', 'image/png',
                         'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'academique' => ['application/pdf', 'image/jpeg', 'image/png'],
        'vie_scolaire' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
        'scolarite'  => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
        'global'     => ['*'],  // admin
    ];

    public static int $tailleMaxOctets = 20971520; // 20 Mo par défaut
}
```

**Arborescence physique** :
```
storage/
└── documents/
    ├── rh/
    │   ├── 2026/07/
    │   │   └── employe_42_a3f9b1c2d4e5f678.pdf
    ├── finance/
    ├── academique/
    ├── vie_scolaire/
    ├── scolarite/
    └── .htaccess  ← Deny from all (accès via route sécurisée uniquement)
```

### 3.3 `VersioningService`

```php
class VersioningService
{
    // Crée un enregistrement doc_versions + met à jour version_courante dans doc_documents
    public function creerVersion(int $documentId, StorageResult $storage, string $notes, int $userId): int;

    // Retourne le chemin du fichier d'une version spécifique
    public function cheminVersion(int $documentId, int $numeroVersion): string;

    // Restaure une version antérieure (crée une nouvelle version = copie de l'ancienne)
    public function restaurerVersion(int $documentId, int $numeroVersion, int $userId): int;

    // Supprime les versions obsolètes (garde N dernières) — usage admin
    public function purgerVersionsAnciennes(int $documentId, int $garder = 5): int;
}
```

### 3.4 `FolderService`

```php
class FolderService
{
    public function creer(FolderDTO $dto, int $userId): int;
    public function renommer(int $folderId, string $newNom, int $userId): void;
    public function deplacer(int $folderId, ?int $newParentId, int $userId): void;
    public function supprimer(int $folderId, int $userId): void;  // soft delete, refuse si non vide

    // Arbre complet d'un module
    public function arbre(string $moduleSource, int $etablissementId = 1): array;

    // Fil d'ariane : [root → parent → dossier courant]
    public function breadcrumb(int $folderId): array;

    // Taille totale du dossier (récursif)
    public function tailleTotal(int $folderId): int;
}
```

### 3.5 `ShareService`

```php
class ShareService
{
    public function partager(ShareDTO $dto, int $userId): int;
    public function revoquer(int $partageId, int $userId): void;
    public function revoquerTous(int $documentId, int $userId): void;

    // Liste des partages actifs d'un document
    public function listerActifs(int $documentId): array;

    // Vérifie si un user a accès via partage (pour lien externe)
    public function verifierToken(string $token): ?array;  // null si expiré/révoqué

    // Nettoyage cron : révoque les partages expirés
    public function revoquerExpires(): int;
}
```

### 3.6 `TagService`

```php
class TagService
{
    public function creer(TagDTO $dto, int $userId): int;
    public function attacher(int $documentId, array $tagIds, int $userId): void;
    public function detacher(int $documentId, int $tagId, int $userId): void;
    public function synchroniser(int $documentId, array $tagIds, int $userId): void;
    public function lister(?string $moduleSource = null, int $etablissementId = 1): array;
    public function listerDuDocument(int $documentId): array;
}
```

### 3.7 `QuotaService`

```php
class QuotaService
{
    // Vérifie avant upload — lance QuotaExceededException si dépassé
    public function verifier(string $moduleSource, int $tailleOctets, ?string $entiteType = null, ?int $entiteId = null): void;

    // Mise à jour après upload/suppression
    public function incrementer(string $moduleSource, int $tailleOctets, ?string $entiteType = null, ?int $entiteId = null): void;
    public function decrementer(string $moduleSource, int $tailleOctets, ?string $entiteType = null, ?int $entiteId = null): void;

    // Rapport
    public function rapport(string $moduleSource): QuotaDTO;
    public function rapportGlobal(): array;

    // Admin
    public function definir(string $moduleSource, int $quotaOctets, ?string $entiteType = null, ?int $entiteId = null): void;
}
```

### 3.8 `SearchService`

```php
class SearchService
{
    // FULLTEXT MySQL sur (titre, description, reference_externe, notes)
    // + filtres module, statut, confidentialite, tags, dateRange
    public function rechercher(SearchDTO $dto): array;
    public function compter(SearchDTO $dto): int;

    // Suggestions pour autocomplete (top 10 titres)
    public function suggestions(string $query, string $moduleSource): array;
}
```

### 3.9 `PreviewService`

```php
class PreviewService
{
    // Génère une image preview PNG (thumbnail)
    // Stratégie : GD pour images, fallback icône générique pour PDF
    public function generer(int $documentId): ?string;  // chemin preview ou null

    // URL preview sécurisée
    public function url(int $documentId): string;

    // Types prévisualisables
    public static array $previewables = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'application/pdf',  // nécessite Imagick ou GhostScript
    ];
}
```

### 3.10 `SignatureService` — Stub V3

```php
class SignatureService
{
    // V3 — Stub pour préparer l'architecture
    public function demanderSignature(int $documentId, array $signataires, int $requestedById): void;
    // → Crée entrées doc_signatures + dispatch DocumentSignatureRequested
    // → Envoi email avec lien token (NotificationService)

    public function signer(string $token, string $hashConfirmation, string $ip): void;
    // → Vérifie token, hash, met à jour doc_signatures statut='signe'
    // → Dispatch DocumentSigned

    public function verifierStatutSignatures(int $documentId): string;
    // → 'non_requis' | 'en_attente' | 'partiel' | 'complet'
}
```

---

## 4. Repositories

### 4.1 `DocumentRepository`

```php
// Méthodes publiques
public function insert(array $data): int;
public function update(int $id, array $data): void;
public function updateStatut(int $id, string $statut, array $extra = []): void;
public function findById(int $id): ?array;
public function findAll(DocumentFiltersDTO $f): array;
public function count(DocumentFiltersDTO $f): int;
public function findByEntite(string $moduleSource, string $entiteType, int $entiteId): array;
public function findExpired(): array;
public function findExpiring(int $daysBefore, string $moduleSource = ''): array;
public function findInTrash(DocumentFiltersDTO $f): array;
public function softDelete(int $id): void;         // deleted_at = NOW()
public function statistiques(string $moduleSource = ''): array;
```

Critères `DocumentFiltersDTO` :
- `q` — recherche texte libre
- `moduleSource` — filtre par module
- `entiteType`, `entiteId` — filtre polymorphe
- `folderId` — filtre dossier
- `categorieId`
- `statut` — 'actif','archive','expire','brouillon','corbeille'
- `confidentialite`
- `tagIds[]`
- `dateEmissionMin`, `dateEmissionMax`
- `dateExpirationAvant`
- `excludeSecret` — masque confidentialite='secret'
- `includeArchive`
- `page`, `perPage`
- `tri`, `ordre`

### 4.2 `HistoriqueRepository`

```php
public function insert(array $data): void;
public function findByDocument(int $documentId): array;
public function findRecentsByModule(string $moduleSource, int $limit = 50): array;
```

---

## 5. DTOs

### 5.1 `DocumentDTO`

```php
class DocumentDTO
{
    public function __construct(
        public readonly string  $titre,
        public readonly string  $moduleSource,
        public readonly string  $confidentialite = 'interne',  // public|interne|confidentiel|secret
        public readonly ?int    $folderId           = null,
        public readonly ?int    $categorieId        = null,
        public readonly ?string $entiteType         = null,
        public readonly ?int    $entiteId           = null,
        public readonly ?string $description        = null,
        public readonly ?string $dateEmission       = null,
        public readonly ?string $dateExpiration     = null,
        public readonly ?int    $alerteJours        = 30,
        public readonly ?string $referenceExterne   = null,
        public readonly ?string $emetteur           = null,
        public readonly ?string $notes              = null,
        public readonly ?array  $metadata           = null,
        public readonly ?string $notesVersion       = null,
    ) {}

    public static function fromRequest(array $data): self;
    public function validate(): array;    // retourne ['champ' => 'message'] ou []
    public function toArray(): array;
}
```

### 5.2 `DocumentFiltersDTO`

```php
class DocumentFiltersDTO
{
    public function __construct(
        public readonly string $q               = '',
        public readonly string $moduleSource    = '',
        public readonly string $entiteType      = '',
        public readonly int    $entiteId        = 0,
        public readonly int    $folderId        = 0,
        public readonly int    $categorieId     = 0,
        public readonly string $statut          = '',
        public readonly string $confidentialite = '',
        public readonly array  $tagIds          = [],
        public readonly string $dateEmissionMin = '',
        public readonly string $dateEmissionMax = '',
        public readonly string $dateExpirationAvant = '',
        public readonly bool   $includeArchive  = false,
        public readonly bool   $excludeSecret   = false,
        public readonly int    $page            = 1,
        public readonly int    $perPage         = 20,
        public readonly string $tri             = 'created_at',
        public readonly string $ordre           = 'DESC',
    ) {}

    public static function fromRequest(array $data): self;
}
```

### 5.3 `ShareDTO`

```php
class ShareDTO
{
    public function __construct(
        public readonly int     $documentId,
        public readonly string  $destinataireType,   // 'user','role','module','externe'
        public readonly ?int    $destinataireId     = null,
        public readonly ?string $destinataireEmail  = null,
        public readonly string  $permission         = 'lecture',
        public readonly ?string $dateExpiration     = null,
        public readonly bool    $notifier           = true,
    ) {}

    public static function fromRequest(array $data): self;
    public function validate(): array;
}
```

### 5.4 `SearchDTO`

```php
class SearchDTO
{
    public function __construct(
        public readonly string $query,
        public readonly string $moduleSource    = '',
        public readonly string $statut          = 'actif',
        public readonly bool   $excludeSecret   = true,
        public readonly int    $page            = 1,
        public readonly int    $perPage         = 20,
    ) {}

    public static function fromRequest(array $data): self;
}
```

---

## 6. Models (Constantes)

### 6.1 `DocumentModel`

```php
class DocumentModel
{
    const STATUTS          = ['brouillon', 'actif', 'expire', 'archive', 'corbeille'];
    const CONFIDENTIALITES = ['public', 'interne', 'confidentiel', 'secret'];
    const MODULES_SOURCE   = ['rh','finance','academique','vie_scolaire','scolarite',
                               'bibliotheque','inventaire','communication','global'];

    const STATUT_LABELS = [
        'brouillon' => 'Brouillon',
        'actif'     => 'Actif',
        'expire'    => 'Expiré',
        'archive'   => 'Archivé',
        'corbeille' => 'Corbeille',
    ];

    const CONFIDENTIALITE_LABELS = [
        'public'       => 'Public',
        'interne'      => 'Interne',
        'confidentiel' => 'Confidentiel',
        'secret'       => 'Secret',
    ];

    const STATUT_COLORS = [
        'brouillon' => 'slate',
        'actif'     => 'green',
        'expire'    => 'red',
        'archive'   => 'gray',
        'corbeille' => 'orange',
    ];

    const CONF_COLORS = [
        'public'       => 'green',
        'interne'      => 'blue',
        'confidentiel' => 'amber',
        'secret'       => 'red',
    ];

    const PURGE_CORBEILLE_JOURS = 30;  // auto-purge après 30j en corbeille

    public static function isExpired(?string $dateExpiration): bool;
    public static function isExpiringSoon(?string $dateExpiration, int $alerteJours = 30): bool;
    public static function joursAvantExpiration(?string $dateExpiration): ?int;
    public static function confidentialiteLabel(string $conf): string;
}
```

---

## 7. Policies

### 7.1 `DocumentPolicy`

```php
class DocumentPolicy
{
    // Lecture selon module + confidentialité
    public function canView(array $user, array $document): bool;
    // → document.view ET (conf != 'secret' OU canViewSecret)
    // → document.view_all pour voir docs d'autres modules

    public function canViewSecret(array $user): bool;
    // → roles admin|directeur OU permission document.view_secret

    public function canCreate(array $user): bool;
    // → permission document.create

    public function canUpdate(array $user, array $document): bool;
    // → document.update ET document.module_source dans les modules du user

    public function canNewVersion(array $user, array $document): bool;
    // → canUpdate ET statut ∉ ['archive','corbeille']

    public function canArchive(array $user, array $document): bool;
    // → document.archive ET statut ∉ ['archive','corbeille']

    public function canTrash(array $user, array $document): bool;
    // → document.delete

    public function canRestore(array $user): bool;
    // → document.restore

    public function canPurge(array $user): bool;
    // → document.admin uniquement

    public function canShare(array $user, array $document): bool;
    // → document.share ET canView(user, document)

    public function canExport(array $user): bool;
    // → document.export

    public function canDownload(array $user, array $document): bool;
    // → canView ET (permission partage = 'telechargement' OU canExport)

    public function canAdmin(array $user): bool;
    // → document.admin
}
```

### 7.2 `FolderPolicy`

```php
class FolderPolicy
{
    public function canView(array $user, array $folder): bool;
    public function canCreate(array $user): bool;     // → folder.create
    public function canUpdate(array $user, array $folder): bool;
    public function canDelete(array $user, array $folder): bool;  // → folder.delete
}
```

---

## 8. Events

| Événement | Propriétés | Déclencheur |
|---|---|---|
| `DocumentUploaded` | documentId, moduleSource, entiteType, entiteId, mimeType, tailleOctets, uploadedById | DocumentService::uploader() |
| `DocumentVersioned` | documentId, oldVersion, newVersion, tailleOctets, updatedById | DocumentService::nouvelleVersion() |
| `DocumentArchived` | documentId, moduleSource, ancienStatut, archivedById | DocumentService::archiver() |
| `DocumentRestored` | documentId, moduleSource, ancienStatut, nouveauStatut, restoredById | DocumentService::restaurer() |
| `DocumentTrashed` | documentId, moduleSource, deletedById | DocumentService::corbeille() |
| `DocumentPurged` | documentId, moduleSource, cheminSupprime, purgedById | DocumentService::purger() |
| `DocumentShared` | documentId, partageId, destinataireType, destinataireId, permission, sharedById | ShareService::partager() |
| `DocumentShareRevoked` | documentId, partageId, revokedById | ShareService::revoquer() |
| `DocumentTagged` | documentId, tagIdsAdded, tagIdsRemoved, taggedById | TagService::synchroniser() |
| `DocumentMoved` | documentId, oldFolderId, newFolderId, movedById | DocumentService::deplacer() |
| `DocumentExpired` | documentId, moduleSource, entiteType, entiteId, dateExpiration | DocumentService::verifierExpirations() |
| `DocumentSignatureRequested` | documentId, signataires[], requestedById | SignatureService::demanderSignature() |
| `DocumentSigned` | documentId, signataireId, signedAt, completionPercent | SignatureService::signer() |
| `FolderCreated` | folderId, parentId, moduleSource, createdById | FolderService::creer() |
| `FolderDeleted` | folderId, moduleSource, deletedById | FolderService::supprimer() |
| `QuotaExceeded` | moduleSource, entiteType, entiteId, quotaOctets, utilisOctets, tentativeOctets | QuotaService::verifier() |

**Total : 16 événements**

Toutes les classes héritent de `Core\Event` avec `public function __construct(...) { parent::__construct(); }` et implémentent `toArray(): array`.

---

## 9. Listeners

### 9.1 `AuditListener`

Écoute : tous les 16 événements.

```php
class AuditListener implements Listener
{
    private AuditService $audit;

    // Mappe chaque event vers audit->log() ou audit->logCreate()
    // Module = 'documents', entite = 'document'|'folder'|'partage'
}
```

### 9.2 `NotificationListener`

Écoute : `DocumentShared`, `DocumentExpired`, `DocumentSignatureRequested`, `DocumentSigned`, `QuotaExceeded`.

```php
class NotificationListener implements Listener
{
    private NotificationService $notif;

    // DocumentShared    → notifier destinataire
    // DocumentExpired   → notifier propriétaire + admin module
    // QuotaExceeded     → notifier admin
    // SignatureRequested → email avec lien token
    // DocumentSigned    → notifier demandeur de signature
}
```

### 9.3 `QuotaListener`

Écoute : `DocumentUploaded`, `DocumentPurged`.

```php
class QuotaListener implements Listener
{
    private QuotaService $quota;

    // DocumentUploaded → quota->incrementer()
    // DocumentPurged   → quota->decrementer()
}
```

### 9.4 `SearchIndexListener`

Écoute : `DocumentUploaded`, `DocumentVersioned`.

```php
class SearchIndexListener implements Listener
{
    // V2 : no-op (FULLTEXT MySQL est auto-updaté)
    // V3 : appel moteur externe (Meilisearch, Elasticsearch)
    public function handle(Event $event): void { /* stub V3 */ }
}
```

---

## 10. RBAC — Permissions

### 10.1 Permissions documents

| Permission | Code | Roles par défaut |
|---|---|---|
| Voir documents (propre module) | `document.view` | admin, directeur, secretaire, comptable, professeur, rh_manager, rh_agent |
| Voir tous modules | `document.view_all` | admin, directeur |
| Voir documents secrets | `document.view_secret` | admin, directeur |
| Uploader / créer | `document.create` | admin, directeur, secretaire, comptable, rh_manager, rh_agent |
| Modifier métadonnées | `document.update` | admin, directeur, secretaire, rh_manager, rh_agent |
| Nouvelle version (fichier) | `document.version` | admin, directeur, secretaire, rh_manager, rh_agent |
| Archiver | `document.archive` | admin, directeur, rh_manager |
| Corbeille | `document.delete` | admin, directeur, rh_manager, rh_agent |
| Restaurer | `document.restore` | admin, directeur |
| Partager | `document.share` | admin, directeur, secretaire, rh_manager |
| Exporter (masse) | `document.export` | admin, directeur, secretaire |
| Administration | `document.admin` | admin |
| Signer | `document.sign` | tous les rôles (self-service) |

### 10.2 Permissions dossiers

| Permission | Code | Roles par défaut |
|---|---|---|
| Créer dossier | `folder.create` | admin, directeur, secretaire, rh_manager |
| Supprimer dossier | `folder.delete` | admin, directeur |

**Total : 15 permissions**

---

## 11. Routes

```php
// app/Modules/Documents/routes.php
// Préfixe : /v2/documents

// ── Documents ──────────────────────────────────────────────────────────────
GET    /v2/documents                              DocumentController::index()
GET    /v2/documents/create                       DocumentController::create()
POST   /v2/documents                              DocumentController::store()           ← upload
GET    /v2/documents/{id}                         DocumentController::show()
GET    /v2/documents/{id}/edit                    DocumentController::edit()
POST   /v2/documents/{id}                         DocumentController::update()          ← _method=PUT
POST   /v2/documents/{id}/version                 DocumentController::newVersion()      ← nouveau fichier
GET    /v2/documents/{id}/download                DocumentController::download()
POST   /v2/documents/{id}/archive                 DocumentController::archive()
POST   /v2/documents/{id}/restore                 DocumentController::restore()
POST   /v2/documents/{id}/trash                   DocumentController::trash()
POST   /v2/documents/{id}/move                    DocumentController::move()

// ── Versions ───────────────────────────────────────────────────────────────
GET    /v2/documents/{id}/versions                DocumentController::versions()
GET    /v2/documents/{id}/versions/{v}/download   DocumentController::downloadVersion()
POST   /v2/documents/{id}/versions/{v}/restore    DocumentController::restoreVersion()

// ── Historique ─────────────────────────────────────────────────────────────
GET    /v2/documents/{id}/history                 DocumentController::history()

// ── Prévisualisation ───────────────────────────────────────────────────────
GET    /v2/documents/{id}/preview                 PreviewController::show()

// ── Partages ───────────────────────────────────────────────────────────────
GET    /v2/documents/{id}/shares                  ShareController::index()
POST   /v2/documents/{id}/shares                  ShareController::store()
DELETE /v2/documents/{id}/shares/{shareId}        ShareController::revoke()
POST   /v2/documents/{id}/shares/revoke-all       ShareController::revokeAll()
GET    /v2/documents/shared/{token}               ShareController::accessToken()        ← accès externe

// ── Tags ───────────────────────────────────────────────────────────────────
POST   /v2/documents/{id}/tags                    TagController::sync()
DELETE /v2/documents/{id}/tags/{tagId}            TagController::detach()

// ── Dossiers ───────────────────────────────────────────────────────────────
GET    /v2/documents/folders                      FolderController::index()
POST   /v2/documents/folders                      FolderController::store()
GET    /v2/documents/folders/{id}                 FolderController::show()
POST   /v2/documents/folders/{id}                 FolderController::update()
POST   /v2/documents/folders/{id}/delete          FolderController::destroy()

// ── Corbeille ──────────────────────────────────────────────────────────────
GET    /v2/documents/trash                        TrashController::index()
POST   /v2/documents/trash/{id}/restore           TrashController::restore()
POST   /v2/documents/trash/{id}/purge             TrashController::purge()
POST   /v2/documents/trash/empty                  TrashController::emptyTrash()

// ── Recherche ──────────────────────────────────────────────────────────────
GET    /v2/documents/search                       SearchController::index()
GET    /v2/documents/search/suggestions           SearchController::suggestions()

// ── Signatures ─────────────────────────────────────────────────────────────
POST   /v2/documents/{id}/signatures              SignatureController::request()        ← stub V3
GET    /v2/documents/sign/{token}                 SignatureController::form()           ← stub V3
POST   /v2/documents/sign/{token}                 SignatureController::sign()           ← stub V3

// ── Administration ─────────────────────────────────────────────────────────
GET    /v2/documents/admin/quotas                 AdminController::quotas()
POST   /v2/documents/admin/quotas                 AdminController::setQuota()
GET    /v2/documents/admin/categories             AdminController::categories()
POST   /v2/documents/admin/categories             AdminController::createCategory()
POST   /v2/documents/admin/categories/{id}        AdminController::updateCategory()
GET    /v2/documents/admin/tags                   AdminController::tags()
POST   /v2/documents/admin/tags                   AdminController::createTag()
GET    /v2/documents/admin/stats                  AdminController::stats()
POST   /v2/documents/admin/purge-trash            AdminController::purgeTrash()

// ── Expirations (batch) ─────────────────────────────────────────────────────
GET    /v2/documents/expirations                  DocumentController::expirations()
```

**Total : 44 routes**

---

## 12. Workflow documentaire

### 12.1 Machine d'états

```
                    ┌──────────────────────────────────────────┐
                    │                                          │
          upload()  ▼              nouvelleVersion()          │
  ┌─────────────────────┐     ┌──────────────────────────┐    │
  │      brouillon      │────▶│         actif            │────┘
  └─────────────────────┘     └──────────────────────────┘
                                    │          │
                          archiver()│          │corbeille()
                                    ▼          ▼
                              ┌─────────┐  ┌──────────┐
                              │ archive │  │ corbeille│──── purger() ──▶ [supprimé]
                              └─────────┘  └──────────┘
                                    │          │
                          restaurer()│          │restaurer()
                                    └────┬─────┘
                                         ▼
                                   actif | expire
                                   (selon date_expiration)

[cron] verifierExpirations():
  actif + date_expiration < today → expire
```

### 12.2 Workflow upload complet

```
Controller::store()
  ├── DocumentPolicy::canCreate()                    ← RBAC
  ├── DocumentDTO::fromRequest()->validate()          ← validation
  ├── QuotaService::verifier()                       ← quota check (lance exception si dépassé)
  ├── StorageService::stocker()                      ← déplace fichier sur disque
  ├── DocumentRepository::insert()                   ← DB
  ├── VersionRepository::insert(version=1)           ← première version
  ├── HistoriqueRepository::insert(action='upload')  ← journal
  └── EventDispatcher::dispatch(DocumentUploaded)
        ├── AuditListener      → AuditService::logCreate()
        ├── QuotaListener      → QuotaService::incrementer()
        └── NotificationListener → [rien au upload, seulement partage/expiration]
```

### 12.3 Workflow nouvelle version

```
Controller::newVersion()
  ├── DocumentPolicy::canNewVersion()
  ├── StorageService::stocker()              ← nouveau fichier physique
  ├── VersioningService::creerVersion()     ← doc_versions + update version_courante
  ├── HistoriqueRepository::insert()
  └── EventDispatcher::dispatch(DocumentVersioned)
        ├── AuditListener
        └── QuotaListener::incrementer(nouvelle_taille - ancienne_taille)
```

### 12.4 Workflow partage externe

```
ShareController::store()
  ├── SharePolicy::canShare()
  ├── ShareService::partager()
  │     ├── token = bin2hex(random_bytes(32))
  │     ├── doc_partages INSERT
  │     └── EventDispatcher::dispatch(DocumentShared)
  │           └── NotificationListener → email avec lien /v2/documents/shared/{token}
  └── redirect show

ShareController::accessToken()
  ├── ShareService::verifierToken(token)     ← null si expiré/révoqué
  ├── DocumentRepository::findById()
  ├── streamer fichier (headers Content-Disposition)
  └── HistoriqueRepository::insert(action='telechargement_externe')
```

---

## 13. Intégrations modules consommateurs

### 13.1 Pattern d'intégration (polymorphe)

Chaque module métier référence un document via 3 champs :

```php
// Exemples d'appel depuis un service métier
$documentService->uploader($file, new DocumentDTO(
    titre       : 'Contrat signé — ' . $employe->nom,
    moduleSource: 'rh',
    confidentialite: 'secret',
    entiteType  : 'employe',
    entiteId    : $employe->id,
    categorieId : CategoryIds::CONTRAT_SIGNE,
), $userId, $userName);

// Récupération des documents d'une entité
$docs = $documentRepository->findByEntite('rh', 'employe', $employeId);
```

### 13.2 Bridge RH/Documents existant

Les tables `rh_documents` restent FROZEN. La coexistence se fait via :

**Option A — Vue SQL (recommandée pour V2.1)**
```sql
CREATE VIEW v_rh_documents_unified AS
    -- Documents natifs V2 (module_source='rh')
    SELECT
        'v2'         AS origine,
        id, titre, statut, confidentialite,
        entite_id    AS employe_id,
        version_courante, created_at
    FROM doc_documents
    WHERE module_source = 'rh' AND deleted_at IS NULL

    UNION ALL

    -- Documents legacy V1
    SELECT
        'v1'         AS origine,
        id, titre, statut, confidentialite,
        employe_id,
        version_courante, created_at
    FROM rh_documents
    WHERE deleted_at IS NULL;
```

**Option B — Migration données (V2.1+)**
Script de migration one-shot `rh_documents` → `doc_documents` (hors scope Blueprint).

### 13.3 Intégration UploadService existant

Le `StorageService` étend et délègue à `UploadService` pour les types existants (avatar, justification, import_csv). Il ajoute le type `documents` avec sous-dossiers par module.

```php
// StorageService::stocker() délègue à UploadService::upload() si type connu
// Sinon gère directement move_uploaded_file() vers storage/documents/{module}/
```

---

## 14. Sécurité

### 14.1 Contrôle d'accès fichiers physiques

```apache
# storage/documents/.htaccess
Deny from all
```

Tous les téléchargements passent par des routes sécurisées :
- `GET /v2/documents/{id}/download` — authentifié + `DocumentPolicy::canDownload()`
- `GET /v2/documents/shared/{token}` — token valide + non expiré

Headers de réponse :
```php
Content-Type: {mime_type}
Content-Disposition: attachment; filename="{titre}.{ext}"
X-Content-Type-Options: nosniff
Cache-Control: private, no-cache
```

### 14.2 Upload sécurisé

- Validation MIME via `mime_content_type()` sur fichier temporaire (résistant au spoofing d'extension)
- Renommage systématique : `{module}_{random16hex}.{ext}` — aucun nom original conservé
- Taille max configurable par module (`QuotaService`)
- Path traversal : `StorageService` refuse tout chemin contenant `..`
- Fichiers exécutables (.php, .exe, .sh) toujours rejetés indépendamment du type déclaré

### 14.3 Tokens partage externe

- `token_acces = bin2hex(random_bytes(32))` — 256 bits d'entropie
- Stocké en clair (pas de hash) — révocable individuellement via `revoked_at`
- Expiration configurable — vérifiée à chaque accès
- Un accès externe enregistré dans `doc_historique` avec IP + user_agent

### 14.4 Checksum intégrité

- `checksum_sha256` calculé à l'upload via `hash_file('sha256', $tmpPath)`
- Vérifié à chaque téléchargement (option AdminController)
- Stocké dans `doc_documents` et dans chaque `doc_versions`

---

## 15. Stratégie de stockage

### 15.1 V2 — Stockage local (implémenté)

```
storage/documents/
├── rh/2026/07/           ← {module}/{year}/{month}/
│   └── employe_a3f9b1.pdf
├── finance/2026/07/
├── academique/2026/07/
└── ...
```

- Chemins relatifs dans DB (`doc_documents.chemin_stockage`)
- ROOT_PATH constant pour résolution absolue

### 15.2 V3 — Cloud ready (préparé)

`StorageService` expose une interface que V3 remplace par une implémentation S3 :

```php
interface StorageInterface {
    public function stocker(array $file, string $module, string $prefix): StorageResult;
    public function supprimer(string $chemin): bool;
    public function url(string $chemin): string;
    public function existe(string $chemin): bool;
    public function taille(string $chemin): int;
}

// V2 : LocalStorageAdapter implements StorageInterface
// V3 : S3StorageAdapter implements StorageInterface  (MinIO ou AWS S3)
```

---

## 16. Stratégie de versionnement

| Scénario | Comportement |
|---|---|
| Upload initial | Version 1 créée automatiquement dans `doc_versions` |
| Nouveau fichier | `version_courante++`, nouvelle entrée `doc_versions`, ancienne version conservée |
| Modification métadonnées seules | Pas de nouvelle version, `updated_at` mis à jour |
| Restauration version antérieure | Nouvelle version N+1 = copie du fichier de la version cible |
| Purge versions anciennes | Admin uniquement, supprime fichiers physiques + entrées `doc_versions` (garde N dernières) |

**Rétention par défaut** : toutes les versions conservées. L'admin peut purger via `VersioningService::purgerVersionsAnciennes()`.

---

## 17. Préparation SaaS & multi-établissement

### 17.1 Colonne `etablissement_id`

Présente dans : `doc_folders`, `doc_documents`, `doc_tags`, `doc_quotas`.

Toutes les requêtes `DocumentRepository` filtrent sur `etablissement_id` (valeur lue depuis la session/config, défaut = 1).

### 17.2 Isolation des données

- Un user ne peut jamais voir les documents d'un autre établissement
- `DocumentFiltersDTO::etablissementId` peuplé automatiquement par le controller depuis la session
- Les partages externes (tokens) encodent l'`etablissement_id` dans le token — vérification à l'accès

### 17.3 Quotas multi-établissement

```sql
-- Un quota par module par établissement
doc_quotas: (module_source='rh', entite_type=NULL, etablissement_id=2, quota_octets=10737418240)  ← 10 Go
```

---

## 18. Préparation API REST (V3)

### 18.1 Points d'API prévus

```
GET    /api/v1/documents              → lister (filtres querystring)
POST   /api/v1/documents              → upload (multipart/form-data)
GET    /api/v1/documents/{id}         → détail + métadonnées
PATCH  /api/v1/documents/{id}         → mettre à jour métadonnées
GET    /api/v1/documents/{id}/download → télécharger
DELETE /api/v1/documents/{id}         → corbeille
GET    /api/v1/documents/{id}/versions → liste versions
POST   /api/v1/documents/{id}/share   → partager
```

### 18.2 Authentification API

- Token Bearer (JWT ou opaque) — implémentation V3
- `DocumentPolicy` réutilisée telle quelle (user array injecté depuis token)

---

## 19. Préparation mobile

### 19.1 Endpoints critiques mobile

| Besoin mobile | Endpoint | Notes |
|---|---|---|
| Lister documents | `GET /v2/documents` (JSON accept) | Pagination 10/page |
| Télécharger | `GET /v2/documents/{id}/download` | Stream binaire |
| Upload photo | `POST /v2/documents` | multipart |
| Preview thumbnail | `GET /v2/documents/{id}/preview` | PNG 200×280 |

### 19.2 Progressive Web App

- `PreviewController` sert des thumbnails légers (200×280px max)
- Headers `Cache-Control: public, max-age=3600` sur previews (non-confidentiels)
- Chunked upload (V3) pour gros fichiers sur mobile

---

## 20. Stratégie de migration V1 → V2

### 20.1 Inventaire V1 existant

| Données V1 | Volume estimé | Destination V2 |
|---|---|---|
| `rh_documents` | ~500 docs | Bridge vue SQL (pont) → migration optionnelle V2.1 |
| Fichiers `storage/uploads/justifications/` | ~200 fichiers | Référencement rétroactif via `entiteType=absence` |
| Avatars, photos élèves/profs | Images uniquement | Non concernés (géré par UploadService) |
| `app\Events\DocumentGenere.php` | Event PDF V1 | Dispatch `DocumentUploaded` en sortie bulletin V2 |

### 20.2 Plan de migration

**Étape 1 (V2.0 — maintenant)** : Module Documents V2 créé, indépendant, `enabled=false`.

**Étape 2 (V2.1)** : Activation `enabled=true`. Les nouveaux documents passent par V2. Les modules métier (RH, Finance…) appellent `DocumentService` pour les nouveaux uploads.

**Étape 3 (V2.1+)** : Script de migration one-shot `rh_documents` → `doc_documents` (coordonné avec admin, test en staging, rollback plan). Vue SQL retirée.

**Étape 4 (V3)** : Tous les modules V1 (comptabilite, paiements, etc.) migrés → Documents V2.

**RÈGLE** : Aucune modification des tables `rh_documents`, `rh_document_versions`, `rh_document_historique` avant la migration officielle validée.

---

## 21. Dette technique anticipée

| Ref | Dette | Impact | Plan |
|---|---|---|---|
| DT-D-001 | `SearchIndexListener` stub — FULLTEXT MySQL pas de highlighting | Mineur | V2.1 : highlight via `MATCH AGAINST` avec `WITH QUERY EXPANSION` |
| DT-D-002 | `PreviewService` dépend de GD (pas Imagick) — pas de preview PDF | Majeur | V3 : extension Imagick ou service externe |
| DT-D-003 | `SignatureService` 100% stub — pas d'implémentation réelle | Majeur | V3 : intégration DocuSign ou solution interne PKI |
| DT-D-004 | Pas de chunked upload — fichiers > 20 Mo bloqués | Majeur | V3 : upload en chunks (Resumable.js) |
| DT-D-005 | 250 instanciations listeners/request (problème global DT-G-002) | Majeur | V2.1 : lazy loading listeners |
| DT-D-006 | Pas de CDN — tous les fichiers servis par PHP | Majeur | V3 : S3 + CloudFront |
| DT-D-007 | Pas d'antivirus scan à l'upload | Mineur | V2.1 : ClamAV via socket ou API |

---

## 22. Checklist pré-implémentation

Avant de passer à la Phase 7.1 implémentation, valider :

- [ ] Migration SQL `doc_categories` seed avec les catégories initiales (30 codes)
- [ ] `config/modules.php` → `'documents' => ['enabled' => false, ...]` ajouté
- [ ] `config/events.php` → 16 mappings events → listeners ajoutés
- [ ] `config/permissions.php` → 15 permissions `document.*` + `folder.*` intégrées par rôle
- [ ] `storage/documents/.htaccess` créé avec `Deny from all`
- [ ] Route sécurisée `/uploads/serve/documents/{hash}` ajoutée (distinct des avatars)
- [ ] `StorageService` étendu (nouveau type `'document'` dans TYPES)
- [ ] Bridge SQL `v_rh_documents_unified` créé (coexistence RH V1)

---

## 23. Récapitulatif

| Axe | Valeur |
|---|---|
| Namespace | `App\Modules\Documents\` |
| Préfixe tables | `doc_` |
| Nombre de tables | 11 |
| Nombre de services | 10 |
| Nombre d'events | 16 |
| Nombre de listeners | 4 |
| Nombre de routes | 44 |
| Nombre de permissions | 15 |
| Modules consommateurs | 8 (RH, Finance, Académique, VS, Scolarité, Bibliothèque, Inventaire, Communication) |
| Statuts document | 5 (brouillon, actif, expire, archive, corbeille) |
| Niveaux confidentialité | 4 (public, interne, confidentiel, secret) |
| Stratégie stockage | Local disk V2 → S3/cloud V3 |
| Versioning | Illimité par défaut, purge admin configurable |
| Signatures | Stub V3 (tables préparées) |
| FULLTEXT | MySQL MATCH AGAINST sur titre+description+ref+notes |
| SaaS ready | `etablissement_id` sur 4 tables |
| API ready | Interface `StorageInterface` + endpoints définis |
| Mobile ready | Preview thumbnail + endpoints JSON |

---

*Blueprint produit le 2026-07-02 — Phase 7.1*
*Prochaine étape : validation blueprint → implémentation Phase 7.2*
*Aucun fichier de code modifié ou créé dans le cadre de ce document.*
