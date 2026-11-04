<?php

/**
 * T052 — Types d'évaluation : référentiel PROPRE à chaque établissement (Lot 3, étape 2)
 *
 * Propriétaire fonctionnel : l'établissement (chacun gère ses catégories sans qu'un autre puisse les modifier).
 *
 * Règles de rattachement — vérifiables, sans valeur par défaut :
 *   1. Un type UTILISÉ (evaluations.type_evaluation_id) par les classes d'UN SEUL établissement est rattaché à cet établissement.
 *   2. Un type utilisé par PLUSIEURS établissements, ou un type NON système (est_systeme = 0) sans aucun usage : propriété non
 *      démontrée => laissé à NULL et signalé ([AMBIGU]). Une ligne à NULL non système est invisible de tous (fail-closed).
 *   3. Les types SYSTÈME (est_systeme = 1, livrés avec l'application) forment le CATALOGUE : lignes à NULL, invisibles des
 *      établissements, dont chaque code existe une fois. Si le seul exemplaire d'un code est un type utilisé (donc rattaché), un
 *      exemplaire de catalogue en est créé. Le catalogue = lignes sans propriétaire ET non référencées par une évaluation. Chaque établissement existant reçoit ensuite SA copie de chaque type système qui lui
 *      manque (copie du catalogue — ce n'est pas une attribution de propriété). Un établissement créé plus tard reçoit ses
 *      copies à la première utilisation (TypeEvaluationRepository::amorcer).
 *
 * Structure : etablissement_id NULLABLE + index + clé étrangère (ON DELETE RESTRICT) ; l'unicité de `code` devient
 * (etablissement_id, code). Aucune évaluation n'est repointée ni modifiée.
 *
 * Compatibilité : le code détecte la colonne ; avant la migration les types restent partagés. Idempotente.
 * Retour arrière : supprime les types NON référencés sauf le plus ancien de chaque code, retire clé/index/colonne et rétablit
 * l'unicité de `code`.
 */

