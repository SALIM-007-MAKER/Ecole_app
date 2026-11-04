<?php

/**
 * T037 — Convergence des absences : migration des lignes V1 (`absences`)
 * vers le schéma V2 (`vs_absences`), suite à l'audit du 20/08/2026.
 *
 * Contexte : `absences` (V1) est utilisé par TOUTE l'interface (menus des
 * 6 rôles, tableau de bord, rapports, espaces parent/élève), tandis que
 * `vs_absences` (V2, module Vie Scolaire) n'était jusqu'ici exposé que par
 * l'API publique `/api/v1/absences` — deux tables séparées, aucun lien entre
 * elles. Décision : converger vers V2 (architecture plus propre : events,
 * policy, workflow de justification dédié), déjà la cible de l'API.
 *
 * Cette migration copie les lignes V1 de type `absence` vers `vs_absences`,
 * avec une justification correspondante (`vs_justifications_absences`) pour
 * celles déjà validées. Le code applicatif (menus, contrôleurs, rapports,
 * tableau de bord) est rebranché sur V2 dans le même changement — voir
 * MenuService.php, RapportModel.php, HomeController.php, EspaceEleveController.php,
 * ParentController.php.
 *
 * Hors périmètre : les lignes V1 de type `retard` ne sont PAS migrées ici —
 * elles chevauchent le domaine Retards (`vs_retards`), déjà branché
 * indépendamment dans tous les menus. C'est un problème cousin distinct,
 * volontairement laissé de côté (voir note dans le rapport de migration).
 *
 * La table `absences` (V1) n'est PAS supprimée par cette migration — elle
 * reste en base, non référencée par le code, comme trace d'audit.
 *
 * Idempotente : n'insère que les lignes absentes de vs_absences (comparaison
 * eleve_id + classe_id + date_absence).
 */

return [
    'id'         => 'T037',
    'name'       => 'Absences — migration des données V1 (type=absence) vers vs_absences V2',
    'reversible' => false,

    'run' => function (PDO $pdo): void {
        $exists = (bool)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'absences'"
        )->fetchColumn();
        if (!$exists) {
            return; // Rien à migrer (table V1 déjà absente).
        }

        $rows = $pdo->query(
            "SELECT b.id, b.eleve_id, b.classe_id, b.date_absence, b.motif,
                    b.signale_par, b.statut_justif, b.created_at,
                    c.annee_scolaire
             FROM `absences` b
             JOIN `classes` c ON c.id = b.classe_id
             WHERE b.type = 'absence'"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return;
        }

        $checkStmt = $pdo->prepare(
            "SELECT id FROM vs_absences
             WHERE eleve_id = :eleve_id AND classe_id = :classe_id AND date_absence = :date_absence
             LIMIT 1"
        );
        $insertStmt = $pdo->prepare(
            "INSERT INTO vs_absences
                (eleve_id, classe_id, annee_scolaire, date_absence, type, statut,
                 saisie_par, observation, created_at)
             VALUES
                (:eleve_id, :classe_id, :annee_scolaire, :date_absence, 'absence', :statut,
                 :saisie_par, :observation, :created_at)"
        );
        $justifStmt = $pdo->prepare(
            "INSERT INTO vs_justifications_absences
                (absence_id, description, statut, soumis_par, soumis_le, valide_par, valide_le)
             VALUES
                (:absence_id, :description, 'validee', :soumis_par, :soumis_le, :valide_par, :valide_le)"
        );

        $migrees = 0;
        foreach ($rows as $row) {
            $checkStmt->execute([
                ':eleve_id'     => $row['eleve_id'],
                ':classe_id'    => $row['classe_id'],
                ':date_absence' => $row['date_absence'],
            ]);
            if ($checkStmt->fetchColumn()) {
                continue; // déjà migrée (idempotence)
            }

            $statut    = $row['statut_justif'] ?: 'non_justifiee';
            $saisiePar = (int)($row['signale_par'] ?: 1);

            $insertStmt->execute([
                ':eleve_id'       => $row['eleve_id'],
                ':classe_id'      => $row['classe_id'],
                ':annee_scolaire' => $row['annee_scolaire'],
                ':date_absence'   => $row['date_absence'],
                ':statut'         => $statut,
                ':saisie_par'     => $saisiePar,
                ':observation'    => $row['motif'],
                ':created_at'     => $row['created_at'],
            ]);
            $newId = (int)$pdo->lastInsertId();
            $migrees++;

            if ($statut === 'justifiee') {
                $justifStmt->execute([
                    ':absence_id'  => $newId,
                    ':description' => $row['motif'] ?: ('Justification migrée depuis absences V1 (id=' . $row['id'] . ')'),
                    ':soumis_par'  => $saisiePar,
                    ':soumis_le'   => $row['created_at'],
                    ':valide_par'  => $saisiePar,
                    ':valide_le'   => $row['created_at'],
                ]);
            }
        }

        error_log("[T037] {$migrees} absence(s) migrée(s) de absences (V1) vers vs_absences (V2).");
    },
];
