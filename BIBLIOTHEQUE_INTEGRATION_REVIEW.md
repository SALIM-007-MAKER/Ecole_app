# BIBLIOTHEQUE_INTEGRATION_REVIEW.md
## Phase 9.3 — Bibliothèque V2 — System Integration Review

**Date :** 2026-07-03  
**Reviewer :** SCOLARIS V2 Integration Audit  
**Module :** `App\Modules\Bibliotheque` (enabled=false)  
**Version :** 2.0.0  

---

## 1. RÉSUMÉ EXÉCUTIF

| Dimension            | Score |
|----------------------|-------|
| Architecture         | 9.0   |
| Base de données      | 9.0   |
| Services             | 8.5   |
| RBAC & Permissions   | 9.0   |
| Événements           | 8.5   |
| Sécurité             | 8.5   |
| Performance          | 8.0   |
| Intégration          | 8.0   |
| Compatibilité V1     | 10.0  |
| SaaS / Multi-étab    | 8.5   |
| **GLOBAL**           | **8.7 / 10** |

**VERDICT : ✅ GO — Module validé après corrections appliquées**

---

## 2. PÉRIMÈTRE AUDITÉ

- **SQL :** `database/migrations/biblio_001_bibliotheque.sql` — 14 tables
- **Events :** 18 événements (`app/Modules/Bibliotheque/Events/`)
- **DTOs :** 8 fichiers
- **Models :** 5 fichiers
- **Policies :** 2 (`BiblioPolicy`, `EmpruntPolicy`)
- **Repositories :** 10 fichiers
- **Services :** 8 + 2 stubs barcode/QR
- **Listeners :** 3 (`AuditListener`, `FinanceIntegrationListener`, `BiblioNotificationHandler`)
- **Controllers :** 9 fichiers
- **Views :** 14 fichiers
- **Routes :** 57 routes (`/v2/bibliotheque/*`)
- **Configs :** `modules.php`, `events.php`, `permissions.php`

---

## 3. ANOMALIES CRITIQUES IDENTIFIÉES ET CORRIGÉES

### BC-C-001 — CatalogueController : appels sans etablissementId ✅ CORRIGÉ
**Fichier :** `Controllers/CatalogueController.php`  
**Problème :** `listerCategories()`, `listerAuteurs()`, `listerEditeurs()` appelés sans argument alors que `CatalogueService` exige `int $etablissementId`.  
**Correction :** Toutes les actions (`index`, `create`, `edit`) passent désormais `$etablissementId` extrait de `$this->user`.

