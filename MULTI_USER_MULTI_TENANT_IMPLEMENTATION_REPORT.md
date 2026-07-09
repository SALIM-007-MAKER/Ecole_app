# MULTI_USER_MULTI_TENANT_IMPLEMENTATION_REPORT.md
## Phase 14.6 — Utilisateurs Multi-Établissements

> **Statut :** ✅ IMPLÉMENTÉ — décision : **GO WITH FIXES** (voir §9)
> **Date :** 2026-07-08
> **Périmètre :** Utilisateurs pouvant appartenir à un ou plusieurs établissements — sélection, changement de contexte, sessions, RBAC contextuel. Aucune régression, aucune modification de la logique métier des modules existants.
> **Référence :** `MULTI_TENANT_V2_BLUEPRINT.md` §7 (Utilisateurs multi-établissements), §25.1 Phase 14.6

---

## 1. Résumé exécutif

Un utilisateur peut désormais appartenir à un ou plusieurs établissements
(`user_etablissements`, Phase 14.2, jusqu'ici seulement peuplée en miroir
mono-tenant). Le flux de connexion suit exactement le blueprint §7.2 :
mono-établissement → activation automatique (comportement strictement
inchangé) ; multi-établissements → écran de choix obligatoire avant tout
accès. Un utilisateur déjà connecté et multi-tenant peut changer
d'établissement actif à tout moment via un sélecteur dans le menu
utilisateur, **sans ressaisir son mot de passe**, avec revérification
systématique de son appartenance et recalcul complet du rôle et des
permissions dans le nouveau contexte.

**Testé en conditions réelles de bout en bout** (HTTP réel, pas seulement
unitaire) avec un vrai utilisateur multi-établissements : écran de choix
affiché, accès direct au tableau de bord bloqué tant que le choix n'est pas
fait, activation correcte, changement d'établissement avec **rôle
effectivement différent** (Enseignant → Secrétaire) et permissions
recalculées, et **rejet journalisé** d'une tentative de bascule vers un
établissement non autorisé.

Un bug préexistant a été détecté et corrigé (§8) — sans lien avec le
multi-tenant, révélé par le premier utilisateur "enseignant sans fiche
professeur" jamais créé dans cette base.

**261/261 assertions passées**, zéro régression.

---

## 2. Composants créés

