# Installation — SCOLARIS V2.0.0 / EduNova

Document de référence de release (Phase 16.0). Détail complet et à jour :
`docs/deploiement/INSTALLATION.md` (identique en substance ; ce fichier racine
est le point d'entrée attendu pour une release officielle).

## 1. Prérequis

| Composant | Version |
|---|---|
| PHP | 8.2+ (extensions `pdo_mysql`, `openssl`, `gd`, `json`, `mbstring`) |
| MySQL | 8.0+ (validé sur 8.4.7) |
| Serveur web | Apache (`mod_rewrite`) ou Nginx, document root sur `public/` |
| Composer | **Non requis** — projet sans dépendance tierce |

## 2. Récupération du code

```bash
git clone <votre-dépôt> scolaris-v2
cd scolaris-v2
git checkout v2.0.0
```

## 3. Configuration

```bash
cp .env.example .env
php -r "echo bin2hex(random_bytes(32));"   # → coller dans APP_KEY
```

Éditer `.env` : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `DB_*`. Voir
`docs/deploiement/VARIABLES_ENVIRONNEMENT.md` pour la référence complète.

## 4. Base de données

```bash
mysql -u root -p -e "CREATE DATABASE ecole_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php database/migrate.php
```

## 5. Permissions fichiers

Le processus web doit pouvoir écrire dans `storage/` et `public/uploads/`.

## 6. Vérification

```bash
curl https://votre-domaine/api/v1/health
# {"success":true,"data":{"status":"healthy",...}}
```

## 7. Étapes suivantes

`DEPLOYMENT.md` (configuration serveur détaillée, Docker), `ADMIN_GUIDE.md`
(prise en main), `SCOLARIS_V2_FINAL_RELEASE_REPORT.md` §7 (limitations connues à
lire avant d'activer Finance/RH/Vie scolaire ou tout module désactivé).
