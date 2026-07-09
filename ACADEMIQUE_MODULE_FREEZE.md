# ACADEMIQUE MODULE FREEZE — ecole_app V2
**Phase 2.10 — Déclaration officielle de gel du Module Académique**
Date : 2026-06-30
Réviseur : Audit automatique + revue statique complète
Périmètre : `app/Modules/Academique/` (104 fichiers) + `tests/Unit/` (4 suites)

---

## Résultat global

| Dimension | Score | Verdict |
|-----------|-------|---------|
| Architecture | 8.5/10 | ✅ Solide |
| Intégration | 7.0/10 | ⚠ 3 anomalies critiques connues (Phase 2.9) |
| Calculs | 9.5/10 | ✅ Excellent |
| Sécurité | 7.5/10 | ⚠ Ownership bulletin + namespace AuditService |
| Performances | 6.5/10 | ⚠ N+1 + 5 index manquants |
| Compatibilité | 9.5/10 | ✅ V1 intacte |
| Qualité / Tests | 9.0/10 | ✅ 338/338 PASS, zéro TODO |

### **Score global : 8.2 / 10**

### Verdict : ⚠ **NO-GO conditionnel → GO après correction des 3 anomalies critiques**

Les anomalies critiques AN-C-001, AN-C-002, AN-C-003 (identifiées en Phase 2.9) restent non corrigées. Leur correction est estimée à **moins d'une demi-journée**. Une fois ces 3 points résolus, le module est apte à la mise en production.

---

## 1. Architecture

### 1.1 Conventions V2 ✅

