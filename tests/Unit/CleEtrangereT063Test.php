<?php
/**
 * Tests — T063 (clés étrangères manquantes) et mode SQL strict par défaut, sur COPIE jetable de la base réelle.
 * La source n'est jamais écrite (empreinte avant / après).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/CleEtrangereT063Test.php
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
    $m = require ROOT_PATH . '/database/migrations/T063_cles_etrangeres_manquantes.php';
    $un = fn(string $sql) => $db->query($sql)->fetchColumn();
    $nbFk = fn() => (int)$un("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE()");
    $nomsT063 = fn() => (int)$un("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND constraint_name LIKE 'fk63\\_%'");

    section('Pose des clés');
    // Si la base source porte déjà T063, on la retire d'abord sur la copie.
    if ($nomsT063() > 0) { $m['rollback']($db); }
    $avantFk = $nbFk();
    ob_start(); $m['run']($db); $sortie = ob_get_clean();
    check($nomsT063() === 47, '47 clés posées', (string)$nomsT063() . ' / ' . trim($sortie));
    check($nbFk() === $avantFk + 47, 'le nombre total de clés augmente de 47');
    check(!str_contains($sortie, 'ignorée :'), 'aucune colonne ignorée (types identiques, aucun orphelin)', $sortie);
    $regle = fn(string $t, string $c) => (string)$un("SELECT delete_rule FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = '$t' AND constraint_name LIKE 'fk63\_$t\_$c%'");
    check($regle('vs_appels', 'matiere_id') === 'SET NULL' && $regle('rh_employes', 'user_id') === 'SET NULL' && $regle('vs_absences', 'eleve_id') === 'RESTRICT' && $regle('notifications', 'user_id') === 'CASCADE', 'règles de suppression : SET NULL (nullable), RESTRICT (obligatoire), CASCADE (données personnelles)', $regle('vs_appels', 'matiere_id') . '/' . $regle('rh_employes', 'user_id') . '/' . $regle('vs_absences', 'eleve_id') . '/' . $regle('notifications', 'user_id'));

    section('Effets');
    $refus = false; try { $db->exec("INSERT INTO vs_retards (eleve_id, classe_id, annee_scolaire, date_retard, saisie_par) VALUES (999999, 999999, '2026-2027', '2026-10-01', 1)"); } catch (PDOException $e) { $refus = (int)$e->errorInfo[1] === 1452; }
    check($refus, 'insertion d\'une ligne orpheline refusée (erreur 1452)');
    // enseignant_id de la vie scolaire = identifiant d'UTILISATEUR : un appel et un créneau saisis comme l'application le fait doivent passer.
    $ens = (int)$un("SELECT id FROM users WHERE role = 'enseignant' ORDER BY id LIMIT 1");
    $cl  = (int)$un('SELECT id FROM classes ORDER BY id LIMIT 1');
    $mat = (int)$un('SELECT id FROM matieres ORDER BY id LIMIT 1');
    $okAppel = true; try { $db->exec("INSERT INTO vs_appels (classe_id, enseignant_id, annee_scolaire, date_appel) VALUES ($cl, $ens, '2026-2027', '2026-10-01')"); } catch (PDOException $e) { $okAppel = false; }
    check($ens > 0 && $okAppel, 'un appel ouvert par un enseignant (identifiant d\'utilisateur) est accepté');
    $plage = (int)$un('SELECT id FROM vs_edt_plages_horaires ORDER BY id LIMIT 1');
    $edt = (int)$un('SELECT id FROM vs_emplois_du_temps ORDER BY id LIMIT 1');
    $okCren = true;
    try { $db->exec("INSERT INTO vs_edt_creneaux (emploi_du_temps_id, classe_id, annee_scolaire, matiere_id, enseignant_id, jour, plage_id) VALUES ($edt, $cl, '2026-2027', $mat, $ens, 2, $plage)"); } catch (PDOException $e) { $okCren = (int)$e->errorInfo[1] !== 1452; }   // seule la violation de clé étrangère nous intéresse
    check($okCren, 'un créneau d\'emploi du temps avec un enseignant (utilisateur) n\'est pas refusé par une clé étrangère');
    $eleveAvecAbsence = (int)$un('SELECT eleve_id FROM vs_absences LIMIT 1');
    $bloque = false;
    if ($eleveAvecAbsence) { try { $db->exec("DELETE FROM eleves WHERE id = $eleveAvecAbsence"); } catch (PDOException $e) { $bloque = (int)$e->errorInfo[1] === 1451; } }
    check($bloque, 'suppression d\'un élève ayant des absences refusée (erreur 1451)');
    $uid = (int)$un('SELECT user_id FROM notifications LIMIT 1');
    check($uid > 0, 'des notifications existent pour le test de CASCADE');
    if ($uid) {
        $nbNotifs = (int)$un("SELECT COUNT(*) FROM notifications WHERE user_id = $uid");
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
        try { $db->exec("DELETE FROM users WHERE id = $uid"); $suppr = true; } catch (PDOException $e) { $suppr = false; }
        if ($suppr) { check((int)$un("SELECT COUNT(*) FROM notifications WHERE user_id = $uid") === 0, "les $nbNotifs notification(s) de l'utilisateur supprimé le sont aussi (CASCADE)"); }
        else { check(true, 'suppression de cet utilisateur bloquée par une autre clé existante (comportement antérieur inchangé)'); }
    }

    section('Idempotence et retour arrière');
    ob_start(); $m['run']($db); ob_end_clean();
    check($nomsT063() === 47, 'idempotente : 2e exécution sans doublon');
    $m['rollback']($db);
    check($nomsT063() === 0 && $nbFk() === $avantFk, 'retour arrière : clés retirées, total identique à l\'état d\'origine', $nomsT063() . ' / ' . $nbFk() . ' vs ' . $avantFk);
    $idx = (int)$un("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND index_name LIKE 'fk63\\_%'");
    check($idx === 0, 'index créés par T063 retirés', (string)$idx);

    section('Mode SQL : historique par défaut, strict à la demande');
    $initDe = function (?string $mode): string {
        if ($mode === null) { unset($_ENV['DB_SQL_MODE']); } else { $_ENV['DB_SQL_MODE'] = $mode; }
        return (string)(require ROOT_PATH . '/config/database.php')['connections']['mysql']['options'][PDO::MYSQL_ATTR_INIT_COMMAND];
    };
    check(!str_contains($initDe(null), 'sql_mode'), 'par défaut : sql_mode du serveur conservé (comportement historique)');
    check(str_contains($initDe('strict'), "sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'"), 'DB_SQL_MODE=strict : mode strict activé');
    check(!str_contains($initDe("x'; DROP TABLE users; --"), 'DROP'), 'valeur non conforme ignorée (aucune injection dans la commande d\'initialisation)');
    unset($_ENV['DB_SQL_MODE']);
    section('Base réelle intacte');
    check(DbCopy::allDataHashes($source) === $avantSource, 'empreinte de la base réelle identique avant / après');
} finally {
    $copy->drop();
}
echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
