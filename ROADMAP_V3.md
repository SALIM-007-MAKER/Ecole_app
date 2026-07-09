# Roadmap V3 — Évolutions majeures

> **Superseded par `ROADMAP_V3.0.md`** (Phase 16.0) — version canonique tenue à
> jour depuis la release officielle SCOLARIS V2.0.0. Ce fichier (Phase 15.2) est
> conservé pour l'historique.

Ces items dépassent la stabilisation (voir `ROADMAP_V2_1.md`) et représentent des
évolutions architecturales ou fonctionnelles significatives, déjà anticipées par
la conception actuelle (adaptateurs remplaçables, stubs documentés) mais non
implémentées.

## Infrastructure cloud réelle

- **Adaptateur S3 fonctionnel** (`Core\Storage\S3StorageAdapter`, aujourd'hui un
  stub) — stockage objet réel pour les sauvegardes et fichiers tenant.
- **Adaptateur Redis fonctionnel** (`Core\Cache\RedisCache`, stub) — cache
  distribué pour un déploiement multi-instance.
- **Workers persistants** (Supervisord/systemd) remplaçant l'invocation manuelle/
  planifiée de `database/queue-worker.php` — traitement continu de la file
  d'attente.
- **Scheduler intégré** pour les sauvegardes quotidiennes automatiques (blueprint
  §19.1 : "02h00 UTC") sans dépendre d'un cron système externe.

## Sauvegardes & reprise après incident — niveau Enterprise

- **Restauration point-in-time** via le binlog MySQL (filtré par
  `etablissement_id`) — actuellement hors périmètre applicatif.
- **Chiffrement des sauvegardes par plan tarifaire** (clé dérivée par plan plutôt
  qu'une clé unique plateforme dérivée de `APP_KEY`).
- **Rétention automatique** (purge planifiée des sauvegardes expirées — le code
  `BackupService::purgeExpired()` existe mais n'est appelé par aucun scheduler).

## Multi-Tenant — achèvement

- Activation complète de `TenantMiddleware` en production, avec bascule
  progressive établissement par établissement.
- Résolution tenant unifiée entre l'application web, l'API Platform et les
  Portails (aujourd'hui trois mécanismes distincts — voir
  `docs/technique/MULTI_TENANT.md` §9).
- Assistant de création d'établissement (onboarding complet avec premier compte
  administrateur automatique) — le portail Super-Admin ne crée aujourd'hui que la
  ligne `etablissements`.
- Export RGPD complet par tenant en tâche asynchrone avec notification email
  (prévu au blueprint §14.3, non implémenté).

## Modules métier — mise en production complète

- Application des migrations et activation réelle, avec jeux de données de
  qualification, pour Finance, RH, Vie Scolaire, puis Documents, Communication,
  Bibliothèque, Inventaire, Rapports & BI, Portails.
- Intégration cross-module complète (ex. facturation Finance déclenchée par
  inscription Scolarité, notifications Communication déclenchées par tous les
  domaines).

## Outillage développeur

- Migration progressive vers un outillage standard (PHPUnit, PHPStan,
  PHP-CS-Fixer) **si** la décision de sortir du principe "zéro dépendance" est
  prise — implique une revue de `docs/developpeur/STANDARDS_CODE.md` §1 et un
  arbitrage explicite, pas une introduction incrémentale non discutée.
- CI/CD : pipeline automatisé exécutant la suite de tests + tests de fumée HTTP à
  chaque changement (aujourd'hui entièrement manuel).

## API Platform

- Authentification OAuth2 complète (un stub existe, voir
  `API_PLATFORM_V2_BLUEPRINT.md`).
- SDKs clients officiels (mentionnés en conception, non livrés).

## Observabilité

- Agrégation de logs externe (aujourd'hui `Logger::security()` écrit localement
  uniquement, cohérent avec l'absence de dépendances tierces mais limitant à
  l'échelle).
- Tableau de bord de santé plateforme temps réel au-delà du portail Super-Admin
  actuel (alerting proactif, pas seulement consultation à la demande).
