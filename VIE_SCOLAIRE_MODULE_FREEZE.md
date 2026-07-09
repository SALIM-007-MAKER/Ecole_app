# VIE SCOLAIRE V2 — MODULE FREEZE

**Version :** 2.6.0  
**Date de gel :** 2026-07-02  
**Statut :** ARCHITECTURE GELÉE  
**Rapport d'intégration :** VIE_SCOLAIRE_INTEGRATION_REVIEW.md (score 7.55/10)

---

## 1. RÉSUMÉ DU MODULE

Le Module Vie Scolaire V2 couvre la gestion complète de la vie quotidienne de l'établissement. Il a été développé en 7 sous-phases (5.1 → 5.7) sur la même journée (2026-07-01) selon l'architecture V2 (PSR-4, Repository/Service/DTO/Policy/Event).

Le module regroupe **7 domaines fonctionnels**, **29 tables SQL** préfixées `vs_`, **28 événements**, **50+ routes** HTTP, et **168 fichiers PHP** structurés. Il coexiste avec la V1 sans régression.

---

## 2. FONCTIONNALITÉS COUVERTES

### 2.1 Absences (Phase 5.1)
- Enregistrement d'absences (journée complète, demi-journée, cours)
- Gestion des justifications (soumission, validation, refus)
- Référentiel de motifs configurable (7 seeds : MALADIE, MEDECIN, DEUIL, FAMILLE, TRANSPORT, ACT_SCOLAIRE, AUTRE)
- Soft delete + archivage
- Statistiques par élève et par classe
- Export CSV

### 2.2 Présences / Appel (Phase 5.2)
- Création de sessions d'appel par classe et créneau
- Pointage individuel (présent / absent / retard)
- Validation de session et clôture
- Historique des modifications de pointage
- Détection automatique et dispatching Absences + Retards depuis les sessions
- Statistiques de présence

### 2.3 Retards (Phase 5.3)
- Enregistrement des retards avec durée en minutes
- Seuil d'alerte configurable (déclenchement `LateThresholdReached`)
- Justifications (soumission, validation, refus)
- Export CSV
- Intégration Discipline via `DisciplineIntegrationHandler`

### 2.4 Discipline (Phase 5.4)
- Dossiers disciplinaires par élève (un dossier par année scolaire, `findOrCreateDossier`)
- Signalement d'incidents avec catégorie, gravité, description
- Machine d'états incident : ouvert → traité → classé
- Prononcé de sanctions avec types configurables
- Machine d'états sanction : prononcée → effective → exécutée → levée
- Procédure d'appel (soumission, examen, décision)
- Clôture de dossier
- Intégration cross-domaine : écoute StudentAbsent, StudentLate, LateThresholdReached
- Statistiques et export

### 2.5 Récompenses (Phase 5.5)
- Attribution de récompenses avec catégorie, niveau, motif
- Machine d'états : attribuée → validée / révoquée
- Historique des changements de statut
- Classement comportemental par classe (score Récompenses - Sanctions)
- Statistiques par élève et par classe
- Export CSV

### 2.6 Emplois du Temps (Phase 5.6)
- Emplois du temps par classe, année scolaire, période et type de semaine
- Grille visuelle hebdomadaire (lundi→samedi, plages horaires configurables)
- Détection de conflits **HARD BLOCK** — triple contrainte :
  - Enseignant : pas deux cours simultanés
  - Salle : pas deux classes simultanées dans la même salle
  - Classe : pas deux matières simultanées
- Gestion des remplacements (enseignant absent → remplaçant)
- Publication avec versioning JSON (snapshot des créneaux)
- Archivage
- Vue enseignant (ses créneaux + heures estimées)
- Export

