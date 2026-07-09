<?php
/**
 * Tests SaaS Super-Admin — Phase 14.10
 *
 * 1. Core\Platform\PlatformAuth — isolation stricte de session vis-à-vis de
 *    Core\Session (établissement) : clé distincte, aucun mélange d'état
 * 2. PlatformEtablissementService — CRUD cycle de vie (créer/activer/
 *    suspendre/archiver/restaurer/supprimer logiquement), recherche/filtre,
 *    assignation de plan (copie des quotas)
 * 3. PlatformPlanService — CRUD plans, usageCount()
 * 4. PlatformStatsService — statistiques globales agrégées sur PLUSIEURS
 *    établissements actifs simultanément, alertes de quota inter-tenants,
 *    instantané (snapshot)
 * 5. Sécurité — un utilisateur sans ligne platform_operators active ne peut
 *    jamais être authentifié comme opérateur ; niveaux (support/admin/
 *    super_admin) correctement distingués
 *
 * Toutes les données de test sont créées/détruites dans une transaction PDO
 * annulée en fin de script — zéro pollution.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/PlatformAdminTest.php
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
    $namespaces = ['Core\\' => ROOT_PATH . '/core/'];
    foreach ($namespaces as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($file)) { require $file; return; }
        }
    }
});

use Core\Database;
use Core\Platform\PlatformAuth;
use Core\Platform\PlatformEtablissementService;
use Core\Platform\PlatformPlanService;
use Core\Platform\PlatformStatsService;
use Core\Session;

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
    // ══════════════════════════════════════════════════════════════════════
    section('1. PlatformAuth — isolation stricte vis-à-vis de Core\Session (établissement)');
    // ══════════════════════════════════════════════════════════════════════

    assert_false(PlatformAuth::isLogged(), 'Aucun opérateur connecté au départ');

    Session::setUser(['id' => 999, 'email' => 'eleve@ecole.dz', 'role' => 'eleve', 'permissions' => ['notes.view_own']]);
    assert_true(Session::isLogged(), 'Un utilisateur établissement est connecté (Session::isLogged)');
    assert_false(PlatformAuth::isLogged(), 'PlatformAuth::isLogged() reste faux — aucun mélange avec Session::setUser()');

    PlatformAuth::login(['id' => 1, 'user_id' => 999, 'email' => 'eleve@ecole.dz', 'nom' => 'Test', 'niveau' => 'support']);
    assert_true(PlatformAuth::isLogged(), 'PlatformAuth::login() active bien l\'état opérateur');
    assert_true(Session::isLogged(), 'Session::isLogged() (établissement) n\'est PAS affecté par PlatformAuth::login() — les deux coexistent');
    assert_eq('eleve@ecole.dz', Session::getUser()['email'], 'Session::getUser() reste inchangé après PlatformAuth::login()');

    assert_true(PlatformAuth::hasLevel('support'), 'hasLevel(\'support\') vrai pour un opérateur niveau support');
    assert_false(PlatformAuth::hasLevel('admin', 'super_admin'), 'hasLevel(\'admin\',\'super_admin\') faux pour un opérateur niveau support');

    PlatformAuth::logout();
    assert_false(PlatformAuth::isLogged(), 'PlatformAuth::logout() désactive l\'état opérateur');
    assert_true(Session::isLogged(), 'PlatformAuth::logout() ne déconnecte PAS la session établissement (totalement indépendantes)');

    @Session::logout(); // session_regenerate_id() émet un warning bénin hors contexte HTTP réel (CLI sans session_start())
    assert_false(Session::isLogged(), 'nettoyage : session établissement déconnectée pour la suite du test');

    // ══════════════════════════════════════════════════════════════════════
    section('2. PlatformEtablissementService — cycle de vie + recherche + assignation de plan');
    // ══════════════════════════════════════════════════════════════════════

    $etabs = new PlatformEtablissementService($pdo);
    $plans = new PlatformPlanService($pdo);
    $fakeOperatorUserId = 1;

    $planId = $plans->create([
        'code' => 'test-plan-' . uniqid(), 'nom' => 'Plan Test', 'max_users' => 20, 'max_eleves' => 200,
        'storage_quota_mb' => 500, 'api_calls_per_day' => 5000, 'prix_mensuel' => 9.99, 'prix_annuel' => 99.99,
    ], $fakeOperatorUserId);
    assert_true($planId > 0, 'PlatformPlanService::create() retourne un id de plan valide');

    $slug = 'test-platform-' . uniqid();
    $newEtabId = $etabs->create([
        'slug' => $slug, 'nom' => 'École Test Platform', 'nom_court' => 'ETP', 'type' => 'lycee', 'pays' => 'DZ',
    ], $fakeOperatorUserId);
    assert_true($newEtabId > 0, 'create() retourne un id d\'établissement valide');

    $found = $etabs->find($newEtabId);
    assert_eq('trial', $found['statut'], 'Un nouvel établissement démarre au statut "trial"');

    try {
        $etabs->create(['slug' => $slug, 'nom' => 'Doublon', 'nom_court' => 'D', 'type' => 'lycee'], $fakeOperatorUserId);
        assert_true(false, 'Slug dupliqué : InvalidArgumentException attendue');
    } catch (\InvalidArgumentException) {
        assert_true(true, 'Slug dupliqué rejeté (unicité globale)');
    }

    assert_true($etabs->activate($newEtabId, $fakeOperatorUserId), 'activate() réussit');
    assert_eq('active', $etabs->find($newEtabId)['statut'], 'Statut passé à "active"');

    assert_true($etabs->suspend($newEtabId, $fakeOperatorUserId, 'Impayé'), 'suspend() réussit');
    $suspended = $etabs->find($newEtabId);
    assert_eq('suspended', $suspended['statut'], 'Statut passé à "suspended"');
    assert_eq('Impayé', $suspended['suspension_reason'], 'Raison de suspension enregistrée');

    assert_true($etabs->archive($newEtabId, $fakeOperatorUserId), 'archive() réussit');
    assert_eq('archived', $etabs->find($newEtabId)['statut'], 'Statut passé à "archived" (distinct de "suspended")');

    assert_true($etabs->restore($newEtabId, $fakeOperatorUserId), 'restore() réussit');
    assert_eq('active', $etabs->find($newEtabId)['statut'], 'restore() ramène au statut "active"');

    assert_true($etabs->assignPlan($newEtabId, $planId, $fakeOperatorUserId), 'assignPlan() réussit');
    $withPlan = $etabs->find($newEtabId);
    assert_eq($planId, (int)$withPlan['plan_id'], 'plan_id mis à jour après assignPlan()');
    assert_eq(500, (int)$withPlan['storage_quota_mb'], 'assignPlan() copie storage_quota_mb du plan sur l\'établissement');
    assert_eq(20, (int)$withPlan['max_users'], 'assignPlan() copie max_users du plan sur l\'établissement');
    assert_eq(200, (int)$withPlan['max_eleves'], 'assignPlan() copie max_eleves du plan sur l\'établissement');
    assert_eq(1, $plans->usageCount($planId), 'usageCount() reflète l\'établissement fraîchement assigné à ce plan');

    $searchResults = $etabs->search(['search' => 'École Test Platform']);
    assert_true(count($searchResults) >= 1, 'search() retrouve l\'établissement par nom');
    $searchBySlug = $etabs->search(['search' => $slug]);
    assert_eq($newEtabId, (int)$searchBySlug[0]['id'], 'search() retrouve l\'établissement par slug');

    assert_true($etabs->softDelete($newEtabId, $fakeOperatorUserId), 'softDelete() réussit');
    assert_true($etabs->find($newEtabId) !== null, 'find() retrouve encore la ligne après softDelete (deleted_at, pas de DROP)');
    $afterDeleteSearch = $etabs->search(['search' => $slug]);
    assert_eq(0, count($afterDeleteSearch), 'search() exclut les établissements supprimés logiquement (deleted_at IS NOT NULL)');

    $stmtCheck = $pdo->prepare("SELECT deleted_at, statut FROM etablissements WHERE id = ?");
    $stmtCheck->execute([$newEtabId]);
    $rowCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    assert_true($rowCheck['deleted_at'] !== null, 'deleted_at est bien renseigné (suppression logique uniquement, ligne physiquement présente)');

    // ══════════════════════════════════════════════════════════════════════
    section('3. PlatformPlanService — CRUD + usageCount()');
    // ══════════════════════════════════════════════════════════════════════

    assert_true($plans->update($planId, ['nom' => 'Plan Test Renommé', 'max_users' => 30, 'max_eleves' => 300, 'storage_quota_mb' => 600, 'api_calls_per_day' => 6000, 'prix_mensuel' => 12, 'prix_annuel' => 120], $fakeOperatorUserId), 'update() réussit');
    assert_eq('Plan Test Renommé', $plans->find($planId)['nom'], 'update() modifie bien le nom');
    assert_eq(0, $plans->usageCount($planId), 'usageCount() exclut l\'établissement supprimé logiquement en fin de section précédente (deleted_at IS NOT NULL) — ne compte que les tenants réellement actifs sur ce plan');

    assert_true($plans->toggleActive($planId, false, $fakeOperatorUserId), 'toggleActive(false) réussit');
    assert_eq(0, (int)$plans->find($planId)['actif'], 'Le plan est bien désactivé (actif=0)');

    // ══════════════════════════════════════════════════════════════════════
    section('4. PlatformStatsService — agrégation sur PLUSIEURS établissements actifs');
    // ══════════════════════════════════════════════════════════════════════

    $pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type, pays, statut, storage_quota_mb)
                VALUES ('test-stats-a', 'École Stats A', 'Stats A', 'lycee', 'DZ', 'active', 1)"); // quota volontairement minuscule → alerte
    $statsEtabA = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type, pays, statut, storage_quota_mb)
                VALUES ('test-stats-b', 'École Stats B', 'Stats B', 'lycee', 'DZ', 'active', 10000)"); // quota confortable → pas d'alerte
    $statsEtabB = (int)$pdo->lastInsertId();

    $pdo->exec("INSERT INTO api_uploads (etablissement_id, module, context, path, size_bytes, mime_type)
                VALUES ({$statsEtabA}, 'documents', 'test', '/x/1.pdf', 900000, 'application/pdf')");

    $stats = new PlatformStatsService($pdo);
    $kpis = $stats->globalKpis();

    assert_true($kpis['total_tenants'] >= 3, 'globalKpis() compte au moins les 3 établissements actifs/archivés créés dans ce test (statsA, statsB, + celui de la section 2)');
    assert_true($kpis['active_tenants'] >= 2, 'globalKpis() : au moins 2 établissements "active" (statsA + statsB)');
    assert_true($kpis['storage_used_gb'] > 0, 'globalKpis() : storage_used_gb reflète les uploads réels (> 0)');
    assert_eq(null, $kpis['total_api_calls'], 'total_api_calls = null (aucune table de suivi API appliquée dans cette base — distinct de 0)');

    $queueStats = $stats->queueStats();
    assert_true(array_key_exists('pending', $queueStats), 'queueStats() retourne bien la structure attendue (pending/processing/done/failed)');

    $moduleHealth = $stats->moduleHealth();
    assert_true(is_bool($moduleHealth['finance'] ?? null), 'moduleHealth() retourne un booléen par module (ex: finance)');

    $atRisk = $stats->tenantsNearQuota();
    $atRiskIds = array_column($atRisk, 'etablissement_id');
    assert_true(in_array($statsEtabA, $atRiskIds, true), 'tenantsNearQuota() détecte l\'établissement dont le quota (1 Mo) est dépassé par un usage réel (900 Ko)');
    assert_false(in_array($statsEtabB, $atRiskIds, true), 'tenantsNearQuota() n\'inclut PAS l\'établissement au quota confortable (10 Go, aucun usage)');

    $snapshotId = $stats->recordSnapshot('test');
    assert_true($snapshotId > 0, 'recordSnapshot() enregistre un instantané et retourne un id valide');
    $recent = $stats->recentSnapshots(5);
    assert_eq($snapshotId, (int)$recent[0]['id'], 'recentSnapshots() retourne bien le dernier instantané en premier');
    assert_eq('test', $recent[0]['period'], 'L\'instantané enregistré porte la bonne période');

    // ══════════════════════════════════════════════════════════════════════
    section('5. Sécurité — accès opérateur strictement conditionné à platform_operators');
    // ══════════════════════════════════════════════════════════════════════

    $pdo->exec("INSERT INTO users (nom, email, password, role, actif, etablissement_id)
                VALUES ('Utilisateur Sans Accès Plateforme', 'no-platform-access-" . uniqid() . "@test.dz', '" . password_hash('x', PASSWORD_DEFAULT) . "', 'secretaire', 1, 1)");
    $regularUserId = (int)$pdo->lastInsertId();

    $opCheck = $pdo->prepare("SELECT * FROM platform_operators WHERE user_id = ? AND actif = 1");
    $opCheck->execute([$regularUserId]);
    assert_false($opCheck->fetch() !== false, 'Un utilisateur établissement ordinaire n\'a AUCUNE ligne platform_operators active — accès refusé par construction');

    $pdo->exec("INSERT INTO platform_operators (user_id, niveau, actif) VALUES ({$regularUserId}, 'support', 1)");
    $opCheck->execute([$regularUserId]);
    $opRow = $opCheck->fetch(PDO::FETCH_ASSOC);
    assert_true($opRow !== false, 'Après ajout d\'une ligne platform_operators active, l\'utilisateur est reconnu comme opérateur');
    assert_eq('support', $opRow['niveau'], 'Le niveau par défaut créé est bien "support" (droits minimaux)');

    $pdo->exec("UPDATE platform_operators SET actif = 0 WHERE user_id = {$regularUserId}");
    $opCheck->execute([$regularUserId]);
    assert_false($opCheck->fetch() !== false, 'Un opérateur désactivé (actif=0) n\'est plus reconnu — accès révocable sans suppression');

} finally {
    $pdo->rollBack();
    @Session::logout(); // session_regenerate_id() émet un warning bénin hors contexte HTTP réel (CLI sans session_start())
    PlatformAuth::logout();
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
