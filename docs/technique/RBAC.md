# RBAC — Contrôle d'accès basé sur les rôles

## 1. Rôles système

Définis dans `config/permissions.php`, 7 rôles :

| Rôle | Code | Profil typique |
|---|---|---|
| Administrateur | `admin` | Accès complet, y compris paramétrage plateforme |
| Direction | `directeur` | Pilotage établissement, quasi tous droits sauf technique pur |
| Secrétariat | `secretaire` | Gestion administrative quotidienne (élèves, inscriptions) |
| Comptable | `comptable` | Finance uniquement |
| Enseignant | `enseignant` | Notes, absences de ses classes, consultation |
| Parent | `parent` | Consultation des données de ses enfants uniquement |
| Élève | `eleve` | Consultation de ses propres données uniquement |

Il n'existe **pas** de rôle "RH" dédié dans le RBAC applicatif aujourd'hui — le
module RH est aujourd'hui opéré via les rôles `admin`/`directeur`/`secretaire` (le
guide `docs/fonctionnel/GUIDE_RH.md` le précise).

## 2. Permissions

Chaque permission suit la convention `module.action` (ex. `eleves.view`,
`eleves.create`, `finance.factures.validate`). Un rôle est une liste de codes de
permission dans `config/permissions.php`. Vérification dans un contrôleur :

```php
$this->requirePermission('eleves.edit');   // interrompt (403) si absent
$this->can('eleves.delete');               // bool, pour un affichage conditionnel
```

## 3. Trois paliers de résolution (`UserModel::getPermissions()`)

```
1. Palier TENANT (etab_role_permissions)  — si un contexte établissement est résolu
2. Palier V2 legacy (rbac_*)               — repli si le palier 1 est vide/absent
3. Palier V1 config (config/permissions.php) — repli final, garantit un
                                                comportement identique à l'historique
```

Les trois paliers produisent aujourd'hui des ensembles de permissions
**équivalents** pour chaque rôle système (vérifié par test d'équivalence stricte,
`RbacTenantTest.php`, 18/18) — le palier tenant existe pour permettre, à terme, des
rôles personnalisés par établissement sans toucher au code, sans changer le
comportement observable tant que ce n'est pas exploité.

## 4. Isolation multi-tenant du RBAC

- Un rôle personnalisé créé pour l'établissement B n'est **jamais** visible ni
  attribuable à un utilisateur de l'établissement A (testé dans les deux sens).
- `TenantAuthContext` résout les permissions effectives d'un utilisateur dans le
  contexte de l'établissement actif de sa session (voir
  `docs/technique/MULTI_TENANT.md` §RBAC).

## 5. Utilisateurs multi-établissements

Un utilisateur peut appartenir à plusieurs établissements (`user_etablissements`,
Phase 14.6) avec un rôle potentiellement différent dans chacun. À la connexion :
mono-établissement → activation automatique ; multi-établissements → sélecteur
(`/choisir-etablissement`) ; changement en cours de session via le sélecteur
d'établissement dans l'en-tête (`/auth/switch-school`). Les permissions affichées/
appliquées correspondent toujours au rôle de l'utilisateur **dans l'établissement
actif**, jamais un cumul entre établissements.

## 6. Portail Super-Admin — RBAC totalement distinct

Le portail `/platform/*` (Super-Admin SaaS) utilise un modèle de permission
**entièrement séparé** : table `platform_operators` (niveaux `support`/`admin`/
`super_admin`), session sous une clé dédiée (`_platform_operator`, jamais
`_auth_user`). Aucun recouvrement de code avec le RBAC établissement décrit
ci-dessus — voir `docs/technique/MULTI_TENANT.md` §"Super-Admin" pour le détail et
la justification de cette séparation stricte.

## 7. Vérification systématique

- `Core\Controller::requireAuth()` / `requireRole()` / `requirePermission()` / `can()`
- Chaque action d'écriture (`POST`) vérifie en plus le jeton CSRF
  (`Core\Controller::verifyCsrf()`)
- Journalisation des refus : `Logger::security('ACCESS_DENIED', ...)`

## 8. Tests

`RbacTenantTest.php` (18/18) — équivalence des 3 paliers par rôle, isolation des
rôles personnalisés inter-tenant, cohérence à la connexion réelle.
`security_tests.php` (48/48) — couverture transverse des points d'accès sensibles.

## 9. Documents associés

`RBAC_V2.md` (conception détaillée, 85 permissions), `RBAC_MULTI_TENANT_IMPLEMENTATION_REPORT.md`.
