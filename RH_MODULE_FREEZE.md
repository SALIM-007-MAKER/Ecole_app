# RH_MODULE_FREEZE.md — Phase 6.13

## Module Ressources Humaines V2 — Déclaration de gel d'architecture

```
╔══════════════════════════════════════════════════════════════════╗
║  MODULE RH V2 — ARCHITECTURE FROZEN                             ║
║  Version : 2.0.0                                                ║
║  Date    : 2026-07-02                                           ║
║  Score   : 9.3 / 10 — GO                                       ║
║  Statut  : STABLE — DÉPENDANCE EXTERNE AUTORISÉE               ║
╚══════════════════════════════════════════════════════════════════╝
```

---

## 1. Périmètre du module

| Axe | Valeur |
|-----|--------|
| Sous-domaines | 10 |
| Tables SQL (`rh_`) | 44 |
| Migrations | 11 (`rh_001` → `rh_011`) |
| Routes | 157 |
| Services | 15 |
| Controllers | 12 |
| Repositories | 10 |
| Policies | 10 |
| DTOs | 29 |
| Événements | 49 |
| Listeners | 30 (10 actifs + 20 stubs) |
| Permissions | 58 (+1 `employee.view.own`) |
| Modèles | 10 |

---

## 2. Sous-domaines — état de chaque couche

### 2.1 Employés (`Employes/`)

| Couche | Fichiers | Statut |
|--------|----------|--------|
| Service | `EmployeeService.php` — 8 méthodes publiques | ✅ FROZEN |
| Repository | `EmployeeRepository.php` | ✅ FROZEN |
| Controller | `EmployeeController.php` — 12 actions | ✅ FROZEN |
| DTOs | `EmployeeDTO`, `EmployeeFiltersDTO` | ✅ FROZEN |
| Policy | `EmployeePolicy.php` | ✅ FROZEN |
| Events | `EmployeeCreated`, `EmployeeUpdated`, `EmployeeArchived`, `EmployeeRestored` | ✅ FROZEN |
| Listeners | `AuditListener` ✅ · `NotificationListener` ⏳ · `StatisticsListener` ⏳ | PARTIEL |
| SQL | `rh_001` + `rh_002` (rh_employes, rh_contacts_urgence) | ✅ FROZEN |

**Correction appliquée** : `EmployeeUpdated` → `AuditListener::onUpdated()` utilisait `logUpdate(5 args)` → corrigé en `log(7 args)`.

---

### 2.2 Enseignants (`Enseignants/`)

| Couche | Fichiers | Statut |
|--------|----------|--------|
| Service | `TeacherService.php` — 6 méthodes publiques | ✅ FROZEN |
| Repository | `TeacherRepository.php` | ✅ FROZEN |
| Controller | `TeacherController.php` — 11 actions | ✅ FROZEN |
| DTOs | `TeacherDTO`, `TeacherFiltersDTO`, `QualificationDTO` | ✅ FROZEN |
| Policy | `TeacherPolicy.php` | ✅ FROZEN |
| Events | `TeacherCreated`, `TeacherUpdated`, `TeacherAssigned`, `TeacherQualificationUpdated` | ✅ FROZEN |
| Listeners | `AuditListener` ✅ · `NotificationListener` ⏳ · `StatisticsListener` ⏳ | PARTIEL |
| SQL | `rh_003` (rh_enseignants, rh_enseignant_matieres, rh_enseignant_qualifications) | ✅ FROZEN |

**Corrections appliquées** : `AuditListener` — 4 méthodes réécrites avec la bonne API `AuditService`.

---

### 2.3 Organisation (`Organisation/`)

| Couche | Fichiers | Statut |
|--------|----------|--------|
| Services | `DepartmentService`, `OrganizationService`, `PositionService` | ✅ FROZEN |
| Repository | `OrganizationRepository.php` | ✅ FROZEN |
| Controllers | `DepartmentController`, `OrganizationController`, `PositionController` | ✅ FROZEN |
| DTOs | `DepartmentDTO`, `ServiceDTO`, `PositionDTO`, `OrganizationFiltersDTO` | ✅ FROZEN |
| Policy | `OrganizationPolicy.php` | ✅ FROZEN |
| Events | `DepartmentCreated`, `DepartmentUpdated`, `PositionCreated`, `PositionAssigned`, `OrganizationUpdated` | ✅ FROZEN |
| SQL | `rh_001` + `rh_004` (ALTER + rh_services, rh_fonctions, rh_employe_fonctions, rh_historique_org) | ✅ FROZEN |

