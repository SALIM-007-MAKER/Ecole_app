# RAPPORTS & BUSINESS INTELLIGENCE V2 — BLUEPRINT
**Phase 11.1 — Module Rapports & BI**
**Date :** 2026-07-03
**Statut :** BLUEPRINT — Aucune implémentation

---

## 1. Vision & Positionnement

Le module Rapports & BI est la **plateforme décisionnelle centrale** de SCOLARIS V2. Il agrège les données produites par les 10 modules existants pour en extraire des indicateurs, tendances et rapports à destination de tous les profils utilisateurs.

### Principes directeurs

| Principe | Application |
|---|---|
| **Consommation seule** | Zéro duplication de logique métier. Lecture des services existants uniquement. |
| **Read-only** | Aucune écriture sur les tables des autres modules. |
| **Multi-domaine** | 8 domaines analytiques alignés sur les 10 modules. |
| **Multi-tenant** | `etablissement_id` systématique. Vue croisée multi-établissements pour admins groupe. |
| **Progressive** | V2 = opérationnel. V3 = Data Warehouse + IA. L'architecture V2 le prépare. |

---

## 2. Architecture globale

```
┌─────────────────────────────────────────────────────────────────────┐
│                    MODULE RAPPORTS & BI V2                          │
│                                                                     │
│  Controllers (thin)                                                 │
│  ├── DashboardController    ← 8 tableaux de bord contextuels       │
│  ├── KpiController          ← indicateurs temps réel               │
│  ├── ExportController       ← génération PDF / Excel / CSV         │
│  ├── PlanificationController← rapports programmés                  │
│  └── ApiAnalyticsController ← API publique (V3-ready)              │
│                                                                     │
│  Services                                                           │
│  ├── DashboardService       ← orchestre les données par contexte   │
│  ├── KpiService             ← calcule et agrège les KPIs           │
│  ├── TendanceService        ← séries temporelles & comparaisons    │
│  ├── ReportGeneratorService ← moteur PDF / Excel / CSV             │
│  ├── PlanificationService   ← gère les rapports planifiés          │
│  ├── SnapshotService        ← captures périodiques → bi_kpi_snapshots│
│  ├── DataAggregatorService  ← agrégation multi-domaine             │
│  └── PredictionService      ← STUB V3 (IA prédictive)             │
│                                                                     │
│  Analytics Repositories (read-only, SQL optimisés)                 │
│  ├── ScolariteAnalyticsRepository                                  │
│  ├── AcademiqueAnalyticsRepository                                 │
│  ├── FinanceAnalyticsRepository                                    │
│  ├── VieScolaireAnalyticsRepository                               │
│  ├── RHAnalyticsRepository                                         │
│  ├── BibliothequAnalyticsRepository                               │
│  ├── InventaireAnalyticsRepository                                 │
│  ├── SnapshotRepository      ← bi_kpi_snapshots                   │
│  ├── PlanificationRepository ← bi_rapports_planifies               │
│  └── ExportRepository        ← bi_exports                          │
│                                                                     │
│  Interfaces (préparation V3)                                        │
│  ├── DataWarehouseInterface  ← STUB (connexion DWH externe)        │
│  ├── ETLInterface            ← STUB (Extract-Transform-Load)       │
│  └── PredictionInterface     ← STUB (ML / IA)                     │
│                                                                     │
│  Base de données : 5 tables bi_*                                    │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼ (read-only, aucun write)
┌─────────────────────────────────────────────────────────────────────┐
│  10 Modules existants — sources de données                          │
│  Scolarité · Académique · Finance · Vie Scolaire · RH              │
│  Documents · Communication · Bibliothèque · Inventaire · Core      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 3. Base de données — 5 tables `bi_*`

Les 10 modules existants conservent leurs tables. Le module BI ne crée que ce qui est nécessaire à ses propres fonctions : planification, historique, snapshots et configuration.

### TABLE : `bi_tableaux_config`
*Configuration personnalisée des widgets par utilisateur et contexte.*

```sql
CREATE TABLE IF NOT EXISTS bi_tableaux_config (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    contexte         ENUM('direction','scolarite','academique','finance',
                          'rh','vie_scolaire','bibliotheque','inventaire') NOT NULL,
    widgets          JSON NOT NULL COMMENT 'Ordre et paramètres des widgets',
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_bi_config_user_ctx (user_id, contexte, etablissement_id),
    INDEX idx_bi_config_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### TABLE : `bi_rapports_planifies`
*Définition des rapports programmés (quotidiens, hebdomadaires, mensuels).*

```sql
CREATE TABLE IF NOT EXISTS bi_rapports_planifies (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom              VARCHAR(200) NOT NULL,
    domaine          VARCHAR(60) NOT NULL COMMENT 'scolarite|academique|finance|rh|...',
    type_export      ENUM('pdf','excel','csv') NOT NULL DEFAULT 'pdf',
    filtres          JSON NULL COMMENT 'Sérialisation de ReportFiltersDTO',
    frequence        ENUM('quotidien','hebdomadaire','mensuel') NOT NULL,
    jour_execution   TINYINT UNSIGNED NULL COMMENT '0-6 pour hebdo, 1-28 pour mensuel',
    heure_execution  TIME NOT NULL DEFAULT '06:00:00',
    destinataires    JSON NULL COMMENT 'Tableau d'emails',
    actif            TINYINT(1) NOT NULL DEFAULT 1,
    created_by       INT UNSIGNED NOT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       DATETIME NULL,

    INDEX idx_bi_plan_actif  (actif, frequence),
    INDEX idx_bi_plan_etab   (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### TABLE : `bi_rapport_executions`
*Historique des exécutions (planifiées ou manuelles).*

```sql
CREATE TABLE IF NOT EXISTS bi_rapport_executions (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rapport_planifie_id INT UNSIGNED NULL COMMENT 'NULL = export manuel',
    domaine             VARCHAR(60) NOT NULL,
    type_export         ENUM('pdf','excel','csv') NOT NULL,
    statut              ENUM('pending','en_cours','termine','erreur') NOT NULL DEFAULT 'pending',
    filtres             JSON NULL,
    debut_execution     DATETIME NULL,
    fin_execution       DATETIME NULL,
    fichier_path        VARCHAR(500) NULL,
    nb_lignes           INT UNSIGNED NULL,
    erreur_message      TEXT NULL,
    user_id             INT UNSIGNED NULL COMMENT 'NULL = tâche planifiée système',
    etablissement_id    INT UNSIGNED NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_bi_exec_plan FOREIGN KEY (rapport_planifie_id)
        REFERENCES bi_rapports_planifies(id) ON DELETE SET NULL,
    INDEX idx_bi_exec_statut (statut),
    INDEX idx_bi_exec_etab   (etablissement_id),
    INDEX idx_bi_exec_date   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### TABLE : `bi_kpi_snapshots`
*Captures périodiques des KPIs pour calcul de tendances.*

```sql
CREATE TABLE IF NOT EXISTS bi_kpi_snapshots (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domaine          VARCHAR(60) NOT NULL COMMENT 'scolarite|finance|rh|...',
    metrique         VARCHAR(100) NOT NULL COMMENT 'nb_eleves|taux_recouvrement|...',
    valeur           DECIMAL(15,4) NOT NULL,
    periode          CHAR(7) NOT NULL COMMENT 'Format YYYY-MM',
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_bi_snap (domaine, metrique, periode, etablissement_id),
    INDEX idx_bi_snap_domaine (domaine, etablissement_id),
    INDEX idx_bi_snap_periode (periode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### TABLE : `bi_exports`
*Registre des fichiers exportés (traçabilité RGPD, expiration).*

```sql
CREATE TABLE IF NOT EXISTS bi_exports (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domaine          VARCHAR(60) NOT NULL,
    type_export      ENUM('pdf','excel','csv') NOT NULL,
    filtres          JSON NULL,
    fichier_path     VARCHAR(500) NOT NULL,
    fichier_nom      VARCHAR(200) NOT NULL,
    nb_lignes        INT UNSIGNED NULL,
    expire_at        DATETIME NULL COMMENT 'Suppression auto après 30 jours',
    user_id          INT UNSIGNED NOT NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_bi_exp_user   (user_id),
    INDEX idx_bi_exp_etab   (etablissement_id),
    INDEX idx_bi_exp_expire (expire_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. DTOs

```
ReportFiltersDTO
├── etablissementId: int
├── anneeScolaire: string          // "2025-2026"
├── periodeDebut: ?string          // "2026-01-01"
├── periodeFin: ?string            // "2026-06-30"
├── classeId: ?int
├── niveau: ?string                // "6ème"|"Terminal"|...
├── userId: ?int
├── domaine: string
└── fromRequest(array): self

PlanificationDTO
├── nom: string
├── domaine: string
├── typeExport: string
├── filtres: array
├── frequence: string
├── jourExecution: ?int
├── heureExecution: string
├── destinataires: array
└── fromRequest(array): self

KpiDTO
├── domaine: string
├── metrique: string
├── valeur: float
├── unite: string                  // "%"|"€"|"nb"|"h"
├── tendance: string               // "hausse"|"baisse"|"stable"
├── variationPct: float
└── periode: string

DashboardMetricsDTO
├── contexte: string
├── kpis: array<KpiDTO>
├── graphiques: array              // données brutes pour Chart.js
├── alertes: array
├── timestamp: string
└── fromServices(string, array, int): self

SnapshotDTO
├── domaine: string
├── metrique: string
├── valeur: float
├── periode: string
├── etablissementId: int
└── fromRequest(array): self
```

---

## 5. Services

### 5.1 DashboardService
Orchestre les données par contexte utilisateur.

```
getDirectionDashboard(int $etab, ReportFiltersDTO $filters): DashboardMetricsDTO
  ├── effectif total élèves (Scolarité)
  ├── taux de recouvrement paiements (Finance)
  ├── taux d'absentéisme (Vie Scolaire)
  ├── masse salariale du mois (RH)
  ├── valeur stock global (Inventaire)
  └── alertes transversales

getScolariteDashboard(int $etab, ReportFiltersDTO $filters): DashboardMetricsDTO
  ├── nb inscrits / capacité par classe
  ├── répartition garçons/filles
  ├── nouvelles inscriptions du mois
  ├── dossiers incomplets
  └── taux de rétention

getAcademiqueDashboard(int $etab, ReportFiltersDTO $filters): DashboardMetricsDTO
  ├── moyenne générale par niveau
  ├── taux de réussite aux évaluations
  ├── classement meilleurs élèves
  ├── matières en difficulté (avg < seuil)
  └── bulletins générés / en attente

getFinanceDashboard(int $etab, ReportFiltersDTO $filters): DashboardMetricsDTO
  ├── CA du mois / objectif
  ├── impayés par tranche
  ├── solde de caisse
  ├── recettes vs dépenses (courbe)
  └── taux de recouvrement global

getRHDashboard(int $etab, ReportFiltersDTO $filters): DashboardMetricsDTO
  ├── effectif personnel
  ├── congés en cours / à venir
  ├── présences du jour
  ├── contrats expirant dans 30j
  └── heures supplémentaires cumulées

getVieScolaireDashboard(int $etab, ReportFiltersDTO $filters): DashboardMetricsDTO
  ├── absences du jour (justifiées / non justifiées)
  ├── retards de la semaine
  ├── incidents disciplinaires ouverts
  ├── emploi du temps conflits
  └── activités en cours / inscriptions

getBibliothequeDashboard(int $etab, ReportFiltersDTO $filters): DashboardMetricsDTO
  ├── prêts actifs / en retard
  ├── ouvrages les plus empruntés
  ├── taux d'occupation du catalogue
  └── retours attendus dans 7 jours

getInventaireDashboard(int $etab, ReportFiltersDTO $filters): DashboardMetricsDTO
  ├── articles en alerte stock
  ├── commandes en cours
  ├── maintenances dues ce mois
  ├── valeur nette comptable équipements
  └── affectations non retournées en retard
```

### 5.2 KpiService
Calcule les KPIs, compare les périodes.

```
getKpisByDomaine(string $domaine, int $etab, ReportFiltersDTO $filters): array<KpiDTO>
getTendances(string $domaine, string $metrique, int $mois, int $etab): array
comparePeriodesScol(string $annee1, string $annee2, int $etab): array
compareMultiEtab(array $etabIds, string $domaine): array  // admin groupe uniquement
getKpiGlobal(int $etab): array<KpiDTO>
```

### 5.3 TendanceService
Séries temporelles, évolutions, prévisions stub V3.

```
serieTemporelle(string $domaine, string $metrique, int $etab, int $mois): array
variationPeriode(string $metrique, string $p1, string $p2, int $etab): float
tendanceLineaire(array $series): array   // régression linéaire simple
prevision(string $metrique, int $etab, int $horizonMois): array  // STUB V3
```

### 5.4 ReportGeneratorService
Moteur de génération de rapports.

```
generatePdf(string $domaine, array $data, ReportFiltersDTO $filters): string  // path
generateExcel(string $domaine, array $data, ReportFiltersDTO $filters): string
generateCsv(string $domaine, array $data, ReportFiltersDTO $filters): string
getTemplate(string $domaine, string $type): string
getAvailableTemplates(): array
```

**Templates par domaine :**
| Domaine | Templates disponibles |
|---|---|
| direction | synthese_globale, rapport_annuel |
| scolarite | liste_inscrits, statistiques_inscriptions, fiche_classe |
| academique | resultats_classe, bulletin_masse, classement_niveau |
| finance | bilan_financier, releve_impayes, recettes_depenses |
| rh | etat_personnel, recap_conges, recap_presences |
| vie_scolaire | rapport_absences, bilan_discipline, recap_activites |
| bibliotheque | etat_prets, inventaire_catalogue, retards |
| inventaire | etat_stock, rapport_maintenances, amortissements |

### 5.5 PlanificationService
Gestion des rapports programmés.

```
planifier(PlanificationDTO $dto, int $userId, int $etab): int
lister(int $etab): array
trouver(int $id): ?array
activer(int $id, int $userId): void
desactiver(int $id, int $userId): void
supprimer(int $id, int $userId): void
executerMaintenant(int $id, int $userId, int $etab): int  // retourne execution_id
getHistorique(int $etab, int $limit = 100): array
executerPlanifies(): void  // appelé par cron, vérifie fréquence/heure
```

### 5.6 SnapshotService
Captures périodiques pour les tendances.

```
capturerDomaine(string $domaine, int $etab): void
capturerTous(int $etab): void        // appelé par cron mensuel
getTendanceSerie(string $domaine, string $metrique, int $etab, int $mois): array
calculerVariation(string $domaine, string $metrique, int $etab): float
nettoyerAnciens(int $joursRetention = 730): void
```

### 5.7 DataAggregatorService
Agrégation multi-domaine pour le dashboard Direction.

```
agregationGlobale(int $etab, string $periode): array
kpisTransversaux(int $etab): array
comparaisonMultiEtab(array $etabIds): array   // admin groupe
alertesConsolidees(int $etab): array
```

### 5.8 PredictionService (STUB V3)
Architecture préparée pour l'IA prédictive.

```
// STUB V3 — implémentation réelle via ML service externe
previsionEffectifs(int $etab, string $annee): float
risqueDecrochage(int $eleveId): float     // 0.0 → 1.0
risqueImpayes(int $eleveId): float        // 0.0 → 1.0
tendanceAcademique(int $classeId): array
```

---

## 6. Repositories Analytics (read-only)

Chaque repository effectue des requêtes SQL analytiques optimisées (GROUP BY, SUM, AVG, COUNT) sur les tables des modules existants. Aucune écriture.

### 6.1 ScolariteAnalyticsRepository
```
effectifParClasse(int $etab, ?string $annee): array
repartitionGenre(int $etab): array
evolutionInscriptions(int $etab, int $mois): array
tauxRetention(int $etab, string $anneeN, string $anneeNM1): float
dossiersIncomplets(int $etab): int
```
*Tables sources : `eleves`, `classes`, `inscriptions`, `familles`*

### 6.2 AcademiqueAnalyticsRepository
```
moyennesParNiveau(int $etab, string $periode): array
tauxReussiteParMatiere(int $etab, string $periode): array
classementGlobal(int $etab, string $periode): array
matieresDifficulte(int $etab, float $seuilMoyenne): array
distributionNotes(int $etab, ?int $classeId): array
evolutionMoyennes(int $etab, int $mois): array
```
*Tables sources : `notes`, `evaluations`, `periodes_scolaires`, `matieres`*

### 6.3 FinanceAnalyticsRepository
```
recettesParPeriode(int $etab, string $debut, string $fin): array
tauxRecouvrement(int $etab, ?string $periode): float
impayes(int $etab, ?string $statut): array
recettesVsDepenses(int $etab, int $mois): array
soldeCaisse(int $etab): float
repartitionTypesPaiements(int $etab): array
evolutionCA(int $etab, int $mois): array
```
*Tables sources : `finance_factures`, `finance_paiements`, `finance_depenses`, `finance_caisse`*

### 6.4 VieScolaireAnalyticsRepository
```
absencesParJour(int $etab, string $date): array
tauxAbsenteisme(int $etab, string $debut, string $fin): float
retardsParSemaine(int $etab): array
incidentsDisciplinaires(int $etab, ?string $statut): array
evolutionAbsences(int $etab, int $mois): array
activitesOccupation(int $etab): array
```
*Tables sources : `vs_absences`, `vs_retards`, `vs_discipline`, `vs_activites`, `vs_presences`*

### 6.5 RHAnalyticsRepository
```
effectifParDepartement(int $etab): array
congesEnCours(int $etab): array
contratsExpirants(int $etab, int $joursHorizon): array
tauxPresence(int $etab, string $mois): float
heuresSupplementaires(int $etab, string $mois): float
evaluationsEnCours(int $etab): array
```
*Tables sources : `rh_employes`, `rh_conges`, `rh_presences`, `rh_contrats`, `rh_evaluations`*

### 6.6 BibliothèqueAnalyticsRepository
```
pretsActifs(int $etab): int
pretsEnRetard(int $etab): array
ouvragesPlusEmpruntes(int $etab, int $limit): array
tauxOccupationCatalogue(int $etab): float
evolutionPrets(int $etab, int $mois): array
retardsAttendusSemaine(int $etab): array
```
*Tables sources : `biblio_prets`, `biblio_livres`, `biblio_exemplaires`*

### 6.7 InventaireAnalyticsRepository
```
articlesEnAlerte(int $etab): array
commandesEnCours(int $etab): array
maintenancesDuMois(int $etab): array
valeurNetteComptable(int $etab): float
mouvementsParType(int $etab, string $mois): array
affectationsNonRetournees(int $etab): array
```
*Tables sources : `inv_articles`, `inv_stocks`, `inv_commandes`, `inv_maintenances`, `inv_amortissements`, `inv_affectations`*

### 6.8 SnapshotRepository
```
create(SnapshotDTO $dto): int
byDomaine(string $domaine, int $etab, int $mois): array
lastByMetrique(string $domaine, string $metrique, int $etab): ?array
deleteOlderThan(string $date): void
```
*Table : `bi_kpi_snapshots`*

### 6.9 PlanificationRepository
```
all(int $etab, ?bool $actif): array
findById(int $id): ?array
create(array $data): int
updateActif(int $id, bool $actif): void
softDelete(int $id): void
getActifsAExecuter(string $frequence, int $heure): array
```
*Table : `bi_rapports_planifies`*

### 6.10 ExportRepository
```
create(array $data): int
byUser(int $userId): array
purgerExpires(): void
```
*Table : `bi_exports`*

---

## 7. Events (6)

```php
// RapportGenere
__construct(int $executionId, string $domaine, string $typeExport, int $userId, int $etablissementId)
toArray(): ['execution_id', 'domaine', 'type_export', 'user_id', 'etablissement_id']

// RapportPlanifie
__construct(int $planificationId, string $nom, string $frequence, int $userId, int $etablissementId)
toArray(): ['planification_id', 'nom', 'frequence', 'user_id', 'etablissement_id']

// RapportExecute
__construct(int $executionId, int $planificationId, string $statut, int $etablissementId)
toArray(): ['execution_id', 'planification_id', 'statut', 'etablissement_id']

// RapportExporte
__construct(int $exportId, string $type, string $domaine, int $userId, int $etablissementId)
toArray(): ['export_id', 'type', 'domaine', 'user_id', 'etablissement_id']

// KpiSnapshot
__construct(string $domaine, string $metrique, float $valeur, string $periode, int $etablissementId)
toArray(): ['domaine', 'metrique', 'valeur', 'periode', 'etablissement_id']

// DashboardConsulte
__construct(string $contexte, int $userId, int $etablissementId)
toArray(): ['contexte', 'user_id', 'etablissement_id']
```

---

## 8. Listeners (3)

### BiAuditListener
Trace toutes les consultations, générations et exports.
Gère : `RapportGenere`, `RapportExporte`, `DashboardConsulte`, `RapportExecute`

### BiNotificationListener
Envoie notifications de rapports planifiés terminés (email / communication interne).
Gère : `RapportExecute` (statut=termine → notif destinataires), `RapportExecute` (statut=erreur → alerte admin)

### BiSnapshotListener
Déclenche la capture de snapshot après certains événements.
Gère : `KpiSnapshot` → persistance en `bi_kpi_snapshots`

---

## 9. Policies

### RapportPolicy
```
viewDashboard(string $contexte): bool        // vérifie rapports.{contexte}
exportPdf(): bool                            // rapports.export.pdf
exportExcel(): bool                          // rapports.export.excel
exportCsv(): bool                            // rapports.export.csv
planifier(): bool                            // rapports.planifier
viewAll(): bool                              // rapports.admin
viewMultiEtab(): bool                        // rapports.multi_etab (admin groupe)
```

---

## 10. RBAC — 16 permissions `rapports.*`

| Permission | Description | Rôles par défaut |
|---|---|---|
| `rapports.view` | Accès au hub rapports | admin, directeur, comptable, secretaire, enseignant |
| `rapports.direction` | Dashboard direction | admin, directeur |
| `rapports.scolarite` | Dashboard scolarité | admin, directeur, secretaire |
| `rapports.academique` | Dashboard académique | admin, directeur, enseignant |
| `rapports.finance` | Dashboard finance | admin, directeur, comptable |
| `rapports.rh` | Dashboard RH | admin, directeur |
| `rapports.vie_scolaire` | Dashboard vie scolaire | admin, directeur, secretaire |
| `rapports.bibliotheque` | Dashboard bibliothèque | admin, directeur, secretaire |
| `rapports.inventaire` | Dashboard inventaire | admin, directeur, secretaire, comptable |
| `rapports.export.pdf` | Export PDF | admin, directeur, comptable, secretaire |
| `rapports.export.excel` | Export Excel | admin, directeur, comptable |
| `rapports.export.csv` | Export CSV | admin, directeur, comptable, secretaire |
| `rapports.planifier` | Planification rapports | admin, directeur |
| `rapports.historique` | Voir historique exécutions | admin, directeur, comptable |
| `rapports.admin` | Gestion complète | admin |
| `rapports.multi_etab` | Vue cross-établissements | admin groupe |

---

## 11. Routes — 30 routes `/v2/rapports/`

```php
// Hub
GET  /v2/rapports/                              → DashboardController::hub()

// Tableaux de bord (8 domaines)
GET  /v2/rapports/direction                     → DashboardController::direction()
GET  /v2/rapports/scolarite                     → DashboardController::scolarite()
GET  /v2/rapports/academique                    → DashboardController::academique()
GET  /v2/rapports/finance                       → DashboardController::finance()
GET  /v2/rapports/rh                            → DashboardController::rh()
GET  /v2/rapports/vie-scolaire                  → DashboardController::vieScolaire()
GET  /v2/rapports/bibliotheque                  → DashboardController::bibliotheque()
GET  /v2/rapports/inventaire                    → DashboardController::inventaire()

// KPIs
GET  /v2/rapports/kpi                           → KpiController::index()
GET  /v2/rapports/kpi/{domaine}                 → KpiController::byDomaine()
GET  /v2/rapports/tendances                     → KpiController::tendances()
GET  /v2/rapports/comparaisons                  → KpiController::comparaisons()

// Export
GET  /v2/rapports/export                        → ExportController::index()
POST /v2/rapports/export/pdf                    → ExportController::pdf()
POST /v2/rapports/export/excel                  → ExportController::excel()
POST /v2/rapports/export/csv                    → ExportController::csv()
GET  /v2/rapports/export/historique             → ExportController::historique()

// Planification
GET  /v2/rapports/planification                 → PlanificationController::index()
GET  /v2/rapports/planification/creer           → PlanificationController::create()
POST /v2/rapports/planification                 → PlanificationController::store()
GET  /v2/rapports/planification/{id}/modifier   → PlanificationController::edit()
POST /v2/rapports/planification/{id}/modifier   → PlanificationController::update()
POST /v2/rapports/planification/{id}/activer    → PlanificationController::activer()
POST /v2/rapports/planification/{id}/executer   → PlanificationController::executerNow()
POST /v2/rapports/planification/{id}/supprimer  → PlanificationController::destroy()

// API Analytics (publique, versionnée)
GET  /v2/rapports/api/kpi                       → ApiAnalyticsController::kpi()
GET  /v2/rapports/api/stats/{domaine}           → ApiAnalyticsController::stats()
GET  /v2/rapports/api/tableau-de-bord           → ApiAnalyticsController::dashboard()
GET  /v2/rapports/api/tendances/{domaine}       → ApiAnalyticsController::tendances()
```

---

## 12. Dépendances cross-modules

Le module BI consomme les services existants en **read-only**. Aucun appel d'écriture.

| Domaine BI | Services consommés (méthodes read) | Tables sources |
|---|---|---|
| Scolarité | `EleveService::lister()`, `ClasseService::lister()`, `InscriptionService::lister()` | `eleves`, `classes`, `inscriptions` |
| Académique | `NoteService::*`, `RankingEngine::*`, `AcademicAnalyticsService::*` | `notes`, `evaluations`, `moyennes` |
| Finance | `FinancialReportService::*`, `InvoiceService::lister()`, `PaymentService::*` | `finance_*` |
| Vie Scolaire | `AbsenceService::*`, `RetardService::*`, `DisciplineService::*` | `vs_*` |
| RH | `EmployeService::*`, `CongesService::*`, `PresenceService::*` | `rh_*` |
| Bibliothèque | `PretService::*`, `CatalogueService::*` | `biblio_*` |
| Inventaire | `StockService::*`, `ArticleService::*`, `MaintenanceService::*` | `inv_*` |
| Communication | `MessageService::*` | Pour envoi notifications rapports |

**Pattern d'accès :** Les `*AnalyticsRepository` du module BI effectuent des requêtes SQL directes (plus efficaces que les services pour l'agrégation), mais les services sont utilisés pour les lookups simples et la validation RBAC.

---

## 13. Intégration Events entrants

Le module BI s'abonne à des events des autres modules pour déclencher des snapshots automatiques :

| Event source | Action BI |
|---|---|
| `Finance\PaymentCompleted` | Snapshot `finance.taux_recouvrement` |
| `Academique\MoyenneCalculee` | Snapshot `academique.moyenne_generale` |
| `Scolarite\EleveInscrit` | Snapshot `scolarite.nb_eleves` |
| `VieScolaire\AbsenceSignalee` | Snapshot `vie_scolaire.nb_absences` |
| `RH\PresencePointee` | Snapshot `rh.taux_presence` |

Ces mappings sont configurés dans `config/events.php`. Aucun couplage fort — le BI reçoit l'event et met à jour ses snapshots.

---

## 14. Vues — 21 vues

```
Views/
├── hub/
│   └── index.php             ← Hub central avec tuiles par domaine
├── dashboards/
│   ├── direction.php         ← 8 widgets KPI + graphiques (Chart.js)
│   ├── scolarite.php
│   ├── academique.php
│   ├── finance.php
│   ├── rh.php
│   ├── vie-scolaire.php
│   ├── bibliotheque.php
│   └── inventaire.php
├── kpi/
│   ├── index.php             ← Tableau de bord KPIs global
│   ├── domaine.php           ← KPIs d'un domaine avec tendances
│   ├── tendances.php         ← Graphiques de tendances (courbes)
│   └── comparaisons.php     ← Comparaisons périodes / établissements
├── export/
│   ├── index.php             ← Hub export
│   └── historique.php        ← Historique des exports
└── planification/
    ├── index.php             ← Liste rapports planifiés
    ├── form.php              ← Create/Edit planification
    └── historique.php        ← Historique exécutions
```

**Composants graphiques :** Chart.js (CDN, déjà utilisé dans le module Reporting V1). Types utilisés : `line`, `bar`, `doughnut`, `radar`.

---

## 15. Préparation V3 — BI & Data Warehouse

### Architecture cible V3

```
┌──────────────────────────────────────────────────────────────┐
│  SCOLARIS DATA WAREHOUSE (V3)                                │
│                                                              │
│  ETL Layer                                                   │
│  ├── Extraction : lectures bi-quotidiennes modules → staging │
│  ├── Transform : nettoyage, standardisation, enrichissement  │
│  └── Load : chargement tables facts/dimensions              │
│                                                              │
│  Star Schema                                                 │
│  ├── fact_performances_eleves                               │
│  ├── fact_paiements                                         │
│  ├── fact_absences                                          │
│  ├── fact_presences_rh                                      │
│  ├── fact_mouvements_stock                                  │
│  ├── dim_eleves, dim_classes, dim_temps, dim_etablissement   │
│  └── dim_matieres, dim_enseignants                          │
│                                                              │
│  BI Tools (V3)                                              │
│  ├── Metabase / Apache Superset (auto-hosted)               │
│  ├── API REST analytique publique                           │
│  └── Export direct OLAP                                     │
└──────────────────────────────────────────────────────────────┘
```

**Stubs V2 préparés :**

```php
// Contracts/DataWarehouseInterface.php (STUB V3)
interface DataWarehouseInterface {
    public function extractFacts(string $domaine, string $debut, string $fin): array;
    public function queryCube(string $mesure, array $dimensions, array $filtres): array;
}

// Contracts/ETLInterface.php (STUB V3)
interface ETLInterface {
    public function extract(string $domaine): array;
    public function transform(array $rawData): array;
    public function load(array $transformedData, string $targetTable): bool;
}

// Contracts/PredictionInterface.php (STUB V3)
interface PredictionInterface {
    public function predict(string $modele, array $features): float;
    public function getModelAccuracy(string $modele): float;
}
```

### IA Prédictive — Cas d'usage (architecture V3)

| Modèle | Features | Output |
|---|---|---|
| `DecrochageScolaire` | absences, notes, discipline, famille | risque 0→1 |
| `ImpayéRisk` | historique paiements, type famille, solde | risque 0→1 |
| `PrevisionEffectifs` | historique inscriptions, démographie | nb élèves N+1 |
| `OptimisationEmploisDuTemps` | contraintes, disponibilités | score conflits |
| `TendanceBudget` | historique recettes/dépenses | prévision mensuelle |

---

## 16. Préparation API Publique

### Architecture API Analytics

```
/api/v1/analytics/
├── GET /kpi/{domaine}                 → KPIs du domaine (JSON)
├── GET /stats/scolarite               → Statistiques scolarité
├── GET /stats/academique              → Résultats académiques
├── GET /stats/finance                 → Indicateurs financiers
├── GET /stats/presences               → Taux de présence
├── GET /tendances/{domaine}/{metrique} → Série temporelle
└── GET /comparaison/etablissements    → Multi-étab (scope groupe)
```

**Sécurité API :**
- Token Bearer (table `api_tokens` — Core module)
- Rate limiting : 1000 req/heure par token
- Scope granulaire : `read:scolarite`, `read:finance`, etc.
- Versionning : `/api/v1/` → `/api/v2/` (non-breaking)
- Format : JSON-API ou JSON simple selon endpoint

---

## 17. SaaS / Multi-Établissements

| Aspect | Implémentation |
|---|---|
| Isolation données | `etablissement_id` sur toutes les tables `bi_*` |
| Vue croisée | `DataAggregatorService::comparaisonMultiEtab()` (permission `rapports.multi_etab`) |
| Benchmarking | Comparaison anonymisée entre établissements du même groupe |
| Tableau de bord groupe | Dashboard consolidé multi-établissements pour admin réseau |
| Exports isolés | Fichiers dans répertoires distincts par `etablissement_id` |
| Planification | Rapports planifiés par établissement, exécution centralisée |

---

## 18. Mobile

| Aspect | Approche |
|---|---|
| Endpoints JSON dédiés | `/v2/rapports/api/*` optimisés (payload léger) |
| KPIs mobiles | Sous-ensemble de 5-6 KPIs essentiels par rôle |
| Graphiques | Données brutes JSON → rendu Chart.js côté client |
| PWA | Mise en cache des derniers KPIs (Service Worker) |
| Push | Notification push sur rapport planifié terminé |

---

## 19. Sous-phases d'implémentation

| Phase | Contenu | Priorité |
|---|---|---|
| **11.2** | Analytics Repositories (7) + DTOs + Policies | CRITIQUE |
| **11.3** | DashboardService (8 tableaux) + 8 vues dashboard | CRITIQUE |
| **11.4** | KpiService + TendanceService + SnapshotService + vues KPI | HAUTE |
| **11.5** | ReportGeneratorService (PDF/Excel/CSV) + ExportController + vues export | HAUTE |
| **11.6** | PlanificationService + PlanificationController + vues planification | MOYENNE |
| **11.7** | ApiAnalyticsController + routes API + stubs DWH/ETL/IA | MOYENNE |
| **11.8** | Listeners cross-modules (events entrants) + SnapshotListener | HAUTE |
| **11.9** | Integration Review + Freeze | BLOQUANT |

---

## 20. Chiffres du module

| Métrique | Valeur |
|---|---|
| Tables SQL | 5 (bi_*) |
| Services | 8 (dont 1 STUB V3) |
| Analytics Repositories | 10 |
| DTOs | 5 |
| Events | 6 |
| Listeners | 3 |
| Controllers | 5 |
| Vues | 21 |
| Routes | 30 |
| Permissions | 16 (rapports.*) |
| Interfaces STUB V3 | 3 |
| Sous-phases | 8 (11.2 → 11.9) |

---

## 21. Contraintes et règles

- **Aucun calcul métier dupliqué** — le BI délègue aux services des modules pour les calculs ; il ne re-implémente pas les moyennes, les statuts de paiement ou les règles d'absentéisme.
- **Lecture seule** — `UPDATE`, `INSERT`, `DELETE` interdits dans tous les `*AnalyticsRepository` sauf `bi_*`.
- **Pas de couplage fort** — si un module est `enabled=false`, ses données sont simplement absentes des dashboards (aucune exception).
- **Soft delete** — `bi_rapports_planifies` utilise `deleted_at`, `bi_exports` utilise `expire_at` + purge.
- **`enabled=false` au départ** — module activé après Phase 11.9 GO.
- **Tailwind CSS + Lucide Icons + Chart.js** — design system cohérent.
- **Multi-tenant strict** — `etablissement_id` systématique dans toutes les requêtes.

---

*SCOLARIS V2 — Rapports & BI Blueprint — Phase 11.1 — 2026-07-03*
