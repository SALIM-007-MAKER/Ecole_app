<?php
/**
 * Tests — Lot 3, étape 3 : migrations T054 (catalogues) et T055 (salles, plages) et outil d'arbitrage, sur COPIES jetables.
 *
 * Vérifie : compatibilité avant migration, rattachement démontré par l'usage, lignes ambiguës laissées à NULL et signalées (y compris
 * l'incident orphelin des données réelles), catalogues copiés par établissement, idempotence, retour arrière exact (schéma et données),
 * outil d'arbitrage (liste en lecture seule, refus sans décision explicite, attribution contrôlée, jamais un gabarit).
 *
 * AUCUNE migration n'est appliquée à la base réelle : la source n'est jamais écrite (empreinte avant / après).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/TenantVieScolaireMigrationsTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);
define('BASE_URL', '/ecole_app');
require_once ROOT_PATH . '/tests/Support/DbCopy.php';
require_once ROOT_PATH . '/tests/Support/ConduiteCopy.php';
require_once ROOT_PATH . '/tests/Support/Lot3Copy.php';

use Tests\Support\ConduiteCopy;
use Tests\Support\DbCopy;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
function outil(string $copie, array $args): array
{
    $p = proc_open(array_merge([PHP_BINARY, ROOT_PATH . '/database/tools/lot3_arbitrage.php'], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), ['LOT3_DB' => $copie]));
    $o = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $o];
}

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) { continue; }
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}

$conn = DbCopy::connectionFromEnv(ROOT_PATH);
$source = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['db']};charset=utf8mb4", $conn['user'], $conn['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$avantSource = DbCopy::allDataHashes($source);
$migrationsSourceAvant = $source->query("SELECT migration FROM migrations_log WHERE migration IN ('T054','T055')")->fetchAll(PDO::FETCH_COLUMN);
$copy = DbCopy::create(ROOT_PATH);
if ($copy === null) { echo "SKIP : copie réelle impossible\n"; exit(0); }

try {
    $db = $copy->pdo();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('SET NAMES utf8mb4');
    ConduiteCopy::migrer($db, ROOT_PATH);
    \Tests\Support\Lot3Copy::retourAvantLot3($db, ROOT_PATH);   // la base réelle porte désormais T051–T058 : la copie est ramenée à l'état « avant »
    $nom = $copy->name;
    $http = fn(string $role, int $uid, string $m, string $u) => ConduiteCopy::http(ROOT_PATH, $nom, $role, $uid, $m, "/ecole_app{$u}");
    $one = fn(string $sql) => $db->query($sql)->fetchColumn();
    $colonne = fn(string $t) => (int)$one("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$t' AND column_name = 'etablissement_id'");
    $m54 = require ROOT_PATH . '/database/migrations/T054_vie_scolaire_catalogues_etablissement.php';
    $m55 = require ROOT_PATH . '/database/migrations/T055_vie_scolaire_salles_plages_etablissement.php';
    $cat = ['vs_motifs_absence', 'vs_discipline_categories', 'vs_recompense_categories', 'vs_activite_categories'];
    $tables = array_merge($cat, ['vs_edt_salles', 'vs_edt_plages_horaires']);

    // ═════════════════════════════════════════════════════════════════════
    section('1. Compatibilité AVANT migration : référentiels partagés, aucune page cassée');
    check(array_sum(array_map($colonne, $tables)) === 0, 'état de départ : aucune colonne etablissement_id sur les six référentiels');
    foreach (['/v2/vie-scolaire/absences', '/v2/vie-scolaire/absences/create', '/v2/vie-scolaire/retards', '/v2/vie-scolaire/discipline', '/v2/vie-scolaire/discipline/create',
              '/v2/vie-scolaire/recompenses', '/v2/vie-scolaire/recompenses/create', '/v2/vie-scolaire/emplois-du-temps', '/v2/vie-scolaire/emplois-du-temps/salles',
              '/v2/vie-scolaire/emplois-du-temps/plages', '/v2/vie-scolaire/activites', '/v2/vie-scolaire/activites/create', '/v2/vie-scolaire/emplois-du-temps/remplacements',
              '/v2/vie-scolaire/activites/statistiques', '/v2/vie-scolaire/absences/statistiques', '/v2/vie-scolaire/retards/statistiques', '/v2/vie-scolaire/discipline/statistiques',
              '/v2/vie-scolaire/recompenses/statistiques', '/v2/vie-scolaire/recompenses/classement', '/v2/vie-scolaire/emplois-du-temps/statistiques'] as $u) {
        [$r, $b] = $http('admin', 1, 'GET', $u);
        check(($r['status'] ?? 0) === 200 && !preg_match('/SQLSTATE|Unknown column|Fatal error/i', $b), "$u : 200 sans erreur SQL sur une base non migrée", 'statut ' . ($r['status'] ?? '?') . ' ' . (preg_match('/(SQLSTATE|Unknown column|Fatal error)[^<]{0,120}/', $b, $mm) ? $mm[0] : ''));
    }

    // ═════════════════════════════════════════════════════════════════════
    section('2. Rattachement par l\'usage et ambiguïtés');
    $db->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('lot3-mig-vs', 'Ecole Mig VS', 'EMV', 'lycee')");
    $E2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO users (etablissement_id, nom, prenom, email, password, role) VALUES ($E2, 'A2', 'x', 'a2-migvs@example.test', 'x', 'admin')");
    $U2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire) VALUES ($E2, 'CL-MIG-VS', '6eme', '2025-2026')");
    $C2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO eleves (etablissement_id, matricule, nom, prenom, date_naissance, sexe, classe_id, actif) VALUES ($E2, 'MV-1', 'EL-MIG-VS', 'Z', '2012-01-01', 'F', $C2, 1)");
    $EL2 = (int)$db->lastInsertId();
    $matiere1 = (int)$one('SELECT id FROM matieres WHERE etablissement_id = 1 LIMIT 1');
    $db->exec("INSERT INTO matieres (etablissement_id, nom, coefficient, volume_horaire) VALUES ($E2, 'M-MIG-VS', 1, 2)");
    $matiere2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO vs_emplois_du_temps (classe_id, annee_scolaire, cree_par, statut) VALUES (131, '2025-2026', 1, 'brouillon')"); $edt1 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO vs_emplois_du_temps (classe_id, annee_scolaire, cree_par, statut) VALUES ($C2, '2025-2026', $U2, 'brouillon')"); $edt2 = (int)$db->lastInsertId();
    // salle 1 : créneau de l'établissement 1 seul ; salle 2 : créneaux des deux établissements ; salle 3+ : aucun usage
    $creneau = fn(int $edt, int $classe, int $mat, int $salle, int $plage, int $jour) => $db->exec("INSERT INTO vs_edt_creneaux (emploi_du_temps_id, classe_id, matiere_id, enseignant_id, salle_id, jour, plage_id, annee_scolaire) VALUES ($edt, $classe, $mat, 1, $salle, $jour, $plage, '2025-2026')");
    $creneau($edt1, 131, $matiere1, 1, 10, 1); $creneau($edt1, 131, $matiere1, 2, 11, 2); $creneau($edt2, $C2, $matiere2, 2, 11, 3);
    // motif utilisé par une justification de l'établissement 2 seul
    $db->exec("INSERT INTO vs_absences (eleve_id, classe_id, annee_scolaire, date_absence, saisie_par, statut) VALUES ($EL2, $C2, '2025-2026', '2026-01-12', $U2, 'non_justifiee')"); $abs2 = (int)$db->lastInsertId();
    $motifUtilise = (int)$one("SELECT id FROM vs_motifs_absence WHERE id NOT IN (SELECT motif_id FROM vs_justifications_absences WHERE motif_id IS NOT NULL) ORDER BY id LIMIT 1");   // motif encore inutilisé
    $db->exec("INSERT INTO vs_justifications_absences (absence_id, motif_id, soumis_par) VALUES ($abs2, $motifUtilise, $U2)");
    // L'incident dont le dossier n'existe pas (anomalie des données réelles) a été archivé puis retiré par T062 : on le recrée sur la copie.
    if ((int)$one('SELECT COUNT(*) FROM vs_incidents_discipline WHERE categorie_id = 2') === 0) {
        $db->exec("INSERT INTO vs_incidents_discipline (dossier_id, categorie_id, gravite, description, date_incident, signale_par, statut) VALUES (999999, 2, 'moyen', 'incident orphelin (test)', '2026-07-11', 1, 'ouvert')");
    }
    $avant = ['schema' => DbCopy::schema($db, $tables), 'data' => DbCopy::allDataHashes($db)];
    $nbSalles = (int)$one('SELECT COUNT(*) FROM vs_edt_salles');

    ob_start(); ($m54['run'])($db); ($m55['run'])($db); $sortie = ob_get_clean();
    check(array_sum(array_map($colonne, $tables)) === 6, 'T054 et T055 appliquées : colonne présente sur les six référentiels');
    check((int)$one("SELECT etablissement_id FROM vs_edt_salles WHERE id = 1") === 1, 'salle utilisée par un créneau de l\'établissement 1 seul : rattachée à l\'établissement 1');
    check($one('SELECT etablissement_id FROM vs_edt_salles WHERE id = 2') === null && str_contains($sortie, 'vs_edt_salles #2'), 'salle utilisée par deux établissements : laissée à NULL et signalée [AMBIGU]');
    check($one('SELECT etablissement_id FROM vs_edt_salles WHERE id = 3') === null && str_contains($sortie, 'vs_edt_salles #3'), 'salle sans usage : laissée à NULL et signalée');
    check((int)$one('SELECT etablissement_id FROM vs_edt_plages_horaires WHERE id = 10') === 1 && $one('SELECT etablissement_id FROM vs_edt_plages_horaires WHERE id = 11') === null, 'plages : rattachée par l\'usage (id 10) / partagée laissée à NULL (id 11)');
    check((int)$one('SELECT COUNT(*) FROM vs_edt_salles WHERE etablissement_id IS NOT NULL AND id NOT IN (1)') === 0, 'aucune salle n\'est attribuée par défaut à l\'établissement 1');
    check((int)$one("SELECT etablissement_id FROM vs_motifs_absence WHERE id = $motifUtilise") === $E2, 'motif utilisé par l\'établissement 2 seul : rattaché à l\'établissement 2 par l\'usage');
    $codeUtilise = (string)$one("SELECT code FROM vs_motifs_absence WHERE id = $motifUtilise");
    check((int)$one("SELECT COUNT(*) FROM vs_motifs_absence WHERE code = '$codeUtilise' AND etablissement_id IS NULL") === 1, 'un gabarit de catalogue (sans propriétaire) existe pour ce code');
    check(str_contains($sortie, 'vs_discipline_categories #2') && str_contains($sortie, 'usage sans propriétaire déterminable'), 'incident orphelin des données réelles (dossier inexistant) : catégorie signalée [AMBIGU], non rattachée');
    foreach ($cat as $t) {
        $nbCodes = (int)$one("SELECT COUNT(DISTINCT code) FROM `$t`");
        check((int)$one("SELECT COUNT(*) FROM `$t` WHERE etablissement_id = 1") >= $nbCodes - 1 && (int)$one("SELECT COUNT(*) FROM `$t` WHERE etablissement_id = $E2") === $nbCodes, "$t : chaque établissement dispose d'une copie de chaque code du catalogue ($nbCodes codes)");
        check((int)$one("SELECT COUNT(*) FROM (SELECT code FROM `$t` WHERE etablissement_id IS NULL GROUP BY code HAVING COUNT(*) > 1) d") <= 1, "$t : pas de doublon de gabarit non référencé");
    }
    // Les statistiques filtrent le catalogue par établissement : elles doivent aussi répondre APRÈS la migration, pour chaque établissement.
    foreach ([['admin', 1, 'établissement 1'], ['admin', $U2, 'établissement 2']] as [$roleStat, $uidStat, $libStat]) {
        foreach (['/v2/vie-scolaire/activites/statistiques', '/v2/vie-scolaire/absences/statistiques', '/v2/vie-scolaire/recompenses/statistiques'] as $u) {
            [$r, $b] = $http($roleStat, $uidStat, 'GET', $u);
            check(($r['status'] ?? 0) === 200 && !preg_match('/SQLSTATE|Unknown column|Fatal error/i', $b), "$u : 200 sans erreur SQL après migration ($libStat)");
        }
    }


    section('3. Idempotence et retour arrière exact');
    ob_start(); $h1 = DbCopy::allDataHashes($db); ($m54['run'])($db); ($m55['run'])($db); ob_end_clean();
    check(DbCopy::allDataHashes($db) === $h1, 'rejouer T054 et T055 ne change rien (idempotentes)');
    ($m55['rollback'])($db); ($m54['rollback'])($db);
    check(DbCopy::schema($db, $tables) === $avant['schema'], 'retour arrière : schéma des six tables identique à l\'état initial');
    $apres = DbCopy::allDataHashes($db);
    $diff = array_keys(array_filter($apres, fn($h, $t) => ($avant['data'][$t] ?? null) !== $h, ARRAY_FILTER_USE_BOTH));
    check($diff === [], 'retour arrière : données identiques à l\'état initial', implode(',', $diff));

    // ═════════════════════════════════════════════════════════════════════
    section('4. Outil d\'arbitrage');
    ob_start(); ($m54['run'])($db); ($m55['run'])($db); ob_end_clean();
    $etatAvant = md5(json_encode([$db->query('SELECT * FROM vs_edt_salles ORDER BY id')->fetchAll(), $db->query('SELECT * FROM vs_edt_plages_horaires ORDER BY id')->fetchAll()]));
    [$c, $o] = outil($nom, []);
    $etatApresListe = md5(json_encode([$db->query('SELECT * FROM vs_edt_salles ORDER BY id')->fetchAll(), $db->query('SELECT * FROM vs_edt_plages_horaires ORDER BY id')->fetchAll()]));
    check($c === 1 && str_contains($o, 'vs_edt_salles') && str_contains($o, 'Salle A02') && str_contains($o, 'Aucune écriture'), 'liste : code 1, salles et plages sans propriétaire listées, lecture seule');
    check($etatApresListe === $etatAvant, 'la liste n\'a modifié aucune ligne');
    check(!str_contains($o, 'Salle A01'), 'une salle déjà rattachée n\'est pas listée');
    [$c] = outil($nom, ['--appliquer']);
    check($c === 2, '--appliquer sans --etab ni --confirmer : refusé (code 2)');
    [$c] = outil($nom, ['--appliquer', '--etab=1']);
    check($c === 2, '--appliquer sans --confirmer : refusé');
    [$c] = outil($nom, ['--appliquer', '--etab=999999', '--confirmer']);
    check($c === 2, 'établissement inexistant : refusé');
    check(md5(json_encode([$db->query('SELECT * FROM vs_edt_salles ORDER BY id')->fetchAll(), $db->query('SELECT * FROM vs_edt_plages_horaires ORDER BY id')->fetchAll()])) === $etatAvant, 'les refus n\'ont rien modifié');
    $gabaritsAvant = md5(json_encode(array_map(fn($t) => $db->query("SELECT * FROM `$t` WHERE etablissement_id IS NULL ORDER BY id")->fetchAll(), $cat)));
    [$c, $o] = outil($nom, ['--appliquer', '--etab=1', '--confirmer', '--tables=vs_edt_salles,vs_edt_plages_horaires']);
    check($c === 0 && (int)$one('SELECT COUNT(*) FROM vs_edt_salles WHERE etablissement_id IS NULL') === 0 && (int)$one('SELECT COUNT(*) FROM vs_edt_plages_horaires WHERE etablissement_id IS NULL') === 0, 'attribution explicite à l\'établissement 1 : salles et plages sans propriétaire attribuées');
    check((int)$one('SELECT etablissement_id FROM vs_edt_salles WHERE id = 1') === 1 && (int)$one('SELECT COUNT(*) FROM vs_edt_salles') === $nbSalles, 'aucune suppression, lignes déjà rattachées inchangées');
    check($gabaritsAvant === md5(json_encode(array_map(fn($t) => $db->query("SELECT * FROM `$t` WHERE etablissement_id IS NULL ORDER BY id")->fetchAll(), $cat))), 'les gabarits de catalogue ne sont jamais attribués');
    [$c, $o] = outil($nom, ['--tables=vs_edt_salles,vs_edt_plages_horaires']);
    check($c === 0, 'après arbitrage : plus rien à arbitrer sur ces tables (code 0)');
    [$c, $o] = outil($nom, ['--tables=table_inconnue']);
    check($c === 2, 'table inconnue : erreur d\'usage');
} finally {
    $copy->drop();
}

section('5. Base réelle');
check(DbCopy::allDataHashes($source) === $avantSource, 'base réelle inchangée (empreinte de toutes les tables)');
$migrationsSourceApres = $source->query("SELECT migration FROM migrations_log WHERE migration IN ('T054','T055')")->fetchAll(PDO::FETCH_COLUMN);
check($migrationsSourceApres === $migrationsSourceAvant, 'aucune migration T054-T055 enregistrée ni appliquée sur la base réelle');
check(count(array_filter($source->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN), fn($d) => str_starts_with($d, 'ecole_app_copy_'))) === 0, 'aucune base temporaire restante');

echo "\n" . ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : $passed/" . ($passed + $failed) . " assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
