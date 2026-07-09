# API Documentation — SCOLARIS V2.0.0 Platform API v1

Document de référence de release (Phase 16.0). Détail complet :
`docs/technique/API_REST.md` (consommateurs), `docs/developpeur/API.md`
(développeurs qui étendent l'API). **Documentation interactive vivante** :
`GET /api/docs` (Swagger UI), `GET /api/docs/openapi.json` (spec OpenAPI 3.1).

## 1. Statut à cette version

Corrigée et vérifiée fonctionnelle Phase 15.1 (un bug de routage rendait
auparavant les 122 routes inaccessibles — voir
`SCOLARIS_V2_FINAL_RELEASE_REPORT.md` §5). Opérationnelle en production pour les
domaines dont les données sont appliquées (Scolarité, Académique) ; les routes
Finance/RH/Vie scolaire existent mais échoueront tant que leurs migrations ne
sont pas appliquées (voir §4).

## 2. Authentification

| Méthode | En-tête | Usage |
|---|---|---|
| JWT | `Authorization: Bearer {token}` | `POST /api/v1/auth/login`, `/refresh`, `/logout` — HS256, TTL 15 min / 30 j |
| Clé API | `X-Api-Key: sk_live_...` | `GET/POST /api/v1/api-keys` |
| Session | (implicite) | Appels AJAX same-origin |

## 3. Conventions

- Enveloppe uniforme : `{"success": bool, "data"|"error": ...}`
- Pagination : `?page=&per_page=` (max 100), tri `?sort=-champ`, filtre `?filter[champ]=`
- Limitation de débit : 300 req/min par défaut, en-têtes `X-RateLimit-*`
- CORS : activé, origines autorisées via `CORS_ORIGINS` (`.env`)

## 4. Ressources par domaine

| Domaine | Statut données | Exemple de route |
|---|---|---|
| Scolarité | ✅ Opérationnel | `GET /api/v1/eleves` |
| Académique | ✅ Opérationnel | `GET /api/v1/notes` |
| Finance | ⚠️ Routes présentes, données non appliquées | `GET /api/v1/factures` |
| Vie scolaire | ⚠️ Idem | `GET /api/v1/absences` |
| RH | ⚠️ Idem | `GET /api/v1/employes` |
| Documents / Communication / Bibliothèque / Inventaire / Rapports | ⚠️ Module désactivé | — |

## 5. Codes d'erreur

`auth_required` (401), `forbidden` (403), `validation_failed` (422),
`not_found` (404), `rate_limited` (429).

## 6. Webhooks

HMAC, 5 tentatives, délais `0/60/300/1800/7200` secondes.

## 7. Documents associés

`docs/technique/API_REST.md`, `docs/developpeur/API.md`, `docs/developpeur/TESTS.md`
(suites `tests/Api/*.php`, 120/120 à cette version).
