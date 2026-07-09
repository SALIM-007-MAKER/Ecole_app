# Guide Administrateur — SCOLARIS V2.0.0 / EduNova

Document de référence de release (Phase 16.0). Détail complet :
`docs/fonctionnel/GUIDE_ADMINISTRATEUR.md`. Pour les autres profils
(Direction, Enseignant, Comptable, RH, Élève, Parent), voir `USER_GUIDE.md`.

## 1. Connexion et gestion des utilisateurs

`/login`, sélecteur d'établissement si votre compte est multi-établissements.
`Utilisateurs` : créer/modifier/désactiver un compte, assigner un rôle (Direction,
Secrétariat, Comptable, Enseignant, Parent, Élève). Un compte peut appartenir à
plusieurs établissements avec un rôle différent dans chacun.

## 2. Élèves, Classes, Enseignants

CRUD complet, import CSV, export PDF/Excel, affectation professeur↔matière↔classe.

## 3. Paramètres établissement

| Écran | Usage |
|---|---|
| `/parametres/branding` | Logo, couleurs, police, image de connexion |
| `/parametres/domaines` | Sous-domaine / domaine personnalisé |
| `/parametres/quotas` | Consommation stockage/utilisateurs/élèves |
| `/parametres/monitoring` | État cache et files d'attente |

## 4. Portail Super-Admin SaaS (`/platform/*`)

**Réservé** aux comptes ayant une entrée dédiée dans `platform_operators` (RBAC
totalement distinct du rôle établissement — voir `ARCHITECTURE.md` §3).

- `/platform/dashboard` — vue plateforme entière (établissements, utilisateurs,
  stockage, files d'attente).
- `/platform/etablissements` — créer, activer, suspendre, archiver, restaurer,
  assigner un plan.
- `/platform/plans` — offres tarifaires et quotas.
- `/platform/backups` (niveau `super_admin` uniquement) — sauvegardes et
  restaurations, voir `DEPLOYMENT.md` §3/§4.

## 5. Points de vigilance à cette version

- Les modules **Finance**, **RH**, **Vie scolaire** sont activés en
  configuration mais leurs données ne sont pas appliquées — leurs écrans
  afficheront une erreur tant que votre administrateur technique n'a pas
  appliqué les migrations correspondantes (voir
  `SCOLARIS_V2_FINAL_RELEASE_REPORT.md` §7).
- Toute suppression est logique (récupérable), jamais définitive immédiatement.

## 6. Support technique

`INSTALLATION.md`, `DEPLOYMENT.md`, `ARCHITECTURE.md`,
`SCOLARIS_V2_FINAL_RELEASE_REPORT.md`.
