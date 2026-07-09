# RH V2 BLUEPRINT — MODULE RESSOURCES HUMAINES
## SCOLARIS V2 — Architecture Officielle

**Date :** 2026-07-02  
**Auteur :** Claude Code (claude-sonnet-4-6)  
**Version :** 1.0.0  
**Statut :** DRAFT — En attente de validation avant implémentation  
**Phase :** 6.1  

---

## TABLE DES MATIÈRES

1. [Vision & Périmètre](#1-vision--périmètre)
2. [Architecture Cible](#2-architecture-cible)
3. [Arborescence Complète](#3-arborescence-complète)
4. [Tables SQL — 15 migrations](#4-tables-sql--15-migrations)
5. [Sous-domaines Détaillés](#5-sous-domaines-détaillés)
6. [Services](#6-services)
7. [Événements](#7-événements)
8. [Permissions RBAC](#8-permissions-rbac)
9. [Intégrations Inter-modules](#9-intégrations-inter-modules)
10. [Stratégie V1 → V2](#10-stratégie-v1--v2)
11. [Workflow Global RH](#11-workflow-global-rh)
12. [Risques & Mitigations](#12-risques--mitigations)
13. [Ordre des Phases](#13-ordre-des-phases)
14. [Tableau de Bord RH](#14-tableau-de-bord-rh)

---

## 1. Vision & Périmètre

### 1.1 Objectif du module

Le Module RH V2 centralise la gestion du **personnel de l'établissement** — enseignants, personnel administratif, direction — en couvrant le cycle de vie complet de l'employé : recrutement → affectation → suivi → évaluation → départ.

Il est le premier module V2 qui **lit et étend** des entités V1 existantes (`professeurs`, `users`) sans les remplacer.

### 1.2 Périmètre inclus

| Domaine | Description |
|---------|-------------|
| Employés | Fiche employé, données administratives, statut, historique |
| Enseignants | Spécialité pédagogique, affectations matières/classes (pont V1) |
| Personnel administratif | Secrétariat, comptabilité, direction |
| Départements | Unités organisationnelles, hiérarchie |
| Postes | Intitulés de fonctions, catégories, niveaux |
| Affectations | Qui occupe quel poste dans quel département, quelle année |
| Contrats | Types, durées, rémunération, machine d'états |
| Présence personnel | Pointage entrées/sorties, absences, missions |
| Congés | Demandes, validations, soldes, règles métier |
| Évaluations | Entretiens annuels, critères, recommandations |
| Formations | Catalogue, inscriptions, suivi des completions |
| Documents RH | Contrats scannés, diplômes, attestations, fiches de paie |
| Organigramme | Vue hiérarchique interactive |
| Tableau de bord RH | KPIs, alertes, statistiques consolidées |

### 1.3 Périmètre EXCLU (différé)

| Fonctionnalité | Raison du report | Prérequis |
|----------------|-----------------|-----------|
| **Sous-module Paie** | `finance_006_decaissements.sql` manquant | Finance décaissements résolu (FN-C-002) |
| **Recrutement / ATS** | Hors scope école primaire/secondaire | Décision directeur |
| **Portail libre-service employé** | Dépend du PWA phase 2 | Module Communication futur |
| **Intégration DGRH** | API externe administrations | Décision projet |

---

## 2. Architecture Cible

### 2.1 Principe fondateur : Extension sans destruction

```
V1 EXISTANT (intouchable)          V2 RH (extension)
─────────────────────────          ─────────────────
professeurs.id ◄────────────────── rh_employes.professeur_id (nullable FK)
users.id ◄──────────────────────── rh_employes.user_id (nullable FK)
classes.id ◄────────────────────── rh_enseignant_matieres.classe_id
matieres.id ◄───────────────────── rh_enseignant_matieres.matiere_id
enseignements (V1 readonly) ◄────── TeacherService lit pour sync
```

**Règle d'or :** RH V2 est la **source de vérité pour les données RH** (contrat, congé, évaluation). La table V1 `professeurs` reste la **source de vérité pour les données pédagogiques** (enseignements, spécialité, grade).

### 2.2 Pattern architectural (uniforme avec les autres modules V2)

```
Request → Router → RH Controller (thin)
                        ↓
                   Policy::canX()        ← RBAC check
                        ↓
                   DTO::fromRequest()    ← validation + typage
                        ↓
                   Service::method()     ← logique métier
                   /         \
           Repository::sql()  EventDispatcher::dispatch()
                                    ↓
                              AuditListener
                              NotificationListener
                              OrganizationListener (cross-domain)
```

### 2.3 Préfixe SQL

Toutes les nouvelles tables RH V2 utilisent le préfixe **`rh_`** — aucune collision avec les tables V1 ni avec `finance_`, `vs_`.

---

## 3. Arborescence Complète

```
app/
└── Modules/
    └── RH/
        ├── module.json
        ├── routes.php
        │
        ├── Controllers/
        │   ├── EmployeeController.php
        │   ├── TeacherController.php
        │   ├── ContractController.php
        │   ├── LeaveController.php
        │   ├── AttendanceController.php
        │   ├── EvaluationController.php
        │   ├── TrainingController.php
        │   ├── OrganizationController.php
        │   ├── DocumentController.php
        │   └── DashboardController.php
        │
        ├── Models/
        │   ├── EmployeeModel.php
        │   ├── DepartementModel.php
        │   ├── PosteModel.php
        │   ├── AffectationModel.php
        │   ├── ContratModel.php
        │   ├── PresencePersonnelModel.php
        │   ├── CongeModel.php
        │   ├── SoldeCongeModel.php
        │   ├── TypeCongeModel.php
        │   ├── EvaluationModel.php
        │   ├── CritereEvaluationModel.php
        │   ├── FormationModel.php
        │   ├── FormationEmployeModel.php
        │   └── DocumentRhModel.php
        │
        ├── Repositories/
        │   ├── EmployeeRepository.php
        │   ├── TeacherRepository.php
        │   ├── ContractRepository.php
        │   ├── LeaveRepository.php
        │   ├── AttendanceRepository.php
        │   ├── EvaluationRepository.php
        │   ├── TrainingRepository.php
        │   ├── OrganizationRepository.php
        │   └── DocumentRepository.php
        │
        ├── Services/
        │   ├── EmployeeService.php
        │   ├── TeacherService.php
        │   ├── ContractService.php
        │   ├── LeaveService.php
        │   ├── AttendanceService.php
        │   ├── EvaluationService.php
        │   ├── TrainingService.php
        │   └── OrganizationService.php
        │
        ├── DTO/
        │   ├── EmployeeDTO.php
        │   ├── EmployeeFiltersDTO.php
        │   ├── TeacherAssignmentDTO.php
        │   ├── ContratDTO.php
        │   ├── ContratFiltersDTO.php
        │   ├── CongeDTO.php
        │   ├── CongeFiltersDTO.php
        │   ├── PresencePersonnelDTO.php
        │   ├── EvaluationDTO.php
        │   ├── EvaluationFiltersDTO.php
        │   ├── FormationDTO.php
        │   ├── FormationEmployeDTO.php
        │   ├── OrganizationDTO.php
        │   └── DocumentRhDTO.php
        │
        ├── Policies/
        │   ├── EmployeePolicy.php
        │   ├── ContractPolicy.php
        │   ├── LeavePolicy.php
        │   ├── AttendancePolicy.php
        │   ├── EvaluationPolicy.php
        │   ├── TrainingPolicy.php
        │   └── OrganizationPolicy.php
        │
        ├── Events/
        │   ├── EmployeeCreated.php
        │   ├── EmployeeUpdated.php
        │   ├── EmployeeStatusChanged.php
        │   ├── EmployeeDeactivated.php
        │   ├── TeacherAssigned.php
        │   ├── TeacherUnassigned.php
        │   ├── ContractCreated.php
        │   ├── ContractSigned.php
        │   ├── ContractExpired.php
        │   ├── ContractTerminated.php
        │   ├── LeaveRequested.php
        │   ├── LeaveApproved.php
        │   ├── LeaveRejected.php
        │   ├── LeaveCancelled.php
        │   ├── EvaluationCreated.php
        │   ├── EvaluationCompleted.php
        │   ├── EvaluationValidated.php
        │   ├── TrainingAssigned.php
        │   ├── TrainingCompleted.php
        │   ├── TrainingCancelled.php
        │   ├── DepartmentCreated.php
        │   ├── DepartmentUpdated.php
        │   ├── EmployeeAssignedToPost.php
        │   └── EmployeeRemovedFromPost.php
        │
        ├── Listeners/
        │   ├── RhAuditHandler.php
        │   ├── RhNotificationHandler.php
        │   ├── ContractHandler.php
        │   ├── LeaveHandler.php
        │   ├── TeacherSyncHandler.php
        │   └── VieScolaireIntegrationHandler.php
        │
        └── Views/
            ├── dashboard/
            │   └── index.php
            ├── employes/
            │   ├── index.php
            │   ├── show.php
            │   ├── create.php
            │   ├── edit.php
            │   └── export.php
            ├── enseignants/
            │   ├── index.php
            │   ├── show.php
            │   └── affectations.php
            ├── contrats/
            │   ├── index.php
            │   ├── show.php
            │   ├── create.php
            │   └── edit.php
            ├── conges/
            │   ├── index.php
            │   ├── show.php
            │   ├── create.php
            │   ├── validation.php
            │   └── soldes.php
            ├── presences/
            │   ├── index.php
            │   ├── saisie.php
            │   └── rapport.php
            ├── evaluations/
            │   ├── index.php
            │   ├── show.php
            │   ├── create.php
            │   └── edit.php
            ├── formations/
            │   ├── index.php
            │   ├── show.php
            │   ├── create.php
            │   └── inscriptions.php
            ├── organisation/
            │   ├── index.php
            │   ├── departements.php
            │   ├── postes.php
            │   └── organigramme.php
            └── documents/
                ├── index.php
                └── upload.php
```

---

## 4. Tables SQL — 15 migrations

**Fichier à créer :** `database/migrations/rh_001_employes.sql` à `rh_010_documents.sql`

> **Convention :** `CREATE TABLE IF NOT EXISTS`, `deleted_at` pour soft delete, `created_at`/`updated_at` systématiques. Aucun `DROP TABLE`.

---

### Migration 01 — Organisation (rh_001_organisation.sql)

```sql
-- Table 1 : Départements
CREATE TABLE IF NOT EXISTS rh_departements (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(100)  NOT NULL,
    code            VARCHAR(20)   NOT NULL UNIQUE,
    description     TEXT,
    responsable_id  INT UNSIGNED  NULL,  -- FK rh_employes (nullable car créé avant les employés)
    parent_id       INT UNSIGNED  NULL,  -- Self-référentiel pour sous-départements
    actif           TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_parent (parent_id),
    KEY idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2 : Postes / Fonctions
CREATE TABLE IF NOT EXISTS rh_postes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    intitule        VARCHAR(150)  NOT NULL,
    code            VARCHAR(30)   NOT NULL UNIQUE,
    departement_id  INT UNSIGNED  NULL,
    categorie       ENUM('enseignant','administratif','support','direction','technique') NOT NULL DEFAULT 'administratif',
    niveau          TINYINT       NOT NULL DEFAULT 1 COMMENT '1=junior, 2=confirmé, 3=senior, 4=chef',
    description     TEXT,
    actif           TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_departement (departement_id),
    KEY idx_categorie (categorie),
    KEY idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeds postes fondateurs
INSERT INTO rh_postes (intitule, code, categorie, niveau) VALUES
    ('Directeur(trice)',             'DIR',       'direction',      4),
    ('Directeur(trice) adjoint(e)',  'DIR_ADJ',   'direction',      3),
    ('Secrétaire de direction',      'SEC_DIR',   'administratif',  2),
    ('Secrétaire',                   'SEC',       'administratif',  1),
    ('Comptable',                    'COMPTA',    'administratif',  2),
    ('Professeur certifié (PES)',    'ENS_PES',   'enseignant',     3),
    ('Professeur de l''enseignement moyen (PEM)', 'ENS_PEM', 'enseignant', 2),
    ('Professeur contractuel',       'ENS_CONT',  'enseignant',     1),
    ('Vacataire',                    'ENS_VAC',   'enseignant',     1),
    ('Maître formateur',             'ENS_MF',    'enseignant',     4),
    ('Surveillant général',          'SURV',      'support',        2),
    ('Agent d''entretien',           'ENTRET',    'support',        1),
    ('Informaticien',                'INFOR',     'technique',      2)
ON DUPLICATE KEY UPDATE intitule = VALUES(intitule);
```

---

### Migration 02 — Employés (rh_002_employes.sql)

```sql
-- Table 3 : Employés (entité centrale RH)
CREATE TABLE IF NOT EXISTS rh_employes (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- Liens V1 (nullables)
    user_id          INT UNSIGNED  NULL COMMENT 'FK users.id — compte système',
    professeur_id    INT UNSIGNED  NULL COMMENT 'FK professeurs.id — uniquement pour enseignants V1',
    -- Identification
    matricule        VARCHAR(30)   NOT NULL UNIQUE,
    type_personnel   ENUM('enseignant','administratif','support','direction','technique') NOT NULL,
    -- Identité
    nom              VARCHAR(100)  NOT NULL,
    prenom           VARCHAR(100)  NOT NULL,
    date_naissance   DATE          NULL,
    lieu_naissance   VARCHAR(150)  NULL,
    genre            ENUM('M','F','autre') NULL,
    nationalite      VARCHAR(80)   NULL DEFAULT 'Algérienne',
    -- Documents identité
    cni_numero       VARCHAR(30)   NULL,
    cni_expiration   DATE          NULL,
    -- Coordonnées
    adresse          TEXT          NULL,
    telephone        VARCHAR(20)   NULL,
    email_pro        VARCHAR(150)  NULL,
    email_perso      VARCHAR(150)  NULL,
    -- Photo
    photo            VARCHAR(255)  NULL,
    -- Statut emploi
    statut           ENUM('actif','inactif','suspendu','retraite','demissionnaire') NOT NULL DEFAULT 'actif',
    date_entree      DATE          NULL,
    date_sortie      DATE          NULL,
    motif_sortie     TEXT          NULL,
    -- Audit
    cree_par_id      INT UNSIGNED  NULL,
    deleted_at       DATETIME      NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    KEY idx_professeur (professeur_id),
    KEY idx_statut (statut),
    KEY idx_type (type_personnel),
    KEY idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 4 : Affectations poste/département
CREATE TABLE IF NOT EXISTS rh_affectations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id      INT UNSIGNED  NOT NULL,
    poste_id        INT UNSIGNED  NOT NULL,
    departement_id  INT UNSIGNED  NULL,
    annee_scolaire  VARCHAR(9)    NOT NULL COMMENT 'ex: 2025-2026',
    date_debut      DATE          NOT NULL,
    date_fin        DATE          NULL,
    principale      TINYINT(1)    NOT NULL DEFAULT 1 COMMENT 'Affectation principale vs secondaire',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_employe (employe_id),
    KEY idx_poste (poste_id),
    KEY idx_annee (annee_scolaire),
    UNIQUE KEY uq_principale (employe_id, annee_scolaire, principale)
        COMMENT 'Un seul poste principal par employé par année'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 5 : Affectations pédagogiques enseignants (pont V1)
CREATE TABLE IF NOT EXISTS rh_enseignant_matieres (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id      INT UNSIGNED  NOT NULL,
    matiere_id      INT UNSIGNED  NOT NULL COMMENT 'FK V1 matieres.id',
    classe_id       INT UNSIGNED  NOT NULL COMMENT 'FK V1 classes.id',
    annee_scolaire  VARCHAR(9)    NOT NULL,
    heures_hebdo    DECIMAL(4,1)  NULL,
    statut          ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_aff (employe_id, matiere_id, classe_id, annee_scolaire),
    KEY idx_employe (employe_id),
    KEY idx_annee (annee_scolaire)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### Migration 03 — Contrats (rh_003_contrats.sql)

```sql
-- Table 6 : Contrats de travail
CREATE TABLE IF NOT EXISTS rh_contrats (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id        INT UNSIGNED  NOT NULL,
    poste_id          INT UNSIGNED  NULL,
    type_contrat      ENUM('cdi','cdd','vacation','stage','interim','convention') NOT NULL,
    date_debut        DATE          NOT NULL,
    date_fin          DATE          NULL COMMENT 'NULL = CDI durée indéterminée',
    salaire_base      DECIMAL(10,2) NULL,
    regime_horaire    ENUM('temps_plein','temps_partiel','vacation') NOT NULL DEFAULT 'temps_plein',
    taux_horaire      DECIMAL(6,2)  NULL COMMENT 'Pour vacataires et temps partiels',
    signe_le          DATE          NULL,
    signe_par_id      INT UNSIGNED  NULL COMMENT 'FK users.id',
    fichier_contrat   VARCHAR(255)  NULL COMMENT 'Chemin upload storage/uploads/contrats/',
    statut            ENUM('brouillon','actif','expire','resilie','archive') NOT NULL DEFAULT 'brouillon',
    motif_resiliation TEXT          NULL,
    date_resiliation  DATE          NULL,
    resilie_par_id    INT UNSIGNED  NULL,
    deleted_at        DATETIME      NULL,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_employe (employe_id),
    KEY idx_statut (statut),
    KEY idx_date_fin (date_fin),
    KEY idx_type (type_contrat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### Migration 04 — Présences Personnel (rh_004_presences.sql)

```sql
-- Table 7 : Présences du personnel
CREATE TABLE IF NOT EXISTS rh_presences_personnel (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id      INT UNSIGNED  NOT NULL,
    date_presence   DATE          NOT NULL,
    heure_arrivee   TIME          NULL,
    heure_depart    TIME          NULL,
    statut          ENUM('present','absent','retard','conge','mission','ferie') NOT NULL DEFAULT 'present',
    source          ENUM('manuel','badge','systeme') NOT NULL DEFAULT 'manuel',
    note            TEXT          NULL,
    saisi_par_id    INT UNSIGNED  NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_presence (employe_id, date_presence),
    KEY idx_employe (employe_id),
    KEY idx_date (date_presence),
    KEY idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### Migration 05 — Congés (rh_005_conges.sql)

```sql
-- Table 8 : Types de congés
CREATE TABLE IF NOT EXISTS rh_types_conges (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code             VARCHAR(20)   NOT NULL UNIQUE,
    libelle          VARCHAR(100)  NOT NULL,
    duree_max_annuel INT           NULL COMMENT 'Jours max par an, NULL=illimité',
    deductible       TINYINT(1)    NOT NULL DEFAULT 1 COMMENT 'Déduit du solde',
    remunere         TINYINT(1)    NOT NULL DEFAULT 1 COMMENT 'Payé pendant le congé',
    justificatif     TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Pièce justificative requise',
    actif            TINYINT(1)    NOT NULL DEFAULT 1,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rh_types_conges (code, libelle, duree_max_annuel, deductible, remunere, justificatif) VALUES
    ('ANNUEL',   'Congé annuel',              30, 1, 1, 0),
    ('MALADIE',  'Congé maladie',             NULL, 1, 1, 1),
    ('MATERNITE','Congé maternité',           98, 0, 1, 1),
    ('PATERNITE','Congé paternité',           3,  0, 1, 1),
    ('EVENEMENT','Événement familial',        5,  1, 1, 1),
    ('FORMATION','Congé formation',           NULL, 1, 1, 0),
    ('MISSION',  'Mission officielle',        NULL, 0, 1, 0),
    ('SANS_SOL', 'Congé sans solde',          NULL, 1, 0, 0)
ON DUPLICATE KEY UPDATE libelle = VALUES(libelle);

-- Table 9 : Demandes de congés
CREATE TABLE IF NOT EXISTS rh_conges (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id          INT UNSIGNED  NOT NULL,
    type_conge_id       INT UNSIGNED  NOT NULL,
    date_debut          DATE          NOT NULL,
    date_fin            DATE          NOT NULL,
    nb_jours            DECIMAL(4,1)  NOT NULL,
    motif               TEXT          NULL,
    statut              ENUM('brouillon','soumis','valide','refuse','annule') NOT NULL DEFAULT 'brouillon',
    valide_par_id       INT UNSIGNED  NULL,
    date_validation     DATETIME      NULL,
    commentaire_valid   TEXT          NULL,
    piece_justificative VARCHAR(255)  NULL,
    deleted_at          DATETIME      NULL,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_employe (employe_id),
    KEY idx_statut (statut),
    KEY idx_dates (date_debut, date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 10 : Soldes de congés
CREATE TABLE IF NOT EXISTS rh_soldes_conges (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id      INT UNSIGNED  NOT NULL,
    type_conge_id   INT UNSIGNED  NOT NULL,
    annee           YEAR          NOT NULL,
    solde_initial   DECIMAL(5,1)  NOT NULL DEFAULT 0,
    solde_pris      DECIMAL(5,1)  NOT NULL DEFAULT 0,
    solde_restant   DECIMAL(5,1)  GENERATED ALWAYS AS (solde_initial - solde_pris) STORED,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_solde (employe_id, type_conge_id, annee),
    KEY idx_employe (employe_id),
    KEY idx_annee (annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### Migration 06 — Évaluations (rh_006_evaluations.sql)

```sql
-- Table 11 : Évaluations annuelles
CREATE TABLE IF NOT EXISTS rh_evaluations (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id       INT UNSIGNED  NOT NULL,
    evaluateur_id    INT UNSIGNED  NOT NULL COMMENT 'FK rh_employes.id',
    periode          ENUM('annuel','semestriel','trimestriel','essai') NOT NULL DEFAULT 'annuel',
    annee            YEAR          NOT NULL,
    date_evaluation  DATE          NULL,
    note_globale     DECIMAL(4,2)  NULL COMMENT 'Calculée automatiquement depuis critères',
    commentaire      TEXT          NULL,
    recommandation   ENUM('promotion','maintien','amelioration','avertissement','rupture') NULL,
    statut           ENUM('brouillon','soumis','valide','archive') NOT NULL DEFAULT 'brouillon',
    valide_par_id    INT UNSIGNED  NULL,
    date_validation  DATETIME      NULL,
    deleted_at       DATETIME      NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_eval (employe_id, periode, annee),
    KEY idx_employe (employe_id),
    KEY idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 12 : Critères d'évaluation
CREATE TABLE IF NOT EXISTS rh_criteres_evaluation (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evaluation_id   INT UNSIGNED  NOT NULL,
    critere         VARCHAR(100)  NOT NULL,
    note            TINYINT       NOT NULL COMMENT '1=insuffisant, 2=à améliorer, 3=satisfaisant, 4=bien, 5=excellent',
    commentaire     TEXT          NULL,
    KEY idx_evaluation (evaluation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rh_criteres_evaluation (evaluation_id, critere, note)
    -- (Les critères sont insérés dynamiquement par EvaluationService, pas de seed global)
    SELECT NULL, NULL, NULL FROM DUAL WHERE 1=0; -- placeholder, aucun seed
```

---

### Migration 07 — Formations (rh_007_formations.sql)

```sql
-- Table 13 : Catalogue des formations
CREATE TABLE IF NOT EXISTS rh_formations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titre           VARCHAR(200)  NOT NULL,
    description     TEXT          NULL,
    organisme       VARCHAR(150)  NULL,
    duree_heures    DECIMAL(5,1)  NULL,
    type_formation  ENUM('interne','externe','certifiante','e_learning','conférence') NOT NULL DEFAULT 'interne',
    categorie       VARCHAR(80)   NULL COMMENT 'ex: pédagogie, administratif, informatique, sécurité',
    cout            DECIMAL(10,2) NULL,
    actif           TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_type (type_formation),
    KEY idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 14 : Inscriptions / Suivi des formations par employé
CREATE TABLE IF NOT EXISTS rh_formations_employes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id      INT UNSIGNED  NOT NULL,
    formation_id    INT UNSIGNED  NOT NULL,
    date_debut      DATE          NOT NULL,
    date_fin        DATE          NULL,
    statut          ENUM('inscrit','en_cours','complete','abandonne','echoue') NOT NULL DEFAULT 'inscrit',
    note            DECIMAL(4,2)  NULL,
    certificat      VARCHAR(255)  NULL,
    commentaire     TEXT          NULL,
    inscrit_par_id  INT UNSIGNED  NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_employe (employe_id),
    KEY idx_formation (formation_id),
    KEY idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### Migration 08 — Documents RH (rh_008_documents.sql)

```sql
-- Table 15 : Documents RH
CREATE TABLE IF NOT EXISTS rh_documents (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employe_id      INT UNSIGNED  NOT NULL,
    type_document   ENUM('contrat','diplome','attestation','fiche_paie','cni','certificat_medical','lettre_mission','autre') NOT NULL,
    titre           VARCHAR(200)  NOT NULL,
    fichier_path    VARCHAR(255)  NOT NULL,
    date_document   DATE          NULL,
    date_expiration DATE          NULL COMMENT 'Pour pièces avec validité (CNI, médical)',
    statut          ENUM('valide','expire','archive') NOT NULL DEFAULT 'valide',
    confidentiel    TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Restreint aux RH+directeur uniquement',
    uploaded_by_id  INT UNSIGNED  NULL,
    deleted_at      DATETIME      NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_employe (employe_id),
    KEY idx_type (type_document),
    KEY idx_expiration (date_expiration),
    KEY idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Résumé des migrations

| Fichier | Tables | Contenu |
|---------|--------|---------|
| `rh_001_organisation.sql` | 2 | rh_departements, rh_postes (+ 13 seeds) |
| `rh_002_employes.sql` | 3 | rh_employes, rh_affectations, rh_enseignant_matieres |
| `rh_003_contrats.sql` | 1 | rh_contrats |
| `rh_004_presences.sql` | 1 | rh_presences_personnel |
| `rh_005_conges.sql` | 3 | rh_types_conges (+ 8 seeds), rh_conges, rh_soldes_conges |
| `rh_006_evaluations.sql` | 2 | rh_evaluations, rh_criteres_evaluation |
| `rh_007_formations.sql` | 2 | rh_formations, rh_formations_employes |
| `rh_008_documents.sql` | 1 | rh_documents |
| **Total** | **15** | |

---

## 5. Sous-domaines Détaillés

### 5.1 — Gestion des Employés

**Objectif :** Fiche maître de chaque membre du personnel, cycle de vie complet.

**Responsabilités :**
- Créer/modifier/archiver une fiche employé
- Attribuer un matricule unique (format : `YYYY-XXXXX`, ex: `2026-00042`)
- Lier optionnellement à un `users.id` (compte système) et `professeurs.id` (enseignants V1)
- Gérer le statut : `actif → suspendu → inactif → retraite/demissionnaire`
- Uploader la photo via `UploadService` (type : `photo_employe` à créer)

**Limites :**
- Ne modifie JAMAIS `professeurs.nom`, `professeurs.email` directement → passe par `TeacherService`
- Ne crée pas de compte `users` automatiquement → action manuelle distincte
- Ne gère pas la paie (différé)

**Règles métier :**
- `matricule` = auto-généré, non modifiable après création
- `email_pro` unique parmi les actifs (peut se dupliquer si archivé)
- Désactivation (`EmployeeDeactivated`) → signal cross-domain vers EDT (retrait créneaux)
- `date_sortie` obligatoire si `statut ∈ {inactif, retraite, demissionnaire}`

**Événements produits :** `EmployeeCreated`, `EmployeeUpdated`, `EmployeeStatusChanged`, `EmployeeDeactivated`

**Permissions RBAC :** `employee.view`, `employee.view.own`, `employee.create`, `employee.update`, `employee.deactivate`, `employee.export`

---

### 5.2 — Gestion des Enseignants

**Objectif :** Extension RH de la table V1 `professeurs`, synchronisation bi-directionnelle.

**Responsabilités :**
- Importer les données de `professeurs` V1 vers `rh_employes` (action ponctuelle au démarrage)
- Gérer les affectations pédagogiques (matière + classe) via `rh_enseignant_matieres`
- Optionnellement synchroniser les modifications RH → V1 `professeurs` (nom, email)
- Calculer la charge horaire hebdomadaire

**Limites :**
- `enseignements` V1 est read-only depuis RH (source of truth pédagogique = V1)
- `TeacherService` peut LIRE V1 `enseignements` pour afficher l'historique
- Aucun DELETE sur `professeurs` V1

**Règles métier :**
- Un enseignant RH doit avoir `type_personnel = 'enseignant'` ET `professeur_id` non-null
- Affectation matière/classe valide : la matière ET la classe doivent exister en V1
- `TeacherAssigned` vérifie l'absence de conflit EDT (advisory, pas bloquant)
- Heures hebdo total ≤ 30h (alerte si dépassement, non bloquant)

**Stratégie d'import initial :**
```
TeacherService::importFromV1() :
  1. SELECT * FROM professeurs WHERE actif = 1 (V1 read)
  2. Pour chaque professeur :
     a. findOrCreate rh_employes par (professeur_id OU email_pro)
     b. Copier nom, prenom, email, specialite, grade
     c. Assigner poste selon grade V1
  3. Logger import via AuditService
```

**Événements produits :** `TeacherAssigned`, `TeacherUnassigned`

**Permissions RBAC :** `teacher.view`, `teacher.assign`, `teacher.manage`

---

### 5.3 — Personnel Administratif

**Objectif :** Membres du personnel non-enseignant dans `rh_employes`.

**Responsabilités :**
- Gérer les fiches des secrétaires, comptables, surveillants, agents d'entretien
- Lier à `users.id` pour leur accès au SI
- Affecter à un poste dans `rh_postes`

**Limites :**
- Pas de spécificités pédagogiques (pas de `professeur_id`)
- Le rôle système (`users.role`) est géré dans le module Utilisateurs V2, pas ici

**Règles métier :**
- `type_personnel ∈ {administratif, support, direction, technique}`
- Vérification unicité email_pro avant création

---

### 5.4 — Départements

**Objectif :** Structurer l'établissement en unités organisationnelles.

**Responsabilités :**
- CRUD complet sur `rh_departements`
- Nommer un responsable (FK `rh_employes.id`)
- Supporter une hiérarchie (parent_id → sous-département)

**Limites :**
- Maximum 3 niveaux de hiérarchie (direction > département > section)
- Un département ne peut pas être son propre parent (vérification circulaire)

**Règles métier :**
- `code` = identifiant court unique (ex: `PEDA`, `ADMIN`, `SURV`)
- Archivage (pas suppression) si le département a des employés actifs
- `DepartmentCreated` → `RhAuditHandler`

**Événements produits :** `DepartmentCreated`, `DepartmentUpdated`

**Permissions RBAC :** `organization.view`, `organization.manage`

---

### 5.5 — Fonctions / Postes

**Objectif :** Référentiel des intitulés de fonctions.

**Responsabilités :**
- Catalogue de postes avec catégorie et niveau
- Association poste → département par défaut

**Limites :**
- Les postes sont des libellés, pas des instances d'occupation (l'occupation = `rh_affectations`)
- Un poste peut être occupé par plusieurs employés simultanément (ex: plusieurs `SEC`)

**Règles métier :**
- `code` unique et immuable après création
- Archivage interdit si poste actif dans `rh_affectations` en cours

---

### 5.6 — Affectations

**Objectif :** Historique de qui occupe quel poste dans quel département, par année scolaire.

**Responsabilités :**
- Créer une affectation en début d'année scolaire
- Identifier l'affectation `principale` (une seule par employé/année)
- Enregistrer les changements de poste en cours d'année (date_fin sur l'ancienne, nouvelle ligne)

**Limites :**
- Ne gère pas les créneaux EDT (→ Vie Scolaire V2)
- Ne définit pas les droits d'accès système (→ RBAC V2)

**Règles métier :**
- UNIQUE sur `(employe_id, annee_scolaire, principale=1)` → un seul poste principal
- Poste secondaire autorisé (ex: enseignant + coordinateur)
- `EmployeeAssignedToPost` → `RhAuditHandler`

**Événements produits :** `EmployeeAssignedToPost`, `EmployeeRemovedFromPost`

---

### 5.7 — Contrats

**Objectif :** Gérer le cycle de vie contractuel de chaque employé.

**Responsabilités :**
- Créer/modifier les contrats (type, durée, salaire, régime)
- Machine d'états : `brouillon → actif → expire/resilie → archive`
- Alertes d'expiration J-30 et J-7 via `ContractHandler`
- Upload du contrat scanné via `UploadService`

**Limites :**
- Ne génère pas les fiches de paie (sous-module Paie différé)
- Un seul contrat `actif` par employé à la fois (vérification par `ContractService`)

**Règles métier :**
- CDI : `date_fin IS NULL`
- CDD/Stage : `date_fin > date_debut` obligatoire
- Salaire : si `regime_horaire = vacation`, `taux_horaire` obligatoire et `salaire_base` calculé
- `ContractExpired` : dispatché par `ContractHandler` lors de la détection (cron futur ou check au boot)
- Résiliation : `motif_resiliation` ≥ 20 caractères obligatoire

**Machine d'états :**
```
brouillon ──► actif ──► expire (date_fin atteinte)
                  └───► resilie (résiliation manuelle)
expire/resilie ──► archive
```

**Événements produits :** `ContractCreated`, `ContractSigned`, `ContractExpired`, `ContractTerminated`

**Permissions RBAC :** `contract.view`, `contract.view.own`, `contract.create`, `contract.update`, `contract.sign`, `contract.terminate`

---

### 5.8 — Présence du Personnel

**Objectif :** Pointage journalier du personnel (distinct des absences élèves du Module VS).

**Responsabilités :**
- Saisie journalière par responsable RH ou secrétaire
- Statuts : `present`, `absent`, `retard`, `conge`, `mission`, `ferie`
- Calcul automatique : durée de présence si heure_arrivee + heure_depart renseignées
- Rapports de présence mensuels par employé/département

**Limites :**
- Pas de système de badge physique (sources : `manuel` ou `systeme` pour future intégration)
- Ne remplace pas le suivi absences élèves (→ VS Module)
- Pas de calcul de paie sur base des heures (→ sous-module Paie différé)

**Règles métier :**
- UNIQUE sur `(employe_id, date_presence)` → une ligne par employé par jour
- Si statut = `conge` : vérifier existence `rh_conges` validée pour la période
- Si `absent` : alerte au responsable département (NotificationService)
- `heure_depart` > `heure_arrivee` obligatoire si les deux sont renseignées

**Permissions RBAC :** `attendance.rh.view`, `attendance.rh.view.own`, `attendance.rh.record`, `attendance.rh.update`

> **Note :** Préfixe `attendance.rh.*` pour éviter la collision avec `attendance.*` du Module Vie Scolaire (absences élèves).

---

### 5.9 — Congés

**Objectif :** Workflow complet de gestion des congés du personnel.

**Responsabilités :**
- Soumettre une demande de congé (employé)
- Valider ou refuser (directeur/responsable RH)
- Calculer et mettre à jour les soldes automatiquement
- Vérifier la disponibilité (pas de conflit avec d'autres congés ou avec EDT enseignant)

**Limites :**
- Les soldes sont initialisés manuellement en début d'année (pas de cumul automatique)
- Pas d'intégration portail employé (self-service différé)

**Règles métier :**
- `date_fin ≥ date_debut` obligatoire
- `nb_jours` calculé automatiquement (excluant weekends et jours fériés futurs)
- Solde disponible vérifié avant soumission si `type_conge.deductible = 1`
- Pas de chevauchement pour le même employé (vérification dans `LeaveService`)
- Validation : seul `directeur` ou `admin` peut valider
- Si enseignant : advisory check VS `rh_enseignant_matieres` (conflit cours) → avertissement non bloquant
- `LeaveApproved` → met à jour `rh_soldes_conges.solde_pris`
- `LeaveCancelled` après validation → restaure le solde

**Machine d'états :**
```
brouillon ──► soumis ──► valide
                   └───► refuse
valide ──► annule (si annulation avant date_debut)
```

**Événements produits :** `LeaveRequested`, `LeaveApproved`, `LeaveRejected`, `LeaveCancelled`

**Permissions RBAC :** `leave.view`, `leave.view.own`, `leave.request`, `leave.validate`, `leave.cancel`, `leave.admin`

---

### 5.10 — Absences du Personnel

**Note architecturale :** Les absences du personnel sont modélisées via `rh_presences_personnel.statut = 'absent'` + motif dans `note`. Pas de table séparée `rh_absences` — la table de présences gère les deux états.

---

### 5.11 — Évaluations

**Objectif :** Entretiens d'évaluation annuels du personnel.

**Responsabilités :**
- Créer une évaluation (directeur → employé)
- Saisir les critères et notes (1 à 5)
- Calculer la note globale (moyenne pondérée des critères)
- Émettre une recommandation
- Valider et archiver

**Critères standards :**
- Ponctualité et assiduité
- Compétences professionnelles
- Qualité du travail
- Communication et relations
- Initiative et autonomie
- Esprit d'équipe
- Respect des règles
- *(Pour enseignants)* : Pédagogie, Résultats élèves, Préparation cours

**Limites :**
- Pas d'auto-évaluation (employé ne crée pas sa propre évaluation)
- L'intégration avec les résultats académiques (pour enseignants) est advisory
- Pas de module 360° (évaluation par les pairs)

**Règles métier :**
- UNIQUE sur `(employe_id, periode, annee)` → une seule évaluation par période/an
- Note globale = AVERAGE(critères.note)
- Recommandation `avertissement` → alerte admin automatique
- Seul le `directeur` ou `admin` peut valider
- Archivage automatique après validation (statut `valide → archive` à l'année suivante)

**Événements produits :** `EvaluationCreated`, `EvaluationCompleted`, `EvaluationValidated`

**Permissions RBAC :** `evaluation.view`, `evaluation.view.own`, `evaluation.create`, `evaluation.validate`, `evaluation.admin`

---

### 5.12 — Formations

**Objectif :** Suivi du développement professionnel du personnel.

**Responsabilités :**
- Maintenir un catalogue de formations
- Inscrire des employés à des formations
- Suivre les completions et délivrer des attestations (upload certificat)
- Générer des statistiques : taux de formation, heures par catégorie

**Limites :**
- Pas de LMS intégré (les formations sont enregistrées, pas dispensées)
- Pas de calcul de coût automatique (DIF/CPF non géré)

**Règles métier :**
- Un employé ne peut pas être inscrit deux fois à la même formation active
- `TrainingCompleted` : déclenche notification à l'employé
- Abandon/Échec : note la raison dans `commentaire`

**Événements produits :** `TrainingAssigned`, `TrainingCompleted`, `TrainingCancelled`

**Permissions RBAC :** `training.view`, `training.view.own`, `training.assign`, `training.complete`, `training.manage`

---

### 5.13 — Documents RH

**Objectif :** Coffre-fort documentaire de la fiche employé.

**Responsabilités :**
- Upload via `UploadService` (types à ajouter : `contrat_rh`, `document_rh`)
- Suivi des dates d'expiration (CNI, certificat médical)
- Contrôle d'accès : documents `confidentiel = 1` visibles uniquement RH + directeur

**Limites :**
- Pas de signature électronique
- Pas de workflow de validation documentaire

**Règles métier :**
- Fichiers acceptés : PDF, JPEG, PNG (max 5 Mo)
- Alerte J-30 avant expiration d'un document (check `date_expiration`)
- Soft delete : les documents ne sont jamais physiquement supprimés (archivage)

**Permissions RBAC :** `rh.documents.view`, `rh.documents.view.own`, `rh.documents.upload`, `rh.documents.delete`

---

### 5.14 — Organigramme

**Objectif :** Représentation visuelle de la structure de l'établissement.

**Responsabilités :**
- Afficher la hiérarchie `rh_departements` (arbre)
- Pour chaque département : liste des postes occupés + noms employés
- Export PDF de l'organigramme

**Limites :**
- Vue en lecture seule (modifications via Départements/Affectations)
- Pas de drag-and-drop interactif (V2.1)

**Permissions RBAC :** `organization.view`

---

### 5.15 — Tableau de Bord RH

**Objectif :** KPIs consolidés et alertes opérationnelles.

**Sections :**

| Section | Contenu |
|---------|---------|
| Effectifs | Total actifs, par type, par département |
| Contrats | Expirant dans 30 jours, résiliés ce mois, CDI vs CDD |
| Congés | En attente de validation, en cours aujourd'hui, soldes critiques |
| Présences | Taux présence semaine, absents aujourd'hui |
| Évaluations | En cours, non démarrées, recommandations «avertissement» |
| Formations | Heures réalisées ce trimestre, formations à venir |
| Alertes | Documents expirant, contrats à renouveler, CNI expirées |

**Permissions RBAC :** `rh.dashboard.view`

---

## 6. Services

### 6.1 EmployeeService

```
Méthodes :
  creerEmploye(EmployeeDTO $dto): int
  modifierEmploye(int $id, EmployeeDTO $dto): void
  changerStatut(int $id, string $statut, string $motif, int $userId): void
  desactiverEmploye(int $id, string $motif, string $dateEffet, int $userId): void
  genererMatricule(int $annee): string
  lierCompteUser(int $employeId, int $userId): void
  lierProfesseurV1(int $employeId, int $professeurId): void
  rechercherEmployes(EmployeeFiltersDTO $filters): array
  exporterListe(string $format): string|array
```

### 6.2 TeacherService

```
Méthodes :
  importerDepuisV1(): array  (import initial)
  affecterMatiere(TeacherAssignmentDTO $dto): void
  retirerMatiere(int $employe, int $matiere, int $classe, string $annee): void
  getAffectations(int $employeId, string $annee): array
  getChargeHoraire(int $employeId, string $annee): float
  synchroniserVersV1(int $employeId): void
```

### 6.3 ContractService

```
Méthodes :
  creerContrat(ContratDTO $dto): int
  signerContrat(int $id, int $userId): void
  resilierContrat(int $id, string $motif, string $dateEffet, int $userId): void
  archiverContrat(int $id): void
  getContratActif(int $employeId): ?array
  detecterExpirations(int $joursAvance = 30): array
  verifierChevauchement(int $employeId, string $dateDebut, ?string $dateFin): bool
```

### 6.4 LeaveService

```
Méthodes :
  soumettreConge(CongeDTO $dto): int
  validerConge(int $id, int $userId, string $commentaire = ''): void
  refuserConge(int $id, int $userId, string $commentaire): void
  annulerConge(int $id, int $userId): void
  calculerNbJours(string $dateDebut, string $dateFin): float  (hors weekends)
  verifierSolde(int $employeId, int $typeCongeId, float $nbJours): bool
  initialiserSoldes(int $employeId, int $annee): void
  detecterChevauchement(int $employeId, string $dateDebut, string $dateFin, ?int $excludeId = null): bool
```

### 6.5 AttendanceService (Personnel)

```
Méthodes :
  saisirPresence(PresencePersonnelDTO $dto): void
  corriger(int $id, PresencePersonnelDTO $dto): void
  rapportMensuel(int $employeId, int $mois, int $annee): array
  tauxPresence(int $departementId, int $mois, int $annee): float
  getAbsentsAujourdhui(): array
```

### 6.6 EvaluationService

```
Méthodes :
  creerEvaluation(EvaluationDTO $dto): int
  saisirCriteres(int $evalId, array $criteres): void
  calculerNoteGlobale(int $evalId): float  (déclenche EvaluationCompleted)
  validerEvaluation(int $evalId, int $userId): void
  getHistorique(int $employeId): array
```

### 6.7 TrainingService

```
Méthodes :
  creerFormation(FormationDTO $dto): int
  inscrireEmploye(FormationEmployeDTO $dto): void
  marquerComplete(int $id, float $note, ?string $certificatPath): void
  annulerInscription(int $id, string $motif): void
  getStatistiques(string $annee): array  (heures/type/completion rate)
```

### 6.8 OrganizationService

```
Méthodes :
  creerDepartement(OrganizationDTO $dto): int
  modifierDepartement(int $id, OrganizationDTO $dto): void
  archiverDepartement(int $id): void
  affecterEmploye(int $employeId, int $posteId, int $departId, string $annee): void
  retirerAffectation(int $affectationId): void
  getOrganigramme(): array  (arbre hiérarchique)
  getEffectifsParDepartement(): array
```

---

## 7. Événements

### 7.1 Liste complète des 24 événements RH

| # | Événement | Payload clé | Listener(s) |
|---|-----------|-------------|------------|
| 1 | `EmployeeCreated` | employeeId, type, matricule, userId | RhAuditHandler, RhNotificationHandler |
| 2 | `EmployeeUpdated` | employeeId, changes[], modifiedById | RhAuditHandler |
| 3 | `EmployeeStatusChanged` | employeeId, oldStatut, newStatut, motif | RhAuditHandler, RhNotificationHandler |
| 4 | `EmployeeDeactivated` | employeeId, motif, dateEffet | RhAuditHandler, VieScolaireIntegrationHandler |
| 5 | `TeacherAssigned` | employeeId, matiereId, classeId, annee | RhAuditHandler |
| 6 | `TeacherUnassigned` | employeeId, matiereId, classeId, annee | RhAuditHandler |
| 7 | `ContractCreated` | contractId, employeeId, type, dateDebut | RhAuditHandler |
| 8 | `ContractSigned` | contractId, employeeId, signedAt | RhAuditHandler, RhNotificationHandler |
| 9 | `ContractExpired` | contractId, employeeId, dateExpiration | RhAuditHandler, RhNotificationHandler |
| 10 | `ContractTerminated` | contractId, employeeId, motif, dateEffet | RhAuditHandler, RhNotificationHandler |
| 11 | `LeaveRequested` | leaveId, employeeId, typeCongeId, dateDebut, nbJours | RhAuditHandler, LeaveHandler |
| 12 | `LeaveApproved` | leaveId, employeeId, approvedById | RhAuditHandler, LeaveHandler, RhNotificationHandler |
| 13 | `LeaveRejected` | leaveId, employeeId, rejectedById, commentaire | RhAuditHandler, RhNotificationHandler |
| 14 | `LeaveCancelled` | leaveId, employeeId, cancelledById | RhAuditHandler, LeaveHandler |
| 15 | `EvaluationCreated` | evaluationId, employeeId, evaluateurId, annee | RhAuditHandler |
| 16 | `EvaluationCompleted` | evaluationId, employeeId, noteGlobale, recommandation | RhAuditHandler, RhNotificationHandler |
| 17 | `EvaluationValidated` | evaluationId, employeeId, validatedById | RhAuditHandler, RhNotificationHandler |
| 18 | `TrainingAssigned` | formationEmployeId, employeeId, formationId | RhAuditHandler |
| 19 | `TrainingCompleted` | formationEmployeId, employeeId, formationId, note | RhAuditHandler, RhNotificationHandler |
| 20 | `TrainingCancelled` | formationEmployeId, employeeId, formationId, motif | RhAuditHandler |
| 21 | `DepartmentCreated` | departementId, nom, responsableId | RhAuditHandler |
| 22 | `DepartmentUpdated` | departementId, changes[] | RhAuditHandler |
| 23 | `EmployeeAssignedToPost` | affectationId, employeeId, posteId, departId | RhAuditHandler |
| 24 | `EmployeeRemovedFromPost` | affectationId, employeeId, posteId | RhAuditHandler |

### 7.2 Listeners

| Listener | Rôle |
|----------|------|
| `RhAuditHandler` | `AuditService::logCreate/Update/Delete()` pour tous les événements RH |
| `RhNotificationHandler` | `NotificationService::notify()` pour les événements à forte visibilité |
| `ContractHandler` | Mise à jour statut contrats, alertes expiration |
| `LeaveHandler` | Mise à jour soldes congés après `LeaveApproved` et `LeaveCancelled` |
| `TeacherSyncHandler` | Synchronisation optionnelle vers V1 `professeurs` après `TeacherAssigned` |
| `VieScolaireIntegrationHandler` | Reçoit `EmployeeDeactivated` → alerte sur les créneaux EDT actifs de l'enseignant |

### 7.3 Événements consommés depuis d'autres modules

| Événement source | Module | Action dans RH |
|-----------------|--------|----------------|
| *(aucun à ce stade)* | — | Le module RH produit mais ne consomme pas d'events V2 dans la Phase 6 initiale |

---

## 8. Permissions RBAC

### 8.1 Catalogue (40 permissions, préfixes 8)

```php
// ── Employés ──────────────────────────────────────────────────────────────────
'employee.view',           // Voir la liste des employés
'employee.view.own',       // Voir sa propre fiche
'employee.create',         // Créer un employé
'employee.update',         // Modifier un employé
'employee.deactivate',     // Désactiver/archiver
'employee.export',         // Exporter la liste

// ── Enseignants ───────────────────────────────────────────────────────────────
'teacher.view',            // Voir les enseignants et leurs affectations
'teacher.assign',          // Affecter matière/classe à un enseignant
'teacher.manage',          // Gérer tous les aspects enseignants (import V1, etc.)

// ── Contrats ──────────────────────────────────────────────────────────────────
'contract.view',           // Voir les contrats de tout le personnel
'contract.view.own',       // Voir son propre contrat
'contract.create',         // Créer un contrat
'contract.update',         // Modifier un contrat (brouillon seulement)
'contract.sign',           // Signer/valider un contrat
'contract.terminate',      // Résilier un contrat

// ── Congés ────────────────────────────────────────────────────────────────────
'leave.view',              // Voir les congés de tout le personnel
'leave.view.own',          // Voir ses propres congés
'leave.request',           // Soumettre une demande de congé
'leave.validate',          // Approuver ou refuser une demande
'leave.cancel',            // Annuler un congé
'leave.admin',             // Gérer les soldes et les types de congés

// ── Présences Personnel ───────────────────────────────────────────────────────
'attendance.rh.view',      // Voir les présences de tout le personnel
'attendance.rh.view.own',  // Voir ses propres présences
'attendance.rh.record',    // Saisir les présences
'attendance.rh.update',    // Corriger une présence

// ── Évaluations ───────────────────────────────────────────────────────────────
'evaluation.rh.view',      // Voir les évaluations (toutes)
'evaluation.rh.view.own',  // Voir sa propre évaluation
'evaluation.rh.create',    // Créer une évaluation
'evaluation.rh.validate',  // Valider une évaluation
'evaluation.rh.admin',     // Gérer les critères d'évaluation

// ── Formations ────────────────────────────────────────────────────────────────
'training.view',           // Voir le catalogue et les inscriptions
'training.view.own',       // Voir ses propres formations
'training.assign',         // Inscrire un employé à une formation
'training.complete',       // Marquer une formation complète
'training.manage',         // Gérer le catalogue (CRUD)

// ── Organisation ─────────────────────────────────────────────────────────────
'organization.view',       // Voir organigramme, départements, postes
'organization.manage',     // Créer/modifier départements, postes, affectations

// ── Documents RH ─────────────────────────────────────────────────────────────
'rh.documents.view',       // Voir documents de tout le personnel
'rh.documents.view.own',   // Voir ses propres documents
'rh.documents.upload',     // Uploader un document
'rh.documents.delete',     // Archiver un document

// ── Tableau de bord & Rapports ────────────────────────────────────────────────
'rh.dashboard.view',       // Voir le tableau de bord RH
'rh.reports.view',         // Voir les rapports RH
'rh.reports.export',       // Exporter les rapports
```

### 8.2 Attribution par rôle

| Permission | admin | directeur | secrétaire | comptable | enseignant |
|-----------|:-----:|:---------:|:----------:|:---------:|:---------:|
| employee.view | ✓ | ✓ | ✓ | — | — |
| employee.view.own | ✓ | ✓ | ✓ | ✓ | ✓ |
| employee.create | ✓ | ✓ | — | — | — |
| employee.update | ✓ | ✓ | — | — | — |
| employee.deactivate | ✓ | ✓ | — | — | — |
| employee.export | ✓ | ✓ | — | — | — |
| teacher.view | ✓ | ✓ | ✓ | — | — |
| teacher.assign | ✓ | ✓ | — | — | — |
| teacher.manage | ✓ | — | — | — | — |
| contract.view | ✓ | ✓ | — | ✓ | — |
| contract.view.own | ✓ | ✓ | ✓ | ✓ | ✓ |
| contract.create | ✓ | ✓ | — | — | — |
| contract.sign | ✓ | ✓ | — | — | — |
| contract.terminate | ✓ | ✓ | — | — | — |
| leave.view | ✓ | ✓ | ✓ | — | — |
| leave.view.own | ✓ | ✓ | ✓ | ✓ | ✓ |
| leave.request | ✓ | ✓ | ✓ | ✓ | ✓ |
| leave.validate | ✓ | ✓ | — | — | — |
| leave.cancel | ✓ | ✓ | ✓ | ✓ | ✓ |
| leave.admin | ✓ | ✓ | — | — | — |
| attendance.rh.view | ✓ | ✓ | ✓ | — | — |
| attendance.rh.view.own | ✓ | ✓ | ✓ | ✓ | ✓ |
| attendance.rh.record | ✓ | ✓ | ✓ | — | — |
| evaluation.rh.view | ✓ | ✓ | — | — | — |
| evaluation.rh.view.own | ✓ | ✓ | ✓ | ✓ | ✓ |
| evaluation.rh.create | ✓ | ✓ | — | — | — |
| evaluation.rh.validate | ✓ | ✓ | — | — | — |
| training.view | ✓ | ✓ | ✓ | — | ✓ |
| training.view.own | ✓ | ✓ | ✓ | ✓ | ✓ |
| training.assign | ✓ | ✓ | — | — | — |
| training.manage | ✓ | ✓ | — | — | — |
| organization.view | ✓ | ✓ | ✓ | ✓ | ✓ |
| organization.manage | ✓ | ✓ | — | — | — |
| rh.documents.view | ✓ | ✓ | — | — | — |
| rh.documents.view.own | ✓ | ✓ | ✓ | ✓ | ✓ |
| rh.documents.upload | ✓ | ✓ | ✓ | — | — |
| rh.dashboard.view | ✓ | ✓ | — | — | — |
| rh.reports.view | ✓ | ✓ | — | ✓ | — |
| rh.reports.export | ✓ | ✓ | — | — | — |

---

## 9. Intégrations Inter-modules

### 9.1 Core (toujours actif)

| Point d'intégration | Sens | Détail |
|--------------------|------|--------|
| `users` table | RH lit ← V1 | `rh_employes.user_id` FK, lecture pour email/nom |
| `AuditService` | RH → Shared | Toutes les opérations RH auditées |
| `NotificationService` | RH → Shared | Congés, contrats, évaluations |
| `UploadService` | RH → Shared | Photos employés, contrats, documents, certificats |
| `Core\EventDispatcher` | RH → Core | 24 événements dispatched |

**Nouveaux types UploadService à ajouter :**
```php
'photo_employe' => [
    'dir'      => 'storage/uploads/employes/',
    'mimes'    => ['image/jpeg', 'image/png', 'image/webp'],
    'max_size' => 2097152,
    'resize'   => [300, 400],
],
'document_rh' => [
    'dir'      => 'storage/uploads/rh/documents/',
    'mimes'    => ['image/jpeg', 'image/png', 'application/pdf'],
    'max_size' => 5242880,
    'resize'   => null,
],
'contrat_rh' => [
    'dir'      => 'storage/uploads/rh/contrats/',
    'mimes'    => ['application/pdf', 'image/jpeg', 'image/png'],
    'max_size' => 10485760,
    'resize'   => null,
],
```

**Nouveaux triggers NotificationService à ajouter :**
```php
'rh_conge'      => ['label' => 'Congé',        'icon' => 'calendar-check',  'color' => 'info'],
'rh_contrat'    => ['label' => 'Contrat',       'icon' => 'file-earmark',    'color' => 'primary'],
'rh_evaluation' => ['label' => 'Évaluation',    'icon' => 'star',            'color' => 'warning'],
'rh_formation'  => ['label' => 'Formation',     'icon' => 'mortarboard',     'color' => 'success'],
```

---

### 9.2 V1 Professeurs (lecture + extension)

| Opération | Sens | Règle |
|-----------|------|-------|
| Import initial | V1 → RH | `TeacherService::importerDepuisV1()` — ponctuel |
| Lecture pédagogique | V1 → RH | `enseignements` : lecture seule depuis `TeacherRepository` |
| Synchronisation nom/email | RH → V1 | Optionnelle, via `TeacherSyncHandler` après `EmployeeUpdated` |
| V1 reste source of truth | — | Pour notes, enseignements, spécialité pédagogique |

---

### 9.3 Finance V2 (intégration différée)

| Prérequis | Statut | Action |
|-----------|--------|--------|
| `finance_006_decaissements.sql` | PENDING | À créer (FN-C-002) |
| Sous-module Paie RH | Différé | Phase 6.x après Finance décaissements résolu |
| Intégration comptable paie | Différé | `PaymentService` générerait les écritures comptables depuis fiches de paie |

---

### 9.4 Vie Scolaire V2 (intégration cross-domain)

| Événement RH | Listener VS | Action |
|-------------|------------|--------|
| `EmployeeDeactivated` | `VieScolaireIntegrationHandler` | Détecter les créneaux EDT actifs de l'enseignant → alerte responsable EDT |
| `LeaveApproved` (enseignant) | *(futur)* | Alerte si l'enseignant a des cours pendant le congé |

**Technique :** `VieScolaireIntegrationHandler` utilise `str_ends_with($class, 'EmployeeDeactivated')` pour éviter le couplage direct.

---

### 9.5 Scolarité V2 / Académique V2 (disabled — dépendances futures)

| Besoin | Statut | Condition |
|--------|--------|-----------|
| Résultats élèves pour évaluation enseignant | Advisory | Académique V2 activé |
| Classes V2 pour affectation enseignant | Possible | Scolarité V2 activé |
| En attendant | RH utilise V1 `classes` et V1 `professeurs` | Aucune dépendance directe sur V2 disabled |

---

## 10. Stratégie V1 → V2

### 10.1 Coexistence sans friction

```
V1 Routes (/enseignants, /professeurs)     → inchangées, fonctionnelles
V2 Routes (/v2/rh/...)                     → nouvelles, parallèles
V1 professeurs table                        → read-only depuis RH V2
V1 enseignements table                      → read-only depuis RH V2
V1 users table                              → lue par FK, non modifiée
```

### 10.2 Import initial (ponctuel)

```
Étape 1 : Exécuter les 8 migrations SQL (IF NOT EXISTS)
Étape 2 : `TeacherService::importerDepuisV1()` — importe professeurs actifs
Étape 3 : Saisir manuellement les autres employés (secrétaires, comptables, etc.)
Étape 4 : Configurer les départements et postes
Étape 5 : Créer les affectations de l'année en cours
Étape 6 : Activer le module dans modules.php : rh.enabled = true
```

### 10.3 Zéro régression V1

- Aucune modification sur `professeurs`, `users`, `enseignements`, `classes`, `matieres`
- Les routes V1 `/enseignants/*` restent actives et indépendantes
- `rh_employes.professeur_id IS NULL` est acceptable pour le personnel non-enseignant
- Préfixe `rh_` sur toutes les tables → zéro collision

---

## 11. Workflow Global RH

```
ONBOARDING EMPLOYÉ
═══════════════════
DirecteurRH créé fiche → EmployeeCreated
    ↓
Choix type :
  ├── enseignant → TeacherService.importerDepuisV1() ou création manuelle
  │                    → TeacherAssigned (si affectation immédiate)
  └── autre → EmployeeCreated suffit

OrganizationService.affecterEmploye() → EmployeeAssignedToPost
    ↓
ContractService.creerContrat() → ContractCreated
    ↓
ContractService.signerContrat() → ContractSigned → NotificationService

SUIVI QUOTIDIEN
════════════════
AttendanceService.saisirPresence() [par secrétaire chaque matin]
    → si statut = absent : RhNotificationHandler → alerte responsable
    → si statut = conge  : vérification rh_conges valide

CYCLE CONGÉS
═════════════
Employé LeaveService.soumettreConge() → LeaveRequested
    ↓
Directeur LeaveService.validerConge() → LeaveApproved
    ↓
LeaveHandler.handle() → update rh_soldes_conges.solde_pris
    ↓                 → NotificationService → employé notifié
AttendanceService.saisirPresence(statut='conge') [auto futur]

CYCLE ÉVALUATION (annuel)
══════════════════════════
Directeur EvaluationService.creerEvaluation() → EvaluationCreated
    ↓
Saisie critères → EvaluationService.calculerNoteGlobale() → EvaluationCompleted
    ↓
EvaluationService.validerEvaluation() → EvaluationValidated
    → si recommandation = 'avertissement' : RhNotificationHandler → admin

OFFBOARDING
════════════
EmployeeService.changerStatut(statut='inactif', motif) → EmployeeStatusChanged
    ↓
ContractService.resilierContrat(motif, dateEffet) → ContractTerminated
    ↓
EmployeeService.desactiverEmploye() → EmployeeDeactivated
    ↓
VieScolaireIntegrationHandler → alerte EDT si enseignant avec créneaux actifs
```

---

## 12. Risques & Mitigations

### 12.1 Risques Techniques

| Risque | Probabilité | Impact | Mitigation |
|--------|------------|--------|------------|
| `professeurs.user_id` NULL pour certains profs V1 | Haute | Moyen | Import partiel toléré ; complétion manuelle |
| Conflit `employee.deactivate` ↔ EDT créneaux | Moyenne | Moyen | Advisory (alerte non bloquante) via event cross-domain |
| N+1 dans organigramme (arbre récursif) | Haute | Faible | CTE ou requête récursive unique dans `OrganizationRepository` |
| Soldes de congés incohérents si `LeaveCancelled` mal géré | Faible | Élevé | Transactions SQL + vérification dans `LeaveService` |
| Doublons employee/professeur si import répété | Moyenne | Moyen | Vérification `professeur_id` unique avant insert |

### 12.2 Risques Fonctionnels

| Risque | Mitigation |
|--------|------------|
| Sous-module Paie bloqué par Finance décaissements | Planifier FN-C-002 dès Phase 6.2 en parallèle |
| Volume de permissions (40 nouvelles) dans `config/permissions.php` | Ajouter en section `// ── RH V2 ──` structurée |
| events.php doublons (AR-C-002) aggravés par 24 nouveaux events | Corriger AR-C-002 AVANT d'ajouter les events RH |

### 12.3 Dettes anticipées

| Dette | Phase | Description |
|-------|-------|-------------|
| DT-RH-01 | 6.x | Sous-module Paie non implémenté |
| DT-RH-02 | 6.x | `ContractExpired` : check manuel, pas automatique (cron absent) |
| DT-RH-03 | 6.x | NotificationListeners seront stubs initialement (MS2-M-004 pattern) |
| DT-RH-04 | 6.x | `TeacherSyncHandler` : synchronisation V1 optionnelle, non activée par défaut |
| DT-RH-05 | 6.x | Portail self-service employé (congés en ligne, documents) différé |

---

## 13. Ordre des Phases

### Phase 6.1 — Blueprint (ce document) ✓
Conception complète. Livrable : `RH_V2_BLUEPRINT.md`. En attente de validation.

### Phase 6.2 — Domaine Employés & Organisation
**Prérequis :** Corriger AR-C-002 (events.php doublons)  
**Périmètre :**
- Migrations `rh_001_organisation.sql` + `rh_002_employes.sql`
- `OrganizationService`, `EmployeeService`
- `EmployeeController`, `OrganizationController`
- DTOs, Policies, Events (EmployeeCreated/Updated/StatusChanged/Deactivated + DepartmentCreated/Updated + EmployeeAssignedToPost/RemovedFromPost)
- `RhAuditHandler`
- Vues : employes/index, show, create, edit + organisation/index, departements, postes, organigramme
- Permissions `employee.*`, `organization.*` dans config/permissions.php

### Phase 6.3 — Domaine Enseignants
**Prérequis :** Phase 6.2 terminée  
**Périmètre :**
- `TeacherService`, `TeacherRepository`
- `TeacherController`
- `TeacherAssignmentDTO`
- Events : `TeacherAssigned`, `TeacherUnassigned`
- `TeacherSyncHandler` (désactivé par défaut)
- Vues : enseignants/index, show, affectations
- Import initial depuis V1 `professeurs`
- Permissions `teacher.*`

### Phase 6.4 — Domaine Contrats
**Prérequis :** Phase 6.2 terminée  
**Périmètre :**
- Migration `rh_003_contrats.sql`
- `ContractService`, `ContractRepository`
- `FactureController` → `ContractController`
- DTOs, Policy, Events (ContractCreated/Signed/Expired/Terminated)
- `ContractHandler` (alertes expiration)
- Vues : contrats/index, show, create, edit
- Permissions `contract.*`

### Phase 6.5 — Domaine Congés
**Prérequis :** Phase 6.2 terminée  
**Périmètre :**
- Migration `rh_005_conges.sql` (3 tables)
- `LeaveService`, `LeaveRepository`
- `LeaveController`
- DTOs, Policy, Events (LeaveRequested/Approved/Rejected/Cancelled)
- `LeaveHandler` (mise à jour soldes)
- Vues : conges/index, show, create, validation, soldes
- Permissions `leave.*`

### Phase 6.6 — Domaine Présences Personnel
**Prérequis :** Phase 6.2 terminée  
**Périmètre :**
- Migration `rh_004_presences.sql`
- `AttendanceService` (rh), `AttendanceRepository` (rh)
- `AttendanceController` (rh, distinct du VS)
- DTOs, Policy
- Vues : presences/index, saisie, rapport
- Permissions `attendance.rh.*`

### Phase 6.7 — Domaine Évaluations
**Prérequis :** Phase 6.2 terminée  
**Périmètre :**
- Migration `rh_006_evaluations.sql`
- `EvaluationService`, `EvaluationRepository`
- `EvaluationController`
- DTOs, Policy, Events (EvaluationCreated/Completed/Validated)
- Vues : evaluations/index, show, create, edit
- Permissions `evaluation.rh.*`

### Phase 6.8 — Domaine Formations
**Prérequis :** Phase 6.2 terminée  
**Périmètre :**
- Migration `rh_007_formations.sql`
- `TrainingService`, `TrainingRepository`
- `TrainingController`
- DTOs, Policy, Events (TrainingAssigned/Completed/Cancelled)
- Vues : formations/index, show, create, inscriptions
- Permissions `training.*`

### Phase 6.9 — Documents RH
**Prérequis :** Phase 6.2 + UploadService types `document_rh`, `contrat_rh` ajoutés  
**Périmètre :**
- Migration `rh_008_documents.sql`
- `DocumentRepository`
- `DocumentController`
- `DocumentRhDTO`
- Vues : documents/index, upload
- Permissions `rh.documents.*`

### Phase 6.10 — Tableau de Bord & Rapports RH
**Prérequis :** Phases 6.2 à 6.9 terminées  
**Périmètre :**
- `DashboardController`
- `RhReportService` (stats consolidées)
- Vues : dashboard/index
- KPIs : effectifs, contrats, congés, présences, évaluations, formations
- Export PDF/CSV
- Permissions `rh.dashboard.view`, `rh.reports.*`

### Phase 6.11 — Integration Review
Audit complet du module RH V2 (10 dimensions, score/10, verdict).  
Livrable : `RH_INTEGRATION_REVIEW.md`

### Phase 6.12 — Module Freeze
Architecture gelée, inventaire complet.  
Livrable : `RH_MODULE_FREEZE.md`

### Phase 6.x — Sous-module Paie *(différé)*
**Prérequis :** `finance_006_decaissements.sql` créé et exécuté  
**Périmètre :** Fiches de paie, éléments variables, génération PDF, lien Finance décaissements

---

## 14. Tableau de Bord RH

### KPIs Principaux (section hero)

```
┌──────────────┬──────────────┬──────────────┬──────────────┐
│ EFFECTIF     │ CONTRATS     │ CONGÉS EN    │ FORMATIONS   │
│ TOTAL        │ EXPIRANT 30j │ ATTENTE      │ CE MOIS      │
│   42         │      3       │     2        │     5        │
│ actifs       │ à renouveler │ à valider    │ employés     │
└──────────────┴──────────────┴──────────────┴──────────────┘
```

### Sections du Tableau de Bord

1. **Effectifs par type** — donut chart : enseignant / admin / support / direction
2. **Effectifs par département** — bar chart horizontal
3. **Contrats à surveiller** — liste des CDD expirant dans ≤30 jours + bouton renouveler
4. **Congés en attente** — liste des demandes `statut = soumis` + bouton valider/refuser
5. **Absences aujourd'hui** — liste des employés `statut = absent` avec nom + département
6. **Évaluations en retard** — employés sans évaluation annuelle pour l'année en cours
7. **Alertes documents** — documents avec `date_expiration ≤ today + 30j`
8. **Heures de formation** — total du trimestre vs objectif

---

## Synthèse Chiffrée du Blueprint

| Dimension | Chiffre |
|-----------|---------|
| Sous-domaines | 15 |
| Tables SQL | 15 (8 migrations IF NOT EXISTS) |
| Services | 8 |
| Événements | 24 |
| Listeners | 6 |
| Controllers | 10 |
| Policies | 7 |
| DTOs | 14 |
| Permissions | 40 |
| Vues | ~35 |
| Routes prévues | ~55 |
| Phases d'implémentation | 11 (6.2 → 6.12) |
| Tables V1 lues (read-only) | 4 (professeurs, enseignements, classes, matieres) |
| Tables V1 modifiées | 0 |

---

**Document produit le :** 2026-07-02  
**Statut :** DRAFT — Validation requise avant implémentation Phase 6.2  
**Prochaine étape :** Approbation du Blueprint → Démarrage Phase 6.2 (Employés & Organisation)
