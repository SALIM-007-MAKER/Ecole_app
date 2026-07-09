# MODULE_VIE_SCOLAIRE_BLUEPRINT.md
## Phase 5.0 — Conception du Module Vie Scolaire V2
**Date** : 2026-07-01  
**Auteur** : Claude Sonnet 4.6  
**Statut** : CONCEPTION — Aucun code, aucune migration

---

## 1. Vue d'ensemble

### 1.1 Périmètre fonctionnel

Le module Vie Scolaire V2 gère la vie quotidienne de l'établissement en dehors des apprentissages formels.
Il couvre six domaines :

| Domaine | Service | Responsabilité |
|---|---|---|
| Présences & Appels | `AttendanceService` | Saisie d'appel, absences, retards, justifications |
| Absences enseignants | `AttendanceService` | Déclaration, remplacement, validation |
| Discipline | `DisciplineService` | Incidents, sanctions, observations, dossier |
| Récompenses | `DisciplineService` | Félicitations, distinctions, points positifs |
| Emploi du temps | `ScheduleService` | Planning, salles, conflits, exceptions |
| Activités scolaires | `ActivityService` | Sorties, événements, clubs, participations |

### 1.2 Position dans l'architecture V2

```
Core Framework
    └── Shared Services (Audit, Notification, Upload)
        ├── Scolarité V2   — Référentiel : élèves, classes, matières, familles
        ├── Académique V2  — Évaluations, notes, bulletins, classement
        ├── Finance V2     — Frais, paiements, comptabilité
        └── Vie Scolaire V2  ← nouveau (lit Scolarité, interagit Académique + Finance)
```

### 1.3 Namespace et structure

```
app/Modules/VieScolaire/
├── Controllers/
│   ├── AppelController.php          (présences)
│   ├── AbsenceController.php        (absences élèves + enseignants)
│   ├── JustificationController.php
│   ├── DisciplineController.php     (incidents + sanctions)
│   ├── RecompenseController.php
│   ├── EmploiDuTempsController.php
│   ├── SalleController.php
│   └── ActiviteController.php
├── Models/
│   ├── AppelModel.php
│   ├── PresenceModel.php
│   ├── JustificationModel.php
│   ├── MotifAbsenceModel.php
│   ├── AbsenceEnseignantModel.php
│   ├── IncidentModel.php
│   ├── SanctionModel.php
│   ├── TypeSanctionModel.php
│   ├── ObservationModel.php
│   ├── RecompenseModel.php
│   ├── EmploiDuTempsModel.php
│   ├── CreneauModel.php
│   ├── SalleModel.php
│   ├── ExceptionCreneauModel.php
│   ├── ActiviteModel.php
│   ├── ParticipantActiviteModel.php
│   ├── ClubModel.php
│   └── MembreClubModel.php
├── Repositories/
│   ├── AttendanceRepository.php
│   ├── DisciplineRepository.php
│   ├── ScheduleRepository.php
│   └── ActivityRepository.php
├── Services/
│   ├── AttendanceService.php
│   ├── DisciplineService.php
│   ├── ScheduleService.php
│   └── ActivityService.php
├── DTO/
│   ├── AppelDTO.php
│   ├── PresenceDTO.php
│   ├── JustificationDTO.php
│   ├── AbsenceEnseignantDTO.php
│   ├── IncidentDTO.php
│   ├── SanctionDTO.php
│   ├── RecompenseDTO.php
│   ├── EmploiDuTempsDTO.php
│   ├── CreneauDTO.php
│   ├── SalleDTO.php
│   └── ActiviteDTO.php
├── Policies/
│   ├── AttendancePolicy.php
│   ├── DisciplinePolicy.php
│   ├── SchedulePolicy.php
│   └── ActivityPolicy.php
├── Events/
│   ├── AppelOuvert.php
│   ├── AppelCloture.php
│   ├── StudentAbsent.php
│   ├── StudentLate.php
│   ├── AbsenceJustified.php
│   ├── AbsenceEnseignantDeclaree.php
│   ├── DisciplineIncidentCreated.php
│   ├── SanctionApplied.php
│   ├── SanctionAnnulee.php
│   ├── RecompenseAttribuee.php
│   ├── SchedulePublished.php
│   ├── ScheduleUpdated.php
│   ├── ScheduleExceptionCreated.php
│   ├── ActivityCreated.php
│   └── ActivityCancelled.php
├── Listeners/
│   ├── AttendanceHandler.php        (audit + notifications absences)
│   ├── DisciplineHandler.php        (audit + notifications incidents)
│   ├── ScheduleHandler.php          (audit + cache EDT)
│   └── ActivityHandler.php          (audit + Finance si budget)
├── Contracts/
│   ├── AttendanceInterface.php
│   ├── DisciplineInterface.php
│   └── ScheduleInterface.php
├── Views/
│   ├── appels/
│   ├── absences/
│   ├── discipline/
│   ├── recompenses/
│   ├── emploi_du_temps/
│   ├── activites/
│   └── rapports/
├── routes.php
└── module.json
```

---

## 2. Modèle de données

### 2.1 Domaine Présences

#### `vs_appels` — Séances d'appel

```sql
CREATE TABLE vs_appels (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    classe_id       INT UNSIGNED NOT NULL,          -- FK classes
    matiere_id      INT UNSIGNED NULL,              -- FK matieres (null = appel général)
    enseignant_id   INT UNSIGNED NOT NULL,          -- FK users
    periode_id      INT UNSIGNED NULL,              -- FK periodes_scolaires (Académique)
    date_appel      DATE NOT NULL,
    heure_debut     TIME NOT NULL,
    heure_fin       TIME NOT NULL,
    type            ENUM('cours','surveillance','general') DEFAULT 'cours',
    statut          ENUM('ouvert','cloture') DEFAULT 'ouvert',
    observations    TEXT NULL,
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_appels_classe_date  (classe_id, date_appel),
    KEY idx_appels_enseignant   (enseignant_id, date_appel),
    KEY idx_appels_periode      (periode_id),
    KEY idx_appels_statut       (statut, date_appel)
);
```

#### `vs_presences` — Pointage par élève

```sql
CREATE TABLE vs_presences (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appel_id        INT UNSIGNED NOT NULL,          -- FK vs_appels
    eleve_id        INT UNSIGNED NOT NULL,          -- FK eleves
    statut          ENUM('present','absent','retard','exclu','dispense') NOT NULL,
    heure_arrivee   TIME NULL,                      -- renseigné si retard
    minutes_retard  SMALLINT UNSIGNED NULL,         -- calculé
    observation     TEXT NULL,
    saisie_par      INT UNSIGNED NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_presence_appel_eleve (appel_id, eleve_id),
    KEY idx_presences_eleve_date (eleve_id, created_at),
    KEY idx_presences_statut     (statut, created_at)
);
```

#### `vs_motifs_absence` — Référentiel motifs

```sql
CREATE TABLE vs_motifs_absence (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                    VARCHAR(20) NOT NULL UNIQUE,
    libelle                 VARCHAR(100) NOT NULL,
    categorie               ENUM('maladie','familial','transport','scolaire','autre') NOT NULL,
    necessite_justificatif  BOOLEAN DEFAULT TRUE,
    compte_comme_absence     BOOLEAN DEFAULT TRUE,  -- false = dispense officielle
    actif                   BOOLEAN DEFAULT TRUE,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Seed
INSERT INTO vs_motifs_absence (code, libelle, categorie, necessite_justificatif) VALUES
    ('MALADIE',    'Maladie',              'maladie',   TRUE),
    ('MEDECIN',    'Rendez-vous médical',  'maladie',   TRUE),
    ('FAMILLE',    'Raison familiale',     'familial',  TRUE),
    ('DEUIL',      'Deuil',                'familial',  FALSE),
    ('TRANSPORT',  'Problème de transport','transport', FALSE),
    ('SCOLAIRE',   'Activité scolaire',    'scolaire',  FALSE),
    ('AUTRE',      'Autre motif',          'autre',     TRUE);
```

