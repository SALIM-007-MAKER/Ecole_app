<?php

/**
 * Arbitrage EXPLICITE des référentiels sans propriétaire démontré (Lot 3, migrations T051 à T055).
 *
 * Les migrations ne rattachent une ligne à un établissement que si l'usage le démontre ; les autres restent à NULL (invisibles de tous).
 * Cet outil les liste, puis — sur décision expresse de l'administrateur — les attribue à UN établissement donné.
 *
 *   php database/tools/lot3_arbitrage.php                         # liste (lecture seule, comportement par défaut)
 *   php database/tools/lot3_arbitrage.php --appliquer --etab=1 --confirmer [--tables=vs_edt_salles,vs_edt_plages_horaires]
 *
 * Sans --appliquer : AUCUNE écriture. Avec --appliquer : --etab=ID et --confirmer sont obligatoires ; seules les lignes à NULL de la
 * liste d'arbitrage sont modifiées (jamais un gabarit de catalogue, jamais une ligne déjà rattachée), aucune suppression.
 * Code retour : 0 = rien à arbitrer / arbitrage appliqué ; 1 = lignes à arbitrer listées ; 2 = erreur ou usage incorrect.
 * Cible alternative RÉSERVÉE aux tests : LOT3_DB=ecole_app_copy_* (copie jetable uniquement).
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));

$options = [];
foreach (array_slice($argv ?? [], 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $options[$m[1]] = $m[2] ?? true; }
    else { fwrite(STDERR, "Argument inconnu : $a\n"); exit(2); }
}

$env = [];
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
$base = $env['DB_DATABASE'] ?? 'ecole_app';
$alt = getenv('LOT3_DB');
if ($alt !== false && $alt !== '') {
    if (!preg_match('/^ecole_app_copy_[a-f0-9]+$/', $alt)) { fwrite(STDERR, "REFUS : LOT3_DB doit être une copie ecole_app_copy_*\n"); exit(2); }
    $base = $alt;
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'] ?? 'localhost', $env['DB_PORT'] ?? '3306', $base),
        $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) { fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . "\n"); exit(2); }

/** table => [libellé de la colonne affichée, condition SQL (alias t) des lignes À ARBITRER (sans propriétaire, hors gabarits de catalogue)] */
$tables = [
    'periodes_scolaires' => ['nom', 't.etablissement_id IS NULL'],
    'types_evaluations'  => ['nom', "t.etablissement_id IS NULL AND (t.est_systeme = 0 OR EXISTS (SELECT 1 FROM evaluations x WHERE x.type_evaluation_id = t.id))"],
    'vs_edt_salles'      => ['nom', 't.etablissement_id IS NULL'],
    'vs_edt_plages_horaires' => ['libelle', 't.etablissement_id IS NULL'],
    'vs_motifs_absence'  => ['libelle', 't.etablissement_id IS NULL AND EXISTS (SELECT 1 FROM vs_justifications_absences x WHERE x.motif_id = t.id)'],
    'vs_discipline_categories' => ['nom', 't.etablissement_id IS NULL AND EXISTS (SELECT 1 FROM vs_incidents_discipline x WHERE x.categorie_id = t.id)'],
    'vs_recompense_categories' => ['nom', 't.etablissement_id IS NULL AND EXISTS (SELECT 1 FROM vs_recompenses x WHERE x.categorie_id = t.id)'],
    'vs_activite_categories' => ['nom', 't.etablissement_id IS NULL AND EXISTS (SELECT 1 FROM vs_activites x WHERE x.categorie_id = t.id)'],
];
$colonne = fn(string $t) => (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$t' AND column_name = 'etablissement_id'")->fetchColumn() > 0;

$choisies = isset($options['tables']) && is_string($options['tables']) ? array_filter(explode(',', $options['tables'])) : array_keys($tables);
foreach ($choisies as $t) { if (!isset($tables[$t])) { fwrite(STDERR, "Table inconnue : $t\n"); exit(2); } }

$aArbitrer = [];
foreach ($choisies as $t) {
    if (!$colonne($t)) { echo "  [$t] migration non appliquée (colonne etablissement_id absente) — ignorée\n"; continue; }
    [$libelle, $cond] = $tables[$t];
    $aArbitrer[$t] = $pdo->query("SELECT t.id, t.`$libelle` AS libelle FROM `$t` t WHERE $cond ORDER BY t.id")->fetchAll();
}

$total = array_sum(array_map('count', $aArbitrer));
if (!isset($options['appliquer'])) {
    if ($total === 0) { echo "Rien à arbitrer : toutes les lignes ont un propriétaire démontré ou sont des gabarits de catalogue.\n"; exit(0); }
    echo "$total ligne(s) sans propriétaire démontré (invisibles de tous les établissements) — liste en lecture seule :\n";
    foreach ($aArbitrer as $t => $lignes) {
        if ($lignes === []) { continue; }
        echo "  $t :\n";
        foreach ($lignes as $l) { echo "    - #{$l['id']}  {$l['libelle']}\n"; }
    }
    echo "\nPour les attribuer à un établissement : --appliquer --etab=ID --confirmer [--tables=…]. Aucune écriture n'a été effectuée.\n";
    exit(1);
}

// ── Application : décision explicite exigée ──
$etab = isset($options['etab']) && is_string($options['etab']) && ctype_digit($options['etab']) ? (int)$options['etab'] : 0;
if ($etab <= 0 || !isset($options['confirmer'])) {
    fwrite(STDERR, "REFUS : --appliquer exige --etab=ID (établissement destinataire) ET --confirmer.\n");
    exit(2);
}
$existe = $pdo->prepare('SELECT nom FROM etablissements WHERE id = ? AND deleted_at IS NULL');
$existe->execute([$etab]);
$nomEtab = $existe->fetchColumn();
if ($nomEtab === false) { fwrite(STDERR, "REFUS : établissement #$etab introuvable.\n"); exit(2); }

$modifiees = 0;
$pdo->beginTransaction();
try {
    foreach ($aArbitrer as $t => $lignes) {
        if ($lignes === []) { continue; }
        $hasUpdated = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$t' AND column_name = 'updated_at'")->fetchColumn() > 0;
        $set = $hasUpdated ? 'etablissement_id = ?, updated_at = updated_at' : 'etablissement_id = ?';
        $stmt = $pdo->prepare("UPDATE `$t` SET $set WHERE id = ? AND etablissement_id IS NULL");
        foreach ($lignes as $l) { $stmt->execute([$etab, $l['id']]); $modifiees += $stmt->rowCount(); }
        echo "  $t : " . count($lignes) . " ligne(s) attribuée(s) à l'établissement #$etab « $nomEtab »\n";
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'ERREUR (aucune modification conservée) : ' . $e->getMessage() . "\n");
    exit(2);
}
echo "$modifiees ligne(s) attribuée(s). Aucune suppression.\n";
exit(0);
