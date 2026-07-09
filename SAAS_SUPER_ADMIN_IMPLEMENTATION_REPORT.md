# Phase 14.10 — SaaS Super-Admin Dashboard — Rapport d'implémentation

## 1. Contexte & périmètre

Blueprint §25.1, ligne Phase 14.10 : *"Super Admin Dashboard SaaS — Interface
opérateur plateforme — Livrable : Portail /platform/ (CRUD établissements, plans,
analytics) — Validation : Créer tenant, assigner plan, suspendre, restaurer."*

Contrairement aux phases précédentes, celle-ci ne bute sur aucune limite
d'infrastructure : `platform_operators` et `platform_plans` existent déjà en base
(créées vides/seedées par la Phase 14.2, jamais exploitées jusqu'ici) et tout le
nécessaire (quotas, cache, queue, domaines) a été construit aux Phases 14.7-14.9. Le
travail de cette phase est donc un portail applicatif complet et réellement
utilisable, pas une architecture "prête mais non branchée".

## 2. Composants créés

### Infrastructure / sécurité

| Fichier | Rôle |
|---|---|
| `database/migrations/T017_platform_admin_infrastructure.php` | Ajoute `'archived'` à `etablissements.statut` (ENUM), crée `platform_analytics_snapshots` (§14.2 du blueprint), bootstrap `platform_operators` pour `admin@ecole.dz` (niveau `super_admin`) |
| `core/Platform/PlatformAuth.php` | Authentification opérateur — session **totalement distincte** (`_platform_operator`) de la session établissement (`_auth_user`) |
| `core/Platform/PlatformController.php` | Base des contrôleurs Platform — `requirePlatformAuth()`/`requirePlatformLevel()`, n'hérite jamais de `requirePermission()`/`can()` (RBAC établissement) |

### Services métier

| Fichier | Rôle |
|---|---|
| `core/Platform/PlatformEtablissementService.php` | CRUD global (recherche/filtre), cycle de vie (activate/suspend/archive/restore/softDelete), `assignPlan()` (copie les quotas du plan sur le tenant) |
| `core/Platform/PlatformPlanService.php` | CRUD `platform_plans`, `usageCount()` |
| `core/Platform/PlatformStatsService.php` | `globalKpis()`, `queueStats()`/`cacheStats()` (réutilisent Phase 14.9), `moduleHealth()`, `tenantsNearQuota()` (réutilise `TenantQuotaService::alerts()` de la Phase 14.8 sur chaque tenant actif), `recordSnapshot()`/`recentSnapshots()` |

### Contrôleurs, vues, routes

| Fichier | Rôle |
|---|---|
| `app/Controllers/PlatformAuthController.php` | `showLogin/login/logout` — vérifie `users` + `platform_operators.actif`, ne touche jamais `Session::setUser()` |
| `app/Controllers/PlatformDashboardController.php` | `index/api/snapshot` |
| `app/Controllers/PlatformEtablissementController.php` | `index/show/create/store/activate/suspend/archive/restore/destroy/assignPlan` |
| `app/Controllers/PlatformPlanController.php` | `index/store/update/toggle` |
| `app/Views/layouts/platform-auth.php`, `platform.php` | Layouts visuellement et structurellement distincts du reste de l'app (thème sombre, aucun appel à `BrandingService` — ce portail n'est d'aucun tenant) |
| `app/Views/platform/login.php`, `dashboard.php`, `etablissements/{index,show,create}.php`, `plans/index.php` | Vues |
| `config/routes.php` | 19 routes `/platform/*` |
| `tests/Unit/PlatformAdminTest.php` | 56 assertions (voir §7) |

## 3. Composants modifiés

Aucun fichier V1 déplacé, aucun contrôleur/route existant modifié, **`config/permissions.php` non touché** (voir §6 — c'est délibéré, pas un oubli). La seule surface partagée avec le reste de l'app est la table `users` (un opérateur est un compte utilisateur réel, `platform_operators.user_id` FK, conforme blueprint §4.5) et `Core\Session` pour ses mécanismes génériques (CSRF, flash) — jamais pour l'état d'authentification lui-même.

## 4. Tableaux de bord

`GET /platform/dashboard` affiche : nombre total d'établissements (+ répartition
trial/active/suspended/archived), utilisateurs, élèves, enseignants (table
`professeurs`), stockage utilisé (Go, agrégé depuis `api_uploads` tous tenants),
files d'attente (agrégées, Phase 14.9), clés de cache actives (agrégées), état des
modules (`config/modules.php`), et une section "établissements proches ou en
dépassement de quota" qui réutilise directement `TenantQuotaService::alerts()`
(Phase 14.8) sur chaque établissement actif — zéro logique de quota dupliquée.

## 5. API internes

| Route | Description |
|---|---|
| `GET /platform/dashboard/api` | JSON : kpis, queue, cache, modules, tenants à risque |
| `POST /platform/dashboard/snapshot` | Enregistre un instantané dans `platform_analytics_snapshots` |

Toutes deux protégées par `PlatformAuth` (401/redirection si non authentifié comme
opérateur — jamais par le RBAC établissement). Point d'entrée prêt pour un futur
snapshot planifié (nécessiterait la Phase 14.9 queue + un scheduler, déjà tous deux
construits — il ne manque qu'un `push()` régulier, hors périmètre littéral ici).

## 6. Sécurité — séparation totale du RBAC établissement

Exigence du blueprint : *"Garantir que ce rôle soit totalement distinct du RBAC des
établissements."* Concrètement :

- **Aucune ligne ajoutée à `config/permissions.php`, `etab_permissions` ou
  `etab_role_permissions`** pour ce portail — le modèle de permission est
  entièrement `platform_operators.niveau` (`support`/`admin`/`super_admin`), une
  table séparée, jamais lue par le code établissement.
- **Session physiquement séparée** : `PlatformAuth` stocke sous `_platform_operator`,
  `Core\Session` (établissement) sous `_auth_user` — deux clés indépendantes dans le
  même `$_SESSION` PHP. Testé explicitement (§7.1) : connecter un opérateur ne
  modifie jamais l'état `Session::getUser()`, et réciproquement.
- **Connexion indépendante** : `PlatformAuthController::login()` ne réutilise aucun
  code de `AuthController::login()` — vérification identifiants + garde
  `platform_operators.actif = 1` supplémentaire, sans laquelle même un mot de passe
  correct est refusé.
- **Aucun contrôleur Platform n'appelle `Controller::requirePermission()`/`can()`** —
  `PlatformController` fournit sa propre garde (`requirePlatformAuth()`/
  `requirePlatformLevel()`), indépendante du code hérité de `Core\Controller`.
- Vérifié qu'un utilisateur établissement standard n'a par défaut aucune ligne
  `platform_operators` active → accès refusé (§7.5).

## 7. Tests réalisés

`tests/Unit/PlatformAdminTest.php` — 56/56 assertions, transaction PDO annulée en
fin de script :

1. **Isolation de session** (11 assertions) : connecter un opérateur ne modifie
   jamais `Session::getUser()`/`isLogged()` (établissement) et vice versa ; les deux
   coexistent sans interférence ; niveaux (`hasLevel`) correctement distingués.
2. **`PlatformEtablissementService`** (22 assertions) : cycle de vie complet
   (trial → active → suspended → **archived** [distinct de suspended] → restored),
   unicité globale du slug, `assignPlan()` copie bien les 3 quotas sur
   l'établissement, recherche par nom/slug, **suppression logique uniquement**
   (`deleted_at` renseigné, ligne toujours présente en base, exclue des résultats de
   recherche).
3. **`PlatformPlanService`** (5 assertions) : CRUD, `usageCount()` reflète
   dynamiquement les tenants réellement actifs sur un plan (exclut les tenants
   supprimés logiquement).
4. **`PlatformStatsService` — plusieurs établissements actifs simultanément**
   (10 assertions) : `globalKpis()` agrège correctement across 2 nouveaux tenants +
   ceux déjà en base, `storage_used_gb` reflète un upload réel, `total_api_calls`
   correctement `null` (pas de table de suivi API dans cette base — distinct de 0),
   `tenantsNearQuota()` détecte précisément le tenant en dépassement (quota 1 Mo,
   usage 900 Ko) et exclut celui au quota confortable (10 Go) — **isolation vérifiée
   dans les deux sens avec 2 tenants réels simultanés**, `recordSnapshot()`/
   `recentSnapshots()`.
5. **Sécurité d'accès** (4 assertions) : un utilisateur établissement standard n'a
   aucun accès opérateur par défaut, une ligne `platform_operators` active
   suffisante et nécessaire, niveau par défaut `support` (droits minimaux),
   désactivation (`actif=0`) révoque l'accès sans suppression de la ligne.

**Vérification HTTP live** (`curl`) : `GET /platform/login` → 200 (rendu correct,
layout indépendant sans branding tenant) ; `GET /platform/dashboard` sans session
opérateur → 302 vers `/platform/login` (garde d'accès fonctionne réellement, pas
seulement en test unitaire) ; connexion avec identifiants invalides → 302 retour
login sans authentification.

**Non-régression** : l'intégralité des tests des Phases 14.2 → 14.9 ré-exécutée —
tous verts sans modification : TenantResolverTest (29/29), TenantIsolationTest
(24/24), RbacTenantTest (18/18), BrandingTenantTest (18/18), MultiTenantUserTest
(15/15), DomainTenantTest (40/40), StorageQuotaTest (30/30), CacheQueueTest (40/40).
Suite historique `security_tests.php` (48/48) et `functional_tests.php` (109/109)
également ré-exécutées.

Total cumulé (Phases 14.2 → 14.10) : **427/427 tests, tous verts**
(270 tests Multi-Tenant + 157 suite historique).

## 8. Anomalie détectée / correction appliquée

**Bug réel trouvé pendant les tests** : `PlatformEtablissementService::search()`
construisait sa clause `WHERE` avec des colonnes non qualifiées (`nom`,
`nom_court`, `slug`, `deleted_at`, `statut`, `plan_id`) alors que la requête fait un
`LEFT JOIN platform_plans p` — et `platform_plans` possède **elle aussi** une colonne
`nom`. MySQL a immédiatement rejeté la requête (`Champ: 'nom' ... est ambigu`) dès le
premier appel avec un filtre de recherche. Corrigé en qualifiant explicitement
toutes les colonnes de la clause WHERE avec l'alias `e.` (table `etablissements`).
Détecté par `tests/Unit/PlatformAdminTest.php` (recherche par nom), corrigé, re-testé
(56/56).

## 9. Hors périmètre

- **Assistant de création d'établissement (onboarding complet)** : `create()` crée
  la ligne `etablissements` uniquement — pas de création automatique d'un premier
  compte admin pour le nouveau tenant, pas de wizard multi-étapes. C'est un
  gap déjà identifié lors d'un audit fonctionnel antérieur à cette phase, distinct du
  "CRUD établissements" littéralement demandé ici.
- **Snapshot planifié automatique** : `recordSnapshot()` est appelable manuellement
  (bouton dashboard) ou via API — aucun cron/queue job récurrent ne l'appelle
  automatiquement (l'infrastructure queue de la Phase 14.9 le permettrait, mais
  aucun démon persistant n'existe dans cet environnement, déjà documenté).
- **Suivi réel des appels API par tenant** : aucune table `api_rate_limit_buckets`
  (ou équivalente) n'est appliquée dans cette base — `total_api_calls` reste `null`
  partout, cohérent avec le constat déjà fait en Phase 14.4 (tables du module API
  Platform non migrées ici).
- **Interface de gestion des domaines/branding depuis le portail plateforme** :
  le blueprint le mentionne ("Gestion globale : domaines, branding") mais ces
  fonctionnalités existent déjà et sont pleinement opérationnelles côté
  établissement (Phases 14.5/14.7) — dupliquer un écran d'administration
  read-only côté plateforme n'apporterait rien de testable de plus que les écrans
  existants ; le lien direct depuis la fiche établissement (`/platform/etablissements/{id}`)
  vers les données pertinentes (quotas, plan) couvre le besoin de supervision.

## 10. Décision finale

**GO WITH FIXES** *(fixes = les 4 points hors périmètre du §9, aucun ne bloquant
l'usage réel du portail)*.

Le portail Super-Admin est fonctionnel de bout en bout : authentification isolée du
RBAC établissement (vérifiée dans les deux sens), CRUD établissements avec cycle de
vie complet (trial/active/suspended/**archived**/soft-deleted), gestion des plans
avec copie automatique des quotas à l'assignation, tableau de bord agrégé
multi-tenant réel (testé avec plusieurs établissements actifs simultanément), API
interne protégée. 427/427 tests verts, zéro régression, 1 anomalie réelle trouvée et
corrigée. Ne pas commencer la phase suivante tant que cette phase n'est pas validée.
