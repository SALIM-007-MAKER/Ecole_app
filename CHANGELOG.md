# Changelog — SCOLARIS V2 / EduNova

Format libre, organisé par phase de développement. Depuis la Phase 16.0, le
projet est versionné dans Git (tag `v2.0.0`) — voir
`SCOLARIS_V2_FINAL_RELEASE_REPORT.md`. Ordre chronologique croissant.

## Post-release — Corrections diverses (2026-08-22)

- **Messagerie (`Communication`) sans aucune vérification de destinataire.**
  Trouvé en vérifiant le guide Élève avant de préciser le texte sur la
  messagerie : `ThreadDTO::validate()` ne contrôle que la présence des champs
  (sujet, destinataires, corps du message) et `ThreadService::creer()`
  insérait chaque `participant_id` reçu du formulaire tel quel, sans vérifier
  qu'il correspond à un compte existant, actif, ni même du même établissement
  que l'expéditeur — un utilisateur aurait pu démarrer une conversation avec
  n'importe quel ID, y compris d'un autre établissement (fuite potentielle
  cross-tenant). Ajouté `ThreadService::filtrerDestinatairesValides()` qui ne
  conserve que les comptes actifs du même établissement ; `creer()` lève
  désormais une `RuntimeException` si aucun destinataire ne survit au filtre,
  remontée en erreur 422 par `ThreadController::store()` plutôt qu'un thread
  vide ou une fuite silencieuse. **Le module `communication` est cependant
  désactivé (`config/modules.php` → `enabled: false`)** dans cette
  installation — ce correctif est sans effet tant qu'il n'est pas activé,
  mais corrige le code avant activation future.

- **Menu Élève incomplet : « Activités scolaires » invisible alors que la
  permission existe.** `MenuService::getStudentMenus()` ne listait pas
  d'entrée vers `/v2/vie-scolaire/activites`, alors que le rôle `eleve`
  possède déjà la permission `activity.view` et que le module `vie_scolaire`
  qui la sert est actif. Ajoutée l'entrée manquante. Les entrées équivalentes
  pour Bibliothèque et Messagerie n'ont pas été ajoutées : ces deux modules
  sont désactivés (`enabled: false`), un lien vers eux produirait une 404.

## Post-release — Corrections diverses (2026-08-21)

- **Rôle `directeur` avait accès aux paramètres techniques (Sécurité, Sauvegarde, Avancé).**
  Trouvé en vérifiant, à la demande d'un utilisateur, la formulation « mêmes écrans que
  l'Administrateur » du guide Direction sur la section Paramètres : `config/permissions.php`
  attribuait à `directeur` des permissions `settings.securite.*`, `settings.sauvegarde.*` et
  `settings.avance.*` strictement identiques à `admin`, et `SettingsController` ne pose aucune
  restriction de rôle supplémentaire au-delà de la permission — Direction pouvait donc modifier
  la politique de mot de passe, l'expiration de session et les réglages de sauvegarde/avancés
  exactement comme Administrateur. Retiré ces 6 permissions (`view`+`update` × 3 sections) du
  rôle `directeur` sur demande explicite. Le menu et la page `/parametres` filtrant déjà par
  permission (pas de liste codée en dur), les deux tuiles disparaissent automatiquement pour
  Direction sans changement de vue nécessaire. Comme pour toute modification de
  `config/permissions.php`, un compte déjà connecté doit se reconnecter pour que le retrait
  s'applique (permissions figées en session à la connexion).