#### `vs_justifications` — Justifications d'absence

```sql
CREATE TABLE vs_justifications (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    presence_id         INT UNSIGNED NOT NULL UNIQUE,    -- FK vs_presences
    motif_id            INT UNSIGNED NOT NULL,           -- FK vs_motifs_absence
    justificatif_fichier VARCHAR(500) NULL,              -- chemin UploadService
    commentaire         TEXT NULL,
    statut              ENUM('en_attente','validee','refusee') DEFAULT 'en_attente',
    valide_par          INT UNSIGNED NULL,               -- FK users
    valide_le           DATETIME NULL,
    motif_refus         TEXT NULL,
    soumis_par          INT UNSIGNED NOT NULL,           -- FK users (ou parent)
    soumis_le           DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_justif_statut     (statut),
    KEY idx_justif_valide_par (valide_par)
);
```

---

### 2.2 Domaine Absences Enseignants

#### `vs_absences_enseignants`

```sql
CREATE TABLE vs_absences_enseignants (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enseignant_id           INT UNSIGNED NOT NULL,       -- FK users
    date_debut              DATE NOT NULL,
    date_fin                DATE NOT NULL,
    motif_id                INT UNSIGNED NULL,           -- FK vs_motifs_absence
    justificatif_fichier    VARCHAR(500) NULL,
    statut                  ENUM('signale','valide','refuse') DEFAULT 'signale',
    remplacement_enseignant_id INT UNSIGNED NULL,        -- FK users
    seances_impactees       JSON NULL,                   -- [appel_ids]
    valide_par              INT UNSIGNED NULL,           -- FK users
    valide_le               DATETIME NULL,
    commentaire             TEXT NULL,
    created_by              INT UNSIGNED NOT NULL,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_abs_ens_enseignant  (enseignant_id, date_debut),
    KEY idx_abs_ens_statut      (statut, date_debut)
);
```

---

### 2.3 Domaine Discipline

#### `vs_incidents` — Incidents disciplinaires

```sql
CREATE TABLE vs_incidents (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    eleve_id                INT UNSIGNED NOT NULL,       -- FK eleves
    rapporteur_id           INT UNSIGNED NOT NULL,       -- FK users
    categorie               ENUM('comportement','violence','fraude','irrespect','bien_scolaire','autre') NOT NULL,
    gravite                 ENUM('mineur','moyen','grave','tres_grave') NOT NULL,
    description             TEXT NOT NULL,
    date_incident           DATETIME NOT NULL,
    lieu                    VARCHAR(200) NULL,
    temoins                 TEXT NULL,
    statut                  ENUM('signale','en_cours','traite','archive') DEFAULT 'signale',
    traite_par              INT UNSIGNED NULL,           -- FK users
    traite_le               DATETIME NULL,
    notif_parents_envoye    BOOLEAN DEFAULT FALSE,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_incidents_eleve   (eleve_id, date_incident),
    KEY idx_incidents_gravite (gravite, statut),
    KEY idx_incidents_date    (date_incident)
);
```

#### `vs_types_sanctions` — Référentiel sanctions

```sql
CREATE TABLE vs_types_sanctions (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                    VARCHAR(30) NOT NULL UNIQUE,
    libelle                 VARCHAR(100) NOT NULL,
    categorie               ENUM('administrative','pedagogique','exclusion','reparation') NOT NULL,
    necessite_parents       BOOLEAN DEFAULT FALSE,
    necessite_direction     BOOLEAN DEFAULT FALSE,        -- approbation direction requise
    duree_max_jours         SMALLINT UNSIGNED NULL,       -- null = illimitée
    actif                   BOOLEAN DEFAULT TRUE,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Seed
INSERT INTO vs_types_sanctions (code, libelle, categorie, necessite_parents, necessite_direction) VALUES
    ('AVERTISSEMENT',    'Avertissement écrit',     'administrative', FALSE, FALSE),
    ('BLAME',            'Blâme',                   'administrative', TRUE,  FALSE),
    ('RETENUE',          'Retenue',                 'pedagogique',    FALSE, FALSE),
    ('EXCLUSION_TEMP',   'Exclusion temporaire',    'exclusion',      TRUE,  TRUE),
    ('EXCLUSION_DEF',    'Exclusion définitive',    'exclusion',      TRUE,  TRUE),
    ('TRAVAIL_INTERET',  'Travail d\'intérêt gen.', 'reparation',     FALSE, FALSE);
```

#### `vs_sanctions` — Sanctions prononcées

```sql
CREATE TABLE vs_sanctions (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    incident_id         INT UNSIGNED NULL,               -- FK vs_incidents (null = sanction préventive)
    eleve_id            INT UNSIGNED NOT NULL,           -- FK eleves
    type_sanction_id    INT UNSIGNED NOT NULL,           -- FK vs_types_sanctions
    description         TEXT NULL,
    date_debut          DATE NOT NULL,
    date_fin            DATE NULL,
    duree_jours         SMALLINT UNSIGNED NULL,          -- calculé
    applique_par        INT UNSIGNED NOT NULL,           -- FK users
    approuve_par        INT UNSIGNED NULL,               -- FK users (si necessite_direction)
    approuve_le         DATETIME NULL,
    statut              ENUM('prononcee','en_attente_approbation','en_cours','executee','annulee') DEFAULT 'prononcee',
    motif_annulation    TEXT NULL,
    notif_parents       BOOLEAN DEFAULT TRUE,
    visible_bulletin    BOOLEAN DEFAULT FALSE,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_sanctions_eleve  (eleve_id, date_debut),
    KEY idx_sanctions_statut (statut, date_debut)
);
```

#### `vs_observations` — Observations sur élèves

```sql
CREATE TABLE vs_observations (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    eleve_id            INT UNSIGNED NOT NULL,           -- FK eleves
    auteur_id           INT UNSIGNED NOT NULL,           -- FK users
    type                ENUM('positive','negative','neutre') NOT NULL,
    contenu             TEXT NOT NULL,
    visible_parents     BOOLEAN DEFAULT TRUE,
    visible_bulletin    BOOLEAN DEFAULT FALSE,
    date_observation    DATE NOT NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,

    KEY idx_obs_eleve (eleve_id, date_observation),
    KEY idx_obs_type  (type, date_observation)
);
```

---

### 2.4 Domaine Récompenses

#### `vs_recompenses`

```sql
CREATE TABLE vs_recompenses (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    eleve_id            INT UNSIGNED NOT NULL,           -- FK eleves
    type                ENUM('felicitation','distinction','bonne_conduite','merite','prix') NOT NULL,
    motif               TEXT NOT NULL,
    points              SMALLINT UNSIGNED DEFAULT 0,
    periode_id          INT UNSIGNED NULL,               -- FK periodes_scolaires (Académique)
    attribue_par        INT UNSIGNED NOT NULL,           -- FK users
    date_attribution    DATE NOT NULL,
    visible_bulletin    BOOLEAN DEFAULT TRUE,
    visible_parents     BOOLEAN DEFAULT TRUE,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,

    KEY idx_recompenses_eleve   (eleve_id, date_attribution),
    KEY idx_recompenses_periode (periode_id)
);
```

---

### 2.5 Domaine Emploi du Temps

#### `vs_salles` — Référentiel salles

