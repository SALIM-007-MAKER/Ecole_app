# DATABASE V2 — SCOLARIS
## Analyse, Problèmes, Modèle Cible & Scripts de Migration

> Document produit dans le cadre de la migration SCOLARIS V2.
> **Aucune migration ne doit être lancée sans validation préalable.**
> Date : 2026-06-29

---

## TABLE DES MATIÈRES

1. [Modèle actuel — inventaire complet](#1-modèle-actuel--inventaire-complet)
2. [Problèmes identifiés](#2-problèmes-identifiés)
3. [Modèle cible V2](#3-modèle-cible-v2)
4. [Nouvelles tables](#4-nouvelles-tables)
5. [Scripts de migration SQL](#5-scripts-de-migration-sql)

---

## 1. MODÈLE ACTUEL — INVENTAIRE COMPLET

### 1.1 Ordre d'application des migrations (tel qu'implémenté)

```
ecole_app.sql                        ← Base initiale (tables fondatrices)
  └── auth_migration.sql             ← ALTER users, password_resets, permissions
        └── eleves_migration.sql     ← ALTER eleves (parent_id)
              └── classes_matieres_migration.sql  ← ALTER matieres/classes, professeurs
                    ├── enseignants_migration.sql  ← ALTER professeurs
                    └── academique_migration.sql   ← periodes, controles, notes(V2), moyennes
absences_migration.sql               ← absences(V2), justifications
comptabilite_migration.sql           ← frais_types, frais_eleves, paiements, depenses
emploi_du_temps_migration.sql        ← salles, creneaux, emplois_du_temps
espaces_migration.sql                ← notifications, annonces
notifications_system_migration.sql   ← notification_preferences, notification_logs
[push_subscriptions]                 ← Créée dynamiquement par PHP (non migrée)
```

### 1.2 Schéma complet par table

#### Groupe AUTH

```sql
-- users (modifiée sur 2 migrations)
users (
  id                  INT UNSIGNED PK AUTO_INCREMENT,
  nom                 VARCHAR(100) NOT NULL,
  prenom              VARCHAR(100) NULL,                    -- ajouté auth_migration
  email               VARCHAR(191) UNIQUE NOT NULL,
  password            VARCHAR(255) NOT NULL,
  role                ENUM('admin','directeur','secretaire','comptable',
                           'enseignant','parent','eleve') DEFAULT 'secretaire',
  telephone           VARCHAR(20)  NULL,                   -- ajouté auth_migration
  photo               VARCHAR(255) NULL,                   -- ajouté auth_migration
  actif               TINYINT(1)   DEFAULT 1,
  derniere_connexion  DATETIME     NULL,                   -- ajouté auth_migration
  reset_token         VARCHAR(100) NULL,                   -- ajouté auth_migration ⚠ DOUBLON
  reset_token_expires DATETIME     NULL,                   -- ajouté auth_migration ⚠ DOUBLON
  created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)

-- password_resets
password_resets (
  id         INT UNSIGNED PK AUTO_INCREMENT,
  email      VARCHAR(191) NOT NULL,                        -- INDEX
  token      VARCHAR(100) UNIQUE NOT NULL,                 -- INDEX
  expires_at DATETIME NOT NULL,
  used       TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)

-- permissions
permissions (
  id      INT UNSIGNED PK AUTO_INCREMENT,
  code    VARCHAR(100) UNIQUE NOT NULL,
  libelle VARCHAR(200) NOT NULL,
  module  VARCHAR(50)  NOT NULL
)

-- role_permissions
role_permissions (
  role          ENUM('admin','directeur','secretaire','comptable','enseignant','parent','eleve'),
  permission_id INT UNSIGNED FK → permissions(id) ON DELETE CASCADE,
  PK(role, permission_id)
)
```

#### Groupe SCOLARITÉ

```sql
-- classes
classes (
  id             INT UNSIGNED PK AUTO_INCREMENT,
  nom            VARCHAR(50)  NOT NULL,
  niveau         VARCHAR(30)  NOT NULL,
  annee_scolaire VARCHAR(9)   NOT NULL,                    -- ⚠ pas de FK, format libre
  max_eleves     TINYINT UNSIGNED DEFAULT 35,
  description    TEXT NULL,                                -- ajouté classes_matieres_migration
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)

-- eleves
eleves (
  id             INT UNSIGNED PK AUTO_INCREMENT,
  matricule      VARCHAR(20)  UNIQUE NOT NULL,
  nom            VARCHAR(100) NOT NULL,
  prenom         VARCHAR(100) NOT NULL,
  date_naissance DATE NOT NULL,
  sexe           ENUM('M','F') NOT NULL,
  adresse        TEXT NULL,
  telephone      VARCHAR(20)  NULL,
  email          VARCHAR(191) NULL,
  classe_id      INT UNSIGNED NULL FK → classes(id) ON DELETE SET NULL,
  parent_id      INT UNSIGNED NULL FK → users(id) ON DELETE SET NULL,  -- ajouté eleves_migration
  photo          VARCHAR(255) NULL,
  actif          TINYINT(1)  DEFAULT 1,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  -- ⚠ ABSENT : user_id → pas de lien vers le compte user de l'élève
)

-- matieres
matieres (
  id             INT UNSIGNED PK AUTO_INCREMENT,
  nom            VARCHAR(100) NOT NULL,
  coefficient    DECIMAL(3,1) DEFAULT 1.0,
  volume_horaire TINYINT UNSIGNED DEFAULT 2,               -- ajouté classes_matieres_migration
  responsable_id INT UNSIGNED NULL FK → professeurs(id) ON DELETE SET NULL, -- ⚠ couplage implicite
  description    TEXT NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)

-- professeurs
professeurs (
  id               INT UNSIGNED PK AUTO_INCREMENT,
  user_id          INT UNSIGNED NULL FK → users(id) ON DELETE SET NULL,
  nom              VARCHAR(100) NOT NULL,                  -- ⚠ DOUBLON si user_id renseigné
  prenom           VARCHAR(100) NOT NULL,                  -- ⚠ DOUBLON si user_id renseigné
  specialite       VARCHAR(100) NOT NULL,
  telephone        VARCHAR(20)  NULL,                      -- ⚠ DOUBLON si user_id renseigné
  email            VARCHAR(191) NULL,                      -- ⚠ DOUBLON si user_id renseigné
  actif            TINYINT(1)   DEFAULT 1,
  grade            VARCHAR(100) NULL,                      -- ajouté enseignants_migration
  date_recrutement DATE         NULL,
  adresse          TEXT         NULL,
  photo            VARCHAR(255) NULL,                      -- ⚠ DOUBLON si user_id renseigné
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)

-- enseignements
enseignements (
  id             INT UNSIGNED PK AUTO_INCREMENT,
  professeur_id  INT UNSIGNED NOT NULL FK → professeurs(id) ON DELETE CASCADE,
  matiere_id     INT UNSIGNED NOT NULL FK → matieres(id) ON DELETE CASCADE,
  classe_id      INT UNSIGNED NOT NULL FK → classes(id) ON DELETE CASCADE,
  annee_scolaire VARCHAR(9)   NOT NULL,                    -- ⚠ pas de FK, format libre
  UNIQUE(professeur_id, matiere_id, classe_id, annee_scolaire)
)
```

#### Groupe ACADÉMIQUE

```sql
-- periodes
periodes (
  id             INT UNSIGNED PK AUTO_INCREMENT,
  nom            VARCHAR(50)  NOT NULL,
  type           ENUM('trimestre','semestre') DEFAULT 'trimestre',
  annee_scolaire VARCHAR(9)   NOT NULL,                    -- ⚠ pas de FK, format libre
  date_debut     DATE NULL,
  date_fin       DATE NULL,
  actif          TINYINT(1)   DEFAULT 1,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(annee_scolaire)
)

-- controles
controles (
  id            INT UNSIGNED PK AUTO_INCREMENT,
  libelle       VARCHAR(100) NOT NULL,
  type          ENUM('controle','devoir','examen','tp','oral') DEFAULT 'controle',
  coefficient   DECIMAL(4,2) DEFAULT 1.00,
  note_max      DECIMAL(5,2) DEFAULT 20.00,
  matiere_id    INT UNSIGNED NOT NULL FK → matieres(id) ON DELETE CASCADE,
  classe_id     INT UNSIGNED NOT NULL FK → classes(id) ON DELETE CASCADE,
  periode_id    INT UNSIGNED NOT NULL FK → periodes(id) ON DELETE CASCADE,
  date_controle DATE NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(classe_id, matiere_id, periode_id)
)

-- notes (SCHEMA V2 — remplace V1 de ecole_app.sql)
-- ⚠ CONFLIT : ecole_app.sql crée notes(eleve_id,matiere_id,trimestre,note,type_note,date_note)
-- ⚠ CONFLIT : academique_migration.sql crée notes(eleve_id,controle_id,note,absent,appreciation)
-- Si les deux sont exécutés dans l'ordre, le IF NOT EXISTS de V2 ne fait RIEN car V1 existe
notes (
  id           INT UNSIGNED PK AUTO_INCREMENT,
  eleve_id     INT UNSIGNED NOT NULL FK → eleves(id) ON DELETE CASCADE,
  controle_id  INT UNSIGNED NOT NULL FK → controles(id) ON DELETE CASCADE,
  note         DECIMAL(5,2) NULL,
  absent       TINYINT(1)   DEFAULT 0,
  appreciation VARCHAR(255) NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE(eleve_id, controle_id)
)

-- moyennes_matieres (table de cache)
moyennes_matieres (
  id         INT UNSIGNED PK AUTO_INCREMENT,
  eleve_id   INT UNSIGNED NOT NULL FK → eleves(id) ON DELETE CASCADE,
  matiere_id INT UNSIGNED NOT NULL FK → matieres(id) ON DELETE CASCADE,
  classe_id  INT UNSIGNED NOT NULL FK → classes(id) ON DELETE CASCADE,
  periode_id INT UNSIGNED NOT NULL FK → periodes(id) ON DELETE CASCADE,
  moyenne    DECIMAL(5,2) NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE(eleve_id, matiere_id, classe_id, periode_id)
)

-- moyennes_generales (table de cache)
moyennes_generales (
  id               INT UNSIGNED PK AUTO_INCREMENT,
  eleve_id         INT UNSIGNED NOT NULL FK → eleves(id) ON DELETE CASCADE,
  classe_id        INT UNSIGNED NOT NULL FK → classes(id) ON DELETE CASCADE,
  periode_id       INT UNSIGNED NOT NULL FK → periodes(id) ON DELETE CASCADE,
  moyenne_generale DECIMAL(5,2) NULL,
  rang             SMALLINT UNSIGNED NULL,
  mention          VARCHAR(30) NULL,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE(eleve_id, classe_id, periode_id)
)
```

#### Groupe ABSENCES

```sql
-- absences (SCHEMA V2 — remplace V1 de ecole_app.sql)
-- ⚠ MÊME CONFLIT QUE notes : IF NOT EXISTS ne remplace pas le schéma V1
absences (
  id            INT UNSIGNED PK AUTO_INCREMENT,
  eleve_id      INT UNSIGNED NOT NULL FK → eleves(id) ON DELETE CASCADE,
  classe_id     INT UNSIGNED NOT NULL FK → classes(id) ON DELETE CASCADE,
  date_absence  DATE NOT NULL,
  session       ENUM('matin','apres_midi','journee') DEFAULT 'journee',
  type          ENUM('absence','retard') DEFAULT 'absence',
  duree_retard  TINYINT UNSIGNED NULL,
  motif         VARCHAR(255) NULL,
  signale_par   INT UNSIGNED NULL FK → users(id) ON DELETE SET NULL,
  statut_justif ENUM('non_justifiee','en_attente','justifiee','refusee') DEFAULT 'non_justifiee',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE(eleve_id, date_absence, session)
)

-- justifications
justifications (
  id                  INT UNSIGNED PK AUTO_INCREMENT,
  absence_id          INT UNSIGNED NOT NULL UNIQUE FK → absences(id) ON DELETE CASCADE,
  soumis_par          INT UNSIGNED NULL FK → users(id) ON DELETE SET NULL,
  motif               TEXT NOT NULL,
  document_path       VARCHAR(255) NULL,
  statut              ENUM('en_attente','acceptee','refusee') DEFAULT 'en_attente',
  commentaire_admin   VARCHAR(255) NULL,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
```

#### Groupe COMPTABILITÉ

```sql
-- frais_types
frais_types (
  id             INT UNSIGNED PK AUTO_INCREMENT,
  nom            VARCHAR(100) NOT NULL,
  description    TEXT NULL,
  montant_defaut DECIMAL(10,2) DEFAULT 0.00,
  periodicite    ENUM('unique','mensuel','trimestriel','annuel') DEFAULT 'annuel',
  actif          TINYINT(1) DEFAULT 1,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)

-- frais_eleves
frais_eleves (
  id             INT UNSIGNED PK AUTO_INCREMENT,
  eleve_id       INT UNSIGNED NOT NULL FK → eleves(id) ON DELETE CASCADE,
  frais_type_id  INT UNSIGNED NOT NULL FK → frais_types(id) ON DELETE CASCADE,
  annee_scolaire VARCHAR(9)   NOT NULL,                    -- ⚠ pas de FK, format libre
  montant        DECIMAL(10,2) NOT NULL,
  echeance       DATE NULL,
  statut         ENUM('en_attente','partiel','paye') DEFAULT 'en_attente',
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE(eleve_id, frais_type_id, annee_scolaire)
)

-- paiements
paiements (
  id             INT UNSIGNED PK AUTO_INCREMENT,
  eleve_id       INT UNSIGNED NOT NULL FK → eleves(id) ON DELETE RESTRICT,
  frais_eleve_id INT UNSIGNED NULL FK → frais_eleves(id) ON DELETE SET NULL,
  annee_scolaire VARCHAR(9)   NOT NULL,                    -- ⚠ pas de FK, format libre
  montant        DECIMAL(10,2) NOT NULL,
  date_paiement  DATE NOT NULL,
  mode_paiement  ENUM('especes','cheque','virement','carte') DEFAULT 'especes',
  reference      VARCHAR(100) NULL,
  note           TEXT NULL,
  encaisse_par   INT UNSIGNED NULL FK → users(id) ON DELETE SET NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)

-- depenses_categories
depenses_categories (
  id      INT UNSIGNED PK AUTO_INCREMENT,
  nom     VARCHAR(100) NOT NULL,
  couleur VARCHAR(7) DEFAULT '#6c757d'
)

-- depenses
depenses (
  id           INT UNSIGNED PK AUTO_INCREMENT,
  categorie_id INT UNSIGNED NULL FK → depenses_categories(id) ON DELETE SET NULL,
  libelle      VARCHAR(255) NOT NULL,
  montant      DECIMAL(10,2) NOT NULL,
  date_depense DATE NOT NULL,
  mode_paiement ENUM('especes','cheque','virement','carte') DEFAULT 'especes',
  reference    VARCHAR(100) NULL,
  note         TEXT NULL,
  saisi_par    INT UNSIGNED NULL FK → users(id) ON DELETE SET NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
```

#### Groupe EMPLOI DU TEMPS

```sql
-- salles
salles (
  id          INT UNSIGNED PK AUTO_INCREMENT,
  nom         VARCHAR(100) NOT NULL,
  capacite    SMALLINT UNSIGNED DEFAULT 0,
  type        ENUM('salle_cours','laboratoire','salle_info','gymnase','amphitheatre','autre') DEFAULT 'salle_cours',
  batiment    VARCHAR(50) NULL,
  description TEXT NULL,
  actif       TINYINT(1) DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)

-- creneaux
creneaux (
  id          INT UNSIGNED PK AUTO_INCREMENT,
  nom         VARCHAR(50) NOT NULL,
  heure_debut TIME NOT NULL,
  heure_fin   TIME NOT NULL,
  type        ENUM('cours','pause','recreation','priere') DEFAULT 'cours',
  ordre       TINYINT UNSIGNED DEFAULT 0,
  actif       TINYINT(1) DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)

-- emplois_du_temps
emplois_du_temps (
  id             INT UNSIGNED PK AUTO_INCREMENT,
  classe_id      INT UNSIGNED NOT NULL FK → classes(id) ON DELETE CASCADE,
  matiere_id     INT UNSIGNED NOT NULL FK → matieres(id) ON DELETE CASCADE,
  professeur_id  INT UNSIGNED NOT NULL FK → professeurs(id) ON DELETE CASCADE,
  salle_id       INT NULL FK → salles(id) ON DELETE SET NULL,              -- ⚠ INT pas UNSIGNED
  creneau_id     INT NOT NULL FK → creneaux(id) ON DELETE CASCADE,         -- ⚠ INT pas UNSIGNED
  jour_semaine   TINYINT NOT NULL COMMENT '1=Lundi..6=Samedi',
  annee_scolaire VARCHAR(9) NOT NULL,                                       -- ⚠ pas de FK
  date_debut     DATE NULL,
  date_fin       DATE NULL,
  couleur        VARCHAR(7) NULL,
  notes          TEXT NULL,
  actif          TINYINT(1) DEFAULT 1,
  created_by     INT NULL,                                                  -- ⚠ INT, pas de FK
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE(classe_id, creneau_id, jour_semaine, annee_scolaire),
  UNIQUE(professeur_id, creneau_id, jour_semaine, annee_scolaire)
)
```

#### Groupe NOTIFICATIONS & ANNONCES

```sql
-- notifications  ⚠ AUCUNE FK sur user_id
notifications (
  id         INT UNSIGNED PK AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,                        -- ⚠ pas de FK → users
  type       ENUM('note','absence','paiement','annonce','info') DEFAULT 'info',
  titre      VARCHAR(255) NOT NULL,
  message    TEXT NULL,
  lien       VARCHAR(500) DEFAULT '',
  lu         TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(user_id, lu)
)

-- annonces  ⚠ AUCUNE FK sur publie_par
annonces (
  id           INT UNSIGNED PK AUTO_INCREMENT,
  titre        VARCHAR(255) NOT NULL,
  contenu      TEXT NOT NULL,
  audience     ENUM('tous','parents','eleves','enseignants') DEFAULT 'tous',
  publie_par   INT UNSIGNED NULL,                         -- ⚠ pas de FK → users
  published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  actif        TINYINT(1) DEFAULT 1,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)

-- notification_preferences
notification_preferences (
  id            INT UNSIGNED PK AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,                    -- ⚠ pas de FK → users
  trigger_type  ENUM('note','absence','paiement','annonce') NOT NULL,
  canal_interne TINYINT(1) DEFAULT 1,
  canal_email   TINYINT(1) DEFAULT 0,
  canal_sms     TINYINT(1) DEFAULT 0,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE(user_id, trigger_type)
)

-- notification_logs  ⚠ AUCUNE FK sur user_id
notification_logs (
  id           INT UNSIGNED PK AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,                     -- ⚠ pas de FK → users
  trigger_type VARCHAR(50) NOT NULL,
  canal        ENUM('interne','email','sms') NOT NULL,
  titre        VARCHAR(255) NOT NULL,
  message      TEXT NULL,
  destinataire VARCHAR(255) NULL,
  statut       ENUM('envoye','echoue','en_attente') DEFAULT 'envoye',
  erreur       TEXT NULL,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
)

-- push_subscriptions  ⚠ NON MIGRÉE — créée dynamiquement par PHP
push_subscriptions (
  id           INT UNSIGNED PK AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,                     -- ⚠ pas de FK → users
  endpoint     TEXT NOT NULL,
  p256dh       VARCHAR(255) DEFAULT '',
  auth         VARCHAR(255) DEFAULT '',
  user_agent   VARCHAR(255) DEFAULT '',
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  INDEX(user_id),
  INDEX(endpoint(200))
)
```

### 1.3 Résumé — Inventaire V1

| # | Table | Groupe | Colonnes clés | FKs | Problèmes |
|---|---|---|---|---|---|
| 1 | `users` | Auth | 14 colonnes | — | reset_token doublon |
| 2 | `password_resets` | Auth | 6 colonnes | — | OK |
| 3 | `permissions` | Auth | 4 colonnes | — | Incomplète vs config/ |
| 4 | `role_permissions` | Auth | 2 colonnes | permissions | Incomplète vs config/ |
| 5 | `classes` | Scolarité | 6 colonnes | — | annee_scolaire VARCHAR libre |
| 6 | `eleves` | Scolarité | 14 colonnes | classes, users | Pas de user_id élève |
| 7 | `matieres` | Scolarité | 7 colonnes | professeurs | responsable_id couplage fort |
| 8 | `professeurs` | Scolarité | 12 colonnes | users | Doublon nom/email/photo |
| 9 | `enseignements` | Scolarité | 5 colonnes | professeurs, matieres, classes | annee_scolaire VARCHAR |
| 10 | `periodes` | Académique | 8 colonnes | — | annee_scolaire VARCHAR |
| 11 | `controles` | Académique | 9 colonnes | matieres, classes, periodes | OK |
| 12 | `notes` | Académique | 7 colonnes | eleves, controles | **SCHEMA EN CONFLIT V1/V2** |
| 13 | `moyennes_matieres` | Académique (cache) | 6 colonnes | eleves, matieres, classes, periodes | OK |
| 14 | `moyennes_generales` | Académique (cache) | 7 colonnes | eleves, classes, periodes | OK |
| 15 | `absences` | Absences | 11 colonnes | eleves, classes, users | **SCHEMA EN CONFLIT V1/V2** |
| 16 | `justifications` | Absences | 8 colonnes | absences, users | OK |
| 17 | `frais_types` | Comptabilité | 7 colonnes | — | OK |
| 18 | `frais_eleves` | Comptabilité | 8 colonnes | eleves, frais_types | annee_scolaire VARCHAR |
| 19 | `paiements` | Comptabilité | 11 colonnes | eleves, frais_eleves, users | annee_scolaire VARCHAR |
| 20 | `depenses_categories` | Comptabilité | 3 colonnes | — | OK |
| 21 | `depenses` | Comptabilité | 10 colonnes | depenses_categories, users | OK |
| 22 | `salles` | EDT | 8 colonnes | — | OK |
| 23 | `creneaux` | EDT | 7 colonnes | — | OK |
| 24 | `emplois_du_temps` | EDT | 15 colonnes | classes, matieres, professeurs, salles, creneaux | Types INT incohérents, created_by sans FK |
| 25 | `notifications` | Notifs | 8 colonnes | **AUCUNE** | Pas de FK sur user_id |
| 26 | `annonces` | Notifs | 8 colonnes | **AUCUNE** | Pas de FK sur publie_par |
| 27 | `notification_preferences` | Notifs | 7 colonnes | **AUCUNE** | Pas de FK sur user_id |
| 28 | `notification_logs` | Notifs | 10 colonnes | **AUCUNE** | Pas de FK sur user_id |
| 29 | `push_subscriptions` | Notifs | 8 colonnes | **AUCUNE** | **Non migrée, créée par PHP** |

**Total : 29 tables, dont 2 en conflit de schéma, 5 sans FK sur user_id, 6 avec annee_scolaire VARCHAR non contrôlé.**

---

## 2. PROBLÈMES IDENTIFIÉS

### DB-001 — CRITIQUE : Schéma `notes` en double (V1/V2 incompatibles)

**Impact :** BLOQUANT. Si les deux migrations sont exécutées dans l'ordre, le comportement est indéterminé.

```
V1 (ecole_app.sql) :
  notes(id, eleve_id, matiere_id, trimestre, note, type_note, date_note, observation)
  → Référence directe matière + trimestre (1, 2, 3)

V2 (academique_migration.sql) :
  notes(id, eleve_id, controle_id, note, absent, appreciation)
  → Référence au contrôle intermédiaire (calcul pondéré par coefficient)

Le CREATE TABLE IF NOT EXISTS de V2 ne fait RIEN si la table V1 existe déjà.
Résultat : le code PHP (NoteModel) utilise la structure V2 mais la table V1 peut être en place.
```

**Correction V2 :** Utiliser `CREATE TABLE IF NOT EXISTS` + `DROP/RECREATE` ou ALTER pour aligner.

---

### DB-002 — CRITIQUE : Schéma `absences` en double (V1/V2 incompatibles)

**Impact :** BLOQUANT. Même problème que DB-001.

```
V1 (ecole_app.sql) :
  absences(id, eleve_id, date_absence, justifiee, motif, nb_heures)
  → Structure basique sans classe_id, session, statut

V2 (absences_migration.sql) :
  absences(id, eleve_id, classe_id, date_absence, session, type, duree_retard,
           motif, signale_par, statut_justif)
  → Structure complète avec FK classe, enum session, enum statut

Le CREATE TABLE IF NOT EXISTS de V2 ne fait RIEN si V1 existe déjà.
```

**Correction V2 :** Migration explicite avec DROP + CREATE ou ALTER TABLE.

---

### DB-003 — MAJEUR : Absence de référentiel `annees_scolaires`

**Impact :** Incohérence des données inter-tables, impossible de filtrer "l'année courante" côté DB.

```
annee_scolaire VARCHAR(9) présent dans 6 tables :
  classes.annee_scolaire
  enseignements.annee_scolaire
  periodes.annee_scolaire
  frais_eleves.annee_scolaire
  paiements.annee_scolaire
  emplois_du_temps.annee_scolaire

Problèmes :
  - Format libre : '2024-2025', '2024/2025', '2025-26' sont tous valides
  - Pas de contrainte FK : une valeur orpheline est silencieuse
  - Pas de notion d'année "active" en DB
  - Cohérence inter-tables garantie uniquement par le code PHP
  - Passage de '2024-2025' à '2025-2026' n'est pas atomique
```

**Correction V2 :** Créer `annees_scolaires` et remplacer `VARCHAR` par `annee_id FK`.

---

### DB-004 — MAJEUR : Double mécanisme de reset password

**Impact :** Confusion maintenabilité, colonnes inutiles sur `users`.

```
Mécanisme 1 (colonnes sur users — INUTILISÉ par le code) :
  users.reset_token         VARCHAR(100) NULL
  users.reset_token_expires DATETIME     NULL

Mécanisme 2 (table dédiée — UTILISÉ par UserModel) :
  password_resets(id, email, token, expires_at, used)

UserModel::createResetToken() INSERT dans password_resets.
UserModel::findByResetToken() SELECT dans password_resets.
Les colonnes reset_token et reset_token_expires sur users ne sont jamais lues.
```

**Correction V2 :** Supprimer `users.reset_token` et `users.reset_token_expires`.

---

### DB-005 — MAJEUR : Élève sans lien vers son compte utilisateur

**Impact :** Impossible de faire le lien DB entre `users.role='eleve'` et `eleves.id`.

```
eleves.parent_id → users (FK pour le parent) : EXISTE ✓
eleves.user_id   → users (FK pour l'élève)   : ABSENT  ✗

L'espace élève (EspaceEleveController) doit retrouver l'enregistrement eleves
correspondant à l'utilisateur connecté. Sans FK, cette jointure se fait
probablement par email (users.email = eleves.email) — fragile et non indexé.
```

**Correction V2 :** Ajouter `eleves.user_id INT UNSIGNED NULL FK → users(id)`.

---

### DB-006 — MODÉRÉ : Redondance `professeurs` ↔ `users`

**Impact :** Désynchronisation silencieuse des données personnelles.

```
Si professeurs.user_id est renseigné, les colonnes suivantes sont en doublon :
  professeurs.nom      = users.nom
  professeurs.prenom   = users.prenom
  professeurs.telephone = users.telephone
  professeurs.email    = users.email    (email principal)
  professeurs.photo    = users.photo

Un changement de numéro de téléphone dans le profil utilisateur
ne met pas à jour professeurs.telephone — deux vérités divergent.
```

**Correction V2 :** Pour les professeurs liés à un user, les colonnes `nom/prenom/telephone/email/photo` deviennent des overrides optionnels (NULL = utiliser la valeur du user). Documenter clairement la hiérarchie de lecture.

---

### DB-007 — MODÉRÉ : Types incohérents dans `emplois_du_temps`

**Impact :** Risque d'erreur FK silencieuse selon la config MySQL.

```
salles.id      : INT UNSIGNED AUTO_INCREMENT
creneaux.id    : INT UNSIGNED AUTO_INCREMENT
users.id       : INT UNSIGNED AUTO_INCREMENT

emplois_du_temps.salle_id   : INT (signé, nullable) → devrait être INT UNSIGNED
emplois_du_temps.creneau_id : INT (signé, NOT NULL)  → devrait être INT UNSIGNED
emplois_du_temps.created_by : INT (signé, nullable)  → pas de FK vers users, devrait être INT UNSIGNED
```

---

### DB-008 — MODÉRÉ : 5 tables sans FK sur `user_id`

**Impact :** Données orphelines possibles si un utilisateur est supprimé.

```
notifications.user_id            → pas de FK → users
notification_preferences.user_id → pas de FK → users
notification_logs.user_id        → pas de FK → users
push_subscriptions.user_id       → pas de FK → users
annonces.publie_par              → pas de FK → users
```

---

### DB-009 — MODÉRÉ : `permissions` en DB incomplète vs `config/permissions.php`

**Impact :** Incohérence entre la source de vérité (config/permissions.php) et la DB.

```
Permissions en DB (auth_migration + classes_matieres_migration) : 27 codes
  eleves.*, enseignants.*, classes.*, notes.*, absences.*, users.*, rapports.view, matieres.*

Permissions dans config/permissions.php (SOURCE DE VÉRITÉ réelle) : ~50 codes
  Supplémentaires : bulletins.*, comptabilite.*, emploi_du_temps.*, annonces.*,
                    absences.justify, notes.view_own, paiements.*, etc.

La table role_permissions en DB n'est jamais consultée par le code.
config/permissions.php est chargé à la connexion dans $_SESSION.
La table DB est donc une documentation partielle et non une source opérationnelle.
```

---

### DB-010 — FAIBLE : `push_subscriptions` créée dynamiquement par PHP

**Impact :** Table absente des migrations, invisible pour les outils de versioning.

```
PushSubscriptionModel::ensureTableExists() crée la table à la première instanciation.
Pas de migration SQL correspondante. Pas de rollback possible.
Ordre de création non contrôlé.
```

---

### DB-011 — FAIBLE : Pas de versioning des migrations

**Impact :** Impossible de savoir quelles migrations ont été appliquées.

```
11 fichiers SQL avec dépendances documentées en commentaires textuels.
Pas de table schema_migrations ou équivalent.
Pas de mécanisme de rollback.
Sur un nouvel environnement, l'ordre d'application est laissé à l'administrateur.
```

---

### DB-012 — FAIBLE : `matieres.responsable_id` — couplage implicite

**Impact :** Confusion entre "professeur responsable de la matière" et "professeur qui enseigne".

```
matieres.responsable_id → professeurs (responsable global de la matière)
enseignements(professeur_id, matiere_id, classe_id) → qui enseigne quoi, où, quand

Un professeur peut enseigner une matière sans en être le responsable.
Un responsable peut ne plus enseigner la matière.
La sémantique n'est pas documentée dans le schéma.
```

---

## 3. MODÈLE CIBLE V2

### 3.1 Diagramme de relations V2 (simplifié)

```
annees_scolaires ──────────────────────────────────────────────────────────────────┐
         │                                                                          │
         ├──→ classes.annee_id                                                      │
         ├──→ enseignements.annee_id                                                │
         ├──→ periodes.annee_id                                                     │
         ├──→ frais_eleves.annee_id                                                 │
         ├──→ paiements.annee_id                                                    │
         └──→ emplois_du_temps.annee_id                                             │
                                                                                    │
users ─────────────────────────────────────────────────────────────────────────┐   │
  │   └──→ password_resets.email                                               │   │
  │                                                                            │   │
  ├──→ professeurs.user_id                                                     │   │
  ├──→ eleves.parent_id                                                        │   │
  ├──→ eleves.user_id           [NOUVEAU]                                      │   │
  ├──→ absences.signale_par                                                    │   │
  ├──→ justifications.soumis_par                                               │   │
  ├──→ paiements.encaisse_par                                                  │   │
  ├──→ depenses.saisi_par                                                      │   │
  ├──→ notifications.user_id    [FK AJOUTÉE]                                   │   │
  ├──→ notification_preferences.user_id [FK AJOUTÉE]                           │   │
  ├──→ notification_logs.user_id [FK AJOUTÉE]                                  │   │
  ├──→ push_subscriptions.user_id [FK AJOUTÉE]                                 │   │
  ├──→ annonces.publie_par      [FK AJOUTÉE]                                   │   │
  └──→ emplois_du_temps.created_by [FK AJOUTÉE + type fixé]                   │   │
                                                                               │   │
classes ──────────────────────────────────────────────────────────────────┐   │   │
  │  annee_id → annees_scolaires ────────────────────────────────────────────→→→──┘
  │                                                                         │   │
  ├──→ eleves.classe_id                                                      │   │
  ├──→ enseignements.classe_id                                               │   │
  ├──→ controles.classe_id                                                   │   │
  ├──→ absences.classe_id                                                    │   │
  ├──→ moyennes_matieres.classe_id                                           │   │
  ├──→ moyennes_generales.classe_id                                          │   │
  └──→ emplois_du_temps.classe_id                                            │   │
                                                                             │   │
eleves                                                                       │   │
  ├──→ classe_id → classes                                                   │   │
  ├──→ parent_id → users ──────────────────────────────────────────────────────→─┘
  ├──→ user_id   → users [NOUVEAU]                                           │
  ├──→ notes.eleve_id                                                         │
  ├──→ absences.eleve_id                                                      │
  ├──→ frais_eleves.eleve_id                                                  │
  ├──→ paiements.eleve_id                                                     │
  ├──→ moyennes_matieres.eleve_id                                             │
  └──→ moyennes_generales.eleve_id                                            │
                                                                              │
professeurs                                                                   │
  ├──→ user_id → users ─────────────────────────────────────────────────────→┘
  ├──→ enseignements.professeur_id
  └──→ emplois_du_temps.professeur_id

matieres
  ├──→ coefficient, volume_horaire
  ├──→ responsable_id → professeurs [maintenu, documenté]
  ├──→ enseignements.matiere_id
  ├──→ controles.matiere_id
  ├──→ moyennes_matieres.matiere_id
  └──→ emplois_du_temps.matiere_id

periodes
  ├──→ annee_id → annees_scolaires [REMPLACE annee_scolaire VARCHAR]
  └──→ controles.periode_id

controles → notes
moyennes_matieres / moyennes_generales : tables de cache (inchangées)

absences → justifications
frais_types → frais_eleves → paiements
depenses_categories → depenses
salles + creneaux → emplois_du_temps
```

### 3.2 Tableau des modifications par table

| Table | Action | Modifications |
|---|---|---|
| `users` | MODIFIER | Supprimer `reset_token`, `reset_token_expires` |
| `eleves` | MODIFIER | Ajouter `user_id FK → users` |
| `classes` | MODIFIER | Ajouter `annee_id FK → annees_scolaires` + garder `annee_scolaire` en transition |
| `enseignements` | MODIFIER | Ajouter `annee_id FK → annees_scolaires` |
| `periodes` | MODIFIER | Ajouter `annee_id FK → annees_scolaires` |
| `frais_eleves` | MODIFIER | Ajouter `annee_id FK → annees_scolaires` |
| `paiements` | MODIFIER | Ajouter `annee_id FK → annees_scolaires` |
| `emplois_du_temps` | MODIFIER | Ajouter `annee_id FK`, fixer types `salle_id`/`creneau_id`/`created_by` |
| `notes` | RÉSOUDRE CONFLIT | Migration explicite vers schéma V2 |
| `absences` | RÉSOUDRE CONFLIT | Migration explicite vers schéma V2 |
| `notifications` | MODIFIER | Ajouter FK `user_id → users` |
| `notification_preferences` | MODIFIER | Ajouter FK `user_id → users` |
| `notification_logs` | MODIFIER | Ajouter FK `user_id → users` |
| `push_subscriptions` | FORMALISER | Ajouter migration SQL + FK `user_id → users` |
| `annonces` | MODIFIER | Ajouter FK `publie_par → users` |
| `permissions` | COMPLÉTER | Ajouter les permissions manquantes |
| `role_permissions` | COMPLÉTER | Ajouter affectations manquantes |
| `annees_scolaires` | **CRÉER** | Nouvelle table référentiel |
| `schema_migrations` | **CRÉER** | Table de versioning des migrations |

---

## 4. NOUVELLES TABLES

### 4.1 `annees_scolaires` — Référentiel des années scolaires

```sql
CREATE TABLE `annees_scolaires` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `libelle`     VARCHAR(9)    NOT NULL UNIQUE  COMMENT 'Ex: 2025-2026',
    `date_debut`  DATE          NOT NULL,
    `date_fin`    DATE          NOT NULL,
    `actif`       TINYINT(1)    NOT NULL DEFAULT 0
                  COMMENT '1 = année scolaire en cours (une seule à la fois)',
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Garantit une seule année active (via application, pas contrainte unique possible)
    INDEX `idx_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Règle métier :** Une seule ligne peut avoir `actif = 1`. Gérée par l'application (UPDATE SET actif=0 WHERE 1 avant SET actif=1).

---

### 4.2 `schema_migrations` — Versioning des migrations

```sql
CREATE TABLE `schema_migrations` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `version`     VARCHAR(100)  NOT NULL UNIQUE  COMMENT 'Ex: 2026_06_29_001_annees_scolaires',
    `description` VARCHAR(255)  NOT NULL,
    `executed_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. SCRIPTS DE MIGRATION SQL

> **ORDRE D'EXÉCUTION OBLIGATOIRE**
> Exécuter dans l'ordre numérique. Chaque script est idempotent où possible.
> Tester sur une copie de la base avant tout.

---

### M001 — Création du système de versioning

```sql
-- ═══════════════════════════════════════════════════════════════════════════
-- M001 : Création de schema_migrations
-- Prérequis : ecole_app.sql appliqué
-- ═══════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `version`     VARCHAR(100)  NOT NULL UNIQUE,
    `description` VARCHAR(255)  NOT NULL,
    `executed_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('M001', 'Création de la table schema_migrations');
```

---

### M002 — Référentiel années scolaires

```sql
-- ═══════════════════════════════════════════════════════════════════════════
-- M002 : Création de annees_scolaires + peuplement initial
-- Prérequis : M001
-- ═══════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

CREATE TABLE IF NOT EXISTS `annees_scolaires` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `libelle`    VARCHAR(9)   NOT NULL UNIQUE COMMENT 'Format : YYYY-YYYY',
    `date_debut` DATE         NOT NULL,
    `date_fin`   DATE         NOT NULL,
    `actif`      TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données initiales basées sur les données existantes dans la base
INSERT IGNORE INTO `annees_scolaires` (`libelle`, `date_debut`, `date_fin`, `actif`) VALUES
('2024-2025', '2024-09-15', '2025-06-30', 0),
('2025-2026', '2025-09-15', '2026-06-30', 1);  -- année active

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('M002', 'Création du référentiel annees_scolaires');
```

---

### M003 — Ajout `annee_id` sur toutes les tables concernées

```sql
-- ═══════════════════════════════════════════════════════════════════════════
-- M003 : Ajout colonne annee_id (FK) dans 6 tables + population depuis libelle
-- Stratégie : ajouter annee_id nullable, peupler depuis annee_scolaire VARCHAR,
--             puis rendre NOT NULL. Conserver annee_scolaire VARCHAR pour compatibilité
--             V1 pendant la migration (supprimé en M009).
-- Prérequis : M002
-- ═══════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

-- ── classes ─────────────────────────────────────────────────────────────────
ALTER TABLE `classes`
    ADD COLUMN `annee_id` INT UNSIGNED NULL AFTER `annee_scolaire`;

UPDATE `classes` c
    JOIN `annees_scolaires` a ON a.`libelle` = c.`annee_scolaire`
    SET c.`annee_id` = a.`id`;

ALTER TABLE `classes`
    MODIFY COLUMN `annee_id` INT UNSIGNED NOT NULL,
    ADD CONSTRAINT `fk_classes_annee`
        FOREIGN KEY (`annee_id`) REFERENCES `annees_scolaires`(`id`) ON DELETE RESTRICT;

-- ── enseignements ────────────────────────────────────────────────────────────
ALTER TABLE `enseignements`
    ADD COLUMN `annee_id` INT UNSIGNED NULL AFTER `annee_scolaire`;

UPDATE `enseignements` e
    JOIN `annees_scolaires` a ON a.`libelle` = e.`annee_scolaire`
    SET e.`annee_id` = a.`id`;

ALTER TABLE `enseignements`
    MODIFY COLUMN `annee_id` INT UNSIGNED NOT NULL,
    ADD CONSTRAINT `fk_ens_annee`
        FOREIGN KEY (`annee_id`) REFERENCES `annees_scolaires`(`id`) ON DELETE RESTRICT;

-- Mettre à jour l'UNIQUE KEY pour inclure annee_id à la place de annee_scolaire
-- (ne pas supprimer l'ancienne avant la fin de migration V1)
ALTER TABLE `enseignements`
    ADD UNIQUE KEY `uq_ens_v2` (`professeur_id`, `matiere_id`, `classe_id`, `annee_id`);

-- ── periodes ─────────────────────────────────────────────────────────────────
ALTER TABLE `periodes`
    ADD COLUMN `annee_id` INT UNSIGNED NULL AFTER `annee_scolaire`;

UPDATE `periodes` p
    JOIN `annees_scolaires` a ON a.`libelle` = p.`annee_scolaire`
    SET p.`annee_id` = a.`id`;

ALTER TABLE `periodes`
    MODIFY COLUMN `annee_id` INT UNSIGNED NOT NULL,
    ADD CONSTRAINT `fk_periodes_annee`
        FOREIGN KEY (`annee_id`) REFERENCES `annees_scolaires`(`id`) ON DELETE RESTRICT;

-- ── frais_eleves ─────────────────────────────────────────────────────────────
ALTER TABLE `frais_eleves`
    ADD COLUMN `annee_id` INT UNSIGNED NULL AFTER `annee_scolaire`;

UPDATE `frais_eleves` fe
    JOIN `annees_scolaires` a ON a.`libelle` = fe.`annee_scolaire`
    SET fe.`annee_id` = a.`id`;

ALTER TABLE `frais_eleves`
    MODIFY COLUMN `annee_id` INT UNSIGNED NOT NULL,
    ADD CONSTRAINT `fk_fe_annee`
        FOREIGN KEY (`annee_id`) REFERENCES `annees_scolaires`(`id`) ON DELETE RESTRICT;

ALTER TABLE `frais_eleves`
    ADD UNIQUE KEY `uq_frais_v2` (`eleve_id`, `frais_type_id`, `annee_id`);

-- ── paiements ────────────────────────────────────────────────────────────────
ALTER TABLE `paiements`
    ADD COLUMN `annee_id` INT UNSIGNED NULL AFTER `annee_scolaire`;

UPDATE `paiements` p
    JOIN `annees_scolaires` a ON a.`libelle` = p.`annee_scolaire`
    SET p.`annee_id` = a.`id`;

ALTER TABLE `paiements`
    MODIFY COLUMN `annee_id` INT UNSIGNED NOT NULL,
    ADD CONSTRAINT `fk_paiements_annee`
        FOREIGN KEY (`annee_id`) REFERENCES `annees_scolaires`(`id`) ON DELETE RESTRICT;

-- ── emplois_du_temps ─────────────────────────────────────────────────────────
ALTER TABLE `emplois_du_temps`
    ADD COLUMN `annee_id` INT UNSIGNED NULL AFTER `annee_scolaire`;

UPDATE `emplois_du_temps` edt
    JOIN `annees_scolaires` a ON a.`libelle` = edt.`annee_scolaire`
    SET edt.`annee_id` = a.`id`;

ALTER TABLE `emplois_du_temps`
    MODIFY COLUMN `annee_id` INT UNSIGNED NOT NULL,
    ADD CONSTRAINT `fk_edt_annee`
        FOREIGN KEY (`annee_id`) REFERENCES `annees_scolaires`(`id`) ON DELETE RESTRICT;

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('M003', 'Ajout annee_id FK dans classes, enseignements, periodes, frais_eleves, paiements, emplois_du_temps');
```

---

### M004 — Résoudre le conflit `notes` (V1 → V2)

```sql
-- ═══════════════════════════════════════════════════════════════════════════
-- M004 : Résoudre le conflit de schéma sur la table notes
-- ATTENTION : À n'exécuter que si la table notes est en schéma V1.
-- Détecter le schéma actuel AVANT d'exécuter :
--   SHOW COLUMNS FROM notes;
-- Si 'controle_id' existe → schéma V2 déjà en place, sauter M004.
-- Si 'matiere_id' existe  → schéma V1 détecté, exécuter M004.
-- Prérequis : M001, M002, M003 + BACKUP de la table notes V1
-- ═══════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

-- 1. Sauvegarder les données V1 (si migration de données nécessaire)
CREATE TABLE IF NOT EXISTS `_backup_notes_v1` AS SELECT * FROM `notes`;

-- 2. Supprimer l'ancienne table V1
DROP TABLE IF EXISTS `notes`;

-- 3. Créer la table notes V2 (schéma correct)
CREATE TABLE `notes` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `eleve_id`     INT UNSIGNED  NOT NULL,
    `controle_id`  INT UNSIGNED  NOT NULL,
    `note`         DECIMAL(5,2)  NULL     COMMENT 'NULL = non encore saisie',
    `absent`       TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 = absent (compte comme 0)',
    `appreciation` VARCHAR(255)  NULL,
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_note` (`eleve_id`, `controle_id`),
    KEY `idx_note_eleve` (`eleve_id`),
    KEY `idx_note_controle` (`controle_id`),
    CONSTRAINT `fk_note_eleve`    FOREIGN KEY (`eleve_id`)    REFERENCES `eleves`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_note_controle` FOREIGN KEY (`controle_id`) REFERENCES `controles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTE : Les données V1 (notes par matière + trimestre) ne peuvent pas être
-- migrées automatiquement vers V2 (qui référence des controles spécifiques).
-- Le mapping nécessite une décision métier (créer des controles fictifs ou purger).
-- La table _backup_notes_v1 est conservée pour consultation.

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('M004', 'Résolution conflit schéma notes V1→V2 (backup dans _backup_notes_v1)');
```

---

### M005 — Résoudre le conflit `absences` (V1 → V2)

```sql
-- ═══════════════════════════════════════════════════════════════════════════
-- M005 : Résoudre le conflit de schéma sur la table absences
-- ATTENTION : Détecter AVANT d'exécuter :
--   SHOW COLUMNS FROM absences;
-- Si 'statut_justif' existe → schéma V2, sauter M005.
-- Si 'nb_heures' existe     → schéma V1, exécuter M005.
-- Prérequis : M001
-- ═══════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

-- 1. Sauvegarder les données V1
CREATE TABLE IF NOT EXISTS `_backup_absences_v1` AS SELECT * FROM `absences`;

-- 2. Supprimer ancienne table V1 (supprime aussi les justifications si FK)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `absences`;
SET FOREIGN_KEY_CHECKS = 1;

-- 3. Créer la table absences V2 (schéma correct)
CREATE TABLE `absences` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `eleve_id`      INT UNSIGNED  NOT NULL,
    `classe_id`     INT UNSIGNED  NOT NULL,
    `date_absence`  DATE          NOT NULL,
    `session`       ENUM('matin','apres_midi','journee') NOT NULL DEFAULT 'journee',
    `type`          ENUM('absence','retard')             NOT NULL DEFAULT 'absence',
    `duree_retard`  TINYINT UNSIGNED NULL COMMENT 'Durée en minutes (si type=retard)',
    `motif`         VARCHAR(255)  NULL,
    `signale_par`   INT UNSIGNED  NULL COMMENT 'users.id de l\'auteur du pointage',
    `statut_justif` ENUM('non_justifiee','en_attente','justifiee','refusee')
                    NOT NULL DEFAULT 'non_justifiee',
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_absence`       (`eleve_id`, `date_absence`, `session`),
    INDEX         `idx_abs_date`  (`date_absence`),
    INDEX         `idx_abs_classe`(`classe_id`, `date_absence`),
    INDEX         `idx_abs_statut`(`statut_justif`),
    CONSTRAINT `fk_abs_eleve`  FOREIGN KEY (`eleve_id`)    REFERENCES `eleves`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_abs_classe` FOREIGN KEY (`classe_id`)   REFERENCES `classes`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_abs_user`   FOREIGN KEY (`signale_par`) REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration des données V1 vers V2 (correspondance approximative)
-- Les absences V1 n'ont pas de classe_id → utiliser classe_id de l'élève
INSERT IGNORE INTO `absences`
    (`eleve_id`, `classe_id`, `date_absence`, `session`, `type`,
     `motif`, `statut_justif`, `created_at`)
SELECT
    a.eleve_id,
    COALESCE(e.classe_id, 1) AS classe_id,
    a.date_absence,
    'journee' AS session,
    'absence'  AS type,
    a.motif,
    CASE WHEN a.justifiee = 1 THEN 'justifiee' ELSE 'non_justifiee' END AS statut_justif,
    a.created_at
FROM `_backup_absences_v1` a
LEFT JOIN `eleves` e ON e.id = a.eleve_id;

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('M005', 'Résolution conflit schéma absences V1→V2 avec migration de données');
```

---

### M006 — Nettoyage `users` (supprimer colonnes reset_token inutiles)

```sql
-- ═══════════════════════════════════════════════════════════════════════════
-- M006 : Suppression users.reset_token + users.reset_token_expires
-- La table password_resets est le mécanisme officiel (UserModel l'utilise).
-- Prérequis : M001
-- ═══════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

-- Vérifier que la colonne existe avant de la supprimer (idempotent)
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'ecole_app'
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'reset_token'
);

-- Exécuter uniquement si les colonnes existent
-- (MySQL 8.0 supporte IF EXISTS sur ALTER TABLE DROP COLUMN)
ALTER TABLE `users`
    DROP COLUMN IF EXISTS `reset_token`,
    DROP COLUMN IF EXISTS `reset_token_expires`;

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('M006', 'Suppression des colonnes reset_token/reset_token_expires sur users (remplacées par password_resets)');
```

---

### M007 — Ajout `eleves.user_id` (lien compte utilisateur élève)

```sql
-- ═══════════════════════════════════════════════════════════════════════════
-- M007 : Ajout eleves.user_id → users (compte de l'élève)
-- Prérequis : M001
-- ═══════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

ALTER TABLE `eleves`
    ADD COLUMN `user_id` INT UNSIGNED NULL
        COMMENT 'Compte utilisateur de l\'élève (users.role=eleve)'
        AFTER `parent_id`,
    ADD INDEX `idx_eleve_user` (`user_id`),
    ADD CONSTRAINT `fk_eleve_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Tentative de liaison automatique par email (best-effort)
UPDATE `eleves` el
    JOIN `users` u ON u.email = el.email AND u.role = 'eleve' AND u.actif = 1
    SET el.user_id = u.id
    WHERE el.user_id IS NULL;

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('M007', 'Ajout eleves.user_id FK vers users pour le compte de l\'élève');
```

---

### M008 — Correction types et FK manquantes dans `emplois_du_temps`

```sql
-- ═══════════════════════════════════════════════════════════════════════════
-- M008 : Correction des types incohérents et FK manquantes dans emplois_du_temps
-- + Ajout FK manquantes sur notifications, annonces, push_subscriptions
-- Prérequis : M001
-- ═══════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

-- ── emplois_du_temps : correction types ──────────────────────────────────────
-- Supprimer les FK existantes avant modification de type
ALTER TABLE `emplois_du_temps`
    DROP FOREIGN KEY IF EXISTS `fk_edt_salle`,
    DROP FOREIGN KEY IF EXISTS `fk_edt_creneau`;

ALTER TABLE `emplois_du_temps`
    MODIFY COLUMN `salle_id`   INT UNSIGNED NULL,
    MODIFY COLUMN `creneau_id` INT UNSIGNED NOT NULL,
    MODIFY COLUMN `created_by` INT UNSIGNED NULL;

-- Recréer les FK avec types corrects
ALTER TABLE `emplois_du_temps`
    ADD CONSTRAINT `fk_edt_salle_v2`
        FOREIGN KEY (`salle_id`) REFERENCES `salles`(`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_edt_creneau_v2`
        FOREIGN KEY (`creneau_id`) REFERENCES `creneaux`(`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_edt_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- ── notifications : ajout FK user_id ─────────────────────────────────────────
ALTER TABLE `notifications`
    MODIFY COLUMN `user_id` INT UNSIGNED NOT NULL,
    ADD CONSTRAINT `fk_notif_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

-- ── notification_preferences : ajout FK user_id ──────────────────────────────
ALTER TABLE `notification_preferences`
    MODIFY COLUMN `user_id` INT UNSIGNED NOT NULL,
    ADD CONSTRAINT `fk_np_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

-- ── notification_logs : ajout FK user_id ─────────────────────────────────────
ALTER TABLE `notification_logs`
    MODIFY COLUMN `user_id` INT UNSIGNED NOT NULL,
    ADD CONSTRAINT `fk_nl_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

-- ── annonces : ajout FK publie_par ───────────────────────────────────────────
ALTER TABLE `annonces`
    ADD CONSTRAINT `fk_annonces_user`
        FOREIGN KEY (`publie_par`) REFERENCES `users`(`id`) ON DELETE SET NULL;

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('M008', 'Correction types INT→UNSIGNED emplois_du_temps, ajout FK manquantes notifications/annonces');
```

---

### M009 — Formalisation de `push_subscriptions` en migration

```sql
-- ═══════════════════════════════════════════════════════════════════════════
-- M009 : Formalisation de push_subscriptions dans les migrations SQL
-- La table est créée par PHP (PushSubscriptionModel::ensureTableExists()).
-- Ce script la crée si absente, ou ajoute la FK si elle existe sans FK.
-- Prérequis : M001
-- ═══════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

-- Créer si absente (idempotent grâce à IF NOT EXISTS)
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `endpoint`     TEXT         NOT NULL,
    `p256dh`       VARCHAR(255) NOT NULL DEFAULT '',
    `auth`         VARCHAR(255) NOT NULL DEFAULT '',
    `user_agent`   VARCHAR(255) NOT NULL DEFAULT '',
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` DATETIME     NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_endpoint` (`endpoint`(200)),
    CONSTRAINT `fk_push_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Si la table existait déjà sans la FK, l'ajouter (ignorer si FK déjà présente)
-- MySQL ne supporte pas IF NOT EXISTS sur ADD CONSTRAINT, utiliser une procédure
DROP PROCEDURE IF EXISTS `add_push_fk_if_missing`;
DELIMITER $$
CREATE PROCEDURE `add_push_fk_if_missing`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = 'ecole_app'
          AND TABLE_NAME = 'push_subscriptions'
          AND CONSTRAINT_NAME = 'fk_push_user'
    ) THEN
        ALTER TABLE `push_subscriptions`
            MODIFY COLUMN `user_id` INT UNSIGNED NOT NULL,
            ADD CONSTRAINT `fk_push_user`
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;
    END IF;
END$$
DELIMITER ;
CALL `add_push_fk_if_missing`();
DROP PROCEDURE IF EXISTS `add_push_fk_if_missing`;

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('M009', 'Formalisation de push_subscriptions en migration SQL + ajout FK user_id');
```

---

### M010 — Compléter les `permissions` en DB

```sql
-- ═══════════════════════════════════════════════════════════════════════════
-- M010 : Ajout des permissions manquantes dans la table permissions
-- Aligne la table DB avec config/permissions.php (source de vérité)
-- Note : La table DB n'est pas utilisée par le code V1 (config/ est chargé).
--        Ces inserts préparent la migration V2 vers permissions en DB.
-- Prérequis : M001, auth_migration.sql, classes_matieres_migration.sql
-- ═══════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

INSERT IGNORE INTO `permissions` (`code`, `libelle`, `module`) VALUES
-- Bulletins
('bulletins.view',           'Consulter les bulletins',           'bulletins'),
('bulletins.generate',       'Générer les bulletins',             'bulletins'),
('bulletins.print',          'Imprimer les bulletins',            'bulletins'),
-- Comptabilité
('comptabilite.view',        'Voir le module comptabilité',        'comptabilite'),
('comptabilite.manage',      'Gérer les frais et paiements',       'comptabilite'),
('comptabilite.rapport',     'Voir les rapports financiers',       'comptabilite'),
('paiements.view',           'Voir les paiements',                 'comptabilite'),
('paiements.create',         'Enregistrer un paiement',            'comptabilite'),
('depenses.view',            'Voir les dépenses',                  'comptabilite'),
('depenses.manage',          'Gérer les dépenses',                 'comptabilite'),
-- Emploi du temps
('emploi_du_temps.view',     'Consulter l\'emploi du temps',       'emploi_du_temps'),
('emploi_du_temps.manage',   'Gérer l\'emploi du temps',           'emploi_du_temps'),
-- Annonces
('annonces.view',            'Voir les annonces',                  'annonces'),
('annonces.create',          'Créer une annonce',                  'annonces'),
('annonces.edit',            'Modifier une annonce',               'annonces'),
('annonces.delete',          'Supprimer une annonce',              'annonces'),
-- Absences complémentaires
('absences.delete',          'Supprimer une absence',              'absences'),
('absences.justify',         'Soumettre une justification',        'absences'),
('absences.justify_admin',   'Valider/refuser une justification',  'absences'),
-- Notes complémentaires
('notes.view_own',           'Voir ses propres notes',             'notes'),
-- Profil
('profil.edit',              'Modifier son propre profil',         'profil');

-- Affecter les nouvelles permissions par rôle selon config/permissions.php
-- Admin : toutes
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'admin', `id` FROM `permissions`
WHERE `code` IN (
    'bulletins.view','bulletins.generate','bulletins.print',
    'comptabilite.view','comptabilite.manage','comptabilite.rapport',
    'paiements.view','paiements.create','depenses.view','depenses.manage',
    'emploi_du_temps.view','emploi_du_temps.manage',
    'annonces.view','annonces.create','annonces.edit','annonces.delete',
    'absences.delete','absences.justify','absences.justify_admin',
    'profil.edit'
);

-- Directeur
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'directeur', `id` FROM `permissions`
WHERE `code` IN (
    'bulletins.view','bulletins.generate','bulletins.print',
    'comptabilite.view','comptabilite.rapport',
    'paiements.view','depenses.view',
    'emploi_du_temps.view','emploi_du_temps.manage',
    'annonces.view','annonces.create','annonces.edit','annonces.delete',
    'absences.justify_admin','profil.edit'
);

-- Secrétaire
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'secretaire', `id` FROM `permissions`
WHERE `code` IN (
    'bulletins.view','emploi_du_temps.view',
    'annonces.view','absences.justify_admin','profil.edit'
);

-- Comptable
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'comptable', `id` FROM `permissions`
WHERE `code` IN (
    'comptabilite.view','comptabilite.manage','comptabilite.rapport',
    'paiements.view','paiements.create','depenses.view','depenses.manage',
    'profil.edit'
);

-- Enseignant
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'enseignant', `id` FROM `permissions`
WHERE `code` IN (
    'bulletins.view','bulletins.print',
    'emploi_du_temps.view',
    'annonces.view','absences.justify_admin','profil.edit'
);

-- Parent
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'parent', `id` FROM `permissions`
WHERE `code` IN (
    'bulletins.view','emploi_du_temps.view',
    'annonces.view','absences.justify','profil.edit',
    'paiements.view'
);

-- Élève
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'eleve', `id` FROM `permissions`
WHERE `code` IN (
    'notes.view_own','bulletins.view','emploi_du_temps.view',
    'annonces.view','profil.edit'
);

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('M010', 'Complétion des permissions DB pour alignement avec config/permissions.php');
```

---

### M011 — Suppression des colonnes `annee_scolaire VARCHAR` (fin de transition)

```sql
-- ═══════════════════════════════════════════════════════════════════════════
-- M011 : Suppression des colonnes annee_scolaire VARCHAR (devenues obsolètes)
-- ⚠ À EXÉCUTER UNIQUEMENT après :
--   1. M003 appliqué (toutes les tables ont annee_id NOT NULL peuplé)
--   2. Tout le code PHP mis à jour pour utiliser annee_id (migration V2 code)
--   3. Tests de non-régression validés
-- Prérequis : M003 + code PHP V2 déployé
-- ═══════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

-- Supprimer les anciens UNIQUE KEY qui utilisaient annee_scolaire
ALTER TABLE `enseignements`
    DROP KEY IF EXISTS `uq_ens`;

ALTER TABLE `frais_eleves`
    DROP KEY IF EXISTS `uq_frais_eleve`;

-- Supprimer les colonnes VARCHAR
ALTER TABLE `classes`           DROP COLUMN IF EXISTS `annee_scolaire`;
ALTER TABLE `enseignements`     DROP COLUMN IF EXISTS `annee_scolaire`;
ALTER TABLE `periodes`          DROP COLUMN IF EXISTS `annee_scolaire`;
ALTER TABLE `frais_eleves`      DROP COLUMN IF EXISTS `annee_scolaire`;
ALTER TABLE `paiements`         DROP COLUMN IF EXISTS `annee_scolaire`;
ALTER TABLE `emplois_du_temps`  DROP COLUMN IF EXISTS `annee_scolaire`;

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('M011', 'Suppression colonnes annee_scolaire VARCHAR (transition vers annee_id FK terminée)');
```

---

### M012 — Nettoyage tables backup (après validation)

```sql
-- ═══════════════════════════════════════════════════════════════════════════
-- M012 : Suppression des tables backup créées par M004 et M005
-- ⚠ À EXÉCUTER UNIQUEMENT après validation complète des données en production
-- Prérequis : M004, M005 + validation de données par équipe métier
-- ═══════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

DROP TABLE IF EXISTS `_backup_notes_v1`;
DROP TABLE IF EXISTS `_backup_absences_v1`;

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('M012', 'Suppression tables backup _backup_notes_v1 et _backup_absences_v1');
```

---

## SYNTHÈSE — PLAN D'EXÉCUTION

### Ordre et dépendances

```
M001 (versioning)
  └── M002 (annees_scolaires)
        └── M003 (annee_id dans 6 tables)
              └── M011 (drop annee_scolaire VARCHAR) ← après code V2 déployé
M004 (fix notes V1→V2)           ← indépendant, détecter schéma avant
M005 (fix absences V1→V2)        ← indépendant, détecter schéma avant
M006 (drop reset_token on users) ← indépendant
M007 (eleves.user_id)            ← indépendant
M008 (types + FK manquantes)     ← après M004, M005 (FK sur notes/absences)
M009 (push_subscriptions)        ← indépendant
M010 (permissions DB)            ← après auth_migration + classes_matieres
M012 (drop backups)              ← après M004 + M005 + validation métier
```

### Risques et précautions

| Migration | Risque | Précaution |
|---|---|---|
| M003 | UPDATE fails si libelle non trouvé dans annees_scolaires | Vérifier AVANT : `SELECT DISTINCT annee_scolaire FROM classes` et s'assurer que chaque valeur est dans `annees_scolaires` |
| M004 | Perte de données notes V1 | Vérifier `_backup_notes_v1` avant DROP. Décider du sort des données V1 avec l'équipe métier |
| M005 | Perte de justifications (FK CASCADE) | Sauvegarder `justifications` aussi si elles existent |
| M006 | Code PHP qui lirait reset_token depuis users serait cassé | Vérifier via grep : `reset_token` n'est lu que dans `UserModel` qui utilise `password_resets` |
| M008 | FK sur notifications/annonces bloque si user_id orphelin | `SELECT user_id FROM notifications WHERE user_id NOT IN (SELECT id FROM users)` avant ALTER |
| M011 | Code PHP encore sur annee_scolaire VARCHAR est cassé | N'exécuter QU'APRÈS migration complète du code |

### Script de vérification pré-migration

```sql
-- À exécuter AVANT M003 pour valider la cohérence des libellés
SELECT DISTINCT 'classes' AS tbl, annee_scolaire FROM classes
UNION SELECT 'enseignements', annee_scolaire FROM enseignements
UNION SELECT 'periodes', annee_scolaire FROM periodes
UNION SELECT 'frais_eleves', annee_scolaire FROM frais_eleves
UNION SELECT 'paiements', annee_scolaire FROM paiements
UNION SELECT 'emplois_du_temps', annee_scolaire FROM emplois_du_temps
ORDER BY 1, 2;
-- Tout libellé qui n'est pas dans annees_scolaires.libelle doit être ajouté en M002

-- À exécuter AVANT M008 pour détecter les user_id orphelins
SELECT 'notifications' AS tbl, user_id FROM notifications WHERE user_id NOT IN (SELECT id FROM users)
UNION ALL
SELECT 'notification_preferences', user_id FROM notification_preferences WHERE user_id NOT IN (SELECT id FROM users)
UNION ALL
SELECT 'notification_logs', user_id FROM notification_logs WHERE user_id NOT IN (SELECT id FROM users)
UNION ALL
SELECT 'push_subscriptions', user_id FROM push_subscriptions WHERE user_id NOT IN (SELECT id FROM users);
-- Toute ligne retournée bloquera l'ajout de FK → nettoyer ou corriger avant

-- À exécuter AVANT M004 pour détecter le schéma actuel de notes
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'ecole_app' AND TABLE_NAME = 'notes'
ORDER BY ORDINAL_POSITION;
-- 'controle_id' présent → schéma V2, sauter M004
-- 'matiere_id' présent  → schéma V1, exécuter M004
```

---

*DATABASE_V2.md — SCOLARIS | Ne pas exécuter les migrations sans validation de l'équipe technique et sauvegarde complète de la base de données.*
