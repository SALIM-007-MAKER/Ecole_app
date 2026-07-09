<?php
/**
 * Tests unitaires — TenantResolver (Phase 14.2, Infrastructure Tenant)
 *
 * Standalone, aucune dépendance DB (StubTenantRepository en mémoire).
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/TenantResolverTest.php
 *
 * Couvre le critère de validation du blueprint (§25.1, Phase 14.2) :
 * "Test unitaire TenantResolver (subdomain, path, header)".
 */

declare(strict_types=1);

// ─── Autoloader manuel ───────────────────────────────────────────────────────

$base = dirname(__DIR__, 2);

define('ROOT_PATH', $base);

spl_autoload_register(function (string $class) use ($base): void {
    $map = [
        'Core\\' => $base . '/core/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $dir . $rel . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

use Core\Request;
use Core\Tenant\Etablissement;
use Core\Tenant\TenantContext;
use Core\Tenant\TenantException;
use Core\Tenant\TenantRepository;
use Core\Tenant\TenantResolver;

// ─── Helpers ─────────────────────────────────────────────────────────────────

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

function assert_true(bool $val, string $label): void  { assert_eq(true,  $val, $label); }
function assert_null(mixed $val, string $label): void { assert_eq(null,  $val, $label); }

function section(string $title): void
{
    echo "\n\033[33m── $title\033[0m\n";
}

function makeEtab(int $id, string $slug, string $statut = 'active'): Etablissement
{
    return Etablissement::fromRow([
        'id' => $id, 'slug' => $slug, 'nom' => "École $slug", 'nom_court' => $slug,
        'code_etablissement' => null, 'type' => 'lycee', 'pays' => 'DZ', 'statut' => $statut,
        'plan_id' => null, 'plan_expires_at' => null, 'storage_quota_mb' => 1024,
        'max_users' => 50, 'max_eleves' => 500, 'trial_ends_at' => null,
        'suspended_at' => null, 'suspension_reason' => null,
    ]);
}

// ─── Stub TenantRepository (jamais de DB) ────────────────────────────────────

class StubTenantRepository extends TenantRepository
{
    /** @var array<string, Etablissement> */
    private array $bySlug;
    /** @var array<string, Etablissement> */
    private array $byDomain;

    public function __construct(array $bySlug = [], array $byDomain = [])
    {
        // Ne pas appeler parent::__construct() — pas de DB
        $this->bySlug   = $bySlug;
        $this->byDomain = $byDomain;
    }

    public function findBySlug(string $slug): ?Etablissement
    {
        return $this->bySlug[$slug] ?? null;
    }

    public function findByDomain(string $domain): ?Etablissement
    {
        return $this->byDomain[$domain] ?? null;
    }

    public function findById(int $id): ?Etablissement
    {
        foreach ($this->bySlug as $etab) {
            if ($etab->id === $id) return $etab;
        }
        return null;
    }
}

function makeRequest(string $uri = '/'): Request
{
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    return new Request();
}

function resetGlobals(): void
{
    unset($_SERVER['HTTP_HOST'], $_SERVER['HTTP_X_TENANT_SLUG']);
    $_SESSION = [];
}

// ══════════════════════════════════════════════════════════════════════════
// 1. extractSubdomainSlug()
// ══════════════════════════════════════════════════════════════════════════

section('1. TenantResolver::extractSubdomainSlug()');

assert_eq('lycee-ibn-badis', TenantResolver::extractSubdomainSlug('lycee-ibn-badis.scolaris.app', 'scolaris.app'), 'sous-domaine simple');
assert_eq('lycee-ibn-badis', TenantResolver::extractSubdomainSlug('lycee-ibn-badis.scolaris.app:8080', 'scolaris.app'), 'ignore le port');
assert_null(TenantResolver::extractSubdomainSlug('scolaris.app', 'scolaris.app'), 'pas de sous-domaine → null');
assert_null(TenantResolver::extractSubdomainSlug('www.scolaris.app', 'scolaris.app'), 'www exclu');
assert_null(TenantResolver::extractSubdomainSlug('api.scolaris.app', 'scolaris.app'), 'api exclu');
assert_null(TenantResolver::extractSubdomainSlug('localhost', 'scolaris.app'), 'host hors base_domain → null');
assert_null(TenantResolver::extractSubdomainSlug('lycee-ibn-badis.scolaris.app', null), 'base_domain non configuré → null');
assert_null(TenantResolver::extractSubdomainSlug(null, 'scolaris.app'), 'host null → null');

// ══════════════════════════════════════════════════════════════════════════
// 2. extractPathSlug()
// ══════════════════════════════════════════════════════════════════════════

section('2. TenantResolver::extractPathSlug()');

assert_eq('lycee-ibn-badis', TenantResolver::extractPathSlug('/s/lycee-ibn-badis/dashboard'), 'chemin avec suite');
assert_eq('lycee-ibn-badis', TenantResolver::extractPathSlug('/s/lycee-ibn-badis'), 'chemin sans suite');
assert_eq('lycee-ibn-badis', TenantResolver::extractPathSlug('/s/lycee-ibn-badis/'), 'chemin avec slash final');
assert_eq('demo', TenantResolver::extractPathSlug('/ecole_app/s/demo/eleves'), 'chemin préfixé BASE_URL');
assert_null(TenantResolver::extractPathSlug('/dashboard'), 'pas de segment /s/ → null');
assert_null(TenantResolver::extractPathSlug('/'), 'racine → null');

// ══════════════════════════════════════════════════════════════════════════
// 3. extractHeaderSlug()
// ══════════════════════════════════════════════════════════════════════════

section('3. TenantResolver::extractHeaderSlug()');

resetGlobals();
$_SERVER['HTTP_X_TENANT_SLUG'] = 'lycee-ibn-badis';
assert_eq('lycee-ibn-badis', TenantResolver::extractHeaderSlug(), 'header présent');

resetGlobals();
$_SERVER['HTTP_X_TENANT_SLUG'] = '  Lycee-Ibn-Badis  ';
assert_eq('lycee-ibn-badis', TenantResolver::extractHeaderSlug(), 'header trim + lowercase');

resetGlobals();
assert_null(TenantResolver::extractHeaderSlug(), 'header absent → null');

// ══════════════════════════════════════════════════════════════════════════
// 4. resolve() — bout en bout avec StubTenantRepository
// ══════════════════════════════════════════════════════════════════════════

section('4. TenantResolver::resolve() — priorités de résolution');

$etabA = makeEtab(1, 'ecole-a');
$etabB = makeEtab(2, 'ecole-b');

// 4.1 — Résolution par sous-domaine
resetGlobals();
$_SERVER['HTTP_HOST'] = 'ecole-a.scolaris.app';
$repo = new StubTenantRepository(['ecole-a' => $etabA]);
$resolver = new TenantResolver($repo, ['base_domain' => 'scolaris.app']);
$result = $resolver->resolve(makeRequest('/dashboard'));
assert_true($result !== null && $result->id === 1, 'résolution par sous-domaine');

// 4.2 — Résolution par chemin
resetGlobals();
$_SERVER['HTTP_HOST'] = 'localhost';
$repo = new StubTenantRepository(['ecole-b' => $etabB]);
$resolver = new TenantResolver($repo, ['base_domain' => 'scolaris.app']);
$result = $resolver->resolve(makeRequest('/s/ecole-b/dashboard'));
assert_true($result !== null && $result->id === 2, 'résolution par chemin /s/{slug}');

// 4.3 — Résolution par header
resetGlobals();
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTP_X_TENANT_SLUG'] = 'ecole-a';
$repo = new StubTenantRepository(['ecole-a' => $etabA]);
$resolver = new TenantResolver($repo, ['base_domain' => 'scolaris.app']);
$result = $resolver->resolve(makeRequest('/api/v1/eleves'));
assert_true($result !== null && $result->id === 1, 'résolution par header X-Tenant-Slug');

// 4.4 — Priorité : sous-domaine avant header
resetGlobals();
$_SERVER['HTTP_HOST'] = 'ecole-a.scolaris.app';
$_SERVER['HTTP_X_TENANT_SLUG'] = 'ecole-b';
$repo = new StubTenantRepository(['ecole-a' => $etabA, 'ecole-b' => $etabB]);
$resolver = new TenantResolver($repo, ['base_domain' => 'scolaris.app']);
$result = $resolver->resolve(makeRequest('/dashboard'));
assert_true($result !== null && $result->id === 1, 'sous-domaine prioritaire sur header');

// 4.5 — Aucune stratégie ne matche → null
resetGlobals();
$_SERVER['HTTP_HOST'] = 'localhost';
$repo = new StubTenantRepository([]);
$resolver = new TenantResolver($repo, ['base_domain' => 'scolaris.app']);
$result = $resolver->resolve(makeRequest('/dashboard'));
assert_null($result, 'rien ne résout → null');

// 4.6 — Résolution par domaine personnalisé
resetGlobals();
$_SERVER['HTTP_HOST'] = 'ecole.institutibadis.dz';
$repo = new StubTenantRepository([], ['ecole.institutibadis.dz' => $etabA]);
$resolver = new TenantResolver($repo, ['base_domain' => 'scolaris.app']);
$result = $resolver->resolve(makeRequest('/dashboard'));
assert_true($result !== null && $result->id === 1, 'résolution par domaine personnalisé');

// ══════════════════════════════════════════════════════════════════════════
// 5. TenantContext
// ══════════════════════════════════════════════════════════════════════════

section('5. TenantContext');

TenantContext::clear();
assert_true(!TenantContext::isSet(), 'isSet() false avant résolution');

$threw = false;
try {
    TenantContext::require();
} catch (TenantException) {
    $threw = true;
}
assert_true($threw, 'require() lève une exception si non résolu');

TenantContext::set(1, $etabA);
assert_true(TenantContext::isSet(), 'isSet() true après set()');
assert_eq(1, TenantContext::require(), 'require() retourne l\'id');
assert_eq('ecole-a', TenantContext::get()?->slug, 'get() retourne l\'Etablissement');

TenantContext::clear();
assert_true(!TenantContext::isSet(), 'clear() réinitialise le contexte');

// ── Résumé ──────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n\033[1m" . ($failed === 0 ? "\033[32m" : "\033[31m");
echo "Résultat : $passed/$total assertions passées";
echo "\033[0m\n\n";

if ($failed > 0) {
    exit(1);
}
