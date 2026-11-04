# Journal d'audit par établissement — étape 2 (migration T065)

> Deux journaux **distincts** : `platform_audit_logs` (opérations de la plateforme, voir `OPERATEUR_PLATEFORME.md`) et `audit_logs`
> (actions sensibles réalisées DANS chaque établissement, objet de ce document). Ils ne se mélangent jamais : chaque événement n'est écrit que
> dans son propre journal.

## 1. Principe

- Table `audit_logs`, une ligne par action sensible, rattachée à un établissement (`etablissement_id`).
- L'établissement vient **exclusivement du contexte tenant validé** (`TenantContext::current()`). Sans contexte (script, file d'attente) : `NULL`.
  Jamais l'établissement par défaut, jamais déduit du compte de l'utilisateur (un compte peut appartenir à plusieurs établissements).
- L'acteur est figé au moment de l'action (`actor_nom`, `actor_role`) : la trace reste lisible si le compte est supprimé. Aucun nom n'est conservé
  pour les rôles élève et parent.
- Données personnelles : les noms et prénoms d'élèves ne sont plus écrits (identifiants uniquement) ; `AuditService` masque en plus `prenom`,
  `nom_eleve`, `telephone`, `email`, `adresse`, `date_naissance`… et tout secret (mot de passe, jeton…).
- Consultation : **Paramètres → Journal d'audit** (`/parametres/audit`), permission **`audit.view`** attribuée aux seuls rôles `admin` et `directeur`
  (aucun nouveau rôle). Lecture seule : recherche, filtres (période, utilisateur, module, action, entité), pagination de 50, détail avec ancienne et
  nouvelle valeur. Le détail d'un événement d'un autre établissement répond 404.

## 2. Migration T065 (additive, idempotente)

| Élément | Contenu |
|---|---|
| Colonnes ajoutées à `audit_logs` | `etablissement_id` (NULL = non démontré), `actor_nom`, `actor_role`, `resultat` (`success`/`failure`) |
| Index / clé étrangère | `(etablissement_id, created_at)`, `(etablissement_id, module, action)`, `fk_al_etablissement` → `etablissements` (RESTRICT) ; `fk_al_user` (SET NULL) **conservée** |
| Rétro-remplissage | `etablissement_id` des anciennes lignes **uniquement** si le compte de l'acteur n'a aucune appartenance (`user_etablissements`, `user_roles_etab`) à un autre établissement, même ancienne, et si `dernier_etablissement_id` est vide ou identique. Sinon NULL (visible d'aucun établissement). Aucune autre colonne historique n'est modifiée |
| Déclencheurs | `trg_audit_logs_no_update`, `trg_audit_logs_no_delete` (ajout seul) |
| Permission | `audit.view` dans `etab_permissions` + `etab_role_permissions` (`admin`, `directeur`) et dans `config/permissions.php` |
| Retour arrière | `php database/migrate.php --rollback=T065 --confirm` retire **uniquement les déclencheurs** ; colonnes, index, données et permission sont conservés |

Tolérance : sur une base où T065 n'est pas appliquée, `AuditService` n'écrit que les colonnes existantes (comportement historique) ; la page de
consultation indique que la mise à jour est en attente. **Appliquer T065 avant d'utiliser le journal par établissement.**

## 3. Actions journalisées

**Opérations critiques — trace dans la MÊME transaction que l'opération ; si l'écriture échoue, l'opération est annulée** (`AuditService::logStrict`,
`AuditIndisponibleException`, message sans détail technique) :

