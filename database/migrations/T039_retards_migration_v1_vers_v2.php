<?php

/**
 * T039 — Dernier reliquat de la convergence Absences (T037) : les 2 lignes
 * V1 de type `retard` n'avaient pas été migrées à l'époque — elles
 * chevauchaient le domaine Retards (`vs_retards`), déjà le seul système
 * branché dans les menus, et le sujet avait été volontairement mis de côté
 * (voir CHANGELOG.md, entrée du 20-21/08/2026).
 *
 * Contrairement à `vs_absences`, `vs_retards.heure_arrivee` est NOT NULL —
 * une heure d'horloge, alors que V1 ne stockait qu'une session
 * ('matin'/'apres_midi'/'journee') et une durée de retard en minutes.
 * Reconstruction : heure_prevue = heure de début du premier créneau de la
 * session concernée (référentiel réel migré en T038 — "Cours 1" = 08:00
 * pour la session 'matin') ; heure_arrivee = heure_prevue + durée V1.
 * Approximation raisonnable, pas une donnée observée — à corriger à la main
 * sur les 2 lignes concernées si l'heure exacte est encore connue.
 *
 * Idempotente : n'insère que si `vs_retards` est vide (les 2 seules lignes
 * concernées sont connues et ne grossiront pas).
 */

return [
    'id'         => 'T039',
    'name'       => 'Retards — reliquat V1 (type=retard, table absences) vers vs_retards V2',
    'reversible' => false,

    'run' => function (PDO $pdo): void {
        $exists = (bool)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'absences'"
        )->fetchColumn();
        if (!$exists) {
            return;
        }

        $dejaMigre = (int)$pdo->query("SELECT COUNT(*) FROM vs_retards")->fetchColumn();
        if ($dejaMigre > 0) {
            error_log('[T039] vs_retards non vide — reliquat V1 laissé de côté (migration manuelle requise).');
            return;
        }

        $rows = $pdo->query(
            "SELECT b.id, b.eleve_id, b.classe_id, b.date_absence, b.session,
                    b.duree_retard, b.motif, b.signale_par, b.statut_justif, b.created_at,
                    c.annee_scolaire
             FROM `absences` b
             JOIN `classes` c ON c.id = b.classe_id
             WHERE b.type = 'retard'"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return;
        }

        // Heure de début du premier créneau par session — référentiel réel migré en T038.
        $heurePrevueParSession = [
            'matin'       => '08:00:00',
            'apres_midi'  => '13:30:00',
            'journee'     => '08:00:00',
        ];

        $statutMap = [
            'non_justifiee' => 'non_justifie',
            'en_attente'    => 'en_attente',
            'justifiee'     => 'justifie',
            'refusee'       => 'refuse',
        ];

        $insert = $pdo->prepare(
            "INSERT INTO vs_retards
                (eleve_id, classe_id, annee_scolaire, date_retard, heure_prevue, heure_arrivee,
                 duree_minutes, statut, saisie_par, observation, created_at)
             VALUES
                (:eleve_id, :classe_id, :annee_scolaire, :date_retard, :heure_prevue, :heure_arrivee,
                 :duree_minutes, :statut, :saisie_par, :observation, :created_at)"
        );

        $migres = 0;
        foreach ($rows as $row) {
            $duree       = max(1, (int)($row['duree_retard'] ?: 15));
            $heurePrevue = $heurePrevueParSession[$row['session']] ?? '08:00:00';
            $heureArrivee = date('H:i:s', strtotime($heurePrevue) + $duree * 60);
            $statut      = $statutMap[$row['statut_justif']] ?? 'non_justifie';
            $saisiePar   = (int)($row['signale_par'] ?: 1);

            $insert->execute([
                ':eleve_id'       => $row['eleve_id'],
                ':classe_id'      => $row['classe_id'],
                ':annee_scolaire' => $row['annee_scolaire'],
                ':date_retard'    => $row['date_absence'],
                ':heure_prevue'   => $heurePrevue,
                ':heure_arrivee'  => $heureArrivee,
                ':duree_minutes'  => $duree,
                ':statut'         => $statut,
                ':saisie_par'     => $saisiePar,
                ':observation'    => $row['motif'] !== null
                    ? $row['motif'] . ' (migré depuis absences V1 id=' . $row['id'] . ', heure reconstituée)'
                    : 'Migré depuis absences V1 id=' . $row['id'] . ' — heure reconstituée, non observée',
                ':created_at'     => $row['created_at'],
            ]);
            $migres++;
        }

        error_log("[T039] {$migres} retard(s) migré(s) de absences (V1) vers vs_retards (V2).");
    },
];
