# Documentation de l'Administrateur — EduNova / Scolaris

> Document établi à partir de l'analyse du code source réel (routes, contrôleurs, politiques d'accès, vues, services,
> modèles) et de vérifications exécutées sur l'instance de développement locale. Il décrit **ce que le compte
> Administrateur peut effectivement faire**, et non ce que les menus ou la documentation historique laissent supposer.
> Aucun mot de passe, aucune clé, aucun contenu du fichier `.env` et aucune donnée personnelle réelle n'y figurent.

| | |
|---|---|
| Version applicative | `2.0.0` (fichier `VERSION`) |
| Branche analysée | `chore/v1-simplification-2026-10-24` |
| Date de l'analyse | 25 octobre 2026 |
| Base de référence | Base locale `ecole_app`, établissement unique « La Persévérance » |

## Légende des statuts

| Symbole | Signification |
|---|---|
| ✅ | **Opérationnel** — route active, contrôleur présent, droit accordé à l'administrateur, page ouverte avec succès lors des essais |
| 🟡 | **Partiel / avec limite** — fonctionne, mais avec une restriction ou une réserve décrite |
| ⛔ | **Bloqué pour l'administrateur actuel** — le code existe et la route est active, mais le droit exigé n'est pas accordé au rôle `admin` (voir [§ 12](#12-écarts-de-permissions-constatés)) |
| 💤 | **Désactivé** — module présent dans le dépôt mais non chargé (`enabled => false`) ; ses URL répondent 404 |
| ❌ | **Absent** — aucune implémentation trouvée |
| ❓ | **Non vérifié** — impossible à confirmer par lecture du code ou par essai |

**Méthode de vérification.** (1) Lecture de `config/routes.php` et des fichiers `routes.php` de chaque module ;
(2) lecture de chaque contrôleur et de chaque politique (`Policies`) ; (3) calcul des permissions effectives du rôle
`admin` telles que l'application les charge à la connexion ; (4) ouverture, avec une session administrateur simulée,
de toutes les pages en lecture (GET) sans paramètre : **124 pages testées → 100 répondent 200, 3 répondent 403,
21 redirigent** (pages réservées aux visiteurs ou à d'autres rôles, ou nécessitant un paramètre) ; (5) évaluation des
méthodes de chaque politique avec les permissions réelles de l'administrateur. Les actions d'écriture (POST) n'ont
**pas** été exécutées : leur disponibilité est déduite du code et des politiques.

---

## Sommaire

1. [Synthèse](#1-synthèse)
2. [A — Architecture et accès administrateur](#2-a--architecture-et-accès-administrateur)
3. [B — Tableau de bord](#3-b--tableau-de-bord)
4. [C — Paramètres](#4-c--paramètres)
5. [D — Gestion de la scolarité](#5-d--gestion-de-la-scolarité)
6. [E — Gestion académique](#6-e--gestion-académique)
7. [F — Finance et comptabilité](#7-f--finance-et-comptabilité)
8. [G — Vie scolaire et communication](#8-g--vie-scolaire-et-communication)
9. [H — Utilisateurs, permissions et traçabilité](#9-h--utilisateurs-permissions-et-traçabilité)
10. [I — Rapports, exports et documents](#10-i--rapports-exports-et-documents)
11. [J — Modules supplémentaires](#11-j--modules-supplémentaires)
12. [Écarts de permissions constatés](#12-écarts-de-permissions-constatés)
13. [Limites connues et points d'attention](#13-limites-connues-et-points-dattention)

---

## 1. Synthèse

L'application est un monolithe PHP natif (MVC maison : `core/`, `app/Controllers`, `app/Models`, `app/Services`,
`app/Modules/*`) sur MySQL, multi-établissements (colonne `etablissement_id`). Seuls **trois modules V2** sont
chargés : **Académique**, **Finance** et **Vie scolaire**. Les fonctions de gestion de la scolarité (élèves, classes,
matières, enseignants, réinscription), les paramètres, les rapports, les annonces et les notifications reposent sur
les **contrôleurs historiques « V1 »** (`app/Controllers`), toujours actifs.

| Domaine | État pour l'administrateur |
|---|---|
| Tableau de bord, profil, notifications, annonces | ✅ |
| Paramètres (11 sous-modules) | ✅ pages et enregistrement ; 🟡 plusieurs valeurs enregistrées ne sont lues par aucun traitement (§ 4.12) |
| Élèves, classes, matières, enseignants, réinscription | ✅ |
| Inscriptions, familles/parents (module Scolarité V2) | 💤 |
| Périodes, évaluations, notes, résultats | ✅ avec réserves ; ⛔ types d'évaluation, configuration des périodes |
| Bulletins | 🟡 consultation et impression de brouillons ; ⛔ génération, publication, archivage, appréciation du directeur |
| Finance (frais, factures, paiements, caisse, décaissements, comptabilité, rapports) | ✅ ; ⛔ annulation de mouvement de caisse, vue de toutes les sessions de caisse |
| Vie scolaire (absences, présences, retards, discipline, récompenses, emplois du temps, activités) | ✅ |
| RH, Documents, Communication, Bibliothèque, Inventaire, Rapports BI, Portails, API REST V2 | 💤 |
| Administration de la plateforme (multi-établissements) | Hors rôle Administrateur (opérateurs de plateforme) |

Le compte `admin` dispose de **281 permissions effectives** (voir § 2.4). Plusieurs politiques du module Académique
exigent des codes (`academique.bulletin.admin`, `academique.periodes.admin`, etc.) qui **ne sont pas accordés** à ce
rôle : c'est la principale source de fonctions bloquées (§ 12).

---

## 2. A — Architecture et accès administrateur

### 2.1 Rôles : qui est « Administrateur » ?

Sept rôles d'établissement existent (`UserModel::allRoles()`, colonne `users.role`, table `etab_roles`) :

| Code | Libellé affiché |
|---|---|
| `admin` | **Administrateur** |
| `directeur` | Directeur |
| `secretaire` | Secrétaire |
| `comptable` | Comptable |
| `enseignant` | Enseignant |
| `parent` | Parent |
| `eleve` | Élève |

**L'Administrateur est le rôle `admin`** (libellé en base : « Accès total à la plateforme »). Ce libellé est trompeur :
le rôle n'a pas un accès total au sens des permissions (§ 12).

**Le « Super Administrateur » n'est pas un rôle d'établissement.** Il s'agit d'un **opérateur de plateforme**
(table `platform_operators`, niveaux `support`, `admin`, `super_admin`), avec une connexion séparée
(`/platform/login`), une session séparée (`Core\Platform\PlatformAuth`) et des écrans dédiés (`/platform/*`). Un compte
Administrateur d'établissement qui ouvre `/platform/dashboard` est redirigé (302) vers la connexion plateforme.

### 2.2 Différences entre Administrateur et les autres rôles

Comparaison des permissions effectives (calculées pour chaque rôle) :

| Rôle | Permissions effectives | Différence notable avec l'Administrateur |
|---|---|---|
| **Administrateur** | 281 | — |
| Directeur | 275 | N'a **pas** `users.delete` (suppression d'utilisateur), ni `inscriptions.*`, `familles.*`, `portal.*`, `biblio.admin`, `finance.comptabilite.saisir`. A en plus les droits de tableaux de bord décisionnels du module Rapports BI (désactivé). |
| Secrétaire | 141 | Pas de gestion des utilisateurs ni des paramètres. |
| Comptable | 58 | Périmètre financier. |
| Enseignant | 80 | Droits « propres » (notes, emploi du temps) et saisie pédagogique. |
| Parent / Élève | 29 / 24 | Espaces dédiés `/parent/*` et `/eleve/*` ; le tableau de bord redirige vers ces espaces. |

Règle explicite dans le code : **seul un administrateur peut attribuer ou modifier le rôle `admin`**
(`UtilisateurController`).

### 2.3 Connexion, déconnexion, comptes

| Fonction | Route | État | Comportement vérifié |
|---|---|---|---|
| Connexion | `GET/POST /login` | ✅ | E-mail + mot de passe (6 caractères minimum au formulaire), jeton CSRF, vérification `password_verify`. Compte désactivé (`actif = 0`) refusé avec message. Échecs et succès journalisés (`Logger::security`). |
| Choix de l'établissement | `/choisir-etablissement` | ✅ | Proposé si l'utilisateur appartient à plusieurs établissements (`user_etablissements`). Tentative sur un établissement non rattaché refusée et journalisée. |
| Changement d'établissement | `POST /auth/switch-school` | ✅ | Disponible depuis le menu utilisateur si plusieurs établissements. |
| Déconnexion | `POST /logout` | ✅ | CSRF vérifié. |
| Mot de passe oublié | `GET/POST /forgot-password`, `/reset-password/{token}` | 🟡 | Jeton aléatoire de 64 caractères hexadécimaux, valable **1 heure**. Message de réponse générique. **L'envoi de l'e-mail n'est pas implémenté** : en mode debug le lien s'affiche à l'écran ; en production, l'appel `mail()` est présent en commentaire. |
| Profil | `GET/POST /profile`, `POST /profile/password` | ✅ | Modification du profil et du mot de passe (mot de passe actuel exigé ; longueur minimale = paramètre « Sécurité »). |
| Inscription publique | `GET/POST /register` | 🟡 | La page est **accessible aux visiteurs** (essai : 200). Elle crée un compte de rôle `eleve` **désactivé** (`actif = 0`), à activer par un administrateur. Le commentaire du code annonce un accès « administrateur uniquement », mais aucune garde correspondante n'a été trouvée. |
| Limitation des tentatives, verrouillage de compte, double authentification | — | ❌ | Aucun mécanisme trouvé. |

Durée de session : `SESSION_LIFETIME` (config, 7200 s par défaut) ; le cookie de session est un cookie de session
navigateur. Le paramètre « durée de session » de la page Sécurité n'est pas relu par le code (§ 4.12).

### 2.4 Contrôle d'accès

Trois niveaux de permissions sont consultés dans cet ordre (`UserModel::getPermissions`) :

1. **RBAC multi-établissement** (tables `etab_roles` / `etab_role_permissions`) — **source utilisée pour l'Administrateur** (281 codes) ;
2. RBAC V2 global (tables `rbac_*`) ;
3. Repli statique `config/permissions.php` (269 codes pour `admin`).

Le contrôle s'applique à deux endroits : `requirePermission('code')` dans les contrôleurs V1 et dans une partie des
contrôleurs V2, et des **politiques** (`app/Modules/*/Policies`) qui combinent permission, statut de l'objet et
parfois périmètre (enseignant, parent). Les actions d'écriture vérifient aussi le **jeton CSRF**.

Permissions effectives de l'Administrateur par famille (nombre de codes) : finance 28, inventaire 22, académique 14,
document 13, paramètres 18, biblio 11, attendance 10, communication 8, familles 2, `users` 5, `eleves` 5, `classes` 5,
`matieres` 5, `enseignants` 5, `notes` 7, `annonces` 5, `emploi_du_temps` 4, `rapports` 1, `bulletins` 2, etc.
La liste complète figure dans le jeu de permissions chargé en session ; elle n'est pas reproduite ici.

Il **n'existe aucune interface** pour créer des rôles, accorder ou retirer des permissions : les permissions sont
attachées aux rôles par configuration/base de données.

### 2.5 Restrictions liées à l'établissement (multi-tenant)

- Les modèles filtrent par `etablissement_id` (`Core\Model`), y compris le `DELETE` générique.
- L'utilisateur est rattaché à un ou plusieurs établissements ; le rôle est résolu **par établissement**.
- Les réglages (`etab_settings`), le branding, les fichiers (logo, favicon, image de connexion) sont **propres à l'établissement**.
- 🟡 Un test d'isolation inter-établissements connu échoue (lecture possible d'absences d'un élève d'un autre établissement) ; il fait l'objet d'un suivi séparé.
- Les écrans **Domaines personnalisés, Quotas de stockage, Monitoring & cache** existent sous forme de vues (`app/Views/settings/domains|quotas|monitoring.php`) mais **aucune route d'établissement ne les expose** (essais : 404). Ils relèvent de l'administration de la plateforme.

### 2.6 Navigation de l'Administrateur

Menu latéral réellement construit pour le rôle `admin` (`MenuService`), en-tête avec cloche de notifications, menu
utilisateur (profil, préférences de notification, changement d'établissement, déconnexion) :

| Groupe | Entrées |
|---|---|
| Tableau de bord | `/dashboard` |
| Élèves | `/eleves` |
| Scolarité | Enseignants `/professeurs` · Classes `/classes` · Matières `/matieres` · Réinscription `/reinscription` |
| Académique | Notes `/v2/academique/evaluations` · Bulletins `/bulletins` · Absences `/v2/vie-scolaire/absences` |
| Vie scolaire | Retards · Discipline · Récompenses |
| Finance | Vue d'ensemble · Frais scolaires · Factures · Paiements · Impayés · Décaissements · Caisse du jour · Comptabilité · Rapports financiers |
| Planning | Emplois du temps · Remplacements · Salles · Plages horaires |
| Rapports | Vue d'ensemble · Stats scolaires · Stats financières · Présences · Réussite · **Utilisateurs** (la gestion des utilisateurs est rangée sous « Rapports ») |
| Paramètres | Établissement · Année scolaire · Organisation académique · Système de notation · Finances · Documents · Notifications · Apparence · Sécurité · Sauvegarde & restauration · Paramètres avancés |
| Annonces · Notifications | `/annonces`, `/notifications` |

**Pages actives mais absentes du menu** (accessibles par URL, testées en 200) : périodes scolaires
(`/v2/academique/periodes`), évaluations (création), résultats (`/v2/academique/resultats/classe|classement|moyennes`),
appréciations (`/v2/academique/appreciations`), présences (`/v2/vie-scolaire/presences`), activités
(`/v2/vie-scolaire/activites`), statistiques de chaque domaine de la vie scolaire, plan comptable/journal/grand
livre/balance, fournisseurs, catégories de frais et de dépenses, historique des notifications (`/admin/notifications`).

---

## 3. B — Tableau de bord

Route : `GET /dashboard` (`HomeController::dashboard`). Les rôles `parent` et `eleve` sont redirigés vers leurs
espaces. Pour l'Administrateur (et le Directeur), la page affiche :

**Cartes de synthèse**

| Carte | Donnée |
|---|---|
| Élèves inscrits | nombre total d'enregistrements `eleves` de l'établissement (actifs et inactifs) |
| Enseignants | nombre de comptes utilisateurs de rôle `enseignant` |
| Classes actives | nombre total de classes de l'établissement |
| Absences aujourd'hui | absences du jour dans `vs_absences` (type `absence`, hors supprimées) |

**Graphiques (Chart.js chargé depuis un CDN)** : Élèves actifs / inactifs (anneau) ; Communauté scolaire
(effectifs par rôle : élèves, parents, enseignants, secrétaires, comptables) ; Absences sur les 6 derniers mois.

**Bloc « Vue d'ensemble »** : élèves, élèves actifs, parents inscrits, enseignants.

**Actions rapides et raccourcis** (affichés selon les permissions) : nouvel élève (`eleves.create`), nouvelle
évaluation (`academique.evaluations.manage`), signaler une absence (`attendance.create`), gérer les classes
(`classes.view`), voir les rapports. **Liens modules** : Élèves, Enseignants, Classes, Notes, Absences, Finance.

**Absents du tableau de bord** : activité récente, alertes, indicateurs financiers (ils sont dans les rapports). Les
notifications sont accessibles par la cloche de l'en-tête (compteur alimenté par `/api/notifications/unread-count`).
Les cartes et graphiques sont identiques pour `admin` et `directeur` ; les rôles secrétaire, comptable et enseignant
ont des tableaux de bord réduits.

---

## 4. C — Paramètres

Entrée : `GET /parametres` (page de catégories filtrée par les permissions). Toutes les écritures exigent la
permission `*.update` correspondante, le jeton CSRF et sont journalisées (`Logger::security`). Les pages sont ouvertes
avec succès (200) pour l'Administrateur.

### 4.1 Établissement — ✅ `/parametres/etablissement` (`branding.view` / `branding.update`)
Identité et contacts affichés sur les documents : nom de l'application, message d'accueil, téléphone, e-mail,
adresse, texte de pied de page. Enregistrement par `POST /parametres/branding` (cible commune avec « Apparence »).

### 4.2 Apparence — ✅ `/parametres/apparence` (`branding.view` / `branding.update`)
Couleur primaire et secondaire (format `#RRGGBB` validé), police, thème (`light`, `dark`, `auto`), affichage du fil
d'Ariane, **logo, favicon et image de connexion** (téléversements optionnels, stockés par établissement avec
contrôle de quota).

### 4.3 Année scolaire — ✅ `/parametres/annee-scolaire` (`settings.general.*`)
Année scolaire active au format `AAAA-AAAA`, l'année de fin devant être l'année de début + 1.

### 4.4 Organisation académique — ✅ `/parametres/academique` (`settings.academique.*`)
Cycles actifs parmi : Préscolaire (PS, MS, GS), Primaire (CI à CM2), Collège (6e à 3e), Lycée (Seconde, Première,
Terminale). Au moins un cycle est obligatoire.

### 4.5 Système de notation — ✅ `/parametres/notation` (`settings.notation.*`)
Barème maximal (5 à 100), seuil de passage (0 à barème), seuil de rattrapage (0 à seuil de passage). Le seuil de
passage et le seuil de rattrapage sont **réellement utilisés** par le calcul des moyennes, des rangs et des bulletins
(`AcademicCalculationService`, `RankingEngine`, `BulletinEngineFactory`).

### 4.6 Finances — ✅ `/parametres/finances` (`settings.finances.*`)
Devise par défaut : XOF, EUR, USD, MAD ou GNF. Lue par les contrôleurs de factures et de paiements.

### 4.7 Documents — ✅ `/parametres/documents` (`settings.documents.*`)
Préfixes de numérotation des **reçus** et des **factures** (2 à 10 lettres/chiffres majuscules). Lus par
`PaymentService` et `InvoiceService`.

### 4.8 Notifications — ✅ page / 🟡 effet `/parametres/notifications` (`settings.notifications.*`)
Deux interrupteurs : e-mail actif, SMS actif. **Aucun traitement ne relit ces deux valeurs** (recherche des clés
`email_actif` / `sms_actif` dans `app`, `core`, `config`). L'envoi dépend des préférences individuelles de chaque
utilisateur (`/notifications/preferences`) et de la configuration serveur (§ 8.7).

### 4.9 Sécurité — ✅ page / 🟡 effet `/parametres/securite` (`settings.securite.*`)
Longueur minimale de mot de passe (6 à 32, **appliquée** à l'inscription, au changement et à la réinitialisation du
mot de passe) et durée de session (15 à 1440 min, **non relue** par le code ; la session suit `SESSION_LIFETIME`).
La création d'un utilisateur par l'administrateur impose une longueur fixe de 8 caractères, indépendante du réglage.

### 4.10 Sauvegarde & restauration — 🟡 `/parametres/sauvegarde` (`settings.sauvegarde.*`)
Seule la **durée de conservation** (7 à 365 jours) est enregistrée. Elle n'est relue par aucun traitement de
l'établissement : la page ne déclenche, ne télécharge ni ne restaure aucune sauvegarde. Les sauvegardes (globales,
par établissement, différentielles, vérification d'intégrité, restauration) sont gérées dans l'**administration de la
plateforme** (`/platform/backups`), hors rôle Administrateur.

### 4.11 Paramètres avancés — ✅ page / 🟡 effet `/parametres/avance` (`settings.avance.*`)
Nombre d'éléments par page : 10, 15, 20, 30, 50 ou 100. **Non relu** : la liste des élèves, par exemple, pagine à 15
éléments fixes.

### 4.12 Tableau récapitulatif de l'effet réel des réglages

| Réglage enregistré | Lu par le code ? |
|---|---|
| Seuil de passage, seuil de rattrapage | **Oui** (calculs académiques) |
| Devise, préfixes reçu/facture | **Oui** (Finance) |
| Longueur minimale du mot de passe | **Oui** (Authentification) |
| Couleurs, police, thème, logo, pied de page, fil d'Ariane | **Oui** (mise en page) |
| Année scolaire active | **Non** (affichée sur la page de réglage uniquement) |
| Cycles actifs | **Non** |
| Barème maximal | **Non** |
| E-mail actif / SMS actif | **Non** |
| Durée de session | **Non** |
| Durée de conservation des sauvegardes | **Non** |
| Éléments par page | **Non** |

### 4.13 Éléments de la liste indicative sans écran de paramétrage dédié
- **Classes, niveaux, matières, coefficients** : gérés dans les modules Scolarité V1 (§ 5), pas dans Paramètres.
- **Périodes académiques** : module Académique (§ 6.1).
- **Bulletins et règles de calcul** : seuils dans « Système de notation » ; règles décrites au § 6.6. Aucun écran ne permet de modifier les règles de calcul elles-mêmes (valeurs par défaut du code).
- **Gestion des modules et fonctionnalités** : ❌ aucune interface ; l'activation se fait par `config/modules.php`.
- **Comptes et sécurité** : § 9 et § 4.9.

---

## 5. D — Gestion de la scolarité

> Le module **Scolarité V2** (`/v2/scolarite/*` : inscriptions, familles, élèves V2) est 💤 désactivé (404). La
> scolarité repose sur les contrôleurs V1 ci-dessous.

### 5.1 Élèves — ✅ (`eleves.view|create|edit|delete`)

| Opération | Route | Détail vérifié |
|---|---|---|
| Lister / rechercher / filtrer | `GET /eleves` | 15 par page ; filtres : recherche texte (`q`), classe, sexe, statut actif (par défaut : actifs) ; compteurs total / garçons / filles. |
| Créer | `GET /eleves/create`, `POST /eleves/store` | Champs : matricule (obligatoire, 3–20 caractères, mis en majuscules), nom, prénom (2–100), sexe, date de naissance (obligatoires), adresse, téléphone, e-mail, classe, **parent responsable** (compte utilisateur de rôle parent), statut actif, photo optionnelle. |
| Consulter | `GET /eleves/{id}` | Fiche avec onglets Scolarité, Notes, Absences, Paiements. Les onglets Absences et Paiements ne contiennent que des **liens** vers les listes filtrées par élève du module Vie scolaire / Finance. |
| Modifier | `GET /eleves/{id}/edit`, `POST /eleves/{id}` | Mêmes champs. Désactiver un élève (statut inactif) tient lieu d'archivage. |
| Supprimer | `POST /eleves/{id}/delete` | **Suppression définitive** de la ligne (`DELETE`), et de la photo si présente. Aucune corbeille ni restauration. Les contraintes de clés étrangères de la base peuvent refuser la suppression d'un élève ayant des données liées (non testé). |
| Importer | `GET/POST /eleves/import` | Fichier `.csv` ou `.txt`, séparateur `;` ou `,` détecté automatiquement ; modèle téléchargeable (`?template=1`). Lignes invalides rapportées une par une (nom, prénom, sexe M/F et date de naissance obligatoires) ; matricule généré s'il est vide ; classe retrouvée par nom ; ligne dont le matricule existe déjà ignorée (« déjà utilisé »). Un événement d'import (nom du fichier, classe, résultats) est émis ; sa trace dépend des écouteurs d'audit. |
| Exporter | `GET /eleves/export/excel` | **CSV** UTF-8 avec BOM, séparateur `;` (ouvrable dans Excel). Colonnes : matricule, nom, prénom, sexe, date de naissance, classe, niveau, téléphone, e-mail, adresse, statut. Respecte les filtres. |
| Imprimer | `GET /eleves/export/pdf` | **Page d'impression HTML** (à imprimer ou enregistrer en PDF depuis le navigateur) ; pas de génération PDF côté serveur. |

### 5.2 Inscriptions, réinscriptions, transferts — 🟡
- **Inscription** (dossier d'inscription par année) : 💤 module Scolarité V2.
- **Réinscription / passage de classe** : ✅ `/reinscription` (`classes.edit`), assistant en trois étapes :
  1. choix de l'année source ; 2. plan de passage classe → classe (`/reinscription/plan`, avec classe de destination ou
  « sortant ») ; 3. aperçu puis **exécution** (`POST /reinscription/executer`). L'exécution déplace les élèves actifs
  vers la classe de destination dans la limite de sa **capacité** (les élèves en surnombre sont listés comme ignorés),
  et **désactive** les élèves « sortants » (classe vidée, statut inactif). Chaque mouvement est consigné.
- **Changement de classe / transfert interne** : ✅ par `affecter` / `retirer` un élève dans la fiche classe (§ 5.3).
- **Transfert vers un autre établissement** : ❌ aucune fonction.
- **Archivage / restauration** : ❌ pas de fonction dédiée ; seul le statut actif/inactif existe.

### 5.3 Classes — ✅ (`classes.view|create|edit|delete`)
- Lister, créer, modifier, consulter (`/classes`, `/classes/create`, `/classes/{id}`, `/classes/{id}/edit`).
- Champs : nom (en majuscules, 10 caractères maximum), niveau, année scolaire (`AAAA-AAAA`), capacité maximale (1 à 100,
  40 par défaut), description.
- **Affecter / retirer un élève** (`POST /classes/{id}/affecter-eleve|retirer-eleve`) : la capacité maximale est contrôlée.
- **Affecter / retirer un enseignement** (enseignant + matière + classe + année) : un doublon est refusé.
- **Supprimer** : refusé tant que des élèves sont affectés à la classe.
- La fiche affiche l'effectif, le taux de remplissage, les élèves et les enseignements affectés.

### 5.4 Matières et coefficients — ✅ (`matieres.*`)
Champs : nom (100 caractères maximum), **coefficient (0,5 à 20)**, volume horaire (1 à 30 h/semaine), enseignant
responsable, catégorie (littéraire, scientifique, autre), description. **Suppression refusée** si la matière est
utilisée dans un enseignement. Pas de programmes (contenus pédagogiques) ni de séries/filières : ❌. Le coefficient de
matière est celui utilisé pour la moyenne générale (§ 6.6).

### 5.4 bis Enseignants — ✅ (`enseignants.*`)
Fiche : nom, prénom, spécialité (obligatoire), grade, téléphone, e-mail (unique), adresse, date de recrutement,
statut actif, photo (3 Mo maximum ; JPEG, PNG, WebP, GIF contrôlés par type MIME). Affectation d'enseignements
(matière × classe × année) et retrait. **Suppression refusée** s'il existe des enseignements liés.
🟡 **Le rattachement d'un enseignant à un compte utilisateur (`professeurs.user_id`) n'est modifiable depuis aucun
écran** : sans ce lien, les écrans « enseignant » (tableau de bord, périmètre de saisie) ne reconnaissent pas la
personne. Dans la base locale, un seul enseignant sur neuf est rattaché.

### 5.5 Parents, responsables, autres personnels
- **Parents** : ce sont des comptes utilisateurs de rôle `parent`, gérés dans « Utilisateurs » (§ 9), reliés à un élève
  par le champ « parent responsable » de la fiche élève. Pas de module famille/responsables multiples (💤 Scolarité V2).
- **Secrétaires, comptables, directeurs** : comptes utilisateurs avec rôle ; aucune fiche d'employé (module RH 💤).
- **Contrats, congés, présences du personnel, formations** : 💤 module RH.

### 5.6 Années scolaires et périodes
Année active : réglage § 4.3 (non relu). Périodes : module Académique, § 6.1.

---

## 6. E — Gestion académique

Module **Académique V2** actif (`/v2/academique/*`, 49 routes). Les écrans « Notes » et « Bulletins » du menu
mènent respectivement aux évaluations V2 et aux aperçus de bulletins V1/V2.

### 6.1 Périodes scolaires — ✅ avec réserves (`/v2/academique/periodes`)
Statuts : `preparation` → `ouverte` → `cloturee` → `archivee`. L'administrateur peut : consulter, créer, modifier,
**générer les périodes par défaut** d'une année (modèle de deux semestres, `POST /periodes/generer`), activer,
ouvrir, clôturer, verrouiller et archiver (sauf si verrouillée).
⛔ Il ne peut **pas** : rouvrir une période clôturée, déverrouiller, modifier directement le statut, ni ouvrir la page
de configuration des périodes (`/periodes/config` → **403**, test réel) : ces actions exigent `academique.periodes.admin`.

### 6.2 Types d'évaluation — ⛔ (`/v2/academique/types-evaluations`)
Les cinq types prévus (Contrôle, Devoir surveillé, Examen, Travaux pratiques, Oral — coefficients par défaut 1, 1, 2, 1,
1) existent en base, mais **toute la gestion (liste, création, modification, activation, archivage) répond 403** à
l'Administrateur, la politique exigeant `academique.types_evaluations.view|manage|admin` (non accordés).

### 6.3 Évaluations — 🟡 (`/v2/academique/evaluations`)
Statuts : `brouillon` → `publiee` → `verrouillee` → `archivee`. Champs : période, type, matière, classe, intitulé
(120 caractères max), description, date, coefficient (0,25 à 10), note maximale (5 à 100).

| Action | Administrateur | Condition |
|---|---|---|
| Lister, consulter | ✅ | — |
| Créer, modifier | ✅ | Matière « Conduite » : exige `academique.evaluations.admin` ⛔ ; évaluation verrouillée : modification réservée à `.admin` ⛔ ; archivée : jamais |
| Publier | ✅ | Évaluation en brouillon |
| Verrouiller | ✅ | Évaluation publiée |
| Déverrouiller, archiver | ⛔ | Exigent `academique.evaluations.admin` |

### 6.4 Notes — 🟡
Saisie en grille (`/evaluations/{id}/notes/saisie`), import CSV (`/evaluations/{id}/notes/importer`), consultation par
évaluation et par élève, modification, publication des notes, verrouillage, suppression.
Statuts d'une note : `saisie` → `publiee` → `verrouillee`.

| Action | Administrateur | Détail |
|---|---|---|
| Consulter | ✅ | — |
| Saisir, modifier, importer | ✅ | Seulement si la saisie est ouverte pour l'évaluation et la note non verrouillée ; **matière « Conduite » : ⛔** (`academique.notes.admin`) |
| Publier les notes | ✅ | — |
| Verrouiller les notes | ⛔ | `academique.notes.admin` |
| Supprimer une note | ⛔ | `academique.notes.admin` |

**Format d'import CSV** : première ligne ignorée (en-tête), puis par ligne : `identifiant_élève` (numérique),
`valeur`, `absent` (1/0), `commentaire` ; séparateur virgule ; extension `.csv` obligatoire ; erreurs comptées et
rapportées.

### 6.5 Résultats, moyennes, classements — 🟡
Pages ✅ : `/v2/academique/resultats/classe`, `/classement`, `/moyennes`, ainsi que les aperçus `/bulletins`,
`/bulletins/classe`, `/bulletins/classement` (calcul à la volée, sans enregistrement). L'Administrateur peut **voir**
les moyennes et les classements ; ⛔ il ne peut ni **déclencher le calcul/la publication des moyennes**
(`academique.moyennes.calculate|admin`), ni **générer ou exporter** un classement (`academique.classement.generate|admin`),
ni consulter le classement par niveau.

### 6.6 Règles de calcul (lues dans `AcademicCalculationService` et `RankingEngine`)
- Note ramenée sur 20 : `valeur ÷ note_max × 20`, arrondie à 2 décimales.
- Moyenne d'une matière : `Σ(note/20 × coefficient d'évaluation) ÷ Σ(coefficients des notes valides)`.
- Moyenne générale : `Σ(moyenne matière × coefficient de la matière) ÷ Σ(coefficients des matières ayant une note)`.
- Options par défaut : un **absent compte 0** ; une **note non saisie compte 0** ; seuil de passage = réglage de l'établissement (10 par défaut).
- Moyenne pluri-périodes pondérée par un poids par période.
- Mentions (sur 20) : ≥ 16 Très Bien ; ≥ 14 Bien ; ≥ 12 Assez Bien ; ≥ 10 Passable ; sinon Insuffisant.
- Les calculs sont **déclenchés par les actions** (aperçu, génération de bulletin) et non par une tâche planifiée ; on ne
  doit donc pas les présenter comme « automatiques » en dehors de ces actions.

### 6.6 bis Appréciations — 🟡 (`/v2/academique/appreciations`)
Consultation ✅. La **saisie** des appréciations par matière et classe exige `academique.notes.manage` (accordé) *et*,
pour un enseignant, d'enseigner la matière dans la classe ; pour un compte sans fiche enseignant le contrôle est
permissif. Non essayé en écriture.

### 6.7 Bulletins — 🟡
| Fonction | Administrateur | Détail |
|---|---|---|
| Aperçu d'un bulletin élève/classe/classement | ✅ | `/bulletins`, `/bulletins/{id}`, `/bulletins/classe`, `/bulletins/classement` |
| Impression d'un bulletin **existant** | ✅ | `/v2/academique/bulletins/{élève}/{période}/imprimer` (essai : 200). Un brouillon porte le filigrane **« BROUILLON » sans QR code** ; un bulletin publié/archivé porte un QR de vérification. |
| **Génération** d'un bulletin (enregistrement) | ⛔ | `academique.bulletin.generate|admin` |
| **Publication**, **archivage** | ⛔ | `academique.bulletin.publish|archive|admin` ; de plus la publication est désactivée tant que `BULLETIN_PUBLICATION_ENABLED` n'est pas à `true` |
| Appréciation du directeur | ⛔ | `/…/appreciation-directeur` → **403** (test réel) ; `academique.bulletin.admin` |
| Export | ⛔ | `academique.bulletin.export|generate|admin` |
| Vérification publique par QR | ✅ (publique) | `/v2/academique/bulletins/verify/{jeton}` : réponse « authentique / non authentique » sans donnée d'élève, 30 requêtes/minute/IP |

État des données locales : 36 bulletins enregistrés, **tous au statut brouillon**. La colonne « appréciation de
l'enseignant » n'est pas imprimée (laissée vide pour une saisie manuscrite).
Les jetons de vérification sont décrits dans `docs/deploiement/BULLETIN_TOKEN_SECRET.md`.

### 6.8 Absences, retards, emplois du temps
Traités au § 8 (module Vie scolaire).

---

## 7. F — Finance et comptabilité

Module **Finance V2** actif (`/v2/finance/*`, 97 routes). Les anciens contrôleurs financiers V1
(`PaiementController`, `DepenseController`, `ComptabiliteController` dans `app/Controllers`) **n'ont plus de route**.
Tous les droits financiers exigés par les politiques sont accordés à l'Administrateur, **sauf** deux points de caisse (§ 7.5).

### 7.1 Frais — ✅
- **Catégories de frais** (liste, création, modification, activation/désactivation) : `/v2/finance/frais/categories`.
- **Types de frais** (`/v2/finance/frais`) : création, modification, consultation, activation, désactivation, archivage,
  suppression ; périodicité : paiement unique, mensuel, trimestriel, semestriel, annuel ; devises : XOF, EUR, USD, MAD, GNF.
- **Tarifs** par type de frais (`POST /frais/{id}/tarif`).

### 7.2 Factures et frais associés aux élèves — ✅
Statuts rencontrés dans le code : brouillon, émise, partiellement payée, payée, en retard, annulée, archivée.
- Création manuelle, modification, ajout/suppression de lignes, **remise** (pourcentage, montant fixe ou exonération
  totale), **échéancier**, émission, annulation, archivage, suppression, impression (`/factures/{id}/print`).
- **Génération en masse** (`/factures/generer`) : par **classe** (types de frais choisis) ou par **niveau**, pour une
  année ; une facture déjà existante pour les mêmes frais est ignorée (règle anti-doublon).
- **Annulation avec paiements existants** : création automatique d'un **avoir** (numéro préfixé `AVO`).

### 7.3 Paiements et reçus — ✅
- Enregistrement d'un paiement sur une facture (montant, mode, date, référence externe, note), **acomptes** (paiements
  partiels), validation, achèvement, annulation, **remboursement**, traitement du **trop-perçu**, liste des paiements d'une facture.
- **Modes de paiement pris en charge** : espèces, chèque, virement bancaire, carte bancaire, Orange Money, Wave,
  Moov Money, avoir/crédit. Ce sont des **modes d'enregistrement manuel** ; aucune passerelle de paiement en ligne
  (agrégateur, API opérateur) n'est implémentée. ❌ paiement en ligne.
- **Reçus** : consultation et impression (`/paiements/{id}/recu`, `/recu/print`), numérotation avec le préfixe du réglage « Documents ».
- ⚠ `GET /v2/finance/paiements/create` redirige (302) lors de l'essai sans paramètre ; le formulaire s'ouvre depuis une facture.

### 7.4 Soldes et impayés — ✅
`/v2/finance/rapports/impayes` (liste des impayés), solde par facture, état de recouvrement par type de frais
(rapport analytique).

### 7.5 Caisse — 🟡 (`/v2/finance/caisse`)
Ouverture d'une session de caisse, mouvements manuels (recette, décaissement, correction), fermeture, **rapprochement**,
impression du journal de caisse : ✅ (droits `finance.caisse.ouvrir|fermer|rapprocher|view` accordés ; l'enregistrement d'un
mouvement est autorisé par `finance.caisse.ouvrir`). ⛔ **Annuler un mouvement** (`finance.caisse.admin` ou
`finance.caisse.mouvement`, non accordés) et voir **toutes** les sessions de caisse (`finance.caisse.admin`) : sans ce
droit, l'Administrateur ne voit que les sessions dont il est le caissier.

### 7.6 Dépenses (décaissements) et fournisseurs — ✅
Circuit : création → **validation** → **approbation** (ou rejet) → **paiement** ; annulation ; pièces justificatives
(téléversement et suppression). **Fournisseurs** (création, modification, activation) et **catégories de dépenses**.
Les recettes sont les paiements encaissés ; il n'existe pas de saisie de « recette » libre hors mouvement de caisse.

### 7.7 Comptabilité — ✅ (`/v2/finance/comptabilite`)
Plan comptable, journal, grand livre, balance, exercices comptables (création, clôture), clôture de période comptable,
consultation et **extourne** d'écritures. Les écritures sont générées par des règles comptables automatiques à partir
des événements financiers (paiements, etc.). Il n'existe **pas de route de saisie manuelle d'écriture** : seule l'extourne
d'une écriture existante est proposée.

### 7.8 Rapports financiers — ✅
Sept types : tableau de bord financier, paiements, factures, impayés, caisse, analytique, comptable. Formats : écran
(HTML), **CSV**, **« Excel »** (fichier `.xls` en réalité de l'HTML tabulaire) et **« PDF »** (page d'impression du
navigateur). Le droit `finance.rapports.print` n'est pas accordé, mais la politique `canPrint` est aussi satisfaite par
`finance.rapports.export`, que l'Administrateur possède : l'impression n'est donc pas bloquée.

### 7.9 Gestion des comptes du comptable
Création et désactivation du compte via « Utilisateurs » (rôle `comptable`). Les permissions du comptable sont celles
du rôle (58) ; elles ne sont pas modifiables par utilisateur.

---

## 8. G — Vie scolaire et communication

Module **Vie scolaire** actif (`/v2/vie-scolaire/*`, 98 routes). Toutes les politiques évaluées pour l'Administrateur
accordent les droits de lecture, création, modification, validation et export (quelques méthodes dépendantes du statut
d'un objet n'ont pu être évaluées qu'à l'exécution).

### 8.1 Absences — ✅
Liste, **pointage de classe** (appel par classe et par date : présent/absent/retard…), saisie individuelle,
modification, suppression, **justificatifs** (dépôt par le parent ou saisie, puis **validation / refus** par
l'administration), statistiques. Statuts : non justifiée, en attente, justifiée, refusée ; types : absence, retard,
dispense ; motifs préparamétrés (7, dont maladie, rendez-vous médical, deuil, transport).

### 8.2 Présences (sessions de cours) — ✅
Sessions de présence, pointage, validation, historique, statistiques. Statuts d'un élève : présent, absent, retard,
dispensé, sortie anticipée. Absente du menu (URL `/v2/vie-scolaire/presences`).

### 8.3 Retards — ✅
Saisie, modification, justification, validation/refus, suppression, statistiques, **export CSV**. Une **alerte** se
déclenche quand le seuil de **3 retards** est atteint pour un élève.

### 8.4 Discipline — ✅
Incidents et dossiers disciplinaires, traitement des incidents, **sanctions** (avertissement, blâme, exclusion de
cours, exclusion temporaire, exclusion définitive, travaux d'intérêt général, conseil de discipline, mesure
éducative), validation et levée de sanction, **appels** (soumission et traitement), clôture de dossier, statistiques,
export CSV. 10 catégories de discipline préparamétrées.

### 8.5 Récompenses — ✅
Attribution, modification, validation, révocation, classement comportemental, statistiques, export CSV.

### 8.6 Emplois du temps — ✅ (`/v2/vie-scolaire/emplois-du-temps`)
Création d'un emploi du temps par classe et année, ajout/modification/suppression de créneaux, **publication**,
vue par enseignant, **remplacements**, **salles** et **plages horaires** (création, modification, activation),
statistiques, export CSV. À l'ajout d'un créneau, trois **conflits** sont détectés : enseignant déjà occupé, salle
déjà occupée, classe déjà occupée sur la même plage.
🟡 Dans la base locale aucun emploi du temps n'est encore créé (listes vides) ; la correction de l'affichage des listes
(`Core\View::render`, variable `data`) était nécessaire pour que la liste s'affiche correctement une fois des données saisies.

### 8.7 Activités scolaires — ✅
Création, modification, publication, annulation, **inscription d'élèves** (liste d'attente), annulation d'inscription,
**pointage des présences**, statistiques, export CSV. Non présent dans le menu (URL `/v2/vie-scolaire/activites`).

### 8.8 Annonces — ✅ (`annonces.view|create|edit|delete`)
Création/modification/suppression d'annonces avec **audience** : tous, parents, élèves, enseignants, une classe, ou
des utilisateurs choisis. À la publication, les destinataires sont notifiés par `NotificationService`
(canal interne toujours, e-mail/SMS selon les préférences de chacun).

### 8.9 Notifications — ✅ / 🟡
- Utilisateur : liste (`/notifications`), marquer comme lu, préférences par canal (`/notifications/preferences` : interne, e-mail, SMS).
- Administrateur (`notifications.manage`) : **historique** des envois avec filtres (recherche, canal, type, statut), **envoi de test**, **purge** du journal (entrées de plus de N jours, 7 minimum).
- Canal interne ✅. **E-mail** : utilise la fonction PHP `mail()` (dépend de la configuration du serveur). **SMS** : pilote `stub` par défaut ; sans passerelle REST configurée, le résultat est « Gateway SMS non configurée ». **Notifications push** : routes `/api/push/*` présentes (clé VAPID, abonnement) ; fonctionnement dépendant de la configuration.

### 8.10 Messagerie et documents scolaires — 💤
La messagerie interne et la gestion documentaire relèvent des modules Communication et Documents (désactivés). Les
documents imprimables actuellement disponibles sont ceux du § 10.

---

## 9. H — Utilisateurs, permissions et traçabilité

### 9.1 Gestion des comptes — ✅ (`/utilisateurs`, `users.*`)
- **Lister** avec recherche, filtre par rôle et par statut.
- **Créer** : nom (2–100), prénom, e-mail (unique), téléphone, **rôle parmi les sept**, mot de passe **de 8 caractères
  minimum** avec confirmation. Compte actif dès la création. Seul un administrateur peut créer un compte `admin`.
- **Modifier** : mêmes champs ; un nouveau mot de passe (8 caractères min.) peut être défini ; **seul un administrateur
  peut modifier le rôle `admin`**.
- **Activer / désactiver** (`toggle-actif`) : interdit sur son propre compte. Sert aussi à **activer les comptes créés
  par l'inscription publique**.
- **Supprimer** (`users.delete`, administrateur uniquement — le Directeur ne l'a pas) : suppression définitive ;
  interdit sur son propre compte.

### 9.2 Rôles et permissions
- Les sept rôles sont fixes. Attribution d'un rôle : à la création/modification d'un utilisateur.
- Aucune interface de gestion des rôles ni des permissions (§ 2.4).
- Les actions sensibles combinent permission, jeton CSRF, politique et, pour certaines, le statut de l'objet.

### 9.3 Mots de passe
Changement par l'utilisateur (`/profile/password`), réinitialisation par e-mail (non envoyé, § 2.3), définition par
l'administrateur dans la fiche utilisateur. Hachage `bcrypt`. Pas d'historique ni d'expiration des mots de passe.

### 9.4 Journaux et traces
- **Journal de sécurité** (fichiers via `Core\Logger::security`) : connexions réussies/échouées/désactivées, accès refusés,
  changements de paramètres, choix d'établissement, accès à des bulletins. **Aucun écran** ne permet de le lire.
- **Journal d'audit en base** (`audit_logs`) alimenté par les écouteurs d'événements (évaluations, notes, périodes,
  bulletins, etc.) avec masquage automatique des champs sensibles. **Aucun écran d'administration** ne l'affiche dans
  les modules actifs (la seule page d'audit, `/v2/portals/admin/audit`, appartient au module Portails désactivé).
- **Historique des notifications** : seul journal consultable (§ 8.9).

---

## 10. I — Rapports, exports et documents

### 10.1 Rapports généraux — ✅ (`/rapports`, permission `rapports.view`)

| Rapport | Contenu | Filtres | Sortie |
|---|---|---|---|
| Vue d'ensemble `/rapports` | KPI globaux, KPI financiers (recettes, dépenses, impayés, solde, taux de recouvrement), KPI de réussite, effectifs par classe, absences hebdomadaires, finance par mois, réussite par classe, répartition des mentions | année scolaire, période | Écran + graphiques |
| Scolaire `/rapports/scolaire` | Effectifs par classe (garçons/filles), distribution des moyennes | période | Écran |
| Financier `/rapports/financier` | Recettes vs dépenses mensuel, dépenses par catégorie, recouvrement par type de frais | année | Écran |
| Présences `/rapports/presences` | Absences justifiées vs non justifiées, absences et retards par classe | — | Écran |
| Réussite `/rapports/reussite` | Taux de réussite par classe, répartition des mentions | période, classe | Écran |

Sources : `bulletins_v2` (réussite), `vs_absences`/`vs_retards` (présences) et dépôts du module Finance
(finances). Les valeurs absentes sont remplacées par des zéros si une requête échoue (`tryGet`).

**Exports** : `GET /rapports/export/pdf` = **page d'impression HTML** ; `GET /rapports/export/excel/{type}` = **CSV**
(UTF-8) nommé `rapport_{type}_{date}.csv`, types : scolaire, financier, présences, réussite.

### 10.2 Autres exports et impressions

| Document | Emplacement | Format réel |
|---|---|---|
| Liste des élèves | `/eleves/export/excel` · `/eleves/export/pdf` | CSV (`;`, BOM) · page d'impression |
| Modèle d'import élèves | `/eleves/import?template=1` | CSV |
| Bulletin (brouillon / publié) | `/v2/academique/bulletins/{é}/{p}/imprimer` | Page d'impression HTML (filigrane BROUILLON ; QR si publié) |
| Facture | `/v2/finance/factures/{id}/print` | Page d'impression |
| Reçu de paiement | `/v2/finance/paiements/{id}/recu/print` | Page d'impression |
| Journal de caisse | `/v2/finance/caisse/{id}/journal/print` | Page d'impression |
| Rapports financiers (7 types) | `/v2/finance/rapports/export` | HTML, CSV, `.xls` (HTML tabulaire), page d'impression |
| Retards, discipline, récompenses, emplois du temps, activités | `…/export` | CSV |
| Notes (import) | `/v2/academique/evaluations/{id}/notes/importer` | Import CSV |

**Limites** : aucun PDF n'est produit côté serveur (impression navigateur uniquement) ; aucun vrai fichier `.xlsx` ;
pas de rapports planifiés (💤 module Rapports & BI) ; pas de certificat de scolarité, attestation ou carte scolaire.

---

## 11. J — Modules supplémentaires

### 11.1 Modules présents mais désactivés (💤)

`config/modules.php` : `enabled => false`. Les routes ne sont pas chargées (essais : 404).

| Module | Routes déclarées | Périmètre prévu (manifeste) |
|---|---|---|
| Scolarité V2 | 50 | Élèves, classes, inscriptions, familles, matières (V2) |
| RH | 157 | Employés, contrats, congés, présences, évaluations, formations, organisation (gelé le 2026-10-24 ; tables conservées) |
| Documents | 41 | Plateforme documentaire, versions, partages, corbeille |
| Communication | 40 | Messagerie, diffusion, campagnes, modèles |
| Bibliothèque | 58 | Catalogue, emprunts, réservations, pénalités |
| Inventaire | 55 | Articles, stocks, commandes, affectations, maintenance |
| Rapports & BI | 35 | Tableaux de bord, KPI, exports, rapports planifiés |
| Portails | 66 | Sept portails contextuels |
| API REST V2 | 75 | JWT, clés d'API, webhooks (gelée le 2026-10-24) |

Le rôle `admin` possède déjà des permissions pour plusieurs de ces modules (inventaire 22, bibliothèque 11, document
13, communication 8…) ; elles n'ont **aucun effet** tant que les modules sont désactivés.

### 11.2 API V1 conservée (actives)
`GET /api/eleves`, `GET /api/eleves/{id}`, `GET /api/notifications/unread-count|recent|latest`,
`GET|POST /api/push/vapid-key|subscribe|unsubscribe|send`. Essais : `/api/eleves` et `/api/notifications/unread-count` répondent 200.

### 11.3 PWA et accès mobile
Manifeste (`manifest.json`), service worker, bandeau d'installation, bandeau hors-ligne et barre de navigation
mobile sont présents dans la mise en page principale. Le comportement hors-ligne détaillé n'a pas été vérifié (❓).

### 11.4 Administration de la plateforme (Super Administrateur) — hors rôle Administrateur
Accessible uniquement par les opérateurs de plateforme : tableau de bord et statistiques, création/activation/
suspension/archivage/restauration/suppression d'établissements, attribution de plans, domaines personnalisés (ajout,
vérification, activation), quotas de stockage, monitoring et vidage de cache par établissement, plans tarifaires,
sauvegardes (globale, par établissement, différentielle, vérification, restauration). Niveaux : `support`, `admin`,
`super_admin`.

---

## 12. Écarts de permissions constatés

Les politiques ci-dessous exigent des codes que l'Administrateur ne possède pas. Le jeu de permissions attribué
contient des codes voisins mais **différents** (par exemple `academique.bulletin.generer` / `publier` au lieu de
`academique.bulletin.generate` / `publish`), ce qui rend les fonctions concernées inaccessibles malgré la présence de
boutons ou de routes.

| Code exigé | Fonctions concernées | Accordé ? |
|---|---|---|
| `academique.bulletin.generate`, `.publish`, `.archive`, `.export`, `.admin` | Générer, publier, archiver, exporter un bulletin ; appréciation du directeur | Non (l'administrateur a `bulletin.generer`, `bulletin.publier`, `bulletin.view`) |
| `academique.periodes.admin` | Rouvrir/déverrouiller une période, statut direct, configuration des périodes | Non |
| `academique.types_evaluations.view`, `.manage`, `.admin` | Toute la gestion des types d'évaluation | Non |
| `academique.evaluations.admin` | Évaluations « Conduite », déverrouiller, archiver | Non |
| `academique.notes.admin` | Notes « Conduite », verrouiller, supprimer une note | Non |
| `academique.moyennes.calculate`, `.admin` | Calcul / publication des moyennes | Non (`academique.moyennes.recalcul` accordé mais non utilisé par la politique) |
| `academique.classement.generate`, `.admin` | Génération, export, classement par niveau | Non |
| `finance.caisse.admin` (ou `finance.caisse.mouvement`) | Voir toutes les sessions de caisse ; annuler un mouvement | Non |
| `finance.rapports.print` | Impression des rapports financiers | Non, mais couvert par `finance.rapports.export` (accordé) |

Ces écarts sont connus et suivis séparément ; ils ne sont **pas** corrigés par ce document.

---

## 13. Limites connues et points d'attention

1. **Écarts de permissions** (§ 12) : bulletins non publiables, types d'évaluation inaccessibles, etc.
2. **Réglages sans effet** (§ 4.12) : année active, cycles, barème, e-mail/SMS, durée de session, conservation des sauvegardes, éléments par page.
3. **E-mails** : réinitialisation de mot de passe non envoyée en production ; SMS en mode simulé tant qu'aucune passerelle n'est configurée.
4. **Suppressions définitives** : élèves, utilisateurs (pas de corbeille).
5. **Inscription publique** ouverte aux visiteurs (comptes créés inactifs).
6. **Pas de limitation des tentatives de connexion** ni de double authentification.
7. **Aucun écran de lecture du journal d'audit ni du journal de sécurité.**
8. **Rattachement enseignant ↔ compte** sans interface (§ 5.4 bis).
9. **Pas de paiement en ligne**, pas de PDF serveur, pas de `.xlsx` natif.
10. **Isolation inter-établissements** : une anomalie connue sur la lecture des absences est suivie à part (§ 2.5).
11. **Pages absentes du menu** (périodes, résultats, présences, activités, historique des notifications) à connaître pour la formation des utilisateurs.
12. Les actions d'écriture (POST) n'ont pas été exécutées pour cette documentation : leur disponibilité est établie par le code et les politiques, non par essai.
