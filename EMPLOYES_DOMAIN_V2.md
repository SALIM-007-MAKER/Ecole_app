# Phase 6.2 — Domaine Employés V2 · Livraison

> Module : `App\Modules\RH\Employes`  
> Date : 2026-07-02  
> Statut : **IMPLÉMENTÉ**

---

## 1. Architecture produite

```
app/Modules/RH/
├── module.json                             ← manifest (v1.0.0, enabled: true)
├── routes.php                              ← 11 routes /v2/rh/employes/*
├── Employes/
│   ├── Controllers/
│   │   └── EmployeeController.php          ← 11 actions (thin)
│   ├── DTO/
│   │   ├── EmployeeDTO.php                 ← fromRequest / validate / toArray
│   │   └── EmployeeFiltersDTO.php          ← filtres pagination
│   ├── Events/
│   │   ├── EmployeeCreated.php
│   │   ├── EmployeeUpdated.php
│   │   ├── EmployeeArchived.php
│   │   └── EmployeeRestored.php
│   ├── Listeners/
│   │   ├── AuditListener.php               ← AuditService intégré
│   │   ├── NotificationListener.php        ← stub (MS2-M-004)
│   │   └── StatisticsListener.php          ← stub (cache futur)
│   ├── Models/
│   │   └── EmployeeModel.php               ← constants TYPES/STATUTS/COLORS
│   ├── Policies/
│   │   └── EmployeePolicy.php              ← 6 méthodes canX()
│   ├── Repositories/
│   │   └── EmployeeRepository.php          ← 20+ méthodes PDO
│   └── Services/
│       └── EmployeeService.php             ← toute la logique métier
└── Views/
    └── employes/
        ├── index.php                       ← liste paginée + filtres + stats rapides
        ├── show.php                        ← détail complet + contacts urgence
        ├── create.php                      ← formulaire création (JS contacts dynamiques)
        ├── edit.php                        ← formulaire édition préchargé
        └── statistiques.php               ← barres par statut / type / département
```

---

## 2. Fichiers créés (25 fichiers)

| Fichier | Rôle |
|---|---|
| `database/migrations/rh_001_organisation.sql` | Tables rh_departements + rh_postes + seeds |
| `database/migrations/rh_002_employes.sql` | Tables rh_employes + rh_contacts_urgence |
| `app/Modules/RH/module.json` | Manifest module |
| `app/Modules/RH/routes.php` | 11 routes |
| `app/Modules/RH/Employes/Events/EmployeeCreated.php` | Event |
| `app/Modules/RH/Employes/Events/EmployeeUpdated.php` | Event |
| `app/Modules/RH/Employes/Events/EmployeeArchived.php` | Event |
| `app/Modules/RH/Employes/Events/EmployeeRestored.php` | Event |
| `app/Modules/RH/Employes/Listeners/AuditListener.php` | Listener |
| `app/Modules/RH/Employes/Listeners/NotificationListener.php` | Listener stub |
| `app/Modules/RH/Employes/Listeners/StatisticsListener.php` | Listener stub |
| `app/Modules/RH/Employes/DTO/EmployeeDTO.php` | DTO complet |
| `app/Modules/RH/Employes/DTO/EmployeeFiltersDTO.php` | DTO filtres |
| `app/Modules/RH/Employes/Models/EmployeeModel.php` | Model |
| `app/Modules/RH/Employes/Policies/EmployeePolicy.php` | Policy |
| `app/Modules/RH/Employes/Repositories/EmployeeRepository.php` | Repository |
| `app/Modules/RH/Employes/Services/EmployeeService.php` | Service |
| `app/Modules/RH/Employes/Controllers/EmployeeController.php` | Controller |
| `app/Modules/RH/Views/employes/index.php` | Vue liste |
| `app/Modules/RH/Views/employes/show.php` | Vue détail |
| `app/Modules/RH/Views/employes/create.php` | Vue création |
| `app/Modules/RH/Views/employes/edit.php` | Vue édition |
| `app/Modules/RH/Views/employes/statistiques.php` | Vue stats |

---

## 3. Fichiers modifiés (3 fichiers)

### `config/modules.php`
- Ajout entrée `rh` : `enabled: true`, namespace, routes, manifest

### `config/events.php`
- **Bugfix AR-C-002** : suppression des 4 premières occurrences dupliquées des clés Finance :
  - `InvoiceCancelled` (ancienne ligne 440) → supprimée
  - `PaymentCompleted` (ancienne ligne 459) → supprimée
  - `PaymentRefunded` (ancienne ligne 468) → supprimée
  - `CashMovementCreated` (ancienne ligne 490) → supprimée
- Ajout section `── Module RH V2 — Employés ──` avec 4 événements → [AuditListener, NotificationListener, StatisticsListener]

### `config/permissions.php`
Permissions ajoutées par rôle :

| Rôle | Permissions |
|---|---|
| `admin` | employee.view / create / update / archive / restore / export |
| `directeur` | employee.view / create / update / archive / restore / export |
| `secretaire` | employee.view / export |
| `enseignant` | employee.view.own |

