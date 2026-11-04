<?php

/**
 * Liste en LECTURE SEULE des bulletins à régénérer EXPLICITEMENT avant publication à cause de la règle Conduite :
 *   - instantané d'ancien format (sans snapshot_version à jour) : à régénérer même si les notes n'ont pas changé ;
 *   - instantané marqué « Conduite incomplète » (conduite_complete = false).
 * Aucune régénération, aucune écriture : la régénération est une action explicite (route « Régénérer en vue de la publication »).
 *
 * Usage : C:/wamp64/bin/php/php8.2.29/php.exe database/tools/conduite_bulletins_concernes.php
 * Code retour : 0 = aucun bulletin concerné ; 1 = bulletins listés ; 2 = erreur.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
const SNAPSHOT_VERSION_ATTENDUE = 2;   // = BulletinGenerator::SNAPSHOT_VERSION

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
        "SELECT b.id, b.eleve_id, b.classe_id, b.periode_id, b.statut, b.data_json, e.nom, e.prenom
           FROM bulletins_v2 b LEFT JOIN eleves e ON e.id = b.eleve_id
          ORDER BY b.classe_id, b.periode_id, e.nom, e.prenom, b.id"
    )->fetchAll();
} catch (Throwable $e) {
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . "\n");
    exit(2);
}

$concernes = [];
foreach ($rows as $r) {
    $d = json_decode((string)$r['data_json'], true) ?: [];
    $motifs = [];
    if ((int)($d['snapshot_version'] ?? 0) < SNAPSHOT_VERSION_ATTENDUE) { $motifs[] = 'ancien format'; }
    if (array_key_exists('conduite_complete', $d) && $d['conduite_complete'] === false) { $motifs[] = 'Conduite incomplète'; }
    if ($motifs !== []) { $concernes[] = [$r, $motifs]; }
}

echo count($rows) . " bulletin(s) examiné(s), " . count($concernes) . " à régénérer explicitement avant publication (rapport en lecture seule).\n";
foreach ($concernes as [$r, $motifs]) {
    printf("  - bulletin #%d  classe %d  période %d  %-9s  élève #%d %s %s  [%s]\n",
        $r['id'], $r['classe_id'], $r['periode_id'], $r['statut'], $r['eleve_id'], $r['prenom'] ?? '', $r['nom'] ?? '', implode(', ', $motifs));
}
echo $concernes === [] ? '' : "\nAucune modification n'a été effectuée. Régénérer chaque bulletin depuis sa page (« Régénérer en vue de la publication »).\n";
exit($concernes === [] ? 0 : 1);
