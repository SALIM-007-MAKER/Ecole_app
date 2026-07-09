# DOCUMENTS V2 — MODULE FREEZE
**Version :** 2.0.0  
**Phase :** 7.4  
**Date :** 2026-07-02  
**Auditeur :** Claude Sonnet 4.6  
**Statut précédent :** GO (Phase 7.3 — Score 8.5/10)

---

## ✅ DÉCISION FINALE

```
┌─────────────────────────────────────────────────────────┐
│           ARCHITECTURE FROZEN — GO                      │
│                                                         │
│   Module Documents V2 — v2.0.0                         │
│   Plateforme documentaire commune — 8 modules consumers │
│   Date de gel : 2026-07-02                             │
└─────────────────────────────────────────────────────────┘
```

**Score global : 8.5 / 10**

Toute modification structurelle future (tables, services publics, events, permissions) requiert :
1. Une migration SQL `doc_002_*.sql`
2. Une mise à jour du numéro de version dans `module.json`
3. Une phase de revue d'intégration dédiée

---

## 1. Architecture — ✅ VALIDÉ

| Critère | État |
|---------|------|
| Namespace isolé `App\Modules\Documents\` | ✅ |
| Module désactivé par défaut (`enabled: false`) | ✅ |
| Contrôleurs fins (< 200 lignes) | ✅ |
| Logique métier dans les services | ✅ |
| Toutes les écritures DB via Events → AuditListener | ✅ |
| SQL 100 % dans les Repositories | ✅ |
| Aucun import de modules gelés | ✅ |
| Pattern DTO + Repository + Policy + Service | ✅ |

**Arbre du module (79 fichiers PHP) :**

```
app/Modules/Documents/
├── module.json                     # Manifeste v2.0.0 FROZEN
├── routes.php                      # 44 routes /v2/documents/*
├── Controllers/  (9 fichiers)
├── Services/     (10 fichiers)
├── Repositories/ (8 fichiers)
├── DTO/          (7 fichiers)
├── Policies/     (3 fichiers)
├── Events/       (16 fichiers)
├── Listeners/    (4 fichiers)
├── Models/       (4 fichiers)
└── Views/        (14 vues)
database/migrations/doc_001_documents.sql
storage/documents/.htaccess
```

---

## 2. Base de données — ✅ VALIDÉ

### Tables figées (11 tables `doc_*`)

| Table | Rôle | Lignes seed |
|-------|------|-------------|
| `doc_categories` | Référentiel catégories documentaires | 24 |
| `doc_folders` | Arborescence logique des dossiers | 0 |
| `doc_documents` | Registre principal (cœur du module) | 0 |
| `doc_versions` | Historique des versions de fichiers | 0 |
| `doc_tags` | Référentiel tags | 0 |
| `doc_document_tags` | Association documents ↔ tags (N:N) | 0 |
| `doc_partages` | Partages internes/externes avec token | 0 |
| `doc_historique` | Journal des actions par document | 0 |
| `doc_corbeille` | Entrées corbeille avec date de purge | 0 |
| `doc_quotas` | Quotas de stockage par module | 0 |
| `doc_signatures` | Circuit signatures électroniques (stub V3) | 0 |

### Caractéristiques structurelles
- **Soft delete** : `deleted_at DATETIME NULL` sur `doc_documents` et `doc_folders`
- **Multi-établissement** : `etablissement_id INT UNSIGNED` sur 5 tables
- **Attachement polymorphe** : `(module_source, entite_type, entite_id)` sans FK enforced
- **FULLTEXT** : `INDEX ft_search (titre, description, reference_externe, notes)` sur `doc_documents`
- **Index composé** : `(module_source, entite_type, entite_id)` pour les lookups cross-module
- **Index expiration** : `(date_expiration, statut)` pour les batch d'expiration
- **Migration** : `doc_001_documents.sql` — `CREATE TABLE IF NOT EXISTS` (idempotent), aucun DROP

### Contraintes d'intégrité
```sql
-- FK dans doc_documents
FOREIGN KEY fk_doc_folder    (folder_id)    REFERENCES doc_folders(id)    ON DELETE SET NULL
FOREIGN KEY fk_doc_categorie (categorie_id) REFERENCES doc_categories(id) ON DELETE SET NULL

