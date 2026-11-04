<?php
/**
 * Tests — DatabaseRestorer et colonnes GÉNÉRÉES (correctif lié à T067 : platform_subscriptions.est_courant).
 *
 * MySQL refuse toute écriture dans une colonne générée (erreur 3105). Une sauvegarde contient pourtant la valeur de cette colonne
 * (SELECT *) : la restauration doit l'écarter de l'INSERT et laisser MySQL la recalculer.
 *
 *  1. Témoin : une écriture directe dans la colonne générée est bien refusée par MySQL (le test est sensible)
 *  2. Mode « base neuve » (restoreGlobalToScratch) : colonnes générées exclues, valeurs recalculées, colonnes ordinaires intactes
 *  3. Ligne dont TOUTES les colonnes sont générées : ignorée sans erreur ni SQL invalide
 *  4. Table sans colonne générée : comportement inchangé (valeurs, NULL, accents, décimaux, compte)
 *  5. Lots de plus de 200 lignes avec colonne générée (BATCH_SIZE)
 *  6. Mode « tenant » (restoreTenantLive, upsert) : ON DUPLICATE KEY UPDATE sans la colonne générée
 *  7. Sauvegarde antérieure à T067 (aucune colonne générée dans le dump, table absente, ligne sans la colonne)
 *  8. Sauvegarde contenant est_courant sur la VRAIE table platform_subscriptions
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante et les sauvegardes existantes ne sont jamais touchées.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/DatabaseRestorerColonnesGenereesTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'Core\\')) {
        $f = ROOT_PATH . '/core/' . str_replace('\\', '/', substr($c, 5)) . '.php';
        if (is_file($f)) { require $f; }
    }
});
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use Core\Backup\DatabaseRestorer;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
/** Exception levée par $f (null si aucune). */
function leve(callable $f): ?\Throwable
{
    try { $f(); return null; } catch (\Throwable $e) { return $e; }
}

