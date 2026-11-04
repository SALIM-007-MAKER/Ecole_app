# Guide Direction — SCOLARIS V2

**Public :** compte avec le rôle **Direction** (`directeur`). Ce guide précise, action par
action, ce que ce rôle peut réellement faire — pas juste « consultation et gestion complète »,
qui ne dit rien. Chaque liste ci-dessous vient d'une comparaison directe, dans le code, entre
les permissions `directeur` et `admin` (`config/permissions.php`), pas d'une supposition.

> Document réécrit le 21 août 2026. Le précédent contenait des informations obsolètes
> (modules « non appliqués », écrans Paramètres retirés du périmètre établissement depuis
> juillet 2026) — corrigées ici.

## Ce qu'il faut savoir avant tout : Direction ≈ Administrateur

Sur les modules actifs de l'établissement (Élèves, Scolarité, Académique, Vie scolaire,
Finance, RH, Paramètres), **le rôle Direction a quasiment les mêmes droits que
Administrateur** — ce n'est pas un accès de simple supervision. Les seules différences
réelles trouvées en comparant les deux listes de permissions :

| Différence | Administrateur | Direction |
|---|---|---|
| Supprimer un compte utilisateur | Oui | **Non** — peut créer/modifier un compte, pas le supprimer (désactiver reste possible) |
| Saisie manuelle en comptabilité générale | Permission présente | Permission absente — **sans conséquence actuelle** : cette fonctionnalité n'existe pas dans l'interface pour personne, voir `GUIDE_COMPTABLE.md` § 8 |
| Paramètres Sécurité, Sauvegarde et Avancé | Oui | **Non** depuis le 21/08/2026 — paramétrage technique réservé à Administrateur/Super-Admin (§ 10) |

Tout le reste (créer, modifier, supprimer/archiver, exporter, valider, approuver…) est
identique aux deux rôles sur les modules listés ci-dessous.

## Sommaire

