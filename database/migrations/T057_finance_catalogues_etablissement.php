<?php

/**
 * T057 — Catalogues financiers PROPRES à chaque établissement (audit d'isolation multi-établissements)
 *
 * Tables : finance_frais_types, finance_categories_frais, finance_modes_paiement, finance_categories_depenses, finance_fournisseurs.
 * Avant : aucune colonne d'établissement — tous les établissements partageaient (et pouvaient lire / modifier) les mêmes frais,
 * catégories, modes de paiement et fournisseurs.
 *
 * Mêmes règles, vérifiables et sans valeur par défaut, que T054 :
 *   1. Une ligne UTILISÉE par les élèves / utilisateurs d'UN SEUL établissement lui est rattachée.
 *   2. Une ligne utilisée par PLUSIEURS établissements est laissée à NULL et signalée ([AMBIGU]).
 *   3. Le CATALOGUE est formé des lignes sans propriétaire et NON référencées, une par code. Chaque établissement existant reçoit
 *      ensuite SA copie de chaque code qui lui manque (copie du catalogue — pas une attribution de propriété).
 * Structure : etablissement_id NULLABLE + index + clé étrangère (RESTRICT) ; l'unicité de `code` devient (etablissement_id, code).
 * Compatibilité : le code détecte la colonne ; avant la migration les catalogues restent partagés. Idempotente.
 * NON APPLIQUÉE À LA BASE RÉELLE tant qu'une autorisation distincte n'a pas été donnée (sauvegarde préalable obligatoire).
 * Retour arrière : supprime les copies non référencées (garde le plus ancien exemplaire de chaque code), rétablit l'unicité de `code`.
 */

