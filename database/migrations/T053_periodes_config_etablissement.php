<?php

/**
 * T053 — Modèle de génération des périodes : configuration PROPRE à chaque établissement (Lot 3, étape 2)
 *
 * Propriétaire fonctionnel : l'établissement. La table `periodes_scolaires_config` contient le modèle par défaut (noms et dates
 * des périodes à générer). Les lignes actuelles ne sont rattachées à aucun établissement et rien ne démontre qu'elles en
 * appartiennent à un seul : ce sont des GABARITS.
 *
 * Règle : les lignes existantes restent le gabarit (etablissement_id NULL, invisible des établissements) ; chaque établissement
 * existant reçoit SA copie du gabarit (copie du modèle — pas une attribution de propriété). Un établissement créé plus tard reçoit
 * sa copie à la première lecture (PeriodeConfigService), sinon les valeurs de secours du service s'appliquent.
 *
 * Structure : etablissement_id NULLABLE + index + clé étrangère (RESTRICT) ; l'unicité de `numero` devient (etablissement_id, numero).
 * Compatibilité : le code détecte la colonne ; avant la migration la configuration reste partagée. Idempotente.
 * Retour arrière : supprime les copies par établissement, retire clé/index/colonne et rétablit l'unicité de `numero`.
 */

return [
    'id'         => 'T053',
    'name'       => 'Configuration des périodes — copie par établissement',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $colonne = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'periodes_scolaires_config' AND column_name = 'etablissement_id'"
        )->fetchColumn() > 0;

        if (!$colonne) {
            $uniqueNumero = $pdo->query(
                "SELECT index_name FROM information_schema.statistics
                  WHERE table_schema = DATABASE() AND table_name = 'periodes_scolaires_config' AND column_name = 'numero' AND non_unique = 0 AND index_name <> 'PRIMARY' LIMIT 1"
            )->fetchColumn();
            $pdo->exec(
                "ALTER TABLE periodes_scolaires_config
                   ADD COLUMN etablissement_id INT UNSIGNED NULL DEFAULT NULL
                       COMMENT 'NULL = gabarit par défaut' AFTER id,
                   ADD KEY idx_psc_etab (etablissement_id),
                   ADD CONSTRAINT fk_psc_etablissement FOREIGN KEY (etablissement_id) REFERENCES etablissements (id) ON DELETE RESTRICT"
            );
            if ($uniqueNumero) {
                $pdo->exec("ALTER TABLE periodes_scolaires_config DROP INDEX `$uniqueNumero`");
            }
            $pdo->exec('ALTER TABLE periodes_scolaires_config ADD UNIQUE KEY uq_psc_etab_numero (etablissement_id, numero)');
        }

        $cols = array_column($pdo->query(
            "SELECT column_name AS c FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'periodes_scolaires_config' AND column_name NOT IN ('id', 'etablissement_id', 'updated_at', 'updated_by')
              ORDER BY ordinal_position"
        )->fetchAll(PDO::FETCH_ASSOC), 'c');
        $liste = '`' . implode('`, `', $cols) . '`';
        $gabarits = $pdo->query('SELECT id, numero FROM periodes_scolaires_config WHERE etablissement_id IS NULL ORDER BY numero')->fetchAll(PDO::FETCH_ASSOC);
        $etablissements = array_column($pdo->query('SELECT id FROM etablissements WHERE deleted_at IS NULL ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'id');
        $existe = $pdo->prepare('SELECT COUNT(*) FROM periodes_scolaires_config WHERE etablissement_id = ? AND numero = ?');
        $copie = $pdo->prepare("INSERT INTO periodes_scolaires_config (etablissement_id, $liste) SELECT ?, $liste FROM periodes_scolaires_config WHERE id = ?");
        foreach ($etablissements as $e) {
            foreach ($gabarits as $g) {
                $existe->execute([$e, $g['numero']]);
                if ((int)$existe->fetchColumn() === 0) { $copie->execute([$e, $g['id']]); }
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        $colonne = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'periodes_scolaires_config' AND column_name = 'etablissement_id'"
        )->fetchColumn();
        if ($colonne === 0) { return; }
        $pdo->exec('DELETE FROM periodes_scolaires_config WHERE etablissement_id IS NOT NULL');
        $pdo->exec('ALTER TABLE periodes_scolaires_config DROP FOREIGN KEY fk_psc_etablissement');
        $pdo->exec('ALTER TABLE periodes_scolaires_config DROP INDEX uq_psc_etab_numero, DROP INDEX idx_psc_etab, DROP COLUMN etablissement_id, ADD UNIQUE KEY uq_psc_numero (numero)');
    },
];
