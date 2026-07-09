# Phase 6.6 — Domaine Affectations RH V2
**Date** : 2026-07-02  
**Statut** : IMPLÉMENTÉ  
**Score** : 9/10

---

## 1. Distinction Contrat / Affectation

| Dimension | Contrat | Affectation |
|-----------|---------|-------------|
| Nature | Lien **juridique** | Lien **opérationnel** |
| Décrit | Le cadre légal de l'emploi | Où et comment l'employé travaille |
| Expiration | Date de fin du contrat | Date de fin de la mission |
| Changement | Résiliation + renouvellement | Transfert (même objet, champs modifiés) |
| Parallèles | 1 actif à la fois | Principale : 1 seule / Secondaire+Temporaire : multiples |

---

## 2. Architecture

```
app/Modules/RH/Affectations/
├── Controllers/  AssignmentController.php     (16 actions)
├── DTO/          AssignmentDTO.php             (TYPES, validate, toArray)
│                 AssignmentFiltersDTO.php       (q, type, statut, employeId, deptId, posteId)
│                 MatiereAssignmentDTO.php       (matière + classe + niveau + heures)
├── Events/       AssignmentCreated.php
│                 AssignmentUpdated.php          (action: modification|suspension|reactivation|cloture|matiere_added|matiere_removed)
│                 AssignmentTransferred.php      (from/to org snapshot + motif)
│                 AssignmentArchived.php
├── Listeners/    AuditListener.php             (instance AuditService — pattern correct)
│                 NotificationListener.php      (stub Phase 6.10)
│                 StatisticsListener.php         (stub Phase 6.10)
├── Models/       AssignmentModel.php            (TYPES, STATUTS, CHANGEMENTS, helpers)
├── Policies/     AssignmentPolicy.php           (5 méthodes canX)
├── Repositories/ AssignmentRepository.php       (25 méthodes)
├── Services/     AssignmentService.php          (15 méthodes)
└── Views/        affectations/{index,show,create,edit,statistiques}.php
```

---

## 3. Migration SQL (`rh_006_affectations.sql`)

| Table | Description | Lignes clés |
|-------|-------------|------------|
| `rh_affectations` | Affectation principale | FK RESTRICT → employe, SET NULL → contrat/poste/dept/service/responsable |
| `rh_affectation_matieres` | Matières/classes enseignants | FK CASCADE → affectation, soft refs V1 vers matieres+classes |
| `rh_historique_affectations` | Audit JSON complet | FK CASCADE → affectation, dénorm modifie_par_nom |

**Note V3** : Colonne `etablissement_id` préparée dans `rh_affectations` (`NULL` en V2, FK réseau V3).

---

## 4. Workflow des affectations

```
                  ┌─────────────────────────────┐
                  │         CRÉATION             │
                  │  type: principale/secondaire │
                  │  /temporaire                 │
                  └──────────────┬──────────────┘
                                 ↓
                            [active]
                           /    |    \
              suspendre() /     |     \ clore()
                         ↓  transferer()  ↓
                    [suspendue]   ↕   [terminee]
                         \      |
               reactiver() \    |
                            ↓   ↓
                           [active]
                                |
                           archiver()  → soft delete (deleted_at)
```

**Règles clés :**
- `principale` : une seule `active` à la fois par employé (bloquant)
- `secondaire`/`temporaire` : chevauchement exact (même poste + même département) bloqué
- Contrat lié : vérifié comme actif et appartenant à l'employé
- `clore()` fixe `date_fin = aujourd'hui` et passe à `terminee`

---

## 5. Transfert

Le **transfert** est une action dédiée (`POST /v2/rh/affectations/{id}/transferer`) qui :

1. Requiert un **motif** (obligatoire)
2. Enregistre le snapshot **`from`** (poste_id, departement_id, service_id, responsable_id)
3. Enregistre le snapshot **`to`** (nouvelles valeurs)
4. Ne modifie que les champs effectivement changés (diff automatique)
5. Logue dans `rh_historique_affectations` (`type_changement = 'transfert'`)
6. Dispatche `AssignmentTransferred`

---

## 6. Matières / Classes (enseignants)

Un enseignant peut avoir plusieurs lignes `rh_affectation_matieres` liées à son affectation :