| Opération | Méthode |
|---|---|
| Enregistrement d'un paiement | `PaymentService::enregistrer`, `initierPaiement`, `completer` |
| Annulation d'un paiement | `PaymentService::annuler` |
| Remboursement | `PaymentService::rembourser` |
| Extourne comptable | `AccountingService::extourner` (écriture d'extourne + marquage + trace en une transaction) |
| Clôture comptable | `AccountingService::cloturerPeriode`, `cloturerExercice` (événements diffusés seulement après validation). **Trace unique** : celle du service ; le listener `AccountingHandler` n'écrit plus rien pour `FiscalYearClosed` (doublon supprimé, test de non-régression) |
| Publication d'un bulletin | `BulletinGenerator::controlerEtBasculer` (dans la transaction de publication) |

**Autres opérations — au mieux (best effort)** : l'opération continue si l'audit échoue, mais l'échec n'est jamais silencieux (§5).
Déjà journalisées avant l'étape 2 : notes, évaluations, périodes, types d'évaluation, bulletins (génération, régénération, archivage,
appréciation, impression), classements, factures, frais, décaissements, caisse, comptabilité, absences, retards, présences, élèves, rotation des
jetons. **Ajoutées par l'étape 2** : modification de tout paramètre de l'établissement (`SettingsService::set`, ancienne et nouvelle valeur, secrets
masqués) et de l'identité visuelle ; création, modification (rôle, état, nature des champs changés), désactivation et suppression d'utilisateurs ;
changement de mot de passe ; sanctions, dossiers disciplinaires, appels, récompenses (écrits en base en plus du fichier texte).

## 4. Isolation multi-établissements

- Toutes les méthodes de lecture d'`AuditService` exigent un identifiant d'établissement explicite (> 0) et filtrent dessus : `rechercher`, `trouver`,
  `getEntityHistory`, `getUserHistory`, `modules`, `actions`, `acteurs`. Les anciennes méthodes non isolées ont été remplacées.
- `AuditController` n'utilise que `TenantContext::current()` ; `etablissement_id`, `etab`, `tenant`… passés dans l'URL sont ignorés.
- Les deux lecteurs du module Portails (désactivé, non réactivé) passent par `AuditService::rechercher($etab)`. Le test garde-fou
  `AuditEtablissementTest` échoue si une requête SQL lit `audit_logs` ailleurs que dans `AuditService`.
- `BulletinTokenRotator` écrit un événement global (script d'administration multi-établissements) : il reste sans établissement.

## 5. Panne du journal

- **Opération critique** : annulée (rollback réel vérifié sur les données) ; l'utilisateur reçoit « Opération annulée : sa traçabilité n'a pas pu
  être enregistrée… », la cause technique va au journal technique (`storage/logs/app-*.log`).
- **Autre opération** : elle aboutit ; l'événement complet est écrit dans `storage/logs/audit_fallback-AAAA-MM-JJ.jsonl` (dossier modifiable par
  `AUDIT_FALLBACK_DIR`), l'erreur est consignée dans le journal technique, et un **bandeau rouge** est affiché aux seuls titulaires de `audit.view`
  de l'établissement concerné, avec le nombre d'événements de **leur** établissement (jamais ceux des autres, ni ceux sans établissement).
- **Rejeu** (accès serveur) : `php database/audit-rejouer.php` réinscrit les événements des deux journaux (date et établissement d'origine
  conservés) ; les fichiers rejoués sont renommés `.rejoue` (jamais supprimés) ; une ligne injouable reste dans le fichier et le script sort en
  code 3. Les événements sans établissement ne sont visibles que dans les journaux techniques et par le rejeu.

## 6. Limites réelles de la protection par déclencheurs

Les déclencheurs refusent tout `UPDATE`/`DELETE` ligne à ligne, mais **ce n'est pas une immutabilité absolue** :
- `TRUNCATE`, `DROP`, `ALTER` et la suppression de la base ne passent pas par les déclencheurs (vérifié par test) ;
- une action de clé étrangère en cascade n'active pas les déclencheurs MySQL : la suppression **physique** d'un compte met `user_id` à NULL ; l'acteur reste
  identifié par `actor_nom`/`actor_role` (vérifié par test) ;
- un compte MySQL ayant `TRIGGER` ou `SUPER` peut supprimer les déclencheurs ;
- une personne ayant accès au serveur de base de données peut modifier le fichier de données.

## 7. Droits MySQL en production (vérifié sur MySQL 8.4)

Un `REVOKE` au niveau d'une table **ne retire pas** un droit accordé au niveau de la base (erreur « There is no such grant defined »). Pour interdire
réellement `UPDATE`, `DELETE`, `TRUNCATE` et `DROP` sur les journaux, il faut accorder les droits **table par table** au compte de l'application, avec
`SELECT, INSERT` seulement sur les tables d'audit, et réserver les migrations (DDL) à un compte d'administration distinct :

```sql
-- À exécuter par un compte d'administration, puis après chaque migration qui ajoute des tables :
SELECT CONCAT('GRANT SELECT, INSERT, UPDATE, DELETE ON `ecole_app`.`', table_name, '` TO ''ecole_app_app''@''%'';')
  FROM information_schema.tables
 WHERE table_schema = 'ecole_app' AND table_type = 'BASE TABLE' AND table_name NOT IN ('audit_logs', 'platform_audit_logs');
GRANT SELECT, INSERT ON `ecole_app`.`audit_logs`          TO 'ecole_app_app'@'%';
GRANT SELECT, INSERT ON `ecole_app`.`platform_audit_logs` TO 'ecole_app_app'@'%';
```

Essai réalisé : avec ces droits, `INSERT` est accepté sur la table d'audit ; `UPDATE`, `DELETE`, `TRUNCATE` et `DROP` sont refusés ; la table `users` reste
modifiable. Le compte de l'application ne doit donc pas servir à `php database/migrate.php`.

## 8. Procédures

**Mise en service** : sauvegarde vérifiée → `php database/migrate.php` (T065) → déconnexion/reconnexion des administrateurs et directeurs (la permission
`audit.view` est chargée à la connexion) → contrôle de `/parametres/audit`.
**Retour arrière** : `php database/migrate.php --rollback=T065 --confirm` (déclencheurs seuls ; données conservées). Pour retirer aussi la
permission : suppression manuelle contrôlée de `audit.view` dans `etab_role_permissions` puis `etab_permissions`.
**Alerte « traçabilité incomplète »** : vérifier la base, puis `php database/audit-rejouer.php`.

## 9. Tests

`tests/Unit/AuditEtablissementTest.php` (198 assertions) : migration et rétro-remplissage, attribution à l'établissement (jamais par défaut), acteur
figé, données personnelles, isolation en lecture (service et HTTP), permission `audit.view` pour chaque rôle, manipulation d'URL/filtres/identifiants,
rollback RÉEL des six opérations critiques (état des données mesuré), panne non critique, alerte limitée à l'établissement, rejeu, tolérance de
schéma historique, lecteurs du portail admin, garde-fou contre toute lecture non isolée, indépendance de `platform_audit_logs`, limites documentées.
Un contrôle par mutation (ancien `PaymentService` remis temporairement) a confirmé que ces tests détectent une opération validée sans trace.

## 10. Dette technique préexistante (constatée, NON corrigée, hors périmètre de l'étape 2)

Ces points existaient avant l'étape 2 (signatures d'`AuditService` identiques dans `HEAD`) et ne concernent que des modules désactivés. Ils ne sont pas
corrigés volontairement : le module Scolarité reste gelé.

