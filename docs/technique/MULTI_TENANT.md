# Multi-Tenant — SCOLARIS V2 / EduNova SaaS

État global : **313 tests dédiés, tous verts** (Phase 14.12, score 8.4/10, GO).
Ce document résume l'architecture ; le détail phase par phase vit dans
`MULTI_TENANT_V2_BLUEPRINT.md` (conception) et les 10
`MULTI_TENANT_*_IMPLEMENTATION_REPORT.md` / `MULTI_TENANT_SYSTEM_INTEGRATION_REVIEW.md`
(réalisation et audit) à la racine du projet.

## 1. Modèle d'isolation : Shared Database, Shared Schema

Un seul schéma MySQL sert tous les établissements. L'isolation repose entièrement
sur la colonne `etablissement_id`, filtrée systématiquement — jamais de base ou de
schéma séparé par client. Voir `docs/technique/BASE_DE_DONNEES.md` §3.

## 2. Infrastructure tenant (`core/Tenant/`)

| Composant | Rôle |
|---|---|
| `TenantContext` | Singleton portant l'établissement résolu pour la requête courante |
| `TenantResolver` | 5 stratégies dans l'ordre : session → sous-domaine → domaine personnalisé → chemin (`/s/{slug}`) → header `X-Tenant-Slug` |
| `TenantMiddleware` | Construit, testé, **jamais activé** dans `Core\Application::run()` — voir §3 |
| `Core\Model::$tenantScoped` | Mécanisme opt-in sur les modèles V1 ; filtre automatiquement par `TenantContext::current()` si positionné, repli sur `config/tenant.php['default_id']` sinon |

### ⚠️ Point le plus important de toute cette documentation

**`TenantMiddleware` n'a jamais été activé.** Tous les sous-systèmes ci-dessous
sont construits, testés unitairement, et fonctionnent correctement — mais
`TenantContext` n'est aujourd'hui positionné automatiquement par **aucune requête
HTTP réelle**. Le système fonctionne en pratique comme une application
mono-tenant (un seul établissement, id=1) qui est *architecturalement prête* à
devenir multi-tenant dès que ce middleware sera activé — pas comme un SaaS
multi-établissements déjà en production. Voir `RELEASE_CANDIDATE_RC1_REPORT.md`
et `MULTI_TENANT_SYSTEM_INTEGRATION_REVIEW.md` §7/§8 pour les conditions
d'activation recommandées.

## 3. RBAC tenant-aware

Voir `docs/technique/RBAC.md` §3/§4.

## 4. Branding, Domaines

- **Branding** (`core/Tenant/BrandingService.php`) : logo, favicon, couleurs,
  police, thème, image de connexion, coordonnées, pied de page — par établissement,
  mis en cache (`TenantCache`, catégorie `branding`), invalidé sur modification.
  Portail : `/parametres/branding`.
- **Domaines** (`core/Tenant/DomainVerificationService.php`) : sous-domaines et
  domaines personnalisés, vérification par challenge DNS TXT, token de
  vérification, isolation stricte (`findOwned()`). Portail : `/parametres/domaines`.
  SSL/ACME et reconfiguration Nginx/Caddy dynamique restent hors périmètre
  applicatif (infrastructure serveur).

## 5. Stockage & Quotas

- `Core\Storage\StorageInterface` (implémentation locale fonctionnelle ; adaptateur
  S3 présent en stub, prêt pour une vraie implémentation).
- `TenantQuotaService` : quotas stockage/utilisateurs/élèves/documents/pièces
  jointes, alertes à 80 % et dépassement, ledger `api_uploads`.
- Portail : `/parametres/quotas`.

## 6. Cache & Files d'attente

- `TenantCache` : préfixe systématique `tenant:{id}:{catégorie}:` — aucune méthode
  n'accepte de clé sans établissement explicite. Backend fichier fonctionnel
  (Redis en stub).
- `Core\Queue\JobQueue`/`QueueWorker` : file MySQL, `QueueWorker` reconstitue
  explicitement `TenantContext` avant l'exécution d'une tâche différée et restaure
  le contexte précédent après (y compris en cas d'échec) — c'est le seul point du
  système où la propagation du contexte tenant across un changement de process/
  tâche doit être gérée explicitement (les événements synchrones n'en ont pas
  besoin, voir `docs/technique/EVENT_SYSTEM.md` §6).
- Aucun démon Supervisord/cron : `database/queue-worker.php` traite un lot puis se
  termine, à invoquer manuellement ou via un ordonnanceur externe.
- Portail : `/parametres/monitoring`.

## 7. Portail Super-Admin SaaS

Voir `docs/technique/PORTAILS.md` §Super-Admin et
`docs/fonctionnel/GUIDE_ADMINISTRATEUR.md`. RBAC totalement indépendant (§6 de
`docs/technique/RBAC.md`).

## 8. Sauvegardes & Reprise après incident

- `core/Backup/DatabaseDumper`/`DatabaseRestorer`/`BackupEncryption`/`BackupService`.
- Sauvegarde globale (schéma+données), par tenant (données uniquement, upsert non
  destructif à la restauration), différentielle (approximation par `created_at`).
- Restauration globale **toujours** vers une base neuve jetable (jamais en place),
  restauration tenant en **upsert** avec confirmation textuelle obligatoire.
- Voir `docs/deploiement/SAUVEGARDES.md` et `docs/deploiement/RESTAURATION.md`
  pour le mode d'emploi opérationnel.

## 9. Compatibilité avec les autres modules

- **API Platform** et **Portails** résolvent le tenant via leurs **propres
  mécanismes indépendants** (claim JWT `etab` / session), pas via
  `TenantContext` — activer `TenantMiddleware` ne les rendrait pas automatiquement
  "conscients" du nouveau composant sans câblage supplémentaire (dette technique
  documentée, `MULTI_TENANT_SYSTEM_INTEGRATION_REVIEW.md` DT1).
- **Finance/RH/Vie scolaire** : tables non appliquées dans cet environnement — la
  dimension multi-tenant de ces modules n'est donc pas démontrable ici (voir
  `docs/technique/ARCHITECTURE_MODULES.md` §4).

## 10. Tests

313 tests répartis en 10 suites (`tests/Unit/*Tenant*Test.php`,
`BrandingTenantTest`, `DomainTenantTest`, `StorageQuotaTest`, `CacheQueueTest`,
`PlatformAdminTest`, `BackupDrTest`) — détail dans
`docs/developpeur/TESTS.md` et `MULTI_TENANT_SYSTEM_INTEGRATION_REVIEW.md` §3.
