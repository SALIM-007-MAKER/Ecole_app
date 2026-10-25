<?php
/**
 * Tests — Lot 2 (Conduite) : migrations T048 (marqueur), T049 (permission), T050 (déclencheurs) et outil --check.
 *
 * Tout tourne sur une COPIE RÉELLE jetable de la base (DbCopy) ; migrate.php et l'outil sont dirigés vers la copie par
 * MIGRATE_DB / CONDUITE_CHECK_DB (refusés hors ecole_app_copy_*). La base réelle n'est jamais écrite : empreintes
 * avant/après comparées. TR-9 lit seulement les métadonnées de la base réelle.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/ConduiteMigrationsTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/tests/Support/DbCopy.php';
require_once ROOT_PATH . '/app/Modules/Academique/Services/ConduiteProtectionStatus.php';

use App\Modules\Academique\Services\ConduiteProtectionStatus as Prot;
use Tests\Support\DbCopy;

$passed = 0; $failed = 0;
function check(bool $ok, string $label): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; } else { $failed++; echo "\033[31m  ✗ $label\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
/** SQLSTATE de l'exception levée par $f (null si aucune). */
function sqlstate(callable $f): ?string
{
    try { $f(); return null; } catch (\PDOException $e) { return (string)($e->errorInfo[0] ?? $e->getCode()); }
}
function lance(array $cmd, array $env = []): array
{
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), $env));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out];
}

$conn = DbCopy::connectionFromEnv(ROOT_PATH);
$source = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['db']};charset=utf8mb4", $conn['user'], $conn['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$avantSource = DbCopy::allDataHashes($source);
$declencheursSource = (int)$source->query("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND trigger_name LIKE 'trg\\_matieres\\_conduite\\_%'")->fetchColumn();

$copy = DbCopy::create(ROOT_PATH);
if ($copy === null) { echo "SKIP : copie réelle impossible\n"; exit(0); }
$php = PHP_BINARY;

