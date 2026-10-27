<?php
/**
 * Tests — audit BD : migrations T060 (tables de présences), T061 (collations, index redondants) et T062 (orphelins archivés),
 * sur COPIE jetable de la base réelle. La source n'est jamais écrite (empreinte avant / après).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/AuditDbMigrationsTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use Tests\Support\DbCopy;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) { continue; }
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
$conn = DbCopy::connectionFromEnv(ROOT_PATH);
$source = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['db']};charset=utf8mb4", $conn['user'], $conn['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$avantSource = DbCopy::allDataHashes($source);
$copy = DbCopy::create(ROOT_PATH);
if ($copy === null) { echo "SKIP : copie réelle impossible\n"; exit(0); }

try {
    $db = $copy->pdo();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('SET NAMES utf8mb4');
    $m = fn(string $id) => require ROOT_PATH . '/database/migrations/' . array_values(array_filter(scandir(ROOT_PATH . '/database/migrations'), fn($f) => str_starts_with($f, $id . '_')))[0];
    $un = fn(string $sql) => $db->query($sql)->fetchColumn();
    $orph = fn() => (int)$un("SELECT (SELECT COUNT(*) FROM notification_logs x WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.id=x.user_id))
        + (SELECT COUNT(*) FROM vs_incidents_discipline x WHERE NOT EXISTS (SELECT 1 FROM vs_dossiers_discipline d WHERE d.id=x.dossier_id))
        + (SELECT COUNT(*) FROM finance_journaux_caisse x WHERE x.session_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM finance_sessions_caisse s WHERE s.id=x.session_id))");
    $nonUnicode = fn() => (int)$un("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_collation<>'utf8mb4_unicode_ci'");
    $tbl = fn(string $t) => (int)$un("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='$t'");
    $idx = fn(string $t, string $i) => (int)$un("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='$t' AND index_name='$i'");

    section('État initial de la copie (ramenée à l\'état « avant T060–T062 »)');
    // La base réelle peut déjà porter ces migrations : on les retire sur la COPIE, puis on recrée les anomalies.
    if ($tbl('audit_orphelins_archive')) { $m('T062')['rollback']($db); $db->exec('DROP TABLE audit_orphelins_archive'); }
    if ($tbl('vs_appels')) { $db->exec('DELETE FROM vs_presences_historique'); $db->exec('DELETE FROM vs_presences'); $db->exec('DELETE FROM vs_appels'); $m('T060')['rollback']($db); }
    $m('T061')['rollback']($db);
    $db->exec('UPDATE notification_logs SET user_id = 999999 ORDER BY id LIMIT 3');
    $db->exec("INSERT INTO vs_incidents_discipline (dossier_id, categorie_id, gravite, description, date_incident, signale_par, statut) VALUES (999999, 2, 'moyen', 'orphelin (test)', '2026-07-11', 1, 'ouvert')");
    $orph0 = $orph();
    $tables0 = (int)$un("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'");
    $hasPres0 = $tbl('vs_appels');
    check($nonUnicode() === 7, '7 tables hors unicode_ci avant T061', (string)$nonUnicode());
    $etatInitialCollation = $nonUnicode();

    section('T060 — tables de présences');
    $t60 = $m('T060'); $t60['run']($db);
    check($tbl('vs_appels') && $tbl('vs_presences') && $tbl('vs_presences_historique'), 'les 3 tables existent');
    $t60['run']($db);
    check(true, 'idempotente (2e exécution sans erreur)');
    $db->exec("INSERT INTO vs_appels (classe_id, enseignant_id, annee_scolaire, date_appel) SELECT id, 1, '2026-2027', '2026-10-01' FROM classes LIMIT 1");
    $refus = false; try { $t60['rollback']($db); } catch (RuntimeException $e) { $refus = true; }
    check($refus && $tbl('vs_appels') === 1, 'retour arrière refusé si des pointages existent');
    $db->exec('DELETE FROM vs_appels');
    $t60['rollback']($db);
    check(!$tbl('vs_appels') && !$tbl('vs_presences') && !$tbl('vs_presences_historique'), 'retour arrière (tables vides) : supprimées');
    $t60['run']($db);

    section('T061 — collations et index redondants');
    $t61 = $m('T061'); $t61['run']($db);
    check($nonUnicode() === 0, 'plus aucune table hors utf8mb4_unicode_ci', (string)$nonUnicode());
    check(!$idx('password_resets', 'idx_pr_token') && !$idx('platform_operators', 'idx_operator_user') && !$idx('rbac_roles', 'idx_role_slug'), '3 index redondants supprimés');
    check($idx('password_resets', 'token') && $idx('platform_operators', 'uq_operator_user') && $idx('rbac_roles', 'slug'), 'index UNIQUE conservés');
    $n = (int)$un('SELECT COUNT(*) FROM notes n JOIN controles c ON c.id = n.controle_id');
    check($n >= 0, 'jointure notes/controles opérationnelle');
    $t61['run']($db);
    check(true, 'idempotente');
    $t61['rollback']($db);
    check($nonUnicode() === 7 && $idx('rbac_roles', 'idx_role_slug'), 'retour arrière : 7 tables en 0900_ai_ci et index recréés', (string)$nonUnicode());
    $t61['run']($db);

    section('T062 — orphelins');
    check($orph0 >= 2, 'orphelins présents avant T062 (' . $orph0 . ')');
    $t62 = $m('T062'); $t62['run']($db);
    check($orph() === 0, 'plus aucun orphelin', (string)$orph());
    check((int)$un("SELECT COUNT(*) FROM audit_orphelins_archive WHERE migration='T062'") === $orph0, 'chaque ligne archivée avant retrait');
    $t62['run']($db);
    check((int)$un("SELECT COUNT(*) FROM audit_orphelins_archive WHERE migration='T062'") === $orph0, 'idempotente : pas de doublon d\'archive');
    $t62['rollback']($db);
    check($orph() === $orph0, 'retour arrière : lignes retirées réinsérées (' . $orph() . ' / ' . $orph0 . ')');

    section('Base réelle intacte');
    check(DbCopy::allDataHashes($source) === $avantSource, 'empreinte de la base réelle identique avant / après');
} finally {
    $copy->drop();
}
echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
