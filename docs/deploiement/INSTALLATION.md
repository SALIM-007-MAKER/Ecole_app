# Installation — SCOLARIS V2 / EduNova

## 1. Prérequis

| Composant | Version | Notes |
|---|---|---|
| PHP | 8.2+ | Extensions : `pdo_mysql`, `openssl`, `gd`, `json`, `mbstring` |
| MySQL | 8.0+ (testé sur 8.4) | InnoDB, `utf8mb4` |
| Serveur web | Apache (mod_rewrite) ou Nginx | Document root sur `public/` |
| Composer | **Non requis** | Ce projet n'a aucune dépendance tierce (voir `docs/developpeur/STANDARDS_CODE.md`) |

## 2. Récupération du code

Ce projet n'est actuellement **pas versionné dans un dépôt Git**
(`RELEASE_CANDIDATE_RC1_REPORT.md` §6, DT5). Avant toute mise en production,
initialisez un dépôt (`git init`) et ajoutez un `.gitignore` couvrant au minimum
`.env`, `storage/cache/`, `storage/tenants/` — voir §6 ci-dessous.

## 3. Configuration de l'environnement

```bash
cp .env.example .env
```

Éditez `.env` (voir `docs/deploiement/VARIABLES_ENVIRONNEMENT.md` pour la
référence complète). Au minimum :

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.tld
APP_KEY=            # générer : php -r "echo bin2hex(random_bytes(32));"

DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
```

## 4. Base de données

```bash
# Créer la base (vide, utf8mb4)
mysql -u root -p -e "CREATE DATABASE ecole_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Appliquer toutes les migrations
php database/migrate.php
```

Le runner est **idempotent** : relancer la commande ne réapplique pas les
migrations déjà exécutées (table `migrations_log`). Voir
`docs/technique/BASE_DE_DONNEES.md` §6.

## 5. Permissions fichiers

Le processus web doit pouvoir écrire dans :
```
storage/            (cache, sauvegardes, uploads tenant)
public/uploads/      (photos, logos, imports)
```

## 6. Configuration du serveur web (Apache)

Document root → `public/`. `mod_rewrite` activé, `.htaccess` déjà présent dans
`public/` pour rediriger toute requête vers `index.php`. Exemple de VirtualHost :

```apache
<VirtualHost *:80>
    ServerName votre-domaine.tld
    DocumentRoot /var/www/ecole_app/public
    <Directory /var/www/ecole_app/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## 7. Compte administrateur initial

Un compte `admin@ecole.dz` est créé par les migrations de base (environnement de
référence) — **changez immédiatement son mot de passe** en production, ou créez
votre propre compte administrateur puis désactivez celui-ci.

## 8. Vérification de l'installation

```bash
curl https://votre-domaine.tld/api/v1/health
# Attendu : {"success":true,"data":{"status":"healthy",...}}
```

Puis connectez-vous via `/login` et vérifiez le tableau de bord.

## 9. Étapes suivantes

- `docs/deploiement/CONFIGURATION.md` — ajustements post-installation
- `docs/deploiement/DOCKER.md` — alternative conteneurisée
- `docs/deploiement/SAUVEGARDES.md` — mise en place des sauvegardes planifiées
- `RELEASE_CANDIDATE_RC1_REPORT.md` — **à lire avant toute mise en production** :
  3 modules (Finance/RH/Vie scolaire) nécessitent l'application de migrations
  supplémentaires avant usage réel (voir
  `docs/technique/ARCHITECTURE_MODULES.md` §4 et `docs/deploiement/MISE_A_JOUR.md`).
