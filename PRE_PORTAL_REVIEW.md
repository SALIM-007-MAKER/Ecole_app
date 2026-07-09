# PRE-PORTAL ARCHITECTURE REVIEW
## Revue Globale pré-développement Portails — SCOLARIS V2

**Date :** 2026-07-04  
**Modules audités :** 11 (Core + Scolarité + Académique + Finance + Vie Scolaire + RH + Documents + Communication + Bibliothèque + Inventaire + Rapports & BI)  
**Auditeur :** Claude Code (claude-sonnet-4-6)  
**Objectif :** Valider l'état global de l'architecture avant le développement des Portails (Élève, Parent, Enseignant)

---

## RÉSUMÉ EXÉCUTIF

| Dimension | Score | Statut |
|---|---|---|
| 1. Architecture Core Framework | 8.5/10 | ✅ GO |
| 2. Architecture Modules V2 | 7.0/10 | ⚠️ FIXES |
| 3. Base de Données & Migrations | 7.5/10 | ⚠️ FIXES |
| 4. Système d'Événements | 8.5/10 | ✅ GO |
| 5. Sécurité & RBAC | 7.5/10 | ⚠️ FIXES |
| 6. API Interne | 6.5/10 | ⚠️ FIXES |
| 7. Services Partagés | 8.5/10 | ✅ GO |
| 8. Intégration Cross-modules | 8.0/10 | ✅ GO |
| 9. Performance | 6.5/10 | ⚠️ FIXES |
| 10. Navigation & UX | 7.5/10 | ✅ GO |
| 11. Portails — Disponibilité des données | 6.5/10 | ⚠️ FIXES |
| 12. SaaS & Multi-établissements | 7.0/10 | ⚠️ FIXES |
| 13. Mobile & PWA | 7.0/10 | ⚠️ FIXES |
| 14. Coexistence V1/V2 | 9.0/10 | ✅ GO |
| 15. État Release Candidate | 6.0/10 | ⚠️ FIXES |
| 16. Dette Technique | 6.5/10 | ⚠️ FIXES |
| 17. Qualité de Code | 7.5/10 | ⚠️ FIXES |
| 18. Préparation Déploiement | 7.0/10 | ⚠️ FIXES |

**SCORE GLOBAL : 7.4/10**

---

## DÉCISION FINALE : GO WITH FIXES ⚠️

Le développement des Portails **peut commencer** après application des corrections critiques listées ci-dessous.

---

## PROBLÈMES CRITIQUES (bloquants)

### PA-C-001 — Syntaxe de routage cassée (4 modules)
**Gravité : CRITIQUE**  
**Modules affectés :** Documents, Communication, Bibliothèque, Rapports

**Diagnostic :**  
Le Router du framework est une classe à instance (`$router->get()`). Quatre modules utilisent la syntaxe statique `Router::get()` avec des handlers tableau `[Controller::class, 'method']`. Ces deux erreurs combinées causent une **Fatal Error au démarrage** lorsque le module est activé.

```php
// CASSÉ (4 modules) — Router::get() statique + handler tableau
use Core\Router;
Router::get('/v2/rapports', [DashboardController::class, 'direction']);
Router::post('/v2/rapports/kpis/snapshot', [KpiController::class, 'snapshot']);

// CORRECT (6 modules) — $router instance + handler string
$router->get('/v2/finance/frais', 'Finance\Controllers\FraisController@index');
$router->post('/v2/rh/employes/store', 'RH\Employes\Controllers\EmployeeController@store');
```

**Impact :**
- Documents, Communication, Bibliothèque, Rapports ne peuvent **pas être activés** en l'état
- Les portails ont besoin de Communication (messagerie) et Documents (bulletins/fichiers élève)
- Blocage total pour les portails si ces modules restent cassés

**Correction requise :** Réécrire les 4 fichiers `routes.php` avec syntaxe `$router->get(...)` et handlers string `'Module\Controllers\Ctrl@method'`. Aucune modification des classes controllers nécessaire.

**Fichiers à corriger :**
- `app/Modules/Rapports/routes.php`
- `app/Modules/Documents/routes.php`
- `app/Modules/Communication/routes.php`
- `app/Modules/Bibliotheque/routes.php`

---

### PA-C-002 — Propriété `$this->user` non déclarée dans Core\Controller
**Gravité : CRITIQUE (PHP 9) / MAJEUR (PHP 8.2)**  
**Fichiers affectés :** 44 contrôleurs V2

