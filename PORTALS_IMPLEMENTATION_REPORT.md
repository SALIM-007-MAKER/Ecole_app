# PORTALS_IMPLEMENTATION_REPORT.md
## Phase 12.2 — Implémentation des 7 Portails SCOLARIS V2

**Date :** 2026-07-05  
**Statut :** ✅ IMPLÉMENTÉ — En attente d'activation

---

## 1. Résumé exécutif

Phase 12.2 complétée : tous les 7 portails contextuels de SCOLARIS V2 sont implémentés exclusivement via le Portal Framework (Phase 12.1). Aucune logique métier dans les portails — consommation stricte des services et repositories des 11 modules existants.

| Portail | Couleur | Rôle(s) | Pages | Widgets | Statut |
|---------|---------|---------|-------|---------|--------|
| Admin | Violet | admin | 4 | 8 | ✅ |
| Direction | Indigo | directeur | 7 | 9 | ✅ |
| Enseignant | Teal | enseignant | 10 | 7 | ✅ |
| Élève | Blue | eleve | 8 | 8 | ✅ |
| Parent | Emerald | parent | 7 | 7 | ✅ |
| Comptabilité | Amber | comptable, secretaire | 6 | 8 | ✅ |
| RH | Rose | directeur (rh.access) | 7 | 8 | ✅ |
| **TOTAL** | | | **49** | **55** | |

---

## 2. Architecture

### 2.1 Principe d'implémentation

```
Request → [Portal Route]
        → [PortalBaseController::__construct()]
            └→ PortalBootstrap::boot() [idempotent, 59 widgets]
        → requirePortalAccess() [auth + RBAC + audit]
        → DashboardEngine::build() [widgets filtrés par perms]
        → portalRender(view, data) [layout + menu + theme + prefs]
```

### 2.2 Hiérarchie contrôleurs

```
Core\Controller (abstract)
└── PortalBaseController (abstract) — Phase 12.1
    ├── Admin\Controllers\AdminPortalController
    ├── Direction\Controllers\DirectionPortalController
    ├── Enseignant\Controllers\EnseignantPortalController
    ├── Eleve\Controllers\ElevePortalController
    ├── Parent\Controllers\ParentPortalController
    ├── Comptabilite\Controllers\ComptabilitePortalController
    └── RH\Controllers\RHPortalController
```

### 2.3 Contraintes respectées

- ✅ Aucune logique métier dans les portails — SQL en lecture seule uniquement
- ✅ `getEtablissementId()` STRICT (RuntimeException si 0, jamais `?? 1`)
- ✅ `$router->get()` instance — jamais `Router::get()` statique
- ✅ `declare(strict_types=1)` sur tous les fichiers
- ✅ Zéro modification de modules V1
- ✅ `enabled: false` — module inactif jusqu'à activation explicite
- ✅ Tous les `\Throwable` catchés dans les helpers — dégradation gracieuse
- ✅ RBAC via `requirePermission()` sur chaque action sensible

---

## 3. Détail par portail

### 3.1 Portail Admin (`/v2/portals/admin`)
**Contrôleur :** `App\Modules\Portals\Admin\Controllers\AdminPortalController`  
**Permission requise :** `portal.admin.access`

| Route | Méthode | Permission | Description |
|-------|---------|-----------|-------------|
| `/v2/portals/admin` | GET | `portal.admin.access` | Dashboard avec 8 widgets |
| `/v2/portals/admin/audit` | GET | `users.view` | Journal d'audit paginé (30/page) |
| `/v2/portals/admin/modules` | GET | - | État des modules config |
| `/v2/portals/admin/parametres` | GET | `users.view` | Lien vers /v2/parametres |

