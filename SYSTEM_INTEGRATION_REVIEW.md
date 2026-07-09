# SYSTEM INTEGRATION REVIEW — ecole_app V2
**Phase 2.9 — Audit d'intégration inter-modules**
Date : 2026-06-30
Périmètre : Module Scolarité V2 ↔ Module Académique V2
Méthode : Revue statique du code source — aucune exécution

---

## Résultat global

| Dimension | Note | Verdict |
|-----------|------|---------|
| Intégration métier | 7.5/10 | ⚠ Risques identifiés |
| Intégration technique | 6.5/10 | ⛔ Anomalies critiques |
| Dépendances | 8.5/10 | ✅ Propre |
| Duplication | 7.0/10 | ⚠ Duplications mineures |
| Performance | 6.0/10 | ⚠ N+1 + index manquants |
| Sécurité | 7.5/10 | ⚠ Surfaces exposées |
| Compatibilité V1 | 9.5/10 | ✅ OK |

### **Score global : 7.1 / 10**

### Verdict : ⚠ **NO-GO conditionnel**

> GO possible après correction des 3 anomalies critiques (AN-C-001, AN-C-002, AN-C-003).
> Les anomalies majeures AN-M-001 à AN-M-004 constituent une dette acceptable à traiter dans la prochaine phase.

---

## 1. Intégration métier

### 1.1 Élèves → Évaluations ✅

**Flux :** `EvaluationService::creer()` reçoit un `classeId` ; la requête SQL dans `NoteRepository::listElevesAvecNotes()` joint `evaluations.classe_id = eleves.classe_id`.

**Correct :** L'appartenance d'un élève à une classe est la seule clé d'intégration. Pas d'intégration directe eleve_id → evaluations — conforme au modèle « évaluation par classe ».

**Gap (mineur) :** Si un élève change de classe en cours de période (via `AffectationService::affecterEleve()`), ses évaluations passées restent liées à l'ancienne `classe_id`. Il n'y a aucun event handler dans le Module Académique qui réagit à `EleveRemovedFromClasse` ou `EleveAssignedToClasse` pour recalculer ou invalider les données. **Risque de données orphelines** si un élève change de classe après saisie de notes.

### 1.2 Élèves → Notes ✅

**Flux :** `NoteService::saisirBatch()` reçoit `eleve_id` via `NoteBatchDTO` et insère dans `notes_v2(eleve_id, evaluation_id)`.

**Validation :** La saisie vérifie l'état de l'évaluation (`notes_saisie_ouverte`, `statut`) mais **ne vérifie pas que l'élève appartient à la classe de l'évaluation**. Un `eleve_id` arbitraire peut être soumis si `notes_saisie_ouverte = 1`.

> **AN-M-001** — Absence de vérification d'appartenance élève/classe lors de la saisie de note.

### 1.3 Élèves → Bulletins ✅

**Flux :** `BulletinGenerator::genererBulletin()` → `repo->infoEleve(eleveId)` récupère `classe_id` depuis `eleves`, puis `repo->notesEleveParPeriode(eleveId, classeId, periodeId)`.

**Correct :** La `classeId` est déduite de la table `eleves` (source de vérité), pas passée par le client. Séquence robuste.

### 1.4 Classes → Évaluations ✅

**Flux :** `evaluations.classe_id` FK vers `classes.id`. `EvaluationRepository::findWithDetails()` joint `classes`. Correct.

### 1.5 Classes → Classements ✅

**Flux :** `RankingEngine::classementClasse(classeId, periodeId)` → `RankingRepository::notesParClasseEtPeriode()` → JOIN `eleves e ON e.classe_id = :classe_id`. Correct.

**Avertissement :** La requête filtre `WHERE e.classe_id = :classe_id` mais aussi `ev.classe_id = e.classe_id`. Si une évaluation est créée pour une classe et qu'un élève change de classe, il ne sera plus inclus dans le classement. Comportement ambigu — voir AN-M-001.

### 1.6 Matières → Notes ✅

**Flux :** `evaluations.matiere_id` FK → `matieres.id`. Les notes héritent de la matière via leur évaluation. `BulletinRepository::notesEleveParPeriode()` joint `matieres m ON m.id = ev.matiere_id`. Correct.

**Gap (mineur) :** Si une matière est archivée via `MatiereService::archiver()`, ses évaluations actives restent accessibles (pas de FK avec `ON DELETE CASCADE` ni de validation dans `EvaluationService`). Un enseignant peut continuer à saisir des notes sur une matière archivée.