```sql
CREATE TABLE vs_salles (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(20) NOT NULL UNIQUE,
    nom             VARCHAR(100) NOT NULL,
    capacite        SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    type            ENUM('salle_cours','laboratoire','gymnase','amphi','salle_info','atelier','autre') DEFAULT 'salle_cours',
    equipements     JSON NULL,                           -- ['projecteur', 'tableau_blanc', ...]
    batiment        VARCHAR(50) NULL,
    etage           TINYINT NULL,
    actif           BOOLEAN DEFAULT TRUE,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### `vs_emplois_du_temps` — Planning par classe

```sql
CREATE TABLE vs_emplois_du_temps (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    classe_id       INT UNSIGNED NOT NULL,               -- FK classes
    annee_scolaire  VARCHAR(9) NOT NULL,                 -- '2025-2026'
    semaine_type    TINYINT UNSIGNED DEFAULT 1,          -- 1=hebdomadaire, 2=bi-semaine
    statut          ENUM('brouillon','publie','archive') DEFAULT 'brouillon',
    publie_par      INT UNSIGNED NULL,                   -- FK users
    publie_le       DATETIME NULL,
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_edt_classe_annee (classe_id, annee_scolaire),
    KEY idx_edt_statut (statut, annee_scolaire)
);
```

#### `vs_creneaux` — Créneaux horaires

```sql
CREATE TABLE vs_creneaux (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    emploi_du_temps_id      INT UNSIGNED NOT NULL,       -- FK vs_emplois_du_temps
    jour_semaine            TINYINT UNSIGNED NOT NULL,   -- 1=Lun, 2=Mar, ..., 6=Sam
    heure_debut             TIME NOT NULL,
    heure_fin               TIME NOT NULL,
    matiere_id              INT UNSIGNED NULL,           -- FK matieres
    enseignant_id           INT UNSIGNED NULL,           -- FK users
    salle_id                INT UNSIGNED NULL,           -- FK vs_salles
    type                    ENUM('cours','td','tp','sport','activite','pause','libre') DEFAULT 'cours',
    couleur                 VARCHAR(7) NULL,             -- '#6366f1' hex pour UI
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_creneaux_edt       (emploi_du_temps_id, jour_semaine),
    KEY idx_creneaux_enseignant(enseignant_id, jour_semaine),
    KEY idx_creneaux_salle     (salle_id, jour_semaine)
);
```

#### `vs_exceptions_creneaux` — Modifications ponctuelles

```sql
CREATE TABLE vs_exceptions_creneaux (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    creneau_id              INT UNSIGNED NOT NULL,       -- FK vs_creneaux
    date_exception          DATE NOT NULL,
    motif                   VARCHAR(200) NULL,
    nouveau_enseignant_id   INT UNSIGNED NULL,           -- FK users
    nouvelle_salle_id       INT UNSIGNED NULL,           -- FK vs_salles
    annule                  BOOLEAN DEFAULT FALSE,       -- true = cours annulé ce jour
    created_by              INT UNSIGNED NOT NULL,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_exception_creneau_date (creneau_id, date_exception),
    KEY idx_exceptions_date (date_exception)
);
```

---

### 2.6 Domaine Activités Scolaires

#### `vs_activites`

```sql
CREATE TABLE vs_activites (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type                    ENUM('sortie','evenement','competition','conference','autre') NOT NULL,
    titre                   VARCHAR(200) NOT NULL,
    description             TEXT NULL,
    date_debut              DATETIME NOT NULL,
    date_fin                DATETIME NULL,
    lieu                    VARCHAR(300) NULL,
    responsable_id          INT UNSIGNED NOT NULL,       -- FK users
    classes_cibles          JSON NULL,                   -- null = tout l'établissement, [1,2,3] = classes ids
    budget_prevu            DECIMAL(12,2) NULL,
    budget_reel             DECIMAL(12,2) NULL,
    statut                  ENUM('planifie','confirme','en_cours','termine','annule') DEFAULT 'planifie',
    autorisation_requise    BOOLEAN DEFAULT FALSE,       -- autorisation parentale
    nb_places               SMALLINT UNSIGNED NULL,      -- null = illimité
    requires_finance        BOOLEAN DEFAULT FALSE,       -- déclenche demande décaissement
    motif_annulation        TEXT NULL,
    created_by              INT UNSIGNED NOT NULL,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_activites_date    (date_debut, statut),
    KEY idx_activites_type    (type, statut),
    KEY idx_activites_resp    (responsable_id)
);
```

#### `vs_participants_activite`

```sql
CREATE TABLE vs_participants_activite (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activite_id             INT UNSIGNED NOT NULL,       -- FK vs_activites
    eleve_id                INT UNSIGNED NOT NULL,       -- FK eleves
    statut                  ENUM('inscrit','confirme','absent','exclu') DEFAULT 'inscrit',
    autorisation_parentale  BOOLEAN DEFAULT FALSE,
    remarque                TEXT NULL,
    inscrit_par             INT UNSIGNED NOT NULL,       -- FK users
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_participant (activite_id, eleve_id),
    KEY idx_participants_eleve (eleve_id)
);
```

#### `vs_clubs`

```sql
CREATE TABLE vs_clubs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(150) NOT NULL,
    description     TEXT NULL,
    responsable_id  INT UNSIGNED NOT NULL,               -- FK users (enseignant)
    salle_id        INT UNSIGNED NULL,                   -- FK vs_salles
    horaire_regulier VARCHAR(200) NULL,                  -- description libre ex: 'Mercredi 14h-16h'
    actif           BOOLEAN DEFAULT TRUE,
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### `vs_membres_club`

```sql
CREATE TABLE vs_membres_club (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    club_id         INT UNSIGNED NOT NULL,               -- FK vs_clubs
    eleve_id        INT UNSIGNED NOT NULL,               -- FK eleves
    date_adhesion   DATE NOT NULL,
    date_fin        DATE NULL,
    actif           BOOLEAN DEFAULT TRUE,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_membre_actif (club_id, eleve_id, actif),
    KEY idx_membres_eleve (eleve_id)
);
```

---

### 2.7 Vue d'ensemble des tables

| Préfixe | Tables | Nb |
|---|---|---|
| `vs_` | vs_appels, vs_presences, vs_motifs_absence, vs_justifications | 4 |
| `vs_` | vs_absences_enseignants | 1 |
| `vs_` | vs_incidents, vs_types_sanctions, vs_sanctions, vs_observations | 4 |
| `vs_` | vs_recompenses | 1 |
| `vs_` | vs_salles, vs_emplois_du_temps, vs_creneaux, vs_exceptions_creneaux | 4 |
| `vs_` | vs_activites, vs_participants_activite, vs_clubs, vs_membres_club | 4 |
| **Total** | | **18 tables** |

---

## 3. Services

### 3.1 AttendanceService

```
Implements: AttendanceInterface
Namespace:  App\Modules\VieScolaire\Services\AttendanceService
```

**API publique :**

| Méthode | Signature | Description |
|---|---|---|
| `ouvrirAppel` | `(AppelDTO $dto, int $userId): int` | Crée une séance d'appel (statut 'ouvert') |
| `saisirPresence` | `(int $appelId, int $eleveId, PresenceDTO $dto, int $userId): void` | Pointe un élève (present/absent/retard/exclu) |
| `saisirAppelMasse` | `(int $appelId, array $presences, int $userId): void` | Pointage de toute la classe en une passe |
| `cloturerAppel` | `(int $appelId, int $userId): void` | Clôture la séance → dispatch AppelCloture |
| `corrigerPresence` | `(int $presenceId, string $statut, int $userId): void` | Correction post-clôture (admin only) |
| `soumettrJustification` | `(int $presenceId, JustificationDTO $dto, int $userId): int` | Dépose une justification |
| `validerJustification` | `(int $justifId, bool $accepter, ?string $motifRefus, int $userId): void` | Valide ou refuse |
| `declarerAbsenceEnseignant` | `(AbsenceEnseignantDTO $dto, int $userId): int` | Déclare une absence enseignant |
| `validerAbsenceEnseignant` | `(int $id, bool $accepter, int $userId): void` | Valide ou refuse |
| `getAbsencesBilan` | `(int $eleveId, array $filters): array` | Bilan absences/retards par élève |
| `getStatistiquesClasse` | `(int $classeId, array $filters): array` | Taux présence par classe |
| `getAlertesAbsences` | `(?int $classeId, int $seuilJours): array` | Élèves dépassant le seuil |

**Règles métier internes :**
- `ouvrirAppel()` vérifie qu'il n'existe pas déjà un appel ouvert pour la même classe/matière/date
- `saisirPresence()` dispatche `StudentAbsent` si statut=absent, `StudentLate` si statut=retard
- `cloturerAppel()` exige que toutes les présences de la classe soient saisies (ou rejette les manquantes comme 'absent')
- `validerJustification()` met à jour le statut de la présence associée si validée