**Diagnostic :**  
`Core\Controller` ne déclare que `$this->view` et `$this->request`. La méthode correcte pour accéder à l'utilisateur courant est `$this->currentUser()`. Or 44 contrôleurs V2 utilisent `$this->user['etablissement_id'] ?? 1`.

```php
// Core\Controller — propriétés déclarées :
protected View    $view;
protected Request $request;
// PAS de $this->user

// Core\Controller — méthode disponible :
protected function currentUser(): ?array {
    return Session::getUser();
}

// PROBLÈME dans 44 contrôleurs :
$etab = $this->user['etablissement_id'] ?? 1;  // $this->user non déclaré
$user = $this->user;                            // retourne null en PHP 8.2
```

**Comportement actuel (PHP 8.2) :**
- PHP retourne `null` pour `$this->user` et émet un `E_WARNING: Undefined property`
- L'opérateur `??` récupère la valeur `1` (fallback hardcodé)
- L'application fonctionne mais pollue les logs d'erreur

**Comportement PHP 9 :**
- `Error: Attempt to read property "user" on null` → **Fatal Error**

**Impact portails :**
- Le fallback `?? 1` suppose toujours l'établissement 1 → critique pour un déploiement multi-établissements
- Les portails liraient toutes les données de l'établissement 1 quel que soit l'utilisateur

**Correction :** Remplacer `$this->user['...'] ?? fallback` par `($this->currentUser()['...'] ?? fallback)` dans 44 fichiers.

---

## PROBLÈMES MAJEURS

### PA-M-001 — Modules Scolarité et Académique désactivés (DT-G-001)
**Gravité : MAJEURE**

Les portails Élève et Parent ont besoin des données suivantes :
- Notes, moyennes, bulletins → **Module Académique** (disabled)
- Inscriptions, classe affectée → **Module Scolarité** (disabled)

Ces deux modules ont une **syntaxe de routage correcte** (`$router->get()` avec handlers string) — ils peuvent être activés. La désactivation est héritée de la décision Enterprise Review (DT-G-001) suite aux 3 critiques AN-C-001/002/003 du module Académique.

**Plan suggéré :** Activer Scolarité et Académique dans `config/modules.php` + exécuter leurs migrations SQL avant de développer les portails qui lisent leurs données.

### PA-M-002 — Listeners toujours chargés pour modules désactivés
**Gravité : MAJEURE**

`config/events.php` instancie les listeners de **tous** les modules à chaque requête, y compris ceux désactivés :

```php
// events.php — chargé à chaque boot, quel que soit l'état du module :
\App\Modules\Bibliotheque\Events\EmpruntEnRetard::class => [
    new \App\Modules\Bibliotheque\Listeners\AuditListener(),  // module disabled
    new \App\Modules\Bibliotheque\Listeners\BiblioNotificationHandler(),
],
```

**Impact :**
- Coût mémoire/CPU au démarrage pour ~60 objets listener inutiles
- Un bug de syntaxe dans un listener de module désactivé crashe **toute l'application**
- Exemple : `new \App\Modules\Bibliotheque\Listeners\FinanceIntegrationListener()` est instancié à chaque paiement (PaymentCompleted)

**Correction suggérée :** Conditionner l'enregistrement des listeners à l'état `enabled` du module dans `config/events.php`.

### PA-M-003 — Aucune couche API authentifiée pour les portails
**Gravité : MAJEURE**

Les portails Élève/Parent sur mobile ou SPA auront besoin d'endpoints JSON authentifiés. L'état actuel :
- API V1 existants : `/api/eleves`, `/api/frais-eleve`, `/api/notifications/*` (non authentifiés, basés sur session)
- Aucun endpoint `/api/v2/` avec authentification token/JWT
- `ApiAnalyticsController` existe dans Rapports mais utilise session

**Impact :** Les portails mobiles ne peuvent pas consommer des données sans session web.

### PA-M-004 — Tailwind CSS via CDN en production
**Gravité : MAJEURE**

Le layout principal charge `https://cdn.tailwindcss.com` à chaque page :
```html
<script src="https://cdn.tailwindcss.com"></script>
```
- Dépendance réseau externe obligatoire (incompatible PWA offline)
- ~100KB+ de CSS non-optimisé chargé à chaque requête
- La CSP existante autorise `cdn.tailwindcss.com` (élargissement de surface)