---

### 2.4 Contrats (`Contrats/`)

| Couche | Fichiers | Statut |
|--------|----------|--------|
| Service | `ContractService.php` — 11 méthodes, machine d'états 6 statuts | ✅ FROZEN |
| Repository | `ContractRepository.php` | ✅ FROZEN |
| Controller | `ContractController.php` — 16 actions | ✅ FROZEN |
| DTOs | `ContractDTO`, `ContractFiltersDTO`, `AvenantDTO` | ✅ FROZEN |
| Policy | `ContractPolicy.php` | ✅ FROZEN |
| Events | `ContractCreated`, `ContractUpdated`, `ContractRenewed`, `ContractExpired`, `ContractTerminated` | ✅ FROZEN |
| SQL | `rh_005` (rh_contrats, rh_contrat_avenants, rh_contrat_sequences) | ✅ FROZEN |

**Intégration validée** : `ContractTerminated` → déclenche `EmployeeService::changerStatut('inactif')` via handler.

---

### 2.5 Affectations (`Affectations/`)

| Couche | Fichiers | Statut |
|--------|----------|--------|
| Service | `AssignmentService.php` — 9 méthodes, types principale/secondaire/temporaire | ✅ FROZEN |
| Repository | `AssignmentRepository.php` | ✅ FROZEN |
| Controller | `AssignmentController.php` — 15 actions | ✅ FROZEN |
| DTOs | `AssignmentDTO`, `AssignmentFiltersDTO`, `MatiereAssignmentDTO` | ✅ FROZEN |
| Policy | `AssignmentPolicy.php` | ✅ FROZEN |
| Events | `AssignmentCreated`, `AssignmentUpdated`, `AssignmentTransferred`, `AssignmentArchived` | ✅ FROZEN |
| SQL | `rh_006` (rh_affectations, rh_affectation_matieres, rh_historique_affectations) | ✅ FROZEN |

---

### 2.6 Présences RH (`Presences/`)

| Couche | Fichiers | Statut |
|--------|----------|--------|
| Service | `AttendanceService.php` — pointage multi-mode, calculs auto durée/retard/hSup | ✅ FROZEN |
| Repository | `AttendanceRepository.php` | ✅ FROZEN |
| Controller | `AttendanceController.php` — 14 actions | ✅ FROZEN |
| DTOs | `AttendanceDTO`, `AttendanceFiltersDTO`, `RegularisationDTO` | ✅ FROZEN |
| Policy | `AttendancePolicy.php` | ✅ FROZEN |
| Events | `AttendanceCreated`, `AttendanceUpdated`, `AttendanceValidated`, `AttendanceLateDetected`, `AttendanceOvertimeDetected` | ✅ FROZEN |
| SQL | `rh_007` (rh_presences, rh_presences_regularisations) | ✅ FROZEN |

---

### 2.7 Congés (`Conges/`)

| Couche | Fichiers | Statut |
|--------|----------|--------|
| Service | `LeaveService.php` — 9 méthodes, machine d'états 7 statuts, 9 types de congés | ✅ FROZEN |
| Repository | `LeaveRepository.php` | ✅ FROZEN |
| Controller | `LeaveController.php` — 16 actions | ✅ FROZEN |
| DTOs | `LeaveDTO`, `LeaveFiltersDTO`, `LeaveSoldeDTO` | ✅ FROZEN |
| Policy | `LeavePolicy.php` | ✅ FROZEN |
| Events | `LeaveRequested`, `LeaveApproved`, `LeaveRejected`, `LeaveCancelled`, `LeaveStarted`, `LeaveFinished` | ✅ FROZEN |
| SQL | `rh_008` (rh_types_conges, rh_soldes_conges, rh_conges, rh_conges_historique, rh_conges_justificatifs) | ✅ FROZEN |

---

### 2.8 Évaluations RH (`Evaluations/`)

