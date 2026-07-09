# Phase 6.5 — Domaine Contrats RH V2
**Date** : 2026-07-02  
**Statut** : IMPLÉMENTÉ  
**Score** : 9/10 (dette mineure DT-C-01)

---

## 1. Architecture

```
app/Modules/RH/Contrats/
├── Controllers/
│   └── ContractController.php        (15 actions)
├── DTO/
│   ├── ContractDTO.php               (fromRequest, validate, toArray)
│   ├── ContractFiltersDTO.php        (q, type, statut, employeId, deptId, echeanceDans)
│   └── AvenantDTO.php                (6 types d'avenant)
├── Events/
│   ├── ContractCreated.php
│   ├── ContractUpdated.php           (action: modification|suspension|reactivation|avenant|activation|archivage)
│   ├── ContractRenewed.php
│   ├── ContractExpired.php
│   └── ContractTerminated.php
├── Listeners/
│   ├── AuditListener.php             (instance AuditService — patron correct)
│   ├── NotificationListener.php      (stub — hooks Phase 6.8)
│   └── StatisticsListener.php        (stub — hooks Phase 6.12)
├── Models/
│   └── ContractModel.php             (TYPES, STATUTS, TRANSITIONS, helpers statiques)
├── Policies/
│   └── ContractPolicy.php            (7 méthodes canX)
├── Repositories/
│   └── ContractRepository.php        (findAll, findById, findByEmploye, statistiques, hasContratActif, hasOverlap, genererNumero, insertAvenant…)
├── Services/
│   └── ContractService.php           (creer, modifier, ajouterAvenant, renouveler, resilier, suspendre, reactiver, activer, archiver, traiterExpirations, exporterCsv)
└── Views/contrats/
    ├── index.php       (liste paginée, filtres, stat cards, badge couleur)
    ├── show.php        (détail, avenants timeline, modals résiliation/renouvellement/avenant)
    ├── create.php      (formulaire création)
    ├── edit.php        (formulaire modification, employé en lecture seule)
    ├── statistiques.php (KPIs, barres par statut/type, masse salariale)
    └── echeances.php   (alertes urgence colorées J-30/60/90, horizon filtrable)
```

---

## 2. Migration SQL (`rh_005_contrats.sql`)

| Table | Description |
|-------|-------------|
| `rh_contrats` | Contrat principal — 24 colonnes |
| `rh_contrat_avenants` | Avenants numérotés par contrat (UNIQUE contrat_id + numero) |
| `rh_contrat_sequences` | Séquence atomique par année (`INSERT ON DUPLICATE KEY UPDATE`) |

**Format numéro** : `CNT-YYYY-NNNN`

---

## 3. Machine d'états

```
brouillon ──→ actif ──→ suspendu ──→ actif
                  ↘         ↘
                  expiré  résilié
                  (auto)
```

| Transition | Action | Vérification |
|-----------|--------|-------------|
| brouillon → actif | `activer()` | Pas de contrat actif existant |
| actif → suspendu | `suspendre()` | — |
| suspendu → actif | `reactiver()` | Pas d'autre contrat actif |
| actif → expiré | `traiterExpirations()` | Auto : date_fin < CURDATE() |
| actif/expiré → renouvellement | `renouveler()` | Crée un nouveau contrat, expire l'ancien |
| actif/suspendu → résilié | `resilier()` | Motif obligatoire |

---

## 4. Règles métier

- **Un seul actif à la fois** : `hasContratActif()` bloquant à la création et à l'activation.
- **Chevauchement de dates** : CDI bloque tout ; CDD vérifie l'overlap avec les contrats en cours.
- **Auto-expiration** : `traiterExpirations()` appelé silencieusement dans le constructeur du Controller à chaque requête RH/Contrats.
- **Avenants** : uniquement sur contrats actifs ou suspendus ; numérotés séquentiellement par contrat.
- **Archivage** : bloqué si statut actif ou suspendu. Soft Delete uniquement.
- **Renouvellement** : crée un nouveau contrat avec `renouvelle_depuis = ancien_id`, reprend les conditions salariales.

---

## 5. Routes (16 routes)

