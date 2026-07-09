# Audit d'Architecture Fonctionnelle — SCOLARIS (ecole_app)

**Date :** 29 juin 2026
**Framework :** PHP 8.2 MVC custom
**Base de données :** MySQL 8
**Auditeur :** Claude Sonnet 4.6

---

## Chiffres clés

| Élément | Quantité |
|---------|----------|
| Modules fonctionnels | 21 |
| Contrôleurs | 22 (21 + 4 API) |
| Modèles | 24 |
| Vues | 82 fichiers .php |
| Tables SQL | 27 |
| Rôles | 7 |
| Permissions codifiées en DB | 28 |
| Routes enregistrées | ~248 |
| Migrations SQL | 11 fichiers |
| Services | 3 (Email, SMS, Notification) |
| Middlewares | 4 |

---

## 1. Cartographie complète des modules

| # | Module | Objectif | Complétude | Controller(s) |
|---|--------|----------|------------|---------------|
| 1 | **Authentification** | Connexion, identité, sessions, reset MdP | 90 % | `AuthController` |
| 2 | **Tableau de bord** | Vue synthétique par rôle + graphiques KPI | 85 % | `HomeController` |
| 3 | **Élèves** | Gestion complète des élèves inscrits | 90 % | `EleveController` |
| 4 | **Classes** | Gestion des classes, affectations élèves/enseignants | 85 % | `ClasseController` |
| 5 | **Matières** | Référentiel matières, coefficients, responsables | 85 % | `MatiereController` |
| 6 | **Enseignants** | Fiche pédagogique, enseignements, profil | 85 % | `ProfesseurController` |
| 7 | **Académique — Notes** | Contrôles, saisie notes, moyennes auto, classements | 80 % | `NoteController` |
| 8 | **Académique — Bulletins** | Génération bulletins PDF, classement par classe | 80 % | `BulletinController` |
| 9 | **Absences** | Pointage journalier, suivi, justifications, alertes | 85 % | `AbsenceController` |
| 10 | **Comptabilité** | Frais scolaires, paiements, dépenses, caisse, impayés | 80 % | `ComptabiliteController` |
| 11 | **Paiements** | Encaissement, reçus | 80 % | `PaiementController` |
| 12 | **Dépenses** | Saisie et suivi des dépenses de l'établissement | 80 % | `DepenseController` |
| 13 | **Emploi du temps** | Planning hebdo/mensuel, salles, créneaux, conflits | 75 % | `EmploiDuTempsController` |
| 14 | **Salles** | Référentiel des salles de cours | 80 % | `SalleController` |
| 15 | **Créneaux horaires** | Définition des plages horaires | 80 % | `CreneauController` |
| 16 | **Rapports** | Analytics scolaires, financiers, présences, réussite | 80 % | `RapportController` |
| 17 | **Annonces** | Création et diffusion d'annonces par audience | 90 % | `AnnonceController` |
| 18 | **Notifications** | Centre de notifications interne, préférences, push | 70 % | `NotificationController` |
| 19 | **Espace Parent** | Consultation données de l'enfant, justifications | 75 % | `ParentController` |
| 20 | **Espace Élève** | Consultation notes, bulletin, emploi du temps | 70 % | `EspaceEleveController` |
| 21 | **Utilisateurs** | CRUD comptes, activation/désactivation | 85 % | `UtilisateurController` |
| 22 | **PWA / API** | Service Worker, offline, push VAPID, endpoints JSON | 65 % | `Api\*Controller` |

---

## 2. Cartographie détaillée des fonctionnalités

### Authentification
- Connexion par email/mot de passe
- Déconnexion
- Auto-redirection si déjà connecté
- Inscription (route présente — à protéger, voir section 3)
- Mot de passe oublié (envoi token par email)
- Réinitialisation via token temporaire
- Consultation et mise à jour du profil (nom, prénom, téléphone, photo, email)
- Changement de mot de passe depuis le profil
- Enregistrement de la dernière connexion (`derniere_connexion`)
- Protection CSRF sur tous les formulaires POST

