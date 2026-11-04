# Paiements manuels et revenus SaaS

Étape 5 du Super-Admin SaaS (migration **T070**). Les paiements sont **saisis à la main** par un `super_admin` (espèces, virement, Orange Money, chèque…). Aucun prestataire de paiement n'est branché ; le modèle est prévu pour que cela s'ajoute plus tard sans migration.

- Fiche établissement `/platform/etablissements/{id}` : section « Paiements (saisie manuelle) » (**super_admin uniquement**, un `admin` ne voit aucune donnée de paiement).
- Reçu / fiche d'un paiement : `/platform/paiements/{id}` (imprimable).
- Revenus : `/platform/revenus` (menu « Revenus »), export CSV `/platform/revenus/export`. **super_admin uniquement.**

## Modèle

`platform_payments` : un encaissement, rattaché à **une période d'abonnement** (`platform_subscriptions`) et à l'établissement.

| Champ | Règle |
|---|---|
| `reference` | `PAY-AAAA-NNNNNN` (année du paiement + identifiant), unique, attribuée à la saisie |
| `montant`, `devise` | montant réellement encaissé ; la devise est **toujours celle de la période** (aucune conversion). XOF/XAF : entier obligatoire |
| `mode` | `especes`, `virement`, `orange_money`, `mobile_money`, `cheque`, `autre` (VARCHAR : un nouveau fournisseur = une ligne dans `PlatformPaymentService::MODES`, aucune migration) |
| `reference_externe` | n° de virement / de transaction ; **obligatoire** pour virement, Orange Money et mobile money |
| `date_paiement` | date réelle de l'encaissement, pas dans le futur (fuseau `PLATFORM_TIMEZONE`, défaut `Africa/Niamey`) |
| `statut` | `recorded` ou `cancelled` |

## Règles

- **Un paiement ne renouvelle rien.** Il est indépendant de l'abonnement : le renouvellement reste une décision explicite (étape 4). Un renouvellement ne crée pas de paiement.
- **Un essai ne se paie pas.** Une période d'essai refuse tout paiement.
- **Pas de dépassement.** Le total encaissé d'une période ne peut pas dépasser son prix figé. Le solde est contrôlé sous verrou de la période : deux saisies simultanées ne peuvent pas le dépasser. Un paiement d'avance se saisit sur la période suivante, une fois l'abonnement renouvelé. Période reprise de l'existant (prix inconnu) : acceptée, sans plafond.
- **Double saisie évitée.** Même mode + même référence d'opération = refusé, tant que le premier paiement n'est pas annulé (contrôle du service **et** index unique en base).
- **Jamais de suppression.** Une erreur se corrige par une **annulation** (motif obligatoire, 255 caractères). La ligne, l'opérateur, la date et le motif sont conservés ; le montant libère le solde de la période et sort des revenus.
- **Intégrité en base.** Deux déclencheurs : `DELETE` interdit ; `UPDATE` limité à l'annulation (`recorded` → `cancelled`). Montant, devise, mode, date, période, référence et note ne sont plus modifiables après la saisie ; une annulation ne se défait pas.
- **Audit strict.** `payment.recorded` et `payment.cancelled` sont écrits dans le journal global **dans la même transaction** : si l'audit est impossible, la saisie ou l'annulation est annulée avec un message clair. L'export des revenus est lui-même journalisé (`revenue.exported`).

### Situation d'une période

| État | Sens |
|---|---|
| Impayé | aucun encaissement |
| Partiel | encaissé < prix figé |
| Soldé | encaissé = prix figé |
| Prix inconnu | période reprise de l'existant |
| Essai | rien à régler |

## Revenus

- Les totaux sont **toujours donnés par devise** ; deux devises ne sont jamais additionnées ni converties.
- Seuls les paiements `recorded` comptent ; les annulés sont comptés à part.
- Période : filtre `du` / `au` sur `date_paiement`, bornes incluses. Valeurs invalides : année en cours jusqu'à aujourd'hui ; bornes inversées : remises dans l'ordre.
- Vues : total et nombre, répartition par mois, par mode de paiement, par plan.
- **Reste à encaisser** : prix figés des périodes en cours (`active`, `grace`, `cancelled`) moins les encaissements. Indépendant du filtre de dates ; les périodes à prix inconnu et les essais n'y figurent pas.
- Export CSV (séparateur `;`, UTF-8 avec BOM) : tous les statuts, l'annulation reste visible ; les cellules commençant par `=`, `+`, `-`, `@` sont neutralisées (injection de formule).

## Migration T070

Additive et idempotente : crée `platform_payments` et ses deux déclencheurs. Aucune table existante n'est modifiée. Avant son application, le code se comporte comme avant (section « Paiements » absente, page « Revenus » indiquant que le suivi n'est pas activé). Retour arrière : supprime la table (**les encaissements saisis sont perdus : sauvegarde préalable**).

Après application : régénérer le jeu de référence des tests (`php tests/Support/fixtures/mettre_a_jour.php`) — déjà fait dans ce dépôt.

## Non couvert

Remboursements (une annulation n'en est pas un), reçus envoyés par e-mail, rapprochement bancaire, paiement en ligne, renouvellement automatique (`auto_renew` reste sans effet), tableau de revenus récurrents (MRR), 2FA.
