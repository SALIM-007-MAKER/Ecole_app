<?php

/**
 * T056 — Rattachement des comptes parents incomplets (rôle RBAC + établissement)
 *
 * Constat : des comptes `users.role = 'parent'` n'ont NI ligne `rbac_user_roles`, NI `user_etablissements`, NI `user_roles_etab`
 * (ils fonctionnent grâce au repli V1 sur `users.etablissement_id` et `users.role`).
 *
 * Règle de rattachement — démontrée, sans valeur par défaut :
 *   l'établissement d'un parent est celui de SES ENFANTS (eleves.parent_id → eleves.etablissement_id) ;
 *   il doit être unique ET identique à users.etablissement_id ET désigner un établissement non supprimé.
 *   Aucun enfant, enfants dans plusieurs établissements, ou contradiction avec users.etablissement_id
 *   ⇒ compte laissé tel quel et signalé ([AMBIGU]), jamais rattaché par défaut.
 * Ne crée QUE les lignes manquantes (jamais de modification, jamais de suppression) : un compte déjà partiellement rattaché est complété.
 * Journal `t056_parents_rattaches` : identifiants EXACTS des lignes insérées, pour un retour arrière qui ne retire que ce que la migration a créé.
 * Idempotente. Retour arrière : supprime les seules lignes journalisées puis le journal.
 */

return [
    'id'         => 'T056',
    'name'       => 'Comptes parents — rôle RBAC et rattachement à l\'établissement (démontré par les enfants)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        // Installation neuve : aucun compte parent à réparer (et le référentiel d'anciens rôles RBAC n'y est pas semé) ; rien à faire, rien à journaliser.
        if ((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'parent'")->fetchColumn() === 0) { return; }

        $rbacRole = $pdo->query("SELECT id FROM rbac_roles WHERE slug = 'parent'")->fetchColumn();
        if ($rbacRole === false) {
            throw new RuntimeException("T056 refusée : le rôle RBAC « parent » est introuvable (rbac_roles.slug = 'parent').");
        }
        $rbacRole = (int)$rbacRole;

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS t056_parents_rattaches (
                user_id INT UNSIGNED NOT NULL PRIMARY KEY,
                etablissement_id INT UNSIGNED NOT NULL,
                ue_id BIGINT UNSIGNED NULL,
                rbac_id INT UNSIGNED NULL,
                ure_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $parents = $pdo->query(
            "SELECT u.id, u.nom, u.prenom, u.etablissement_id,
                    (SELECT COUNT(*) FROM user_etablissements x WHERE x.user_id = u.id) AS n_ue,
                    (SELECT COUNT(*) FROM rbac_user_roles x WHERE x.user_id = u.id AND x.role_id = $rbacRole) AS n_rbac,
                    (SELECT COUNT(*) FROM user_roles_etab x WHERE x.user_id = u.id) AS n_ure
               FROM users u
              WHERE u.role = 'parent'
              ORDER BY u.id"
        )->fetchAll(PDO::FETCH_ASSOC);

        $etabsEnfants = $pdo->prepare('SELECT DISTINCT etablissement_id FROM eleves WHERE parent_id = ?');
        $etabValide   = $pdo->prepare('SELECT COUNT(*) FROM etablissements WHERE id = ? AND deleted_at IS NULL');
        $roleEtab     = $pdo->prepare("SELECT id FROM etab_roles WHERE code = 'parent' AND (etablissement_id IS NULL OR etablissement_id = ?) ORDER BY etablissement_id IS NULL, id LIMIT 1");
        $insUe        = $pdo->prepare('INSERT INTO user_etablissements (user_id, etablissement_id, is_primary) VALUES (?, ?, ?)');
        $insRbac      = $pdo->prepare('INSERT INTO rbac_user_roles (user_id, role_id, granted_by, actif) VALUES (?, ?, NULL, 1)');
        $insUre       = $pdo->prepare('INSERT INTO user_roles_etab (user_id, etablissement_id, role_id, assigned_by) VALUES (?, ?, ?, NULL)');
        $aPrimaire    = $pdo->prepare('SELECT COUNT(*) FROM user_etablissements WHERE user_id = ? AND is_primary = 1 AND left_at IS NULL');
        $journal      = $pdo->prepare('INSERT INTO t056_parents_rattaches (user_id, etablissement_id, ue_id, rbac_id, ure_id) VALUES (?, ?, ?, ?, ?)');

        foreach ($parents as $p) {
            if ((int)$p['n_ue'] > 0 && (int)$p['n_rbac'] > 0 && (int)$p['n_ure'] > 0) { continue; }   // déjà complet
            $uid = (int)$p['id'];
            $libelle = "users #$uid « {$p['prenom']} {$p['nom']} »";

            $etablissementsEnfants = $etabsEnfants->execute([$uid]) ? array_map('intval', $etabsEnfants->fetchAll(PDO::FETCH_COLUMN)) : [];
            if (count($etablissementsEnfants) !== 1) {
                echo "\n      [AMBIGU] $libelle : " . (count($etablissementsEnfants) === 0 ? 'aucun enfant lié' : 'enfants dans ' . count($etablissementsEnfants) . ' établissements') . ' — non rattaché';
                continue;
            }
            $etab = $etablissementsEnfants[0];
            if ($p['etablissement_id'] === null || (int)$p['etablissement_id'] !== $etab) {
                echo "\n      [AMBIGU] $libelle : users.etablissement_id (" . ($p['etablissement_id'] ?? 'NULL') . ") ≠ établissement des enfants ($etab) — non rattaché";
                continue;
            }
            $etabValide->execute([$etab]);
            if ((int)$etabValide->fetchColumn() === 0) {
                echo "\n      [AMBIGU] $libelle : établissement #$etab supprimé — non rattaché";
                continue;
            }
            $roleEtab->execute([$etab]);
            $roleEtabId = $roleEtab->fetchColumn();
            if ((int)$p['n_ure'] === 0 && $roleEtabId === false) {
                echo "\n      [AMBIGU] $libelle : rôle d'établissement « parent » introuvable — non rattaché";
                continue;
            }

            $pdo->beginTransaction();
            try {
                $ueId = $rbacId = $ureId = null;
                if ((int)$p['n_ue'] === 0) {
                    $aPrimaire->execute([$uid]);
                    $insUe->execute([$uid, $etab, (int)$aPrimaire->fetchColumn() === 0 ? 1 : 0]);
                    $ueId = (int)$pdo->lastInsertId();
                }
                if ((int)$p['n_rbac'] === 0) {
                    $insRbac->execute([$uid, $rbacRole]);
                    $rbacId = (int)$pdo->lastInsertId();
                }
                if ((int)$p['n_ure'] === 0) {
                    $insUre->execute([$uid, $etab, (int)$roleEtabId]);
                    $ureId = (int)$pdo->lastInsertId();
                }
                $journal->execute([$uid, $etab, $ueId, $rbacId, $ureId]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        $existe = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 't056_parents_rattaches'")->fetchColumn();
        if ($existe === 0) { return; }
        $pdo->beginTransaction();
        try {
            foreach ([['user_etablissements', 'ue_id'], ['rbac_user_roles', 'rbac_id'], ['user_roles_etab', 'ure_id']] as [$table, $col]) {
                $pdo->exec("DELETE t FROM `$table` t JOIN t056_parents_rattaches j ON j.`$col` = t.id");
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $pdo->exec('DROP TABLE t056_parents_rattaches');
    },
];
