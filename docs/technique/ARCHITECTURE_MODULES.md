# Architecture des modules — SCOLARIS V2

## 1. Modèle V2 : structure d'un module

Chaque module sous `app/Modules/{Nom}/` suit (avec des variations mineures selon
l'ancienneté) la structure suivante :

```
app/Modules/{Nom}/
├── module.json          Manifeste : tables SQL déclarées, permissions, routes, historique de phases
├── routes.php            Routes du module, chargées uniquement si enabled=true
├── Controllers/           Contrôleurs fins — délèguent aux Services, ne SELECT/INSERT jamais eux-mêmes
├── Services/               Logique métier + validations
├── Repositories/           Accès SQL, toujours filtré par etablissement_id si applicable
├── Events/                 Événements du domaine (ex: EleveCreated, FactureValidee)
├── Listeners/              Réagissent aux événements (écriture, notifications, audit)
├── DTO/                    Objets de transfert (Data Transfer Objects) immuables
├── Policies/                Règles d'autorisation spécifiques au domaine
└── Views/                   Vues propres au module (rendues via 'Module::vue')
```

## 2. Registre des modules

Source de vérité : `config/modules.php`. Un module inactif (`enabled=false`) n'a
**aucune route chargée** — son code existe mais n'est jamais exécuté tant qu'il
n'est pas activé.

| Module | `enabled` | Rôle | Tables déclarées appliquées ? |
|---|---|---|---|
| `scolarite` | false | Élèves, classes, matières, inscriptions, familles (référentiel pédagogique) | Tables **V1** sous-jacentes actives (voir §3) |
| `academique` | false | Notes, contrôles, bulletins, classements, analytique | Tables **V1** sous-jacentes actives (voir §3) |
| `finance` | true | Facturation, encaissements, caisse, comptabilité PCG, rapports financiers | ❌ Non — voir avertissement §4 |
| `vie_scolaire` | true | Absences, présences, retards, discipline, récompenses, emplois du temps, activités | ❌ Non — voir avertissement §4 |
| `rh` | true | Employés, contrats, affectations, présences RH, congés, évaluations, formations, documents RH | ❌ Non — voir avertissement §4 |
| `documents` | false | GED transversale (génération, signature, classement) | ❌ Non |
| `communication` | false | Messagerie, annonces, notifications multi-canal | ❌ Non |
| `bibliotheque` | false | Catalogue, prêts, réservations | ❌ Non |
| `inventaire` | false | Immobilisations, stock, maintenance | ❌ Non |
| `rapports` | false | Business Intelligence, tableaux de bord analytiques transverses | ❌ Non |
| `portals` | false | Portails dédiés par profil (voir `docs/technique/PORTAILS.md`) | — (pas de table propre) |
| `api` | true | API REST v1 (JWT, clés API, webhooks) | — (pas de table métier propre, consomme les autres) |

## 3. Le socle réellement actif aujourd'hui

Une nuance importante distingue le **flag de module V2** (`config/modules.php`) de
l'**usage réel en production**. Scolarité et Académique sont `enabled=false` en tant
que *modules V2*, mais les fonctionnalités correspondantes (élèves, classes,
matières, notes, absences, bulletins) sont **pleinement actives** via les
contrôleurs et modèles **V1** (`app/Controllers/EleveController.php`,
`app/Models/EleveModel.php`, etc.), qui ne dépendent pas du flag module. C'est ce
socle V1, plus toute l'infrastructure Multi-Tenant (Phases 14.2-14.11), qui
constitue la base fonctionnelle démontrée de l'application aujourd'hui.

## 4. Avertissement — modules marqués actifs sans données appliquées

`finance`, `vie_scolaire` et `rh` sont déclarés `enabled=true` dans
`config/modules.php` (leurs routes et contrôleurs se chargent donc normalement),
**mais aucune des tables SQL qu'ils déclarent dans leur `module.json` n'a été
appliquée à la base de données de cet environnement.** Toute tentative d'usage réel
de ces modules provoquerait une erreur SQL immédiate ("table introuvable"). Ce
constat, vérifié empiriquement (comparaison `module.json` ↔ `INFORMATION_SCHEMA.TABLES`),
est détaillé dans `RELEASE_CANDIDATE_RC1_REPORT.md` §5. **Avant toute mise en
production de ces trois modules, leurs migrations SQL doivent être appliquées** —
voir `docs/deploiement/MISE_A_JOUR.md`.

## 5. Détail par module

### Scolarité (V1 actif + fondations V2 en pause)
Élèves (CRUD, photo, import CSV, export PDF/Excel), classes, matières,
enseignements (affectation professeur↔matière↔classe), inscriptions, familles/
parents (lien élève↔parent). Blueprint : `MULTI_TENANT_V2_BLUEPRINT.md` (pour la
dimension tenant) et `AFFECTATIONS_DOMAIN_V2.md`/`CONTRATS_DOMAIN_V2.md` pour les
domaines connexes RH réutilisant des affectations similaires.

### Académique (V1 actif + fondations V2 en pause)
Notes, contrôles, moyennes automatiques, bulletins PDF, classement, mentions,
périodes scolaires. Moteurs dédiés : `RankingEngine`, `BulletinGenerator`,
`AcademicAnalyticsService` (chacun avec ses propres tests unitaires, voir
`docs/developpeur/TESTS.md`). Référence : `ACADEMIQUE_BLUEPRINT_V2.md`,
`ACADEMIQUE_MODULE_FREEZE.md`.

### Finance *(routes actives, données non appliquées — voir §4)*
Facturation (machine d'états, avoirs), encaissements (reçus, trop-perçus,
remboursements), caisse (mouvements, rapprochement), comptabilité (plan comptable
PCG, journal double-entrée, grand livre, balance, clôture d'exercice), rapports
financiers. Référence : `FINANCE_BLUEPRINT_V2.md`, `FINANCE_MODULE_FREEZE.md`.

### Vie scolaire *(routes actives, données non appliquées — voir §4)*
Absences, présences (pointage), retards, discipline, récompenses, emplois du temps
(grille visuelle, conflits, remplacements), activités scolaires (inscriptions,
liste d'attente). Référence : `MODULE_VIE_SCOLAIRE_BLUEPRINT.md`,
`VIE_SCOLAIRE_MODULE_FREEZE.md`.

### RH *(routes actives, données non appliquées — voir §4)*
Employés, organisation (organigramme), contrats (machine d'états, avenants),
affectations, présences RH, congés (soldes, anti-chevauchement), évaluations
(campagnes, critères pondérés), formations (certifications), documents RH.
Référence : `RH_V2_BLUEPRINT.md`, `RH_MODULE_FREEZE.md`.

### Documents, Communication, Bibliothèque, Inventaire, Rapports & BI, Portails
Modules désactivés (`enabled=false`), code présent et documenté individuellement
(`*_BLUEPRINT.md`, `*_IMPLEMENTATION_REPORT.md`, `*_INTEGRATION_REVIEW.md` à la
racine du projet pour chacun) mais jamais exécutés contre une base réelle dans cet
environnement. Portails détaillé séparément : `docs/technique/PORTAILS.md`.

### API Platform
API REST v1 versionnée (JWT + clés API), consommant les modules ci-dessus.
Détaillée dans `docs/technique/API_REST.md`. Corrigée et vérifiée fonctionnelle en
Phase 15.1 (RC1) — voir `RELEASE_CANDIDATE_RC1_REPORT.md` §2 pour l'historique du
bug de routage qui la rendait totalement inaccessible avant cette correction.

## 6. Ajouter un nouveau module

Voir `docs/developpeur/CONVENTIONS.md` §"Créer un module V2".