---

### 3.2 DisciplineService

```
Namespace: App\Modules\VieScolaire\Services\DisciplineService
```

**API publique :**

| Méthode | Signature | Description |
|---|---|---|
| `signalerIncident` | `(IncidentDTO $dto, int $userId): int` | Crée un incident |
| `modifierIncident` | `(int $id, IncidentDTO $dto, int $userId): void` | Mise à jour |
| `traiterIncident` | `(int $id, int $userId): void` | Marque 'en_cours' → attribution sanction |
| `cloturerIncident` | `(int $id, int $userId): void` | Marque 'traite' |
| `appliquerSanction` | `(int $incidentId, SanctionDTO $dto, int $userId): int` | Prononce une sanction |
| `approuverSanction` | `(int $id, int $userId): void` | Direction approuve une exclusion |
| `annulerSanction` | `(int $id, string $motif, int $userId): void` | Annule (statut 'annulee') |
| `ajouterObservation` | `(int $eleveId, string $type, string $contenu, int $userId): int` | Ajoute une observation |
| `attribuerRecompense` | `(RecompenseDTO $dto, int $userId): int` | Attribue une récompense |
| `getDossierDisciplinaire` | `(int $eleveId, string $annee): array` | Incidents + sanctions + observations |
| `getScoreConduite` | `(int $eleveId, string $annee): int` | Calcule le score de conduite |
| `getSanctionsEnCours` | `(?int $classeId): array` | Sanctions actives |

**Règles métier internes :**
- `appliquerSanction()` — si `type_sanction.necessite_direction = true`, statut initial = 'en_attente_approbation' (pas 'prononcee')
- `appliquerSanction()` — si `type_sanction.necessite_parents = true`, dispatch `SanctionApplied` qui déclenche NotificationService
- `getScoreConduite()` : base 100, -points par sanction (gravité), +points par récompense
- Un incident grave → `DisciplineIncidentCreated` avec `gravite='grave'` → DisciplineHandler → notifie direction + parents

---

### 3.3 ScheduleService

```
Implements: ScheduleInterface
Namespace:  App\Modules\VieScolaire\Services\ScheduleService
```

**API publique :**

| Méthode | Signature | Description |
|---|---|---|
| `creerEmploiDuTemps` | `(EmploiDuTempsDTO $dto, int $userId): int` | Crée un EDT vide (brouillon) |
| `ajouterCreneau` | `(int $edtId, CreneauDTO $dto, int $userId): int` | Ajoute un créneau |
| `modifierCreneau` | `(int $id, CreneauDTO $dto, int $userId): void` | Modifie (brouillon) ou crée exception (publié) |
| `supprimerCreneau` | `(int $id, int $userId): void` | Supprime (brouillon uniquement) |
| `publierEmploiDuTemps` | `(int $edtId, int $userId): void` | Publie → dispatch SchedulePublished |
| `creerException` | `(int $creneauId, array $data, int $userId): int` | Exception ponctuelle (annulation/remplacement) |
| `detecterConflits` | `(int $edtId): array` | Retourne les conflits salle/enseignant/classe |
| `getEmploiDuTempsClasse` | `(int $classeId, string $annee): array` | EDT de la semaine |
| `getEmploiDuTempsEnseignant` | `(int $enseignantId, string $annee): array` | EDT personnel enseignant |
| `getDisponibiliteSalle` | `(int $salleId, int $jourSemaine): array` | Plages horaires libres |
| `copierVersSemaineProchaine` | `(int $edtId, int $userId): int` | Clone un EDT pour semaine N+1 |

**Règles métier internes :**
- `ajouterCreneau()` appelle automatiquement `detecterConflits()` — lève une `DomainException` si conflit détecté
- `publierEmploiDuTemps()` lance une dernière détection de conflits avant publication
- `modifierCreneau()` sur un EDT publié crée automatiquement une `vs_exceptions_creneaux` pour la prochaine occurrence plutôt que de modifier le créneau de base
- Un EDT publié ne peut être archivé que si un nouvel EDT est publié pour la même classe+année

**Algorithme de détection des conflits :**
```
Pour chaque créneau (jour, heure_debut, heure_fin) de l'EDT :
  1. Vérifier qu'aucun autre créneau de la MÊME CLASSE ne chevauche cet horaire
  2. Vérifier qu'aucun autre créneau avec le MÊME ENSEIGNANT ne chevauche cet horaire
  3. Vérifier qu'aucun autre créneau dans la MÊME SALLE ne chevauche cet horaire
Retourner liste des conflits avec type + créneau source + créneau en conflit
```

---

### 3.4 ActivityService

```
Namespace: App\Modules\VieScolaire\Services\ActivityService
```

**API publique :**

| Méthode | Signature | Description |
|---|---|---|
| `planifierActivite` | `(ActiviteDTO $dto, int $userId): int` | Planifie une activité (statut 'planifie') |
| `confirmerActivite` | `(int $id, int $userId): void` | Confirme → dispatch ActivityCreated |
| `annulerActivite` | `(int $id, string $motif, int $userId): void` | Annule → dispatch ActivityCancelled |
| `inscrireEleve` | `(int $activiteId, int $eleveId, int $userId): void` | Inscrit un élève |
| `inscrireClasseEntiere` | `(int $activiteId, int $classeId, int $userId): void` | Inscription en masse |
| `confirmerParticipation` | `(int $activiteId, int $eleveId): void` | Autorisation parentale reçue |
| `marquerPresenceActivite` | `(int $activiteId, array $presents, int $userId): void` | Pointage lors de l'activité |
| `creerClub` | `(array $data, int $userId): int` | Crée un club |
| `inscrireMembreClub` | `(int $clubId, int $eleveId, int $userId): void` | Inscrit au club |
| `getActivitesEleve` | `(int $eleveId): array` | Historique activités d'un élève |
| `getCalendrierActivites` | `(array $filters): array` | Vue calendrier |

**Règles métier internes :**
- Si `nb_places` est défini, `inscrireEleve()` vérifie le nombre de participants inscrits+confirmés avant d'accepter
- Si `requires_finance = true`, `confirmerActivite()` dispatche un événement que Finance V2 Phase décaissements écoutera
- Si `autorisation_requise = true`, les élèves restent en statut 'inscrit' jusqu'à `confirmerParticipation()`

---

## 4. Événements et Listeners

### 4.1 Catalogue des événements (15)

#### Présences

| Événement | Dispatché par | Champs |
|---|---|---|
| `AppelOuvert` | `AttendanceService::ouvrirAppel()` | appelId, classeId, enseignantId, dateAppel, heureDebut |
| `AppelCloture` | `AttendanceService::cloturerAppel()` | appelId, classeId, nbPresents, nbAbsents, nbRetards, nbExclus |
| `StudentAbsent` | `AttendanceService::saisirPresence()` | eleveId, appelId, classeId, dateAppel, enseignantId |
| `StudentLate` | `AttendanceService::saisirPresence()` | eleveId, appelId, classeId, minutesRetard, heureArrivee |
| `AbsenceJustified` | `AttendanceService::validerJustification()` | eleveId, presenceId, justificationId, statut ('validee'\|'refusee'), valideParId |
| `AbsenceEnseignantDeclaree` | `AttendanceService::declarerAbsenceEnseignant()` | absenceId, enseignantId, dateDebut, dateFin, creneauxImpactes[] |

#### Discipline

| Événement | Dispatché par | Champs |
|---|---|---|
| `DisciplineIncidentCreated` | `DisciplineService::signalerIncident()` | incidentId, eleveId, gravite, categorie, rapporteurId, dateIncident |
| `SanctionApplied` | `DisciplineService::appliquerSanction()` | sanctionId, eleveId, incidentId, typeSanction, dateDebut, dateFin, notifParents |
| `SanctionAnnulee` | `DisciplineService::annulerSanction()` | sanctionId, eleveId, motif, annulePar |
| `RecompenseAttribuee` | `DisciplineService::attribuerRecompense()` | recompenseId, eleveId, type, points, attribueParId |

