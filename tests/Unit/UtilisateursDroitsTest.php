<?php
/**
 * Tests — droits de modification des comptes utilisateurs, sur COPIE jetable.
 *
 * Règle : seuls l'administrateur et le directeur peuvent créer / modifier les comptes d'AUTRES utilisateurs ;
 * tout autre rôle ne peut modifier que ses données personnelles (/profile), sans jamais toucher rôle, statut, établissement ni autre compte.
 * Le directeur ne peut pas agir sur un compte administrateur (modifier, désactiver, supprimer).
 *
 * AUCUNE écriture sur la base réelle (empreinte avant / après).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/UtilisateursDroitsTest.php
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
$copy = DbCopy::create(ROOT_PATH);
if ($copy === null) { echo "SKIP : copie réelle impossible\n"; exit(0); }

try {
    $db = $copy->pdo();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('SET NAMES utf8mb4');
    ConduiteCopy::migrer($db, ROOT_PATH);
    $nom = $copy->name;
    // GARDE : toute écriture se fait sur la copie jetable, jamais sur la base réelle.
    if (!preg_match('/^ecole_app_copy_[a-f0-9]+$/', (string)$db->query('SELECT DATABASE()')->fetchColumn()) || $nom === $conn['db']) {
        fwrite(STDERR, "REFUS : la connexion n'est pas une copie jetable.
"); exit(2);
    }
    $http = fn(string $role, int $uid, string $m, string $u, array $post = []) => ConduiteCopy::http(ROOT_PATH, $nom, $role, $uid, $m, "/ecole_app{$u}", $post)[0];
    $ligne = fn(int $id) => json_encode($db->query("SELECT nom,prenom,email,role,actif,telephone,etablissement_id,password FROM users WHERE id = $id")->fetch());
    $nbUsers = fn() => (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();

    // Instantané EXACT des tables que les actions testées peuvent écrire ; restauré à l'identique après chaque scénario.
    // (Les requêtes HTTP s'exécutent dans des processus séparés qui valident leurs écritures : une transaction ne peut pas les englober.)
    $tablesEcrites = ['users', 'user_etablissements', 'rbac_user_roles', 'user_roles_etab'];
    $instantane = function () use ($db, $tablesEcrites): array {
        $o = []; foreach ($tablesEcrites as $t) { $o[$t] = $db->query("SELECT * FROM `$t`")->fetchAll(); } return $o;
    };
    $restaurer = function (array $snap) use ($db): void {
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (array_reverse(array_keys($snap)) as $t) { $db->exec("DELETE FROM `$t`"); }
        foreach ($snap as $t => $rows) {
            foreach ($rows as $r) {
                $cols = array_keys($r);
                $db->prepare("INSERT INTO `$t` (`" . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($r));
            }
        }
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    };
    $base = $instantane();
    $identique = fn() => $instantane() === $base;

    $acteurs = ['admin' => 1, 'directeur' => 2, 'secretaire' => 3, 'comptable' => 4, 'enseignant' => 5, 'parent' => 6, 'eleve' => 7];
    $autorises = ['admin', 'directeur'];
    $CIBLE = 4;   // compte comptable

    section('1. Modifier le compte d\'un AUTRE utilisateur');
    foreach ($acteurs as $role => $uid) {
        $avant = $ligne($CIBLE);
        $g = $http($role, $uid, 'GET', "/utilisateurs/$CIBLE/edit");
        $p = $http($role, $uid, 'POST', "/utilisateurs/$CIBLE", ['nom' => "MOD-$role", 'prenom' => 'x', 'email' => 'cible4@example.test', 'role' => 'comptable', 'telephone' => '0', 'actif' => '1']);
        $modifie = $ligne($CIBLE) !== $avant;
        if (in_array($role, $autorises, true)) {
            check(($g['status'] ?? 0) === 200 && $modifie, "$role : peut ouvrir et modifier le compte d'un autre utilisateur");
        } else {
            check(($g['status'] ?? 0) === 403 && ($p['status'] ?? 0) === 403 && !$modifie, "$role : refusé (403) et aucune donnée modifiée");
        }
        $restaurer($base);
    }

    section('2. Créer un compte');
    foreach ($acteurs as $role => $uid) {
        $n = $nbUsers();
        $r = $http($role, $uid, 'POST', '/utilisateurs/store', ['nom' => "NEW$role", 'prenom' => 'x', 'email' => "new-$role@example.test", 'role' => 'comptable', 'password' => 'Abcdef123!', 'password_confirmation' => 'Abcdef123!']);
        if (in_array($role, $autorises, true)) { check($nbUsers() === $n + 1, "$role : peut créer un compte"); }
        else { check(($r['status'] ?? 0) === 403 && $nbUsers() === $n, "$role : refusé (403), aucun compte créé"); }
    }

    section('3. Activer / désactiver et supprimer');
    foreach ($acteurs as $role => $uid) {
        $avant = $ligne(5);
        $r = $http($role, $uid, 'POST', '/utilisateurs/5/toggle-actif');
        $change = $ligne(5) !== $avant;
        if (in_array($role, $autorises, true)) { check($change, "$role : peut activer / désactiver un autre compte"); }
        else { check(($r['status'] ?? 0) === 403 && !$change, "$role : refusé (403)"); }
        $restaurer($base);
    }
    $r = $http('directeur', 2, 'POST', '/utilisateurs/5/delete');
    check(($r['status'] ?? 0) === 403 && (int)$db->query('SELECT COUNT(*) FROM users WHERE id = 5')->fetchColumn() === 1, 'directeur : la suppression reste réservée à l\'administrateur (403)');

    section('4. Le directeur ne peut pas agir sur le compte administrateur');
    $avant = $ligne(1);
    $http('directeur', 2, 'POST', '/utilisateurs/1/toggle-actif');
    check($ligne(1) === $avant, 'directeur : ne peut PAS désactiver l\'administrateur');
    $http('directeur', 2, 'POST', '/utilisateurs/1', ['nom' => 'PWNED', 'prenom' => 'x', 'email' => 'admin@ecole.dz', 'role' => 'admin', 'actif' => '1']);
    check($ligne(1) === $avant, 'directeur : ne peut PAS modifier l\'administrateur');
    $http('directeur', 2, 'POST', '/utilisateurs/1/delete');
    check((int)$db->query('SELECT COUNT(*) FROM users WHERE id = 1')->fetchColumn() === 1 && $ligne(1) === $avant, 'directeur : ne peut PAS supprimer l\'administrateur');
    $http('directeur', 2, 'POST', '/utilisateurs/2', ['nom' => 'Benali', 'prenom' => 'x', 'email' => 'dir@ecole.dz', 'role' => 'admin', 'actif' => '1']);
    check((string)$db->query('SELECT role FROM users WHERE id = 2')->fetchColumn() === 'directeur', 'directeur : ne peut PAS s\'auto-promouvoir administrateur');
    $http('admin', 1, 'POST', '/utilisateurs/5/toggle-actif');
    check((int)$db->query('SELECT actif FROM users WHERE id = 5')->fetchColumn() === 0, 'témoin : l\'administrateur, lui, peut désactiver un compte');
    $restaurer($base);

    section('5. Chacun modifie ses données personnelles, et seulement elles');
    foreach ($acteurs as $role => $uid) {
        $autre = $uid === 1 ? 2 : 1;
        $avantAutre = $ligne($autre);
        $roleAvant = (string)$db->query("SELECT role FROM users WHERE id = $uid")->fetchColumn();
        $r = $http($role, $uid, 'POST', '/profile', ['nom' => "Moi-$role", 'prenom' => 'p', 'email' => "moi-$role@example.test", 'telephone' => '0102',
            'role' => 'admin', 'actif' => '0', 'id' => (string)$autre, 'user_id' => (string)$autre, 'etablissement_id' => '99']);
        $s = $db->query("SELECT nom,email,telephone,role,actif,etablissement_id FROM users WHERE id = $uid")->fetch();
        check($s['nom'] === "Moi-$role" && $s['email'] === "moi-$role@example.test" && $s['telephone'] === '0102', "$role : modifie son propre nom, e-mail et téléphone");
        check($s['role'] === $roleAvant && (int)$s['actif'] === 1 && (int)$s['etablissement_id'] === 1, "$role : rôle, statut et établissement inchangés malgré l'injection");
        check($ligne($autre) === $avantAutre, "$role : aucun autre compte modifié (id / user_id injectés ignorés)");
        $restaurer($base);
    }
    $mdp = (string)$db->query('SELECT password FROM users WHERE id = 6')->fetchColumn();
    $http('parent', 6, 'POST', '/profile/password', ['current_password' => 'mauvais', 'password' => 'NouveauMdp123!', 'password_confirmation' => 'NouveauMdp123!']);
    check((string)$db->query('SELECT password FROM users WHERE id = 6')->fetchColumn() === $mdp, 'mot de passe : refusé sans le mot de passe actuel correct');
    $restaurer($base);
    section('5bis. Restauration');
    check($identique(), 'les quatre tables écrites sont identiques à l\'instantané initial (restauration exacte)');
} finally {
    $copy->drop();
}

section('6. Base réelle');
check(DbCopy::allDataHashes($source) === $avantSource, 'base réelle inchangée (empreinte de toutes les tables)');
check(count(array_filter($source->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN), fn($d) => str_starts_with($d, 'ecole_app_copy_'))) === 0, 'aucune base temporaire restante');

echo "\n" . ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : $passed/" . ($passed + $failed) . " assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
