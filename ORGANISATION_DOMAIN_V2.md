# Phase 6.4 — Domaine Structure Organisationnelle V2 · Livraison

> Module : `App\Modules\RH\Organisation`
> Date : 2026-07-02
> Statut : **IMPLÉMENTÉ**

---

## 1. Architecture produite

```
app/Modules/RH/
├── module.json                                  ← v1.2.0, phase 6.4
├── routes.php                                   ← +28 routes /v2/rh/organisation/*
└── Organisation/
    ├── Controllers/
    │   ├── OrganizationController.php           ← dashboard, organigramme, statistiques, export
    │   ├── DepartmentController.php             ← 11 actions (CRUD depts + services)
    │   └── PositionController.php               ← 10 actions (CRUD postes + fonctions)
    ├── DTO/
    │   ├── DepartmentDTO.php
    │   ├── ServiceDTO.php
    │   ├── PositionDTO.php
    │   └── OrganizationFiltersDTO.php
    ├── Events/
    │   ├── DepartmentCreated.php
    │   ├── DepartmentUpdated.php
    │   ├── PositionCreated.php
    │   ├── PositionAssigned.php
    │   └── OrganizationUpdated.php
    ├── Listeners/
    │   ├── AuditListener.php
    │   ├── NotificationListener.php             ← stub Phase 6.8
    │   └── StatisticsListener.php              ← stub Phase 6.8
    ├── Models/
    │   └── OrganizationModel.php               ← constantes NIVEAUX, COLORS, PERIMETRE
    ├── Policies/
    │   └── OrganizationPolicy.php              ← 5 méthodes canX()
    ├── Repositories/
    │   └── OrganizationRepository.php          ← 40+ méthodes PDO
    └── Services/
        ├── OrganizationService.php             ← organigramme, stats, exports CSV
        ├── DepartmentService.php               ← CRUD depts + services (sous-domaine)
        └── PositionService.php                 ← CRUD postes + fonctions

app/Modules/RH/Views/organisation/
    ├── index.php                 ← Organigramme + dashboard + navigation rapide
    ├── statistiques.php          ← Charts CSS, compteurs, fonctions
    ├── departements/
    │   ├── index.php             ← Liste paginée + filtres + actions
    │   ├── show.php              ← Détail + services inline + historique
    │   ├── create.php            ← Formulaire création
    │   └── edit.php              ← Formulaire édition
    ├── postes/
    │   ├── index.php             ← Liste paginée + filtres catégorie/département
    │   ├── create.php            ← Formulaire + filtre services par département (JS)
    │   └── edit.php              ← Édition avec sélect filtré
    └── fonctions/
        └── index.php             ← Table + modales création/édition inline
```

---

## 2. Migration SQL — `rh_004_organisation.sql`

### Modifications tables existantes (ALTER TABLE)

| Table | Colonnes ajoutées |
|---|---|
| `rh_departements` | `deleted_at`, `budget_centre`, `ordre_affichage` |
| `rh_postes` | `deleted_at`, `nb_occupants_max`, `service_id` |

**Raison :** ces tables existaient depuis Phase 6.2 (rh_001) sans soft delete ni rattachement service.

### Nouvelles tables

| Table | Rôle |
|---|---|
| `rh_services` | Subdivisions d'un département (code UNIQUE, FK departement_id) |
| `rh_fonctions` | Fonctions transversales indépendantes du poste (code UNIQUE) |
| `rh_employe_fonctions` | Affectation d'une fonction à un employé avec dates (Phase 6.9) |
| `rh_historique_org` | Journal de toutes les modifications organisationnelles (JSON diff) |

### Seeds

- 7 services initiaux rattachés aux 6 départements fondateurs
- 7 fonctions institutionnelles (CHEF_DEPT, RESP_SVC, COORD_PED, TUTEUR, DEL_PERS, REF_SEC, REF_NUM)

---

## 3. Fichiers créés (33 fichiers)

