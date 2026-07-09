<?php
/**
 * Tests Cache & Queue Multi-Tenant — Phase 14.9
 *
 * 1. Core\Cache\FileCache — get/put/has/forget/flushPrefix/stats, expiration TTL
 * 2. Core\Tenant\TenantCache — isolation stricte par tenant (même clé,
 *    catégories différentes, remember(), forgetCategory(), flushTenant())
 * 3. Core\Tenant\BrandingService — intégration réelle du cache persistant
 *    (remplace la mémoïsation Phase 14.5), invalidation ciblée par save()
 * 4. Core\Queue\JobQueue — push/reserveNext/markDone/markFailed (backoff),
 *    stats(), isolation des statistiques par tenant
 * 5. Core\Queue\QueueWorker — propagation du TenantContext pendant handle(),
 *    restauration du contexte précédent après (y compris en cas d'échec),
 *    traitement de plusieurs tenants à la suite sans fuite
 * 6. Montée en charge légère : plusieurs établissements traités "simultanément"
 *    (lots entrelacés) sans confusion de contexte ni de données
 *
 * Toutes les données de test sont créées/détruites dans une transaction PDO
 * annulée en fin de script — zéro pollution DB. Le cache fichier utilise un
 * répertoire temporaire dédié, supprimé en fin de script.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/CacheQueueTest.php
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
        'Core\\'      => ROOT_PATH . '/core/',
        'App\\Jobs\\' => ROOT_PATH . '/app/Jobs/',
        'App\\Services\\' => ROOT_PATH . '/app/Services/',
    ];
    foreach ($namespaces as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file = $dir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) { require $file; return; }
        }
    }
});

use Core\Cache\FileCache;
use Core\Database;
use Core\Queue\Job;
use Core\Queue\JobQueue;
use Core\Queue\QueueWorker;
use Core\Tenant\BrandingService;
use Core\Tenant\TenantCache;
use Core\Tenant\TenantContext;

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

/** Job de test : enregistre dans un fichier partagé le tenant vu pendant handle(), pour vérifier la propagation. */
final class RecordTenantJob implements Job
{
    public static string $logFile = '';
    public function handle(array $payload): void
    {
        $seen = TenantContext::current();
        file_put_contents(self::$logFile, ($seen ?? 'NULL') . "\n", FILE_APPEND);
        if (($payload['fail'] ?? false) === true) {
            throw new \RuntimeException('échec simulé pour le test de backoff');
        }
    }
}

$pdo = Database::getInstance()->getConnection();
$pdo->beginTransaction();

$testCacheDir = sys_get_temp_dir() . '/edunova_test_cache_' . uniqid();
$testLogFile = sys_get_temp_dir() . '/edunova_test_joblog_' . uniqid() . '.txt';
file_put_contents($testLogFile, '');
RecordTenantJob::$logFile = $testLogFile;