try {
    $db = $copy->pdo();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $nom = $copy->name;
    $migrate = fn(string $id, array $env = []) => lance([$php, ROOT_PATH . '/database/migrate.php', "--id=$id"], ['MIGRATE_DB' => $nom] + $env);
    $outil   = fn() => lance([$php, ROOT_PATH . '/database/tools/conduite_protection.php', '--check'], ['CONDUITE_CHECK_DB' => $nom]);
    $nbTrg   = fn() => (int)$db->query("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND trigger_name LIKE 'trg\\_matieres\\_conduite\\_%'")->fetchColumn();
    $t048 = require ROOT_PATH . '/database/migrations/T048_matieres_est_conduite.php';
    $t049 = require ROOT_PATH . '/database/migrations/T049_permission_conduite_manage.php';
    $t050 = require ROOT_PATH . '/database/migrations/T050_matieres_conduite_declencheurs.php';

    // État de départ « avant Lot 2 » (la source peut déjà l'avoir reçu)
    if ($nbTrg() > 0) { $t050['rollback']($db); }
    if ((int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='matieres' AND column_name='est_conduite'")->fetchColumn() > 0) { $t048['rollback']($db); }
    $t049['rollback']($db);
    $db->exec("DELETE FROM migrations_log WHERE migration IN ('T048','T049','T050')");

    section('1. T048 — marqueur est_conduite');
    $matieresAvant = $db->query('SELECT id, etablissement_id, nom, coefficient, categorie, volume_horaire, responsable_id, description FROM matieres ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $autresAvant = DbCopy::allDataHashes($db, ['matieres', 'migrations_log']);
    $conduite = (int)$db->query("SELECT id FROM matieres WHERE LOWER(nom)='conduite' AND etablissement_id=1")->fetchColumn();
    [$code, $out] = $migrate('T048');
    check($code === 0 && str_contains($out, 'T048'), "migrate.php --id=T048 sur la copie : code 0 ($code)");
    check((int)$db->query("SELECT COUNT(*) FROM matieres WHERE est_conduite = 1")->fetchColumn() === 1, 'exactement une matière marquée Conduite');
    check((int)$db->query("SELECT est_conduite FROM matieres WHERE id = $conduite")->fetchColumn() === 1, "la matière $conduite (Conduite) est marquée");
    check((int)$db->query("SELECT COUNT(*) FROM matieres WHERE est_conduite IS NULL")->fetchColumn() === count($matieresAvant) - 1, 'toutes les autres matières restent à NULL');
    check((int)$db->query("SELECT volume_horaire FROM matieres WHERE id = $conduite")->fetchColumn() === 0, 'volume horaire de la Conduite = 0');
    check((float)$db->query("SELECT coefficient FROM matieres WHERE id = $conduite")->fetchColumn() === 1.0, 'coefficient de la Conduite inchangé (1)');
    $matieresApres = $db->query('SELECT id, etablissement_id, nom, coefficient, categorie, volume_horaire, responsable_id, description FROM matieres ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $diff = [];
    foreach ($matieresAvant as $i => $m) { if ($m !== $matieresApres[$i]) { $diff[] = $m['id']; } }
    check($diff === [(string)$conduite] || $diff === [$conduite], 'seule la ligne de la Conduite diffère (autres matières identiques)');
    check(DbCopy::allDataHashes($db, ['matieres', 'migrations_log']) === $autresAvant, 'aucune autre table modifiée (évaluations, notes, bulletins… identiques)');
    check((int)$db->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name='matieres' AND index_name='uq_matiere_conduite' AND non_unique = 0")->fetchColumn() === 2, 'index unique (etablissement_id, est_conduite) présent');
    check($db->query("SELECT status FROM migrations_log WHERE migration='T048'")->fetchColumn() === 'success', 'migrations_log : T048 success');
    $t048['run']($db);
    check((int)$db->query("SELECT COUNT(*) FROM matieres WHERE est_conduite = 1")->fetchColumn() === 1, 'T048 rejouée : idempotente');

    section('2. T049 — permission academique.conduite.manage');
    $permAvant = (int)$db->query("SELECT COUNT(*) FROM etab_role_permissions")->fetchColumn();
    [$code] = $migrate('T049');
    check($code === 0, "migrate.php --id=T049 : code 0 ($code)");
    $roles = $db->query("SELECT r.code FROM etab_role_permissions rp JOIN etab_roles r ON r.id=rp.role_id JOIN etab_permissions p ON p.id=rp.permission_id WHERE p.code='academique.conduite.manage' ORDER BY r.code")->fetchAll(PDO::FETCH_COLUMN);
    check($roles === ['admin', 'directeur'], 'attribuée à admin et directeur uniquement : ' . implode(',', $roles));
    check((int)$db->query("SELECT COUNT(*) FROM etab_role_permissions")->fetchColumn() === $permAvant + 2, 'exactement 2 attributions ajoutées, aucune retirée');
    $t049['run']($db);
    check((int)$db->query("SELECT COUNT(*) FROM etab_role_permissions")->fetchColumn() === $permAvant + 2, 'T049 rejouée : idempotente');

    section('3. TR-9 — outil --check avant T050 (copie) puis sur la base réelle (lecture seule)');
    [$code, $out] = $outil();
    check($code === 1 && str_contains($out, 'NON opérationnelle'), "copie sans T050 : code 1 ($code)");

    section('4. T050 — trois déclencheurs');
    [$code, $out] = $migrate('T050');
    check($code === 0, "migrate.php --id=T050 : code 0 ($code)");
    check($nbTrg() === 3, '3 déclencheurs créés');
    [$code, $out] = $outil();
    check($code === 0 && str_contains($out, 'opérationnelle'), "--check : code 0 ($code)");
    check($db->query("SELECT status FROM migrations_log WHERE migration='T050'")->fetchColumn() === 'success', 'migrations_log : T050 success');

    section('5. TR-1 à TR-4 — règles des déclencheurs');
    check(sqlstate(fn() => $db->exec("DELETE FROM matieres WHERE id = $conduite")) === '45000', 'suppression de la Conduite refusée (45000)');
    $db->exec("INSERT INTO matieres (etablissement_id, nom, coefficient, volume_horaire) VALUES (1, 'MatiereTestLot2', 2, 3)");
    $ordinaire = (int)$db->lastInsertId();
    check(sqlstate(fn() => $db->exec("DELETE FROM matieres WHERE id = $ordinaire")) === null, 'suppression d\'une matière ordinaire acceptée');
    check(sqlstate(fn() => $db->exec("UPDATE matieres SET est_conduite = NULL WHERE id = $conduite")) === '45000', 'retrait du marqueur refusé');
    check(sqlstate(fn() => $db->exec("UPDATE matieres SET coefficient = 2 WHERE id = $conduite")) === '45000', 'coefficient 2 refusé');
    check(sqlstate(fn() => $db->exec("UPDATE matieres SET volume_horaire = 3 WHERE id = $conduite")) === '45000', 'volume 3 refusé');
    $db->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('lot2-autre', 'Autre Lot2', 'Autre', 'lycee')");
    $autreEtab = (int)$db->lastInsertId();
    check(sqlstate(fn() => $db->exec("UPDATE matieres SET etablissement_id = $autreEtab WHERE id = $conduite")) === '45000', 'changement d\'établissement refusé');
    check(sqlstate(fn() => $db->exec("UPDATE matieres SET nom = 'Comportement' WHERE id = $conduite")) === null, 'renommage de la Conduite accepté');
    $db->exec("UPDATE matieres SET nom = 'Conduite' WHERE id = $conduite");
    $db->exec("INSERT INTO matieres (etablissement_id, nom, coefficient, volume_horaire) VALUES (1, 'OrdinaireLot2', 2, 3)");
    $ord = (int)$db->lastInsertId();
    check(sqlstate(fn() => $db->exec("UPDATE matieres SET coefficient = 3, volume_horaire = 5, categorie = 'scientifique' WHERE id = $ord")) === null, 'matière ordinaire : coefficient, volume et catégorie modifiables');
    check(sqlstate(fn() => $db->exec("UPDATE matieres SET est_conduite = 1 WHERE id = $ord")) === '45000', 'marquer une matière ordinaire avec coefficient/volume non conformes : refusé');
    $db->exec("UPDATE matieres SET coefficient = 1, volume_horaire = 0 WHERE id = $ord");
    $e = sqlstate(fn() => $db->exec("UPDATE matieres SET est_conduite = 1 WHERE id = $ord"));
    check($e === '23000', 'seconde Conduite dans le même établissement refusée par l\'index unique (' . $e . ')');
    check(sqlstate(fn() => $db->exec("INSERT INTO matieres (etablissement_id, nom, coefficient, volume_horaire, est_conduite) VALUES ($autreEtab, 'CondBad', 2, 0, 1)")) === '45000', 'INSERT d\'une Conduite non conforme refusé');
    check(sqlstate(fn() => $db->exec("INSERT INTO matieres (etablissement_id, nom, coefficient, volume_horaire, est_conduite) VALUES ($autreEtab, 'Conduite', 1, 0, 1)")) === null, 'INSERT d\'une Conduite conforme dans un nouvel établissement accepté');
    $db->exec("DELETE FROM matieres WHERE id = $ord");

    section('6. TR-7 — détection d\'un déclencheur absent ou au corps modifié');
    $db->exec('DROP TRIGGER trg_matieres_conduite_bd');
    $r = Prot::check($db);
    check(!$r['ok'] && $r['details']['trg_matieres_conduite_bd'] === 'absent', 'déclencheur absent détecté');
    $db->exec("CREATE TRIGGER trg_matieres_conduite_bd BEFORE DELETE ON matieres FOR EACH ROW BEGIN END");
    $r = Prot::check($db);
    check(!$r['ok'] && $r['details']['trg_matieres_conduite_bd'] === 'corps modifié', 'déclencheur au corps vide détecté');
    [$code] = $outil();
    check($code === 1, 'l\'outil --check retourne 1 dans les deux cas');
    $db->exec('DROP TRIGGER trg_matieres_conduite_bd');
    $db->exec("CREATE TRIGGER trg_matieres_conduite_bd AFTER DELETE ON matieres FOR EACH ROW BEGIN END");
    $r = Prot::check($db);
    check(!$r['ok'] && str_contains($r['details']['trg_matieres_conduite_bd'], 'inattendu'), 'moment (AFTER au lieu de BEFORE) détecté');
    $db->exec('DROP TRIGGER trg_matieres_conduite_bd');

    section('7. TR-6 — échec partiel injecté sur chacun des trois déclencheurs');
    $db->exec("DELETE FROM migrations_log WHERE migration='T050'");
    $t050['rollback']($db);
    foreach ([1, 2, 3] as $rang) {
        [$code, $out] = $migrate('T050', ['CONDUITE_T050_FAIL_AT' => (string)$rang]);
        check($code === 1 && str_contains($out, 'N\'EST PAS opérationnelle'), "panne au déclencheur $rang : code 1 et message explicite");
        check($nbTrg() === 0, "panne au déclencheur $rang : 0 déclencheur restant (nettoyage)");
        $log = $db->query("SELECT status, error FROM migrations_log WHERE migration='T050'")->fetch(PDO::FETCH_ASSOC);
        check($log && $log['status'] === 'failed' && str_contains((string)$log['error'], 'T050 ÉCHEC'), "panne $rang : migrations_log = failed avec l'erreur");
        [$c2] = $outil();
        check($c2 === 1, "panne $rang : --check en échec");
    }
    [$code] = $migrate('T050');
    check($code === 0 && $nbTrg() === 3, 'nouvelle tentative sans panne : 3 déclencheurs');
    check($db->query("SELECT status FROM migrations_log WHERE migration='T050'")->fetchColumn() === 'success', 'migrations_log : retour à success');
    [$c3] = $outil();
    check($c3 === 0, '--check vert après reprise');

    section('8. TR-5/TR-8 — idempotence et ordre de retour arrière');
    $t050['run']($db);
    check($nbTrg() === 3 && Prot::check($db)['ok'], 'T050 appliquée deux fois : toujours 3 déclencheurs, contrôle vert');
    $refus = null;
    try { $t048['rollback']($db); } catch (\Throwable $e) { $refus = $e->getMessage(); }
    check($refus !== null && str_contains($refus, 'T050'), 'annuler T048 avant T050 : refusé (' . substr((string)$refus, 0, 50) . ')');
    check((int)$db->query("SELECT COUNT(*) FROM matieres WHERE est_conduite = 1 AND etablissement_id = 1")->fetchColumn() === 1, 'rien n\'a été retiré par le retour arrière refusé');
    $t050['rollback']($db);
    check($nbTrg() === 0, 'T050 annulée : 0 déclencheur');
    $t049['rollback']($db);
    check((int)$db->query("SELECT COUNT(*) FROM etab_permissions WHERE code='academique.conduite.manage'")->fetchColumn() === 0, 'T049 annulée : permission retirée');
    $db->exec("DELETE FROM matieres WHERE etablissement_id = $autreEtab");
    $t048['rollback']($db);
    check((int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='matieres' AND column_name='est_conduite'")->fetchColumn() === 0, 'T048 annulée : colonne retirée');
    $apres = $db->query("SELECT id, etablissement_id, nom, coefficient, categorie, volume_horaire, responsable_id, description FROM matieres WHERE etablissement_id = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $initial = array_values(array_filter($matieresAvant, fn($m) => (int)$m['etablissement_id'] === 1));
    check($apres === $initial, 'après T050→T049→T048 : table matieres de l\'établissement 1 identique à l\'état initial (volume horaire d\'origine rétabli)');

    section('9. TR-9 — outil --check sur la base réelle (lecture seule)');
    [$code, $out] = lance([$php, ROOT_PATH . '/database/tools/conduite_protection.php', '--check']);
    check($code === ($declencheursSource === 3 ? 0 : 1), "base réelle : code $code cohérent avec $declencheursSource déclencheur(s) présent(s)");
} finally {
    $copy->drop();
}

section('10. Base réelle');
check(DbCopy::allDataHashes($source) === $avantSource, 'base réelle inchangée (empreinte de toutes les tables)');
check((int)$source->query("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND trigger_name LIKE 'trg\\_matieres\\_conduite\\_%'")->fetchColumn() === $declencheursSource, 'aucun déclencheur ajouté ni retiré sur la base réelle');
check(count(array_filter($source->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN), fn($d) => str_starts_with($d, 'ecole_app_copy_'))) === 0, 'aucune base temporaire restante');

echo "\n" . ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : $passed/" . ($passed + $failed) . " assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
