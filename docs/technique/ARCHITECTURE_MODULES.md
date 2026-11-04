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
| `academique` | **true** | Notes, évaluations, périodes, classements, bulletins, analytique | ✅ Oui — moteur V2 seul actif depuis le 22/07/2026 (voir §3 et §5) |
| `finance` | true | Facturation, encaissements, caisse, comptabilité PCG, rapports financiers | ✅ Oui, dans cet environnement — voir §4 |
| `vie_scolaire` | true | Absences, présences, retards, discipline, récompenses, emplois du temps, activités | ✅ Oui, dans cet environnement — voir §4 |
| `rh` | true | Employés, contrats, affectations, présences RH, congés, évaluations, formations, documents RH | ✅ Oui, dans cet environnement — voir §4 |
| `documents` | false | GED transversale (génération, signature, classement) | ❌ Non |
| `communication` | false | Messagerie, annonces, notifications multi-canal | ❌ Non |
| `bibliotheque` | false | Catalogue, prêts, réservations | ❌ Non |
| `inventaire` | false | Immobilisations, stock, maintenance | ❌ Non |
| `rapports` | false | Business Intelligence, tableaux de bord analytiques transverses | ❌ Non |
| `portals` | false | Portails dédiés par profil (voir `docs/technique/PORTAILS.md`) | — (pas de table propre) |
| `api` | true | API REST v1 (JWT, clés API, webhooks) | — (pas de table métier propre, consomme les autres) |

## 3. Le socle réellement actif aujourd'hui

Une nuance importante distingue le **flag de module V2** (`config/modules.php`) de
l'**usage réel en production**, et elle ne joue plus dans le même sens pour tous
les modules :

- **Scolarité** reste `enabled=false` en tant que *module V2* : élèves, classes,
  matières, professeurs sont servis par les contrôleurs/modèles **V1**
  (`app/Controllers/EleveController.php`, `app/Models/EleveModel.php`, etc., aux
  URL racine `/eleves`, `/classes`, `/matieres`, `/professeurs`), qui ne dépendent
  pas du flag module.
- **Académique**, en revanche, est `enabled=true` depuis le 22/07/2026 (migration
  trimestre → semestre, voir `ACADEMIQUE_MODULE_FREEZE.md`) et son moteur **V2**
  (`RankingEngine`, `BulletinGenerator`, `AcademicCalculationService`) est **le
  seul moteur académique vivant** — même les URL historiques comme `/bulletins`
  délèguent au V2 (`BulletinEngineFactory`). Il n'y a plus de socle V1 académique
  en parallèle.
- **Finance, Vie scolaire et RH** sont `enabled=true` et leurs tables sont
  **appliquées et utilisées** dans cet environnement — voir §4, qui a remplacé un
  avertissement devenu obsolète.

## 4. État des tables — vérifié empiriquement, pas supposé

Un avertissement précédent affirmait qu'aucune table `finance_*`, `vs_*` ou `rh_*`
n'était appliquée dans cet environnement. **Ce n'est plus le cas.** Vérifié le
21/08/2026 par comparaison directe `module.json` ↔ `INFORMATION_SCHEMA.TABLES`,
avec des données réelles (pas des tables vides) :

| Préfixe | Tables présentes | Exemple de volumétrie |
|---|---|---|
| `finance_*` | 32 | `finance_factures` : 15 lignes |
| `vs_*` (Vie scolaire) | 26 | `vs_absences` : 5 lignes |
| `rh_*` | 44 | `rh_employes` : 9 lignes |

Ces trois modules sont donc **réellement opérationnels ici**, pas seulement
« routés ». **Ce constat est spécifique à cet environnement**, pas une garantie
universelle : un nouveau déploiement (autre établissement, autre serveur) doit
appliquer les migrations SQL déclarées dans chaque `module.json` avant que ces
modules ne soient utilisables — voir `docs/deploiement/MISE_A_JOUR.md`. C'est
pour cette raison que la documentation fonctionnelle (`docs/fonctionnel/*.md`)
qualifie ces fonctionnalités de « selon les fonctionnalités activées et les
permissions du compte » plutôt que de les présenter comme universellement
disponibles : `enabled=true` dans `config/modules.php` garantit que les routes se
chargent, pas que les migrations ont été jouées sur la base ciblée.

## 5. Détail par module

### Scolarité (V1 actif + fondations V2 en pause)
Élèves (CRUD, photo, import CSV, export PDF/Excel), classes, matières,
enseignements (affectation professeur↔matière↔classe), inscriptions, familles/
parents (lien élève↔parent). Blueprint : `MULTI_TENANT_V2_BLUEPRINT.md` (pour la
dimension tenant) et `AFFECTATIONS_DOMAIN_V2.md`/`CONTRATS_DOMAIN_V2.md` pour les
domaines connexes RH réutilisant des affectations similaires.

### Académique (V2 seul moteur vivant depuis le 22/07/2026)
Périodes scolaires (semestres), types d'évaluation, évaluations, notes, moyennes
automatiques, classements (par classe, niveau, matière ; un classement général
pondéré multi-périodes existe aussi, mais uniquement en usage interne — voir la
mise en garde plus bas), bulletins PDF, mentions, analytics. Moteurs dédiés :
`RankingEngine`, `BulletinGenerator`, `AcademicAnalyticsService` (chacun avec ses
propres tests unitaires, voir `docs/developpeur/TESTS.md`). Référence :
`ACADEMIQUE_BLUEPRINT_V2.md`, `ACADEMIQUE_MODULE_FREEZE.md`.

> **Nuance sur le « classement général »** : `RankingEngine::classementGeneral()`
> calcule bien un rang pondéré sur plusieurs périodes (ex. moyenne annuelle
> semestre 1 + semestre 2), mais au 21/08/2026 il n'est appelé **que** par
> `BulletinGenerator` pour le bulletin annuel — il n'existe **pas** d'écran ou de
> rapport dédié qui l'expose seul. Le classement consultable en dehors d'un
> bulletin (`/v2/academique/resultats/classement`) est **par classe, niveau ou
> matière et par période**, pas un « classement général » autonome.

### Finance — appliqué et utilisé dans cet environnement (voir §4)
Facturation (machine d'états, avoirs), encaissements (reçus, trop-perçus,
remboursements), caisse (mouvements, rapprochement), comptabilité (plan comptable
PCG, journal double-entrée, grand livre, balance, clôture d'exercice), rapports
financiers. Référence : `FINANCE_BLUEPRINT_V2.md`, `FINANCE_MODULE_FREEZE.md`.

### Vie scolaire — appliqué et utilisé dans cet environnement (voir §4)
Absences, présences (pointage), retards, discipline, récompenses, emplois du temps
(grille visuelle, conflits, remplacements), activités scolaires (inscriptions,
liste d'attente). Référence : `MODULE_VIE_SCOLAIRE_BLUEPRINT.md`,
`VIE_SCOLAIRE_MODULE_FREEZE.md`.

### RH — appliqué et utilisé dans cet environnement (voir §4)
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