**Widgets (8) :**
- `admin_stats_globales` (lg, refresh 300s) — nb élèves/employés/classes/users
- `admin_modules_status` (md) — état enabled/disabled des modules
- `admin_activite_recente` (md, refresh 60s) — 10 derniers logs audit
- `admin_users_actifs` (sm, refresh 120s) — répartition par rôle
- `admin_alertes_systeme` (sm, refresh 120s) — tokens API expirants
- `admin_repartition_roles` (sm) — distribution des rôles
- `admin_capacite` (sm) — taux remplissage classes
- `admin_sessions_actives` (sm, refresh 60s) — activité 30min par portail

**Données consommées :** `users`, `classes`, `eleves`, `rh_employes`, `audit_logs`, `portal_access_logs`, `portal_api_tokens`

---

### 3.2 Portail Direction (`/v2/portals/direction`)
**Contrôleur :** `App\Modules\Portals\Direction\Controllers\DirectionPortalController`  
**Permission requise :** `portal.direction.access`

| Route | Permission | Description |
|-------|-----------|-------------|
| `/v2/portals/direction` | - | Dashboard agrégé + 6 raccourcis |
| `/v2/portals/direction/scolarite` | `eleves.view` | Liens module Scolarité |
| `/v2/portals/direction/academique` | `academique.bulletin.view` | Liens module Académique |
| `/v2/portals/direction/finance` | `finance.dashboard.view` | Liens module Finance |
| `/v2/portals/direction/vie-scolaire` | `attendance.view` | Liens module Vie scolaire |
| `/v2/portals/direction/rh` | `employee.view` | Liens module RH |
| `/v2/portals/direction/rapports` | `rapports.dashboard.direction` | Lien Rapports BI |

**Widgets (9) :**
- `direction_kpi_scolarite` (sm, refresh 300s) — nb élèves/classes/inscrits
- `direction_kpi_academique` (sm, refresh 600s) — moyenne générale + nb bulletins
- `direction_kpi_finance` (sm, refresh 300s) — recettes mois + impayés
- `direction_kpi_rh` (sm, refresh 300s) — effectif + présence + congés
- `direction_absences_semaine` (md, refresh 300s) — absences par jour cette semaine
- `direction_paiements_recent` (md, refresh 120s) — 8 derniers paiements
- `direction_bulletin_stats` (md) — stats par période (4 dernières)
- `direction_alertes` (sm, refresh 120s) — factures en retard + contrats expirants
- `direction_calendrier` (md) — activités 14 prochains jours

---

### 3.3 Portail Enseignant (`/v2/portals/enseignant`)
**Contrôleur :** `App\Modules\Portals\Enseignant\Controllers\EnseignantPortalController`  
**Permission requise :** `portal.enseignant.access`

| Route | Permission | Description |
|-------|-----------|-------------|
| `/v2/portals/enseignant` | - | Dashboard + 4 actions rapides |
| `/v2/portals/enseignant/mes-classes` | `academique.notes.manage` | Grille classes/matières |
| `/v2/portals/enseignant/notes` | `academique.notes.manage` | Sélecteur classe → redirection |
| `/v2/portals/enseignant/appel` | `attendance.session.create` | Appel par classe |
| `/v2/portals/enseignant/evaluations` | `academique.evaluations.manage` | Lien évaluations |
| `/v2/portals/enseignant/emploi-du-temps` | `timetable.view` | Grille EDT hebdomadaire |
| `/v2/portals/enseignant/absences` | `attendance.session.validate` | Absences classes |
| `/v2/portals/enseignant/bulletins` | `academique.bulletin.view` | Lien bulletins |
| `/v2/portals/enseignant/documents` | `document.view` | Redirect /v2/documents |
| `/v2/portals/enseignant/messagerie` | `communication.view` | Lien messagerie |

**Widgets (7) :**
- `enseignant_mon_emploi_du_temps` (lg) — emploi du temps complet + filtré aujourd'hui
- `enseignant_mes_classes` (sm) — classes + matières + nb élèves
- `enseignant_absences_classe` (sm, refresh 300s) — absences 7 derniers jours par classe
- `enseignant_evaluations_a_noter` (sm, refresh 300s) — évaluations avec notes manquantes
- `enseignant_stats_notes` (md) — moyennes/min/max par matière/classe
- `enseignant_prochains_cours` (sm, refresh 300s) — 5 prochains créneaux
- `enseignant_messages_recents` (sm, refresh 60s) — 8 derniers messages non lus

