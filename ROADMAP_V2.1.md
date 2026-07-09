# Roadmap V2.1 — Stabilisation et achèvement

Version canonique post-release (Phase 16.0) de la feuille de route de
stabilisation. Remplace `ROADMAP_V2_1.md` (Phase 15.2), dont le contenu est
repris et confirmé ici sans changement de fond.

**Ces éléments ne sont PAS intégrés à SCOLARIS V2.0.0** — leur intégration ouvre
un nouveau cycle de développement (2.1.x), distinct de la version figée décrite
dans `SCOLARIS_V2_FINAL_RELEASE_REPORT.md`.

## Priorité 1 — Seule condition bloquante pour un déploiement production complet

- [ ] **Appliquer les migrations Finance/RH/Vie Scolaire** (ou corriger
  `config/modules.php` pour refléter honnêtement leur indisponibilité tant que ce
  n'est pas fait). Voir `SCOLARIS_V2_FINAL_RELEASE_REPORT.md` §7,
  `docs/technique/ARCHITECTURE_MODULES.md` §4.

## Priorité 2 — Dette technique documentée

- [ ] Nettoyer/réécrire `tests/Api/FilterTest.php` (dépend de PHPUnit non
  installé — ses cas sont dupliqués et fonctionnels dans `PaginationTest.php`).
- [ ] Corriger la route V1 `/api/eleves` pointant vers une classe inexistante à
  son chemin actuel.
- [ ] Câbler `Core\Tenant\TenantContext` dans `ApiBaseController` et
  `PortalBaseController` (deux mécanismes de résolution tenant indépendants
  coexistent aujourd'hui).
- [ ] Automatiser la sauvegarde `pre_migration` avant `database/migrate.php`.
- [ ] Ajouter un test de fumée HTTP réel par module activé — c'est ce type de
  test qui a permis de trouver les bugs critiques corrigés en Phases 15.1 et
  15.3 ; à systématiser plutôt qu'à refaire ponctuellement à chaque revue.

## Priorité 3 — Activation progressive

- [ ] Activer `TenantMiddleware` derrière une bascule contrôlée et testée en
  conditions HTTP réelles.
- [ ] Activer et valider en conditions réelles (au moins un test de fumée par
  écran principal) : Documents, Communication, Bibliothèque, Inventaire,
  Rapports & BI, Portails — leur routage a été corrigé Phase 15.3, il ne leur
  manque plus que l'application des migrations et une vérification fonctionnelle.
- [ ] Rafraîchir `DATABASE_V2.md` pour refléter l'état post-Multi-Tenant.

## Priorité 4 — Qualité et outillage

- [ ] Script agrégant l'exécution de toute la suite de tests.
- [ ] Mesurer les performances de sauvegarde/restauration sur l'infrastructure
  cible réelle (chiffres actuels issus d'un environnement de développement local).
- [ ] Test de montée en charge concurrente réel.
- [ ] Pousser le dépôt Git initialisé en Phase 16.0 vers un hébergeur distant
  (GitHub/GitLab) avec une politique de branches et de revue de code.

## Hors périmètre V2.1

Voir `ROADMAP_V3.0.md`.
