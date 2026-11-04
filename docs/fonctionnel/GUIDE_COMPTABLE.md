# Guide Comptable — SCOLARIS V2

**Public :** compte avec le rôle **Comptable**. Accès centré sur le module Finance
(`/v2/finance/*`) — ce guide détaille, écran par écran, **les actions concrètes**
disponibles, pas seulement ce que chaque écran affiche.

> Document mis à jour le 21 août 2026, vérifié directement dans le code
> (`app/Modules/Finance/Controllers/*`, `Services/*`) plutôt que supposé — voir la
> note de fiabilité en § 8.

## Sommaire

1. [Connexion et tableau de bord](#1-connexion-et-tableau-de-bord)
2. [Deux niveaux distincts : Finance scolaire et Comptabilité générale](#2-deux-niveaux-distincts--finance-scolaire-et-comptabilité-générale)
3. [Frais scolaires](#3-frais-scolaires)
4. [Factures](#4-factures)
5. [Paiements (encaissements)](#5-paiements-encaissements)
6. [Caisse](#6-caisse)
7. [Décaissements (dépenses)](#7-décaissements-dépenses)
8. [Comptabilité générale — ce qui est réellement disponible](#8-comptabilité-générale--ce-qui-est-réellement-disponible)
9. [Rapports financiers](#9-rapports-financiers)
10. [Points de vigilance](#10-points-de-vigilance)

---

## 1. Connexion et tableau de bord

`Finance → Vue d'ensemble` (`/v2/finance/rapports/dashboard`) : encaissements du jour/mois,
factures en attente, état de caisse — point d'entrée, pas d'action à proprement parler.

---

## 2. Deux niveaux distincts : Finance scolaire et Comptabilité générale

Le module Finance couvre deux choses différentes, et il vaut mieux le savoir avant de
naviguer :

- **Finance scolaire** (§ 3 à 7) — frais, factures élèves, paiements, reçus, caisse,
  décaissements. C'est là que vous passez le plus clair de votre temps : des écrans avec de
  vrais formulaires, des actions à cliquer, des workflows à plusieurs étapes.
- **Comptabilité générale** (§ 8) — plan comptable, journal, grand livre, balance, exercices.
  **Presque tout y est généré automatiquement** à partir de la Finance scolaire (un
  encaissement, un décaissement, une annulation de facture y créent une écriture tout seuls) —
  ce n'est **pas** un outil de saisie comptable manuelle. Le § 8 précise exactement ce qui est
  actionnable là-dedans, pour ne pas chercher un bouton qui n'existe pas.

---

## 3. Frais scolaires

`Finance → Frais scolaires` (`/v2/finance/frais`) — référentiel préalable à toute facturation.

**Catégories de frais** (`/v2/finance/frais/categories`)
- Créer une catégorie (scolarité, cantine, transport…)
- Modifier une catégorie
- Activer / désactiver une catégorie

**Types de frais**
- Créer un type de frais, rattaché à une catégorie
- Consulter le détail (tarifs associés)
- Modifier
- Ajouter un tarif (`storeTarif`) — un type de frais peut avoir plusieurs tarifs (ex. par
  niveau, par période)
- Activer / Désactiver (retire ou remet le type disponible à la facturation, sans le perdre)
- Archiver
- Supprimer (réservé aux types jamais utilisés dans une facture)

---

## 4. Factures

`Finance → Factures` (`/v2/finance/factures`).

**Créer**
- Facture individuelle : `Nouvelle facture` — élève, lignes de frais, chacune éditable tant
  que la facture reste en **brouillon**.
- Facturation en masse : `Générer` (`/v2/finance/factures/generer`) — choisissez une **classe
  ou un niveau**, une **année scolaire**, un ou plusieurs **types de frais** : une facture est
  créée pour chaque élève concerné en une fois.

**Rechercher / filtrer** (liste `/v2/finance/factures`) : recherche libre, **statut** (7 valeurs
réelles : `brouillon`, `émise`, `partiellement payée`, `payée`, `en retard`, `annulée`,
`archivée` — filtrer sur *en retard* ou *partiellement payée* est la façon la plus directe de
retrouver les impayés depuis cet écran), année scolaire, élève, classe, niveau, plage de dates.

**Consulter le détail** : lignes, remises, échéancier de paiement, historique des paiements
déjà reçus sur cette facture, avoirs déjà émis pour l'élève.

**Modifier** — uniquement tant que le statut est `brouillon` : lignes, remise, échéancier.

**Émettre** — fait passer `brouillon → émise`, verrouille le contenu.

**Imprimer** — vue imprimable dans le navigateur (`/v2/finance/factures/{id}/print`) ; pas de
génération PDF séparée, c'est l'impression/export PDF du navigateur sur cette page.

**Annuler** — motif obligatoire ; trace conservée, la facture reste consultable avec son
statut `annulée`.

**Archiver** — range la facture sans la supprimer.

**Supprimer** — **réservé aux factures encore en `brouillon`** ; au-delà, seules
Annuler/Archiver sont possibles (suppression physique bloquée par le code, pas juste une
question de permission).

**Avoir** — il n'y a **pas** de bouton « Générer un avoir » sur une facture. Un avoir naît
uniquement du traitement d'un **trop-perçu** côté Paiements (§ 5) — voir ce détail avant de le
chercher ici.

---

## 5. Paiements (encaissements)

`Finance → Paiements` (`/v2/finance/paiements`).

**Enregistrer un paiement** : `Nouveau paiement`, toujours rattaché à **une facture** dont le
statut le permet (`émise`, `partiellement payée` ou `en retard` — une facture `brouillon` ou
déjà `payée` n'est pas proposée). Champs : montant, date, **mode de paiement** (liste
configurée par l'établissement), référence externe si applicable. Un **avoir disponible** sur
l'élève (issu d'un trop-perçu antérieur, voir plus bas) peut être appliqué au passage.

**Consulter le détail / l'historique** : fiche du paiement, et depuis une facture,
`/v2/finance/factures/{id}/paiements` liste tous les paiements reçus sur elle.

**Générer et imprimer le reçu** : `Reçu` (`/v2/finance/paiements/{id}/recu`) — généré à la
première consultation puis figé (même principe que les bulletins : une modification
ultérieure du paiement ne met pas à jour un reçu déjà généré) ; version imprimable séparée
(`/recu/print`).

**Annuler un paiement** — motif obligatoire.

**Rembourser** — distinct d'annuler : enregistre un remboursement effectif (montant, mode)
sur un paiement déjà encaissé.

**Trop-perçu** (quand un règlement dépasse ce qui était dû) — trois traitements possibles,
choisis au cas par cas depuis la fiche du paiement concerné :
- **Restituer** — remboursé à la famille.
- **Imputer** — converti en **avoir**, réutilisable sur une facture future du même élève
  (c'est ce mécanisme, et lui seul, qui produit un avoir).
- **Annuler** le trop-perçu constaté (erreur de saisie, par exemple).

---

## 6. Caisse

`Finance → Caisse du jour` (`/v2/finance/caisse`).

**Ouvrir une caisse** : `Nouvelle caisse` — fond de caisse initial. Une seule caisse active à
la fois par utilisateur/poste (`/v2/finance/caisse/active`).

**Enregistrer un mouvement manuel** — entrée ou sortie d'espèces hors paiement élève (ex.
appoint, dépôt en banque). Les paiements élèves encaissés en espèces alimentent la caisse
automatiquement, sans saisie manuelle.

**Annuler un mouvement** — retire un mouvement saisi par erreur.

**Fermer la caisse** : saisissez le **solde réel compté** (le formulaire refuse un montant
négatif) + une note optionnelle — le système compare au solde théorique et signale l'écart au
**rapprochement**.

**Rapprocher** — étape de contrôle associée à la fermeture, trace l'écart constaté.

**Imprimer le journal de caisse** (`/v2/finance/caisse/{id}/journal/print`).

---

## 7. Décaissements (dépenses)

`Finance → Décaissements` (`/v2/finance/decaissements`) — voir aussi le workflow détaillé
dans `docs/fonctionnel/GUIDE_ADMINISTRATEUR.md` § 2.9. En résumé, les actions disponibles :

- Gérer les **fournisseurs** et les **catégories de dépense** (créer, modifier, activer/désactiver).
- **Créer une demande** de décaissement (libellé, montant, date, catégorie, fournisseur, description, échéance).
- **Joindre des justificatifs** à tout moment sur la demande.
- **Valider** (contrôle de premier niveau).
- **Approuver** — obligatoire seulement au-delà du seuil configuré (50 000 par défaut), et
  nécessite alors au moins un justificatif déjà joint.
- **Payer** — accessible dès *Validé* si le montant est sous le seuil, sinon seulement après
  *Approuvé* ; demande le mode de paiement.
- **Rejeter** (motif obligatoire) ou **Annuler**.

---

## 8. Comptabilité générale — ce qui est réellement disponible

`Finance → Comptabilité` (`/v2/finance/comptabilite`).

**À savoir avant d'y aller** : il n'existe **aucun écran de saisie d'écriture manuelle**. Le
plan comptable, le journal, le grand livre et la balance sont des **vues de consultation**,
alimentées automatiquement par les événements de la Finance scolaire :

| Événement en Finance scolaire | Écriture générée automatiquement |
|---|---|
| Un paiement est encaissé | Écriture de recette |
| Un remboursement est effectué | Écriture d'ajustement |
| Un mouvement de caisse est enregistré | Écriture de trésorerie |
| Une dépense est payée (décaissement) | Écriture de charge |
| Une facture est annulée | Écriture de correction |

**Ce que vous pouvez réellement faire ici** :
- **Consulter** : plan comptable, journal, grand livre, balance — lecture seule.
- **Consulter une écriture** en détail (`/v2/finance/comptabilite/ecritures/{id}`).
- **Extourner une écriture** — la seule façon de corriger une erreur ; motif obligatoire.
  Jamais de suppression ou de modification directe d'une écriture déjà passée.
- **Gérer les exercices comptables** : créer un exercice, **clôturer une période**,
  **clôturer un exercice** — ce sont les seules actions de cycle de vie disponibles.

Si votre établissement a besoin de passer des écritures qui ne découlent d'aucun événement
Finance scolaire (ex. amortissements, régularisations diverses), **cette fonctionnalité
n'existe pas encore dans l'application** — à traiter hors outil pour l'instant, ou à signaler
comme besoin si vous en avez un usage récurrent.

---

## 9. Rapports financiers

`Finance → Rapports financiers` (`/v2/finance/rapports`) : tableau de bord, rapports
paiements/factures/impayés/caisse/analytique — consultation et export (CSV/Excel/PDF) sur
chaque écran, pas d'action de saisie.

---

## 10. Points de vigilance

- **Comptabilité générale = miroir automatique, pas un outil de saisie.** Ne cherchez pas de
  bouton « Nouvelle écriture » — il n'existe pas (§ 8). Toute correction passe par une
  **extourne**, jamais par une modification directe.
- **Avoir ≠ action facture.** Un avoir se crée uniquement en traitant un trop-perçu côté
  Paiements (§ 5), jamais depuis l'écran Factures.
- **Suppression très limitée.** Seules les factures encore en `brouillon` peuvent être
  supprimées physiquement — au-delà, Annuler/Archiver sont les seules options, et c'est voulu
  (traçabilité comptable).
- **Reçus et bulletins partagent le même piège** : générés une fois puis figés. Vérifiez
  qu'un paiement est correctement enregistré *avant* de générer son reçu.

---

*Ce guide a été vérifié directement dans le code source (contrôleurs et services Finance) le
21 août 2026, à la demande explicite d'un utilisateur qui doutait — à raison — qu'une
« saisie d'écritures » et une génération d'avoir depuis une facture existent réellement.
Les deux ont été retirées de ce document une fois leur absence confirmée.*
