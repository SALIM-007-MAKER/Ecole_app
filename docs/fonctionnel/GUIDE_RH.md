# Guide RH — SCOLARIS V2 / EduNova

Public : personnel en charge des ressources humaines de l'établissement.

> **Périmètre des rôles** : le module RH est accessible selon les permissions du
> compte. Selon la configuration de l'établissement, il peut être utilisé par la
> Direction, un Administrateur, ou un rôle personnalisé disposant des
> permissions RH nécessaires — détail des rôles et permissions dans
> `docs/technique/RBAC.md`.

> **Disponibilité** : les fonctionnalités présentées sont disponibles lorsque le
> module RH est activé et que les migrations nécessaires sont appliquées. Les
> fonctionnalités visibles dépendent également des permissions de votre compte.
> Détails techniques (activation, migrations, tables) : voir
> `docs/technique/ARCHITECTURE_MODULES.md` §4.

## 1. Employés

`RH → Employés` : dossier employé complet (données personnelles, poste).
Consultation, création et modification selon vos permissions — certains
comptes (ex. Secrétariat) peuvent n'avoir accès qu'en consultation, sans
pouvoir créer ou modifier une fiche.

## 2. Organisation

`RH → Organigramme` : structure hiérarchique de l'établissement, services,
rattachements.

## 3. Contrats

`RH → Contrats` : machine d'états (brouillon → actif → terminé/résilié), avenants,
renouvellement, expiration automatique surveillée.

## 4. Affectations

`RH → Affectations` : affectation principale/secondaire/temporaire d'un employé à
un poste ou une matière (pour le personnel enseignant), historique conservé.

## 5. Présences RH

`RH → Présences` : pointage du personnel — le mode utilisé est précisé parmi
manuel, badge, QR code ou biométrie, selon l'équipement de votre
établissement. Calcul automatique des durées, retards, heures supplémentaires ;
régularisation tracée et soumise à validation.

## 6. Congés

`RH → Congés` : demande de congé, suivi du solde par type, workflow de
validation hiérarchique, détection automatique des chevauchements entre
demandes. Le détail des types de congés et des statuts possibles dépend de la
configuration de votre établissement.

## 7. Évaluations

`RH → Évaluations` : campagnes d'évaluation, critères pondérés, auto-évaluation,
plans de développement individuel.

## 8. Formations

`RH → Formations` : catalogue, inscriptions, suivi des certifications et
compétences acquises.

## 9. Documents RH

`RH → Documents` : documents administratifs par employé, versionnés, avec gestion
d'expiration (ex. certificats médicaux, diplômes).

## 10. Confidentialité et accès

Le module RH manipule des données plus sensibles que les modules Élève ou
Enseignant (données personnelles, contractuelles, disciplinaires) :

- Les dossiers employés ne sont accessibles qu'aux comptes disposant des
  permissions RH nécessaires.
- Les informations personnelles et contractuelles sont protégées par ce même
  contrôle d'accès — un compte sans la permission requise n'y a pas accès,
  même en lecture.
- Les actions importantes (création, modification, validation) sont tracées.
- Vous ne voyez que les données correspondant à vos permissions : par exemple,
  un compte en consultation seule ne pourra ni créer ni modifier une fiche, et
  certains rôles ne voient que leur propre dossier plutôt que celui de tous
  les employés.

Si vous constatez un accès qui semble incorrect (donnée visible qui ne
devrait pas l'être, ou inversement), signalez-le immédiatement à votre
établissement.