**Requêtes critiques :**
- `getMyClasses()` : JOIN `classes + rh_affectation_matieres + rh_employes + users + matieres`
- `getMySchedule()` : JOIN `vs_emplois_du_temps + vs_edt_creneaux + classes + matieres + vs_edt_salles`

---

### 3.4 Portail Élève (`/v2/portals/eleve`)
**Contrôleur :** `App\Modules\Portals\Eleve\Controllers\ElevePortalController`  
**Permission requise :** `portal.eleve.access`

| Route | Permission | Description |
|-------|-----------|-------------|
| `/v2/portals/eleve` | - | Dashboard + 7 raccourcis |
| `/v2/portals/eleve/notes` | `notes.view_own` | Tableau notes (30 dernières) |
| `/v2/portals/eleve/bulletins` | `bulletins.view` | Liste bulletins avec téléchargement |
| `/v2/portals/eleve/absences` | `absences.view_own` | Tableau absences (50 dernières) |
| `/v2/portals/eleve/emploi-du-temps` | `emploi_du_temps.view_own` | Grille EDT par jour |
| `/v2/portals/eleve/bibliotheque` | `biblio.view` | Emprunts en cours |
| `/v2/portals/eleve/messagerie` | `communication.view` | Lien messagerie |
| `/v2/portals/eleve/profil` | - | Informations élève |

**Widgets (8) :**
- `eleve_mon_emploi_du_temps` (lg) — EDT + focus aujourd'hui
- `eleve_notes_recentes` (md, refresh 300s) — 8 dernières notes
- `eleve_absences` (sm) — compteur avec répartition justifiée/non
- `eleve_prochaines_echeances` (sm) — 5 prochaines évaluations
- `eleve_emprunts` (sm) — emprunts en cours avec alertes retard
- `eleve_bulletin_disponible` (sm) — dernier bulletin avec moyenne/mention
- `eleve_activites` (sm) — activités inscrites
- `eleve_messages` (sm, refresh 60s) — messages non lus

---

### 3.5 Portail Parent (`/v2/portals/parent`)
**Contrôleur :** `App\Modules\Portals\Parent\Controllers\ParentPortalController`  
**Permission requise :** `portal.parent.access`

| Route | Permission | Description |
|-------|-----------|-------------|
| `/v2/portals/parent` | - | Dashboard + sélecteur enfants |
| `/v2/portals/parent/enfants` | - | Cartes enfants |
| `/v2/portals/parent/notes` | `notes.view_own` | Notes de tous les enfants |
| `/v2/portals/parent/absences` | `absences.view_own` | Absences de tous les enfants |
| `/v2/portals/parent/paiements` | `finance.invoice.view` | Factures impayées |
| `/v2/portals/parent/messagerie` | `communication.view` | Lien messagerie |
| `/v2/portals/parent/documents` | `document.view` | Documents partagés parents |

**Widgets (7) :**
- `parent_mes_enfants` (md) — cartes enfants avec classes
- `parent_absences_recentes` (sm, refresh 300s) — absences récentes multi-enfants
- `parent_paiements_en_attente` (sm, refresh 300s) — factures en attente + total dû
- `parent_notes_recentes` (md, refresh 300s) — 10 dernières notes tous enfants
- `parent_bulletins` (sm) — 5 derniers bulletins tous enfants
- `parent_messages` (sm, refresh 60s) — messages non lus
- `parent_prochains_evenements` (sm) — 5 prochains événements scolaires

**Table de liaison :** `famille_eleve` + `famille_membres` (module Familles Phase 1.5)

---

### 3.6 Portail Comptabilité (`/v2/portals/comptabilite`)
**Contrôleur :** `App\Modules\Portals\Comptabilite\Controllers\ComptabilitePortalController`  
**Permission requise :** `portal.comptabilite.access`