| Couche | Fichiers | Statut |
|--------|----------|--------|
| Service | `EvaluationService.php` — 12 méthodes, campagnes, critères pondérés, auto-éval, machine 7 statuts | ✅ FROZEN |
| Repository | `EvaluationRepository.php` | ✅ FROZEN |
| Controller | `EvaluationController.php` — 17 actions | ✅ FROZEN |
| DTOs | `EvaluationDTO`, `EvaluationFiltersDTO`, `CampagneDTO` | ✅ FROZEN |
| Policy | `EvaluationPolicy.php` | ✅ FROZEN |
| Events | `EvaluationCreated`, `EvaluationUpdated`, `EvaluationValidated`, `EvaluationPublished`, `DevelopmentPlanCreated` | ✅ FROZEN |
| SQL | `rh_009` (7 tables : campagnes, critères, évaluations, plans développement) | ✅ FROZEN |

---

### 2.9 Formations (`Formations/`)

| Couche | Fichiers | Statut |
|--------|----------|--------|
| Services | `TrainingService`, `CertificationService`, `CompetencyService`, `LearningPathService` | ✅ FROZEN |
| Repository | `TrainingRepository.php` | ✅ FROZEN |
| Controller | `TrainingController.php` — 20 actions | ✅ FROZEN |
| DTOs | `TrainingDTO`, `SessionDTO`, `TrainingFiltersDTO` | ✅ FROZEN |
| Policy | `TrainingPolicy.php` | ✅ FROZEN |
| Events | `TrainingCreated`, `TrainingSessionOpened`, `EmployeeEnrolled`, `TrainingCompleted`, `CertificationGranted`, `CertificationExpired`, `CompetencyValidated` | ✅ FROZEN |
| SQL | `rh_010` (10 tables : catalogue, sessions, inscriptions, présences, certifications, compétences) | ✅ FROZEN |

---

### 2.10 Documents RH (`Documents/`)

| Couche | Fichiers | Statut |
|--------|----------|--------|
| Service | `HRDocumentService.php` — 5 méthodes, versioning N+1, machine 5 statuts | ✅ FROZEN |
| Repository | `HRDocumentRepository.php` — `findExpiring(bool $includeSecret)` | ✅ FROZEN |
| Controller | `HRDocumentController.php` — 10 actions + guard canViewSecret | ✅ FROZEN |
| DTOs | `HRDocumentDTO`, `HRDocumentFiltersDTO` (avec `excludeSecret`) | ✅ FROZEN |
| Policy | `HRDocumentPolicy.php` — 7 méthodes dont `canViewSecret()` | ✅ FROZEN |
| Events | `HRDocumentCreated`, `HRDocumentUpdated`, `HRDocumentExpired`, `HRDocumentArchived` | ✅ FROZEN |
| SQL | `rh_011` (rh_documents, rh_document_versions, rh_document_historique) | ✅ FROZEN |

**Correction appliquée** : `canViewSecret()` désormais enforced dans `show()`, `index()`, `export()`, `expirations()`.

---

## 3. Interfaces publiques figées — Services

> Ces signatures ne doivent PAS être modifiées avant V2.1.  
> Tout ajout de méthode = nouvelle version mineure ; toute modification de signature = breaking change.

### EmployeeService

```php
public function creer(EmployeeDTO $dto, array $contacts, int $userId): int
public function modifier(int $id, EmployeeDTO $dto, array $contacts, int $userId): void
public function changerStatut(int $id, string $statut, int $userId): void
public function archiver(int $id, int $userId): void
public function restaurer(int $id, int $userId): void
public function exporterCsv(EmployeeFiltersDTO $filters): string
public function findById(int $id): ?array
public function paginate(EmployeeFiltersDTO $filters): array
```

### TeacherService

```php
public function creer(TeacherDTO $dto, int $userId): int
public function modifier(int $id, TeacherDTO $dto, int $userId): void
public function assignerMatieres(int $id, array $matieres, int $userId): void
public function ajouterQualification(int $id, QualificationDTO $dto, int $userId): int
public function archiver(int $id, int $userId): void
public function restaurer(int $id, int $userId): void
```

### ContractService

