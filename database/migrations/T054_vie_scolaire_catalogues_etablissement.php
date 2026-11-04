<?php

/**
 * T054 — Catalogues de la Vie scolaire PROPRES à chaque établissement (Lot 3, étape 3)
 *
 * Tables : vs_motifs_absence, vs_discipline_categories, vs_recompense_categories, vs_activite_categories.
 * Propriétaire fonctionnel : l'établissement (chacun gère ses motifs et catégories sans qu'un autre puisse les modifier).
 *
 * Ce sont des CATALOGUES livrés par les migrations de l'application, pas des données saisies par un établissement. Règles — vérifiables,
 * sans valeur par défaut :
 *   1. Une ligne UTILISÉE (par des justifications, incidents, récompenses ou activités) par les classes / utilisateurs d'UN SEUL
 *      établissement est rattachée à cet établissement.
 *   2. Une ligne utilisée par PLUSIEURS établissements est laissée à NULL et signalée ([AMBIGU]).
 *   3. Le CATALOGUE est formé des lignes sans propriétaire et NON référencées, une par code. Si le seul exemplaire d'un code est une
 *      ligne utilisée, un exemplaire de catalogue en est créé. Chaque établissement existant reçoit ensuite SA copie de chaque code
 *      qui lui manque (copie du catalogue — pas une attribution de propriété). Un établissement créé plus tard reçoit ses copies à
 *      la première utilisation (Core\Tenant\Catalogue::amorcer).
 * Structure : etablissement_id NULLABLE + index + clé étrangère (RESTRICT) ; l'unicité de `code` devient (etablissement_id, code).
 * Compatibilité : le code détecte la colonne ; avant la migration les catalogues restent partagés. Idempotente.
 * Retour arrière : supprime les copies non référencées (garde le plus ancien exemplaire de chaque code), rétablit l'unicité de `code`.
 */

return [
    'id'         => 'T054',
    'name'       => 'Vie scolaire — catalogues par établissement (motifs, catégories) : rattachement démontré et copies',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $catalogues = [
            // table => [index unique d'origine, SQL des usages (id, etab), SQL des références (id)]
            'vs_motifs_absence' => ['code',
                'SELECT ja.motif_id AS id, c.etablissement_id AS etab FROM vs_justifications_absences ja JOIN vs_absences a ON a.id = ja.absence_id JOIN classes c ON c.id = a.classe_id WHERE ja.motif_id IS NOT NULL',
                'SELECT DISTINCT motif_id AS id FROM vs_justifications_absences WHERE motif_id IS NOT NULL'],
            'vs_discipline_categories' => ['uk_categorie_code',
                'SELECT i.categorie_id AS id, c.etablissement_id AS etab FROM vs_incidents_discipline i JOIN vs_dossiers_discipline d ON d.id = i.dossier_id JOIN classes c ON c.id = d.classe_id',
                'SELECT DISTINCT categorie_id AS id FROM vs_incidents_discipline'],
            'vs_recompense_categories' => ['code',
                'SELECT r.categorie_id AS id, c.etablissement_id AS etab FROM vs_recompenses r JOIN classes c ON c.id = r.classe_id',
                'SELECT DISTINCT categorie_id AS id FROM vs_recompenses'],
            'vs_activite_categories' => ['uq_activite_cat_code',
                'SELECT a.categorie_id AS id, u.etablissement_id AS etab FROM vs_activites a JOIN users u ON u.id = a.cree_par',
                'SELECT DISTINCT categorie_id AS id FROM vs_activites'],
        ];
        $etablissements = array_column($pdo->query('SELECT id FROM etablissements WHERE deleted_at IS NULL ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'id');

        foreach ($catalogues as $table => [$uniqueOrigine, $sqlUsages, $sqlRefs]) {
            $existe = (int)$pdo->query(
                "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = 'etablissement_id'"
            )->fetchColumn() > 0;
            if (!$existe) {
                $court = substr(preg_replace('/[^a-z]/', '', str_replace('vs_', '', $table)), 0, 8);
                $pdo->exec(
                    "ALTER TABLE `$table`
                       ADD COLUMN etablissement_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = catalogue ou propriété non démontrée' AFTER id,
                       ADD KEY idx_{$court}_etab (etablissement_id),
                       ADD CONSTRAINT fk_{$court}_etab FOREIGN KEY (etablissement_id) REFERENCES etablissements (id) ON DELETE RESTRICT"
                );
                $pdo->exec("ALTER TABLE `$table` DROP INDEX `$uniqueOrigine`, ADD UNIQUE KEY uq_{$court}_etab_code (etablissement_id, code)");
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
            'vs_motifs_absence' => ['code', 'SELECT 1 FROM vs_justifications_absences x WHERE x.motif_id = t.id'],
            'vs_discipline_categories' => ['uk_categorie_code', 'SELECT 1 FROM vs_incidents_discipline x WHERE x.categorie_id = t.id'],
            'vs_recompense_categories' => ['code', 'SELECT 1 FROM vs_recompenses x WHERE x.categorie_id = t.id'],
            'vs_activite_categories' => ['uq_activite_cat_code', 'SELECT 1 FROM vs_activites x WHERE x.categorie_id = t.id'],
        ];
        foreach ($defs as $table => [$uniqueOrigine, $exists]) {
            $existe = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = 'etablissement_id'")->fetchColumn();
            if ($existe === 0) { continue; }
            $pdo->exec(
                "DELETE t FROM `$table` t WHERE NOT EXISTS ($exists)
                    AND t.id > (SELECT m.min_id FROM (SELECT code, MIN(id) AS min_id FROM `$table` GROUP BY code) m WHERE m.code = t.code)"
            );
            if ((int)$pdo->query("SELECT COUNT(*) FROM (SELECT code FROM `$table` GROUP BY code HAVING COUNT(*) > 1) d")->fetchColumn() > 0) {
                throw new RuntimeException("T054 rollback refusé : $table contient des codes référencés en plusieurs exemplaires (catalogue partagé entre établissements).");
            }
            $fk = $pdo->query("SELECT constraint_name FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = '$table' AND constraint_type = 'FOREIGN KEY' AND constraint_name LIKE 'fk\\_%\\_etab'")->fetchColumn();
            if ($fk) { $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY `$fk`"); }
            $idx = $pdo->query("SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '$table' AND index_name LIKE 'uq\\_%\\_etab\\_code' LIMIT 1")->fetchColumn();
            $idxEtab = $pdo->query("SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '$table' AND index_name LIKE 'idx\\_%\\_etab' LIMIT 1")->fetchColumn();
            $drop = [];
            if ($idx) { $drop[] = "DROP INDEX `$idx`"; }
            if ($idxEtab) { $drop[] = "DROP INDEX `$idxEtab`"; }
            $drop[] = 'DROP COLUMN etablissement_id';
            $drop[] = $uniqueOrigine === 'code' ? 'ADD UNIQUE KEY `code` (code)' : "ADD UNIQUE KEY `$uniqueOrigine` (code)";
            $pdo->exec("ALTER TABLE `$table` " . implode(', ', $drop));
        }
    },
];
