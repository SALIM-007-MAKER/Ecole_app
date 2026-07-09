# Roadmap V3.0 — Évolutions majeures

Version canonique post-release (Phase 16.0) de la feuille de route long terme.
Remplace `ROADMAP_V3.md` (Phase 15.2), dont le contenu est repris et confirmé ici
sans changement de fond.

**Ces éléments ne sont PAS intégrés à SCOLARIS V2.0.0**, ni prévus pour 2.1.x
(voir `ROADMAP_V2.1.md`) — ils représentent des évolutions architecturales
majeures nécessitant leur propre cycle de développement (V3).

## Infrastructure cloud réelle

- Adaptateur S3 fonctionnel (`Core\Storage\S3StorageAdapter`, stub à ce jour).
- Adaptateur Redis fonctionnel (`Core\Cache\RedisCache`, stub à ce jour).
- Workers persistants (Supervisord/systemd) remplaçant l'invocation manuelle de
  `database/queue-worker.php`.
- Scheduler intégré pour les sauvegardes automatiques quotidiennes.

## Sauvegardes & reprise après incident — niveau Enterprise

- Restauration point-in-time via binlog MySQL.
- Chiffrement des sauvegardes par plan tarifaire (clé dérivée par plan).
- Purge automatique planifiée des sauvegardes expirées
  (`BackupService::purgeExpired()` existe, non planifié).

## Multi-Tenant — achèvement

- Activation complète de `TenantMiddleware` en production, bascule progressive.
- Résolution tenant unifiée entre application web, API Platform et Portails.
- Assistant de création d'établissement (onboarding complet, premier compte
  administrateur automatique).
- Export RGPD complet par tenant en tâche asynchrone avec notification email.

## Modules métier — mise en production complète

- Application des migrations et activation réelle, avec jeux de données de
  qualification, pour Finance, RH, Vie Scolaire, puis Documents, Communication,
  Bibliothèque, Inventaire, Rapports & BI, Portails.
- Intégration cross-module complète (facturation ↔ inscription, notifications ↔
  tous domaines).

## Outillage développeur

- Migration éventuelle vers un outillage standard (PHPUnit, PHPStan,
  PHP-CS-Fixer) **si** la décision de sortir du principe "zéro dépendance" est
  prise explicitement — implique une revue de `docs/developpeur/STANDARDS_CODE.md`.
- CI/CD : pipeline automatisé exécutant tests + fumée HTTP à chaque changement.

## API Platform

- Authentification OAuth2 complète.
- SDKs clients officiels.

## Observabilité

- Agrégation de logs externe.
- Tableau de bord de santé plateforme avec alerting proactif.

## Prérequis avant d'ouvrir le cycle V3

`ROADMAP_V2.1.md` entièrement traité, en particulier la Priorité 1 (Finance/RH/
Vie scolaire opérationnels) — une évolution architecturale majeure ne doit pas
être entamée sur une base dont l'activation complète des modules existants
n'est pas encore démontrée.
