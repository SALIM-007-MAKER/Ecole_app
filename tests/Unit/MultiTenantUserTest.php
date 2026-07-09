<?php
/**
 * Tests Multi-Utilisateurs / Multi-Établissements — Phase 14.6
 *
 * 1. Utilisateur mono-tenant : comportement inchangé (1 seule appartenance
 *    → pas de picker, activation directe) — zéro régression.
 * 2. Utilisateur multi-tenant : appartenances multiples correctement
 *    listées, établissement principal en premier.
 * 3. Isolation stricte : isMember() rejette tout établissement auquel
 *    l'utilisateur n'appartient pas — jamais de confiance dans un id fourni
 *    par la requête.
 * 4. RBAC contextuel : le rôle et les permissions résolus changent
 *    correctement selon l'établissement actif (même utilisateur, deux
 *    rôles différents dans deux établissements).
 * 5. Mémorisation du dernier établissement utilisé.
 *
 * Toutes les données de test sont créées/détruites dans une transaction
 * PDO annulée en fin de script — zéro pollution.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/MultiTenantUserTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));

$envFile = ROOT_PATH . '/.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $val] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
}

spl_autoload_register(function (string $class) {
    $namespaces = ['Core\\' => ROOT_PATH . '/core/', 'App\\Models\\' => ROOT_PATH . '/app/Models/'];
    foreach ($namespaces as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($file)) { require $file; return; }
        }
    }
});

use App\Models\UserModel;
use Core\Database;
use Core\Tenant\TenantMembershipService;

$passed = 0;
$failed = 0;

function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        echo "\033[32m  ✓ $label\033[0m\n";
        $passed++;
    } else {
        echo "\033[31m  ✗ $label\033[0m\n";
        echo "      expected: " . var_export($expected, true) . "\n";
        echo "      actual:   " . var_export($actual, true) . "\n";
        $failed++;
    }
}
function assert_true(bool $v, string $label): void  { assert_eq(true, $v, $label); }
function assert_false(bool $v, string $label): void { assert_eq(false, $v, $label); }
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

$pdo = Database::getInstance()->getConnection();
$pdo->beginTransaction();

try {
    $membership = TenantMembershipService::make();
    $userModel  = new UserModel();

    // ══════════════════════════════════════════════════════════════════════
    section('1. Utilisateur mono-tenant (100% des utilisateurs seedés) — zéro régression');
    // ══════════════════════════════════════════════════════════════════════

    $adminId = (int)$pdo->query("SELECT id FROM users WHERE email='admin@ecole.dz'")->fetchColumn();
    $adminSchools = $membership->listForUser($adminId);
    assert_eq(1, count($adminSchools), 'admin@ecole.dz a exactement 1 établissement (comportement historique)');
    assert_true($adminSchools[0]['is_primary'], 'Son établissement unique est bien marqué principal');

    // ══════════════════════════════════════════════════════════════════════
    section('2. Utilisateur multi-tenant — appartenances et rôles distincts');
    // ══════════════════════════════════════════════════════════════════════

    $pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type, pays, statut)
                VALUES ('mt-test-b', 'École Multi-Test B', 'MT Test B', 'lycee', 'DZ', 'active')");
    $etabB = (int)$pdo->lastInsertId();
    $etabA = 1;

    $hash = password_hash('x', PASSWORD_BCRYPT);
    $pdo->exec(
        "INSERT INTO users (nom, prenom, email, password, role, actif, etablissement_id)
         VALUES ('MTUnitTest', 'User', 'mt.unittest@ecole.dz', " . $pdo->quote($hash) . ", 'enseignant', 1, 1)"
    );
    $userId = (int)$pdo->lastInsertId();

    $pdo->exec("INSERT INTO user_etablissements (user_id, etablissement_id, is_primary) VALUES ($userId, $etabA, 1)");
    $pdo->exec("INSERT INTO user_etablissements (user_id, etablissement_id, is_primary) VALUES ($userId, $etabB, 0)");

    $enseignantRoleId = (int)$pdo->query("SELECT id FROM etab_roles WHERE code='enseignant' AND etablissement_id IS NULL")->fetchColumn();
    $comptableRoleId  = (int)$pdo->query("SELECT id FROM etab_roles WHERE code='comptable' AND etablissement_id IS NULL")->fetchColumn();
    $pdo->exec("INSERT INTO user_roles_etab (user_id, etablissement_id, role_id) VALUES ($userId, $etabA, $enseignantRoleId)");
    $pdo->exec("INSERT INTO user_roles_etab (user_id, etablissement_id, role_id) VALUES ($userId, $etabB, $comptableRoleId)");

    $schools = $membership->listForUser($userId);
    assert_eq(2, count($schools), 'Utilisateur multi-tenant : 2 appartenances actives listées');
    assert_true($schools[0]['is_primary'], 'Établissement principal trié en premier');

    // ══════════════════════════════════════════════════════════════════════
    section('3. isMember() — isolation stricte, jamais de confiance aveugle');
    // ══════════════════════════════════════════════════════════════════════

    assert_true($membership->isMember($userId, $etabA), 'Membre de A : confirmé');
    assert_true($membership->isMember($userId, $etabB), 'Membre de B : confirmé');
    assert_false($membership->isMember($userId, 999999), 'PAS membre d\'un établissement inexistant/étranger : rejeté');
    assert_false($membership->isMember($adminId, $etabB), 'admin@ecole.dz n\'est PAS membre de B : rejeté (pas de fuite inter-utilisateurs)');

    // ══════════════════════════════════════════════════════════════════════
    section('4. RBAC contextuel — rôle et permissions changent selon l\'établissement actif');
    // ══════════════════════════════════════════════════════════════════════

    $roleInA = $membership->roleForUser($userId, $etabA);
    $roleInB = $membership->roleForUser($userId, $etabB);
    assert_eq('enseignant', $roleInA, 'Rôle dans A = enseignant');
    assert_eq('comptable', $roleInB, 'Rôle dans B = comptable (différent du rôle dans A)');

    $permsInA = $userModel->getPermissions($roleInA, $userId, $etabA);
    $permsInB = $userModel->getPermissions($roleInB, $userId, $etabB);
    assert_true(in_array('notes.edit', $permsInA, true), 'Permissions en A incluent "notes.edit" (rôle enseignant)');
    assert_false(in_array('notes.edit', $permsInB, true), 'Permissions en B N\'incluent PAS "notes.edit" (rôle comptable) — aucune fuite de permission entre contextes');
    assert_true(in_array('comptabilite.create', $permsInB, true), 'Permissions en B incluent "comptabilite.create" (rôle comptable)');

    // ══════════════════════════════════════════════════════════════════════
    section('5. Mémorisation du dernier établissement utilisé');
    // ══════════════════════════════════════════════════════════════════════

    assert_eq(null, $membership->getLastUsed($userId), 'Nouvel utilisateur : pas encore de dernier établissement mémorisé (avant toute connexion)');
    $membership->setLastUsed($userId, $etabB);
    assert_eq($etabB, $membership->getLastUsed($userId), 'setLastUsed(B) → getLastUsed() retourne bien B');

} finally {
    $pdo->rollBack();
    echo "\n(rollback effectué — aucune donnée de test persistée)\n";
}

// ── Résumé ──────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n\033[1m" . ($failed === 0 ? "\033[32m" : "\033[31m");
echo "Résultat : $passed/$total assertions passées";
echo "\033[0m\n\n";

if ($failed > 0) {
    exit(1);
}
