<?php

/**
 * T055 — Salles et plages horaires PROPRES à chaque établissement (Lot 3, étape 3)
 *
 * Tables : vs_edt_salles, vs_edt_plages_horaires.
 * Propriétaire fonctionnel : l'établissement (ses salles et ses plages horaires).
 *
 * Ce sont des DONNÉES de l'établissement (et non un catalogue d'application) : aucune copie, aucun gabarit.
 * Règle de rattachement — vérifiable, sans valeur par défaut :
 *   une salle / plage appartient à l'établissement des classes des créneaux (vs_edt_creneaux) qui l'utilisent ;
 *   exactement UN établissement ⇒ rattachée ; AUCUN usage (propriété non démontrée) ou PLUSIEURS établissements ⇒ laissée à NULL et
 *   signalée ([AMBIGU]). Une ligne à NULL est invisible de tous (fail-closed) jusqu'à arbitrage EXPLICITE :
 *   database/tools/lot3_arbitrage.php (liste, puis --appliquer --etab=ID).
 * Structure : etablissement_id NULLABLE + index + clé étrangère (RESTRICT) ; l'unicité de vs_edt_salles.code devient (etablissement_id, code).
 * Compatibilité : le code détecte la colonne ; avant la migration salles et plages restent partagées. Idempotente.
 * Retour arrière : retire clé, index et colonne (aucune ligne supprimée) ; refusé par MySQL si deux établissements ont le même code de salle.
 */

return [
    'id'         => 'T055',
    'name'       => 'Vie scolaire — salles et plages horaires par établissement (rattachement démontré par les créneaux)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $specs = [
            'vs_edt_salles' => ['salle', 'SELECT k.salle_id AS id, c.etablissement_id AS etab FROM vs_edt_creneaux k JOIN classes c ON c.id = k.classe_id WHERE k.salle_id IS NOT NULL', 'code'],
            'vs_edt_plages_horaires' => ['plage', 'SELECT k.plage_id AS id, c.etablissement_id AS etab FROM vs_edt_creneaux k JOIN classes c ON c.id = k.classe_id', null],
        ];
        foreach ($specs as $table => [$court, $sqlUsages, $uniqueCode]) {
            $existe = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = 'etablissement_id'")->fetchColumn() > 0;
            if (!$existe) {
                $pdo->exec(
                    "ALTER TABLE `$table`
                       ADD COLUMN etablissement_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'Établissement propriétaire ; NULL = propriété non démontrée (invisible, à arbitrer)' AFTER id,
                       ADD KEY idx_{$court}_etab (etablissement_id),
                       ADD CONSTRAINT fk_{$court}_etab FOREIGN KEY (etablissement_id) REFERENCES etablissements (id) ON DELETE RESTRICT"
                );
                if ($uniqueCode !== null) {
                    $pdo->exec("ALTER TABLE `$table` DROP INDEX `code`, ADD UNIQUE KEY uq_{$court}_etab_code (etablissement_id, code)");
                }
            }
            $usages = [];
            foreach ($pdo->query($sqlUsages)->fetchAll(PDO::FETCH_ASSOC) as $r) { $usages[(int)$r['id']][] = (int)$r['etab']; }
            $maj = $pdo->prepare("UPDATE `$table` SET etablissement_id = ? WHERE id = ? AND etablissement_id IS NULL");
            $nomCol = $table === 'vs_edt_salles' ? 'nom' : 'libelle';
            foreach ($pdo->query("SELECT id, `$nomCol` AS nom, etablissement_id FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $l) {
                if ($l['etablissement_id'] !== null) { continue; }
                $etabs = array_values(array_unique($usages[(int)$l['id']] ?? []));
                if (count($etabs) === 1) { $maj->execute([$etabs[0], $l['id']]); continue; }
                echo "\n      [AMBIGU] $table #{$l['id']} « {$l['nom']} » : " . (count($etabs) === 0 ? 'aucun créneau' : 'utilisé par ' . count($etabs) . ' établissements') . ' — non rattaché (NULL), à arbitrer';
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        foreach (['vs_edt_salles' => ['salle', 'code'], 'vs_edt_plages_horaires' => ['plage', null]] as $table => [$court, $uniqueCode]) {
            $existe = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = 'etablissement_id'")->fetchColumn();
            if ($existe === 0) { continue; }
            $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY fk_{$court}_etab");
            $parts = [];
            if ($uniqueCode !== null) { $parts[] = "DROP INDEX uq_{$court}_etab_code"; }
            $parts[] = "DROP INDEX idx_{$court}_etab";
            $parts[] = 'DROP COLUMN etablissement_id';
            if ($uniqueCode !== null) { $parts[] = 'ADD UNIQUE KEY `code` (code)'; }
            $pdo->exec("ALTER TABLE `$table` " . implode(', ', $parts));
        }
    },
];
