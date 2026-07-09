# Phase 14.9 — Cache & Queue Multi-Tenant — Rapport d'implémentation

## 1. Contexte & périmètre

Blueprint §25.1, ligne Phase 14.9 : *"Cache Redis + Queue Workers — Performance +
async — Livrable : Redis namespaced, workers Supervisord, jobs listés §16.2 —
Validation : Génération bulletin → async → notification email."*

Cet environnement (Windows/WAMP local) ne dispose ni de l'extension PHP `redis`, ni de
`apcu` (vérifié : `php -m` ne les liste pas), ni d'un process manager de type
Supervisord/systemd pour maintenir un worker persistant. Même discipline de périmètre
qu'aux Phases 14.7 (SSL/ACME) et 14.8 (S3) : livrer des interfaces conformes au
blueprint avec un adaptateur **réellement fonctionnel et testé** (fichier disque pour
le cache, table MySQL pour la file d'attente), un adaptateur Redis **stub** honnête,
et documenter explicitement ce qui relève de l'infrastructure serveur.

## 2. Composants créés

### Cache

| Fichier | Rôle |
|---|---|
| `core/Cache/CacheInterface.php` | Contrat `get/has/put/forget/flushPrefix/stats` |
| `core/Cache/FileCache.php` | Backend disque réel : 1 fichier JSON par clé (nommé par `sha1(clé)`), écriture atomique (`rename()`), expiration TTL, scan pour `flushPrefix()`/`stats()` |
| `core/Cache/RedisCache.php` | Stub conforme à l'interface — lève une exception explicite (extension absente) |
| `core/Cache/CacheManager.php` | Fabrique (driver `file`/`redis` piloté par `CACHE_DRIVER`) |
| `config/cache.php` | Driver actif, répertoire du cache fichier, TTL par catégorie |
| `core/Tenant/TenantCache.php` | Cache préfixé `tenant:{etablissement_id}:{catégorie}:` — `get/put/remember/forget/forgetCategory/flushTenant/stats` |

### Queue

| Fichier | Rôle |
|---|---|
| `database/migrations/T015_cache_queue_infrastructure.php` | Table `jobs` (tenant-scopée, statuts pending/processing/done/failed, `run_at`, `attempts`/`max_attempts`, durée, erreur) |
| `core/Queue/Job.php` | Contrat `handle(array $payload): void` |
| `core/Queue/JobQueue.php` | `push/reserveNext (SELECT...FOR UPDATE atomique)/markDone/markFailed (backoff exponentiel)/stats/recent` |
| `core/Queue/QueueWorker.php` | `processNext/processBatch` — **reconstitue le `TenantContext` de la tâche avant `handle()`, le restaure après (finally), y compris en cas d'échec** |
| `app/Jobs/SendEmailJob.php` | Exemple de tâche réelle (enveloppe `EmailService::send()`), prête à l'emploi, non câblée dans les appelants existants (voir §5) |
| `database/queue-worker.php` | Runner CLI (traite un lot puis se termine — pas un démon, voir §9) |

### Monitoring

| Fichier | Rôle |
|---|---|
| `app/Controllers/MonitoringController.php` | `index/api/flushCache` — lecture seule, scopé à l'établissement connecté |
| `app/Views/settings/monitoring.php` | Jauges cache (clés actives par catégorie), file (pending/processing/done/failed, durée moyenne), tâches récentes |
| `database/migrations/T016_monitoring_permissions.php` | Permission `monitoring.view` |
| `tests/Unit/CacheQueueTest.php` | 40 assertions (voir §7) |

## 3. Composants modifiés

| Fichier | Modification |
|---|---|
| `core/Tenant/BrandingService.php` | Remplace la mémoïsation statique en mémoire (Phase 14.5, portée à la requête PHP courante) par `TenantCache` (catégorie `branding`) — **persiste désormais inter-requêtes**, invalidation ciblée via `forgetCategory()` dans `save()`. `clearCache()` (statique, réservée aux tests) et nouveau `clearCacheFor(etabId)` (invalidation ciblée) conservés/ajoutés pour compatibilité avec les tests existants. |
| `public/index.php` | Ajout de `App\Jobs\` à l'autoloader PSR-4 maison (absent, nécessaire pour que `QueueWorker` résolve les classes de tâches) |
| `config/permissions.php` | Ajout `monitoring.view` aux rôles `admin`/`directeur` |
| `config/routes.php` | 3 routes `/parametres/monitoring*` |

Aucun fichier V1 déplacé, aucun contrôleur V1 supprimé, aucune route existante
modifiée, aucune logique métier des modules existants touchée.

## 4. Adaptations du cache

Garantie d'isolation par construction : `TenantCache` n'expose **aucune** méthode qui
accepterait une clé sans `etablissementId` explicite — toute clé stockée est préfixée
`tenant:{id}:{catégorie}:`, et `flushTenant()`/`forgetCategory()` bornent
systématiquement leur `flushPrefix()` au préfixe de l'établissement appelant. Il est
structurellement impossible d'invalider ou de lire le cache d'un autre tenant par
erreur d'appel.

**Intégration réelle** : `BrandingService` (catégorie `branding`) — seul appelant
métier existant migré vers ce cache, avec test de non-régression (§7.3).
**Primitive testée et prête, non câblée sur un appelant existant** (mêmes raisons
qu'en 14.7/14.8 — éviter le code non testable) :
- **RBAC** : `TenantCache::remember($etab, 'rbac', ...)` est testé génériquement
  (§7.2) ; non câblé dans `UserModel::getPermissions()` parce que les permissions
  sont aujourd'hui calculées une fois au login et stockées en session (pas
  recalculées à chaque requête) — il n'y a pas de point d'intégration à haute
  fréquence qui bénéficierait d'un cache sans changer le flux de login lui-même.
- **Paramètres** (`etab_settings`) : la table est déjà lue par `BrandingService`
  (donc déjà bénéficiaire du cache branding) ; les autres lecteurs de paramètres
  (Phase Paramètres V2) restent inchangés pour ne pas toucher à leur logique métier.
- **Tableaux de bord / rapports** : le module Rapports & BI (Phase 11.x) est
  désactivé (`config/modules.php` : `rapports=>false`) — construire une intégration
  dans un module inactif produirait du code non exécutable et invérifié.

## 5. Adaptations des queues

`app/Jobs/SendEmailJob.php` démontre et teste le mécanisme de bout en bout (enveloppe
`EmailService::send()`), mais n'est **volontairement pas câblé** dans les appelants
existants qui envoient des emails aujourd'hui de façon synchrone (`AuthController`,
`NotificationHandler`...). Les basculer vers la file changerait un comportement métier
observable (un email envoyé de façon synchrone deviendrait asynchrone, avec un délai
et un risque de perte silencieuse si le worker n'est jamais invoqué en production) —
c'est un changement de logique métier, explicitement hors périmètre de cette phase
("adapter l'infrastructure", pas "changer le comportement des fonctionnalités
existantes"). L'infrastructure (`JobQueue`, `QueueWorker`, `SendEmailJob`) est réelle,
testée, et prête à être adoptée par un futur contrôleur/listener qui le demanderait
explicitement.

Génération PDF, exports, imports, synchronisations, webhooks : mêmes conclusions —
aucun de ces traitements n'est aujourd'hui asynchrone dans l'application (ils
s'exécutent synchrones dans la requête HTTP qui les déclenche), et les rendre
asynchrones changerait leur comportement observable pour l'utilisateur final. La file
`jobs` est prête à les recevoir (`JobQueue::push($jobClass, $payload, $etabId)`) le
jour où un futur ticket demande explicitement de les rendre asynchrones.

## 6. Propagation du TenantContext

- **Événements synchrones** (`Core\EventDispatcher`, inchangé) : `dispatch()` appelle
  les listeners dans le **même process PHP, la même requête** que l'événement — le
  `TenantContext` (s'il est positionné) est donc trivialement identique pour
  l'événement et tous ses listeners, sans mécanisme supplémentaire nécessaire.
  Vérifié par lecture de code (`core/EventDispatcher.php`, aucune file d'attente,
  aucun changement de process entre `dispatch()` et `Listener::handle()`).
- **Traitements différés (queue)** : c'est ici que la propagation devient un vrai
  problème (le worker peut traiter la tâche de l'établissement A puis, immédiatement
  après dans le même process, celle de l'établissement B). `JobQueue::push()`
  enregistre systématiquement l'`etablissement_id` d'origine sur la ligne `jobs` ;
  `QueueWorker::processNext()` positionne `TenantContext::set()` avec cette valeur
  **avant** `Job::handle()`, puis restaure explicitement le contexte précédent dans un
  `finally` — y compris si le job lève une exception. Testé avec 2 tenants alternés
  sur 5 tâches consécutives sans aucune fuite (§7.5/7.6).

## 7. Tests réalisés

`tests/Unit/CacheQueueTest.php` — 40/40 assertions, transaction PDO annulée en fin de
script + nettoyage du répertoire de cache de test :

1. **`FileCache`** (9 assertions) : cycle get/has/put/forget, structures complexes
   préservées (tableaux imbriqués), expiration TTL (entrée avec `expires_at` passé
   correctement ignorée), `flushPrefix()`/`stats()` bornés au préfixe demandé.
2. **`TenantCache`** (9 assertions) : même clé/catégorie entre 2 tenants → jamais de
   confusion, `remember()` ne recalcule pas au 2e appel, `forgetCategory()` isolé par
   tenant, `flushTenant()` isolé par tenant (vérifié dans les deux sens).
3. **`BrandingService` — intégration réelle** (5 assertions) : premier `get()` peuple
   le cache, un changement DB direct (hors application) n'est PAS vu tant que le cache
   n'est pas invalidé (comportement de cache attendu), `save()` invalide et rend la
   nouvelle valeur immédiatement visible, `save()` sur A n'invalide jamais le cache de
   B.
4. **`JobQueue`** (9 assertions) : `push`/`reserveNext` (atomique, transporte
   l'etablissement_id), `markDone`/`stats`, file vide → `null`, tâche différée dans le
   futur non réservée avant son heure, épuisement des essais → `failed`.
5. **`QueueWorker` — propagation TenantContext** (4 assertions) : le job voit
   `TenantContext::current()` = l'établissement d'origine pendant `handle()`, le
   contexte est restauré après (y compris après un job qui échoue).
6. **Montée en charge légère, plusieurs tenants "simultanés"** (4 assertions) : 5
   tâches alternées entre 2 établissements traitées dans `processBatch()` — chaque
   tâche a vu **exactement** son propre tenant, dans l'ordre, zéro confusion malgré
   l'alternance.

**Non-régression** : l'intégralité des tests des Phases 14.2 → 14.8 ré-exécutée après
la migration de `BrandingService` vers le cache persistant et l'ajout de
`App\Jobs\` à l'autoloader — tous verts sans modification :
TenantResolverTest (29/29), TenantIsolationTest (24/24), RbacTenantTest (18/18),
**BrandingTenantTest (18/18)**, MultiTenantUserTest (15/15), DomainTenantTest (40/40),
StorageQuotaTest (30/30). Suite historique `security_tests.php` (48/48) et
`functional_tests.php` (109/109) également ré-exécutées, aucune régression.

Total cumulé (Phases 14.2 → 14.9) : **371/371 tests, tous verts**
(174 tests Multi-Tenant antérieurs + 40 nouveaux + 157 suite historique).

## 8. Anomalies détectées / corrections appliquées

- **Transactions imbriquées** : `JobQueue::reserveNext()` ouvrait initialement sa
  propre transaction PDO sans condition — cassait dès qu'il était appelé depuis un
  contexte déjà transactionnel (le propre harnais de test de ce projet enveloppe
  systématiquement ses scénarios dans une transaction annulée en fin de script ; PDO
  ne supporte pas les transactions imbriquées et lève une exception au 2e
  `beginTransaction()`). Corrigé en rendant la méthode réentrante
  (`!$this->pdo->inTransaction()` avant d'ouvrir/fermer sa propre transaction).
  Détecté avant l'exécution des tests par relecture, pas par un test en échec.
- **`App\Jobs\` absent de l'autoloader** : `public/index.php` ne mappait pas ce
  namespace — `QueueWorker::resolveJob()` (qui repose sur `class_exists()`) aurait
  échoué en production dès la première tâche réelle. Corrigé par l'ajout d'une ligne
  au tableau `$namespaces` existant.

Aucune anomalie fonctionnelle découverte par l'exécution des tests eux-mêmes cette
fois-ci (les deux points ci-dessus ont été trouvés/corrigés avant que les tests ne
passent au vert).

## 9. API

Aucun mécanisme de mise en cache des réponses n'existe aujourd'hui dans l'API
Platform (Phase 13.x) — vérifié (`config/api.php` ne référence aucune notion de
cache). L'exigence "le cache API est isolé par tenant, les réponses ne sont jamais
partagées" est donc **vacuously vraie** : il n'y a rien à isoler puisqu'il n'y a pas
de cache de réponses à ce jour. `TenantCache` est disponible et testé pour le jour où
un tel cache serait introduit (il suffirait de préfixer par
`TenantAuthContext`/JWT claim `etab`, déjà résolu par le middleware API existant). Les
quotas (Phase 14.8, `api_uploads`/`TenantQuotaService`) restent inchangés et
continuent de s'appliquer indépendamment du cache.

## 10. Monitoring

`MonitoringController`/`app/Views/settings/monitoring.php` exposent, par
établissement : nombre de clés de cache actives (par catégorie + total), compteurs de
file (pending/processing/done/failed), durée moyenne d'exécution (`AVG(duration_ms)`
sur les tâches terminées), liste des tâches récentes avec statut et message d'erreur
tronqué. "Relances automatiques" = le backoff exponentiel de `markFailed()`
(2^tentative minutes, plafonné à 30 min) — une tâche échouée est automatiquement
remise en file jusqu'à `max_attempts`, sans intervention manuelle.

## 11. Hors périmètre (infrastructure)

- **Redis réel** : extension absente de cet environnement — `RedisCache` est un stub
  conforme à l'interface, non fonctionnel par construction (voir `core/Cache/RedisCache.php`).
- **Workers Supervisord/démon persistant** : aucun process manager dans ce WAMP local
  — `database/queue-worker.php` traite un lot puis se termine ; en production, ce
  même script serait invoqué en boucle par Supervisord (Linux) ou une tâche planifiée
  Windows (`schtasks`). Non testable ici faute de l'infrastructure elle-même.
- **Basculement des envois d'emails/PDF/exports/imports/webhooks existants vers la
  file** : changerait un comportement métier synchrone observable — voir §5.
  L'infrastructure est prête ; l'adoption est un choix produit qui dépasse "adapter le
  cache/la queue à l'architecture multi-tenant".
- **Cache API** : rien à isoler, aucun cache de réponses n'existe — voir §9.

## 12. Décision finale

**GO WITH FIXES** *(fixes = les 4 points hors périmètre du §11, qui ne bloquent pas
l'usage applicatif du sous-système mais devront être adressés avant un déploiement
public réel avec un vrai volume de trafic/tâches asynchrones)*.

`CacheInterface`/`FileCache`/`TenantCache` (isolation stricte, testée dans les deux
sens), `JobQueue`/`QueueWorker` (propagation et restauration du `TenantContext`,
testée avec plusieurs tenants entrelacés), et l'intégration réelle sur le seul
appelant de cache actif (branding) sont implémentés et testés (371/371 tests verts,
zéro régression, 2 anomalies trouvées et corrigées avant mise au vert). Ne pas
commencer la phase suivante tant que cette phase n'est pas validée.