try {
    $pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type, pays, statut)
                VALUES ('test-cq-a', 'Établissement Cache/Queue Test A', 'CQ A', 'lycee', 'DZ', 'active')");
    $etabA = (int)$pdo->lastInsertId();

    $pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type, pays, statut)
                VALUES ('test-cq-b', 'Établissement Cache/Queue Test B', 'CQ B', 'lycee', 'DZ', 'active')");
    $etabB = (int)$pdo->lastInsertId();

    echo "Établissement A = {$etabA}, Établissement B = {$etabB}\n";

    // ══════════════════════════════════════════════════════════════════════
    section('1. Core\Cache\FileCache — cycle de vie + expiration TTL');
    // ══════════════════════════════════════════════════════════════════════

    $fileCache = new FileCache($testCacheDir);

    assert_false($fileCache->has('inexistant'), 'has() faux pour une clé jamais écrite');
    $fileCache->put('k1', ['x' => 1], 3600);
    assert_true($fileCache->has('k1'), 'has() vrai après put()');
    assert_eq(['x' => 1], $fileCache->get('k1'), 'get() retourne la valeur exacte (structure préservée)');

    // put() impose un TTL minimum de 1s (max(1, $ttl)) — pour tester l'expiration
    // sans dépendre d'un vrai sleep(), on écrit directement un fichier d'entrée
    // avec un expires_at déjà passé, dans le même format que FileCache::put().
    $expiredPath = $testCacheDir . '/' . sha1('k2') . '.json';
    file_put_contents($expiredPath, json_encode(['key' => 'k2', 'value' => 'expire-vite', 'expires_at' => time() - 10]));
    assert_false($fileCache->has('k2'), 'Une entrée dont expires_at est dans le passé n\'est plus considérée présente');
    assert_eq(null, $fileCache->get('k2'), 'get() retourne null pour une entrée expirée');

    $fileCache->put('prefix:a:1', 'v1', 3600);
    $fileCache->put('prefix:a:2', 'v2', 3600);
    $fileCache->put('prefix:b:1', 'v3', 3600);
    assert_eq(2, $fileCache->stats('prefix:a:')['keys'], 'stats(prefix) compte uniquement les clés du préfixe demandé');
    $fileCache->flushPrefix('prefix:a:');
    assert_false($fileCache->has('prefix:a:1'), 'flushPrefix() supprime les clés du préfixe');
    assert_true($fileCache->has('prefix:b:1'), 'flushPrefix() ne touche pas aux clés hors préfixe');

    $fileCache->forget('k1');
    assert_false($fileCache->has('k1'), 'forget() supprime une clé précise');

    // ══════════════════════════════════════════════════════════════════════
    section('2. Core\Tenant\TenantCache — isolation stricte par tenant');
    // ══════════════════════════════════════════════════════════════════════

    $tcache = new TenantCache($fileCache);

    $tcache->put($etabA, 'settings', 'theme', 'violet', 3600);
    $tcache->put($etabB, 'settings', 'theme', 'bleu', 3600);
    assert_eq('violet', $tcache->get($etabA, 'settings', 'theme'), 'Même clé/catégorie : etabA lit sa propre valeur');
    assert_eq('bleu', $tcache->get($etabB, 'settings', 'theme'), 'Même clé/catégorie : etabB lit sa propre valeur (pas celle de A)');

    $calls = 0;
    $resolver = function () use (&$calls) { $calls++; return 'calculé-' . $calls; };
    $r1 = $tcache->remember($etabA, 'rbac', 'permissions', 300, $resolver);
    $r2 = $tcache->remember($etabA, 'rbac', 'permissions', 300, $resolver);
    assert_eq($r1, $r2, 'remember() ne recalcule pas au 2e appel (résultat mis en cache)');
    assert_eq(1, $calls, 'remember() n\'invoque le resolver qu\'une seule fois');

    $tcache->forgetCategory($etabA, 'settings');
    assert_eq(null, $tcache->get($etabA, 'settings', 'theme'), 'forgetCategory() invalide la catégorie ciblée pour etabA');
    assert_eq('bleu', $tcache->get($etabB, 'settings', 'theme'), 'forgetCategory(etabA) ne touche jamais au cache de etabB');

    $tcache->put($etabA, 'dashboard', 'stats', ['n' => 42], 300);
    $statsBefore = $tcache->stats($etabA);
    assert_true($statsBefore['keys'] >= 2, 'stats() compte les clés actives de etabA (rbac + dashboard)');
    $tcache->flushTenant($etabA);
    assert_eq(0, $tcache->stats($etabA)['keys'], 'flushTenant() vide tout le cache de etabA');
    assert_eq('bleu', $tcache->get($etabB, 'settings', 'theme'), 'flushTenant(etabA) laisse etabB totalement intact');

    // ══════════════════════════════════════════════════════════════════════
    section('3. BrandingService — cache persistant réel + invalidation ciblée');
    // ══════════════════════════════════════════════════════════════════════

    $pdo->exec("INSERT INTO etablissement_branding (etablissement_id, app_name, primary_color, secondary_color)
                VALUES ({$etabA}, 'École A Test', '#111111', '#222222')");
    $pdo->exec("INSERT INTO etablissement_branding (etablissement_id, app_name, primary_color, secondary_color)
                VALUES ({$etabB}, 'École B Test', '#333333', '#444444')");

    $branding = new BrandingService($pdo, $tcache);

    $dataA1 = $branding->get($etabA);
    assert_eq('École A Test', $dataA1->appName, 'BrandingService::get() lit correctement depuis la DB (premier appel, pas de cache)');
    assert_eq(1, $tcache->stats($etabA, 'branding')['keys'], 'Le premier get() peuple bien le cache (1 clé "branding")');

    // Modifie directement en DB (contournant l'app) — un get() en cache doit encore renvoyer l'ancienne valeur
    $pdo->exec("UPDATE etablissement_branding SET app_name = 'Changé en direct' WHERE etablissement_id = {$etabA}");
    $dataA2 = $branding->get($etabA);
    assert_eq('École A Test', $dataA2->appName, 'Le cache sert la valeur mise en cache tant qu\'il n\'est pas invalidé (même après un changement DB direct)');

    $branding->save($etabA, ['app_name' => 'École A Renommée']);
    $dataA3 = $branding->get($etabA);
    assert_eq('École A Renommée', $dataA3->appName, 'save() invalide le cache — la nouvelle valeur est immédiatement visible');

    $dataB = $branding->get($etabB);
    assert_eq('École B Test', $dataB->appName, 'save() sur etabA n\'invalide jamais le cache de etabB');

    BrandingService::clearCache();

    // ══════════════════════════════════════════════════════════════════════
    section('4. Core\Queue\JobQueue — push/reserveNext/markDone/markFailed');
    // ══════════════════════════════════════════════════════════════════════

    $queue = new JobQueue($pdo);

    $jobId = $queue->push(RecordTenantJob::class, ['x' => 1], $etabA);
    assert_true($jobId > 0, 'push() retourne un id de tâche valide');

    $reserved = $queue->reserveNext();
    assert_true($reserved !== null, 'reserveNext() retrouve la tâche pending due (run_at <= maintenant)');
    assert_eq($etabA, $reserved['etablissement_id'], 'La tâche réservée transporte bien l\'etablissement_id de origine');
    assert_eq(1, $reserved['attempts'], 'Premier essai comptabilisé (attempts=1)');

    $queue->markDone($reserved['id'], 12.5);
    $statsA = $queue->stats($etabA);
    assert_eq(1, $statsA['done'], 'stats() : 1 tâche done pour etabA');
    assert_eq(0, $statsA['pending'], 'stats() : 0 tâche pending restante pour etabA');

    assert_true($queue->reserveNext() === null, 'reserveNext() retourne null quand la file est vide');

    // Tâche différée dans le futur : ne doit pas être réservée avant son heure
    $futureJobId = $queue->push(RecordTenantJob::class, [], $etabB, delaySeconds: 3600);
    assert_true($queue->reserveNext() === null, 'Une tâche différée dans le futur (delaySeconds=3600) n\'est pas réservée avant son heure');

    // Échec + retry (backoff) : simule maxAttempts=1 pour observer le passage direct à 'failed'
    $failJobId = $queue->push(RecordTenantJob::class, ['fail' => true], $etabA, maxAttempts: 1);
    $reservedFail = $queue->reserveNext();
    $queue->markFailed($reservedFail['id'], $reservedFail['attempts'], $reservedFail['max_attempts'], 'erreur de test');
    $statsAfterFail = $queue->stats($etabA);
    assert_eq(1, $statsAfterFail['failed'], 'Après épuisement des essais (max_attempts=1), la tâche passe à failed');

    // ══════════════════════════════════════════════════════════════════════
    section('5. Core\Queue\QueueWorker — propagation et restauration du TenantContext');
    // ══════════════════════════════════════════════════════════════════════

    TenantContext::clear();
    file_put_contents($testLogFile, ''); // reset log

    $queue->push(RecordTenantJob::class, [], $etabA);
    $worker = new QueueWorker($queue);
    $ran = $worker->processNext();
    assert_true($ran, 'processNext() traite bien la tâche disponible');

    $seenTenants = array_filter(explode("\n", file_get_contents($testLogFile)));
    assert_eq([(string)$etabA], array_values($seenTenants), 'Le job a vu TenantContext::current() = etabA pendant son exécution');
    assert_false(TenantContext::isSet(), 'Après traitement, le contexte est restauré à son état précédent (non défini ici)');

    // Le contexte précédent (s'il existait) doit être restauré même en cas d'échec du job
    TenantContext::set($etabB);
    $queue->push(RecordTenantJob::class, ['fail' => true], $etabA, maxAttempts: 5);
    $worker->processNext();
    assert_eq($etabB, TenantContext::current(), 'Le contexte précédent (etabB) est restauré même après un job qui a levé une exception');
    TenantContext::clear();

    // ══════════════════════════════════════════════════════════════════════
    section('6. Montée en charge légère — plusieurs tenants traités à la suite sans fuite');
    // ══════════════════════════════════════════════════════════════════════

    file_put_contents($testLogFile, '');
    $expectedOrder = [];
    for ($i = 0; $i < 5; $i++) {
        $etab = $i % 2 === 0 ? $etabA : $etabB;
        $queue->push(RecordTenantJob::class, [], $etab);
        $expectedOrder[] = (string)$etab;
    }
    $processed = $worker->processBatch(10);
    assert_eq(5, $processed, 'processBatch() traite les 5 tâches entrelacées de deux tenants différents');

    $seenOrder = array_values(array_filter(explode("\n", file_get_contents($testLogFile))));
    assert_eq($expectedOrder, $seenOrder, 'Chaque tâche a vu EXACTEMENT le tenant qui lui correspond, dans l\'ordre, sans confusion malgré l\'alternance A/B/A/B/A');

    $statsAFinal = $queue->stats($etabA);
    $statsBFinal = $queue->stats($etabB);
    assert_true($statsAFinal['done'] >= 3, 'Statistiques finales etabA cohérentes (>= 3 tâches done)');
    assert_true($statsBFinal['done'] >= 2, 'Statistiques finales etabB cohérentes (>= 2 tâches done)');

} finally {
    $pdo->rollBack();
    if (is_dir($testCacheDir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testCacheDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
        @rmdir($testCacheDir);
    }
    @unlink($testLogFile);
    TenantContext::clear();
    echo "\n(rollback effectué + fichiers de test nettoyés — aucune donnée de test persistée)\n";
}

// ── Résumé ──────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n\033[1m" . ($failed === 0 ? "\033[32m" : "\033[31m");
echo "Résultat : $passed/$total assertions passées";
echo "\033[0m\n\n";

if ($failed > 0) {
    exit(1);
}
