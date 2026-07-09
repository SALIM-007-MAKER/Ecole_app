# Déploiement — SCOLARIS V2.0.0 / EduNova

Document de référence de release (Phase 16.0), consolidant les guides détaillés
sous `docs/deploiement/` (`CONFIGURATION.md`, `DOCKER.md`, `SAUVEGARDES.md`,
`RESTAURATION.md`, `MISE_A_JOUR.md`, `VARIABLES_ENVIRONNEMENT.md`) — voir chacun
pour le détail opérationnel complet. Installation initiale : `INSTALLATION.md`.

## 1. Configuration

`config/*.php` (un fichier par domaine), piloté par `.env`. Points de vigilance
sécurité avant production : `APP_DEBUG=false`, `APP_KEY`/`JWT_SECRET` uniques et
aléatoires, `CORS_ORIGINS` restreint aux domaines clients réels. Voir
`docs/deploiement/CONFIGURATION.md` et `docs/deploiement/VARIABLES_ENVIRONNEMENT.md`.

## 2. Docker

Aucun `Dockerfile`/`docker-compose.yml` n'est livré dans cette version — un
exemple de référence, non testé, est documenté dans `docs/deploiement/DOCKER.md`
à valider avant usage. Déploiement natif Apache/PHP-FPM recommandé pour cette
version (voir `INSTALLATION.md`).

## 3. Sauvegardes

Trois types (globale, par établissement, différentielle), chiffrement AES-256-CBC
par défaut, portail Super-Admin `/platform/backups` (niveau `super_admin`
uniquement). Aucun scheduler automatique livré — planification via cron système
externe appelant `database/queue-worker.php`. Détail :
`docs/deploiement/SAUVEGARDES.md`.

## 4. Restauration

Deux mécanismes distincts et non interchangeables :
- **Vérification** (base temporaire jetable) — sans risque, ne touche jamais la
  production.
- **Restauration tenant en direct** (upsert non destructif, confirmation
  textuelle obligatoire) — pour récupérer les données d'un établissement précis.

**Aucun mécanisme "écraser la production en place" n'existe** — la reprise après
sinistre total consiste à restaurer vers une nouvelle instance. Détail complet,
**à lire avant toute restauration réelle** : `docs/deploiement/RESTAURATION.md`.

## 5. Mise à jour

```bash
# 1. Sauvegarde globale préalable (voir §3)
# 2. Déployer le nouveau code
git fetch && git checkout v2.x.x
# 3. Appliquer les migrations (idempotent)
php database/migrate.php
# 4. Vérifier
curl https://votre-domaine/api/v1/health
```

Détail, y compris activation d'un module actuellement désactivé :
`docs/deploiement/MISE_A_JOUR.md`.

## 6. Monitoring

Portail Super-Admin `/platform/dashboard` et `/platform/monitoring` : état des
établissements, cache, files d'attente, alertes de quota. Journalisation
applicative via `Logger::security()` (fichiers locaux — aucun agrégateur externe
livré, cohérent avec l'architecture sans dépendance tierce).

## 7. Limitations connues à cette version

Voir `SCOLARIS_V2_FINAL_RELEASE_REPORT.md` §7 — en particulier : Finance/RH/Vie
scolaire nécessitent l'application de migrations avant tout déploiement en usage
réel ; aucun scheduler ni worker persistant n'est fourni (invocation manuelle/
planifiée requise) ; adaptateurs Redis/S3 non fonctionnels (stubs).
