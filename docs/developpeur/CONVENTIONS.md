# Conventions de développement — SCOLARIS V2

## 1. Créer un module V2

1. `app/Modules/{Nom}/` avec la structure standard (voir
   `docs/technique/ARCHITECTURE_MODULES.md` §1) :
   `module.json`, `routes.php`, `Controllers/`, `Services/`, `Repositories/`,
   `Events/`, `Listeners/`, `DTO/`, `Policies/`, `Views/`.
2. `module.json` : déclarer `name`, `namespace`, `sql_tables` (liste exhaustive et
   tenue à jour — c'est cette liste qui a permis de détecter, en Phase 15.1, que
   Finance/RH/Vie scolaire n'avaient aucune table réellement appliquée), `permissions`.
3. Ajouter l'entrée dans `config/modules.php` :
   ```php
   '{nom}' => [
       'enabled'   => false,  // true seulement après migrations appliquées ET testées
       'namespace' => 'App\\Modules\\{Nom}',
       'routes'    => ROOT_PATH . '/app/Modules/{Nom}/routes.php',
       'manifest'  => ROOT_PATH . '/app/Modules/{Nom}/module.json',
   ],
   ```
4. Écrire les migrations SQL correspondant à `sql_tables` (voir §3 ci-dessous).
5. **Ne jamais activer `enabled=true` avant d'avoir vérifié que les tables
   existent réellement** (`SHOW TABLES LIKE '{prefixe}_%'`) — voir
   `docs/technique/ARCHITECTURE_MODULES.md` §4 pour ce qui arrive sinon.

## 2. Créer une route

```php
// Convention V1 (app/Controllers/)
$router->get('/mon-ecran', 'MonController@index');

// Convention V2 (app/Modules/{Nom}/Controllers/)
$router->get('/api/v1/ma-ressource', MaApiController::class . '@index');
```
Routes paramétrées AVANT les routes statiques en conflit potentiel — voir l'ordre
dans `config/routes.php` (ex. `/eleves/create` déclaré avant `/eleves/{id}`).

## 3. Écrire une migration

```php
<?php
return [
    'id'         => 'T019',                       // identifiant unique, séquentiel
    'name'       => 'Description courte',
    'reversible' => true,
    'run' => function (PDO $pdo): void {
        $exists = $pdo->query("SHOW TABLES LIKE 'ma_table'")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($exists)) {
            $pdo->exec("CREATE TABLE `ma_table` (...) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    },
    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `ma_table`");
    },
];
```
**Toujours idempotente** (vérifier l'existence avant de créer/altérer) — le runner
peut être relancé sans risque. Voir `docs/technique/BASE_DE_DONNEES.md` §6.

## 4. Créer un événement/listener

Voir `docs/technique/EVENT_SYSTEM.md` §4/§5.

## 5. Ajouter une permission

1. `config/permissions.php` : ajouter le code (`module.action`) à la liste des
   rôles concernés.
2. Si le contexte multi-tenant l'exige, ajouter une migration seedant
   `etab_permissions`/`etab_role_permissions` (voir les migrations `T009`, `T012`,
   `T014`, `T016` comme modèles).

## 6. Conventions de nommage SQL

Voir `docs/technique/BASE_DE_DONNEES.md` §2.

## 7. Revue avant merge — checklist minimale

- [ ] Tests unitaires ajoutés/mis à jour et verts (`docs/developpeur/TESTS.md`)
- [ ] Si nouvelle table tenant-scopée : colonne `etablissement_id` indexée
- [ ] Si nouveau namespace racine : autoloader `public/index.php` mis à jour
- [ ] Si nouvelle route : testée avec un appel HTTP réel, pas seulement en test
      unitaire (voir `docs/developpeur/ARCHITECTURE.md` §5)
- [ ] Aucune régression sur `tests/security_tests.php`/`functional_tests.php`