- **`academique.bulletin.admin` non attribuée à aucun rôle.** Trouvé en vérifiant le contenu
  du guide administrateur avant publication : `BulletinPolicy::canAddDirecteurAppreciation()`
  vérifie cette permission pour autoriser l'écran d'appréciation du chef d'établissement sur
  un bulletin, mais elle n'existait dans aucun rôle de `config/permissions.php` — l'écran
  était inaccessible à tout le monde, y compris Administrateur. Ajoutée aux rôles `admin` et
  `directeur` (aux côtés de `academique.bulletin.generer`/`publier`, déjà présentes pour ces
  deux rôles) ; vérifié via `BulletinPolicy::canAddDirecteurAppreciation()` sur les 7 rôles —
  seuls `admin`/`directeur` passent, comme attendu. Les permissions étant figées en session à
  la connexion (`AuthController::completeLogin()`), un compte déjà connecté doit se
  reconnecter pour que le correctif s'applique.

## Post-release — Convergence modules V1/V2 (2026-08-20 → 2026-08-21)

Audit de cohérence UI (police, boutons, formulaires) ayant révélé que plusieurs écrans V2
coexistaient avec leur ancienne version V1 sans lien entre elles — chaque système avait sa
propre table, avec un risque réel de divergence de données selon l'écran utilisé.

