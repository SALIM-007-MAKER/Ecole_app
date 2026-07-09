# API PLATFORM V2 — BLUEPRINT COMPLET
**SCOLARIS V2 — Plateforme API Publique**
**Phase :** 13.1
**Date :** 2026-07-05
**Statut :** BLUEPRINT — Aucune implémentation

---

## TABLE DES MATIÈRES

1. [Vision & Principes](#1-vision--principes)
2. [Architecture globale](#2-architecture-globale)
3. [API Gateway](#3-api-gateway)
4. [Authentification](#4-authentification)
5. [Versionnement](#5-versionnement)
6. [Documentation OpenAPI 3.1](#6-documentation-openapi-31)
7. [Sécurité](#7-sécurité)
8. [Permissions & RBAC](#8-permissions--rbac)
9. [Réponses standardisées](#9-réponses-standardisées)
10. [Pagination, Filtrage, Tri, Recherche](#10-pagination-filtrage-tri-recherche)
11. [Upload de fichiers](#11-upload-de-fichiers)
12. [Gestion des erreurs](#12-gestion-des-erreurs)
13. [Webhooks](#13-webhooks)
14. [SDK Clients](#14-sdk-clients)
15. [Monitoring & Observabilité](#15-monitoring--observabilité)
16. [Intégrations modules](#16-intégrations-modules)
17. [Architecture des dossiers](#17-architecture-des-dossiers)
18. [Base de données — Tables API](#18-base-de-données--tables-api)
19. [Conventions REST](#19-conventions-rest)
20. [Roadmap API V2.1](#20-roadmap-api-v21)

---

## 1. VISION & PRINCIPES

### 1.1 Objectif

Exposer l'ensemble des fonctionnalités de SCOLARIS V2 à des applications tierces via une API REST JSON unifiée, sécurisée et documentée, en respectant les principes **API-First**.

### 1.2 Principes directeurs

| Principe | Application |
|---------|------------|
| **API-First** | Toute fonctionnalité est d'abord conçue comme une API, puis consommée en interne |
| **Backward Compatibility** | Une fois publiée, une route v1 ne peut jamais casser |
| **Security by Default** | Auth requise sur toutes les routes, RBAC granulaire |
| **Graceful Degradation** | Erreurs métier = 4xx, pas de 500 exposés |
| **Multi-Tenant Ready** | `etablissement_id` toujours présent dans tous les contextes |
| **Idempotence** | PUT et DELETE idempotents par convention |
| **Observabilité** | Chaque requête trace : qui, quoi, quand, durée, résultat |

### 1.3 Contexte technique existant

- Framework PHP 8.2 custom (`Core\` namespace)
- `Core\Router` — instance-based, `get/post/put/delete`, params `{param}`
- `Core\EventDispatcher` — statique, synchrone
- `Core\Controller` — `json()`, `requireAuth()`, `currentUser()`, `requirePermission()`
- RBAC dans `config/permissions.php` (rôles → tableaux de permissions)
- Tokens Bearer existants : table `portal_api_tokens` (clé simple, TTL 1h)
- `Core\Logger` disponible

L'API Platform V2 **étend** cette fondation sans rien réécrire.

---

## 2. ARCHITECTURE GLOBALE

```
┌──────────────────────────────────────────────────────────────────┐
│                        CLIENTS EXTERNES                          │
│   Mobile (Flutter)  │  PWA  │  JS App  │  PHP SDK  │  Tiers    │
└──────────────────────────┬───────────────────────────────────────┘
                           │ HTTPS
                           ▼
┌──────────────────────────────────────────────────────────────────┐
│                        API GATEWAY                               │
│  /api/v1/*  │  Rate Limiter  │  CORS  │  Auth Resolver  │  Log  │
└──────┬───────────────────────────────────────────────────────────┘
       │
       ├─── Middleware Stack (Auth → Tenant → Permission → Validate)
       │
       ▼
┌──────────────────────────────────────────────────────────────────┐
│                    API CONTROLLERS (thin)                        │
│  ApiController (abstract)  →  ResourceController (abstract)     │
│  EleveApiController │ ClasseApiController │ NoteApiController…   │
└──────┬───────────────────────────────────────────────────────────┘
       │
       ├─── ApiResource (transformateur DTO → JSON)
       │
       ▼
┌──────────────────────────────────────────────────────────────────┐
│              SERVICES MODULES EXISTANTS (lecture seule)          │
│  EleveService │ ClasseService │ NoteService │ InvoiceService…    │
└──────────────────────────────────────────────────────────────────┘
       │
       ▼
┌──────────────────────────────────────────────────────────────────┐
│                  COUCHES TRANSVERSALES API                       │
│  WebhookDispatcher │ RateLimiter │ ApiLogger │ MetricsCollector  │
└──────────────────────────────────────────────────────────────────┘
```

### 2.1 Séparation des responsabilités

```
Route → Gateway Middleware → ApiController → ApiResource → JSON
          (auth/rate/cors)    (thin: valide,   (transforme,
                               appelle service) masque champs)
```

**Règle stricte :** Les ApiControllers ne contiennent aucune logique métier. Ils ne font que :
1. Valider les inputs via `ApiRequest`
2. Appeler le service du module correspondant
3. Passer le résultat à `ApiResource` pour transformation
4. Retourner `json()`

---

## 3. API GATEWAY

### 3.1 Point d'entrée unique

Toutes les requêtes API transitent par `/api/v1/`. Le routeur `Core\Router` achemine vers la stack de middleware, puis vers le contrôleur.

Préfixe officiel : `/api/v1/`
Préfixe futur : `/api/v2/` (lorsque des breaking changes seront nécessaires)

### 3.2 Middleware Stack — ordre d'exécution

```
1. CorsMiddleware          — entêtes CORS + preflight OPTIONS
2. RateLimitMiddleware     — vérification quota avant tout traitement
3. ApiAuthMiddleware       — résolution du porteur d'identité (JWT / API Key / Session)
4. TenantMiddleware        — résolution de l'établissement (etab_id dans le contexte)
5. PermissionMiddleware    — vérification RBAC de la route demandée
6. ValidationMiddleware    — validation du corps de la requête via ApiRequest
7. ApiLoggingMiddleware    — journalisation entrée/sortie (après réponse)
```

Chaque middleware implémente `Core\Middleware` :

```php
interface Middleware
{
    public function handle(ApiRequest $request, callable $next): ApiResponse;
}
```

### 3.3 API Request / Response cycle

```php
// Résolution dans Core\Application ou un ApiKernel dédié
class ApiKernel
{
    private array $middlewares = [
        CorsMiddleware::class,
        RateLimitMiddleware::class,
        ApiAuthMiddleware::class,
        TenantMiddleware::class,
        PermissionMiddleware::class,
        ValidationMiddleware::class,
        ApiLoggingMiddleware::class,
    ];

    public function handle(Request $request): void
    {
        // Pipeline de middlewares en oignon
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            fn($carry, $class) => fn($req) => (new $class)->handle($req, $carry),
            fn($req) => $this->router->dispatch($req)
        );
        $pipeline($request);
    }
}
```

### 3.4 Gestion des erreurs Gateway

| Situation | Code | Corps |
|-----------|------|-------|
| Route inconnue | 404 | `{"error": "route_not_found"}` |
| Méthode non autorisée | 405 | `{"error": "method_not_allowed", "allowed": ["GET","POST"]}` |
| Payload > 10 Mo | 413 | `{"error": "payload_too_large"}` |
| Content-Type invalide | 415 | `{"error": "unsupported_media_type"}` |
| Rate limit dépassé | 429 | `{"error": "rate_limit_exceeded", "retry_after": 60}` |
| Erreur interne non attrapée | 500 | `{"error": "internal_error", "ref": "ERR-{uuid}"}` (jamais de stacktrace en prod) |

---

## 4. AUTHENTIFICATION

### 4.1 Vue d'ensemble — 3 méthodes supportées

```
┌─────────────────────────────────────────────────────────────┐
│  Méthode         │  Usage                │  Stockage        │
├─────────────────────────────────────────────────────────────┤
│  JWT             │  Clients stateless    │  En mémoire      │
│  API Key         │  Intégrations serveur │  Table api_keys  │
│  OAuth2 (stub)   │  Délégation tierce    │  Table oauth_*   │
└─────────────────────────────────────────────────────────────┘
```

### 4.2 JWT (JSON Web Token)

**Algorithme :** HS256 avec clé secrète de 64 octets (stockée dans `config/app.php`)
**TTL Access Token :** 15 minutes
**TTL Refresh Token :** 30 jours
**Header :** `Authorization: Bearer {jwt}`

#### Structure du JWT Payload

```json
{
  "iss": "scolaris-v2",
  "aud": "api-v1",
  "sub": "42",
  "jti": "uuid-v4",
  "iat": 1751500000,
  "exp": 1751500900,
  "etab": 1,
  "role": "enseignant",
  "perms": ["notes.view", "absences.view_own", "evaluations.create"],
  "portal": "enseignant"
}
```

> `perms` : liste compactée des permissions du rôle (évite une requête DB à chaque appel).
> Si le rôle change, le refresh token force la régénération avec les nouvelles permissions.

#### Flow JWT complet

```
POST /api/v1/auth/login
  → {email, password}
  ← {access_token, refresh_token, expires_in: 900, token_type: "Bearer"}

POST /api/v1/auth/refresh
  → {refresh_token}
  ← {access_token, expires_in: 900}

POST /api/v1/auth/logout
  → Bearer {access_token}
  ← {success: true}  (refresh_token révoqué en DB)
```

#### Table `api_refresh_tokens`

```sql
CREATE TABLE api_refresh_tokens (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    token_hash      VARCHAR(64) NOT NULL UNIQUE,   -- SHA-256 du refresh token
    etablissement_id INT UNSIGNED NOT NULL,
    expires_at      DATETIME NOT NULL,
    revoked_at      DATETIME NULL,
    user_agent      VARCHAR(500) NULL,
    ip_address      VARCHAR(45) NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at)
);
```

### 4.3 API Keys (intégrations serveur-à-serveur)

**Usage :** scripts automatisés, ERPs, systèmes RH tiers, intégrations SaaS
**Transmission :** Header `X-Api-Key: {key}` ou query param `?api_key={key}` (déconseillé)
**Format :** `sk_live_{32_hex}` (production) / `sk_test_{32_hex}` (test)

#### Table `api_keys`

```sql
CREATE TABLE api_keys (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    etablissement_id INT UNSIGNED NOT NULL,
    name             VARCHAR(100) NOT NULL,
    key_prefix       VARCHAR(10) NOT NULL,    -- 'sk_live_' ou 'sk_test_'
    key_hash         VARCHAR(64) NOT NULL UNIQUE,  -- SHA-256 de la clé complète
    key_hint         VARCHAR(8) NOT NULL,      -- 8 derniers chars pour affichage
    permissions      JSON NOT NULL DEFAULT '[]',
    rate_limit       INT UNSIGNED DEFAULT 1000,   -- req/heure
    allowed_ips      JSON NULL,                   -- whitelist IP (null = tous)
    expires_at       DATETIME NULL,
    last_used_at     DATETIME NULL,
    revoked_at       DATETIME NULL,
    created_by       INT UNSIGNED NOT NULL,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_etab (etablissement_id),
    INDEX idx_prefix (key_prefix)
);
```

**Sécurité :**
- La clé complète n'est affichée qu'une seule fois à la création
- Seul le hash SHA-256 est stocké en base
- Rotation possible : nouvelle clé émise, ancienne révoquée après 7 jours (grace period)

### 4.4 OAuth2 (stub — V2.1)

Structure préparée pour délégation de droits tiers (portail parents d'une ENT, système de notes externe) :

```sql
-- Tables préparées, implémentation différée Phase 13.x
CREATE TABLE oauth_clients (...);    -- applications tierces enregistrées
CREATE TABLE oauth_auth_codes (...); -- codes d'autorisation (TTL 10 min)
CREATE TABLE oauth_access_tokens (...);
CREATE TABLE oauth_refresh_tokens (...);
CREATE TABLE oauth_scopes (...);     -- mapping scopes → permissions RBAC
```

**Flows prévus :** Authorization Code + PKCE (applications publiques), Client Credentials (serveur-à-serveur)

### 4.5 Résolution d'identité — `ApiAuthMiddleware`

```php
class ApiAuthMiddleware implements Middleware
{
    public function handle(ApiRequest $request, callable $next): ApiResponse
    {
        // 1. JWT (priorité)
        if ($jwt = $this->extractBearer($request)) {
            $context = $this->resolveJwt($jwt);          // vérifie signature + exp
        }
        // 2. API Key
        elseif ($key = $request->header('X-Api-Key')) {
            $context = $this->resolveApiKey($key);       // hash + vérif whitelist IP
        }
        // 3. Session PHP (backward compat navigateur)
        else {
            $context = $this->resolveSession();
        }

        if ($context === null) {
            return ApiResponse::unauthorized();
        }

        $request->setAuthContext($context);
        return $next($request);
    }
}
```

**`AuthContext` DTO :**
```php
class AuthContext
{
    public int    $userId;
    public int    $etablissementId;
    public string $role;
    public array  $permissions;
    public string $authMethod;    // 'jwt' | 'api_key' | 'session'
    public ?string $apiKeyId;
    public ?string $jti;          // JWT ID pour révocation
}
```

---

## 5. VERSIONNEMENT

### 5.1 Stratégie URL-based

```
/api/v1/eleves          ← actuelle
/api/v2/eleves          ← futures breaking changes uniquement
```

**Règle :** une nouvelle version majeure uniquement en cas de breaking change inévitable.
Les ajouts de champs, endpoints optionnels, nouvelles ressources sont rétro-compatibles et ne nécessitent pas de nouvelle version.

### 5.2 Cycle de vie des versions

```
v1 (current)   → Active — SLA de support garanti 18 mois après sortie de v2
v2 (future)    → Annoncé 3 mois avant lancement, migration guide fourni
vN dépréciée   → Header Deprecation + Sunset ajouté à chaque réponse
```

### 5.3 Headers de version

```http
# Sur les routes actives
API-Version: 1
API-Deprecated: false

# Sur les routes dépréciées
API-Version: 1
API-Deprecated: true
Deprecation: true
Sunset: Sat, 01 Jan 2028 00:00:00 GMT
Link: </api/v2/eleves>; rel="successor-version"
```

### 5.4 Compatibilité — règles immuables

| Action | Autorisé en v1 | Nécessite v2 |
|--------|---------------|--------------|
| Ajouter un champ optionnel à la réponse | ✅ | — |
| Ajouter un paramètre optionnel | ✅ | — |
| Ajouter un nouvel endpoint | ✅ | — |
| Supprimer un champ de réponse | ❌ | ✅ |
| Changer le type d'un champ | ❌ | ✅ |
| Changer la sémantique d'un paramètre | ❌ | ✅ |
| Modifier les codes HTTP retournés | ❌ | ✅ |

---

## 6. DOCUMENTATION OPENAPI 3.1

### 6.1 Stratégie de génération

**Approche : Code → Spec** (pas de spec → code)
Les annotations PHP dans les controllers génèrent automatiquement le fichier `openapi.json` via un script de build.

```php
// Exemple d'annotation dans EleveApiController
/**
 * @OA\Get(
 *   path="/api/v1/eleves",
 *   summary="Lister les élèves",
 *   tags={"Scolarité"},
 *   security={{"bearerAuth": {}}},
 *   @OA\Parameter(name="classe_id", in="query", required=false, @OA\Schema(type="integer")),
 *   @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
 *   @OA\Response(response=200, description="Liste paginée des élèves",
 *     @OA\JsonContent(ref="#/components/schemas/EleveListResponse")
 *   )
 * )
 */
public function index(): void { ... }
```

### 6.2 Structure du document OpenAPI

```yaml
openapi: 3.1.0
info:
  title: SCOLARIS V2 API
  version: 1.0.0
  description: API REST de gestion scolaire
  contact:
    email: api@scolaris.io
  license:
    name: Propriétaire

servers:
  - url: https://{etab}.scolaris.io/api/v1
    variables:
      etab:
        description: Sous-domaine de l'établissement
        default: demo

components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
    apiKey:
      type: apiKey
      in: header
      name: X-Api-Key

  schemas:
    Pagination: { ... }
    ApiError:   { ... }
    Eleve:      { ... }
    Classe:     { ... }
    Note:       { ... }
    # ... un schéma par ressource

tags:
  - name: Auth
  - name: Scolarité
  - name: Académique
  - name: Finance
  - name: Vie scolaire
  - name: RH
  - name: Documents
  - name: Communication
  - name: Bibliothèque
  - name: Inventaire
  - name: Rapports
```

### 6.3 Swagger UI

URL : `/api/docs` (protégée par auth admin ou IP whitelist en production)
Fichier spec : `/api/openapi.json` (généré à la build ou à la demande)

```
/api/docs              → Swagger UI (interface navigateur)
/api/openapi.json      → Spec OpenAPI 3.1 brute
/api/postman           → Collection Postman exportée automatiquement
```

### 6.4 Exemples de requêtes/réponses

Chaque endpoint documente :
- `curl` minimal
- Réponse succès (200/201)
- Réponse erreur la plus probable (400/401/403/404)
- Schéma JSON complet avec types et contraintes

---

## 7. SÉCURITÉ

### 7.1 Rate Limiting

#### Limites par type d'acteur

| Type | Limite | Fenêtre | Burst |
|------|--------|---------|-------|
| JWT anonymisé | 60 req | /minute | 10 |
| JWT authentifié | 300 req | /minute | 50 |
| API Key — lecture | 1 000 req | /heure | 100 |
| API Key — écriture | 200 req | /heure | 20 |
| Auth endpoints | 5 req | /minute/IP | — |
| Upload | 10 req | /heure | — |

#### Headers de réponse Rate Limit

```http
X-RateLimit-Limit: 300
X-RateLimit-Remaining: 247
X-RateLimit-Reset: 1751501400
Retry-After: 60          ← uniquement si 429
```

#### Implémentation — `RateLimiter`

```php
class RateLimiter
{
    // Backend : table api_rate_limit_buckets (token bucket algorithm)
    // Clé de bucket : sha256("{acteur_id}:{endpoint_group}:{window}")
    public function consume(string $key, int $limit, int $windowSeconds): RateLimitResult;
    public function reset(string $key): void;
    public function peek(string $key): RateLimitResult;
}
```

#### Table `api_rate_limit_buckets`

```sql
CREATE TABLE api_rate_limit_buckets (
    bucket_key   VARCHAR(64) PRIMARY KEY,
    tokens       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_refill  DATETIME(6) NOT NULL,
    expires_at   DATETIME NOT NULL,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;
```

### 7.2 CORS

#### Politique par environnement

```php
// config/api.php
'cors' => [
    'allowed_origins' => [
        'production' => ['https://*.scolaris.io', 'https://*.etablissement.fr'],
        'development' => ['http://localhost:*', 'http://127.0.0.1:*'],
    ],
    'allowed_methods'  => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers'  => ['Content-Type', 'Authorization', 'X-Api-Key', 'X-Requested-With', 'Accept'],
    'exposed_headers'  => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'API-Version'],
    'max_age'          => 86400,
    'credentials'      => true,
],
```

**Preflight OPTIONS :** répondu directement par `CorsMiddleware` sans traverser le reste du pipeline.

### 7.3 CSRF

L'API REST est **exempt de CSRF** car :
- JWT transmis dans `Authorization: Bearer` (pas de cookie)
- API Keys dans `X-Api-Key` header
- SameSite=Strict sur les cookies de session web

**Exception :** si session PHP utilisée comme fallback (navigateur), CSRF requis via header `X-CSRF-Token`.

### 7.4 Validation des inputs

Chaque endpoint possède un `ApiRequest` dédié :

```php
abstract class ApiRequest
{
    abstract public function rules(): array;

    public function validate(): array  // retourne données validées
    public function fails(): bool
    public function errors(): array
}

// Exemple
class CreateEleveRequest extends ApiRequest
{
    public function rules(): array {
        return [
            'nom'          => 'required|string|max:100',
            'prenom'       => 'required|string|max:100',
            'date_naissance' => 'required|date|before:today',
            'classe_id'    => 'required|integer|exists:classes,id',
            'email'        => 'nullable|email|max:255',
        ];
    }
}
```

**Règles de validation disponibles :**
`required`, `nullable`, `string`, `integer`, `boolean`, `float`, `date`, `email`, `url`, `max:{n}`, `min:{n}`, `in:{a,b,c}`, `exists:{table},{col}`, `unique:{table},{col}`, `before:{date}`, `after:{date}`, `regex:{pattern}`, `array`, `uuid`

### 7.5 Sanitisation

```php
class Sanitizer
{
    public static function string(string $val): string    // trim + strip_tags
    public static function integer(mixed $val): int       // (int)
    public static function email(string $val): string     // filter_var FILTER_SANITIZE_EMAIL
    public static function filename(string $val): string  // supprime ../ et chars dangereux
    public static function html(string $val): string      // HTMLPurifier (librarie externe)
}
```

### 7.6 Journalisation & Audit

Toutes les requêtes API sont journalisées via le `ApiLoggingMiddleware` :

```php
// Écrit dans api_request_logs (table) + fichier log rotatif
class ApiLoggingMiddleware implements Middleware
{
    // Consigne APRÈS la réponse (non bloquant sur le chemin critique)
    // Données : method, path, status, duration_ms, user_id, etab_id,
    //           ip, user_agent, request_id, params (sans données sensibles)
}
```

**Données sensibles exclues des logs :** passwords, tokens, numéros de carte, données médicales.

#### Table `api_request_logs`

```sql
CREATE TABLE api_request_logs (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id       CHAR(36) NOT NULL,              -- UUID v4 par requête
    method           VARCHAR(10) NOT NULL,
    path             VARCHAR(500) NOT NULL,
    query_string     TEXT NULL,
    status_code      SMALLINT UNSIGNED NOT NULL,
    duration_ms      SMALLINT UNSIGNED NOT NULL,
    response_bytes   INT UNSIGNED NULL,
    user_id          INT UNSIGNED NULL,
    etablissement_id INT UNSIGNED NULL,
    auth_method      VARCHAR(20) NULL,
    api_key_id       BIGINT UNSIGNED NULL,
    ip_address       VARCHAR(45) NOT NULL,
    user_agent       VARCHAR(500) NULL,
    error_code       VARCHAR(50) NULL,
    created_at       DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3),
    INDEX idx_user    (user_id, created_at),
    INDEX idx_etab    (etablissement_id, created_at),
    INDEX idx_status  (status_code, created_at),
    INDEX idx_path    (path(100), created_at)
) ENGINE=InnoDB
  PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2026 VALUES LESS THAN (2027),
    PARTITION p2027 VALUES LESS THAN (2028),
    PARTITION pmax  VALUES LESS THAN MAXVALUE
  );
```

---

## 8. PERMISSIONS & RBAC

### 8.1 Intégration avec le RBAC existant

Le RBAC de `config/permissions.php` (rôles → permissions) est la **source de vérité unique**. L'API ne définit pas de nouveau système d'autorisation.

```php
// PermissionMiddleware
class PermissionMiddleware implements Middleware
{
    // Charge la permission requise depuis la route
    // Compare avec $request->authContext()->permissions
    // Ou appelle config('permissions')[$role] pour les API Keys
}
```

### 8.2 Route → Permission mapping

Chaque route API déclare explicitement la permission requise :

```php
// routes/api.php
$router->get('/api/v1/eleves', 'Api\V1\EleveApiController@index')
       ->permission('eleves.view');

$router->post('/api/v1/eleves', 'Api\V1\EleveApiController@store')
       ->permission('eleves.create');

$router->get('/api/v1/notes', 'Api\V1\NoteApiController@index')
       ->permission('notes.view');
```

### 8.3 Scopes API Key

Les API Keys ont un tableau `permissions` JSON qui est l'intersection de :
- Les permissions du rôle de l'utilisateur créateur
- Les permissions explicitement choisies à la création de la clé

```php
// Lors de la création d'une API Key par un directeur
$keyPermissions = array_intersect(
    config('permissions')[$creatorRole],  // permissions du directeur
    $requestedScopes                       // scopes choisis par l'utilisateur
);
```

### 8.4 Isolation multi-établissement

`TenantMiddleware` injecte `etablissement_id` dans chaque requête depuis :
1. Le JWT payload (`etab` claim)
2. L'API Key (colonne `etablissement_id`)
3. La session PHP (colonne `users.etablissement_id`)

**Règle absolue :** aucune requête API ne peut accéder à des données d'un établissement différent de celui de l'`AuthContext`. Vérifié au niveau du `ApiBaseController` :

```php
abstract class ApiBaseController extends Controller
{
    protected function getEtabId(): int
    {
        $id = $this->authContext()->etablissementId;
        if ($id === 0) throw new \RuntimeException('Tenant non résolu');
        return $id;
    }
}
```

---

## 9. RÉPONSES STANDARDISÉES

### 9.1 Enveloppe universelle

**Succès — collection :**
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "total": 250,
    "page": 2,
    "per_page": 20,
    "last_page": 13,
    "from": 21,
    "to": 40
  },
  "links": {
    "self":  "/api/v1/eleves?page=2",
    "first": "/api/v1/eleves?page=1",
    "prev":  "/api/v1/eleves?page=1",
    "next":  "/api/v1/eleves?page=3",
    "last":  "/api/v1/eleves?page=13"
  }
}
```

**Succès — ressource unique :**
```json
{
  "success": true,
  "data": {
    "id": 42,
    "nom": "Diallo",
    "prenom": "Fatou",
    "classe": { "id": 3, "nom": "6ème A" },
    "created_at": "2026-09-01T08:00:00Z",
    "updated_at": "2026-09-01T08:00:00Z"
  }
}
```

**Succès — création (201) :**
```json
{
  "success": true,
  "data": { ... },
  "message": "Élève créé avec succès."
}
```

**Erreur :**
```json
{
  "success": false,
  "error": {
    "code": "validation_failed",
    "message": "Les données fournies sont invalides.",
    "details": {
      "nom": ["Le champ nom est obligatoire."],
      "date_naissance": ["La date de naissance doit être dans le passé."]
    },
    "request_id": "a1b2c3d4-e5f6-...",
    "docs": "https://docs.scolaris.io/api/errors#validation_failed"
  }
}
```

### 9.2 ApiResource — transformateur

```php
abstract class ApiResource
{
    abstract public function toArray(AuthContext $context): array;

    // Collection de ressources
    public static function collection(array $items, AuthContext $ctx): array {
        return array_map(fn($item) => (new static($item))->toArray($ctx), $items);
    }

    // Masquage conditionnel selon permissions
    protected function when(bool $condition, mixed $value, mixed $default = null): mixed {
        return $condition ? $value : $default;
    }

    // Inclusion conditionnelle de relations
    protected function whenLoaded(string $relation, mixed $data): mixed {
        return isset($this->data[$relation]) ? $data : null;
    }
}

// Exemple
class EleveResource extends ApiResource
{
    public function toArray(AuthContext $ctx): array {
        return [
            'id'              => $this->data['id'],
            'matricule'       => $this->data['matricule'],
            'nom'             => $this->data['nom'],
            'prenom'          => $this->data['prenom'],
            'date_naissance'  => $this->data['date_naissance'],
            // Champ sensible — caché si pas admin/direction
            'dossier_medical' => $this->when(
                in_array('eleves.view_medical', $ctx->permissions, true),
                $this->data['dossier_medical'] ?? null
            ),
            'classe'          => $this->whenLoaded('classe', [
                'id'  => $this->data['classe_id'],
                'nom' => $this->data['classe_nom'],
            ]),
            'created_at'      => $this->data['created_at'],
            'updated_at'      => $this->data['updated_at'],
        ];
    }
}
```

### 9.3 Codes HTTP utilisés

| Code | Signification | Usage |
|------|--------------|-------|
| 200 | OK | GET, PUT, PATCH, DELETE réussis |
| 201 | Created | POST créant une nouvelle ressource |
| 202 | Accepted | Traitement asynchrone lancé (webhooks, exports) |
| 204 | No Content | DELETE sans corps de réponse |
| 400 | Bad Request | Requête malformée |
| 401 | Unauthorized | Token absent ou invalide |
| 403 | Forbidden | Authentifié mais non autorisé |
| 404 | Not Found | Ressource inexistante ou hors tenant |
| 405 | Method Not Allowed | Méthode HTTP non supportée |
| 409 | Conflict | Doublon (matricule existant, etc.) |
| 413 | Payload Too Large | Fichier trop volumineux |
| 422 | Unprocessable Entity | Validation métier échouée |
| 429 | Too Many Requests | Rate limit dépassé |
| 500 | Internal Server Error | Erreur interne (référence uniquement, pas de détails) |
| 503 | Service Unavailable | Maintenance planifiée |

### 9.4 Codes d'erreur métier

```
validation_failed        → Validation d'inputs échouée
auth_required            → Pas d'authentification
auth_invalid             → Token invalide ou expiré
auth_expired             → Token JWT expiré (distingué d'invalid pour le client)
permission_denied        → Droits insuffisants
resource_not_found       → Ressource inexistante dans le tenant
duplicate_entry          → Violation de contrainte d'unicité
rate_limit_exceeded      → Quota dépassé
tenant_not_found         → Établissement introuvable
conflict                 → Conflit d'état (ex: note sur une période clôturée)
file_too_large           → Upload dépassant la limite
file_type_not_allowed    → Type MIME non autorisé
internal_error           → Erreur interne (avec request_id pour le support)
```

---

## 10. PAGINATION, FILTRAGE, TRI, RECHERCHE

### 10.1 Pagination

**Paramètres :**
```
GET /api/v1/eleves?page=2&per_page=20
```

| Paramètre | Type | Défaut | Max |
|-----------|------|--------|-----|
| `page` | integer | 1 | — |
| `per_page` | integer | 20 | 100 |

Cursor-based pagination (pour les exports volumineux) :
```
GET /api/v1/eleves?cursor={opaque_cursor}&per_page=100
```

### 10.2 Filtrage

Syntaxe : `filter[{champ}]={valeur}` ou opérateur `filter[{champ}][{op}]={valeur}`

```
GET /api/v1/eleves?filter[classe_id]=3
GET /api/v1/eleves?filter[statut]=actif
GET /api/v1/notes?filter[matiere_id]=5&filter[date][gte]=2026-01-01
GET /api/v1/factures?filter[montant][gt]=50000&filter[statut][in]=emise,partielle
```

**Opérateurs supportés :**

| Opérateur | Signification | SQL |
|-----------|--------------|-----|
| `eq` (défaut) | Égalité | `= ?` |
| `neq` | Inégalité | `!= ?` |
| `gt` | Supérieur | `> ?` |
| `gte` | Supérieur ou égal | `>= ?` |
| `lt` | Inférieur | `< ?` |
| `lte` | Inférieur ou égal | `<= ?` |
| `like` | Contient | `LIKE %?%` |
| `starts` | Commence par | `LIKE ?%` |
| `in` | Dans une liste | `IN (?,?,?)` |
| `null` | Est NULL | `IS NULL` |
| `not_null` | N'est pas NULL | `IS NOT NULL` |
| `between` | Intervalle | `BETWEEN ? AND ?` |

**Champs filtrables définis par ressource** (whitelist — pas d'injection SQL possible) :

```php
class EleveApiController extends ResourceApiController
{
    protected array $filterableFields = [
        'classe_id', 'statut', 'annee_scolaire', 'nom', 'matricule',
    ];
}
```

### 10.3 Tri

```
GET /api/v1/eleves?sort=nom              → ASC par défaut
GET /api/v1/eleves?sort=-date_inscription  → DESC (préfixe -)
GET /api/v1/notes?sort=matiere_nom,-date   → tri composé
```

**Champs triables définis par ressource (whitelist) :**

```php
protected array $sortableFields = ['nom', 'prenom', 'date_inscription', 'created_at'];
protected string $defaultSort   = 'nom';
```

### 10.4 Recherche full-text

```
GET /api/v1/eleves?q=fatou diallo
GET /api/v1/search?q=diallo&modules=eleves,enseignants
```

La recherche fédérée `GlobalSearchEngine` (portails) est exposée via l'API :

```php
// GET /api/v1/search
// Paramètres : q (requis, min 2), modules (optionnel), limit (défaut 10)
// Réponse : { results: [{module, id, label, url, icon}], total, time_ms }
```

### 10.5 Inclusion de relations (sparse fieldsets)

```
GET /api/v1/eleves?include=classe,famille
GET /api/v1/eleves?fields[eleves]=id,nom,prenom&fields[classes]=id,nom
```

- `include` : charge des relations liées (eager loading contrôlé)
- `fields` : limite les champs retournés (économie de bande passante)

---

## 11. UPLOAD DE FICHIERS

### 11.1 Flow d'upload

```
POST /api/v1/uploads
  Content-Type: multipart/form-data
  Body: file={binary}, module=documents, context=eleve, context_id=42

← 201 { upload_id, url, mime_type, size, status: "processing" }

GET /api/v1/uploads/{upload_id}
← { status: "ready"|"processing"|"failed", url, thumbnail_url }
```

### 11.2 Contraintes

| Paramètre | Valeur |
|-----------|--------|
| Taille max | 10 Mo (configurable par établissement) |
| Types autorisés | PDF, DOCX, XLSX, PNG, JPG, WEBP, CSV |
| Stockage | `storage/uploads/{etab_id}/{year}/{month}/{uuid}.{ext}` |
| Virus scan | ClamAV ou API VirusTotal (stub V2.1) |
| Chunked upload | Préparé pour fichiers > 5 Mo (TUS protocol, V2.1) |

### 11.3 `FileUploadService`

```php
class FileUploadService
{
    public function store(UploadedFile $file, int $etabId, string $module): UploadResult;
    public function validate(UploadedFile $file): void;          // exception si invalide
    public function generateThumbnail(string $path): ?string;    // pour images
    public function getSignedUrl(string $path, int $ttl): string; // URL temporaire signée
    public function delete(string $path): void;
}
```

---

## 12. GESTION DES ERREURS

### 12.1 Handler global — `ApiExceptionHandler`

```php
class ApiExceptionHandler
{
    public function handle(\Throwable $e, ApiRequest $request): ApiResponse
    {
        return match(true) {
            $e instanceof ValidationException  => $this->validationError($e),
            $e instanceof AuthException        => $this->authError($e),
            $e instanceof PermissionException  => $this->permissionError($e),
            $e instanceof NotFoundException    => $this->notFound($e),
            $e instanceof ConflictException    => $this->conflict($e),
            $e instanceof RateLimitException   => $this->rateLimited($e),
            $e instanceof \PDOException        => $this->databaseError($e, $request),
            default                            => $this->internalError($e, $request),
        };
    }

    private function internalError(\Throwable $e, ApiRequest $request): ApiResponse
    {
        // Log complet en privé
        Logger::critical($e->getMessage(), ['trace' => $e->getTraceAsString()]);
        // Référence opaque pour le support (jamais de stacktrace exposée)
        $ref = 'ERR-' . strtoupper(substr(md5($e->getMessage() . time()), 0, 8));
        return ApiResponse::error('internal_error', "Erreur interne. Référence : $ref", 500);
    }
}
```

### 12.2 Hiérarchie des exceptions API

```
ApiException (base)
├── ValidationException    (422)
├── AuthException          (401)
├── PermissionException    (403)
├── NotFoundException      (404)
├── ConflictException      (409)
├── RateLimitException     (429)
├── UploadException        (413 / 415)
└── BusinessRuleException  (422) ← règles métier échouées (période clôturée, etc.)
```

---

## 13. WEBHOOKS

### 13.1 Architecture Event → Webhook

Les Events V2 existants (`Core\EventDispatcher`) déclenchent des Webhooks configurés par les établissements :

```
EventDispatcher::dispatch(EleveCreated)
        ↓
WebhookDispatcher::onEvent(EleveCreated)
        ↓
  Cherche les abonnements actifs pour cet établissement + cet event
        ↓
  Enfile les livraisons dans la table webhook_deliveries
        ↓
  Worker asynchrone livre via HTTP POST vers l'endpoint client
```

### 13.2 Events exportés en Webhook

Tous les events V2 existants peuvent être exposés. Sélection initiale :

**Scolarité :** `eleve.created`, `eleve.updated`, `inscription.confirmed`, `classe.eleve_assigned`
**Académique :** `note.created`, `bulletin.generated`, `periode.closed`
**Finance :** `facture.created`, `paiement.received`, `facture.overdue`
**Vie Scolaire :** `absence.declared`, `absence.justified`
**RH :** `conge.approved`, `conge.rejected`, `contrat.expiring_soon`
**Documents :** `document.uploaded`, `document.shared`
**Communication :** `message.sent`, `annonce.published`

### 13.3 Table `webhook_subscriptions`

```sql
CREATE TABLE webhook_subscriptions (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    etablissement_id INT UNSIGNED NOT NULL,
    name             VARCHAR(100) NOT NULL,
    url              VARCHAR(2000) NOT NULL,
    secret           VARCHAR(64) NOT NULL,    -- secret HMAC, stocké hashé
    events           JSON NOT NULL,           -- ["eleve.created", "note.created"]
    active           TINYINT(1) DEFAULT 1,
    headers          JSON NULL,               -- entêtes personnalisés
    verify_ssl       TINYINT(1) DEFAULT 1,
    created_by       INT UNSIGNED NOT NULL,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_etab (etablissement_id, active)
);
```

### 13.4 Table `webhook_deliveries`

```sql
CREATE TABLE webhook_deliveries (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id  BIGINT UNSIGNED NOT NULL,
    event_type       VARCHAR(100) NOT NULL,
    payload          JSON NOT NULL,
    status           ENUM('pending','success','failed') DEFAULT 'pending',
    attempts         TINYINT UNSIGNED DEFAULT 0,
    max_attempts     TINYINT UNSIGNED DEFAULT 5,
    next_attempt_at  DATETIME NULL,
    last_http_status SMALLINT NULL,
    last_response    TEXT NULL,
    duration_ms      SMALLINT NULL,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    delivered_at     DATETIME NULL,
    INDEX idx_status (status, next_attempt_at),
    INDEX idx_sub    (subscription_id, created_at)
);
```

### 13.5 Signature HMAC

Chaque livraison est signée avec HMAC-SHA256 :

```php
class WebhookSigner
{
    public function sign(string $payload, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    public function verify(string $payload, string $secret, string $signature): bool
    {
        return hash_equals($this->sign($payload, $secret), $signature);
    }
}
```

**Header de livraison :**
```http
POST https://client.example.com/webhook
Content-Type: application/json
X-Scolaris-Event: eleve.created
X-Scolaris-Delivery: uuid-v4
X-Scolaris-Signature: sha256=abc123...
X-Scolaris-Timestamp: 1751500000
```

**Corps de la livraison :**
```json
{
  "event": "eleve.created",
  "timestamp": "2026-09-01T08:00:00Z",
  "etablissement_id": 1,
  "data": {
    "eleve": { "id": 42, "nom": "Diallo", "prenom": "Fatou" }
  }
}
```

### 13.6 Retry automatique (backoff exponentiel)

```
Tentative 1 : immédiat
Tentative 2 : +1 min
Tentative 3 : +5 min
Tentative 4 : +30 min
Tentative 5 : +2 heures
→ Status: failed (alerter l'administrateur)
```

Condition d'échec : HTTP status >= 400 ou timeout > 30s.

### 13.7 API de gestion des Webhooks

```
GET    /api/v1/webhooks              → liste des abonnements
POST   /api/v1/webhooks              → créer un abonnement
GET    /api/v1/webhooks/{id}         → détail
PUT    /api/v1/webhooks/{id}         → modifier
DELETE /api/v1/webhooks/{id}         → supprimer
POST   /api/v1/webhooks/{id}/test    → livraison de test
GET    /api/v1/webhooks/{id}/deliveries → historique des livraisons
POST   /api/v1/webhooks/{id}/deliveries/{did}/retry → rejouer
```

---

## 14. SDK CLIENTS

### 14.1 Architecture commune des SDKs

Chaque SDK respecte la même interface logique :

```
ScolarisSdk
├── auth                 → login(), logout(), refresh(), issueApiKey()
├── eleves               → list(), get(), create(), update(), delete()
├── classes              → list(), get(), create()
├── notes                → list(), create(), update()
├── bulletins            → list(), download()
├── factures             → list(), get(), pdf()
├── absences             → list(), declare(), justify()
├── documents            → list(), upload(), download(), delete()
├── communication        → messages(), send(), notifications()
└── webhooks             → list(), create(), delete(), test()
```

### 14.2 SDK JavaScript / TypeScript

**Package :** `@scolaris/api-client`
**Cibles :** Browser ES2020+, Node.js 18+

```typescript
// Installation : npm install @scolaris/api-client

import { ScolarisSdk } from '@scolaris/api-client';

const sdk = new ScolarisSdk({
  baseUrl: 'https://demo.scolaris.io/api/v1',
  auth: { type: 'jwt', token: await sdk.auth.login(email, password) },
  // ou
  auth: { type: 'apiKey', key: 'sk_live_...' },
});

// Utilisation
const eleves = await sdk.eleves.list({ filter: { classe_id: 3 }, page: 1 });
const eleve  = await sdk.eleves.get(42);
await sdk.eleves.create({ nom: 'Diallo', prenom: 'Fatou', classe_id: 3 });

// Webhooks typés
sdk.webhooks.on('eleve.created', (event) => console.log(event.data));
```

**Fonctionnalités :**
- Gestion automatique du refresh JWT
- Retry sur 429 (respect du `Retry-After`)
- TypeScript interfaces générées depuis OpenAPI spec
- Intercepteurs configurables (logging, metrics)
- Bundle < 15 Ko (gzip)

### 14.3 SDK PHP

**Package :** `scolaris/api-php`
**Cible :** PHP 8.1+, Composer

```php
// Installation : composer require scolaris/api-php

use Scolaris\Api\Client;

$sdk = new Client(
    baseUrl: 'https://demo.scolaris.io/api/v1',
    apiKey:  'sk_live_...',
);

$eleves = $sdk->eleves()->list(filter: ['classe_id' => 3], page: 1);
$eleve  = $sdk->eleves()->get(42);

// Upload
$sdk->documents()->upload(
    file:      '/path/to/file.pdf',
    module:    'documents',
    contextId: 42,
);
```

**Fonctionnalités :**
- PSR-18 HttpClient (compatible Guzzle, Symfony HttpClient)
- PSR-3 Logger injectable
- DTOs typés pour chaque ressource
- Gestion des erreurs via exceptions typées

### 14.4 SDK Flutter / Dart

**Package :** `scolaris_api` (pub.dev)
**Cible :** Flutter 3.x (iOS, Android, Web)

```dart
// Installation : flutter pub add scolaris_api

import 'package:scolaris_api/scolaris_api.dart';

final sdk = ScolarisSdk(
  baseUrl: 'https://demo.scolaris.io/api/v1',
  authMethod: BearerAuth(token: await sdk.auth.login(email, password)),
);

// Utilisation async/await
final eleves = await sdk.eleves.list(classeId: 3);
final eleve  = await sdk.eleves.get(42);

// Stream pour mises à jour temps réel (WebSocket, V2.1)
sdk.eleves.stream(classeId: 3).listen((event) { ... });
```

**Fonctionnalités :**
- Gestion automatique des tokens (SecureStorage)
- Mode offline (cache local SQLite)
- Push notifications via FCM (intégration Communication module)
- Génération de modèles depuis OpenAPI spec

---

## 15. MONITORING & OBSERVABILITÉ

### 15.1 Métriques collectées

| Métrique | Type | Description |
|----------|------|-------------|
| `api.requests.total` | Counter | Requêtes totales par méthode/status |
| `api.requests.duration_ms` | Histogram | Temps de réponse par endpoint |
| `api.errors.total` | Counter | Erreurs par code/type |
| `api.auth.failures` | Counter | Échecs d'auth (brute force detection) |
| `api.rate_limit.hits` | Counter | Rate limit dépassé par acteur |
| `api.webhooks.deliveries` | Counter | Livraisons réussies/échouées |
| `api.uploads.bytes` | Counter | Volume uploadé par établissement |
| `api.search.duration_ms` | Histogram | Temps de recherche |
| `api.quota.usage` | Gauge | Usage quota par API key |

### 15.2 `MetricsCollector`

```php
class MetricsCollector
{
    // Backend configurable : fichier JSON agrégé (par défaut) ou Prometheus (V2.1)
    public function increment(string $metric, array $labels = []): void;
    public function histogram(string $metric, float $value, array $labels = []): void;
    public function gauge(string $metric, float $value, array $labels = []): void;
}
```

### 15.3 Health endpoints

```
GET /api/health              → {"status": "ok", "version": "1.0.0"}
GET /api/health/deep         → {"db": "ok", "cache": "ok", "storage": "ok"}
GET /api/metrics             → métriques Prometheus (protégé par IP whitelist)
```

### 15.4 Dashboard monitoring (stub)

Table `api_metrics_snapshots` pour agrégats horaires consultables via `/v2/portals/admin` :

```sql
CREATE TABLE api_metrics_snapshots (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    etablissement_id INT UNSIGNED NULL,   -- NULL = global
    snapshot_at      DATETIME NOT NULL,
    period           ENUM('hour','day','month') NOT NULL,
    requests_total   INT UNSIGNED DEFAULT 0,
    errors_total     INT UNSIGNED DEFAULT 0,
    avg_duration_ms  FLOAT DEFAULT 0,
    p95_duration_ms  FLOAT DEFAULT 0,
    webhooks_sent    INT UNSIGNED DEFAULT 0,
    webhooks_failed  INT UNSIGNED DEFAULT 0,
    top_endpoints    JSON NULL,
    INDEX idx_snapshot (etablissement_id, period, snapshot_at)
);
```

### 15.5 Alertes

Règles d'alerte préparées (implémentation Monitoring module ou email) :
- Taux d'erreur 5xx > 1% sur 5 minutes
- Latence p95 > 2s sur 10 minutes
- Rate limit dépassé > 100x/heure pour une API key
- Auth failures > 20/minute depuis une même IP

---

## 16. INTÉGRATIONS MODULES

### 16.1 Ressources exposées par module

| Module | Ressources API | Méthodes | Permission base |
|--------|---------------|---------|----------------|
| **Scolarité** | `eleves`, `classes`, `inscriptions`, `familles`, `matieres` | CRUD | `eleves.*`, `classes.*` |
| **Académique** | `periodes`, `evaluations`, `notes`, `bulletins`, `classements` | CRUD + actions | `notes.*`, `bulletins.*` |
| **Finance** | `factures`, `paiements`, `caisse`, `rapports-financiers` | CRUD + actions | `finance.*` |
| **Vie Scolaire** | `absences`, `presences`, `retards`, `disciplines`, `emploi-du-temps`, `activites` | CRUD | `absences.*`, `presences.*` |
| **RH** | `employes`, `enseignants`, `contrats`, `conges`, `presences-rh`, `evaluations-rh`, `formations` | CRUD | `employee.*`, `teacher.*` |
| **Documents** | `documents`, `categories`, `versions` | CRUD + upload | `document.*` |
| **Communication** | `messages`, `annonces`, `notifications` | Read + send | `communication.*` |
| **Bibliothèque** | `livres`, `exemplaires`, `emprunts`, `reservations` | CRUD | `biblio.*` |
| **Inventaire** | `articles`, `categories-inv`, `mouvements`, `maintenances` | CRUD | `inventaire.*` |
| **Rapports & BI** | `rapports`, `exports`, `analytics` | Read + trigger | `rapports.*` |
| **Portails** | `widgets`, `preferences`, `notifications-portail` | Read | `portal.*` |

### 16.2 Routes API complètes — `/api/v1/`

```
── Auth
POST   /auth/login
POST   /auth/refresh
POST   /auth/logout
POST   /auth/api-keys              → créer une API key
GET    /auth/api-keys              → liste des clés
DELETE /auth/api-keys/{id}        → révoquer

── Scolarité
GET    /eleves                     → liste paginée
POST   /eleves                     → créer
GET    /eleves/{id}
PUT    /eleves/{id}
DELETE /eleves/{id}                → soft delete
POST   /eleves/{id}/transfer       → changer de classe
POST   /eleves/import              → import CSV

GET    /classes
POST   /classes
GET    /classes/{id}
PUT    /classes/{id}
GET    /classes/{id}/eleves
POST   /classes/{id}/eleves/{eid}  → affecter un élève

GET    /inscriptions
POST   /inscriptions
GET    /inscriptions/{id}
PUT    /inscriptions/{id}/statut

GET    /matieres
POST   /matieres
GET    /matieres/{id}
PUT    /matieres/{id}

── Académique
GET    /periodes
POST   /periodes
PUT    /periodes/{id}
POST   /periodes/{id}/ouvrir
POST   /periodes/{id}/cloturer

GET    /evaluations
POST   /evaluations
GET    /evaluations/{id}
PUT    /evaluations/{id}

GET    /notes
POST   /notes
PUT    /notes/{id}
POST   /notes/bulk                 → saisie en masse

GET    /bulletins
GET    /bulletins/{id}
POST   /bulletins/generer          → génération masse
GET    /bulletins/{id}/pdf

GET    /classements/{classeId}/{periodeId}

── Finance
GET    /factures
POST   /factures
GET    /factures/{id}
PUT    /factures/{id}
POST   /factures/{id}/annuler
GET    /factures/{id}/pdf

GET    /paiements
POST   /paiements
GET    /paiements/{id}
POST   /paiements/{id}/rembourser

GET    /caisse/journal
GET    /caisse/solde

── Vie Scolaire
GET    /absences
POST   /absences
GET    /absences/{id}
POST   /absences/{id}/justifier

GET    /emploi-du-temps/{classeId}
GET    /emploi-du-temps/enseignant/{userId}

GET    /activites
POST   /activites/{id}/inscrire/{eleveId}

── RH
GET    /employes
POST   /employes
GET    /employes/{id}
PUT    /employes/{id}

GET    /conges
POST   /conges
PUT    /conges/{id}/statut

GET    /formations
POST   /formations/{id}/inscrire/{employeId}

── Documents
GET    /documents
POST   /documents                  → métadonnées (upload via /uploads)
GET    /documents/{id}
GET    /documents/{id}/download
DELETE /documents/{id}

── Communication
GET    /messages
POST   /messages
GET    /messages/{id}

GET    /annonces
POST   /annonces

GET    /notifications

── Bibliothèque
GET    /livres
POST   /livres
GET    /livres/{id}

GET    /emprunts
POST   /emprunts
PUT    /emprunts/{id}/retour

── Inventaire
GET    /articles
POST   /articles
GET    /articles/{id}
POST   /articles/{id}/mouvement

── Rapports & BI
GET    /rapports
POST   /rapports/{type}/generer
GET    /rapports/{id}
GET    /rapports/{id}/download

── Upload transversal
POST   /uploads                    → upload de fichier
GET    /uploads/{id}               → statut

── Webhooks
GET    /webhooks
POST   /webhooks
GET    /webhooks/{id}
PUT    /webhooks/{id}
DELETE /webhooks/{id}
POST   /webhooks/{id}/test
GET    /webhooks/{id}/deliveries

── Monitoring
GET    /health
GET    /health/deep
```

**Total estimé :** ~120 routes

---

## 17. ARCHITECTURE DES DOSSIERS

```
app/
└── Modules/
    └── Api/                              ← Nouveau module V2
        ├── module.json
        ├── routes.php
        │
        ├── Kernel/
        │   ├── ApiKernel.php             ← Pipeline middlewares
        │   └── ApiRouter.php             ← Chargement routes API
        │
        ├── Middleware/
        │   ├── CorsMiddleware.php
        │   ├── RateLimitMiddleware.php
        │   ├── ApiAuthMiddleware.php
        │   ├── TenantMiddleware.php
        │   ├── PermissionMiddleware.php
        │   ├── ValidationMiddleware.php
        │   └── ApiLoggingMiddleware.php
        │
        ├── Controllers/
        │   ├── ApiBaseController.php     ← Abstract, étend Core\Controller
        │   ├── ResourceApiController.php ← Abstract, pagination/filtrage/tri
        │   │
        │   ├── Auth/
        │   │   ├── LoginController.php
        │   │   ├── RefreshController.php
        │   │   ├── LogoutController.php
        │   │   └── ApiKeyController.php
        │   │
        │   ├── V1/
        │   │   ├── Scolarite/
        │   │   │   ├── EleveApiController.php
        │   │   │   ├── ClasseApiController.php
        │   │   │   ├── InscriptionApiController.php
        │   │   │   ├── FamilleApiController.php
        │   │   │   └── MatiereApiController.php
        │   │   ├── Academique/
        │   │   │   ├── PeriodeApiController.php
        │   │   │   ├── EvaluationApiController.php
        │   │   │   ├── NoteApiController.php
        │   │   │   ├── BulletinApiController.php
        │   │   │   └── ClassementApiController.php
        │   │   ├── Finance/
        │   │   │   ├── FactureApiController.php
        │   │   │   ├── PaiementApiController.php
        │   │   │   └── CaisseApiController.php
        │   │   ├── VieScolaire/
        │   │   │   ├── AbsenceApiController.php
        │   │   │   ├── EmploiDuTempsApiController.php
        │   │   │   └── ActiviteApiController.php
        │   │   ├── RH/
        │   │   │   ├── EmployeApiController.php
        │   │   │   ├── CongeApiController.php
        │   │   │   └── FormationApiController.php
        │   │   ├── Documents/
        │   │   │   └── DocumentApiController.php
        │   │   ├── Communication/
        │   │   │   ├── MessageApiController.php
        │   │   │   └── AnnonceApiController.php
        │   │   ├── Bibliotheque/
        │   │   │   ├── LivreApiController.php
        │   │   │   └── EmpruntApiController.php
        │   │   ├── Inventaire/
        │   │   │   └── ArticleApiController.php
        │   │   ├── Rapports/
        │   │   │   └── RapportApiController.php
        │   │   ├── Upload/
        │   │   │   └── UploadApiController.php
        │   │   └── Webhook/
        │   │       └── WebhookApiController.php
        │
        ├── Resources/                    ← Transformateurs DTO → JSON
        │   ├── ApiResource.php           ← Abstract
        │   ├── PaginatedResource.php
        │   ├── Scolarite/
        │   │   ├── EleveResource.php
        │   │   ├── ClasseResource.php
        │   │   └── ...
        │   ├── Academique/
        │   │   ├── NoteResource.php
        │   │   ├── BulletinResource.php
        │   │   └── ...
        │   └── ...
        │
        ├── Requests/                     ← Validation des inputs
        │   ├── ApiRequest.php            ← Abstract
        │   ├── Scolarite/
        │   │   ├── CreateEleveRequest.php
        │   │   ├── UpdateEleveRequest.php
        │   │   └── ...
        │   └── ...
        │
        ├── Auth/
        │   ├── JwtService.php            ← Encode/decode JWT
        │   ├── ApiKeyService.php         ← Gestion API Keys
        │   ├── AuthContext.php           ← DTO contexte auth
        │   ├── OAuthStub.php             ← Stub OAuth2 (V2.1)
        │   └── RefreshTokenService.php
        │
        ├── RateLimit/
        │   ├── RateLimiter.php
        │   └── RateLimitResult.php
        │
        ├── Webhooks/
        │   ├── WebhookDispatcher.php     ← Écoute events, enfile livraisons
        │   ├── WebhookSigner.php         ← HMAC-SHA256
        │   ├── WebhookWorker.php         ← Livraison + retry
        │   └── WebhookRepository.php
        │
        ├── Upload/
        │   ├── FileUploadService.php
        │   └── UploadedFile.php
        │
        ├── Monitoring/
        │   ├── MetricsCollector.php
        │   ├── ApiLogger.php
        │   └── HealthChecker.php
        │
        ├── Exceptions/
        │   ├── ApiException.php
        │   ├── ApiExceptionHandler.php
        │   ├── ValidationException.php
        │   ├── AuthException.php
        │   ├── PermissionException.php
        │   ├── NotFoundException.php
        │   ├── ConflictException.php
        │   ├── RateLimitException.php
        │   └── BusinessRuleException.php
        │
        ├── Repositories/
        │   ├── ApiKeyRepository.php
        │   ├── RefreshTokenRepository.php
        │   ├── WebhookRepository.php
        │   ├── RateLimitRepository.php
        │   └── ApiRequestLogRepository.php
        │
        └── Documentation/
            ├── OpenApiGenerator.php      ← Génère openapi.json depuis annotations
            ├── SwaggerController.php     ← Sert /api/docs
            ├── PostmanExporter.php       ← Exporte collection Postman
            └── schemas/                 ← Schémas JSON réutilisables
                ├── Eleve.json
                ├── Classe.json
                ├── Note.json
                └── ...
```

---

## 18. BASE DE DONNÉES — TABLES API

### 18.1 Récapitulatif des nouvelles tables

| Table | Description |
|-------|------------|
| `api_keys` | Clés d'API (hash SHA-256) |
| `api_refresh_tokens` | Refresh tokens JWT |
| `api_rate_limit_buckets` | Token bucket pour rate limiting |
| `api_request_logs` | Journalisation toutes requêtes (partitionné) |
| `api_metrics_snapshots` | Agrégats horaires/journaliers |
| `webhook_subscriptions` | Abonnements webhook par établissement |
| `webhook_deliveries` | File de livraison + historique |
| `oauth_clients` | Applications OAuth2 (stub, V2.1) |
| `oauth_auth_codes` | Codes d'autorisation OAuth2 |
| `uploads` | Métadonnées des fichiers uploadés |

### 18.2 Migration principale `api_001_foundation.sql`

```sql
-- api_keys, api_refresh_tokens, api_rate_limit_buckets,
-- api_request_logs (partitionné), api_metrics_snapshots,
-- webhook_subscriptions, webhook_deliveries,
-- uploads
```

### 18.3 Migration OAuth2 `api_002_oauth_stub.sql`

```sql
-- Tables créées vides — implémentation Phase 13.x
-- oauth_clients, oauth_auth_codes, oauth_access_tokens, oauth_refresh_tokens, oauth_scopes
```

---

## 19. CONVENTIONS REST

### 19.1 Nommage des URLs

| Convention | Exemple |
|------------|---------|
| Pluriel pour les collections | `/api/v1/eleves` |
| Kebab-case pour les mots composés | `/api/v1/emploi-du-temps` |
| IDs numériques dans le path | `/api/v1/eleves/42` |
| Actions non-CRUD sur sous-ressource | `/api/v1/factures/42/annuler` |
| Relations via sous-ressources | `/api/v1/classes/3/eleves` |
| Pas de verbes dans les URLs CRUD | ~~`/api/v1/getEleve`~~ |

### 19.2 Nommage des champs JSON

| Convention | Exemple |
|------------|---------|
| snake_case | `date_naissance`, `classe_id` |
| Timestamps en ISO 8601 UTC | `"2026-09-01T08:00:00Z"` |
| Booléens : `true`/`false` | `"actif": true` |
| Nombres sans guillemets | `"montant": 50000.00` |
| Null explicite si absent | `"photo_url": null` |
| IDs toujours entiers | `"id": 42` (pas `"42"`) |

### 19.3 Méthodes HTTP — sémantique

| Méthode | Idempotent | Corps req. | Corps rép. | Usage |
|---------|-----------|------------|------------|-------|
| GET | ✅ | — | ✅ | Lecture |
| POST | ❌ | ✅ | ✅ | Création ou action |
| PUT | ✅ | ✅ | ✅ | Remplacement complet |
| PATCH | ✅ | ✅ | ✅ | Modification partielle |
| DELETE | ✅ | — | 204 | Suppression (soft) |
| OPTIONS | ✅ | — | ✅ | Preflight CORS |

### 19.4 Internationalisation

```http
Accept-Language: fr, en;q=0.8
```

Messages d'erreur dans la langue du header. Données métier (noms, libellés) toujours tels quels en base.

### 19.5 Content-Type

Requêtes : `Content-Type: application/json` (sauf uploads : `multipart/form-data`)
Réponses : `Content-Type: application/json; charset=utf-8`

---

## 20. ROADMAP API V2.1

### Phase 13.1 (actuelle) — Blueprint
- [x] Architecture définie
- [x] 17 composants spécifiés
- [x] ~120 routes planifiées
- [x] 10 tables SQL définies
- [x] SDKs architecturés (JS, PHP, Flutter)

### Phase 13.2 — Foundation (prochaine)
- [ ] `Api\Kernel` — pipeline middlewares
- [ ] `JwtService` — encode/decode HS256
- [ ] `ApiKeyService` — création/révocation/hash
- [ ] `RateLimiter` — token bucket en DB
- [ ] `ApiExceptionHandler` — handler global
- [ ] Migration `api_001_foundation.sql`
- [ ] Routes Auth (`/api/v1/auth/*`)

### Phase 13.3 — Ressources CRUD (Scolarité + Académique)
- [ ] 5 contrôleurs Scolarité
- [ ] 5 contrôleurs Académique
- [ ] Resources + Requests correspondantes
- [ ] Tests d'intégration

### Phase 13.4 — Ressources CRUD (Finance + Vie Scolaire + RH)
- [ ] 3 contrôleurs Finance
- [ ] 3 contrôleurs Vie Scolaire
- [ ] 3 contrôleurs RH
- [ ] Actions métier (annuler facture, justifier absence, valider congé)

### Phase 13.5 — Ressources CRUD (Documents + Comm + Biblio + Inventaire + Rapports)
- [ ] 5 modules restants
- [ ] `FileUploadService` + `UploadApiController`

### Phase 13.6 — Webhooks
- [ ] `WebhookDispatcher` — écoute EventDispatcher
- [ ] `WebhookWorker` — livraison + retry
- [ ] `WebhookSigner` — HMAC-SHA256
- [ ] Migration `webhook_*`
- [ ] API de gestion webhooks

### Phase 13.7 — Documentation
- [ ] `OpenApiGenerator` — annotations → `openapi.json`
- [ ] Swagger UI — `/api/docs`
- [ ] Postman collection export
- [ ] Guide migration V1 → V2

### Phase 13.8 — SDKs
- [ ] `@scolaris/api-client` (JavaScript/TypeScript)
- [ ] `scolaris/api-php` (PHP Composer)
- [ ] `scolaris_api` (Flutter/Dart)

### Phase 13.9 — Monitoring & Hardening
- [ ] `MetricsCollector` — agrégats DB
- [ ] Dashboard admin `/v2/portals/admin/api`
- [ ] Alertes seuils automatiques
- [ ] Pentest / audit sécurité

### V2.1 — Fonctionnalités avancées (différées)
- OAuth2 Authorization Code + PKCE
- Chunked upload (TUS protocol)
- GraphQL endpoint (lecture seule)
- WebSocket (notifications temps réel)
- Cache distribué Redis (remplacement DB cache)
- ETL / DWH export (Rapports & BI)

---

## ANNEXE A — Checklist Sécurité API

- [ ] JWT signé HS256 avec clé ≥ 64 octets aléatoires
- [ ] Refresh tokens hachés SHA-256 en base (jamais en clair)
- [ ] API Keys hachées SHA-256 en base
- [ ] Rate limiting sur tous les endpoints d'auth
- [ ] CORS strict (whitelist par établissement)
- [ ] Validation whitelist sur champs filtrables/triables (no injection)
- [ ] Pas de stacktrace exposée en production
- [ ] `request_id` unique sur chaque réponse (debugging sans info leak)
- [ ] Logs purgés après 90 jours (RGPD)
- [ ] TLS 1.2+ obligatoire (HTTP strict)
- [ ] `Content-Security-Policy` sur `/api/docs`
- [ ] IP whitelist sur `/api/metrics` et `/api/health/deep`
- [ ] Webhook HMAC-SHA256 vérifiable côté client

---

## ANNEXE B — Conventions de nommage classes PHP

| Type | Pattern | Exemple |
|------|---------|---------|
| Controller | `{Ressource}ApiController` | `EleveApiController` |
| Resource | `{Ressource}Resource` | `EleveResource` |
| Request | `{Action}{Ressource}Request` | `CreateEleveRequest` |
| Exception | `{Type}Exception` | `PermissionException` |
| Repository | `{Nom}Repository` | `ApiKeyRepository` |
| Service | `{Nom}Service` | `JwtService` |
| Middleware | `{Nom}Middleware` | `RateLimitMiddleware` |
| DTO | `{Nom}DTO` | `AuthContextDTO` |
| Event | `{Nom}{Action}` | `EleveCreated` |

---

*Blueprint Phase 13.1 — API PLATFORM V2 — SCOLARIS*
*Aucune implémentation dans ce document — uniquement architecture et conventions*
