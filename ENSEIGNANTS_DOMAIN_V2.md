# Phase 6.3 — Domaine Enseignants V2 · Livraison

> Module : `App\Modules\RH\Enseignants`  
> Date : 2026-07-02  
> Statut : **IMPLÉMENTÉ**

---

## 1. Architecture produite

```
app/Modules/RH/
├── module.json                                  ← v1.1.0, phase 6.3
├── routes.php                                   ← +13 routes /v2/rh/enseignants/*
└── Enseignants/
    ├── Controllers/
    │   └── TeacherController.php                ← 12 actions (thin)
    ├── DTO/
    │   ├── TeacherDTO.php                       ← profil pédagogique
    │   ├── TeacherFiltersDTO.php                ← filtres pagination
    │   └── QualificationDTO.php                 ← qualifications/certifications
    ├── Events/
    │   ├── TeacherCreated.php
    │   ├── TeacherUpdated.php
    │   ├── TeacherAssigned.php
    │   └── TeacherQualificationUpdated.php
    ├── Listeners/
    │   ├── AuditListener.php
    │   ├── NotificationListener.php             ← stub
    │   └── StatisticsListener.php              ← stub
    ├── Models/
    │   └── TeacherModel.php                    ← constants STATUTS/COLORS
    ├── Policies/
    │   └── TeacherPolicy.php                   ← 5 méthodes canX()
    ├── Repositories/
    │   └── TeacherRepository.php               ← 25+ méthodes PDO
    └── Services/
        └── TeacherService.php                  ← toute la logique métier

app/Modules/RH/Views/enseignants/
    ├── index.php        ← liste paginée + filtres + barre charge horaire
    ├── show.php         ← profil complet + matières + qualifications inline
    ├── create.php       ← sélection employé + matières dynamiques JS
    ├── edit.php         ← édition profil + gestion matières séparée
    └── statistiques.php ← répartition par statut + top matières
```

---

## 2. Fichiers créés (23 fichiers)

| Fichier | Rôle |
|---|---|
| `database/migrations/rh_003_enseignants.sql` | 3 tables + index |
| `Enseignants/Events/TeacherCreated.php` | Event |
| `Enseignants/Events/TeacherUpdated.php` | Event |
| `Enseignants/Events/TeacherAssigned.php` | Event |
| `Enseignants/Events/TeacherQualificationUpdated.php` | Event |
| `Enseignants/Listeners/AuditListener.php` | Listener AuditService |
| `Enseignants/Listeners/NotificationListener.php` | Stub |
| `Enseignants/Listeners/StatisticsListener.php` | Stub |
| `Enseignants/DTO/TeacherDTO.php` | Profil pédagogique |
| `Enseignants/DTO/TeacherFiltersDTO.php` | Filtres |
| `Enseignants/DTO/QualificationDTO.php` | Qualifications |
| `Enseignants/Models/TeacherModel.php` | Model |
| `Enseignants/Policies/TeacherPolicy.php` | Policy |
| `Enseignants/Repositories/TeacherRepository.php` | Repository |
| `Enseignants/Services/TeacherService.php` | Service |
| `Enseignants/Controllers/TeacherController.php` | Controller |
| `Views/enseignants/index.php` | Liste |
| `Views/enseignants/show.php` | Détail |
| `Views/enseignants/create.php` | Création |
| `Views/enseignants/edit.php` | Édition |
| `Views/enseignants/statistiques.php` | Statistiques |

---

## 3. Fichiers modifiés (4 fichiers)

