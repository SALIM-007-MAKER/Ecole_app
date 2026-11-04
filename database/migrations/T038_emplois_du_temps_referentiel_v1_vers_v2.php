<?php

/**
 * T038 — Convergence du référentiel Emplois du temps : migre les salles et
 * plages horaires réelles (V1) vers le module Vie Scolaire V2, suite au même
 * constat que pour les absences (T037) : le menu Planning pointait vers
 * `/emplois-du-temps` (V1, tables `emplois_du_temps`/`salles`/`creneaux`,
 * toutes vides ou en doublon) pendant que le module V2
 * (`vs_emplois_du_temps` / `vs_edt_*`) restait invisible.
 *
 * Contrairement aux absences, aucune grille horaire n'existait encore nulle
 * part (`emplois_du_temps` V1 et `vs_emplois_du_temps` V2 : 0 ligne chacune)
 * — seul le référentiel (salles, créneaux) contenait de vraies données V1 :
 *
 *   - `salles` (8 lignes réelles)      → `vs_edt_salles` (vide, sans code)
 *   - `creneaux` (11 lignes réelles,
 *     avec récréations/pauses)         → `vs_edt_plages_horaires`
 *                                         (9 lignes, données de démo
 *                                         génériques 7h30→18h sans pause,
 *                                         jamais référencées par une grille
 *                                         réelle — remplacées, pas fusionnées)
 *
 * Idempotente : ne migre les salles que si `vs_edt_salles` est vide ; ne
 * remplace les plages que si aucune n'est encore référencée par un créneau
 * réel (vs_edt_creneaux vide au moment de la migration).
 */

return [
    'id'         => 'T038',
    'name'       => 'Emplois du temps — référentiel salles/créneaux V1 vers V2',
    'reversible' => false,

    'run' => function (PDO $pdo): void {

        // ── Salles ────────────────────────────────────────────────────────────
        $salleCount = (int)$pdo->query("SELECT COUNT(*) FROM vs_edt_salles")->fetchColumn();
        if ($salleCount === 0) {
            $salles = $pdo->query("SELECT * FROM `salles`")->fetchAll(PDO::FETCH_ASSOC);

            $typeMap = [
                'salle_cours'  => 'cours',
                'laboratoire'  => 'labo',
                'salle_info'   => 'informatique',
                'amphitheatre' => 'autre',
            ];

            $insert = $pdo->prepare(
                "INSERT INTO vs_edt_salles (nom, code, capacite, type, actif)
                 VALUES (:nom, :code, :capacite, :type, :actif)"
            );
            $usedCodes = [];
            foreach ($salles as $s) {
                $base = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $s['nom']));
                $base = $base !== '' ? substr($base, 0, 15) : 'SALLE';
                $code = $base;
                $i = 1;
                while (isset($usedCodes[$code])) {
                    $code = $base . $i;
                    $i++;
                }
                $usedCodes[$code] = true;

                $insert->execute([
                    ':nom'      => $s['nom'],
                    ':code'     => $code,
                    ':capacite' => $s['capacite'] ?: 30,
                    ':type'     => $typeMap[$s['type']] ?? 'autre',
                    ':actif'    => $s['actif'],
                ]);
            }
            error_log('[T038] ' . count($salles) . ' salle(s) migrée(s) vers vs_edt_salles.');
        }

        // ── Plages horaires ──────────────────────────────────────────────────
        $creneauxUtilises = (int)$pdo->query("SELECT COUNT(*) FROM vs_edt_creneaux")->fetchColumn();
        if ($creneauxUtilises === 0) {
            $pdo->exec("DELETE FROM vs_edt_plages_horaires");

            $creneauxV1 = $pdo->query("SELECT * FROM `creneaux` ORDER BY ordre ASC")->fetchAll(PDO::FETCH_ASSOC);
            $insert = $pdo->prepare(
                "INSERT INTO vs_edt_plages_horaires (libelle, heure_debut, heure_fin, ordre, actif)
                 VALUES (:libelle, :heure_debut, :heure_fin, :ordre, :actif)"
            );
            foreach ($creneauxV1 as $c) {
                $insert->execute([
                    ':libelle'     => $c['nom'],
                    ':heure_debut' => $c['heure_debut'],
                    ':heure_fin'   => $c['heure_fin'],
                    ':ordre'       => $c['ordre'],
                    ':actif'       => $c['actif'],
                ]);
            }
            error_log('[T038] ' . count($creneauxV1) . ' plage(s) horaire(s) migrée(s) vers vs_edt_plages_horaires (remplacement du seed générique).');
        } else {
            error_log('[T038] vs_edt_creneaux non vide — plages horaires laissées inchangées (migration manuelle requise).');
        }
    },
];
