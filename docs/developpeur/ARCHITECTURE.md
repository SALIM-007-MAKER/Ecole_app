# Architecture — Repères pour développeurs

Ce document est le complément **orienté développeur** de
`docs/technique/ARCHITECTURE_GLOBALE.md` (référence architecturale complète, à
lire en premier). Il se concentre sur ce qu'il faut savoir pour **écrire du code**
dans ce projet au quotidien.

## 1. Comment une classe est-elle trouvée ?

Autoloader PSR-4 maison, `public/index.php`, table `$namespaces` :

```php
'Core\\'              => ROOT_PATH . '/core/',
'App\\Controllers\\'  => ROOT_PATH . '/app/Controllers/',
'App\\Models\\'       => ROOT_PATH . '/app/Models/',
'App\\Middleware\\'   => ROOT_PATH . '/app/Middleware/',
'App\\Services\\'     => ROOT_PATH . '/app/Services/',
'App\\Events\\'       => ROOT_PATH . '/app/Events/',
'App\\Listeners\\'    => ROOT_PATH . '/app/Listeners/',
'App\\Modules\\'      => ROOT_PATH . '/app/Modules/',
'App\\Jobs\\'         => ROOT_PATH . '/app/Jobs/',
'App\\Shared\\'       => ROOT_PATH . '/app/Shared/',
```

**Si vous créez un nouveau namespace racine** (`App\NouveauTruc\`), il **doit**
être ajouté ici explicitement — sinon la classe n'est trouvable qu'en test (si le
fichier de test définit son propre autoloader privé) mais jamais en production.
C'est exactement le bug corrigé en Phase 15.1 pour `App\Shared\` (oublié pendant
plusieurs phases) — voir `RELEASE_CANDIDATE_RC1_REPORT.md` §2.2.

## 2. Comment une route est-elle résolue vers une classe ?

`Core\Router::callHandler()` accepte **trois conventions** pour le second argument
de `$router->get()`/`post()`/etc. :

| Convention | Exemple | Résolution |
|---|---|---|
| Nom brut (V1) | `'EleveController@index'` | `App\Controllers\EleveController` |
| Chemin partiel (V2) | `'Finance\Controllers\FraisController@index'` | `App\Modules\Finance\Controllers\FraisController` |
| Nom pleinement qualifié | `HealthController::class . '@check'` | Utilisé tel quel (commence par `App\`) |

La troisième convention a été ajoutée en Phase 15.1 pour corriger un bug de
double-préfixage qui rendait 122 routes API inaccessibles — si vous ajoutez une
quatrième convention un jour, testez les trois existantes avant de merger.

## 3. Où écrire quoi ?

| Je veux... | J'écris dans... |
|---|---|
| Un écran V1 classique (CRUD simple, module ancien) | `app/Controllers/`, `app/Models/`, `app/Views/` |
| Un nouveau domaine métier structuré | `app/Modules/{Module}/` (voir `docs/technique/ARCHITECTURE_MODULES.md` §1) |
| Un composant transverse à toute l'app (cache, storage, queue, backup, tenant) | `core/{Domaine}/` |
| Un service partagé consommé par l'API Platform | `app/Shared/` (n'oubliez pas §1 ci-dessus si vous créez un nouveau sous-namespace) |
| Une tâche de file d'attente | `app/Jobs/`, implémente `Core\Queue\Job` |

## 4. Cycle de vie d'une requête — voir aussi

`docs/technique/ARCHITECTURE_GLOBALE.md` §4.

## 5. Avant de merger un changement touchant `core/`

`core/` est consommé par **tous** les contrôleurs, tous les modules, l'API
Platform, et les scripts CLI (`migrate.php`, `queue-worker.php`). Un changement ici
a un rayon d'impact maximal — exécutez la suite de tests complète
(`docs/developpeur/TESTS.md`) et, si le changement touche le routage ou
l'autoloading, faites au moins un test HTTP réel (`curl`) sur une route de chaque
convention avant de considérer le changement sûr — c'est précisément ce qui a
manqué avant Phase 15.1.
