# Guide Comptable — SCOLARIS V2 / EduNova

Public : rôle **Comptable** (`comptable`). Accès centré sur le module Finance.

> **Avertissement environnement** : ce guide décrit les écrans tels que conçus. Le
> module Finance est activé en configuration mais, dans certains environnements
> (dont l'environnement de développement de référence), ses tables ne sont pas
> encore appliquées à la base de données — les écrans afficheraient une erreur
> jusqu'à ce que cette étape soit réalisée par votre administrateur technique (voir
> `docs/technique/ARCHITECTURE_MODULES.md` §4 et
> `docs/deploiement/MISE_A_JOUR.md`).

## 1. Connexion et tableau de bord

`Finance → Tableau de bord` (`finance.dashboard.view`) : synthèse encaissements du
jour/mois, factures en attente, état de caisse.

## 2. Facturation

`Finance → Factures` : émission de factures élève (individuelle ou en masse par
classe/niveau), avoirs, suivi des impayés. Machine d'états (brouillon → émise →
payée/annulée) — pas de suppression physique, uniquement annulation tracée.

## 3. Encaissements

`Finance → Paiements` : enregistrement d'un paiement, génération de reçu, gestion
des trop-perçus et remboursements. Annulation d'un paiement tracée et justifiée.

## 4. Caisse

`Finance → Caisse` : ouverture/fermeture de caisse journalière, mouvements,
rapprochement en fin de journée.

## 5. Comptabilité générale

`Finance → Comptabilité` : plan comptable (PCG), saisie d'écritures, grand livre,
balance. Clôture d'exercice réservée aux utilisateurs habilités.

## 6. Rapports financiers

`Finance → Rapports` : export CSV/Excel/PDF, tableaux de bord dédiés.

## 7. Modules connexes accessibles

Consultation de l'inventaire (commandes, stock), documents RH en lecture/export,
selon les droits accordés à votre compte.