```php
public function creer(ContractDTO $dto, int $userId): int
public function modifier(int $id, ContractDTO $dto, int $userId): void
public function ajouterAvenant(int $contratId, AvenantDTO $dto, int $userId): int
public function renouveler(int $id, string $nouvelleDateDebut, ?string $nouvelleDateFin, int $userId): int
public function resilier(int $id, string $motif, int $userId): void
public function suspendre(int $id, int $userId): void
public function reactiver(int $id, int $userId): void
public function autoExpirer(): int   // batch
```

### LeaveService

```php
public function creer(LeaveDTO $dto, int $userId, string $userName): int
public function modifier(int $id, LeaveDTO $dto, int $userId, string $userName): void
public function soumettre(int $id, int $userId, string $userName): void
public function approuver(int $id, int $userId, string $userName): void
public function rejeter(int $id, string $motif, int $userId, string $userName): void
public function annuler(int $id, string $motif, int $userId, string $userName): void
public function demarrer(int $id, int $userId, string $userName): void
public function terminer(int $id, ?string $dateRetour, ?string $commentaire, int $userId, string $userName): void
```

### EvaluationService

```php
public function creerCampagne(CampagneDTO $dto, int $userId): int
public function activerCampagne(int $id, int $userId): void
public function cloturerCampagne(int $id, int $userId): void
public function creer(EvaluationDTO $dto, int $userId, string $userName): int
public function demarrerAutoEval(int $id, int $userId, string $userName): void
public function soumettreAutoEval(int $id, array $notes, string $commentaire, int $userId, string $userName): void
public function evaluerResponsable(int $id, array $notes, string $commentaire, int $userId, string $userName): void
public function valider(int $id, string $commentaire, int $userId, string $userName): void
public function publier(int $id, int $userId, string $userName): void
public function creerPlanDeveloppement(int $evalId, array $data, int $userId): int
```

### TrainingService

```php
public function creerFormation(TrainingDTO $dto, int $userId): int
public function creerSession(SessionDTO $dto, int $userId): int
public function ouvrirSession(int $id, int $userId): void
public function demarrerSession(int $id, int $userId): void
public function terminerSession(int $id, int $userId): void
public function annulerSession(int $id, int $userId): void
public function inscrire(int $sessionId, int $employeId, int $userId): int
public function validerInscription(int $inscriptionId, ?float $note, ?string $commentaire, int $userId): void
public function annulerInscription(int $inscriptionId, int $userId): void
```

### HRDocumentService

```php
public function creerDocument(HRDocumentDTO $dto, int $userId, string $userName): int
public function mettreAJour(int $id, HRDocumentDTO $dto, int $userId, string $userName): void
public function archiverDocument(int $id, int $userId, string $userName, ?string $notes = null): void
public function restaurerDocument(int $id, int $userId, string $userName): void
public function verifierExpirations(): int   // batch
```

---

## 4. Base de données — migrations validées

| Migration | Tables | Lignes SQL | Statut |
|-----------|--------|------------|--------|
| `rh_001_organisation.sql` | rh_departements, rh_postes | 64 | ✅ |
| `rh_002_employes.sql` | rh_employes, rh_contacts_urgence | 71 | ✅ |
| `rh_003_enseignants.sql` | rh_enseignants, rh_enseignant_matieres, rh_enseignant_qualifications | 92 | ✅ |
| `rh_004_organisation.sql` | ALTER + rh_services, rh_fonctions, rh_employe_fonctions, rh_historique_org | 122 | ✅ |
| `rh_005_contrats.sql` | rh_contrats, rh_contrat_avenants, rh_contrat_sequences | 97 | ✅ |
| `rh_006_affectations.sql` | rh_affectations, rh_affectation_matieres, rh_historique_affectations | 102 | ✅ |
| `rh_007_presences.sql` | rh_presences, rh_presences_regularisations | 142 | ✅ |
| `rh_008_conges.sql` | rh_types_conges, rh_soldes_conges, rh_conges, rh_conges_historique, rh_conges_justificatifs | 134 | ✅ |
| `rh_009_evaluations.sql` | rh_campagnes_evaluation, rh_criteres_evaluation, rh_campagne_criteres, rh_evaluations, rh_evaluation_criteres, rh_evaluation_historique, rh_plans_developpement | 167 | ✅ |
| `rh_010_formations.sql` | rh_formations_organismes, rh_formations_catalogue, rh_formations_sessions, rh_formations_inscriptions, rh_formations_presences, rh_certifications, rh_employe_certifications, rh_competences, rh_employe_competences, rh_formation_competences | 217 | ✅ |
| `rh_011_documents.sql` | rh_documents, rh_document_versions, rh_document_historique | 99 | ✅ |

