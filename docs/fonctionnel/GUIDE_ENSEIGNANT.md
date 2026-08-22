# Guide Enseignant — SCOLARIS V2 / EduNova

Public : rôle **Enseignant** (`enseignant`). Accès centré sur vos classes et
matières assignées.

## 1. Connexion

`https://votre-domaine/login`. Votre tableau de bord affiche votre fiche
enseignant, le nombre de classes/matières/élèves qui vous sont assignés pour
l'année en cours, et la liste de vos enseignements (classe + matière). Il
n'affiche pas de liste de tâches en attente ni votre emploi du temps du jour
— pour ça, consultez `Emploi du temps` (§4).

## 2. Saisie des notes et appréciations

`Notes` (menu) → sélectionner la classe, la matière, le contrôle. Saisie en grille
(un élève par ligne). Le circuit complet, jusqu'au bulletin :

1. **Vous saisissez** les notes (uniquement pour vos matières/classes assignées).
2. **Le système calcule** votre moyenne par matière automatiquement, à l'affichage.
3. **La Direction déclenche** le calcul officiel du classement — pas le Secrétariat,
   qui n'a pas cette permission.
4. **La Direction génère et publie** les bulletins — même remarque : le
   Secrétariat n'a pas les droits de génération/publication des bulletins dans
   cette version.

### Appréciations par matière

`Appréciations` (`/v2/academique/appreciations`) : saisie de l'appréciation qui
apparaît en face de votre matière sur le bulletin de l'élève, pour une classe,
une matière et une période que vous choisissez. Réservé à vos propres
matières/classes assignées (vérifié à l'enregistrement) — l'application ne
limite pas actuellement cette saisie aux seules périodes encore ouvertes,
donc vérifiez la période sélectionnée avant d'enregistrer.

## 3. Présences, absences et retards

Pointage de présence par séance (`Vie scolaire → Absences`) : marquer
présent/absent/retard pour chaque élève de la séance en cours. Motif et durée
peuvent être précisés pour un retard. Vous pouvez également valider ou
refuser une justification d'absence soumise par un parent.

## 4. Emploi du temps

`Emploi du temps` : votre grille hebdomadaire, salles assignées, éventuels
remplacements.

## 5. Consultation

- **Bulletins** de vos classes (lecture).
- **Discipline** : consultation des dossiers de vos élèves, mais aussi **signalement
  d'un incident** et mise à jour de son traitement — vous ne pouvez pas prononcer
  de sanction, réservée à Direction/Secrétariat.
- **Activités scolaires** : création et suivi si vous encadrez une activité
  (inscriptions élèves, liste d'attente).
- **Bibliothèque** *(module non activé actuellement)* : une fois activée,
  recherche du catalogue et emprunt/réservation, selon vos droits.

## 6. Communication *(module non activé actuellement)*

Une fois activé par votre établissement (`Communication` au menu) : envoi de
messages/annonces. À noter : dans l'implémentation actuelle, l'envoi de
messages n'est pas limité à vos classes ni à un rôle de destinataire précis —
à utiliser de façon responsable, conformément au règlement de votre
établissement.

## 7. Ce que vous ne voyez pas

Les données financières, RH, et les élèves/classes qui ne vous sont pas assignés
sont hors de votre périmètre d'accès — c'est un comportement normal du contrôle
d'accès (voir `docs/technique/RBAC.md`), pas une erreur.
