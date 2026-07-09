# Roadmap V2.1 — Stabilisation et achèvement

> **Superseded par `ROADMAP_V2.1.md`** (Phase 16.0) — version canonique tenue à
> jour depuis la release officielle SCOLARIS V2.0.0. Ce fichier (Phase 15.2) est
> conservé pour l'historique ; le contenu ci-dessous reste globalement exact mais
> n'est plus mis à jour.

Objectif de cette version mineure : lever les conditions identifiées dans
`RELEASE_CANDIDATE_RC1_REPORT.md` pour passer d'un **GO WITH FIXES** à un **GO**
sans réserve, et combler la dette technique documentée sans introduire de
nouvelles fonctionnalités majeures.

## Priorité 1 — Bloquant pour un GO RC1 sans réserve

- [ ] **Appliquer les migrations Finance/RH/Vie Scolaire** (ou corriger
  `config/modules.php` pour refléter honnêtement leur indisponibilité tant que ce
  n'est pas fait). Voir `docs/technique/ARCHITECTURE_MODULES.md` §4,
  `docs/deploiement/MISE_A_JOUR.md` §2.
- [ ] **Initialiser un dépôt Git** avec `.gitignore` (`.env`, `storage/cache/`,
  `storage/tenants/`) — traçabilité de version inexistante à ce jour.
- [ ] **Rédiger `README.md`** consolidant les points d'entrée vers `docs/`.

## Priorité 2 — Dette technique documentée

- [ ] Nettoyer/réécrire `tests/Api/FilterTest.php` (dépend de PHPUnit non
  installé — ses cas sont dupliqués et fonctionnels dans `PaginationTest.php`).
- [ ] Corriger la route `/api/eleves` (V1 legacy) qui pointe vers une classe
  inexistante à son chemin actuel.
- [ ] Câbler `Core\Tenant\TenantContext` dans `ApiBaseController` et
  `PortalBaseController` (aujourd'hui deux mécanismes de résolution tenant
  indépendants coexistent — voir `docs/technique/MULTI_TENANT.md` §9).
- [ ] Automatiser la sauvegarde `pre_migration` avant `database/migrate.php` (voir
  `docs/deploiement/SAUVEGARDES.md` §5).
- [ ] Ajouter un test de fumée HTTP réel par module activé, exécuté en CI/à
  chaque phase — recommandation directe de la Phase 15.1 (c'est le seul type de
  test qui aurait détecté plus tôt le bug de routage API).

## Priorité 3 — Activation progressive

- [ ] Activer `TenantMiddleware` derrière une bascule contrôlée et testée en
  conditions HTTP réelles (voir `docs/deploiement/CONFIGURATION.md` §3).
- [ ] Activer et valider en conditions réelles (au moins un test de fumée par
  écran principal) : Documents, Communication, Bibliothèque, Inventaire,
  Rapports & BI, Portails.
- [ ] Rafraîchir `DATABASE_V2.md` pour refléter l'état post-Phase 14 (Multi-Tenant).

## Priorité 4 — Qualité et outillage

- [ ] Script agrégant l'exécution de toute la suite de tests (aucun runner
  unique n'existe à ce jour — voir `docs/developpeur/TESTS.md` §3).
- [ ] Mesurer les performances de sauvegarde/restauration sur l'infrastructure
  cible réelle (les chiffres actuels proviennent d'un environnement de
  développement local WAMP/Windows, potentiellement ralenti par l'antivirus — voir
  `RELEASE_CANDIDATE_RC1_REPORT.md` §8).
- [ ] Documenter/exécuter un test de montée en charge concurrente réel (aucun
  outil de charge n'est actuellement disponible dans ce projet sans dépendance).

## Hors périmètre V2.1 (reporté à V3)

Voir `ROADMAP_V3.md`.
