# Base de données — SCOLARIS V2

## 1. Vue d'ensemble

- **SGBD** : MySQL 8.4 (testé sur 8.4.7), moteur **InnoDB** partout, jeu de
  caractères `utf8mb4` / collation `utf8mb4_unicode_ci`.
- **51 tables** actuellement présentes dans la base de développement de référence.
- **Un seul schéma pour tous les établissements** ("Shared Database, Shared
  Schema") — voir `docs/technique/MULTI_TENANT.md` pour l'isolation.
- Connexion centralisée : `Core\Database` (singleton PDO), configuration dans
  `config/database.php` / variables `DB_*` du `.env`.

## 2. Conventions

| Convention | Détail |
|---|---|
| Clé primaire | `id` auto-incrémenté (`INT UNSIGNED` ou `BIGINT UNSIGNED` pour les tables à fort volume : `jobs`, `api_uploads`, `platform_backups`) |
| Isolation tenant | Colonne `etablissement_id` (voir §3), **toujours indexée** |
| Suppression | **Logique uniquement** — colonne `deleted_at DATETIME NULL`. Aucun `DELETE`/`DROP TABLE` physique sur des données utilisateur dans le code applicatif |
| Horodatage | `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `updated_at` avec `ON UPDATE CURRENT_TIMESTAMP` quand la mutabilité de la ligne le justifie |
| Nommage des tables de module | Préfixe par module : `finance_*`, `vs_*` (Vie Scolaire), `rh_*`, `doc_*`, `biblio_*`, `inv_*`, `bi_*`, `platform_*`, `etab_*`/`etablissement_*` (Multi-Tenant) |
| Statuts | `ENUM` plutôt que chaîne libre partout où l'ensemble de valeurs est fermé et stable |

## 3. Isolation multi-tenant au niveau schéma

Toute table portant des données propres à un établissement possède une colonne
`etablissement_id INT UNSIGNED` indexée. Liste courante (découverte dynamique via
`INFORMATION_SCHEMA.COLUMNS`, vérifiée Phase 14.12/15.1 — **19 tables**) :

```
absences, api_uploads, classes, eleves, enseignements, etab_roles, etab_settings,
etablissement_branding, etablissement_domains, jobs, matieres, notes, periodes,
platform_backups, platform_restores, professeurs, user_etablissements,
user_roles_etab, users
```

`platform_backups`/`platform_restores` portent aussi cette colonne mais sont des
**métadonnées système** (pas des données métier d'un établissement) — elles sont
explicitement exclues de toute opération de sauvegarde/restauration par tenant
(voir `docs/technique/MULTI_TENANT.md` §Sauvegardes).

## 4. Table pivot : `etablissements`

Registre central des tenants (blueprint `MULTI_TENANT_V2_BLUEPRINT.md` §4.2) :

| Colonne | Rôle |
|---|---|
| `slug` | Identifiant URL-safe unique (résolution sous-domaine/chemin) |
| `plan_id` | FK → `platform_plans` |
| `storage_quota_mb`, `max_users`, `max_eleves`, `max_documents`, `max_attachments` | Quotas effectifs (copiés depuis le plan à l'assignation) |
| `statut` | `ENUM('trial','active','suspended','cancelled','archived')` |
| `deleted_at` | Suppression logique |

## 5. Tables Multi-Tenant / SaaS (Phases 14.2-14.11)

| Table | Rôle |
|---|---|
| `etablissements`, `user_etablissements` | Registre tenant + appartenance multi-établissement |
| `etab_roles`, `etab_permissions`, `etab_role_permissions`, `user_roles_etab` | RBAC tenant-aware |
| `etablissement_branding`, `etab_settings` | Branding et paramètres par établissement |
| `etablissement_domains` | Domaines personnalisés / sous-domaines |
| `api_uploads` | Registre d'usage de stockage (calcul des quotas) |
| `jobs` | File d'attente asynchrone |
| `platform_operators`, `platform_plans`, `platform_backups`, `platform_restores`, `platform_analytics_snapshots` | Portail Super-Admin SaaS |

## 6. Migrations

- Runner : `database/migrate.php` (voir `docs/deploiement/INSTALLATION.md` §4 et
  `docs/developpeur/CONVENTIONS.md` §"Écrire une migration").
- Fichiers sous `database/migrations/`, identifiants `T0xx` pour la série
  Multi-Tenant (T001 à T018) et `M0xx`/`S0xx`/`R0xx`/`P0xx` pour les séries
  antérieures (Élèves/Sécurité, Shared Services, RBAC, Paramètres).
- Chaque migration retourne `['id', 'name', 'reversible', 'run', 'rollback']` et
  est **idempotente** (vérifie l'existence avant de créer/altérer).
- Traçabilité : table `migrations_log` (id, migration, name, applied_at,
  duration_ms, status, error).
- **24 migrations appliquées** dans l'environnement de référence à ce jour.

## 7. Intégrité référentielle

Vérifiée Phase 14.12 : **zéro ligne orpheline** détectée sur les 19 tables
tenant-scopées (`etablissement_id` pointant vers un `etablissements.id`
inexistant). Les contraintes `FOREIGN KEY` explicites sont utilisées avec
parcimonie dans ce projet — l'intégrité est en grande partie garantie
applicativement (Repositories/Services) plutôt que par des contraintes SQL
strictes, choix hérité de l'historique du projet.

## 8. Documents associés

- `DATABASE_V2.md` — audit détaillé historique (29 tables analysées, 12 migrations
  préparées, Phase Migration V2)
- `docs/technique/MULTI_TENANT.md` — détail de l'isolation par tenant
- `docs/deploiement/SAUVEGARDES.md` / `RESTAURATION.md` — stratégie de sauvegarde
  fondée sur cette même base
- Chaque module dispose de son propre schéma détaillé dans son `*_BLUEPRINT.md` à
  la racine du projet (ex. `FINANCE_BLUEPRINT_V2.md` §tables, `RH_V2_BLUEPRINT.md` §tables)