---

## 1. ARCHITECTURE CORE FRAMEWORK — 8.5/10

**Router (`Core\Router`) :**  
Instance-based avec méthodes `get()`, `post()`, `put()`, `delete()`. `callHandler()` attend le format string `'Module\Controllers\Ctrl@method'`. Préfixe automatique `App\Modules\` ou `App\Controllers\` selon présence de `\`. Résolution correcte pour 6/10 modules V2 et tous les modules V1.

**EventDispatcher (`Core\EventDispatcher`) :**  
Synchrone, isolation par try/catch individuel. `listen()` + `dispatch()` statiques. 181+ mappings en production. Comportement correct : une exception dans un listener n'arrête pas les autres.

**Controller (`Core\Controller`) :**  
Abstract. Propriétés : `$view`, `$request`. Méthodes : `currentUser()`, `requirePermission()`, `requireRole()`, `verifyCsrf()`, `can()`, `validate()`, `redirect()`, `json()`. Headers sécurité automatiques. Aucun `$this->user` — voir PA-C-002.

**Session (`Core\Session`) :**  
`$_SESSION['_auth_user']`, régénération toutes les 300s, CSRF token, flash messages. Stable.

**View (`Core\View`) :**  
Notation `Module::path/to/view` pour modules, `path/to/view` pour V1. Layouts : `main`, `auth`, `print`. `View::json()` disponible pour API.

---

## 2. ARCHITECTURE MODULES V2 — 7.0/10

| Module | Routes | Statut | Score |
|---|---|---|---|
| Scolarité | `$router->get()` ✅ | disabled | 8/10 |
| Académique | `$router->get()` ✅ | disabled | 8/10 |
| Finance | `$router->get()` ✅ | enabled | 9/10 |
| Vie Scolaire | `$router->get()` ✅ | enabled | 9/10 |
| RH | `$router->get()` ✅ | enabled | 9.3/10 |
| Documents | `Router::get()` ❌ | disabled | 5/10 |
| Communication | `Router::get()` ❌ | disabled | 6/10 |
| Bibliothèque | `Router::get()` ❌ | disabled | 6/10 |
| Inventaire | `$router->get()` ✅ | disabled | 8.4/10 |
| Rapports & BI | `Router::get()` ❌ | disabled | 8.5/10* |

*Score après corrections Phase 11.3 appliquées.

**Points positifs :**
- Pattern DTO + Repository + Service + Events respecté dans 10/10 modules
- `declare(strict_types=1)` sur tous les fichiers PHP
- Controllers thin — logique métier dans les Services
- PSR-4 autoloader `App\Modules\` fonctionnel

---

## 3. BASE DE DONNÉES & MIGRATIONS — 7.5/10

**Inventaire tables :**
- ~147 tables au total (35 tables core V2 + 112 tables modules)
- `etablissement_id` présent sur toutes les tables V2
- `deleted_at` pour soft-delete
- `INSERT ... ON DUPLICATE KEY UPDATE` pour upserts atomiques

**État migrations :**

| Module | Migration | État |
|---|---|---|
| Foundation (35 tables) | DATABASE_V2.md | Préparée |
| RBAC (4 tables) | RBAC_V2.md | Préparée |
| Paramètres (3 tables) | PARAMETRES_V2.md | Préparée |
| Shared Services (1 table audit_logs) | SHARED_SERVICES.md | Préparée |
| Scolarité | scolarite_001.sql | Prête |
| Académique | academique_001.sql | Prête |
| Finance | finance_001..008.sql | Prête |
| Vie Scolaire | vs_001..007.sql | Prête |
| RH | rh_001..011.sql | Prête |
| Documents | documents_001.sql | Prête |
| Communication | communication_001.sql | Prête |
| Bibliothèque | bibliotheque_001.sql | Prête |
| Inventaire | inventaire_001.sql | Prête |
| Rapports & BI | bi_001_rapports.sql | Prête |

**Risque :** Aucune gestion de migration automatisée — scripts SQL manuels uniquement. Pas de rollback formalisé.

---

## 4. SYSTÈME D'ÉVÉNEMENTS — 8.5/10

**`config/events.php` :** 1 365 lignes, ~181 mappings event→listeners.

**Statistiques :**
- Modules avec events : 11/11
- Events V1 globaux : 7
- Events Scolarité : 17
- Events Académique : 23
- Events Finance : 18
- Events Vie Scolaire : 28
- Events RH : 24
- Events Documents : 16
- Events Communication : 12
- Events Bibliothèque : 18
- Events Inventaire : 20
- Events Rapports & BI : 6

**Pattern respecté :**
- Listeners : AuditListener, NotificationListener, StatisticsListener, CrossModuleListener
- `CrossModuleListener` : 14 événements inter-modules cross-wired
- AuditHandler systématiquement en 1er (conformité audit)

**Problème :** PA-M-002 — listeners de modules désactivés toujours instanciés.

---

## 5. SÉCURITÉ & RBAC — 7.5/10

**RBAC :**
- 85 permissions (7 actions × N entités)
- 4 tables : `roles`, `permissions`, `role_permissions`, `user_roles`
- `requirePermission()` dans tous les controllers V2
- `config/permissions.php` : matrice complète admin/directeur/enseignant/comptable/parent/eleve

**Sécurité applicative :**
- CSRF : `verifyCsrf()` sur toutes les mutations POST
- SQL : PDO prepared statements systématiques dans les Repositories
- XSS : `htmlspecialchars()` dans layout, `$this->safe()` dans Controller
- Headers : X-Frame-Options, X-Content-Type-Options, CSP, Referrer-Policy
- AuditService : masquage automatique password/token/api_key/secret

**Problèmes :**
- PA-C-002 : `$this->user['etablissement_id'] ?? 1` — fallback hardcodé contourne le tenant
- API V1 `/api/*` sans authentification token (session uniquement)
- CSP autorise `cdn.tailwindcss.com` + `unsafe-inline` pour scripts

---

## 6. API INTERNE — 6.5/10

**Endpoints API disponibles :**

| Endpoint | Module | Auth | Format |
|---|---|---|---|
| `GET /api/eleves` | V1 | Session | JSON |
| `GET /api/eleves/{id}` | V1 | Session | JSON |
| `GET /api/frais-eleve` | V1 | Session | JSON |
| `GET /api/notifications/unread-count` | V1 | Session | JSON |
| `GET /api/notifications/recent` | V1 | Session | JSON |
| `GET /v2/rapports/api/analytics` | V2 Rapports | Session | JSON |

**Manques critiques pour portails :**
- Aucun `/api/v2/` avec authentification token/Bearer
- Pas de versioning API
- Pas d'endpoint pour notes, bulletins, absences, emploi du temps en JSON V2
- Pas de rate limiting
- Pas de documentation OpenAPI/Swagger

---

## 7. SERVICES PARTAGÉS — 8.5/10

| Service | Fichier | État |
|---|---|---|
| AuditService | `app/Services/AuditService.php` | ✅ Complet |
| EmailService | `app/Services/EmailService.php` | ✅ Présent |
| NotificationService | `app/Services/NotificationService.php` | ✅ Présent |
| SmsService | `app/Services/SmsService.php` | ✅ Présent |
| UploadService | `app/Services/UploadService.php` | ✅ Présent |
| KPIEngine | `app/Shared/Analytics/KPIEngine.php` | ✅ Complet |
| ReportEngine | `app/Shared/Analytics/ReportEngine.php` | ✅ Complet |
| ChartEngine | `app/Shared/Analytics/ChartEngine.php` | ✅ Complet |
| ExportEngine | `app/Shared/Analytics/ExportEngine.php` | ✅ Complet |
| DashboardBuilder | `app/Shared/Analytics/DashboardBuilder.php` | ✅ Complet |

**AuditService :** Instance-based. Méthodes `log()`, `logCreate()`, `logUpdate()`, `logDelete()`, `logLogin()`, `diff()`. Masquage automatique des données sensibles. Dégradation silencieuse si table absente. Conforme.

---

## 8. INTÉGRATION CROSS-MODULES — 8.0/10

**Dépendances confirmées :**

```
Bibliothèque ←── Finance (FinanceIntegrationListener sur PaymentCompleted)
Inventaire   ←── Finance (InvFinanceIntegrationListener sur CommandeValidee)
Inventaire   ←── RH (InvRHIntegrationListener sur AffectationCreee)
Inventaire   ←── Documents (InvDocumentsIntegrationListener sur CommandeValidee)
Discipline   ←── Présences (DisciplineIntegrationHandler sur StudentAbsent/Late)
Communication ←── [tous modules via CrossModuleListener sur 14 events]
Rapports     ←── [tous modules via read-only SQL — jamais d'écriture directe]
```

**Points positifs :**
- Communication événementielle stricte : les services ne s'appellent pas entre eux
- `CrossModuleListener` centralise les effets de bord inter-modules
- Module Rapports : lecture seule, ne modifie jamais les tables des autres modules

**Point d'attention :**
- `FinanceIntegrationListener` de Bibliothèque est instancié même si Bibliothèque est désactivée (PA-M-002)

---

## 9. PERFORMANCE — 6.5/10

**Points positifs :**
- PDO prepared statements : éliminent les N+1 naïfs dans les Repositories
- Pagination systématique dans les listings
- `INSERT ON DUPLICATE KEY UPDATE` pour snapshots KPI (upsert atomique)
- Service Worker PWA avec cache stratégique

**Problèmes :**
- Tailwind CDN : ~300KB de CSS chargé depuis CDN externe à chaque page (PA-M-004)
- Aucun OPcache configuré explicitement
- Aucune mise en cache applicative (Redis/Memcached) pour dashboards/analytics
- `config/events.php` instancie ~60 objets listener à chaque requête (PA-M-002)
- Pas d'indices de base de données documentés pour les colonnes filtrées fréquemment

---

## 10. NAVIGATION & UX — 7.5/10

**Layout principal (`app/Views/layouts/main.php`) :**
- Routage dashboard selon rôle :
  - `parent` → `/parent/dashboard`
  - `eleve` → `/eleve/dashboard`
  - autres → `/dashboard`
- Navigation sidebar avec `hasPerm()` + `navCls()` helpers
- Tailwind CSS CDN + Lucide Icons + Inter font
- Design violet/slate cohérent sur 78 vues V2

**Portails V1 existants :**
- `/eleve/dashboard`, `/eleve/notes`, `/eleve/bulletin`, `/eleve/emploi-du-temps`, `/eleve/profil`
- `/parent/dashboard`, `/parent/notes`, `/parent/bulletin`, `/parent/absences`, `/parent/paiements`
- `EspaceEleveController.php` existe en V1

**Portails V2 à créer :**
- Aucun module Portal V2 n'existe encore
- Les V1 portals sont des vues HTML classiques, sans architecture V2

---

## 11. DISPONIBILITÉ DES DONNÉES POUR LES PORTAILS — 6.5/10

**Données requises par chaque portail :**

| Donnée | Source | Disponibilité |
|---|---|---|
| Identité élève | Scolarité V2 | ❌ Module disabled |
| Classe, inscription | Scolarité V2 | ❌ Module disabled |
| Notes, évaluations | Académique V2 | ❌ Module disabled |
| Bulletins, moyennes | Académique V2 | ❌ Module disabled |
| Classement | Académique V2 | ❌ Module disabled |
| Absences, retards | Vie Scolaire V2 | ✅ Module enabled |
| Emploi du temps | Vie Scolaire V2 | ✅ Module enabled |
| Factures, paiements | Finance V2 | ✅ Module enabled |
| Messagerie | Communication V2 | ❌ Routes cassées |
| Documents scolaires | Documents V2 | ❌ Routes cassées |
| Récompenses/discipline | Vie Scolaire V2 | ✅ Module enabled |

**Données disponibles aujourd'hui pour portails :** 3/11 ✅  
**Données bloquées par PA-C-001 :** 2/11 ❌  
**Données bloquées par DT-G-001 :** 4/11 ❌  
**Données disponibles en V1 :** partiellement (sans architecture V2)

---

## 12. SAAS & MULTI-ÉTABLISSEMENTS — 7.0/10

**Points positifs :**
- `etablissement_id` sur toutes les tables V2
- Filtrage systématique par `etablissement_id` dans les Repositories
- Soft delete partout (`deleted_at`)

**Problèmes :**
- `$this->user['etablissement_id'] ?? 1` : fallback hardcodé `1` — en multi-tenant, toutes les requêtes d'un utilisateur sans session tombent sur l'établissement 1
- Aucun middleware de scoping tenant (pas de tenant resolver centralisé)
- Paramètres d'établissement en `config/parametres.php` — pas de table `etablissements` avec isolation complète

---

## 13. MOBILE & PWA — 7.0/10

**Implémenté :**
- Service Worker v2.1 avec cache stratégique
- Manifest PWA avec 10 icônes
- Push notifications VAPID
- Offline fallback

**Problèmes :**
- Tailwind CDN ne peut pas être mis en cache offline (CDN externe bloqué par CSP en offline)
- `https://fonts.googleapis.com` dans le layout — dépendance externe bloquée offline
- Aucune API JSON V2 authentifiée pour les clients mobiles (PA-M-003)

---

## 14. COEXISTENCE V1/V2 — 9.0/10

**Points forts :**
- Toutes les routes V2 préfixées `/v2/` — zéro collision avec V1
- Toggle `enabled: bool` dans `config/modules.php` — activation module par module
- Application bootstrap : V1 routes d'abord, puis V2 modules activés
- V1 controllers non modifiés, V1 tables non droppées
- Soft delete V2 / données V1 coexistantes

**Minor :** Listeners de modules V2 enregistrés même quand modules disabled (PA-M-002).

---

## 15. ÉTAT RELEASE CANDIDATE — 6.0/10

| Module | Enabled | Route Syntax | Phase Review | Score |
|---|---|---|---|---|
| Finance | ✅ | ✅ | 7.6/10 | Production-ready |
| Vie Scolaire | ✅ | ✅ | GO WITH FIXES | Production-ready |
| RH | ✅ | ✅ | 9.3/10 GO | Production-ready |
| Scolarité | ❌ | ✅ | 8.5/10 (après fixes) | Ready to enable |
| Académique | ❌ | ✅ | 8.2/10 (après fixes) | Ready to enable |
| Inventaire | ❌ | ✅ | 8.4/10 GO | Ready to enable |
| Rapports & BI | ❌ | ❌ | 8.5/10 GO* | Route fix needed |
| Documents | ❌ | ❌ | 8.5/10 GO | Route fix needed |
| Communication | ❌ | ❌ | GO WITH FIXES | Route fix needed |
| Bibliothèque | ❌ | ❌ | 8.7/10 GO | Route fix needed |

*Corrections Phase 11.3 appliquées.

---

## 16. DETTE TECHNIQUE ACCUMULÉE — 6.5/10

### Dettes critiques actives

| ID | Module | Description | Impact |
|---|---|---|---|
| PA-C-001 | 4 modules | Routes statiques cassées | Bloquant activation |
| PA-C-002 | 44 controllers | `$this->user` non déclaré | PHP 9 fatal error |
| DT-G-001 | Scolarité+Académique | Modules disabled (AN-C-001/002/003) | Portails bloqués |

### Dettes héritées non résolues

| ID | Module | Description |
|---|---|---|
| DT-M1 | Scolarité | `MatiereAssigned/Removed` jamais déclenchés |
| AN-C-001/002/003 | Académique | 3 critiques système intégration |
| FN-C-001/002/003 | Finance | 3 critiques finance |
| DT-D-001..004 | Documents | 4 dettes documents |
| DT-R-001..007 | Rapports | 7 dettes BI/ML |

---

## 17. QUALITÉ DE CODE — 7.5/10

**Positif :**
- `declare(strict_types=1)` sur tous les fichiers PHP
- Pattern DTO readonly + `fromRequest()` cohérent
- Services n'écrivent jamais directement depuis les controllers (règle respectée)
- AuditService diff-aware (ne log que les champs changés)
- Repositories isolés : zéro SQL hors Repository

**Négatif :**
- 44 controllers utilisent `$this->user` (propriété fantôme)
- `AuditService::log()` : paramètres `int $entiteId = null` (nullable implicite, PHP 8.4 warning) — partiellement corrigé dans Rapports seulement
- Aucun test unitaire observable pour les modules V2 (tests référencés dans FREEZE docs mais non exécutés via CI)
- Manque d'interfaces pour les Repositories (couplage service→classe concrète)

---

## 18. PRÉPARATION DÉPLOIEMENT — 7.0/10

**Disponible :**
- `config/modules.php` : toggle par module
- Migrations SQL préparées pour tous les modules
- `AuditService` : dégradation silencieuse si table manquante
- `.env` ou configuration séparée supposée (non vérifiée dans cet audit)

**Manquant :**
- Aucune gestion de migration automatisée (Phinx/Flyway)
- Aucun rollback formalisé
- Tailwind CDN → doit compiler avant mise en prod
- Aucune configuration OPcache documentée
- Aucun health-check endpoint `/api/health`
- Logs applicatifs : `error_log()` uniquement — pas de PSR-3 Logger centralisé

---

## PLAN D'ACTION AVANT DÉVELOPPEMENT PORTAILS

### Priorité 1 — BLOQUANT (avant tout développement portail)

**Fix PA-C-001 — Routes des 4 modules cassés**  
Réécrire `routes.php` de : Documents, Communication, Bibliothèque, Rapports  
Pattern cible : `$router->get('/v2/module/path', 'Module\Controllers\Ctrl@action');`  
Estimation : 4 fichiers × ~30 min = 2h

**Fix PA-C-002 — `$this->user` → `currentUser()`**  
Remplacer dans 44 controllers :
```php
// Avant
$etab = $this->user['etablissement_id'] ?? 1;
$userId = $this->user['id'] ?? 0;

// Après
$user = $this->currentUser();
$etab = $user['etablissement_id'] ?? 1;
$userId = $user['id'] ?? 0;
```
Estimation : script de remplacement semi-automatique, 1-2h

### Priorité 2 — REQUIS pour données portails

**Activer Scolarité + Académique**
- `config/modules.php` : `scolarite.enabled = true`, `academique.enabled = true`
- Exécuter migrations SQL correspondantes
- Résoudre DT-G-001 (AN-C-001/002/003) préalablement

**Activer Communication + Documents** (après fix PA-C-001)
- Communication : messagerie portail
- Documents : bulletins PDF, fichiers élève

### Priorité 3 — RECOMMANDÉ avant production portails

**Compiler Tailwind CSS**  
Remplacer CDN par CSS compilé pour compatibilité PWA offline.

**API JSON authentifiée**  
Créer `/api/v2/` avec middleware token pour clients mobiles portails.

**Scoping event listeners**  
Conditionner enregistrement des listeners à `modules.php` enabled state.

---

## RÉSUMÉ DES FINDINGS

| ID | Gravité | Fichier(s) | Description | Statut |
|---|---|---|---|---|
| PA-C-001 | CRITIQUE | 4 × routes.php | Syntaxe `Router::get()` statique + handler tableau | À corriger |
| PA-C-002 | CRITIQUE | 44 × *Controller.php | `$this->user` non déclaré — PHP 9 fatal | À corriger |
| PA-M-001 | MAJEUR | config/modules.php | Scolarité + Académique désactivés | À activer |
| PA-M-002 | MAJEUR | config/events.php | Listeners disabled modules toujours instanciés | À conditionner |
| PA-M-003 | MAJEUR | (non existant) | Aucune API JSON V2 authentifiée pour portails mobile | À créer |
| PA-M-004 | MAJEUR | layouts/main.php | Tailwind CDN — incompatible offline/production | À compiler |
| PA-mi-001 | MINEUR | AuditService.php | `int $entiteId = null` implicite (PHP 8.4) | À normaliser |
| PA-mi-002 | MINEUR | config/events.php | Aucun tri par ordre de priorité explicite pour listeners | Documentaire |

---

## DÉCISION FINALE

```
╔══════════════════════════════════════════════════════════════╗
║                                                              ║
║    VERDICT : GO WITH FIXES                   Score: 7.4/10   ║
║                                                              ║
║    Le développement des Portails peut commencer              ║
║    APRÈS application de PA-C-001 et PA-C-002.                ║
║                                                              ║
║    Conditions GO :                                           ║
║    ✅ [C1] Fix routes 4 modules (PA-C-001) — ~2h             ║
║    ✅ [C2] Fix $this->user 44 controllers (PA-C-002) — ~2h   ║
║                                                              ║
║    Non-bloquant (parallèle au développement) :               ║
║    ⚡ Activer Scolarité + Académique (PA-M-001)               ║
║    ⚡ Conditionner listeners disabled modules (PA-M-002)      ║
║    ⚡ Compiler Tailwind CSS (PA-M-004)                        ║
║    ⚡ Concevoir API V2 JSON pour portails mobile (PA-M-003)   ║
║                                                              ║
╚══════════════════════════════════════════════════════════════╝
```

---

*PRE_PORTAL_REVIEW.md — Architecture Review pre-Portal — SCOLARIS V2 — 2026-07-04*