#### Emploi du temps

| Événement | Dispatché par | Champs |
|---|---|---|
| `SchedulePublished` | `ScheduleService::publierEmploiDuTemps()` | edtId, classeId, anneeScolaire, publishedById |
| `ScheduleUpdated` | `ScheduleService::modifierCreneau()` | edtId, classeId, creneauId, updateType ('modified'\|'deleted') |
| `ScheduleExceptionCreated` | `ScheduleService::creerException()` | exceptionId, creneauId, dateException, annule, nouvelEnseignantId |

#### Activités

| Événement | Dispatché par | Champs |
|---|---|---|
| `ActivityCreated` | `ActivityService::confirmerActivite()` | activiteId, type, titre, classesCibles[], responsableId, requiresFinance |
| `ActivityCancelled` | `ActivityService::annulerActivite()` | activiteId, titre, motif, annulePar |

### 4.2 Handlers

#### AttendanceHandler

```
Écoute : StudentAbsent, StudentLate, AbsenceJustified, AppelCloture, AbsenceEnseignantDeclaree

onStudentAbsent()
  → AuditService::log() [audit]
  → verifier seuil absences non justifiées
    → si seuil atteint : NotificationService::notify(parentId, 'absence', ...) [alerte]
  → si presences sans justif > 3 ce mois : NotificationService::notify(direction, ...) [alerte direction]

onStudentLate()
  → AuditService::log() [audit]
  → si minutes_retard > 30 : NotificationService::notify(parentId, ...) [alerte retard grave]

onAbsenceJustified()
  → AuditService::log() [audit]

onAppelCloture()
  → AuditService::log() [audit] avec nb_absents
  → Stats cache invalidation [si cache implémenté]

onAbsenceEnseignantDeclaree()
  → AuditService::log() [audit]
  → NotificationService (direction) [alerte remplacement requis]
```

#### DisciplineHandler

```
Écoute : DisciplineIncidentCreated, SanctionApplied, SanctionAnnulee, RecompenseAttribuee

onDisciplineIncidentCreated()
  → AuditService::log()
  → si gravite IN ('grave','tres_grave') :
    → NotificationService::notify(parentId, ...) [alerte parent]
    → NotificationService::notify(direction_id, ...) [alerte direction]

onSanctionApplied()
  → AuditService::log()
  → si $event->notifParents :
    → NotificationService::notify(parentId, 'discipline', ...)

onSanctionAnnulee()
  → AuditService::log()

onRecompenseAttribuee()
  → AuditService::log()
  → NotificationService::notify(parentId, 'recompense', ...)
```

#### ScheduleHandler

```
Écoute : SchedulePublished, ScheduleUpdated, ScheduleExceptionCreated

onSchedulePublished()
  → AuditService::log()
  → NotificationService::notifyBulk(enseignants_classes, 'emploi_du_temps', ...)

onScheduleUpdated()
  → AuditService::log()

onScheduleExceptionCreated()
  → AuditService::log()
  → si $event->annule :
    → NotificationService::notify(enseignantId, 'cours_annule', ...)
```

#### ActivityHandler

```
Écoute : ActivityCreated, ActivityCancelled

onActivityCreated()
  → AuditService::log()
  → si $event->requiresFinance :
    → [PLACEHOLDER] Dispatcher vers Finance Phase décaissements
  → NotificationService::notifyBulk(parents_classes_cibles, 'activite', ...)

onActivityCancelled()
  → AuditService::log()
  → NotificationService::notifyBulk(parents_inscrits, 'activite_annulee', ...)
```

### 4.3 Intégration dans config/events.php

```php
// ── Module Vie Scolaire V2 — Présences ──────────────────────────────────────
\App\Modules\VieScolaire\Events\AppelOuvert::class => [
    new \App\Modules\VieScolaire\Listeners\AttendanceHandler(),
],
\App\Modules\VieScolaire\Events\AppelCloture::class => [
    new \App\Modules\VieScolaire\Listeners\AttendanceHandler(),
],
\App\Modules\VieScolaire\Events\StudentAbsent::class => [
    new \App\Modules\VieScolaire\Listeners\AttendanceHandler(),
],
\App\Modules\VieScolaire\Events\StudentLate::class => [
    new \App\Modules\VieScolaire\Listeners\AttendanceHandler(),
],
\App\Modules\VieScolaire\Events\AbsenceJustified::class => [
    new \App\Modules\VieScolaire\Listeners\AttendanceHandler(),
],
\App\Modules\VieScolaire\Events\AbsenceEnseignantDeclaree::class => [
    new \App\Modules\VieScolaire\Listeners\AttendanceHandler(),
],

// ── Module Vie Scolaire V2 — Discipline ─────────────────────────────────────
\App\Modules\VieScolaire\Events\DisciplineIncidentCreated::class => [
    new \App\Modules\VieScolaire\Listeners\DisciplineHandler(),
],
\App\Modules\VieScolaire\Events\SanctionApplied::class => [
    new \App\Modules\VieScolaire\Listeners\DisciplineHandler(),
],
\App\Modules\VieScolaire\Events\SanctionAnnulee::class => [
    new \App\Modules\VieScolaire\Listeners\DisciplineHandler(),
],
\App\Modules\VieScolaire\Events\RecompenseAttribuee::class => [
    new \App\Modules\VieScolaire\Listeners\DisciplineHandler(),
],

// ── Module Vie Scolaire V2 — Emploi du temps ────────────────────────────────
\App\Modules\VieScolaire\Events\SchedulePublished::class => [
    new \App\Modules\VieScolaire\Listeners\ScheduleHandler(),
],
\App\Modules\VieScolaire\Events\ScheduleUpdated::class => [
    new \App\Modules\VieScolaire\Listeners\ScheduleHandler(),
],
\App\Modules\VieScolaire\Events\ScheduleExceptionCreated::class => [
    new \App\Modules\VieScolaire\Listeners\ScheduleHandler(),
],

// ── Module Vie Scolaire V2 — Activités ──────────────────────────────────────
\App\Modules\VieScolaire\Events\ActivityCreated::class => [
    new \App\Modules\VieScolaire\Listeners\ActivityHandler(),
],
\App\Modules\VieScolaire\Events\ActivityCancelled::class => [
    new \App\Modules\VieScolaire\Listeners\ActivityHandler(),
],
```

---

## 5. RBAC — Permissions

### 5.1 Permissions (22)

```php
// config/permissions.php — sections à ajouter

'vie_scolaire.presences.view'         => "Voir les appels et présences",
'vie_scolaire.presences.manage'       => "Saisir et corriger les appels",
'vie_scolaire.absences.view'          => "Voir toutes les absences",
'vie_scolaire.absences.view.own'      => "Voir ses propres absences (élève/parent)",
'vie_scolaire.absences.justify'       => "Soumettre une justification",
'vie_scolaire.absences.validate'      => "Valider les justifications",
'vie_scolaire.absences.manage'        => "Gérer les absences enseignants",
'vie_scolaire.discipline.view'        => "Voir les incidents disciplinaires",
'vie_scolaire.discipline.view.own'    => "Voir son dossier disciplinaire",
'vie_scolaire.discipline.create'      => "Signaler un incident",
'vie_scolaire.discipline.manage'      => "Gérer les sanctions",
'vie_scolaire.discipline.approve'     => "Approuver les exclusions (direction)",
'vie_scolaire.recompenses.view'       => "Voir les récompenses",
'vie_scolaire.recompenses.manage'     => "Attribuer des récompenses",
'vie_scolaire.emploi_du_temps.view'   => "Voir l'emploi du temps",
'vie_scolaire.emploi_du_temps.manage' => "Créer et modifier l'emploi du temps",
'vie_scolaire.emploi_du_temps.publish'=> "Publier l'emploi du temps",
'vie_scolaire.activites.view'         => "Voir les activités scolaires",
'vie_scolaire.activites.manage'       => "Créer et modifier des activités",
'vie_scolaire.activites.approve'      => "Valider une activité",
'vie_scolaire.clubs.view'             => "Voir les clubs",
'vie_scolaire.clubs.manage'           => "Gérer les clubs",
```

