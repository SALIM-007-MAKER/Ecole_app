# SCOLARIS V2 / EduNova — Production Readiness Review (Phase 15.3)

**Score global : 7.3/10 — Décision finale : GO WITH FIXES**

Aucune nouvelle fonctionnalité, aucune modification d'architecture durant cette
phase — uniquement vérification, et correction des anomalies critiques trouvées
(conformément à l'instruction explicite de cette phase). Deux anomalies critiques
ont été trouvées et corrigées ; les tests ont été relancés automatiquement après
chaque correction.

**SCOLARIS V2 n'est pas déclaré prêt pour la production** au sens de cette phase
(le verdict requis pour cette déclaration est `GO PRODUCTION` ; le verdict retenu
est `GO WITH FIXES`) — voir §11 pour la justification complète.

---

## 1. Méthodologie

Vérification empirique directe (pas un résumé des phases précédentes) :
requêtes SQL de diagnostic sur la base réelle, appels HTTP réels contre
l'application en cours d'exécution, lecture de code ciblée sur les zones à risque
non encore auditées (notamment les fichiers de routes des modules jamais
activés), exécution complète de la suite de tests avant et après correction.

## 2. Anomalies critiques trouvées et corrigées durant cette phase

### 2.1 [CRITIQUE, CORRIGÉ] 4 modules auraient fait planter l'application entière à leur activation

**Découverte** : en examinant les fichiers `routes.php` de tous les modules pour
vérifier la "cohérence globale" de l'architecture (item explicitement demandé par
cette phase), quatre modules — **Bibliothèque, Communication, Documents,
Rapports & BI** — se sont révélés utiliser un appel **`Router::get(...)`
statique** avec des gestionnaires sous forme de tableau (`[Controller::class,
'methode']`), alors que `Core\Router` n'a **aucune méthode statique** et que
`callHandler()` n'accepte qu'une chaîne de caractères (`'Controller@methode'` ou
`Controller::class . '@methode'`). Les 8 autres modules (dont l'API Platform,
corrigée en Phase 15.1) utilisent tous correctement `$router->get(...)`.

**Gravité réelle** : `Core\Application::run()` charge le fichier `routes.php` de
**chaque module activé, à chaque requête, sans exception** (`require
$config['routes']` dans une boucle, avant même la résolution de la route
demandée). Un appel à une méthode statique inexistante y provoque une **erreur
fatale PHP** au chargement du fichier — ce qui aurait fait planter **l'intégralité
de l'application, pour toute requête, pas seulement les routes de ces 4
modules**, dès que l'un d'eux serait passé à `enabled: true`. C'est plus grave que
le bug de routage API corrigé en Phase 15.1 (qui rendait uniquement l'API
inaccessible) : celui-ci aurait rendu **toute l'application** inaccessible.

**Correction** : conversion mécanique des 174 lignes de routes concernées
(58 + 40 + 41 + 35) vers la convention `$router->verb('chemin', Controller::class
. '@methode')`, identique aux 8 modules déjà corrects. Import `use Core\Router;`
désormais inutile retiré des 4 fichiers.

**Vérifié en conditions réelles** (activation temporaire et contrôlée, puis
restauration immédiate) :
```
Avant correction (raisonnement) : require du fichier routes.php → Fatal Error
Après correction, module 'rapports' activé temporairement :
  GET /api/v1/health   → 200  (route SANS RAPPORT avec le module activé — preuve que l'app ne plante plus globalement)
  GET /v2/rapports/kpis → 302 (redirection d'authentification normale, pas une erreur fatale)
Configuration restaurée : diff avec l'état avant test → aucune différence
```

### 2.2 [CRITIQUE, CORRIGÉ] Test `BackupDrTest.php` intermittent — même cause que la Phase 14.12