| Méthode | URI | Action |
|---------|-----|--------|
| GET | /v2/rh/contrats | index |
| GET | /v2/rh/contrats/create | create |
| POST | /v2/rh/contrats | store |
| GET | /v2/rh/contrats/echeances | echeances |
| GET | /v2/rh/contrats/statistiques | statistiques |
| GET | /v2/rh/contrats/export | export (CSV) |
| GET | /v2/rh/contrats/{id} | show |
| GET | /v2/rh/contrats/{id}/edit | edit |
| POST | /v2/rh/contrats/{id} | update |
| POST | /v2/rh/contrats/{id}/avenants | storeAvenant |
| POST | /v2/rh/contrats/{id}/activer | activer |
| POST | /v2/rh/contrats/{id}/suspendre | suspendre |
| POST | /v2/rh/contrats/{id}/reactiver | reactiver |
| POST | /v2/rh/contrats/{id}/renouveler | renouveler |
| POST | /v2/rh/contrats/{id}/resilier | resilier |
| POST | /v2/rh/contrats/{id}/archive | archive |

---

## 6. Permissions RBAC (7 permissions)

| Permission | admin | directeur | secretaire | enseignant |
|-----------|-------|-----------|------------|-----------|
| contract.view | ✓ | ✓ | ✓ | ✓ |
| contract.create | ✓ | ✓ | | |
| contract.update | ✓ | ✓ | | |
| contract.renew | ✓ | ✓ | | |
| contract.terminate | ✓ | ✓ | | |
| contract.archive | ✓ | ✓ | | |
| contract.export | ✓ | ✓ | ✓ | |

---

## 7. Événements (5 events × 3 listeners)

| Événement | AuditListener | NotificationListener | StatisticsListener |
|-----------|:---:|:---:|:---:|
| ContractCreated | ✓ | ✓ | ✓ |
| ContractUpdated | ✓ | ✓ | ✓ |
| ContractRenewed | ✓ | ✓ | ✓ |
| ContractExpired | ✓ | ✓ | ✓ |
| ContractTerminated | ✓ | ✓ | ✓ |

---

## 8. Intégrations cross-domaine

| Domaine | Type | Description |
|---------|------|-------------|
| Employés | Obligatoire | `employe_id` FK RESTRICT — un contrat ne peut exister sans employé |
| Organisation | Optionnel | `poste_id` SET NULL, `departement_id` SET NULL — suppression gracieuse |
| Finance / Paie | Hook futur | `StatisticsListener` contient les hooks Phase 6.12 (masse salariale) |
| Communication | Hook futur | `NotificationListener` contient les hooks Phase 6.8 (alertes J-90/60/30) |

---

## 9. Export CSV

Colonnes : Numéro, Employé, Matricule, Type, Statut, Début, Fin, Salaire brut, Département, Poste  
Encodage : UTF-8 BOM, séparateur `;`  
Filtres : tous les filtres de la liste s'appliquent, perPage=9999

---

## 10. Compatibilité V1

- **Zéro collision** : toutes les routes sont sous `/v2/rh/contrats/*`
- **Aucun fichier V1 modifié**
- **Aucune table V1 touchée** : nouvelles tables `rh_contrats`, `rh_contrat_avenants`, `rh_contrat_sequences`
- Les routes V1 `/comptabilite/*` et autres modules financiers coexistent sans conflit

---

## 11. Dette technique

| ID | Sévérité | Description |
|----|---------|-------------|
| DT-C-01 | Mineure | `NotificationListener` et `StatisticsListener` sont des stubs (Phase 6.8 / 6.12) |
| DT-ORG-01 | Majeure | Phase 6.4 Organisation AuditListener utilise des appels statiques incorrects — corriger en Phase 6.10 |

---

## 12. Fichiers créés / modifiés

**Créés (26 fichiers) :**
- `database/migrations/rh_005_contrats.sql`
- `app/Modules/RH/Contrats/Events/Contract{Created,Updated,Renewed,Expired,Terminated}.php` (×5)
- `app/Modules/RH/Contrats/Listeners/{Audit,Notification,Statistics}Listener.php` (×3)
- `app/Modules/RH/Contrats/DTO/{ContractDTO,ContractFiltersDTO,AvenantDTO}.php` (×3)
- `app/Modules/RH/Contrats/Models/ContractModel.php`
- `app/Modules/RH/Contrats/Policies/ContractPolicy.php`
- `app/Modules/RH/Contrats/Repositories/ContractRepository.php`
- `app/Modules/RH/Contrats/Services/ContractService.php`
- `app/Modules/RH/Contrats/Controllers/ContractController.php`
- `app/Modules/RH/Views/contrats/{index,show,create,edit,statistiques,echeances}.php` (×6)
- `CONTRATS_DOMAIN_V2.md`

**Modifiés (3 fichiers) :**
- `app/Modules/RH/routes.php` — +16 routes
- `config/events.php` — +5 events × 3 listeners
- `config/permissions.php` — +7 permissions sur 4 rôles
- `app/Modules/RH/module.json` — v1.2.0 → v1.3.0, phase 6.5
