<?php

/**
 * T048 — Identification de la matière de Conduite (`matieres.est_conduite`)
 *
 * La Conduite est une note de comportement, pas une matière d'enseignement : le code ne doit plus la
 * reconnaître par son libellé. Cette migration :
 *   1. ajoute `est_conduite TINYINT UNSIGNED NULL DEFAULT NULL` ;
 *   2. ajoute l'index unique (etablissement_id, est_conduite) : NULL pour les matières ordinaires (plusieurs NULL
 *      admis), 1 pour la Conduite => AU PLUS UNE Conduite par établissement ;
 *   3. marque comme Conduite la matière nommée « Conduite » de chaque établissement qui n'en possède qu'UNE seule
 *      (ambiguïté => aucun marquage, migration en échec explicite) et fixe son volume horaire à 0.
 *      Le coefficient n'est jamais modifié ici ; s'il n'est pas 1, la migration s'arrête (décision métier requise).
 *
 * Aucune suppression, aucune note, évaluation ni bulletin touché. Idempotente. Réversible :
 * le retour arrière doit se faire APRÈS celui de T050 (le déclencheur refuse de retirer le marqueur).
 */

return [
    'id'         => 'T048',
    'name'       => 'Matières — marqueur est_conduite (identification de la Conduite)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $colonne = static fn(): bool => (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'matieres' AND column_name = 'est_conduite'"
        )->fetchColumn() > 0;
        $index = static fn(): bool => (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = 'matieres' AND index_name = 'uq_matiere_conduite'"
        )->fetchColumn() > 0;

        // Contrôle préalable (avant toute écriture) : une seule Conduite par établissement, coefficient 1.
        $doublons = $pdo->query(
            "SELECT etablissement_id, COUNT(*) AS n FROM matieres WHERE LOWER(nom) = 'conduite'
              GROUP BY etablissement_id HAVING COUNT(*) > 1"
        )->fetchAll(PDO::FETCH_ASSOC);
        if ($doublons) {
            throw new RuntimeException('T048 : plusieurs matières « Conduite » dans un même établissement ('
                . json_encode($doublons) . ') — arbitrage métier requis, aucune écriture effectuée.');
        }
        $incoherentes = $pdo->query(
            "SELECT id, coefficient FROM matieres WHERE LOWER(nom) = 'conduite' AND coefficient <> 1"
        )->fetchAll(PDO::FETCH_ASSOC);
        if ($incoherentes) {
            throw new RuntimeException('T048 : coefficient de Conduite différent de 1 (' . json_encode($incoherentes)
                . ') — décision métier requise, aucune écriture effectuée.');
        }

        if (!$colonne()) {
            $pdo->exec("ALTER TABLE matieres ADD COLUMN est_conduite TINYINT UNSIGNED NULL DEFAULT NULL
                        COMMENT '1 = matière de Conduite (note de comportement), NULL = matière ordinaire' AFTER categorie");
        }
        if (!$index()) {
            $pdo->exec("ALTER TABLE matieres ADD UNIQUE KEY uq_matiere_conduite (etablissement_id, est_conduite)");
        }

        // Marquage : jamais une matière déjà marquée, jamais une seconde dans le même établissement.
        $pdo->exec(
            "UPDATE matieres SET est_conduite = 1, volume_horaire = 0
              WHERE LOWER(nom) = 'conduite' AND est_conduite IS NULL"
        );
    },

    'rollback' => function (PDO $pdo): void {
        $declencheurs = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.triggers
              WHERE trigger_schema = DATABASE() AND event_object_table = 'matieres' AND trigger_name LIKE 'trg_matieres_conduite_%'"
        )->fetchColumn();
        if ($declencheurs > 0) {
            throw new RuntimeException('T048 rollback refusé : annuler T050 (déclencheurs de protection) avant T048.');
        }
        $index = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = 'matieres' AND index_name = 'uq_matiere_conduite'"
        )->fetchColumn();
        if ($index > 0) { $pdo->exec("ALTER TABLE matieres DROP INDEX uq_matiere_conduite"); }
        $col = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'matieres' AND column_name = 'est_conduite'"
        )->fetchColumn();
        if ($col > 0) {
            // Le volume horaire de la Conduite est rétabli à sa valeur d'origine (2 h) avant retrait du marqueur.
            $pdo->exec("UPDATE matieres SET volume_horaire = 2 WHERE est_conduite = 1 AND volume_horaire = 0");
            $pdo->exec("ALTER TABLE matieres DROP COLUMN est_conduite");
        }
    },
];
