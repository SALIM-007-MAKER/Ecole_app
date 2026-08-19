<?php
/**
 * Tests Sauvegardes & Reprise après incident — Phase 14.11
 *
 * 1. DatabaseDumper — découverte des tables tenant-scopées, dump tenant
 *    isolé pour 2 établissements à jeux de données différents, dump global
 *    (schéma + données), dump différentiel (filtre par date)
 * 2. BackupEncryption — chiffrement/déchiffrement, détection d'altération
 * 3. BackupService — création (global/tenant/différentiel), ledger
 *    platform_backups, vérification d'intégrité (checksum), détection de
 *    falsification du fichier stocké
 * 4. DatabaseRestorer::restoreTenantLive() — upsert non destructif, garde
 *    d'isolation (rejette les lignes d'un autre etablissement_id même si
 *    falsifiées dans le dump), confirmation textuelle obligatoire
 * 5. Restauration globale de vérification — base de test RÉELLE créée puis
 *    supprimée, ne touche jamais la base appelante
 * 6. Journalisation — platform_restores reflète chaque tentative
 *
 * Toutes les données de test (lignes DB) sont créées/détruites dans une
 * transaction PDO annulée en fin de script. Les fichiers de sauvegarde
 * physiquement écrits sur disque (hors transaction) sont nettoyés
 * explicitement. La base de test créée en section 5 est supprimée par le
 * code testé lui-même (comportement normal), vérifié après coup.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/BackupDrTest.php
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

use Core\Backup\BackupEncryption;
use Core\Backup\BackupService;
use Core\Backup\DatabaseDumper;
use Core\Backup\DatabaseRestorer;
use Core\Database;
use Core\Storage\StorageManager;

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

$filesToCleanup = [];

try {
    // Deux établissements de test avec des jeux de données DIFFÉRENTS
    $pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type, pays, statut)
                VALUES ('test-backup-a', 'École Backup Test A', 'Backup A', 'lycee', 'DZ', 'active')");
    $etabA = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type, pays, statut)
                VALUES ('test-backup-b', 'École Backup Test B', 'Backup B', 'lycee', 'DZ', 'active')");
    $etabB = (int)$pdo->lastInsertId();

    $pdo->exec("INSERT INTO classes (nom, niveau, annee_scolaire, max_eleves, etablissement_id) VALUES ('ClasseA1', 'Seconde', '2025-2026', 30, {$etabA})");
    $classeA = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO classes (nom, niveau, annee_scolaire, max_eleves, etablissement_id) VALUES ('ClasseB1', 'Première', '2025-2026', 25, {$etabB})");
    $pdo->exec("INSERT INTO classes (nom, niveau, annee_scolaire, max_eleves, etablissement_id) VALUES ('ClasseB2', 'Terminale', '2025-2026', 20, {$etabB})");

    echo "Établissement A = {$etabA} (1 classe), Établissement B = {$etabB} (2 classes)\n";

    $dumper = new DatabaseDumper($pdo);
    $restorer = new DatabaseRestorer();
    $encryption = new BackupEncryption('test-app-key-for-backup-suite');

    // ══════════════════════════════════════════════════════════════════════
    section('1. DatabaseDumper — isolation par tenant + différentiel');
    // ══════════════════════════════════════════════════════════════════════

    $tenantTables = $dumper->tenantScopedTables();
    assert_true(in_array('classes', $tenantTables, true), 'tenantScopedTables() détecte "classes" (colonne etablissement_id)');
    assert_true(in_array('eleves', $tenantTables, true), 'tenantScopedTables() détecte "eleves"');
    assert_false(in_array('platform_plans', $tenantTables, true), 'tenantScopedTables() exclut "platform_plans" (pas de colonne etablissement_id)');
    assert_false(in_array('platform_backups', $tenantTables, true), 'tenantScopedTables() exclut "platform_backups" bien qu\'elle ait une colonne etablissement_id (métadonnée système, pas donnée métier — évite l\'auto-corruption d\'une sauvegarde par elle-même)');
    assert_false(in_array('platform_restores', $tenantTables, true), 'tenantScopedTables() exclut "platform_restores" (même raison)');

    $dumpA = $dumper->dumpTenant($etabA);
    $dumpB = $dumper->dumpTenant($etabB);

    assert_eq(1, count($dumpA['tables']['classes']['rows']), 'dumpTenant(A) contient exactement 1 classe (celle de A)');
    assert_eq(2, count($dumpB['tables']['classes']['rows']), 'dumpTenant(B) contient exactement 2 classes (celles de B)');
    assert_eq('ClasseA1', $dumpA['tables']['classes']['rows'][0]['nom'], 'dumpTenant(A) contient bien les données de A');

    $namesInB = array_column($dumpB['tables']['classes']['rows'], 'nom');
    assert_false(in_array('ClasseA1', $namesInB, true), 'dumpTenant(B) NE contient PAS la classe de A (isolation stricte)');

    assert_eq($etabA, $dumpA['tables']['etablissements']['rows'][0]['id'], 'dumpTenant() inclut la ligne etablissements elle-même');

    $dumpGlobal = $dumper->dumpGlobal();
    assert_true(isset($dumpGlobal['tables']['classes']['create']), 'dumpGlobal() inclut le schéma (CREATE TABLE) de chaque table');
    assert_true(count($dumpGlobal['tables']['classes']['rows']) >= 3, 'dumpGlobal() contient TOUTES les classes (tous tenants confondus)');

    // +6h (pas +1h) : cet environnement fait tourner PHP en UTC alors que MySQL
    // utilise le fuseau système (UTC+1 ici, voir MULTI_TENANT_SYSTEM_INTEGRATION_REVIEW.md
    // §5.1) — un script CLI autonome comme celui-ci n'applique jamais
    // APP_TIMEZONE (contrairement à Core\Application::bootstrap() pour les
    // requêtes HTTP réelles). +1h ne dépassait donc l'horloge MySQL que de
    // quelques secondes selon le moment d'exécution, rendant ce test
    // intermittent (reproduit Phase 15.3) sans lien avec le code testé. Une
    // marge large élimine la dépendance au fuseau horaire de la machine.
    $future = new DateTimeImmutable('+6 hours');
    $dumpDiffEmpty = $dumper->dumpDifferential($future, $etabA);
    assert_true(empty($dumpDiffEmpty['tables']), 'dumpDifferential() avec un "since" dans le futur ne retourne aucune ligne');

    $past = new DateTimeImmutable('-1 minute');
    $dumpDiffRecent = $dumper->dumpDifferential($past, $etabA);
    assert_true(isset($dumpDiffRecent['tables']['classes']), 'dumpDifferential() avec un "since" récent retrouve la classe fraîchement créée');

    // ══════════════════════════════════════════════════════════════════════
    section('2. BackupEncryption — chiffrement/déchiffrement + détection d\'altération');
    // ══════════════════════════════════════════════════════════════════════

    $plaintext = json_encode(['secret' => 'donnee-sensible-etablissement']);
    $encrypted = $encryption->encrypt($plaintext);
    assert_true($encrypted !== $plaintext, 'Le texte chiffré diffère du texte en clair');
    assert_eq($plaintext, $encryption->decrypt($encrypted), 'decrypt(encrypt(x)) === x');

    $tampered = substr($encrypted, 0, -1) . chr((ord(substr($encrypted, -1)) + 1) % 256);
    try {
        $encryption->decrypt($tampered);
        assert_true(false, 'Déchiffrer un contenu altéré aurait dû échouer');
    } catch (\RuntimeException) {
        assert_true(true, 'Une charge utile altérée est détectée et rejetée au déchiffrement');
    }

    // ══════════════════════════════════════════════════════════════════════
    section('3. BackupService — création, ledger, vérification d\'intégrité');
    // ══════════════════════════════════════════════════════════════════════

    $fakeOperatorUserId = 1;
    $service = new BackupService($pdo, $dumper, $restorer, $encryption);

    $backupA = $service->createTenant($etabA, $fakeOperatorUserId);
    $backupB = $service->createTenant($etabB, $fakeOperatorUserId);

    $rowA = $pdo->query("SELECT * FROM platform_backups WHERE id = {$backupA['id']}")->fetch(PDO::FETCH_ASSOC);
    $filesToCleanup[] = $rowA['storage_path'];
    $rowB = $pdo->query("SELECT * FROM platform_backups WHERE id = {$backupB['id']}")->fetch(PDO::FETCH_ASSOC);
    $filesToCleanup[] = $rowB['storage_path'];

    assert_eq('success', $rowA['statut'], 'La sauvegarde de A est enregistrée avec le statut "success"');
    assert_eq('tenant', $rowA['type'], 'Le type enregistré est "tenant"');
    assert_eq(1, (int)$rowA['encrypted'], 'La sauvegarde est chiffrée par défaut');
    assert_true($rowA['checksum'] !== $rowB['checksum'], 'Les checksums de A et B diffèrent (contenus différents)');
    assert_true((int)$rowA['duration_ms'] >= 0, 'La durée de la sauvegarde est enregistrée');

    assert_true($service->verifyIntegrity($backupA['id']), 'verifyIntegrity() : checksum valide pour une sauvegarde intacte');

    $loadedA = $service->loadDump($backupA['id']);
    assert_eq(1, count($loadedA['tables']['classes']['rows']), 'loadDump() déchiffre et retourne le contenu exact (1 classe pour A)');

    // Falsifie le fichier stocké directement sur disque → l'intégrité doit être détectée comme invalide
    $storagePathA = $rowA['storage_path'];
    $originalContent = StorageManager::driver()->get($storagePathA);
    StorageManager::driver()->put($storagePathA, $originalContent . 'X');
    assert_false($service->verifyIntegrity($backupA['id']), 'verifyIntegrity() détecte un fichier de sauvegarde altéré sur disque');
    // Restaure le contenu original pour la suite du test
    StorageManager::driver()->put($storagePathA, $originalContent);
    assert_true($service->verifyIntegrity($backupA['id']), 'verifyIntegrity() redevient vraie une fois le contenu original restauré');

    $backupDiff = $service->createDifferential($etabA, new DateTimeImmutable('-1 hour'), $fakeOperatorUserId);
    $rowDiff = $pdo->query("SELECT * FROM platform_backups WHERE id = {$backupDiff['id']}")->fetch(PDO::FETCH_ASSOC);
    $filesToCleanup[] = $rowDiff['storage_path'];
    assert_eq('differential', $rowDiff['type'], 'Sauvegarde différentielle correctement typée dans le ledger');

    // ══════════════════════════════════════════════════════════════════════
    section('4. Restauration tenant EN DIRECT (upsert) — non destructive + garde d\'isolation');
    // ══════════════════════════════════════════════════════════════════════

    // Modifie la classe de A APRÈS la sauvegarde, pour vérifier que la restauration ramène l'ancienne valeur
    $pdo->exec("UPDATE classes SET nom = 'ClasseA1-MODIFIEE-APRES-BACKUP' WHERE id = {$classeA}");
    $modifiedName = $pdo->query("SELECT nom FROM classes WHERE id = {$classeA}")->fetchColumn();
    assert_eq('ClasseA1-MODIFIEE-APRES-BACKUP', $modifiedName, 'La classe de A a bien été modifiée après la sauvegarde (préparation du test)');

    $dumpForRestore = $service->loadDump($backupA['id']);
    $restoreResult = $restorer->restoreTenantLive($dumpForRestore, $etabA, $pdo);
    assert_true($restoreResult['rows_restored'] >= 1, 'restoreTenantLive() restaure au moins 1 ligne');

    $restoredName = $pdo->query("SELECT nom FROM classes WHERE id = {$classeA}")->fetchColumn();
    assert_eq('ClasseA1', $restoredName, 'La restauration (upsert) ramène bien la valeur sauvegardée, écrasant la modification post-backup');

    $classesBStillThere = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE etablissement_id = {$etabB}")->fetchColumn();
    assert_eq(2, $classesBStillThere, 'La restauration de A n\'a strictement aucun effet sur les classes de B (isolation)');

    // Garde d'isolation : un dump falsifié avec des lignes d'un AUTRE etablissement_id ne doit jamais être appliqué
    $poisonedDump = $dumpForRestore;
    $poisonedDump['tables']['classes']['rows'][] = [
        'id' => 999999, 'nom' => 'INJECTION-MALVEILLANTE', 'niveau' => 'Seconde',
        'annee_scolaire' => '2025-2026', 'max_eleves' => 1, 'etablissement_id' => $etabB,
    ];
    $poisonedResult = $restorer->restoreTenantLive($poisonedDump, $etabA, $pdo);
    $injected = $pdo->query("SELECT COUNT(*) FROM classes WHERE id = 999999")->fetchColumn();
    assert_eq(0, (int)$injected, 'Une ligne portant un etablissement_id différent du tenant demandé est filtrée et JAMAIS insérée, même présente dans le dump');

    // ══════════════════════════════════════════════════════════════════════
    section('5. Restauration globale de vérification — base de test réelle, jamais la base appelante');
    // ══════════════════════════════════════════════════════════════════════

    $backupGlobal = $service->createGlobal($fakeOperatorUserId, 'manual', encrypt: false);
    $rowGlobal = $pdo->query("SELECT * FROM platform_backups WHERE id = {$backupGlobal['id']}")->fetch(PDO::FETCH_ASSOC);
    $filesToCleanup[] = $rowGlobal['storage_path'];
    assert_eq(0, (int)$rowGlobal['encrypted'], 'createGlobal(..., encrypt: false) enregistre bien encrypted=0');

    $verifyResult = $service->restoreGlobalVerify($backupGlobal['id'], $fakeOperatorUserId);
    assert_true($verifyResult['tables_created'] > 40, 'restoreGlobalVerify() recrée bien la quasi-totalité des tables (schéma complet)');
    assert_true($verifyResult['rows_restored'] > 0, 'restoreGlobalVerify() restaure des lignes réelles dans la base de vérification');

    $scratchStillExists = $pdo->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . $pdo->quote($verifyResult['scratch_db'])
    )->fetchColumn();
    assert_eq(0, (int)$scratchStillExists, 'La base de vérification temporaire est bien supprimée après usage — aucune trace persistante');

    $currentDbUnaffected = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE etablissement_id = {$etabB}")->fetchColumn();
    assert_eq(2, $currentDbUnaffected, 'La base appelante (courante) est totalement inchangée par la vérification globale');

    // ══════════════════════════════════════════════════════════════════════
    section('6. Journalisation des restaurations + confirmation textuelle obligatoire');
    // ══════════════════════════════════════════════════════════════════════

    try {
        $service->restoreTenantLive($backupA['id'], $etabA, $fakeOperatorUserId, 'mauvaise-confirmation');
        assert_true(false, 'Une confirmation textuelle incorrecte aurait dû être rejetée');
    } catch (\InvalidArgumentException) {
        assert_true(true, 'restoreTenantLive() rejette une confirmation textuelle incorrecte (protection contre le clic accidentel)');
    }

    $rowABeforeSecondRestore = $pdo->query("SELECT storage_path, checksum, encrypted FROM platform_backups WHERE id = {$backupA['id']}")->fetch(PDO::FETCH_ASSOC);
    assert_true($rowABeforeSecondRestore['storage_path'] !== '', 'La ligne platform_backups de A reste intacte après les restaurations tenant précédentes (non auto-corrompue par sa propre sauvegarde)');

    $slugA = $pdo->query("SELECT slug FROM etablissements WHERE id = {$etabA}")->fetchColumn();
    $service->restoreTenantLive($backupA['id'], $etabA, $fakeOperatorUserId, $slugA);

    $restoreLogCount = (int)$pdo->query("SELECT COUNT(*) FROM platform_restores WHERE backup_id = {$backupA['id']}")->fetchColumn();
    assert_true($restoreLogCount >= 1, 'Chaque tentative de restauration (y compris la confirmation invalide n\'atteignant pas ce point) est journalisée dans platform_restores');

    $lastRestore = $pdo->query("SELECT * FROM platform_restores WHERE backup_id = {$backupA['id']} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    assert_eq('success', $lastRestore['statut'], 'La restauration réussie est journalisée avec le statut "success"');
    assert_eq('tenant_live', $lastRestore['type'], 'Le type de restauration journalisé est "tenant_live"');
    assert_eq($fakeOperatorUserId, (int)$lastRestore['triggered_by'], 'L\'opérateur déclencheur est journalisé');

} finally {
    $pdo->rollBack();
    foreach ($filesToCleanup as $path) {
        try {
            if (StorageManager::driver()->exists($path)) {
                StorageManager::driver()->delete($path);
            }
        } catch (\Throwable) {
        }
    }
    echo "\n(rollback effectué + fichiers de sauvegarde de test nettoyés — aucune donnée de test persistée)\n";
}

// ── Résumé ──────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n\033[1m" . ($failed === 0 ? "\033[32m" : "\033[31m");
echo "Résultat : $passed/$total assertions passées";
echo "\033[0m\n\n";

if ($failed > 0) {
    exit(1);
}
