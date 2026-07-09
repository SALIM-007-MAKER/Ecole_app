# Configuration — SCOLARIS V2 / EduNova

## 1. Fichiers de configuration (`config/`)

| Fichier | Domaine |
|---|---|
| `app.php` | Nom, environnement, debug, URL, clé applicative, fuseau horaire, session, upload |
| `database.php` | Connexion MySQL |
| `mail.php` / `sms.php` | Canaux de notification sortants |
| `events.php` | Registre événements → listeners |
| `api.php` | JWT, clés API, rate limiting, CORS, pagination, webhooks |
| `modules.php` | Activation par module V2, chemin des routes/manifestes |
| `tenant.php` | Multi-tenant : activation du middleware, établissement par défaut, domaine de base |
| `cache.php` | Pilote de cache (fichier/Redis), TTL par catégorie |
| `storage.php` | Pilote de stockage (local/S3), répertoire local, clé de signature |
| `permissions.php` | RBAC : rôles système et leurs permissions |
| `routes.php` | Routes V1 |

Chaque fichier retourne un tableau PHP ; la plupart des valeurs se pilotent via
`.env` sans toucher au code (voir `docs/deploiement/VARIABLES_ENVIRONNEMENT.md`).

## 2. Activer/désactiver un module V2

```php
// config/modules.php
'finance' => [
    'enabled'   => true,   // false désactive TOUTES les routes du module
    'namespace' => 'App\\Modules\\Finance',
    'routes'    => ROOT_PATH . '/app/Modules/Finance/routes.php',
    'manifest'  => ROOT_PATH . '/app/Modules/Finance/module.json',
],
```

**Avant d'activer un module actuellement désactivé**, vérifiez que ses migrations
SQL ont été appliquées (`docs/deploiement/MISE_A_JOUR.md`) — sinon les écrans du
module échoueront à la première requête (voir
`docs/technique/ARCHITECTURE_MODULES.md` §4).

## 3. Activer le mode Multi-Tenant complet

Par défaut, `config/tenant.php['enabled']` (`TENANT_MODE_ENABLED`) est un simple
indicateur documentaire — le `TenantMiddleware` **n'est pas activé automatiquement**
même si ce flag passe à `true` (voir `docs/technique/MULTI_TENANT.md` §2).
L'activation réelle nécessite une modification de code
(`core/Application.php::run()`) et doit être traitée comme sa propre bascule
contrôlée, avec tests de non-régression HTTP complets — voir
`MULTI_TENANT_SYSTEM_INTEGRATION_REVIEW.md` §8, recommandation 1.

## 4. Cache

```
CACHE_DRIVER=file    # ou 'redis' (nécessite l'extension PHP redis, non fournie)
```
Répertoire du cache fichier : `storage/cache/`. TTL par catégorie ajustables dans
`config/cache.php['ttl']`.

## 5. Stockage

```
STORAGE_DRIVER=local   # ou 's3' (nécessite des credentials AWS réels)
```
Répertoire local : `storage/tenants/`. Voir `docs/technique/MULTI_TENANT.md` §5.

## 6. Sécurité — points à vérifier avant mise en production

- `APP_DEBUG=false` impérativement (le repli par défaut du code est désormais
  `false` si la variable est absente — corrigé Phase 15.1 — mais vérifiez la
  valeur explicite de votre `.env`).
- `APP_KEY` généré aléatoirement et unique par environnement (jamais la valeur
  d'exemple).
- `SESSION_LIFETIME` adapté à votre politique de sécurité.
- `CORS_ORIGINS` limité aux domaines réels de vos clients front-end/mobiles.
- Voir `docs/deploiement/VARIABLES_ENVIRONNEMENT.md` §Sécurité pour la liste
  complète des secrets à protéger.
