<?php
declare(strict_types=1);

/**
 * Tests unitaires — FilterService : robustesse et sécurité (Phase 13.2)
 *
 * Script autonome (même runner que PaginationTest.php et les autres tests de tests/Api, sans PHPUnit).
 * Les cas nominaux de chaque opérateur sont dans PaginationTest.php (F01–F30) ; ce fichier couvre ce qui
 * relève de la sécurité et de la robustesse : format attendu, injection, entrées malformées, combinaisons.
 *
 * Format des filtres : filter[champ]=valeur (égalité) ou filter[champ][opérateur]=valeur.
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Api/FilterTest.php
 */

$base = dirname(__DIR__, 2);
if (!defined('ROOT_PATH')) define('ROOT_PATH', $base);

spl_autoload_register(function (string $class) use ($base): void {
    $map = [
        'App\Shared\Api\\' => $base . '/app/Shared/Api/',
        'Core\\'           => $base . '/core/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $dir . $rel . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

$pass = 0; $fail = 0;
function t(string $id, bool $result, string $info = ''): void {
    global $pass, $fail;
    if ($result) { $pass++; echo "  PASS  $id $info\n"; }
    else         { $fail++; echo "  FAIL  $id $info\n"; }
}

use App\Shared\Api\FilterService;

function parseFilter(array $filter, array $whitelist, string $alias = ''): array
{
    $_GET    = ['filter' => $filter];
    $_POST   = [];
    $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];
    return (new FilterService())->parse(new \Core\Request(), $whitelist, $alias);
}

/** Exécute parse() et retourne [résultat|null, nom de l'exception|null]. */
function tryParse(array $filter, array $whitelist, string $alias = ''): array
{
    try { return [parseFilter($filter, $whitelist, $alias), null]; }
    catch (\Throwable $e) { return [null, get_class($e)]; }
}

// ─── FT01–FT04 : format de filtre ────────────────────────────────────────────

// Ancien format (op/value), abandonné : doit être ignoré, jamais interprété comme une condition.
$r = parseFilter(['nom' => ['op' => 'like', 'value' => 'Diop']], ['nom']);
t('FT01 ancien format op/value ignoré',      $r['conditions'] === [] && $r['bindings'] === []);

$r = parseFilter(['nom' => ['LIKE' => 'Diop']], ['nom']);
t('FT02 opérateur insensible à la casse',    ($r['conditions'][0] ?? '') === '`nom` LIKE ?' && $r['bindings'] === ['%Diop%']);

$r = parseFilter(['statut' => ['in' => 'a , b']], ['statut']);
t('FT03 IN : espaces retirés',               $r['bindings'] === ['a', 'b'] && $r['conditions'] === ['`statut` IN (?,?)']);

$r = parseFilter(['nom' => ['like' => 'Di'], 'statut' => 'actif'], ['nom', 'statut'], 'e');
t('FT04 plusieurs filtres : conditions et bindings alignés',
    $r['conditions'] === ['`e`.`nom` LIKE ?', '`e`.`statut` = ?'] && $r['bindings'] === ['%Di%', 'actif']);

// ─── FT05–FT09 : injection ───────────────────────────────────────────────────

$r = parseFilter(['nom' => '1 OR 1=1'], ['nom']);
t('FT05 valeur piégée : reste un paramètre lié', $r['conditions'] === ['`nom` = ?'] && $r['bindings'] === ['1 OR 1=1']);

$r = parseFilter(['nom`; DROP TABLE eleves; --' => 'x'], ['nom']);
t('FT06 nom de champ piégé : ignoré (liste blanche)', $r['conditions'] === [] && $r['bindings'] === []);

$r = parseFilter(['nom' => ['like; DROP' => 'x']], ['nom']);
t('FT07 opérateur piégé : ignoré',           $r['conditions'] === [] && $r['bindings'] === []);

[$r, $err] = tryParse(['nom' => ["x' OR '1'='1"]], ['nom']);
$ok = true; foreach ($r['conditions'] ?? [] as $c) { if (preg_match('/OR|DROP|--|;/i', $c)) { $ok = false; } }
t('FT08 aucune condition ne contient de fragment SQL issu de l\'entrée (et aucune exception)', $ok && $err === null, $err ? "[$err]" : '');

$r = parseFilter(['deleted_at' => ['null' => "'; DROP TABLE x; --"]], ['deleted_at']);
t('FT09 opérateur null : la valeur n\'apparaît ni dans la condition ni dans les bindings',
    $r['conditions'] === ['`deleted_at` IS NULL'] && $r['bindings'] === []);

// ─── FT10–FT15 : entrées malformées (comportement attendu : jamais d'exception) ─

$cas = [
    'FT10 filter[nom][]=x'              => ['nom' => ['x']],
    'FT11 filter[nom][like][]=x'        => ['nom' => ['like' => ['x']]],
    'FT12 filter[nom][in][]=a'          => ['nom' => ['in' => ['a', 'b']]],
    'FT13 filter[nom][gt][a][b]=x'      => ['nom' => ['gt' => ['a' => ['b' => 'x']]]],
];
foreach ($cas as $label => $malforme) {
    [$res, $err] = tryParse($malforme, ['nom'], 'e');
    t($label . ' : aucune exception', $err === null, $err ? "[$err]" : '');
    t($label . ' : ignoré, ni condition ni binding', $res !== null && $res['conditions'] === [] && $res['bindings'] === []);
}

// Une entrée malformée ne doit pas empêcher les filtres valides voisins.
[$res, $err] = tryParse(['nom' => ['like' => ['x']], 'statut' => 'actif'], ['nom', 'statut'], 'e');
t('FT14 filtre valide conservé malgré un voisin malformé : aucune exception', $err === null, $err ? "[$err]" : '');
t('FT15 filtre valide conservé malgré un voisin malformé : condition présente',
    $res !== null && $res['conditions'] === ['`e`.`statut` = ?'] && $res['bindings'] === ['actif']);

// ─── Résumé ───────────────────────────────────────────────────────────────────
echo "\nFilterTest: $pass/" . ($pass + $fail) . " PASS\n";
if ($fail > 0) exit(1);