return [
    'id'         => 'T057',
    'name'       => 'Finance — catalogues par établissement (frais, catégories, modes de paiement, fournisseurs) : rattachement démontré et copies',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $etabDe = 'e.etablissement_id';
        $catalogues = [
            // table => [court, usages (id, etab), références (id)] — l'ordre compte : les catégories de frais se rattachent via les frais
            'finance_frais_types' => ['ftyp',
                "SELECT l.frais_type_id AS id, $etabDe AS etab FROM finance_lignes_facture l JOIN finance_factures f ON f.id = l.facture_id JOIN eleves e ON e.id = f.eleve_id WHERE l.frais_type_id IS NOT NULL",
                'SELECT DISTINCT frais_type_id AS id FROM finance_lignes_facture WHERE frais_type_id IS NOT NULL'],
            'finance_categories_frais' => ['cfrai',
                'SELECT categorie_id AS id, etablissement_id AS etab FROM finance_frais_types WHERE categorie_id IS NOT NULL AND etablissement_id IS NOT NULL',
                'SELECT DISTINCT categorie_id AS id FROM finance_frais_types WHERE categorie_id IS NOT NULL'],
            'finance_modes_paiement' => ['mpaie',
                "SELECT p.mode_paiement_id AS id, $etabDe AS etab FROM finance_paiements p JOIN finance_factures f ON f.id = p.facture_id JOIN eleves e ON e.id = f.eleve_id WHERE p.mode_paiement_id IS NOT NULL
                 UNION ALL SELECT d.mode_paiement_id, u.etablissement_id FROM finance_decaissements d JOIN users u ON u.id = d.saisi_par WHERE d.mode_paiement_id IS NOT NULL",
                'SELECT DISTINCT mode_paiement_id AS id FROM finance_paiements WHERE mode_paiement_id IS NOT NULL UNION SELECT DISTINCT mode_paiement_id FROM finance_decaissements WHERE mode_paiement_id IS NOT NULL'],
            'finance_categories_depenses' => ['cdep',
                'SELECT d.categorie_id AS id, u.etablissement_id AS etab FROM finance_decaissements d JOIN users u ON u.id = d.saisi_par WHERE d.categorie_id IS NOT NULL',
                'SELECT DISTINCT categorie_id AS id FROM finance_decaissements WHERE categorie_id IS NOT NULL'],
            'finance_fournisseurs' => ['four',
                'SELECT d.fournisseur_id AS id, u.etablissement_id AS etab FROM finance_decaissements d JOIN users u ON u.id = d.saisi_par WHERE d.fournisseur_id IS NOT NULL',
                'SELECT DISTINCT fournisseur_id AS id FROM finance_decaissements WHERE fournisseur_id IS NOT NULL'],
        ];
        $etablissements = array_column($pdo->query('SELECT id FROM etablissements WHERE deleted_at IS NULL ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'id');

        foreach ($catalogues as $table => [$court, $sqlUsages, $sqlRefs]) {
            $existe = (int)$pdo->query(
                "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = 'etablissement_id'"
            )->fetchColumn() > 0;
            if (!$existe) {
                $pdo->exec(
                    "ALTER TABLE `$table`
                       ADD COLUMN etablissement_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = catalogue ou propriété non démontrée' AFTER id,
                       ADD KEY idx_{$court}_etab (etablissement_id),
                       ADD CONSTRAINT fk_{$court}_etab FOREIGN KEY (etablissement_id) REFERENCES etablissements (id) ON DELETE RESTRICT"
                );
                $pdo->exec("ALTER TABLE `$table` DROP INDEX `code`, ADD UNIQUE KEY uq_{$court}_etab_code (etablissement_id, code)");
            }

            $aUpdated = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = 'updated_at'")->fetchColumn() > 0;
            $cols = array_column($pdo->query(
                "SELECT column_name AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name NOT IN ('id', 'etablissement_id') ORDER BY ordinal_position"
            )->fetchAll(PDO::FETCH_ASSOC), 'c');
            $liste = '`' . implode('`, `', $cols) . '`';
            $copier = static function (int $sourceId, ?int $etab) use ($pdo, $table, $liste): int {
                $pdo->prepare("INSERT INTO `$table` (etablissement_id, $liste) SELECT ?, $liste FROM `$table` WHERE id = ?")->execute([$etab, $sourceId]);
                return (int)$pdo->lastInsertId();
            };

            $usages = [];
            foreach ($pdo->query($sqlUsages)->fetchAll(PDO::FETCH_ASSOC) as $r) { if ($r['etab'] !== null) { $usages[(int)$r['id']][] = (int)$r['etab']; } }
            $references = array_map('intval', array_column($pdo->query($sqlRefs)->fetchAll(PDO::FETCH_ASSOC), 'id'));
            $lignes = $pdo->query("SELECT id, code, etablissement_id FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

            // 3) catalogue : un exemplaire sans propriétaire et NON référencé par code
            $catalogue = [];
            foreach ($lignes as $l) {
                if ($l['etablissement_id'] === null && !in_array((int)$l['id'], $references, true) && !isset($catalogue[$l['code']])) { $catalogue[$l['code']] = (int)$l['id']; }
            }
            foreach ($lignes as $l) {
                if ($l['etablissement_id'] === null && !isset($catalogue[$l['code']])) { $catalogue[$l['code']] = $copier((int)$l['id'], null); }
            }

            // 1-2) rattachement des lignes utilisées
            $set = $aUpdated ? 'etablissement_id = ?, updated_at = updated_at' : 'etablissement_id = ?';
            $rattache = $pdo->prepare("UPDATE `$table` SET $set WHERE id = ? AND etablissement_id IS NULL");
            foreach ($lignes as $l) {
                $id = (int)$l['id'];
                if ($l['etablissement_id'] !== null || in_array($id, $catalogue, true) || !in_array($id, $references, true)) { continue; }
                $etabs = array_values(array_unique($usages[$id] ?? []));
                if (count($etabs) === 1) { $rattache->execute([$etabs[0], $id]); continue; }
                echo "\n      [AMBIGU] $table #$id « {$l['code']} » : " . (count($etabs) === 0 ? 'usage sans propriétaire déterminable' : 'utilisé par ' . count($etabs) . ' établissements') . ' — non rattaché (NULL), à arbitrer';
            }

            // 3) copies par établissement
            $a = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE etablissement_id = ? AND code = ?");
            foreach ($etablissements as $e) {
                foreach ($catalogue as $code => $gabarit) {
                    $a->execute([$e, $code]);
                    if ((int)$a->fetchColumn() === 0) { $copier($gabarit, (int)$e); }
                }
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        $defs = [
            'finance_fournisseurs'        => 'SELECT 1 FROM finance_decaissements x WHERE x.fournisseur_id = t.id',
            'finance_categories_depenses' => 'SELECT 1 FROM finance_decaissements x WHERE x.categorie_id = t.id',
            'finance_modes_paiement'      => 'SELECT 1 FROM finance_paiements x WHERE x.mode_paiement_id = t.id UNION ALL SELECT 1 FROM finance_decaissements y WHERE y.mode_paiement_id = t.id',
            'finance_categories_frais'    => 'SELECT 1 FROM finance_frais_types x WHERE x.categorie_id = t.id',
            'finance_frais_types'         => 'SELECT 1 FROM finance_lignes_facture x WHERE x.frais_type_id = t.id',
        ];
        foreach ($defs as $table => $exists) {
            $existe = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = 'etablissement_id'")->fetchColumn();
            if ($existe === 0) { continue; }
            $pdo->exec(
                "DELETE t FROM `$table` t WHERE NOT EXISTS ($exists)
                    AND t.id > (SELECT m.min_id FROM (SELECT code, MIN(id) AS min_id FROM `$table` GROUP BY code) m WHERE m.code = t.code)"
            );
            if ((int)$pdo->query("SELECT COUNT(*) FROM (SELECT code FROM `$table` GROUP BY code HAVING COUNT(*) > 1) d")->fetchColumn() > 0) {
                throw new RuntimeException("T057 rollback refusé : $table contient des codes référencés en plusieurs exemplaires (catalogue partagé entre établissements).");
            }
            $fk = $pdo->query("SELECT constraint_name FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = '$table' AND constraint_type = 'FOREIGN KEY' AND constraint_name LIKE 'fk\\_%\\_etab'")->fetchColumn();
            if ($fk) { $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY `$fk`"); }
            $idx = $pdo->query("SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '$table' AND index_name LIKE 'uq\\_%\\_etab\\_code' LIMIT 1")->fetchColumn();
            $idxEtab = $pdo->query("SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '$table' AND index_name LIKE 'idx\\_%\\_etab' LIMIT 1")->fetchColumn();
            $drop = [];
            if ($idx) { $drop[] = "DROP INDEX `$idx`"; }
            if ($idxEtab) { $drop[] = "DROP INDEX `$idxEtab`"; }
            $drop[] = 'DROP COLUMN etablissement_id';
            $drop[] = 'ADD UNIQUE KEY `code` (code)';
            $pdo->exec("ALTER TABLE `$table` " . implode(', ', $drop));
        }
    },
];