### 5.2 Attribution par rôle

| Permission | admin | directeur | secretaire | enseignant | parent | élève |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| presences.view | ✅ | ✅ | ✅ | ✅ (ses classes) | — | — |
| presences.manage | ✅ | ✅ | ✅ | ✅ (ses classes) | — | — |
| absences.view | ✅ | ✅ | ✅ | ✅ (ses classes) | — | — |
| absences.view.own | ✅ | ✅ | ✅ | — | ✅ | ✅ |
| absences.justify | ✅ | ✅ | ✅ | — | ✅ | — |
| absences.validate | ✅ | ✅ | ✅ | — | — | — |
| absences.manage | ✅ | ✅ | ✅ | — | — | — |
| discipline.view | ✅ | ✅ | ✅ | ✅ (ses incidents) | — | — |
| discipline.view.own | — | — | — | — | ✅ | ✅ |
| discipline.create | ✅ | ✅ | ✅ | ✅ | — | — |
| discipline.manage | ✅ | ✅ | — | — | — | — |
| discipline.approve | ✅ | ✅ | — | — | — | — |
| recompenses.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| recompenses.manage | ✅ | ✅ | ✅ | ✅ | — | — |
| emploi_du_temps.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| emploi_du_temps.manage | ✅ | ✅ | ✅ | — | — | — |
| emploi_du_temps.publish | ✅ | ✅ | — | — | — | — |
| activites.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| activites.manage | ✅ | ✅ | ✅ | ✅ | — | — |
| activites.approve | ✅ | ✅ | — | — | — | — |
| clubs.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| clubs.manage | ✅ | ✅ | ✅ | ✅ | — | — |

### 5.3 Policies

```
AttendancePolicy
  canView($user)        → vie_scolaire.presences.view
  canManage($user)      → vie_scolaire.presences.manage
  canValidate($user)    → vie_scolaire.absences.validate
  canViewOwn($user)     → vie_scolaire.absences.view.own
  canJustify($user)     → vie_scolaire.absences.justify

DisciplinePolicy
  canView($user)        → vie_scolaire.discipline.view
  canViewOwn($user)     → vie_scolaire.discipline.view.own
  canCreate($user)      → vie_scolaire.discipline.create
  canManage($user)      → vie_scolaire.discipline.manage
  canApprove($user)     → vie_scolaire.discipline.approve
  canManageRecompenses($user) → vie_scolaire.recompenses.manage

SchedulePolicy
  canView($user)        → vie_scolaire.emploi_du_temps.view
  canManage($user)      → vie_scolaire.emploi_du_temps.manage
  canPublish($user)     → vie_scolaire.emploi_du_temps.publish

ActivityPolicy
  canView($user)        → vie_scolaire.activites.view
  canManage($user)      → vie_scolaire.activites.manage
  canApprove($user)     → vie_scolaire.activites.approve
  canManageClubs($user) → vie_scolaire.clubs.manage
```

---

## 6. Flux métier

### 6.1 Flux appel en classe

```
Enseignant ouvre l'appel
  → AppelController::store() → AttendanceService::ouvrirAppel(AppelDTO)
    → Vérifier: pas d'appel ouvert pour classe+matière+date
    → INSERT vs_appels (statut='ouvert')
    → dispatch AppelOuvert
    → Retourne appelId

Enseignant saisit les présences
  → AppelController::saisirMasse() → AttendanceService::saisirAppelMasse(appelId, presences[])
    → Pour chaque élève :
      → INSERT / UPDATE vs_presences
      → si statut='absent' → dispatch StudentAbsent
        → AttendanceHandler → audit + vérif seuil + notif parent si seuil
      → si statut='retard' → dispatch StudentLate
        → AttendanceHandler → audit + notif si retard > 30 min

Enseignant clôture l'appel
  → AppelController::cloture() → AttendanceService::cloturerAppel(appelId)
    → UPDATE vs_appels statut='cloture'
    → dispatch AppelCloture(nbAbsents, nbRetards)
    → AttendanceHandler → audit + stats

Parent justifie une absence
  → JustificationController::store() → AttendanceService::soumettrJustification(presenceId, JustificationDTO)
    → INSERT vs_justifications (statut='en_attente')
    → AuditService::log()

Secrétaire valide la justification
  → JustificationController::valider() → AttendanceService::validerJustification(justifId, true)
    → UPDATE vs_justifications statut='validee'
    → UPDATE vs_presences observation='justifiee'
    → dispatch AbsenceJustified(statut='validee')
    → AttendanceHandler → audit
```

### 6.2 Flux incident disciplinaire

```
Enseignant signale un incident
  → DisciplineController::store() → DisciplineService::signalerIncident(IncidentDTO)
    → INSERT vs_incidents (statut='signale')
    → dispatch DisciplineIncidentCreated(gravite)
    → DisciplineHandler:
      → audit
      → si gravite IN ('grave','tres_grave') :
        → NotificationService → parent + direction

Direction prononce une sanction
  → DisciplineController::sanction() → DisciplineService::appliquerSanction(incidentId, SanctionDTO)
    → Vérifier Policy canManage()
    → INSERT vs_sanctions
    → si type necessite_direction → statut='en_attente_approbation'
    → sinon → statut='prononcee'
    → dispatch SanctionApplied(notifParents=true)
    → DisciplineHandler → audit + NotificationService(parents)

Direction approuve une exclusion
  → DisciplineController::approuver() → [vérifier canApprove()]
    → UPDATE vs_sanctions statut='prononcee', approuve_par, approuve_le
    → NotificationService → parents (confirmation exclusion)
```

### 6.3 Flux emploi du temps

```
Secrétaire crée l'EDT (brouillon)
  → EmploiDuTempsController::store() → ScheduleService::creerEmploiDuTemps(DTO)
    → Vérifier: pas d'EDT non-archivé pour classe+année
    → INSERT vs_emplois_du_temps (statut='brouillon')

Secrétaire ajoute des créneaux
  → EmploiDuTempsController::creneau() → ScheduleService::ajouterCreneau(edtId, CreneauDTO)
    → detecterConflits() → si conflit : DomainException
    → INSERT vs_creneaux

Directeur publie
  → EmploiDuTempsController::publier() → ScheduleService::publierEmploiDuTemps(edtId)
    → detecterConflits() → si conflit : DomainException
    → UPDATE vs_emplois_du_temps statut='publie'
    → dispatch SchedulePublished
    → ScheduleHandler → audit + NotificationService(enseignants)

Exception post-publication (cours annulé)
  → EmploiDuTempsController::exception() → ScheduleService::creerException(creneauId, data)
    → INSERT vs_exceptions_creneaux(annule=true, date_exception)
    → dispatch ScheduleExceptionCreated
    → ScheduleHandler → audit + NotificationService(enseignant)
```

### 6.4 Flux sortie scolaire

```
Enseignant planifie une sortie
  → ActiviteController::store() → ActivityService::planifierActivite(ActiviteDTO)
    → INSERT vs_activites (statut='planifie', requires_finance=true si budget>0)

Secrétaire inscrit les élèves
  → ActiviteController::inscrireClasse() → ActivityService::inscrireClasseEntiere(activiteId, classeId)
    → INSERT vs_participants_activite (statut='inscrit') pour chaque élève de la classe

Directeur confirme
  → ActiviteController::confirmer() → ActivityService::confirmerActivite(id)
    → UPDATE vs_activites statut='confirme'
    → dispatch ActivityCreated(requiresFinance=true)
    → ActivityHandler:
      → audit
      → [PLACEHOLDER] si requiresFinance → finance integration (Phase décaissements)
      → NotificationService → parents des élèves inscrits

Parent confirme autorisation
  → [Portail Parent] ActivityService::confirmerParticipation(activiteId, eleveId)
    → UPDATE vs_participants_activite autorisation_parentale=true, statut='confirme'
```

---

## 7. Dépendances inter-modules

### 7.1 Dépendances en lecture (Scolarité V2)