### BC-C-002 — ReferentielController : signatures incompatibles avec CatalogueService ✅ CORRIGÉ
**Fichier :** `Controllers/ReferentielController.php`  
**Problème :** Appels du type `ajouterAuteur($_POST['nom'], $_POST['prenom'], $_POST['biographie'])` (3 strings) alors que le service attend `(array $data, int $etablissementId)`. Même mismatch pour Éditeur, Catégorie, Tag. Méthode `archiverEditeur()` absente du service.  
**Correction :**  
- Controller refactorisé pour construire des tableaux et passer `$etab`.  
- `CatalogueService::archiverEditeur(int $id): void` ajouté.  
- Les userId inutiles supprimés des appels `archiverAuteur`/`archiverCategorie` (service n'en a pas besoin).

### BC-C-003 — EmpruntRepository::markOverdue() — injection SQL ✅ CORRIGÉ
**Fichier :** `Repositories/EmpruntRepository.php:69`  
**Problème :** `$ids = implode(',', array_column($rows, 'id'))` injecté directement dans `exec("WHERE id IN ($ids)")`. Si un ID était corrompu, exécution arbitraire possible.  
**Correction :** `array_map('intval', ...)` appliqué avant `implode` — toutes les valeurs sont garanties entières.

### BC-C-004 — emprunts/index.php : alias de colonnes incorrects ✅ CORRIGÉ
**Fichier :** `Views/emprunts/index.php:45`  
**Problème :** `$e['emprunteur_prenom']` / `$e['emprunteur_nom']` — ces clés n'existent pas. La requête `EmpruntRepository::findEnCours()` retourne `u.prenom` et `u.nom AS user_nom`.  
**Correction :** Remplacé par `$e['prenom']` et `$e['user_nom']`.

### BC-C-005 — exemplaires/index.php : ENUM etat incompatible ✅ CORRIGÉ
**Fichier :** `Views/exemplaires/index.php:38-42`  
**Problème :** Le formulaire proposait `neuf / bon / acceptable / mauvais`. Le ENUM SQL est `('bon','use','deteriore')`. Toute saisie de `neuf` ou `acceptable` provoquait une erreur SQL silencieuse (MySQL -> valeur vide).  
**Correction :** Options alignées : `bon / use / détérioré`. Labels humains affichés via `$etatLabels[]` mapping.

### BC-C-006 — AuditListener : clés camelCase au lieu de snake_case ✅ CORRIGÉ
**Fichier :** `Listeners/AuditListener.php`  
**Problème :** Tous les handlers utilisaient `$data['ouvrageId']`, `$data['userId']`, `$data['empruntId']`, etc. Or toutes les méthodes `toArray()` des événements retournent du snake_case (`ouvrage_id`, `user_id`, `emprunt_id`). Les 18 appels à `AuditService` recevaient `null` pour les IDs.  
**Correction :** 18 handlers corrigés avec les bonnes clés snake_case.

---

## 4. ANOMALIES MAJEURES IDENTIFIÉES ET CORRIGÉES

### BC-M-001 — PenaliteRepository::totalImpayeesUser() — mauvais type ✅ CORRIGÉ
**Fichier :** `Repositories/PenaliteRepository.php:105`  
**Problème :** `float $userId` — un ID utilisateur ne peut pas être un float.  
**Correction :** `int $userId`.

### BC-M-002 — BiblioAnalyticsService::penalitesImpayees() — statut inexistant ✅ CORRIGÉ
**Fichier :** `Services/BiblioAnalyticsService.php:203`  
**Problème :** `WHERE statut = 'impayee'` — ce statut n'existe pas dans le ENUM `('en_attente','payee','annulee')`. La requête retournait toujours 0.  
**Correction :** `WHERE statut = 'en_attente'`.

### BC-M-003 — CatalogueService : méthode archiverEditeur() absente ✅ CORRIGÉ
**Fichier :** `Services/CatalogueService.php`  
**Problème :** `ReferentielController::archiveEditeur()` appelait `$this->service->archiverEditeur($id)` — méthode non définie.  
**Correction :** Méthode `archiverEditeur(int $id): void` ajoutée, délègue à `$this->editeurs->softDelete($id)`.

### BC-M-004 — penalites/index.php : alias prenom et statut impayee ✅ CORRIGÉ
**Fichier :** `Views/penalites/index.php`  
**Problème :**  
- `$p['user_prenom']` inexistant — requête retourne `u.prenom` (clé `prenom`).  
- Comparaison `$p['statut'] === 'impayee'` impossible (ENUM n'a pas cette valeur).  
**Correction :** `$p['prenom']` ; conditions `=== 'en_attente'` ; label affiché `'Impayée'` via ternaire.

---

## 5. POINTS VALIDÉS

### 5.1 Architecture
- ✅ `declare(strict_types=1)` sur tous les fichiers PHP
- ✅ PSR-4 `App\Modules\Bibliotheque\` via autoloader `App\Modules\`
- ✅ Pattern Repository : tout le SQL dans les Repositories, PDO avec `Database::getInstance()`
- ✅ Pattern DTO avec `fromRequest()` factory statique
- ✅ Contrôleurs minces : pas de SQL direct, uniquement appels de Services
- ✅ Services dispatch les événements via `EventDispatcher::dispatch()`
- ✅ Machine d'états cohérente : Exemplaire (6 statuts), Emprunt (4), Réservation (5), Pénalité (3)
- ✅ File FIFO des réservations avec délai confirmation 48h
- ✅ `BarcodeInterface` + `QrCodeInterface` contractuelles (stubs V2, libs V3)

### 5.2 Base de données
- ✅ 14 tables `biblio_*` avec `CREATE TABLE IF NOT EXISTS` (idempotentes)
- ✅ Index FULLTEXT sur `biblio_ouvrages(titre, sous_titre, resume)`
- ✅ Clés étrangères avec `ON DELETE RESTRICT` / `ON DELETE SET NULL` selon sémantique
- ✅ `etablissement_id` sur toutes les tables (SaaS multi-tenant)
- ✅ `deleted_at` sur toutes les entités principales (soft delete)
- ✅ `created_at`/`updated_at` via `DEFAULT CURRENT_TIMESTAMP ON UPDATE`
- ✅ Tables de liaison normalisées : `biblio_ouvrage_auteurs`, `biblio_ouvrage_categories`, `biblio_ouvrage_tags`

### 5.3 Services
- ✅ `EmpruntService` : quota 3 emprunts simultanés, blocage pénalités ≥3, prolongation ≤2 fois
- ✅ `ReservationService` : FIFO, expiration automatique, notification en cascade
- ✅ `PenaliteService` : calcul 0,50 €/jour retard, 25,00 € perte, paiement Finance
- ✅ `CatalogueService` : CRUD ouvrages + référentiels (auteurs/éditeurs/catégories/tags)
- ✅ `ExemplaireService` : génération numéro inventaire auto, QR/barcode via contrats
- ✅ `RechercheService` : FULLTEXT + LIKE fallback, suggestions, ouvrages similaires
- ✅ `InventaireService` : session scan, détection manquants/détériorés, clôture
- ✅ `BiblioAnalyticsService` : 11 requêtes SQL directes, dashboard, export JSON/CSV

### 5.4 RBAC
- ✅ 11 permissions `biblio.*` : `view, search, borrow, reserve, manage_loans, manage_catalogue, manage_exemplaires, manage_penalties, inventory, analytics, admin`
- ✅ 7 rôles configurés avec permissions graduées (admin → élève)
- ✅ `BiblioPolicy` : 11 méthodes delegates à `$user['permissions']`
- ✅ `EmpruntPolicy` : logique métier (quota, statut, prolongation)
- ✅ `requirePermission()` sur toutes les actions de tous les controllers

### 5.5 Événements
- ✅ 18 événements couvrant tout le cycle de vie (catalogue, exemplaire, emprunt, réservation, pénalité, inventaire)
- ✅ Toutes les méthodes `toArray()` retournent des clés snake_case cohérentes
- ✅ `AuditListener` lit correctement les 18 événements (corrigé BC-C-006)
- ✅ `FinanceIntegrationListener` raccordé à `PaymentCompleted` dans `config/events.php`
- ✅ `BiblioNotificationHandler` en place — extension point Communication (stub V2)

### 5.6 Sécurité
- ✅ Aucune injection SQL (après correction BC-C-003)
- ✅ `verifyCsrf()` sur tous les POST/mutations
- ✅ `requirePermission()` avant chaque action sensible
- ✅ Toutes les saisies utilisateur via paramètres liés PDO
- ✅ Aucun `DELETE` physique — soft delete uniquement
- ✅ `htmlspecialchars()` sur toutes les sorties dans les vues

### 5.7 Performance
- ✅ FULLTEXT index + BOOLEAN MODE pour la recherche catalogue
- ✅ `LIKE` fallback en cas d'échec FULLTEXT (petit dataset)
- ✅ Pagination sur emprunts en cours et réservations
- ✅ Requêtes analytiques groupées, pas de N+1
- ✅ `countImpayeesUser()` + `countEnCoursUser()` — requêtes COUNT scalaires (pas fetch all)
- ✅ `ExemplaireRepository::findDisponibles()` avec index sur `statut`

### 5.8 Intégration inter-modules
- ✅ **Finance :** `PenaliteService` → `PenaliteCreee` → `FinanceIntegrationListener` → `PaymentCompleted` → `marquerPayeeViaFinance()`
- ✅ **Audit :** `AuditService::logCreate/log` déclenché sur les 18 événements via `AuditListener`
- ✅ **Communication :** `BiblioNotificationHandler` en place pour `EmpruntEnRetard`, `ReservationDisponible`, `PenaliteCreee` (implémentation Communication V2 requise)
- ✅ **Core :** `Database`, `EventDispatcher`, `Controller`, `Event`, `Listener` tous utilisés correctement

### 5.9 Compatibilité V1
- ✅ Module `enabled=false` dans `config/modules.php` — routes non chargées
- ✅ Aucun fichier V1 modifié
- ✅ Aucune table existante touchée (14 tables `biblio_*` entièrement nouvelles)
- ✅ Coexistence V1 garantie

### 5.10 SaaS / Multi-établissements
- ✅ `etablissement_id` sur les 14 tables
- ✅ Toutes les requêtes filtrées par `etablissement_id`
- ✅ `$this->user['etablissement_id']` extrait dans chaque controller
- ✅ Services paramétriques sur `int $etablissementId`

---

## 6. DETTE TECHNIQUE (non bloquante)

| ID       | Description | Impact | Déféré à |
|----------|-------------|--------|----------|
| DT-B-001 | `BarcodeService` stub — ne génère pas de vrai code-barres | Faible | V3 |
| DT-B-002 | `QrCodeService` stub — ne génère pas de vrai QR | Faible | V3 |
| DT-B-003 | `RechercheService::ouvragesSimilaires()` basé sur titre seulement | Moyen | V3 |
| DT-B-004 | Pas de création automatique de facture Finance à la création d'une pénalité | Moyen | V3 (nécessite FN-C review) |
| DT-B-005 | `ExemplaireRepository::nextSequence()` via COUNT(*) — pas gap-safe | Faible | V3 |
| DT-B-006 | `syncAuteurs/Categories/Tags` via DELETE+INSERT — non idempotent sous concurrence | Faible | V3 |

---

## 7. RISQUES RÉSIDUELS

### R-001 — Race condition Réservation (faible probabilité)
`ReservationService::creerReservation()` lit `countDisponibles` puis insère. Deux requêtes simultanées pourraient toutes deux obtenir `statut='disponible'`. Mitigation : `hasActiveReservation()` bloque la double réservation d'un même user. Impact faible en production scolaire (faible concurrence). Correction possible via `SELECT ... FOR UPDATE` en V3.

### R-002 — BiblioNotificationHandler est un stub
Les notifications `EmpruntEnRetard`, `ReservationDisponible`, `PenaliteCreee` ne génèrent actuellement aucun message. Les utilisateurs ne reçoivent pas d'alertes. Dépend de l'activation du module `Communication`.

---

## 8. VALIDATION FINALE

### Corrections appliquées : 10/10
| # | Anomalie | Statut |
|---|----------|--------|
| 1 | BC-C-001 CatalogueController sans etablissementId | ✅ CORRIGÉ |
| 2 | BC-C-002 ReferentielController mauvaises signatures | ✅ CORRIGÉ |
| 3 | BC-C-003 EmpruntRepository::markOverdue() SQL injection | ✅ CORRIGÉ |
| 4 | BC-C-004 emprunts/index.php alias emprunteur_prenom | ✅ CORRIGÉ |
| 5 | BC-C-005 exemplaires/index.php ENUM etat mismatch | ✅ CORRIGÉ |
| 6 | BC-C-006 AuditListener clés camelCase | ✅ CORRIGÉ |
| 7 | BC-M-001 PenaliteRepository float userId | ✅ CORRIGÉ |
| 8 | BC-M-002 BiblioAnalyticsService statut='impayee' | ✅ CORRIGÉ |
| 9 | BC-M-003 CatalogueService archiverEditeur() absent | ✅ CORRIGÉ |
| 10| BC-M-004 penalites/index.php user_prenom + impayee | ✅ CORRIGÉ |

### Fichiers modifiés lors de cette phase
| Fichier | Correction |
|---------|-----------|
| `Listeners/AuditListener.php` | BC-C-006 : 18 handlers corrigés snake_case |
| `Services/CatalogueService.php` | BC-M-003 : ajout `archiverEditeur()` |
| `Controllers/ReferentielController.php` | BC-C-002 : refactoring complet des appels service |
| `Controllers/CatalogueController.php` | BC-C-001 : `$etablissementId` passé aux lister*() |
| `Repositories/EmpruntRepository.php` | BC-C-003 : `array_map('intval', ...)` dans markOverdue() |
| `Repositories/PenaliteRepository.php` | BC-M-001 : `float` → `int` pour `$userId` |
| `Services/BiblioAnalyticsService.php` | BC-M-002 : `'impayee'` → `'en_attente'` |
| `Views/emprunts/index.php` | BC-C-004 : `prenom`/`user_nom` |
| `Views/penalites/index.php` | BC-M-004 : `prenom`/`user_nom` + `en_attente` |
| `Views/exemplaires/index.php` | BC-C-005 : ENUM `bon/use/deteriore` |

---

## 9. VERDICT FINAL

```
╔══════════════════════════════════════════════════════════════╗
║     MODULE BIBLIOTHÈQUE V2 — PHASE 9.3 INTEGRATION REVIEW    ║
║                                                              ║
║   SCORE GLOBAL : 8.7 / 10                                    ║
║                                                              ║
║   VERDICT : ✅ GO                                            ║
║                                                              ║
║   10 anomalies corrigées (6 critiques + 4 majeures)          ║
║   6 dettes techniques mineures → déférées V3                 ║
║   Module prêt pour activation (enabled=true)                 ║
║   après exécution de biblio_001_bibliotheque.sql             ║
╚══════════════════════════════════════════════════════════════╝
```

### Prérequis pour activation
1. Exécuter `database/migrations/biblio_001_bibliotheque.sql` sur la base de données
2. Passer `'bibliotheque' => ['enabled' => true, ...]` dans `config/modules.php`
3. Activer le module Communication pour les notifications temps réel (R-002)
4. Intégrer les libs barcode/QR en production (DT-B-001/002)

---

*Rapport produit par l'audit Phase 9.3 — SCOLARIS V2*  
*Version module : 2.0.0 — Date : 2026-07-03*