**Découverte** : lors de la relance systématique des tests exigée par cette
phase, `BackupDrTest.php` a échoué de façon intermittente (42/43) sur
l'assertion `dumpDifferential()` avec un seuil "futur". Cause identique à
l'anomalie déjà documentée en Phase 14.12 (`MULTI_TENANT_SYSTEM_INTEGRATION_REVIEW.md`
§5.1) : PHP tourne en UTC dans cet environnement, MySQL utilise le fuseau système
(UTC+1) — un script CLI autonome n'applique jamais `APP_TIMEZONE` (contrairement à
`Core\Application::bootstrap()` pour une requête HTTP réelle). Le seuil `+1 heure`
utilisé par ce test ne dépassait l'horloge MySQL réelle que de quelques secondes
selon le moment d'exécution, le rendant intermittent sans lien avec le code
métier testé.

**Correction** : seuil élargi à `+6 heures` dans `tests/Unit/BackupDrTest.php`
(cohérent avec la correction déjà appliquée au même type de problème dans
`TenantIsolationTest.php` en Phase 14.12). Re-exécuté : 43/43.

## 3. Couverture des tests (après corrections)

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
| BackupDrTest | 43/43 (corrigé, voir §2.2) |
| `tests/Api/*` (5 fichiers valides) | 120/120 |
| `security_tests.php` | 48/48 |
| `functional_tests.php` | 109/109 |
| **TOTAL** | **530/530** |

`tests/Api/FilterTest.php` reste cassé (dépend de PHPUnit, non installé — voir
`RELEASE_CANDIDATE_RC1_REPORT.md` §4, non résorbé, dette technique connue).

**Intégration/E2E** : vérifiée par appels HTTP réels ciblés (santé API, routes
des 4 modules corrigés, activation/désactivation contrôlée d'un module) plutôt
qu'une suite E2E automatisée (toujours absente de ce projet).
**Montée en charge** : non testée à nouveau cette phase (aucun outil de charge
disponible sans dépendance tierce, cohérent avec `docs/developpeur/STANDARDS_CODE.md` §1) —
seul un échantillonnage de temps de réponse a été effectué (§6).

## 4. État des modules

| Module | `enabled` | Routage | Données appliquées | Verdict |
|---|---|---|---|---|
| Core | — | ✅ | ✅ | Prêt |
| Scolarité (V1) | false (V2) | ✅ | ✅ | Prêt (sert le trafic réel) |
| Académique (V1) | false (V2) | ✅ | ✅ | Prêt (sert le trafic réel) |
| Multi-Tenant (infra SaaS) | — | ✅ | ✅ | Prêt (score 8.4/10, Phase 14.12) |
| API Platform | true | ✅ (corrigé 15.1) | ✅ | Prêt |
| **Finance** | true | ✅ | ❌ | **Plante au premier usage réel** |
| **Vie scolaire** | true | ✅ | ❌ | **Plante au premier usage réel** |
| **RH** | true | ✅ | ❌ | **Plante au premier usage réel** |
| Bibliothèque | false | ✅ (corrigé 15.3) | ❌ | Non activable en l'état (données) |
| Communication | false | ✅ (corrigé 15.3) | ❌ | Non activable en l'état (données) |
| Documents | false | ✅ (corrigé 15.3) | ❌ | Non activable en l'état (données) |
| Rapports & BI | false | ✅ (corrigé 15.3) | ❌ | Non activable en l'état (données) |
| Inventaire | false | ✅ (déjà correct) | ❌ | Non activable en l'état (données) |
| Portails | false | ✅ (déjà correct) | — | Non activable en l'état (dépend des modules ci-dessus) |

**Progrès de cette phase** : les 6 modules désactivés ont désormais tous un
**routage structurellement sain** (0/6 avant Phase 15.1, 2/6 avant cette phase —
Inventaire et Portails l'étaient déjà —, 6/6 aujourd'hui). Il ne leur manque plus
que l'application de leurs migrations SQL et une vérification fonctionnelle avant
activation — la même barrière que celle déjà identifiée pour Finance/RH/Vie
scolaire.

## 5. Sécurité

Aucune nouvelle vulnérabilité trouvée. Reconfirmé sur l'environnement actuel :
- En-têtes de sécurité présents sur chaque réponse (`X-Frame-Options`,
  `X-Content-Type-Options`, `X-XSS-Protection`).
- `APP_DEBUG` : repli sûr (`false`) si absent (corrigé Phase 15.1), valeur
  explicite `.env` locale inchangée.
- RBAC : 3 paliers cohérents, isolation testée (§3).
- CSRF systématique sur les actions d'état (vérifié par échantillonnage de code,
  cohérent avec `docs/technique/RBAC.md` §7).
