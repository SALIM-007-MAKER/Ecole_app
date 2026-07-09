# V2 READINESS REPORT — SCOLARIS
## Audit de cohérence globale des documents de conception V2

> **Étape 8** de la migration SCOLARIS V2.
> Documents audités : BLUEPRINT_V2.md · DATABASE_V2.md · RBAC_V2.md · PARAMETRES_V2.md · SHARED_SERVICES.md · EVENTS_V2.md
> Documents de code existants : core/Event.php · core/Listener.php · core/EventDispatcher.php · app/Events/* · app/Listeners/* · app/Services/AuditService.php · app/Services/UploadService.php · config/events.php · app/Controllers/{Eleve,Absence,Note,Paiement}Controller.php
> Date d'audit : 2026-06-29
> Statut : **NE MODIFIE AUCUN FICHIER — LECTURE SEULE**

---

## TABLE DES MATIÈRES

1. [Bilan exécutif](#1-bilan-exécutif)
2. [Éléments validés](#2-éléments-validés)
3. [Incohérences détectées](#3-incohérences-détectées)
4. [Corrections nécessaires](#4-corrections-nécessaires)
5. [Ordre recommandé d'implémentation](#5-ordre-recommandé-dimplémentation)
6. [Matrice de risque globale](#6-matrice-de-risque-globale)

---

## 1. BILAN EXÉCUTIF

```
Axe audité            Statut      Niveau de risque
─────────────────     ─────────   ────────────────
Architecture Core     ✅ VALIDÉ   Faible
Database V2           ⚠️ RISQUES  CRITIQUE (C1)
RBAC V2               ⚠️ RISQUES  Modéré (M3, M4)
Paramètres V2         ⚠️ RISQUES  Modéré (M1)
Shared Services       ⚠️ RISQUES  Modéré (M2, C2)
Event System          ✅ VALIDÉ   Faible (I4, M5, M6)
Cohérence globale     ⚠️ PARTIEL  1 bloquant, 6 majeurs, 6 mineurs
```

**Verdict : La migration peut progresser** mais **la correction C1 est bloquante** avant toute exécution des migrations SQL M001 et M002. Les autres corrections sont importantes mais non bloquantes à court terme.

---

## 2. ÉLÉMENTS VALIDÉS

### 2.1 Architecture Core — Entièrement validée ✅

| Composant | Statut | Vérification |
|---|---|---|
| `Core\Event` (abstraite) | ✅ Implémenté | PHP 8.2.29 valide — `getName()`, `getFiredAt()`, `toArray()` |
| `Core\Listener` (interface) | ✅ Implémenté | `handle(Event): void` — signature correcte |
| `Core\EventDispatcher` (statique) | ✅ Implémenté | Isolation par try/catch par handler, error_log sur échec |
| PSR-4 autoloader | ✅ Étendu | `App\Events\\` et `App\Listeners\\` ajoutés dans public/index.php |
| `config/events.php` | ✅ Correct | Retourne array EventClass → [Handler instances] |
| `Application::run()` | ✅ Intégré | Events chargés après routes.php, avant dispatch router |

**Responsabilités séparées — non mélangées :** Core\EventDispatcher ne connaît pas les contrôleurs, pas de redirect(), pas de render(). ✅

**Pas de dépendances circulaires dans le Core :** Event → rien. Listener → Event. EventDispatcher → Listener + Event. ✅

---

### 2.2 Event System — Validé ✅

| Événement | Dispatché depuis | Handlers | Validé |
|---|---|---|---|
| `EleveCreated` | EleveController::store() | AuditHandler, StatsCacheHandler | ✅ |
| `PaiementValide` | PaiementController::store() | AuditHandler, NotificationHandler | ✅ |
| `NoteAjoutee` | NoteController::storeSaisie() | AuditHandler, NotificationHandler | ✅ |
| `AbsenceCreee` | AbsenceController (storePointage) | AuditHandler, NotifHandler, StatsCacheHandler | ✅ |
| `DocumentGenere` | (non dispatché — voir C3) | AuditHandler | ⚠️ |

**Couplage faible validé :** Les contrôleurs ne connaissent que `EventDispatcher::dispatch()` + la classe d'événement. Zéro dépendance directe sur AuditService, NotificationService. ✅

**NoteAjoutee.isBatch() cohérent :** AuditHandler appelle `$event->isBatch()` — méthode présente (`eleveId === 0`). StoreSaisie dispatche avec eleveId=0 (batch) → cohérent. ✅

**StatsCacheHandler sécurisé :** Utilise purge session `$_SESSION['_stats_cache_*']`, pas de dépendance sur StatisticsService (non implémenté). Pas de Fatal Error. ✅

---

### 2.3 AuditService — Validé ✅

| Aspect | Statut |
|---|---|
| PDO direct (`Database::getInstance()->getConnection()`) | ✅ Correct |
| Dégradation gracieuse (table absente → error_log, pas d'exception) | ✅ Correct |
| Filtrage des champs sensibles (password, token, api_key…) | ✅ Correct |
| Diff logUpdate (stocke seulement les champs modifiés) | ✅ Correct |
| IP X-Forwarded-For avec fallback REMOTE_ADDR | ✅ Correct |
| WRITE-ONLY (jamais de UPDATE/DELETE sur audit_logs) | ✅ Conforme au design |

---

### 2.4 UploadService — Validé ✅

- Validation MIME réelle via `mime_content_type()` (pas le type navigateur) ✅
- 6 types distincts avec chemins vers `storage/uploads/` (non public) ✅
- Route `/uploads/serve/` prévue (accès contrôlé avec auth) — route non encore créée (voir M2) ⚠️

---

### 2.5 RBAC V2 Design — Validé ✅

| Aspect | Statut |
|---|---|
| Backward-compatible : `can()` et `requirePermission()` inchangés | ✅ |
| 7 actions normalisées (view, create, update, delete, export, print, approve) | ✅ |
| 4 tables (roles, permissions, role_permissions, user_roles) | ✅ |
| Multi-rôles via `user_roles` | ✅ |
| Scopes global / own modélisés | ✅ |
| Correspondance V1 → V2 documentée (tableau §4.3 de RBAC_V2.md) | ✅ |
| Vue `v_user_permissions` prévue | ✅ |
| Session format inchangé : `['permissions' => ['eleves.view', ...]]` | ✅ |

---

### 2.6 Database V2 — Analyse validée ✅

- 29 tables inventoriées, 12 problèmes identifiés avec solutions ✅
- 12 migrations M001-M012 préparées ✅
- Groupes cohérents : Auth, Scolarité, Académique, Absences, Comptabilité, EDT, Notifications ✅

---

### 2.7 Paramètres V2 — Design validé ✅

- 51 paramètres catalogués, 14 modules dans le registre ✅
- Chiffrement AES-256-CBC pour secrets avec APP_KEY ✅
- Règle `.env reste intouchable` : infra vs métier bien séparé ✅
- 10 sections UI claires avec routes ✅

---

## 3. INCOHÉRENCES DÉTECTÉES

### 3.1 Niveau CRITIQUE — Bloquant

#### [C1] Migrations M001 (notes) et M002 (absences) silencieusement inefficaces

**Problème :** `DATABASE_V2.md` le documente lui-même (DB-001, DB-002) : les tables `notes` et `absences` existent déjà en V1 avec un schéma différent. Toute migration utilisant `CREATE TABLE IF NOT EXISTS` ne fera **strictement rien** — le code PHP V2 qui lit `controle_id`, `absent`, `statut_justif` fonctionnera mais les colonnes sont absentes.

```
V1 notes     : eleve_id, matiere_id, trimestre, note, type_note, date_note
V2 notes     : eleve_id, controle_id, note, absent, appreciation  ← colonnes V2 absentes
V1 absences  : eleve_id, date_absence, motif, justifie (TINYINT simple)
V2 absences  : eleve_id, classe_id, date_absence, session, type, duree_retard, statut_justif ← absentes
```

**Impact :** FATAL. Toutes les saisies de notes V2 crashent (colonne `controle_id` inconnue). Le pointage des absences V2 crashe (colonne `session`, `type` inconnues).

**Correction requise :** Voir COR-1 ci-dessous.

---

### 3.2 Niveau MAJEUR — À corriger avant la phase concernée

#### [M1] Services Shared non implémentés (~60% de la couche)

| Service | Statut | Impact si manquant |
|---|---|---|
| `ParametreService` | ❌ Non implémenté | Module Paramètres entier bloqué ; EmailService/SmsService ne lisent pas DB |
| `BackupService` | ❌ Non implémenté | Section sauvegardes inaccessible |
| `ApiKeyService` | ❌ Non implémenté | Section API inaccessible |
| `PwaConfigService` | ❌ Non implémenté | NotificationService V2 push bloqué (VAPID depuis DB) |
| `ModuleService` | ❌ Non implémenté | Aucun module n'est activable/désactivable |
| `ExportService` | ❌ Non implémenté | Exports CSV/Excel encore dupliqués dans RapportController |
| `PdfService` | ❌ Non implémenté | Bulletins uniquement imprimables manuellement (layout=print) |
| `SearchService` | ❌ Non implémenté | Aucune recherche globale |
| `StatisticsService` | ❌ Non implémenté | StatsCacheHandler utilise session (contournement OK), mais futurs appels directs planteront |

**Aucune erreur immédiate** car StatsCacheHandler contourne StatisticsService. L'application fonctionne. Mais la dette grandit à chaque module qui reste sur l'ancienne logique.

---

#### [M2] UploadService implémenté mais non intégré

`app/Services/UploadService.php` existe mais les 4 contrôleurs qui uploadent des fichiers n'ont pas été migrés :

| Contrôleur | Ligne approx. | Problème actuel |
|---|---|---|
| `AuthController` | ~393 | Upload avatar dans `public/uploads/` |
| `ProfesseurController` | ~386 | Upload photo dans `public/uploads/` |
| `EleveController` | ~466 | Upload photo dans `public/uploads/` |
| `AbsenceController` | ~522 | Upload justification sans validation MIME réelle |

La route `/uploads/serve/{type}/{filename}` (accès contrôlé) n'est pas encore dans `config/routes.php`.

**Impact :** Fichiers exposés directement via HTTP sans contrôle d'authentification. Risque sécurité.

---

#### [M3] Renommages de permissions non migrés dans les contrôleurs

Le RBAC V2 renomme des codes de permission. Lors du passage DB-driven, les codes V1 dans les contrôleurs ne correspondront à aucune permission en DB.

| Code V1 dans les contrôleurs | Code V2 DB | Contrôleurs concernés |
|---|---|---|
| `notes.edit` | `notes.update` | NoteController (ligne 136) |
| `absences.edit` | `absences.update` | AbsenceController |
| `absences.justify` | `absences.approve.own` | AbsenceController |
| `classes.edit` | `classes.update` | ClasseController |
| `matieres.edit` | `matieres.update` | MatiereController |
| `emploi_du_temps.edit` | `emploi_du_temps.update` | EmploiDuTempsController |
| `enseignants.edit` | `enseignants.update` | ProfesseurController |
| `comptabilite.edit` | `comptabilite.update` | PaiementController |

**Impact :** Lors du passage RBAC V2, tous les utilisateurs (y compris admin) seront refusés pour ces actions → blocage silencieux.

---

#### [M4] RBAC V2 dépend de `UserModel::getPermissions()` mais la transition n'est pas planifiée

En V1, `AuthController::login()` charge les permissions depuis `config/permissions.php` (tableau PHP statique). En V2 il faut charger depuis `v_user_permissions` (vue SQL). Mais :
- La vue `v_user_permissions` n'est créée que dans R006.
- `UserModel::getPermissions()` n'est pas encore modifié.
- La transition est décrite dans RBAC_V2.md §3.4 mais aucun code n'existe.

**Impact :** Si les migrations RBAC sont exécutées sans modifier `UserModel::getPermissions()`, les utilisateurs gardent les anciennes permissions V1 (depuis config/permissions.php) → pas d'erreur visible, mais RBAC V2 n'est pas actif.

---

#### [M5] EleveController::importCsv() n'dispatche pas EleveCreated

EVENTS_V2.md §4.2 stipule : `EleveController::importCsv() → après chaque insertion réussie (fromCsvImport: true)`.
Seul `EleveController::store()` a reçu le dispatch. L'import CSV crée des élèves sans audit.

**Impact :** Aucun élève créé par import n'est tracé dans audit_logs.

---

#### [M6] NoteController::updateControle() recalcule sans dispatcher NoteAjoutee

```php
// updateControle() — ligne 179-182
$this->ctrlModel->update((int)$id, $data);
$this->noteModel->recalculerDepuisControle((int)$id);
// ← EventDispatcher::dispatch(new NoteAjoutee(...)) manquant ici
```

Modifier un contrôle recalcule toutes les moyennes mais ne déclenche ni audit ni notification.

---

### 3.3 Niveau MINEUR — À corriger progressivement

#### [I1] emplois_du_temps — Incohérence de types SQL

`salle_id INT` (pas UNSIGNED), `creneau_id INT` (pas UNSIGNED), `created_by INT` (pas de FK vers users). Documenté dans DATABASE_V2.md. Migration M005 devrait corriger. Fonctionnel en l'état.

---

#### [I2] push_subscriptions — Table non formellement migrée

Créée dynamiquement par PHP (PushController) mais sans script de migration officiel dans M001-M012. Doit être ajoutée dans M012 ou un nouveau script M013.

---

#### [I3] Dépendance Router → ModuleService non architecturalement documentée

BLUEPRINT_V2.md et PARAMETRES_V2.md mentionnent que les modules inactifs masquent leurs routes et menus. Mais `Core\Router` est dans le namespace `Core` et ne peut pas dépendre de `App\Services\ModuleService`. L'implémentation de cette fonctionnalité nécessite une décision architecturale (middleware, hook, ou injection de config dans Application::run()) qui n'est pas encore documentée.

**Risque :** Si implémenté naïvement, crée une dépendance Core → App (circulaire et interdite par les règles de la couche Shared).

---

#### [I4] NotificationHandler instancie PeriodeModel pour résoudre le nom de période

```php
// NotificationHandler::handle() — NoteAjoutee
(new PeriodeModel())->findById($event->periodeId)?->nom ?? "Période #{$event->periodeId}"
```

Une requête SQL dans un handler synchrone. Mineur en V2 (synchrone acceptable), mais anti-pattern à éviter en V3 (queue). À corriger : passer `periodeNom: string` directement dans NoteAjoutee.

---

#### [I5] BLUEPRINT_V2.md liste des modules H2 non couverts

Le Blueprint liste 12 modules dont Bibliothèque, Inventaire, Documents (module), Ressources Humaines, Réseau (H3). Ces modules n'ont aucun document de conception dans cette migration. Non bloquant — ils sont clairement labellés "H2" dans le Blueprint. Cohérent avec le scope V2.

---

#### [I6] DocumentGenere non dispatché depuis aucun déclencheur

`DocumentGenere` est défini (EVENTS_V2.md §8), AuditHandler l'écoute, mais :
- BulletinController n'a pas de dispatch (génération 100% navigateur)
- ExportService n'existe pas encore
- BackupService n'existe pas encore

Le handler existe mais n'est jamais appelé → pas d'audit des exports/documents. Non bloquant (application fonctionne), mais lacune d'audit RGPD.

---

## 4. CORRECTIONS NÉCESSAIRES

### COR-1 — OBLIGATOIRE avant M001 et M002 (Critique)

Remplacer les scripts M001 (notes) et M002 (absences) par un script ALTER TABLE sécurisé.

**Stratégie recommandée :**

```sql
-- M001 — notes V2 : migration sécurisée
-- Étape A : sauvegarder les données V1 si la table a la structure V1
ALTER TABLE notes
  RENAME TO notes_v1_backup;

-- Étape B : créer la table V2
CREATE TABLE notes (
  id           INT UNSIGNED PK AUTO_INCREMENT,
  eleve_id     INT UNSIGNED NOT NULL,
  controle_id  INT UNSIGNED NOT NULL,
  note         DECIMAL(5,2) NULL,
  absent       TINYINT(1) DEFAULT 0,
  appreciation VARCHAR(255) NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE(eleve_id, controle_id),
  FK eleve_id → eleves(id) ON DELETE CASCADE,
  FK controle_id → controles(id) ON DELETE CASCADE
);

-- Étape C : migration des données si possible (V1 notes n'ont pas de controle_id → données non migrables automatiquement)
-- → Les anciennes notes V1 sont dans notes_v1_backup pour référence manuelle.
```

**Même stratégie pour M002 (absences).**

> ⚠️ Les données notes V1 (`matiere_id, trimestre, note, type_note`) ne sont pas directement migrables vers V2 (`controle_id, note, absent`) car il n'existe pas de correspondance controle_id. Les données V1 seront perdues lors de la migration. Si des données existent en production, prévoir un export avant migration.

---

### COR-2 — RECOMMANDÉ avant activation RBAC (Majeur)

Pendant la période de transition (après R001-R006 mais avant migration des contrôleurs), injecter les anciens codes de permission V1 en doublon dans la table `permissions` :

```sql
-- Dans R006 ou un script de transition T001
INSERT INTO permissions (code, libelle, module, action, scope, is_system)
VALUES
  ('notes.edit',           'Modifier notes (V1 compat)',    'notes',      'update', 'global', 0),
  ('absences.edit',        'Modifier absences (V1 compat)', 'absences',   'update', 'global', 0),
  ('absences.justify',     'Justifier absence (V1 compat)', 'absences',   'approve','own',    0),
  ('classes.edit',         'Modifier classes (V1 compat)',  'classes',    'update', 'global', 0),
  ('matieres.edit',        'Modifier matières (V1 compat)', 'matieres',   'update', 'global', 0),
  ('emploi_du_temps.edit', 'Modifier EDT (V1 compat)',      'emploi_du_temps','update','global',0),
  ('enseignants.edit',     'Modifier enseignants (V1)',     'enseignants','update', 'global', 0),
  ('comptabilite.edit',    'Modifier compta (V1 compat)',   'comptabilite','update','global', 0)
ON DUPLICATE KEY UPDATE libelle = VALUES(libelle);
-- Puis affecter ces permissions aux rôles appropriés dans role_permissions
```

Cela permet aux contrôleurs de fonctionner sans modification pendant la transition.

---

### COR-3 — Dispatcher EleveCreated depuis importCsv()

```php
// EleveController::importCsv() — après chaque $id = $this->eleveModel->insert(...)
EventDispatcher::dispatch(new EleveCreated(
    eleveId:      $id,
    nom:          $row['nom'],
    prenom:       $row['prenom'],
    matricule:    $row['matricule'] ?? '',
    classeId:     (int)($row['classe_id'] ?? 0),
    createdById:  (int)$this->currentUser()['id'],
    fromCsvImport: true,
));
```

---

### COR-4 — Dispatcher NoteAjoutee depuis updateControle()

```php
// NoteController::updateControle() — après recalculerDepuisControle()
EventDispatcher::dispatch(new NoteAjoutee(
    controleId:  (int)$id,
    saisieParId: (int)$this->currentUser()['id'],
    classeId:    (int)$data['classe_id'],
    periodeId:   (int)$data['periode_id'],
    matiereId:   (int)$data['matiere_id'],
));
```

---

### COR-5 — Ajouter la route /uploads/serve/

```php
// config/routes.php
$router->get('/uploads/serve/{type}/{filename}', 'UploadController@serve');
// + créer UploadController::serve(string $type, string $filename)
//   → vérifie auth, construit le path storage/uploads/{type}/{filename},
//   → lit et envoie le fichier avec header Content-Type approprié
```

---

### COR-6 — Résoudre I3 (ModuleService + Router) via un hook Application

```php
// Application::run() — après chargement des events, avant dispatch router
if (class_exists('\App\Services\ModuleService')) {
    $inactiveModules = (new \App\Services\ModuleService())->getInactiveSlugs();
    $this->router->disableModules($inactiveModules);
    // Router::disableModules() filtre les routes par préfixe
}
```

Core\Router reçoit la liste des modules inactifs comme donnée externe (pas de dépendance directe sur ModuleService). ModuleService reste dans App\Services.

---

## 5. ORDRE RECOMMANDÉ D'IMPLÉMENTATION

### PHASE 1 — Infrastructure DB (prérequis de tout)

| Ordre | Action | Fichier | Dépendances | Risque |
|---|---|---|---|---|
| 1 | Exécuter S001 | `audit_logs` table | Aucune | Faible |
| 2 | Exécuter S002 | storage/ directories | Aucune | Faible |
| 3 | **COR-1** Corriger M001 | notes V2 avec sauvegarde | S001 exécuté | CRITIQUE — exporter les données avant |
| 4 | **COR-1** Corriger M002 | absences V2 avec sauvegarde | S001 exécuté | CRITIQUE |
| 5 | Exécuter M003-M012 | annees_scolaires, EDT, FKs notifs, push | M001, M002 faits | Modéré |

### PHASE 2 — RBAC (débloque la sécurité V2)

| Ordre | Action | Dépendances |
|---|---|---|
| 6 | Exécuter R001-R006 | Phase 1 complète |
| 7 | **COR-2** Insérer permissions compat V1 | R001-R006 exécutés |
| 8 | Implémenter `RbacService::loadUserPermissions()` | R006 (vue `v_user_permissions`) |
| 9 | Modifier `AuthController::login()` → charge permissions depuis DB | RbacService prêt |
| 10 | Tester : tous les rôles ont leurs permissions en session | AuthController modifié |

### PHASE 3 — Paramètres (débloque la config centralisée)

| Ordre | Action | Dépendances |
|---|---|---|
| 11 | Exécuter P001-P004 | Phase 2 complète |
| 12 | Implémenter `ParametreService` (get/set/cache/chiffrement) | P001 (table parametres) |
| 13 | Implémenter `ModuleService` + **COR-6** (Router hook) | P003 (table modules) |
| 14 | Implémenter `ParametresController` (10 sections) | ParametreService |
| 15 | Implémenter `BackupService` (PDO-only en priorité) | P004 (backup_logs) |
| 16 | Implémenter `ApiKeyService`, `PwaConfigService` | ParametreService |
| 17 | Connecter EmailService + SmsService → ParametreService (sections 5, 6) | ParametreService |

### PHASE 4 — Services Shared restants

| Ordre | Action | Dépendances |
|---|---|---|
| 18 | **COR-5** Route /uploads/serve/ + UploadController::serve() | config/routes.php |
| 19 | Migrer 4 contrôleurs vers UploadService (Auth, Prof, Eleve, Absence) | COR-5 |
| 20 | Implémenter `StatisticsService` (méthodes + clearCache()) | Phase 1 |
| 21 | Implémenter `ExportService` (CSV, Excel) | StatisticsService |
| 22 | Migrer RapportController + EleveController vers ExportService | ExportService |
| 23 | Décision Dompdf vs TCPDF → implémenter `PdfService` | Décision externe |
| 24 | Implémenter `SearchService` | Phase 1 |

### PHASE 5 — Complétion Events

| Ordre | Action | Dépendances |
|---|---|---|
| 25 | **COR-3** EleveCreated depuis importCsv() | Phase 1 |
| 26 | **COR-4** NoteAjoutee depuis updateControle() | Phase 1 |
| 27 | DocumentGenere depuis ExportService | ExportService, Phase 4 |
| 28 | DocumentGenere depuis BackupService | BackupService, Phase 3 |
| 29 | DocumentGenere depuis BulletinController (si PdfService implémenté) | PdfService |

### PHASE 6 — Migration des codes de permission dans les contrôleurs

| Ordre | Action | Dépendances |
|---|---|---|
| 30 | Migrer `*.edit` → `*.update` dans tous les contrôleurs | Phase 2 complète |
| 31 | Migrer `absences.justify` → `absences.approve.own` | Phase 2 complète |
| 32 | Migrer `*.view_own` → `*.view.own` | Phase 2 complète |
| 33 | Supprimer les permissions compat V1 (COR-2) une fois tous les contrôleurs migrés | Phase 6 |

---

## 6. MATRICE DE RISQUE GLOBALE

```
ID    Intitulé                                    Sévérité    Effort    Ordre
──    ─────────────────────────────────────────   ─────────   ──────    ─────
C1    M001/M002 CREATE IF NOT EXISTS inefficace   CRITIQUE    Medium    Phase 1 (avant tout)
M1    Services Shared non implémentés (~60%)      MAJEUR      Grand     Phases 3, 4
M2    UploadService non intégré (sécurité)        MAJEUR      Medium    Phase 4
M3    Renommages permissions dans contrôleurs     MAJEUR      Medium    Phase 6
M4    UserModel::getPermissions() non migré       MAJEUR      Petit     Phase 2
M5    EleveCreated manquant dans importCsv        MAJEUR      Trivial   Phase 5
M6    NoteAjoutee manquant dans updateControle    MAJEUR      Trivial   Phase 5
I1    emplois_du_temps INT vs UNSIGNED            Mineur      Trivial   M005
I2    push_subscriptions non formellement migrée  Mineur      Trivial   M013
I3    Router → ModuleService (architecture)       Mineur      Medium    Phase 3
I4    PeriodeModel dans NotificationHandler       Mineur      Trivial   Futur
I5    Modules H2 hors scope (Biblio, Inventaire)  Aucun       —         Hors scope V2
I6    DocumentGenere non dispatché (audit RGPD)   Mineur      Medium    Phase 5
```

---

## Résumé des décisions à prendre

Avant ou pendant l'implémentation, les décisions suivantes doivent être tranchées explicitement :

| # | Décision | Options | Impact |
|---|---|---|---|
| D1 | **Moteur PDF** | Dompdf (meilleur CSS) vs TCPDF (plus stable, pur PHP) | Phase 4, PdfService |
| D2 | **Données notes V1** | Exporter avant migration ou abandonner | M001 (CRITIQUE) |
| D3 | **Données absences V1** | Exporter avant migration ou abandonner | M002 (CRITIQUE) |
| D4 | **Période de transition RBAC** | Combien de temps garder les permissions compat V1 | Phase 6 |
| D5 | **BackupService** | mysqldump (si shell dispo) vs PDO pur (toujours dispo sous WAMP) | Phase 3 |

---

*V2_READINESS_REPORT.md — SCOLARIS | Audit de cohérence. Aucun fichier modifié. En attente de validation.*