return [
    'id'         => 'T052',
    'name'       => 'Types d\'évaluation — colonne etablissement_id, catalogue système et rattachement démontré',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $colonne = static fn(): bool => (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'types_evaluations' AND column_name = 'etablissement_id'"
        )->fetchColumn() > 0;

        if (!$colonne()) {
            $pdo->exec(
                "ALTER TABLE types_evaluations
                   ADD COLUMN etablissement_id INT UNSIGNED NULL DEFAULT NULL
                       COMMENT 'Établissement propriétaire ; NULL = catalogue système ou propriété non démontrée' AFTER id,
                   ADD KEY idx_te_etab (etablissement_id),
                   ADD CONSTRAINT fk_te_etablissement FOREIGN KEY (etablissement_id) REFERENCES etablissements (id) ON DELETE RESTRICT"
            );
            $pdo->exec('ALTER TABLE types_evaluations DROP INDEX uq_te_code, ADD UNIQUE KEY uq_te_etab_code (etablissement_id, code)');
        }

        // Colonnes copiables (tout sauf id et etablissement_id)
        $cols = array_column($pdo->query(
            "SELECT column_name AS c FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'types_evaluations' AND column_name NOT IN ('id', 'etablissement_id')
              ORDER BY ordinal_position"
        )->fetchAll(PDO::FETCH_ASSOC), 'c');
        $liste = '`' . implode('`, `', $cols) . '`';
        $copier = static function (int $sourceId, ?int $etab) use ($pdo, $liste): int {
            $pdo->prepare("INSERT INTO types_evaluations (etablissement_id, $liste) SELECT ?, $liste FROM types_evaluations WHERE id = ?")->execute([$etab, $sourceId]);
            return (int)$pdo->lastInsertId();
        };

        // Établissements utilisateurs de chaque type (via les classes des évaluations)
        $usages = [];
        foreach ($pdo->query(
            'SELECT ev.type_evaluation_id AS t, c.etablissement_id AS e FROM evaluations ev JOIN classes c ON c.id = ev.classe_id GROUP BY ev.type_evaluation_id, c.etablissement_id'
        )->fetchAll(PDO::FETCH_ASSOC) as $r) { $usages[(int)$r['t']][] = (int)$r['e']; }

        $types = $pdo->query('SELECT id, code, nom, est_systeme, etablissement_id FROM types_evaluations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

        // 3) catalogue système : un exemplaire NON rattaché par code
        $catalogue = [];
        foreach ($types as $t) {
            if ((int)$t['est_systeme'] !== 1 || $t['etablissement_id'] !== null) { continue; }
            $utilise = isset($usages[(int)$t['id']]);
            if (!$utilise && !isset($catalogue[$t['code']])) { $catalogue[$t['code']] = (int)$t['id']; }   // inutilisé : il EST le gabarit
        }
        foreach ($types as $t) {
            if ((int)$t['est_systeme'] !== 1 || $t['etablissement_id'] !== null || isset($catalogue[$t['code']])) { continue; }
            $catalogue[$t['code']] = $copier((int)$t['id'], null);   // seul exemplaire = type utilisé : gabarit créé en copie
        }

        // 1-2) rattachement des types utilisés / signalement des ambiguïtés
        $rattache = $pdo->prepare('UPDATE types_evaluations SET etablissement_id = ?, updated_at = updated_at WHERE id = ? AND etablissement_id IS NULL');
        foreach ($types as $t) {
            $id = (int)$t['id'];
            if ($t['etablissement_id'] !== null || in_array($id, $catalogue, true)) { continue; }
            $etabs = array_values(array_unique($usages[$id] ?? []));
            if (count($etabs) === 1) { $rattache->execute([$etabs[0], $id]); continue; }
            echo "\n      [AMBIGU] type #{$id} « {$t['code']} » : "
                . (count($etabs) === 0 ? 'non système et sans usage' : 'utilisé par ' . count($etabs) . ' établissements')
                . ' — non rattaché (NULL), à arbitrer';
        }

        // 3) chaque établissement existant reçoit sa copie des types système qui lui manquent
        $etablissements = array_column($pdo->query('SELECT id FROM etablissements WHERE deleted_at IS NULL ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'id');
        $a = $pdo->prepare('SELECT COUNT(*) FROM types_evaluations WHERE etablissement_id = ? AND code = ?');
        foreach ($etablissements as $e) {
            foreach ($catalogue as $code => $gabarit) {
                $a->execute([$e, $code]);
                if ((int)$a->fetchColumn() === 0) { $copier($gabarit, (int)$e); }
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        $colonne = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'types_evaluations' AND column_name = 'etablissement_id'"
        )->fetchColumn();
        if ($colonne === 0) { return; }
        // Supprime les copies non référencées ; garde le plus ancien exemplaire de chaque code (état d'origine)
        $pdo->exec(
            "DELETE t FROM types_evaluations t
              WHERE NOT EXISTS (SELECT 1 FROM evaluations ev WHERE ev.type_evaluation_id = t.id)
                AND t.id > (SELECT m.min_id FROM (SELECT code, MIN(id) AS min_id FROM types_evaluations GROUP BY code) m WHERE m.code = t.code)"
        );
        $doublons = (int)$pdo->query('SELECT COUNT(*) FROM (SELECT code FROM types_evaluations GROUP BY code HAVING COUNT(*) > 1) d')->fetchColumn();
        if ($doublons > 0) { throw new RuntimeException('T052 rollback refusé : des codes de type référencés existent en plusieurs exemplaires (types partagés entre établissements).'); }
        $pdo->exec('ALTER TABLE types_evaluations DROP FOREIGN KEY fk_te_etablissement');
        $pdo->exec('ALTER TABLE types_evaluations DROP INDEX uq_te_etab_code, DROP INDEX idx_te_etab, DROP COLUMN etablissement_id, ADD UNIQUE KEY uq_te_code (code)');
    },
];
