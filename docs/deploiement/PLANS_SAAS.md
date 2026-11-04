# Plans SaaS — conditions commerciales

Portail plateforme : `/platform/plans`. Lecture : tout opérateur connecté. Création, modification, archivage, restauration : **`super_admin` uniquement**.

## Champs d'un plan (`platform_plans`, migration T066)

| Champ | Règle |
|---|---|
| `code` | 2 à 30 caractères `a-z 0-9 _ -`, unique, **immuable** après création |
| `nom` | 2 à 100 caractères |
| `devise` | code ISO 4217 de 3 lettres (défaut `PLATFORM_DEFAULT_CURRENCY`, repli `XOF`) |
| `prix_mensuel`, `prix_annuel` | montants positifs ou nuls, **8 chiffres au maximum avant la virgule** (colonne `DECIMAL(10,2)`) ; **entiers pour le XOF**, 2 décimales au maximum sinon. Facturation mensuelle et annuelle uniquement |
| `max_users`, `max_eleves`, `storage_quota_mb`, `api_calls_per_day` | entiers ≥ 1 |
| `duree_essai_jours` | 0 à 365 (0 = pas d'essai) |
| `jours_grace` | 0 à 90 : délai après expiration avant suspension |
| `ordre` | tri d'affichage (puis `id`) ; entier de **-32768 à 32767** |
| `visible_catalogue` | booléen (`0`/`1`, `true`/`false`, `on`/`off`, `yes`/`no`) ; toute autre valeur est **refusée**. 0 = masqué du catalogue, mais assignable par un opérateur |
| `modules_inclus` | clés de `config/modules.php` (modules désactivés compris). **Cocher un module ne l'active pas** |
| `actif` | 0 = plan archivé |

## Règles de gestion

- **Aucune conversion de devise.** Les montants sont stockés tels que saisis, dans la devise du plan. Les plans livrés à l'origine (`starter`, `pro`, `enterprise`) sont en `DZD` et le restent ; la liste les signale par « Devise à vérifier » quand leur devise diffère de `PLATFORM_DEFAULT_CURRENCY`. Les corriger est une décision manuelle (modifier devise **et** prix).
- **Modification partielle** : un champ absent de la requête conserve sa valeur.
- **Plan utilisé** : changer le prix ou la devise d'un plan rattaché à au moins un établissement exige la case de confirmation explicite. La modification est auditée avec l'ancienne et la nouvelle valeur, la confirmation et le nombre d'établissements concernés.
- **Archivage** : un plan n'est jamais supprimé. Archivé, il n'est plus assignable (assignation, création d'établissement) mais les établissements qui l'ont déjà le conservent, et leur page de détail l'affiche (« archivé »). Archiver ou restaurer un plan déjà dans l'état demandé ne change rien et n'écrit pas dans le journal.
- **Atomicité et audit strict** : la création, la modification, l'archivage et la restauration d'un plan sont chacune exécutées dans **une seule transaction** qui contient l'écriture du plan (données, `updated_at`, état `actif`) **et** sa trace dans le journal d'audit global. La trace est écrite en mode *strict* : si elle ne peut pas être enregistrée (journal indisponible, erreur de base), **toute l'opération est annulée** — plan non créé, modification non appliquée, archivage ou restauration non effectué — et l'opérateur reçoit un message explicite (« … annulé(e) : la trace d'audit n'a pas pu être enregistrée »). Aucun fichier de secours n'est écrit dans ce cas, puisqu'il n'y a rien à rejouer pour une opération annulée. Les opérations sans effet (aucun champ modifié, plan déjà dans l'état demandé, plan inexistant, données invalides) n'écrivent ni plan ni audit.
- **Erreurs techniques** : une erreur inattendue de base de données pendant une de ces opérations annule la transaction et n'est **jamais affichée telle quelle** à l'opérateur (un message SQL expose noms de tables et de colonnes). L'opérateur voit un message générique avec une **référence** ; le détail complet (classe, message, fichier, ligne) est journalisé côté serveur dans `storage/logs/app-AAAA-MM-JJ.log` avec la même référence. L'échec de la trace d'audit est distingué par une exception dédiée (`PlanAuditException`) dont le message est destiné à l'opérateur ; sa cause technique est journalisée (`PLATFORM_PLAN_AUDIT_ABORTED`, journal de sécurité).
- **Transaction de l'appelant** : si une transaction est déjà ouverte (script, test), l'opération s'exécute dans un point de sauvegarde (`SAVEPOINT`). Un échec n'annule que le travail de l'opération ; la transaction de l'appelant reste ouverte et maîtresse de son propre `COMMIT` / `ROLLBACK`.
- **Code de plan et concurrence** : un code déjà pris, y compris par une requête concurrente entre la vérification et l'insertion, est signalé par un message de validation (« existe déjà »), jamais par une erreur SQL.
- **Limites copiées, pas liées** : les quotas du plan sont copiés sur l'établissement à l'assignation ; modifier un plan ne change pas les établissements déjà rattachés. Le gel des conditions commerciales (prix, devise, essai, grâce) par abonnement relève de l'étape suivante.

## Audit

Actions du journal global : `plan.created` (valeurs après), `plan.updated` et `plan.toggled` (valeurs avant / après). Ces trois actions sont écrites en mode strict et dans la transaction de l'opération (voir « Atomicité et audit strict »). Cette exigence ne vaut que pour les plans : les autres appels au journal global (connexions, établissements, opérateurs…) conservent leur comportement habituel — une panne du journal ne bloque pas l'opération, elle est signalée et l'événement est écrit dans le fichier de secours.

## Migration T066

Additive et idempotente : 5 colonnes ajoutées (`duree_essai_jours`, `jours_grace`, `ordre`, `visible_catalogue`, `updated_at`) et défaut de colonne `devise` passé à `XOF`. **Aucune ligne n'est modifiée.** Retour arrière : le défaut de devise redevient `DZD`, colonnes et données sont conservées. **Appliquée à la base WAMP réelle `ecole_app` le 03/11/2026** (`php database/migrate.php --id=T066`, journal `migrations_log` : `success`) : les plans `starter`, `pro` et `enterprise` sont restés en `DZD` avec leurs prix d'origine. La vérification d'une sauvegarde préalable n'est pas consignée ici ; pour une autre installation, appliquer la migration après une sauvegarde vérifiée (`php database/migrate.php`).
