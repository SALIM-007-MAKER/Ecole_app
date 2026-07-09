# Portails — Framework multi-profils

**État** : module `portals` désactivé (`config/modules.php['portals']['enabled'] = false`)
— code présent, jamais exécuté contre une base réelle dans cet environnement (voir
`docs/technique/ARCHITECTURE_MODULES.md` §4). Ce document décrit l'architecture
telle que construite (Phases 12.1-12.3), à valider en conditions réelles avant
activation.

## 1. Sept portails contextuels

| Portail | Répertoire | Public visé |
|---|---|---|
| Admin | `app/Modules/Portals/Admin/` | Administrateurs |
| Direction | `.../Direction/` | Direction établissement |
| Comptabilité | `.../Comptabilite/` | Comptables |
| RH | `.../RH/` | Gestion des ressources humaines |
| Enseignant | `.../Enseignant/` | Professeurs |
| Élève | `.../Eleve/` | Élèves |
| Parent | `.../Parent/` | Parents |

Chaque portail possède ses propres `Controllers/` et `Widgets/` (composants de
tableau de bord réutilisables), plus des vues dédiées sous `Views/{Portail}/`.

## 2. Framework commun (`app/Modules/Portals/Framework/`)

10 moteurs partagés par les 7 portails (dashboards configurables, widgets,
recherche transverse, navigation contextuelle par profil, etc.) — évite qu'un
portail dupliquant la logique d'un autre. `PortalBaseController`
(`Controllers/PortalBaseController.php`) est la classe de base commune : résolution
de l'utilisateur courant, du contexte d'établissement, rendu (`portalRender()`),
réponses JSON (`portalJson()`), dispatch des événements `PortalAccessed`/`PortalError`.

## 3. Résolution du tenant

`PortalBaseController::getEtablissementId()` résout l'établissement via
`Session::getUser()['etablissement_id']` — **pas** via
`Core\Tenant\TenantContext` (voir `docs/technique/MULTI_TENANT.md` §9). C'est un
mécanisme différent mais fonctionnellement équivalent au reste de l'application
tant que `TenantMiddleware` n'est pas activé.

## 4. Changement de tenant

Un utilisateur multi-établissements (Phase 14.6) change d'établissement actif via
le sélecteur de l'en-tête applicatif (`/auth/switch-school`) — le portail affiché
recharge alors ses données dans le nouveau contexte de session. Aucun mécanisme de
changement de tenant propre aux portails eux-mêmes ; ils héritent de la session
globale.

## 5. Branding dans les portails

Chaque portail est censé hériter automatiquement du branding de l'établissement
actif (`BrandingService::forCurrentRequest()`, voir
`docs/technique/MULTI_TENANT.md` §4) — non re-vérifié en conditions réelles dans
cette phase (module désactivé), à confirmer lors de l'activation.

## 6. API dédiée aux portails

`app/Modules/Portals/Controllers/Api/` expose des endpoints JSON consommés par les
widgets côté client (rafraîchissement partiel sans rechargement de page complet).

## 7. Permissions

`portal.{profil}.access` (ex. `portal.admin.access`, `portal.direction.access`) —
7 permissions dédiées, une par portail, définies dans `module.json` et vérifiées
par `PortalBaseController` avant tout rendu.

## 8. Avant activation en production

1. Appliquer/vérifier les migrations des modules métier consommés par chaque
   portail (Finance/RH/Vie scolaire notamment — voir
   `docs/technique/ARCHITECTURE_MODULES.md` §4).
2. Passer `config/modules.php['portals']['enabled']` à `true`.
3. Exécuter un test de fumée HTTP réel sur chacun des 7 portails (recommandation
   `RELEASE_CANDIDATE_RC1_REPORT.md` §12.4) avant toute mise à disposition aux
   utilisateurs finaux.

## 9. Documents associés

`PORTALS_PLATFORM_BLUEPRINT.md`, `PORTALS_FRAMEWORK_IMPLEMENTATION_REPORT.md`,
`PORTALS_IMPLEMENTATION_REPORT.md`, `PORTALS_INTEGRATION_REVIEW.md`,
`PRE_PORTAL_REVIEW.md`.
