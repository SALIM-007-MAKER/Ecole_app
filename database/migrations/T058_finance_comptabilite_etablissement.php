<?php

/**
 * T058 — Comptabilité PROPRE à chaque établissement (audit d'isolation multi-établissements)
 *
 * Tables : finance_exercices (données de l'établissement), finance_comptes / finance_journaux_comptables / finance_regles_comptables
 * (plan comptable, journaux et règles : modèle copié pour chaque établissement). Les périodes, écritures et lignes en dépendent par
 * exercice_id / ecriture_id (aucune colonne à ajouter).
 * Avant : un seul plan comptable, des exercices et des écritures communs à tous les établissements.
 *
 * Règles de rattachement (vérifiables, sans valeur par défaut) :
 *   1. Exercice : rattaché à l'établissement des utilisateurs qui ont créé ses écritures (finance_ecritures.created_by). Plusieurs
 *      établissements -> NULL + [AMBIGU]. Sans écriture : rattaché à l'établissement UNIQUE s'il n'en existe qu'un, sinon NULL + [AMBIGU].
 *   2. Plan comptable, journaux, règles : rattachés à l'établissement propriétaire (le plus ancien non supprimé, normalement l'établissement
 *      historique) ; les autres établissements reçoivent leur copie à la première utilisation (Core\Tenant\ComptabiliteAmorcage).
 * Structure : etablissement_id NULLABLE + index + clé étrangère (RESTRICT) ; unicité de `code` / `type_source` -> (etablissement_id, ...).
 * Compatibilité : le code détecte la colonne ; avant la migration tout reste partagé. Idempotente.
 * NON APPLIQUÉE À LA BASE RÉELLE tant qu'une autorisation distincte n'a pas été donnée (sauvegarde préalable obligatoire).
 */

return [
    'id'         => 'T058',
    'name'       => 'Finance — comptabilité par établissement (exercices, plan comptable, journaux, règles)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $colonne = static fn(string $t): bool => (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$t' AND column_name = 'etablissement_id'")->fetchColumn() > 0;
        $defs = [
            // table => [court, index unique d'origine, colonne d'unicité]
            'finance_exercices'            => ['fex', null, null],
            'finance_comptes'              => ['fcp', 'uq_fc_code', 'code'],
            'finance_journaux_comptables'  => ['fjo', 'uq_fjc_code', 'code'],
            'finance_regles_comptables'    => ['fre', 'uq_frc_type', 'type_source'],
        ];
        foreach ($defs as $table => [$court, $uniq, $col]) {
            if ($colonne($table)) { continue; }
            $sql = "ALTER TABLE `$table`
                      ADD COLUMN etablissement_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = propriété non démontrée' AFTER id,
                      ADD KEY idx_{$court}_etab (etablissement_id),
                      ADD CONSTRAINT fk_{$court}_etab FOREIGN KEY (etablissement_id) REFERENCES etablissements (id) ON DELETE RESTRICT";
            $pdo->exec($sql);
            if ($uniq !== null) { $pdo->exec("ALTER TABLE `$table` DROP INDEX `$uniq`, ADD UNIQUE KEY uq_{$court}_etab_{$col} (etablissement_id, `$col`)"); }
        }

        $etabs = array_map('intval', array_column($pdo->query('SELECT id FROM etablissements WHERE deleted_at IS NULL ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'id'));
        $proprietaire = $etabs[0] ?? null;

        // 1) exercices
        $usages = [];
        foreach ($pdo->query('SELECT ec.exercice_id AS id, u.etablissement_id AS etab FROM finance_ecritures ec JOIN users u ON u.id = ec.created_by WHERE ec.created_by IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['etab'] !== null) { $usages[(int)$r['id']][(int)$r['etab']] = true; }
        }
        $set = $pdo->prepare('UPDATE finance_exercices SET etablissement_id = ? WHERE id = ? AND etablissement_id IS NULL');
        foreach ($pdo->query('SELECT id, libelle FROM finance_exercices WHERE etablissement_id IS NULL ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $ex) {
            $id = (int)$ex['id']; $e = array_keys($usages[$id] ?? []);
            if (count($e) === 1) { $set->execute([$e[0], $id]); continue; }
            if (count($e) === 0 && count($etabs) === 1) { $set->execute([$etabs[0], $id]); continue; }
            echo "\n      [AMBIGU] finance_exercices #$id « {$ex['libelle']} » : " . (count($e) === 0 ? 'aucune écriture et plusieurs établissements' : 'écritures de ' . count($e) . ' établissements') . ' — non rattaché (NULL), à arbitrer';
        }

        // 2) plan comptable, journaux, règles -> établissement propriétaire
        if ($proprietaire !== null) {
            foreach (['finance_comptes', 'finance_journaux_comptables', 'finance_regles_comptables'] as $t) {
                $pdo->prepare("UPDATE `$t` SET etablissement_id = ? WHERE etablissement_id IS NULL")->execute([$proprietaire]);
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        $defs = [
            'finance_exercices'            => ['fex', null, null],
            'finance_comptes'              => ['fcp', 'uq_fc_code', 'code'],
            'finance_journaux_comptables'  => ['fjo', 'uq_fjc_code', 'code'],
            'finance_regles_comptables'    => ['fre', 'uq_frc_type', 'type_source'],
        ];
        foreach ($defs as $table => [$court, $uniq, $col]) {
            if ((int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = 'etablissement_id'")->fetchColumn() === 0) { continue; }
            if ($uniq !== null) {
                if ((int)$pdo->query("SELECT COUNT(*) FROM (SELECT `$col` FROM `$table` GROUP BY `$col` HAVING COUNT(*) > 1) d")->fetchColumn() > 0) {
                    throw new RuntimeException("T058 rollback refusé : $table contient des « $col » en plusieurs exemplaires (copies par établissement).");
                }
            }
            $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY fk_{$court}_etab");
            $drop = ["DROP INDEX idx_{$court}_etab"];
            if ($uniq !== null) { $drop[] = "DROP INDEX uq_{$court}_etab_{$col}"; $drop[] = "ADD UNIQUE KEY `$uniq` (`$col`)"; }
            $drop[] = 'DROP COLUMN etablissement_id';
            $pdo->exec("ALTER TABLE `$table` " . implode(', ', $drop));
        }
    },
];