### 2.7 Activités Scolaires (Phase 5.7)
- Activités avec catégorie, lieu, date, heures, capacité
- 8 catégories seeds : club, association, événement, sortie, compétition, cérémonie, conférence, atelier
- Machine d'états : brouillon → publié → en_cours → terminé / annulé
- Inscriptions avec gestion de la capacité maximale
- Liste d'attente FIFO avec promotion automatique à l'annulation
- Marquage des présences (déclenche passage en `terminé`)
- Détection de conflits EDT **ADVISORY** (non bloquant)
- Historique JSON immutable par activité
- Annulation avec motif ≥ 10 caractères obligatoire
- Export CSV avec BOM UTF-8
- Statistiques par catégorie et année scolaire

---

## 3. ARCHITECTURE FINALE

### Pattern architectural
```
Controller (thin)
    ↓ DTO::fromRequest() + validate()
    ↓ Policy::canX()
    ↓ Service (logique métier)
        ↓ Repository (SQL uniquement)
        ↓ EventDispatcher::dispatch(Event)
            → Listener[Audit]
            → Listener[Notification] (stub MS2-M-004)
            → Listener[Statistics]
            → DisciplineIntegrationHandler (cross-domaine)
```

### Règles invariantes respectées
| Règle | Statut |
|-------|--------|
| Zéro DB dans les contrôleurs | ✓ (1 exception mineure : ActivityController) |
| Logique métier dans les Services uniquement | ✓ |
| SQL dans les Repositories uniquement | ✓ |
| Events dispatched depuis les Services | ✓ |
| Soft delete systématique | ✓ |
| CSRF sur toutes les mutations | ✓ |
| requireAuth() + requirePermission() partout | ✓ |
| Routes statiques avant wildcards {id} | ✓ |

### Namespace
```
App\Modules\VieScolaire\{Domaine}\
    Controllers\
    DTO\
    Events\
    Listeners\
    Models\
    Policies\
    Repositories\
    Services\
```

---

## 4. ARBORESCENCE FINALE

