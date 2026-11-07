# Déploiement Docker sur un VPS

Pile : **Caddy** (HTTPS Let's Encrypt automatique) → **app** (PHP 8.2 + Apache) + **worker** (file d'attente et abonnements, toutes les minutes) + **db** (MySQL 8.4, jamais exposé sur Internet).

## 1. Prérequis
- Un VPS Linux avec Docker et le plugin Compose (`curl -fsSL https://get.docker.com | sh`).
- Un nom de domaine dont l'enregistrement **A** pointe vers l'IP du VPS (avant le premier démarrage, sinon Let's Encrypt échoue).
- Ports **80** et **443** ouverts (pare-feu : `ufw allow 80,443/tcp` et `ufw allow OpenSSH`). Ne pas ouvrir 3306.

## 2. Récupérer le code et configurer
```bash
git clone https://github.com/SALIM-007-MAKER/Ecole_app.git && cd Ecole_app
cp .env.production.example .env.production
chmod 600 .env.production
nano .env.production
```
Renseigner au minimum : `DOMAIN`, `APP_URL=https://<domaine>`, `CORS_ORIGINS`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `MAIL_*`, et générer chaque secret séparément :
```bash
php -r "echo bin2hex(random_bytes(32));"   # ou : openssl rand -hex 32
```
(`APP_KEY`, `BULLETIN_TOKEN_SECRET`, `JWT_SECRET`, `STORAGE_SIGNING_KEY` : quatre valeurs différentes.)
`DB_HOST`, `DB_PORT` et `TRUSTED_PROXIES` sont fixés par le compose ; laisser `TRUSTED_PROXIES` vide dans le fichier.

## 3. Démarrer
```bash
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml ps
```

## 4. Initialiser la base (une seule fois, base vide)
```bash
docker compose -f docker-compose.prod.yml exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < database/schema/ecole_app_schema_v2.0.0_T062.sql
docker compose -f docker-compose.prod.yml exec app php database/migrate.php
docker compose -f docker-compose.prod.yml exec -e INSTALL_ADMIN_PASSWORD='<12 caractères min>' app \
  php database/installer.php --slug=mon-ecole --nom="Mon École" --admin-email=vous@exemple.org --admin-nom="Nom Prénom"
docker compose -f docker-compose.prod.yml exec -e OPERATOR_PASSWORD='<14 caractères min>' app \
  php database/create-operator.php --email=vous@exemple.org --nom="Prénom Nom"
```
`migrate.php` doit finir par `0 erreurs`. Aucun compte de démonstration n'est créé.

## 5. Vérifier
- `curl https://<domaine>/health` → `{"status":"healthy","database":"ok"}`
- Connexion école : `https://<domaine>/login` ; Super-Admin : `https://<domaine>/platform/login`.
- Activer la double authentification de l'opérateur avant d'imposer `PLATFORM_REQUIRE_2FA=1`.
- Tester l'envoi d'e-mail (voir `EMAIL_GMAIL.md`).

## 6. Exploitation
- **Mise à jour** : `git pull && docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build && docker compose -f docker-compose.prod.yml exec app php database/migrate.php`
- **Journaux** : `docker compose -f docker-compose.prod.yml logs -f app worker caddy`
- **Sauvegarde de la base** : `docker compose -f docker-compose.prod.yml exec -T db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > sauvegarde-$(date +%F).sql` ; sauvegarder aussi les volumes `storage_data` et `uploads_data`. Voir `SAUVEGARDES.md` et `RESTAURATION.md`.
- Ne jamais lancer `docker compose down -v` : l'option `-v` supprime les volumes (base et fichiers).
