# Guide Direction — SCOLARIS V2 / EduNova

Public : rôle **Direction** (`directeur`). Pilotage global de l'établissement,
accès à la quasi-totalité des données à l'exception du paramétrage technique
réservé à l'Administrateur.

## 1. Connexion et tableau de bord

Identique au parcours Administrateur (`docs/fonctionnel/GUIDE_ADMINISTRATEUR.md`
§1). Le tableau de bord Direction met l'accent sur les indicateurs de pilotage :
effectifs, taux de réussite, absentéisme, situation financière (si le module
Finance est opérationnel dans votre environnement).

## 2. Vue d'ensemble académique

- **Élèves & Classes** : consultation et gestion complète.
- **Notes & Bulletins** : suivi des moyennes, génération et export des bulletins
  PDF, classements par classe/niveau/général, mentions automatiques.
- **Rapports** (`/rapports/*`, si le module Rapports & BI est actif) : tableaux de
  bord analytiques transverses (scolaire, présences, réussite).

## 3. Vie scolaire

Absences et présences (validation), retards, discipline, récompenses, emploi du
temps de l'établissement. *(Module actif en configuration mais données non
appliquées dans certains environnements — voir
`docs/technique/ARCHITECTURE_MODULES.md` §4 si un écran signale une erreur.)*

## 4. Finance (vue de supervision)

Tableau de bord financier, validation de certaines opérations sensibles (selon vos
permissions), rapports. La saisie quotidienne relève en général du rôle Comptable
(`docs/fonctionnel/GUIDE_COMPTABLE.md`).

## 5. Ressources humaines (vue de supervision)

Organigramme, contrats, congés, évaluations — consultation et validation.
`docs/fonctionnel/GUIDE_RH.md` détaille la gestion opérationnelle quotidienne.

## 6. Paramètres établissement

Branding, quotas de stockage — mêmes écrans que pour l'Administrateur
(`/parametres/branding`, `/parametres/quotas`), sans accès au portail technique
Super-Admin.

## 7. Multi-établissements

Si votre compte est rattaché à plusieurs établissements (groupe scolaire), utilisez
le sélecteur d'établissement dans l'en-tête pour basculer — chaque établissement
conserve ses propres données, entièrement isolées des autres.