| Table lue | Colonnes utilisées | Usage |
|---|---|---|
| `eleves` | id, nom, prenom, classe_id, parent_id | Pointage, dossier disciplinaire, activités |
| `classes` | id, nom, niveau | EDT, appels, discipline |
| `matieres` | id, nom | Créneau EDT, appel matière |
| `users` | id, nom, prenom, role, email | Enseignants (appels, EDT, clubs), parents (notifications) |

**Remarque** : Vie Scolaire lit ces tables mais n'écrit jamais dedans. Toute modification passe par les Services Scolarité V2.

### 7.2 Dépendances événementielles (Scolarité V2 → Vie Scolaire)

| Événement Scolarité | Réaction Vie Scolaire |
|---|---|
| `InscriptionCreated` | Créer les entrées présence pour le nouvel élève dans les appels à venir |
| `ClasseChanged` | Mettre à jour les créneaux si l'enseignant est lié à une classe réaffectée |
| `EleveArchived` | Marquer les participations futures de l'élève comme annulées |

### 7.3 Dépendances avec Académique V2

| Lien | Direction | Usage |
|---|---|---|
| `periodes_scolaires.id` | Lire | Rattacher les appels à une période académique |
| `PeriodeLocked` (event) | Écouter | Bloquer les corrections d'appel sur une période clôturée |
| Récompenses → bulletin | Data flow | `vs_recompenses.visible_bulletin = true` → BulletinGenerator lit la table pour l'intégrer |

### 7.4 Dépendances avec Finance V2

| Lien | Direction | Usage |
|---|---|---|
| `ActivityCreated(requiresFinance=true)` | Dispatch | ActivityHandler notifie Finance pour créer une demande de décaissement |
| Finance V2 `ExpenseValidated` | Attendre | La sortie ne peut être définitivement confirmée qu'après validation du budget (optionnel, Phase décaissements) |

### 7.5 Dépendances avec Portails Phase 5

| Portail | Fonctionnalités attendues |
|---|---|
| **Parent** | Voir présences/absences de son enfant, soumettre justifications, voir sanctions visibles, voir EDT, confirmer participations activités |
| **Élève** | Voir son EDT, voir ses récompenses, voir son dossier (visible_parents=true), s'inscrire aux clubs |
| **Enseignant** | Saisir appels (ses classes), signaler incidents, voir son EDT personnel, gérer ses clubs |

---

## 8. Routes prévues

```
Préfixe : /v2/vie-scolaire

── Appels / Présences ────────────────────────────────────────────────────────
GET  /v2/vie-scolaire/appels                     AppelController@index
GET  /v2/vie-scolaire/appels/create              AppelController@create
POST /v2/vie-scolaire/appels                     AppelController@store
GET  /v2/vie-scolaire/appels/{id}                AppelController@show
POST /v2/vie-scolaire/appels/{id}/presences      AppelController@saisirMasse
POST /v2/vie-scolaire/appels/{id}/cloture        AppelController@cloture

── Justifications ────────────────────────────────────────────────────────────
GET  /v2/vie-scolaire/justifications             JustificationController@index
POST /v2/vie-scolaire/justifications             JustificationController@store
POST /v2/vie-scolaire/justifications/{id}/valider JustificationController@valider
POST /v2/vie-scolaire/justifications/{id}/refuser JustificationController@refuser

── Absences ──────────────────────────────────────────────────────────────────
GET  /v2/vie-scolaire/absences                   AbsenceController@index
GET  /v2/vie-scolaire/absences/eleve/{id}        AbsenceController@parEleve
GET  /v2/vie-scolaire/absences/enseignants        AbsenceController@enseignants
POST /v2/vie-scolaire/absences/enseignants        AbsenceController@storeEnseignant
POST /v2/vie-scolaire/absences/enseignants/{id}/valider AbsenceController@validerEnseignant

── Discipline ────────────────────────────────────────────────────────────────
GET  /v2/vie-scolaire/discipline                 DisciplineController@index
POST /v2/vie-scolaire/discipline/incidents        DisciplineController@storeIncident
GET  /v2/vie-scolaire/discipline/incidents/{id}  DisciplineController@showIncident
POST /v2/vie-scolaire/discipline/incidents/{id}/sanction DisciplineController@appliquerSanction
POST /v2/vie-scolaire/discipline/sanctions/{id}/approuver DisciplineController@approuver
POST /v2/vie-scolaire/discipline/sanctions/{id}/annuler   DisciplineController@annuler
POST /v2/vie-scolaire/discipline/observations    DisciplineController@storeObservation
GET  /v2/vie-scolaire/discipline/dossier/{eleveId} DisciplineController@dossier

── Récompenses ───────────────────────────────────────────────────────────────
GET  /v2/vie-scolaire/recompenses                RecompenseController@index
POST /v2/vie-scolaire/recompenses                RecompenseController@store
GET  /v2/vie-scolaire/recompenses/eleve/{id}     RecompenseController@parEleve

── Emploi du temps ───────────────────────────────────────────────────────────
GET  /v2/vie-scolaire/emploi-du-temps             EmploiDuTempsController@index
GET  /v2/vie-scolaire/emploi-du-temps/create      EmploiDuTempsController@create
POST /v2/vie-scolaire/emploi-du-temps             EmploiDuTempsController@store
GET  /v2/vie-scolaire/emploi-du-temps/{id}        EmploiDuTempsController@show
POST /v2/vie-scolaire/emploi-du-temps/{id}/creneaux  EmploiDuTempsController@storeCreneau
POST /v2/vie-scolaire/emploi-du-temps/{id}/publier   EmploiDuTempsController@publier
POST /v2/vie-scolaire/creneaux/{id}              EmploiDuTempsController@updateCreneau
POST /v2/vie-scolaire/creneaux/{id}/exception    EmploiDuTempsController@creerException

── Salles ─────────────────────────────────────────────────────────────────────
GET  /v2/vie-scolaire/salles                     SalleController@index
POST /v2/vie-scolaire/salles                     SalleController@store
GET  /v2/vie-scolaire/salles/{id}                SalleController@show
POST /v2/vie-scolaire/salles/{id}                SalleController@update
POST /v2/vie-scolaire/salles/{id}/toggle         SalleController@toggle

── Activités ─────────────────────────────────────────────────────────────────
GET  /v2/vie-scolaire/activites                  ActiviteController@index
POST /v2/vie-scolaire/activites                  ActiviteController@store
GET  /v2/vie-scolaire/activites/{id}             ActiviteController@show
POST /v2/vie-scolaire/activites/{id}/confirmer   ActiviteController@confirmer
POST /v2/vie-scolaire/activites/{id}/annuler     ActiviteController@annuler
POST /v2/vie-scolaire/activites/{id}/inscrire    ActiviteController@inscrireClasse
POST /v2/vie-scolaire/clubs                      ActiviteController@storeClub
GET  /v2/vie-scolaire/clubs                      ActiviteController@clubs
POST /v2/vie-scolaire/clubs/{id}/membre          ActiviteController@ajouterMembre

── Rapports ──────────────────────────────────────────────────────────────────
GET  /v2/vie-scolaire/rapports/presences         RapportController@presences
GET  /v2/vie-scolaire/rapports/discipline        RapportController@discipline
GET  /v2/vie-scolaire/rapports/activites         RapportController@activites
GET  /v2/vie-scolaire/rapports                   RapportController@index

Total : ~45 routes
```

---

## 9. Sécurité

### 9.1 Données sensibles

| Donnée | Sensibilité | Mesure |
|---|---|---|
| Dossier disciplinaire | Élevée — données personnelles | Accès RBAC strict, soft delete uniquement |
| Incidents | Élevée | `visible_parents` / `visible_bulletin` = false par défaut |
| Justificatifs (upload) | Moyenne | UploadService — mime check + path traversal protection |
| Observations négatives | Élevée | `visible_parents` configurable par auteur |
| Présences | Faible-moyenne | Accessible aux rôles autorisés uniquement |

### 9.2 Invariants de sécurité