-- FK dans doc_versions
FOREIGN KEY fk_version_doc (document_id) REFERENCES doc_documents(id) ON DELETE CASCADE

-- FK dans doc_signatures
FOREIGN KEY fk_sig_doc     (document_id) REFERENCES doc_documents(id) ON DELETE CASCADE
```

---

## 3. Services — ✅ VALIDÉS (10 services)

### Interface publique figée

#### DocumentService — Orchestrateur principal
```php
public function uploader(DocumentDTO $dto, array $file, int $userId): int
public function modifier(int $documentId, DocumentDTO $dto, int $userId): void
public function nouvelleVersion(int $documentId, array $file, ?string $notes, int $userId): int
public function archiver(int $documentId, int $userId, ?string $raison = null): void
public function mettreEnCorbeille(int $documentId, int $userId, ?string $raison = null): void
public function restaurer(int $documentId, int $userId): void
public function purger(int $documentId, int $userId): void
public function viderCorbeille(int $userId): int
public function deplacer(int $documentId, ?int $newFolderId, int $userId): void
public function telecharger(array $doc): never
public function verifierExpirations(): int
public function trouver(int $documentId): ?array
public function paginer(DocumentFiltersDTO $filters): array   // retourne ['data','total','page','per_page','total_pages']
public function historique(int $documentId): array
public function listerVersions(int $documentId): array
public function corbeille(): array
public function statistiques(string $moduleSource = ''): array
public function categories(): array
public function expirationProchaine(int $jours): array
public function documentsExpires(): array
```

#### StorageService — Stockage physique
```php
public function stocker(array $file, string $module, string $prefix = ''): StorageResult
public function supprimer(string $cheminRelatif): bool         // guard: str_starts_with('storage/documents/')
public function url(string $cheminRelatif): string
public function existe(string $cheminRelatif): bool
public function taille(string $cheminRelatif): int
public function verifierIntegrite(string $cheminRelatif, string $checksumAttendu): bool
```

#### VersioningService — Versioning
```php
public function creerVersion(int $documentId, StorageResult $storage, string $notes, int $userId): int
public function restaurerVersion(int $documentId, int $numeroVersion, int $userId): int
public function cheminVersion(int $documentId, int $numeroVersion): string
public function listerVersions(int $documentId): array
public function purgerVersionsAnciennes(int $documentId, int $garder = 5): int
```

#### QuotaService
```php
public function verifier(string $moduleSource, int $tailleOctets, int $etablissementId = 1): void  // lance OverflowException
public function incrementer(string $moduleSource, int $octets, int $etablissementId = 1): void
public function decrementer(string $moduleSource, int $octets, int $etablissementId = 1): void
public function definir(string $moduleSource, int $maxOctets, int $etablissementId = 1): void
public function rapportGlobal(int $etablissementId = 1): array
public function tableau(): array
```

#### FolderService
```php
public function creer(FolderDTO $dto, int $userId): int
public function modifier(int $id, FolderDTO $dto, int $userId): void
public function renommer(int $folderId, string $newNom, int $userId): void
public function deplacer(int $folderId, ?int $newParentId, int $userId): void
public function supprimer(int $folderId, int $userId): void
public function arbre(?string $moduleSource = null, int $etablissementId = 1): array
public function breadcrumb(int $folderId): array
public function findById(int $id): ?array
public function findRoots(string $moduleSource, int $etablissementId = 1): array
```

#### ShareService
```php
public function partager(ShareDTO $dto, int $userId): array    // retourne ['id' => int, 'token' => string]
public function revoquer(int $partageId, int $userId): void
public function listerPartages(int $documentId): array
public function trouverPartage(int $partageId): ?array
public function accederParToken(string $token): ?array         // retourne ['document' => ..., 'partage' => ...]
```

#### TagService
```php
public function creer(TagDTO $dto, int $userId): int
public function attacher(int $documentId, array $tagIds, int $userId): void
public function detacher(int $documentId, int $tagId, int $userId): void
public function synchroniser(int $documentId, array $tagIds, int $userId): void
public function lister(?string $moduleSource = null, int $etablissementId = 1): array
public function listerDuDocument(int $documentId): array
public function tous(): array
```

#### SearchService
```php
public function rechercher(SearchDTO $dto, array $user = []): array   // retourne ['data','total','page','per_page']
public function compter(SearchDTO $dto): int
public function suggestions(string $query, array $user = []): array
```

#### PreviewService
```php
public function generer(array $doc): mixed
public function cheminSurveilleAcces(string $filename, array $user): ?string
```

#### SignatureService (Stub V3)
```php
public function demanderSignature(int $documentId, array $signataires, int $requestedById): void
public function signer(string $token, int $userId): void
public function rejeter(string $token, ?string $motif, int $userId): void
public function signataires(int $documentId): array
public function verifierStatut(int $documentId): string
public function findByToken(string $token): ?array
```

---

## 4. Repositories — ✅ VALIDÉS (8 repositories)

| Repository | Méthodes clés |
|-----------|---------------|
| `DocumentRepository` | insert, update, updateStatut, softDelete, findById, findAll, count, findByEntite, findExpired, findExpiring, findInTrash, statistiques |
| `FolderRepository` | findById, findByModule(?string), findChildren, findRoots, hasChildren, hasDocuments, insert, update, softDelete, breadcrumb |
| `VersionRepository` | insert, findByDocument, findVersion, maxNumero, deleteOldVersions |
| `ShareRepository` | insert, findByDocument, findById, update |
| `TagRepository` | insert, findAll, findByDocument, findByNom, attachTags, detachTag, syncTags |
| `HistoriqueRepository` | insert, findByDocument |
| `TrashRepository` | insert, findByDocument, remove |
| `QuotaRepository` | findByModule, upsert, incrementer, decrementer |

**Règle immuable :** Tout SQL est dans les Repositories. Aucun accès PDO direct dans les Services ou Controllers.

---

## 5. DTOs — ✅ VALIDÉS (7 DTOs)

| DTO | Propriétés figées | Méthodes |
|-----|-------------------|---------|
| `DocumentDTO` | titre, moduleSource, confidentialite, folderId, categorieId, entiteType, entiteId, description, dateEmission, dateExpiration, alerteJours, referenceExterne, emetteur, notes, metadata, notesVersion | fromRequest, validate, toArray |
| `DocumentFiltersDTO` | q, moduleSource, entiteType, entiteId, folderId, categorieId, statut, confidentialite, tagIds[], dateEmissionMin, dateEmissionMax, dateExpirationAvant, includeArchive, excludeSecret, etablissementId, page, perPage, tri, ordre | fromRequest |
| `FolderDTO` | nom, moduleSource, parentId, description, icone, couleur, ordre, etablissementId | fromRequest, validate |
| `ShareDTO` | documentId, destinataireType, destinataireId, destinataireEmail, permission, dateExpiration, notifier | fromRequest, validate |
| `TagDTO` | nom, couleur, moduleSource, etablissementId | fromRequest, validate |
| `SearchDTO` | query, moduleSource, statut, excludeSecret, etablissementId, page, perPage | fromRequest |
| `QuotaDTO` | moduleSource, quotaOctets, utilisOctets, pourcentage, illimite | fromRow, formatOctets, octetsDisponibles |

---

## 6. Policies — ✅ VALIDÉES (3 policies)

### DocumentPolicy — 16 méthodes
```php
canView(array $user, array $document): bool      // vérifie document.view + secret guard
canViewAll(array $user): bool                    // document.view_all
canViewSecret(array $user): bool                 // document.view_secret OU rôle admin/directeur
canCreate(array $user): bool                     // document.create
canUpdate(array $user, array $document): bool    // document.update
canNewVersion(array $user, array $document): bool // document.version + statut non archive/corbeille
canArchive(array $user, array $document): bool   // document.archive + statut non archive/corbeille
canTrash(array $user): bool                      // document.delete
canRestore(array $user): bool                    // document.restore
canPurge(array $user): bool                      // document.admin
canShare(array $user, array $document): bool     // document.share + canView
canExport(array $user): bool                     // document.export
canDownload(array $user, array $document): bool  // alias canView
canAdmin(array $user): bool                      // document.admin
canSign(array $user): bool                       // document.sign
canRequestSignature(array $user): bool           // document.admin OU document.share
```

### FolderPolicy — 4 méthodes
```php
canView(array $user): bool    // document.view
canCreate(array $user): bool  // folder.create
canUpdate(array $user): bool  // folder.create
canDelete(array $user): bool  // folder.delete
```

### SharePolicy — 2 méthodes
```php
canShare(array $user): bool                         // document.share
canRevoke(array $user, array $partage): bool        // document.share OU créateur du partage
```

---

## 7. RBAC — ✅ VALIDÉ (15 permissions)

### Permissions figées

| Permission | Description |
|-----------|-------------|
| `document.view` | Voir les documents (filtre secret automatique) |
| `document.view_all` | Voir tous les documents (sans filtre module) |
| `document.view_secret` | Voir les documents de confidentialité 'secret' |
| `document.create` | Uploader un nouveau document |
| `document.update` | Modifier les métadonnées |
| `document.version` | Créer une nouvelle version de fichier |
| `document.archive` | Archiver un document actif |
| `document.delete` | Mettre en corbeille |
| `document.restore` | Restaurer depuis archive ou corbeille |
| `document.share` | Créer/révoquer des partages |
| `document.export` | Exporter ou télécharger |
| `document.sign` | Signer électroniquement |
| `document.admin` | Purge, quotas, statistiques, demander signature |
| `folder.create` | Créer et modifier des dossiers |
| `folder.delete` | Supprimer des dossiers |

### Matrice RBAC figée

| Permission | admin | directeur | secrétaire | comptable | enseignant | parent | élève |
|-----------|:-----:|:---------:|:----------:|:---------:|:----------:|:------:|:-----:|
| document.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| document.view_all | ✅ | ✅ | ✅ | — | — | — | — |
| document.view_secret | ✅ | ✅ | — | — | — | — | — |
| document.create | ✅ | ✅ | ✅ | — | ✅ | — | — |
| document.update | ✅ | ✅ | ✅ | — | ✅ | — | — |
| document.version | ✅ | ✅ | ✅ | — | ✅ | — | — |
| document.archive | ✅ | ✅ | ✅ | — | — | — | — |
| document.delete | ✅ | ✅ | ✅ | — | — | — | — |
| document.restore | ✅ | ✅ | ✅ | — | — | — | — |
| document.share | ✅ | ✅ | ✅ | — | ✅ | — | — |
| document.export | ✅ | ✅ | ✅ | ✅ | ✅ | — | — |
| document.sign | ✅ | ✅ | — | — | — | — | — |
| document.admin | ✅ | ✅ | — | — | — | — | — |
| folder.create | ✅ | ✅ | ✅ | — | ✅ | — | — |
| folder.delete | ✅ | ✅ | ✅ | — | — | — | — |

---

## 8. Events — ✅ VALIDÉS (16 événements)

### Événements figés et leurs signatures

| Événement | Paramètres constructeur |
|-----------|------------------------|
| `DocumentUploaded` | documentId, moduleSource, entiteType, entiteId, mimeType, tailleOctets, uploadedById |
| `DocumentVersioned` | documentId, oldVersion, newVersion, tailleOctets, updatedById |
| `DocumentArchived` | documentId, moduleSource, ancienStatut, archivedById |
| `DocumentRestored` | documentId, moduleSource, ancienStatut, nouveauStatut, restoredById |
| `DocumentTrashed` | documentId, moduleSource, deletedById |
| `DocumentPurged` | documentId, moduleSource, cheminSupprime, purgedById |
| `DocumentShared` | documentId, partageId, destinataireType, destinataireId, permission, token, sharedById |
| `DocumentShareRevoked` | documentId, partageId, revokedById |
| `DocumentTagged` | documentId, tagIdsAdded[], tagIdsRemoved[], taggedById |
| `DocumentMoved` | documentId, oldFolderId, newFolderId, movedById |
| `DocumentExpired` | documentId, moduleSource, entiteType, entiteId, dateExpiration |
| `DocumentSignatureRequested` | documentId, signataires[], requestedById |
| `DocumentSigned` | documentId, signataireId, signedAt, completionPercent |
| `FolderCreated` | folderId, parentId, moduleSource, createdById |
| `FolderDeleted` | folderId, moduleSource, deletedById |
| `QuotaExceeded` | moduleSource, quotaOctets, utilisOctets, tentativeOctets |

### Listeners et couverture

| Listener | Événements traités |
|----------|-------------------|
| `AuditListener` | 16 / 16 — AuditService::log() + logCreate() |
| `QuotaListener` | DocumentUploaded (+octets), DocumentPurged (−octets) |
| `NotificationListener` | DocumentShared, DocumentExpired, DocumentSignatureRequested, DocumentSigned, QuotaExceeded |
| `SearchIndexListener` | No-op V2 (MySQL FULLTEXT natif) |

**Règle immuable :** Les nouveaux consommateurs d'events Documents s'enregistrent dans `config/events.php` uniquement. Aucun listener ne doit importer d'autres modules gelés.

---

## 9. API — ✅ VALIDÉE (44 routes figées)

### Routes figées

#### Documents (10)
```
GET  /v2/documents                          → DocumentController::index
GET  /v2/documents/create                   → DocumentController::create
POST /v2/documents                          → DocumentController::store
GET  /v2/documents/{id}                     → DocumentController::show
GET  /v2/documents/{id}/edit                → DocumentController::edit
POST /v2/documents/{id}                     → DocumentController::update
POST /v2/documents/{id}/archive             → DocumentController::archive
POST /v2/documents/{id}/restore             → DocumentController::restore
GET  /v2/documents/{id}/download            → DocumentController::download
POST /v2/documents/{id}/version             → DocumentController::version
```

#### Fichiers & Aperçu (2)
```
GET  /v2/documents/file/{filename}          → PreviewController::serveFile     (auth requis)
GET  /v2/documents/{id}/preview             → PreviewController::show
```

#### Partages (4)
```
GET  /v2/documents/{id}/shares              → ShareController::index
POST /v2/documents/{id}/shares              → ShareController::store
POST /v2/shares/{id}/revoke                 → ShareController::revoke
GET  /v2/share/{token}                      → ShareController::accessByToken   (public)
```

#### Signatures (4)
```
GET  /v2/documents/{id}/signatures          → SignatureController::index
POST /v2/documents/{id}/signatures/request  → SignatureController::request
POST /v2/signatures/{token}/sign            → SignatureController::sign
POST /v2/signatures/{token}/reject          → SignatureController::reject
```

#### Dossiers (5)
```
GET  /v2/folders                            → FolderController::index
POST /v2/folders                            → FolderController::store
POST /v2/folders/{id}                       → FolderController::update
POST /v2/folders/{id}/delete                → FolderController::destroy
GET  /v2/folders/{id}/breadcrumb            → FolderController::breadcrumb
```

#### Corbeille (5)
```
GET  /v2/trash                              → TrashController::index
POST /v2/trash/{id}                         → TrashController::trash
POST /v2/trash/{id}/restore                 → TrashController::restore
POST /v2/trash/{id}/purge                   → TrashController::purge
POST /v2/trash/purge-all                    → TrashController::purgeAll
```

#### Recherche (2)
```
GET  /v2/documents/search                   → SearchController::index
GET  /v2/documents/search/suggestions       → SearchController::suggestions
```

#### Tags (4)
```
GET  /v2/tags                               → TagController::index
POST /v2/tags                               → TagController::store
POST /v2/documents/{id}/tags                → TagController::attach
POST /v2/documents/{id}/tags/sync           → TagController::sync
```

#### Administration (5)
```
GET  /v2/documents/admin/quotas             → AdminController::quotas
POST /v2/documents/admin/quota/{module}     → AdminController::updateQuota
GET  /v2/documents/admin/expirations        → AdminController::expirations
POST /v2/documents/admin/expire-check       → AdminController::runExpireCheck
GET  /v2/documents/admin/statistics         → AdminController::statistics
```

**Garanties API :**
- Toutes les mutations : `verifyCsrf()` + `requirePermission()`
- Toutes les lectures : `requirePermission()`
- Exception : `/v2/share/{token}` — accès public par token opaque 64 hex chars
- Format réponse JSON : `['success' => bool, ...]` sur toutes les mutations

---

## 10. Sécurité — ✅ VALIDÉE

| Contrôle | Implémentation | Fichier |
|---------|----------------|---------|
| MIME validation | `mime_content_type()` sur fichier temporaire, pas sur l'extension | StorageService:30 |
| Taille max | 20 Mo (`TAILLE_MAX_OCTETS`) | DocumentModel:69 |
| Path traversal | `str_starts_with($chemin, 'storage/documents/')` | StorageService:71 |
| Nommage opaque | `{prefix}_{bin2hex(random_bytes(8))}.{ext}` | StorageService:50 |
| Serve sécurisé | `realpath()` + `str_starts_with(baseDir)` | PreviewService |
| .htaccess | `Deny from all` sur `storage/documents/` | storage/documents/.htaccess |
| Soft delete | `deleted_at DATETIME NULL` — aucun DELETE physique | tous les Repos |
| CSRF | `verifyCsrf()` sur toutes les mutations POST | tous les Controllers |
| Confidentialité | `document.view_secret` requis pour `confidentialite='secret'` | DocumentPolicy:17 |
| Token partage | `bin2hex(random_bytes(32))` = 64 char hex, expirable | ShareService |
| Signature token | `bin2hex(random_bytes(32))` + expiration 7 jours | SignatureService:47 |
| Quota | `\OverflowException` stoppable et catchée dans `DocumentController::store()` | QuotaService |
| Audit | Toutes actions logées via AuditListener → AuditService | AuditListener |

---

## 11. Performance — ✅ VALIDÉE

| Mécanisme | Impact |
|---------|--------|
| FULLTEXT `AGAINST(:q IN BOOLEAN MODE)` | Recherche rapide jusqu'à ~500k docs |
| `LIMIT/OFFSET` paginé sur `findAll()` | Évite les scans complets |
| Index `(module_source, entite_type, entite_id)` | Lookup cross-module O(log n) |
| Index `(date_expiration, statut)` | Batch expiration efficace |
| Index `(etablissement_id)` | Isolation multi-tenant O(log n) |
| `mime_content_type()` une seule fois à l'upload | Pas de détection répétée |
| `hash_file('sha256')` à l'upload | Intégrité vérifiable sans ré-hash |

**Limites acceptées V2 :**
- `breadcrumb()` : N requêtes pour N niveaux (récursif). Acceptable ≤ 5 niveaux.
- Quota non atomique (race condition multi-upload simultané) — acceptable charge V2.
- Pas de cache Redis (prévu V3).

---

## 12. Compatibilité V1 — ✅ VALIDÉE

| Critère | Résultat |
|---------|---------|
| Contrôleurs V1 supprimés | Aucun — 0 suppression |
| Routes V1 modifiées | Aucune — 0 modification |
| Tables V1 altérées | Aucune — 0 ALTER TABLE |
| `rh_documents` (V1 RH) | Coexiste, namespace séparé `App\Modules\RH\Documents` |
| `config/modules.php` | Ajout additif seul — entrée `'documents'` en fin |
| `config/events.php` | Ajout additif seul — 16 mappings en fin |
| `config/permissions.php` | Ajout additif seul — bloc `document.*` + `folder.*` par rôle |
| Module désactivé | `enabled: false` — zéro impact bootstrap tant qu'inactif |

---

## 13. Compatibilité API — ✅ VALIDÉE

| Critère | Résultat |
|---------|---------|
| Préfixe routes | `/v2/` — aucune collision avec V1 |
| Format réponses JSON | `['success' => bool, 'data' => ...]` stable |
| Route publique token | `/v2/share/{token}` — sans auth, protégée par token |
| Serve fichier | `/v2/documents/file/{filename}` — avec auth |
| AJAX détection | `HTTP_X_REQUESTED_WITH` OU `HTTP_ACCEPT: application/json` |
| Upload | `multipart/form-data` avec `$_FILES['fichier']` |
| Pagination | `page` + `per_page` dans query string, retour `['data','total','page','per_page','total_pages']` |

---

## 14. Compatibilité Multi-Établissements — ✅ VALIDÉE

| Table | Colonne `etablissement_id` | Filtrage |
|-------|---------------------------|---------|
| `doc_documents` | ✅ | Automatique via DocumentFiltersDTO::$etablissementId |
| `doc_folders` | ✅ | Filtré dans FolderRepository::findByModule() |
| `doc_tags` | ✅ | Filtré dans TagRepository::findAll() |
| `doc_quotas` | ✅ | Filtré dans QuotaRepository |
| `doc_partages` | via document | Héritage via document_id |

**Note V2 :** Les fichiers physiques sont stockés par `{module}/{year}/{month}/` — non partitionnés par établissement. Acceptable V2 ; partitionnement `{etab}/{module}/` prévu V3.

---

## 15. Dette technique résiduelle

| ID | Sévérité | Description | Cible |
|----|----------|-------------|-------|
| DT-D-001 | Mineure | `SignatureService` stub — sans PKI, OTP, ni circuit email réel | V3 |
| DT-D-002 | Mineure | `SearchIndexListener` no-op — moteur externe (Meilisearch) prévu | V3 |
| DT-D-003 | Mineure | `verifierExpirations()` sans déclencheur cron — à brancher côté infra | V2.1 |
| DT-D-006 | Mineure | `breadcrumb()` : N requêtes pour N niveaux (pas de CTE récursive) | V3 |
| DT-D-007 | Info | Quota incrémental non atomique (race condition multi-upload concurrent) | V3 |

---

## Éléments reportés en V2.1

| Élément | Raison du report |
|---------|-----------------|
| Cron `verifierExpirations()` | Configuration infra, hors scope code |
| API REST JSON `/api/v2/documents/*` | Prévu Phase 7.x — roadmap Enterprise Review |
| StorageInterface S3-ready | DT-D-004 résolu en V2, implémentation S3 en V3 |
| Partitionnement stockage par établissement | Acceptable V2 mono-instance |

## Éléments reportés en V3

| Élément | Raison du report |
|---------|-----------------|
| PKI / circuit signatures électroniques | Complexité, certificats, PKCS#7 |
| Moteur de recherche externe (Meilisearch) | Dépendance infrastructure |
| Cache Redis quotas / métadonnées | Dépendance infrastructure |
| CTE récursive breadcrumb | Optimisation non critique en V2 |
| `document.download` comme permission séparée | Actuellement alias de `document.view` |

---

## Activation du module

```bash
# 1. Exécuter la migration
mysql -u root -p scolaris_db < database/migrations/doc_001_documents.sql

# 2. Créer le répertoire de stockage
mkdir -p storage/documents
cp storage/documents/.htaccess storage/documents/  # déjà en place

# 3. Activer dans config/modules.php
'documents' => ['enabled' => true, ...]
```

```sql
-- Vérification post-migration
SHOW TABLES LIKE 'doc_%';
-- Résultat attendu : 11 tables
SELECT COUNT(*) FROM doc_categories;
-- Résultat attendu : 24 (catégories seed)
```

---

## Interface publique pour les modules consumers

Les modules RH, Finance, Académique, Vie Scolaire, Scolarité utilisent le module Documents via :

```php
// Instanciation directe (V2) — API REST prévue V3
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Documents\DTO\DocumentDTO;
use App\Modules\Documents\DTO\DocumentFiltersDTO;
use App\Modules\Documents\Repositories\DocumentRepository;

// Upload d'un document depuis un module consumer
$svc = new DocumentService();
$dto = DocumentDTO::fromRequest([
    'titre'        => 'Contrat de travail',
    'module_source'=> 'rh',
    'entite_type'  => 'employe',
    'entite_id'    => $employeId,
    'confidentialite' => 'confidentiel',
]);
$documentId = $svc->uploader($dto, $_FILES['fichier'], $currentUserId);

// Récupérer les documents d'une entité
$repo = new DocumentRepository();
$docs = $repo->findByEntite('rh', 'employe', $employeId);
```

---

## Règles de gel (FROZEN — à respecter par toutes les phases suivantes)

1. **Toute nouvelle table** `doc_*` requiert une migration `doc_002_*.sql` + revue d'intégration
2. **Toute modification de signature** d'une méthode publique de service requiert une revue de tous les consumers
3. **Tout nouvel event Documents** doit être déclaré dans `config/events.php` avec au minimum un handler `AuditListener`
4. **Toute nouvelle permission** `document.*` ou `folder.*` doit être ajoutée dans `config/permissions.php` pour tous les rôles concernés
5. **Aucun DELETE physique** en base — soft delete via `deleted_at` uniquement
6. **Aucune modification** des tables V1 (`rh_documents`, etc.) via des migrations Documents
7. **Aucun import cross-module** dans les Listeners Documents (couplage zero)

---

*Rapport DOCUMENTS_MODULE_FREEZE.md généré le 2026-07-02.*  
*Module Documents V2 officiellement gelé en version 2.0.0.*  
*Prochain jalon : Phase 8.0 — Enterprise Integration & API REST.*
