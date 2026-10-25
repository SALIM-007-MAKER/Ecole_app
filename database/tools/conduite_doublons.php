<?php

/**
 * Rapport en LECTURE SEULE des évaluations de Conduite en doublon (plusieurs évaluations de Conduite non archivées pour une
 * même classe et une même période). Aucune écriture, aucun archivage : les doublons historiques sont conservés et leur
 * traitement éventuel (archivage) relève d'une décision distincte, qui modifierait les moyennes et exigerait une régénération.
 *
 * Usage : C:/wamp64/bin/php/php8.2.29/php.exe database/tools/conduite_doublons.php
 * Code retour : 0 = aucun doublon ; 1 = doublons listés ; 2 = erreur (connexion, colonne est_conduite absente).
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));

$env = [];
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
$base = $env['DB_DATABASE'] ?? 'ecole_app';
$alt = getenv('CONDUITE_CHECK_DB');
if ($alt !== false && $alt !== '') {
    if (!preg_match('/^ecole_app_copy_[a-f0-9]+$/', $alt)) { fwrite(STDERR, "REFUS : CONDUITE_CHECK_DB doit être une copie ecole_app_copy_*\n"); exit(2); }
    $base = $alt;
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'] ?? 'localhost', $env['DB_PORT'] ?? '3306', $base),
        $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $rows = $pdo->query(
        "SELECT ev.classe_id, c.nom AS classe, ev.periode_scolaire_id AS periode_id, ps.nom AS periode,
                ev.id, ev.libelle, ev.coefficient, ev.note_max, ev.statut,
                (SELECT COUNT(*) FROM notes_v2 n WHERE n.evaluation_id = ev.id) AS nb_notes
           FROM evaluations ev
           JOIN matieres m ON m.id = ev.matiere_id AND m.est_conduite = 1
           JOIN classes c ON c.id = ev.classe_id
           JOIN periodes_scolaires ps ON ps.id = ev.periode_scolaire_id
          WHERE ev.statut <> 'archivee'
          ORDER BY ev.classe_id, ev.periode_scolaire_id, ev.id"
    )->fetchAll();
} catch (Throwable $e) {
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . "\n");
    exit(2);
}

$groupes = [];
foreach ($rows as $r) { $groupes[$r['classe_id'] . '-' . $r['periode_id']][] = $r; }
$doublons = array_filter($groupes, fn($g) => count($g) > 1);

if ($doublons === []) {
    echo "Aucun doublon : au plus une évaluation de Conduite non archivée par classe et par période.\n";
    exit(0);
}
echo count($doublons) . " couple(s) classe/période avec plusieurs évaluations de Conduite non archivées (rapport en lecture seule) :\n";
foreach ($doublons as $g) {
    echo "\n  Classe {$g[0]['classe']} (#{$g[0]['classe_id']}) — {$g[0]['periode']} (#{$g[0]['periode_id']})\n";
    foreach ($g as $r) {
        printf("    - évaluation #%d  coef %s  /%s  %-11s  %d note(s)  « %s »\n", $r['id'], $r['coefficient'], $r['note_max'], $r['statut'], $r['nb_notes'], $r['libelle']);
    }
}
echo "\nAucune modification n'a été effectuée. Les doublons restent comptés dans la moyenne (et exigés par la complétude) tant qu'ils ne sont pas archivés.\n";
exit(1);