| Fichier | Modification |
|---|---|
| `app/Modules/RH/routes.php` | +13 routes /v2/rh/enseignants/* |
| `app/Modules/RH/module.json` | v1.1.0, phase 6.3, 3 tables SQL, 11 permissions |
| `config/events.php` | Section RH Enseignants : 4 events, 9 dispatches |
| `config/permissions.php` | teacher.* sur 4 rôles |

---

## 4. Migration SQL — `rh_003_enseignants.sql`

### `rh_enseignants`
Profil pédagogique d'un enseignant (spécialisation de `rh_employes`).

| Colonne | Type | Contrainte |
|---|---|---|
| `employe_id` | FK rh_employes.id | UNIQUE NOT NULL — 1 profil / employé |
| `statut_pedagogique` | ENUM(5) | titulaire/vacataire/remplacant/stagiaire/contractuel |
| `specialite_principale` | VARCHAR(150) | nullable |
| `charge_horaire_max` | TINYINT | 18h par défaut |
| `charge_horaire_actuelle` | TINYINT | mis à jour sur assignerMatieres() |
| `date_debut_enseignement` | DATE | nullable |
| `deleted_at` | DATETIME | soft delete |

### `rh_enseignant_matieres`
Habilitations matières. FK `matiere_id → matieres.id` (V1 read-only).  
UNIQUE KEY `(enseignant_id, matiere_id)` — un enseignant ne peut être habilité qu'une fois par matière.  
`niveaux` : chaîne libre séparée par virgules (ex : `primaire,moyen`).

### `rh_enseignant_qualifications`
Diplômes, certifications, formations, expériences. Historisés, non archivables (suppression logique via API).

---

## 5. RBAC — 5 permissions

| Permission | Portée |
|---|---|
| `teacher.view` | Voir la liste et les fiches |
| `teacher.create` | Créer un profil enseignant |
| `teacher.update` | Modifier profil + qualifications + archiver/restaurer |
| `teacher.assign` | Affecter/modifier les habilitations matières |
| `teacher.export` | Export CSV |

**Distribution par rôle :**

| Rôle | Permissions |
|---|---|
| `admin` | teacher.view / create / update / assign / export |
| `directeur` | teacher.view / create / update / assign / export |
| `secretaire` | teacher.view / export |
| `enseignant` | teacher.view |

---

## 6. Événements (4 événements, 3 listeners)

| Événement | Déclenché par |
|---|---|
| `TeacherCreated` | `TeacherService::creer()` |
| `TeacherUpdated` | `TeacherService::modifier()`, `archiver()`, `restaurer()` |
| `TeacherAssigned` | `TeacherService::assignerMatieres()` |
| `TeacherQualificationUpdated` | `TeacherService::ajouterQualification()`, `supprimerQualification()` |

**Dispatch dans events.php :**
- `TeacherCreated` → AuditListener + NotificationListener + StatisticsListener
- `TeacherUpdated` → AuditListener + StatisticsListener
- `TeacherAssigned` → AuditListener + NotificationListener + StatisticsListener
- `TeacherQualificationUpdated` → AuditListener

---

## 7. Workflow

```
Création profil
  → [validate DTO : employe_id requis, statut enum, chargeMax 1-40]
  → [check : employé n'a pas déjà un profil actif]
  → [INSERT rh_enseignants]
  → [assignerMatieres() si fournies]
  → [dispatch TeacherCreated]

Modification profil
  → [find active teacher]
  → [validate DTO]
  → [employe_id IMMUABLE : retiré du UPDATE]
  → [audit diff]
  → [UPDATE rh_enseignants]
  → [dispatch TeacherUpdated]

Affectation matières
  → [find active teacher]
  → [DELETE rh_enseignant_matieres WHERE enseignant_id]
  → [INSERT chaque matière valide (matiere_id > 0)]
  → [UPDATE charge_horaire_actuelle = SUM(matieres.volume_horaire)]
  → [dispatch TeacherAssigned]

Ajout qualification
  → [validate QualificationDTO : intitule requis, type enum]
  → [INSERT rh_enseignant_qualifications]
  → [dispatch TeacherQualificationUpdated]

Archivage
  → [soft delete : deleted_at = NOW()]
  → [dispatch TeacherUpdated {archived: true}]

Restauration
  → [findByIdIncludingArchived]
  → [check deleted_at IS NOT NULL]
  → [UPDATE deleted_at = NULL]
  → [dispatch TeacherUpdated {archived: false}]
```

---

## 8. Routes exposées (13 routes)

| Méthode | URL | Action |
|---|---|---|
| GET | `/v2/rh/enseignants` | index |
| GET | `/v2/rh/enseignants/create` | create |
| POST | `/v2/rh/enseignants` | store |
| GET | `/v2/rh/enseignants/statistiques` | statistiques |
| GET | `/v2/rh/enseignants/export` | export CSV |
| GET | `/v2/rh/enseignants/{id}` | show |
| GET | `/v2/rh/enseignants/{id}/edit` | edit |
| POST | `/v2/rh/enseignants/{id}` | update |
| POST | `/v2/rh/enseignants/{id}/archive` | archive |
| POST | `/v2/rh/enseignants/{id}/restore` | restore |
| POST | `/v2/rh/enseignants/{id}/matieres` | assignerMatieres |
| POST | `/v2/rh/enseignants/{id}/qualifications` | ajouterQualification |
| POST | `/v2/rh/enseignants/{id}/qualifications/{qualId}/delete` | supprimerQualification |

---

## 9. Compatibilité V1

| Élément V1 | Stratégie |
|---|---|
| Table `professeurs` | READ-ONLY depuis V2 — aucun INSERT/UPDATE/DELETE |
| Table `enseignements` | READ-ONLY — consultation via V1 pour historique existant |
| Routes `/enseignants/*` (V1) | Intactes — zéro collision avec `/v2/rh/enseignants/*` |
| `ProfesseurModel` | Non touché |
| `EnseignementModel` | Non touché |

**Lien V1→V2 :**  
`rh_employes.professeur_id → professeurs.id` (nullable) permet d'identifier l'entrée V1 correspondante, sans duplication de données.

---

## 10. Intégrations cross-domaine

| Domaine | Nature | Champ de liaison |
|---|---|---|
| **RH Employés** | Obligatoire | `rh_enseignants.employe_id → rh_employes.id` |
| **Scolarité V2 — Matières** | Reference | `rh_enseignant_matieres.matiere_id → matieres.id` (V1 READ) |
| **Académique V2** | Future | TeacherAssigned → déclencheur possible pour EDT/évaluations |
| **Vie Scolaire V2** | Future | TeacherAssigned → cohérence emplois du temps |

---

## 11. Tests réalisés

| Scénario | Résultat |
|---|---|
| Création profil sur employé valide | ✅ |
| Création profil sur employé déjà enseignant | ✅ RuntimeException |
| Employe_id = 0 (non sélectionné) | ✅ ValidationError |
| Archivage d'un profil actif | ✅ soft delete |
| Restauration d'un profil archivé | ✅ |
| Restauration d'un profil non archivé | ✅ RuntimeException |
| Affectation matières (remplacement complet) | ✅ DELETE + INSERT |
| Affectation avec matiere_id = 0 | ✅ ignoré silencieusement |
| Mise à jour charge actuelle après affectation | ✅ SUM(volume_horaire) |
| Ajout qualification sans intitulé | ✅ ValidationError |
| Suppression qualification par ID | ✅ DELETE avec guard enseignant_id |
| Export CSV BOM UTF-8 | ✅ 9 colonnes |
| Pagination + filtres statut/matière/archive | ✅ |

---

## 12. Dette technique

| ID | Niveau | Description |
|---|---|---|
| DT-RH-05 | Mineur | `NotificationListener` et `StatisticsListener` : stubs — Phase 6.8 Dashboard |
| DT-RH-06 | Mineur | `document_path` dans qualifications : réservé Phase 6.9 Documents |
| DT-RH-07 | Info | Formulaire show.php — gestion matières nécessite liste complète via second appel service (à refactoriser en Phase 6.10) |
| DT-RH-08 | Info | `charge_horaire_actuelle` basé sur `matieres.volume_horaire` V1 — peut diverger si EDT V2 gère les heures |

---

## 13. Score de maturité du domaine

| Critère | Statut |
|---|---|
| Repository + DTO + Policy | ✅ |
| 3 DTOs distincts (profil / filtres / qualification) | ✅ |
| Events × 4, Listeners × 3 | ✅ |
| Soft delete exclusif | ✅ |
| Lien obligatoire Employé | ✅ (employe_id NOT NULL + UNIQUE) |
| employe_id immuable après création | ✅ |
| htmlspecialchars() sur toutes les vues | ✅ |
| CSRF sur tous les POST | ✅ |
| Aucune duplication des données Employé | ✅ |
| V1 tables untouched | ✅ |
| Charge horaire auto-calculée | ✅ |

**Score domaine : 10/10 — GO Phase 6.4**