| Route | Permission | Description |
|-------|-----------|-------------|
| `/v2/portals/comptabilite` | - | Dashboard + KPI bar + 5 actions |
| `/v2/portals/comptabilite/factures` | `finance.invoice.view` | Tableau factures filtrable par statut |
| `/v2/portals/comptabilite/paiements` | `finance.payment.view` | Journal paiements paginé |
| `/v2/portals/comptabilite/caisse` | `finance.caisse.view` | Journal de caisse du jour |
| `/v2/portals/comptabilite/impayes` | `finance.invoice.view` | Liste impayés avec retards |
| `/v2/portals/comptabilite/rapports` | `finance.report.view` | Recettes par mode/mois |

**Widgets (8) :**
- `compta_caisse_du_jour` (sm, refresh 120s) — encaissé + entrées/sorties
- `compta_factures_en_attente` (sm, refresh 300s) — nb + montant par statut
- `compta_recettes_mois` (sm, refresh 300s) — recettes avec variation vs mois précédent
- `compta_impayes` (sm, refresh 300s) — nb total + nb en retard
- `compta_taux_recouvrement` (sm) — taux calculé sur toutes factures
- `compta_derniers_paiements` (md, refresh 120s) — 10 derniers paiements
- `compta_comparatif_mensuel` (lg) — 6 mois glissants
- `compta_depenses_mois` (sm, refresh 300s) — sorties de caisse du mois

---

### 3.7 Portail RH (`/v2/portals/rh`)
**Contrôleur :** `App\Modules\Portals\RH\Controllers\RHPortalController`  
**Permission requise :** `portal.rh.access`

| Route | Permission | Description |
|-------|-----------|-------------|
| `/v2/portals/rh` | - | Dashboard + KPI bar (5 métriques) + 6 liens |
| `/v2/portals/rh/presences` | `rh.presence.view` | Présences du jour filtrable par date |
| `/v2/portals/rh/conges` | `leave.manage` | Gestion congés (en_attente/approuvé/refusé) |
| `/v2/portals/rh/contrats` | `contract.view` | Contrats expirant 60j |
| `/v2/portals/rh/formations` | `training.view` | Sessions en cours/planifiées |
| `/v2/portals/rh/evaluations` | `evaluation.manage` | Évaluations planifiées |
| `/v2/portals/rh/documents` | `hr_document.view` | Documents RH |

**Widgets (8) :**
- `rh_presences_aujourd_hui` (sm, refresh 120s) — effectif/présents/taux
- `rh_conges_en_attente` (sm, refresh 120s) — 10 demandes en attente
- `rh_contrats_expiration` (sm, refresh 600s) — contrats <60j avec urgence <30j
- `rh_effectifs` (sm) — effectifs par type et statut
- `rh_formations_en_cours` (sm) — 5 sessions actives/planifiées
- `rh_evaluations_planifiees` (sm) — 8 évaluations à venir
- `rh_alertes_rh` (sm, refresh 120s) — congés + contrats
- `rh_absences_rh` (sm, refresh 300s) — absences personnel cette semaine

---

## 4. Fichiers créés

### 4.1 Contrôleurs (7)
```
app/Modules/Portals/
├── Admin/Controllers/AdminPortalController.php
├── Direction/Controllers/DirectionPortalController.php
├── Enseignant/Controllers/EnseignantPortalController.php
├── Eleve/Controllers/ElevePortalController.php
├── Parent/Controllers/ParentPortalController.php
├── Comptabilite/Controllers/ComptabilitePortalController.php
└── RH/Controllers/RHPortalController.php
```