| Convention | Vérification | Résultat |
|-----------|-------------|---------|
| Namespace `App\Modules\Academique\` | Présent dans 104 fichiers | ✅ |
| Autoloader PSR-4 manuel | Chemin `app/Modules/Academique/{type}/{Name}.php` | ✅ |
| Routes préfixées `/v2/academique/*` | 66 routes déclarées dans `routes.php` | ✅ |
| Coexistence V1 | Aucun conflit de route | ✅ |
| PHP 8.1+ (readonly, constructor promotion) | DTOs + Events | ✅ |
| `module.json` présent | Version 2.1.0, `enabled: false` | ✅ |

**Observation :** `module.json` est obsolète — il ne reflète que la Phase 2.1 (Périodes). Le fichier doit être mis à jour pour déclarer tous les composants des Phases 2.1 à 2.8 et passer `enabled: true` avant l'activation.

> **DT-A-001** — `module.json` non mis à jour depuis la Phase 2.1. À mettre à jour avant activation.

### 1.2 Séparation des responsabilités ✅

**Controllers (thin) :**
- Reçoivent la requête HTTP, délèguent immédiatement au service
- Aucun calcul métier dans les controllers audités
- CSRF vérifié sur toutes les actions POST (23 occurrences sur 4 controllers)
- Permissions vérifiées au point d'entrée (39 occurrences `requirePermission`)

**Services (orchestration métier) :**
- `EvaluationService` : cycle de vie évaluation sans accès direct à la logique de note
- `NoteService` : saisie, publication, verrouillage, import CSV
- `PeriodeScolaireService` : cycle de vie période
- `TypeEvaluationService` : cycle de vie type
- `BulletinGenerator` : génération bulletin, zéro calcul
- `RankingEngine` : classement, zéro calcul propre
- `AcademicCalculationService` : calcul pur, zéro DB

**Repositories (accès données) :**
- 8 repositories dans le module
- SQL complexe exclusivement dans les repositories
- Aucun SQL dans les services ou controllers

### 1.3 Repository Pattern ✅

Tous les repositories :
- Injectent `Core\Database::getInstance()->getConnection()` dans le constructeur
- Exception : `NoteRepository` utilise `Database::getConnection()` (méthode statique — **AN-m-002**)
- Chaque méthode public retourne des types cohérents (`array`, `?object`, `int`, `bool`)
- `try/catch \PDOException` systématique dans `RankingRepository` et `AnalyticsRepository` pour la forward-compat
- Pagination présente dans `NoteRepository`, `EvaluationRepository`, `PeriodeScolaireRepository`, `TypeEvaluationRepository`

### 1.4 DTO Pattern ✅

| DTO | readonly | fromArray | toArray | fromRequest | validate |
|-----|---------|-----------|---------|-------------|---------|
| `PeriodeScolaireDTO` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `TypeEvaluationDTO` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `EvaluationDTO` | ✅ | — | ✅ | ✅ | ✅ |
| `NoteDTO` | ✅ | — | ✅ | ✅ | — |
| `NoteBatchDTO` | ✅ | — | — | ✅ | — |
| `RankingResultDTO` | ✅ | — | ✅ | — | — |
| `BulletinData` | ✅ | ✅ | ✅ | — | — |
| `BulletinSummary` | ✅ | — | ✅ | — | — |
| `AnalyticsResult` | ✅ | — | ✅ | — | — |
| `DashboardMetrics` | ✅ | — | ✅ | — | — |

**Immutabilité :** `BulletinData` utilise `withStatut()` et `withAppreciationDirecteur()` pour créer de nouvelles instances plutôt que de muter (pattern correct).

### 1.5 Policies ✅

| Domaine | Policy | Méthodes | Wildcard `*` |
|---------|--------|----------|-------------|
| Périodes | `PeriodePolicy` | 5 | ✅ |
| Types éval. | `TypeEvaluationPolicy` | 4 | ✅ |
| Évaluations | `EvaluationPolicy` | 6 | ❌ (AN-m-001) |
| Notes | `NotePolicy` | 5 | ✅ |
| Classements | `RankingPolicy` | 4 | ✅ |
| Bulletins | `BulletinPolicy` | 7 | ✅ — mais ownership manquant (AN-C-002) |
| Calcul | `CalculationPolicy` | 3 | ✅ |
| Analytics | — | — | — |

**Gap :** `AcademicAnalyticsService` ne possède pas de Policy dédiée. L'accès est contrôlé uniquement par les Policies des dashboards sous-jacents. Acceptable pour la V2, à formaliser en V2.1.

### 1.6 Value Objects ✅

6 Value Objects immuables :
- `NoteValue` — validation note dans le barème, `null` = absent
- `BaremeValue` — note maximale > 0
- `CoefficientValue` — coefficient > 0
- `AverageValue` — moyenne avec `isEmpty()`, `compareTo()`, `isPassant()`
- `GradeValue` — note sur 20 dérivée
- `MentionValue` — seuils TB/B/AB/P/INS, source unique des thresholds de mention

**Analyse `MentionValue` :** Les seuils sont définis dans `$THRESHOLDS` (16/14/12/10/0). La duplication identifiée dans `AcademicAnalyticsService::getMentionCode()` et le SQL de `AnalyticsRepository` constitue la dette **AN-M-005**.

### 1.7 Contracts ✅

- `BulletinGeneratorInterface` — 7 méthodes, implémenté par `BulletinGenerator`
- `AcademicAnalyticsInterface` — 14 méthodes, implémenté par `AcademicAnalyticsService`

Les contracts permettent l'injection de dépendances et le mocking dans les tests. ✅

---

## 2. Intégration

### 2.1 Intégration Scolarité ✅ avec réserves

| Point d'intégration | Mécanisme | Statut |
|--------------------|-----------|--------|
| Élèves → Classes | `eleves.classe_id` FK — lu dans repos V2 | ✅ |
| Élèves → Évaluations | `evaluations.classe_id = eleves.classe_id` | ✅ |
| Élèves → Notes | `notes_v2(eleve_id, evaluation_id)` | ✅ |
| Élèves → Bulletins | `BulletinRepository::infoEleve()` lit `classe_id` | ✅ |
| Classes → Évaluations | `evaluations.classe_id` FK | ✅ |
| Classes → Classements | `RankingRepository::notesParClasseEtPeriode()` | ✅ |
| Matières → Évaluations | `evaluations.matiere_id` FK | ✅ |
| Matières → Notes | Via évaluation (indirectement) | ✅ |
| Inscriptions → Académique | **Aucun lien** validé au code | ⛔ AN-C-001 |
| Familles → Bulletins | Ownership parent non implémenté | ⛔ AN-C-002 |
| Années scolaires | String non normalisé | ⚠ AN-M-002 |

### 2.2 Intégration Core ✅

| Composant Core | Usage dans Académique | Statut |
|----------------|----------------------|--------|
| `Core\Database` | Tous les repositories | ✅ (1 incohérence NoteRepository) |
| `Core\Event` | 15 classes événement héritent de `Event` | ✅ |
| `Core\Listener` | 7 handlers implémentent `Listener` | ✅ |
| `Core\EventDispatcher` | Usage statique correct | ✅ |
| `Core\Controller` | 4 controllers héritent de `Controller` | ✅ |
| `Core\Session` | Flash messages dans tous les controllers | ✅ |
| `Core\Router` | 66 routes déclarées | ✅ |

### 2.3 Intégration RBAC ✅

- 16 permissions académiques déclarées (periodes, types, evaluations, notes, classement, bulletin, analytics)
- Vérification `requirePermission()` systématique en entrée de controller
- Vérification Policy sur les actions sensibles (modifier, publier, verrouiller)

### 2.4 Intégration Shared Services ✅ avec anomalies

| Service | Usage | Statut |
|---------|-------|--------|
| `AuditService` | 7 handlers + 1 service | ⚠ AN-C-003 namespace + statique |
| `NotificationService` | Non utilisé dans V2 Académique | ⚠ AN-M-003 |
| `UploadService` | Non utilisé (CSV import direct) | ⚠ AN-M-004 |

### 2.5 Event System ✅

**Cartographie complète — Module Académique :**

| Événement | Handler | Audit | Notification |
|-----------|---------|-------|-------------|
| `PeriodeCreated/Updated/Activated/Locked/Unlocked/Archived` | `PeriodeHandler` | ✅ | — |
| `EvaluationTypeCreated/.../Archived` (5) | `TypeEvaluationHandler` | ✅ | — |
| `EvaluationCreated/.../Archived` (5) | `EvaluationHandler` | ✅ | — |
| `NoteCreated/Updated/Published/Locked/Imported` (5) | `NoteHandler` ⚠ | crash AN-C-003 | — |
| `AverageCalculated/ClassAverageUpdated` | `AverageHandler` ⚠ | crash AN-C-003 | — |
| `RankingGenerated/Updated` | `RankingHandler` ⚠ | appel statique | — |
| `BulletinGenerated/Published/Archived` | `BulletinHandler` ⚠ | appel statique | — |
| `AnalyticsGenerated/StatisticsUpdated` | `AnalyticsHandler` ⚠ | appel statique | — |

**Total : 27 événements académiques V2 / 7 handlers**

---

## 3. Calculs — Source unique de vérité ✅

### 3.1 Inventaire des formules de calcul

| Calcul | Responsable | Statut |
|--------|-------------|--------|
| Note ramenée sur 20 | `AcademicCalculationService::noteRameneeSur20()` | ✅ |
| Note pondérée | `AcademicCalculationService::notePonderee()` | ✅ |
| Moyenne matière | `AcademicCalculationService::moyenneMatiere()` | ✅ |
| Moyenne période | `AcademicCalculationService::moyennePeriode()` | ✅ |
| Moyenne générale | `AcademicCalculationService::moyenneGenerale()` | ✅ |
| Statistiques classe | `AcademicCalculationService::statistiquesClasse()` | ✅ |
| Mention | `MentionValue::fromAverage()` via `AcademicCalculationService::mention()` | ✅ |
| Classement / rang | `AcademicCalculationService::classement()` appelé par `RankingEngine::rank()` | ✅ |
| Décision passage | `AcademicCalculationService::prepareDecision()` | ✅ |
| Bulletin complet | `AcademicCalculationService::calculerBulletin()` (façade) | ✅ |
| Note éliminatoire | `AcademicCalculationService::estEliminatoire()` | ✅ |
| Écart-type | `AcademicCalculationService::ecartType()` (privé) | ✅ |

**Duplication identifiée :**
- Seuils mention (TB≥16…) : dans `MentionValue::$THRESHOLDS` (source) + `AcademicAnalyticsService::getMentionCode()` + SQL CASE dans `AnalyticsRepository` (dette AN-M-005)
- Diff avant/après : dans `AuditService::diff()` + `EvaluationService::diff()` (dette AN-m-003)

### 3.2 RankingEngine — Indépendance ✅

`RankingEngine` :
- Ne contient aucun calcul de moyenne
- Délègue `calculerBulletin()` à `AcademicCalculationService`
- Délègue `classement()` à `AcademicCalculationService`
- Appelle `statistiquesClasse()` pour les stats du classement
- Méthode `computeFromNoteRows()` **publique** → testable sans DB

### 3.3 BulletinGenerator — Zéro calcul métier ✅

`BulletinGenerator` :
- Ne contient aucun calcul de moyenne (délègue à `AcademicCalculationService::calculerBulletin()`)
- Ne calcule aucun classement (délègue à `RankingEngine::classementClasse()`)
- Token de vérification : `hash('sha256', "bulletin:{$eleveId}:{$periodeId}:{$appKey}")` — déterministe
- Appréciation : `$pool[$eleveId % count($pool)]` — déterministe
- `groupNotesByMatiere()` **publique** → testable sans DB
- `genererBulletinsClasse()` : classement calculé une fois, réutilisé pour N élèves ✅

### 3.4 AcademicAnalyticsService — Agrégations uniquement ✅

- Délègue toutes les agrégations de volume à `AnalyticsRepository` (SQL GROUP BY)
- `AcademicCalculationService` instancié mais utilisé uniquement pour `getMentionCode()` (dette AN-M-005)
- Aucune formule de calcul propriétaire

---

## 4. Sécurité

### 4.1 RBAC — Couverture ✅

Toutes les actions protégées par `requirePermission()` en entrée de controller. Policies consultées pour les actions de cycle de vie. Aucune route non protégée détectée.

**Gap :** `EvaluationPolicy::hasPermission()` ne supporte pas le wildcard `*` (AN-m-001).

### 4.2 Audit — Couverture globale ✅ avec anomalies

| Action auditée | Handler | Fonctionnel |
|---------------|---------|------------|
| Création évaluation | EvaluationHandler | ✅ |
| Cycle vie évaluation | EvaluationHandler | ✅ |
| Saisie note | NoteHandler | ⛔ crash (AN-C-003) |
| Modification note | NoteHandler | ⛔ crash (AN-C-003) |
| Publication notes | NoteHandler | ⛔ crash (AN-C-003) |
| Import CSV notes | NoteHandler | ⛔ crash (AN-C-003) |
| Calcul moyenne | AverageHandler | ⛔ crash (AN-C-003) |
| Classement | RankingHandler | ⚠ appel statique |
| Génération bulletin | BulletinHandler | ⚠ appel statique |
| Analytics | AnalyticsHandler | ⚠ appel statique |
| Période | PeriodeHandler | ✅ |
| Type évaluation | TypeEvaluationHandler | ✅ |

### 4.3 Ownership ⚠

- **Notes** : pas de vérification élève ∈ classe de l'évaluation (AN-M-001)
- **Bulletins** : `BulletinPolicy::canView()` ne vérifie pas le lien parent/enfant (AN-C-002)
- **Évaluations enseignant** : un enseignant avec `notes.manage` accède à toutes les évaluations (AN-m-004)

### 4.4 Validation ✅

- DTOs avec `validate()` : `PeriodeScolaireDTO`, `TypeEvaluationDTO`, `EvaluationDTO`
- Value Objects rejettent les valeurs invalides à la construction (NoteValue, BaremeValue, CoefficientValue)
- `NoteService::saisirBatch()` → `new NoteValue(...)` → exception si hors barème

### 4.5 CSRF ✅

23 vérifications `$this->verifyCsrf()` distribuées sur toutes les actions POST mutantes. Aucune action POST sans vérification CSRF détectée.

### 4.6 SQL Injection ✅

- Toutes les requêtes utilisent `$pdo->prepare()` + `execute([$param])` ou paramètres nommés
- Aucune interpolation de variable utilisateur directe dans le SQL détectée
- `$perPage` et `$offset` dans `NoteRepository::paginate()` sont interpolés **après cast `(int)`** — correct

### 4.7 Upload — Vérification partielle ⚠

`NoteController::importerCsv()` vérifie :
- ✅ `is_uploaded_file()` — protection contre path injection
- ✅ Extension `.csv`
- ❌ MIME type réel non vérifié (`mime_content_type()`) — AN-m-005

---

## 5. Performances

### 5.1 N+1 Queries identifiées

| Localisation | Description | Impact | Recommandation |
|-------------|-------------|--------|----------------|
| `NoteService::publierTout()` | UPDATE par note en boucle | 30-60 requêtes/appel | Remplacer par `UPDATE notes_v2 SET statut='publiee' WHERE evaluation_id=? AND statut='saisie'` |
| `NoteService::verrouillerTout()` | UPDATE par note en boucle | 30-60 requêtes/appel | Même solution |
| `BulletinGenerator::genererBulletin()` | `classementClasse()` pour 1 élève = chargement complet de la classe | O(N) par appel individuel | Obligation documentée d'utiliser `genererBulletinsClasse()` pour les lots |
| `BulletinGenerator::genererBulletinsClasse()` | `repo->infoEleve()` pour chaque élève | N requêtes pour N élèves | Précharger la liste d'élèves avec 1 requête JOIN |

### 5.2 Index SQL recommandés

```sql
-- Migration M_PERF_001 (à créer)

-- evaluations : requêtes de classement et bulletins
CREATE INDEX idx_eval_periode_statut    ON evaluations (periode_scolaire_id, statut);
CREATE INDEX idx_eval_classe_periode    ON evaluations (classe_id, periode_scolaire_id, statut);

-- notes_v2 : requêtes analytiques
CREATE INDEX idx_note_eval_absent       ON notes_v2 (evaluation_id, est_absent);
CREATE INDEX idx_note_eleve_eval        ON notes_v2 (eleve_id, evaluation_id);

-- eleves : jointures fréquentes
CREATE INDEX idx_eleve_classe           ON eleves (classe_id);

-- audit_logs : recherches dans l'interface admin
CREATE INDEX idx_audit_module_action    ON audit_logs (module, action, created_at);
```

### 5.3 Cache

| Composant | Cache actuel | Recommandation |
|-----------|-------------|----------------|
| `dashboardDirecteur()` | Aucun — 6 requêtes/appel | Cache 5 min (APCu/Redis) |
| `dashboardResponsable()` | Aucun | Cache 5 min |
| `classementClasse()` | Aucun | Cache invalidé par `NotePublished` |
| `moyenneEtablissement()` | Aucun | Cache 10 min |

### 5.4 Pagination ✅

Pagination présente dans : `NoteRepository`, `EvaluationRepository`, `PeriodeScolaireRepository`, `TypeEvaluationRepository`. `NoteRepository::paginate()` exécute 2 COUNT queries (bug : double execute, l'une est un no-op) — fonctionnel mais wasteful.

---

## 6. Compatibilité

### 6.1 Compatibilité V1 ✅

| Aspect | Vérification | Résultat |
|--------|-------------|---------|
| Routes V1 existantes | Non modifiées | ✅ |
| Routes V2 | Préfixe `/v2/academique/*` exclusif | ✅ |
| Tables V1 (`eleves`, `classes`, `matieres`) | Lues en lecture seule depuis V2 | ✅ |
| Nouvelles tables V2 | `notes_v2`, `bulletins_v2`, `periodes_scolaires`, `evaluations`, `types_evaluations` | ✅ |
| Handlers V1 (`AuditHandler`, `StatsCacheHandler`) | Intacts dans `config/events.php` | ✅ |

### 6.2 Migrations SQL

| Migration | Table | Type | Statut |
|-----------|-------|------|--------|
| `migration_periodes_scolaires.sql` | `periodes_scolaires` | CREATE IF NOT EXISTS | Préparée |
| `migration_types_evaluations.sql` | `types_evaluations` | CREATE IF NOT EXISTS | Préparée |
| `migration_evaluations.sql` | `evaluations` | CREATE IF NOT EXISTS | Préparée |
| `migration_notes_v2.sql` | `notes_v2`, `notes_historique` | CREATE IF NOT EXISTS | Préparée |
| `migration_bulletins.sql` | `bulletins_v2` | CREATE IF NOT EXISTS | Préparée |
| `M_PERF_001` (index) | 4 tables | CREATE INDEX | À créer |
| `M_ANNEE_001` (référentiel) | `annees_scolaires` | CREATE IF NOT EXISTS | À créer (AN-M-002) |

**Convention respectée :** `CREATE TABLE IF NOT EXISTS` systématique. Aucun `DROP TABLE`.

### 6.3 Rollback

Toutes les migrations utilisent `CREATE TABLE IF NOT EXISTS` — rollback safe en supprimant les nouvelles tables. Les données V1 ne sont pas affectées.

---

## 7. Qualité

### 7.1 Couverture fonctionnelle

| Domaine | Fonctionnalités | Implémentées | % |
|---------|----------------|-------------|---|
| Périodes scolaires | Créer, modifier, activer, fermer, verrouiller, déverrouiller, archiver | 7/7 | 100% |
| Types d'évaluations | Créer, modifier, activer, désactiver, archiver | 5/5 | 100% |
| Évaluations | Créer, modifier, publier, verrouiller, déverrouiller, archiver | 6/6 | 100% |
| Notes | Saisir (batch), modifier, publier, verrouiller, importer CSV | 5/5 | 100% |
| Calculs | Note/20, pondération, matière, période, générale, stats, mention, classement, décision | 9/9 | 100% |
| Classement | Classe, niveau, matière, général multi-périodes | 4/4 | 100% |
| Bulletins | Générer (1 ou N), preview, publier, archiver, token QR, export HTML | 6/6 | 100% |
| Analytics | Moyennes (4 niveaux), taux, distribution, mentions, évolution | 10/10 | 100% |
| Dashboards | Directeur, enseignant, responsable | 3/3 | 100% |
| Export | Excel data, PDF data, widgets | 3/3 | 100% |

**Total : 58/58 fonctionnalités implémentées.**

### 7.2 Couverture des tests

| Suite | Sections | Assertions | Résultat |
|-------|---------|-----------|---------|
| `AcademicCalculationServiceTest` | 11 | 85 | ✅ 85/85 PASS |
| `RankingEngineTest` | 13 | 68 | ✅ 68/68 PASS |
| `BulletinGeneratorTest` | 14 | 82 | ✅ 82/82 PASS |
| `AcademicAnalyticsServiceTest` | 15 | 103 | ✅ 103/103 PASS |
| **Total** | **53** | **338** | **✅ 338/338 PASS** |

**Stratégie de test :** Tests unitaires purs, isolation via Stub repositories (pas de DB). Tous les services métier critiques sont couverts. Aucun test d'intégration (à ajouter en V2.1).

**Couverture fonctionnelle des tests :**
- Cas nominaux ✅
- Cas limites (vide, null, absent, éliminatoire, ex-aequo) ✅
- Calculs de mention ✅
- Décisions de passage (admis/rattrapage/refusé) ✅
- Export HTML bulletin ✅
- Distribution notes par tranche ✅
- Dashboards par rôle ✅

### 7.3 Zéro TODO/FIXME ✅

Aucun marqueur `TODO`, `FIXME`, `HACK`, `XXX` dans le code source du module. ✅

### 7.4 Documentation ✅

- Chaque service documente son contrat en en-tête (`RÈGLES D'OR`)
- `AcademicCalculationService` : conventions d'entrée documentées
- `RankingEngine` : invariant déclaré (`ce service ne calcule JAMAIS les moyennes`)
- `BulletinGenerator` : règles d'or documentées
- `AnalyticsRepository` : avertissement sur la différence approximation SQL vs calcul PHP

---

## 8. Dette technique restante

### Critique (à corriger avant GO)

| ID | Composant | Action requise | Effort |
|----|-----------|---------------|--------|
| AN-C-001 | `EvaluationService::creer()`, `NoteService::saisirBatch()` | Ajouter validation `inscription.annee_scolaire` | 2h |
| AN-C-002 | `BulletinPolicy` | Ajouter `canViewForParent(array $user, int $eleveId)` via `FamilleRepository` | 3h |
| AN-C-003 | `NoteHandler`, `AverageHandler` | Corriger namespace : `use App\Services\AuditService` | 5 min |
| AN-C-003 | `BulletinHandler`, `RankingHandler`, `AnalyticsHandler` | Remplacer appels statiques par `(new AuditService())->log(...)` | 30 min |

**Effort total critique : ~6h**

### Majeure (avant mise en production finale)

| ID | Composant | Action requise | Effort |
|----|-----------|---------------|--------|
| AN-M-001 | `NoteService::saisirBatch()` | Vérifier `eleve.classe_id = evaluation.classe_id` | 1h |
| AN-M-002 | Tous modules | Créer table `annees_scolaires`, normaliser les strings | 4h |
| AN-M-003 | `config/events.php` | Ajouter `NotificationHandler` sur `BulletinPublished` + `NotePublished` | 2h |
| AN-M-004 | `NoteController::importerCsv()` | Passer par `UploadService` | 1h |
| AN-M-005 | `AcademicAnalyticsService`, `AnalyticsRepository` | Extraire seuils mention depuis `MentionValue::$THRESHOLDS` | 1h |

**Effort total majeur : ~9h**

### Mineure (phase suivante)

| ID | Composant | Description |
|----|-----------|------------|
| AN-m-001 | `EvaluationPolicy` | Ajouter wildcard `*` |
| AN-m-002 | `NoteRepository` | Uniformiser `Database::getInstance()->getConnection()` |
| AN-m-003 | `EvaluationService::diff()` | Supprimer, utiliser `AuditService::diff()` (ou exposer en public) |
| AN-m-004 | `NotePolicy::canSaisir()` | Restreindre enseignant à ses matières affectées |
| AN-m-005 | `NoteController::importerCsv()` | Vérifier MIME type réel |
| DT-A-001 | `module.json` | Mettre à jour pour refléter Phases 2.1 à 2.8, passer `enabled: true` |
| DT-M1 | `MatiereService` / handlers Académique | Consommer `MatiereAssignedToClasse/Removed` dans un handler Académique |

---

## 9. Recommandations V2.1

### Priorité 1 — Qualité (post-freeze immédiat)

1. **Corriger AN-C-001 à AN-C-003** — conditions de GO
2. **Tests d'intégration SQL** — base MySQL de test avec données synthétiques, couvrir les requêtes critiques de `RankingRepository` et `BulletinRepository`
3. **Cache analytics** — APCu/Redis sur les dashboards directeur/responsable, invalidation par `NotePublished`

### Priorité 2 — Architecture

4. **`AcademicContextService`** — service partagé validant (élève inscrit, période ouverte, classe active) — utilisé par `EvaluationService`, `NoteService`, `BulletinGenerator`
5. **Rendre `AuditService` fully statique** — méthodes `static`, appel via `AuditService::log(...)` uniforme dans tous les handlers (actuellement mixte instance/statique)
6. **Référentiel `annees_scolaires`** — table centralisée, validation cross-module

### Priorité 3 — Performance

7. **Batch UPDATE dans `NoteService`** — `publierTout()` et `verrouillerTout()` en une seule requête SQL
8. **Migration index `M_PERF_001`** — 6 index identifiés, amélioration estimée 60-80% sur les requêtes analytiques
9. **Préchargement élèves dans `genererBulletinsClasse()`** — 1 JOIN au lieu de N appels `infoEleve()`

### Priorité 4 — Fonctionnel (nouvelles fonctionnalités V2.1)

10. **Controller Bulletin** — exposer `BulletinGenerator` via un `BulletinController` avec routes `/v2/academique/bulletins/*`
11. **Controller Analytics** — exposer `AcademicAnalyticsService` via routes `/v2/academique/analytics/*` et vues dashboard
12. **Export PDF** — intégrer une lib PDF (TCPDF/DomPDF) connectée à `BulletinGenerator::exportHtml()`
13. **Notification parents** — `NotificationHandler` sur `BulletinPublished`
14. **Policy Analytics** — formaliser une `AnalyticsPolicy` (actuellement sans Policy dédiée)

---

## 10. Déclaration de gel

```
MODULE : Academique V2
VERSION : 2.8.0
DATE : 2026-06-30
ÉTAT : GELÉ — NO-GO conditionnel

Conditions de GO :
  [ ] AN-C-003 — Namespace AuditService corrigé (NoteHandler, AverageHandler)
  [ ] AN-C-003 — Appels statiques AuditService corrigés (Bulletin/Ranking/Analytics Handler)
  [ ] AN-C-002 — BulletinPolicy::canViewForParent() implémentée
  [ ] AN-C-001 — Validation inscription/année scolaire dans EvaluationService

Architecture gelée :
  ✅ AcademicCalculationService — source unique de vérité pour les calculs
  ✅ RankingEngine — source unique de vérité pour les classements
  ✅ BulletinGenerator — source unique de vérité pour les bulletins
  ✅ AcademicAnalyticsService — source unique de vérité pour les analytics
  ✅ 6 Value Objects immuables
  ✅ 2 Contracts (BulletinGeneratorInterface, AcademicAnalyticsInterface)
  ✅ 10 DTOs readonly
  ✅ 7 Policies RBAC
  ✅ 8 Repositories (pattern respecté)
  ✅ 27 événements / 7 handlers
  ✅ 66 routes /v2/academique/* (coexistence V1)

Tests :
  ✅ 338/338 assertions PASS (4 suites, 53 sections)
  ✅ Zéro TODO/FIXME dans le code source
  ✅ Zéro dépendance circulaire inter-modules

Signatures :
  Architecture :  VALIDÉE
  Calculs :       VALIDÉS
  Tests :         VALIDÉS
  Sécurité :      CONDITIONNELLE (3 corrections requises)
  Performances :  CONDITIONNELLE (index à créer)
```

---

*Rapport produit — ecole_app Phase 2.10 Academic Module Freeze*
*Prochaine phase : correction des anomalies critiques puis Phase 3.0 (Module Finance/Comptabilité V2)*