| Champ | Description |
|-------|-------------|
| `matiere_id` | Référence soft vers table `matieres` (V1) |
| `classe_id`  | Référence soft vers table `classes` (V1) |
| `niveau`     | Texte libre : "6ème", "5ème", "Terminale S" |
| `heures_hebdo` | Charge hebdomadaire (max 40h) |
| `date_debut` / `date_fin` | Période de l'affectation matière |

Action `retirer` = `closeMatiereAssignment(date_fin = today)` — pas de DELETE physique.

---

## 7. Routes (16 routes)

| Méthode | URI | Action |
|---------|-----|--------|
| GET | /v2/rh/affectations | index |
| GET | /v2/rh/affectations/create | create |
| POST | /v2/rh/affectations | store |
| GET | /v2/rh/affectations/statistiques | statistiques |
| GET | /v2/rh/affectations/export | export CSV |
| GET | /v2/rh/affectations/{id} | show (+ historique + matieres) |
| GET | /v2/rh/affectations/{id}/edit | edit |
| POST | /v2/rh/affectations/{id} | update |
| POST | /v2/rh/affectations/{id}/transferer | transferer |
| POST | /v2/rh/affectations/{id}/suspendre | suspendre |
| POST | /v2/rh/affectations/{id}/reactiver | reactiver |
| POST | /v2/rh/affectations/{id}/clore | clore |
| POST | /v2/rh/affectations/{id}/archive | archive |
| POST | /v2/rh/affectations/{id}/matieres | storeMatiereAssignment |
| POST | /v2/rh/affectations/{id}/matieres/{matId}/remove | removeMatiereAssignment |

---

## 8. Permissions RBAC (5 permissions)

| Permission | admin | directeur | secretaire | enseignant |
|-----------|:---:|:---:|:---:|:---:|
| assignment.view | ✓ | ✓ | ✓ | ✓ |
| assignment.create | ✓ | ✓ | | |
| assignment.update | ✓ | ✓ | | |
| assignment.archive | ✓ | ✓ | | |
| assignment.export | ✓ | ✓ | ✓ | |

---

## 9. Événements (4 events × 3 listeners)

| Événement | AuditListener | NotificationListener | StatisticsListener |
|-----------|:---:|:---:|:---:|
| AssignmentCreated | ✓ | ✓ (stub) | ✓ (stub) |
| AssignmentUpdated | ✓ | ✓ (stub) | ✓ (stub) |
| AssignmentTransferred | ✓ | ✓ (stub) | ✓ (stub) |
| AssignmentArchived | ✓ | ✓ (stub) | ✓ (stub) |

---

## 10. Intégrations cross-domaine

| Domaine | Type | Mécanisme |
|---------|------|-----------|
| **Employés** | Obligatoire | `employe_id` FK RESTRICT — affectation impossible sans employé |
| **Contrats** | Optionnel-fort | `contrat_id` FK SET NULL — vérifié actif + appartenant à l'employé |
| **Organisation** | Optionnel | `poste_id`, `departement_id`, `service_id` FK SET NULL |
| **Académique** | Via matieres | `rh_affectation_matieres` → `matiere_id` (soft ref V1) + `classe_id` |
| **Vie Scolaire** | Via employe_id | Absences/présences du personnel = employé_id commun |
| **V3 Réseau** | Préparé | `etablissement_id INT NULL` dans `rh_affectations` |

---

## 11. Diagramme métier

```
Employé ──────────────────────────────────────────────────────┐
   │                                                           │
   ├─ Contrat (lien juridique) ←── vérifié actif              │
   │                                                           │
   └─ Affectation (lien opérationnel)                         │
        │                                                      │
        ├─ [principale]  ←── 1 seule active                   │
        │    ├─ Poste                                          │
        │    ├─ Département                                    │
        │    ├─ Service                                        │
        │    ├─ Responsable direct                             │
        │    └─ Matières/Classes (si enseignant)               │
        │         ├─ Matière V1 (soft ref)                    │
        │         ├─ Classe V1 (soft ref)                     │
        │         └─ Niveau + Heures                          │
        │                                                      │
        ├─ [secondaire]  ←── multiple OK, sans doublon exact  │
        │                                                      │
        └─ [temporaire]  ←── multiple OK, sans doublon exact  │
                                                               │
        Historique ←── chaque changement loggué JSON          │
        Transfert   ←── snapshot from/to + motif              │
```

