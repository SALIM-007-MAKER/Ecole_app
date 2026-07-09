# Mise à jour — SCOLARIS V2 / EduNova

## 1. Procédure générale

1. **Sauvegarde globale préalable** — obligatoire (voir
   `docs/deploiement/SAUVEGARDES.md`) ; ce projet n'automatise pas encore la
   sauvegarde `pre_migration` avant `database/migrate.php` (voir
   `docs/deploiement/SAUVEGARDES.md` §5), donc **déclenchez-la manuellement**.
2. Déployer le nouveau code (remplacement des fichiers applicatifs — pas de
   `composer install`, ce projet n'a aucune dépendance tierce).
3. Appliquer les migrations :
   ```bash
   php database/migrate.php
   ```
   Idempotent — les migrations déjà appliquées (table `migrations_log`) sont
   ignorées automatiquement.
4. Vérifier : `curl https://votre-domaine/api/v1/health`, puis un contrôle
   fonctionnel des écrans principaux.
5. En cas de problème : restaurer la sauvegarde préalable
   (`docs/deploiement/RESTAURATION.md`) et revenir au code précédent.

## 2. Activer un module actuellement désactivé (Finance, RH, Vie scolaire, etc.)

Ces modules ont des routes/contrôleurs déjà en place mais nécessitent que leurs
tables SQL soient effectivement créées avant tout usage réel (voir
`docs/technique/ARCHITECTURE_MODULES.md` §4) :

1. Identifier les migrations SQL déclarées dans le `module.json` du module
   concerné (`app/Modules/{Module}/module.json`, clé `sql_tables`).
2. Créer/vérifier les fichiers de migration correspondants sous
   `database/migrations/` (certains modules peuvent nécessiter la création de ces
   migrations si elles n'ont jamais été formalisées — vérifier le rapport
   d'implémentation du module, ex. `FINANCE_BLUEPRINT_V2.md`).
3. `php database/migrate.php`.
4. Vérifier que les tables attendues existent (`SHOW TABLES LIKE '{prefixe}_%'`).
5. Passer `config/modules.php['{module}']['enabled']` à `true` (si pas déjà fait).
6. Effectuer un test de fumée HTTP réel sur chaque route principale du module
   avant de l'ouvrir aux utilisateurs (recommandation
   `RELEASE_CANDIDATE_RC1_REPORT.md` §12.4 — c'est précisément ce type de contrôle
   qui aurait détecté plus tôt le bug de routage corrigé en Phase 15.1).

## 3. Activer le Multi-Tenant complet (`TenantMiddleware`)

Voir `docs/deploiement/CONFIGURATION.md` §3 et
`MULTI_TENANT_SYSTEM_INTEGRATION_REVIEW.md` §8 — à traiter comme sa propre
bascule contrôlée, pas comme une simple mise à jour de routine.

## 4. Compatibilité ascendante

- Aucune migration de ce projet ne supprime de colonne/table existante
  (`reversible: true` avec `rollback` défini, mais l'usage courant est purement
  additif — voir `docs/technique/BASE_DE_DONNEES.md` §6).
- Les contrôleurs/routes V1 ne sont jamais supprimés lors de l'introduction d'un
  module V2 équivalent — les deux coexistent (voir
  `docs/technique/ARCHITECTURE_GLOBALE.md` §2).

## 5. Rollback d'une migration

```bash
# Non exposé par une commande CLI dédiée à ce jour — exécuter manuellement
# la clé 'rollback' du fichier de migration concerné via un script ad hoc,
# ou restaurer la sauvegarde pre-migration (méthode recommandée).
```

## 6. Documents associés

`docs/deploiement/SAUVEGARDES.md`, `docs/deploiement/RESTAURATION.md`,
`docs/technique/BASE_DE_DONNEES.md` §6, `CHANGELOG.md`.