1. [Connexion et tableau de bord](#1-connexion-et-tableau-de-bord)
2. [Workflow de pilotage](#2-workflow-de-pilotage)
3. [Élèves et classes](#3-élèves-et-classes)
4. [Matières et enseignants](#4-matières-et-enseignants)
5. [Académique](#5-académique)
6. [Vie scolaire](#6-vie-scolaire)
7. [Finance](#7-finance)
8. [Ressources humaines](#8-ressources-humaines)
9. [Comptes utilisateurs](#9-comptes-utilisateurs)
10. [Paramètres de l'établissement](#10-paramètres-de-létablissement)
11. [Multi-établissements](#11-multi-établissements)

---

## 1. Connexion et tableau de bord

Identique au parcours Administrateur (`GUIDE_ADMINISTRATEUR.md` § 1).

---

## 2. Workflow de pilotage

Ce guide décrit les écrans module par module (§ 3 et suivants), mais Direction s'en sert
rarement module par module : le point de départ, c'est un indicateur qui interpelle sur le
tableau de bord, puis on descend jusqu'à l'action concrète. Voici la logique, avec les écrans
réels et non des exemples génériques.

**Ce que montre réellement le tableau de bord (`/dashboard`)** : effectifs (élèves, élèves
actifs, classes, enseignants, parents), absences du jour, un graphique d'absences sur 5 mois, et
une répartition des comptes par rôle. **Il n'affiche ni indicateur financier ni indicateur de
réussite scolaire** — ces deux familles vivent dans des rapports dédiés (§ 7 et § 5
ci-dessous). Un pilotage complet passe donc par trois points d'entrée, pas un seul.

### A. Absentéisme

1. **Constater** — tableau de bord : compteur « absences du jour » ou pic visible sur le
   graphique 5 mois.
2. **Investiguer** — `Vie scolaire → Absences` (`/v2/vie-scolaire/absences`), filtrer par classe
   ou par élève pour isoler la source du pic ; consulter les justifications en attente.
3. **Agir** — valider ou rejeter les justifications en attente ; si le comportement le
   justifie, ouvrir un dossier `Discipline` (§ 6).
4. **Vérifier** — revenir au tableau de bord (ou au rapport `/rapports/presences`) les jours
   suivants pour confirmer que le pic redescend.

### B. Impayés et trésorerie

1. **Constater** — **pas** sur le tableau de bord principal (aucun indicateur financier
   dessus) : le point d'entrée réel est `Finance → Vue d'ensemble`
   (`/v2/finance/rapports/dashboard`) — encaissements du jour/mois, état de caisse, factures en
   attente.
2. **Investiguer** — `/v2/finance/rapports/impayes` : filtrer par classe ou niveau pour
   identifier les familles concernées, croiser avec la liste des factures `en retard` /
   `partiellement payée` (`/v2/finance/factures`).
3. **Agir** — relancer les familles, traiter les paiements/trop-perçus au cas par cas (§ 7 et
   `GUIDE_COMPTABLE.md` § 5), ou valider un décaissement si l'anomalie est côté dépenses (§ 7,
   workflow `GUIDE_ADMINISTRATEUR.md` § 2.9).
4. **Vérifier** — retour à `/v2/finance/rapports` (rapport paiements ou impayés) pour constater
   l'évolution après relance.

### C. Résultats scolaires

1. **Constater** — après clôture d'une période et génération des bulletins (workflow
   `GUIDE_ADMINISTRATEUR.md` § 2.3 et § 2.10), consulter `/rapports/reussite` : moyennes et taux
   de réussite par classe/matière.
2. **Investiguer** — depuis ce rapport, descendre vers `/v2/academique/resultats/classe` ou le
   classement pour repérer la classe, la matière ou les élèves en dessous du seuil attendu.
3. **Agir** — échanger avec l'enseignant concerné, ajouter l'**appréciation du chef
   d'établissement** sur les bulletins qui le nécessitent (§ 5), envisager un accompagnement.
4. **Vérifier** — comparer le même rapport à la période suivante pour mesurer l'effet.

### D. Ressources humaines

1. **Constater** — `RH → Présences` ou `RH → Congés` : cumul d'heures supplémentaires
   inhabituel, absences répétées d'un employé, demandes de congé en attente qui s'accumulent.
2. **Investiguer** — fiche employé concernée : historique de présence, solde de congés,
   contrat en cours.
3. **Agir** — **approuver/rejeter** la demande de congé en attente, engager un entretien si les
   absences sont répétées (§ 8, workflow `GUIDE_ADMINISTRATEUR.md` § 2.7 et § 2.8).
4. **Vérifier** — export ou tableau de présence RH à la période suivante.

Dans les quatre cas, le schéma est le même : un signal réel sur un tableau de bord (principal ou
dédié à un module), un écran de consultation pour isoler la cause, une action de validation ou
de correction réellement disponible pour Direction (détaillée module par module ci-dessous), et
un retour au même rapport pour vérifier que l'anomalie a été traitée.

---

## 3. Élèves et classes

**Élèves** (`/eleves`) :
- **Consulter** la fiche, la liste (avec filtres), les statistiques.
- **Créer** une fiche élève, **importer** en masse (CSV).
- **Modifier** tous les champs d'une fiche existante.
- **Changer un élève de classe** — depuis la fiche de la classe (§ voir Classes ci-dessous),
  pas depuis la fiche élève elle-même.
- **Désactiver** — décocher « actif » sur la fiche (via Modifier) : l'élève disparaît des
  listes/statistiques sans que rien ne soit perdu. **Il n'existe pas de bouton « Archiver »
  séparé** pour les élèves — c'est ce mécanisme (actif/inactif) qui en tient lieu.
- **Supprimer** — action distincte et **définitive** (suppression physique en base, y compris
  la photo) : à réserver aux fiches créées par erreur, jamais à un départ réel. Utilisez
  Désactiver pour un départ.
- **Exporter** — PDF ou Excel, respecte les filtres actifs de la liste.

**Classes** (`/classes`) :
- **Consulter**, **créer**, **modifier** (niveau, nom, année scolaire, capacité maximale).
- **Affecter un élève** à la classe, **retirer un élève** — pour déplacer un élève d'une
  classe à une autre, affectez-le directement à la nouvelle classe : inutile de le retirer de
  l'ancienne au préalable, la nouvelle affectation remplace l'ancienne.
- **Affecter / retirer un enseignant**.
- **Supprimer** — **bloqué tant qu'au moins un élève est encore affecté à la classe** (message
  d'erreur explicite, pas de suppression en cascade). Videz-la d'abord si besoin.

---

## 4. Matières et enseignants

**Matières** (`/matieres`) : consulter, créer, modifier, supprimer — CRUD complet, pas de
restriction particulière.

**Enseignants** (`/professeurs`, fiche pédagogique — distincte du dossier RH, § 8) : consulter,
créer, modifier, supprimer ; depuis la fiche, affecter ou retirer un enseignement
(matière + classe).

---

## 5. Académique

Accès identique à l'Administrateur sur l'ensemble du cycle — voir
`GUIDE_ADMINISTRATEUR.md` § 6 pour le détail écran par écran. En résumé :

- **Périodes scolaires** : créer/générer, activer, ouvrir, clôturer, rouvrir, verrouiller,
  archiver.
- **Types d'évaluation** : créer, modifier, activer/désactiver, archiver.
- **Évaluations** : créer, modifier, publier, verrouiller, archiver.
- **Notes** : saisir, importer (CSV), publier, verrouiller.
- **Bulletins** : consulter, générer/imprimer.
- **Appréciation du chef d'établissement sur un bulletin**
  (`/v2/academique/bulletins/{eleveId}/{periodeId}/appreciation-directeur`) — c'est un des
  deux seuls écrans où Direction diffère : accessible à Direction (et Administrateur), mais à
  aucun autre rôle. Si votre session a démarré avant le 21/08/2026, reconnectez-vous une fois
  pour que la permission prenne effet — elle est figée en session à la connexion.

### Le parcours complet d'un bulletin, étape par étape

Le module Académique est celui où Direction passe le plus de temps hors pilotage courant : voici
le chemin réel, du premier chiffre saisi jusqu'au document remis à la famille.

| # | Étape | Où | Ce qui se passe réellement |
|---|---|---|---|
| 1 | **Notes** | Depuis chaque évaluation : `/v2/academique/evaluations/{id}/notes/saisie` (grille) ou `.../notes/importer` (CSV) | Saisie unitaire ou import CSV, puis **publier** (visible aux familles) ou **verrouiller** (figée) l'évaluation. Seules les évaluations `publiée`/`verrouillée` entrent dans les calculs suivants — une évaluation encore en brouillon est **ignorée silencieusement**, pas signalée comme manquante. |
| 2 | **Calcul des moyennes** | `Académique → Résultats → Moyennes` (`/v2/academique/resultats/moyennes`) | Ce n'est pas une action à déclencher : la moyenne de chaque élève, par matière et générale, est **recalculée en direct** à chaque ouverture de cet écran, à partir des notes publiées/verrouillées du moment. Aucun bouton « calculer » — c'est une vue toujours à jour. |
| 3 | **Classement** | `Académique → Résultats → Classement` (`/v2/academique/resultats/classement`) | Même principe, en direct (`RankingEngine`) : rangs par classe, avec l'évolution par rapport à la période précédente si elle existe ; un classement par matière ou par niveau est disponible depuis les mêmes filtres. |
| 4 | **Génération du bulletin** | `Académique → Bulletins` → grille de classe (`/bulletins/classe`) puis bulletin individuel (`/bulletins/{eleveId}`) | La grille de classe repère les notes manquantes avant d'imprimer quoi que ce soit. Un bulletin individuel s'ouvre d'abord en **aperçu calculé en direct** (rien n'est encore enregistré) ; la première ouverture de la version imprimable (`/v2/academique/bulletins/{eleveId}/{periodeId}/imprimer`) **génère et fige** le document définitivement. |
| 5 | **Vérification** | Sur le bulletin déjà généré | Ajoutez si besoin l'**appréciation du chef d'établissement** (ci-dessus). Chaque bulletin imprimé porte aussi un **QR code de vérification publique** (`/v2/academique/bulletins/verify/{token}`) : un tiers peut confirmer son authenticité sans compte, en le scannant. |
| 6 | **Export / impression PDF** | Depuis la version imprimable | Pas de génération PDF séparée : c'est l'impression/export PDF du **navigateur** sur cette page — même principe que les factures et les reçus (`GUIDE_COMPTABLE.md`). |

**Le point qui piège le plus souvent** : les étapes 2 et 3 (moyennes, classement) sont **vivantes**
— elles reflètent toujours les notes actuelles — mais l'étape 4, une fois franchie, **fige**
le bulletin. Corriger une note après avoir imprimé un bulletin ne le met **pas** à jour
automatiquement ; il faut le régénérer volontairement. Vérifiez donc la grille de l'étape 4
et vos moyennes/classement (étapes 2-3) **avant** la première impression de chaque élève, pas
après. Voir aussi le scénario **C. Résultats scolaires** du § 2 pour la lecture de pilotage
(anomalie → action) qui suit la publication des bulletins d'une période.

---

## 6. Vie scolaire

Accès identique à l'Administrateur — retards, discipline, récompenses (`GUIDE_ADMINISTRATEUR.md`
§ 7), y compris les actions de **validation** (justifications, sanctions) et l'**export**.

---

## 7. Finance

Accès identique à l'Administrateur sur toute la Finance scolaire, **y compris les actions
sensibles** : émettre/annuler une facture, encaisser/rembourser un paiement,
**valider/approuver/rejeter/payer** un décaissement, ouvrir/fermer une caisse. Détail complet
action par action : `GUIDE_COMPTABLE.md`.

Seule restriction réelle : pas de permission de saisie comptable manuelle — sans effet
aujourd'hui puisque cette fonctionnalité n'existe pour personne (§ ci-dessus).

---

## 8. Ressources humaines

Accès identique à l'Administrateur sur les 10 domaines RH — y compris les actions de
validation/approbation : **approuver/rejeter** une demande de congé, **valider/publier** une
évaluation, **archiver/restaurer** un employé, **renouveler/résilier** un contrat. Détail
complet : `GUIDE_ADMINISTRATEUR.md` § 8, workflows § 2.7 et § 2.8.

---

## 9. Comptes utilisateurs

`/utilisateurs` :
- **Consulter**, **créer**, **modifier** un compte (nom, email, rôle, mot de passe).
- **Désactiver / réactiver** un compte.
- **Supprimer un compte** — **non disponible pour ce rôle**, contrairement à
  l'Administrateur. Pour un départ, désactivez le compte plutôt que de chercher à le
  supprimer.

---

## 10. Paramètres de l'établissement

`/parametres/*` : Direction accède aux sections pour lesquelles son rôle porte une permission
`settings.*` explicite (`config/permissions.php`) — l'accès est **permission par section**, pas
un statut générique « comme Administrateur ». Voir `GUIDE_ADMINISTRATEUR.md` § 12 pour le détail
de chaque section.

**Sections accessibles à Direction** (permission `settings.*.view`, correspondance directe avec
`config/permissions.php`) : Établissement, Année scolaire, Organisation académique, Notation,
Finances, Documents, Apparence, Notifications.

**Paramétrage technique et système — réservé à Administrateur/Super-Admin** : **Sécurité**
(politique de mot de passe, expiration de session) et **Sauvegarde / Avancé** (rétention des
sauvegardes, options techniques) ont été retirées du rôle `directeur` le 21 août 2026
(`settings.securite.*`, `settings.sauvegarde.*`, `settings.avance.*` supprimées de
`config/permissions.php`). Le menu et la page `/parametres` masquent désormais automatiquement
ces deux tuiles pour Direction (filtrage par permission, pas une liste codée en dur) ; un accès
direct par URL renvoie une erreur d'autorisation. **Si votre session a démarré avant cette date,
reconnectez-vous une fois** — les permissions sont figées en session à la connexion.

Non accessible (réservé aux opérateurs de la plateforme, indépendamment du rôle
établissement) : le portail Super-Admin SaaS (`/platform/*`) — gestion multi-établissements,
plans d'abonnement, sauvegardes globales. C'est la seule frontière technique réelle entre
Direction et une administration système au sens strict.

---

## 11. Multi-établissements

Si votre compte est rattaché à plusieurs établissements, utilisez le sélecteur dans l'en-tête
pour basculer sans vous reconnecter — chaque établissement reste entièrement isolé des autres.