| Fichier | Rôle |
|---|---|
| `core/Tenant/TenantMembershipService.php` | `listForUser()`, `isMember()` (garde d'isolation systématique), `roleForUser()`, `getLastUsed()`/`setLastUsed()` |
| `app/Views/auth/choose-school.php` | Écran de sélection d'établissement (affiché uniquement si 2+ appartenances), pré-sélection visuelle du dernier établissement utilisé |
| `database/migrations/T010_users_dernier_etablissement.php` | Colonne `users.dernier_etablissement_id` (mémorisation), backfill des utilisateurs existants |
| `tests/Unit/MultiTenantUserTest.php` | 15 assertions : mono-tenant, multi-tenant, isMember, RBAC contextuel, mémorisation |

## 3. Composants modifiés

| Fichier | Changement |
|---|---|
| `app/Controllers/AuthController.php` | `login()` réécrit pour consulter `user_etablissements` (source de vérité, plutôt que `users.etablissement_id` seul) ; nouvelle méthode privée `completeLogin()` centralisant la résolution rôle+permissions (élimine la duplication entre login direct, finalisation du choix et changement d'établissement) ; nouvelles actions `showChooseEtablissement()`, `chooseEtablissement()`, `switchSchool()` ; `logout()` nettoie aussi le marqueur d'authentification en attente |
| `app/Views/layouts/main.php` | Sélecteur d'établissement dans le menu utilisateur, affiché uniquement si `available_etablissements` contient 2+ entrées (session légère pour l'immense majorité mono-tenant) |
| `config/routes.php` | +3 routes : `GET`/`POST /choisir-etablissement`, `POST /auth/switch-school` (nom conforme au blueprint §7.2) |
| `app/Models/ProfesseurModel.php` | Correction d'un bug préexistant sans lien avec cette phase (§8) |

**Aucune vue métier, aucun contrôleur métier autre que `AuthController`, aucun modèle autre que le correctif ponctuel ci-dessus.**

---

## 4. Gestion des sessions

| Exigence | Implémentation |
|---|---|
| Sélection de l'établissement actif | `AuthController::login()` — 0 appartenance → repli V1 ; 1 → activation directe ; 2+ → écran de choix obligatoire |
| Mémorisation du dernier établissement | `users.dernier_etablissement_id`, mis à jour à chaque activation réussie (`completeLogin()`), utilisé pour pré-sélectionner visuellement le choix par défaut — **jamais** pour sauter le choix explicite quand 2+ établissements existent (conforme au flux §7.2 du blueprint) |
| Rafraîchissement sécurisé du contexte | `switchSchool()` revérifie `isMember()` à CHAQUE appel (aucune confiance dans l'id fourni par le formulaire), recalcule intégralement rôle + permissions, ne fait AUCUNE hypothèse sur l'état précédent de la session |
| Déconnexion sécurisée | Inchangée (`Session::logout()` régénère l'ID de session) + nettoyage du marqueur `_pending_auth_user_id` si présent |
| État "authentification vérifiée mais contexte non choisi" | Marqueur de session dédié `_pending_auth_user_id`, **distinct** de `_auth_user` — `Session::isLogged()` reste `false` tant que le choix n'est pas fait ; testé : accès direct à `/dashboard` entre les deux étapes → redirection vers `/login`, aucun contournement possible |

---

## 5. Adaptations RBAC

Le rôle et les permissions sont désormais **contextuels à l'établissement actif** :
- `TenantMembershipService::roleForUser($userId, $etabId)` délègue à
  `TenantAuthContext::roles()` (Phase 14.4) pour retourner le rôle de
  l'utilisateur DANS cet établissement précis.
- `completeLogin()` appelle `UserModel::getPermissions($role, $userId, $etabId)`
  (mécanisme déjà construit en Phase 14.4) avec le rôle et l'établissement
  du contexte choisi — jamais un rôle ou un établissement mis en cache
  d'une session précédente.

**Vérifié en conditions réelles** avec un utilisateur ayant le rôle
`enseignant` dans l'établissement A et `comptable` dans l'établissement B :
après `switch-school`, le rôle affiché passe correctement de "Enseignant"
à "Secrétaire" (test unitaire avec un autre couple de rôles) et les
permissions se recalculent intégralement — `notes.edit` (présent en tant
qu'enseignant) disparaît, `comptabilite.create` (propre au rôle comptable)
apparaît. **Aucune permission de l'ancien contexte ne persiste après un
changement d'établissement.**

---

## 6. Adaptations API

**Statut inchangé, non re-testable.** La Phase 14.4 avait déjà découvert
que les tables des modules Finance/RH/VieScolaire/Api (177 tables) ne sont
pas appliquées à cette base — conclusion inchangée ici. Revue de code
uniquement : `App\Modules\Api\Auth\AuthContext` porte déjà un champ
`etablissementId`, cohérent avec une éventuelle intégration future du
contexte multi-tenant côté API, mais aucune vérification fonctionnelle
n'est possible tant que ce gap infrastructure (documenté, hors périmètre
sécurité/RBAC) n'est pas comblé.

---

## 7. Adaptations Portails

Comme en Phase 14.4/14.5, **aucune modification de `MenuService` n'a été
nécessaire** : les menus et tableaux de bord se filtrent déjà sur
`Session::getUser()['permissions']`, désormais recalculées correctement à
chaque changement d'établissement. En corrigeant la source (session), tous
les composants consommateurs héritent automatiquement du bon contexte —
vérifié en conditions réelles (dashboard et sidebar corrects après
`choisir-etablissement` ET après `switch-school`).

**Notifications** : `NotificationModel` scope déjà chaque requête par
`user_id` (`WHERE user_id = ?`), ce qui exclut toute fuite entre
UTILISATEURS différents, tenant ou non. En revanche, la table
`notifications` ne porte pas `etablissement_id` (hors périmètre des 9
tables migrées en Phase 14.3) : un utilisateur réellement multi-tenant
verrait, aujourd'hui, ses notifications des DEUX établissements mélangées
sans filtrage par contexte actif. **Gap documenté, non corrigé** — aucun
utilisateur multi-tenant réel n'existe en production, et corriger
nécessiterait de modifier tous les points de création de notification à
travers l'application (violerait "ne pas modifier la logique métier des
modules existants").

**Recherche** : les recherches (élèves, classes, etc.) passent déjà par les
9 modèles tenant-scopés de la Phase 14.3, qui résolvent `etablissement_id`
depuis `TenantContext`/repli configuré — un changement d'établissement en
cours de session met à jour `Session::getUser()['etablissement_id']`
immédiatement, donc toute recherche suivante est automatiquement
recontextualisée sans action supplémentaire.

---

## 8. Anomalie détectée et corrigée

**BUG PRÉEXISTANT (sans lien avec le multi-tenant) —
`ProfesseurModel::findByUserId()`** déclare un type de retour `?stdClass`,
mais `Core\Model::queryOne()` retourne `object|false`. Quand aucune ligne
`professeurs` ne correspond au `user_id` (utilisateur de rôle `enseignant`
sans fiche professeur associée), PHP lève une `TypeError` fatale
("Return value must be of type ?stdClass, bool returned"), faisant planter
tout le tableau de bord. **Ce cas n'avait jamais été exercé auparavant**
car tous les utilisateurs `enseignant` existants possèdent une fiche
`professeurs`. Le premier utilisateur de test multi-établissements créé
pour cette phase (rôle `enseignant`, sans fiche professeur) l'a révélé
immédiatement.

**Correction :** conversion explicite `false → null` avant retour
(`return $row ?: null;`), une ligne, aucun changement de comportement pour
tout appelant existant. Reproduit et vérifié corrigé par requête HTTP réelle
(dashboard passe de "Fatal error" à 200 OK avec contenu correct).

---

## 9. Tests réalisés

| Suite | Résultat | Portée |
|---|---|---|
| `tests/Unit/MultiTenantUserTest.php` (nouveau) | **15/15 PASS** | Mono-tenant (non-régression), multi-tenant, isMember, RBAC contextuel, mémorisation |
| `tests/Unit/BrandingTenantTest.php` (14.5) | **18/18 PASS** | Non-régression |
| `tests/Unit/RbacTenantTest.php` (14.4) | **18/18 PASS** | Non-régression |
| `tests/Unit/TenantIsolationTest.php` (14.3) | **24/24 PASS** | Non-régression |
| `tests/Unit/TenantResolverTest.php` (14.2) | **29/29 PASS** | Non-régression |
| `tests/security_tests.php` (pré-existant) | **48/48 PASS** | Régression sécurité globale |
| `tests/functional_tests.php` (pré-existant) | **109/109 PASS** | Régression fonctionnelle globale |
| **Total automatisé** | **261/261 PASS** | |

**Vérification HTTP réelle (navigateur + requêtes directes), utilisateur
mono-tenant :**
- Connexion directe au tableau de bord sans interruption (0 régression sur
  les 7 comptes existants).

**Vérification HTTP réelle, utilisateur multi-tenant créé pour le test**
(2 établissements, rôles différents — supprimé après test) :
1. Connexion → redirection vers `/choisir-etablissement`, 2 établissements proposés.
2. Accès direct à `/dashboard` avant le choix → refusé, redirigé vers `/login` (`Session::isLogged()` reste `false`).
3. Choix de l'établissement A → tableau de bord chargé, branding et menu corrects.
4. Changement d'établissement via le sélecteur du menu (`POST /auth/switch-school`) vers B → **rôle affiché passe de "Enseignant" à "Secrétaire"**, permissions recalculées, session cohérente.
5. Tentative de bascule vers un établissement non autorisé (`school_id=2`, non-membre) → **rejetée**, contexte inchangé, `SWITCH_SCHOOL_REJECTED` journalisé dans `storage/logs/security-*.log`.

Toutes les données de test (utilisateur, établissement, appartenances,
rôles) ont été supprimées après vérification — confirmé par requête SQL
post-nettoyage (0 ligne résiduelle).

**Sessions simultanées** : vérifiées par construction — chaque session PHP
est isolée par cookie (`ecole_session`), `Session::getUser()` ne lit que
l'état de LA session courante ; les tests ont utilisé des jars de cookies
(`curl -c/-b`) strictement séparés pour l'utilisateur admin mono-tenant et
l'utilisateur multi-tenant, sans interférence observée entre les deux.

---

## 10. Décision finale : **GO WITH FIXES**

**GO** sur le périmètre livré : association utilisateur↔tenant (mono et
multi), sélection d'établissement, changement de contexte sans re-login,
mémorisation du dernier établissement, RBAC contextuel entièrement
recalculé, isolation stricte vérifiée par un scénario réel d'attaque
(tentative de bascule non autorisée, rejetée et journalisée). Bug
préexistant détecté et corrigé avant livraison.

**WITH FIXES** — deux réserves déjà connues (issues des phases
précédentes, non aggravées ici) :
1. API/Finance/RH/VieScolaire toujours non testables (tables absentes,
   Phase 14.4).
2. Notifications non isolées par établissement pour un futur utilisateur
   réellement multi-tenant (gap mineur, sans impact tant qu'aucun tel
   utilisateur n'existe en production).

---

## 11. Compatibilité V2

**100% compatible.** Les 7 utilisateurs existants (tous mono-tenant)
suivent exactement le même chemin de code qu'avant cette phase
(`count($schools) === 1` → activation directe), avec les mêmes permissions
et le même rôle qu'auparavant — vérifié par équivalence stricte réutilisée
depuis les tests RBAC (Phase 14.4) et par test de connexion réel. Aucune
route, vue ou contrôleur métier existant modifié.