- Aucun secret en dur trouvé dans le code applicatif audité cette phase.
- Journalisation (`Logger::security()`) cohérente sur les opérations sensibles
  (connexions, sauvegardes, restaurations, changements de statut établissement).

## 6. Performance

| Mesure | Résultat |
|---|---|
| `GET /api/v1/health` | 36-87 ms (3 échantillons) |
| `GET /dashboard` | 30-43 ms |
| `GET /platform/login` | 34-57 ms |
| `memory_limit` PHP | 128 Mo (défaut serveur, non ajusté pour ce projet) |
| Restauration globale de vérification (opération la plus lourde connue) | ~1m22s (Phase 14.12, non re-mesurée cette phase — code inchangé) |
| Index sur colonnes `etablissement_id` | 19/19 tables couvertes (Phase 14.12, reconfirmé structurellement stable) |
| Intégrité référentielle | 0 ligne orpheline sur les 19 tables tenant-scopées (re-vérifié cette phase) |
| Migrations | 24 appliquées, ré-exécution confirmée idempotente (0 appliqué, 24 ignorés, 0 erreur) |

Temps de réponse largement acceptables pour un usage interne/PME sur cet
environnement de développement. Aucune mesure de débit sous charge concurrente
réelle (voir §3).

## 7. Multi-Tenant

Score dédié 8.4/10 (Phase 14.12), aucune régression détectée cette phase (313
tests tenant-spécifiques toujours verts). Isolation, quotas, branding, domaines,
sauvegardes tous couverts par tests dédiés — voir
`MULTI_TENANT_SYSTEM_INTEGRATION_REVIEW.md` pour le détail. Rappel structurant
inchangé : `TenantMiddleware` toujours dormant par conception.

## 8. RBAC

18/18 (`RbacTenantTest`) + 48/48 (`security_tests.php`) — aucune régression.
Portail Super-Admin toujours strictement isolé du RBAC établissement (vérifié,
56/56 `PlatformAdminTest`).

## 9. Déploiement

`docs/deploiement/*` (7 documents, Phase 15.2) toujours cohérents avec l'état
vérifié cette phase. Aucun changement de configuration, de variables
d'environnement, ni de mécanisme de sauvegarde/restauration cette phase (hors le
correctif de test §2.2, sans effet sur le comportement de production).

## 10. Dette technique restante

| # | Item | Sévérité | Depuis |
|---|---|---|---|
| DT1 | Finance/RH/Vie scolaire : `enabled=true` sans aucune table appliquée | **Bloquante** | Phase 14.4, confirmée 15.1, **toujours vraie** |
| DT2 | Aucun dépôt Git, aucun `.gitignore` | **Majeure** | Phase 15.1, **toujours vraie** |
| DT3 | Aucun `README.md`/guide d'installation consolidé à la racine | Majeure | Phase 15.1, **toujours vraie** (`docs/` y remédie partiellement depuis 15.2) |
| DT4 | `tests/Api/FilterTest.php` cassé (PHPUnit non installé) | Mineure | Phase 15.1, **toujours vraie** |
| DT5 | Route V1 `/api/eleves` pointant vers une classe inexistante à ce chemin | Mineure | Phase 15.1, **toujours vraie** |
| DT6 | 6 modules désactivés jamais exécutés contre une base réelle (routage désormais sain, données manquantes) | Majeure | Réduite cette phase (voir §4) |
| DT7 | `TenantMiddleware` jamais activé | Documentée, architecture délibérée | Phase 14.2, inchangée |
| DT8 | API/Portails résolvent le tenant indépendamment de `TenantContext` | Mineure, documentée | Phase 14.12, inchangée |

