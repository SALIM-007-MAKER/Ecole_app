# Opérateurs de la plateforme (Super-Admin SaaS) et journal d'audit global

> Étape 1 du Super-Admin SaaS — migration **T064**. Portail : `/platform/login`.

## 1. Principe

Un **opérateur** est un compte de la plateforme EduNova (propriétaire du SaaS). Son identité est **entièrement indépendante** des comptes
d'établissement : table `platform_operators` (e-mail, nom, mot de passe haché, niveau), aucun lien avec `users`. Un administrateur d'école ne peut
jamais ouvrir une session opérateur, et inversement. Les deux sessions utilisent des clés distinctes (`_auth_user` / `_platform_operator`).

Niveaux : `support` (consultation), `admin` (gestion des établissements et des domaines ; **consultation seule des plans** ; journal d'audit en lecture),
`super_admin` (tout, y compris la **création, la modification, l'archivage et la restauration des plans**, les sauvegardes et l'**export** du journal).

**Aucun compte par défaut n'existe.** Ni la migration, ni l'installateur ne créent d'opérateur : sans le script ci-dessous, `/platform` est inaccessible.

## 2. Créer le premier opérateur

Le script exige un accès serveur (ligne de commande). Le mot de passe se passe **uniquement** par la variable d'environnement `OPERATOR_PASSWORD`
(jamais en argument de ligne de commande). Politique : 14 caractères minimum, 3 types de caractères parmi minuscules/majuscules/chiffres/symboles,
sans votre e-mail ni votre nom, sans mot courant ni suite triviale.

```bash
# Avec un mot de passe que vous choisissez
OPERATOR_PASSWORD='<mot de passe fort>' php database/create-operator.php --email=vous@exemple.tld --nom="Prénom Nom"

# Sans OPERATOR_PASSWORD : un mot de passe aléatoire de 24 caractères est généré et affiché UNE seule fois ;
# le changement est alors obligatoire à la première connexion.
php database/create-operator.php --email=vous@exemple.tld --nom="Prénom Nom"
```

Docker : `docker compose exec -e OPERATOR_PASSWORD='…' app php database/create-operator.php --email=… --nom="…"`
(le conteneur utilise ses variables `DB_*` ; aucun fichier `.env` n'est nécessaire). Hébergeur : même commande dans le terminal du service.

Options : `--niveau=super_admin|admin|support` (défaut `super_admin`), `--lister` (inventaire), `--desactiver` / `--activer`.

> **Ordre de déploiement** : appliquer T064 (`php database/migrate.php`), créer le premier opérateur, **puis** se connecter. Entre T064 et la création du
> premier opérateur, `/platform` est inaccessible : les anciennes lignes `platform_operators` (liées à un compte d'établissement, sans mot de passe
> propre) sont conservées mais ne peuvent plus se connecter.

## 3. Récupération d'accès (mot de passe perdu, compte verrouillé)

Procédure réservée à qui a un **accès serveur** (c'est le garant : il n'existe volontairement aucun « mot de passe oublié » public sur ce portail).

```bash
php database/create-operator.php --reinitialiser --email=vous@exemple.tld     # nouveau mot de passe (OPERATOR_PASSWORD ou généré)
```

Le compte est réactivé, le mot de passe actuel cesse de fonctionner, le changement est obligatoire à la prochaine connexion. L'opération est
inscrite au journal d'audit (acteur `cli`). Si aucun opérateur ne subsiste : `--lister`, puis créer un nouvel opérateur comme au §2.
Un blocage temporaire après des échecs (5 par e-mail et IP, 15 par e-mail, 20 par IP) expire seul au bout de quelques minutes.

Révoquer un opérateur sans perdre son historique : `--desactiver --email=…` (ou `--activer`). Rien n'est jamais supprimé.

## 4. Sécurité de la session opérateur

Inactivité maximale 30 minutes, durée maximale 8 heures ; identifiant de session régénéré à la connexion ; cookie `SameSite=Strict`, `HttpOnly`.
Message d'échec unique (« Identifiants invalides ou accès non autorisé ») et temps de réponse égalisé pour ne pas révéler l'existence d'un compte.

**À faire avant la mise en production commerciale (décision du 02/11/2026) : authentification à deux facteurs obligatoire pour les opérateurs.**

## 5. Journal d'audit global

Table `platform_audit_logs` (**ajout seul**). Pour chaque événement : date (ms), acteur (type `operator`/`system`/`anonymous`, identifiant, e-mail et
niveau figés au moment de l'action), établissement concerné, action, entité, ancienne et nouvelle valeur (JSON, secrets masqués), résultat
(`success`/`failure`/`denied`), adresse IP, navigateur.

Actions journalisées : connexions (réussies, échouées, bloquées), déconnexion, changement/réinitialisation de mot de passe, création/activation/
désactivation d'opérateurs, établissements (création, activation, suspension, archivage, restauration, suppression logique, changement de plan),
plans (création, modification, archivage, restauration), domaines, quotas, cache, sauvegardes/restaurations/téléchargements, instantanés, refus d'accès par niveau,
export du journal. Les tentatives de connexion échouées ou bloquées sont plafonnées à 30 lignes par IP et par fenêtre.

Consultation : menu **Journal d'audit** (niveaux `admin` et `super_admin`) — recherche, filtres (période, opérateur, action, établissement, résultat),
pagination de 50, détail avec ancienne/nouvelle valeur. **Export CSV** : `super_admin` uniquement, limité à 50 000 lignes, lui-même inscrit au journal ;
les cellules commençant par `=`, `+`, `-`, `@` sont neutralisées contre l'injection de formules.

### Protection contre la modification
Deux déclencheurs MySQL (`trg_platform_audit_no_update`, `trg_platform_audit_no_delete`) refusent tout `UPDATE`/`DELETE` (y compris `REPLACE`).
**Limite connue** : `TRUNCATE`, `DROP` et `ALTER` ne passent pas par les déclencheurs. Un `REVOKE` au niveau de la table ne retire PAS un droit accordé
au niveau de la base : pour les interdire réellement, accorder au compte de l'application des droits table par table, avec `SELECT, INSERT` seulement sur
`platform_audit_logs` et `audit_logs` (recette vérifiée sur MySQL 8.4 : `docs/deploiement/AUDIT_ETABLISSEMENT.md` §7), et réserver les migrations à un
compte d'administration distinct.

### Panne du journal : jamais bloquante, jamais silencieuse
Si l'écriture dans `platform_audit_logs` échoue, l'opération métier **n'est pas bloquée** mais :
1. l'événement complet est écrit dans `storage/logs/platform_audit_fallback-AAAA-MM-JJ.jsonl` (dossier modifiable par `PLATFORM_AUDIT_FALLBACK_DIR`) ;
2. l'erreur est consignée dans le journal technique (`PLATFORM_AUDIT_WRITE_FAILED`) ;
3. l'opérateur voit un avertissement explicite (l'opération n'est **jamais** présentée comme auditée) ;
4. un bandeau rouge « traçabilité incomplète » reste affiché sur toutes les pages du portail tant que des événements n'ont pas été rejoués.

Une fois la base rétablie : `php database/audit-rejouer.php` réinscrit les événements (date d'origine conservée) ; le fichier est renommé `.rejoue`
(jamais supprimé) et le bandeau disparaît.

## 6. Migration T064 (additive, idempotente)

- `platform_operators` : + `email` (unique), `nom`, `password_hash`, `must_change_password`, `password_changed_at`, `last_login_at` ; `user_id` devient nullable.
- `platform_audit_logs` : nouvelle table + déclencheurs.
- `platform_backups.triggered_by_kind` et `platform_restores.triggered_by_kind` (`user`|`operator`).

**Sens de `triggered_by`** : avant T064 c'est un `users.id` (anciennes lignes : `triggered_by_kind = 'user'`, valeur par défaut, données inchangées) ;
depuis T064 c'est un `platform_operators.id` (`triggered_by_kind = 'operator'`). Pour interpréter une ligne, lire d'abord `triggered_by_kind`.

Retour arrière : `php database/migrate.php --rollback=T064 --confirm` retire déclencheurs, journal et colonnes ajoutées (les colonnes d'identité ne sont retirées que
si aucun opérateur indépendant n'existe). Ne jamais appliquer sur la base réelle sans sauvegarde vérifiée.

## 7. Tests

`tests/Unit/PlatformOperatorAuthTest.php` (migration, politique, création, authentification indépendante de `users`, récupération, session, script réellement
exécuté, parcours HTTP) et `tests/Unit/PlatformAuditTest.php` (écriture, immuabilité, panne et rejeu, valeurs avant/après, filtres, accès par niveau, export,
isolation vis-à-vis des comptes d'établissement). `tests/Support/platform_request.php` simule une requête HTTP du portail sur une copie jetable.
