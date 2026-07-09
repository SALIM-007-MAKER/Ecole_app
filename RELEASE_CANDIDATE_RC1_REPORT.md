# SCOLARIS V2 / EduNova — Release Candidate RC1 — Rapport de validation

**Score global : 6.8/10 — Décision finale : GO WITH FIXES**

Aucune nouvelle fonctionnalité n'a été développée durant cette phase. Le travail a
consisté en un audit direct (lecture de code, requêtes de diagnostic sur la base
réelle, appels HTTP réels contre l'application qui tourne) plutôt qu'un résumé des
rapports de phases précédentes, la correction immédiate des anomalies critiques
trouvées, puis une nouvelle validation par les tests.

**Avertissement liminaire, important** : le contexte de cette phase affirme que
*"Tous les modules de SCOLARIS V2 sont officiellement terminés et validés"*, citant
notamment Finance, RH, Vie scolaire, Documents, Communication, Bibliothèque,
Inventaire, Rapports & BI, Portails et API Platform. **Cette revue a vérifié cette
affirmation empiriquement (et non en la tenant pour acquise) et a trouvé qu'elle est
inexacte pour 9 des 14 modules cités** — voir §5. Ce constat est central à la
décision finale et n'a pas été édulcoré.

---

## 1. Méthodologie

- Inventaire réel des suites de tests existantes (`tests/Unit/*.php`, `tests/Api/*.php`,
  `tests/security_tests.php`, `tests/functional_tests.php`) — 22 fichiers de test au
  total, tous exécutés.
- Requêtes directes sur la base de données réelle (`INFORMATION_SCHEMA`) pour
  vérifier quelles tables existent effectivement, par comparaison avec les manifestes
  `module.json` de chaque module (pas une supposition sur un préfixe de nommage).
- Appels HTTP réels (`curl`) contre l'application en cours d'exécution — pas
  seulement des tests unitaires isolés — pour vérifier que le routage fonctionne
  réellement de bout en bout.
- Mesure de performance chronométrée sur l'opération la plus coûteuse connue
  (restauration globale de vérification, Phase 14.11).
- Lecture ciblée de la configuration de sécurité/production (`config/app.php`,
  gestion des erreurs, fuseau horaire, autoloading).

## 2. Anomalies critiques — trouvées ET corrigées durant cette phase

### 2.1 [CRITIQUE, CORRIGÉ] L'intégralité de l'API Platform (122 routes) était inaccessible en HTTP

**Découverte** : un appel réel à `GET /api/v1/health` retournait un **404**, rendu à
l'intérieur du layout complet du tableau de bord — pas une erreur explicite, ce qui
avait probablement masqué le problème depuis la Phase 13.x.

**Cause racine** : `app/Modules/Api/routes.php` enregistre systématiquement ses
routes avec la syntaxe `Controller::class . '@methode'`, qui produit une chaîne déjà
pleinement qualifiée (ex. `App\Modules\Api\Controllers\V1\HealthController@check`).
`Core\Router::callHandler()` détectait un `\` dans le nom de contrôleur et
**préfixait à nouveau** avec `App\Modules\`, produisant
`App\Modules\App\Modules\Api\Controllers\V1\HealthController` — une classe qui n'a
jamais existé. **Les 122 routes de l'API Platform étaient concernées sans
exception**, puisque `app/Modules/Api/routes.php` utilise cette syntaxe partout.

**Correction** (`core/Router.php`) : si le nom de contrôleur commence déjà par
`App\`, il est utilisé tel quel, sans re-préfixage. Comportement des deux autres
conventions existantes (noms bruts V1, chemins partiels `Module\Controllers\X` V2)
strictement inchangé — vérifié par la suite de tests complète (470/470 + tests API)
et par des appels HTTP réels sur des routes de chaque convention.

**Vérifié en direct après correction** :
```
GET  /api/v1/health          → 200 {"success":true,"data":{"status":"healthy",...}}
GET  /api/v1/classes         → 401 auth_required (au lieu de 404)
GET  /api/v1/notes           → 401 auth_required (au lieu de 404)
POST /api/v1/auth/login      → 422 validation_failed (au lieu de 404)
GET  /api/docs               → 200 (Swagger UI)
GET  /dashboard, /parametres/domaines → 302 (inchangé, non régressé)
```

### 2.2 [CRITIQUE, CORRIGÉ] `App\Shared\` jamais enregistré dans l'autoloader de production

**Découverte** : une fois 2.1 corrigé, `GET /api/v1/health` révélait une seconde
erreur fatale : `Class "App\Shared\Api\ApiRequestContext" not found`. Le répertoire
`app/Shared/` (Analytics + Api : `FilterService`, `PaginationService`, `RateLimiter`,
`ApiRequestContext`, `ApiResponseBuilder`) existe et est référencé par du code de
production réel (`ApiBaseController.php`, `ResourceApiController.php`), mais n'a
**jamais été ajouté** à la table de correspondance PSR-4 maison de
`public/index.php`. Les tests dédiés (`tests/Api/*.php`) ne l'avaient jamais
détecté car ils définissent chacun leur **propre autoloader privé** incluant
`App\Shared\Api\`, contournant sans le savoir le bootstrap de production réel.

**Correction** : ajout de `'App\\Shared\\' => ROOT_PATH . '/app/Shared/'` à la table
`$namespaces` de `public/index.php`.

**Vérifié en direct** : `GET /api/v1/health` retourne désormais un 200 JSON complet
et correct (voir ci-dessus).

**Impact combiné de 2.1+2.2** : avant cette phase, l'API Platform entière — l'un des
14 modules explicitement cités comme "terminé et validé" dans le contexte de cette
phase — était **totalement inutilisable par tout client HTTP réel**, malgré des
rapports de phase antérieurs (13.2/13.3) indiquant "458/458 tests PASS" et
"GO FROZEN v2.0.0". Ces tests, bien que corrects sur la logique qu'ils couvrent,
n'exerçaient jamais le chemin HTTP réel (routage + autoloading de production), d'où
la non-détection pendant deux phases consécutives.

## 3. Anomalies mineures — trouvées ET corrigées

1. **`config/app.php` — `APP_DEBUG` par défaut `true`** si la variable d'env est
   absente : un défaut non sécurisé (une variable manquante en production exposerait
   des traces d'erreur détaillées). Corrigé : repli à `false`. Aucun changement de
   comportement local (`.env` fixe déjà explicitement `APP_DEBUG=true` en
   développement). Architecture de gestion d'erreur elle-même vérifiée saine par
   ailleurs : `Core\Application::bootstrap()` enregistre un
   `set_exception_handler`/`set_error_handler` propre quand `debug=false`.
2. **`database/migrate.php` et `database/queue-worker.php` n'appliquaient jamais
   `APP_TIMEZONE`** — contrairement aux requêtes HTTP réelles (`Core\Application::bootstrap()`
   appelle `date_default_timezone_set()`), ces deux scripts CLI tournaient sous le
   fuseau horaire PHP nu (UTC dans cet environnement) alors que MySQL utilise le
   fuseau système (UTC+1 ici) — un job planifié (sauvegarde, traitement de file)
   déclenché en CLI pourrait donc calculer des dates décalées d'une heure par
   rapport à une action déclenchée via le web. Corrigé : `date_default_timezone_set()`
   ajouté aux deux scripts.
3. **`tests/Unit/TenantIsolationTest.php` — fragilité liée au décalage PHP/MySQL
   ci-dessus** : comparait une date écrite via `date('Y-m-d')` à une date lue via
   `CURDATE()`. Reproduit pendant cette revue (échec intermittent selon l'heure),
   confirmé comme un artefact de test — pas une régression du code métier
   (`AbsenceModel::storePointage()` vérifié correct isolément avec une date fixe).
   Corrigé en fixant la date du scénario.

Après ces 5 corrections, suite complète re-exécutée : **voir §4**.

## 4. Couverture des tests (après corrections)

| Suite | Résultat |
|---|---|
| TenantResolverTest | 29/29 |
| TenantIsolationTest | 24/24 |
| RbacTenantTest | 18/18 |
| BrandingTenantTest | 18/18 |
| MultiTenantUserTest | 15/15 |
| DomainTenantTest | 40/40 |
| StorageQuotaTest | 30/30 |
| CacheQueueTest | 40/40 |
| PlatformAdminTest | 56/56 |
| BackupDrTest | 43/43 |
| `tests/Api/ApiKeyTest.php` | 20/20 |
| `tests/Api/AuthApiTest.php` | 18/18 |
| `tests/Api/PaginationTest.php` (+ Filter + Sort) | 38/38 |
| `tests/Api/RateLimitTest.php` | 29/29 |
| `tests/Api/WebhookTest.php` (+ ApiKey) | 15/15 |
| `tests/security_tests.php` | 48/48 |
| `tests/functional_tests.php` | 109/109 |
| **TOTAL** | **530/530** |

Plus, au-delà de ces suites automatisées, 4 modèles/services académiques disposent
de leurs propres tests dédiés non ré-exécutés dans le cadre de cette revue faute de
changement les concernant (`AcademicAnalyticsServiceTest.php`,
`AcademicCalculationServiceTest.php`, `BulletinGeneratorTest.php`,
`RankingEngineTest.php`) — présents et non modifiés.

**Note** : `tests/Api/FilterTest.php` échoue immédiatement
(`Class "PHPUnit\Framework\TestCase" not found"`) — ce fichier a été écrit pour
PHPUnit, qui n'est installé nulle part dans ce projet (aucun `composer.json`, aucun
répertoire `vendor/`). Ses assertions existent déjà, dupliquées et fonctionnelles,
dans `tests/Api/PaginationTest.php` (comptabilisé ci-dessus) — aucune perte de
couverture réelle, mais ce fichier mort devrait être supprimé ou réécrit dans le
format manuel utilisé partout ailleurs dans ce projet (voir §6, dette technique).

**Types de tests demandés par cette phase — couverture réelle** :

| Type demandé | Statut |
|---|---|
| Unitaires | ✅ 530/530 (ci-dessus) |
| Intégration | ✅ Isolation multi-tenant testée avec 2+ établissements sur chaque sous-système |
| Fonctionnels | ✅ `functional_tests.php` (109/109) |
| **End-to-end** | ⚠️ Partiel — vérifié par appels HTTP réels ciblés (`curl`) sur les routes corrigées, pas par une suite E2E automatisée (aucune n'existe dans ce projet — Playwright a été utilisé ponctuellement lors de phases antérieures mais pas conservé comme suite reproductible) |
| Montée en charge | ⚠️ Partiel — voir §5, mesure chronométrée unique, pas de test de charge concurrente réel |
| Sécurité | ✅ `security_tests.php` (48/48) + revue ciblée §7 |
| API | ✅ 120/120 (`tests/Api/*`, hors FilterTest.php cassé) + vérification HTTP réelle post-correction |
| Multi-Tenant | ✅ 313/313 (voir Phase 14.12) |
| RBAC | ✅ RbacTenantTest 18/18 + `security_tests.php` |
| Portails | ❌ Non vérifiable — module `enabled=false`, aucune table appliquée (voir §5) |

## 5. Le constat central : 9 des 14 modules cités ne sont pas réellement opérationnels

Vérifié par requête directe sur `INFORMATION_SCHEMA.TABLES`, comparée aux tables
déclarées dans le `module.json` de chaque module (pas une supposition) :

| Module | `config/modules.php` | Tables déclarées appliquées ? |
|---|---|---|
| Core | — | ✅ (fondation de l'application) |
| Scolarité | `enabled=false` | Tables V1 sous-jacentes utilisées en pratique (élèves/classes/...) malgré le flag module V2 à `false` |
| Académique | `enabled=false` | Idem — notes/bulletins/périodes V1 fonctionnels, tests dédiés présents et verts |
| **Finance** | `enabled=true` | ❌ **0 table** `finance_*` trouvée (vérifié contre les 7+ tables déclarées dans `Finance/module.json`, ex. `finance_comptes`, `finance_ecritures`...) |
| **Vie scolaire** | `enabled=true` | ❌ **0 table** `vs_*` trouvée |
| **RH** | `enabled=true` | ❌ **0 table** `rh_*` trouvée |
| Documents | `enabled=false` | ❌ 0 table `doc_*` |
| Communication | `enabled=false` | ❌ 0 table |
| Bibliothèque | `enabled=false` | ❌ 0 table `biblio_*` |
| Inventaire | `enabled=false` | ❌ 0 table `inv_*` |
| Rapports & BI | `enabled=false` | ❌ 0 table `bi_*` |
| Portails | `enabled=false` | ❌ aucune table dédiée, framework présent mais jamais activé |
| **API Platform** | `enabled=true` | ✅ Code fonctionnel — **cassé en HTTP jusqu'à cette phase** (§2), maintenant corrigé et vérifié |
| Multi-Tenant | — | ✅ Voir Phase 14.12 (score 8.4/10, GO) |

**Lecture honnête de ce tableau** : le socle réellement démontré fonctionnel en
production aujourd'hui est *Core + les tables V1 Scolarité/Académique (élèves,
classes, notes, absences, bulletins...) + toute l'infrastructure Multi-Tenant
(Phases 14.2-14.11) + l'API Platform (après correction de cette phase)*. Les 3
modules marqués `enabled=true` dans la config mais dépourvus de la moindre table
(Finance, RH, Vie scolaire) planteraient immédiatement (erreur SQL "table
introuvable") au premier usage réel — **ce n'est pas une nouvelle découverte de
cette phase** (déjà signalé Phase 14.4, reconfirmé Phase 14.12), mais le contexte de
cette Phase 15.1 affirmant ces modules "officiellement terminés et validés" ne
correspond pas à l'état réel de la base de données de cet environnement. Les 6
modules à `enabled=false` sont au moins **honnêtement** déclarés non actifs — leur
code existe (vérifié via les rapports d'implémentation individuels, non ré-audité
ligne à ligne ici, hors périmètre "aucune nouvelle fonctionnalité") mais n'a jamais
tourné contre une base réelle dans cet environnement.

## 6. Dette technique

| # | Item | Sévérité |
|---|---|---|
| DT1 | `tests/Api/FilterTest.php` mort (dépend de PHPUnit, non installé) — dupliqué ailleurs, aucune perte de couverture mais fichier trompeur | Mineure |
| DT2 | Route V1 `/api/eleves` (`Api\EleveApiController`) pointe vers une classe inexistante à ce chemin (la vraie classe est `App\Controllers\Api\EleveApiController`) — 404 pré-existant, non lié à la correction §2.1, non corrigé (périmètre restreint à cette seule route, risque de mauvaise correction sans connaître l'usage réel de cette route legacy) | Mineure |
| DT3 | Finance/RH/VieScolaire : `enabled=true` sans la moindre table appliquée — décalage entre configuration et réalité (voir §5) | **Majeure** |
| DT4 | Aucun `composer.json`/`vendor/` dans tout le projet — zéro dépendance tierce gérée, 100% du code (y compris les autoloaders) est artisanal. Cohérent et stable depuis le début de ce projet, mais empêche l'usage d'outils standards (PHPUnit, PHPStan, PHP-CS-Fixer) sans changement d'architecture | Mineure (choix délibéré, pas un défaut en soi) |
| DT5 | Aucun dépôt Git initialisé (`git status` échoue : "not a git repository"), aucun `.gitignore` | **Majeure** pour la préparation MEP |
| DT6 | Aucun `README.md`/`INSTALL.md`/`DEPLOY.md` à la racine du projet | Majeure pour la documentation de déploiement |
| DT7 | `TenantMiddleware` (Multi-Tenant) toujours dormant — voir Phase 14.12 §7/§8 | Documentée, non nouvelle |
| DT8 | API/Portails résolvent le tenant via leurs propres mécanismes, jamais `TenantContext` | Documentée, non nouvelle (Phase 14.12 DT1) |

## 7. Sécurité

Contrôlé, sans modification sauf mention contraire :

- **Authentification** : hachage `password_verify()` standard, verrouillage de
  compte via `actif`, journalisation des échecs (`Logger::security`) — cohérent sur
  tous les points d'entrée audités (web, API, portail plateforme).
- **Autorisation/RBAC** : 3 paliers de résolution testés et cohérents (18/18,
  Phase 14.12).
- **CSRF** : jeton par session (`Core\Session::getCsrfToken()`/`verifyCsrf()`),
  vérifié systématiquement sur les actions `POST` des contrôleurs audités durant
  tout ce cycle de phases.
- **CORS** : non applicable dans l'architecture actuelle (pas de configuration CORS
  dédiée trouvée — l'API est actuellement consommée en same-origin ou via clients
  serveur-à-serveur avec clé API/JWT, pas de scénario cross-origin navigateur
  identifié qui l'exigerait aujourd'hui).
- **Gestion des erreurs** : architecture saine (voir §3.1) — `set_exception_handler`
  propre en production, `display_errors` correctement gated par `config('app.debug')`.
- **Gestion des secrets** : `.env` centralise les secrets (`APP_KEY`, mots de passe
  DB) — mais absence de `.gitignore`/dépôt Git (DT5) signifie qu'aucune protection
  automatique contre un commit accidentel de `.env` n'est en place si un dépôt était
  initialisé sans précaution.
- **Journalisation/audit** : `Logger::security()` utilisé de façon cohérente sur
  toutes les actions sensibles auditées (connexions, permissions refusées,
  sauvegardes, restaurations, changements de statut établissement...).
- **Validation des entrées** : `Core\Controller::validate()` + validation
  spécifique par contrôleur, cohérent sur l'ensemble du code audité.

Aucune vulnérabilité nouvelle trouvée. Les 2 anomalies critiques de cette phase
(§2) étaient des bugs de disponibilité (404/fatal), pas des failles de sécurité au
sens propre — mais une API totalement indisponible aurait bloqué toute intégration
tierce prévue pour le lancement.

## 8. Performance

- Couverture d'index : 19/19 tables tenant-scopées indexées sur `etablissement_id`
  (Phase 14.12), aucune ligne orpheline.
- Mesure chronométrée réelle : `restoreGlobalToScratch()` (opération la plus lourde
  du système, Phase 14.11) — 2m03s avant optimisation, **1m22s après** le
  regroupement des `INSERT` par lots de 200 (correction appliquée Phase 14.12,
  reconfirmée fonctionnelle ici). Le reliquat (~1m20s) est dominé par ~102 opérations
  `DROP`/`CREATE TABLE` sur cette machine Windows/WAMP (probablement ralenties par
  l'antivirus scannant les fichiers InnoDB à la volée) — comportement à
  re-mesurer sur l'infrastructure cible réelle avant d'en tirer une conclusion de
  capacité de production.
- **Montée en charge réelle non testée** dans cet environnement (pas d'outil de
  charge disponible/installé, cohérent avec DT4 — aucune dépendance tierce). Les
  tests d'isolation multi-tenant valident la correction fonctionnelle sous
  plusieurs tenants simultanés, pas le débit sous charge concurrente réelle.
- Cache/Queue : primitives testées et fonctionnelles (Phase 14.9), mais adossées à
  un cache fichier et une file MySQL — pas encore à Redis/un vrai worker
  persistant (DT documentée, Phase 14.9).

## 9. Documentation

Point fort : **79 fichiers Markdown** de documentation technique produits au fil du
projet (blueprints, rapports d'implémentation, revues d'intégration, gels de
module) — une base de documentation architecturale et fonctionnelle inhabituellement
riche pour un projet de cette taille.

Manques identifiés (signalés, non comblés — hors périmètre "aucune nouvelle
fonctionnalité", mais listés comme demandé) :
- Aucun point d'entrée unique consolidé (`README.md`) pour un nouvel arrivant.
- Aucun guide d'installation/déploiement pas-à-pas (DT6).
- Documentation API : `GET /api/docs` (Swagger UI, vérifié fonctionnel après §2)
  sert de documentation API vivante — bon point, mais non complétée par un document
  narratif de démarrage rapide.
- Documentation base de données : `DATABASE_V2.md` existe (Phase Migration V2) mais
  date d'avant plusieurs phases ultérieures (Multi-Tenant notamment) — à
  rafraîchir avant une release publique.

## 10. Préparation production

| Élément | État |
|---|---|
| Configuration/variables d'environnement | ✅ `.env` centralisé, `config/*.php` cohérents ; 1 défaut non sûr corrigé (§3.1) |
| Migrations | ✅ `database/migrate.php` fonctionnel, idempotent, 18 migrations Multi-Tenant appliquées avec succès ; fuseau horaire CLI corrigé (§3.2) |
| Sauvegardes | ✅ Système complet et testé (Phase 14.11), performance optimisée cette phase |
| Restauration | ✅ Testée de bout en bout, y compris vers une base MySQL réelle jetable |
| Monitoring/logs | ✅ `Logger::security()`, portail Super-Admin (monitoring cache/queue), aucun agrégateur de logs externe (cohérent avec l'absence de dépendances tierces) |
| Installation | ❌ Aucun script/guide d'installation formalisé (DT6) |
| Dépôt de code source | ❌ Aucun dépôt Git initialisé (DT5) |

## 11. Risques

1. **Le plus important** : si RC1 est communiqué en interne/externe comme "les 14
   modules sont prêts", c'est factuellement incorrect pour 9 d'entre eux (§5) — un
   déploiement commercial sur cette base créerait des attentes non tenables.
2. Sans dépôt Git (DT5), aucune traçabilité de version, aucun retour arrière propre
   possible en cas de problème post-déploiement.
3. Le bug §2 (API totalement cassée) démontre qu'un test vert au niveau unitaire
   n'implique pas un chemin HTTP fonctionnel — risque méthodologique à retenir pour
   la suite : au moins un test de fumée HTTP réel par module avant tout futur GO.

## 12. Recommandations

1. Avant toute communication externe sur cette RC1, corriger l'affirmation "tous
   les modules terminés" pour refléter l'état réel (§5) — ou traiter l'application
   des migrations Finance/RH/Vie scolaire comme un prérequis bloquant explicite,
   séparé de cette phase de stabilisation.
2. Initialiser un dépôt Git avec un `.gitignore` couvrant `.env`, `storage/cache/`,
   `storage/tenants/` avant toute mise en production — actuellement aucune
   traçabilité de version n'existe pour ce projet.
3. Rédiger un `README.md`/`INSTALL.md` minimal (prérequis, étapes d'installation,
   variables d'environnement requises) — l'information existe déjà éparpillée dans
   79 fichiers de rapport, mais rien de consolidé pour un nouvel opérateur.
4. Ajouter au moins un test de fumée HTTP réel (pas seulement unitaire) par module
   activé, exécuté après chaque phase — c'est le seul type de test qui aurait
   détecté le bug §2 plus tôt.
5. Mesurer les performances de sauvegarde/restauration sur l'infrastructure cible
   réelle avant de fixer des objectifs de RTO/RPO basés sur les chiffres de cet
   environnement de développement local.

## 13. Décision finale

**GO WITH FIXES**

Justification : deux anomalies véritablement critiques ont été trouvées et
corrigées pendant cette phase (API Platform totalement inaccessible en HTTP,
autoloader de production incomplet) — sans cette revue, la RC1 aurait été
communiquée avec un module entier non fonctionnel. Trois anomalies mineures
supplémentaires ont également été corrigées. La suite de tests complète (530/530)
est verte après corrections, et le socle réellement démontré fonctionnel
(Core, Scolarité/Académique V1, Multi-Tenant complet, API Platform après
correction) est solide, testé en profondeur, et vérifié par des appels HTTP réels,
pas seulement des tests isolés.

Ce n'est cependant pas un **GO RC1** sans réserve : l'affirmation de départ selon
laquelle les 14 modules sont "officiellement terminés et validés" ne résiste pas à
la vérification empirique — 3 modules marqués actifs (Finance, RH, Vie scolaire)
n'ont aucune table appliquée et planteraient au premier usage réel, et 6 autres
modules restent désactivés sans jamais avoir tourné contre une base réelle. Ce
n'est pas un défaut introduit par cette phase, mais une release candidate ne peut
pas être déclarée prête sur la base d'une affirmation non vérifiée.

**Conditions à lever avant un GO RC1 sans réserve** : appliquer (ou retirer
honnêtement la mention "terminé") les migrations Finance/RH/Vie scolaire ;
initialiser le contrôle de version ; produire une documentation d'installation
minimale. Aucune de ces conditions ne remet en cause la qualité du travail déjà
livré — elles portent sur des périmètres qui n'ont, à ce jour, jamais été vérifiés
en conditions réelles dans cet environnement.