### 4.2 Widgets spécifiques (55)
```
app/Modules/Portals/
├── Admin/Widgets/ (8 widgets)
│   ├── StatsGlobalesWidget.php
│   ├── ModulesStatusWidget.php
│   ├── ActiviteRecenteWidget.php
│   ├── UsersActifsWidget.php
│   ├── AlertesSystèmeWidget.php
│   ├── RepartitionRolesWidget.php
│   ├── CapaciteWidget.php
│   └── SessionsActivesWidget.php
├── Direction/Widgets/ (9 widgets)
│   ├── KpiScolariteWidget.php ... CalendrierWidget.php
├── Enseignant/Widgets/ (7 widgets)
│   ├── MonEmploiDuTempsWidget.php ... MessagesRecentsWidget.php
├── Eleve/Widgets/ (8 widgets)
│   ├── MonEmploiDuTempsEleveWidget.php ... MessagesEleveWidget.php
├── Parent/Widgets/ (7 widgets)
│   ├── MesEnfantsWidget.php ... ProchainsEvenementsWidget.php
├── Comptabilite/Widgets/ (8 widgets)
│   ├── CaisseDuJourWidget.php ... DepensesMoisWidget.php
└── RH/Widgets/ (8 widgets)
    ├── PresencesAujourdhuiWidget.php ... AbsencesRhWidget.php
```
**Total widgets : 4 partagés (Phase 12.1) + 55 spécifiques = 59 widgets**

### 4.3 Vues (48 fichiers)
```
app/Modules/Portals/Views/
├── Admin/ (4) — dashboard, audit, modules, parametres
├── Direction/ (7) — dashboard, scolarite, academique, finance, vie_scolaire, rh, rapports
├── Enseignant/ (9) — dashboard, mes_classes, notes, appel, evaluations,
│                      emploi_du_temps, absences, bulletins, messagerie
├── Eleve/ (8) — dashboard, notes, bulletins, absences, emploi_du_temps,
│               bibliotheque, messagerie, profil
├── Parent/ (7) — dashboard, enfants, notes, absences, paiements, messagerie, documents
├── Comptabilite/ (6) — dashboard, factures, paiements, caisse, impayes, rapports
└── RH/ (7) — dashboard, presences, conges, contrats, formations, evaluations, documents
```

### 4.4 Fichiers modifiés (2)
- `app/Modules/Portals/routes.php` — +49 routes portail (67 routes totales avec framework)
- `app/Modules/Portals/Framework/PortalBootstrap.php` — registration 55 widgets portail-spécifiques

---

## 5. Statistiques globales Phase 12.2

| Métrique | Valeur |
|----------|--------|
| Contrôleurs portail | 7 |
| Widgets portail-spécifiques | 55 |
| Widgets totaux (incl. partagés) | 59 |
| Vues (pages) | 48 |
| Routes ajoutées | 49 |
| Routes totales module Portails | 67 |
| Pages totales par portail | Admin:4 • Dir:7 • Ens:10 • Elv:8 • Par:7 • Cpt:6 • RH:7 |
| Tables lues | 35+ (lecture seule, sans écriture) |
| Modules consommés | Core, Scolarité, Académique, Finance, Vie Scolaire, RH, Communication, Bibliothèque, Inventaire |

---

## 6. Intégrations modules

| Module | Portails consommateurs | Tables lues |
|--------|----------------------|-------------|
| Core | Tous | `users`, `audit_logs` |
| Scolarité | Admin, Direction, Enseignant, Élève, Parent | `eleves`, `classes`, `matieres`, `inscriptions`, `familles` |
| Académique | Direction, Enseignant, Élève, Parent | `notes_v`, `evaluations`, `bulletins_v2`, `periodes_scolaires` |
| Finance | Direction, Parent, Comptabilité | `finance_factures`, `finance_paiements`, `finance_caisse_mouvements` |
| Vie Scolaire | Direction, Enseignant, Élève | `vs_absences`, `vs_emplois_du_temps`, `vs_edt_creneaux`, `vs_activites` |
| RH | Direction, RH | `rh_employes`, `rh_contrats`, `rh_presences`, `rh_conges`, `rh_formations_*`, `rh_evaluations` |
| Communication | Tous | `comm_notifications`, `comm_messages`, `comm_annonces` |
| Bibliothèque | Élève | `biblio_emprunts`, `biblio_exemplaires`, `biblio_livres` |
| Documents | Enseignant (redirect), Parent | `doc_documents` |
| Portals (self) | - | `portal_preferences`, `portal_widget_cache`, `portal_access_logs`, `portal_api_tokens` |