- Un parent ne peut voir que les données de ses propres enfants (`parent_id = eleve.parent_id`)
- Un enseignant ne peut saisir un appel que pour une classe qu'il enseigne (vérification dans Policy)
- Une sanction d'exclusion ne peut être prononcée que par un rôle `admin` ou `directeur`
- Les justificatifs uploadés sont servis via la route sécurisée UploadService (`/uploads/serve/`)
- Toutes les mutations passent par `verifyCsrf()` (héritage Core\Controller obligatoire)

### 9.3 RGPD — Données disciplinaires

Le dossier disciplinaire contient des données sensibles sur des mineurs. À documenter dans la politique de l'établissement :
- Durée de conservation : recommandée 3 ans après la fin de scolarité
- Droit à l'effacement : disponible via soft delete (`statut='archive'`) + purge programmable
- Accès : log dans audit_logs pour tout accès au dossier d'un élève
- Justificatifs (fichiers) : purge des fichiers après validation (ne pas conserver les certificats médicaux au-delà de 1 an)

### 9.4 Audit

Toutes les mutations critiques sont journalisées via AuditService :
- Création/modification/clôture d'appel
- Ajout/correction de présence
- Soumission/validation de justification
- Création/modification de sanction
- Attribution de récompense
- Publication d'emploi du temps
- Toute exception de créneau

---

## 10. Stratégie de migration V1 → V2

### 10.1 Coexistence avec les modules V1

| Module V1 existant | Action |
|---|---|
| `AbsencesController.php` (V1) | **Maintenu actif** — routes V1 `/absences/*` préservées |
| Module Emploi du Temps V1 | **Maintenu actif** — routes V1 `/emploi-du-temps/*` préservées |
| Tables absences V1 (si existantes) | **Non touchées** — vs_ prefix évite tout conflit |

### 10.2 Ordre de déploiement recommandé

```
Phase 5.1 — Référentiels
  → vs_salles, vs_motifs_absence, vs_types_sanctions
  → SalleController + référentiels discipline

Phase 5.2 — Emploi du temps
  → vs_emplois_du_temps, vs_creneaux, vs_exceptions_creneaux
  → ScheduleService + ScheduleController
  → Désactivation progressive du module EDT V1

Phase 5.3 — Présences
  → vs_appels, vs_presences, vs_justifications, vs_absences_enseignants
  → AttendanceService + AppelController + JustificationController
  → NotificationService branché sur StudentAbsent

Phase 5.4 — Discipline et Récompenses
  → vs_incidents, vs_sanctions, vs_observations, vs_recompenses
  → DisciplineService + DisciplineController + RecompenseController
  → DisciplineHandler branché sur NotificationService

Phase 5.5 — Activités
  → vs_activites, vs_participants_activite, vs_clubs, vs_membres_club
  → ActivityService + ActiviteController
  → ActivityHandler avec placeholder Finance

Phase 5.6 — Intégrations
  → Branchement InscriptionCreated → AttendanceHandler
  → Branchement PeriodeLocked → AttendanceHandler
  → Intégration bulletin (vs_recompenses + vs_sanctions visible_bulletin)
  → Module Freeze Vie Scolaire V2
```

---

## 11. Risques

### Risques critiques

| ID | Risque | Impact | Mitigation |
|---|---|---|---|
| VS-R-001 | **Volume données présences** : 500 élèves × 200 jours × 6 appels/jour = 600 000 enregistrements/an | Lenteur queries | Index composites (eleve_id, created_at), (appel_id, statut) obligatoires dès migration |
| VS-R-002 | **Notifications en rafale** : StudentAbsent dispatché 6 fois si élève absent toute la journée → 6 SMS au parent | Coût + spam | Implémenter un système de digest quotidien (1 notif/jour max par parent) dans AttendanceHandler |
| VS-R-003 | **Conflit emploi du temps** : Deux enseignants créent simultanément des créneaux chevauchants | Doublon non détecté | `detecterConflits()` avant publication ET transaction PDO sur `ajouterCreneau()` pour prévenir les races conditions |
| VS-R-004 | **Données RGPD mineurs** : Incidents, sanctions, observations — sensibilité juridique élevée | Risque légal | Durée de conservation définie, accès audité, soft delete uniquement |

### Risques majeurs

| ID | Risque | Mitigation |
|---|---|---|
| VS-R-005 | Double comptage absences (V1 + V2 coexistent) | Tableaux de bord VS V2 n'agrègent que les tables `vs_*` |
| VS-R-006 | EDT V2 publié mais élèves consultent encore V1 | Activation progressive par classe, notification aux concernés |
| VS-R-007 | Sanction prononcée sans approbation direction (oubli de Policy) | Test unitaire Policy obligatoire : `canApprove()` pour exclusions |
| VS-R-008 | Activité avec budget confirmée sans Finance (décaissements absent) | Placeholder clair dans ActivityHandler, flag `requires_finance` visible dans UI |

---

## 12. Roadmap du module

### Découpage en 6 sous-phases

```
Phase 5.1 — Référentiels (Semaine 1)
  Durée estimée : 1 jour
  Livrable : vs_salles + vs_motifs_absence + vs_types_sanctions + seeds
  Critère GO : référentiels accessibles via API, seedés avec données par défaut

Phase 5.2 — Emploi du temps (Semaine 1-2)
  Durée estimée : 3 jours
  Livrable : ScheduleService + 3 vues + 12 routes + ScheduleHandler
  Critère GO : création EDT, ajout créneaux, détection conflits, publication, exceptions

Phase 5.3 — Présences & Absences (Semaine 2-3)
  Durée estimée : 4 jours
  Livrable : AttendanceService + 4 controllers + justifications + absences enseignants
  Critère GO : cycle complet appel → absence → justification → validation

Phase 5.4 — Discipline & Récompenses (Semaine 3)
  Durée estimée : 3 jours
  Livrable : DisciplineService + DisciplineController + RecompenseController + dossier
  Critère GO : cycle incident → sanction → approbation direction → notification parents

Phase 5.5 — Activités & Clubs (Semaine 4)
  Durée estimée : 2 jours
  Livrable : ActivityService + ActiviteController + clubs + participations
  Critère GO : planification → confirmation → inscription → autorisation parentale

Phase 5.6 — Intégrations & Freeze (Semaine 4-5)
  Durée estimée : 2 jours
  Livrable : connecteurs inter-modules + System Integration Review + Module Freeze
  Critère GO : InscriptionCreated → présences, PeriodeLocked → appels, bulletin + récompenses
```

### Jalons de validation

| Jalon | Critère |
|---|---|
| Blueprint validé (Phase 5.0) | Document approuvé par le responsable projet |
| Phase 5.2 GO | EDT publié visible par enseignants et parents |
| Phase 5.3 GO | Taux présence calculé automatiquement, notifications parents actives |
| Phase 5.4 GO | Dossier disciplinaire complet, approbation direction fonctionnelle |
| Module Freeze VS | Score ≥ 8.0/10, zéro critique bloquante |

---

## 13. Synthèse du blueprint

### Inventaire prévisionnel

| Catégorie | Quantité |
|---|---|
| Tables SQL (préfixe vs_) | 18 |
| Services | 4 |
| Controllers | 8+ |
| Repositories | 4 |
| DTOs | 11 |
| Policies | 4 |
| Events | 15 |
| Listeners | 4 |
| Permissions RBAC | 22 |
| Routes | ~45 |
| Vues | ~30 (estimation) |

### Dépendances confirmées

| Module | Type | Tables lues |
|---|---|---|
| Scolarité V2 | Lecture + Events | eleves, classes, matieres, inscriptions |
| Académique V2 | Lecture + Events | periodes_scolaires (PeriodeLocked) |
| Finance V2 | Events sortants | ActivityCreated → décaissement |
| Portails Phase 5 | Consommation | Toutes tables vs_* en lecture filtrée |

---

*MODULE_VIE_SCOLAIRE_BLUEPRINT.md — Phase 5.0 — 2026-07-01*  
*Conception uniquement — aucun code, aucune migration*
