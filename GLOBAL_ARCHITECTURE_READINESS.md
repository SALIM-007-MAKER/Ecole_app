# GLOBAL ARCHITECTURE READINESS REVIEW
**Phase 3.0 — Audit de préparation avant développement des modules restants**
Date : 2026-07-01
Périmètre : ecole_app entier — Core, Shared Services, Event System, RBAC, Scolarité V2, Académique V2
Auditeur : Revue statique complète (aucun fichier modifié)

---

## Résultat global

| Dimension | Score | Verdict |
|-----------|-------|---------|
| Architecture globale V2 | 8.5/10 | ✅ Conventions respectées |
| Infrastructure module (chargement + dispatch) | **4.0/10** | ⛔ 2 bloquants critiques |
| Couplage inter-modules | 7.5/10 | ⚠ V1 imports en Scolarité (connu) |
| Contrats & communication | 8.5/10 | ✅ Events + Contracts propres |
| Performances | 6.5/10 | ⚠ Index manquants, N+1 |
| Sécurité | 7.0/10 | ⚠ Gap permissions V2, AuditService bugs |
| Extensibilité | 7.0/10 | ⚠ Conditionnelle (dépend du fix infra) |

### **Score global : 7.0 / 10**

### Verdict : ⛔ **NO-GO — 2 bloquants d'infrastructure à corriger avant Phase 3**

Les modules Scolarité et Académique V2 sont correctement **écrits** mais **ne peuvent pas être activés** : l'Application ne charge jamais les routes des modules, et le Router ne sait pas dispatcher vers les namespaces modules. Ces deux défauts bloquent l'ensemble de la roadmap V2.

---

## 1. Architecture globale

### 1.1 Core framework ✅

| Fichier | Rôle | Qualité |
|---------|------|---------|
| `core/Application.php` | Bootstrap, Session, Event registration | ✅ Singleton propre |
| `core/Router.php` | Dispatch HTTP | ⛔ Namespace hardcodé (voir § critique) |
| `core/Database.php` | Connexion PDO Singleton | ✅ |
| `core/EventDispatcher.php` | Event bus statique, try/catch par handler | ✅ |
| `core/Controller.php` | Base controller, CSRF, requirePermission, security headers | ✅ |
| `core/View.php` | Render layout + `Module::path` notation | ✅ |
| `core/Event.php` | Classe abstraite event | ✅ |
| `core/Listener.php` | Interface handler | ✅ |
| `core/Session.php` | Session PHP native | ✅ |
| `core/Logger.php` | Logger fichier | ✅ |

