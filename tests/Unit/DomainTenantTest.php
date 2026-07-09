<?php
/**
 * Tests Domaines Personnalisés — Phase 14.7
 *
 * 1. isValidHostname() — formats acceptés/rejetés
 * 2. addDomain() — création, unicité globale, limite par établissement,
 *    génération de token
 * 3. verify() — challenge DNS TXT (lookup injecté, aucun réseau réel),
 *    succès/échec, idempotence, isolation stricte inter-tenant
 * 4. toggleActive() / delete() — isolation stricte inter-tenant
 *    (findOwned() rejette tout domain_id n'appartenant pas à l'appelant)
 * 5. Résolution multi-tenant par domaine — TenantRepository::findByDomain()
 *    distingue correctement 2 établissements différents par leur domaine
 *
 * Toutes les données de test sont créées/détruites dans une transaction
 * PDO annulée en fin de script — zéro pollution.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/DomainTenantTest.php
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
        'Core\\' => ROOT_PATH . '/core/',
    ];
    foreach ($namespaces as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file = $dir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) { require $file; return; }
        }
    }
});

use Core\Database;
use Core\Tenant\DomainVerificationService;
use Core\Tenant\TenantRepository;

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
    // Deux établissements de test réels (pour la résolution multi-tenant en fin de script)
    $pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type, pays, statut)
                VALUES ('test-domain-a', 'Établissement Domaine Test A', 'Dom A', 'lycee', 'DZ', 'active')");
    $etabA = (int)$pdo->lastInsertId();

    $pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type, pays, statut)
                VALUES ('test-domain-b', 'Établissement Domaine Test B', 'Dom B', 'lycee', 'DZ', 'active')");
    $etabB = (int)$pdo->lastInsertId();

    echo "Établissement A (test) = {$etabA}, Établissement B (test) = {$etabB}\n";

    // Service avec lookup DNS injecté par défaut (renvoie toujours vide — pas de réseau réel)
    $noopDns = static fn(string $domain): array => [];
    $service = new DomainVerificationService($pdo, $noopDns);

    // ══════════════════════════════════════════════════════════════════════
    section('1. isValidHostname() — formats acceptés / rejetés');
    // ══════════════════════════════════════════════════════════════════════

    assert_true($service->isValidHostname('ecole-exemple.com'), 'Domaine simple valide');
    assert_true($service->isValidHostname('sub.ecole-exemple.com'), 'Sous-domaine valide');
    assert_true($service->isValidHostname('ecole.dz'), 'TLD court valide');

    assert_false($service->isValidHostname(''), 'Chaîne vide rejetée');
    assert_false($service->isValidHostname('-bad.com'), 'Label commençant par un tiret rejeté');
    assert_false($service->isValidHostname('bad-.com'), 'Label finissant par un tiret rejeté');
    assert_false($service->isValidHostname('sanspointtld'), 'Absence de TLD rejetée');
    assert_false($service->isValidHostname('under_score.com'), 'Underscore rejeté (RFC 1123)');
    assert_false($service->isValidHostname(str_repeat('a', 250) . '.com'), 'Hôte > 253 caractères rejeté');
    assert_false($service->isValidHostname('ecole exemple.com'), 'Espace rejeté');

    // ══════════════════════════════════════════════════════════════════════
    section('2. addDomain() — création, unicité, limite, token');
    // ══════════════════════════════════════════════════════════════════════

    $added = $service->addDomain($etabA, 'Ecole-Test-A.example.com');
    assert_eq('ecole-test-a.example.com', $added['domain'], 'Domaine normalisé en minuscules');
    assert_eq(32, strlen($added['verification_token']), 'Token généré (32 caractères hex)');
    assert_eq('_edunova-verify.ecole-test-a.example.com', $added['txt_record_name'], 'Nom d\'enregistrement TXT correct');
    assert_eq('edunova-verify=' . $added['verification_token'], $added['txt_record_value'], 'Valeur d\'enregistrement TXT correcte');

    $rows = $service->listForEtablissement($etabA);
    assert_eq(1, count($rows), 'listForEtablissement retourne le domaine créé');
    assert_false($rows[0]['verified'], 'Domaine nouvellement créé : non vérifié');
    assert_eq('pending', $rows[0]['ssl_status'], 'Domaine nouvellement créé : ssl_status pending');

    try {
        $service->addDomain($etabB, 'invalide domaine !!');
        assert_true(false, 'Format invalide : InvalidArgumentException attendue');
    } catch (\InvalidArgumentException) {
        assert_true(true, 'Format invalide rejeté (InvalidArgumentException)');
    }

    try {
        $service->addDomain($etabB, 'ecole-test-a.example.com'); // déjà pris par etabA
        assert_true(false, 'Domaine déjà pris : InvalidArgumentException attendue');
    } catch (\InvalidArgumentException) {
        assert_true(true, 'Unicité globale respectée (domaine déjà pris par un autre établissement)');
    }

    for ($i = 1; $i <= 10; $i++) {
        $service->addDomain($etabB, "domaine-{$i}.example.com");
    }
    assert_eq(10, $service->countForEtablissement($etabB), 'Limite de 10 domaines atteinte pour etabB');
    try {
        $service->addDomain($etabB, 'domaine-11.example.com');
        assert_true(false, 'Limite dépassée : InvalidArgumentException attendue');
    } catch (\InvalidArgumentException) {
        assert_true(true, 'Limite de 10 domaines par établissement appliquée');
    }

    // ══════════════════════════════════════════════════════════════════════
    section('3. verify() — challenge DNS TXT (lookup injecté)');
    // ══════════════════════════════════════════════════════════════════════

    $domainRow = $rows[0];
    $expectedTxt = 'edunova-verify=' . $domainRow['verification_token'];

    $successDns = static fn(string $domain): array => [['txt' => 'edunova-verify=WRONGTOKEN'], ['txt' => $GLOBALS['__expectedTxt']]];
    $GLOBALS['__expectedTxt'] = $expectedTxt;
    $successService = new DomainVerificationService($pdo, $successDns);
    assert_true($successService->verify($etabA, (int)$domainRow['id']), 'Vérification réussie (TXT correspondant trouvé)');

    $rowsAfter = $service->listForEtablissement($etabA);
    assert_true($rowsAfter[0]['verified'], 'Domaine marqué vérifié après verify() réussi');

    assert_true($successService->verify($etabA, (int)$domainRow['id']), 'Vérification idempotente (déjà vérifié → true sans re-vérifier DNS)');

    // Nouveau domaine non vérifié, DNS ne renvoie rien de correspondant
    $added2 = $service->addDomain($etabA, 'jamais-verifie.example.com');
    $failDns = static fn(string $domain): array => [['txt' => 'autre-chose']];
    $failService = new DomainVerificationService($pdo, $failDns);
    assert_false($failService->verify($etabA, $added2['id']), 'Vérification échoue si aucun enregistrement TXT ne correspond');

    // Isolation : etabB ne peut pas vérifier un domaine appartenant à etabA
    assert_false($successService->verify($etabB, $added2['id']), 'Isolation : etabB ne peut pas vérifier un domaine de etabA');
    $stillUnverified = $service->listForEtablissement($etabA);
    $found = array_values(array_filter($stillUnverified, fn($r) => $r['id'] === $added2['id']));
    assert_false($found[0]['verified'], 'Le domaine de etabA reste non vérifié après tentative illégitime de etabB');

    // ══════════════════════════════════════════════════════════════════════
    section('4. toggleActive() / delete() — isolation stricte inter-tenant');
    // ══════════════════════════════════════════════════════════════════════

    assert_false($service->toggleActive($etabB, $added2['id'], false), 'Isolation : etabB ne peut pas désactiver un domaine de etabA');
    assert_false($service->delete($etabB, $added2['id']), 'Isolation : etabB ne peut pas supprimer un domaine de etabA');

    $stillThere = $pdo->prepare('SELECT COUNT(*) FROM etablissement_domains WHERE id = ?');
    $stillThere->execute([$added2['id']]);
    assert_eq(1, (int)$stillThere->fetchColumn(), 'Le domaine de etabA existe toujours après tentative de suppression illégitime');

    assert_true($service->toggleActive($etabA, $added2['id'], false), 'Le propriétaire légitime peut désactiver son propre domaine');
    assert_true($service->delete($etabA, $added2['id']), 'Le propriétaire légitime peut supprimer son propre domaine');

    $deletedCheck = $pdo->prepare('SELECT COUNT(*) FROM etablissement_domains WHERE id = ?');
    $deletedCheck->execute([$added2['id']]);
    assert_eq(0, (int)$deletedCheck->fetchColumn(), 'Le domaine est bien supprimé de la base');

    // ══════════════════════════════════════════════════════════════════════
    section('5. Résolution multi-tenant par domaine — TenantRepository::findByDomain()');
    // ══════════════════════════════════════════════════════════════════════

    $repo = new TenantRepository($pdo);

    // domainRow (ecole-test-a...) est vérifié pour etabA depuis la section 3
    $resolvedA = $repo->findByDomain('ecole-test-a.example.com');
    assert_true($resolvedA !== null, 'findByDomain résout le domaine vérifié de etabA');
    assert_eq($etabA, $resolvedA?->id, 'findByDomain retourne le bon établissement pour son domaine');

    // Vérifie un domaine de etabB pour tester la distinction entre 2 tenants différents
    $etabBDomain = $service->listForEtablissement($etabB)[0];
    $dnsForB = static fn(string $domain): array => [['txt' => 'edunova-verify=' . $etabBDomain['verification_token']]];
    (new DomainVerificationService($pdo, $dnsForB))->verify($etabB, (int)$etabBDomain['id']);

    $resolvedB = $repo->findByDomain($etabBDomain['domain']);
    assert_true($resolvedB !== null, 'findByDomain résout le domaine vérifié de etabB');
    assert_eq($etabB, $resolvedB?->id, 'findByDomain retourne le bon établissement (distinct de etabA) pour son domaine');
    assert_true($resolvedA?->id !== $resolvedB?->id, 'Deux domaines différents résolvent bien vers deux établissements distincts');

    $unverifiedDomain = $service->listForEtablissement($etabB)[1] ?? null;
    if ($unverifiedDomain !== null) {
        assert_true($repo->findByDomain($unverifiedDomain['domain']) === null, 'Un domaine non vérifié ne résout vers aucun établissement (protection anti-usurpation)');
    }

    assert_true($repo->findByDomain('domaine-totalement-inconnu.example.com') === null, 'Un domaine inconnu ne résout vers aucun établissement');

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