### Tableau de bord
- Vue admin/directeur : 4 KPI + 3 graphiques Chart.js (donut actifs/inactifs, barres communauté, courbe absences 6 mois) + vue d'ensemble chiffres + actions rapides
- Vue secrétaire : 3 KPI + 4 accès rapides
- Vue comptable : 3 KPI + redirection finance + accès rapides
- Vue enseignant : profil + 4 KPI + tableau des enseignements de l'année + accès rapides
- Vue parent : 2 CTA (notes, absences)
- Vue élève : 2 CTA (notes, absences) + conseil

### Élèves
- Liste paginée avec filtres (nom, prénom, matricule, classe, sexe, actif)
- Création avec tous les champs (matricule, nom, prénom, naissance, sexe, adresse, téléphone, email, classe, parent, photo)
- Modification
- Consultation fiche détaillée
- Suppression (soft delete via `actif = 0`)
- Import bulk par fichier CSV
- Export liste en PDF (layout dédié)
- Export liste en Excel
- Upload et affichage photo

### Classes
- Liste des classes (avec nombre d'élèves et enseignants)
- Création (nom, niveau, année scolaire, max_élèves, description)
- Modification / Suppression
- Vue détaillée : liste des élèves inscrits + liste des enseignants affectés
- Affecter / Retirer un élève de la classe
- Affecter / Retirer un enseignant

### Matières
- Liste (nom, coefficient, volume horaire, responsable)
- Création / Modification / Vue détaillée / Suppression
- Affectation d'un responsable (professeur)

### Enseignants
- Liste avec filtres
- Création (nom, prénom, spécialité, grade, date_recrutement, téléphone, email, adresse, photo)
- Modification / Vue fiche détaillée / Suppression
- Affecter / Retirer un enseignement (prof + matière + classe + année)
- Upload photo

### Notes / Évaluations
- Liste des contrôles (avec filtres classe/matière/période)
- Créer un contrôle (libellé, type, coefficient, note max, classe, matière, période, date)
- Modifier / Supprimer un contrôle
- Saisie des notes par contrôle (grille par élève, marquer absent)
- Calcul automatique des moyennes par matière (`moyennes_matieres`)
- Calcul automatique des moyennes générales + rang + mention (`moyennes_generales`)
- Vue d'ensemble des moyennes par classe/période

### Bulletins
- Liste des bulletins disponibles
- Vue bulletin par élève (notes par matière + moyennes + rang + mention)
- Vue bulletins par classe
- Classement général par classe et période
- Impression bulletin PDF (layout dédié print)

### Absences
- Pointage journalier (liste des élèves d'une classe par session : matin / après-midi / journée)
- Création manuelle d'une absence ou retard
- Consultation liste filtrée (élève, classe, date, type, statut_justif)
- Détail d'une absence / Suppression
- Soumettre une justification (parent ou admin, avec pièce jointe optionnelle)
- Valider / Refuser une justification
- Statistiques d'absences (par classe, par élève, par période)
- Page alertes (élèves dépassant un seuil : surveiller 3+, sérieux 5+, critique 10+)

### Comptabilité
- Liste et gestion des types de frais (inscription, mensualité, transport, cantine, sport, fournitures)
- Affectation de frais à un élève (année scolaire, montant, échéance)
- Liste des frais par élève avec statut (en_attente / partiel / payé)
- Enregistrement d'un paiement (montant, date, mode, référence)
- Consultation et impression d'un reçu de paiement
- Saisie des dépenses (avec catégorie, libellé, montant, mode, référence)
- Tableau de bord caisse (entrées/sorties du jour/mois)
- Liste des impayés
- Rapport financier global + impression PDF + export Excel

### Emploi du temps
- Vue hebdomadaire (grille jour × créneau) et vue mensuelle
- Création d'un cours (classe + matière + professeur + salle + créneau + jour)
- Modification / Suppression / Impression
- Détection de conflits horaires en temps réel (API JSON)
- Gestion des salles (CRUD : nom, capacité, type, bâtiment)
- Gestion des créneaux horaires (CRUD : nom, heure début/fin, type, ordre)

### Rapports
- Dashboard analytique centralisé
- Rapport scolaire (effectifs, taux de réussite, moyennes)
- Rapport financier (recettes, dépenses, solde)
- Rapport présences/absences (taux d'absentéisme)
- Rapport réussite (classements, mentions)
- Export PDF (tous rapports) / Export Excel par type

### Annonces
- Liste filtrée par audience et recherche textuelle
- Création (titre, contenu, audience : tous / parents / élèves / enseignants)
- Modification / Suppression avec confirmation modal
- Badge "Nouveau" (< 3 jours) / Aperçu temps réel / Compteur de caractères

### Notifications
- Centre de notifications (liste, marquer lu, marquer tout lu)
- Préférences par canal et par type d'événement (note, absence, paiement, annonce)
- API : compteur non lus, récentes, dernières
- Administration : historique des envois, test d'envoi, purge
- Push VAPID (abonnement, désabonnement, envoi)
- Canal interne : ✅ opérationnel
- Canal email : ⚠️ configuration nécessaire
- Canal SMS : ⚠️ non implémenté côté provider

### Espace Parent
- Dashboard, notes enfant, bulletin, absences (+ justifier), paiements

### Espace Élève
- Dashboard, mes notes, mon bulletin, mon emploi du temps, mon profil

### Utilisateurs
- Liste paginée, 7 cartes de stat par rôle
- Création / Modification / Suppression
- Activation / Désactivation (avec garde : impossible pour soi-même)

---

## 3. Fonctionnalités incomplètes

| Fonctionnalité | Module | Ce qui manque |
|----------------|--------|---------------|
| **Inscription publique non protégée** | Auth | La route `/register` est accessible sans guard de rôle. Dans un ERP scolaire, la création de comptes devrait être admin-only. |
| **Canal email notifications** | Notifications | `canal_email` existe en DB, aucun provider SMTP configuré. Les envois échouent silencieusement. |
| **Canal SMS notifications** | Notifications | `canal_sms` existe en DB, zéro implémentation côté service. |
| **Push notifications VAPID — déclencheurs auto** | PWA | Subscribe/unsubscribe implémentés, mais `/api/push/send` est manuel. Aucun déclencheur automatique sur événement. |
| **Mode offline PWA** | PWA | Service Worker v2.1 présent, périmètre du cache offline non documenté. Données dynamiques non synchronisées. |
| **Justification avec pièce jointe** | Absences | Colonne `document_path` existe en DB, upload fichier absent des routes. |
| **Permissions manquantes en DB** | Permissions | `annonces.*`, `comptabilite.*`, `paiements.*`, `depenses.*`, `bulletins.*`, `emplois_du_temps.*` ne figurent pas dans la table `permissions`. Ces modules font des vérifications de rôle directement. |
| **Comptable sans accès comptabilité en DB** | Permissions | Le rôle `comptable` n'a en DB que `eleves.view`, `classes.view`, `enseignants.view`, `rapports.view`. Son accès réel à `/comptabilite` est géré hors table permissions. |
| **Lien parent ↔ élève** | Élèves | `eleves.parent_id → users.id` existe en DB. Si le filtrage applicatif n'est pas systématique, un parent peut accéder aux données de n'importe quel élève. |
| **Historique des notes** | Académique | Aucun log des modifications de notes (qui a changé quoi, quand). |
| **Audit log général** | Transversal | Seul `notification_logs` existe. Aucune trace CRUD pour les autres modules. |
| **Moyenne annuelle multi-période** | Académique | `moyennes_generales` stocke par période. Pas de table pour l'agrégat annuel des trimestres. |
| **API REST complète** | PWA/API | Seulement 3 entités exposées en JSON (élèves, frais-élève, notifications). Notes, absences, bulletins, emploi du temps ne sont pas exposés. |
| **Référentiel année scolaire** | Transversal | L'année scolaire est une chaîne VARCHAR répétée dans 6+ tables (`enseignements`, `frais_eleves`, `paiements`, `emplois_du_temps`, `periodes`, `controles`). Pas de table `annees_scolaires` centrale. |

---

## 4. Fonctionnalités dupliquées

| Duplication | Description |
|-------------|-------------|
| **Stats KPI dashboard vs rapports** | `HomeController::getStats()` et `RapportController` recalculent les mêmes agrégations. À extraire dans un `StatisticsService`. |
| **Filtre élèves par parent** | La logique de filtrage par `parent_id` est réécrite dans chaque contrôleur de l'espace parent. À centraliser dans `EleveModel::findByParentId()`. |
| **Calcul des moyennes** | `NoteController`, `BulletinController` et `RapportController` agrègent tous les mêmes données de `notes` + `moyennes_matieres` + `moyennes_generales`. |
| **Vérification de rôle codée en dur** | Comptabilité, EmploiDuTemps, Annonces contournent la table `permissions` avec des `$user['role'] === 'admin'`. |
| **Enseignements vs Emploi du temps** | `EnseignementModel` (affectation annuelle) et `EmploiDuTempsModel` (créneau hebdomadaire) représentent tous deux des "cours" avec des structures différentes — confusion conceptuelle à clarifier. |
| **Double identité enseignant** | Un enseignant a un `users.id` ET un `professeurs.id`. La jointure `professeurs.user_id → users.id` est parfois oubliée lors de la création. |

---

## 5. Arborescence fonctionnelle

```
SCOLARIS
│
├── Accès Public
│   ├── Connexion                    /login
│   ├── Inscription                  /register  ⚠️ (devrait être admin-only)
│   ├── Mot de passe oublié          /forgot-password
│   └── Réinitialisation             /reset-password/{token}
│
├── Administration
│   ├── Tableau de bord              /dashboard
│   ├── Utilisateurs                 /utilisateurs
│   │   ├── Liste + filtres
│   │   ├── Créer / Modifier / Supprimer
│   │   └── Activer / Désactiver
│   ├── Annonces                     /annonces
│   │   ├── Liste + filtres audience
│   │   ├── Créer / Modifier / Supprimer
│   └── Notifications admin          /admin/notifications
│       ├── Historique envois
│       ├── Test d'envoi
│       └── Purge
│
├── Académique
│   ├── Élèves                       /eleves
│   │   ├── Liste + filtres + pagination
│   │   ├── Fiche détaillée
│   │   ├── Créer / Modifier / Supprimer
│   │   ├── Import CSV
│   │   └── Export PDF / Excel
│   ├── Classes                      /classes
│   │   ├── Liste / Créer / Modifier / Supprimer
│   │   ├── Détail (élèves + enseignants)
│   │   └── Affecter / Retirer élève ou enseignant
│   ├── Matières                     /matieres
│   │   ├── Liste / Créer / Modifier / Supprimer
│   │   └── Détail + responsable
│   ├── Enseignants                  /professeurs
│   │   ├── Liste + filtres
│   │   ├── Fiche (infos + enseignements + historique)
│   │   ├── Créer / Modifier / Supprimer
│   │   └── Affecter / Retirer enseignement
│   ├── Notes & Évaluations          /notes
│   │   ├── Vue d'ensemble
│   │   ├── Contrôles                /notes/controles
│   │   │   ├── Liste + filtres
│   │   │   ├── Créer / Modifier / Supprimer
│   │   │   └── Saisie notes         /notes/saisie/{id}
│   │   └── Moyennes                 /notes/moyennes
│   └── Bulletins                    /bulletins
│       ├── Liste / Par élève / Par classe
│       ├── Classement
│       └── Impression PDF
│
├── Vie Scolaire
│   ├── Absences                     /absences
│   │   ├── Tableau de bord
│   │   ├── Pointage journalier      /absences/pointage
│   │   ├── Liste complète           /absences/liste
│   │   ├── Créer manuellement       /absences/create
│   │   ├── Détail + justification   /absences/{id}
│   │   ├── Statistiques             /absences/stats
│   │   └── Alertes                  /absences/alertes
│   └── Emploi du temps              /emplois-du-temps
│       ├── Vue hebdomadaire / mensuelle
│       ├── Créer / Modifier / Supprimer / Impression
│       ├── Salles                   /salles
│       └── Créneaux                 /creneaux
│
├── Finance
│   ├── Comptabilité                 /comptabilite
│   │   ├── Tableau de bord
│   │   ├── Types de frais / Affectation
│   │   ├── Impayés / Caisse
│   │   └── Rapport (PDF + Excel)
│   ├── Paiements                    /paiements
│   │   ├── Liste / Encaisser / Détail / Reçu PDF
│   └── Dépenses                     /depenses
│       └── Liste / Saisir / Modifier / Supprimer
│
├── Reporting                        /rapports
│   ├── Dashboard analytique
│   ├── Scolaire / Financier / Présences / Réussite
│   └── Export PDF / Excel
│
├── Espaces dédiés
│   ├── Espace Parent                /parent/*
│   │   ├── Dashboard / Notes / Bulletin
│   │   ├── Absences + Justifier
│   │   └── Paiements
│   └── Espace Élève                 /eleve/*
│       ├── Dashboard / Notes / Bulletin
│       ├── Emploi du temps
│       └── Profil
│
├── Notifications                    /notifications
│   ├── Centre de notifications
│   ├── Préférences canal/événement
│   └── Marquer lu / tout lu
│
└── PWA & API                        /api/*
    ├── Élèves JSON
    ├── Frais-élève JSON
    ├── Notifications JSON
    └── Push VAPID (subscribe/send)
```

---

## 6. Flux de navigation

### Admin / Directeur
```
/login → /dashboard → /eleves, /classes, /professeurs, /notes/controles
       → /notes/saisie/{id}, /bulletins/print/{id}
       → /absences/pointage, /absences/alertes
       → /comptabilite, /paiements, /depenses
       → /emplois-du-temps, /rapports, /annonces
       → /utilisateurs, /notifications
```

### Enseignant
```
/login → /dashboard (mes cours + mes classes)
       → /notes/controles/create, /notes/saisie/{id}
       → /absences/create, /eleves, /bulletins
```

### Secrétaire
```
/login → /dashboard → /eleves (inscrire), /classes, /absences/pointage
```

### Comptable
```
/login → /dashboard → /comptabilite, /paiements, /depenses, /rapports
```

### Parent
```
/login → /parent/dashboard → /parent/notes, /parent/bulletin
       → /parent/absences (+ justifier), /parent/paiements
```

### Élève
```
/login → /eleve/dashboard → /eleve/notes, /eleve/bulletin
       → /eleve/emploi-du-temps, /eleve/profil
```

### Ruptures de navigation identifiées

1. **`/register` sans garde** — accessible sans être connecté, non approprié pour un ERP scolaire
2. **Pas de breadcrumb global** — l'utilisateur ne sait pas toujours où il se trouve
3. **Espace parent sans sélecteur d'enfant** — si un parent a plusieurs enfants, pas de sélecteur visible
4. **Deux chemins vers les rapports financiers** — `/rapports/financier` ET `/comptabilite/rapport` (comportements différents)
5. **`/bulletins/print/{id}`** — ouvre un layout print séparé sans retour à l'application

---

## 7. Architecture des données

### Inventaire complet des 27 tables

| Table | Module | Clés étrangères |
|-------|--------|-----------------|
| `users` | Auth / Utilisateurs | — |
| `password_resets` | Auth | — |
| `permissions` | Auth | — |
| `role_permissions` | Auth | → permissions.id |
| `eleves` | Élèves | → classes.id, → users.id (parent_id) |
| `classes` | Classes | — |
| `matieres` | Matières | → professeurs.id (responsable_id) |
| `professeurs` | Enseignants | → users.id |
| `enseignements` | Enseignants | → professeurs.id, → matieres.id, → classes.id |
| `periodes` | Académique | — |
| `controles` | Académique | → matieres, → classes, → periodes |
| `notes` | Académique | → eleves, → controles |
| `moyennes_matieres` | Académique (cache) | → eleves, → matieres, → classes, → periodes |
| `moyennes_generales` | Académique (cache) | → eleves, → classes, → periodes |
| `absences` | Absences | → eleves, → classes, → users (signale_par) |
| `justifications` | Absences | → absences, → users |
| `frais_types` | Comptabilité | — |
| `frais_eleves` | Comptabilité | → eleves, → frais_types |
| `paiements` | Comptabilité | → eleves, → frais_eleves, → users (encaisse_par) |
| `depenses_categories` | Comptabilité | — |
| `depenses` | Comptabilité | → depenses_categories, → users (saisi_par) |
| `salles` | Emploi du temps | — |
| `creneaux` | Emploi du temps | — |
| `emplois_du_temps` | Emploi du temps | → classes, → matieres, → professeurs, → salles, → creneaux |
| `notifications` | Notifications | → users (pas de FK déclarée) |
| `annonces` | Annonces | → users (pas de FK déclarée) |
| `notification_preferences` | Notifications | → users (pas de FK déclarée) |
| `notification_logs` | Notifications | → users (pas de FK déclarée) |
| `push_subscriptions` | PWA | → users (pas de FK déclarée) |

### Tables manquantes identifiées

| Table absente | Impact |
|---------------|--------|
| `annees_scolaires` | L'année est un VARCHAR répété dans 6+ tables — basculements d'année risqués |
| `audit_logs` | Aucune traçabilité des actions CRUD (critique pour un ERP éducatif) |

### FK non déclarées (risque d'intégrité)

- `notifications.user_id`
- `annonces.publie_par`
- `notification_preferences.user_id`
- `notification_logs.user_id`
- `push_subscriptions.user_id`

### Schéma logique des dépendances

```
users ──────────────────────────────────────────────────────┐
  │                                                         │
  ├──→ professeurs.user_id                                  │
  │      ├──→ enseignements → matieres, classes             │
  │      └──→ emplois_du_temps                              │
  │                                                         │
  ├──→ eleves.parent_id                                     │
  │      ├──→ classes.id (classe_id)                        │
  │      ├──→ notes → controles → matieres, classes, periodes│
  │      ├──→ moyennes_matieres / moyennes_generales        │
  │      ├──→ absences → justifications                     │
  │      └──→ frais_eleves → paiements                      │
  │                                                         │
  └──→ notifications / notification_preferences / logs ─────┘

  matieres.responsable_id → professeurs.id
  emplois_du_temps.salle_id → salles.id
  emplois_du_temps.creneau_id → creneaux.id
```

---

## 8. Architecture des permissions

### Les 7 rôles

`admin` | `directeur` | `secretaire` | `comptable` | `enseignant` | `parent` | `eleve`

### Matrice des permissions (table `permissions` + assignations)

| Permission | admin | directeur | secrétaire | comptable | enseignant | parent | élève |
|-----------|:-----:|:---------:|:----------:|:---------:|:----------:|:------:|:-----:|
| eleves.view | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| eleves.create | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| eleves.edit | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| eleves.delete | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| enseignants.view | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ |
| enseignants.create/edit/delete | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| classes.view | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| classes.create/edit/delete | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| notes.view | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| notes.create/edit | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| notes.delete | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| notes.view_own | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ✅ |
| absences.view | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| absences.create/edit | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| absences.view_own | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ✅ |
| users.view/create/edit | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| users.delete | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| rapports.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| matieres.view | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| matieres.create/edit/delete | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

### Permissions non codifiées en DB (gérées par rôle direct)

- `annonces.*` — vérification `$role` dans AnnonceController
- `comptabilite.*` — vérification `$role` dans ComptabiliteController
- `bulletins.*` — vérification `$role` dans BulletinController
- `emplois_du_temps.*` — vérification `$role` dans EmploiDuTempsController
- `notifications.admin` — admin uniquement, vérification directe

---

## 9. État réel de l'application

| Module | État | Notes |
|--------|:----:|-------|
| Authentification | ✅ Terminé | `/register` à protéger |
| Tableau de bord | ✅ Fonctionnel | Vues par rôle + graphiques Chart.js |
| Élèves | ✅ Terminé | CRUD, import, export, photo |
| Classes | ✅ Terminé | CRUD, affectations complètes |
| Matières | ✅ Terminé | CRUD, responsable, volume horaire |
| Enseignants | ✅ Terminé | CRUD, photo, grade, enseignements |
| Notes / Académique | ✅ Fonctionnel | Contrôles, saisie, moyennes auto |
| Bulletins | ✅ Fonctionnel | PDF, classement, par classe/élève |
| Absences | ✅ Fonctionnel | Pointage, stats, alertes, justifications |
| Comptabilité | ✅ Fonctionnel | Frais, paiements, dépenses, caisse, rapport |
| Emploi du temps | ✅ Fonctionnel | Hebdo/mensuel, conflits, salles, créneaux |
| Rapports | ✅ Fonctionnel | 4 rapports + export PDF/Excel |
| Annonces | ✅ Terminé | CRUD, audiences, modal, filtres |
| Notifications (interne) | ✅ Fonctionnel | Centre, préférences, API unread count |
| Notifications (email/SMS) | ⚙️ En développement | Structure présente, providers non configurés |
| Push VAPID | ⚙️ En développement | Subscribe/unsubscribe OK, déclencheurs auto manquants |
| Espace Parent | ✅ Fonctionnel | 5 pages opérationnelles |
| Espace Élève | ✅ Fonctionnel | 5 pages opérationnelles |
| Utilisateurs | ✅ Terminé | CRUD, toggle actif |
| API REST | ⚙️ En développement | 3 entités seulement (élèves, frais, notifications) |
| PWA / Offline | ⚙️ En développement | SW présent, cache offline non documenté |
| Système permissions DB | ⚠️ À refactoriser | Incomplet (annonces, comptabilité, bulletins manquants) |
| Audit log | ❌ Absent | Aucune traçabilité CRUD |
| Référentiel année scolaire | ❌ Absent | VARCHAR répété dans 6+ tables |
| Multi-établissement | ❌ Absent | Architecture mono-tenant |

---

## 10. Modules pouvant devenir indépendants

| Module | Indépendance | Raison |
|--------|:------------:|--------|
| Finance (Comptabilité + Paiements + Dépenses) | ✅ Oui | 3 contrôleurs + 5 tables autonomes. Dépend uniquement d'`eleves` et `users` en entrée. |
| Emploi du temps | ✅ Oui | Tables propres (`salles`, `creneaux`, `emplois_du_temps`). Dépend de `classes`, `matieres`, `professeurs` en lecture seule. |
| Rapports | ✅ Oui | Purement en lecture. Peut être externalisé comme service de reporting. |
| Notifications | ✅ Oui | Tables dédiées. Peut recevoir des événements via hooks. |
| Annonces | ✅ Oui | Table `annonces` autonome. Dépend seulement de `users.id` pour l'auteur. |
| PWA / API | ✅ Oui | Couche de présentation JSON sans logique métier propre. |
| Espace Parent | ⚠️ Partiel | Consomme 4 modules (notes, absences, bulletins, paiements). Extractible via API. |
| Espace Élève | ⚠️ Partiel | Idem Espace Parent. |
| Absences | ⚠️ Partiel | Couplé à `eleves` et `classes`. Extractible avec contrat d'interface. |
| Authentification | ⚠️ Partiel | Peut devenir SSO/OAuth mais nécessite refactoring de toutes les dépendances à `Session::getUser()`. |
| Académique (Notes + Bulletins) | ❌ Difficile | Trop couplé : `controles → classes → matieres → enseignements → professeurs → eleves → periodes`. |

---

## 11. Dépendances critiques

```
users
  └── [TOUT LE SYSTÈME] (chaque contrôleur vérifie requireAuth())

eleves (nœud central de 5 modules)
  ├── classes         (classe_id)
  ├── users           (parent_id)
  ├── notes → controles → matieres, classes, periodes
  ├── moyennes_matieres / moyennes_generales
  ├── absences → justifications
  └── frais_eleves → paiements

professeurs
  ├── users           (user_id)
  ├── enseignements → matieres, classes
  └── emplois_du_temps

matieres
  ├── enseignements
  ├── controles (suppression cascade → perte notes)
  └── emplois_du_temps

periodes
  ├── controles
  ├── moyennes_matieres
  └── moyennes_generales (suppression → perte toutes les notes associées)
```

### Dépendances par ordre d'impact (suppression)

| Rang | Table | Impact |
|------|-------|--------|
| 1 | `users` | Cascade sur toutes les tables — suppression impossible sans précaution |
| 2 | `eleves` | Nœud central de 5 modules |
| 3 | `classes` | Structurant pour absences, notes, emploi du temps |
| 4 | `periodes` | Suppression → perte des notes et moyennes par CASCADE |
| 5 | `matieres` | Suppression cascade sur controles → notes (cascade totale) |

---

## 12. Carte finale du projet

| Module | Fonctionnalités principales | État | Priorité | Indépendant |
|--------|-----------------------------|:----:|:--------:|:-----------:|
| Authentification | Login, profil, reset MdP, permissions | ✅ | — | ⚠️ |
| Tableau de bord | KPI, graphiques, vues par rôle | ✅ | — | ❌ |
| Élèves | CRUD, import CSV, export PDF/Excel, photo | ✅ | — | ❌ |
| Classes | CRUD, affectations élèves/enseignants | ✅ | — | ❌ |
| Matières | CRUD, coefficient, volume H, responsable | ✅ | — | ⚠️ |
| Enseignants | CRUD, photo, grade, enseignements | ✅ | — | ❌ |
| Notes / Évaluations | Contrôles, saisie, moyennes auto | ✅ | Moyen | ❌ |
| Bulletins | Génération PDF, classement | ✅ | Moyen | ❌ |
| Absences | Pointage, stats, justifications, alertes | ✅ | Moyen | ⚠️ |
| Comptabilité | Frais, paiements, dépenses, caisse | ✅ | Moyen | ✅ |
| Emploi du temps | Planning, conflits, salles, créneaux | ✅ | Faible | ✅ |
| Rapports | 4 rapports analytiques, export PDF/Excel | ✅ | Faible | ✅ |
| Annonces | CRUD, audiences, filtres | ✅ | — | ✅ |
| Notifications (interne) | Centre, préférences, API | ✅ | Faible | ✅ |
| **Notifications (email/SMS)** | Config provider, envoi auto | ⚙️ | **Haut** | ✅ |
| **Push VAPID** | Subscribe, déclencheurs auto | ⚙️ | **Haut** | ✅ |
| Espace Parent | Notes, bulletin, absences, paiements | ✅ | Moyen | ⚠️ |
| Espace Élève | Notes, bulletin, EDT, profil | ✅ | Moyen | ⚠️ |
| Utilisateurs | CRUD, toggle actif | ✅ | — | ⚠️ |
| **Système permissions DB** | Codification complète en table | ⚠️ | **Haut** | ⚠️ |
| **API REST** | Endpoints JSON complets | ⚙️ | **Haut** | ✅ |
| PWA / Offline | Cache, sync offline | ⚙️ | Moyen | ✅ |
| **Audit log** | Traçabilité actions CRUD | ❌ | Moyen | ✅ |
| **Référentiel année scolaire** | Table centrale annees_scolaires | ❌ | **Haut** | ✅ |
| Multi-établissement | Isolation des données par tenant | ❌ | Long terme | — |

---

## Résumé exécutif

SCOLARIS est fonctionnel et couvre l'essentiel d'un ERP scolaire mono-établissement. Les 22 modules principaux sont présents et opérationnels à **75–90 %**.

### 4 axes de fragilité prioritaires avant modularisation

1. **Système de permissions incomplet en DB** — plusieurs modules contournent la table `permissions` avec des vérifications de rôle codées en dur
2. **Absence d'un référentiel `annees_scolaires`** — l'année scolaire est un VARCHAR répété dans 6+ tables, les basculements d'année sont risqués
3. **Lien parent ↔ élève non garanti applicativement** — `eleves.parent_id` existe en DB mais le filtrage côté application doit être systématique pour éviter les fuites de données entre familles
4. **Aucun audit log** — aucune traçabilité des modifications CRUD (critique pour un ERP éducatif)
