# ACADEMIQUE_BLUEPRINT_V2 — Conception Complète du Module Académique V2
**Date :** 2026-06-30  
**Auteur :** SCOLARIS V2 — Phase 2.0 (Blueprint)  
**Version :** 1.0.0-draft  
**Statut :** Conception uniquement — aucun code, aucune migration

---

## Table des Matières

1. [Responsabilités du module](#1-responsabilités-du-module)
2. [Limites avec le module Scolarité](#2-limites-avec-le-module-scolarité)
3. [Entités métier](#3-entités-métier)
4. [Relations entre les entités](#4-relations-entre-les-entités)
5. [Architecture interne](#5-architecture-interne)
6. [Services métiers](#6-services-métiers)
7. [Repositories](#7-repositories)
8. [DTO](#8-dto)
9. [Policies](#9-policies)
10. [Événements](#10-événements)
11. [Permissions RBAC](#11-permissions-rbac)
12. [Interfaces utilisateur](#12-interfaces-utilisateur)
13. [Flux métier principaux](#13-flux-métier-principaux)
14. [API futures](#14-api-futures)
15. [Dépendances avec les autres modules](#15-dépendances-avec-les-autres-modules)
16. [Stratégie de migration V1 → V2](#16-stratégie-de-migration-v1--v2)
17. [Définition des règles de calcul](#17-définition-des-règles-de-calcul)

---

## 1. Responsabilités du Module

Le module Académique V2 est propriétaire de tout ce qui concerne l'évaluation pédagogique et ses résultats. Il ne gère **pas** l'identité des élèves ni l'organisation scolaire — il les **consomme**.

### Ce que le module Académique V2 gère

| Domaine | Description |
|---|---|
| **Périodes scolaires** | Trimestres et semestres : création, fermeture, verrouillage, cycle de vie |
| **Types d'évaluations** | Référentiel configurable : contrôle, devoir, examen, TP, oral, composition… |
| **Évaluations** | Une évaluation = un acte d'évaluation (date, matière, classe, type, barème, coefficient) |
| **Notes** | La note d'un élève pour une évaluation ; gestion des absences et des dispenses |
| **Appréciations** | Commentaire de l'enseignant sur un élève pour une matière et une période |
| **Moyennes** | Calcul automatique des moyennes par matière, par période, et annuelle |
| **Classements** | Rang de chaque élève dans sa classe, par période et annuel |
| **Bulletins** | Génération, stockage et impression des bulletins scolaires en PDF |
| **Décisions de fin d'année** | Admis, redoublant, passage conditionnel, orienté, exclu |
| **Sessions de rattrapage** | Planification, notes et décisions post-rattrapage |
| **Statistiques académiques** | Taux de réussite, répartition des mentions, évolution, comparaisons |
| **Import / Export** | Import CSV des notes par évaluation ; export Excel et PDF |

### Ce que le module Académique V2 ne gère PAS

- L'inscription d'un élève dans une classe → **Module Scolarité V2**
- L'affectation d'un enseignant à une matière → **Module Scolarité V2 (AffectationService)**
- La gestion des familles / responsables légaux → **Module Scolarité V2**
- L'emploi du temps horaire → **Module Emploi du Temps V2** (futur)
- Les paiements et frais scolaires → **Module Comptabilité**

---

## 2. Limites avec le Module Scolarité

```
┌─────────────────────────────────────────────────────────────────┐
│                     MODULE SCOLARITÉ V2                         │
│                                                                 │
│  eleves  ──────────────────────────────────────────────────┐   │
│  classes ──────────────────────────────────────────────┐   │   │
│  matieres ─────────────────────────────────────────┐   │   │   │
│  enseignements (prof × matière × classe) ───────┐  │   │   │   │
│                                                  │  │   │   │   │
└──────────────────────────────────────────────────┼──┼───┼───┼───┘
                                                   │  │   │   │
                          Lecture en lecture seule │  │   │   │
                          (FK, jamais modifié)     │  │   │   │
                                                   ▼  ▼   ▼   ▼
┌─────────────────────────────────────────────────────────────────┐
│                    MODULE ACADÉMIQUE V2                         │
│                                                                 │
│  periodes_scolaires                                             │
│  types_evaluations                                              │
│  evaluations ──── (matiere_id, classe_id, professeur_id)       │
│  notes       ──── (eleve_id, evaluation_id)                    │
│  appreciations ── (eleve_id, matiere_id, periode_id)           │
│  moyennes_matieres, moyennes_generales, moyennes_annuelles      │
│  bulletins, decisions_fin_annee                                 │
│  sessions_rattrapage, notes_rattrapage                          │
└─────────────────────────────────────────────────────────────────┘
```

### Règles de frontière

| Règle | Raison |
|---|---|
| L'Académique ne modifie jamais `eleves`, `classes`, `matieres` | Intégrité du domaine Scolarité |
| L'Académique ne crée pas d'enseignements | C'est AffectationService (Scolarité) qui lie prof×matière×classe |
| La validation d'une inscription reste dans Scolarité | La décision académique (admis/redoublant) est un signal, pas une modification de l'inscription |
| Les coefficients des matières sont lus depuis `matieres.coefficient` | Scolarité reste maître des coefficients de référence |

---

## 3. Entités Métier

### 3.1 Schéma SQL V2 — Tables nouvelles (CREATE TABLE IF NOT EXISTS)

#### `periodes_scolaires`
```sql
CREATE TABLE IF NOT EXISTS `periodes_scolaires` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `annee_scolaire`  VARCHAR(9)   NOT NULL COMMENT 'Ex: 2025-2026',
    `type_periode`    ENUM('trimestre','semestre') NOT NULL DEFAULT 'trimestre',
    `numero`          TINYINT UNSIGNED NOT NULL COMMENT '1, 2 ou 3',
    `nom`             VARCHAR(60)  NOT NULL COMMENT 'Ex: Trimestre 1 — 2025-2026',
    `date_debut`      DATE         NULL,
    `date_fin`        DATE         NULL,
    `statut`          ENUM('ouverte','fermee','verrouillee') NOT NULL DEFAULT 'ouverte',
    `verrouille_par`  INT UNSIGNED NULL,
    `verrouille_le`   DATETIME     NULL,
    `notes_saisie_ouverte` TINYINT(1) NOT NULL DEFAULT 1
                      COMMENT '0 = saisie bloquée pour les enseignants',
    `created_by`      INT UNSIGNED NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_periode` (`annee_scolaire`, `type_periode`, `numero`),
    INDEX `idx_periode_annee` (`annee_scolaire`),
    INDEX `idx_periode_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `types_evaluations`
```sql
CREATE TABLE IF NOT EXISTS `types_evaluations` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(20)  NOT NULL UNIQUE COMMENT 'controle, devoir, examen, tp, oral, composition',
    `nom`         VARCHAR(60)  NOT NULL,
    `description` VARCHAR(255) NULL,
    `ponderation` DECIMAL(4,2) NOT NULL DEFAULT 1.00
                  COMMENT 'Multiplicateur du coefficient de l\'évaluation',
    `couleur`     VARCHAR(7)   NULL COMMENT '#RRGGBB',
    `icone`       VARCHAR(30)  NULL,
    `compte_bulletin` TINYINT(1) NOT NULL DEFAULT 1,
    `actif`       TINYINT(1)   NOT NULL DEFAULT 1,
    `ordre`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_type_actif` (`actif`, `ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Données initiales (à insérer lors de la migration) :**

| code | nom | pondération |
|---|---|---|
| `controle` | Contrôle continu | 1.0 |
| `devoir` | Devoir surveillé | 1.5 |
| `composition` | Composition (exam trimestriel) | 2.0 |
| `examen` | Examen | 2.0 |
| `tp` | Travaux Pratiques | 0.5 |
| `oral` | Interrogation orale | 0.5 |
| `rattrapage` | Évaluation de rattrapage | 1.0 |

#### `evaluations`
```sql
CREATE TABLE IF NOT EXISTS `evaluations` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `matiere_id`          INT UNSIGNED NOT NULL,
    `classe_id`           INT UNSIGNED NOT NULL,
    `periode_id`          INT UNSIGNED NOT NULL
                          COMMENT 'FK → periodes_scolaires.id',
    `type_evaluation_id`  INT UNSIGNED NOT NULL,
    `professeur_id`       INT UNSIGNED NULL COMMENT 'FK → professeurs.id',
    `intitule`            VARCHAR(150) NOT NULL,
    `date_evaluation`     DATE         NULL,
    `coefficient`         DECIMAL(4,2) NOT NULL DEFAULT 1.00
                          COMMENT 'Coefficient propre de cette évaluation',
    `sur`                 DECIMAL(5,2) NOT NULL DEFAULT 20.00
                          COMMENT 'Barème max (sur 20, sur 10, sur 100…)',
    `statut`              ENUM('brouillon','publiee','corrigee','cloturee')
                          NOT NULL DEFAULT 'brouillon',
    `notes_importees_le`  DATETIME     NULL,
    `cloturee_par`        INT UNSIGNED NULL,
    `cloturee_le`         DATETIME     NULL,
    `created_by`          INT UNSIGNED NOT NULL,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_eval_matiere`    FOREIGN KEY (`matiere_id`)         REFERENCES `matieres`(`id`)          ON DELETE RESTRICT,
    CONSTRAINT `fk_eval_classe`     FOREIGN KEY (`classe_id`)          REFERENCES `classes`(`id`)           ON DELETE RESTRICT,
    CONSTRAINT `fk_eval_periode`    FOREIGN KEY (`periode_id`)         REFERENCES `periodes_scolaires`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_eval_type`       FOREIGN KEY (`type_evaluation_id`) REFERENCES `types_evaluations`(`id`) ON DELETE RESTRICT,
    INDEX `idx_eval_classe_periode` (`classe_id`, `periode_id`),
    INDEX `idx_eval_matiere`        (`matiere_id`),
    INDEX `idx_eval_statut`         (`statut`),
    INDEX `idx_eval_date`           (`date_evaluation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `notes_v2`
```sql
CREATE TABLE IF NOT EXISTS `notes_v2` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `evaluation_id`   INT UNSIGNED NOT NULL,
    `eleve_id`        INT UNSIGNED NOT NULL,
    `note`            DECIMAL(6,2) NULL    COMMENT 'NULL = non saisie',
    `absent`          TINYINT(1)   NOT NULL DEFAULT 0,
    `absent_justifie` TINYINT(1)   NOT NULL DEFAULT 0,
    `dispense`        TINYINT(1)   NOT NULL DEFAULT 0
                      COMMENT 'Dispense médicale ou autre — exclue du calcul',
    `observation`     VARCHAR(255) NULL,
    `saisie_par`      INT UNSIGNED NULL,
    `saisie_le`       DATETIME     NULL,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_note_eleve_eval` (`evaluation_id`, `eleve_id`),
    CONSTRAINT `fk_notev2_eval`  FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notev2_eleve` FOREIGN KEY (`eleve_id`)      REFERENCES `eleves`(`id`)      ON DELETE CASCADE,
    INDEX `idx_notev2_eleve` (`eleve_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> **Nommage `notes_v2` :** La table `notes` existe en V1. Deux options en migration : (a) utiliser `notes_v2` (coexistence propre) ou (b) enrichir `notes` via ALTER TABLE. L'option (a) est recommandée pour garantir zéro régression V1.

#### `appreciations`
```sql
CREATE TABLE IF NOT EXISTS `appreciations` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `eleve_id`     INT UNSIGNED NOT NULL,
    `matiere_id`   INT UNSIGNED NOT NULL,
    `classe_id`    INT UNSIGNED NOT NULL,
    `periode_id`   INT UNSIGNED NOT NULL,
    `professeur_id` INT UNSIGNED NULL,
    `texte`        TEXT         NOT NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_appre` (`eleve_id`, `matiere_id`, `periode_id`),
    CONSTRAINT `fk_appre_eleve`   FOREIGN KEY (`eleve_id`)   REFERENCES `eleves`(`id`)             ON DELETE CASCADE,
    CONSTRAINT `fk_appre_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres`(`id`)            ON DELETE CASCADE,
    CONSTRAINT `fk_appre_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes_scolaires`(`id`)  ON DELETE CASCADE,
    INDEX `idx_appre_eleve_periode` (`eleve_id`, `periode_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `moyennes_matieres_v2`
```sql
CREATE TABLE IF NOT EXISTS `moyennes_matieres_v2` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `eleve_id`        INT UNSIGNED NOT NULL,
    `matiere_id`      INT UNSIGNED NOT NULL,
    `classe_id`       INT UNSIGNED NOT NULL,
    `periode_id`      INT UNSIGNED NOT NULL,
    `annee_scolaire`  VARCHAR(9)   NOT NULL,
    `moyenne`         DECIMAL(5,2) NULL,
    `note_min`        DECIMAL(5,2) NULL,
    `note_max`        DECIMAL(5,2) NULL,
    `nb_evaluations`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `nb_absences`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `rang_matiere`    SMALLINT UNSIGNED NULL,
    `appreciation_id` INT UNSIGNED NULL,
    `calculated_at`   DATETIME     NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_moy_mat` (`eleve_id`, `matiere_id`, `classe_id`, `periode_id`),
    CONSTRAINT `fk_mm_eleve`   FOREIGN KEY (`eleve_id`)   REFERENCES `eleves`(`id`)            ON DELETE CASCADE,
    CONSTRAINT `fk_mm_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres`(`id`)           ON DELETE CASCADE,
    CONSTRAINT `fk_mm_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes_scolaires`(`id`) ON DELETE CASCADE,
    INDEX `idx_mm_classe_periode` (`classe_id`, `periode_id`),
    INDEX `idx_mm_eleve` (`eleve_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `moyennes_generales_v2`
```sql
CREATE TABLE IF NOT EXISTS `moyennes_generales_v2` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `eleve_id`           INT UNSIGNED NOT NULL,
    `classe_id`          INT UNSIGNED NOT NULL,
    `periode_id`         INT UNSIGNED NOT NULL,
    `annee_scolaire`     VARCHAR(9)   NOT NULL,
    `moyenne`            DECIMAL(5,2) NULL,
    `total_points`       DECIMAL(7,2) NULL COMMENT 'Σ(moyenne_matière × coef)',
    `total_coefficients` DECIMAL(6,2) NULL COMMENT 'Σ(coef_matière)',
    `nb_matieres`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `rang`               SMALLINT UNSIGNED NULL,
    `mention`            VARCHAR(20)  NULL,
    `appreciation_generale` TEXT      NULL COMMENT 'Appréciation du conseil de classe',
    `calculated_at`      DATETIME     NULL,
    `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_moy_gen` (`eleve_id`, `classe_id`, `periode_id`),
    CONSTRAINT `fk_mg_eleve`   FOREIGN KEY (`eleve_id`)   REFERENCES `eleves`(`id`)            ON DELETE CASCADE,
    CONSTRAINT `fk_mg_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes_scolaires`(`id`) ON DELETE CASCADE,
    INDEX `idx_mg_classe_periode` (`classe_id`, `periode_id`),
    INDEX `idx_mg_rang` (`classe_id`, `periode_id`, `rang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `moyennes_annuelles`
```sql
CREATE TABLE IF NOT EXISTS `moyennes_annuelles` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `eleve_id`           INT UNSIGNED NOT NULL,
    `classe_id`          INT UNSIGNED NOT NULL,
    `annee_scolaire`     VARCHAR(9)   NOT NULL,
    `moyenne`            DECIMAL(5,2) NULL,
    `rang`               SMALLINT UNSIGNED NULL,
    `mention`            VARCHAR(20)  NULL,
    `nb_periodes`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `eligible_rattrapage` TINYINT(1)  NOT NULL DEFAULT 0,
    `calculated_at`      DATETIME     NULL,
    `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_moy_ann` (`eleve_id`, `classe_id`, `annee_scolaire`),
    CONSTRAINT `fk_ma_eleve`  FOREIGN KEY (`eleve_id`)  REFERENCES `eleves`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_ma_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    INDEX `idx_ma_annee` (`annee_scolaire`, `classe_id`),
    INDEX `idx_ma_rang`  (`classe_id`, `annee_scolaire`, `rang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `bulletins`
```sql
CREATE TABLE IF NOT EXISTS `bulletins` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `eleve_id`       INT UNSIGNED NOT NULL,
    `classe_id`      INT UNSIGNED NOT NULL,
    `periode_id`     INT UNSIGNED NOT NULL,
    `annee_scolaire` VARCHAR(9)   NOT NULL,
    `statut`         ENUM('non_genere','genere','imprime','archive')
                     NOT NULL DEFAULT 'non_genere',
    `fichier_path`   VARCHAR(255) NULL COMMENT 'Chemin relatif depuis ROOT_PATH/storage/',
    `hash`           VARCHAR(64)  NULL COMMENT 'SHA-256 du PDF pour détecter les changements',
    `genere_le`      DATETIME     NULL,
    `genere_par`     INT UNSIGNED NULL,
    `imprime_le`     DATETIME     NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bulletin` (`eleve_id`, `classe_id`, `periode_id`),
    CONSTRAINT `fk_bul_eleve`   FOREIGN KEY (`eleve_id`)   REFERENCES `eleves`(`id`)            ON DELETE CASCADE,
    CONSTRAINT `fk_bul_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes_scolaires`(`id`) ON DELETE RESTRICT,
    INDEX `idx_bul_classe_periode` (`classe_id`, `periode_id`),
    INDEX `idx_bul_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `decisions_fin_annee`
```sql
CREATE TABLE IF NOT EXISTS `decisions_fin_annee` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `eleve_id`       INT UNSIGNED NOT NULL,
    `classe_id`      INT UNSIGNED NOT NULL,
    `annee_scolaire` VARCHAR(9)   NOT NULL,
    `decision`       ENUM('admis','redoublant','passage_conditionnel','oriente','exclu','en_attente')
                     NOT NULL DEFAULT 'en_attente',
    `motif`          TEXT         NULL,
    `delibere_par`   INT UNSIGNED NULL,
    `delibere_le`    DATETIME     NULL,
    `est_definitif`  TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_decision` (`eleve_id`, `annee_scolaire`),
    CONSTRAINT `fk_dec_eleve`  FOREIGN KEY (`eleve_id`)  REFERENCES `eleves`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_dec_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes`(`id`) ON DELETE RESTRICT,
    INDEX `idx_dec_annee` (`annee_scolaire`, `classe_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `sessions_rattrapage`
```sql
CREATE TABLE IF NOT EXISTS `sessions_rattrapage` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `annee_scolaire` VARCHAR(9)   NOT NULL,
    `nom`            VARCHAR(100) NOT NULL,
    `date_debut`     DATE         NULL,
    `date_fin`       DATE         NULL,
    `statut`         ENUM('planifiee','ouverte','terminee','cloturee')
                     NOT NULL DEFAULT 'planifiee',
    `seuil_eligible` DECIMAL(4,2) NOT NULL DEFAULT 8.00
                     COMMENT 'Moyenne annuelle minimale pour participer au rattrapage',
    `created_by`     INT UNSIGNED NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_rattr_annee` (`annee_scolaire`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `rattrapage_matieres`
```sql
CREATE TABLE IF NOT EXISTS `rattrapage_matieres` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`      INT UNSIGNED NOT NULL,
    `matiere_id`      INT UNSIGNED NOT NULL,
    `classe_id`       INT UNSIGNED NOT NULL,
    `seuil_matiere`   DECIMAL(4,2) NULL
                      COMMENT 'Seuil spécifique pour cette matière (sinon seuil global)',
    `coefficient`     DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    `sur`             DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rattr_mat` (`session_id`, `matiere_id`, `classe_id`),
    CONSTRAINT `fk_rm_session` FOREIGN KEY (`session_id`) REFERENCES `sessions_rattrapage`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rm_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres`(`id`)            ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `notes_rattrapage`
```sql
CREATE TABLE IF NOT EXISTS `notes_rattrapage` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`    INT UNSIGNED NOT NULL,
    `matiere_id`    INT UNSIGNED NOT NULL,
    `eleve_id`      INT UNSIGNED NOT NULL,
    `note`          DECIMAL(5,2) NULL,
    `absent`        TINYINT(1)   NOT NULL DEFAULT 0,
    `observation`   VARCHAR(255) NULL,
    `saisie_par`    INT UNSIGNED NULL,
    `saisie_le`     DATETIME     NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_note_rattr` (`session_id`, `matiere_id`, `eleve_id`),
    CONSTRAINT `fk_nr_session` FOREIGN KEY (`session_id`) REFERENCES `sessions_rattrapage`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_nr_eleve`   FOREIGN KEY (`eleve_id`)   REFERENCES `eleves`(`id`)              ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Tables V1 enrichies par ALTER TABLE (sans casser la V1)

```sql
-- Enrichissement de `periodes` pour aligner V1 et V2
ALTER TABLE `periodes`
    ADD COLUMN IF NOT EXISTS `type_periode`    ENUM('trimestre','semestre') DEFAULT 'trimestre',
    ADD COLUMN IF NOT EXISTS `date_debut`      DATE NULL,
    ADD COLUMN IF NOT EXISTS `date_fin`        DATE NULL,
    ADD COLUMN IF NOT EXISTS `statut`          ENUM('ouverte','fermee','verrouillee') DEFAULT 'ouverte',
    ADD COLUMN IF NOT EXISTS `periode_v2_id`   INT UNSIGNED NULL
        COMMENT 'FK optionnel vers periodes_scolaires.id après migration';
```

> **Note :** Les tables `controles`, `notes`, `moyennes_matieres`, `moyennes_generales` restent intactes. V2 utilise ses propres tables (`evaluations`, `notes_v2`, `moyennes_matieres_v2`, `moyennes_generales_v2`).

---

## 4. Relations entre les Entités

```
periodes_scolaires ──────┐
                          │1
types_evaluations ────┐   │
                       │1  │N
                       ▼   ▼
matieres (Scolarité) ──► evaluations ◄── classes (Scolarité)
                              │N            │
                              │1            │
                              ▼             │
                          notes_v2 ◄── eleves (Scolarité)
                          (eleve × eval)
                              │
                              │ recalcule
                              ▼
                     moyennes_matieres_v2
                     (eleve × matière × période)
                              │
                              │ agrège
                              ▼
                     moyennes_generales_v2
                     (eleve × période)
                              │
                              │ agrège (fin d'année)
                              ▼
                     moyennes_annuelles
                     (eleve × année)
                              │
                              ├── bulletins (1 par eleve × période)
                              │
                              └── decisions_fin_annee (1 par eleve × année)
                                        │
                                        │ si éligible
                                        ▼
                               sessions_rattrapage
                                ├── rattrapage_matieres
                                └── notes_rattrapage
                                          │
                                          └── (nouveau calcul) → décision finale
```

---

## 5. Architecture Interne

```
app/Modules/Academique/
├── module.json
├── routes.php                    # /v2/academique/*
├── Controllers/
│   ├── PeriodeController.php     # CRUD périodes + workflow statuts
│   ├── TypeEvaluationController.php
│   ├── EvaluationController.php  # CRUD + statuts + saisie
│   ├── NoteController.php        # Saisie inline + import CSV
│   ├── MoyenneController.php     # Recalcul + tableau de bord
│   ├── AppreciationController.php
│   ├── BulletinController.php    # Génération + impression
│   ├── DecisionController.php    # Fin d'année + délibération
│   ├── RattrapageController.php  # Session + notes + décisions
│   └── StatistiquesController.php
├── Services/
│   ├── PeriodeScolaireService.php
│   ├── EvaluationService.php
│   ├── NoteService.php
│   ├── MoyenneService.php        # Cœur du module
│   ├── ClassementService.php
│   ├── AppreciationService.php
│   ├── BulletinService.php
│   ├── DecisionService.php
│   ├── RattrapageService.php
│   └── StatistiquesAcademiqueService.php
├── Repositories/
│   ├── PeriodeScolaireRepository.php
│   ├── EvaluationRepository.php
│   ├── NoteRepository.php
│   ├── MoyenneRepository.php
│   ├── ClassementRepository.php
│   ├── BulletinRepository.php
│   ├── DecisionRepository.php
│   └── StatistiquesRepository.php
├── Models/
│   ├── PeriodeScolaireModel.php
│   ├── TypeEvaluationModel.php
│   ├── EvaluationModel.php
│   ├── NoteV2Model.php
│   ├── AppreciationModel.php
│   ├── MoyenneMatiereV2Model.php
│   ├── MoyenneGeneraleV2Model.php
│   ├── MoyenneAnnuelleModel.php
│   ├── BulletinModel.php
│   ├── DecisionFinAnneeModel.php
│   ├── SessionRattrapageModel.php
│   └── NoteRattrapageModel.php
├── DTO/
│   ├── PeriodeScolaireDTO.php
│   ├── EvaluationDTO.php
│   ├── NoteDTO.php
│   ├── NotesBulkDTO.php
│   ├── AppreciationDTO.php
│   ├── DecisionDTO.php
│   ├── RattrapageNoteDTO.php
│   ├── EvaluationFiltersDTO.php
│   └── NoteFiltersDTO.php
├── Events/
│   ├── EvaluationCreee.php
│   ├── EvaluationCloturee.php
│   ├── NotesSaisies.php         # Batch (une évaluation)
│   ├── NotesImportees.php
│   ├── MoyenneCalculee.php      # eleve × période
│   ├── BulletinGenere.php
│   ├── BulletinsBatchGeneres.php
│   ├── DecisionAttribuee.php
│   ├── RattrapageSessionOuverte.php
│   └── RattrapageNoteSaisie.php
├── Listeners/
│   ├── EvaluationHandler.php
│   ├── NoteHandler.php
│   ├── MoyenneHandler.php
│   ├── BulletinHandler.php
│   └── DecisionHandler.php
├── Policies/
│   ├── EvaluationPolicy.php
│   ├── NotePolicy.php
│   ├── BulletinPolicy.php
│   ├── PeriodePolicy.php
│   └── DecisionPolicy.php
└── Views/
    ├── periodes/
    │   ├── index.php, form.php, show.php
    ├── evaluations/
    │   ├── index.php, show.php, form.php
    ├── notes/
    │   ├── saisie.php, import.php, historique.php
    ├── moyennes/
    │   ├── recapitulatif_classe.php, evolution_eleve.php, classement.php
    ├── bulletins/
    │   ├── index.php, apercu.php, print.php, batch.php
    ├── decisions/
    │   ├── fin_annee.php, deliberation.php
    ├── rattrapage/
    │   ├── session.php, saisie.php, resultats.php
    └── statistiques/
        ├── dashboard.php, taux_reussite.php, repartition.php
```

---

## 6. Services Métiers

### 6.1 `PeriodeScolaireService`

**Responsabilités :** Cycle de vie des périodes.

| Méthode | Règles métier |
|---|---|
| `creer(PeriodeScolaireDTO, userId): int` | Unicité (annee + type + numero). Date fin > date début. |
| `modifier(id, DTO, userId)` | Impossible si statut = verrouillée. |
| `fermer(id, userId)` | Statut ouverte → fermee. Publie événement `PeriodeFermee`. Bloque la saisie enseignants. |
| `verrouiller(id, userId)` | Statut fermee → verrouillee. Impossible de modifier les notes après. Direction uniquement. |
| `rouvrir(id, userId)` | Statut fermee → ouverte. Ne peut pas ré-ouvrir une verrouillée. Admin uniquement. |
| `getOuverte(annee): ?object` | Retourne la période active pour une année donnée. |

### 6.2 `EvaluationService`

**Responsabilités :** Gestion du cycle de vie d'une évaluation.

| Méthode | Règles métier |
|---|---|
| `creer(EvaluationDTO, userId): int` | Vérifier que la période est ouverte. Vérifier que l'enseignant est affecté à cette matière/classe (via `enseignements`). `coefficient` > 0, `sur` > 0. |
| `modifier(id, DTO, userId)` | Impossible si statut = cloturee. Si statut = corrigee, recalcul des moyennes requis. |
| `publier(id, userId)` | Statut brouillon → publiee. L'évaluation est visible par les parents/élèves. |
| `cloturer(id, userId)` | Statut → cloturee. Les notes sont figées. Déclenche recalcul moyennes. |
| `supprimer(id, userId)` | Impossible si des notes ont été saisies. Impossible si statut ≠ brouillon. |

### 6.3 `NoteService`

**Responsabilités :** Saisie et gestion des notes.

| Méthode | Règles métier |
|---|---|
| `saisirBulk(NotesBulkDTO, userId): array` | Vérifier période ouverte et notes_saisie_ouverte = 1. Vérifier que le saisisseur est bien l'enseignant de l'évaluation (sauf admin). `note` entre 0 et `evaluation.sur`. Upsert via UNIQUE KEY. Retourne [saisies, ignorees, erreurs]. |
| `saisirUne(NoteDTO, userId)` | Même règles que bulk, pour une note individuelle. |
| `importerCsv(evaluationId, file, userId): array` | Parse CSV (BOM, délimiteur auto). Colonnes attendues : `matricule` ou `eleve_id`, `note`, `absent`. Validation ligne par ligne. Upsert en transaction. Dispatch `NotesImportees`. |
| `exporterCsv(evaluationId): string` | Génère CSV avec entête + toutes les notes. |
| `getNotesSaisies(evaluationId): array` | Grille complète (eleves de la classe × note saisie). |

### 6.4 `MoyenneService` *(Cœur du module)*

**Responsabilités :** Calcul, persistance et cohérence des moyennes.

| Méthode | Règles métier |
|---|---|
| `recalculerMoyenneMatiere(eleveId, matiereId, classeId, periodeId)` | Formule §17. Exclut les dispensés. Note 0 pour les absents non justifiés. Upsert dans `moyennes_matieres_v2`. |
| `recalculerMoyennesMatieresEleve(eleveId, classeId, periodeId)` | Boucle sur toutes les matières avec évaluations dans la période. |
| `recalculerMoyenneGenerale(eleveId, classeId, periodeId)` | Formule §17. Requiert toutes les `moyennes_matieres_v2`. Upsert `moyennes_generales_v2`. |
| `recalculerClasse(classeId, periodeId)` | Transaction englobant recalcul de tous les élèves + classement. Déclenche `MoyenneCalculee` pour chaque élève. |
| `recalculerDepuisEvaluation(evaluationId)` | Récupère classe+période de l'évaluation, puis `recalculerClasse()`. |
| `calculerMoyenneAnnuelle(eleveId, classeId, annee)` | Agrège les `moyennes_generales_v2` des périodes de l'année. Formule §17. Upsert `moyennes_annuelles`. |
| `recalculerMoyennesAnnuellesClasse(classeId, annee)` | Boucle élèves + classement annuel. |

**Stratégie transactionnelle :** Tout recalcul de classe est dans une transaction. En cas d'échec partiel, rollback complet. Les moyennes partielles ne sont jamais persistées.

### 6.5 `ClassementService`

**Responsabilités :** Calcul et mise à jour des rangs. Isolé de `MoyenneService` pour pouvoir être appelé indépendamment.

| Méthode | Règles métier |
|---|---|
| `recalculerRangsPeriode(classeId, periodeId)` | Tri DESC par `moyennes_generales_v2.moyenne`. Égalités → même rang (dense ranking : 1,1,2 et non 1,1,3). UPDATE en lot. |
| `recalculerRangsMatiere(matiereId, classeId, periodeId)` | Idem dans `moyennes_matieres_v2`. |
| `recalculerRangsAnnuels(classeId, annee)` | Idem dans `moyennes_annuelles`. |
| `getRangEleve(eleveId, classeId, periodeId): ?int` | Lecture rapide depuis `moyennes_generales_v2`. |

### 6.6 `AppreciationService`

**Responsabilités :** Appréciations par matière et par période.

| Méthode | Règles métier |
|---|---|
| `sauvegarder(AppreciationDTO, userId)` | Upsert (une seule appréciation par élève × matière × période). Seul l'enseignant de la matière/classe peut créer. Admin peut modifier. |
| `supprimer(id, userId)` | Impossible si période verrouillée. |
| `findByEleveEtPeriode(eleveId, periodeId): array` | Toutes les appréciations pour le bulletin. |

### 6.7 `BulletinService`

**Responsabilités :** Génération, stockage et impression des bulletins.

| Méthode | Règles métier |
|---|---|
| `genererBulletin(eleveId, classeId, periodeId, userId): string` | Vérifie que `moyennes_generales_v2` existe. Compile les données (notes détaillées, moyennes, rang, mention, appréciations, stats classe). Génère PDF via la bibliothèque PDF (à définir). Stocke le fichier. Upsert `bulletins` avec statut='genere'. Dispatch `BulletinGenere`. |
| `genererBatch(classeId, periodeId, userId): array` | Génère tous les bulletins d'une classe. Transaction par bulletin (un échec n'annule pas les autres). Dispatch `BulletinsBatchGeneres`. |
| `getBulletinData(eleveId, classeId, periodeId): array` | Données structurées pour le template : matieres[], notes_detail[][], appreciations[], mg, rang, mention, stats_classe (min/max/moy/nb). |
| `regenerer(bulletinId, userId)` | Recrée le PDF et met à jour le hash. |
| `archiver(bulletinId, userId)` | Statut → archive. |

### 6.8 `DecisionService`

**Responsabilités :** Décisions de fin d'année et processus de délibération.

| Méthode | Règles métier |
|---|---|
| `proposerDecisions(classeId, annee, userId): array` | Calcule automatiquement la décision proposée basée sur `moyennes_annuelles.moyenne` et les seuils configurés (voir §17). Retourne un tableau pour validation humaine. |
| `attribuerDecision(DecisionDTO, userId)` | Valide les valeurs d'ENUM. Upsert `decisions_fin_annee`. `est_definitif = 0`. Dispatch `DecisionAttribuee`. |
| `confirmerDecision(decisionId, userId)` | `est_definitif = 1`. Irréversible sans permission admin. |
| `getDecisionsClasse(classeId, annee): array` | Vue d'ensemble pour le délibéré. |

### 6.9 `RattrapageService`

**Responsabilités :** Sessions de rattrapage.

| Méthode | Règles métier |
|---|---|
| `creerSession(data, userId): int` | Crée la session + insère les élèves éligibles (moyenne annuelle entre seuil_eligible et 10). |
| `ajouterMatiere(sessionId, matiereId, classeId, userId)` | Ajoute une matière à la session. |
| `saisirNote(RattrapageNoteDTO, userId)` | Vérifie session ouverte. Note entre 0 et sur. Upsert. |
| `cloturerSession(sessionId, userId)` | Statut → cloturee. Déclenche `calculerDecisionsPostRattrapage()`. |
| `calculerDecisionsPostRattrapage(sessionId)` | Pour chaque élève en session : si note_rattrapage ≥ seuil → admis, sinon → redoublant définitif. Met à jour `decisions_fin_annee.est_definitif = 1`. |

### 6.10 `StatistiquesAcademiqueService`

**Responsabilités :** Données analytiques du module académique.

| Méthode | Données produites |
|---|---|
| `tauxReussiteClasse(classeId, periodeId)` | % élèves avec moyenne ≥ 10 |
| `tauxReussiteParMatiere(classeId, periodeId)` | % par matière |
| `repartitionMentions(classeId, periodeId)` | Répartition [TB, B, AB, P, I] |
| `evolutionEleve(eleveId, annee)` | Moyenne par période (données séries temporelles) |
| `comparaisonClasses(annee, periodeId)` | Tableau comparatif inter-classes |
| `statsGlobalesAnnee(annee)` | KPIs pour le dashboard Reporting V2 |

---

## 7. Repositories

### `PeriodeScolaireRepository`
- `findByAnnee(annee): array`
- `findOuverte(annee): ?object`
- `findWithStats(annee): array` — JOIN evaluations COUNT, JOIN notes COUNT

### `EvaluationRepository`
- `paginate(classeId, periodeId, matiereId, statut, page, perPage): array`
- `findWithDetails(id): ?object` — JOIN matiere + classe + periode + type + prof + COUNT(notes)
- `findByClasseAndPeriode(classeId, periodeId): array`
- `findByMatiereProfesseur(matiereId, professeurId): array` — pour la vue enseignant
- `countEvaluationsParMatiere(classeId, periodeId): array` — [matiere_id → count]
- `getStatsEvaluation(id): object` — moy, min, max, nb_absents, nb_notes

### `NoteRepository`
- `findByEvaluation(evaluationId): array` — grille complète (LEFT JOIN eleves)
- `findByEleve(eleveId, classeId, periodeId): array` — toutes les notes d'un élève sur une période
- `countSaisies(evaluationId): int`
- `bulkUpsert(evaluationId, array $notes): int` — INSERT ON DUPLICATE KEY UPDATE en lot

### `MoyenneRepository`
- `findMatiereByEleve(eleveId, classeId, periodeId): array`
- `findGeneraleByClasse(classeId, periodeId): array`
- `findGeneraleByEleve(eleveId, classeId, periodeId): ?object`
- `findAnnuelleByClasse(classeId, annee): array`
- `getBulletinData(eleveId, classeId, periodeId): array` — requête complexe multi-tables
- `countElevesAvecMoyenne(classeId, periodeId): int`

### `ClassementRepository`
- `getRangsParMoyenne(classeId, periodeId): array` — trié DESC, avec dense ranking
- `updateRangsBatch(classeId, periodeId, array $rangs): void` — UPDATE en lot
- `getStatistiquesClasse(classeId, periodeId): object` — moy_classe, min, max, ecart_type

### `BulletinRepository`
- `findByClasse(classeId, periodeId): array`
- `findNonGeneres(classeId, periodeId): array`
- `findByEleve(eleveId): array`
- `countParStatut(classeId, periodeId): array`

### `DecisionRepository`
- `findByClasse(classeId, annee): array`
- `findByEleve(eleveId): array`
- `countParDecision(classeId, annee): array`

### `StatistiquesRepository`
- `tauxReussiteParClasse(annee, periodeId): array`
- `repartitionMentionsParClasse(classeId, periodeId): array`
- `evolutionMoyennesEleve(eleveId, annee): array`
- `topElevesClasse(classeId, periodeId, limit): array`
- `matieresPlusDifficiles(classeId, periodeId): array` — moyennes classe par matière, triées ASC

---

## 8. DTO

### `PeriodeScolaireDTO`
```
+ annee_scolaire: string           # regex \d{4}-\d{4}
+ type_periode: string             # 'trimestre' | 'semestre'
+ numero: int                      # 1-3 (trimestre) ou 1-2 (semestre)
+ nom: string                      # max 60
+ date_debut: ?string              # Y-m-d ou null
+ date_fin: ?string                # Y-m-d ou null, > date_debut
validate(): date_fin > date_debut si les deux présentes
           numero cohérent avec type_periode
```

### `EvaluationDTO`
```
+ matiere_id: int                  # > 0, matière active
+ classe_id: int                   # > 0
+ periode_id: int                  # > 0, période ouverte
+ type_evaluation_id: int          # > 0, type actif
+ professeur_id: ?int
+ intitule: string                 # max 150, required
+ date_evaluation: ?string         # Y-m-d ou null
+ coefficient: float               # 0.5 - 10.0
+ sur: float                       # 5.0 - 100.0
validate(): coefficient > 0, sur > 0, intitule not empty
```

### `NoteDTO`
```
+ evaluation_id: int
+ eleve_id: int
+ note: ?float                     # null si absent/non saisie
+ absent: bool
+ absent_justifie: bool
+ dispense: bool
+ observation: ?string             # max 255
validate(): si not absent et not dispense → note must be <= evaluation.sur
           si absent = true → note doit être null
           note >= 0
```

### `NotesBulkDTO`
```
+ evaluation_id: int
+ notes: NoteDTO[]                 # tableau indexé par eleve_id
fromRequest(data): itère $_POST['notes'] et $_POST['absents']
validate(): evaluation_id > 0, notes non vide
```

### `AppreciationDTO`
```
+ eleve_id: int
+ matiere_id: int
+ periode_id: int
+ texte: string                    # max 500, required
validate(): texte not empty, max 500
```

### `DecisionDTO`
```
+ eleve_id: int
+ classe_id: int
+ annee_scolaire: string
+ decision: string                 # ENUM values
+ motif: ?string
validate(): decision IN ['admis','redoublant','passage_conditionnel','oriente','exclu','en_attente']
```

### `RattrapageNoteDTO`
```
+ session_id: int
+ matiere_id: int
+ eleve_id: int
+ note: ?float
+ absent: bool
+ observation: ?string
validate(): note >= 0 si présente
```

### `EvaluationFiltersDTO`
```
+ classeId: int                    # 0 = tous
+ periodeId: int                   # 0 = tous
+ matiereId: int                   # 0 = toutes
+ professeurId: int                # 0 = tous
+ statut: string                   # '' = tous
+ q: string
+ page: int                        # default 1
+ perPage: int                     # default 25
```

---

## 9. Policies

### `EvaluationPolicy`

| Méthode | Logique |
|---|---|
| `canView(user, ?eval)` | Permission `academique.evaluations.view` OU (rôle=enseignant ET eval.professeur_id = user.prof_id) |
| `canCreate(user, classeId, matiereId)` | Permission `academique.evaluations.create` ET (admin OU enseignant affecté à cette matière/classe via `enseignements`) |
| `canUpdate(user, eval)` | Même que canCreate + eval.statut ≠ cloturee |
| `canCloturer(user, eval)` | Permission `academique.evaluations.manage` OU (enseignant propriétaire ET notes complètes) |
| `canDelete(user, eval)` | Permission `academique.evaluations.manage` ET eval.statut = brouillon ET 0 notes saisies |

### `NotePolicy`

| Méthode | Logique |
|---|---|
| `canSaisir(user, eval)` | Permission `academique.notes.saisir` OU (enseignant affecté = eval.professeur_id) + période.notes_saisie_ouverte = 1 + eval.statut ≠ cloturee |
| `canManage(user)` | Permission `academique.notes.manage` (admin/direction) |
| `canImporter(user)` | Permission `academique.import` |
| `canExporter(user)` | Permission `academique.export` |

### `BulletinPolicy`

| Méthode | Logique |
|---|---|
| `canGenerer(user)` | Permission `academique.bulletins.generer` (direction/admin) |
| `canView(user, ?bulletin)` | Permission `academique.bulletins.view` OU (rôle=parent ET bulletin.eleve appartient à sa famille via FamillePolicy) OU (rôle=enseignant ET enseignant de la classe) |
| `canPrint(user)` | Permission `academique.bulletins.print` |

### `PeriodePolicy`

| Méthode | Logique |
|---|---|
| `canCreate(user)` | Permission `academique.periodes.manage` (direction uniquement) |
| `canFermer(user)` | Permission `academique.periodes.manage` |
| `canVerrouiller(user)` | Permission `academique.periodes.manage` |
| `canRouvrir(user)` | Permission `academique.admin` (super-admin uniquement) |

### `DecisionPolicy`

| Méthode | Logique |
|---|---|
| `canProposer(user)` | Permission `academique.decisions.attribuer` |
| `canAttribuer(user)` | Permission `academique.decisions.attribuer` |
| `canConfirmer(user)` | Permission `academique.decisions.confirmer` (admin uniquement) |

---

## 10. Événements

| Event | Propriétés | Dispatché depuis |
|---|---|---|
| `EvaluationCreee` | evaluationId, matiereId, classeId, periodeId, createdById | `EvaluationService::creer()` |
| `EvaluationCloturee` | evaluationId, classeId, periodeId, matiereId, nbNotes, clotureeById | `EvaluationService::cloturer()` |
| `NotesSaisies` | evaluationId, classeId, periodeId, matiereId, nbNotes, saisieById | `NoteService::saisirBulk()` |
| `NotesImportees` | evaluationId, totalImportees, totalErreurs, fichier, importeById | `NoteService::importerCsv()` |
| `MoyenneCalculee` | eleveId, classeId, periodeId, moyenne, rang, mention | `MoyenneService::recalculerMoyenneGenerale()` |
| `BulletinGenere` | bulletinId, eleveId, classeId, periodeId, genereById | `BulletinService::genererBulletin()` |
| `BulletinsBatchGeneres` | classeId, periodeId, total, erreurs, genereById | `BulletinService::genererBatch()` |
| `DecisionAttribuee` | eleveId, annee, decision, decidePar | `DecisionService::attribuerDecision()` |
| `RattrapageSessionOuverte` | sessionId, annee, nbEligibles, creePar | `RattrapageService::creerSession()` |
| `RattrapageNoteSaisie` | sessionId, matiereId, eleveId, note, saisieById | `RattrapageService::saisirNote()` |

**Handlers :**

| Handler | Gère les events |
|---|---|
| `EvaluationHandler` | EvaluationCreee, EvaluationCloturee → logCreate, logUpdate via AuditService |
| `NoteHandler` | NotesSaisies, NotesImportees → logCreate via AuditService |
| `MoyenneHandler` | MoyenneCalculee → logUpdate via AuditService ; futur cache invalidation |
| `BulletinHandler` | BulletinGenere, BulletinsBatchGeneres → logCreate, optionnel NotificationService |
| `DecisionHandler` | DecisionAttribuee → logCreate via AuditService ; optionnel NotificationService parent |

---

## 11. Permissions RBAC

### Définition des 16 permissions

| Permission | Description | Rôles suggérés |
|---|---|---|
| `academique.view` | Voir l'index, les listes d'évaluations et de notes | Tous les rôles authentifiés |
| `academique.evaluations.create` | Créer une évaluation pour ses propres matières | Enseignant, Admin |
| `academique.evaluations.manage` | Gérer toutes les évaluations (CRUD complet) | Admin, Direction |
| `academique.notes.saisir` | Saisir les notes de ses propres évaluations | Enseignant, Admin |
| `academique.notes.manage` | Modifier toutes les notes (y compris les autres enseignants) | Admin, Direction |
| `academique.notes.import` | Importer des notes via CSV | Enseignant, Admin |
| `academique.notes.export` | Exporter les notes en CSV/Excel | Enseignant, Admin, Direction |
| `academique.moyennes.recalculer` | Déclencher manuellement un recalcul de moyennes | Admin, Direction |
| `academique.bulletins.generer` | Générer les bulletins PDF | Admin, Direction |
| `academique.bulletins.view` | Voir et télécharger les bulletins | Admin, Direction, Enseignant (sa classe), Parent (son enfant) |
| `academique.bulletins.print` | Imprimer les bulletins | Admin, Direction |
| `academique.decisions.attribuer` | Proposer et saisir les décisions de fin d'année | Direction, Admin |
| `academique.decisions.confirmer` | Rendre une décision définitive (irréversible) | Admin |
| `academique.periodes.manage` | Créer, fermer, verrouiller les périodes scolaires | Direction, Admin |
| `academique.rattrapage.manage` | Gérer les sessions de rattrapage | Direction, Admin |
| `academique.stats.view` | Consulter les statistiques académiques | Admin, Direction, Enseignant |

---

## 12. Interfaces Utilisateur

### 12.1 Navigation principale

```
/v2/academique/
├── periodes/
│   ├── (index)     — Liste des périodes par année scolaire
│   ├── create      — Créer une période
│   ├── {id}        — Détail + workflow (fermer/verrouiller)
│   └── {id}/edit   — Modifier
│
├── evaluations/
│   ├── (index)     — Liste filtrée (classe, période, matière, statut)
│   ├── create      — Créer une évaluation
│   ├── {id}        — Fiche évaluation + stats
│   ├── {id}/edit   — Modifier
│   ├── {id}/notes  — Grille de saisie des notes
│   └── {id}/import — Import CSV
│
├── moyennes/
│   ├── (index)     — Sélecteur classe + période
│   ├── classe      — Tableau double-entrée élèves × matières
│   ├── classement  — Classement par période
│   └── eleve/{id}  — Historique et évolution d'un élève
│
├── bulletins/
│   ├── (index)     — Sélecteur classe + période + statuts
│   ├── apercu/{id} — Aperçu HTML d'un bulletin
│   ├── print/{id}  — Version impression
│   └── batch       — Génération en lot
│
├── decisions/
│   ├── fin-annee   — Tableau de délibération par classe
│   └── {id}/edit   — Modifier une décision
│
├── rattrapage/
│   ├── (index)     — Liste des sessions
│   ├── create      — Nouvelle session
│   ├── {id}        — Détail session + liste éligibles
│   └── {id}/notes  — Saisie des notes de rattrapage
│
└── statistiques/
    ├── (index)     — Dashboard académique
    ├── reussite    — Taux de réussite par classe/matière
    └── repartition — Graphiques mentions
```

### 12.2 Vues clés — Description fonctionnelle

**`evaluations/notes` (Grille de saisie) :**
- Tableau : colonne 1 = Élève (nom, prénom, matricule), colonne 2 = Note (/sur), colonne 3 = Absent, colonne 4 = Justifié, colonne 5 = Dispensé, colonne 6 = Observation
- Saisie via `<input type="number" step="0.25">` avec validation côté client (0 ≤ note ≤ sur)
- Cochage "Absent" → grise et vide le champ note
- Soumission AJAX recommandée (optionnel phase 2.0 → implémentation classique acceptable en 2.1)
- Raccourci clavier Tab pour naviguer de cellule en cellule

**`moyennes/classe` (Tableau double-entrée) :**
- Lignes = élèves (triés par rang)
- Colonnes = matières (avec coefficient en en-tête)
- Cellule = moyenne matière, colorée par seuil (rouge < 10, orange 10-12, vert ≥ 12)
- Dernière colonne = Moyenne générale + Rang + Mention
- Export Excel et PDF depuis cette vue

**`bulletins/apercu` (Aperçu HTML) :**
- Mise en page fidèle au PDF (logo école, en-tête, tableau matières, stats classe, mention, appréciation générale, signature)
- Bouton "Générer PDF" et "Imprimer"
- Indicateur de version (hash) pour savoir si la version PDF est à jour

**`decisions/fin-annee` :**
- Tableau : Élève | Moyenne annuelle | Rang | Mention | Décision proposée | Décision saisie | Définitif
- Décision proposée calculée automatiquement
- Cellule "Décision saisie" = `<select>` inline
- Bouton "Tout confirmer" (admin uniquement, irréversible)

---

## 13. Flux Métier Principaux

### Flux 1 — Saisie des notes et recalcul

```
Enseignant crée une Évaluation (brouillon)
    → [facultatif] Publie l'évaluation (visible parents)
    → Accède à la grille de saisie /v2/academique/evaluations/{id}/notes
    → Saisit les notes (bulk POST)
    → NoteService::saisirBulk()
          → valide notes (0 ≤ note ≤ sur)
          → INSERT ON DUPLICATE KEY UPDATE dans notes_v2
          → Dispatch NotesSaisies
          → MoyenneService::recalculerDepuisEvaluation()
              → recalculerMoyennesMatieresEleve() pour chaque élève de la classe
              → recalculerMoyenneGenerale() pour chaque élève
              → ClassementService::recalculerRangsPeriode()
              → Dispatch MoyenneCalculee pour chaque élève
    ← Flash success "Notes enregistrées, moyennes recalculées"
    → Peut clôturer l'évaluation (notes figées)
```

### Flux 2 — Import CSV des notes

```
Enseignant → /v2/academique/evaluations/{id}/import
    → Upload fichier CSV
    → NoteService::importerCsv()
          → Parse CSV (BOM, délimiteur auto)
          → Résout les élèves par matricule ou eleve_id
          → Valide chaque ligne
          → Insère valides en transaction (INSERT ON DUPLICATE KEY UPDATE)
          → Dispatch NotesImportees
    ← Page résultat : tableau importées / erreurs / ignorées
```

### Flux 3 — Fermeture de période et génération des bulletins

```
Direction → Ferme la période (PeriodeScolaireService::fermer())
    → Statut = fermee, notes_saisie_ouverte = 0
    → Toute tentative de saisie enseignant → 403

Direction → /v2/academique/bulletins/batch?classe_id=X&periode_id=Y
    → BulletinService::genererBatch()
          → Pour chaque élève de la classe :
              → BulletinService::getBulletinData()
              → Génération PDF
              → Stockage fichier storage/bulletins/{annee}/{periode}/{eleve_id}.pdf
              → Upsert bulletins (statut=genere, hash, chemin)
              → Dispatch BulletinGenere
    ← Rapport : X bulletins générés, Y erreurs
    → Direction peut imprimer en lot
```

### Flux 4 — Fin d'année et délibération

```
[Après clôture du T3 / S2]

Admin → MoyenneService::recalculerMoyennesAnnuellesClasse(classeId, annee)
    → Agrège moyennes_generales_v2 des 3 périodes
    → Upsert moyennes_annuelles (moyenne, rang, mention)

Direction → /v2/academique/decisions/fin-annee?classe_id=X&annee=Y
    → DecisionService::proposerDecisions()
          → Pour chaque élève : applique règles §17.3
          → Retourne décisions proposées (non persistées)
    → Direction ajuste manuellement les décisions proposées
    → Sauvegarde (attribuerDecision())
    → Confirm → est_definitif = 1
```

### Flux 5 — Rattrapage

```
[Après délibération, certains élèves en "passage_conditionnel"]

Direction → RattrapageService::creerSession()
    → Crée sessions_rattrapage
    → Identifie élèves éligibles (moyenne annuelle entre seuil_eligible et 10)
    → Dispatch RattrapageSessionOuverte

Direction → Ajoute matières concernées (rattrapage_matieres)

Enseignant → Saisit notes de rattrapage (/v2/academique/rattrapage/{id}/notes)
    → RattrapageService::saisirNote()
    → Upsert notes_rattrapage

Direction → RattrapageService::cloturerSession()
    → calculerDecisionsPostRattrapage()
          → Pour chaque élève : note_rattr ≥ seuil → admis, sinon → redoublant
          → Update decisions_fin_annee.est_definitif = 1
```

---

## 14. API Futures (V2.1+)

Ces endpoints sont prévus pour une intégration future avec l'application mobile ou des systèmes tiers.

| Endpoint | Méthode | Description |
|---|---|---|
| `/api/v2/academique/evaluations` | GET | Liste paginée des évaluations avec filtres |
| `/api/v2/academique/evaluations/{id}/notes` | GET | Notes d'une évaluation |
| `/api/v2/academique/evaluations/{id}/notes` | POST | Saisie de notes (JSON) |
| `/api/v2/academique/eleves/{id}/moyennes` | GET | Moyennes d'un élève pour toutes les périodes |
| `/api/v2/academique/bulletins/{id}` | GET | Données structurées d'un bulletin (JSON) |
| `/api/v2/academique/classes/{id}/classement` | GET | Classement d'une classe |
| `/api/v2/academique/stats/reussite` | GET | Taux de réussite par classe/période |

**Format des réponses :** JSON `{"data": ..., "meta": {...}, "errors": null}`  
**Authentification :** Token Bearer (à définir lors de l'implémentation Phase 3.x)

---

## 15. Dépendances avec les Autres Modules

### Dépendances en lecture (FK vers tables Scolarité)

| Table consommée | Module propriétaire | Usage dans Académique |
|---|---|---|
| `eleves` | Scolarité V2 | Notes, moyennes, bulletins, décisions |
| `classes` | Scolarité V2 | Contexte classe pour toutes les entités |
| `matieres` | Scolarité V2 | Matière liée à une évaluation ; coefficient de référence |
| `enseignements` | Scolarité V2 (AffectationService) | Vérification qu'un enseignant peut créer une évaluation |
| `professeurs` | V1 (inchangé) | Identification de l'auteur d'une évaluation |

### Dépendances vers d'autres modules

| Module | Nature de la dépendance |
|---|---|
| **Emploi du Temps V2** (futur) | Les évaluations peuvent référencer un créneau horaire (FK optionnel) |
| **Reporting V2** | `StatistiquesAcademiqueService::statsGlobalesAnnee()` alimente le dashboard |
| **Notification** | `BulletinHandler` et `DecisionHandler` peuvent déclencher des notifications aux parents |
| **Paramètres V2** | Seuils de passage, mentions, formule de calcul de la moyenne annuelle — configurable |
| **Comptabilité** | Les décisions de redoublement peuvent déclencher une mise à jour des frais (signal) |

### Contrat d'interface avec le Module Scolarité

Le module Académique ne peut PAS :
1. Modifier `eleves.classe_id` (c'est InscriptionService)
2. Modifier `enseignements` (c'est AffectationService)
3. Créer ou modifier des `matieres`

Le module Académique PEUT :
1. Lire toutes les tables de Scolarité en SELECT
2. Écouter les events Scolarité (`InscriptionValidee` → créer une entrée dans `bulletins` vide)

---

## 16. Stratégie de Migration V1 → V2

### Inventaire V1 existant

| Table V1 | Lignes approx. | Usage V2 |
|---|---|---|
| `periodes` | Faible | Enrichie via ALTER TABLE + mappée vers `periodes_scolaires` |
| `controles` | Moyen | Conservée intacte. V2 crée `evaluations`. Migration en script. |
| `notes` | Élevé | Conservée intacte. V2 crée `notes_v2`. Migration en script. |
| `moyennes_matieres` | Élevé | Conservée intacte. V2 crée `moyennes_matieres_v2`. |
| `moyennes_generales` | Moyen | Conservée intacte. V2 crée `moyennes_generales_v2`. |

### Principe général

```
V1 routes : /notes/*, /bulletins/*    → INCHANGÉES, fonctionnent sur les tables V1
V2 routes : /v2/academique/*          → Nouvelles tables V2

Coexistence garantie :
  - enabled: false dans module.json jusqu'à validation complète
  - 0 modification de tables V1
  - Routes V1 non modifiées
```

### Script de migration V1 → V2 (à écrire en Phase 2.x)

```
Script : scripts/migrate_academique_v1_to_v2.php

Étape 1 — Migrer les périodes
    INSERT INTO periodes_scolaires (...)
    SELECT id, annee_scolaire, 'trimestre', ... FROM periodes

Étape 2 — Migrer les types d'évaluations
    INSERT INTO types_evaluations (code, nom, ponderation)
    VALUES pour les 5 types de ControleModel::TYPES

Étape 3 — Migrer les controles → evaluations
    INSERT INTO evaluations (matiere_id, classe_id, periode_id, intitule, coefficient, sur, statut='cloturee', ...)
    SELECT c.*, pe.id AS periode_v2_id
    FROM controles c
    JOIN periodes_scolaires pe ON pe.annee_scolaire = (SELECT p.annee_scolaire FROM periodes p WHERE p.id = c.periode_id)
    -- Associer le type d'évaluation via c.type → types_evaluations.code

Étape 4 — Migrer les notes
    INSERT INTO notes_v2 (evaluation_id, eleve_id, note, absent, ...)
    SELECT n.eleve_id, e.id AS evaluation_id, n.note, n.absent
    FROM notes n
    JOIN evaluations e ON e.matiere_id = (controles join...) ...

Étape 5 — Migrer les moyennes (option A : recalculer depuis notes_v2)
    → Appeler MoyenneService::recalculerClasse() pour chaque classe × période

Étape 6 — Vérification de cohérence
    → Comparer moyennes_generales (V1) vs moyennes_generales_v2 (V2)
    → Tolérance : ±0.01 (arrondi)
    → Rapport d'écarts
```

### Plan de bascule progressive

| Phase | Action | Condition |
|---|---|---|
| Phase 2.0 | Blueprint seul | Aucune |
| Phase 2.1 | Implémenter PeriodeScolaireService + EvaluationService + NoteService | Après GO du blueprint |
| Phase 2.2 | MoyenneService + ClassementService | Après tests 2.1 |
| Phase 2.3 | BulletinService + AppreciationService | Après tests 2.2 |
| Phase 2.4 | DecisionService + RattrapageService | Après tests 2.3 |
| Phase 2.5 | StatistiquesAcademiqueService + migration V1 | Après tests 2.4 |
| Phase 2.6 | Module Freeze Académique | Après audit complet |
| Phase 3.0 | Bascule V2 active, V1 en lecture seule | Après validation direction |

---

## 17. Définition des Règles de Calcul

### 17.1 Moyenne par Matière (par période)

```
Données d'entrée :
  Pour un élève E, une matière M, une classe C, une période P :
  Évaluations = {e₁, e₂, ..., eₙ} (évaluations de M dans C sur P)
  Pour chaque évaluation eᵢ :
    - nᵢ = note obtenue (sur barème bᵢ, ramené sur 20)
    - cᵢ = coefficient de l'évaluation
    - absentᵢ = booléen (absent non justifié)
    - dispenséᵢ = booléen (exclu du calcul)

Règle de calcul :

  note_normalisée(i) = (nᵢ / bᵢ) × 20      # Ramener sur 20
    si absentᵢ = true → note_normalisée = 0   # Absent = 0
    si dispenséᵢ = true → exclure eᵢ entièrement

  Moyenne_Matière(E, M, C, P) =
    Σ [note_normalisée(i) × cᵢ]  pour i où dispenséᵢ = false
    ─────────────────────────────────────────────────────────
    Σ [cᵢ]                        pour i où dispenséᵢ = false

  Si Σ[cᵢ] = 0 (tous dispensés ou aucune évaluation) → moyenne = NULL

  Arrondi : 2 décimales (ROUND(x, 2))
```

### 17.2 Moyenne Générale (par période)

```
Données d'entrée :
  Pour un élève E, une classe C, une période P :
  Matières = {M₁, M₂, ..., Mₘ} avec moyennes_matieres calculées
  Pour chaque matière Mⱼ :
    - moyⱼ = Moyenne_Matière(E, Mⱼ, C, P) — peut être NULL
    - coefⱼ = coefficient de Mⱼ (depuis matieres.coefficient)

Règle de calcul :

  Moyenne_Générale(E, C, P) =
    Σ [moyⱼ × coefⱼ]  pour j où moyⱼ IS NOT NULL
    ────────────────────────────────────────────────
    Σ [coefⱼ]          pour j où moyⱼ IS NOT NULL

  Si aucune matière avec moyenne → NULL

  Arrondi : 2 décimales
```

### 17.3 Moyenne Annuelle

**Système Trimestres (3 périodes) :**
```
  Option A — Égale pondération (défaut) :
    Moyenne_Annuelle = (MG_T1 + MG_T2 + MG_T3) / 3

  Option B — Pondération progressive (configurable via Paramètres V2) :
    Moyenne_Annuelle = (MG_T1 × 1 + MG_T2 × 1 + MG_T3 × 2) / 4
    (Troisième trimestre compte double — pratique algérienne courante)

  Arrondi : 2 décimales
```

**Système Semestres (2 périodes) :**
```
  Moyenne_Annuelle = (MG_S1 + MG_S2) / 2
  Arrondi : 2 décimales
```

> **Paramètre configuré dans Paramètres V2 :** `academique.formule_annuelle` = `'egalite'` | `'progressive'`

### 17.4 Classement et Rangs

```
Algorithme : Dense Ranking (rangs sans trous)

  Entrée : liste d'élèves avec leurs moyennes générales pour (C, P)
  Tri : DESC par moyenne, puis ASC par nom (départage)

  Rang ← 1
  Pour chaque élève dans la liste triée :
    Si première occurrence ou moyenne DIFFÉRENTE du précédent :
      Rang ← position courante (1-based, comptant toutes les occurrences)
    Assigner Rang à l'élève

  Exemple :
    Alice  14.50 → Rang 1
    Bruno  14.50 → Rang 1    (même moyenne = même rang)
    Carole 13.25 → Rang 3    (dense ranking : position 3, pas 2)
    David  11.00 → Rang 4
```

### 17.5 Mentions

| Seuil | Mention | Couleur UI |
|---|---|---|
| ≥ 18.00 | Excellent | Violet (`#6366F1`) |
| ≥ 16.00 | Très Bien | Vert (`#10B981`) |
| ≥ 14.00 | Bien | Bleu (`#3B82F6`) |
| ≥ 12.00 | Assez Bien | Cyan (`#06B6D4`) |
| ≥ 10.00 | Passable | Ambre (`#F59E0B`) |
| < 10.00 | Insuffisant | Rouge (`#EF4444`) |

> **Note V1 :** La V1 n'a pas de mention "Excellent". V2 l'ajoute. La migration doit recalculer les mentions pour les données historiques migrées.

### 17.6 Décisions de Fin d'Année Automatiques

Ces décisions sont des **propositions** calculées automatiquement par `DecisionService::proposerDecisions()`. La direction peut les modifier avant confirmation.

| Condition | Décision proposée |
|---|---|
| Moyenne annuelle ≥ 10.00 | `admis` |
| seuil_rattrapage ≤ Moyenne < 10.00 | `passage_conditionnel` (éligible rattrapage) |
| Moyenne < seuil_rattrapage | `redoublant` |

**Paramètres configurables (Paramètres V2) :**

| Paramètre | Valeur défaut | Description |
|---|---|---|
| `academique.seuil_admission` | 10.00 | Moyenne minimale pour être admis directement |
| `academique.seuil_rattrapage` | 8.00 | Moyenne minimale pour avoir droit au rattrapage |
| `academique.matiere_bloquante_actif` | false | Si true : une matière < N bloque l'admission même avec moy ≥ 10 |
| `academique.matiere_bloquante_seuil` | 6.00 | Seuil de la matière bloquante |

### 17.7 Décisions Post-Rattrapage

```
Pour chaque élève ayant passé le rattrapage :
  note_rattr = note obtenue en rattrapage (sur 20, normalisée)

  Si note_rattr >= seuil_matiere → matière rattrapée
  
  Décision finale :
    Si TOUTES les matières de rattrapage sont rattrapées → admis
    Sinon → redoublant (définitif)

  Note retenue au dossier :
    Option A : note_rattr (si supérieure)
    Option B : MAX(moyenne_annuelle_matiere, note_rattr)
    → Configurable via paramètre : 'academique.rattrapage_note_retenue'
```

### 17.8 Statistiques de la Classe (pour les bulletins)

Pour chaque période (C, P), les données suivantes sont calculées et affichées sur chaque bulletin :

```
  stats_classe = {
    nb_eleves   : COUNT(élèves ayant une moyenne générale),
    moy_classe  : AVG(moyennes_generales_v2.moyenne),
    moy_min     : MIN(moyennes_generales_v2.moyenne),
    moy_max     : MAX(moyennes_generales_v2.moyenne),
    taux_reussite : COUNT(moyenne ≥ 10) / nb_eleves × 100,
    ecart_type  : SQRT(AVG((moyenne - moy_classe)²))   # optionnel
  }
```

---

## Synthèse Blueprint

### Résumé des entités

| Entité | Table | Nouvelle V2 | Relation principale |
|---|---|---|---|
| Période scolaire | `periodes_scolaires` | ✅ | Agrège les évaluations |
| Type d'évaluation | `types_evaluations` | ✅ | Caractérise une évaluation |
| Évaluation | `evaluations` | ✅ | Matière × Classe × Période |
| Note | `notes_v2` | ✅ | Élève × Évaluation |
| Appréciation | `appreciations` | ✅ | Élève × Matière × Période |
| Moyenne matière | `moyennes_matieres_v2` | ✅ | Calculée depuis notes_v2 |
| Moyenne générale | `moyennes_generales_v2` | ✅ | Calculée depuis moyennes_matieres_v2 |
| Moyenne annuelle | `moyennes_annuelles` | ✅ | Agrège moyennes_generales_v2 |
| Bulletin | `bulletins` | ✅ | Snapshot d'une période pour un élève |
| Décision fin année | `decisions_fin_annee` | ✅ | Par élève × année scolaire |
| Session rattrapage | `sessions_rattrapage` | ✅ | Par année scolaire |
| Matière rattrapage | `rattrapage_matieres` | ✅ | Session × Matière × Classe |
| Note rattrapage | `notes_rattrapage` | ✅ | Session × Matière × Élève |

**Total tables V2 :** 13 nouvelles + 1 ALTER TABLE (periodes)  
**Total services :** 10  
**Total repositories :** 8  
**Total DTOs :** 9  
**Total events :** 10  
**Total permissions :** 16  
**Total routes prévues :** ~45

### Matrice de complexité

| Composant | Complexité | Effort estimé |
|---|---|---|
| PeriodeScolaireService | Faible | ~1 jour |
| EvaluationService | Moyen | ~1.5 jours |
| NoteService (saisie + import) | Moyen | ~2 jours |
| MoyenneService | **Élevé** | ~3 jours |
| ClassementService | Moyen | ~1 jour |
| AppreciationService | Faible | ~0.5 jour |
| BulletinService (PDF) | **Élevé** | ~3 jours |
| DecisionService | Moyen | ~1.5 jours |
| RattrapageService | Moyen | ~1.5 jours |
| StatistiquesService | Moyen | ~2 jours |
| Vues (30+ interfaces) | **Élevé** | ~5 jours |
| Migration V1→V2 + tests | Moyen | ~3 jours |
| **TOTAL ESTIMÉ** | | **~25 jours** |

---

*Produit le 2026-06-30 — SCOLARIS V2 Phase 2.0 — Blueprint uniquement*  
*Aucun fichier de code créé. Aucune migration exécutée.*  
*En attente de validation avant implémentation de la Phase 2.1.*