**Module Scolarité (`enabled => false`) — cinq appels à `AuditService` avec l'ancien ordre d'arguments** (module, identifiant, données, utilisateur) au lieu de
la signature actuelle `(int $userId, string $module, string $entite, …)`. À la réactivation du module, le premier événement concerné lèvera une
`ArgumentCountError` / `TypeError` :

| Fichier | Méthode (ligne) | Appel obsolète |
|---|---|---|
| `app/Modules/Scolarite/Listeners/FamilleHandler.php` | `onCreated(ParentCreated)` (37) | `logCreate('familles', familleId, ['nom' => …], createdById)` |
| `app/Modules/Scolarite/Listeners/FamilleHandler.php` | `onUpdated(ParentUpdated)` (47) | `logUpdate('familles', familleId, changedFields, updatedById)` |
| `app/Modules/Scolarite/Listeners/MatiereHandler.php` | `onCreated(MatiereCreated)` (37) | `logCreate('matieres', matiereId, […], createdById)` |
| `app/Modules/Scolarite/Listeners/MatiereHandler.php` | `onUpdated(MatiereUpdated)` (47) | `logUpdate('matieres', matiereId, changedFields, updatedById)` |
| `app/Modules/Scolarite/Listeners/MatiereHandler.php` | `onArchived(MatiereArchived)` (57) | `logDelete('matieres', matiereId, […], archivedById)` |

Correction à prévoir lors de la réactivation : passer l'identifiant de l'utilisateur en premier argument, puis le module, le nom d'entité (`'famille'`,
`'matiere'`), l'identifiant et les données (pour `logUpdate`, un tableau « avant » et un tableau « après », et non une liste de champs).

**Doublon analogue à la création d'un exercice comptable** : `AccountingService::creerExercice()` écrit `create` / `exercice`, et
`AccountingHandler::onFiscalYearOpened()` écrit `create` / `exercice_comptable` pour le même fait. Non corrigé (la création d'un exercice n'est pas une
opération critique et n'entrait pas dans la demande) ; à traiter comme la clôture, où la trace unique est désormais celle du service (voir §3).