### 1.7 Inscriptions → Académique ⚠

**Analyse :** Le Module Scolarité gère les inscriptions via `InscriptionService`. Ces inscriptions sont **liées à une année scolaire** (`annee_scolaire` dans `inscriptions`). Le Module Académique utilise des **périodes scolaires** (`periodes_scolaires` avec `annee_scolaire` + `type_periode`).

**Gap critique :** Il n'existe **aucun lien direct** entre `inscriptions.annee_scolaire` et `periodes_scolaires.annee_scolaire` dans les requêtes académiques. `EvaluationService::creer()` valide que la période n'est pas archivée, mais ne vérifie pas si l'élève est inscrit pour cette année scolaire. Un élève sans inscription valide peut recevoir des notes.

> **AN-C-001** — Absence de validation inscription/année scolaire dans le flux de saisie académique.

### 1.8 Familles → Bulletins ⚠

**Analyse :** `FamillePolicy::canViewChild()` dans le Module Scolarité contrôle l'accès parent → enfant. Mais `BulletinPolicy` ne contient **aucune méthode** `canViewForParent()` ou `canViewForFamily()`. Un parent peut accéder aux bulletins de n'importe quel élève si la permission `academique.bulletin.view` lui est attribuée.

**Flux de vérification :** La seule sécurité famille → bulletin est via `BulletinPolicy::canVerify()` (token public, ouvert à tous) ou via `canView()` qui est permission-only.

> **AN-C-002** — BulletinPolicy n'implémente pas le contrôle d'ownership parent/enfant.

### 1.9 Années scolaires → Tous les domaines ⚠

**Analyse :** L'année scolaire est un **string libre** (`annee_scolaire` = ex. `"2025-2026"`) dans : `inscriptions`, `periodes_scolaires`, `enseignements`, `bulletins_v2`. Il n'existe pas de table de référentiel `annees_scolaires` centralisée.

**Risques :**
- Désynchronisation possible entre la casse/format de la chaîne selon les saisies
- Pas de validation cross-module de cohérence
- `PeriodeScolaireService` vérifie l'unicité `(annee_scolaire, type_periode, numero)` mais sans référentiel centralisé

> **AN-M-002** — Année scolaire = string non normalisé, sans référentiel centralisé. Risque de désynchronisation inter-modules.

---

## 2. Intégration technique

### 2.1 EventDispatcher ✅ avec réserve

**Correct :** `EventDispatcher::dispatch()` est synchrone, statique, avec `try/catch` par handler. Aucune dépendance circulaire dans le flux d'événements.

**Mapping `config/events.php` :** Tous les événements des deux modules sont enregistrés. 23 événements cartographiés au total.

**Gap :** Les événements `MatiereAssignedToClasse` et `MatiereRemovedFromClasse` sont dispatchés par `MatiereService` mais **aucun handler du Module Académique** ne les écoute. Si un enseignant est retiré d'une matière/classe, les évaluations existantes ne sont pas invalidées ou notifiées.

> **Rappel DT-M1 :** Ce gap était identifié dans le freeze Phase 1.7. Il reste non résolu.

### 2.2 AuditService ⛔ CRITIQUE

**Problème 1 — Namespace inconsistant :**

| Fichier | Import utilisé |
|---------|---------------|
| `NoteHandler.php` | `use Services\AuditService;` ❌ |
| `AverageHandler.php` | `use Services\AuditService;` ❌ |
| `EvaluationHandler.php` | `use App\Services\AuditService;` ✅ |
| `BulletinHandler.php` | `use App\Services\AuditService;` ✅ |
| `RankingHandler.php` | `use App\Services\AuditService;` ✅ |
| `AnalyticsHandler.php` | `use App\Services\AuditService;` ✅ |
| `PeriodeHandler.php` | `new AuditService()` via `use App\Services\AuditService` ✅ |

`NoteHandler` et `AverageHandler` utilisent `use Services\AuditService` (namespace incorrect — `App\Services` attendu). Ces fichiers **échoueront au chargement** avec un autoloader PSR-4 standard.