---

## 4. Migrations SQL

### `rh_001_organisation.sql`
- `rh_departements` (6 départements seeds : DIR/ADMIN/ENS/FIN/VS/TECH)
- `rh_postes` (13 postes seeds : DIR, DIR_ADJ, SEC, SEC_DIR, COMPTA, ENS_PES/PEM/CONT/VAC/MF, SURV, ENTRET, INFOR)

### `rh_002_employes.sql`
- `rh_employes` — dossier complet employé avec soft delete (`deleted_at`)
- `rh_contacts_urgence` — contacts d'urgence liés à l'employé

---

## 5. RBAC — 6 permissions

| Permission | Portée |
|---|---|
| `employee.view` | Voir la liste et les fiches |
| `employee.create` | Créer un nouvel employé |
| `employee.update` | Modifier + changer statut |
| `employee.archive` | Soft delete |
| `employee.restore` | Restaurer depuis archive |
| `employee.export` | Export CSV |

---

## 6. Événements (4 événements, 3 listeners)

| Événement | Déclenché par |
|---|---|
| `EmployeeCreated` | `EmployeeService::creer()` |
| `EmployeeUpdated` | `EmployeeService::modifier()` + `changerStatut()` |
| `EmployeeArchived` | `EmployeeService::archiver()` |
| `EmployeeRestored` | `EmployeeService::restaurer()` |

**Listeners actifs :**
- `AuditListener` → `AuditService::logCreate/logUpdate/logDelete/log`
- `NotificationListener` → stub (MS2-M-004 : déclenchers V2 non encore définis)
- `StatisticsListener` → stub (cache futur)

---

## 7. Workflow employé

```
Création → [validate DTO] → [check email unique] → [générer matricule YYYY-NNNNN]
        → [INSERT rh_employes] → [sauver contacts urgence] → [dispatch EmployeeCreated]

Modification → [find] → [validate] → [check email unique excl. self] → [diff audit]
            → [UPDATE] → [reset contacts] → [dispatch EmployeeUpdated]

Changement statut → [validate enum] → [check not same] → [UPDATE statut]
                 → [dispatch EmployeeUpdated (changes: {statut})]

Archivage → [find actif] → [UPDATE deleted_at = NOW()] → [dispatch EmployeeArchived]

Restauration → [find incl. archivés] → [check deleted_at IS NOT NULL]
            → [UPDATE deleted_at = NULL, statut = 'actif'] → [dispatch EmployeeRestored]
```

---

## 8. Stratégie compatibilité V1

- Aucune table V1 modifiée (`professeurs`, `users`, `enseignements`)
- Liens nullable : `rh_employes.professeur_id → professeurs.id`, `rh_employes.user_id → users.id`
- Migration manuelle V1→V2 prévue : Phase 6.11 (Import RH)
- Routes V1 (`/enseignants`, `/professeurs`) intactes

---

## 9. Routes exposées

| Méthode | URL | Action |
|---|---|---|
| GET | `/v2/rh/employes` | index |
| GET | `/v2/rh/employes/create` | create |
| POST | `/v2/rh/employes` | store |
| GET | `/v2/rh/employes/statistiques` | statistiques |
| GET | `/v2/rh/employes/export` | export CSV |
| GET | `/v2/rh/employes/{id}` | show |
| GET | `/v2/rh/employes/{id}/edit` | edit |
| POST | `/v2/rh/employes/{id}` | update |
| POST | `/v2/rh/employes/{id}/archive` | archive |
| POST | `/v2/rh/employes/{id}/restore` | restore |
| POST | `/v2/rh/employes/{id}/statut` | changerStatut |

---

## 10. Dette technique

| ID | Niveau | Description |
|---|---|---|
| DT-RH-01 | Mineur | `NotificationListener` et `StatisticsListener` : stubs — à implémenter en Phase 6.8 (Dashboard RH) |
| DT-RH-02 | Mineur | Upload photo employé absent — prévu Phase 6.9 (Documents) |
| DT-RH-03 | Mineur | `employee.view.own` reconnu en policy mais pas utilisé dans les vues (page profil employé futur) |
| DT-RH-04 | Info | `professeur_id` FK non indexée — à ajouter en rh_003 si volume > 1000 |

---

## 11. Score de maturité du domaine

| Critère | Statut |
|---|---|
| Repository + DTO + Policy | ✅ |
| Events + Listeners (4/3) | ✅ |
| Soft delete exclusif | ✅ |
| Matricule auto YYYY-NNNNN | ✅ |
| htmlspecialchars sur toutes les vues | ✅ |
| CSRF sur tous les POST | ✅ |
| Zéro DELETE physique | ✅ |
| V1 compat (routes + tables) | ✅ |
| AR-C-002 corrigé | ✅ |
| Permissions en session | ✅ |

**Score domaine : 10/10 — GO Phase 6.3**
