<?php
/**
 * Tests — migration T046 (schéma des jetons de vérification des bulletins), sur une COPIE RÉELLE de la base
 *
 * Une copie complète de la base de développement (structure, données, index, clés étrangères) est importée
 * dans une base temporaire ; T046 est exécutée et annulée sur cette copie. La base source n'est jamais
 * écrite : le test le prouve en comparant son empreinte avant / après.
 *
 * Vérifie : aucune donnée existante modifiée (aucun jeton touché), contraintes d'origine intactes, idempotence,
 * clé étrangère de la table des jetons retirés, retour arrière refusé quand il perdrait de l'information,
 * retour arrière exact (structure et données identiques à l'état initial).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/BulletinTokenSchemaTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use Tests\Support\DbCopy;

$passed = 0; $failed = 0; $skipped = 0;
function check(bool $ok, string $label): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
function leve(callable $f): ?string { try { $f(); return null; } catch (\Throwable $e) { return $e->getMessage(); } }

$conn = DbCopy::connectionFromEnv(ROOT_PATH);
$source = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['db']};charset=utf8mb4", $conn['user'], $conn['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$tablesSurveillees = ['bulletins_v2', 'eleves', 'classes', 'periodes_scolaires'];
$avantSource = ['schema' => DbCopy::schema($source, $tablesSurveillees), 'data' => DbCopy::allDataHashes($source)];

$copy = DbCopy::create(ROOT_PATH);
if ($copy === null) {
    echo "\033[33m  - SKIP (copie réelle impossible : mysqldump / mysql / droit CREATE DATABASE indisponibles)\033[0m\n";
    exit(0);
}

try {
    $db  = $copy->pdo();
    $mig = require ROOT_PATH . '/database/migrations/T046_bulletin_token_schema.php';
    $run = $mig['run']; $rollback = $mig['rollback'];

    // État de départ identique à « avant T046 », que la source l'ait déjà reçue ou non
    $dejaLa = (int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bulletins_v2' AND column_name = 'token_version'")->fetchColumn();
    if ($dejaLa > 0) { $db->exec("UPDATE bulletins_v2 SET token_version = 1, updated_at = updated_at"); $rollback($db); }

    section('1. État initial de la copie');
    $schemaAvant = DbCopy::schema($db, $tablesSurveillees);
    $donneesAvant = DbCopy::allDataHashes($db);
    $bulletinsAvant = $db->query("SELECT id, eleve_id, periode_id, verification_token, statut, data_json FROM bulletins_v2 ORDER BY id")->fetchAll();
    $nb = count($bulletinsAvant);
    check($nb > 0, "la copie contient les bulletins réels ($nb lignes)");
    check(!str_contains($schemaAvant['bulletins_v2'], 'token_version'), 'la copie n\'a pas encore la colonne token_version');
    check($db->query("SHOW TABLES LIKE 'bulletin_tokens_retired'")->fetch() === false, 'la copie n\'a pas encore la table bulletin_tokens_retired');
    $fkAvant = (int)$db->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE()")->fetchColumn();

    section('2. T046 appliquée sur la copie');
    $run($db);
    $col = $db->query("SELECT column_type AS column_type, is_nullable AS is_nullable, column_default AS column_default FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bulletins_v2' AND column_name = 'token_version'")->fetch();
    check($col && str_contains($col['column_type'], 'tinyint') && str_contains($col['column_type'], 'unsigned') && $col['is_nullable'] === 'NO' && (string)$col['column_default'] === '1',
          'colonne token_version : TINYINT UNSIGNED NOT NULL DEFAULT 1');
    check((int)$db->query("SELECT COUNT(*) FROM bulletins_v2 WHERE token_version = 1")->fetchColumn() === $nb, "les $nb bulletins existants reçoivent token_version = 1 (jeton hérité)");
    $bulletinsApres = $db->query("SELECT id, eleve_id, periode_id, verification_token, statut, data_json FROM bulletins_v2 ORDER BY id")->fetchAll();
    check($bulletinsApres === $bulletinsAvant, 'AUCUN jeton, JSON ni statut modifié (comparaison exacte de toutes les lignes)');
    check(DbCopy::dataHash($db, 'bulletins_v2', ['token_version']) === $donneesAvant['bulletins_v2'], 'bulletins_v2 : toutes les autres colonnes identiques (empreinte)');

    $apres = DbCopy::allDataHashes($db, ['bulletin_tokens_retired']);
    $diff = array_keys(array_filter($apres, fn($h, $t) => $t !== 'bulletins_v2' && ($donneesAvant[$t] ?? null) !== $h, ARRAY_FILTER_USE_BOTH));
    check($diff === [], 'toutes les autres tables sont strictement identiques (' . count($apres) . ' tables comparées)');

    $schemaApres = DbCopy::schema($db, $tablesSurveillees);
    $sansColonne = preg_replace('/\n\s*`token_version`[^\n]*,/', '', $schemaApres['bulletins_v2']);
    check($sansColonne === $schemaAvant['bulletins_v2'], 'bulletins_v2 : structure, index (uq_token, uq_bulletin_eleve_per…) et clés étrangères d\'origine intacts');
    foreach (['eleves', 'classes', 'periodes_scolaires'] as $t) {
        check($schemaApres[$t] === $schemaAvant[$t], "$t : structure inchangée");
    }

    section('3. Table des jetons retirés');
    $ddl = (string)$db->query("SHOW CREATE TABLE bulletin_tokens_retired")->fetch(PDO::FETCH_NUM)[1];
    check(DbCopy::countRows($db, 'bulletin_tokens_retired') === 0, 'table créée vide (aucun jeton retiré à ce stade)');
    check(str_contains($ddl, 'UNIQUE KEY `uq_btr_sha` (`token_sha256`)'), 'unicité sur l\'empreinte du jeton');
    check(str_contains($ddl, 'ON DELETE SET NULL') && str_contains($ddl, 'REFERENCES `bulletins_v2`'), 'clé étrangère vers bulletins_v2 (ON DELETE SET NULL)');
    check(str_contains($ddl, 'ascii_bin'), 'empreinte comparée octet par octet (collation binaire)');
    check((int)$db->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE()")->fetchColumn() === $fkAvant + 1, 'une seule clé étrangère ajoutée au total');

    section('4. Idempotence');
    $run($db); $run($db);
    check(DbCopy::schema($db, $tablesSurveillees) === $schemaApres, 'deux exécutions supplémentaires : structure identique');
    $norm = fn(array $rows) => array_map(fn($r) => array_map('strval', $r), $rows);
    check($norm($db->query("SELECT id, verification_token, token_version, data_json FROM bulletins_v2 ORDER BY id")->fetchAll())
          === $norm(array_map(fn($r) => ['id' => $r['id'], 'verification_token' => $r['verification_token'], 'token_version' => 1, 'data_json' => $r['data_json']], $bulletinsAvant)),
          'deux exécutions supplémentaires : données identiques');
    check((int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bulletins_v2' AND column_name = 'token_version'")->fetchColumn() === 1,
          'une seule colonne token_version');

    section('5. Comportement de la clé étrangère');
    $premier = $bulletinsAvant[0];
    $db->prepare("INSERT INTO bulletin_tokens_retired (token_sha256, bulletin_id, motif) VALUES (?, ?, 'test')")->execute([hash('sha256', 'x1'), $premier['id']]);
    check(leve(fn() => $db->prepare("INSERT INTO bulletin_tokens_retired (token_sha256, bulletin_id, motif) VALUES (?, NULL, 'test')")->execute([hash('sha256', 'x1')])) !== null,
          'une même empreinte ne peut pas être enregistrée deux fois');
    check(leve(fn() => $db->prepare("INSERT INTO bulletin_tokens_retired (token_sha256, bulletin_id, motif) VALUES (?, 99999999, 'test')")->execute([hash('sha256', 'x2')])) !== null,
          'un bulletin inexistant est refusé (intégrité référentielle)');

    section('6. Retour arrière : refusé s\'il perdrait de l\'information');
    check(leve(fn() => $rollback($db)) !== null && str_contains((string)leve(fn() => $rollback($db)), 'jetons retirés'), 'refusé tant que bulletin_tokens_retired contient des empreintes');
    $db->exec("DELETE FROM bulletin_tokens_retired");
    $db->exec("UPDATE bulletins_v2 SET token_version = 2, updated_at = updated_at WHERE id = " . (int)$premier['id']);
    check(str_contains((string)leve(fn() => $rollback($db)), 'version 2'), 'refusé tant qu\'un bulletin porte un jeton de version 2');
    $db->exec("UPDATE bulletins_v2 SET token_version = 1, updated_at = updated_at");

    section('7. Retour arrière exact');
    $rollback($db);
    check(DbCopy::schema($db, $tablesSurveillees) === $schemaAvant, 'structure strictement identique à l\'état initial');
    check($db->query("SHOW TABLES LIKE 'bulletin_tokens_retired'")->fetch() === false, 'table bulletin_tokens_retired supprimée');
    check(DbCopy::allDataHashes($db) === $donneesAvant, 'données strictement identiques à l\'état initial (toutes les tables)');
    check((int)$db->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE()")->fetchColumn() === $fkAvant, 'même nombre de clés étrangères qu\'au départ');
    $run($db);
    check(DbCopy::schema($db, $tablesSurveillees) === $schemaApres, 'ré-application après retour arrière : même structure');
} finally {
    $copy->drop();
}

section('8. La base source n\'a pas été touchée');
$apresSource = ['schema' => DbCopy::schema($source, $tablesSurveillees), 'data' => DbCopy::allDataHashes($source)];
check($apresSource['schema'] === $avantSource['schema'], 'structure de la base source identique avant / après le test');
check($apresSource['data'] === $avantSource['data'], 'données de TOUTES les tables de la base source identiques avant / après le test');
check((int)$source->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name LIKE 'ecole_app_copy_%'")->fetchColumn() === 0, 'aucune base temporaire restante');

echo "\n";
$total = $passed + $failed;
echo ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : {$passed}/{$total} assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
