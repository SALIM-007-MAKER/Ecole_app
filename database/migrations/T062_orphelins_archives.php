<?php

/**
 * T062 — Lignes orphelines archivées puis retirées (audit BD)
 *
 * Constat (base réelle au 2026-10-26) : notification_logs.user_id → users inexistant (7 lignes), vs_incidents_discipline.dossier_id → dossier
 * inexistant (1 ligne), finance_journaux_caisse.session_id → session inexistante (1 ligne, FK contournée à une époque).
 * Rien n'est supprimé sans trace : chaque ligne concernée est d'abord copiée (JSON) dans `audit_orphelins_archive`.
 *   - notification_logs / vs_incidents_discipline : ligne retirée (colonne NOT NULL, parent introuvable, aucune FK à respecter) ;
 *   - finance_journaux_caisse.session_id : mis à NULL (colonne nullable ; la ligne comptable est conservée).
 * Idempotente (ne traite que les orphelins encore présents). NON APPLIQUÉE À LA BASE RÉELLE sans autorisation distincte + sauvegarde.
 * Retour arrière : réinsère les lignes retirées depuis l'archive ; la valeur session_id des lignes caisse n'est PAS restaurée (la FK
 * existante la refuserait : le parent n'existe pas) — elle reste consultable dans l'archive.
 */

return [
    'id'         => 'T062',
    'name'       => 'Archive puis retire les lignes orphelines (notification_logs, vs_incidents_discipline, finance_journaux_caisse)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS audit_orphelins_archive (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(10) NOT NULL,
                table_name VARCHAR(64) NOT NULL,
                action VARCHAR(10) NOT NULL,
                ligne_id BIGINT UNSIGNED NOT NULL,
                donnees LONGTEXT NOT NULL,
                archive_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_orph_mig (migration, table_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $cibles = [
            ['notification_logs', 'user_id', 'users', 'DELETE'],
            ['vs_incidents_discipline', 'dossier_id', 'vs_dossiers_discipline', 'DELETE'],
            ['finance_journaux_caisse', 'session_id', 'finance_sessions_caisse', 'NULLIFY'],
        ];
        $arch = $pdo->prepare('INSERT INTO audit_orphelins_archive (migration, table_name, action, ligne_id, donnees) VALUES (\'T062\', ?, ?, ?, ?)');
        foreach ($cibles as [$t, $col, $parent, $action]) {
            $exists = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('$t', '$parent')")->fetchColumn();
            if ((int)$exists < 2) {
                continue;
            }
            $rows = $pdo->query("SELECT x.* FROM `$t` x WHERE x.`$col` IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `$parent` p WHERE p.id = x.`$col`)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $arch->execute([$t, $action, $r['id'], json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                if ($action === 'DELETE') {
                    $pdo->prepare("DELETE FROM `$t` WHERE id = ?")->execute([$r['id']]);
                } else {
                    $pdo->prepare("UPDATE `$t` SET `$col` = NULL WHERE id = ?")->execute([$r['id']]);
                }
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        $has = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'audit_orphelins_archive'")->fetchColumn();
        if (!$has) {
            return;
        }
        $rows = $pdo->query("SELECT table_name, donnees FROM audit_orphelins_archive WHERE migration = 'T062' AND action = 'DELETE'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $d = json_decode($r['donnees'], true);
            $cols = '`' . implode('`,`', array_keys($d)) . '`';
            $ph = implode(',', array_fill(0, count($d), '?'));
            $pdo->prepare("INSERT IGNORE INTO `{$r['table_name']}` ($cols) VALUES ($ph)")->execute(array_values($d));
        }
        // L'archive est conservée (traçabilité) ; les lignes NULLIFY ne sont pas restaurées (voir l'en-tête).
        $pdo->exec("DELETE FROM audit_orphelins_archive WHERE migration = 'T062' AND action = 'DELETE'");
    },
];
