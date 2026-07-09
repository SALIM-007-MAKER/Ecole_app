# RBAC V2 — SCOLARIS
## Conception du Système de Contrôle d'Accès Basé sur les Rôles

> Étape 3 de la migration SCOLARIS V2.
> **Aucune modification du code métier.** Uniquement conception et migrations SQL.
> Date : 2026-06-29

---

## TABLE DES MATIÈRES

1. [Analyse du système actuel](#1-analyse-du-système-actuel)
2. [Problèmes identifiés](#2-problèmes-identifiés)
3. [Conception RBAC V2](#3-conception-rbac-v2)
4. [Matrice des permissions V2](#4-matrice-des-permissions-v2)
5. [Schéma SQL des 4 tables](#5-schéma-sql-des-4-tables)
6. [Scripts de migration](#6-scripts-de-migration)
7. [Stratégie de transition V1 → V2](#7-stratégie-de-transition-v1--v2)

---

## 1. ANALYSE DU SYSTÈME ACTUEL

### 1.1 Architecture V1 — Comment ça fonctionne

```
LOGIN
  │
  ├── AuthController::login()
  │     └── UserModel::getPermissions($role)
  │           └── require 'config/permissions.php'  ← SOURCE DE VÉRITÉ
  │                 return $map[$role] ?? []
  │
  └── Session::setUser([..., 'permissions' => $permissions])
                                    │
                                    ▼
                          $_SESSION['_auth_user']['permissions']
                                    │
                    ┌───────────────┼───────────────┐
                    ▼               ▼               ▼
            Controller::can()  requirePermission()  vue: can($perms, 'x')
            (vérifie in_array)  (redirige 403)      (vérifie in_array)
```

**En un mot :** Les permissions sont un simple tableau PHP (flat array de strings) chargé une seule fois en session depuis un fichier de configuration statique. Aucune requête DB n'est effectuée à l'exécution.

---

### 1.2 Inventaire complet des permissions V1

Extrait de `config/permissions.php` — 7 rôles, ~60 codes de permissions :

**Format actuel :** `{module}.{action}` (ex : `eleves.view`, `notes.view_own`)

| Module | Actions accordées | Observation |
|---|---|---|
| `eleves` | view, create, edit, delete | aucune action export/import |
| `enseignants` | view, create, edit, delete | |
| `classes` | view, create, edit, delete | |
| `matieres` | view, create, edit, delete | |
| `notes` | view, create, edit, delete, view_own | `view_own` = élève/parent |
| `absences` | view, create, edit, view_own, justify | `justify` = parent soumet |
| `bulletins` | view | pas de print, pas d'approve |
| `comptabilite` | view, create, edit, view_own | create/edit = tout gérer |
| `emploi_du_temps` | view, create, edit, view_own | |
| `annonces` | view, create, edit, delete | |
| `notifications` | manage | action unique fourre-tout |
| `users` | view, create, edit, delete | |
| `rapports` | view | pas d'export |

**Codes dans `permissions.php` mais ABSENTS de la table DB :**
```
bulletins.view, comptabilite.view_own, comptabilite.create, comptabilite.edit,
emploi_du_temps.view_own, emploi_du_temps.create, emploi_du_temps.edit,
annonces.create, annonces.edit, annonces.delete, notifications.manage,
absences.justify
```

**Codes dans la table DB mais ABSENTS de `permissions.php` :**
```
notes.view_own (dans DB, mais en double avec notes.view_own dans config)
```

---

### 1.3 Usage du système dans le code

**Contrôleurs — `requirePermission()` (bloquant, HTTP 403) :**
- 85+ appels répartis dans 15 contrôleurs
- Pattern systématique : `$this->requirePermission('module.action')` en début de méthode

**Contrôleurs — `can()` (conditionnel, bool) :**
- 20+ appels pour adapter l'UI selon les droits
- Ex : `'canEdit' => $this->can('absences.edit')` passé aux vues

**Vues — `can($perms, 'permission')` :**
- Fonction locale définie dans `dashboard.php`
- Utilise `$user['permissions']` passé par le contrôleur

---

## 2. PROBLÈMES IDENTIFIÉS

### RBAC-001 — Permissions statiques, non modifiables sans déploiement

Le fichier `config/permissions.php` est dans le dépôt. Toute modification de droits (ex : donner à la secrétaire le droit de supprimer des élèves) impose un déploiement de code.

**Impact :** Impossible pour un administrateur de personnaliser les droits depuis l'interface.

---

### RBAC-002 — Un seul rôle par utilisateur (ENUM)

`users.role` est un `ENUM` à valeur unique. Il est impossible d'attribuer deux rôles à un utilisateur (ex : directeur + comptable) ou de créer un rôle personnalisé.

**Impact :** Pas de flexibilité organisationnelle. Ajout d'un rôle = migration de schéma.

---

### RBAC-003 — Actions incohérentes et incomplètes

| Problème | Exemple |
|---|---|
| `edit` au lieu de `update` | Non conforme aux conventions REST |
| Pas d'action `export` | `eleves.view` couvre aussi l'export CSV/PDF |
| Pas d'action `print` | `bulletins.view` couvre aussi l'impression |
| Pas d'action `approve` | `absences.edit` couvre aussi la validation |
| `notifications.manage` | Action fourre-tout non granulaire |
| `comptabilite.create` + `comptabilite.edit` | Couvrent frais + paiements + dépenses ensemble |

---

### RBAC-004 — Scope `view_own` mal modélisé

`notes.view_own`, `absences.view_own`, `comptabilite.view_own`, `emploi_du_temps.view_own` sont des permissions distinctes alors que le scope (global vs propres données) est une propriété orthogonale à l'action.

**Impact :** Prolifération des codes de permission, logique métier dupliquée.

---

### RBAC-005 — Table DB `permissions` et `config/` désynchronisées

Deux sources de vérité dont une (la table DB) est ignorée à l'exécution. La table contient 27 codes, le fichier PHP en contient ~60. Toute évolution dans l'un n'est pas répercutée dans l'autre.

---

### RBAC-006 — Pas de traçabilité des changements de rôle

Aucun audit : qui a accordé quel rôle, quand, pour combien de temps. Pas d'accès temporaire possible.

---

### RBAC-007 — Vérification uniquement à l'exécution de l'action

Les permissions sont vérifiées au moment de l'action (login, form submit). Pas de vérification de cohérence lors du changement de rôle d'un utilisateur connecté. Sa session garde les anciennes permissions jusqu'à déconnexion.

---

## 3. CONCEPTION RBAC V2

### 3.1 Principes directeurs

1. **DB-driven** : Les permissions sont lues depuis la DB à la connexion. Plus de fichier PHP statique.
2. **Backward-compatible** : `can()` et `requirePermission()` dans `Controller` ne changent PAS — seul le chargement des permissions change.
3. **Multi-rôles** : Un utilisateur peut avoir plusieurs rôles via `user_roles`.
4. **Scopes** : `global` (toutes les données) vs `own` (données propres) modélisé sur la permission.
5. **Actions normalisées** : 7 actions standard pour tous les modules.
6. **Granularité** : Chaque module/action/scope = une permission distincte.

---

### 3.2 Les 7 actions standard

| Action | Code V2 | Remplace V1 | Sémantique |
|---|---|---|---|
| `view` | `module.view` | `module.view` | Consulter la liste et le détail |
| `create` | `module.create` | `module.create` | Créer un enregistrement |
| `update` | `module.update` | `module.edit` ← renommé | Modifier un enregistrement existant |
| `delete` | `module.delete` | `module.delete` | Supprimer |
| `export` | `module.export` | — (nouveau) | Exporter en CSV/Excel/PDF |
| `print` | `module.print` | — (nouveau) | Imprimer (bulletins, reçus, EDT) |
| `approve` | `module.approve` | — (nouveau) | Valider/refuser (justifications, bulletins) |

**Scope :** Certaines actions existent en deux variantes :
- `module.action` → scope `global` (toutes les données)
- `module.action.own` → scope `own` (ses propres données uniquement)

**Règle de cumul :** `global` inclut implicitement `own`. Un utilisateur avec `notes.view` peut aussi voir ses propres notes.

---

### 3.3 Les 4 tables du RBAC V2

```
users ──────────── user_roles ──────────── roles
  id                user_id (FK)            id
  nom               role_id (FK)            slug
  email             granted_by (FK→users)   label
  role (V1, gardé) granted_at              description
  ...               expires_at              is_system
                    actif                   actif
                                            ordre

roles ──────────── role_permissions ──────── permissions
  id                role_id (FK)             id
                    permission_id (FK)       code          ← 'eleves.view'
                                             libelle
                                             module        ← 'eleves'
                                             action        ← 'view'
                                             scope         ← 'global' | 'own'
                                             is_system
```

---

### 3.4 Règles de chargement à la connexion (V2)

```
LOGIN
  │
  ├── AuthController::login()
  │     └── RbacService::loadUserPermissions($userId)
  │           │
  │           ├── SELECT p.code
  │           │   FROM user_roles ur
  │           │   JOIN role_permissions rp ON rp.role_id = ur.role_id
  │           │   JOIN permissions p ON p.id = rp.permission_id
  │           │   WHERE ur.user_id = ? AND ur.actif = 1
  │           │     AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
  │           │   GROUP BY p.code
  │           │
  │           └── return ['eleves.view', 'notes.update', ...] ← même format qu'avant
  │
  └── Session::setUser([..., 'permissions' => $permissions])
                                    │
                              INCHANGÉ ←──── can(), requirePermission() : ZERO modification
```

**La session garde exactement le même format.** Les 85+ appels à `requirePermission()` et `can()` dans les contrôleurs fonctionnent sans aucune modification.

---

### 3.5 Gestion des scopes dans les contrôleurs (V2)

Les permissions `*.view.own` remplacent `*.view_own`. Le code de contrôleur évolue graduellement :

```php
// V1 (actuel — continue à fonctionner pendant transition)
if (!$this->can('absences.view') && !$this->can('absences.view_own')) { ... }

// V2 (après migration de code)
if (!$this->can('absences.view') && !$this->can('absences.view.own')) { ... }
```

**Note :** Pendant la transition, les deux codes coexistent dans la table `permissions`. Le service de chargement V2 peut retourner les deux si nécessaire.

---

## 4. MATRICE DES PERMISSIONS V2

### 4.1 Convention de lecture

- `✓` : permission accordée (scope global)
- `○` : permission accordée (scope own seulement)
- `—` : permission refusée
- `*` : action non applicable à ce module

### 4.2 Matrice complète

```
MODULE          ACTION     admin  directeur  secretaire  comptable  enseignant  parent  eleve
─────────────────────────────────────────────────────────────────────────────────────────────
eleves          view         ✓       ✓          ✓           ✓          ✓         —       —
                create       ✓       ✓          ✓           —          —         —       —
                update       ✓       ✓          ✓           —          —         —       —
                delete       ✓       ✓          —           —          —         —       —
                export       ✓       ✓          ✓           —          —         —       —
                print        —       —           —           —          —         —       —
                approve      —       —           —           —          —         —       —

enseignants     view         ✓       ✓          —           ✓          —         —       —
                create       ✓       ✓          —           —          —         —       —
                update       ✓       ✓          —           —          —         —       —
                delete       ✓       ✓          —           —          —         —       —
                export       ✓       ✓          —           —          —         —       —
                print        —       —           —           —          —         —       —
                approve      —       —           —           —          —         —       —

classes         view         ✓       ✓          ✓           ✓          ✓         —       —
                create       ✓       ✓          —           —          —         —       —
                update       ✓       ✓          —           —          —         —       —
                delete       ✓       ✓          —           —          —         —       —
                export       —       —           —           —          —         —       —
                print        —       —           —           —          —         —       —
                approve      —       —           —           —          —         —       —

matieres        view         ✓       ✓          ✓           ✓          ✓         —       —
                create       ✓       ✓          —           —          —         —       —
                update       ✓       ✓          —           —          —         —       —
                delete       ✓       ✓          —           —          —         —       —
                export       —       —           —           —          —         —       —
                print        —       —           —           —          —         —       —
                approve      —       —           —           —          —         —       —

notes           view         ✓       ✓          ✓           —          ✓         —       —
                view.own     ✓       ✓          ✓           —          ✓         ○       ○
                create       ✓       ✓          —           —          ✓         —       —
                update       ✓       ✓          —           —          ✓         —       —
                delete       ✓       ✓          —           —          —         —       —
                export       ✓       ✓          —           —          —         —       —
                print        —       —           —           —          —         —       —
                approve      —       —           —           —          —         —       —

bulletins       view         ✓       ✓          ✓           —          ✓         —       —
                view.own     ✓       ✓          ✓           —          ✓         ○       ○
                create       —       —           —           —          —         —       —
                update       —       —           —           —          —         —       —
                delete       —       —           —           —          —         —       —
                export       ✓       ✓          —           —          ✓         ○       ○
                print        ✓       ✓          —           —          ✓         ○       ○
                approve      ✓       ✓          —           —          —         —       —

absences        view         ✓       ✓          ✓           —          ✓         —       —
                view.own     ✓       ✓          ✓           —          ✓         ○       ○
                create       ✓       ✓          ✓           —          ✓         —       —
                update       ✓       ✓          ✓           —          ✓         —       —
                delete       ✓       ✓          —           —          —         —       —
                export       ✓       ✓          ✓           —          —         —       —
                print        —       —           —           —          —         —       —
                approve      ✓       ✓          ✓           —          ✓         ○       —

comptabilite    view         ✓       ✓          ✓           ✓          —         —       —
                view.own     ✓       ✓          ✓           ✓          —         ○       —
                create       ✓       ✓          ✓           ✓          —         —       —
                update       ✓       ✓          —           ✓          —         —       —
                delete       ✓       —           —           ✓          —         —       —
                export       ✓       ✓          —           ✓          —         —       —
                print        ✓       ✓          ✓           ✓          —         ○       —
                approve      —       —           —           —          —         —       —

paiements       view         ✓       ✓          ✓           ✓          —         —       —
                view.own     ✓       ✓          ✓           ✓          —         ○       —
                create       ✓       ✓          ✓           ✓          —         —       —
                update       ✓       —           —           ✓          —         —       —
                delete       ✓       —           —           ✓          —         —       —
                export       ✓       ✓          —           ✓          —         —       —
                print        ✓       ✓          ✓           ✓          —         ○       —
                approve      —       —           —           —          —         —       —

depenses        view         ✓       ✓          —           ✓          —         —       —
                create       ✓       —           —           ✓          —         —       —
                update       ✓       —           —           ✓          —         —       —
                delete       ✓       —           —           ✓          —         —       —
                export       ✓       ✓          —           ✓          —         —       —
                print        —       —           —           —          —         —       —
                approve      —       —           —           —          —         —       —

emploi_du_temps view         ✓       ✓          ✓           —          ✓         —       —
                view.own     ✓       ✓          ✓           —          ✓         ○       ○
                create       ✓       ✓          —           —          —         —       —
                update       ✓       ✓          —           —          —         —       —
                delete       ✓       ✓          —           —          —         —       —
                export       —       —           —           —          —         —       —
                print        ✓       ✓          ✓           —          ✓         ○       ○
                approve      —       —           —           —          —         —       —

annonces        view         ✓       ✓          ✓           ✓          ✓         ✓       ✓
                create       ✓       ✓          ✓           —          —         —       —
                update       ✓       ✓          ✓           —          —         —       —
                delete       ✓       ✓          ✓           —          —         —       —
                export       —       —           —           —          —         —       —
                print        —       —           —           —          —         —       —
                approve      —       —           —           —          —         —       —

notifications   view         ✓       ✓          ✓           ✓          ✓         ✓       ✓
                view.own     ✓       ✓          ✓           ✓          ✓         ○       ○
                create       ✓       ✓          —           —          —         —       —
                update       —       —           —           —          —         —       —
                delete       ✓       ✓          —           —          —         —       —
                export       —       —           —           —          —         —       —
                print        —       —           —           —          —         —       —
                approve      —       —           —           —          —         —       —

users           view         ✓       ✓          —           —          —         —       —
                create       ✓       ✓          —           —          —         —       —
                update       ✓       ✓          —           —          —         ○       ○
                delete       ✓       —           —           —          —         —       —
                export       ✓       —           —           —          —         —       —
                print        —       —           —           —          —         —       —
                approve      —       —           —           —          —         —       —

rapports        view         ✓       ✓          ✓           ✓          —         —       —
                export       ✓       ✓          ✓           ✓          —         —       —
                print        ✓       ✓          ✓           ✓          —         —       —
                create       —       —           —           —          —         —       —
                update       —       —           —           —          —         —       —
                delete       —       —           —           —          —         —       —
                approve      —       —           —           —          —         —       —

profil          view.own     ✓       ✓          ✓           ✓          ✓         ✓       ✓
                update.own   ✓       ✓          ✓           ✓          ✓         ✓       ✓
```

### 4.3 Tableau de correspondance V1 → V2

| Code V1 | Code V2 | Changement |
|---|---|---|
| `eleves.edit` | `eleves.update` | Renommé |
| `enseignants.edit` | `enseignants.update` | Renommé |
| `classes.edit` | `classes.update` | Renommé |
| `matieres.edit` | `matieres.update` | Renommé |
| `notes.edit` | `notes.update` | Renommé |
| `notes.view_own` | `notes.view.own` | Scope séparé |
| `absences.edit` | `absences.update` | Renommé |
| `absences.view_own` | `absences.view.own` | Scope séparé |
| `absences.justify` | `absences.approve.own` | Action précisée, scope own |
| `bulletins.view` | `bulletins.view` + `bulletins.print` + `bulletins.export` | Décomposé |
| `comptabilite.create` | `comptabilite.create` + `paiements.create` | Décomposé |
| `comptabilite.edit` | `comptabilite.update` + `paiements.update` | Décomposé |
| `comptabilite.view_own` | `comptabilite.view.own` | Scope séparé |
| `emploi_du_temps.edit` | `emploi_du_temps.update` | Renommé |
| `emploi_du_temps.view_own` | `emploi_du_temps.view.own` | Scope séparé |
| `annonces.edit` | `annonces.update` | Renommé |
| `notifications.manage` | `notifications.create` + `notifications.delete` | Décomposé |
| `users.edit` | `users.update` | Renommé |
| `rapports.view` | `rapports.view` + `rapports.export` | Décomposé |
| — | `eleves.export` | Nouveau |
| — | `bulletins.approve` | Nouveau |
| — | `absences.approve` | Nouveau |
| — | `depenses.*` | Nouveau module séparé |
| — | `paiements.*` | Nouveau module séparé |
| — | `profil.view.own` | Nouveau |
| — | `profil.update.own` | Nouveau |

---

## 5. SCHÉMA SQL DES 4 TABLES

### 5.1 `roles` — Table des rôles

```sql
CREATE TABLE `roles` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `slug`        VARCHAR(50)   NOT NULL UNIQUE
                  COMMENT 'Identifiant technique : admin, directeur, enseignant…',
    `label`       VARCHAR(100)  NOT NULL
                  COMMENT 'Nom affiché : Administrateur, Directeur…',
    `description` TEXT          NULL,
    `is_system`   TINYINT(1)    NOT NULL DEFAULT 1
                  COMMENT '1 = rôle système non supprimable (admin, directeur…)',
    `actif`       TINYINT(1)    NOT NULL DEFAULT 1,
    `ordre`       TINYINT UNSIGNED NOT NULL DEFAULT 0
                  COMMENT 'Ordre d\'affichage dans l\'interface (0 = premier)',
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_roles_actif` (`actif`),
    INDEX `idx_roles_ordre` (`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Référentiel des rôles du système RBAC V2';
```

---

### 5.2 `permissions` — Table des permissions (refactorisée)

```sql
CREATE TABLE `permissions` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(120)  NOT NULL UNIQUE
                  COMMENT 'Code complet : module.action ou module.action.scope',
    `libelle`     VARCHAR(255)  NOT NULL
                  COMMENT 'Description lisible : Consulter les élèves',
    `module`      VARCHAR(50)   NOT NULL
                  COMMENT 'Module fonctionnel : eleves, notes, absences…',
    `action`      ENUM(
                    'view',
                    'create',
                    'update',
                    'delete',
                    'export',
                    'print',
                    'approve'
                  ) NOT NULL
                  COMMENT 'Action standard RBAC V2',
    `scope`       ENUM('global','own') NOT NULL DEFAULT 'global'
                  COMMENT 'global = toutes données | own = données propres',
    `is_system`   TINYINT(1)    NOT NULL DEFAULT 1
                  COMMENT '1 = permission système non supprimable',
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_perm_module` (`module`),
    INDEX `idx_perm_action` (`action`),
    INDEX `idx_perm_scope`  (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Permissions atomiques du système RBAC V2 — module × action × scope';
```

---

### 5.3 `role_permissions` — Association rôles ↔ permissions

```sql
CREATE TABLE `role_permissions` (
    `role_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `granted_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                    COMMENT 'Date d\'attribution de la permission au rôle',
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_rp_role`
        FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_permission`
        FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Association many-to-many : rôle ↔ permission';
```

---

### 5.4 `user_roles` — Association utilisateurs ↔ rôles

```sql
CREATE TABLE `user_roles` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED  NOT NULL,
    `role_id`     INT UNSIGNED  NOT NULL,
    `granted_by`  INT UNSIGNED  NULL
                  COMMENT 'users.id de l\'administrateur ayant accordé le rôle',
    `granted_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`  DATETIME      NULL
                  COMMENT 'NULL = permanent | DATE = accès temporaire',
    `actif`       TINYINT(1)    NOT NULL DEFAULT 1,
    `note`        VARCHAR(255)  NULL
                  COMMENT 'Raison de l\'attribution (remplacement, intérim…)',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_role` (`user_id`, `role_id`),
    INDEX `idx_ur_user`    (`user_id`),
    INDEX `idx_ur_role`    (`role_id`),
    INDEX `idx_ur_actif`   (`actif`),
    INDEX `idx_ur_expires` (`expires_at`),
    CONSTRAINT `fk_ur_user`
        FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_ur_role`
        FOREIGN KEY (`role_id`)    REFERENCES `roles`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_ur_granted_by`
        FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Attribution des rôles aux utilisateurs — supporte multi-rôles et accès temporaires';
```

---

## 6. SCRIPTS DE MIGRATION

> **Ordre d'exécution :** R001 → R002 → R003 → R004 → R005
> Ces migrations créent les nouvelles structures SANS toucher au code métier.
> La table `permissions` V1 est REMPLACÉE (DROP + CREATE) car son schéma change.
> Les tables `roles` et `user_roles` sont créées from scratch.

---

### R001 — Créer la table `roles` et insérer les 7 rôles système

```sql
-- ════════════════════════════════════════════════════════════════════════════
-- R001 : Création de la table roles + 7 rôles système
-- Prérequis : ecole_app.sql + auth_migration.sql appliqués
-- ════════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

CREATE TABLE IF NOT EXISTS `roles` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `slug`        VARCHAR(50)      NOT NULL UNIQUE,
    `label`       VARCHAR(100)     NOT NULL,
    `description` TEXT             NULL,
    `is_system`   TINYINT(1)       NOT NULL DEFAULT 1,
    `actif`       TINYINT(1)       NOT NULL DEFAULT 1,
    `ordre`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_roles_actif` (`actif`),
    INDEX `idx_roles_ordre` (`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `roles` (`slug`, `label`, `description`, `is_system`, `ordre`) VALUES
('admin',      'Administrateur',
 'Accès complet à toutes les fonctionnalités du système. Non limitable.',            1, 1),
('directeur',  'Directeur',
 'Direction de l\'établissement. Accès global sauf suppression d\'utilisateurs.',    1, 2),
('secretaire', 'Secrétaire',
 'Gestion administrative : élèves, absences, inscriptions, communication.',          1, 3),
('comptable',  'Comptable',
 'Gestion financière : frais, paiements, dépenses, rapports financiers.',            1, 4),
('enseignant', 'Enseignant',
 'Saisie des notes, gestion des absences de ses classes, consultation EDT.',         1, 5),
('parent',     'Parent / Tuteur',
 'Consultation des données propres à ses enfants : notes, absences, paiements.',     1, 6),
('eleve',      'Élève',
 'Consultation de ses propres données : notes, bulletins, emploi du temps.',         1, 7);

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('R001', 'Création de la table roles avec 7 rôles système');
```

---

### R002 — Recréer `permissions` avec le schéma V2

```sql
-- ════════════════════════════════════════════════════════════════════════════
-- R002 : Remplacement de la table permissions (schéma V1 → V2)
-- ⚠ ATTENTION : DROP de la table existante (et de role_permissions par CASCADE)
-- Prérequis : R001
-- ════════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

SET FOREIGN_KEY_CHECKS = 0;

-- Supprimer les anciennes tables (les données sont invalidées par le nouveau schéma)
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;

SET FOREIGN_KEY_CHECKS = 1;

-- Créer permissions V2
CREATE TABLE `permissions` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`       VARCHAR(120) NOT NULL UNIQUE,
    `libelle`    VARCHAR(255) NOT NULL,
    `module`     VARCHAR(50)  NOT NULL,
    `action`     ENUM('view','create','update','delete','export','print','approve') NOT NULL,
    `scope`      ENUM('global','own') NOT NULL DEFAULT 'global',
    `is_system`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_perm_module` (`module`),
    INDEX `idx_perm_action` (`action`),
    INDEX `idx_perm_scope`  (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Créer role_permissions V2 (avec FK vers roles.id au lieu de ENUM)
CREATE TABLE `role_permissions` (
    `role_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `granted_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_rp_role_v2`
        FOREIGN KEY (`role_id`)       REFERENCES `roles`(`id`)       ON DELETE CASCADE,
    CONSTRAINT `fk_rp_permission_v2`
        FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('R002', 'Remplacement permissions V1 par schéma V2 (module+action+scope)');
```

---

### R003 — Insérer toutes les permissions V2

```sql
-- ════════════════════════════════════════════════════════════════════════════
-- R003 : Insertion de toutes les permissions RBAC V2
-- Format code : module.action ou module.action.own
-- Prérequis : R002
-- ════════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

INSERT INTO `permissions` (`code`, `libelle`, `module`, `action`, `scope`) VALUES

-- ── ÉLÈVES ───────────────────────────────────────────────────────────────────
('eleves.view',   'Consulter la liste des élèves',     'eleves', 'view',   'global'),
('eleves.create', 'Inscrire un nouvel élève',           'eleves', 'create', 'global'),
('eleves.update', 'Modifier le dossier d\'un élève',    'eleves', 'update', 'global'),
('eleves.delete', 'Supprimer un élève',                 'eleves', 'delete', 'global'),
('eleves.export', 'Exporter la liste des élèves',       'eleves', 'export', 'global'),

-- ── ENSEIGNANTS ──────────────────────────────────────────────────────────────
('enseignants.view',   'Consulter la liste des enseignants',   'enseignants', 'view',   'global'),
('enseignants.create', 'Ajouter un enseignant',                'enseignants', 'create', 'global'),
('enseignants.update', 'Modifier le profil d\'un enseignant',  'enseignants', 'update', 'global'),
('enseignants.delete', 'Supprimer un enseignant',              'enseignants', 'delete', 'global'),
('enseignants.export', 'Exporter la liste des enseignants',    'enseignants', 'export', 'global'),

-- ── CLASSES ───────────────────────────────────────────────────────────────────
('classes.view',   'Consulter les classes',     'classes', 'view',   'global'),
('classes.create', 'Créer une classe',           'classes', 'create', 'global'),
('classes.update', 'Modifier une classe',        'classes', 'update', 'global'),
('classes.delete', 'Supprimer une classe',       'classes', 'delete', 'global'),

-- ── MATIÈRES ─────────────────────────────────────────────────────────────────
('matieres.view',   'Consulter les matières',     'matieres', 'view',   'global'),
('matieres.create', 'Créer une matière',           'matieres', 'create', 'global'),
('matieres.update', 'Modifier une matière',        'matieres', 'update', 'global'),
('matieres.delete', 'Supprimer une matière',       'matieres', 'delete', 'global'),

-- ── NOTES ─────────────────────────────────────────────────────────────────────
('notes.view',       'Consulter toutes les notes',    'notes', 'view',   'global'),
('notes.view.own',   'Consulter ses propres notes',   'notes', 'view',   'own'),
('notes.create',     'Saisir une note',                'notes', 'create', 'global'),
('notes.update',     'Modifier une note',              'notes', 'update', 'global'),
('notes.delete',     'Supprimer une note',             'notes', 'delete', 'global'),
('notes.export',     'Exporter les notes de classe',   'notes', 'export', 'global'),

-- ── BULLETINS ────────────────────────────────────────────────────────────────
('bulletins.view',       'Consulter tous les bulletins',      'bulletins', 'view',    'global'),
('bulletins.view.own',   'Consulter son propre bulletin',     'bulletins', 'view',    'own'),
('bulletins.print',      'Imprimer un bulletin',              'bulletins', 'print',   'global'),
('bulletins.print.own',  'Imprimer son propre bulletin',      'bulletins', 'print',   'own'),
('bulletins.export',     'Exporter les bulletins en PDF',     'bulletins', 'export',  'global'),
('bulletins.export.own', 'Exporter son propre bulletin',      'bulletins', 'export',  'own'),
('bulletins.approve',    'Valider et publier les bulletins',  'bulletins', 'approve', 'global'),

-- ── ABSENCES ─────────────────────────────────────────────────────────────────
('absences.view',         'Consulter toutes les absences',         'absences', 'view',    'global'),
('absences.view.own',     'Consulter ses propres absences',        'absences', 'view',    'own'),
('absences.create',       'Enregistrer une absence',               'absences', 'create',  'global'),
('absences.update',       'Modifier une absence',                  'absences', 'update',  'global'),
('absences.delete',       'Supprimer une absence',                 'absences', 'delete',  'global'),
('absences.export',       'Exporter les absences',                 'absences', 'export',  'global'),
('absences.approve',      'Valider/refuser une justification',     'absences', 'approve', 'global'),
('absences.approve.own',  'Soumettre une justification (parent)',  'absences', 'approve', 'own'),

-- ── COMPTABILITÉ ─────────────────────────────────────────────────────────────
('comptabilite.view',     'Consulter le module comptabilité',       'comptabilite', 'view',   'global'),
('comptabilite.view.own', 'Consulter ses propres données financières','comptabilite','view',   'own'),
('comptabilite.create',   'Créer des frais / types de frais',       'comptabilite', 'create', 'global'),
('comptabilite.update',   'Modifier des frais',                     'comptabilite', 'update', 'global'),
('comptabilite.delete',   'Supprimer des frais',                    'comptabilite', 'delete', 'global'),
('comptabilite.export',   'Exporter les données comptables',        'comptabilite', 'export', 'global'),
('comptabilite.print',    'Imprimer les rapports comptables',       'comptabilite', 'print',  'global'),

-- ── PAIEMENTS ────────────────────────────────────────────────────────────────
('paiements.view',       'Consulter les paiements',           'paiements', 'view',   'global'),
('paiements.view.own',   'Consulter ses propres paiements',   'paiements', 'view',   'own'),
('paiements.create',     'Enregistrer un paiement',           'paiements', 'create', 'global'),
('paiements.update',     'Modifier un paiement',              'paiements', 'update', 'global'),
('paiements.delete',     'Supprimer un paiement',             'paiements', 'delete', 'global'),
('paiements.export',     'Exporter les paiements',            'paiements', 'export', 'global'),
('paiements.print',      'Imprimer un reçu de paiement',      'paiements', 'print',  'global'),
('paiements.print.own',  'Imprimer son propre reçu',          'paiements', 'print',  'own'),

-- ── DÉPENSES ─────────────────────────────────────────────────────────────────
('depenses.view',   'Consulter les dépenses',        'depenses', 'view',   'global'),
('depenses.create', 'Saisir une dépense',             'depenses', 'create', 'global'),
('depenses.update', 'Modifier une dépense',           'depenses', 'update', 'global'),
('depenses.delete', 'Supprimer une dépense',          'depenses', 'delete', 'global'),
('depenses.export', 'Exporter les dépenses',          'depenses', 'export', 'global'),

-- ── EMPLOI DU TEMPS ──────────────────────────────────────────────────────────
('emploi_du_temps.view',       'Consulter tous les emplois du temps',      'emploi_du_temps', 'view',   'global'),
('emploi_du_temps.view.own',   'Consulter son propre emploi du temps',     'emploi_du_temps', 'view',   'own'),
('emploi_du_temps.create',     'Créer un créneau',                         'emploi_du_temps', 'create', 'global'),
('emploi_du_temps.update',     'Modifier un créneau',                      'emploi_du_temps', 'update', 'global'),
('emploi_du_temps.delete',     'Supprimer un créneau',                     'emploi_du_temps', 'delete', 'global'),
('emploi_du_temps.print',      'Imprimer l\'emploi du temps',              'emploi_du_temps', 'print',  'global'),
('emploi_du_temps.print.own',  'Imprimer son propre emploi du temps',      'emploi_du_temps', 'print',  'own'),

-- ── ANNONCES ─────────────────────────────────────────────────────────────────
('annonces.view',   'Consulter les annonces',     'annonces', 'view',   'global'),
('annonces.create', 'Publier une annonce',         'annonces', 'create', 'global'),
('annonces.update', 'Modifier une annonce',        'annonces', 'update', 'global'),
('annonces.delete', 'Supprimer une annonce',       'annonces', 'delete', 'global'),

-- ── NOTIFICATIONS ────────────────────────────────────────────────────────────
('notifications.view',     'Consulter les notifications',        'notifications', 'view',   'global'),
('notifications.view.own', 'Consulter ses propres notifications','notifications', 'view',   'own'),
('notifications.create',   'Envoyer une notification',           'notifications', 'create', 'global'),
('notifications.delete',   'Supprimer des notifications',        'notifications', 'delete', 'global'),

-- ── UTILISATEURS ─────────────────────────────────────────────────────────────
('users.view',       'Consulter la liste des utilisateurs',  'users', 'view',   'global'),
('users.create',     'Créer un utilisateur',                  'users', 'create', 'global'),
('users.update',     'Modifier un utilisateur',               'users', 'update', 'global'),
('users.update.own', 'Modifier son propre profil',            'users', 'update', 'own'),
('users.delete',     'Supprimer un utilisateur',              'users', 'delete', 'global'),
('users.export',     'Exporter la liste des utilisateurs',    'users', 'export', 'global'),

-- ── RAPPORTS ─────────────────────────────────────────────────────────────────
('rapports.view',   'Consulter les rapports analytiques', 'rapports', 'view',   'global'),
('rapports.export', 'Exporter les rapports',              'rapports', 'export', 'global'),
('rapports.print',  'Imprimer les rapports',              'rapports', 'print',  'global'),

-- ── PROFIL ───────────────────────────────────────────────────────────────────
('profil.view.own',   'Consulter son propre profil', 'profil', 'view',   'own'),
('profil.update.own', 'Modifier son propre profil',  'profil', 'update', 'own');

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('R003', 'Insertion des permissions RBAC V2 — 85 permissions (module×action×scope)');
```

---

### R004 — Affecter les permissions aux rôles

```sql
-- ════════════════════════════════════════════════════════════════════════════
-- R004 : Attribution des permissions aux 7 rôles selon la matrice V2
-- Prérequis : R001, R002, R003
-- ════════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

-- Helper : récupérer l'ID d'un rôle par slug
-- Utilisation : (SELECT id FROM roles WHERE slug = 'admin')

-- ── ADMIN : toutes les permissions ───────────────────────────────────────────
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT id FROM `roles` WHERE slug = 'admin'), p.id
FROM `permissions` p;

-- ── DIRECTEUR ────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT id FROM `roles` WHERE slug = 'directeur'), p.id
FROM `permissions` p WHERE p.code IN (
    -- Élèves
    'eleves.view','eleves.create','eleves.update','eleves.delete','eleves.export',
    -- Enseignants
    'enseignants.view','enseignants.create','enseignants.update','enseignants.delete','enseignants.export',
    -- Classes
    'classes.view','classes.create','classes.update','classes.delete',
    -- Matières
    'matieres.view','matieres.create','matieres.update','matieres.delete',
    -- Notes
    'notes.view','notes.create','notes.update','notes.delete','notes.export',
    -- Bulletins
    'bulletins.view','bulletins.print','bulletins.export','bulletins.approve',
    -- Absences
    'absences.view','absences.create','absences.update','absences.delete','absences.export','absences.approve',
    -- Comptabilité
    'comptabilite.view','comptabilite.export','comptabilite.print',
    -- Paiements
    'paiements.view','paiements.export','paiements.print',
    -- Dépenses
    'depenses.view','depenses.export',
    -- Emploi du temps
    'emploi_du_temps.view','emploi_du_temps.create','emploi_du_temps.update','emploi_du_temps.delete','emploi_du_temps.print',
    -- Annonces
    'annonces.view','annonces.create','annonces.update','annonces.delete',
    -- Notifications
    'notifications.view','notifications.create','notifications.delete',
    -- Utilisateurs (pas delete)
    'users.view','users.create','users.update','users.export',
    -- Rapports
    'rapports.view','rapports.export','rapports.print',
    -- Profil
    'profil.view.own','profil.update.own'
);

-- ── SECRÉTAIRE ────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT id FROM `roles` WHERE slug = 'secretaire'), p.id
FROM `permissions` p WHERE p.code IN (
    -- Élèves
    'eleves.view','eleves.create','eleves.update','eleves.export',
    -- Classes
    'classes.view',
    -- Matières
    'matieres.view',
    -- Notes
    'notes.view',
    -- Bulletins
    'bulletins.view','bulletins.print',
    -- Absences
    'absences.view','absences.create','absences.update','absences.export','absences.approve',
    -- Comptabilité
    'comptabilite.view','comptabilite.create','comptabilite.print',
    -- Paiements
    'paiements.view','paiements.create','paiements.print',
    -- Emploi du temps
    'emploi_du_temps.view','emploi_du_temps.print',
    -- Annonces
    'annonces.view','annonces.create','annonces.update','annonces.delete',
    -- Notifications
    'notifications.view',
    -- Rapports
    'rapports.view','rapports.export','rapports.print',
    -- Profil
    'profil.view.own','profil.update.own'
);

-- ── COMPTABLE ─────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT id FROM `roles` WHERE slug = 'comptable'), p.id
FROM `permissions` p WHERE p.code IN (
    -- Lecture seule élèves
    'eleves.view',
    -- Lecture seule classes/enseignants
    'classes.view','enseignants.view','matieres.view',
    -- Comptabilité complète
    'comptabilite.view','comptabilite.create','comptabilite.update','comptabilite.delete','comptabilite.export','comptabilite.print',
    -- Paiements complets
    'paiements.view','paiements.create','paiements.update','paiements.delete','paiements.export','paiements.print',
    -- Dépenses complètes
    'depenses.view','depenses.create','depenses.update','depenses.delete','depenses.export',
    -- Rapports
    'rapports.view','rapports.export','rapports.print',
    -- Annonces
    'annonces.view',
    -- Notifications
    'notifications.view.own',
    -- Profil
    'profil.view.own','profil.update.own'
);

-- ── ENSEIGNANT ────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT id FROM `roles` WHERE slug = 'enseignant'), p.id
FROM `permissions` p WHERE p.code IN (
    -- Lecture élèves/classes/matières
    'eleves.view','classes.view','matieres.view',
    -- Notes (ses classes uniquement — filtrage métier dans le Controller)
    'notes.view','notes.create','notes.update','notes.export',
    -- Bulletins
    'bulletins.view','bulletins.print',
    -- Absences (ses classes)
    'absences.view','absences.create','absences.update','absences.approve',
    -- Emploi du temps
    'emploi_du_temps.view','emploi_du_temps.view.own','emploi_du_temps.print.own',
    -- Annonces
    'annonces.view',
    -- Notifications
    'notifications.view.own',
    -- Profil
    'profil.view.own','profil.update.own'
);

-- ── PARENT ────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT id FROM `roles` WHERE slug = 'parent'), p.id
FROM `permissions` p WHERE p.code IN (
    -- Notes propres de ses enfants
    'notes.view.own',
    -- Bulletins propres
    'bulletins.view.own','bulletins.print.own','bulletins.export.own',
    -- Absences propres + soumettre justification
    'absences.view.own','absences.approve.own',
    -- Paiements propres
    'paiements.view.own','paiements.print.own',
    -- Comptabilité own
    'comptabilite.view.own',
    -- Emploi du temps propre (enfants)
    'emploi_du_temps.view.own','emploi_du_temps.print.own',
    -- Annonces
    'annonces.view',
    -- Notifications propres
    'notifications.view.own',
    -- Profil
    'profil.view.own','profil.update.own'
);

-- ── ÉLÈVE ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT id FROM `roles` WHERE slug = 'eleve'), p.id
FROM `permissions` p WHERE p.code IN (
    -- Notes propres
    'notes.view.own',
    -- Bulletins propres
    'bulletins.view.own','bulletins.print.own',
    -- Absences propres
    'absences.view.own',
    -- Emploi du temps propre
    'emploi_du_temps.view.own','emploi_du_temps.print.own',
    -- Annonces
    'annonces.view',
    -- Notifications propres
    'notifications.view.own',
    -- Profil
    'profil.view.own','profil.update.own'
);

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('R004', 'Attribution des permissions aux 7 rôles selon matrice RBAC V2');
```

---

### R005 — Créer `user_roles` et initialiser depuis `users.role`

```sql
-- ════════════════════════════════════════════════════════════════════════════
-- R005 : Création de user_roles + initialisation depuis users.role (ENUM V1)
-- Prérequis : R001
-- ════════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

CREATE TABLE IF NOT EXISTS `user_roles` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED  NOT NULL,
    `role_id`    INT UNSIGNED  NOT NULL,
    `granted_by` INT UNSIGNED  NULL,
    `granted_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME      NULL,
    `actif`      TINYINT(1)    NOT NULL DEFAULT 1,
    `note`       VARCHAR(255)  NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_role` (`user_id`, `role_id`),
    INDEX `idx_ur_user`    (`user_id`),
    INDEX `idx_ur_role`    (`role_id`),
    INDEX `idx_ur_actif`   (`actif`),
    INDEX `idx_ur_expires` (`expires_at`),
    CONSTRAINT `fk_ur_user`       FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_ur_role`       FOREIGN KEY (`role_id`)    REFERENCES `roles`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_ur_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialiser user_roles depuis users.role (migration V1 → V2)
-- Pour chaque utilisateur, créer une entrée dans user_roles
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`, `note`)
SELECT
    u.id,
    r.id,
    'Migré automatiquement depuis users.role (RBAC V2 initialisation)'
FROM `users` u
JOIN `roles` r ON r.slug = u.role
WHERE u.actif = 1;

-- Vérification : compter les utilisateurs sans user_roles (cas d'erreur)
-- SELECT COUNT(*) FROM users WHERE id NOT IN (SELECT user_id FROM user_roles);

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('R005', 'Création user_roles + migration initiale depuis users.role ENUM');
```

---

### R006 — Vue SQL pour le chargement des permissions (helper)

```sql
-- ════════════════════════════════════════════════════════════════════════════
-- R006 : Vue v_user_permissions pour simplifier le chargement en PHP
-- Prérequis : R001-R005
-- ════════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

CREATE OR REPLACE VIEW `v_user_permissions` AS
    SELECT
        ur.user_id,
        p.code      AS permission_code,
        p.module,
        p.action,
        p.scope,
        r.slug      AS role_slug,
        r.label     AS role_label,
        ur.expires_at
    FROM `user_roles` ur
    JOIN `roles`            r  ON r.id  = ur.role_id
    JOIN `role_permissions` rp ON rp.role_id = r.id
    JOIN `permissions`      p  ON p.id  = rp.permission_id
    WHERE ur.actif = 1
      AND r.actif  = 1
      AND (ur.expires_at IS NULL OR ur.expires_at > NOW());

-- Usage PHP côté RbacService :
-- SELECT DISTINCT permission_code FROM v_user_permissions WHERE user_id = ?

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('R006', 'Création vue v_user_permissions pour chargement des permissions à la connexion');
```

---

## 7. STRATÉGIE DE TRANSITION V1 → V2

### 7.1 Principe — Transition en 4 phases, zéro downtime

```
PHASE 0 (actuel)          PHASE 1 (après R001-R006)    PHASE 2 (code adapté)    PHASE 3 (fin)
─────────────────────────────────────────────────────────────────────────────────────────────
config/permissions.php    config/ + DB coexistent       DB seul                  config/ supprimé
users.role ENUM           users.role + user_roles        user_roles seul          users.role supprimé
can() lit session         can() lit session              can() lit session         users.role peut rester
                          Chargement depuis DB           Chargement depuis DB
                          (RbacService::load())
```

---

### 7.2 Phase 0 → Phase 1 (AUJOURD'HUI — sans modifier le code)

Exécuter R001 à R006. L'application continue de fonctionner exactement comme avant car :
- `config/permissions.php` n'est pas supprimé
- `AuthController::login()` n'est pas modifié — charge toujours depuis le fichier
- `can()` et `requirePermission()` sont inchangés
- Les nouvelles tables sont créées mais non consultées par le code V1

**Résultat :** Double source de vérité en DB, mais V1 reste actif.

---

### 7.3 Phase 1 → Phase 2 (modification minimale du code)

**Un seul fichier à modifier :** `app/Models/UserModel.php` — méthode `getPermissions()`.

```php
// AVANT (V1 — lit le fichier config)
public function getPermissions(string $role): array
{
    $map = require ROOT_PATH . '/config/permissions.php';
    return $map[$role] ?? [];
}

// APRÈS (V2 — lit la DB via la vue v_user_permissions)
public function getPermissions(string $role, int $userId): array
{
    $stmt = $this->db->prepare(
        "SELECT DISTINCT permission_code FROM v_user_permissions WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $perms = $stmt->fetchAll(\PDO::FETCH_COLUMN);

    // Fallback sécurisé si DB vide (ex: migration incomplète)
    if (empty($perms)) {
        $map = require ROOT_PATH . '/config/permissions.php';
        return $map[$role] ?? [];
    }

    return $perms;
}
```

**Et dans `AuthController::login()` :** Passer `$user->id` en plus du rôle.

```php
// AVANT
$permissions = $this->userModel->getPermissions($user->role);

// APRÈS
$permissions = $this->userModel->getPermissions($user->role, (int)$user->id);
```

**Cela ne casse rien :** Le format de retour (`string[]`) est identique. La session garde le même format. Tous les appels à `can()` et `requirePermission()` fonctionnent sans modification.

---

### 7.4 Phase 2 → Phase 3 (nettoyage final)

Après validation en production que le RBAC V2 fonctionne correctement :

1. Supprimer `config/permissions.php` (ou le garder comme documentation)
2. Optionnellement supprimer `users.role` ENUM (si `user_roles` est la source unique)
3. Mettre à jour les références à `$user['role']` dans le code qui ne doivent plus exister

**Note :** `users.role` peut rester indéfiniment comme cache de lecture rapide pour les vues qui affichent le rôle principal. L'important est que les **permissions** viennent de la DB.

---

### 7.5 Migration des codes V1 → V2 dans le code PHP

Le tableau suivant liste les substitutions à effectuer dans les contrôleurs lors de la Phase 2 :

| Code V1 | Code V2 | Fichier(s) concernés |
|---|---|---|
| `eleves.edit` | `eleves.update` | EleveController |
| `enseignants.edit` | `enseignants.update` | ProfesseurController |
| `classes.edit` | `classes.update` | ClasseController |
| `matieres.edit` | `matieres.update` | MatiereController |
| `notes.edit` | `notes.update` | NoteController |
| `notes.view_own` | `notes.view.own` | NoteController |
| `absences.edit` | `absences.update` | AbsenceController |
| `absences.view_own` | `absences.view.own` | AbsenceController |
| `absences.justify` | `absences.approve.own` | AbsenceController |
| `comptabilite.edit` | `comptabilite.update` | ComptabiliteController, DepenseController |
| `emploi_du_temps.edit` | `emploi_du_temps.update` | EmploiDuTempsController, SalleController, CreneauController |
| `emploi_du_temps.view_own` | `emploi_du_temps.view.own` | EmploiDuTempsController |
| `annonces.edit` | `annonces.update` | AnnonceController |
| `users.edit` | `users.update` | UtilisateurController |
| `notifications.manage` | `notifications.create` + `notifications.delete` | NotificationController |

**Stratégie de migration sécurisée :** Insérer les DEUX codes (V1 et V2) dans `role_permissions` pendant la transition, puis supprimer les codes V1 une fois le code PHP mis à jour.

---

### 7.6 Vérification d'intégrité pré-déploiement

```sql
-- Vérifier que tous les rôles ont des permissions
SELECT r.slug, COUNT(rp.permission_id) AS nb_permissions
FROM roles r
LEFT JOIN role_permissions rp ON rp.role_id = r.id
GROUP BY r.slug
ORDER BY r.ordre;
-- Attendu : 7 lignes, toutes avec nb_permissions > 0

-- Vérifier que tous les users actifs ont un user_role
SELECT COUNT(*) AS users_sans_role
FROM users
WHERE actif = 1
  AND id NOT IN (SELECT user_id FROM user_roles WHERE actif = 1);
-- Attendu : 0

-- Vérifier la cohérence users.role vs user_roles.role_id
SELECT u.id, u.email, u.role AS role_v1, r.slug AS role_v2
FROM users u
JOIN user_roles ur ON ur.user_id = u.id AND ur.actif = 1
JOIN roles r ON r.id = ur.role_id
WHERE u.role != r.slug;
-- Attendu : 0 lignes (incohérence entre V1 et V2)

-- Vérifier que admin a bien toutes les permissions
SELECT COUNT(*) AS perms_admin FROM role_permissions rp
JOIN roles r ON r.id = rp.role_id WHERE r.slug = 'admin';
-- Doit être égal à : SELECT COUNT(*) FROM permissions;
```

---

## RÉSUMÉ — Ce qui change, ce qui ne change pas

| Élément | V1 | V2 | Statut |
|---|---|---|---|
| `config/permissions.php` | Source de vérité | Fallback de sécurité | Conservé pendant transition |
| `users.role` ENUM | Rôle unique | Cache lecture | Conservé |
| Table `permissions` | Schéma partiel (V1) | Schéma complet (V2) | **Recréée** |
| Table `role_permissions` | ENUM role, non utilisée | FK role_id, utilisée | **Recréée** |
| Table `roles` | N'existe pas | 7 rôles système | **Créée** |
| Table `user_roles` | N'existe pas | Multi-rôles + audit | **Créée** |
| `Controller::can()` | Lit session | Lit session | **INCHANGÉ** |
| `Controller::requirePermission()` | Lit session | Lit session | **INCHANGÉ** |
| `AuthController::login()` | Charge depuis fichier | Charge depuis DB | 2 lignes modifiées |
| `UserModel::getPermissions()` | Lit fichier PHP | Lit DB (vue) | **1 méthode modifiée** |
| Codes de permissions | `module.edit` | `module.update` | Migration progressive |
| Scopes | `module.view_own` | `module.view.own` | Migration progressive |

---

*RBAC_V2.md — SCOLARIS | Migrations SQL préparées, code non modifié. En attente de validation pour passer à l'implémentation.*