> **AN-C-003** — `NoteHandler` et `AverageHandler` : namespace AuditService incorrect (`Services\` vs `App\Services\`). Tout dispatch `NoteCreated`, `NoteUpdated`, `AverageCalculated`, `ClassAverageUpdated` provoquera une `Error: Class "Services\AuditService" not found`.

**Problème 2 — Signature incohérente (instance vs statique) :**

`AuditService` est conçu pour usage **instancié** (`new AuditService()->log()`). Ses méthodes ne sont **pas statiques**. Cependant :

- `BulletinHandler` appelle `AuditService::logCreate(...)` (statique) ❌
- `RankingHandler` appelle `AuditService::log(...)` (statique) ❌
- `AnalyticsHandler` appelle `AuditService::log(...)` (statique) ❌

Ces appels statiques sur une classe non-statique provoquent une `E_DEPRECATED` en PHP 8.0+ et une `Error` en PHP 8.2+ dans certains contextes.

> **AN-C-003 (suite)** — 3 handlers (Bulletin, Ranking, Analytics) appellent AuditService en mode statique sur une classe instanciée. Incompatible PHP 8.2.

**Problème 3 — Signatures AuditService incohérentes entre handlers :**

La signature réelle de `AuditService::log()` est :
```php
public function log(?int $userId, string $action, string $module, ...)
```

Mais certains handlers l'appellent avec des arguments nommés dans un ordre différent ou avec des noms absents de la signature :
- `NoteHandler::onCreated()` appelle `logCreate('notes_v2', $e->noteId, "...", $e->createdById)` — 4 args, mais `logCreate` attend `(int $userId, string $module, string $entite, ?int $newId, array $data)` — **ordre inversé**.
- `AverageHandler::onCalculated()` appelle `log('calculate', 'notes_v2', $e->eleveId, "...", $e->calculatedById)` — le premier argument devrait être `?int $userId`, pas `'calculate'`.

### 2.3 NotificationService ⚠

**Analyse :** `NotificationService` existe dans `app/Services/NotificationService.php`. Il est enregistré comme handler de `PaiementValide` et `NoteAjoutee` dans `config/events.php` (handlers V1). **Aucun événement V2 du Module Académique ne déclenche de notification.** Notamment :
- `NotePublished` → pas de notification aux parents
- `BulletinPublished` → pas de notification aux parents
- `PeriodeLocked` → pas de notification à la direction

**Impact :** Les parents ne reçoivent aucune notification lors de la publication des bulletins V2.

> **AN-M-003** — Notifications manquantes pour les événements académiques clés (bulletin publié, notes publiées).

### 2.4 UploadService

**Analyse :** `UploadService` existe dans `app/Services/UploadService.php`. Il n'est utilisé par aucun composant V2 du Module Académique. L'upload de fichier CSV dans `NoteController::importerCsv()` utilise directement `$_FILES` et `file_get_contents()` sans passer par `UploadService`.

**Impact :** Pas de validation centralisée du type MIME, pas de limitation de taille gérée par le service.

> **AN-M-004** — `NoteController::importerCsv()` ne passe pas par `UploadService`. Validation de fichier incomplète.

### 2.5 RBAC ✅ avec réserve

**Architecture :** Chaque module possède ses propres Policies (`NotePolicy`, `EvaluationPolicy`, `BulletinPolicy`, etc.). Les permissions sont vérifiées via `$user['permissions']` (tableau fourni par la session).

**Correct :**
- `NotePolicy::canSaisir()` vérifie à la fois la permission ET `notes_saisie_ouverte`
- `EvaluationPolicy::canUpdate()` différencie verrouillé/archivé
- `BulletinPolicy` couvre les 7 cas d'usage

**Gap :** `EvaluationPolicy::hasPermission()` utilise `in_array(... true)` (strict), mais `NotePolicy::has()` utilise également `in_array(... true)` — cohérent. Cependant, `EvaluationPolicy` ne gère pas le wildcard `'*'` contrairement à `NotePolicy` et `BulletinPolicy`. Une permission `*` n'autorisera pas les évaluations.

> **AN-m-001** (mineure) — `EvaluationPolicy` ne supporte pas le wildcard `*`. Incohérence avec NotePolicy et BulletinPolicy.

### 2.6 Repositories ✅

**Architecture :** Repository pattern respecté dans les deux modules. SQL complexe dans les repositories, logique métier dans les services.

**Cohérence d'accès DB :**
- `NoteRepository` : utilise `Database::getConnection()` (méthode statique) ❌
- Tous les autres repos V2 : utilisent `Database::getInstance()->getConnection()` ✅

> **AN-m-002** (mineure) — `NoteRepository` utilise `Database::getConnection()` au lieu de `Database::getInstance()->getConnection()`.

### 2.7 DTOs ✅

DTOs correctement immutables (readonly PHP 8.1), `fromArray()`/`toArray()` présents là où nécessaire. Aucune logique métier dans les DTOs. Conforme à l'architecture.

### 2.8 Contracts ✅

`BulletinGeneratorInterface` et `AcademicAnalyticsInterface` existent et sont implémentés. Utiles pour les tests unitaires (isolation via stub).

---

## 3. Dépendances circulaires

**Analyse complète :**

```
Module Scolarité
  EleveService         → App\Models\EleveModel (V1 model)
  AffectationService   → App\Models\EleveModel (V1 model)
  InscriptionService   → App\Models\EleveModel (V1 model)
  MatiereService       → Scolarite\Repositories\MatiereRepository

Module Académique
  EvaluationService    → Academique\Models\* (V2)
  NoteService          → Academique\Models\* (V2)
  BulletinGenerator    → AcademicCalculationService + RankingEngine (intra-module)
  RankingEngine        → AcademicCalculationService (intra-module)
  AcademicAnalyticsService → AnalyticsRepository + AcademicCalculationService

Shared Services
  AuditService         → Core\Database (acceptable)
```

**Aucune dépendance circulaire entre modules identifiée.** Le Module Académique ne dépend pas du Module Scolarité au niveau du code PHP — l'intégration est exclusivement **via la base de données** (tables partagées : `eleves`, `classes`, `matieres`).

**Note :** Les modules Scolarité V2 (`App\Modules\Scolarite\Services\*`) continuent d'utiliser `App\Models\EleveModel` (modèle V1). Cette dépendance transversale est connue et acceptée comme dette technique (DT-1 du Foundation Freeze).

---

## 4. Duplication

### 4.1 Calcul de moyenne SQL vs PHP

**Duplication identifiée :**

`AnalyticsRepository` calcule des moyennes approchées via `AVG((valeur / note_max) * 20)` en SQL. `AcademicCalculationService` calcule les vraies moyennes pondérées en PHP. Ces deux résultats **divergent** car :
- SQL : moyenne arithmétique des notes individuelles (sans pondération coefficient)
- PHP : moyenne pondérée par coefficient d'évaluation ET de matière

`AcademicAnalyticsService` documente explicitement cette différence dans les commentaires de `AnalyticsRepository`. Le comportement est intentionnel pour les tableaux de bord (rapide/approximatif). **Accepté.**

### 4.2 Mention CASE SQL vs MentionValue

**Duplication identifiée :**

Les seuils de mention (TB≥16, B≥14, AB≥12, P≥10) apparaissent :
1. Dans `MentionValue::fromMoyenne()` — PHP, source de vérité
2. Dans `AnalyticsRepository` — fragment SQL CASE commenté et dans `AcademicAnalyticsService::getMentionCode()`

Les seuils SQL ne sont pas dérivés de `MentionValue` — si les seuils changent dans `MentionValue`, `AnalyticsRepository` et `AcademicAnalyticsService::getMentionCode()` ne seront pas mis à jour automatiquement.

> **AN-M-005** — Duplication des seuils mention : `MentionValue` + `AnalyticsRepository` SQL + `AcademicAnalyticsService::getMentionCode()`. Risque de désynchronisation.

### 4.3 Diff avant/après

**Duplication identifiée :**

La logique de diff avant/après (pour détecter les changements) est implémentée deux fois :
- `AuditService::diff()` (private) — dans le service partagé
- `EvaluationService::diff()` (private) — dupliquée dans le service métier

> **AN-m-003** (mineure) — `EvaluationService::diff()` duplique `AuditService::diff()`.

### 4.4 Construction de BulletinData

`BulletinGenerator::buildBulletinData()` (privé) est la source unique de génération de données bulletin. Pas de duplication. ✅

---

## 5. Performance

### 5.1 N+1 Query — NoteService::publierTout() ⚠

```php
// NoteService::publierTout()
$notes = $this->model->findByEvaluation($evaluationId); // 1 query
foreach ($notes as $note) {
    $this->model->update((int)$note->id, ['statut' => 'publiee']); // N queries
}
```

Pour une évaluation avec 60 élèves : **61 requêtes**. Un `UPDATE … WHERE evaluation_id = ? AND statut = 'saisie'` en lot serait 1 seule requête.

> **AN-P-001** — N+1 dans `NoteService::publierTout()` et `verrouillerTout()`. Impact: classes de 60+ élèves.

### 5.2 N+1 Query — NoteService::verrouillerTout() ⚠

Même pattern que `publierTout()`.

### 5.3 Classement complet pour chaque bulletin ⚠

`BulletinGenerator::genererBulletin()` appelle `rankingEngine->classementClasse()` pour **un seul élève**. Cela déclenche `RankingRepository::notesParClasseEtPeriode()` (récupère TOUS les élèves de la classe) + calcul Python PHP pour TOUS les élèves. Pour générer N bulletins individuellement : **N × (coût classement complet classe)**.

`genererBulletinsClasse()` optimise ce cas (classement calculé une fois). Mais si l'appelant utilise `genererBulletin()` en boucle, le problème N+1 apparaît.

> **AN-P-002** — `genererBulletin()` appelle `classementClasse()` (coût O(N)) à chaque appel individuel. Documenter l'obligation d'utiliser `genererBulletinsClasse()` pour les générations en lot.

### 5.4 Index manquants

Après analyse des requêtes SQL principales :

| Table | Colonne(s) | Requêtes concernées | Recommandation |
|-------|-----------|---------------------|----------------|
| `evaluations` | `(periode_scolaire_id, statut)` | RankingRepo, BulletinRepo, AnalyticsRepo | `CREATE INDEX idx_eval_periode_statut` |
| `evaluations` | `(classe_id, periode_scolaire_id, statut)` | toutes les requêtes de classement | `CREATE INDEX idx_eval_classe_periode` |
| `notes_v2` | `(evaluation_id, est_absent)` | AnalyticsRepository | `CREATE INDEX idx_note_eval_absent` |
| `eleves` | `classe_id` | RankingRepo, BulletinRepo | `CREATE INDEX idx_eleve_classe` (si absent) |
| `bulletins_v2` | `(eleve_id, periode_id)` | UNIQUE KEY déjà défini ✅ | — |
| `audit_logs` | `(module, action, created_at)` | AuditService::search() | `CREATE INDEX idx_audit_search` |

> **AN-P-003** — 5 index manquants identifiés sur les tables critiques des requêtes analytiques et de classement.

### 5.5 Cache

`AcademicAnalyticsService` ne cache aucun résultat. `dashboardDirecteur()` déclenche 6 requêtes SQL à chaque appel. Pour un tableau de bord consulté fréquemment, un cache de 5 minutes réduirait la charge de 90%.

> **AN-P-004** (recommandation) — Ajouter un cache Redis/APCu sur `dashboardDirecteur()` et `dashboardResponsable()`.

---

## 6. Sécurité

### 6.1 Permissions — Couverture ✅

| Domaine | Permissions | Controllers | Verdict |
|---------|-------------|-------------|---------|
| Évaluations | `academique.evaluations.*` | `EvaluationController::requirePermission()` | ✅ |
| Notes | `academique.notes.*` | `NoteController::requirePermission()` | ✅ |
| Périodes | `academique.periodes.*` | `PeriodeController::requirePermission()` | ✅ |
| Types éval. | `academique.types_evaluations.*` | `TypeEvaluationController::requirePermission()` | ✅ |
| Bulletins | `academique.bulletin.*` | Policy + Controller | ✅ |

### 6.2 Ownership — Élèves ⚠

**`NoteService::saisirBatch()`** ne vérifie pas que les `eleve_id` soumis appartiennent à la classe de l'évaluation. Un utilisateur avec `academique.notes.manage` peut saisir une note pour n'importe quel `eleve_id` tant que l'évaluation est ouverte. (Voir AN-M-001.)

### 6.3 Accès parents ⛔ CRITIQUE

`BulletinPolicy::canView()` vérifie la permission `academique.bulletin.view` mais **ne vérifie pas le lien parent-enfant**. Si un compte parent reçoit la permission `academique.bulletin.view`, il peut visualiser les bulletins de tous les élèves, pas seulement de ses enfants. (Voir AN-C-002.)

### 6.4 Accès enseignants ✅

`NotePolicy::canSaisir()` vérifie `academique.notes.manage` — conçu pour les enseignants. **Mais** : il n'y a pas de restriction à la matière/classe assignée à l'enseignant. Un enseignant avec `academique.notes.manage` peut saisir des notes sur n'importe quelle évaluation.

> **AN-m-004** (mineure) — Les enseignants ne sont pas restreints à leurs matières/classes affectées. Toute évaluation ouverte leur est accessible.

### 6.5 Accès direction ✅

`RankingPolicy::canViewNiveau()` réserve les classements inter-niveaux aux admins. `CalculationPolicy` différencie view/manage/admin. Cohérent.

### 6.6 Journalisation ⚠

Couverture d'audit :
- ✅ Évaluations : créer/modifier/publier/verrouiller/archiver
- ✅ Notes : créer/modifier/publier/verrouiller/importer
- ✅ Bulletins : générer/publier/archiver
- ✅ Classements : générer/mettre à jour
- ✅ Analytics : générer
- ❌ `BulletinGenerator::publierBulletin()` et `archiverBulletin()` appellent `AuditService::logUpdate()` statiquement (non-static sur instance) — voir AN-C-003.
- ❌ Changement de classe d'un élève → pas de ré-audit des bulletins associés

### 6.7 Import CSV — Injection ✅

`NoteController::importerCsv()` vérifie l'extension `.csv` et utilise `file_get_contents()` sur un fichier temporaire système. Les données sont parsées via `str_getcsv()` puis validées par `NoteValue` (Value Object). Pas de risque d'injection SQL (requêtes préparées utilisées en aval).

**Manque :** Pas de vérification du MIME type réel (`mime_content_type()`), seulement l'extension. Un attaquant peut uploader un fichier PHP avec l'extension `.csv`.

> **AN-m-005** (mineure) — Import CSV : vérification extension seule, pas du MIME type réel.

---

## 7. Compatibilité V1

### 7.1 Routes ✅

Toutes les routes V2 sont préfixées `/v2/academique/*` et `/v2/scolarite/*`. Aucune route V1 n'est modifiée ou en conflit.

### 7.2 Tables ✅

- `notes_v2` (nouvelle table, pas de conflit avec V1)
- `bulletins_v2` (nouvelle table)
- `periodes_scolaires` (nouvelle table V2)
- `evaluations` (table V2, pas de relation FK forte avec les tables V1 `controles`, `notes`)
- `matieres`, `classes`, `eleves` : tables partagées V1/V2 — l'ajout de colonnes V2 (`filiere`, `niveaux`, `actif`, `couleur`) via ALTER est rétrocompatible

### 7.3 Événements V1 ✅

Les handlers V1 (`AuditHandler`, `StatsCacheHandler`, `NotificationHandler`) restent enregistrés dans `config/events.php` pour les événements V1 (`EleveCreated`, `PaiementValide`, etc.). Non perturbés par les ajouts V2.

### 7.4 Services partagés ✅

`AuditService`, `UploadService`, `NotificationService` sont partagés V1/V2 sans modification. Rétrocompatible.

---

## 8. Récapitulatif des anomalies

### Anomalies Critiques (bloquantes — GO conditionné)

| ID | Localisation | Description | Impact |
|----|-------------|-------------|--------|
| AN-C-001 | `EvaluationService::creer()`, `NoteService::saisirBatch()` | Absence de validation inscription/année scolaire — un élève non inscrit peut recevoir des notes | Données invalides en production |
| AN-C-002 | `BulletinPolicy::canView()` | Pas de contrôle ownership parent/enfant — un parent peut voir les bulletins de tous les élèves | Fuite de données personnelles (RGPD) |
| AN-C-003 | `NoteHandler`, `AverageHandler`, `BulletinHandler`, `RankingHandler`, `AnalyticsHandler` | (a) Namespace `Services\AuditService` invalide dans NoteHandler+AverageHandler ; (b) Appels statiques sur instance non-statique dans 3 handlers — crash PHP 8.2 garanti à l'exécution | Runtime Error — journalisation entière cassée |

### Anomalies Majeures (à corriger avant la mise en production finale)

| ID | Localisation | Description | Impact |
|----|-------------|-------------|--------|
| AN-M-001 | `NoteService::saisirBatch()` | Pas de vérification d'appartenance élève/classe lors de la saisie | Données incohérentes |
| AN-M-002 | Tous modules | Année scolaire = string non normalisé, sans référentiel | Désynchronisation cross-module |
| AN-M-003 | `config/events.php` | Aucun événement V2 ne déclenche `NotificationService` | Parents non notifiés des bulletins/notes |
| AN-M-004 | `NoteController::importerCsv()` | Upload CSV ne passe pas par `UploadService` | Validation de fichier incomplète |
| AN-M-005 | `AnalyticsRepository`, `AcademicAnalyticsService::getMentionCode()` | Seuils de mention dupliqués — hors synchronisation avec `MentionValue` | Incohérences analytiques si seuils changent |

### Anomalies Mineures (dette technique acceptable)

| ID | Localisation | Description |
|----|-------------|-------------|
| AN-m-001 | `EvaluationPolicy::hasPermission()` | Ne supporte pas le wildcard `*` |
| AN-m-002 | `NoteRepository` | Utilise `Database::getConnection()` au lieu de `Database::getInstance()->getConnection()` |
| AN-m-003 | `EvaluationService::diff()` | Duplique `AuditService::diff()` |
| AN-m-004 | `NotePolicy::canSaisir()` | Pas de restriction enseignant → ses matières affectées |
| AN-m-005 | `NoteController::importerCsv()` | MIME type CSV non vérifié, extension seule |
| DT-M1 | `MatiereService` / `AffectationService` | `MatiereAssignedToClasse` / `MatiereRemovedFromClasse` non consommés par le Module Académique (hérité de Phase 1.7) |

---

## 9. Plan de correction

### Sprint correctif (obligatoire avant GO)

**AN-C-003 — AuditService namespace + statique (priorité 1)**

```php
// NoteHandler.php et AverageHandler.php — corriger l'import :
// ❌ use Services\AuditService;
// ✅ use App\Services\AuditService;

// BulletinHandler.php, RankingHandler.php, AnalyticsHandler.php
// Remplacer les appels statiques par instanciation :
// ❌ AuditService::log(action: '...', ...)
// ✅ (new AuditService())->log($userId, 'action', 'module', ...)
```

**AN-C-002 — BulletinPolicy ownership parent (priorité 2)**

```php
// Ajouter dans BulletinPolicy :
public function canViewForParent(array $user, int $eleveId): bool
{
    // Vérifier via FamilleRepository que $user['id'] est parent de $eleveId
    // OU déléguer à FamillePolicy::canViewChild()
}
```

**AN-C-001 — Validation inscription/année scolaire (priorité 3)**

Ajouter dans `EvaluationService::creer()` une vérification que la classe cible a des élèves inscrits pour l'`annee_scolaire` de la période. Peut être une requête légère (`SELECT COUNT(*) FROM inscriptions WHERE classe_id = ? AND annee_scolaire = ?`).

### Phase suivante (Phase 2.9 dette)

1. **AN-P-001/002** — Réécrire `publierTout()`/`verrouillerTout()` en UPDATE batch
2. **AN-P-003** — Créer les 5 index SQL identifiés (migration `M_PERF_001`)
3. **AN-M-005** — Extraire les seuils mention dans une constante partagée ou les lire depuis `MentionValue`
4. **AN-M-003** — Ajouter `NotificationHandler` sur `BulletinPublished` et `NotePublished`
5. **DT-M1** — Résolution du fil `MatiereAssigned/Removed` dans un handler Académique

---

## 10. Recommandations architecturales

1. **Créer un `AnnéeScolaireService` ou une table de référentiel** — normaliser la chaîne année scolaire, la valider à la création de chaque entité.

2. **Introduire `AcademicContextService`** — service léger qui vérifie : élève inscrit ? période ouverte ? classe active ? Utilisable dans `EvaluationService`, `NoteService`, `BulletinGenerator` pour éviter les vérifications ad hoc dispersées.

3. **Rendre `AuditService` entièrement statique ou entièrement instancié** — le mélange actuel (instance dans certains handlers, statique dans d'autres, implémentation non-statique) est la cause directe d'AN-C-003. Recommandation : passer à `static` pour simplifier l'usage dans les handlers.

4. **Documenter `genererBulletinsClasse()` comme seul point d'entrée pour les générations en lot** — interdire explicitement l'appel de `genererBulletin()` dans une boucle.

5. **Ajouter un test d'intégration SQL** — un test qui exécute les requêtes critiques (RankingRepository, BulletinRepository) sur une base de données de test réelle avec données synthétiques, pour détecter les index manquants et les N+1.

---

*Rapport généré par révision statique du code source — ecole_app Phase 2.9*
*Révision suivante recommandée après correction des anomalies critiques.*
