# SCOLARIS V2 — Blueprint Officiel d'Architecture Fonctionnelle

**Version :** 2.0
**Date :** 29 juin 2026
**Statut :** Document de référence officiel

---

## Chiffres cibles V2

| Élément | V1 actuel | V2 cible |
|---------|-----------|----------|
| Modules | 22 (éparpillés) | 12 (structurés) |
| Rôles | 7 | 7 |
| Espaces utilisateurs | 2 (parent, élève) | 3 portails complets |
| Tables SQL | 27 | ~35 (+ annees_scolaires, audit_logs, messages...) |
| Permissions codifiées en DB | 28 (incomplètes) | 100 % (toutes) |
| Modules activables | 0 | 10 |

---

# 1. Vision générale

## 1.1 Mission

SCOLARIS est une plateforme de gestion scolaire intégrée destinée aux établissements d'enseignement. Sa mission est de centraliser, d'automatiser et de sécuriser l'ensemble des processus administratifs, pédagogiques et financiers d'un établissement scolaire, tout en offrant des espaces dédiés et personnalisés à chaque acteur de la communauté éducative.

## 1.2 Objectif

Remplacer les processus manuels (registres papier, tableurs, communications informelles) par un système cohérent, traçable et accessible en tout lieu. Permettre à une direction d'établissement de piloter l'ensemble de son école depuis un seul tableau de bord. Permettre aux enseignants, parents et élèves de consulter et d'agir en temps réel sur les données qui les concernent.

## 1.3 Les utilisateurs de SCOLARIS

| Profil | Rôle dans l'établissement | Besoin principal |
|--------|--------------------------|------------------|
| **Administrateur système** | Responsable informatique | Configuration, accès total, supervision technique |
| **Directeur** | Chef d'établissement | Pilotage global, validation, rapports de direction |
| **Secrétaire** | Administration scolaire | Inscriptions, absences, communications quotidiennes |
| **Comptable** | Gestion financière | Encaissements, décaissements, caisse, rapports |
| **Enseignant** | Corps pédagogique | Notes, absences, emploi du temps, bulletins |
| **Parent** | Représentant légal de l'élève | Suivi de l'enfant, paiements, justifications, communication |
| **Élève** | Apprenant | Consultation de ses résultats, emploi du temps, bulletin |

## 1.4 Philosophie du logiciel

**Modularité.** SCOLARIS est construit autour d'un Core non désactivable et de modules optionnels. Un établissement qui n'utilise pas la bibliothèque ne la voit pas.

**Hiérarchie des données.** Tout part de l'Année Scolaire. Toutes les données académiques, financières et de présence sont rattachées à une année scolaire. C'est le pivot central du système.

**Rôle unique, espace unique.** Chaque utilisateur a un rôle précis et accède à un espace taillé pour ce rôle. Il ne voit que ce qui le concerne.

**Traçabilité totale.** Toute action significative sur les données est enregistrée. Qui a modifié quoi, quand. C'est non négociable dans un ERP éducatif.

**Données officielles.** SCOLARIS produit des documents ayant valeur officielle (bulletins, reçus, registres). La qualité et l'exactitude des données priment sur la performance.

## 1.5 Évolution future

| Horizon | Périmètre |
|---------|-----------|
| **H1 — Consolidation** | Compléter les modules existants, unifier les permissions, introduire l'Audit Log et le référentiel d'années scolaires |
| **H2 — Enrichissement** | Documents, Inventaire, Bibliothèque, Messagerie, Portails complets, Notifications multi-canal |
| **H3 — Réseau** | Multi-établissement, partage de ressources, dashboard de supervision réseau |

---

# 2. Modules définitifs

## Liste des 12 modules

| # | Module | Désactivable | Horizon |
|---|--------|:------------:|:-------:|
| 0 | **CORE** | ❌ Non | V2 |
| 1 | **Scolarité** | ❌ Non | V2 |
| 2 | **Académique** | ⚠️ Avec précautions | V2 |
| 3 | **Vie Scolaire** | ✅ Oui | V2 |
| 4 | **Ressources Humaines** | ✅ Oui | V2 |
| 5 | **Finance** | ✅ Oui | V2 |
| 6 | **Documents** | ✅ Oui | V2 |
| 7 | **Bibliothèque** | ✅ Oui | V2 |
| 8 | **Inventaire** | ✅ Oui | V2 |
| 9 | **Communication** | ✅ Oui | V2 |
| 10 | **Rapports & Analytique** | ✅ Oui | V2 |
| 11 | **Portails** | ✅ Par portail | V2 |
| 12 | **Réseau** | ✅ Oui | H3 |

---

## MODULE 0 — CORE

**Objectif :** Fournir les fondations non désactivables sur lesquelles repose tout SCOLARIS.

**Responsabilités :** Authentification et sessions · Gestion des comptes utilisateurs · Système de rôles et permissions · Référentiel des années scolaires · Configuration générale de l'établissement · Tableau de bord central · Journal d'audit · Service de notifications (infrastructure) · PWA

**Dépendances :** Aucune.

**Limites :** Le Core ne contient aucune logique métier scolaire. Il ne sait pas ce qu'est un élève ou une note.

---

## MODULE 1 — SCOLARITÉ

**Objectif :** Gérer le référentiel de base : qui sont les élèves, comment sont organisées les classes, quelles matières sont enseignées.

**Responsabilités :** Gestion complète des élèves · Gestion des classes et niveaux · Gestion des matières et coefficients · Inscriptions et réinscriptions annuelles · Liaison élève ↔ parent · Registres officiels d'effectifs

**Dépendances :** CORE (années scolaires, utilisateurs pour le lien parent)

**Limites :** La Scolarité ne calcule pas de notes et ne gère pas les paiements. Elle fournit les données de référence à tous les autres modules. C'est un module fournisseur.