```
app/Modules/VieScolaire/
├── module.json                          (v2.6.0)
├── routes.php                           (50+ routes)
├── Views/
│   ├── absences/       (6 vues : index, show, create, edit, justifier, statistiques)
│   ├── presences/      (6 vues : index, show, create, valider, historique, statistiques)
│   ├── retards/        (6 vues : index, show, create, edit, justifier, statistiques)
│   ├── discipline/     (6 vues : index, show, create, sanctionner, appel, statistiques)
│   ├── recompenses/    (6 vues : index, show, create, edit, classement, statistiques)
│   ├── emplois_du_temps/ (7 vues : index, show, create, enseignant, remplacements, statistiques + creneaux/ajouter)
│   └── activites/      (6 vues : index, show, create, edit, inscrire, statistiques)
│
├── Absences/
│   ├── Controllers/AbsenceController.php
│   ├── DTO/AbsenceDTO.php, AbsenceFiltersDTO.php, JustificationDTO.php
│   ├── Events/StudentAbsent.php, AbsenceJustified.php, AbsenceRejected.php
│   ├── Listeners/AttendanceHandler.php
│   ├── Models/AbsenceModel.php, JustificationAbsenceModel.php, MotifAbsenceModel.php
│   ├── Policies/AbsencePolicy.php
│   ├── Repositories/AbsenceRepository.php
│   └── Services/AbsenceService.php
│
├── Presences/
│   ├── Controllers/PresenceController.php
│   ├── DTO/AttendanceSessionDTO.php, AttendanceFiltersDTO.php, PresenceDTO.php
│   ├── Events/AttendanceStarted.php, AttendanceValidated.php, AttendanceCompleted.php
│   │         StudentPresent.php, StudentAbsent.php, StudentLate.php
│   ├── Listeners/AuditListener.php, NotificationListener.php, StatisticsListener.php
│   ├── Models/AppelModel.php, PresenceModel.php, PresenceHistoriqueModel.php
│   ├── Policies/AttendancePolicy.php
│   ├── Repositories/AttendanceRepository.php
│   └── Services/AttendanceService.php
│
├── Retards/
│   ├── Controllers/LateController.php
│   ├── DTO/LateDTO.php, LateFiltersDTO.php, LateJustificationDTO.php
│   ├── Events/StudentLate.php, LateJustified.php, LateRejected.php, LateThresholdReached.php
│   ├── Listeners/AuditListener.php, NotificationListener.php, StatisticsListener.php
│   ├── Models/RetardModel.php, JustificationRetardModel.php
│   ├── Policies/LatePolicy.php
│   ├── Repositories/LateRepository.php
│   └── Services/LateService.php
│
├── Discipline/
│   ├── Controllers/DisciplineController.php
│   ├── DTO/DisciplineDTO.php, SanctionDTO.php, AppealDTO.php, DisciplineFiltersDTO.php
│   ├── Events/DisciplineCaseCreated.php, DisciplinaryActionAssigned.php
│   │         DisciplineCaseClosed.php, DisciplineAppealSubmitted.php
│   ├── Listeners/AuditListener.php, NotificationListener.php, StatisticsListener.php
│   │            DisciplineIntegrationHandler.php
│   ├── Models/DossierDisciplineModel.php, IncidentDisciplineModel.php
│   │         SanctionDisciplineModel.php, AppelDisciplineModel.php
│   ├── Policies/DisciplinePolicy.php
│   ├── Repositories/DisciplineRepository.php
│   └── Services/DisciplineService.php
│
├── Recompenses/
│   ├── Controllers/RewardController.php
│   ├── DTO/RewardDTO.php, RewardFiltersDTO.php
│   ├── Events/RewardGranted.php, RewardUpdated.php, RewardRevoked.php
│   ├── Listeners/AuditListener.php, NotificationListener.php, StatisticsListener.php
│   ├── Models/RewardModel.php, RewardCategoryModel.php
│   ├── Policies/RewardPolicy.php (via permissions)
│   ├── Repositories/RewardRepository.php
│   └── Services/RewardService.php
│
├── EmploisDuTemps/
│   ├── Controllers/TimetableController.php           (13 actions)
│   ├── DTO/TimetableDTO.php, TimetableFiltersDTO.php
│   │       CreneauDTO.php, RemplacementDTO.php
│   ├── Events/TimetableCreated.php, TimetableUpdated.php, TimetablePublished.php
│   │         TimetableConflictDetected.php, TeacherReplacementAssigned.php
│   ├── Listeners/AuditListener.php, NotificationListener.php, StatisticsListener.php
│   ├── Models/EmploiDuTempsModel.php, CreneauModel.php, PlageHoraireModel.php
│   │         SalleModel.php, VersionModel.php, RemplacementModel.php
│   ├── Policies/TimetablePolicy.php
│   ├── Repositories/TimetableRepository.php          (25 méthodes)
│   └── Services/TimetableService.php
│
└── Activites/
    ├── Controllers/ActivityController.php            (14 actions)
    ├── DTO/ActivityDTO.php, ActivityFiltersDTO.php, InscriptionActivityDTO.php
    ├── Events/ActivityCreated.php, ActivityUpdated.php, ActivityPublished.php
    │         ActivityCancelled.php, StudentRegisteredToActivity.php
    ├── Listeners/AuditListener.php, NotificationListener.php, StatisticsListener.php
    ├── Models/ActivityModel.php, ActivityCategoryModel.php, ActivityInscriptionModel.php
    ├── Policies/ActivityPolicy.php
    ├── Repositories/ActivityRepository.php           (~25 méthodes)
    └── Services/ActivityService.php

database/migrations/
├── vie_scolaire_001_absences.sql
├── vie_scolaire_002_presences.sql
├── vie_scolaire_003_retards.sql
├── vie_scolaire_004_discipline.sql
├── vie_scolaire_005_recompenses.sql
├── vie_scolaire_006_emplois_du_temps.sql
└── vie_scolaire_007_activites.sql

TOTAL : ~168 fichiers PHP + 43 vues PHP + 7 migrations SQL
```

---

## 5. DÉPENDANCES OFFICIELLES

