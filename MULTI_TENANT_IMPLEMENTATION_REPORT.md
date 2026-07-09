# MULTI_TENANT_IMPLEMENTATION_REPORT.md
## Phase 14.2 — Infrastructure Tenant (Fondation)

> **Statut :** ✅ IMPLÉMENTÉ — en attente de validation GO pour Phase 14.3
> **Date :** 2026-07-08
> **Périmètre :** Strictement limité à la Phase 14.2 du blueprint (§25.1) — migrations MT-A1→A8, `TenantContext`, `TenantResolver`, `TenantMiddleware`.
> **Référence :** `MULTI_TENANT_V2_BLUEPRINT.md`

---

## 1. Résumé exécutif

La Phase 14.2 livre les fondations du multi-tenant : le registre central des
établissements, les tables structurelles RBAC/membership/config par tenant,
et la couche applicative de résolution (`TenantContext`, `TenantResolver`,
`TenantMiddleware`). **Aucune table existante n'a été modifiée, aucun
comportement applicatif actuel n'a changé.** L'application reste
fonctionnellement mono-tenant jusqu'à l'activation explicite en Phase 14.3+.

**Décision de cadrage** (voir §5) : le blueprint liste `TenantManager`,
`TenantPolicy`, `TenantCacheManager`, `TenantStorageManager` dans le cahier
des charges initial, mais son propre §25.1 ne livre pour la Phase 14.2 QUE
`TenantContext` + `TenantResolver` (+ `TenantMiddleware`, cité en §6.3/D4).
Les quatre autres composants relèvent explicitement des phases 14.8/14.9 et
n'ont pas été implémentés ici — voir §7 Dette technique.

---

## 2. Fichiers créés

### 2.1 Composants applicatifs (`core/Tenant/`)

| Fichier | Rôle |
|---|---|
| `core/Tenant/TenantException.php` | Exceptions typées (notResolved, notFound, suspended, planExpired) |
| `core/Tenant/Etablissement.php` | DTO immuable représentant une ligne `etablissements` |
| `core/Tenant/TenantContext.php` | Singleton porteur du tenant résolu (§5.1 du blueprint) |
| `core/Tenant/TenantRepository.php` | Accès lecture seule à `etablissements` / `etablissement_domains` |
| `core/Tenant/TenantResolver.php` | Résolution session → sous-domaine → domaine perso → chemin → header (§6.2) |
| `core/Tenant/TenantMiddleware.php` | `implements Core\Middleware`, orchestration resolve + codes HTTP (§6.2/§6.3) |

