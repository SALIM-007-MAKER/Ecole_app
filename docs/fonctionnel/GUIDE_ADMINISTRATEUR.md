# Guide Administrateur — SCOLARIS V2 / EduNova

Public : utilisateurs avec le rôle **Administrateur** (`admin`). Accès le plus
large de l'application, y compris le paramétrage technique et le portail
Super-Admin SaaS.

## 1. Connexion

`https://votre-domaine/login` avec votre email et mot de passe. Si votre compte
appartient à plusieurs établissements, un écran de sélection s'affiche après
connexion — vous pouvez changer d'établissement à tout moment via le sélecteur en
haut de l'écran, sans vous reconnecter.

## 2. Tableau de bord

Vue d'ensemble : effectifs, alertes récentes, raccourcis vers les tâches
fréquentes. Le contenu s'adapte aux modules réellement actifs pour votre
établissement.

## 3. Gestion des utilisateurs et rôles

`Utilisateurs` (menu principal) : créer/modifier/désactiver un compte, assigner un
rôle (Direction, Secrétariat, Comptable, Enseignant, Parent, Élève). Un compte peut
appartenir à plusieurs établissements avec un rôle différent dans chacun.

## 4. Élèves, Classes, Enseignants

CRUD complet, import CSV en masse, export PDF/Excel, affectation
professeur↔matière↔classe. Voir aussi `docs/fonctionnel/GUIDE_DIRECTION.md` pour
les vues de pilotage associées.

## 5. Paramètres établissement (`/parametres/*`)

| Écran | Usage |
|---|---|
| `/parametres/branding` | Logo, favicon, couleurs, police, image de connexion, coordonnées |
| `/parametres/domaines` | Sous-domaine et domaine personnalisé de votre établissement |
| `/parametres/quotas` | Consommation stockage/utilisateurs/élèves, alertes de dépassement |
| `/parametres/monitoring` | État du cache et des files d'attente de votre établissement |

## 6. Portail Super-Admin SaaS (`/platform/*`)

**Réservé** aux comptes ayant en plus une entrée dans la table des opérateurs
plateforme (rôle distinct du rôle établissement — voir
`docs/technique/RBAC.md` §6). Ce n'est pas automatique pour tout Administrateur
d'établissement.

- **Tableau de bord** (`/platform/dashboard`) : établissements, utilisateurs,
  élèves, stockage, files d'attente — vue plateforme entière.
- **Établissements** (`/platform/etablissements`) : créer, activer, suspendre,
  archiver, restaurer, assigner un plan.
- **Plans** (`/platform/plans`) : créer/modifier les offres tarifaires et leurs
  quotas.
- **Sauvegardes** (`/platform/backups`, niveau `super_admin` uniquement) :
  déclencher une sauvegarde, vérifier son intégrité, restaurer un établissement.
  Voir `docs/deploiement/SAUVEGARDES.md`.

## 7. Points de vigilance

- Les modules **Finance**, **RH** et **Vie scolaire** apparaissent activés dans la
  configuration mais leurs données ne sont pas encore appliquées dans certains
  environnements — si un écran de ces modules affiche une erreur, contactez votre
  administrateur technique (voir `docs/technique/ARCHITECTURE_MODULES.md` §4).
- Toute suppression dans l'application est **logique** (récupérable), jamais
  définitive immédiatement.

## 8. Support

Pour toute question technique, voir `docs/deploiement/INSTALLATION.md` et
`docs/technique/ARCHITECTURE_GLOBALE.md`.
