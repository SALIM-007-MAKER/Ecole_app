# RAPPORTS & BUSINESS INTELLIGENCE V2 — IMPLEMENTATION REPORT
**Phase:** 11.2  
**Date:** 2026-07-04  
**Statut:** IMPLÉMENTÉ — prêt pour Phase 11.3 Integration Review  
**Fichiers créés:** 70 (65 module + 5 Shared/Analytics)

---

## 1. ARCHITECTURE GLOBALE

```
app/
├── Shared/Analytics/                   ← Couche analytique réutilisable (NEW)
│   ├── KPIEngine.php                   ← Calculs variations, tendances, alertes
│   ├── ReportEngine.php                ← Tableaux, agrégations, CSV
│   ├── ChartEngine.php                 ← Chart.js configs (palette violet)
│   ├── ExportEngine.php                ← CSV, Excel XML, HTML print
│   └── DashboardBuilder.php            ← Builder pattern, config utilisateur
│
└── Modules/Rapports/
    ├── module.json
    ├── routes.php                       ← 30 routes /v2/rapports/*
    ├── Contracts/
    │   ├── DataWarehouseInterface.php   ← STUB V3
    │   ├── ETLInterface.php             ← STUB V3
    │   └── PredictionInterface.php      ← STUB V3
    ├── DTO/  (5 DTOs)
    ├── Events/  (6 events)
    ├── Listeners/  (3 listeners)
    ├── Policies/RapportPolicy.php
    ├── Repositories/
    │   ├── SnapshotRepository.php       ← bi_kpi_snapshots (upsert atomique)
    │   ├── PlanificationRepository.php  ← bi_rapports_planifies
    │   ├── ExportRepository.php         ← bi_exports + bi_rapport_executions
    │   └── Analytics/  (7 repos read-only)
    ├── Services/  (8 services)
    ├── Controllers/  (5 controllers)
    └── Views/  (25 vues)
```

---

## 2. BASE DE DONNÉES

**Migration:** `database/migrations/bi_001_rapports.sql`