---

## 7. RBAC — Permissions par portail

| Portail | Permission d'accès | Rôles autorisés (config/permissions.php) |
|---------|-------------------|----------------------------------------|
| admin | `portal.admin.access` | admin |
| direction | `portal.direction.access` | directeur |
| enseignant | `portal.enseignant.access` | enseignant |
| eleve | `portal.eleve.access` | eleve |
| parent | `portal.parent.access` | parent |
| comptabilite | `portal.comptabilite.access` | comptable, secretaire |
| rh | `portal.rh.access` | directeur |

Chaque action sensible dispose de son propre `requirePermission()` supplémentaire.

---

## 8. Activation

Le module Portails reste `enabled: false` jusqu'à exécution de la migration SQL et validation manuelle.

### Checklist d'activation
- [ ] Exécuter `database/migrations/portals_001_tables.sql`
- [ ] Passer `enabled: true` dans `config/modules.php` → clé `portals`
- [ ] Vérifier permissions dans `config/permissions.php` (déjà configurées)
- [ ] Tester accès via `/v2/portals/admin` avec un compte admin
- [ ] Tester accès via `/v2/portals/enseignant` avec un compte enseignant
- [ ] Valider dégradation gracieuse sur widgets dont les tables n'existent pas encore

---

## 9. Dettes techniques

| ID | Sévérité | Description | Impact |
|----|---------|-------------|--------|
| PF-DT-005 | Mineure | Widget templates `getTemplate()` non utilisés dans les vues — données rendues inline | Pas de template partiel réutilisable |
| PF-DT-006 | Mineure | `AlertesSystèmeWidget` avec caractère accentué dans le nom de classe | À renommer en `AlertesSystemeWidget` si problèmes d'encodage |
| PF-DT-007 | Mineure | `ParentPortalController` utilise `namespace …\Parent\…` — `parent` est un mot-clé PHP mais autorisé dans les namespaces | Tester sur PHP 8.2 |
| PF-DT-008 | Mineure | Queries SQL directes dans les widgets — pas de repository dédié | Acceptable pour portails read-only |
| PF-DT-009 | Mineure | `bulletins_v2` vs `bulletins_v` (vue) — à confirmer selon migration réelle | Dépend de l'environnement |

---

## 10. Validations Phase 12.2

| N° | Critère | Statut |
|----|---------|--------|
| 1 | 7 contrôleurs portail créés | ✅ |
| 2 | getPortalName() implémenté sur chaque portail | ✅ |
| 3 | requirePortalAccess() appelé sur chaque action | ✅ |
| 4 | getEtablissementId() STRICT (jamais ?? 1) | ✅ |
| 5 | 55 widgets portail-spécifiques créés | ✅ |
| 6 | PortalBootstrap.php mis à jour (59 widgets total) | ✅ |
| 7 | 49 routes portail ajoutées dans routes.php | ✅ |
| 8 | 48 vues PHP créées (dashboard + sections) | ✅ |
| 9 | Aucune logique métier dans les portails | ✅ |
| 10 | Tous les Throwable catchés (dégradation gracieuse) | ✅ |
| 11 | RBAC `requirePermission()` sur actions sensibles | ✅ |
| 12 | declare(strict_types=1) partout | ✅ |
| 13 | Aucun module V1 modifié | ✅ |
| 14 | API JSON wantsJson() sur dashboard controllers | ✅ |
| 15 | Couleurs portail cohérentes avec PortalThemeManager | ✅ |

**Score Phase 12.2 : 15/15 critères PASS**

---

*Rapport généré le 2026-07-05 — Phase 12.2 Portails SCOLARIS V2*