**Points forts Core :**
- Autoloader PSR-4 maison dans `public/index.php` : 8 namespaces mappés, dont `App\Modules\` ✅
- Security headers systématiques dans `Controller::__construct()` (X-Frame-Options, CSP, X-Content-Type) ✅
- `EventDispatcher::dispatch()` : chaque handler isolé dans try/catch, exception non propagée ✅
- `View::render()` supporte la notation `Module::Views/path` pour les modules ✅

### 1.2 Séparation Core / Modules / Shared ✅

```
core/            → Framework (Router, DB, View, Event, Controller…)
app/Services/    → Shared Services (AuditService, NotificationService, UploadService, EmailService, SmsService)
app/Events/      → Events V1 (7 événements legacy)
app/Listeners/   → Handlers V1 (AuditHandler, NotificationHandler, StatsCacheHandler)
app/Modules/     → Modules V2 (Scolarite, Academique, …)
app/Controllers/ → Controllers V1
app/Models/      → Models V1 (ActiveRecord léger)
```

**Séparation respectée :** aucune logique métier dans le Core, aucune dépendance Core → App.

### 1.3 Conventions V2 ✅

| Convention | Statut | Commentaire |
|-----------|--------|-------------|
| Namespace `App\Modules\{Module}\` | ✅ | Tous les fichiers V2 |
| Routes préfixées `/v2/{module}/*` | ✅ | Aucun conflit avec V1 |
| Controllers héritent de `Core\Controller` | ✅ | 9/9 controllers V2 |
| Services sans accès HTTP | ✅ | Aucun `$_GET`/`$_POST` dans les Services |
| Repositories = SQL, Services = logique | ✅ | Respecté partout sauf NoteService N+1 |
| DTOs readonly | ✅ | 20 DTOs PHP 8.1 |
| Policies RBAC | ✅ | 12 Policies (5 Scolarité + 7 Académique) |
| Value Objects immuables | ✅ | 6 VOs Académique |
| Contracts (interfaces) | ✅ | 2 interfaces Académique |
| `module.json` présent | ⚠ | Académique : stale (version 2.1.0 au lieu de 2.8.0) |

### 1.4 Cohérence des namespaces ✅ avec 1 anomalie

Tous les `use` statements des services, handlers et repositories sont dans le bon namespace — **sauf** :

| Fichier | Problème | Statut |
|---------|---------|--------|
| `NoteHandler.php` | `use Services\AuditService` → doit être `App\Services\AuditService` | ⛔ Crash PHP 8.2 |
| `AverageHandler.php` | `use Services\AuditService` → doit être `App\Services\AuditService` | ⛔ Crash PHP 8.2 |
| `BulletinHandler.php` | Appel statique sur classe non-statique | ⚠ Error PHP 8.2 |
| `RankingHandler.php` | Appel statique sur classe non-statique | ⚠ Error PHP 8.2 |
| `AnalyticsHandler.php` | Appel statique sur classe non-statique | ⚠ Error PHP 8.2 |

---

## 2. Bloquants d'infrastructure critiques

### ⛔ INF-C-001 — Application ne charge jamais les routes des modules

**Localisation :** `core/Application.php:63-74`

```php
public function run(): void
{
    $request = new Request();
    $router = $this->router;
    require ROOT_PATH . '/config/routes.php';   // ← SEULEMENT les routes V1

    $events = require ROOT_PATH . '/config/events.php';
    foreach ($events as $eventClass => $listeners) { ... }

    $this->router->dispatch($request);
}
```

`config/modules.php` déclare les modules avec leurs `routes` et leur flag `enabled`, mais `Application::run()` n'itère **jamais** ce fichier. Les routes V2 (`/v2/scolarite/*`, `/v2/academique/*`) ne sont **jamais enregistrées** dans le Router.

**Impact :** Toute tentative d'accès à une URL V2 retourne 404, même avec `enabled: true`.

**Correction requise :**
```php
// Dans Application::run(), après le require config/routes.php :
$modules = require ROOT_PATH . '/config/modules.php';
foreach ($modules as $slug => $module) {
    if (!empty($module['enabled']) && file_exists($module['routes'])) {
        require $module['routes'];
    }
}
```

---

### ⛔ INF-C-002 — Router::callHandler() force le namespace `App\Controllers\`

**Localisation :** `core/Router.php:82`

```php
private function callHandler(string $handler, array $params): void
{
    [$controllerName, $method] = explode('@', $handler, 2);
    $controllerName = str_replace('/', '\\', $controllerName);
    $class = 'App\\Controllers\\' . $controllerName;   // ← HARDCODÉ
    ...
}
```

Les routes V2 utilisent des handlers comme `'Scolarite\Controllers\EleveController@index'`.
Le Router résout cela en `App\Controllers\Scolarite\Controllers\EleveController` au lieu de `App\Modules\Scolarite\Controllers\EleveController`.

**Impact :** Même si INF-C-001 est corrigé et que les routes V2 sont chargées, chaque dispatch retourne 404 (classe introuvable). Les 95 routes V2 sont **toutes non-fonctionnelles**.

**Correction requise :**
```php
private function callHandler(string $handler, array $params): void
{
    [$controllerName, $method] = explode('@', $handler, 2);
    $controllerName = str_replace('/', '\\', $controllerName);

    // Résolution namespace : Module\Controllers\X → App\Modules\Module\Controllers\X
    // ou sous-namespace V1 : Api\X → App\Controllers\Api\X
    if (str_contains($controllerName, '\\Controllers\\')) {
        $class = 'App\\Modules\\' . $controllerName;
    } else {
        $class = 'App\\Controllers\\' . $controllerName;
    }
    ...
}
```

---

### ⛔ INF-C-003 — Permissions V2 absentes de `config/permissions.php`

**Localisation :** `config/permissions.php`

Le fichier définit les permissions par rôle (ex: `notes.view`, `bulletins.view`) mais les modules V2 utilisent un espace de nommage différent :
- Scolarité V2 : `eleves.view.own`, `inscriptions.create`, `familles.manage`
- Académique V2 : `academique.periodes.view`, `academique.notes.manage`, `academique.bulletin.view`

Les permissions V2 ne sont pas dans `config/permissions.php`. Elles ne sont **jamais assignées** à un rôle lors du login. Résultat : `requirePermission('academique.notes.manage')` échoue pour **tous les utilisateurs** → 403 systématique sur tous les endpoints V2.

**Correction requise :** Ajouter les permissions V2 de chaque module à `config/permissions.php` pour chaque rôle concerné.

---

## 3. Couplage

### 3.1 Cartographie des dépendances inter-modules

```
config/events.php  ←──── tous les handlers (couplage centralisé ✅)
                          V1: AuditHandler, NotificationHandler, StatsCacheHandler
                          Scolarite: EleveHandler, ClasseHandler, InscriptionHandler, FamilleHandler, MatiereHandler
                          Academique: PeriodeHandler, TypeEvaluationHandler, EvaluationHandler,
                                      NoteHandler, AverageHandler, RankingHandler, BulletinHandler, AnalyticsHandler

App\Modules\Academique  ──reads──▶  tables V1 (eleves, classes, matieres) via SQL direct
App\Modules\Scolarite   ──imports──▶  App\Models\ClasseModel, MatiereModel, EleveModel, UserModel (V1)
App\Modules\Academique  ──NO direct import──▶  App\Modules\Scolarite (✅ aucun use statement)
App\Modules\Scolarite   ──NO direct import──▶  App\Modules\Academique (✅ aucun use statement)
```

### 3.2 Couplage fort résiduel

| Couplage | Lieu | Justification | Risque |
|---------|------|--------------|--------|
| Scolarite → `App\Models\ClasseModel` (V1) | `ClasseController.php:5` | Compatibilité transitoire | Moyen — bloque extraction complète |
| Scolarite → `App\Models\EleveModel` (V1) | `EleveController.php:7`, `InscriptionController.php:5` | Idem | Moyen |
| Academique → tables V1 via SQL | 8 repositories | SQL JOIN sur `eleves`, `classes`, `matieres` | Faible — tabless stables |
| V1 Controllers → instanciation directe de Models | 18 controllers | Pattern V1 (normal) | Nul — encapsulé dans V1 |

### 3.3 Communication inter-modules : via Events ✅

Aucun module V2 n'importe directement une classe d'un autre module V2. La communication passe uniquement par :
- **Events/Listeners** : `config/events.php` comme bus central
- **Tables partagées** : lecture des tables V1 (données commune) via SQL
- **Shared Services** : `AuditService`, `NotificationService` (namespace `App\Services\`)

**Dépendances circulaires :** aucune détectée ✅

---

## 4. Contrats

### 4.1 Contracts entre modules ✅

| Interface | Module | Implémenteur | Utilisateurs |
|-----------|--------|-------------|-------------|
| `BulletinGeneratorInterface` | Academique | `BulletinGenerator` | Futur: Controllers, API |
| `AcademicAnalyticsInterface` | Academique | `AcademicAnalyticsService` | Futur: Controllers, API |

### 4.2 Shared Services comme contrats implicites ✅

| Service | API publique | Utilisateurs V2 |
|---------|-------------|----------------|
| `AuditService` | `log()`, `logCreate()`, `logUpdate()`, `logDelete()`, `logLogin()` | 7 handlers Académique ⚠ (bugs namespace/static) |
| `NotificationService` | `notify()`, `notifyBulk()`, `onAbsence()`, `onNote()`, `onPaiement()` | 0 handlers V2 (non câblé) |
| `UploadService` | `upload()`, `validate()`, `delete()`, `url()` | `NoteController::importerCsv()` (contournement) |

### 4.3 Communication via Events : 50 événements au total

| Module | Événements | Handlers | Câblage dans events.php |
|--------|-----------|---------|------------------------|
| V1 legacy | 7 | 3 | ✅ |
| Scolarite V2 | 16 | 5 | ✅ |
| Academique V2 | 27 | 8 | ✅ (5 avec bugs AuditService) |
| **Total** | **50** | **16** | — |

**Gap :** `MatiereAssignedToClasse` et `MatiereRemovedFromClasse` sont câblés dans `MatiereHandler` mais `MatiereHandler` ne déclenche **aucune action** sur le module Académique (DT-M1 de la Phase 1.7) — si une matière est retirée d'une classe, les évaluations en cours ne sont pas invalidées.

---

## 5. Performances

### 5.1 Requêtes SQL — problèmes identifiés

| Problème | Localisation | Impact | Priorité |
|---------|-------------|--------|---------|
| N+1 `publierTout()` | `NoteService.php` | 30-60 req/appel | Haute |
| N+1 `verrouillerTout()` | `NoteService.php` | 30-60 req/appel | Haute |
| `classementClasse()` pour 1 élève | `BulletinGenerator` | O(N) élèves/appel | Haute |
| 2× COUNT dans `paginate()` | `NoteRepository` | requête redondante | Basse |
| Pas de cache sur dashboards | `AcademicAnalyticsService` | 6 requêtes/appel répété | Haute |
| `onAbsence()` : `new EleveModel()` inline | `NotificationService` | 1 objet/notification | Moyenne |

### 5.2 Index SQL manquants

```sql
-- À créer dans une migration M_PERF_001

-- evaluations : classements et bulletins
CREATE INDEX idx_eval_periode_statut   ON evaluations (periode_scolaire_id, statut);
CREATE INDEX idx_eval_classe_periode   ON evaluations (classe_id, periode_scolaire_id, statut);

-- notes_v2 : requêtes analytiques et classement
CREATE INDEX idx_note_eval_absent      ON notes_v2 (evaluation_id, est_absent);
CREATE INDEX idx_note_eleve_eval       ON notes_v2 (eleve_id, evaluation_id);

-- eleves : jointures classements
CREATE INDEX idx_eleve_classe          ON eleves (classe_id);

-- audit_logs : recherche admin
CREATE INDEX idx_audit_module_action   ON audit_logs (module, action, created_at);
```

**Impact estimé :** -60 à -80% sur les requêtes analytiques et classement.

### 5.3 Cache

Aucun mécanisme de cache applicatif implémenté. L'APCu est disponible sous PHP 8.2 mais non utilisé. Les dashboards directeur (`dashboardDirecteur()`) exécutent 6 requêtes GROUP BY à chaque appel sans cache.

**Recommandation pour Phase Finance :** planifier un `CacheService` (APCu ou Redis) avant les dashboards Finance qui seront appelés fréquemment.

### 5.4 Pagination ✅

Pagination présente dans tous les repositories V2 qui listent des collections. Pattern `[$perPage, $offset]` avec cast `(int)` — safe SQL.

---

## 6. Sécurité

### 6.1 RBAC — couverture

| Mécanisme | V1 | V2 Scolarite | V2 Académique |
|-----------|-----|-------------|--------------|
| `requireAuth()` | ✅ | ✅ | ✅ |
| `requirePermission()` | ✅ | ✅ | ✅ (39 occurrences) |
| `requireRole()` | ✅ | — | — |
| Policies RBAC | ✗ | ✅ (5) | ✅ (7) |
| Wildcard `*` dans Policies | — | ✅ | ⚠ manquant EvaluationPolicy |

**Gap critique :** Permissions V2 absentes de `config/permissions.php` (INF-C-003). Aucun utilisateur ne peut accéder aux endpoints V2.

**Gap ownership :** `BulletinPolicy::canView()` sans contrôle parent/enfant (AN-C-002), `NoteService` sans vérification élève∈classe (AN-M-001).

### 6.2 Audit Trail

| Module | Couverture | Statut |
|--------|-----------|--------|
| Auth (login/logout/fail) | `AuditService::logLogin()` | ✅ |
| V1 events | `AuditHandler` | ✅ |
| Scolarité V2 | EleveHandler, ClasseHandler, InscriptionHandler, FamilleHandler, MatiereHandler | ✅ (via `new AuditService()`) |
| Académique V2 (Période, TypeEvaluation, Evaluation) | PeriodeHandler, TypeEvaluationHandler, EvaluationHandler | ✅ |
| Académique V2 (Notes, Moyennes) | NoteHandler, AverageHandler | ⛔ crash namespace |
| Académique V2 (Ranking, Bulletin, Analytics) | RankingHandler, BulletinHandler, AnalyticsHandler | ⚠ appels statiques |

### 6.3 CSRF ✅

- `Core\Controller::verifyCsrf()` sur toutes les actions POST mutantes
- Vérification systématique détectée : 23 occurrences V2 + toutes les actions POST V1
- `Logger::security('CSRF_INVALID', ...)` sur échec → traçabilité

### 6.4 Headers de sécurité ✅

Chaque instanciation de `Controller::__construct()` envoie :
- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Content-Security-Policy` : whitelist cdn.tailwindcss.com, cdn.jsdelivr.net, fonts.googleapis.com

**Gap CSP :** `unsafe-inline` autorisé sur `script-src` et `style-src` (nécessaire pour Tailwind CDN). Acceptable en V2, à réviser si on passe en build local.

### 6.5 SQL Injection ✅

Toutes les requêtes dans les repositories V2 utilisent des requêtes préparées PDO avec paramètres positionnels ou nommés. Aucune interpolation de variable utilisateur détectée.

### 6.6 Upload ✅ avec réserves

- `UploadService::upload()` : MIME réel vérifié via `mime_content_type()` ✅
- `UploadService::delete()` : protection path traversal (`storage/` prefix check) ✅
- `NoteController::importerCsv()` : n'utilise pas `UploadService`, vérification extension uniquement ⚠

### 6.7 API readiness — gap

L'API REST actuelle (`/api/eleves`, `/api/frais-eleve`, `/api/notifications/*`) n'a pas :
- Rate limiting
- Token-based auth (JWT/API key) — utilise la session PHP
- Versioning (`/api/v1/` ou `/api/v2/`)
- CORS headers

Pour les portails parent/élève et les intégrations futures (API multi-établissements), une couche API dédiée sera nécessaire.

---

## 7. Extensibilité

### 7.1 Capacité à accueillir les modules futurs

| Module futur | Pré-requis | Facilité |
|-------------|-----------|---------|
| **Finance V2** | INF-C-001 + INF-C-002 + INF-C-003 corrigés | ✅ Après fix infra |
| **Vie scolaire** | Idem + tables absences V2 | ✅ Modèle Scolarité applicable |
| **Documents** | UploadService existant, `DocumentGenere` event V1 | ✅ Service déjà prêt |
| **Communication** | NotificationService + EmailService + SmsService existants | ✅ Infrastructure en place |
| **Bibliothèque** | Module indépendant, tables propres | ✅ Aucun pré-requis bloquant |
| **Inventaire** | Idem | ✅ |
| **Portail parent V2** | FamillePolicy::canViewForParent() (AN-C-002) | ⚠ 1 fix requis |
| **API REST V2** | Router API-aware, auth JWT, CORS | ⚠ Infrastructure à construire |
| **Multi-établissement** | Colonne `etablissement_id` sur toutes les tables | ⛔ Refactoring majeur |

### 7.2 Pattern de création d'un nouveau module V2

La structure démontrée par Scolarité et Académique établit un blueprint reproductible :

```
app/Modules/{Nom}/
├── Contracts/      (interfaces)
├── Controllers/    (thin, extends Core\Controller)
├── DTO/            (readonly, fromArray/toArray)
├── Events/         (extends Core\Event)
├── Listeners/      (implements Core\Listener)
├── Models/         (léger, lecture seule ou CRUD basique)
├── Policies/       (canX() + hasPermission())
├── Repositories/   (SQL complexe, pagination)
├── Services/       (orchestration, pas de SQL)
├── ValueObjects/   (immuables, validés)
├── Views/          (Tailwind + Lucide Icons)
├── module.json
└── routes.php      (préfixe /v2/{nom}/*)
```

**Temps de bootstrap estimé pour un nouveau module :** 2-4h (structure + autoloader + events.php + permissions.php + modules.php).

### 7.3 Contraintes d'extension

1. **Un seul fichier `config/events.php`** : deviendra très long avec 10+ modules (50 événements actuellement, projection 200+ à terme). Envisager `config/events/` directory avec 1 fichier par module.

2. **Un seul fichier `config/permissions.php`** : idem, maintenabilité à surveiller.

3. **Router synchrone, 1 handler par route** : pas de middleware stack, pas de route groups avec prefix automatique. Acceptable jusqu'à ~500 routes.

4. **Pas de conteneur DI** : instanciation manuelle dans les controllers (`new EvaluationService(...)`). Acceptable pour la taille actuelle, à réévaluer si > 20 modules.

---

## 8. Dette technique — classification

### Critique (GO bloquant — à corriger avant toute activation V2)

| ID | Description | Effort | Impact |
|----|------------|--------|--------|
| **INF-C-001** | `Application::run()` ne charge pas les routes des modules | 30 min | Tous les modules V2 inaccessibles |
| **INF-C-002** | `Router::callHandler()` hardcode `App\Controllers\` | 45 min | Toutes les routes V2 retournent 404 |
| **INF-C-003** | Permissions V2 absentes de `config/permissions.php` | 2h | Tous les endpoints V2 retournent 403 |
| **AN-C-003** | Namespace AuditService incorrect (NoteHandler, AverageHandler) | 10 min | Crash PHP 8.2 sur toute saisie de note |

### Majeure (avant mise en production)

| ID | Description | Effort |
|----|------------|--------|
| AN-C-001 | Pas de validation inscription/année dans EvaluationService + NoteService | 2h |
| AN-C-002 | `BulletinPolicy::canView()` sans ownership parent | 3h |
| AN-M-001 | `NoteService` : pas de vérification élève∈classe de l'évaluation | 1h |
| AN-M-002 | Année scolaire = string sans référentiel centralisé (`annees_scolaires`) | 4h |
| AN-M-003 | Aucun événement V2 ne déclenche `NotificationService` (parents non notifiés) | 2h |
| AN-M-004 | `NoteController::importerCsv()` contourne `UploadService` | 1h |
| AN-M-005 | Seuils mention dupliqués (MentionValue + SQL + getMentionCode) | 1h |
| DT-M1 | `MatiereAssigned/Removed` non consommés par Académique | 3h |

### Mineure (Phase 2.1 / optimisation)

| ID | Description |
|----|------------|
| AN-m-001 | `EvaluationPolicy` sans wildcard `*` |
| AN-m-002 | `NoteRepository` : `Database::getConnection()` statique (incohérence) |
| AN-m-003 | `EvaluationService::diff()` duplique `AuditService::diff()` |
| AN-m-004 | `NotePolicy::canSaisir()` : enseignant non restreint à ses matières |
| AN-m-005 | `NoteController` : vérification MIME type réelle manquante |
| DT-A-001 | `module.json` Académique stale (version 2.1.0, enabled:false) |
| PERF-001 | N+1 dans `NoteService::publierTout()` / `verrouillerTout()` |
| PERF-002 | 6 index SQL manquants (migration M_PERF_001) |
| PERF-003 | Aucun cache sur les dashboards analytiques |

### V2.1 (post-stabilisation)

| ID | Description |
|----|------------|
| ARCH-001 | Conteneur DI simple pour éviter `new Service()` dans les controllers |
| ARCH-002 | Éclater `config/events.php` en `config/events/{module}.php` |
| ARCH-003 | Éclater `config/permissions.php` en `config/permissions/{module}.php` |
| ARCH-004 | `CacheService` APCu/Redis pour dashboards |
| ARCH-005 | Route groups avec préfixe automatique dans `Router` |
| API-001 | Auth JWT pour endpoints `/api/*` |
| API-002 | Rate limiting middleware |
| API-003 | CORS configurable par module |
| MULTI-001 | Colonne `etablissement_id` (refactoring majeur — roadmap long terme) |

---

## 9. Risques

| Risque | Probabilité | Impact | Mitigation |
|--------|------------|--------|-----------|
| INF-C-001+002 non corrigés avant Phase 3 | Haute (déjà en production sur cette branche) | Critique — Finance V2 inaccessible | Corriger en priorité absolue avant tout développement Finance |
| `config/permissions.php` non mis à jour | Haute | Critique — 403 systématique | Ajouter les permissions Finance dès le blueprint |
| AuditService bugs (AN-C-003) — notes non auditées | Haute (crash at runtime) | Haute — perte de traçabilité réglementaire | Corriger namespace + static en même temps que INF-C |
| Croissance de `config/events.php` | Certaine (7 modules futurs) | Moyen — maintenabilité | Éclater en fichiers par module dès Phase 4 |
| Absence de cache analytics | Certaine (utilisation quotidienne) | Moyen — latence dashboard directeur | CacheService avant ouverture portail |
| Multi-établissement non prévu dans le schéma | Certaine (besoin futur) | Haute — refactoring massif | Décision architecturale à prendre maintenant |

---

## 10. Recommandations

### Priorité 1 — Pré-requis Phase Finance (à faire immédiatement)

1. **Corriger INF-C-001** : ajouter le chargement des routes modules dans `Application::run()` (30 min)
2. **Corriger INF-C-002** : étendre `Router::callHandler()` pour résoudre `App\Modules\` (45 min)
3. **Corriger INF-C-003** : ajouter les permissions V2 à `config/permissions.php` (2h)
4. **Corriger AN-C-003** : namespace AuditService + appels statiques dans 5 handlers (45 min)
5. **Mettre à jour `module.json` Académique** : version 2.8.0, enabled:true, liste complète (30 min)

**Total effort pré-requis : ~5h**

### Priorité 2 — Blueprint Finance (avant codage)

6. Définir les permissions Finance dans `config/permissions.php` dès le blueprint
7. Créer la migration `M_PERF_001` (6 index) en même temps que les migrations Finance
8. Décider du pattern `annees_scolaires` (AN-M-002) avant que Finance ne crée ses propres frais par année

### Priorité 3 — Architecture évolutive (Phase 4+)

9. Créer `CacheService` (APCu wrapper) — utilisé par Analytics + Finance dashboards
10. Éclater `config/events.php` en fichiers par module
11. Ajouter route groups avec préfixe automatique dans `Router`

---

## 11. Checklist avant Phase Finance

```
Infrastructure (BLOQUANT — NE PAS CODER FINANCE SANS CES 4 FIXES)
  [ ] INF-C-001 — Application charge les routes des modules activés
  [ ] INF-C-002 — Router dispatch vers App\Modules\ namespace
  [ ] INF-C-003 — Permissions V2 dans config/permissions.php
  [ ] AN-C-003  — Namespace AuditService corrigé (5 fichiers handlers)

Qualité Académique (avant activation)
  [ ] AN-C-001  — Validation inscription/année scolaire
  [ ] AN-C-002  — BulletinPolicy::canViewForParent()
  [ ] module.json Académique mis à jour (v2.8.0, enabled:true)

Finance — Blueprint
  [ ] Définir les 16+ permissions Finance dans config/permissions.php
  [ ] Définir les tables Finance (ne pas DROP les tables V1 comptabilite/paiements/depenses)
  [ ] Définir les événements Finance (PaiementCreated, FactureEmise, etc.)
  [ ] Décider du référentiel annees_scolaires (table centralisée)
  [ ] Module Finance dépend de : Scolarite (élèves) uniquement

Performance
  [ ] Migration M_PERF_001 créée et prête à l'exécution (6 index)

Documentation
  [ ] FINANCE_BLUEPRINT_V2.md produit avant tout codage
```

---

## 12. Score de maturité par module

```
Module          | Code | Tests | Infra | Total
────────────────|──────|───────|───────|──────
Core Framework  | 9/10 |  7/10 |  6/10 | 7.3/10  ← Router namespace bug
Shared Services | 8/10 |  6/10 |  8/10 | 7.3/10
Event System    | 9/10 |  7/10 |  9/10 | 8.3/10
RBAC            | 8/10 |  6/10 |  5/10 | 6.3/10  ← permissions V2 absentes
Scolarité V2    | 8/10 |  5/10 |  4/10 | 5.7/10  ← modules.php enabled:false
Académique V2   | 8/10 |  9/10 |  4/10 | 7.0/10  ← même infra + bugs handlers
────────────────|──────|───────|───────|──────
GLOBAL          | 8.3  |  6.7  |  6.0  | 7.0/10
```

---

*Rapport produit — ecole_app Phase 3.0 Global Architecture Readiness Review*
*Prochain rapport attendu après correction des 4 bloquants critiques : INFRASTRUCTURE_FIXED.md*
