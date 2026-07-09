# Changelog — SCOLARIS V2 / EduNova

Format libre, organisé par phase de développement. Depuis la Phase 16.0, le
projet est versionné dans Git (tag `v2.0.0`) — voir
`SCOLARIS_V2_FINAL_RELEASE_REPORT.md`. Ordre chronologique croissant.

## [2.0.0] — Official Release (2026-07-10)

Clôture officielle du cycle de développement V2. Architecture figée (Core,
Scolarité, Académique, Finance, Vie scolaire, RH, Documents, Communication,
Bibliothèque, Inventaire, Rapports & BI, Portails, API Platform, Multi-Tenant).
Premier commit Git et tag `v2.0.0`. Voir `SCOLARIS_V2_FINAL_RELEASE_REPORT.md`
pour l'état détaillé et les limitations connues de cette version.

## Phase 16 — Release officielle (2026-07-10)

- **16.0 — SCOLARIS V2.0.0 Official Release** : gel de l'architecture, version
  officielle définie (`config/app.php['version']`, fichier `VERSION`),
  initialisation du dépôt Git (`.gitignore`, commit initial, tag `v2.0.0`),
  documentation de release complétée (`ARCHITECTURE.md`, `INSTALLATION.md`,
  `DEPLOYMENT.md`, `API_DOCUMENTATION.md`, `ADMIN_GUIDE.md`, `USER_GUIDE.md`),
  roadmaps `ROADMAP_V2.1.md`/`ROADMAP_V3.0.md`, rapport final
  `SCOLARIS_V2_FINAL_RELEASE_REPORT.md`.

## Phase 15 — Release Candidate & Documentation (2026-07-09)

- **15.1 — Release Candidate RC1** : audit global, 2 anomalies critiques trouvées
  et corrigées (routage API Platform totalement cassé — 122 routes inaccessibles ;
  autoloader `App\Shared\` manquant en production), 3 anomalies mineures corrigées
  (défaut `APP_DEBUG` non sûr, fuseau horaire CLI, fragilité de test). 530/530
  tests verts. Décision : GO WITH FIXES. Voir `RELEASE_CANDIDATE_RC1_REPORT.md`.
- **15.2 — Documentation finale** : production de la documentation technique,
  fonctionnelle, déploiement et développeur complète (`docs/`), plus les documents
  de release (ce fichier, `RELEASE_NOTES_V2.md`, `ROADMAP_V2_1.md`, `ROADMAP_V3.md`).

## Phase 14 — Architecture Multi-Tenant SaaS (2026-06-30 → 2026-07-08)

- **14.2** Infrastructure Tenant : `TenantContext`, `TenantResolver`,
  `TenantMiddleware` (construit, non activé), 11 tables.
- **14.3** Migration des tables métier : `etablissement_id` sur 9 tables V1
  (users, classes, eleves, professeurs, matieres, enseignements, notes, absences,
  periodes), `Core\Model::$tenantScoped`.
- **14.4** RBAC Multi-Tenant : résolution à 3 paliers, isolation des rôles
  personnalisés par établissement.
- **14.5** Branding Multi-Tenant : logo/couleurs/police/thème par établissement.
- **14.6** Multi-Utilisateurs / Multi-Établissements : appartenance multiple,
  sélecteur d'établissement, changement de contexte en session.
- **14.7** Domaines personnalisés : sous-domaines, domaines personnalisés,
  vérification DNS TXT.
- **14.8** Stockage & Quotas : `Core\Storage`, quotas par ressource, alertes.
- **14.9** Cache & Files d'attente : `TenantCache`, `Core\Queue` (MySQL).
- **14.10** Portail Super-Admin SaaS : `/platform/*`, gestion établissements/
  plans, RBAC totalement distinct.
- **14.11** Sauvegardes & Reprise après incident : sauvegarde globale/tenant/
  différentielle chiffrée, restauration upsert non destructive.
- **14.12** Revue d'intégration système Multi-Tenant : score 8.4/10, GO. 2
  anomalies trouvées et corrigées pendant la revue (fragilité de test liée au
  fuseau horaire, performance de restauration en masse ×1,5).

## Phase 13 — API Platform (2026-07-04 → 2026-07-06)

JWT (HS256), clés API, rate limiting, versioning, webhooks HMAC, OpenAPI 3.1,
122 routes couvrant Scolarité/Académique/Finance/Vie scolaire/RH/Documents/
Communication/Bibliothèque/Inventaire/Rapports.

## Phase 12 — Portails (2026-07-04 → 2026-07-05)

Framework commun (10 moteurs), 7 portails contextuels (Admin, Direction,
Comptabilité, RH, Enseignant, Élève, Parent), 55 widgets.

## Phase 11 — Rapports & BI (2026-07-03 → 2026-07-04)

Dashboards analytiques configurables, 30 routes, moteurs d'export.

## Phase 10 — Inventaire (2026-07-03)

Immobilisations, stock, commandes, maintenance.

## Phase 9 — Bibliothèque (2026-07-03)

Catalogue, prêts, réservations, code-barres/QR (stubs).

## Phase 8 — Communication (2026-07-03)

Messagerie, annonces, notifications multi-canal.

## Phase 7 — Documents (2026-07-02)

GED transversale, génération, classement, signature (stub V3).

## Phase 6 — Ressources Humaines (2026-07-02)

Employés, organisation, contrats, affectations, présences, congés, évaluations,
formations, documents RH — 10 domaines.

## Phase 5 — Vie Scolaire (2026-07-01)

Absences, présences, retards, discipline, récompenses, emplois du temps,
activités — 7 domaines.

## Phase 4 — Milestone 2 Review (2026-07-01)

## Phase 3 — Finance (2026-07-01)

Facturation, encaissements, caisse, comptabilité PCG, rapports financiers.

## Phase 2 — Académique (2026-06-30)

Notes, contrôles, moyennes automatiques, bulletins PDF, classements, analytique.

## Phase 1 — Scolarité V2 (2026-06-30)

Structure modulaire, domaines Élèves/Classes/Inscriptions/Familles/Référentiel
pédagogique.

## Fondations V2 (2026-06-29 → 2026-06-30)

Migration base de données, RBAC V2, Paramètres, Shared Services, Event System —
Foundation Freeze déclaré GO le 2026-06-30 (35 tables, RBAC 7/7).

## Pré-V2 — Modules fonctionnels initiaux (2026-06-24 → 2026-06-26)

Authentification, gestion des élèves (CRUD, import/export), classes & matières,
enseignants, académique (V1), absences, comptabilité (V1), emploi du temps,
reporting, finalisation production (48+109 tests), PWA (manifest, service worker,
notifications push), modernisation UI (Tailwind + Lucide), module Utilisateurs.

## Corrections notables hors phase (2026-07-08 → 2026-07-09)

- Correction de l'accordéon de menu latéral (activation erronée d'un groupe non
  lié par correspondance de sous-chaîne).
- Renommage de marque "SCOLARIS"/"Ecole App" → "EduNova".
- Ajout du favicon sur les écrans d'authentification/erreur.
- Migration Bootstrap → Tailwind CSS sur 78 vues.
