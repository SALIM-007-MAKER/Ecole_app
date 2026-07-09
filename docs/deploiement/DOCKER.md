# Docker — SCOLARIS V2 / EduNova

> **Statut** : aucun `Dockerfile`/`docker-compose.yml` n'existe actuellement dans
> ce projet — l'application a toujours été exploitée directement sur WAMP/Apache
> (voir `docs/deploiement/INSTALLATION.md`). Ce document fournit une configuration
> Docker **de référence, non testée dans cet environnement**, à valider avant tout
> usage réel (build, démarrage, exécution de la suite de tests dans le conteneur).
> Cohérent avec la phase de documentation (aucune modification de code) : les
> fichiers ci-dessous sont des **exemples à créer**, pas des fichiers livrés.

## 1. Dockerfile (exemple)

```dockerfile
FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_mysql gd zip opcache \
    && a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html

# Le document root de l'application est public/
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!/var/www/html/public/!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/public/uploads

EXPOSE 80
```

Ce projet n'a **aucune dépendance Composer** (voir
`docs/developpeur/STANDARDS_CODE.md`) — pas d'étape `composer install` nécessaire,
contrairement à la plupart des projets PHP modernes.

## 2. docker-compose.yml (exemple)

```yaml
services:
  app:
    build: .
    ports: ["8080:80"]
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
      DB_HOST: db
      DB_DATABASE: ecole_app
      DB_USERNAME: ecole_app
      DB_PASSWORD: ${DB_PASSWORD}
    volumes:
      - ./storage:/var/www/html/storage
      - ./public/uploads:/var/www/html/public/uploads
    depends_on: [db]

  db:
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: ecole_app
      MYSQL_USER: ecole_app
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
    command: --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci

volumes:
  db_data:
```

## 3. Démarrage (une fois les fichiers créés et validés)

```bash
docker compose up -d --build
docker compose exec app php database/migrate.php
docker compose exec app curl -f http://localhost/api/v1/health
```

## 4. Points de vigilance spécifiques à ce projet

- **`storage/` et `public/uploads/`** doivent être montés en volumes persistants —
  sauvegardes locales (`docs/deploiement/SAUVEGARDES.md`) et fichiers uploadés y
  vivent, une reconstruction de conteneur sans volume les perdrait.
- **Extension `gd`** requise (génération d'icônes PWA, traitement d'images) —
  incluse dans l'exemple ci-dessus.
- **`APP_URL`** doit correspondre exactement à l'URL externe réelle (proxy inverse
  éventuel) — `Core\Application::getBasePath()` en dérive le chemin de base.
- **File d'attente** (`docs/technique/MULTI_TENANT.md` §6) : aucun démon n'est
  fourni — ajouter un conteneur/cron séparé exécutant
  `php database/queue-worker.php` périodiquement si des sauvegardes planifiées ou
  un traitement asynchrone sont nécessaires.
- **Redis/S3** : les adaptateurs sont des stubs non fonctionnels dans le code
  actuel (`CACHE_DRIVER`/`STORAGE_DRIVER`) — ajouter les services correspondants au
  `docker-compose.yml` n'aurait d'effet qu'après implémentation réelle des
  adaptateurs côté application.

## 5. Documents associés

`docs/deploiement/INSTALLATION.md`, `docs/deploiement/VARIABLES_ENVIRONNEMENT.md`.