---

## 12. Compatibilité V1

- **Zéro collision** : routes `/v2/rh/affectations/*` uniquement
- **Aucun fichier V1 modifié**
- `rh_affectation_matieres` utilise des **soft references** (sans FK) vers les tables V1 `matieres` et `classes` pour éviter les contraintes d'intégrité cross-module
- `tryFindMatieres()` et `tryFindClasses()` wrappés dans `try/catch` : si les tables V1 sont absentes, les sélecteurs sont vides sans erreur

---

## 13. Stratégie de migration V1 → V2

1. **Identification** : Requêter les enseignants actifs avec leurs attributions de classes/matières en V1 (`enseignant_matiere`, `classe_enseignant`)
2. **Création des affectations** : Une affectation principale par enseignant actif, liée à leur contrat actif
3. **Migration des matières** : Pour chaque attribution V1, créer une ligne `rh_affectation_matieres`
4. **Vérification** : Comparer le count V1 vs V2 et vérifier l'intégrité des données migrées

---

## 14. Tests réalisés (statique)

| Scénario | Résultat |
|----------|---------|
| Création principale — aucune autre active | ✓ OK |
| Création principale — autre déjà active | ✓ RuntimeException bloquante |
| Création secondaire — même poste/dept, période chevauchante | ✓ RuntimeException bloquante |
| Contrat non actif lié | ✓ RuntimeException bloquante |
| Contrat appartenant à un autre employé | ✓ RuntimeException bloquante |
| Transfert sans motif | ✓ InvalidArgumentException |
| Transfert sans changement détecté | ✓ RuntimeException |
| Clôture d'une affectation déjà terminée | ✓ RuntimeException |
| Archivage d'une affectation active | ✓ RuntimeException bloquante |
| Réactivation avec autre principale active | ✓ RuntimeException bloquante |
| Tables V1 matieres/classes absentes | ✓ Dégradation gracieuse (try/catch) |

---

## 15. Dette technique

| ID | Sévérité | Description |
|----|---------|-------------|
| DT-A-01 | Mineure | `NotificationListener` et `StatisticsListener` sont des stubs (Phase 6.10) |
| DT-A-02 | Mineure | La vue `show.php` instancie directement `AssignmentRepository` pour les modals — refactoriser en données passées par le contrôleur en Phase 6.10 |
| DT-A-03 | Info | AJAX contrats-par-employé non implémenté (JS commenté) — le sélecteur de contrat est pré-chargé |
| DT-ORG-01 | Majeure | Phase 6.4 Organisation AuditListener — appels statiques incorrects (corriger en Phase 6.10) |

---

## 16. Fichiers créés / modifiés

**Créés (25 fichiers) :**
- `database/migrations/rh_006_affectations.sql` (3 tables)
- `app/Modules/RH/Affectations/Events/Assignment{Created,Updated,Transferred,Archived}.php` (×4)
- `app/Modules/RH/Affectations/Listeners/{Audit,Notification,Statistics}Listener.php` (×3)
- `app/Modules/RH/Affectations/DTO/{AssignmentDTO,AssignmentFiltersDTO,MatiereAssignmentDTO}.php` (×3)
- `app/Modules/RH/Affectations/Models/AssignmentModel.php`
- `app/Modules/RH/Affectations/Policies/AssignmentPolicy.php`
- `app/Modules/RH/Affectations/Repositories/AssignmentRepository.php`
- `app/Modules/RH/Affectations/Services/AssignmentService.php`
- `app/Modules/RH/Affectations/Controllers/AssignmentController.php`
- `app/Modules/RH/Views/affectations/{index,show,create,edit,statistiques}.php` (×5)
- `AFFECTATIONS_DOMAIN_V2.md`

**Modifiés (4 fichiers) :**
- `app/Modules/RH/routes.php` — +15 routes affectations
- `config/events.php` — +4 events × 3 listeners
- `config/permissions.php` — +5 permissions sur 4 rôles
- `app/Modules/RH/module.json` — v1.3.0 → v1.4.0, phase 6.6, 17 tables SQL