$copie = \Tests\Support\DbCopy::create(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
try {
    $pdo = $copie->pdo();
    $nomBase = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!preg_match('/^ecole_app_copy_[a-f0-9]+$/', $nomBase)) { exit("REFUS : la base de travail n'est pas une copie jetable ($nomBase)\n"); }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $restorer = new DatabaseRestorer();

    // Tables de test (préfixe gt_ : n'existent dans aucune sauvegarde réelle).
    $creerSub = static function (string $nom) use ($pdo): string {
        $pdo->exec("DROP TABLE IF EXISTS `$nom`");
        $sql = "CREATE TABLE `$nom` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `groupe` INT UNSIGNED NOT NULL,
            `statut` VARCHAR(20) NOT NULL,
            `libelle` VARCHAR(100) NULL,
            `montant` DECIMAL(10,2) NULL,
            `est_courant` TINYINT GENERATED ALWAYS AS (CASE WHEN `statut` IN ('actif','essai') THEN 1 ELSE NULL END) STORED,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_{$nom}_courant` (`groupe`, `est_courant`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $pdo->exec($sql);
        return $sql;
    };
    $dumpGlobal = static fn(array $tables): array => ['meta' => ['type' => 'global'], 'tables' => $tables];
    $lignes = static fn(string $t): array => $pdo->query("SELECT id, groupe, statut, libelle, montant, est_courant FROM `$t` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('1. Témoin : MySQL refuse l\'écriture directe dans une colonne générée');
    $createSub = $creerSub('gt_sub');
    $e = leve(fn() => $pdo->exec("INSERT INTO gt_sub (groupe, statut, est_courant) VALUES (1, 'actif', 1)"));
    check($e instanceof PDOException && str_contains($e->getMessage(), '3105'), 'INSERT direct avec la colonne générée : erreur 3105 (le correctif est bien nécessaire)', $e ? $e->getMessage() : 'aucune erreur');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('2. Mode base neuve : colonnes générées exclues, ordinaires conservées');
    $rows = [
        ['id' => 1, 'groupe' => 10, 'statut' => 'actif',  'libelle' => 'Établissement « Été »', 'montant' => '5000.50', 'est_courant' => 1],
        ['id' => 2, 'groupe' => 10, 'statut' => 'expiré', 'libelle' => null,                  'montant' => null,      'est_courant' => null],
        ['id' => 3, 'groupe' => 11, 'statut' => 'essai',  'libelle' => 'Ligne trois',         'montant' => '0.00',    'est_courant' => 1],
        // La valeur sauvegardée de la colonne générée est volontairement FAUSSE : elle doit être ignorée puis recalculée.
        ['id' => 4, 'groupe' => 12, 'statut' => 'expiré', 'libelle' => 'valeur fausse',       'montant' => '1.00',    'est_courant' => 99],
    ];
    $r = leve(function () use ($restorer, $dumpGlobal, $createSub, $rows, $pdo, &$res) {
        $res = $restorer->restoreGlobalToScratch($dumpGlobal(['gt_sub' => ['create' => $createSub, 'rows' => $rows]]), $pdo);
    });
    check($r === null, 'restauration d\'un dump contenant la colonne générée : aucune erreur', $r ? $r->getMessage() : '');
    check(($res['rows_restored'] ?? -1) === 4 && ($res['tables_created'] ?? -1) === 1, 'compte : 4 lignes restaurées, 1 table créée', json_encode($res ?? null));
    $apres = $lignes('gt_sub');
    check(count($apres) === 4, '4 lignes présentes');
    check($apres[0]['libelle'] === 'Établissement « Été »' && $apres[0]['montant'] === '5000.50' && $apres[0]['statut'] === 'actif' && (int)$apres[0]['groupe'] === 10, 'colonnes ordinaires conservées (accents, guillemets, décimal exact)');
    check($apres[1]['libelle'] === null && $apres[1]['montant'] === null, 'NULL conservés');
    check((int)$apres[0]['est_courant'] === 1 && $apres[1]['est_courant'] === null && (int)$apres[2]['est_courant'] === 1, 'colonne générée recalculée par MySQL (1 / NULL / 1)');
    check($apres[3]['est_courant'] === null, 'la valeur sauvegardée fausse (99) est ignorée : recalculée à NULL');
    check($apres[3]['libelle'] === 'valeur fausse' && $apres[3]['montant'] === '1.00', 'la ligne à valeur générée fausse est restaurée avec ses colonnes ordinaires');
    check((int)$pdo->query("SELECT COUNT(*) FROM gt_sub WHERE est_courant = 1")->fetchColumn() === 2, 'l\'index unique sur la colonne générée est cohérent (2 courants, groupes différents)');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('3. Ligne dont toutes les colonnes sont générées');
    $createSub = $creerSub('gt_sub');
    $r = leve(function () use ($restorer, $dumpGlobal, $createSub, $pdo, &$res) {
        $res = $restorer->restoreGlobalToScratch($dumpGlobal(['gt_sub' => ['create' => $createSub, 'rows' => [
            ['est_courant' => 1],
            ['id' => 5, 'groupe' => 20, 'statut' => 'actif', 'libelle' => 'normale', 'montant' => '10.00', 'est_courant' => 1],
            ['est_courant' => null],
        ]]]), $pdo);
    });
    check($r === null, 'lignes entièrement générées : ignorées sans erreur SQL', $r ? $r->getMessage() : '');
    check(($res['rows_restored'] ?? -1) === 1 && count($lignes('gt_sub')) === 1 && $lignes('gt_sub')[0]['libelle'] === 'normale', 'seule la ligne ordinaire est restaurée et comptée', json_encode($res ?? null));
    $createSub = $creerSub('gt_sub');
    $r = leve(function () use ($restorer, $dumpGlobal, $createSub, $pdo, &$res) {
        $res = $restorer->restoreGlobalToScratch($dumpGlobal(['gt_sub' => ['create' => $createSub, 'rows' => [['est_courant' => 1]]]]), $pdo);
    });
    check($r === null && ($res['rows_restored'] ?? -1) === 0 && count($lignes('gt_sub')) === 0, 'dump réduit à des lignes entièrement générées : 0 ligne, aucune erreur');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('4. Table sans colonne générée : comportement inchangé');
    $pdo->exec('DROP TABLE IF EXISTS gt_plain');
    $createPlain = "CREATE TABLE `gt_plain` (`id` INT UNSIGNED NOT NULL, `nom` VARCHAR(80) NOT NULL, `note` TEXT NULL, `prix` DECIMAL(10,2) NOT NULL DEFAULT 0.00, `actif` TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $plain = [
        ['id' => 1, 'nom' => 'Élève — Zoé', 'note' => "ligne1\nligne2 'quote'", 'prix' => '2500.00', 'actif' => 1],
        ['id' => 2, 'nom' => 'Sans note',    'note' => null,                      'prix' => '0.00',    'actif' => 0],
    ];
    $r = leve(function () use ($restorer, $dumpGlobal, $createPlain, $plain, $pdo, &$res) {
        $res = $restorer->restoreGlobalToScratch($dumpGlobal(['gt_plain' => ['create' => $createPlain, 'rows' => $plain]]), $pdo);
    });
    $lus = $pdo->query('SELECT id, nom, note, prix, actif FROM gt_plain ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    check($r === null && ($res['rows_restored'] ?? -1) === 2, 'table ordinaire restaurée, 2 lignes comptées', $r ? $r->getMessage() : json_encode($res ?? null));
    check($lus[0]['nom'] === 'Élève — Zoé' && $lus[0]['note'] === "ligne1\nligne2 'quote'" && $lus[0]['prix'] === '2500.00' && (int)$lus[0]['actif'] === 1 && $lus[1]['note'] === null && (int)$lus[1]['actif'] === 0, 'valeurs identiques au dump (accents, retours à la ligne, apostrophes, NULL, décimal, booléen)');
    $r = leve(fn() => $restorer->restoreGlobalToScratch($dumpGlobal(['gt_plain' => ['create' => $createPlain, 'rows' => []]]), $pdo));
    check($r === null && (int)$pdo->query('SELECT COUNT(*) FROM gt_plain')->fetchColumn() === 0, 'table vide : recréée sans ligne, sans erreur');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('5. Lots de plus de 200 lignes avec colonne générée');
    $createSub = $creerSub('gt_sub');
    $gros = [];
    for ($i = 1; $i <= 450; $i++) {
        $gros[] = ['id' => $i, 'groupe' => $i, 'statut' => $i % 3 === 0 ? 'actif' : 'expiré', 'libelle' => "L$i", 'montant' => '1.00', 'est_courant' => $i % 3 === 0 ? 1 : 0];
    }
    $r = leve(function () use ($restorer, $dumpGlobal, $createSub, $gros, $pdo, &$res) {
        $res = $restorer->restoreGlobalToScratch($dumpGlobal(['gt_sub' => ['create' => $createSub, 'rows' => $gros]]), $pdo);
    });
    check($r === null && ($res['rows_restored'] ?? -1) === 450 && (int)$pdo->query('SELECT COUNT(*) FROM gt_sub')->fetchColumn() === 450, '450 lignes (3 lots) restaurées', $r ? $r->getMessage() : json_encode($res ?? null));
    check((int)$pdo->query('SELECT COUNT(*) FROM gt_sub WHERE est_courant = 1')->fetchColumn() === 150 && (int)$pdo->query('SELECT COUNT(*) FROM gt_sub WHERE est_courant IS NULL')->fetchColumn() === 300, 'colonne générée recalculée sur tous les lots (150 courants, 300 NULL)');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('6. Mode tenant (upsert)');
    $pdo->exec('DROP TABLE IF EXISTS gt_tenant');
    $pdo->exec("CREATE TABLE `gt_tenant` (`id` INT UNSIGNED NOT NULL, `etablissement_id` INT UNSIGNED NOT NULL, `etat` VARCHAR(20) NOT NULL, `note` VARCHAR(50) NULL,
        `est_actif` TINYINT GENERATED ALWAYS AS (`etat` = 'actif') STORED, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("INSERT INTO gt_tenant (id, etablissement_id, etat, note) VALUES (1, 7, 'actif', 'avant'), (2, 7, 'actif', 'inchangée'), (3, 8, 'actif', 'autre établissement')");
    $dumpTenant = ['meta' => ['type' => 'tenant', 'etablissement_id' => 7], 'tables' => ['gt_tenant' => ['rows' => [
        ['id' => 1, 'etablissement_id' => 7, 'etat' => 'clos', 'note' => 'après', 'est_actif' => 1],
        ['id' => 4, 'etablissement_id' => 7, 'etat' => 'actif', 'note' => 'nouvelle', 'est_actif' => 0],
        ['id' => 3, 'etablissement_id' => 8, 'etat' => 'clos', 'note' => 'ne doit pas passer', 'est_actif' => 1],
    ]]]];
    $r = leve(function () use ($restorer, $dumpTenant, $pdo, &$res) { $res = $restorer->restoreTenantLive($dumpTenant, 7, $pdo); });
    check($r === null && ($res['rows_restored'] ?? -1) === 2, 'upsert avec colonne générée dans le dump : aucune erreur, 2 lignes du tenant 7', $r ? $r->getMessage() : json_encode($res ?? null));
    $t = $pdo->query('SELECT id, etat, note, est_actif FROM gt_tenant ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    check($t[0]['etat'] === 'clos' && $t[0]['note'] === 'après' && (int)$t[0]['est_actif'] === 0, 'ligne existante mise à jour (ON DUPLICATE KEY UPDATE) et colonne générée recalculée (0, pas la valeur sauvegardée 1)');
    check($t[1]['note'] === 'inchangée' && $t[2]['note'] === 'autre établissement' && $t[2]['etat'] === 'actif', 'autres lignes intactes, dont celle d\'un autre établissement (garde d\'isolation inchangée)');
    check(count($t) === 4 && $t[3]['note'] === 'nouvelle' && (int)$t[3]['est_actif'] === 1, 'nouvelle ligne insérée, colonne générée recalculée (1)');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('7. Sauvegarde antérieure à T067');
    // (a) dump sans aucune colonne générée, tables réelles : plans SaaS restaurés à l'identique, table platform_subscriptions absente du dump
    $createPlans = (string)$pdo->query('SHOW CREATE TABLE platform_plans')->fetch(PDO::FETCH_NUM)[1];
    $plansAvant = $pdo->query('SELECT * FROM platform_plans ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $r = leve(function () use ($restorer, $dumpGlobal, $createPlans, $plansAvant, $pdo, &$res) {
        $res = $restorer->restoreGlobalToScratch($dumpGlobal(['platform_plans' => ['create' => $createPlans, 'rows' => $plansAvant]]), $pdo);
    });
    check($r === null && ($res['rows_restored'] ?? -1) === count($plansAvant) && $plansAvant !== [], 'table réelle sans colonne générée (platform_plans) : restaurée, comptes identiques', $r ? $r->getMessage() : json_encode($res ?? null));
    check($pdo->query('SELECT * FROM platform_plans ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $plansAvant, 'platform_plans restaurée strictement à l\'identique (toutes colonnes)');
    // (b) ligne ancienne SANS la colonne générée dans une table qui la possède (dump produit avant l'ajout de la colonne)
    $createSub = $creerSub('gt_sub');
    $r = leve(function () use ($restorer, $dumpGlobal, $createSub, $pdo, &$res) {
        $res = $restorer->restoreGlobalToScratch($dumpGlobal(['gt_sub' => ['create' => $createSub, 'rows' => [
            ['id' => 1, 'groupe' => 1, 'statut' => 'actif', 'libelle' => 'ancien format', 'montant' => '3.00'],
            ['id' => 2, 'groupe' => 2, 'statut' => 'expiré', 'libelle' => 'ancien format', 'montant' => '4.00'],
        ]]]), $pdo);
    });
    $l = $lignes('gt_sub');
    check($r === null && ($res['rows_restored'] ?? -1) === 2 && (int)$l[0]['est_courant'] === 1 && $l[1]['est_courant'] === null, 'lignes sans la colonne générée : restaurées, colonne calculée par MySQL', $r ? $r->getMessage() : json_encode($res ?? null));
    // (c) une table du dump qui n'existe pas dans la base de restauration (table supprimée depuis) : la lecture des colonnes générées ne doit pas casser
    $r = leve(fn() => $restorer->restoreGlobalToScratch($dumpGlobal(['gt_plain' => ['create' => $createPlain, 'rows' => [['id' => 9, 'nom' => 'x', 'note' => null, 'prix' => '1.00', 'actif' => 1]]]]), $pdo));
    check($r === null && (int)$pdo->query('SELECT COUNT(*) FROM gt_plain')->fetchColumn() === 1, 'table recréée par le dump puis remplie : aucune interférence entre tables');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('8. Sauvegarde contenant est_courant (vraie table platform_subscriptions)');
    $createReel = (string)$pdo->query('SHOW CREATE TABLE platform_subscriptions')->fetch(PDO::FETCH_NUM)[1];
    $now = (new DateTimeImmutable('2026-03-10'))->format('Y-m-d');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $etabId = (int)$pdo->query('SELECT id FROM etablissements ORDER BY id LIMIT 1')->fetchColumn();
    $planId = (int)$pdo->query('SELECT id FROM platform_plans ORDER BY id LIMIT 1')->fetchColumn();
    $pdo->exec('DELETE FROM platform_subscriptions');
    $pdo->exec("INSERT INTO platform_subscriptions (id, etablissement_id, plan_id, statut, cycle, date_debut, date_fin, devise, jours_grace_appliques) VALUES
        (1, $etabId, $planId, 'expired', 'monthly', '2026-01-01', '2026-01-31', 'XOF', 0),
        (2, $etabId, $planId, 'superseded', 'monthly', '2026-02-01', '2026-02-28', 'XOF', 0),
        (3, $etabId, $planId, 'active', 'monthly', '$now', '2026-04-09', 'XOF', 5)");
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $dumpRows = $pdo->query('SELECT * FROM platform_subscriptions ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    check(array_key_exists('est_courant', $dumpRows[0]) && (int)$dumpRows[2]['est_courant'] === 1 && $dumpRows[0]['est_courant'] === null, 'préparation : le SELECT * d\'une sauvegarde contient bien est_courant (1 pour l\'abonnement courant)');
    $r = leve(function () use ($restorer, $dumpGlobal, $createReel, $dumpRows, $pdo, &$res) {
        $res = $restorer->restoreGlobalToScratch($dumpGlobal(['platform_subscriptions' => ['create' => $createReel, 'rows' => $dumpRows]]), $pdo);
    });
    check($r === null && ($res['rows_restored'] ?? -1) === 3, 'restauration de platform_subscriptions avec est_courant : aucune erreur 3105, 3 lignes', $r ? $r->getMessage() : json_encode($res ?? null));
    check($pdo->query('SELECT * FROM platform_subscriptions ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $dumpRows, 'table restaurée strictement identique à la source, est_courant compris');
    check((int)$pdo->query("SELECT COUNT(*) FROM platform_subscriptions WHERE est_courant = 1")->fetchColumn() === 1, 'un seul abonnement courant (index unique intact)');
    $dumpFaux = $dumpRows; $dumpFaux[0]['est_courant'] = 1; $dumpFaux[1]['est_courant'] = 1;   // altération : deux « courants » en plus dans le dump
    $r = leve(fn() => $restorer->restoreGlobalToScratch($dumpGlobal(['platform_subscriptions' => ['create' => $createReel, 'rows' => $dumpFaux]]), $pdo));
    check($r === null && (int)$pdo->query("SELECT COUNT(*) FROM platform_subscriptions WHERE est_courant = 1")->fetchColumn() === 1, 'dump altéré (est_courant = 1 sur des périodes expirées) : la valeur sauvegardée est ignorée, l\'unicité du courant tient');
} finally {
    $copie->drop();
}

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
