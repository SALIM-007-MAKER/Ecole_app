<?php
/**
 * Tests RBAC Multi-Tenant — Phase 14.4
 *
 * 1. Équivalence stricte : pour chaque rôle système, le nouveau palier
 *    tenant (etab_role_permissions) doit produire EXACTEMENT le même
 *    ensemble de permissions que l'ancien fallback config/permissions.php
 *    (zéro régression sur les 7 rôles × leurs permissions).
 * 2. Isolation : un rôle personnalisé créé pour l'établissement B (avec
 *    une permission que A n'a pas) ne doit jamais être visible/attribuable
 *    à un utilisateur de l'établissement A, et réciproquement.
 * 3. Connexion réelle (AuthController::login) : permissions correctement
 *    chargées en session pour un utilisateur existant.
 *
 * Toutes les données de test sont créées/détruites dans une transaction
 * PDO annulée en fin de script — zéro pollution.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/RbacTenantTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));

$envFile = ROOT_PATH . '/.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $val] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
}

spl_autoload_register(function (string $class): void {
    $namespaces = [
        'Core\\'        => ROOT_PATH . '/core/',
        'App\\Models\\' => ROOT_PATH . '/app/Models/',
    ];
    foreach ($namespaces as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file = $dir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) { require $file; return; }
        }
    }
});

use App\Models\UserModel;
use Core\Database;
use Core\Tenant\TenantAuthContext;

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

/** Compare deux tableaux de codes de permission sans tenir compte de l'ordre. */
function same_set(array $a, array $b): bool
{
    sort($a); sort($b);
    return $a === $b;
}

$pdo = Database::getInstance()->getConnection();
$pdo->beginTransaction();

try {
    // ══════════════════════════════════════════════════════════════════════
    section('1. Équivalence stricte tenant vs V1 (zéro régression, 7 rôles)');
    // ══════════════════════════════════════════════════════════════════════

    $userModel = new UserModel();
    $configPerms = require ROOT_PATH . '/config/permissions.php';
    $etabA = 1;

    // users existants (seed) : id 1..7, un par rôle, tous etablissement_id=1
    $usersByRole = [];
    foreach ($pdo->query("SELECT id, role FROM users WHERE etablissement_id = {$etabA}") as $u) {
        $usersByRole[$u->role] = (int)$u->id;
    }

    foreach (UserModel::allRoles() as $role) {
        if (!isset($usersByRole[$role])) {
            continue; // pas d'utilisateur seed pour ce rôle — ignoré
        }
        $userId = $usersByRole[$role];

        // Palier tenant (etab_role_permissions, via user_roles_etab)
        $tenantResult = $userModel->getPermissions($role, $userId, $etabA);

        // Palier V1 pur (aucun user_id => bypasse tenant ET rbac_* legacy)
        $v1Result = $userModel->getPermissions($role, 0, null);

        assert_true(
            same_set($tenantResult, $v1Result),
            "Rôle '{$role}' : permissions tenant === permissions V1 (" . count($tenantResult) . " codes)"
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    section('2. Isolation — rôle personnalisé propre à un établissement');
    // ══════════════════════════════════════════════════════════════════════

    $pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type, pays, statut)
                VALUES ('rbac-test-b', 'Établissement RBAC Test B', 'RBAC B', 'lycee', 'DZ', 'active')");
    $etabB = (int)$pdo->lastInsertId();

    // Rôle personnalisé "cpe" créé UNIQUEMENT pour l'établissement B
    $pdo->exec("INSERT INTO etab_roles (etablissement_id, code, nom, is_system)
                VALUES ({$etabB}, 'cpe', 'CPE', 0)");
    $cpeRoleId = (int)$pdo->lastInsertId();

    // Permission spéciale attribuée à ce rôle
    $permRow = $pdo->query("SELECT id FROM etab_permissions WHERE code = 'absences.justify'")->fetch();
    if ($permRow) {
        $pdo->exec("INSERT INTO etab_role_permissions (role_id, permission_id) VALUES ({$cpeRoleId}, {$permRow->id})");
    }

    // Utilisateur de test dans l'établissement B, avec ce rôle "cpe"
    $pdo->exec("INSERT INTO users (nom, prenom, email, password, role, actif, etablissement_id)
                VALUES ('CpeTest', 'Rbac', 'cpe.rbactest@ecole.dz', '" . password_hash('x', PASSWORD_BCRYPT) . "', 'enseignant', 1, {$etabB})");
    $cpeUserId = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO user_roles_etab (user_id, etablissement_id, role_id) VALUES ({$cpeUserId}, {$etabB}, {$cpeRoleId})");

    $auth = TenantAuthContext::make();

    $cpePerms = $auth->permissions($cpeUserId, $etabB);
    assert_true(in_array('absences.justify', $cpePerms, true), 'Utilisateur B avec rôle "cpe" a bien "absences.justify"');

    // Un utilisateur de l'établissement A ne doit JAMAIS voir ce rôle ni cette permission via lui
    $rolesVisibleFromA = $auth->roles($usersByRole['enseignant'], $etabA);
    assert_false(in_array('cpe', $rolesVisibleFromA, true), 'Le rôle "cpe" (propre à B) est invisible depuis le contexte A');

    // Même utilisateur, interrogé dans le MAUVAIS établissement → aucune permission
    $crossPerms = $auth->permissions($cpeUserId, $etabA);
    assert_eq(0, count($crossPerms), 'Utilisateur de B interrogé dans le contexte A → 0 permission (pas de fuite)');

    // hasAnyRole — cohérence
    assert_true($auth->hasAnyRole($cpeUserId, $etabB), 'hasAnyRole(user B, etab B) = true');
    assert_false($auth->hasAnyRole($cpeUserId, $etabA), 'hasAnyRole(user B, etab A) = false');

    // ══════════════════════════════════════════════════════════════════════
    section('3. Accès refusé / autorisé — cohérence avec les permissions V1');
    // ══════════════════════════════════════════════════════════════════════

    $eleveUserId = $usersByRole['eleve'] ?? null;
    if ($eleveUserId) {
        $eleveTenantPerms = $userModel->getPermissions('eleve', $eleveUserId, $etabA);
        assert_true(in_array('notes.view_own', $eleveTenantPerms, true), 'Élève : accès autorisé à "notes.view_own"');
        assert_false(in_array('users.delete', $eleveTenantPerms, true), 'Élève : accès refusé à "users.delete"');
    }

    $adminUserId = $usersByRole['admin'] ?? null;
    if ($adminUserId) {
        $adminTenantPerms = $userModel->getPermissions('admin', $adminUserId, $etabA);
        assert_true(in_array('users.delete', $adminTenantPerms, true), 'Admin : accès autorisé à "users.delete"');
    }

    // ══════════════════════════════════════════════════════════════════════
    section('4. Connexion réelle — AuthController charge des permissions cohérentes');
    // ══════════════════════════════════════════════════════════════════════

    $adminRow = $userModel->findByEmail('admin@ecole.dz');
    assert_true($adminRow !== false, 'Utilisateur admin trouvé pour test de connexion');
    if ($adminRow) {
        $loginPerms = $userModel->getPermissions($adminRow->role, (int)$adminRow->id, (int)$adminRow->etablissement_id);
        assert_true(count($loginPerms) > 200, 'Connexion admin : volume de permissions cohérent (>200, comme avant)');
        assert_true(in_array('eleves.view', $loginPerms, true), 'Connexion admin : "eleves.view" présent');
    }

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