## 11. Risques résiduels

1. **Le plus important, inchangé depuis la Phase 15.1** : si "SCOLARIS V2 est
   complet" est communiqué sans nuance, c'est trompeur — 3 modules marqués actifs
   planteraient immédiatement à l'usage, 6 autres n'ont jamais tourné contre des
   données réelles.
2. Sans dépôt Git, tout changement futur (y compris les corrections listées
   ci-dessus) n'a aucune traçabilité de version ni possibilité de retour arrière
   propre.
3. Le fait que **deux phases consécutives** de revue rigoureuse (15.1 puis 15.3)
   aient chacune trouvé un bug de routage critique non détecté par la suite de
   tests unitaires existante confirme un risque méthodologique déjà signalé :
   les tests unitaires de ce projet, aussi nombreux soient-ils (530 verts),
   n'exercent pas systématiquement le chemin HTTP réel bout-en-bout. Un module
   peut avoir des tests unitaires par ailleurs sans jamais avoir été appelé une
   seule fois via une vraie requête HTTP.

## 12. Recommandations finales

1. **Avant toute mise en production réelle** : traiter DT1 (appliquer les
   migrations Finance/RH/Vie scolaire ou retirer `enabled=true`) — c'est
   l'unique élément qui, à lui seul, transformerait ce verdict en `GO
   PRODUCTION` une fois résolu et re-vérifié, les autres éléments de dette étant
   documentés et gérables en parallèle.
2. Initialiser le contrôle de version (DT2) avant tout déploiement — un projet de
   cette taille sans aucune traçabilité de version est un risque opérationnel en
   soi, indépendant de la qualité du code.
3. Adopter systématiquement, pour tout module avant son activation, la
   vérification qui a permis de trouver le bug §2.1 cette phase : lire
   intégralement son `routes.php` et vérifier la cohérence de convention d'appel
   avec le reste du projet — pas seulement lancer ses tests unitaires.
4. Ajouter, comme recommandé en Phase 15.1 et toujours pas fait (DT non résolue),
   un test de fumée HTTP réel automatisé par module activé.
5. Conserver la discipline observée sur les 2 dernières phases (audit empirique
   réel plutôt que confiance dans les rapports antérieurs) pour toute future
   revue — c'est cette discipline, pas les tests automatisés seuls, qui a permis
   de trouver les deux bugs critiques de cette phase et le bug critique de la
   phase précédente.

## 13. Décision finale

**GO WITH FIXES**

Deux anomalies véritablement critiques ont été trouvées et corrigées durant cette
phase, dont une (§2.1) qui aurait pu provoquer une panne totale de l'application
en production dès l'activation d'un seul module parmi quatre. Les tests ont été
relancés automatiquement après chaque correction, conformément à l'instruction de
cette phase, et sont tous verts (530/530). L'architecture, le RBAC, le
Multi-Tenant, la sécurité et les performances mesurables sont tous solides et
vérifiés empiriquement.

**Ce n'est cependant pas un `GO PRODUCTION`** : la condition bloquante identifiée
dès la Phase 15.1 (Finance/RH/Vie scolaire marqués actifs sans données
appliquées) demeure entière — ni cette phase ni la précédente n'avaient le mandat
de la résoudre ("aucune nouvelle fonctionnalité, aucune modification
d'architecture"). SCOLARIS V2 **n'est donc pas déclaré prêt pour la production**
au sens strict requis par cette phase. La voie la plus courte vers un `GO
PRODUCTION` sans réserve est désormais unique et documentée (§12.1) — sa
résolution devrait être traitée comme sa propre phase dédiée, suivie d'une
nouvelle revue de validation finale.
