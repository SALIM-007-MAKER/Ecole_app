<?php

/**
 * T051 — Périodes scolaires PROPRES à chaque établissement (Lot 3, étape 2)
 *
 * Propriétaire fonctionnel : l'établissement (calendrier scolaire et période active gérés indépendamment).
 *
 * Règle de rattachement des lignes existantes — vérifiable, sans valeur par défaut :
 *   une période appartient à l'établissement des classes qui l'utilisent, c'est-à-dire l'ensemble des établissements des classes des
 *   évaluations (evaluations.periode_scolaire_id) ET des bulletins (bulletins_v2.periode_id) qui la référencent.
 *     - exactement UN établissement  => rattachée à cet établissement ;
 *     - AUCUN usage (propriété non démontrée) ou PLUSIEURS établissements (période partagée) => LAISSÉE à NULL et signalée
 *       ([AMBIGU] dans la sortie). Une période à NULL est invisible de tous (fail-closed) jusqu'à arbitrage explicite.
 *   Aucun remplissage vers « l'établissement 1 ».
 *
 * Structure : colonne etablissement_id NULLABLE (les lignes ambiguës restent NULL), index et clé étrangère vers etablissements
 * (ON DELETE RESTRICT). L'unicité (annee_scolaire, type_periode, numero) devient (etablissement_id, annee_scolaire, type_periode,
 * numero) : deux établissements ont chacun leur « Premier semestre ». Toute création applicative renseigne la colonne.
 *
 * Compatibilité : le code détecte la colonne (Core\Tenant\TenantScope) ; avant cette migration les périodes restent partagées.
 * Idempotente. Retour arrière : retire la clé étrangère, l'index et la colonne (aucune donnée de période n'est supprimée).
 */

return [
    'id'         => 'T051',
    'name'       => 'Périodes scolaires — colonne etablissement_id et rattachement démontré',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $existe = static fn(): bool => (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'periodes_scolaires' AND column_name = 'etablissement_id'"
        )->fetchColumn() > 0;

        if (!$existe()) {
            $pdo->exec(
                "ALTER TABLE periodes_scolaires
                   ADD COLUMN etablissement_id INT UNSIGNED NULL DEFAULT NULL
                       COMMENT 'Établissement propriétaire ; NULL = propriété non démontrée (invisible, à arbitrer)' AFTER id,
                   ADD KEY idx_ps_etab (etablissement_id),
                   ADD CONSTRAINT fk_ps_etablissement FOREIGN KEY (etablissement_id) REFERENCES etablissements (id) ON DELETE RESTRICT"
            );
            $pdo->exec('ALTER TABLE periodes_scolaires DROP INDEX uq_ps_annee_type_num, ADD UNIQUE KEY uq_ps_etab_annee_type_num (etablissement_id, annee_scolaire, type_periode, numero)');
        }

        // Établissements utilisateurs de chaque période, via les classes des évaluations et des bulletins
        $usages = [];
        $sql = "SELECT periode, etab FROM (
                    SELECT ev.periode_scolaire_id AS periode, c.etablissement_id AS etab FROM evaluations ev JOIN classes c ON c.id = ev.classe_id
                    UNION
                    SELECT b.periode_id AS periode, c.etablissement_id AS etab FROM bulletins_v2 b JOIN classes c ON c.id = b.classe_id
                ) u GROUP BY periode, etab";
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) { $usages[(int)$r['periode']][] = (int)$r['etab']; }

        $maj = $pdo->prepare('UPDATE periodes_scolaires SET etablissement_id = ?, updated_at = updated_at WHERE id = ? AND etablissement_id IS NULL');
        foreach ($pdo->query('SELECT id, nom, annee_scolaire, etablissement_id FROM periodes_scolaires ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $p) {
            if ($p['etablissement_id'] !== null) { continue; }
            $etabs = array_values(array_unique($usages[(int)$p['id']] ?? []));
            if (count($etabs) === 1) {
                $maj->execute([$etabs[0], $p['id']]);
            } else {
                echo "\n      [AMBIGU] période #{$p['id']} « {$p['nom']} » {$p['annee_scolaire']} : "
                    . (count($etabs) === 0 ? 'aucun usage' : 'utilisée par ' . count($etabs) . ' établissements')
                    . ' — non rattachée (NULL), à arbitrer';
            }
        }
    },

    'rollback' => function (PDO $pdo): void {
        $fk = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.table_constraints
              WHERE table_schema = DATABASE() AND table_name = 'periodes_scolaires' AND constraint_name = 'fk_ps_etablissement'"
        )->fetchColumn();
        if ($fk > 0) { $pdo->exec('ALTER TABLE periodes_scolaires DROP FOREIGN KEY fk_ps_etablissement'); }
        $col = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'periodes_scolaires' AND column_name = 'etablissement_id'"
        )->fetchColumn();
        if ($col > 0) {
            $pdo->exec('ALTER TABLE periodes_scolaires DROP INDEX uq_ps_etab_annee_type_num, DROP COLUMN etablissement_id, ADD UNIQUE KEY uq_ps_annee_type_num (annee_scolaire, type_periode, numero)');   // refusé par MySQL si deux établissements ont la même période
        }
    },
];