**Total** : 11 migrations · 44 tables · 1 307 lignes SQL · 0 `migrations_pending`

### Invariants SQL validés

| Invariant | Statut |
|-----------|--------|
| Toutes les tables en `IF NOT EXISTS` | ✅ |
| `deleted_at TIMESTAMP NULL` sur toutes les tables actives | ✅ |
| `ON DELETE RESTRICT` sur entités parent critiques (`rh_employes`) | ✅ |
| `ON DELETE CASCADE` sur tables enfant (versions, historique) | ✅ |
| `ON DELETE SET NULL` sur FK optionnelles | ✅ |
| Colonnes ENUM explicitement contraintes | ✅ |
| `AUTO_INCREMENT` sur toutes les PKs | ✅ |
| Index sur clés de recherche fréquentes | ✅ |
| Pas de `DROP TABLE` dans aucune migration | ✅ |

---

## 5. Événements validés (49)

| Domaine | Événements | Listeners actifs |
|---------|------------|-----------------|
| Employés (4) | `EmployeeCreated/Updated/Archived/Restored` | AuditListener ✅ |
| Enseignants (4) | `TeacherCreated/Updated/Assigned/QualificationUpdated` | AuditListener ✅ |
| Organisation (5) | `DepartmentCreated/Updated`, `PositionCreated/Assigned`, `OrganizationUpdated` | AuditListener ✅ |
| Contrats (5) | `ContractCreated/Updated/Renewed/Expired/Terminated` | AuditListener ✅ |
| Affectations (4) | `AssignmentCreated/Updated/Transferred/Archived` | AuditListener ✅ |
| Présences (5) | `AttendanceCreated/Updated/Validated/LateDetected/OvertimeDetected` | AuditListener ✅ |
| Congés (6) | `LeaveRequested/Approved/Rejected/Cancelled/Started/Finished` | AuditListener ✅ |
| Évaluations (5) | `EvaluationCreated/Updated/Validated/Published`, `DevelopmentPlanCreated` | AuditListener ✅ |
| Formations (7) | `TrainingCreated/SessionOpened/EmployeeEnrolled/TrainingCompleted/CertificationGranted/CertificationExpired/CompetencyValidated` | AuditListener ✅ |
| Documents (4) | `HRDocumentCreated/Updated/Expired/Archived` | AuditListener ✅ |

**Règle figée** : `EventDispatcher::dispatch()` exclusivement depuis les **Services**. Jamais depuis les Controllers.

---

## 6. Permissions validées (59)

| Domaine | Permissions | Admin | Directeur | Secrétaire | Comptable | Enseignant |
|---------|:-----------:|:-----:|:---------:|:----------:|:---------:|:---------:|
| Employés (6) | view/create/update/archive/restore/export | ✅ 6 | ✅ 6 | ✅ 4 | ✅ 2 | — |
| Enseignants (5) | view/create/update/assign/export | ✅ 5 | ✅ 5 | ✅ 3 | — | ✅ 1 |
| Organisation (5) | view/create/update/archive/export | ✅ 5 | ✅ 5 | ✅ 3 | — | — |
| Contrats (7) | view/create/update/renew/terminate/archive/export | ✅ 7 | ✅ 7 | ✅ 5 | — | — |
| Affectations (5) | view/create/update/archive/export | ✅ 5 | ✅ 5 | ✅ 5 | — | — |
| Présences RH (5) | view/create/update/validate/export | ✅ 5 | ✅ 5 | ✅ 3 | ✅ 2 | — |
| Congés (7) | view/create/update/approve/reject/cancel/export | ✅ 7 | ✅ 7 | ✅ 5 | ✅ 2 | ✅ 2 |
| Évaluations (6) | view/create/update/validate/publish/export | ✅ 6 | ✅ 6 | — | ✅ 1 | ✅ 2 |
| Formations (7) | view/create/update/enroll/validate/export/manage_catalog | ✅ 7 | ✅ 7 | ✅ 5 | ✅ 2 | ✅ 1 |
| Documents RH (5) | view/create/update/archive/export | ✅ 5 | ✅ 5 | ✅ 4 | ✅ 2 | ✅ 1 |
| Spéciale | `employee.view.own` | — | — | — | — | ✅ |

