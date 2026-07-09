# Variables d'environnement — Référence complète

Fichier modèle : `.env.example` (à copier en `.env`, jamais versionné).

## Application

| Variable | Défaut | Description |
|---|---|---|
| `APP_NAME` | `EduNova` | Nom affiché |
| `APP_ENV` | `development` | `development` \| `production` |
| `APP_DEBUG` | `false`¹ | Affichage des erreurs détaillées — **`false` obligatoire en production** |
| `APP_URL` | `http://localhost/ecole_app` | URL de base, sans slash final |
| `APP_KEY` | — | Clé applicative (32 octets aléatoires), sert aussi de repli pour `JWT_SECRET` et `STORAGE_SIGNING_KEY` |
| `APP_TIMEZONE` | `Africa/Algiers` | Appliqué par `Core\Application::bootstrap()` pour les requêtes HTTP ; appliqué explicitement par `database/migrate.php` et `database/queue-worker.php` depuis Phase 15.1 |

¹ *Corrigé Phase 15.1 : le repli était auparavant `true` (non sécurisé) si la
variable était absente. `.env` doit toujours fixer explicitement sa valeur.*

## Base de données

| Variable | Défaut | Description |
|---|---|---|
| `DB_HOST` | `localhost` | |
| `DB_PORT` | `3306` | |
| `DB_DATABASE` | `ecole_app` | |
| `DB_USERNAME` | `root` | |
| `DB_PASSWORD` | *(vide)* | |

## Session

| Variable | Défaut | Description |
|---|---|---|
| `SESSION_LIFETIME` | `7200` | Secondes (2h) |
| `SESSION_NAME` | `ecole_session` | Nom du cookie |

## Upload (V1)

| Variable | Défaut | Description |
|---|---|---|
| `UPLOAD_MAX_SIZE` | `5242880` (5 Mo) | |
| `UPLOAD_PATH` | `storage/uploads` | |

## Email / SMS (optionnel)

| Variable | Description |
|---|---|
| `MAIL_DRIVER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM`, `MAIL_FROM_NAME`, `MAIL_FROM_EMAIL`, `MAIL_REPLY_TO` | Canal email |
| `SMS_DRIVER`, `SMS_GATEWAY_URL`, `SMS_API_URL`, `SMS_SENDER`, `SMS_API_KEY`, `SMS_PAYLOAD_TPL`, `SMS_COUNTRY_CODE` | Canal SMS |

## Multi-Tenant (Phases 14.2-14.11)

| Variable | Défaut | Description |
|---|---|---|
| `TENANT_MODE_ENABLED` | `false` | **Indicateur documentaire uniquement** — n'active pas `TenantMiddleware` (voir `docs/technique/MULTI_TENANT.md`) |
| `TENANT_DEFAULT_ID` | `1` | Établissement de repli quand `TenantContext` n'est pas positionné |
| `TENANT_BASE_DOMAIN` | *(vide)* | Domaine de base pour la résolution par sous-domaine (`{slug}.{base_domain}`) |

## API Platform

| Variable | Défaut | Description |
|---|---|---|
| `JWT_SECRET` | `APP_KEY` | Secret de signature HS256 — **définir une valeur dédiée en production**, distincte de `APP_KEY` |
| `JWT_ACCESS_TTL` | `900` | Durée de vie du jeton d'accès (secondes) |
| `JWT_REFRESH_TTL` | `2592000` | Durée de vie du jeton de rafraîchissement |
| `CORS_ORIGINS` | `http://localhost:3000,http://localhost:8080` | Liste blanche séparée par virgules — **à restreindre aux domaines réels en production** |
| `API_UPLOAD_MAX_SIZE` | `10485760` (10 Mo) | |

## Cache (Phase 14.9)

| Variable | Défaut | Description |
|---|---|---|
| `CACHE_DRIVER` | `file` | `file` (fonctionnel) \| `redis` (stub, extension PHP `redis` non fournie) |
| `REDIS_HOST` / `REDIS_PORT` | `127.0.0.1` / `6379` | Utilisés seulement si `CACHE_DRIVER=redis` — non opérationnel tant que le stub n'est pas remplacé |

## Stockage (Phase 14.8)

| Variable | Défaut | Description |
|---|---|---|
| `STORAGE_DRIVER` | `local` | `local` (fonctionnel) \| `s3` (stub, non fonctionnel) |
| `STORAGE_SIGNING_KEY` | `APP_KEY` | Clé de signature des URLs temporaires |
| `AWS_BUCKET`, `AWS_REGION`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | *(vide)* | Utilisés seulement si `STORAGE_DRIVER=s3` — non opérationnel tant que le stub n'est pas remplacé |

## Sécurité — checklist avant production

- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` généré aléatoirement, unique à cet environnement
- [ ] `JWT_SECRET` distinct de `APP_KEY`
- [ ] `CORS_ORIGINS` restreint aux domaines clients réels
- [ ] `DB_PASSWORD` fort, différent entre environnements
- [ ] `.env` protégé par les permissions du système de fichiers, exclu de tout
      contrôle de version (`.gitignore` — voir `docs/deploiement/INSTALLATION.md` §6)
