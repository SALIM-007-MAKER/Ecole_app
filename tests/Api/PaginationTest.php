<?php
declare(strict_types=1);

/**
 * Tests unitaires — PaginationService + FilterService + SortService (Phase 13.2)
 */

$base = dirname(__DIR__, 2);

if (!defined('ROOT_PATH')) define('ROOT_PATH', $base);

spl_autoload_register(function (string $class) use ($base): void {
    $map = [
        'App\Shared\Api\\'  => $base . '/app/Shared/Api/',
        'App\Modules\Api\\' => $base . '/app/Modules/Api/',
        'Core\\'            => $base . '/core/',
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

use App\Shared\Api\PaginationService;
use App\Shared\Api\FilterService;
use App\Shared\Api\SortService;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeRequest(array $get = [], array $server = []): \Core\Request
{
    $_GET    = $get;
    $_POST   = [];
    $_SERVER = array_merge(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'], $server);
    return new \Core\Request();
}

// ─── P01–P17 : PaginationService ─────────────────────────────────────────────

$pager = new PaginationService();

$req = makeRequest([]);
$p   = $pager->parse($req);
t('P01 default page=1',         $p['page'] === 1);
t('P02 default per_page=20',    $p['per_page'] === 20);
t('P03 default offset=0',       $p['offset'] === 0);

$req = makeRequest(['page' => '3', 'per_page' => '50']);
$p   = $pager->parse($req);
t('P04 custom page',            $p['page'] === 3);
t('P05 custom per_page',        $p['per_page'] === 50);
t('P06 offset = (page-1)*pp',   $p['offset'] === 100);

$req = makeRequest(['per_page' => '999']);
$p   = $pager->parse($req);
t('P07 per_page capped 100',    $p['per_page'] === 100);

$req = makeRequest(['page' => '-5']);
$p   = $pager->parse($req);
t('P08 page min=1',             $p['page'] === 1);

$req  = makeRequest(['page' => '2', 'per_page' => '10']);
$p    = $pager->parse($req);
$meta = $pager->buildMeta(55, $p['page'], $p['per_page']);
t('P09 meta total',             $meta['total'] === 55);
t('P10 meta last_page=6',       $meta['last_page'] === 6);
t('P11 meta from',              $meta['from'] === 11);
t('P12 meta to',                $meta['to'] === 20);

$req   = makeRequest(['page' => '6', 'per_page' => '10']);
$p     = $pager->parse($req);
$meta2 = $pager->buildMeta(55, $p['page'], $p['per_page']);
t('P13 last page to=55',        $meta2['to'] === 55);

$links = $pager->buildLinks('/api/v1/eleves', 55, $p['page'], $p['per_page']);
t('P14 has self',               str_contains($links['self'], 'page=6'));
t('P15 no next on last page',   $links['next'] === null);

$req2  = makeRequest(['page' => '3', 'per_page' => '10']);
$p3    = $pager->parse($req2);
$l3    = $pager->buildLinks('/api/v1/eleves', 55, $p3['page'], $p3['per_page']);
t('P16 has next',               str_contains($l3['next'], 'page=4'));
t('P17 has prev',               str_contains($l3['prev'], 'page=2'));

// ─── F01–F14 : FilterService ─────────────────────────────────────────────────

$filter = new FilterService();

$req    = makeRequest([]);
$result = $filter->parse($req, ['nom', 'statut'], 'e');
t('F01 no filters empty',       empty($result['conditions']));
t('F02 no bindings empty',      empty($result['bindings']));

$req    = makeRequest(['filter' => ['statut' => 'actif']]);
$result = $filter->parse($req, ['statut'], 'e');
t('F03 simple eq condition',    count($result['conditions']) === 1);
t('F04 eq binding',             $result['bindings'] === ['actif']);
t('F05 alias prefix',           str_contains($result['conditions'][0], '`e`.`statut`'));

// Format: filter[field][operator]=value  (e.g., filter[nom][like]=Diop)
$req    = makeRequest(['filter' => ['nom' => ['like' => 'Diop']]]);
$result = $filter->parse($req, ['nom'], 'e');
t('F06 like condition',         str_contains($result['conditions'][0] ?? '', 'LIKE'));
t('F07 like wraps %',           $result['bindings'] === ['%Diop%']);

$req    = makeRequest(['filter' => ['statut' => ['in' => 'actif,inactif']]]);
$result = $filter->parse($req, ['statut'], '');
t('F08 in condition',           str_contains($result['conditions'][0] ?? '', 'IN'));
t('F09 in binding count=2',     count($result['bindings']) === 2);

$req    = makeRequest(['filter' => ['deleted_at' => ['null' => '']]]);
$result = $filter->parse($req, ['deleted_at'], '');
t('F10 null operator',          str_contains($result['conditions'][0] ?? '', 'IS NULL'));
t('F11 null no bindings',       empty($result['bindings']));

$req    = makeRequest(['filter' => ['injected_field' => 'hack']]);
$result = $filter->parse($req, ['nom'], 'e');
t('F12 unknown field ignored',  empty($result['conditions']));

$req    = makeRequest(['filter' => ['nom' => ['BADOP' => 'x']]]);
$result = $filter->parse($req, ['nom'], '');
t('F13 unknown op ignored',     empty($result['conditions']));

$req    = makeRequest(['filter' => ['nom' => ['starts' => 'Ali']]]);
$result = $filter->parse($req, ['nom'], '');
t('F14 starts binding',         $result['bindings'] === ['Ali%']);

// ─── S01–S06 : SortService ───────────────────────────────────────────────────

$sorter = new SortService();

$req = makeRequest([]);
$sql = $sorter->parse($req, ['nom', 'created_at'], 'nom', 'e');
t('S01 default sort nom',       str_contains($sql, '`e`.`nom`'));
t('S02 default asc',            str_contains($sql, 'ASC'));

$req = makeRequest(['sort' => '-created_at']);
$sql = $sorter->parse($req, ['nom', 'created_at'], 'nom', 'e');
t('S03 desc prefix',            str_contains($sql, 'DESC'));
t('S04 desc field',             str_contains($sql, '`e`.`created_at`'));

$req = makeRequest(['sort' => 'nom,-created_at']);
$sql = $sorter->parse($req, ['nom', 'created_at'], 'nom', 'e');
t('S05 multi sort has nom',     str_contains($sql, '`e`.`nom`'));
t('S05b multi sort has date',   str_contains($sql, '`e`.`created_at`'));

$req = makeRequest(['sort' => 'hack_field']);
$sql = $sorter->parse($req, ['nom', 'created_at'], 'nom', 'e');
t('S06 unknown field fallback', str_contains($sql, '`e`.`nom`'));

// ─── Résumé ───────────────────────────────────────────────────────────────────
echo "\nPaginationTest+FilterTest+SortTest: $pass/" . ($pass + $fail) . " PASS\n";
if ($fail > 0) exit(1);
