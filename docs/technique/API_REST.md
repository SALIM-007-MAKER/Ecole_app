# API REST — SCOLARIS V2 Platform API v1

## 1. Vue d'ensemble

- **Préfixe** : `/api/v1/*` (+ `/api/docs` pour la documentation interactive)
- **Format** : JSON exclusivement, enveloppe uniforme `{"success": bool, "data": ...}`
  ou `{"success": false, "error": {"code": ..., "message": ..., "details": ...}}`
- **Documentation vivante** : `GET /api/docs` (Swagger UI), `GET /api/docs/openapi.json`
  (spécification OpenAPI 3.1)
- **Vérification santé** : `GET /api/v1/health`
- Module source : `app/Modules/Api/` (`config/modules.php['api']['enabled'] = true`)

> **Correction Phase 15.1 (RC1)** : jusqu'à cette phase, l'intégralité des 122
> routes de cette API étaient inaccessibles (404 systématique) suite à un bug de
> double-préfixage dans `Core\Router`. Corrigé et vérifié en conditions HTTP
> réelles — voir `RELEASE_CANDIDATE_RC1_REPORT.md` §2.

## 2. Authentification

Trois méthodes, résolues dans cet ordre par `ApiBaseController::tryResolveAuth()` :

1. **JWT Bearer** — `Authorization: Bearer {token}`. Obtenu via
   `POST /api/v1/auth/login` (email + mot de passe), rafraîchi via
   `POST /api/v1/auth/refresh`, invalidé via `POST /api/v1/auth/logout`.
   Algorithme `HS256`, TTL access 15 min / refresh 30 jours (`config/api.php['jwt']`).
   Le payload transporte un claim `etab` (identifiant d'établissement).
2. **Clé API** — en-tête `X-Api-Key: sk_live_...` (ou `sk_test_...`). Gestion via
   `GET/POST /api/v1/api-keys`, `DELETE /api/v1/api-keys/{id}`. Chaque clé est liée
   à un établissement et un niveau de droits (lecture/écriture).
3. **Session navigateur** — repli sur `Core\Session::getUser()` pour les appels
   AJAX same-origin depuis l'application elle-même.

Sans authentification valide : `401 {"error":{"code":"auth_required",...}}`.

## 3. Ressources principales

| Domaine | Routes (extrait) |
|---|---|
| Scolarité | `/eleves`, `/classes`, `/inscriptions`, `/matieres` |
| Académique | `/periodes`, `/notes` (+ `/notes/batch`), `/bulletins` (+ `/export`), `/classements/*` |
| Finance | `/factures`, `/paiements`, `/caisse` *(module actif mais tables non appliquées — voir `docs/technique/ARCHITECTURE_MODULES.md` §4)* |
| Vie scolaire | `/absences`, `/emploi-du-temps`, `/activites` *(idem)* |
| RH | `/employes`, `/conges`, `/formations` *(idem)* |
| Documents / Communication / Bibliothèque / Inventaire / Rapports | Routes déclarées, modules désactivés (`enabled=false`) |

Liste exhaustive et à jour : `app/Modules/Api/routes.php`, ou de façon interactive
via `GET /api/docs`.

## 4. Pagination, filtrage, tri

Gérés par `App\Shared\Api\PaginationService`/`FilterService` (query string) :

```
GET /api/v1/eleves?page=2&per_page=20&sort=nom&filter[classe_id]=12
```

- `per_page` : défaut 20, maximum 100 (`config/api.php['pagination']`)
- `sort` : préfixe `-` pour ordre descendant (ex. `-created_at`), tri multiple
  séparé par virgule
- `filter[champ]` : liste blanche définie par ressource, valeur inconnue ignorée
  silencieusement (pas d'erreur, comportement testé — `tests/Api/PaginationTest.php`)

## 5. Limitation de débit (rate limiting)

Par fenêtre glissante, clé combinant IP + méthode d'authentification
(`config/api.php['rate_limit']`) :

| Contexte | Limite |
|---|---|
| Par défaut | 300 req/min |
| Authentification (`/auth/*`) | 5 req/min (anti brute-force) |
| Clé API — lecture | 1000 req/h |
| Clé API — écriture | 200 req/h |
| Upload | 10 req/h |

En-têtes de réponse : `X-RateLimit-Limit`, `X-RateLimit-Remaining`,
`X-RateLimit-Reset`. Dépassement → `429`.

## 6. CORS

Activé et appliqué (`ApiBaseController`, headers `Access-Control-Allow-*`) pour les
origines listées dans `CORS_ORIGINS` (`.env`, défaut
`http://localhost:3000,http://localhost:8080`) — à adapter en production avec les
domaines réels des clients front-end autorisés. Credentials activés
(`Access-Control-Allow-Credentials: true`).

## 7. Webhooks

`config/api.php['webhooks']` : timeout 30s, 5 tentatives max, délais de relance
`0s / 60s / 5min / 30min / 2h`. Gestion via `WebhookApiController` (routes
`/api/v1/webhooks*`). Signature HMAC pour la vérification côté récepteur.

## 8. Codes d'erreur

| Code | HTTP | Signification |
|---|---|---|
| `auth_required` | 401 | Authentification absente ou invalide |
| `forbidden` | 403 | Authentifié mais permission insuffisante |
| `validation_failed` | 422 | Corps de requête invalide (détails dans `error.details`) |
| `not_found` | 404 | Ressource inexistante |
| `rate_limited` | 429 | Quota de requêtes dépassé |

## 9. Tests

`tests/Api/*.php` : `ApiKeyTest` (20), `AuthApiTest` (18), `PaginationTest` +
`FilterTest` + `SortTest` (38), `RateLimitTest` (29), `WebhookTest` (15) — voir
`docs/developpeur/TESTS.md`. **Note** : `tests/Api/FilterTest.php` (fichier
séparé) est cassé (dépend de PHPUnit, non installé) ; ses cas sont dupliqués et
exécutés avec succès dans `PaginationTest.php`.

## 10. Documents associés

`API_PLATFORM_V2_BLUEPRINT.md`, `API_PLATFORM_IMPLEMENTATION_REPORT.md`,
`API_PLATFORM_INTEGRATION_REVIEW.md` (historique de conception détaillé).
