# ÉTAPE 9 — RAPPORT DE COMPLÉTION
## Correction des bloquants avant Phase 1

> Date : 2026-06-29
> Statut : **COMPLÉTÉ — 16 fichiers PHP, syntaxe 16/16 OK, dry-run OK**

---

## 1. FICHIERS CRÉÉS

### Migration Runner
| Fichier | Rôle |
|---|---|
| `database/migrate.php` | Runner CLI PHP — détecte/exécute les migrations, table `migrations_log` |

### Scripts de migration
| Fichier | ID | Description |
|---|---|---|
| `database/migrations/S001_audit_logs.php` | S001 | Crée `audit_logs` (BIGINT id, 9 colonnes, FK users) |
| `database/migrations/M001_notes_v2.php` | M001 | Migration sécurisée `notes` V1→V2 (backup + recréation) |
| `database/migrations/M002_absences_v2.php` | M002 | Migration sécurisée `absences` V1→V2 + migration partielle données |
| `database/migrations/M003_annees_scolaires.php` | M003 | Référentiel `annees_scolaires`, peuplé depuis les valeurs existantes |
| `database/migrations/R001_rbac_tables.php` | R001 | Tables RBAC V2 : `rbac_roles`, `rbac_role_permissions`, `rbac_user_roles` + extension `permissions` + 85 permissions + matrice rôles |

### Nouveaux événements
| Fichier | Événement | Déclencheur |
|---|---|---|
| `app/Events/ImportCsvCompleted.php` | `ImportCsvCompleted` | Après import CSV complet (summary) |
| `app/Events/ControleUpdated.php` | `ControleUpdated` | Après modification et recalcul d'un contrôle |

### Nouveau contrôleur
| Fichier | Route | Rôle |
|---|---|---|
| `app/Controllers/UploadController.php` | `GET /uploads/serve/{type}/{filename}` | Sert les fichiers uploadés de façon sécurisée (auth + anti path-traversal) |

---

## 2. FICHIERS MODIFIÉS

| Fichier | Modification |
|---|---|
| `app/Models/UserModel.php` | `getPermissions()` → RBAC V2 DB-driven + fallback config V1 + alias V1↔V2 |
| `app/Controllers/AuthController.php` | `login()` → passe `userId` à `getPermissions()` |
| `app/Controllers/EleveController.php` | `handlePhoto()` → `UploadService` ; `import()` → dispatch `EleveCreated` par ligne + `ImportCsvCompleted` |
| `app/Controllers/NoteController.php` | `updateControle()` → dispatch `ControleUpdated` |
| `app/Models/EleveModel.php` | `importFromCsv()` → retourne `ids[]` (nouveaux IDs insérés) |
| `app/Listeners/AuditHandler.php` | Gère `ImportCsvCompleted` + `ControleUpdated` |
| `app/Listeners/StatsCacheHandler.php` | Gère `ImportCsvCompleted` (purge cache stats) |
| `config/events.php` | Enregistre `ImportCsvCompleted` + `ControleUpdated` |
| `config/routes.php` | Route `/uploads/serve/{type}/{filename}` |

---

## 3. SCRIPTS SQL CRÉÉS

### Stratégies de migration sécurisées

**S001 — audit_logs**
- `CREATE TABLE IF NOT EXISTS` — nouvelle table, aucun risque.

**M001 — notes V2 (C1 BLOQUANT corrigé)**
- Détecte si V1 (`matiere_id` présent, `controle_id` absent) ou V2 déjà.
- Si V1 : `RENAME TABLE notes TO notes_v1_backup` (données préservées) puis `CREATE TABLE notes` V2.
- Si V2 déjà : ajoute `updated_at` si manquante, et sort.
- Idempotent.

**M002 — absences V2 (C1 BLOQUANT corrigé)**
- Même stratégie que M001.
- Tente une migration partielle des données V1 → V2 (colonnes communes : `eleve_id`, `date_absence`, `motif`, `justifiee → statut_justif`).
- Si la migration partielle échoue : `error_log()` non bloquant, données V1 dans `absences_v1_backup`.

**M003 — annees_scolaires**
- `CREATE TABLE IF NOT EXISTS` — nouvelle table.
- Peuple depuis les valeurs `annee_scolaire` distinctes trouvées dans les tables existantes.
- Marque l'année en cours comme active.

**R001 — RBAC V2**
- Étend `permissions` V1 avec 3 colonnes (`action`, `scope`, `is_system`) via `ALTER TABLE` conditionnel.
- Crée `rbac_roles` (7 rôles), `rbac_role_permissions`, `rbac_user_roles`.
- Insère 85+ permissions (V2 + aliases compat V1).
- Affecte les permissions aux rôles.
- Peuple `rbac_user_roles` depuis `users.role`.

---

## 4. TESTS EFFECTUÉS

### Syntaxe PHP (16/16 OK)
```
OK  database/migrate.php
OK  database/migrations/S001_audit_logs.php
OK  database/migrations/M001_notes_v2.php
OK  database/migrations/M002_absences_v2.php
OK  database/migrations/M003_annees_scolaires.php
OK  database/migrations/R001_rbac_tables.php
OK  app/Models/UserModel.php
OK  app/Controllers/AuthController.php
OK  app/Controllers/EleveController.php
OK  app/Controllers/NoteController.php
OK  app/Controllers/UploadController.php
OK  app/Events/ImportCsvCompleted.php
OK  app/Events/ControleUpdated.php
OK  app/Listeners/AuditHandler.php
OK  app/Listeners/StatsCacheHandler.php
OK  config/events.php
```

