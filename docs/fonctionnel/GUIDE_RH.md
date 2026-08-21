# Guide RH — SCOLARIS V2 / EduNova

Public : personnel en charge des ressources humaines de l'établissement.

> **Note sur les rôles** : il n'existe pas, à ce jour, de rôle RBAC dédié nommé
> "RH" dans l'application (voir `docs/technique/RBAC.md` §1) — le module RH est
> opéré via les rôles **Administrateur** ou **Direction**, ou via un rôle
> personnalisé que votre établissement peut créer. Ce guide décrit les
> fonctionnalités du module, indépendamment du rôle exact qui y accède chez vous.

> **Avertissement environnement** : les écrans ci-dessous supposent que le module
> RH est actif **et** que ses migrations SQL ont été appliquées à la base ciblée
> — c'est le cas dans cet environnement (44 tables `rh_*`, vérifié le 21/08/2026,
> voir `docs/technique/ARCHITECTURE_MODULES.md` §4), mais ce n'est pas garanti sur
> un autre déploiement : `enabled=true` dans `config/modules.php` fait charger les
> routes, pas nécessairement les tables. En cas de doute sur votre installation,
> considérez chaque fonctionnalité de ce guide comme disponible **selon les
> fonctionnalités activées et les permissions du compte**, et vérifiez
> `docs/technique/ARCHITECTURE_MODULES.md` §4 pour l'état réel des migrations.

## 1. Employés

`RH → Employés` : dossier employé complet, données personnelles, poste.

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

`RH → Présences` : pointage du personnel (multi-mode), calcul automatique des
durées, retards, heures supplémentaires ; régularisation tracée et soumise à
validation.

## 6. Congés

`RH → Congés` : demande, solde par type de congé (9 types), validation
hiérarchique (machine d'états à 7 statuts), détection des chevauchements.

## 7. Évaluations

`RH → Évaluations` : campagnes d'évaluation, critères pondérés, auto-évaluation,
plans de développement individuel.

## 8. Formations

`RH → Formations` : catalogue, inscriptions, suivi des certifications et
compétences acquises.

## 9. Documents RH

`RH → Documents` : documents administratifs par employé, versionnés, avec gestion
d'expiration (ex. certificats médicaux, diplômes).