**Justification de fusion :** Les anciens modules Élèves, Classes et Matières étaient séparés sans frontière fonctionnelle réelle. Même propriétaire (secrétaire/direction), même finalité (référentiel de base), même cycle de vie (liés à l'année scolaire).

---

## MODULE 2 — ACADÉMIQUE

**Objectif :** Gérer tout le cycle d'évaluation : de la création d'un contrôle jusqu'à la production du bulletin officiel.

**Responsabilités :** Gestion des périodes et trimestres · Contrôles et évaluations · Saisie des notes · Calcul automatique des moyennes · Classement des élèves · Production des bulletins · Gestion des mentions

**Dépendances :** CORE, Scolarité (élèves, classes, matières)

**Limites :** L'Académique ne gère pas les absences (Vie Scolaire), ne produit pas les impressions officielles (Documents), ne gère pas l'emploi du temps (Vie Scolaire).

---

## MODULE 3 — VIE SCOLAIRE

**Objectif :** Gérer le quotidien opérationnel : présences, emploi du temps, organisation des espaces.

**Responsabilités :** Pointage journalier · Justifications d'absences · Alertes d'absentéisme · Emploi du temps hebdomadaire et mensuel · Gestion des salles et créneaux horaires · Détection des conflits d'horaires

**Dépendances :** CORE, Scolarité (élèves, classes, matières), RH (enseignants pour l'EDT)

**Limites :** Ne calcule pas de notes, ne gère pas les finances, ne produit pas de documents officiels.

---

## MODULE 4 — RESSOURCES HUMAINES

**Objectif :** Gérer le personnel de l'établissement, en priorité les enseignants.

**Responsabilités :** Fiches individuelles du personnel enseignant · Grades et spécialités · Affectations aux classes et matières · Suivi des enseignements par année scolaire

**Dépendances :** CORE (utilisateurs)

**Limites :** Centré sur les enseignants en V2. Ne gère pas la paie (hors périmètre), ni les congés (futur H2). Fournit les données enseignant à Vie Scolaire (EDT) et Académique.

**Justification de séparation :** Un enseignant est d'abord un employé de l'établissement. Sa gestion relève des RH, pas de l'Académique.

---

## MODULE 5 — FINANCE

**Objectif :** Gérer l'ensemble des flux financiers de l'établissement.

**Responsabilités :** Définition des types de frais · Affectation des frais aux élèves · Enregistrement des encaissements · Enregistrement des décaissements · Suivi de la caisse · Gestion des impayés · Rapports financiers · Production des reçus

**Dépendances :** CORE, Scolarité (élèves, classes pour filtres)

**Limites :** Ne gère pas la paie du personnel. Ne produit pas les états financiers légaux.

---

## MODULE 6 — DOCUMENTS

**Objectif :** Centraliser la production, le stockage et la distribution de tous les documents officiels.

**Responsabilités :** Documents administratifs (attestations, certificats, convocations) · Documents pédagogiques (bulletins imprimables, relevés) · Registres officiels · Génération automatique depuis les données existantes · Partage de documents · Archivage

**Dépendances :** CORE, Scolarité, Académique, Finance, Vie Scolaire

**Limites :** Module de présentation et d'archivage — ne crée pas de données, ne contient pas de logique métier.

**Justification de création :** Les bulletins (Académique), reçus (Finance), rapports (Reporting) et registres étaient éparpillés dans différents modules. Documents centralise la production officielle.

---

## MODULE 7 — BIBLIOTHÈQUE

**Objectif :** Gérer les ressources documentaires et pédagogiques de l'établissement.

**Responsabilités :** Catalogue de ressources · Gestion des emprunts et retours · Ressources numériques partagées · Statistiques d'utilisation

**Dépendances :** CORE, Scolarité (élèves, enseignants comme emprunteurs)

**Limites :** Module optionnel. Désactivable sans impact sur les autres modules.

---

## MODULE 8 — INVENTAIRE

**Objectif :** Gérer les biens matériels de l'établissement.

**Responsabilités :** Catalogue des équipements et mobilier · Suivi de l'état et de la localisation · Affectation des ressources · Alertes de maintenance · Suivi des consommables et stocks

**Dépendances :** CORE, Vie Scolaire (salles pour localisation)

**Limites :** Module optionnel. Ne gère pas les finances (les achats sont dans Finance/Dépenses).

---

## MODULE 9 — COMMUNICATION

**Objectif :** Gérer tous les canaux de communication de la communauté scolaire.

**Responsabilités :** Annonces à audience ciblée · Messagerie interne · Notifications multi-canal (interne, email, SMS, push) · Préférences de notification par utilisateur · Communication inter-établissements (H3)

**Dépendances :** CORE (utilisateurs, rôles)

**Limites :** Ne contient pas de données scolaires. Reçoit des événements des autres modules et les diffuse selon les préférences utilisateur.

---

## MODULE 10 — RAPPORTS & ANALYTIQUE

**Objectif :** Fournir une vision agrégée et décisionnelle de toutes les données de l'établissement.

**Responsabilités :** Dashboard analytique · Rapports scolaires · Rapports financiers · Rapports de présence · Rapports RH · Export PDF et Excel

**Dépendances :** Tous les modules (lecture seule)

**Limites :** Module en lecture seule exclusivement. Toute modification passe par le module propriétaire des données.

---

## MODULE 11 — PORTAILS

**Objectif :** Fournir des interfaces dédiées et sécurisées aux acteurs extérieurs à l'administration.

**Sous-portails :**
- **Portail Parent** : consultation de l'enfant, paiements, justifications, messagerie, annonces
- **Portail Élève** : mes résultats, mon emploi du temps, mon bulletin, mes ressources, mon profil
- **Portail Enseignant** : mes classes, mes notes, mes absences, mon emploi du temps, mes documents

**Dépendances :** CORE, Scolarité, Académique, Vie Scolaire, Finance, Communication

**Limites :** Interfaces de consultation et d'action limitée (justification, paiement, profil). Ne créent pas de données administratives.

---

## MODULE 12 — RÉSEAU *(Horizon 3)*

**Objectif :** Permettre la gestion et la supervision d'un réseau d'établissements.

**Responsabilités :** Dashboard de suivi multi-établissements · Communication inter-établissements · Partage de ressources · Benchmarking · Administration centralisée d'un groupe scolaire

**Dépendances :** CORE (multi-tenant)

---

# 3. Sous-modules détaillés

### CORE
- Authentification (login, logout, session, CSRF)
- Réinitialisation de mot de passe
- Gestion des comptes utilisateurs (CRUD, activation)
- Système de rôles
- Système de permissions granulaires (100 % codifiées en DB)
- Référentiel des années scolaires (CRUD, année active, basculement)
- Configuration établissement (nom, logo, fuseau horaire, coordonnées)
- Tableau de bord central (KPI injectés par les modules selon le rôle)
- Journal d'audit (Audit Log — écriture seule pour les utilisateurs)
- Service de notifications (infrastructure d'événements)
- Profil utilisateur
- PWA (manifest, Service Worker, cache offline)

### MODULE 1 — SCOLARITÉ
- **Élèves** : fiche individuelle, photo, contacts d'urgence, historique scolaire, statut
- **Inscriptions** : inscription initiale, réinscription annuelle, transfert, radiation
- **Famille** : liaison parent-enfant, contacts familiaux, représentants légaux
- **Classes** : création, niveaux, capacité, affectation des élèves
- **Matières** : référentiel, coefficients, volume horaire, responsable pédagogique
- **Configuration académique** : paramètres de calcul des moyennes, seuils de réussite
- **Registres** : registre d'inscription, effectifs officiels

### MODULE 2 — ACADÉMIQUE
- **Périodes** : création des trimestres/semestres, dates, activation
- **Contrôles** : création, types (contrôle, devoir, examen, TP, oral), coefficient, date
- **Saisie des notes** : grille par contrôle, marquage absent, appréciation
- **Moyennes** : calcul automatique par matière, calcul de la moyenne générale
- **Classement** : rang par classe et par période
- **Bulletins** : génération, visualisation, impression
- **Mentions** : seuils configurables (Très Bien, Bien, Assez Bien, Passable, Insuffisant)
- **Archives** : historique des résultats par année scolaire

### MODULE 3 — VIE SCOLAIRE
- **Emploi du temps** : grille hebdomadaire, grille mensuelle, impression
- **Salles** : CRUD, capacité, type, bâtiment
- **Créneaux horaires** : CRUD, heure début/fin, type (cours, pause, prière)
- **Affectation EDT** : lier classe × matière × enseignant × salle × créneau × jour
- **Détection de conflits** : vérification temps réel des doubles affectations
- **Pointage** : grille de présence journalière par classe et session
- **Absences** : enregistrement, types (absence, retard), durée
- **Justifications** : soumission parent, validation administration, pièces jointes
- **Statistiques d'absences** : par élève, par classe, par période
- **Alertes d'absentéisme** : seuils configurables (surveiller 3+, sérieux 5+, critique 10+)

### MODULE 4 — RESSOURCES HUMAINES
- **Fiches enseignants** : informations personnelles, photo, coordonnées
- **Grades et spécialités** : référentiel des grades, domaines d'enseignement
- **Affectations** : quel enseignant enseigne quelle matière dans quelle classe
- **Historique** : évolution des affectations par année scolaire
- **Tableau de bord enseignant** : charge horaire, nombre d'élèves, classes

### MODULE 5 — FINANCE
- **Types de frais** : référentiel (inscription, mensualité, cantine, transport, sport), montant par défaut, périodicité
- **Affectation des frais** : appliquer un type de frais à un ou plusieurs élèves
- **Encaissements** : enregistrement des paiements, modes (espèces, chèque, virement, carte), référence
- **Reçus** : génération et impression du reçu de paiement
- **Décaissements** : saisie des dépenses, catégories, modes de paiement
- **Catégories de dépenses** : référentiel configurable (salaires, fournitures, infrastructure...)
- **Caisse** : état quotidien et mensuel des entrées/sorties
- **Impayés** : liste des élèves avec solde en attente, filtres par classe et type
- **Rapports financiers** : recettes, dépenses, solde, taux de recouvrement par type de frais

### MODULE 6 — DOCUMENTS
- **Modèles de documents** : templates configurables (attestation, certificat, convocation)
- **Documents administratifs** : attestation de scolarité, certificat de présence, convocation
- **Documents pédagogiques** : bulletin officiel imprimable, relevé de notes, attestation de réussite
- **Registres** : registre d'inscription officiel, registre des absences, registre des paiements
- **Génération automatique** : production à partir des données existantes (un clic → document)
- **Partage** : envoi d'un document à un parent ou un enseignant
- **Archivage** : historique des documents produits avec date et auteur

### MODULE 7 — BIBLIOTHÈQUE
- **Catalogue** : livres, manuels, ressources, auteur, classification, exemplaires
- **Emprunts** : enregistrement, date retour prévue, relances
- **Retours** : réception, état
- **Ressources numériques** : fichiers PDF, liens, partage par classe ou matière
- **Statistiques** : ressources les plus empruntées, taux d'utilisation

### MODULE 8 — INVENTAIRE
- **Catalogue des biens** : équipements, mobilier, matériel informatique
- **Localisation** : affectation par salle ou par personne
- **État** : bon état, à réparer, hors service
- **Mouvements** : entrées, sorties, transferts entre locaux
- **Consommables** : stocks, seuils d'alerte, historique

### MODULE 9 — COMMUNICATION
- **Annonces** : création, audiences ciblées, badge "Nouveau", filtres
- **Messagerie** : conversations directes (1-à-1), conversations de groupe, pièces jointes
- **Notifications internes** : centre de notifications, lecture, marquage, historique
- **Notifications email** : configuration SMTP, templates, envoi automatique sur événement
- **Notifications SMS** : configuration provider, templates, envoi automatique sur événement
- **Notifications push** : VAPID, abonnement/désabonnement, envoi sur événement
- **Préférences** : par utilisateur, par type d'événement, par canal
- **Administration** : historique des envois, test, purge

### MODULE 10 — RAPPORTS & ANALYTIQUE
- **Dashboard analytique** : KPI globaux, graphiques tendances
- **Rapport scolaire** : effectifs par classe/niveau, distribution des notes, taux de réussite
- **Rapport financier** : recettes, dépenses, solde, recouvrement par type de frais
- **Rapport présences** : taux d'absentéisme par classe, tendances hebdomadaires
- **Rapport réussite** : mentions, classements, progression entre périodes
- **Rapport RH** : charge horaire des enseignants, taux d'occupation des salles
- **Export** : tous les rapports en PDF et Excel

### MODULE 11 — PORTAILS

#### Portail Parent
- Tableau de bord famille (résumé enfant(s), sélecteur si plusieurs enfants)
- Résultats scolaires de l'enfant
- Bulletin de l'enfant (consultation + téléchargement)
- Absences et justifications
- Paiements et solde de compte
- Documents reçus (bulletins, attestations)
- Messagerie avec l'administration
- Annonces

#### Portail Élève
- Tableau de bord personnel
- Mes notes et moyennes
- Mon bulletin
- Mon emploi du temps
- Mes absences
- Mes ressources (bibliothèque, documents partagés)
- Mon profil
- Annonces

#### Portail Enseignant
- Tableau de bord pédagogique
- Mes classes et mes élèves
- Saisie des notes (accès direct)
- Pointage des absences (accès direct)
- Mon emploi du temps
- Mes documents pédagogiques
- Messagerie avec l'administration et les parents
- Annonces

### MODULE 12 — RÉSEAU *(Horizon 3)*
- Gestion des établissements du réseau
- Dashboard de supervision multi-écoles
- Communication inter-établissements
- Partage de ressources documentaires
- Benchmarking des indicateurs clés
- Administration centralisée

---

# 4. Répartition des fonctionnalités existantes

| Fonctionnalité actuelle | Module V2 | Sous-module |
|-------------------------|-----------|-------------|
| Login / Logout | CORE | Authentification |
| Inscription compte | CORE | Gestion des comptes |
| Mot de passe oublié / Reset | CORE | Authentification |
| Profil utilisateur | CORE | Profil utilisateur |
| Gestion des utilisateurs (CRUD) | CORE | Gestion des comptes |
| Activation / Désactivation utilisateur | CORE | Gestion des comptes |
| Tableau de bord admin/directeur | CORE | Tableau de bord central |
| Tableau de bord secrétaire/comptable | CORE | Tableau de bord central |
| Tableau de bord enseignant | MODULE 11 | Portail Enseignant |
| Tableau de bord parent | MODULE 11 | Portail Parent |
| Tableau de bord élève | MODULE 11 | Portail Élève |
| Liste élèves + filtres + pagination | MODULE 1 | Élèves |
| Fiche détaillée élève | MODULE 1 | Élèves |
| Création / Modification / Suppression élève | MODULE 1 | Élèves |
| Import élèves CSV | MODULE 1 | Inscriptions |
| Export liste élèves PDF / Excel | MODULE 1 | Élèves |
| Upload photo élève | MODULE 1 | Élèves |
| Liaison parent ↔ élève | MODULE 1 | Famille |
| Gestion des classes (CRUD) | MODULE 1 | Classes |
| Affecter / Retirer élève d'une classe | MODULE 1 | Inscriptions |
| Affecter / Retirer enseignant d'une classe | MODULE 4 | Affectations |
| Gestion des matières (CRUD) | MODULE 1 | Matières |
| Responsable d'une matière | MODULE 4 | Affectations |
| Gestion des enseignants (CRUD) | MODULE 4 | Fiches enseignants |
| Grades et spécialités enseignants | MODULE 4 | Grades et spécialités |
| Historique des enseignements | MODULE 4 | Historique |
| Affecter un enseignement | MODULE 4 | Affectations |
| Gestion des périodes / trimestres | MODULE 2 | Périodes |
| Création de contrôles | MODULE 2 | Contrôles |
| Saisie des notes | MODULE 2 | Saisie des notes |
| Calcul automatique des moyennes par matière | MODULE 2 | Moyennes |
| Calcul automatique des moyennes générales | MODULE 2 | Moyennes |
| Rang et mention | MODULE 2 | Classement / Mentions |
| Bulletin par élève | MODULE 2 | Bulletins |
| Bulletin par classe | MODULE 2 | Bulletins |
| Classement par classe et période | MODULE 2 | Classement |
| Impression bulletin PDF | MODULE 6 | Documents pédagogiques |
| Pointage journalier | MODULE 3 | Pointage |
| Saisie manuelle d'une absence | MODULE 3 | Absences |
| Liste des absences avec filtres | MODULE 3 | Absences |
| Détail et suppression d'une absence | MODULE 3 | Absences |
| Soumettre une justification | MODULE 3 | Justifications |
| Valider / Refuser une justification | MODULE 3 | Justifications |
| Statistiques d'absences | MODULE 3 | Statistiques d'absences |
| Alertes d'absentéisme | MODULE 3 | Alertes d'absentéisme |
| Vue hebdomadaire EDT | MODULE 3 | Emploi du temps |
| Vue mensuelle EDT | MODULE 3 | Emploi du temps |
| Créer / Modifier / Supprimer un cours EDT | MODULE 3 | Affectation EDT |
| Impression EDT | MODULE 6 | Documents administratifs |
| Détection de conflits horaires (API) | MODULE 3 | Détection de conflits |
| Gestion des salles (CRUD) | MODULE 3 | Salles |
| Gestion des créneaux horaires (CRUD) | MODULE 3 | Créneaux horaires |
| Types de frais scolaires | MODULE 5 | Types de frais |
| Affectation de frais à un élève | MODULE 5 | Affectation des frais |
| Enregistrement d'un paiement | MODULE 5 | Encaissements |
| Consultation et impression reçu | MODULE 5 | Reçus |
| Liste des paiements | MODULE 5 | Encaissements |
| Saisie des dépenses | MODULE 5 | Décaissements |
| Catégories de dépenses | MODULE 5 | Catégories de dépenses |
| État de caisse | MODULE 5 | Caisse |
| Liste des impayés | MODULE 5 | Impayés |
| Rapport financier + impression + export | MODULE 10 | Rapport financier |
| Annonces (CRUD) | MODULE 9 | Annonces |
| Audiences des annonces | MODULE 9 | Annonces |
| Centre de notifications | MODULE 9 | Notifications internes |
| Préférences de notification | MODULE 9 | Préférences |
| API notifications (unread count, recent) | MODULE 9 | Notifications internes |
| Administration notifications (test, purge) | MODULE 9 | Administration |
| Push VAPID (subscribe/send) | MODULE 9 | Notifications push |
| Dashboard analytique global | MODULE 10 | Dashboard analytique |
| Rapport scolaire | MODULE 10 | Rapport scolaire |
| Rapport financier | MODULE 10 | Rapport financier |
| Rapport présences | MODULE 10 | Rapport présences |
| Rapport réussite | MODULE 10 | Rapport réussite |
| Export PDF / Excel rapports | MODULE 10 | Export |
| Notes enfant (espace parent) | MODULE 11 | Portail Parent |
| Bulletin enfant (espace parent) | MODULE 11 | Portail Parent |
| Absences + justifications (espace parent) | MODULE 11 | Portail Parent |
| Paiements (espace parent) | MODULE 11 | Portail Parent |
| Mes notes (espace élève) | MODULE 11 | Portail Élève |
| Mon bulletin (espace élève) | MODULE 11 | Portail Élève |
| Mon EDT (espace élève) | MODULE 11 | Portail Élève |
| Mon profil (espace élève) | MODULE 11 | Portail Élève |
| API élèves JSON | MODULE 11 | Infrastructure API Portails |
| API frais-élève JSON | MODULE 11 | Infrastructure API Portails |
| Service Worker PWA | CORE | Infrastructure PWA |
| Manifest PWA | CORE | Infrastructure PWA |

---

# 5. Répartition des nouvelles fonctionnalités prévues

| Nouvelle fonctionnalité | Module V2 | Sous-module cible |
|-------------------------|-----------|-------------------|
| Gestion complète des parents | MODULE 1 | Famille |
| Gestion avancée des encaissements | MODULE 5 | Encaissements |
| Gestion avancée des décaissements | MODULE 5 | Décaissements |
| Gestion des inventaires | MODULE 8 | Inventaire complet |
| Documents administratifs | MODULE 6 | Documents administratifs |
| Documents pédagogiques | MODULE 6 | Documents pédagogiques |
| Gestion des registres | MODULE 6 | Registres |
| Génération automatique des documents officiels | MODULE 6 | Génération automatique |
| Gestion des frais scolaires (avancée) | MODULE 5 | Types de frais + Affectation |
| Configuration des classes (avancée) | MODULE 1 | Classes |
| Communication inter-écoles | MODULE 12 | Communication inter-établissements |
| Partage de documents | MODULE 6 | Partage |
| Messagerie | MODULE 9 | Messagerie |
| Collaboration | MODULE 9 | Messagerie (groupes) |
| Suivi d'écoles | MODULE 12 | Dashboard supervision |
| Portail parents (enrichi) | MODULE 11 | Portail Parent |
| Portail enseignants (enrichi) | MODULE 11 | Portail Enseignant |
| Portail élèves (enrichi) | MODULE 11 | Portail Élève |
| Audit Log | CORE | Journal d'audit |
| Gestion des années scolaires | CORE | Référentiel des années scolaires |
| Bibliothèque | MODULE 7 | Bibliothèque complète |
| Notifications SMS | MODULE 9 | Notifications SMS |
| Notifications Email | MODULE 9 | Notifications email |
| Notifications Push | MODULE 9 | Notifications push |

---

# 6. Arborescence complète

```
SCOLARIS V2
│
├── CORE (non désactivable)
│   ├── Tableau de bord
│   ├── Profil utilisateur
│   ├── Notifications (centre)
│   └── Configuration
│       ├── Établissement
│       ├── Années scolaires
│       ├── Utilisateurs
│       ├── Rôles & Permissions
│       ├── Modules actifs
│       └── Journal d'audit
│
├── SCOLARITÉ
│   ├── Élèves
│   │   ├── Liste & Recherche
│   │   ├── Fiche élève
│   │   ├── Nouvel élève
│   │   ├── Import CSV
│   │   └── Export PDF / Excel
│   ├── Familles
│   │   ├── Liste des parents
│   │   ├── Fiche famille
│   │   └── Liens parent ↔ enfant(s)
│   ├── Classes
│   │   ├── Liste des classes
│   │   ├── Détail classe (élèves + enseignants)
│   │   └── Configuration classe
│   ├── Matières
│   │   ├── Liste des matières
│   │   └── Configuration matière
│   ├── Inscriptions
│   │   ├── Nouvelle inscription
│   │   ├── Réinscription annuelle
│   │   └── Transfert / Radiation
│   └── Registres
│       ├── Registre d'inscription
│       └── Effectifs officiels
│
├── ACADÉMIQUE
│   ├── Périodes
│   │   ├── Liste des périodes
│   │   └── Période active
│   ├── Contrôles & Évaluations
│   │   ├── Liste des contrôles
│   │   ├── Créer un contrôle
│   │   └── Saisie des notes
│   ├── Résultats
│   │   ├── Moyennes par matière
│   │   ├── Moyennes générales
│   │   └── Classement
│   └── Bulletins
│       ├── Par classe
│       ├── Par élève
│       └── Classement général
│
├── VIE SCOLAIRE
│   ├── Emploi du temps
│   │   ├── Vue semaine
│   │   ├── Vue mensuelle
│   │   └── Paramètres
│   │       ├── Salles
│   │       └── Créneaux horaires
│   └── Absences
│       ├── Pointage du jour
│       ├── Liste des absences
│       ├── Justifications
│       │   ├── En attente de validation
│       │   └── Historique
│       ├── Statistiques
│       └── Alertes
│
├── RESSOURCES HUMAINES
│   ├── Enseignants
│   │   ├── Liste
│   │   ├── Fiche enseignant
│   │   └── Nouvel enseignant
│   └── Affectations
│       ├── Par enseignant
│       ├── Par classe
│       └── Par matière
│
├── FINANCE
│   ├── Vue d'ensemble (caisse)
│   ├── Encaissements
│   │   ├── Liste des paiements
│   │   ├── Nouveau paiement
│   │   └── Reçus
│   ├── Décaissements
│   │   ├── Liste des dépenses
│   │   └── Nouvelle dépense
│   ├── Frais scolaires
│   │   ├── Types de frais
│   │   ├── Affectation aux élèves
│   │   └── Impayés
│   └── Rapports financiers
│       ├── Tableau récapitulatif
│       ├── Rapport PDF
│       └── Export Excel
│
├── DOCUMENTS
│   ├── Documents administratifs
│   │   ├── Attestation de scolarité
│   │   ├── Certificat de présence
│   │   └── Convocations
│   ├── Documents pédagogiques
│   │   ├── Bulletins imprimables
│   │   └── Relevés de notes
│   ├── Registres officiels
│   │   ├── Registre d'inscription
│   │   ├── Registre des absences
│   │   └── Registre des paiements
│   ├── Génération automatique
│   └── Partage de documents
│       ├── Envoyés
│       └── Reçus
│
├── BIBLIOTHÈQUE
│   ├── Catalogue
│   ├── Emprunts en cours
│   ├── Retours
│   └── Ressources numériques
│
├── INVENTAIRE
│   ├── Catalogue des biens
│   ├── Par salle / localisation
│   ├── Mouvements
│   └── Consommables & stocks
│
├── COMMUNICATION
│   ├── Annonces
│   │   ├── Liste
│   │   └── Nouvelle annonce
│   ├── Messagerie
│   │   ├── Boîte de réception
│   │   ├── Envoyés
│   │   └── Nouvelle conversation
│   └── Administration notifications
│       ├── Historique des envois
│       ├── Test d'envoi
│       └── Purge
│
├── RAPPORTS & ANALYTIQUE
│   ├── Dashboard analytique
│   ├── Rapport scolaire
│   ├── Rapport financier
│   ├── Rapport présences
│   ├── Rapport réussite
│   └── Rapport RH
│
├── PORTAILS
│   ├── Portail Parent
│   │   ├── Tableau de bord famille
│   │   ├── Résultats de mon enfant
│   │   ├── Bulletin
│   │   ├── Absences & Justifications
│   │   ├── Mes paiements
│   │   ├── Documents reçus
│   │   ├── Messagerie
│   │   └── Annonces
│   ├── Portail Élève
│   │   ├── Tableau de bord
│   │   ├── Mes notes
│   │   ├── Mon bulletin
│   │   ├── Mon emploi du temps
│   │   ├── Mes absences
│   │   ├── Mes ressources
│   │   └── Mon profil
│   └── Portail Enseignant
│       ├── Tableau de bord
│       ├── Mes classes
│       ├── Saisie des notes
│       ├── Pointage des absences
│       ├── Mon emploi du temps
│       ├── Mes documents
│       └── Messagerie
│
└── RÉSEAU (Horizon 3)
    ├── Mes établissements
    ├── Dashboard supervision
    ├── Communication inter-écoles
    └── Partage de ressources
```

---

# 7. Futurs menus

## 7.1 Menu principal — Sidebar Desktop

```
┌─────────────────────────────────┐
│  ● SCOLARIS                     │
│  [Nom de l'établissement]       │
├─────────────────────────────────┤
│  Tableau de bord                │  → Toujours visible
├─────────────────────────────────┤
│  ▾ SCOLARITÉ                   │
│    Élèves                       │
│    Familles                     │
│    Classes                      │
│    Matières                     │
│    Inscriptions                 │
│    Registres                    │
├─────────────────────────────────┤
│  ▾ ACADÉMIQUE                  │
│    Périodes                     │
│    Contrôles & Notes            │
│    Résultats                    │
│    Bulletins                    │
├─────────────────────────────────┤
│  ▾ VIE SCOLAIRE                │
│    Emploi du temps              │
│    Absences                     │
├─────────────────────────────────┤
│  ▾ RESSOURCES HUMAINES         │  (si module actif)
│    Enseignants                  │
│    Affectations                 │
├─────────────────────────────────┤
│  ▾ FINANCE                     │  (si module actif)
│    Caisse                       │
│    Encaissements                │
│    Décaissements                │
│    Frais scolaires              │
│    Impayés                      │
├─────────────────────────────────┤
│  ▾ DOCUMENTS                   │  (si module actif)
│    Documents admin              │
│    Documents pédago             │
│    Registres                    │
│    Partage                      │
├─────────────────────────────────┤
│  ▾ BIBLIOTHÈQUE                │  (si module actif)
│    Catalogue                    │
│    Emprunts                     │
├─────────────────────────────────┤
│  ▾ INVENTAIRE                  │  (si module actif)
│    Catalogue                    │
│    Mouvements                   │
├─────────────────────────────────┤
│  ▾ COMMUNICATION               │
│    Annonces                     │
│    Messagerie                   │
├─────────────────────────────────┤
│  ▾ RAPPORTS                    │  (si permission)
│    Analytique                   │
│    Scolaire / Financier         │
│    Présences / Réussite         │
├─────────────────────────────────┤
│  ▾ CONFIGURATION               │  (admin / directeur)
│    Établissement                │
│    Années scolaires             │
│    Utilisateurs                 │
│    Permissions                  │
│    Modules actifs               │
│    Journal d'audit              │
└─────────────────────────────────┘
```

## 7.2 Sous-menus contextuels (barre de section)

| Section | Sous-menu |
|---------|-----------|
| Élèves | [Liste] [Nouvel élève] [Import CSV] [Export PDF] [Export Excel] |
| Académique | [Périodes] [Contrôles] [Saisie des notes] [Moyennes] [Bulletins] |
| Absences | [Pointage du jour] [Liste] [Justifications] [Statistiques] [Alertes] |
| Finance | [Caisse] [Paiements] [Dépenses] [Frais] [Impayés] [Rapport] |
| EDT | [Vue semaine] [Vue mensuelle] [Ajouter cours] [Salles] [Créneaux] |
| Rapports | [Analytique] [Scolaire] [Financier] [Présences] [Réussite] [RH] |

## 7.3 Menus contextuels par ligne de liste

Chaque ligne expose selon le contexte :
- **Voir** — fiche détaillée
- **Modifier** — formulaire d'édition
- **Supprimer** — avec confirmation modale
- **Actions métier** : Encaisser (impayés), Pointer (absences), Imprimer (bulletins, reçus), Justifier, Valider

## 7.4 Actions rapides (Header)

| Action | Destination |
|--------|-------------|
| Nouvelle absence | `/absences/create` |
| Nouveau paiement | `/paiements/create` |
| Nouvelle annonce | `/annonces/create` |
| Recherche globale | Overlay de recherche cross-modules |
| Notifications | Dropdown centre de notifications (badge nombre non lus) |
| Profil | Dropdown profil + préférences + déconnexion |

## 7.5 Navigation utilisateur

- **Fil d'Ariane** : toujours visible. Exemple : `Scolarité > Élèves > Ahmed Benali`
- **Onglets** sur les fiches multi-sections. Exemple fiche élève : `Infos | Notes | Absences | Paiements`
- **Pagination** en bas des listes avec sélecteur (10 / 25 / 50 / 100 par page)
- **Filtres persistants** : conservés pendant la session

## 7.6 Navigation mobile (Bottom bar)

| Rôle | Icônes bottom bar |
|------|-------------------|
| Admin / Directeur | Dashboard · Scolarité · Finance · Communication · Plus |
| Secrétaire | Dashboard · Élèves · Absences · Annonces · Plus |
| Comptable | Dashboard · Finance · Impayés · Rapports · Plus |
| Enseignant | Dashboard · Notes · Absences · EDT · Plus |
| Parent | Dashboard · Notes · Absences · Paiements · Annonces |
| Élève | Dashboard · Notes · EDT · Annonces · Profil |

---

# 8. Espaces utilisateurs

## ADMINISTRATEUR SYSTÈME

Accès total. Seul rôle pouvant gérer la configuration technique, activer/désactiver des modules, consulter le Journal d'audit complet.

| Module | Accès |
|--------|-------|
| Tous les modules | ✅ CRUD complet |
| Configuration | ✅ Établissement, modules, utilisateurs, permissions, audit log |

---

## DIRECTEUR

Pilotage global de l'établissement. Accès quasi-identique à l'Administrateur, sans la gestion technique de la plateforme ni la suppression d'utilisateurs.

| Module | Accès |
|--------|-------|
| Tous les modules métier | ✅ CRUD complet |
| Configuration | ✅ Établissement, utilisateurs (sans suppression), années scolaires |
| Journal d'audit | ✅ Consultation uniquement |

---

## SECRÉTAIRE

Gestion administrative quotidienne.

| Module | Accès |
|--------|-------|
| Scolarité | ✅ CRUD élèves, consultation classes et matières, inscriptions |
| Académique | ✅ Consultation uniquement (notes, bulletins) |
| Vie Scolaire | ✅ Pointage, gestion absences, justifications, EDT (consultation) |
| RH | ✅ Consultation uniquement |
| Finance | ✅ Consultation uniquement |
| Documents | ✅ Génération, consultation, partage |
| Communication | ✅ Annonces (CRUD), messagerie |
| Rapports | ✅ Consultation uniquement |
| Configuration | ❌ |

---

## COMPTABLE

Gestion financière de l'établissement.

| Module | Accès |
|--------|-------|
| Scolarité | ✅ Consultation élèves et classes uniquement |
| Finance | ✅ CRUD complet (encaissements, décaissements, frais, caisse, impayés) |
| Documents | ✅ Reçus, rapports financiers |
| Inventaire | ✅ Consultation (valorisation) |
| Communication | ✅ Annonces (consultation), messagerie |
| Rapports | ✅ Rapport financier uniquement |
| Tous les autres | ❌ |

---

## ENSEIGNANT (via Portail Enseignant)

Gestion pédagogique de ses propres classes uniquement.

| Fonctionnalité | Accès |
|----------------|-------|
| Tableau de bord pédagogique | ✅ Ses classes, sa charge horaire |
| Élèves de ses classes | ✅ Consultation uniquement |
| Créer des contrôles | ✅ Ses matières/classes uniquement |
| Saisir des notes | ✅ Ses contrôles uniquement |
| Pointer les absences | ✅ Ses classes uniquement |
| Son emploi du temps | ✅ Consultation uniquement |
| Sa propre fiche RH | ✅ Consultation uniquement |
| Documents pédagogiques | ✅ Bulletins (consultation), partage ressources |
| Bibliothèque | ✅ Catalogue, emprunts, partage numérique |
| Messagerie | ✅ Administration et parents uniquement |
| Finance, Configuration | ❌ |

---

## PARENT (via Portail Parent)

Suivi de son ou ses enfants. Accès exclusivement en lecture, sauf justifications.

| Fonctionnalité | Accès |
|----------------|-------|
| Tableau de bord famille | ✅ Résumé enfant(s) + sélecteur si plusieurs |
| Notes & résultats (enfant) | ✅ Consultation uniquement |
| Bulletin (enfant) | ✅ Consultation + téléchargement |
| Absences (enfant) | ✅ Consultation + soumettre justification |
| EDT (enfant) | ✅ Consultation uniquement |
| Frais & paiements (enfant) | ✅ Consultation solde + historique |
| Documents reçus | ✅ Bulletins, attestations envoyées par l'école |
| Messagerie | ✅ Administration uniquement |
| Annonces | ✅ Celles destinées aux parents |
| Tout le reste | ❌ |

---

## ÉLÈVE (via Portail Élève)

Consultation de ses propres données uniquement. Aucune action administrative.

| Fonctionnalité | Accès |
|----------------|-------|
| Tableau de bord personnel | ✅ |
| Mes notes | ✅ Consultation uniquement |
| Mon bulletin | ✅ Consultation + téléchargement |
| Mon emploi du temps | ✅ Consultation uniquement |
| Mes absences | ✅ Consultation uniquement |
| Mes ressources | ✅ Ressources partagées par les enseignants |
| Mon profil | ✅ Consultation, modification mineure (photo) |
| Annonces | ✅ Celles destinées aux élèves |
| Tout le reste | ❌ |

---

# 9. Dépendances entre modules

## 9.1 Schéma de dépendances

```
                    ┌──────────────────────────────────┐
                    │              CORE                 │
                    │  Auth · Users · Rôles · Perms     │
                    │  Années scolaires · Config        │
                    │  Dashboard · Audit Log · PWA      │
                    └──────────────┬───────────────────┘
                                   │ (tous en dépendent)
       ┌───────────────────────────┼───────────────────────────┐
       │                           │                           │
       ▼                           ▼                           ▼
┌──────────────┐         ┌─────────────────┐        ┌──────────────────┐
│  SCOLARITÉ   │         │       RH        │        │  COMMUNICATION   │
│ (référentiel)│         │  Enseignants    │        │ Annonces         │
│ Élèves       │         │  Affectations   │        │ Messagerie       │
│ Classes      │         └────────┬────────┘        │ Notifications    │
│ Matières     │                  │                 └──────────────────┘
│ Familles     │                  │
└──────┬───────┘                  │
       │                          │
       └──────────┬───────────────┘
                  │ (fournit élèves, classes, matières, enseignants)
       ┌──────────┴───────────────┐
       │                          │
       ▼                          ▼
┌──────────────┐        ┌──────────────────────┐
│  ACADÉMIQUE  │        │     VIE SCOLAIRE      │
│ Périodes     │        │ Emploi du temps       │
│ Contrôles    │        │ Absences              │
│ Notes        │        │ Justifications        │
│ Moyennes     │        │ Alertes               │
│ Bulletins    │        └──────────┬────────────┘
└──────┬───────┘                   │
       │                           │
       └──────────┬────────────────┘
                  │ (alimente)
       ┌──────────┴───────────────────────────────┐
       │                                           │
       ▼                                           ▼
┌──────────────────┐                   ┌──────────────────────┐
│     FINANCE      │                   │      DOCUMENTS        │
│ Frais · Paiements│                   │ Docs admin · Pédago  │
│ Dépenses · Caisse│                   │ Registres · Génération│
└──────────────────┘                   └──────────────────────┘
       │                                           │
       └──────────────────┬────────────────────────┘
                          │
       ┌──────────────────┴──────────────────────┐
       │          RAPPORTS & ANALYTIQUE           │
       │       (lecture seule sur tout)           │
       └──────────────────┬──────────────────────┘
                          │
       ┌──────────────────┴──────────────────────┐
       │                PORTAILS                  │
       │     Parent · Élève · Enseignant          │
       └─────────────────────────────────────────┘

BIBLIOTHÈQUE ──► (optionnel, dépend CORE + Scolarité)
INVENTAIRE   ──► (optionnel, dépend CORE + Vie Scolaire/Salles)
RÉSEAU       ──► (H3, dépend CORE + tous)
```

## 9.2 Classification des modules

### Modules indépendants (isolables)
- **Communication** — dépend uniquement du Core
- **Bibliothèque** — dépend du Core et de Scolarité (emprunteurs)
- **Inventaire** — dépend du Core et de Vie Scolaire (salles)

### Modules centraux (pivot)
- **Scolarité** — fournit le référentiel élèves/classes/matières à 5 modules
- **CORE** — socle de tout, aucun module ne peut fonctionner sans lui

### Modules critiques (ne pas désactiver une fois en production)
- **Finance** — données financières à valeur légale (reçus, paiements)
- **Académique** — résultats scolaires = historique officiel de l'élève
- **Documents** — une fois des documents officiels produits, l'archivage est obligatoire

### Modules optionnels
- Bibliothèque · Inventaire · Réseau · Portails (par portail) · Rapports

## 9.3 Dépendances d'activation

Un module ne peut être activé que si ses dépendances le sont :

| Module à activer | Prérequis |
|------------------|-----------|
| Académique | Scolarité |
| Vie Scolaire | Scolarité + RH |
| Finance | Scolarité |
| Documents | Scolarité (+ Académique recommandé + Finance recommandé) |
| Portail Parent | Scolarité + Académique + Finance |
| Portail Élève | Scolarité + Académique + Vie Scolaire |
| Portail Enseignant | Scolarité + Académique + Vie Scolaire + RH |
| Bibliothèque | Scolarité |
| Inventaire | Vie Scolaire (salles) |

## 9.4 Impact des suppressions (cascade)

| Table supprimée | Impact en cascade |
|----------------|-------------------|
| `users` | Cascade sur toutes les tables — suppression impossible sans précaution |
| `eleves` | Nœud central : notes, absences, frais, paiements, bulletins |
| `classes` | Affecte : absences, notes, contrôles, emploi du temps |
| `periodes` | Perte des contrôles → notes → moyennes associées |
| `matieres` | Cascade sur contrôles → notes (perte totale des évaluations de la matière) |

---

# 10. Modules activables

| Module | Activable | Raison |
|--------|:---------:|--------|
| CORE | ❌ | Socle non désactivable |
| Scolarité | ❌ | Toute école a des élèves et des classes |
| Académique | ⚠️ | Désactivable avant toute saisie. Irréversible après (données officielles). |
| Vie Scolaire | ✅ | EDT et absences peuvent être gérés externalement |
| RH | ✅ | Certains établissements ont un outil RH dédié |
| Finance | ✅ | Certains utilisent un logiciel comptable séparé |
| Documents | ✅ | Peut être géré externalement |
| Bibliothèque | ✅ | Module optionnel par nature |
| Inventaire | ✅ | Module optionnel par nature |
| Communication | ✅ (partiel) | Annonces quasi-indispensables. Messagerie et notifications push optionnelles. |
| Rapports | ✅ | La direction peut utiliser des outils BI externes |
| Portail Parent | ✅ | Activable séparément |
| Portail Élève | ✅ | Activable séparément |
| Portail Enseignant | ✅ | Activable séparément |
| Réseau | ✅ | Pour les groupes scolaires uniquement (H3) |

---

# 11. Le Core

Le Core est le noyau non désactivable de SCOLARIS. Présent dans toutes les installations, quelle que soit la configuration.

## Les 10 composants du Core

### 1. Authentification & Sécurité
Login, logout, gestion des sessions, CSRF, réinitialisation de mot de passe, enregistrement de la dernière connexion. Le Core ne contient pas de logique de vérification de données scolaires.

### 2. Gestion des utilisateurs
Création, modification, suppression et activation des comptes. Gestion du profil (nom, prénom, photo, téléphone, email). Le Core gère des utilisateurs avec des rôles — pas des élèves ou des enseignants.

### 3. Système de rôles
Les 7 rôles de la plateforme sont définis dans le Core : `admin`, `directeur`, `secretaire`, `comptable`, `enseignant`, `parent`, `eleve`. L'ajout d'un rôle personnalisé sera possible à terme.

### 4. Système de permissions granulaires
Toutes les permissions de la plateforme sont codifiées dans le Core — sans exception. Chaque action de chaque module possède une permission correspondante en base de données. Aucun module ne vérifie le rôle directement dans le code.

La règle : `permissions` centralise toutes les permissions. `role_permissions` définit ce que chaque rôle peut faire. Zéro vérification `$role === 'admin'` dans les contrôleurs.

### 5. Référentiel des années scolaires
L'année scolaire est le pivot temporel de toutes les données de SCOLARIS. Le Core gère :
- La liste des années scolaires (2024-2025, 2025-2026...)
- L'année scolaire active (une seule à la fois)
- Le basculement d'une année à l'autre
- La conservation des données des années précédentes en lecture seule

Toute donnée horodatée par une année scolaire fait référence à ce référentiel central — jamais à une chaîne de caractères libre.

### 6. Configuration générale de l'établissement
Nom de l'établissement, logo, adresse, téléphone, email officiel, fuseau horaire, langue. Ces données alimentent les en-têtes de tous les documents produits.

### 7. Tableau de bord central
Infrastructure du tableau de bord. Le contenu de chaque tableau de bord est injecté par les modules actifs selon le rôle de l'utilisateur connecté. Un module désactivé n'injecte pas de données.

### 8. Journal d'audit (Audit Log)
Toute action significative est enregistrée : qui, quoi, quand, depuis quelle IP.

Catégories d'événements :
- **Authentification** : connexion, déconnexion, tentatives échouées, reset MdP
- **Données** : création, modification, suppression de tout enregistrement
- **Accès** : tentative d'accès à une ressource non autorisée
- **Configuration** : modification des paramètres système
- **Documents** : génération et partage de documents officiels

Le journal d'audit est en écriture seule pour les utilisateurs non-administrateurs.

### 9. Service de notifications (infrastructure)
Le Core expose un mécanisme d'événements que les modules peuvent déclencher. Le module Communication se charge de la diffusion. Le Core ne sait pas ce qu'est une "note" ou un "paiement" — il sait qu'un événement s'est produit et qu'il faut le notifier.

### 10. PWA & Infrastructure technique
Manifest, Service Worker, configuration du cache offline. S'appliquent à toute la plateforme, pas à un module spécifique.

---

# 12. Carte fonctionnelle

```
╔══════════════════════════════════════════════════════════════════╗
║              SCOLARIS V2 — CARTE FONCTIONNELLE COMPLÈTE         ║
╚══════════════════════════════════════════════════════════════════╝

┌─────────────────────────────────────────────────────────────────┐
│                           C O R E                               │
│                                                                 │
│  Auth & Sécurité   ·   Utilisateurs   ·   Rôles                │
│  Permissions       ·   Années scolaires   ·   Configuration     │
│  Dashboard         ·   Audit Log          ·   PWA               │
│  Service Notifications (infrastructure)                         │
└──────────────────────────────┬──────────────────────────────────┘
                               │
       ┌───────────────────────┼──────────────────────────┐
       │                       │                          │
       ▼                       ▼                          ▼
┌─────────────┐     ┌──────────────────┐     ┌──────────────────┐
│  SCOLARITÉ  │     │       RH         │     │  COMMUNICATION   │
│             │     │                  │     │                  │
│ Élèves      │     │ Fiches enseignant│     │ Annonces         │
│  Fiche      │     │  Photo, grade    │     │  Création        │
│  Import CSV │     │  Spécialité      │     │  Audiences       │
│  Export     │     │                  │     │  Filtres         │
│  Photo      │     │ Affectations     │     │                  │
│             │     │  Prof×Mat×Classe │     │ Messagerie       │
│ Familles    │     │  Par année       │     │  Directe         │
│  Parents    │     │                  │     │  Groupes         │
│  Liens      │     │ Historique       │     │  Pièces jointes  │
│             │     │  Par prof        │     │                  │
│ Classes     │     │  Par classe      │     │ Notifications    │
│  Niveaux    │     │  Par matière     │     │  Interne         │
│  Capacité   │     │                  │     │  Email           │
│  Affectation│     └────────┬─────────┘     │  SMS             │
│             │              │               │  Push VAPID      │
│ Matières    │              │               │  Préférences     │
│  Coeff.     │              │               │  Administration  │
│  Vol. h.    │              │               └──────────────────┘
│  Responsable│              │
│             │              │
│ Inscriptions│              │
│  Admission  │              │
│  Réinscr.   │              │
│  Transfert  │              │
│             │              │
│ Registres   │              │
│  Effectifs  │              │
└──────┬──────┘              │
       │                     │
       └───────────┬─────────┘
                   │ (fournit élèves, classes, matières, enseignants)
       ┌───────────┴─────────────────────┐
       │                                 │
       ▼                                 ▼
┌──────────────────┐         ┌───────────────────────────┐
│    ACADÉMIQUE    │         │       VIE SCOLAIRE         │
│                  │         │                           │
│ Périodes         │         │ Emploi du temps           │
│  Trimestres      │         │  Grille semaine           │
│  Semestres       │         │  Grille mensuelle         │
│  Activation      │         │  Création cours           │
│                  │         │  Conflits (API)           │
│ Contrôles        │         │                           │
│  Types           │         │ Salles                    │
│  Coefficient     │         │  CRUD                     │
│  Note max        │         │  Type / Capacité          │
│  Dates           │         │                           │
│                  │         │ Créneaux                  │
│ Saisie notes     │         │  CRUD                     │
│  Grille          │         │  Heure / Type             │
│  Absent          │         │  Ordre                    │
│  Appréciation    │         │                           │
│                  │         │ Pointage                  │
│ Moyennes         │         │  Journalier               │
│  Par matière     │         │  Par session              │
│  Générales       │         │  Par classe               │
│  Auto-calcul     │         │                           │
│                  │         │ Absences                  │
│ Classement       │         │  Enregistrement           │
│  Rang            │         │  Type / Durée             │
│  Mentions        │         │  Suppression              │
│                  │         │                           │
│ Bulletins        │         │ Justifications            │
│  Par élève       │         │  Soumission (parent)      │
│  Par classe      │         │  Validation (admin)       │
│  Impression      │         │  Pièces jointes           │
│                  │         │                           │
│ Archives         │         │ Statistiques              │
│  Par année       │         │  Par élève / classe       │
│                  │         │  Tendances                │
└──────┬───────────┘         │                           │
       │                     │ Alertes                   │
       │                     │  Seuils configurables     │
       │                     │  Élèves critiques         │
       │                     └───────────┬───────────────┘
       │                                 │
       └────────────────┬────────────────┘
                        │
       ┌────────────────┴──────────────────────────────┐
       │                                               │
       ▼                                               ▼
┌──────────────────┐                    ┌──────────────────────┐
│     FINANCE      │                    │      DOCUMENTS        │
│                  │                    │                       │
│ Types de frais   │                    │ Docs administratifs   │
│  Référentiel     │                    │  Attestation scol.    │
│  Montant défaut  │                    │  Certificat prés.     │
│  Périodicité     │                    │  Convocations         │
│                  │                    │                       │
│ Affectation      │                    │ Docs pédagogiques     │
│  Par élève       │                    │  Bulletins impr.      │
│  Par classe      │                    │  Relevés de notes     │
│  Par année       │                    │                       │
│                  │                    │ Registres officiels   │
│ Encaissements    │                    │  Inscriptions         │
│  Paiements       │                    │  Absences             │
│  Modes           │                    │  Paiements            │
│  Références      │                    │                       │
│                  │                    │ Génération auto       │
│ Reçus            │                    │  Depuis les données   │
│  Génération      │                    │  Templates config.    │
│  Impression      │                    │                       │
│                  │                    │ Partage               │
│ Décaissements    │                    │  Envoi parent         │
│  Dépenses        │                    │  Envoi enseignant     │
│  Catégories      │                    │                       │
│  Modes           │                    │ Archivage             │
│                  │                    │  Historique           │
│ Caisse           │                    │  Date + auteur        │
│  Solde           │                    └──────────────────────┘
│  Entrées/Sorties │
│  Par période     │
│                  │
│ Impayés          │
│  Par classe      │
│  Par type        │
│  Filtre annee    │
│                  │
│ Rapports         │
│  Récapitulatif   │
│  PDF / Excel     │
└──────────────────┘

┌───────────────────────────────────────────────────────────────┐
│                  RAPPORTS & ANALYTIQUE                         │
│                 (lecture seule sur tous les modules)           │
│                                                               │
│  KPI Globaux  ·  Rapport Scolaire  ·  Rapport Financier       │
│  Rapport Présences  ·  Rapport Réussite  ·  Rapport RH        │
│  Export PDF  ·  Export Excel                                  │
└───────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────┐
│                          PORTAILS                              │
│         (interfaces sur les données des autres modules)        │
│                                                               │
│  ┌──────────────────┐ ┌─────────────────┐ ┌───────────────┐  │
│  │  PORTAIL PARENT  │ │ PORTAIL ÉLÈVE   │ │ PORTAIL ENSEIG│  │
│  │                  │ │                 │ │               │  │
│  │ TB famille       │ │ TB personnel    │ │ TB pédago     │  │
│  │ Notes enfant     │ │ Mes notes       │ │ Mes classes   │  │
│  │ Bulletin         │ │ Mon bulletin    │ │ Saisie notes  │  │
│  │ Absences         │ │ Mon EDT         │ │ Pointage      │  │
│  │ Justifications   │ │ Mes absences    │ │ Mon EDT       │  │
│  │ Paiements        │ │ Mes ressources  │ │ Mes documents │  │
│  │ Documents reçus  │ │ Mon profil      │ │ Messagerie    │  │
│  │ Messagerie       │ │ Annonces        │ │ Annonces      │  │
│  │ Annonces         │ │                 │ │               │  │
│  └──────────────────┘ └─────────────────┘ └───────────────┘  │
└───────────────────────────────────────────────────────────────┘

┌──────────────────┐  ┌──────────────────┐
│   BIBLIOTHÈQUE   │  │    INVENTAIRE    │
│  (optionnel)     │  │  (optionnel)     │
│                  │  │                  │
│ Catalogue        │  │ Catalogue biens  │
│ Emprunts/Retours │  │ Localisation     │
│ Ressources numér.│  │ Mouvements       │
│ Statistiques     │  │ Consommables     │
└──────────────────┘  └──────────────────┘

┌───────────────────────────────────────────────────────────────┐
│                    RÉSEAU  (Horizon 3)                         │
│  Établissements  ·  Supervision  ·  Communication inter-écoles │
│  Partage de ressources  ·  Benchmarking                        │
└───────────────────────────────────────────────────────────────┘
```

---

# 13. Incohérences actuelles

## 13.1 Chevauchements fonctionnels

| Chevauchement | Description |
|---------------|-------------|
| **Rapport financier dupliqué** | `/comptabilite/rapport` et `/rapports/financier` produisent des données similaires avec des comportements différents. Propriétaire unique requis : Finance produit les données brutes, Rapports les agrège. |
| **Statistiques en double** | `HomeController::getStats()` et `RapportModel` recalculent les mêmes totaux. Deux sources de vérité pour les mêmes chiffres. |
| **Calcul des moyennes dispersé** | `NoteController`, `BulletinController` et `RapportController` accèdent tous à `notes`, `moyennes_matieres` et `moyennes_generales`. Pas de propriétaire unique du calcul. |

## 13.2 Incohérences fonctionnelles

| Incohérence | Description |
|-------------|-------------|
| **Inscription publique non sécurisée** | `/register` est accessible sans authentification. Dans un ERP scolaire, la création d'un compte est une action administrative. |
| **Lien parent-enfant non garanti applicativement** | `eleves.parent_id` existe en DB. Si `ParentController` ne filtre pas systématiquement par `parent_id`, un parent peut consulter des données qui ne sont pas les siennes. |
| **Double identité enseignant** | Un enseignant existe dans `users` (identité) ET dans `professeurs` (fiche pédagogique). Si la création d'un compte enseignant ne crée pas automatiquement une fiche `professeurs`, l'enseignant peut se connecter mais est invisible dans les modules pédagogiques. |
| **Année scolaire sans référentiel** | L'année scolaire est une chaîne VARCHAR libre répétée dans 6+ tables. Une faute de frappe crée une incohérence invisible entre modules. |

## 13.3 Responsabilités mal réparties

| Problème | Description |
|----------|-------------|
| **ComptabiliteController trop large** | Gère à la fois le paramétrage des frais, l'affectation, les paiements, les dépenses, la caisse et le rapport. Trop de responsabilités pour un seul contrôleur. |
| **Espace parent/élève = fragments** | Ce sont des vues réduites de l'interface admin, pas des portails à part entière. Ils reproduisent des fonctionnalités existantes avec des filtres différents sans navigation propre. |
| **Notifications = module UI + service infrastructure mélangés** | `notifications` (messages à lire) et `NotificationService` (déclencheur d'alertes sur événements) ont deux responsabilités distinctes mélangées dans la même structure. |

## 13.4 Menus confus

| Problème | Description |
|----------|-------------|
| **Finance dispersée** | Frais (`/comptabilite/frais`), paiements (`/paiements`), dépenses (`/depenses`) et rapports financiers (`/rapports/financier` + `/comptabilite/rapport`) ont quatre entrées différentes. Un comptable ne sait pas où aller. |
| **Paramètres EDT dans la navigation principale** | Salles et créneaux sont des paramètres de configuration (modifiés rarement) mais apparaissent comme des entrées de menu principales. Ils devraient être dans "Paramètres de l'emploi du temps". |
| **Chemin bulletins non indiqué** | Pour accéder à un bulletin : Bulletins → choisir période → choisir classe → choisir élève. Trois clics sans indication dans la navigation. |

## 13.5 Doublons et anomalies de schéma

| Anomalie | Description |
|----------|-------------|
| **Permissions non codifiées en DB** | `annonces.*`, `comptabilite.*`, `paiements.*`, `depenses.*`, `bulletins.*`, `emplois_du_temps.*` n'existent pas dans la table `permissions`. Ces modules vérifient le rôle directement dans le code. Système à deux vitesses — impossible d'administrer tous les droits depuis l'interface. |
| **Deux tables `notes`** | `ecole_app.sql` crée une table `notes` (ancien schéma simplifié). `academique_migration.sql` crée une nouvelle table `notes` (nouveau schéma avec contrôle_id). Si l'ancienne n'a pas été supprimée, il y a ambiguïté. |
| **Deux tables `absences`** | Même problème — ancien schéma simple dans `ecole_app.sql`, nouveau schéma étendu dans `absences_migration.sql`. |
| **FK non déclarées** | `notifications.user_id`, `annonces.publie_par`, `notification_preferences.user_id`, `notification_logs.user_id`, `push_subscriptions.user_id` n'ont pas de contraintes FK déclarées. |

---

# 14. Blueprint Final

## Vision

SCOLARIS est une plateforme de gestion scolaire intégrée. Mission : centraliser tous les processus d'un établissement d'enseignement dans un système unique, traçable et accessible à chaque acteur de la communauté éducative selon son rôle.

---

## Architecture

| Élément | Valeur |
|---------|--------|
| Type | ERP modulaire mono-établissement (multi-établissement en H3) |
| Pattern | MVC — Core non désactivable + Modules activables |
| Pivot central | L'année scolaire — toute donnée y est rattachée |
| Traçabilité | Journal d'Audit sur toutes les actions significatives |
| Permissions | 100 % codifiées en DB — zéro vérification de rôle en dur |

---

## Modules

| # | Module | Désactivable | Horizon |
|---|--------|:------------:|:-------:|
| 0 | CORE | ❌ | V2 |
| 1 | Scolarité | ❌ | V2 |
| 2 | Académique | ⚠️ | V2 |
| 3 | Vie Scolaire | ✅ | V2 |
| 4 | Ressources Humaines | ✅ | V2 |
| 5 | Finance | ✅ | V2 |
| 6 | Documents | ✅ | V2 |
| 7 | Bibliothèque | ✅ | V2 |
| 8 | Inventaire | ✅ | V2 |
| 9 | Communication | ✅ | V2 |
| 10 | Rapports & Analytique | ✅ | V2 |
| 11 | Portails (Parent / Élève / Enseignant) | ✅ | V2 |
| 12 | Réseau | ✅ | H3 |

---

## Core — composants non désactivables

1. Authentification & Sécurité
2. Gestion des utilisateurs (CRUD, activation)
3. Système de rôles (7 rôles)
4. Système de permissions granulaires (100 % en DB)
5. Référentiel des années scolaires (pivot temporel)
6. Configuration générale de l'établissement
7. Tableau de bord central (KPI injectés par les modules)
8. Journal d'Audit (écriture seule pour les utilisateurs)
9. Service de notifications (infrastructure d'événements)
10. PWA (manifest, Service Worker)

---

## Incohérences prioritaires à résoudre avant V2

| Priorité | Incohérence |
|:--------:|-------------|
| 🔴 | Permissions non codifiées en DB pour 5+ modules |
| 🔴 | `/register` accessible sans garde administrateur |
| 🔴 | Lien parent-enfant non garanti applicativement |
| 🔴 | Tables `notes` et `absences` en double (ancien + nouveau schéma) |
| 🟠 | Absence de référentiel `annees_scolaires` central |
| 🟠 | Aucun Journal d'Audit |
| 🟠 | Double calcul des statistiques (dashboard + rapports) |
| 🟡 | Rapport financier dupliqué (Finance vs Rapports) |
| 🟡 | Notifications : module UI et service infrastructure mélangés |
| 🟡 | Enseignant sans fiche `professeurs` si création manuelle |

---

## Roadmap générale

### Phase 0 — Fondations (Prérequis avant toute V2)
- Créer le référentiel `annees_scolaires` et migrer les chaînes VARCHAR
- Codifier 100 % des permissions dans la table `permissions`
- Sécuriser `/register` (admin-only)
- Garantir le filtre `parent_id` dans toutes les vues parent
- Résoudre la duplication des tables `notes` et `absences`
- Implémenter le Journal d'Audit

### Phase 1 — Core V2
- Gestion des Années Scolaires (CRUD, activation, basculement)
- Refactoriser les permissions (zéro vérification de rôle en dur)
- Construire le Portail Enseignant complet
- Enrichir le Portail Parent (sélecteur enfant, messagerie, documents)
- Enrichir le Portail Élève (ressources, absences consultation)

### Phase 2 — Nouveaux modules
- Module Documents (génération automatique, partage, registres)
- Module Messagerie (dans Communication)
- Notifications SMS et Email (avec providers configurés)
- Push Notifications avec déclencheurs automatiques sur événements

### Phase 3 — Modules optionnels
- Module Bibliothèque
- Module Inventaire
- API REST complète (tous modules exposés en JSON)
- Gestion avancée des Familles (fiche famille complète)

### Phase 4 — Réseau (Horizon 3)
- Architecture multi-établissement
- Dashboard de supervision réseau
- Communication inter-établissements
- Partage de ressources entre écoles

---

*Ce document est la référence officielle de SCOLARIS V2.*
*Toute décision d'implémentation doit être validée contre ce Blueprint.*
*Toute divergence doit être documentée et justifiée.*

*Version 2.0 — 29 juin 2026*