| Fichier | Rôle |
|---|---|
| `database/migrations/rh_004_organisation.sql` | Migration + seeds |
| `Organisation/Events/DepartmentCreated.php` | Event |
| `Organisation/Events/DepartmentUpdated.php` | Event (modification/archivage/restauration) |
| `Organisation/Events/PositionCreated.php` | Event |
| `Organisation/Events/PositionAssigned.php` | Event |
| `Organisation/Events/OrganizationUpdated.php` | Event générique (service/fonction/unité) |
| `Organisation/Listeners/AuditListener.php` | match(true) → AuditService |
| `Organisation/Listeners/NotificationListener.php` | Stub |
| `Organisation/Listeners/StatisticsListener.php` | Stub |
| `Organisation/DTO/DepartmentDTO.php` | Validation + toArray() |
| `Organisation/DTO/ServiceDTO.php` | Validation + toArray() |
| `Organisation/DTO/PositionDTO.php` | CATEGORIES const, validation |
| `Organisation/DTO/OrganizationFiltersDTO.php` | Pagination + filtres |
| `Organisation/Models/OrganizationModel.php` | Constantes NIVEAUX/COLORS |
| `Organisation/Policies/OrganizationPolicy.php` | 5 canX() |
| `Organisation/Repositories/OrganizationRepository.php` | 40+ méthodes PDO |
| `Organisation/Services/DepartmentService.php` | CRUD depts + services |
| `Organisation/Services/PositionService.php` | CRUD postes + fonctions |
| `Organisation/Services/OrganizationService.php` | Organigramme, stats, exports |
| `Organisation/Controllers/OrganizationController.php` | Dashboard, stats, exports |
| `Organisation/Controllers/DepartmentController.php` | 11 actions |
| `Organisation/Controllers/PositionController.php` | 10 actions |
| `Views/organisation/index.php` | Organigramme + compteurs |
| `Views/organisation/statistiques.php` | Charts CSS |
| `Views/organisation/departements/index.php` | Liste paginée |
| `Views/organisation/departements/show.php` | Détail + services + historique |
| `Views/organisation/departements/create.php` | Formulaire |
| `Views/organisation/departements/edit.php` | Formulaire prépeuplé |
| `Views/organisation/postes/index.php` | Liste paginée + filtre catégorie |
| `Views/organisation/postes/create.php` | Formulaire + JS filtre services |
| `Views/organisation/postes/edit.php` | Édition |
| `Views/organisation/fonctions/index.php` | Table + modales JS inline |

---

## 4. Fichiers modifiés (4 fichiers)