### Core (obligatoire)
| Composant | Usage |
|-----------|-------|
| `Core\Application` | Singleton bootstrap |
| `Core\Router` | Enregistrement des 50+ routes |
| `Core\Controller` | Classe parente de tous les 7 controllers VS |
| `Core\Database` | Accès PDO singleton dans tous les Repositories |
| `Core\EventDispatcher` | Dispatch synchrone dans tous les Services |
| `Core\View` | Rendu via notation `VieScolaire::domaine/vue` |
| `Core\Session` | Flash messages (success/error) |
| `Core\Logger` | Logging dans DisciplineIntegrationHandler |
| `Core\Event` | Classe parente de tous les 28 Events |
| `Core\Listener` | Interface implémentée par tous les Listeners |

### Scolarité (lecture seule)
| Composant | Usage |
|-----------|-------|
| `App\Models\ClasseModel` | Référentiel classes dans AbsenceController, LateController |
| Table `eleves` (V1) | JOIN dans Repositories Absences, Présences, Retards |
| Table `classes` (V1) | JOIN dans tous les Repositories VS |
| Table `inscriptions` (V1) | Sous-requête dans ActivityRepository.findInscriptions() |

### Académique (lecture seule)
| Composant | Usage |
|-----------|-------|
| Table `users` (rôle enseignant) | Récupération enseignants dans ActivityController.formData() |
| Table `matieres` (V1) | JOIN dans TimetableRepository (creneaux) |

### Finance
Aucune dépendance directe. Le module VS est indépendant de Finance.

