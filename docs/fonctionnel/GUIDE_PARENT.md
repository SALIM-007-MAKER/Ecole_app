# Guide Parent — SCOLARIS V2 / EduNova

Public : rôle **Parent** (`parent`). Accès **principalement en consultation**
aux informations scolaires et financières de vos enfants, avec quelques
actions autorisées (soumission d'une justification d'absence — §2 —, messagerie
si le module est activé — §4). Il n'existe pas de compte ou de portefeuille
financier propre au parent : toutes les données financières affichées sont
celles de vos enfants.

## 1. Connexion

`https://votre-domaine/login`. Si vous avez plusieurs enfants dans le même
établissement, un sélecteur vous permet de basculer entre leurs profils.

## 2. Suivi scolaire de mon enfant

- **Notes** et **Bulletins** (lecture, téléchargement PDF).
- **Absences et retards** : consultation de l'historique, et **soumission** d'une
  justification (`Absences → Justifier`) lorsqu'une absence n'est pas encore
  justifiée. Le circuit complet :
  1. Vous consultez l'absence de votre enfant.
  2. Vous fournissez un motif et soumettez la justification.
  3. Elle passe alors au statut **« en attente »** — ce n'est pas une
     justification automatique.
  4. L'établissement (enseignant ou secrétariat, selon l'organisation choisie)
     l'examine et la passe à **« justifiée »** ou **« refusée »**.
- **Emploi du temps** de la classe de votre enfant.
- **Discipline** et **récompenses** : consultation des incidents et sanctions
  disciplinaires, ainsi que des récompenses, concernant vos enfants — en
  lecture seule, vous ne pouvez pas signaler un incident vous-même (à la
  différence de l'enseignant).

## 3. Frais et paiements scolaires

`Scolarité et paiements` (menu) : situation des frais de scolarité de votre
enfant, historique des paiements déjà effectués par l'établissement pour son
compte — **en lecture seule**, aucun paiement en ligne n'est possible
actuellement. Pour tout paiement, contactez le secrétariat/la comptabilité de
l'établissement (voir `docs/fonctionnel/GUIDE_COMPTABLE.md` pour le processus
côté établissement). Circuit actuel : frais → facture → paiement enregistré
par l'établissement → reçu — sans étape « paiement en ligne » à ce jour ; ce
guide sera mis à jour si un moyen de paiement en ligne (Mobile Money, carte…)
est ajouté.

## 4. Communication

Consultation des annonces de l'établissement et de la classe de votre enfant —
disponible dès maintenant.

`Messagerie` *(module non activé actuellement)* : une fois activée, elle
permettra d'envoyer des messages. À noter : dans l'implémentation actuelle,
l'envoi n'est pas limité à un rôle de destinataire précis (enseignant,
administration…) — à utiliser de façon responsable, conformément au règlement
de votre établissement, une fois le module activé.

## 5. Activités et bibliothèque

**Activités scolaires** : consultation des activités auxquelles votre enfant
est inscrit.

**Bibliothèque** *(module non activé actuellement)* : une fois activée,
recherche du catalogue.

## 6. Plusieurs enfants, plusieurs établissements

Vérifié dans le code (pas seulement prévu à l'architecture) : un sélecteur
« Changer d'établissement » existe réellement dans le menu de votre compte, en
haut à droite — il apparaît uniquement si votre **propre compte** (et non vos
enfants) est rattaché à plusieurs établissements. Les données de chaque
établissement restent entièrement séparées.

Deux précisions importantes :
- Ce rattachement multi-établissement de votre compte est mis en place par un
  administrateur — il n'est pas créé automatiquement du seul fait que vos
  enfants sont scolarisés dans des établissements différents.
- Dans l'installation actuelle, un seul établissement est enregistré au total :
  le sélecteur ne peut donc pas encore apparaître concrètement pour qui que ce
  soit. Il s'activera de lui-même dès qu'un second établissement existera et
  que votre compte y sera rattaché.