Namespace `Core\Tenant\` — autoload automatique via le préfixe `Core\` déjà
enregistré dans `public/index.php` (aucune modification de l'autoloader
nécessaire).

### 2.2 Configuration

| Fichier | Rôle |
|---|---|
| `config/tenant.php` | `enabled` (false par défaut), `base_domain`, `strategies` |

### 2.3 Migrations (`database/migrations/`)

| ID | Fichier | Contenu | Étape blueprint |
|---|---|---|---|
| T001 | `T001_tenant_platform.php` | `etablissements`, `platform_plans`, `platform_operators` | A1 |
| T002 | `T002_tenant_rbac_scaffold.php` | `etab_roles`, `etab_permissions`, `etab_role_permissions` | A3 |
| T003 | `T003_tenant_membership.php` | `user_etablissements`, `user_roles_etab` | A2 |
| T004 | `T004_tenant_config.php` | `etab_settings`, `etablissement_branding`, `etablissement_domains` | A4 |
| T005 | `T005_tenant_seeds.php` | Seeds : établissement démo, 3 plans, 267 permissions, 7 rôles système | A5–A8 |

Note d'ordonnancement : le blueprint séquence A1→A2→A3→A4, mais `user_roles_etab`
(A2) référence `etab_roles` (A3) par clé étrangère. L'ordre d'exécution réel
(T001→T002→T003→T004) inverse A2/A3 pour respecter les contraintes FK — le
résultat final en base est strictement identique au schéma cible du blueprint.

### 2.4 Tests

| Fichier | Contenu |
|---|---|
| `tests/Unit/TenantResolverTest.php` | 29 assertions : `extractSubdomainSlug`, `extractPathSlug`, `extractHeaderSlug`, `resolve()` bout-en-bout (5 stratégies + priorités), `TenantContext` |

---

## 3. Fichiers modifiés

**Aucun.** Cette phase est purement additive : aucun fichier PHP existant
(`core/Application.php`, `core/Router.php`, contrôleurs, modèles, vues) n'a
été touché, et aucune table existante n'a reçu d'`ALTER TABLE`.

---

## 4. Migrations — détail d'exécution

Sauvegarde complète de la base effectuée avant exécution (mitigation RISQUE 4
du blueprint §24), stockée hors du dépôt.

```
T001 → OK (6024ms)  — etablissements, platform_plans, platform_operators
T002 → OK (2950ms)  — etab_roles, etab_permissions, etab_role_permissions
T003 → OK (2393ms)  — user_etablissements, user_roles_etab
T004 → OK (2653ms)  — etab_settings, etablissement_branding, etablissement_domains
T005 → OK (7120ms)  — seeds
```

**Vérification post-migration (état réel en base) :**

| Table | Lignes | Détail |
|---|---|---|
| `etablissements` | 1 | `id=1`, `slug=edunova-demo`, `statut=active` — deviendra le tenant de rattachement des données V1 en Phase 14.3 |
| `platform_plans` | 3 | starter / pro / enterprise |
| `etab_roles` | 7 | admin, directeur, secretaire, comptable, enseignant, parent, eleve — tous `etablissement_id=NULL` (système) |
| `etab_permissions` | 267 | importées et dédupliquées depuis `config/permissions.php` |
| `platform_operators`, `etab_role_permissions`, `user_etablissements`, `user_roles_etab`, `etab_settings`, `etablissement_branding`, `etablissement_domains` | 0 | structurelles, peuplées en phases ultérieures |

**Vérification zéro-régression schéma :** `users` (et toutes les tables V1)
ne portent toujours aucune colonne `etablissement_id` — confirmé par requête
`INFORMATION_SCHEMA.COLUMNS`. Table count : 35 → 46 (+11, exactement les
tables listées ci-dessus).

**Index et contraintes :** chaque table porte les clés primaires, uniques et
étrangères prescrites par le blueprint (§4.2–§4.5, §8.2, §9.2, §11.1, §23.2),
avec `ON DELETE CASCADE` pour les enfants directs d'un établissement et
`ON DELETE SET NULL` pour les références d'audit (`invited_by`, `granted_by`,
`assigned_by`, `updated_by`).

---

## 5. Composants applicatifs — décisions de conception

- **`TenantContext`** : singleton statique conforme à §5.1 (`set`, `id`,
  `require`, `isSet`, `clear`). `require()` lève `TenantException` si non
  résolu — comportement strict et sans danger puisqu'aucun code de
  production ne l'appelle encore.
- **`TenantResolver`** : implémente les 5 stratégies non-JWT du §6.2
  (session, sous-domaine, domaine personnalisé, chemin `/s/{slug}`, header
  `X-Tenant-Slug`), dans l'ordre de priorité du blueprint. La résolution par
  claim JWT (priorité 1 du blueprint) est un **no-op documenté** : elle
  dépend du `LoginController` multi-tenant, hors périmètre (Phase 14.6).
  Les méthodes d'extraction (`extractSubdomainSlug`, `extractPathSlug`,
  `extractHeaderSlug`) sont statiques et pures, testables sans dépendance DB.
- **`TenantMiddleware`** : implémente `Core\Middleware` (même interface que
  `AuthMiddleware`), gère les 3 codes d'erreur du §6.2 (404 introuvable, 503
  suspendu, 402 plan expiré). **Non activé** — voir §6.
- **`TenantManager` / `TenantPolicy` / `TenantCacheManager` /
  `TenantStorageManager`** : **non implémentés dans cette phase** (voir
  Résumé exécutif §1 et Dette technique §7).

---

## 6. Non-activation volontaire (garantie zéro régression)

Aucun point d'entrée de l'application n'appelle `TenantResolver`,
`TenantContext::set()` ou `TenantMiddleware::handle()` :

- `core/Application.php::run()` n'a **pas** été modifié — `TenantMiddleware`
  n'est pas invoqué avant le dispatch des routes.
- `config/tenant.php['enabled']` vaut `false` par défaut.
- Aucun contrôleur, modèle ou vue ne référence `Core\Tenant\*`.

**Point d'intégration prévu pour la Phase 14.3+** (documenté dans le code,
`core/Tenant/TenantMiddleware.php`) : `core/Application.php::run()`, juste
après `$request = new Request();` et avant le chargement des routes — exactement
l'étape D2 du blueprint (§24.1).

Cette non-activation est délibérée : les tables V1 (`users`, `classes`,
`eleves`, ...) ne portent pas encore `etablissement_id` (Phase 14.3), et
aucune configuration réseau (sous-domaine, chemin `/s/`) n'existe en
environnement local. Activer le middleware maintenant romprait
immédiatement l'application entière.

---

## 7. Tests réalisés

| Suite | Résultat | Portée |
|---|---|---|
| `tests/Unit/TenantResolverTest.php` | **29/29 PASS** | Critère de validation du blueprint §25.1 : subdomain, path, header + TenantContext |
| `tests/security_tests.php` (pré-existant) | **48/48 PASS** | Régression sécurité globale |
| `tests/functional_tests.php` (pré-existant) | **109/109 PASS** | Régression fonctionnelle globale |
| **Total** | **186/186 PASS** | |
| Vérification manuelle | ✅ | Login + dashboard + liste élèves rechargés après migration, 0 erreur JS console |

---

## 8. Compatibilité V2

**100% compatible.** Aucune fonctionnalité existante modifiée, aucune route
existante affectée, aucune table V1/V2 altérée. Les modules déjà `enabled`
(Finance, VieScolaire, RH, Api) continuent de fonctionner sans changement —
ils ne consultent pas les nouvelles tables tenant.

---

## 9. Dette technique restante (explicitement hors périmètre 14.2)

| Élément | Phase blueprint | Statut |
|---|---|---|
| `etablissement_id` sur tables V1 (users, classes, eleves, professeurs, matieres) | 14.3 | Non fait |
| Bootstrap : activation de `TenantMiddleware` dans `Application::run()` | 14.3 (D2) | Non fait |
| Peuplement `etab_role_permissions`, `user_roles_etab` + `TenantAuthContext::permissions()` | 14.4 | Non fait |
| `SettingsService`, `BrandingService`, UI admin par établissement | 14.5 | Non fait |
| `LoginController` multi-tenant (claim JWT `etab`, school-picker) | 14.6 | Non fait — `TenantResolver` a un no-op documenté pour cette source |
| Vérification DNS/SSL domaines personnalisés | 14.7 | Non fait |
| Stockage S3 partitionné + quotas (`TenantStorageManager`) | 14.8 | Non fait |
| Cache Redis namespacé + queue workers (`TenantCacheManager`) | 14.9 | Non fait |
| Dashboard super-admin SaaS (`/platform/`) | 14.10 | Non fait |
| Sauvegardes automatiques par tenant | 14.11 | Non fait |
| `TenantManager`, `TenantPolicy` (génériques, non spécifiés par le blueprint pour 14.2) | — | Non fait, à clarifier si besoin réel identifié |
| Cache des lookups `TenantRepository` (actuellement requête DB directe à chaque appel) | 14.9 | Acceptable : non appelé en production tant que le middleware n'est pas activé |

---

## 10. Recommandation

**GO proposé pour Phase 14.3 — Migration V1 Tables**, sous réserve de
validation de ce rapport. Critères de la Phase 14.2 tous remplis :
tables A1-A8 créées et seedées, `TenantContext`/`TenantResolver` fonctionnels
et testés (29/29), zéro régression (186/186 tests globaux), zéro fichier
existant modifié.
