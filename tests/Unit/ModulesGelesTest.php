<?php
/**
 * Tests — modules gelés pour la V1 (RH, API REST)
 *
 * Vérifie que la désactivation de RH et API est complète et cohérente, sans casser le noyau :
 *   - config/modules.php : RH et API inactifs, modules du noyau actifs ;
 *   - config/events.php : aucun listener RH enregistré tant que RH est inactif, mais le fichier
 *     config/events.rh.php conserve les 49 événements RH (réactivation = repasser enabled à true) ;
 *   - MenuService : aucune entrée /v2/rh pour aucun rôle, le reste du menu est inchangé ;
 *   - dépendances : les classes Scolarité utilisées par Académique / par l'API gelée restent chargeables.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/ModulesGelesTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);

spl_autoload_register(function (string $class): void {
    $map = ['Core\\' => '/core/', 'App\\Modules\\' => '/app/Modules/', 'App\\' => '/app/'];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = ROOT_PATH . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) { require_once $file; return; }
        }
    }
});

$passed = 0;
$failed = 0;

function assert_true(bool $v, string $label): void
{
    global $passed, $failed;
    if ($v) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else    { $failed++; echo "\033[31m  ✗ $label\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

// ─── 1. Configuration des modules ────────────────────────────────────────────
section('1. config/modules.php');

$modules = require ROOT_PATH . '/config/modules.php';
assert_true($modules['rh']['enabled'] === false,  'RH désactivé');
assert_true($modules['api']['enabled'] === false, 'API REST désactivée');
foreach (['academique', 'finance', 'vie_scolaire'] as $core) {
    assert_true($modules[$core]['enabled'] === true, "Module du noyau « $core » toujours actif");
}
assert_true(is_file($modules['rh']['routes']) && is_file($modules['api']['routes']), 'Code RH et API conservé (fichiers de routes présents)');

// ─── 2. Événements ───────────────────────────────────────────────────────────
section('2. Listeners d\'événements');

$events   = require ROOT_PATH . '/config/events.php';
$rhEvents = array_filter(array_keys($events), static fn(string $k): bool => str_contains($k, 'Modules\\RH\\'));
assert_true($rhEvents === [], 'Aucun événement RH enregistré tant que RH est désactivé');
assert_true(count($events) > 150, 'Les événements des autres modules restent enregistrés (' . count($events) . ')');

$rhFile   = require ROOT_PATH . '/config/events.rh.php';
$allRh    = count($rhFile) > 0 && count(array_filter(array_keys($rhFile), static fn(string $k): bool => str_contains($k, 'Modules\\RH\\'))) === count($rhFile);
assert_true($allRh, 'events.rh.php ne contient que des événements RH (' . count($rhFile) . ')');
assert_true(count($rhFile) === 49, 'events.rh.php conserve les 49 événements RH d\'origine');
assert_true(count(array_intersect_key($events, $rhFile)) === 0, 'Aucun chevauchement entre events.php et events.rh.php');

$listenersOk = true;
foreach ($events as $listeners) {
    foreach ($listeners as $l) { if (!is_object($l)) { $listenersOk = false; } }
}
assert_true($listenersOk, 'Tous les listeners enregistrés sont instanciables');

// ─── 3. Menu ─────────────────────────────────────────────────────────────────
section('3. MenuService');

$collect = static function (array $v) use (&$collect): array {
    $perms = [];
    foreach ($v as $k => $x) {
        if ($k === 'permissions' && is_array($x)) { foreach ($x as $p) { $perms[$p] = true; } }
        if (is_array($x)) { $perms += $collect($x); }
    }
    return $perms;
};
$def = new ReflectionMethod(\App\Services\MenuService::class, 'getMenusDefinition');
$def->setAccessible(true);
$allPerms = array_keys($collect($def->invoke(null)));

$urls = static function (array $menus) use (&$urls): array {
    $o = [];
    foreach ($menus as $m) {
        if (!empty($m['url']))      { $o[] = $m['url']; }
        if (!empty($m['children'])) { $o = array_merge($o, $urls($m['children'])); }
    }
    return $o;
};

$rhVisible = 0;
foreach (['admin', 'directeur', 'secretaire', 'comptable', 'enseignant', 'parent', 'eleve'] as $role) {
    $links = $urls(\App\Services\MenuService::getMenuStructure($role, $allPerms));
    $rhVisible += count(array_filter($links, static fn(string $u): bool => str_starts_with($u, '/v2/rh')));
}
assert_true($rhVisible === 0, 'Aucune entrée /v2/rh dans le menu, tous rôles confondus (permissions maximales)');

$adminLinks = $urls(\App\Services\MenuService::getMenuStructure('admin', $allPerms));
assert_true(count(array_filter($adminLinks, static fn(string $u): bool => str_starts_with($u, '/v2/finance'))) > 0, 'Admin : menu Finance conservé');
assert_true(count(array_filter($adminLinks, static fn(string $u): bool => str_starts_with($u, '/v2/vie-scolaire'))) > 0, 'Admin : menu Vie scolaire conservé');
assert_true(count(array_filter($adminLinks, static fn(string $u): bool => str_contains($u, 'academique') || $u === '/bulletins')) > 0, 'Admin : notes / bulletins conservés');

$menuNoGroupEmpty = true;
foreach (\App\Services\MenuService::getMenuStructure('admin', $allPerms) as $item) {
    if (array_key_exists('children', $item) && $item['children'] === [] && empty($item['url'])) { $menuNoGroupEmpty = false; }
}
assert_true($menuNoGroupEmpty, 'Aucun groupe de menu vide après masquage');

// ─── 4. Dépendances des modules actifs vers des modules désactivés ───────────
section('4. Dépendances vers Scolarité (désactivé) et API gelée');

foreach ([
    \App\Modules\Scolarite\Repositories\InscriptionRepository::class,   // NoteService, EvaluationService (Académique)
    \App\Modules\Scolarite\Repositories\FamilleRepository::class,       // BulletinPolicy (Académique)
    \App\Modules\Scolarite\Services\EleveService::class,                // EleveApiController (API gelée)
    \App\Modules\Api\Controllers\V1\Scolarite\EleveApiController::class, // code API conservé
] as $class) {
    assert_true(class_exists($class), 'Classe chargeable : ' . substr($class, strrpos($class, '\\Modules\\') + 1));
}

echo "\n";
if ($failed === 0) {
    echo "\033[1;32mRésultat : {$passed}/{$passed} assertions passées\033[0m\n";
    exit(0);
}
echo "\033[1;31mRésultat : {$passed} OK / {$failed} FAIL\033[0m\n";
exit(1);
