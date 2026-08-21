<?php

/**
 * T040 — Correction d'une justification migrée par erreur en placeholder.
 *
 * La migration T037 (absences V1 → V2) a reconstruit les justifications à
 * partir de `absences.statut_justif`/`absences.motif` uniquement — sans
 * savoir qu'une table V1 séparée, `justifications` (utilisée par l'ancien
 * App\Models\JustificationModel, supprimé depuis), contenait le vrai motif
 * soumis par la famille. Trouvée lors de la préparation du DROP de cette
 * table (T041) : `justifications.id=1` (absence_id=7 V1) porte un motif réel
 * ("Maladie – certificat médical fourni", soumis par l'utilisateur 6) alors
 * que `vs_justifications_absences.id=1` (absence_id=6, la même ligne migrée)
 * ne contient que le texte générique "Justification migrée...".
 *
 * Corrige cette unique ligne avec la donnée réelle avant que la table V1 ne
 * disparaisse. Idempotente : ne modifie que si la description est encore le
 * texte générique.
 */

return [
    'id'         => 'T040',
    'name'       => 'Correction justification absence migrée (donnée réelle depuis justifications V1)',
    'reversible' => false,

    'run' => function (PDO $pdo): void {
        $exists = (bool)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'justifications'"
        )->fetchColumn();
        if (!$exists) {
            return;
        }

        $rows = $pdo->query(
            "SELECT j.absence_id AS absence_id_v1, j.motif, j.soumis_par, j.statut, j.created_at
             FROM `justifications` j"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return;
        }

        $motifV1 = $pdo->prepare("SELECT id FROM vs_motifs_absence WHERE code = :code LIMIT 1");

        $update = $pdo->prepare(
            "UPDATE vs_justifications_absences
             SET description = :description, soumis_par = :soumis_par, motif_id = :motif_id
             WHERE absence_id = :absence_id AND description LIKE 'Justification migrée depuis absences V1%'"
        );

        $corrigees = 0;
        foreach ($rows as $row) {
            // Retrouver l'id vs_absences correspondant via la trace laissée par T037
            // dans la description générique.
            $like = $pdo->prepare(
                "SELECT absence_id FROM vs_justifications_absences
                 WHERE description LIKE :pattern LIMIT 1"
            );
            $like->execute([':pattern' => '%(id=' . (int)$row['absence_id_v1'] . ')%']);
            $vsAbsenceId = $like->fetchColumn();
            if (!$vsAbsenceId) {
                continue; // Rien à corriger — pas de trace du placeholder T037
            }

            $motifId = null;
            if (stripos($row['motif'], 'maladie') !== false) {
                $motifV1->execute([':code' => 'MALADIE']);
                $motifId = $motifV1->fetchColumn() ?: null;
            }

            $update->execute([
                ':description' => $row['motif'],
                ':soumis_par'  => $row['soumis_par'] ?: 1,
                ':motif_id'    => $motifId,
                ':absence_id'  => $vsAbsenceId,
            ]);
            if ($update->rowCount() > 0) {
                $corrigees++;
            }
        }

        error_log("[T040] {$corrigees} justification(s) corrigée(s) avec la donnée réelle V1.");
    },
];