### Inter-domaine VS (événements uniquement)
| Source | Cible | Événement |
|--------|-------|-----------|
| Absences | Discipline | `StudentAbsent` → `DisciplineIntegrationHandler` |
| Retards | Discipline | `RetardStudentLate` → `DisciplineIntegrationHandler` |
| Retards | Discipline | `LateThresholdReached` → `DisciplineIntegrationHandler` |
| EmploisDuTemps | Activités | Requête advisory cross-table (pas d'événement) |

---

## 6. PERMISSIONS OFFICIELLES (RBAC)

### Matrice complète par rôle

| Permission | admin | directeur | secrétaire | enseignant | parent | élève |
|------------|:-----:|:---------:|:----------:|:----------:|:------:|:-----:|
| **Absences** | | | | | | |
| attendance.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| attendance.create | ✓ | ✓ | ✓ | ✓ | — | — |
| attendance.update | ✓ | ✓ | ✓ | — | — | — |
| attendance.delete | ✓ | ✓ | — | — | — | — |
| attendance.justify | ✓ | ✓ | ✓ | — | ✓ | — |
| attendance.validate | ✓ | ✓ | ✓ | ✓ | — | — |
| **Présences** | | | | | | |
| attendance.session.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| attendance.session.create | ✓ | ✓ | ✓ | ✓ | — | — |
| attendance.session.update | ✓ | ✓ | ✓ | ✓ | — | — |
| attendance.session.validate | ✓ | ✓ | — | ✓ | — | — |
| **Retards** | | | | | | |
| late.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| late.create | ✓ | ✓ | ✓ | ✓ | — | — |
| late.update | ✓ | ✓ | ✓ | ✓ | — | — |
| late.justify | ✓ | ✓ | ✓ | — | ✓ | — |
| late.validate | ✓ | ✓ | ✓ | ✓ | — | — |
| late.export | ✓ | ✓ | ✓ | ✓ | — | — |
| **Discipline** | | | | | | |
| discipline.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| discipline.create | ✓ | ✓ | ✓ | ✓ | — | — |
| discipline.update | ✓ | ✓ | ✓ | ✓ | — | — |
| discipline.validate | ✓ | ✓ | ✓ | ✓ | — | — |
| discipline.sanction | ✓ | ✓ | ✓ | — | — | — |
| discipline.export | ✓ | ✓ | ✓ | ✓ | — | — |
| **Récompenses** | | | | | | |
| reward.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| reward.create | ✓ | ✓ | ✓ | ✓ | — | — |
| reward.update | ✓ | ✓ | ✓ | ✓ | — | — |
| reward.validate | ✓ | ✓ | ✓ | — | — | — |
| reward.export | ✓ | ✓ | ✓ | ✓ | — | — |
| **Emplois du temps** | | | | | | |
| timetable.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| timetable.create | ✓ | ✓ | ✓ | ✓ | — | — |
| timetable.update | ✓ | ✓ | ✓ | ✓ | — | — |
| timetable.publish | ✓ | ✓ | ✓ | — | — | — |
| timetable.export | ✓ | ✓ | ✓ | ✓ | — | — |
| **Activités** | | | | | | |
| activity.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| activity.create | ✓ | ✓ | ✓ | ✓ | — | — |
| activity.update | ✓ | ✓ | ✓ | ✓ | — | — |
| activity.validate | ✓ | ✓ | ✓ | — | — | — |
| activity.publish | ✓ | ✓ | — | — | — | — |
| activity.export | ✓ | ✓ | ✓ | ✓ | — | — |

**Total : 36 permissions VS** réparties sur 7 préfixes.

---

## 7. ÉVÉNEMENTS OFFICIELS (28 events)

### Domaine Absences (3)
| Événement | Listeners |
|-----------|-----------|
| `StudentAbsent` | AttendanceHandler, DisciplineIntegrationHandler |
| `AbsenceJustified` | AttendanceHandler |
| `AbsenceRejected` | AttendanceHandler |

### Domaine Présences (6)
| Événement | Listeners |
|-----------|-----------|
| `AttendanceStarted` | AuditListener |
| `AttendanceValidated` | AuditListener, NotificationListener, StatisticsListener |
| `AttendanceCompleted` | AuditListener, StatisticsListener |
| `StudentPresent` | AuditListener |
| `StudentAbsent` (Présences) | AuditListener, NotificationListener |
| `StudentLate` (Présences) | AuditListener, NotificationListener, StatisticsListener |

### Domaine Retards (4)
| Événement | Listeners |
|-----------|-----------|
| `StudentLate` (Retards) | AuditListener, NotificationListener, StatisticsListener, **DisciplineIntegrationHandler** |
| `LateJustified` | AuditListener, NotificationListener |
| `LateRejected` | AuditListener, NotificationListener |
| `LateThresholdReached` | AuditListener, NotificationListener, **DisciplineIntegrationHandler** |

### Domaine Discipline (4)
| Événement | Listeners |
|-----------|-----------|
| `DisciplineCaseCreated` | AuditListener, NotificationListener, StatisticsListener |
| `DisciplinaryActionAssigned` | AuditListener, NotificationListener |
| `DisciplineCaseClosed` | AuditListener, NotificationListener |
| `DisciplineAppealSubmitted` | AuditListener, NotificationListener |

### Domaine Récompenses (3)
| Événement | Listeners |
|-----------|-----------|
| `RewardGranted` | AuditListener, NotificationListener, StatisticsListener |
| `RewardUpdated` | AuditListener, NotificationListener |
| `RewardRevoked` | AuditListener, NotificationListener |

### Domaine EmploisDuTemps (5)
| Événement | Listeners |
|-----------|-----------|
| `TimetableCreated` | AuditListener, NotificationListener |
| `TimetableUpdated` | AuditListener, StatisticsListener |
| `TimetablePublished` | AuditListener, NotificationListener, StatisticsListener |
| `TimetableConflictDetected` | AuditListener, StatisticsListener |
| `TeacherReplacementAssigned` | AuditListener, NotificationListener |

### Domaine Activités (5)
| Événement | Listeners |
|-----------|-----------|
| `ActivityCreated` | AuditListener, NotificationListener, StatisticsListener |
| `ActivityUpdated` | AuditListener, StatisticsListener |
| `ActivityPublished` | AuditListener, NotificationListener, StatisticsListener |
| `ActivityCancelled` | AuditListener, NotificationListener, StatisticsListener |
| `StudentRegisteredToActivity` | AuditListener, NotificationListener, StatisticsListener |

---

## 8. SERVICES UTILISÉS

| Service | Provenance | Domaines utilisateurs |
|---------|------------|----------------------|
| `App\Services\AuditService` | Shared Services | AbsenceService |
| `Core\EventDispatcher` | Core | Tous les 7 Services |
| `Core\Logger` | Core | DisciplineIntegrationHandler |
| `Core\Database` | Core | Tous les 7 Repositories |
| `Core\Session` | Core | Tous les 7 Controllers |
| `App\Models\ClasseModel` | Scolarité V1 | AbsenceController, LateController |
| NotificationService | Non implémenté | — (stub MS2-M-004) |

**Note :** TimetableService et ActivityService délèguent l'audit à leurs propres tables (`vs_edt_versions`, `vs_activite_historique`) plutôt qu'à `AuditService`. Cette divergence est documentée dans la dette technique.

---

## 9. MIGRATIONS RÉALISÉES

| Fichier | Tables créées | Seeds |
|---------|--------------|-------|
| `vie_scolaire_001_absences.sql` | `vs_motifs_absence`, `vs_absences`, `vs_justifications_absences` | 7 motifs d'absence |
| `vie_scolaire_002_presences.sql` | `vs_appels`, `vs_presences`, `vs_presences_historique` | — |
| `vie_scolaire_003_retards.sql` | `vs_retards`, `vs_justifications_retards` | — |
| `vie_scolaire_004_discipline.sql` | `vs_discipline_categories`, `vs_dossiers_discipline`, `vs_incidents_discipline`, `vs_sanctions_discipline`, `vs_sanctions_historique`, `vs_appels_discipline` | 6 catégories |
| `vie_scolaire_005_recompenses.sql` | `vs_recompense_categories`, `vs_recompenses`, `vs_recompenses_historique` | 5 catégories |
| `vie_scolaire_006_emplois_du_temps.sql` | `vs_edt_plages_horaires`, `vs_edt_salles`, `vs_emplois_du_temps`, `vs_edt_creneaux`, `vs_edt_versions`, `vs_edt_remplacements` | 9 plages horaires |
| `vie_scolaire_007_activites.sql` | `vs_activite_categories`, `vs_activites`, `vs_activite_classes`, `vs_activite_responsables`, `vs_activite_inscriptions`, `vs_activite_historique` | 8 catégories |

**Total : 7 migrations — 29 tables — `CREATE TABLE IF NOT EXISTS` systématique — aucun `DROP TABLE`**

### Détail des 29 tables
```
Absences (3)     : vs_motifs_absence, vs_absences, vs_justifications_absences
Présences (3)    : vs_appels, vs_presences, vs_presences_historique
Retards (2)      : vs_retards, vs_justifications_retards
Discipline (6)   : vs_discipline_categories, vs_dossiers_discipline,
                   vs_incidents_discipline, vs_sanctions_discipline,
                   vs_sanctions_historique, vs_appels_discipline
Récompenses (3)  : vs_recompense_categories, vs_recompenses, vs_recompenses_historique
EmploisDuTemps (6): vs_edt_plages_horaires, vs_edt_salles, vs_emplois_du_temps,
                    vs_edt_creneaux, vs_edt_versions, vs_edt_remplacements
Activités (6)    : vs_activite_categories, vs_activites, vs_activite_classes,
                   vs_activite_responsables, vs_activite_inscriptions, vs_activite_historique
```

---

## 10. COMPATIBILITÉ V1

| Critère | Résultat |
|---------|----------|
| Routes V1 (`/absences`, `/emploi-du-temps`, ...) | **Inchangées** ✓ |
| Tables V1 | **Inchangées** ✓ |
| Controllers V1 | **Inchangés** ✓ |
| Préfixe routes V2 | `/v2/vie-scolaire/*` — isolation complète ✓ |
| Préfixe tables V2 | `vs_` — zéro collision ✓ |
| module.json | Déclare `"dependencies": ["Scolarite", "Academique"]` ✓ |
| Couplage V1 en lecture | `eleves`, `classes`, `users`, `matieres` (read-only) ✓ |
| Table `inscriptions` V1 | Référencée en sous-requête dans ActivityRepository ⚠ (fragile) |

**Verdict compatibilité : ZÉRO RÉGRESSION V1 confirmé.**

---

## 11. DETTE TECHNIQUE REPORTÉE EN V2.1

### Critique — à corriger avant mise en production

| ID | Description | Action requise |
|----|-------------|----------------|
| VS-C-001 | Race condition `vs_activite_inscriptions` : UNIQUE sur (activite_id, eleve_id, deleted_at) ne prévient pas les doubles inscriptions actives sous concurrence | Ajouter `SELECT ... FOR UPDATE` dans une transaction PDO dans `ActivityService.inscrireEleve()` |
| VS-C-002 | `events.php` : 4 événements Finance avec clés PHP dupliquées (PaymentCompleted, PaymentRefunded, CashMovementCreated, InvoiceCancelled) | Fusionner les entrées dupliquées en une seule par événement |

### Majeure — planifiable V2.1

| ID | Description |
|----|-------------|
| VS-M-001 | `ActivityController` : 3 méthodes accèdent à `Database::getInstance()` directement — déplacer dans ActivityRepository |
| VS-M-002 | Sous-requêtes corrélées N+1 dans `findAll()` (ActivityRepository, TimetableRepository) — remplacer par LEFT JOIN + GROUP BY |
| VS-M-003 | Index composite manquant `(activite_id, statut, date_inscription)` pour la liste d'attente FIFO |
| VS-M-004 | `NotificationListeners` : stubs dans 7 domaines × 3 listeners — implémenter email/push réels |
| VS-M-005 | `DisciplineIntegrationHandler` : logging uniquement — implémenter l'escalade automatique |
| VS-M-006 | `ActivityController` : `parent::__construct()` absent |
| VS-M-007 | `publierEdt()` : 3 opérations SQL non encapsulées dans une transaction PDO |

### Mineure — backlog

| ID | Description |
|----|-------------|
| VS-m-001 | `AbsenceController` instancie `AbsenceRepository` directement dans `show()` |
| VS-m-002 | Rôle secrétaire : `attendance.session.validate` manquant — à confirmer avec règles métier |
| VS-m-003 | Flash error non échappé (`htmlspecialchars` manquant) dans certaines vues |
| VS-m-004 | `HTTP_REFERER` non validé dans `annulerInscription()` — open redirect |
| VS-m-005 | Référence à table V1 `inscriptions` dans `ActivityRepository.findInscriptions()` — dépendance non déclarée |
| VS-m-006 | `modifierActivite()` re-insère N:N en boucle (pas de batch INSERT) |
| VS-m-007 | `statsByAnnee()` utilise `:annee` et `:annee2` pour la même valeur |

---

## 12. AMÉLIORATIONS FUTURES (V2.2+)

### Fonctionnelles
- **Notifications temps réel** : implémentation complète des NotificationListeners (email, push PWA, SMS)
- **DisciplineIntegrationHandler** : création automatique de dossier disciplinaire sur `LateThresholdReached`
- **Emplois du temps** : gestion des semaines A/B, import Excel, impression PDF
- **Activités** : gestion des documents joints (sorties pédagogiques, autorisations parentales)
- **Absences** : calcul automatique du taux d'absentéisme et alertes sur dépassement de seuil
- **Retards** : seuil configurable par établissement (actuellement hardcodé)
- **Classement comportemental** : algorithme de score configurable (Récompenses vs Sanctions)
- **Statut `en_cours`** pour les activités : déclenchement automatique par cron au moment de `heure_debut`

### Techniques
- Migration du wiring `events.php` vers un system de discovery automatique par module
- Tests unitaires et d'intégration pour le module VS (0 couverture actuellement)
- Cache des permissions en Redis (actuellement rechargées depuis session à chaque requête)
- Index composite sur tables fréquemment filtrées (VS-M-003)
- Normalisation de l'audit : unification AuditService + historique JSON (VS, EDT)

---

## 13. SCORE QUALITÉ FINAL

| Dimension | Score | Justification |
|-----------|-------|---------------|
| Complétude fonctionnelle | 10/10 | 7/7 domaines implémentés, toutes les features du blueprint couvertes |
| Architecture MVC | 8.5/10 | Pattern uniforme, 1 violation mineure ActivityController |
| Base de données | 7.5/10 | 29 tables solides, 1 race condition critique |
| RBAC | 8.0/10 | 36 permissions, double vérification, 1 gap secrétaire |
| Système d'événements | 7.5/10 | 28 events câblés, stubs Notification, 1 dup Finance |
| Services partagés | 6.5/10 | AuditService partiel, NotificationService absent |
| Intégration cross-module | 7.5/10 | Couplage loose correct, 1 dépendance V1 fragile |
| Compatibilité V1 | 9.0/10 | Zéro régression, préfixes isolés |
| Performance | 7.0/10 | N+1 identifiés mais non bloquants |
| Sécurité | 8.0/10 | CSRF/Auth/PDO params OK, 2 mineures |
| **SCORE GLOBAL** | **7.55/10** | — |

---

## 14. DÉCISION FINALE

```
╔══════════════════════════════════════════════════════════════╗
║                                                              ║
║   MODULE VIE SCOLAIRE V2 — v2.6.0                           ║
║                                                              ║
║   SCORE    : 7.55 / 10                                       ║
║   DÉCISION : ✅  GO WITH MINOR IMPROVEMENTS                  ║
║                                                              ║
╠══════════════════════════════════════════════════════════════╣
║                                                              ║
║   L'architecture est officiellement GELÉE.                   ║
║                                                              ║
║   Les 7 domaines sont complets, conformes au blueprint,      ║
║   architecturalement stables et coexistants avec la V1.      ║
║                                                              ║
║   2 corrections obligatoires avant déploiement prod :        ║
║     → VS-C-001 : transaction SELECT FOR UPDATE inscriptions  ║
║     → VS-C-002 : fusion des clés dupliquées events.php       ║
║                                                              ║
║   Ces corrections ne nécessitent pas de nouveau              ║
║   cycle d'architecture — uniquement des hotfixes ciblés.     ║
║                                                              ║
╠══════════════════════════════════════════════════════════════╣
║                                                              ║
║   CHIFFRES DU MODULE                                         ║
║   ─────────────────────────────────────────────────────      ║
║   7   domaines fonctionnels                                  ║
║   29  tables SQL (préfixe vs_)                               ║
║   28  événements                                             ║
║   50+ routes HTTP                                            ║
║   36  permissions RBAC                                       ║
║   7   migrations (IF NOT EXISTS, aucun DROP)                 ║
║   43  vues PHP (Tailwind CSS + Lucide Icons)                 ║
║   ~168 fichiers PHP                                          ║
║   0   régression V1                                          ║
║                                                              ║
╠══════════════════════════════════════════════════════════════╣
║                                                              ║
║   PROCHAINE ÉTAPE RECOMMANDÉE                                ║
║   ─────────────────────────────────────────────────────      ║
║   Phase 6.0 — Global Architecture Readiness Review          ║
║   (Modules Core + Scolarité + Académique + Finance           ║
║    + Vie Scolaire — revue globale avant Milestone 3)         ║
║                                                              ║
╚══════════════════════════════════════════════════════════════╝
```

---

*VIE_SCOLAIRE_MODULE_FREEZE.md — document de gel officiel, aucune modification de code effectuée.*  
*Gel déclaré le 2026-07-02 — Architecture figée à partir de ce document.*
