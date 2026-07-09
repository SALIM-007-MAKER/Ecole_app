# Guide Utilisateur — SCOLARIS V2.0.0 / EduNova

Document de référence de release (Phase 16.0), couvrant tous les profils hors
Administrateur (voir `ADMIN_GUIDE.md`). Détail complet par profil :
`docs/fonctionnel/GUIDE_{DIRECTION,ENSEIGNANT,COMPTABLE,RH,ELEVE,PARENT}.md`.

## Connexion (tous profils)

`https://votre-domaine/login`. Si votre compte est rattaché à plusieurs
établissements, un sélecteur apparaît après connexion et reste accessible dans
l'en-tête pour changer de contexte à tout moment.

## Direction

Pilotage global : élèves/classes, notes/bulletins/classements, vie scolaire
(absences, discipline, emploi du temps), supervision Finance et RH, rapports
analytiques. Détail : `docs/fonctionnel/GUIDE_DIRECTION.md`.

## Enseignant

Saisie des notes par classe/matière/contrôle, pointage de présence par séance,
consultation de l'emploi du temps, gestion des activités encadrées. Détail :
`docs/fonctionnel/GUIDE_ENSEIGNANT.md`.

## Comptable

Facturation, encaissements, caisse, comptabilité générale (PCG), rapports
financiers. *Module Finance activé en configuration mais données non appliquées
à cette version — voir avertissement `docs/fonctionnel/GUIDE_COMPTABLE.md`.*

## RH (Ressources Humaines)

Employés, organigramme, contrats, affectations, présences, congés, évaluations,
formations. Aucun rôle RBAC "RH" dédié — opéré via Administrateur/Direction ou un
rôle personnalisé. *Module activé en configuration mais données non appliquées à
cette version — voir `docs/fonctionnel/GUIDE_RH.md`.*

## Élève

Consultation en lecture seule de vos propres notes, bulletins, absences, emploi
du temps, discipline/récompenses, activités, bibliothèque. Détail :
`docs/fonctionnel/GUIDE_ELEVE.md`.

## Parent

Suivi scolaire et financier (lecture seule) de vos enfants, communication avec
l'établissement, sélecteur multi-enfants/multi-établissements. Détail :
`docs/fonctionnel/GUIDE_PARENT.md`.

## Confidentialité et accès

Chaque profil ne voit que les données auxquelles son rôle donne accès (RBAC —
voir `ARCHITECTURE.md` §3) — c'est un comportement attendu, pas une erreur si un
écran ou une donnée n'apparaît pas.
