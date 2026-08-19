<?php
/**
 * Tests — migration de la navigation académique (sidebar) vers le moteur V2.
 *
 * Vérifie que MenuService pointe désormais "Notes" (admin/directeur) et
 * "Mes notes" (enseignant) vers /v2/academique/evaluations, que "Bulletins"
 * reste sur /bulletins (aucun contrôleur V2 "liste" n'existe — décision
 * documentée), que le RBAC est respecté (item absent si permission absente),
 * et que les routes /notes V1 ont bien été supprimées au nettoyage final
 * (App\Controllers\NoteController) tandis que /bulletins reste déclarée.
 *
 * Standalone, aucune dépendance DB — MenuService::getMenuStructure() est une
 * fonction pure (permissions passées en paramètre, pas lues en session).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/MenuServiceAcademiqueV2Test.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));

spl_autoload_register(function (string $class): void {
    $namespaces = [
        'Core\\'          => ROOT_PATH . '/core/',
        'App\\Services\\' => ROOT_PATH . '/app/Services/',
    ];
    foreach ($namespaces as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $dir . $rel . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

// MenuService::getMenuStructure() ne touche pas Core\Session tant que les
// permissions sont passées explicitement — stub minimal au cas où une autre
// méthode du fichier y ferait référence au chargement de la classe.
if (!class_exists('Core\Session')) {
    eval('namespace Core; class Session {
        public static function getUser(): ?array { return null; }
        public static function isLogged(): bool { return false; }
    }');
}

use App\Services\MenuService;

$passed = 0;
$failed = 0;

function assert_true(bool $val, string $label): void
{
    global $passed, $failed;
    if ($val) { echo "\033[32m  ✓ $label\033[0m\n"; $passed++; }
    else      { echo "\033[31m  ✗ $label\033[0m\n"; $failed++; }
}

function section(string $title): void { echo "\n\033[33m── $title\033[0m\n"; }

/** Cherche récursivement un item de menu par son label et retourne son url (ou null). */
function findMenuUrl(array $menus, string $label): ?string
{
    foreach ($menus as $item) {
        if (($item['label'] ?? null) === $label) return $item['url'] ?? null;
        if (!empty($item['children'])) {
            $found = findMenuUrl($item['children'], $label);
            if ($found !== null) return $found;
        }
    }
    return null;
}

// ─── Permissions réelles par rôle (config/permissions.php) ───────────────────

$permsFile = require ROOT_PATH . '/config/permissions.php';

// ─── 1. Nouvelles cibles V2 pour Notes / Mes notes ────────────────────────────

section('Notes / Mes notes -> /v2/academique/evaluations (V2)');

foreach (['admin' => 'Notes', 'directeur' => 'Notes', 'enseignant' => 'Mes notes'] as $role => $label) {
    $menu = MenuService::getMenuStructure($role, $permsFile[$role] ?? []);
    $url  = findMenuUrl($menu, $label);
    assert_true($url === '/v2/academique/evaluations', "$role: '$label' pointe vers /v2/academique/evaluations (trouvé: " . var_export($url, true) . ")");
}

// ─── 2. Bulletins reste sur /bulletins (pas de contrôleur V2 "liste") ─────────

section('Bulletins -> /bulletins (inchangé, décision documentée)');

foreach (['admin', 'directeur'] as $role) {
    $menu = MenuService::getMenuStructure($role, $permsFile[$role] ?? []);
    $url  = findMenuUrl($menu, 'Bulletins');
    assert_true($url === '/bulletins', "$role: 'Bulletins' toujours sur /bulletins (trouvé: " . var_export($url, true) . ")");
}

// ─── 3. RBAC — l'item disparaît si la permission est absente ─────────────────

section('RBAC — un rôle sans academique.evaluations.view ne voit pas le lien');

$permsSansAcademique = array_values(array_filter(
    $permsFile['admin'] ?? [],
    fn($p) => $p !== 'academique.evaluations.view' && $p !== 'notes.view'
));
$menuSansPerm = MenuService::getMenuStructure('admin', $permsSansAcademique);
assert_true(findMenuUrl($menuSansPerm, 'Notes') === null, "admin sans notes.view/academique.evaluations.view -> pas de lien 'Notes'");

// Rôles sans le groupe Académique du tout (secrétaire/comptable) : on vérifie
// juste qu'on ne casse rien s'ils n'ont pas la permission.
$menuComptable = MenuService::getMenuStructure('comptable', $permsFile['comptable'] ?? []);
assert_true(findMenuUrl($menuComptable, 'Notes') === null, "comptable ne voit pas 'Notes' (pas de academique.evaluations.view)");

// ─── 4. RBAC — les 3 rôles migrés possèdent bien la permission cible ─────────

section('config/permissions.php — academique.evaluations.view présent pour les rôles migrés');

foreach (['admin', 'directeur', 'enseignant'] as $role) {
    assert_true(
        in_array('academique.evaluations.view', $permsFile[$role] ?? [], true),
        "$role possède academique.evaluations.view"
    );
}

// ─── 5. Routes bulletins conservées / routes notes V1 supprimées (nettoyage final) ─

section('/bulletins toujours déclarée — /notes V1 supprimée au nettoyage final');

$routesSrc = file_get_contents(ROOT_PATH . '/config/routes.php');
assert_true(str_contains($routesSrc, "\$router->get('/bulletins',"), "config/routes.php contient toujours : \$router->get('/bulletins',");

foreach (["\$router->get('/notes',", "\$router->get('/notes/controles',", "\$router->get('/notes/saisie/{id}',", "NoteController@"] as $needle) {
    assert_true(!str_contains($routesSrc, $needle), "config/routes.php ne contient plus (nettoyage final) : $needle");
}

// ─── 6. Nouvelle route V2 bien déclarée ──────────────────────────────────────

section('Route V2 cible bien déclarée');

$academiqueRoutesSrc = file_get_contents(ROOT_PATH . '/app/Modules/Academique/routes.php');
assert_true(
    str_contains($academiqueRoutesSrc, "\$router->get('/v2/academique/evaluations',"),
    "app/Modules/Academique/routes.php déclare GET /v2/academique/evaluations"
);

// ─── Résumé ────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n\033[1m" . ($failed === 0 ? "\033[32m" : "\033[31m");
echo "Résultat : $passed/$total assertions passées";
echo "\033[0m\n\n";

if ($failed > 0) exit(1);