**Aucune permission orpheline** — toutes les permissions déclarées dans `config/permissions.php` sont utilisées par au moins une `Policy`.  
**`canViewSecret()`** : contrôle d'accès aux documents confidentiels réservé admin/directeur — **enforced** dans le Controller.

---

## 7. Architecture — vérifications transversales

| Point de contrôle | Statut |
|-------------------|--------|
| Namespace unifié `App\Modules\RH\` | ✅ |
| Autoloader PSR-4 résout tous les sous-espaces | ✅ |
| Aucun couplage fort inter-domaines (pas d'`use` direct entre Services RH) | ✅ |
| Intégrations cross-domaines via Events uniquement | ✅ |
| AuditService instancié via `new AuditService()` dans chaque Listener | ✅ |
| `AuditService::diff()` déclarée `public` (corrigé 6.12) | ✅ |
| `verifyCsrf()` sur tous les POST | ✅ |
| `requirePermission()` en tête de chaque action | ✅ |
| PDO Prepared Statements — pas de concaténation | ✅ |
| Soft delete — `deleted_at` uniquement, jamais `DELETE FROM` | ✅ |
| Routes statiques avant wildcards `{id}` | ✅ |

---

## 8. Compatibilité

### 8.1 V1 — Zéro régression

| Critère | Statut |
|---------|--------|
| Aucun fichier V1 modifié | ✅ |
| Aucune route V1 supprimée ou altérée | ✅ |
| Aucun contrôleur V1 impacté | ✅ |
| Aucune table V1 modifiée (pas d'ALTER sur tables existantes sans `rh_` prefix) | ✅ |
| Coexistence `/v2/rh/*` avec routes V1 | ✅ |

### 8.2 Préparation API V3 / Mobile

| Point | Implémentation |
|-------|---------------|
| Champ `reference_externe` (Documents) | Pivot vers DocumentService V2/V3 |
| Champ `metadata JSON` (Documents) | Données libres pour API V3 |
| `employee.view.own` permission | Prépare endpoint `/api/v3/rh/mon-dossier` |
| Soft delete universel | Compatible avec synchronisation mobile offline |
| `rh_historique_affectations` | Historique JSON — compatible multi-établissement V3 |
| `rh_presences_regularisations` | Tracé pour audit mobile |

### 8.3 Réseau / Performance

| Point | Statut |
|-------|--------|
| Pagination sur toutes les listes | ✅ (`perPage` configurable dans FiltersDTOs) |
| Requêtes SQL avec `LIMIT/OFFSET` | ✅ |
| Index sur colonnes de recherche fréquente | ✅ |
| Export CSV streamed via `php://output` | ✅ |
| Pas de `N+1` queries identifié | ✅ |

---

## 9. Sécurité — audit final

| Point | Statut |
|-------|--------|
| Injection SQL — aucune concaténation `$_GET/$_POST` dans SQL | ✅ |
| CSRF — `verifyCsrf()` sur tous les POST | ✅ |
| XSS — `e()` (`htmlspecialchars ENT_QUOTES UTF-8`) dans toutes les vues | ✅ |
| RBAC — `requirePermission()` en tête de chaque action | ✅ |
| Documents secrets — `canViewSecret()` enforced (corrigé 6.12) | ✅ |
| Machines d'états — `assertTransition()` guard avant chaque changement | ✅ |
| Soft delete — jamais de destruction de données | ✅ |
| `AuditService` trace toutes les mutations | ✅ |

---

## 10. Performance — observations

| Point | Observation |
|-------|-------------|
| `loadEmployes()` — requête directe dans 3 controllers | ⚠️ DT-RH-1 (cf. dette) |
| `rh_employes` — utilisé par 9/10 sous-domaines | Pas d'index supplémentaire nécessaire (PK + actif) |
| `rh_presences` — forte volumétrie attendue | Index sur `employe_id + date_pointage` présent |
| `rh_conges` — recherches sur `statut + employe_id` | Index composé présent |
| Export CSV — streamed, pas de buffer mémoire | ✅ |

---

## 11. Dette technique résiduelle — GO accepté

| ID | Sévérité | Description | Prévu en |
|----|----------|-------------|----------|
| DT-RH-1 | Mineur | `loadEmployes()` direct PDO dupliqué dans `EvaluationController`, `TrainingController`, `HRDocumentController` — à extraire dans `EmployeeRepository::findActifs()` | V2.1 |
| DT-RH-2 | Mineur | `verifierExpirations()` dans `CertificationService` + `HRDocumentService` non branchés sur cron automatique | V2.1 Scheduling |
| DT-RH-3 | Futur | 20 stubs `NotificationListener` + `StatisticsListener` (10 domaines × 2) — à brancher sur `NotificationService` + `DashboardMetrics` | V3 |
| DT-RH-4 | Futur | Pas de validation anti-doublon contrat actif par employé — règle à définir avec le client | V2.1 métier |
| DT-RH-5 | Futur | Paie salariale non implémentée — domaine différé (nécessite module Finance + Comptabilité) | V2.2 Paie |

**Dettes critiques** : **0**  
**Dettes bloquantes** : **0**

---

## 12. Éléments reportés en V2.1

| Élément | Raison du report |
|---------|-----------------|
| Module de Paie | Dépendance sur Finance V2 + règles légales pays |
| Cron scheduler | Infrastructure manquante (pas de daemon V2) |
| `NotificationService` | Service V3 non implémenté |
| `DashboardMetrics` central | Besoin de l'analytique cross-modules |
| `DocumentService V2` physique | Module Documents V2 séparé |
| Multi-établissement affectations | Architecture V3 |

---

## 13. Score par couche

| Couche | Score | Notes |
|--------|-------|-------|
| Architecture MVC | 10/10 | Pattern pur, events-only writes, controllers thin |
| Base de données | 10/10 | 44 tables, 11 migrations, 0 pending, FK correctes |
| Services (APIs publiques) | 9/10 | 3 critiques corrigées en 6.12 ; `loadEmployes` duplication DT |
| Repositories | 9/10 | SQL pur PDO ; enrichissement `findExpiring(includeSecret)` |
| DTOs | 10/10 | Validation, `fromRequest`, `toArray` cohérents |
| Policies | 10/10 | `canViewSecret` enforced ; granularité fine |
| RBAC | 10/10 | 59 permissions, 0 orphelines, 5 rôles couverts |
| Events / Listeners | 9/10 | 49 events ; 20 stubs (dette future acceptable) |
| Sécurité | 9/10 | Toutes les failles connues corrigées |
| Compatibilité V1 | 10/10 | Zéro régression |
| Performance | 9/10 | `loadEmployes` duplication ; export streamed OK |
| Dette technique | 8/10 | 5 dettes documentées, 0 bloquante |

**Score global : 9.3 / 10**

---

## 14. Décision finale

```
════════════════════════════════════════════════════════════════════
  MODULE RH V2 — PHASE 6.13 — MODULE FREEZE
  
  Version : 2.0.0
  Score   : 9.3 / 10
  
  ✅ ARCHITECTURE FROZEN
  ✅ GO — MODULE OFFICIELLEMENT TERMINÉ
  
  Le module RH V2 est déclaré STABLE.
  Il peut être utilisé comme DÉPENDANCE par les modules futurs.
  Aucune modification structurelle avant V2.1.
  
  Sous-domaines gelés (10/10) :
    ✅ Employés          ✅ Enseignants
    ✅ Organisation      ✅ Contrats
    ✅ Affectations      ✅ Présences RH
    ✅ Congés            ✅ Évaluations
    ✅ Formations        ✅ Documents RH
  
  Prochaine modification autorisée : V2.1
    → loadEmployes() refactorisé
    → crons branchés
    → NotificationService connecté
════════════════════════════════════════════════════════════════════
```

---

*Produit automatiquement le 2026-07-02 — Phase 6.13 — Claude Sonnet 4.6*
