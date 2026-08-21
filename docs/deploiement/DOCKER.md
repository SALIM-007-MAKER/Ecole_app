# Docker — SCOLARIS V2 / EduNova

> **Statut** : `Dockerfile`, `docker-compose.yml` et `.dockerignore` existent à
> la racine du dépôt et sont **vérifiés de bout en bout** (21/08/2026) — build,
> démarrage avec MySQL, import d'un snapshot de base, migrations, et appel
> réel à `/api/v1/health` (`{"status":"healthy","database":"ok"}`) dans un
> conteneur vide. Ce document ne recopie plus leur contenu (pour éviter qu'ils
> divergent) — il documente les pièges déjà rencontrés et donne la marche à
> suivre pour un déploiement réel.

## 1. Fichiers du projet

- `Dockerfile` — base `php:8.2-apache`, extensions `pdo_mysql`, `gd`, `zip`,
  `opcache`, document root sur `public/`. Pas de `composer install` (aucune
  dépendance Composer dans ce projet).
- `docker-compose.yml` — pour le développement/test local : service `app` +
  MySQL 8.4 avec volumes persistants (`storage_data`, `uploads_data`,
  `db_data`).
- `.dockerignore` — **exclut `.env`**. Piège réel rencontré en testant : sans
  lui, `COPY . /var/www/html` embarque le `.env` de développement local dans
  l'image, qui écrase ensuite silencieusement les variables d'environnement
  fournies par la plateforme d'hébergement (`DB_HOST=localhost` au lieu de
  l'hôte réel, connexion PDO en erreur `No such file or directory`). Ne le
  supprimez pas.

## 2. Démarrage local

```bash
docker compose up -d --build
```

Puis, base de données (voir `docs/deploiement/INSTALLATION.md` §4 pour le
détail et le piège à éviter — `migrate.php` seul ne suffit pas sur une base
vide) :

```bash
docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASSWORD" ecole_app < snapshot.sql
docker compose exec app php database/migrate.php
curl http://localhost:8080/api/v1/health
```

## 3. Points de vigilance spécifiques à ce projet

- **`storage/` et `public/uploads/`** doivent être montés en volumes
  persistants — uploads et sauvegardes locales y vivent ; une reconstruction
  de conteneur sans volume les perdrait.
- **`.env` ne doit jamais être copié dans l'image** (voir §1) — les variables
  viennent de la plateforme d'hébergement (`docker compose environment:`,
  variables Railway/Render…). `public/index.php` et `database/migrate.php`/
  `queue-worker.php` chargent `.env` **s'il existe**, sinon utilisent
  `$_ENV`/les variables déjà présentes — corrigé le 21/08/2026 : ces deux
  derniers exigeaient auparavant un fichier `.env` physique et refusaient de
  démarrer sans lui, ce qui cassait toute installation pilotée uniquement par
  variables d'environnement.
- **`APP_URL`** doit correspondre exactement à l'URL externe réelle — `Core\
  Application::getBasePath()` en dérive le chemin de base.
- **File d'attente** (`docs/technique/MULTI_TENANT.md` §6) : aucun démon
  n'est fourni — ajouter un service/cron séparé exécutant
  `php database/queue-worker.php` périodiquement si des sauvegardes
  planifiées ou un traitement asynchrone sont nécessaires.
- **Redis/S3** : les adaptateurs sont des stubs non fonctionnels dans le code
  actuel (`CACHE_DRIVER`/`STORAGE_DRIVER`) — les ajouter au
  `docker-compose.yml` n'aurait d'effet qu'après implémentation réelle côté
  application.

## 4. Déploiement sur Railway (recommandé)

Railway a été retenu plutôt que Vercel : cette application utilise des
sessions PHP natives et écrit des fichiers uploadés sur disque, ce que
l'exécution serverless de Vercel ne permet pas sans réécriture importante du
code (stockage objet, sessions externalisées) — voir l'échange du 21/08/2026.
Railway déploie directement le `Dockerfile` avec disque persistant et MySQL
géré.

1. **Créer un projet** sur [railway.app](https://railway.app), *New Project →
   Deploy from GitHub repo*, sélectionner `SALIM-007-MAKER/Ecole_app` et la
   branche à déployer. Railway détecte le `Dockerfile` automatiquement.
2. **Ajouter une base de données** : *New → Database → MySQL* dans le même
   projet. Railway fournit `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`,
   `MYSQLUSER`, `MYSQLPASSWORD` — à reporter dans les variables du service
   `app` sous les noms attendus par l'application (`DB_HOST`, `DB_PORT`,
   `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
3. **Variables d'environnement du service `app`** (onglet *Variables*) — au
   minimum celles de `docs/deploiement/VARIABLES_ENVIRONNEMENT.md` :
   `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domaine
   Railway>`, `APP_KEY` (généré), `DB_HOST`/`DB_PORT`/`DB_DATABASE`/
   `DB_USERNAME`/`DB_PASSWORD` (repris de l'étape 2).
4. **Volume persistant** : *Settings → Volumes*, monter un volume sur
   `/var/www/html/storage` et un second sur `/var/www/html/public/uploads` —
   sans ça, tout fichier uploadé disparaît au prochain déploiement.
5. **Base de données** : se connecter au MySQL Railway (identifiants fournis
   dans l'onglet *Connect*) et importer un snapshot (voir
   `docs/deploiement/INSTALLATION.md` §4) :
   ```bash
   mysql -h <MYSQLHOST> -P <MYSQLPORT> -u <MYSQLUSER> -p<MYSQLPASSWORD> \
       <MYSQLDATABASE> < snapshot.sql
   ```
6. **Domaine** : Railway fournit un sous-domaine `*.up.railway.app`
   gratuitement (*Settings → Networking → Generate Domain*) ; un domaine
   personnalisé peut être attaché ensuite.
7. **Vérification** : `curl https://<domaine>/api/v1/health`, puis `/login`.

## 5. Documents associés

`docs/deploiement/INSTALLATION.md`, `docs/deploiement/VARIABLES_ENVIRONNEMENT.md`.