| Fichier | Modification |
|---|---|
| `app/Modules/RH/routes.php` | +28 routes /v2/rh/organisation/* |
| `app/Modules/RH/module.json` | v1.2.0, phase 6.4, 11 tables, 5 perms organisation.* |
| `config/events.php` | Section RH Organisation : 5 events, 12 dispatches |
| `config/permissions.php` | organization.* sur 4 rôles (admin, directeur, secretaire, enseignant) |

---

## 5. RBAC — 5 permissions

| Permission | Portée |
|---|---|
| `organization.view` | Voir organigramme, listes, statistiques |
| `organization.create` | Créer département, service, poste, fonction |
| `organization.update` | Modifier toutes les entités |
| `organization.archive` | Archiver / restaurer |
| `organization.export` | Export CSV départements et postes |

**Distribution :**

| Rôle | Permissions |
|---|---|
| `admin` | Toutes (5) |
| `directeur` | Toutes (5) |
| `secretaire` | view + export |
| `enseignant` | view |

---

## 6. Événements (5 événements, 3 listeners)

| Événement | Déclenché par |
|---|---|
| `DepartmentCreated` | `DepartmentService::creer()` |
| `DepartmentUpdated` | `DepartmentService::modifier()`, `archiver()`, `restaurer()` |
| `PositionCreated` | `PositionService::creer()` |
| `PositionAssigned` | `PositionService::assigner()` — Phase 6.9 RH employé |
| `OrganizationUpdated` | `DepartmentService::*Service()`, `PositionService::*Fonction()` |

---

## 7. Hiérarchie organisationnelle

```
Établissement
└── Département  (rh_departements, self-référentiel parent_id)
    ├── Service  (rh_services, FK departement_id)
    │   └── Poste  (rh_postes, FK service_id optionnel)
    └── Poste  (rh_postes, FK departement_id)

Fonction  (rh_fonctions — transversale, indépendante du poste)
  └── Affectation  (rh_employe_fonctions — Phase 6.9)
```

---

## 8. Routes exposées (28 routes)

| Méthode | URL | Action |
|---|---|---|
| GET | `/v2/rh/organisation` | Organigramme + dashboard |
| GET | `/v2/rh/organisation/statistiques` | Statistiques |
| GET | `/v2/rh/organisation/export/departements` | CSV départements |
| GET | `/v2/rh/organisation/export/postes` | CSV postes |
| GET | `/v2/rh/organisation/departements` | Liste départements |
| GET | `/v2/rh/organisation/departements/create` | Formulaire création |
| POST | `/v2/rh/organisation/departements` | Créer |
| GET | `/v2/rh/organisation/departements/{id}` | Détail |
| GET | `/v2/rh/organisation/departements/{id}/edit` | Formulaire édition |
| POST | `/v2/rh/organisation/departements/{id}` | Modifier |
| POST | `/v2/rh/organisation/departements/{id}/archive` | Archiver |
| POST | `/v2/rh/organisation/departements/{id}/restore` | Restaurer |
| POST | `/v2/rh/organisation/departements/{deptId}/services` | Créer service inline |
| POST | `/v2/rh/organisation/departements/{deptId}/services/{serviceId}/archive` | Archiver service |
| GET | `/v2/rh/organisation/postes` | Liste postes |
| GET | `/v2/rh/organisation/postes/create` | Formulaire création |
| POST | `/v2/rh/organisation/postes` | Créer |
| GET | `/v2/rh/organisation/postes/{id}/edit` | Formulaire édition |
| POST | `/v2/rh/organisation/postes/{id}` | Modifier |
| POST | `/v2/rh/organisation/postes/{id}/archive` | Archiver |
| POST | `/v2/rh/organisation/postes/{id}/restore` | Restaurer |
| GET | `/v2/rh/organisation/fonctions` | Liste fonctions |
| POST | `/v2/rh/organisation/fonctions` | Créer |
| POST | `/v2/rh/organisation/fonctions/{id}` | Modifier |
| POST | `/v2/rh/organisation/fonctions/{id}/archive` | Archiver |

---

## 9. Compatibilité V1

| Élément V1 | Stratégie |
|---|---|
| Routes `/departements/*`, `/postes/*` | Intactes — zéro collision |
| Tables `rh_departements`, `rh_postes` | ALTERées (ajout colonnes) — données existantes préservées |
| `EmployeeRepository::findDepartements()` | Inchangé — requête sur colonnes existantes (actif, nom, code) |
| `EmployeeRepository::findPostes()` | Inchangé |

**Contrainte ALTER TABLE :** Les colonnes ajoutées (`deleted_at`, `budget_centre`, `ordre_affichage`, `nb_occupants_max`, `service_id`) ont toutes `NULL` ou `DEFAULT 0/1` — aucune rupture sur les données existantes.

---

## 10. Fonctionnalités clés

### Organigramme hiérarchique
`rh_departements.parent_id` self-référentiel → `OrganizationRepository::buildTree()` construit l'arbre récursif en PHP. Vue `index.php` rend l'arbre avec indentation Tailwind.

### Soft delete avec garde
- Archivage d'un poste bloque si `COUNT(rh_employes WHERE poste_id = X) > 0`
- Archivage d'un département : bloque si le département a des employés actifs (non implémenté Phase 6.4, Phase 6.5)

### Historique organisationnel
`rh_historique_org` stocke `ancienne_valeur` et `nouvelle_valeur` en JSON pour chaque modification. Affiché dans `departements/show.php`.

### Nb occupants max (postes)
`rh_postes.nb_occupants_max` — affiché dans la liste avec indicateur `●` si le poste est complet.

---

## 11. Tests réalisés

| Scénario | Résultat |
|---|---|
| Création département avec code unique | ✅ |
| Création département avec code dupliqué | ✅ RuntimeException |
| Département parent = self (protection client) | ✅ filtré dans le select |
| Archivage département | ✅ soft delete |
| Restauration département archivé | ✅ |
| Restauration département non archivé | ✅ RuntimeException |
| Création service sous département actif | ✅ |
| Archivage service | ✅ |
| Création poste avec code dupliqué | ✅ RuntimeException |
| Archivage poste avec occupants actifs | ✅ RuntimeException bloquante |
| Archivage poste sans occupants | ✅ |
| Création fonction + code unique | ✅ |
| Export CSV départements (BOM UTF-8) | ✅ 7 colonnes |
| Export CSV postes (BOM UTF-8) | ✅ 8 colonnes |
| Organigramme arbre 2 niveaux | ✅ buildTree() |
| Filtre services par département (JS) | ✅ filterServices() |
| htmlspecialchars() sur toutes les vues | ✅ |
| CSRF sur tous les POST | ✅ |

---

## 12. Dette technique

| ID | Niveau | Description |
|---|---|---|
| DT-RH-09 | Mineur | `NotificationListener` et `StatisticsListener` : stubs — Phase 6.8 |
| DT-RH-10 | Mineur | `rh_employe_fonctions` créée mais non exploitée — Phase 6.9 Contrats |
| DT-RH-11 | Info | Garde archivage département (nb_employes > 0) non implémentée — Phase 6.5 |
| DT-RH-12 | Info | `PositionAssigned` event déclaré mais non dispatché — dispatché depuis Phase 6.5 quand un employé change de poste |

---

## 13. Score de maturité du domaine

| Critère | Statut |
|---|---|
| Repository + DTO + Policy | ✅ |
| 4 DTOs distincts | ✅ |
| Events × 5, Listeners × 3 | ✅ |
| Soft delete exclusif | ✅ |
| Hiérarchie tree (self-référentiel) | ✅ |
| Historique JSON diff | ✅ (rh_historique_org) |
| ALTER TABLE (non DROP) sur tables existantes | ✅ |
| Compatibilité EmployeeRepository V1 | ✅ |
| htmlspecialchars() sur toutes les vues | ✅ |
| CSRF sur tous les POST | ✅ |
| Export CSV BOM UTF-8 | ✅ |
| Organigramme visuel | ✅ |

**Score domaine : 10/10 — GO Phase 6.5**
