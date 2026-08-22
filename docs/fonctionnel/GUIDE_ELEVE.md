# Guide Élève — SCOLARIS V2 / EduNova

Public : rôle **Élève** (`eleve`). Accès **principalement en consultation**,
avec quelques actions autorisées selon les fonctionnalités et les droits
activés par votre établissement. Dans tous les cas, vous ne voyez jamais les
données d'un autre élève.

> Les modules **Bibliothèque** (§7) et **Communication** (§8) ne sont pas
> activés dans cette installation au 22/08/2026 — les fonctionnalités
> décrites plus bas ne sont pas accessibles tant qu'un administrateur ne les
> aura pas activées (`config/modules.php`).

## 1. Connexion

`https://votre-domaine/login` avec les identifiants fournis par votre
établissement.

## 2. Mes notes et bulletins

`Notes` : pour chaque matière, le détail de vos notes par contrôle (avec
barème et coefficient de chaque évaluation) et votre moyenne par matière.

`Bulletins` : bulletin du semestre en cours et des semestres précédents
(ou de la période en cours/précédentes, selon le découpage — semestriel ou
trimestriel — configuré par votre établissement) —
moyenne générale, rang de classement (calculé automatiquement ; peut être
absent si les données de la classe ne permettent pas encore de le déterminer),
mention, et appréciations par matière ainsi qu'une appréciation générale —
téléchargeables en PDF.

## 3. Mes absences et retards

`Absences` : historique de vos absences et retards — date, type (absence ou
retard), statut (non justifiée, en attente, justifiée ou refusée) et, si
disponible, l'observation de la personne ayant enregistré l'absence ainsi que
le motif une fois une justification traitée. Une absence est enregistrée par
journée (et non par cours) : elle ne précise donc pas la matière concernée.

**Vous ne pouvez pas soumettre vous-même une justification** — seul un parent
en a le droit dans l'application ; adressez-vous à votre établissement ou à
votre parent/tuteur pour qu'une justification soit déposée.

## 4. Mon emploi du temps

`Emploi du temps` : votre grille de cours de la semaine.

## 5. Discipline et récompenses

Consultation des incidents et sanctions disciplinaires vous concernant, ainsi
que de vos récompenses et distinctions.

## 6. Activités scolaires

Consultation des activités auxquelles vous êtes inscrit(e).

## 7. Bibliothèque *(module non activé actuellement)*

Une fois activé par votre établissement : recherche du catalogue, emprunt et
réservation d'ouvrages selon ses règles.

## 8. Communication

Consultation des annonces de l'établissement et de vos classes — disponible
dès maintenant.

`Messagerie` *(module non activé actuellement)* : une fois activée, elle
permettra d'envoyer des messages à un ou plusieurs destinataires de votre
établissement. À noter pour cette fonctionnalité : dans l'implémentation
actuelle du code, l'application ne limite pas les destinataires à un rôle
particulier (enseignant, administration…) — à utiliser de façon responsable,
conformément au règlement de votre établissement, une fois le module activé.

## 9. Confidentialité

Toutes les données affichées vous concernent exclusivement. Si vous constatez une
information qui ne vous appartient pas, signalez-le immédiatement à votre
établissement.