| Table | Description |
|---|---|
| `bi_tableaux_config` | Config widgets par user+contexte (JSON) |
| `bi_rapports_planifies` | Rapports récurrents avec soft-delete |
| `bi_rapport_executions` | Traçabilité des exécutions (machine d'états) |
| `bi_kpi_snapshots` | Historique mensuel KPIs (UNIQUE domaine+metrique+periode+etab) |
| `bi_exports` | Registre des fichiers générés avec expiration |

---

## 3. COUCHE ANALYTIQUE SHARED

### KPIEngine — `app/Shared/Analytics/KPIEngine.php`
- `variation(float $ancien, float $nouveau): float` — variation en %
- `tendance(array $series): string` — hausse/baisse/stable (seuil ±2%)
- `moyenneGlissante(array $values, int $fenetre): array` — window rolling avg
- `tauxRealisation(float $objectif, float $realise): float`
- `scoreComposite(array $kpis, array $poids): float` — score pondéré
- `alerteSeuil(float $valeur, float $seuil, string $direction): bool`
- `formatKpi(float $valeur, string $unite): string` — format k/M

### ReportEngine — `app/Shared/Analytics/ReportEngine.php`
- `buildTableau(array $rows, array $colonnes, array $options): array`
- `agreger(array $rows, string $groupKey, string $valueKey, string $fn): array`
- `top(array $rows, int $n, string $valueKey): array`
- `distribuerParTranche(array $values, array $tranches): array`
- `csvEncode(array $rows, array $colonnes): string`

### ChartEngine — `app/Shared/Analytics/ChartEngine.php`
- `line/bar/horizontalBar/doughnut/radar` — configs Chart.js
- `serieMensuelle/serieJournaliere` — agrégation temporelle
- Palette 10 couleurs violet/slate cohérente avec le design system

### ExportEngine — `app/Shared/Analytics/ExportEngine.php`
- `toCsv` — BOM UTF-8, séparateur `;`
- `toExcelXml` — format SpreadsheetML compatible Excel
- `toHtmlTable` — print-ready avec styles inline
- `sendCsvResponse/sendExcelResponse` — headers HTTP

### DashboardBuilder — `app/Shared/Analytics/DashboardBuilder.php`
- Builder fluent : `addKpi/addChart/addTable/addAlertes`
- `applyUserConfig(array $config)` — personnalisation ordre/visibilité
- `build(): array` — sortie JSON-sérialisable

---

## 4. ANALYTICS REPOSITORIES (lecture seule)

| Repository | Tables sources | Méthodes clés |
|---|---|---|
| `ScolariteAnalyticsRepository` | eleves, classes, inscriptions | effectifsParClasse, evolutionInscriptions, repartitionGenre, tauxRemplissage, counters |
| `AcademiqueAnalyticsRepository` | notes_v2, evaluations, periodes_scolaires, matieres | moyenneGeneraleParClasse, distribMentions, tauxReussiteParMatiere, evolutionMoyennesParPeriode |
| `FinanceAnalyticsRepository` | finance_factures, finance_paiements, finance_mouvements_caisse | recettesParMois, facturesImpayees, tauxRecouvrement, mouvementsCaisse |
| `VieScolaireAnalyticsRepository` | vs_absences, vs_retards, vs_incidents_discipline | absencesParClasse, retardsParClasse, incidentsParType, evolutionAbsences |
| `RHAnalyticsRepository` | rh_employes, rh_presences, rh_conges, rh_contrats, rh_evaluations | effectifsParDepartement, tauxPresence, congesParType, scoresEvaluations, contratsExpirant |
| `BibliothequeAnalyticsRepository` | biblio_emprunts, biblio_ouvrages, biblio_exemplaires | empruntsParMois, ouvragesLesPlusEmpruntes, tauxRetour, empruntsEnRetard |
| `InventaireAnalyticsRepository` | inv_articles, inv_stocks, inv_commandes, inv_maintenances, inv_amortissements | valeurTotaleStock, articlesEnAlerteStock, commandesParStatut, coutMaintenanceParArticle |

**Principe immuable** : Aucune écriture vers les tables sources. Read-only absolu.

---

## 5. SERVICES

| Service | Responsabilité |
|---|---|
| `DataAggregatorService` | Façade : dispatch vers les 7 repos analytics |
| `DashboardService` | Construction des 9 dashboards contextuels |
| `KpiService` | Calcul KPIs par domaine + dispatch KpiSnapshot events |
| `TendanceService` | Analyse tendances, comparaison périodes, alertes seuil |
| `SnapshotService` | Capture manuelle/batch KPIs → Events |
| `ReportGeneratorService` | Génération rapports CSV/Excel/HTML |
| `PlanificationService` | CRUD planifications + exécution déclenchée |
| `PredictionService` | STUB V3 (régression linéaire basique) |

---

## 6. EVENTS & LISTENERS

| Event | Listeners | Déclencheur |
|---|---|---|
| `RapportGenere` | BiAuditListener | ReportGeneratorService::generer() |
| `RapportPlanifie` | BiAuditListener | PlanificationService::creer() |
| `RapportExecute` | BiAuditListener, BiNotificationListener | PlanificationService::executerPlanifie() |
| `RapportExporte` | BiAuditListener | ExportController (csv/excel/pdf) |
| `KpiSnapshot` | BiAuditListener, BiSnapshotListener | KpiService::buildKpisFromData() |
| `DashboardConsulte` | BiAuditListener | DashboardService::getDashboard() |

**BiSnapshotListener** : persist via `SnapshotRepository::upsert()` (INSERT ... ON DUPLICATE KEY UPDATE).

---

## 7. CONTROLLERS (thin)

| Controller | Actions |
|---|---|
| `DashboardController` | direction, administration, scolarite, academique, finance, rh, vieScolaire, bibliotheque, inventaire (9) |
| `KpiController` | index, tendanceView, snapshot, historiqueJson, alertes (5) |
| `ExportController` | index, form, csv, excel, pdf, apercu (6) |
| `PlanificationController` | index, create, store, show, edit, update, destroy, executer (8) |
| `ApiAnalyticsController` | kpis, domaine, tendanceJson, alertesJson, prevision (5) |

---

## 8. ROUTES (30 routes)

```
GET  /v2/rapports                          → dashboard direction (défaut)
GET  /v2/rapports/dashboard/{contexte}     → 9 dashboards contextuels
GET  /v2/rapports/kpis                     → KPIs par domaine
GET  /v2/rapports/kpis/tendance            → Analyse tendance métrique
GET  /v2/rapports/kpis/alertes             → Alertes seuils KPI
POST /v2/rapports/kpis/snapshot            → Capture batch snapshots
GET  /v2/rapports/kpis/historique          → JSON historique KPI
GET  /v2/rapports/exports                  → Liste exports
GET  /v2/rapports/exports/form             → Formulaire export
GET  /v2/rapports/exports/apercu           → Aperçu rapport
GET  /v2/rapports/exports/csv              → Télécharger CSV
GET  /v2/rapports/exports/excel            → Télécharger Excel
GET  /v2/rapports/exports/pdf              → PDF (HTML print)
GET  /v2/rapports/planifications           → Liste planifications
CRUD /v2/rapports/planifications/{id}      → CRUD complet
POST /v2/rapports/planifications/{id}/executer → Exécution manuelle
GET  /v2/rapports/api/kpis                 → JSON KPIs
GET  /v2/rapports/api/domaine              → JSON données domaine
GET  /v2/rapports/api/tendance             → JSON tendance
GET  /v2/rapports/api/alertes              → JSON alertes
GET  /v2/rapports/api/prevision            → JSON prévisions V3 stub
```

---

## 9. PERMISSIONS (13 permissions)

```
rapports.dashboard.direction
rapports.dashboard.administration
rapports.dashboard.scolarite
rapports.dashboard.academique
rapports.dashboard.finance
rapports.dashboard.rh
rapports.dashboard.vie_scolaire
rapports.dashboard.bibliotheque
rapports.dashboard.inventaire
rapports.kpis.voir
rapports.exporter
rapports.planifier
rapports.api.analytics
```

**Attribution par rôle :**
- `admin` : toutes les permissions
- `secretaire` : dashboards direction/scolarite/academique/finance/vie_scolaire + kpis.voir + exporter
- `comptable` : dashboards direction/finance + kpis.voir + exporter
- `enseignant` : dashboards academique/vie_scolaire + kpis.voir

---

## 10. VUES (25 vues)

```
Views/
├── dashboards/
│   ├── _layout.php          ← Layout partagé (nav, KPIs, charts, tables)
│   ├── direction.php
│   ├── administration.php
│   ├── scolarite.php
│   ├── academique.php
│   ├── finance.php
│   ├── rh.php
│   ├── vie-scolaire.php
│   ├── bibliotheque.php
│   └── inventaire.php
├── kpis/
│   ├── index.php            ← KPIs + filtre domaine
│   ├── tendance.php         ← Chart tendance + historique
│   └── alertes.php          ← Alertes seuils
├── exports/
│   ├── index.php            ← Historique exports
│   ├── form.php             ← Formulaire génération
│   └── apercu.php           ← Preview rapport
├── planifications/
│   ├── index.php
│   ├── form.php
│   └── show.php
└── partials/
    ├── kpi-card.php
    ├── chart-card.php
    └── alertes-banner.php
```

---

## 11. CONFIG FILES MODIFIÉS

| Fichier | Modification |
|---|---|
| `config/modules.php` | Entrée `rapports` ajoutée (enabled: false) |
| `config/events.php` | 6 event→listener mappings ajoutés |
| `config/permissions.php` | 13 permissions ajoutées pour admin/secretaire/comptable/enseignant |

---

## 12. DETTES TECHNIQUES

| ID | Description | Priorité |
|---|---|---|
| DT-R-001 | PredictionService : implémentation ML réelle (sklearn-like) | V3 |
| DT-R-002 | DataWarehouseInterface : ETL nocturne, star schema | V3 |
| DT-R-003 | ExportEngine PDF : intégration vraie lib PDF (DomPDF/mPDF) | V3 |
| DT-R-004 | ApiAnalyticsController : authentification token stateless | V3 |
| DT-R-005 | BiNotificationListener : implémentation envoi email planif. | V3 |

---

## 13. COMPATIBILITÉ V1

- Aucune table V1 modifiée
- Aucune route V1 touchée
- Module `enabled: false` — activable indépendamment
- Toutes les tables sources lues sans JOIN ni write

---

## 14. CHECKLIST PHASE 11.3

- [ ] Vérifier `Core\Controller::render()` supporte `'Rapports::dashboards/direction'`
- [ ] Vérifier `Auth::hasPermission()` disponible dans RapportPolicy
- [ ] Exécuter migration `bi_001_rapports.sql` sur DB de test
- [ ] Tester `DashboardService::getDashboard('direction', 1, 1)` sans erreur
- [ ] Tester `KpiService::getKpisDomaine('finance', 1)` + vérifier dispatch KpiSnapshot
- [ ] Tester `ReportGeneratorService::genererCsv()` retourne string UTF-8 BOM
- [ ] Vérifier que `BiSnapshotListener` persiste bien dans `bi_kpi_snapshots`
- [ ] Tester `PlanificationService::creer()` + `executerPlanifie()`
- [ ] Vérifier `InventaireAnalyticsRepository::counters()` requête GROUP BY correcte
- [ ] Valider que toutes les routes renvoient 200 ou redirect (pas 500)
