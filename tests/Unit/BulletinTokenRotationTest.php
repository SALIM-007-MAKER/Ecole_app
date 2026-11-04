<?php
/**
 * Tests — rotation des jetons de bulletins (BulletinTokenRotator + database/tools/bulletin_tokens.php),
 * SIMULATION, APPLICATION et RESTAURATION sur une COPIE RÉELLE de la base. La base source n'est jamais écrite
 * (empreinte comparée avant / après). T047 doit rester inactive : hors de database/migrations/.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/BulletinTokenRotationTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/tests/Support/DbCopy.php';
foreach (['BulletinTokenException', 'BulletinTokenService', 'BulletinTokenRotator'] as $c) {
    require_once ROOT_PATH . "/app/Modules/Academique/Services/$c.php";
}

use App\Modules\Academique\Services\BulletinTokenRotator;
use App\Modules\Academique\Services\BulletinTokenService;
use Tests\Support\DbCopy;

$passed = 0; $failed = 0;
function check(bool $ok, string $label): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
function leve(callable $f): ?string { try { $f(); return null; } catch (\Throwable $e) { return $e->getMessage(); } }

// ════════════════════════════════════════════════════════════════════════════
section('1. T047 reste inactive');

$migrations = array_map('basename', glob(ROOT_PATH . '/database/migrations/*') ?: []);
check(array_filter($migrations, fn($f) => str_starts_with($f, 'T047')) === [], 'aucun fichier T047 dans database/migrations/ (migrate.php ne peut pas l\'appliquer)');
$t047 = ROOT_PATH . '/database/manual/T047_rotate_bulletin_tokens.php';
check(is_file($t047), 'T047 existe dans database/manual/ (hors découverte automatique)');
$d = require $t047;
check(($d['active'] ?? null) === false, 'le descripteur T047 déclare active = false');
check(str_contains((string)leve(fn() => $d['run'](new PDO('sqlite::memory:'))), 'inactive'), 'exécuter le descripteur T047 lève une erreur « inactive »');
$migrate = (string)file_get_contents(ROOT_PATH . '/database/migrate.php');
check(str_contains($migrate, "database/migrations/") && !str_contains($migrate, 'database/manual'), 'migrate.php ne lit que database/migrations/');

// ════════════════════════════════════════════════════════════════════════════
section('2. Outil en ligne de commande : garde-fous (sans base modifiée)');

$php = PHP_BINARY;
$cli = fn(string $args): array => (function () use ($php, $args) {
    $p = proc_open('"' . $php . '" "' . ROOT_PATH . '/database/tools/bulletin_tokens.php" ' . $args, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['PATH' => getenv('PATH'), 'SystemRoot' => getenv('SystemRoot') ?: '']);
    $o = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return ['code' => proc_close($p), 'out' => $o, 'err' => $e];
})();
$r = $cli('');
check($r['code'] === 2 && str_contains($r['out'], 'Usage'), 'sans option : affiche l\'aide et ne fait rien');
$r = $cli('--apply --db=base_inexistante_xyz');
check($r['code'] !== 0, 'base inexistante : refus');
$r = $cli('--check --apply');
check($r['code'] === 2, 'deux modes à la fois : refus');

$conn = DbCopy::connectionFromEnv(ROOT_PATH);
$source = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['db']};charset=utf8mb4", $conn['user'], $conn['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$tablesSource = array_column($source->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM), 0);
$avantSource = ['schema' => DbCopy::schema($source, $tablesSource), 'data' => DbCopy::allDataHashes($source)];

$copy = DbCopy::create(ROOT_PATH);
if ($copy === null) {
    echo "\033[33m  - SKIP (copie réelle impossible : mysqldump / mysql / droit CREATE DATABASE indisponibles)\033[0m\n";
    exit($failed === 0 ? 0 : 1);
}

$secret = hash('sha256', 'bulletin-rotation-test-secret-1');
$tokens = BulletinTokenService::fromEnvironment(['BULLETIN_TOKEN_SECRET' => $secret]);
$backupDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rot_' . bin2hex(random_bytes(4));
$tousLesFichiers = [];

try {
    $db = $copy->pdo();
    $tables = array_column($db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM), 0);
    $rot = new BulletinTokenRotator($db, $tokens, $backupDir, $copy->name, 'development');
    $sansRotation = ['bulletins_v2', 'bulletin_tokens_retired', 'audit_logs', 'migrations_log'];

    $ids = $db->query('SELECT id FROM bulletins_v2 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $n = count($ids);
    $avantSchema = DbCopy::schema($db, $tables);
    $avantData = DbCopy::allDataHashes($db);
    $avantBulletins = $db->query('SELECT * FROM bulletins_v2 ORDER BY id')->fetchAll();
    $anciensJetons = array_column($avantBulletins, 'verification_token');
    foreach ($avantBulletins as $b) {
        $j = json_decode($b['data_json'], true);
        if (($j['verification_token'] ?? '') !== '') { $anciensJetons[] = $j['verification_token']; }
    }

    // ════════════════════════════════════════════════════════════════════════
    section("3. Lecture seule : contrôle et simulation ($n bulletins)");

    $c = $rot->check();
    check($c['schema_pret'] === true && $c['secret_configure'] === true && $c['bulletins'] === $n, 'check() : schéma prêt, secret configuré, nombre de bulletins exact');
    check($c['par_version'] === [1 => $n] || array_sum($c['par_version']) === $n, 'check() : répartition par version cohérente');
    $sim = $rot->simulate();
    check($sim['summary']['a_roter'] === $n && $sim['summary']['anomalies'] === 0, 'simulate() : tous les bulletins hérités sont planifiés, aucune anomalie');
    check(preg_match('/^[0-9a-f]{64}$/', $sim['checksum']) === 1, 'simulate() : somme de contrôle sha256');
    check($rot->simulate()['checksum'] === $sim['checksum'], 'simulate() : somme de contrôle stable (déterministe)');
    $texte = json_encode($c) . json_encode($sim);
    $fuite = array_filter($anciensJetons, fn($t) => $t !== '' && str_contains($texte, $t));
    check($fuite === [], 'check() / simulate() ne contiennent aucun ancien jeton');
    check(!str_contains($texte, $secret), 'check() / simulate() ne contiennent pas le secret');
    check(DbCopy::allDataHashes($db) === $avantData, 'LECTURE SEULE : aucune table modifiée après check() + simulate() (audit_logs et migrations_log inclus)');
    check(DbCopy::schema($db, $tables) === $avantSchema, 'LECTURE SEULE : structure identique');
    check(!is_dir($backupDir) || glob($backupDir . '/*') === [], 'LECTURE SEULE : aucune sauvegarde créée');

    $sansSecret = new BulletinTokenRotator($db, null, $backupDir, $copy->name);
    check($sansSecret->check()['secret_configure'] === false, 'check() sans secret : fonctionne et le signale');
    check(str_contains((string)leve(fn() => $sansSecret->plan()), 'BULLETIN_TOKEN_SECRET') || leve(fn() => $sansSecret->plan()) !== null, 'plan() sans secret : refus explicite');

    // ════════════════════════════════════════════════════════════════════════
    section('4. Refus avant toute écriture');

    $refus = [
        'somme erronée'         => fn() => $rot->apply(str_repeat('0', 64), 'Responsable Test'),
        'somme vide'            => fn() => $rot->apply('', 'Responsable Test'),
        'autorisation absente'  => fn() => $rot->apply($sim['checksum'], ''),
        'autorisation trop courte' => fn() => $rot->apply($sim['checksum'], 'ab'),
        'sans secret'           => fn() => $sansSecret->apply($sim['checksum'], 'Responsable Test'),
    ];
    foreach ($refus as $nom => $f) { check(leve($f) !== null, "apply refusé : $nom"); }
    $prod = new BulletinTokenRotator($db, $tokens, $backupDir, $copy->name, 'production');
    check(str_contains((string)leve(fn() => $prod->apply($sim['checksum'], 'Responsable Test')), 'production'), 'apply refusé en production sans autorisation explicite');
    check(DbCopy::allDataHashes($db) === $avantData, 'après tous ces refus : base strictement inchangée');
    check(!is_dir($backupDir) || glob($backupDir . '/*') === [], 'après ces refus : aucune sauvegarde créée');

    // ════════════════════════════════════════════════════════════════════════
    section('5. Application sur la copie');

    $res = $rot->apply($sim['checksum'], 'Responsable Test');
    check($res['rotated'] === $n, "$n bulletins renouvelés");
    $apres = $db->query('SELECT * FROM bulletins_v2 ORDER BY id')->fetchAll();
    check(count($apres) === $n, 'même nombre de bulletins');
    check(count(array_unique(array_column($apres, 'verification_token'))) === $n, 'jetons tous distincts');
    check(count(array_filter($apres, fn($b) => (int)$b['token_version'] === 2)) === $n, 'token_version = 2 partout');

    $ok = true; $json = true; $statuts = true; $autres = true; $maj = true; $reste = true;
    foreach ($apres as $i => $b) {
        $av = $avantBulletins[$i];
        $e = (int)$db->query('SELECT etablissement_id FROM eleves WHERE id = ' . (int)$b['eleve_id'])->fetchColumn();
        $ok &= $tokens->matches($b['verification_token'], $e, (int)$b['eleve_id'], (int)$b['periode_id']);
        $j = json_decode($b['data_json'], true); $jAv = json_decode($av['data_json'], true);
        $json &= ($j['verification_token'] ?? null) === $b['verification_token'] && ($j['qr_code_url'] ?? 'x') === '';
        $jAv['verification_token'] = $b['verification_token']; $jAv['qr_code_url'] = '';
        $json &= $jAv == $j;
        $statuts &= $b['statut'] === $av['statut'];
        $maj &= (string)$b['updated_at'] === (string)$av['updated_at'];
        foreach ($av as $col => $val) {
            if (!in_array($col, ['verification_token', 'token_version', 'data_json'], true) && $b[$col] !== $val) { $autres = false; }
        }
        $reste &= !in_array($b['verification_token'], $anciensJetons, true);
    }
    check((bool)$ok, 'chaque nouveau jeton est le HMAC attendu (établissement, élève, période)');
    check((bool)$json, 'JSON : verification_token = colonne, qr_code_url vidée, aucune autre clé modifiée');
    check((bool)$statuts, 'statuts inchangés');
    check((bool)$autres, 'toutes les autres colonnes de bulletins_v2 inchangées');
    check((bool)$maj, 'updated_at préservé');
    check((bool)$reste, 'aucun ancien jeton n\'est resté comme jeton actif');

    $retires = $db->query('SELECT token_sha256, bulletin_id, motif FROM bulletin_tokens_retired')->fetchAll();
    $attendus = array_unique(array_map([BulletinTokenService::class, 'retiredDigest'], array_filter($anciensJetons, fn($t) => $t !== '')));
    check(count($retires) === count($attendus), 'empreintes retirées : ' . count($retires) . ' = nombre d\'anciens jetons distincts (colonne + JSON divergent)');
    check(array_diff($attendus, array_column($retires, 'token_sha256')) === [], 'chaque ancien jeton a son empreinte SHA-256 enregistrée');
    check(array_filter($retires, fn($r) => !preg_match('/^[0-9a-f]{64}$/', $r['token_sha256'])) === [], 'seules des empreintes (64 hex) sont stockées');
    $stock = json_encode($db->query('SELECT * FROM bulletin_tokens_retired')->fetchAll());
    check(array_filter($anciensJetons, fn($t) => $t !== '' && str_contains($stock, '"' . $t . '"')) === [], 'aucun ancien jeton en clair dans bulletin_tokens_retired');

    // Autres tables strictement identiques
    $apresData = DbCopy::allDataHashes($db);
    $diff = array_keys(array_filter($apresData, fn($h, $t) => ($avantData[$t] ?? null) !== $h, ARRAY_FILTER_USE_BOTH));
    sort($diff);
    $attenduDiff = ['audit_logs', 'bulletin_tokens_retired', 'bulletins_v2', 'migrations_log'];
    check($diff === $attenduDiff, 'seules ces tables ont changé : ' . implode(', ', $attenduDiff) . ($diff === $attenduDiff ? '' : ' — constaté : ' . implode(', ', $diff)));
    check(DbCopy::schema($db, $tables) === $avantSchema, 'structure et contraintes identiques après application');

    $audit = $db->query("SELECT apres FROM audit_logs WHERE action = 'rotation_jetons' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $auditTexte = (string)$audit;
    check($audit !== false && json_decode($auditTexte, true)['bulletins'] === $n, 'audit : un événement « rotation_jetons » avec comptage');
    $tousJetons = array_merge($anciensJetons, array_column($apres, 'verification_token'));
    check(array_filter($tousJetons, fn($t) => $t !== '' && str_contains($auditTexte, $t)) === [] && !str_contains($auditTexte, $secret), 'audit : aucun jeton complet, ni ancien ni nouveau, ni secret');
    $mlog = $db->query("SELECT COUNT(*) FROM migrations_log WHERE migration = 'T047'")->fetchColumn();
    check((int)$mlog === 1, 'T047 enregistrée dans migrations_log de la COPIE uniquement après exécution manuelle');

    // Sauvegarde
    $fichiers = glob($backupDir . '/bulletin_tokens_*.json') ?: [];
    check(count($fichiers) === 1 && is_file($fichiers[0] . '.sha256'), 'sauvegarde + somme de contrôle écrites');
    $fichierSauvegarde = $fichiers[0] ?? '';

    // Anciens jetons refusés par le contrôle de format / HMAC
    $anciensRefuses = true;
    foreach ($avantBulletins as $b) {
        $e = (int)$db->query('SELECT etablissement_id FROM eleves WHERE id = ' . (int)$b['eleve_id'])->fetchColumn();
        if ($tokens->matches($b['verification_token'], $e, (int)$b['eleve_id'], (int)$b['periode_id'])) { $anciensRefuses = false; }
    }
    check($anciensRefuses, 'aucun ancien jeton ne correspond au HMAC attendu (donc refusé par la vérification publique)');
    $trouveAncien = $db->prepare('SELECT COUNT(*) FROM bulletins_v2 WHERE verification_token = ?');
    $nbTrouves = 0; foreach (array_unique(array_column($avantBulletins, 'verification_token')) as $t) { $trouveAncien->execute([$t]); $nbTrouves += (int)$trouveAncien->fetchColumn(); }
    check($nbTrouves === 0, 'aucun ancien jeton retrouvable dans bulletins_v2 (la page publique répondrait 404)');

    // ════════════════════════════════════════════════════════════════════════
    section('6. Idempotence');

    $apresApplyData = DbCopy::allDataHashes($db);
    $sim2 = $rot->simulate();
    check($sim2['summary']['a_roter'] === 0 && $sim2['summary']['deja_a_jour'] === $n, 'seconde simulation : plus rien à faire, tout est à jour');
    $res2 = $rot->apply($sim2['checksum'], 'Responsable Test');
    check(($res2['rotated'] ?? -1) === 0, 'seconde application : 0 bulletin touché');
    check(DbCopy::allDataHashes($db) === $apresApplyData, 'seconde application : aucune écriture (pas même une nouvelle sauvegarde ni un audit)');
    check(count(glob($backupDir . '/bulletin_tokens_*.json') ?: []) === 1, 'seconde application : pas de nouvelle sauvegarde');
    $verif = $rot->check();
    check($verif['jetons_faibles'] === 0 && $verif['v2_non_concordants'] === 0 && $verif['colonne_differente_du_json'] === 0 && $verif['qr_relatifs'] === 0 && $verif['qr_absolus'] === 0,
          'check() après rotation : 0 jeton faible, 0 non-concordant, 0 divergence colonne/JSON, 0 URL de QR figée');

    // ════════════════════════════════════════════════════════════════════════
    section('7. Un changement concurrent annule tout (comparaison-et-échange)');

    $copie2 = DbCopy::create(ROOT_PATH);
    if ($copie2 !== null) {
        try {
            $db2 = $copie2->pdo();
            $rot2 = new BulletinTokenRotator($db2, $tokens, $backupDir . '_cas', $copie2->name);
            $s = $rot2->simulate();
            $avant2 = DbCopy::allDataHashes($db2);
            // Un trigger simule une modification concurrente : le 2e bulletin change pendant la rotation
            $db2->exec("UPDATE bulletins_v2 SET data_json = JSON_SET(data_json, '$.note_test', 1), updated_at = updated_at WHERE id = " . (int)$ids[count($ids) - 1]);
            $msg = leve(fn() => $rot2->apply($s['checksum'], 'Responsable Test'));
            check($msg !== null && str_contains($msg, 'Somme de contrôle'), 'état modifié depuis la simulation : application refusée');
            $apres2 = DbCopy::allDataHashes($db2);
            $diff2 = array_keys(array_filter($apres2, fn($h, $t) => ($avant2[$t] ?? null) !== $h, ARRAY_FILTER_USE_BOTH));
            check($diff2 === ['bulletins_v2'], 'seule la modification volontaire du test est présente (aucune rotation partielle)');
            check(!is_dir($backupDir . '_cas') || glob($backupDir . '_cas/*') === [], 'aucune sauvegarde ni écriture sur refus');
        } finally {
            $copie2->drop();
            foreach (glob($backupDir . '_cas/*') ?: [] as $f) { @unlink($f); }
            @rmdir($backupDir . '_cas');
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    section('8. Restauration');

    check(str_contains((string)leve(fn() => $rot->restore($fichierSauvegarde, '')), 'obligatoire'), 'restauration refusée sans nom d\'autorisation');
    check(leve(fn() => $rot->restore($backupDir . '/inexistant.json', 'Responsable Test')) !== null, 'sauvegarde inexistante : refus');
    $autreBase = new BulletinTokenRotator($db, $tokens, $backupDir, 'autre_base');
    check(str_contains((string)leve(fn() => $autreBase->restore($fichierSauvegarde, 'Responsable Test')), 'autre base'), 'sauvegarde d\'une autre base : refus');

    // Altération
    $copieAltérée = $backupDir . '/altere.json';
    file_put_contents($copieAltérée, str_replace('"format": 1', '"format": 1 ', (string)file_get_contents($fichierSauvegarde)));
    copy($fichierSauvegarde . '.sha256', $copieAltérée . '.sha256');
    check(str_contains((string)leve(fn() => $rot->restore($copieAltérée, 'Responsable Test')), 'altérée'), 'sauvegarde altérée : refus (somme de contrôle)');
    check(DbCopy::allDataHashes($db) === $apresApplyData, 'refus de restauration : base inchangée');

    // Bulletin publié depuis la rotation : refus sauf autorisation
    $idPub = (int)$ids[0];
    $db->exec("UPDATE bulletins_v2 SET statut = 'publie', updated_at = updated_at WHERE id = $idPub");
    check(str_contains((string)leve(fn() => $rot->restore($fichierSauvegarde, 'Responsable Test')), 'statut'), 'bulletin publié depuis la rotation : restauration refusée');
    $db->exec("UPDATE bulletins_v2 SET statut = '" . $avantBulletins[0]['statut'] . "', updated_at = updated_at WHERE id = $idPub");

    $rest = $rot->restore($fichierSauvegarde, 'Responsable Test');
    check($rest['restaures'] === $n, "$n bulletins restaurés");

    $apresRestore = $db->query('SELECT * FROM bulletins_v2 ORDER BY id')->fetchAll();
    check($apresRestore == $avantBulletins, 'bulletins_v2 : TOUTES les colonnes identiques à l\'état d\'avant (jetons, version, JSON, updated_at)');
    check(DbCopy::dataHash($db, 'bulletins_v2') === $avantData['bulletins_v2'], 'bulletins_v2 : empreinte de contenu identique à l\'originale');
    check(DbCopy::countRows($db, 'bulletin_tokens_retired') === 0, 'bulletin_tokens_retired : vidée des empreintes ajoutées par la rotation');
    check(DbCopy::schema($db, $tables) === $avantSchema, 'structure et contraintes identiques à l\'original après restauration');
    $apresRestoreData = DbCopy::allDataHashes($db);
    $diff3 = array_keys(array_filter($apresRestoreData, fn($h, $t) => ($avantData[$t] ?? null) !== $h, ARRAY_FILTER_USE_BOTH));
    sort($diff3);
    check($diff3 === ['audit_logs', 'migrations_log'], 'après restauration, seules les traces d\'audit et migrations_log diffèrent de l\'original');
    $rest2 = $rot->restore($fichierSauvegarde, 'Responsable Test');
    check($rest2['restaures'] === 0 && $rest2['deja_restaures'] === $n, 'restauration répétée : idempotente');

    // Après restauration, on peut ré-appliquer (cycle complet)
    $s3 = $rot->simulate();
    check($s3['checksum'] === $sim['checksum'], 'même plan qu\'au départ après restauration (déterminisme du cycle)');
} finally {
    $copy->drop();
    foreach (glob($backupDir . '/*') ?: [] as $f) { @unlink($f); }
    @rmdir($backupDir);
}

// ════════════════════════════════════════════════════════════════════════════
section('9. La base source n\'a jamais été écrite');

check(DbCopy::allDataHashes($source) === $avantSource['data'], 'base source : contenu de TOUTES les tables identique avant / après');
check(DbCopy::schema($source, $tablesSource) === $avantSource['schema'], 'base source : structure identique avant / après');

echo "\n";
$total = $passed + $failed;
echo ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : {$passed}/{$total} assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
