# Développer sur l'API Platform — Guide développeur

Référence consommateur (endpoints existants, authentification, pagination) :
`docs/technique/API_REST.md`. Ce document couvre l'ajout de nouvelles ressources
côté développeur.

## 1. Ajouter une ressource API

1. Contrôleur sous `app/Modules/Api/Controllers/V1/{Domaine}/{Ressource}ApiController.php`,
   étendant `ApiBaseController` (authentification, permissions, réponses
   standardisées) ou `ResourceApiController` (CRUD générique) selon le besoin.
2. Enregistrer les routes dans `app/Modules/Api/routes.php` :
   ```php
   use App\Modules\Api\Controllers\V1\{Domaine}\{Ressource}ApiController;
   $router->get('/api/v1/{ressources}', {Ressource}ApiController::class . '@index');
   ```
   **Toujours** avec la syntaxe `Controller::class . '@methode'` (cohérent avec le
   reste du module depuis Phase 15.1 — voir
   `docs/developpeur/ARCHITECTURE.md` §2).
3. Réutiliser `App\Shared\Api\PaginationService`/`FilterService` pour toute
   ressource listable — ne pas réimplémenter la pagination/le filtrage à la main.
4. Réponse toujours via `ApiResponseBuilder` (enveloppe `{"success":...}` uniforme).

## 2. Authentification dans un nouveau contrôleur

```php
protected function index(): void
{
    $ctx = $this->requireApiAuth();               // 401 automatique si absent
    $this->requireApiPermission('mon.permission'); // 403 automatique si absent
    $etabId = $ctx->etablissementId;               // scoper systématiquement dessus
    // ...
}
```

## 3. Tester

Voir `tests/Api/*.php` comme modèles. **Attention** : ces fichiers de test
définissent chacun leur propre autoloader privé (incluant `App\Shared\Api\`) — ce
qui les a rendus verts pendant plusieurs phases alors que le vrai bootstrap de
production (`public/index.php`) avait un gap (`App\Shared\` non enregistré,
corrigé Phase 15.1). **Complétez systématiquement vos tests unitaires par au
moins un appel HTTP réel** (`curl` contre l'application qui tourne) avant de
considérer une nouvelle route API terminée — voir
`docs/developpeur/TESTS.md` §4.

## 4. Rate limiting et CORS

Gérés automatiquement par `ApiBaseController` pour toute route qui en hérite — pas
d'action nécessaire sauf ajustement des seuils (`config/api.php`).

## 5. Documentation vivante

Toute nouvelle route doit apparaître dans le Swagger (`GET /api/docs`) — vérifier
`app/Modules/Api/Controllers/Documentation/SwaggerController.php` pour le
mécanisme de génération de la spécification.