### Migration Runner — Dry-run
```
=== SCOLARIS V2 — Migration Runner ===
  [APPLIQUER] M001 — Migration sécurisée notes → schéma V2 (--dry-run)
  [APPLIQUER] M002 — Migration sécurisée absences → schéma V2 (--dry-run)
  [APPLIQUER] M003 — Créer le référentiel annees_scolaires (--dry-run)
  [APPLIQUER] R001 — Tables RBAC V2 (--dry-run)
  [APPLIQUER] S001 — Créer la table audit_logs (--dry-run)
=== Résultat : 0 appliqués, 0 ignorés, 0 erreurs ===
```

---

## 5. COMPATIBILITÉ V1

| Élément | Compatibilité | Détail |
|---|---|---|
| `can()` + `requirePermission()` | ✅ Inchangés | Signatures non modifiées dans Controller |
| Permissions V1 (`notes.edit`, `absences.justify`…) | ✅ Préservées | Retournées par `getPermissions()` + alias V2 ajoutés |
| Tables DB V1 (`notes_v1_backup`, `absences_v1_backup`) | ✅ Préservées | `RENAME TABLE` conserve toutes les données |
| Données élèves importées via CSV | ✅ Non impacté | Seul le retour de `importFromCsv()` a été étendu (`ids[]`) |
| Upload élèves legacy (`public/uploads/eleves/`) | ✅ Servi | `UploadController::serve()` cherche aussi le chemin legacy |
| Session format | ✅ Inchangé | `['permissions' => [...]]` — identique V1 |

---

## 6. RISQUES RESTANTS

| # | Risque | Niveau | Action requise |
|---|---|---|---|
| R1 | Données `notes` V1 non migrables automatiquement (pas de `controle_id`) | Modéré | M001 backup dans `notes_v1_backup` — migration manuelle si nécessaire |
| R2 | Migration RBAC R001 non exécutée — UserModel reste sur config V1 | Faible | Fonctionnel : fallback silencieux vers config/permissions.php |
| R3 | `PdfService`, `ExportService`, `SearchService`, `StatisticsService` non implémentés | Modéré | Prévu Phase 4 — aucune régression V1 |
| R4 | Photos élèves déjà uploadées dans `public/uploads/eleves/` | Faible | UploadController sert les deux chemins (storage/ + legacy) |
| R5 | `NotificationService` push (VAPID V2) non activé | Faible | PwaConfigService non implémenté — push reste en V1 |

---

## 6b. CORRECTION POST-MIGRATION P001

**Anomalie détectée** : `bulletins.view` (code V1 vérifié par BulletinController) absent des rôles `eleve` et `parent` dans RBAC V2.

**Cause** : R001 assignait `bulletins.view.own` + `bulletins.print.own` mais omettait le code V1 `bulletins.view`, present en DB (id=61) mais non affecté à ces rôles.

**Audit** : script `audit_rbac_gaps.php` — comparaison permissions V1 config vs RBAC V2 DB — résultat initial : `[parent] manquantes: bulletins.view`, `[eleve] manquantes: bulletins.view`.

**Correction** : migration `P001_rbac_fix_bulletins_view.php` — `INSERT IGNORE INTO rbac_role_permissions` pour les deux rôles.

```
[APPLIQUER] P001 — Correction RBAC — bulletins.view pour eleve et parent → OK (9ms)
```

**Audit post-P001** : `[admin] OK | [directeur] OK | [secretaire] OK | [comptable] OK | [enseignant] OK | [parent] OK | [eleve] OK`

Note : `rbac.manage` non trouvé pour `admin` → attendu (permission V2 pure, aucun contrôleur V1 ne la vérifie).

---

## 7. COMMANDE D'EXÉCUTION DES MIGRATIONS

```bash
# À exécuter dans le terminal WAMP sur la machine serveur :
C:/wamp64/bin/php/php8.2.29/php.exe database/migrate.php

# Exécuter une migration spécifique :
C:/wamp64/bin/php/php8.2.29/php.exe database/migrate.php --id=S001

# Dry-run (simulation sans modification) :
C:/wamp64/bin/php/php8.2.29/php.exe database/migrate.php --dry-run
```

**Ordre exécuté (2026-06-30 04:03) :**
| Migration | Durée | Statut |
|---|---|---|
| M001 — notes V2 | 34ms | ✅ success |
| M002 — absences V2 | 2ms | ✅ success |
| M003 — annees_scolaires | 74ms | ✅ success |
| R001 — RBAC V2 | 1033ms | ✅ success |
| S001 — audit_logs | 75ms | ✅ success |
| P001 — RBAC fix bulletins.view | 9ms | ✅ success |

---

*ÉTAPE_9_RAPPORT.md — SCOLARIS | 6 migrations appliquées, 7/7 rôles RBAC validés, prêt pour Phase 1.*