- **Absences** : `absences` (V1, utilisée par toute l'interface) et `vs_absences` (V2, module
  Vie Scolaire, jusque-là uniquement lue/écrite par l'API publique `/api/v1/absences`)
  coexistaient sans lien. Décision : convergence vers le V2 (architecture plus propre —
  events, policy, workflow de justification dédié — déjà la cible de l'API).
  - Migration `T037` : 5 lignes réelles (+ 2 justifications reconstruites) migrées vers
    `vs_absences`. Table V1 conservée en base à ce stade comme trace d'audit, non lue par le
    code — supprimée par la suite, voir plus bas.
  - Écran de pointage groupé par classe, absent côté V2, reconstruit à l'identique
    (`AbsenceRepository::findRosterForPointage`, `AbsenceController::pointage/storePointage`,
    vue dédiée) — les retards saisis y sont routés vers `vs_retards`, pas vers `vs_absences`.
  - Menus des 6 rôles, tableau de bord (`HomeController`), rapports (`RapportModel`, 5
    méthodes), espace parent (`ParentController`) et espace élève (`EspaceEleveController`,
    bug latent de jointure vers une colonne `matiere_id` inexistante corrigé au passage)
    rebranchés sur le V2. Routes `/absences/*` (V1) décommissionnées dans `config/routes.php`.
  - Non repris : `/absences/alertes` (seuil global tous classes confondues, sans équivalent
    V2 direct — le plus proche est `/v2/vie-scolaire/absences/statistiques`).
- **Emplois du temps / Salles / Créneaux** : même constat, mais `emplois_du_temps` (V1) et
  `vs_emplois_du_temps` (V2) étaient tous deux vides — le référentiel (salles, créneaux) était
  la seule vraie donnée à migrer.
  - Le module V2 comportait deux bugs bloquants, indépendants du problème de menu : 3 requêtes
    dans `TimetableController` joignaient une table `roles` inexistante (RBAC réellement piloté
    par `config/permissions.php`, pas par les tables `rbac_*`/`roles`) — écrans « Vue
    enseignant », « Remplacements » et « Ajouter un créneau » plantaient systématiquement ;
    2 requêtes dans `TimetableRepository` joignaient `matieres.couleur`, colonne inexistante —
    l'affichage de toute grille remplie plantait. Corrigés.
  - Migration `T038` : 8 salles et 11 plages horaires réelles (V1 `salles`/`creneaux`) migrées
    vers `vs_edt_salles`/`vs_edt_plages_horaires`, remplaçant les 9 lignes de démo génériques
    jamais utilisées par une grille réelle.
  - Écrans de gestion Salles et Plages horaires, absents côté V2, construits
    (`SalleController`, `PlageHoraireController` du module `VieScolaire\EmploisDuTemps`).
  - Menus (admin/direction/enseignant) et espace élève rebranchés sur le V2. Routes
    `/emplois-du-temps/*`, `/salles/*`, `/creneaux/*` et `/api/emploi-du-temps/conflits` (V1)
    décommissionnées.
- **Retards** : reliquat laissé de côté lors de la migration des absences (T037) — les 2 lignes
  V1 de type `retard` (table `absences`) chevauchaient le domaine Retards (`vs_retards`), déjà
  seul système branché dans les menus. Migration `T039` : les 2 lignes migrées vers
  `vs_retards`. `vs_retards.heure_arrivee` est NOT NULL (une heure d'horloge) alors que la V1 ne
  stockait qu'une session ('matin'/'apres_midi') et une durée en minutes — heure reconstituée à
  partir du premier créneau réel de la session (référentiel migré en T038), notée comme telle
  dans l'observation de chaque ligne migrée. Absences (T037) et Retards (T039) couvrent
  désormais l'intégralité des 7 lignes de l'ancienne table `absences` (V1).
- Commentaires d'en-tête obsolètes corrigés dans `app/Modules/Academique/routes.php`
  (mentionnait encore une coexistence avec `/notes` V1, supprimé depuis) et
  `app/Modules/VieScolaire/routes.php` (mentionnait `/absences` V1, décommissionnée, et
  `/presences` V1, qui n'a jamais existé — la V1 ne suivait que les absences).
- **Retrait progressif — code V1** (une fois la confiance établie par les vérifications
  ci-dessus) : suppression des 18 fichiers V1 devenus inatteignables — contrôleurs
  (`AbsenceController`, `EmploiDuTempsController`, `SalleController`, `CreneauController`),
  modèles (`AbsenceModel`, `JustificationModel`, `EmploiDuTempsModel`, `SalleModel`,
  `CreneauModel`), événement `AbsenceCreee`, vues (`app/Views/{absences,emplois_du_temps,
  salles,creneaux}/`).
  - `NotificationHandler` (écoutait uniquement `AbsenceCreee`) devenu orphelin, supprimé avec
    le reste — les notifications d'absence V2 passent déjà par leur propre chaîne
    (`AttendanceHandler`/`DisciplineIntegrationHandler`/`CrossModuleListener`, préexistante).
    `AuditHandler`/`StatsCacheHandler` conservés (gèrent d'autres événements encore actifs) —
    seule leur branche `AbsenceCreee` retirée. Registration nettoyée dans `config/events.php`.
- **Retrait progressif — tables V1** :
  - Migration `T040` : en préparant la suppression, trouvé que la migration T037 avait
    reconstruit une justification en texte générique alors qu'une table V1 séparée
    (`justifications`, liée à `JustificationModel` supprimé) contenait le vrai motif soumis par
    la famille — corrigé avec la donnée réelle avant qu'elle ne disparaisse.
  - Sauvegarde complète (structure + données) des 5 tables avant suppression :
    `database/backups/v1_tables_backup_before_drop_20260821.sql` (non versionnée —
    `.gitignore` mis à jour, contient des données personnelles).
  - Migration `T041` : `DROP TABLE` sur `justifications`, `absences`, `emplois_du_temps`,
    `salles`, `creneaux`. FK désactivées le temps de l'opération (dépendances croisées entre
    ces tables). Irréversible par conception — restauration via le fichier de sauvegarde
    uniquement, pas de rollback automatique.
  - `tests/security_tests.php` : 4 tests référençaient les fichiers/tables V1 supprimés
    (`T06`, `T08` réécrits contre `vs_absences`/`vs_justifications_absences` ; `T34` réécrit
    contre le contrôleur V2 ; `T26`/`T27` retirés — testaient l'absence de `$_GET`/`$_POST`
    bruts dans le contrôleur V1, une convention que les contrôleurs V2 n'appliquent
    délibérément pas). Suite repassée : 45/45 PASS.
- Vérification : lint PHP sur tous les fichiers touchés, harnais CLI (écriture/archivage/
  détection de conflit testés directement contre la base réelle, données de test nettoyées),
  vérification navigateur partielle (session expirée en cours de test), suite de tests
  `security_tests.php` au vert après nettoyage.

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
