<?php
/**
 * Tests — migration T056 (rattachement des comptes parents incomplets), sur COPIE jetable.
 *
 * Vérifie : état de départ réel (9 parents sans rattachement), rattachement démontré par les enfants, cas ambigus laissés intacts
 * et signalés (sans enfant, enfants dans deux établissements, contradiction users.etablissement_id), complétion d'un compte partiel,
 * accès réel des parents rattachés, idempotence, retour arrière exact (lignes ET journal), base réelle jamais écrite.
 *
 * AUCUNE migration n'est appliquée à la base réelle.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/ParentsRattachementMigrationTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);
define('BASE_URL', '/ecole_app');
require_once ROOT_PATH . '/tests/Support/DbCopy.php';
require_once ROOT_PATH . '/tests/Support/ConduiteCopy.php';

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

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) { continue; }
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}

$conn = DbCopy::connectionFromEnv(ROOT_PATH);
$source = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['db']};charset=utf8mb4", $conn['user'], $conn['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$avantSource = DbCopy::allDataHashes($source);
$migrationsSourceAvant = $source->query("SELECT migration FROM migrations_log WHERE migration = 'T056'")->fetchAll(PDO::FETCH_COLUMN);
$copy = DbCopy::create(ROOT_PATH);
if ($copy === null) { echo "SKIP : copie réelle impossible\n"; exit(0); }

try {
    $db = $copy->pdo();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('SET NAMES utf8mb4');
    ConduiteCopy::migrer($db, ROOT_PATH);
    $nom = $copy->name;
    $one = fn(string $sql) => $db->query($sql)->fetchColumn();
    $http = fn(string $role, int $uid, string $u) => ConduiteCopy::http(ROOT_PATH, $nom, $role, $uid, 'GET', "/ecole_app{$u}");
    $ins = function (string $t, array $row) use ($db): int {
        $db->prepare("INSERT INTO $t (" . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
        return (int)$db->lastInsertId();
    };
    $manquants = fn() => (int)$one(
        "SELECT COUNT(*) FROM users u WHERE u.role = 'parent' AND (
            NOT EXISTS (SELECT 1 FROM user_etablissements x WHERE x.user_id = u.id)
         OR NOT EXISTS (SELECT 1 FROM rbac_user_roles x JOIN rbac_roles r ON r.id = x.role_id WHERE x.user_id = u.id AND r.slug = 'parent')
         OR NOT EXISTS (SELECT 1 FROM user_roles_etab x WHERE x.user_id = u.id))"
    );
    $tablesRattachement = ['user_etablissements', 'rbac_user_roles', 'user_roles_etab'];
    $empreinte = function () use ($db, $tablesRattachement): array {
        $o = [];
        foreach ($tablesRattachement as $t) { $o[$t] = DbCopy::dataHash($db, $t); }
        return $o;
    };
    $m56 = require ROOT_PATH . '/database/migrations/T056_rattachement_comptes_parents.php';
    $executer = function () use ($m56, $db): string { ob_start(); ($m56['run'])($db); return (string)ob_get_clean(); };

    // ═════════════════════════════════════════════════════════════════════
    section('1. État de départ (données réelles copiées)');
    $parentsReels = range(281, 289);
    check($manquants() === 9, 'départ : 9 parents sans rattachement complet', (string)$manquants());
    check((int)$one("SELECT COUNT(*) FROM users WHERE id BETWEEN 281 AND 289 AND role = 'parent'") === 9, 'ce sont les comptes 281 à 289');
    check((int)$one("SELECT COUNT(*) FROM user_etablissements WHERE user_id BETWEEN 281 AND 289") === 0, 'aucune ligne user_etablissements pour eux');
    check((int)$one("SELECT COUNT(*) FROM rbac_user_roles WHERE user_id BETWEEN 281 AND 289") === 0, 'aucune ligne rbac_user_roles pour eux');
    check((int)$one("SELECT COUNT(*) FROM user_roles_etab WHERE user_id BETWEEN 281 AND 289") === 0, 'aucune ligne user_roles_etab pour eux');

    // ── Cas pièges ajoutés sur la copie ──
    $db->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('t056-e2', 'Ecole Deux T056', 'E2T', 'lycee')");
    $E2 = (int)$db->lastInsertId();
    $mk = fn(string $nomP, int $etab) => $ins('users', ['etablissement_id' => $etab, 'nom' => $nomP, 'prenom' => 'Test', 'email' => strtolower($nomP) . '@example.test', 'password' => 'x', 'role' => 'parent']);
    $eleve = fn(string $mat, int $etab, ?int $parent) => $ins('eleves', ['etablissement_id' => $etab, 'matricule' => $mat, 'nom' => 'E' . $mat, 'prenom' => 'Z', 'date_naissance' => '2012-01-01', 'sexe' => 'F', 'actif' => 1, 'parent_id' => $parent]);

    $PSANS    = $mk('PSANSENFANT', 1);                              // aucun enfant
    $PDEUX    = $mk('PDEUXETABS', 1); $eleve('T56-A', 1, $PDEUX); $eleve('T56-B', $E2, $PDEUX);   // enfants dans deux établissements
    $PCONTRA  = $mk('PCONTRADICT', $E2); $eleve('T56-C', 1, $PCONTRA);                            // users.etablissement_id ≠ celui de l'enfant
    $PPARTIEL = $mk('PPARTIEL', 1); $eleve('T56-D', 1, $PPARTIEL);                                // déjà un rôle RBAC, le reste manque
    $PPARTIEL_RBAC = (int)$one("SELECT id FROM rbac_roles WHERE slug = 'parent'");
    $db->exec("INSERT INTO rbac_user_roles (user_id, role_id, actif) VALUES ($PPARTIEL, $PPARTIEL_RBAC, 1)");
    $PE2      = $mk('PETABDEUX', $E2); $eleve('T56-E', $E2, $PE2);                                // parent d'un autre établissement, cohérent
    $avantAmbigus = [];
    foreach ([$PSANS, $PDEUX, $PCONTRA] as $id) { $avantAmbigus[$id] = (int)$one("SELECT COUNT(*) FROM user_etablissements WHERE user_id = $id") + (int)$one("SELECT COUNT(*) FROM rbac_user_roles WHERE user_id = $id") + (int)$one("SELECT COUNT(*) FROM user_roles_etab WHERE user_id = $id"); }
    $empreinteAvant = $empreinte();
    $nUe0 = (int)$one('SELECT COUNT(*) FROM user_etablissements'); $nRbac0 = (int)$one('SELECT COUNT(*) FROM rbac_user_roles'); $nUre0 = (int)$one('SELECT COUNT(*) FROM user_roles_etab');

    // ═════════════════════════════════════════════════════════════════════
    section('2. Application de T056 sur la copie');
    $sortie = $executer();
    foreach ($parentsReels as $id) {
        $ok = (int)$one("SELECT COUNT(*) FROM user_etablissements WHERE user_id = $id AND etablissement_id = 1 AND is_primary = 1 AND left_at IS NULL") === 1
           && (int)$one("SELECT COUNT(*) FROM rbac_user_roles ur JOIN rbac_roles r ON r.id = ur.role_id WHERE ur.user_id = $id AND r.slug = 'parent' AND ur.actif = 1") === 1
           && (int)$one("SELECT COUNT(*) FROM user_roles_etab x JOIN etab_roles r ON r.id = x.role_id WHERE x.user_id = $id AND x.etablissement_id = 1 AND r.code = 'parent'") === 1;
        check($ok, "parent #$id : établissement 1 (principal) + rôle RBAC parent + rôle d'établissement parent");
    }
    $ok = (int)$one("SELECT COUNT(*) FROM user_etablissements WHERE user_id = $PE2 AND etablissement_id = $E2") === 1;
    check($ok, 'parent d\'un second établissement : rattaché à SON établissement (jamais à l\'établissement 1)');
    check((int)$one("SELECT COUNT(*) FROM user_etablissements WHERE user_id = $PE2 AND etablissement_id = 1") === 0, 'aucun rattachement croisé');
    check((int)$one("SELECT COUNT(*) FROM user_etablissements WHERE user_id = $PPARTIEL") === 1
       && (int)$one("SELECT COUNT(*) FROM rbac_user_roles WHERE user_id = $PPARTIEL") === 1
       && (int)$one("SELECT COUNT(*) FROM user_roles_etab WHERE user_id = $PPARTIEL") === 1, 'compte partiel : complété sans doublon du rôle RBAC existant');

    section('3. Cas ambigus : laissés intacts et signalés');
    foreach ([[$PSANS, 'aucun enfant'], [$PDEUX, '2 établissements'], [$PCONTRA, 'users.etablissement_id']] as [$id, $motif]) {
        $apres = (int)$one("SELECT COUNT(*) FROM user_etablissements WHERE user_id = $id") + (int)$one("SELECT COUNT(*) FROM rbac_user_roles WHERE user_id = $id") + (int)$one("SELECT COUNT(*) FROM user_roles_etab WHERE user_id = $id");
        check($apres === $avantAmbigus[$id], "users #$id ($motif) : aucune ligne créée");
        check(str_contains($sortie, "[AMBIGU] users #$id"), "users #$id ($motif) : signalé [AMBIGU]");
    }
    check($manquants() === 3, 'il ne reste que les 3 comptes ambigus sans rattachement complet', (string)$manquants());
    check((int)$one('SELECT COUNT(*) FROM t056_parents_rattaches') === 11, 'journal : 11 comptes rattachés (9 réels + 1 d\'un autre établissement + 1 partiel)');

    section('4. Accès réel après rattachement');
    [$r, $b] = $http('parent', 281, '/parent/dashboard');
    check(($r['status'] ?? 0) === 200, 'parent #281 : /parent/dashboard répond 200', (string)($r['status'] ?? '?'));
    $nomEnfant = (string)$one('SELECT nom FROM eleves WHERE parent_id = 281 LIMIT 1');
    check(str_contains($b, $nomEnfant), 'parent #281 voit son enfant');
    $nomAutre = (string)$one('SELECT nom FROM eleves WHERE parent_id = 282 LIMIT 1');
    check(!str_contains($b, $nomAutre), 'parent #281 ne voit pas l\'enfant du parent #282');
    [$r] = $http('parent', 281, '/eleves');
    check(($r['status'] ?? 0) === 403, 'parent #281 : /eleves toujours refusé (403)');
    [$r] = $http('parent', 6, '/parent/dashboard');
    check(($r['status'] ?? 0) === 200, 'parent #6 (déjà rattaché) : inchangé');
    [$r] = $http('admin', 1, '/dashboard');
    check(($r['status'] ?? 0) === 200, 'admin #1 : inchangé');

    section('5. Idempotence');
    $empreinteApresRun1 = $empreinte();
    $sortie2 = $executer();
    check($empreinte() === $empreinteApresRun1, 'seconde exécution : aucune ligne ajoutée ni modifiée');
    check((int)$one('SELECT COUNT(*) FROM t056_parents_rattaches') === 11, 'seconde exécution : journal inchangé');
    check(substr_count($sortie2, '[AMBIGU]') === 3, 'seconde exécution : les 3 cas ambigus restent signalés');

    section('6. Retour arrière exact');
    ($m56['rollback'])($db);
    check($empreinte() === $empreinteAvant, 'retour arrière : les trois tables sont identiques à l\'état avant T056 (empreinte)');
    check((int)$one('SELECT COUNT(*) FROM user_etablissements') === $nUe0 && (int)$one('SELECT COUNT(*) FROM rbac_user_roles') === $nRbac0 && (int)$one('SELECT COUNT(*) FROM user_roles_etab') === $nUre0, 'nombres de lignes identiques');
    check((int)$one("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 't056_parents_rattaches'") === 0, 'journal supprimé');
    check((int)$one("SELECT COUNT(*) FROM rbac_user_roles WHERE user_id = $PPARTIEL") === 1, 'le rôle RBAC pré-existant du compte partiel est conservé');
    ($m56['rollback'])($db);
    check(true, 'retour arrière rejoué sans journal : aucune erreur');
    $executer();
    check($manquants() === 3, 'réapplication après retour arrière : même résultat');

    section('7. Refus si le rôle RBAC « parent » est absent');
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    ($m56['rollback'])($db);
    $db->exec("UPDATE rbac_roles SET slug = 'parent_x' WHERE slug = 'parent'");
    $refus = false;
    try { $executer(); } catch (RuntimeException $e) { $refus = str_contains($e->getMessage(), 'parent'); }
    check($refus, 'T056 refuse proprement (exception explicite) sans rôle RBAC parent');
    $db->exec("UPDATE rbac_roles SET slug = 'parent' WHERE slug = 'parent_x'");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
} finally {
    $copy->drop();
}

section('8. Base réelle');
check(DbCopy::allDataHashes($source) === $avantSource, 'base réelle inchangée (empreinte de toutes les tables)');
$migrationsSourceApres = $source->query("SELECT migration FROM migrations_log WHERE migration = 'T056'")->fetchAll(PDO::FETCH_COLUMN);
check($migrationsSourceApres === $migrationsSourceAvant, 'T056 non enregistrée ni appliquée sur la base réelle');
check(count(array_filter($source->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN), fn($d) => str_starts_with($d, 'ecole_app_copy_'))) === 0, 'aucune base temporaire restante');

echo "\n" . ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : $passed/" . ($passed + $failed) . " assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
