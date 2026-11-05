<?php
/**
 * Tests — séries du lycée (migration T074, configuration par niveau, classes parallèles, validation, filtre).
 *
 * 1. Avant la migration : fonctionnalité inerte, application inchangée (aucune erreur SQL)
 * 2. Migration T074 : colonne nullable, index, idempotence, données et unicité intactes, retour arrière
 * 3. Service : normalisation, séries personnalisées 1 à 10 caractères, configuration INDÉPENDANTE par niveau
 * 4. Paramètres : enregistrement par niveau, rejet des codes invalides sans rien écrire, permissions
 * 5. Classes : création avec série, classes parallèles sans limite, refus d'une série non autorisée,
 *    modification d'une classe dont la série a été désactivée, série retirée hors lycée
 * 6. Filtre par série dans la liste des classes
 * 7. Isolation multi-établissement
 * 8. Rattachement ciblé de la classe 131 « 2nde C1 » à la série C : avant/après, aucune autre ligne touchée
 * 9. Inscriptions, réinscriptions, bulletins et moyennes : aucune modification
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/SeriesLyceeTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\Models\\' => '/app/Models/', 'App\\Services\\' => '/app/Services/', 'App\\Modules\\' => '/app/Modules/', 'App\\Shared\\' => '/app/Shared/', 'App\\Controllers\\' => '/app/Controllers/'] as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php';
            if (is_file($f)) { require $f; return; }
        }
    }
});
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use App\Services\SeriesLyceeService as S;
use Core\Database;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
/** @return array{0:int,1:string} */
function lancer(array $cmd, array $env = []): array
{
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), $env));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out];
}

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$admin = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' AND etablissement_id = 1 ORDER BY id LIMIT 1")->fetchColumn();
if ($admin === 0) { exit("ECHEC : aucun administrateur d'école dans la copie\n"); }

function http(string $nomCopie, string $role, int $uid, string $methode, string $uri, array $post = [], bool $commit = false, array $env = []): array
{
    $corps = sys_get_temp_dir() . '/ser_' . bin2hex(random_bytes(4)) . '.html';
    [, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/request_as.php', $role, (string)$uid, $methode, '/ecole_app' . $uri, json_encode($post)],
        ['REQUEST_AS_DB' => $nomCopie, 'REQUEST_AS_BODY_OUT' => $corps] + ($commit ? ['REQUEST_AS_COMMIT' => '1'] : []) + $env);
    $r = preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: []) : [];
    $body = (string)@file_get_contents($corps); @unlink($corps);
    return ['status' => (int)($r['status'] ?? 0), 'body' => $body];
}
$adm = static fn(string $m, string $u, array $p = [], array $env = []) => http($nomCopie, 'admin', $admin, $m, $u, $p, true, $env);
$serieDe = static function (int $id) use ($pdo): ?string {
    $v = $pdo->query("SELECT serie FROM classes WHERE id = $id")->fetchColumn();
    return $v === false || $v === null ? null : (string)$v;
};
$classe = static function (string $nom, string $niveau) use ($pdo): ?array {
    $s = $pdo->prepare('SELECT id FROM classes WHERE etablissement_id = 1 AND nom = ? AND niveau = ? ORDER BY id DESC LIMIT 1');
    $s->execute([$nom, $niveau]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
};
$reglage = static function (int $etab, ?array $series) use ($pdo): void {
    $pdo->prepare("DELETE FROM etab_settings WHERE etablissement_id = ? AND section = 'academique' AND cle = 'series_par_niveau'")->execute([$etab]);
    if ($series !== null) {
        $pdo->prepare("INSERT INTO etab_settings (etablissement_id, section, cle, valeur, type) VALUES (?, 'academique', 'series_par_niveau', ?, 'json')")->execute([$etab, json_encode($series, JSON_UNESCAPED_UNICODE)]);
    }
    \Core\Tenant\TenantCache::make()->forget($etab, 'settings', 'academique');
};
$lireReglage = static function (int $etab) use ($pdo): ?array {
    $s = $pdo->prepare("SELECT valeur FROM etab_settings WHERE etablissement_id = ? AND section = 'academique' AND cle = 'series_par_niveau'");
    $s->execute([$etab]);
    $v = $s->fetchColumn();
    return $v === false ? null : json_decode((string)$v, true);
};
$base = ['annee_scolaire' => '2026-2027', 'max_eleves' => 30, 'description' => ''];
$snapshot = static fn(): array => $pdo->query('SELECT id, etablissement_id, nom, niveau, annee_scolaire, max_eleves, description FROM classes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$checksum = static function (array $tables) use ($pdo): array {
    $o = [];
    foreach ($tables as $t) { $o[$t] = (string)$pdo->query("CHECKSUM TABLE `$t`")->fetch(PDO::FETCH_ASSOC)['Checksum']; }
    return $o;
};
$tablesProtegees = ['bulletins_v2', 'moyennes_generales', 'moyennes_matieres', 'inscriptions', 'eleves', 'enseignements', 'evaluations', 'matieres'];
$checksumsAvant = $checksum($tablesProtegees);
$classesAvant = $snapshot();

// ═══════════════════════════════════════════════════════════
section('1. Avant la migration : fonctionnalité inerte');
// La base vivante porte désormais T074 (copiée avec le reste) : on simule l'état d'AVANT la migration en la retirant de la copie.
$migrationAvant = require ROOT_PATH . '/database/migrations/T074_classes_serie.php';
$migrationAvant['rollback']($pdo);
$classesAvant = $snapshot();   // base de comparaison : l'état d'origine, sans la colonne
S::oublierDetection();
check(S::disponible() === false, 'colonne classes.serie absente (état simulé d\'avant T074)');
$r = http($nomCopie, 'admin', $admin, 'GET', '/classes/create');
check($r['status'] === 200 && !str_contains($r['body'], 'id="bloc-serie"'), 'formulaire de classe : aucun champ série');
$r = $adm('POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'P1', 'serie' => 'D'] + $base);
$pre = $classe('P1', 'Terminale');
check($pre !== null, 'création de classe fonctionne sans la colonne (série ignorée, aucune erreur SQL)');
$r = http($nomCopie, 'admin', $admin, 'GET', '/parametres/academique');
check($r['status'] === 200 && !str_contains($r['body'], 'series_present'), 'Paramètres académiques : pas de section séries');
$r = http($nomCopie, 'admin', $admin, 'GET', '/classes');
check($r['status'] === 200 && !str_contains($r['body'], 'filtre-serie'), 'liste des classes : pas de filtre série');
$pdo->exec("DELETE FROM classes WHERE nom = 'P1' AND niveau = 'Terminale'");

// ═══════════════════════════════════════════════════════════
section('2. Migration T074');
$migration = require ROOT_PATH . '/database/migrations/T074_classes_serie.php';
check($migration['id'] === 'T074' && $migration['reversible'] === true, 'identifiant T074, réversible');
$migration['run']($pdo);
S::oublierDetection();
$col = $pdo->query("SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND COLUMN_NAME = 'serie'")->fetch(PDO::FETCH_ASSOC);
check($col && $col['COLUMN_TYPE'] === 'varchar(10)' && $col['IS_NULLABLE'] === 'YES' && $col['COLUMN_DEFAULT'] === null, 'colonne serie : VARCHAR(10), NULL autorisé, sans défaut', json_encode($col));
check(S::disponible() === true, 'détection : fonctionnalité disponible');
check((int)$pdo->query("SELECT COUNT(*) FROM classes WHERE serie IS NOT NULL")->fetchColumn() === 0, 'toutes les classes existantes : serie = NULL (aucune déduction depuis le nom)');
check($snapshot() === $classesAvant, 'classes existantes : toutes les colonnes d\'origine inchangées');
$structure1 = $pdo->query("SHOW CREATE TABLE classes")->fetch(PDO::FETCH_NUM)[1];
$migration['run']($pdo);
$migration['run']($pdo);
check($pdo->query("SHOW CREATE TABLE classes")->fetch(PDO::FETCH_NUM)[1] === $structure1, 'idempotence : deux relances, structure strictement identique');
check(str_contains($structure1, 'UNIQUE KEY `uq_classe_nom_niveau_annee` (`etablissement_id`,`nom`,`niveau`,`annee_scolaire`)'), 'unicité des classes inchangée (classes parallèles non limitées)');
check(str_contains($structure1, 'idx_classes_serie'), 'index de filtre présent');
$migration['rollback']($pdo);
check((int)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND COLUMN_NAME = 'serie'")->fetchColumn() === 0, 'retour arrière : colonne retirée');
check($snapshot() === $classesAvant, 'retour arrière : classes intactes');
$migration['run']($pdo);
S::oublierDetection();
check(S::disponible() === true, 'ré-application après retour arrière');

// ═══════════════════════════════════════════════════════════
section('3. Service : normalisation et configuration par niveau');
foreach ([['c', 'C'], [' d ', 'D'], ['', null], ['   ', null], ['AB-12', 'AB-12'], ['ti', 'TI'], ['É', 'É'], ['1', '1'], ['ABCDEFGHIJ', 'ABCDEFGHIJ']] as [$in, $out]) {
    check(S::normaliser($in) === $out, 'normaliser(' . json_encode($in, JSON_UNESCAPED_UNICODE) . ') = ' . json_encode($out, JSON_UNESCAPED_UNICODE));
}
foreach (['ABCDEFGHIJK', 'A B', 'A;', 'A-', '-A', 'A--', "A\nB", "<b>", 'A,C'] as $mauvais) {
    check(S::normaliser($mauvais) === false, 'normaliser(' . json_encode($mauvais) . ') refusé');
}
$reglage(1, null);
check(S::pour(1)->seriesParNiveau() === [], 'aucun réglage : aucune série (rien n\'est présumé)');
check(S::SERIES_PAR_DEFAUT === ['A', 'C', 'D'], 'A, C, D proposées par défaut dans l\'écran de configuration');
$reglage(1, ['Première' => ['A', 'C'], 'Terminale' => ['C', 'D', 'ti', 'D'], 'Troisième' => ['A'], 'Seconde' => []]);
$sp = S::pour(1)->seriesParNiveau();
check($sp === ['Première' => ['A', 'C'], 'Terminale' => ['C', 'D', 'TI']], 'séries INDÉPENDANTES par niveau ; Seconde sans série ; niveau hors lycée, doublons et casse ignorés', json_encode($sp, JSON_UNESCAPED_UNICODE));
check(S::pour(1)->seriesPour('Seconde') === [] && S::pour(1)->seriesPour('3e') === [], 'Seconde et 3e : aucune série');
$pdo->exec("DELETE FROM etab_settings WHERE etablissement_id = 1 AND cle = 'series_par_niveau'");
$pdo->exec("INSERT INTO etab_settings (etablissement_id, section, cle, valeur, type) VALUES (1, 'academique', 'series_par_niveau', 'illisible', 'string')");
\Core\Tenant\TenantCache::make()->forget(1, 'settings', 'academique');
check(S::pour(1)->seriesParNiveau() === [], 'réglage illisible : ignoré sans erreur');
$reglage(1, ['Première' => ['A', 'C'], 'Terminale' => ['C', 'D', 'TI']]);
$e = S::pour(1);
check($e->erreurSerie('Terminale', null) === null, 'série absente : toujours valide (optionnelle)');
check($e->erreurSerie('Terminale', 'D') === null && $e->erreurSerie('Terminale', 'TI') === null, 'série activée / personnalisée : valide');
check($e->erreurSerie('Terminale', 'A') !== null, 'série non activée pour ce niveau : refusée');
check($e->erreurSerie('Seconde', 'C') !== null, 'série pour une Seconde sans série activée : refusée');
check($e->erreurSerie('3e', 'A') !== null, 'série hors lycée : refusée');
check($e->erreurSerie('Terminale', 'B', 'Terminale', 'B') === null, 'série désactivée mais inchangée sur la même classe : acceptée');
check($e->erreurSerie('Terminale', 'B', 'Première', 'B') !== null, 'série désactivée avec changement de niveau : refusée');
$d = S::depuisSaisie(['Terminale' => ['C', 'D']], ['Terminale' => 'b, e;TI', 'Première' => '']);
check($d['erreurs'] === [] && $d['series'] === ['Terminale' => ['C', 'D', 'B', 'E', 'TI']], 'saisie de configuration : cases + codes personnalisés normalisés', json_encode($d, JSON_UNESCAPED_UNICODE));
$d = S::depuisSaisie([], ['Seconde' => 'ABCDEFGHIJK, ok']);
check($d['erreurs'] !== [] && !isset($d['series']['Seconde']) || $d['erreurs'] !== [], 'saisie : code de plus de 10 caractères signalé');

// ═══════════════════════════════════════════════════════════
section('4. Paramètres : configuration par niveau et permissions');
$reglage(1, null);
$pdo->exec("DELETE FROM etab_settings WHERE etablissement_id = 1 AND cle = 'cycles_actifs'");
$r = http($nomCopie, 'admin', $admin, 'GET', '/parametres/academique');
check($r['status'] === 200 && str_contains($r['body'], 'name="series[Terminale][]"') && str_contains($r['body'], 'name="series_perso[Seconde]"'), 'page : grille des séries par niveau (Seconde, Première, Terminale)');
$adm('POST', '/parametres/academique', ['cycles' => ['Lycée'], 'series_present' => '1', 'series' => ['Première' => ['A', 'C'], 'Terminale' => ['C', 'D']], 'series_perso' => ['Terminale' => 'ti', 'Seconde' => '']]);
check($lireReglage(1) === ['Première' => ['A', 'C'], 'Terminale' => ['C', 'D', 'TI']], 'enregistrement par niveau, série personnalisée normalisée', json_encode($lireReglage(1), JSON_UNESCAPED_UNICODE));
$avant = $lireReglage(1);
$adm('POST', '/parametres/academique', ['cycles' => ['Lycée'], 'series_present' => '1', 'series' => ['Terminale' => ['D']], 'series_perso' => ['Terminale' => 'CODE-TROP-LONG']]);
check($lireReglage(1) === $avant, 'code personnalisé invalide : rejeté, rien n\'est écrit');
$adm('POST', '/parametres/academique', ['cycles' => ['Lycée'], 'series' => ['Terminale' => ['D']]]);
check($lireReglage(1) === $avant, 'sans series_present : séries inchangées (enregistrement des cycles seul)');
$adm('POST', '/parametres/academique', ['cycles' => ['Lycée'], 'series_present' => '1', 'series' => ['Terminale' => ['<script>']]]);
check($lireReglage(1) === $avant, 'code HTML refusé');
$eleveUid = (function () use ($pdo): int {
    $pdo->exec("INSERT INTO users (etablissement_id, nom, email, password, role) VALUES (1, 'Sans droit', 'sansdroit@exemple.test', 'x', 'eleve')");
    return (int)$pdo->lastInsertId();
})();
http($nomCopie, 'eleve', $eleveUid, 'POST', '/parametres/academique', ['cycles' => ['Lycée'], 'series_present' => '1', 'series' => ['Seconde' => ['A']]], true);
check($lireReglage(1) === $avant, 'utilisateur sans la permission settings.academique.update : aucune écriture');
http($nomCopie, 'eleve', $eleveUid, 'POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'HX', 'serie' => 'D'] + $base, true);
check($classe('HX', 'Terminale') === null, 'utilisateur sans la permission classes.create : aucune classe créée');

// ═══════════════════════════════════════════════════════════
section('5. Classes : création, classes parallèles, refus, modification');
$pdo->exec("DELETE FROM etab_settings WHERE etablissement_id = 1 AND cle = 'cycles_actifs'");   // tous les cycles actifs : seule la règle des séries est testée
\Core\Tenant\TenantCache::make()->forget(1, 'settings', 'academique');
$reglage(1, ['Première' => ['A', 'C'], 'Terminale' => ['C', 'D', 'TI']]);
$r = http($nomCopie, 'admin', $admin, 'GET', '/classes/create');
check($r['status'] === 200 && str_contains($r['body'], 'id="bloc-serie"') && str_contains($r['body'], '"Terminale":["C","D","TI"]'), 'formulaire : champ série et séries activées par niveau');
$adm('POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'D1', 'serie' => 'D'] + $base);
$adm('POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'D2', 'serie' => 'D'] + $base);
$adm('POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'D3', 'serie' => 'D'] + $base);
$adm('POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'C1', 'serie' => 'c'] + $base);
check($serieDe($classe('D1', 'Terminale')['id']) === 'D' && $serieDe($classe('D2', 'Terminale')['id']) === 'D' && $serieDe($classe('D3', 'Terminale')['id']) === 'D', 'trois classes parallèles dans la série D (Terminale D1, D2, D3)');
check($serieDe($classe('C1', 'Terminale')['id']) === 'C', 'code posté « c » normalisé en « C »');
$adm('POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'TI1', 'serie' => 'ti'] + $base);
check($classe('TI1', 'Terminale') !== null && $serieDe($classe('TI1', 'Terminale')['id']) === 'TI', 'série personnalisée de 2 caractères acceptée');
$adm('POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'SANS', 'serie' => ''] + $base);
check($classe('SANS', 'Terminale') !== null && $serieDe($classe('SANS', 'Terminale')['id']) === null, 'série vide : classe créée sans série (NULL)');
$adm('POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'ABS'] + $base);
check($classe('ABS', 'Terminale') !== null && $serieDe($classe('ABS', 'Terminale')['id']) === null, 'série non postée : NULL');
$adm('POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'A1', 'serie' => 'A'] + $base);
check($classe('A1', 'Terminale') === null, 'série non activée pour Terminale (A) : classe refusée');
$adm('POST', '/classes/store', ['niveau' => 'Seconde', 'nom' => 'S1', 'serie' => 'C'] + $base);
check($classe('S1', 'Seconde') === null, 'Seconde sans série activée : série refusée');
$adm('POST', '/classes/store', ['niveau' => '3e', 'nom' => 'T1', 'serie' => 'A'] + $base);
check($classe('T1', '3e') === null, 'niveau hors lycée avec série : refusé');
$adm('POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'X1', 'serie' => 'A B'] + $base);
$adm('POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'X2', 'serie' => 'ABCDEFGHIJK'] + $base);
check($classe('X1', 'Terminale') === null && $classe('X2', 'Terminale') === null, 'codes invalides (espace, > 10 caractères) : refusés');
$adm('POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'D1', 'serie' => 'D'] + $base);
check((int)$pdo->query("SELECT COUNT(*) FROM classes WHERE niveau = 'Terminale' AND nom = 'D1' AND annee_scolaire = '2026-2027'")->fetchColumn() === 1, 'doublon (même nom, niveau, année) toujours refusé');

// Modification d'une classe dont la série a été désactivée depuis
$idD1 = (int)$classe('D1', 'Terminale')['id'];
$reglage(1, ['Première' => ['A', 'C'], 'Terminale' => ['C']]);   // D désactivée
$r = http($nomCopie, 'admin', $admin, 'GET', "/classes/$idD1/edit");
check($r['status'] === 200 && str_contains($r['body'], 'var actuelle = "D"'), 'édition : la série désactivée (D) reste connue du formulaire, proposée « (désactivée) »');
$adm('POST', "/classes/$idD1", ['niveau' => 'Terminale', 'nom' => 'D1', 'serie' => 'D', 'annee_scolaire' => '2026-2027', 'max_eleves' => 33, 'description' => 'maj']);
$ligne = $pdo->query("SELECT max_eleves, description, serie FROM classes WHERE id = $idD1")->fetch(PDO::FETCH_ASSOC);
check((int)$ligne['max_eleves'] === 33 && $ligne['description'] === 'maj' && $ligne['serie'] === 'D', 'classe dont la série est désactivée : modifiable, série inchangée conservée');
$adm('POST', "/classes/$idD1", ['niveau' => 'Terminale', 'nom' => 'D1', 'serie' => 'A', 'annee_scolaire' => '2026-2027', 'max_eleves' => 33, 'description' => 'maj']);
check($serieDe($idD1) === 'D', 'passage vers une série non activée (A) : refusé, série inchangée');
$adm('POST', "/classes/$idD1", ['niveau' => 'Première', 'nom' => 'D1', 'serie' => 'D', 'annee_scolaire' => '2026-2027', 'max_eleves' => 33, 'description' => 'maj']);
check($serieDe($idD1) === 'D' && $pdo->query("SELECT niveau FROM classes WHERE id = $idD1")->fetchColumn() === 'Terminale', 'changement de niveau en gardant une série désactivée : refusé');
$adm('POST', "/classes/$idD1", ['niveau' => 'Terminale', 'nom' => 'D1', 'annee_scolaire' => '2026-2027', 'max_eleves' => 34, 'description' => 'sans champ']);
check($serieDe($idD1) === 'D', 'champ série non posté (lycée) : série inchangée (jamais effacée par omission)');
$adm('POST', "/classes/$idD1", ['niveau' => 'Terminale', 'nom' => 'D1', 'serie' => '', 'annee_scolaire' => '2026-2027', 'max_eleves' => 34, 'description' => 'retrait']);
check($serieDe($idD1) === null, 'série vide postée : série retirée volontairement');
$idD2 = (int)$classe('D2', 'Terminale')['id'];
$adm('POST', "/classes/$idD2", ['niveau' => '3e', 'nom' => 'D2', 'annee_scolaire' => '2026-2027', 'max_eleves' => 30, 'description' => '']);
check($serieDe($idD2) === null && $pdo->query("SELECT niveau FROM classes WHERE id = $idD2")->fetchColumn() === '3e', 'passage à un niveau hors lycée : série retirée automatiquement');

// ═══════════════════════════════════════════════════════════
section('6. Filtre par série dans la liste');
$reglage(1, ['Première' => ['A', 'C'], 'Terminale' => ['C', 'D', 'TI']]);
$pdo->exec("UPDATE classes SET serie = 'D' WHERE id = " . (int)$classe('D3', 'Terminale')['id']);
$idC1 = (int)$classe('C1', 'Terminale')['id'];
$idD3 = (int)$classe('D3', 'Terminale')['id'];
$idSans = (int)$classe('SANS', 'Terminale')['id'];
$present = static fn(string $body, int $id) => str_contains($body, "/classes/$id");
$r = http($nomCopie, 'admin', $admin, 'GET', '/classes');
check($r['status'] === 200 && str_contains($r['body'], 'id="filtre-serie"'), 'liste : filtre par série affiché');
check($present($r['body'], $idC1) && $present($r['body'], $idD3) && $present($r['body'], $idSans), 'sans filtre : toutes les classes');
$r = http($nomCopie, 'admin', $admin, 'GET', '/classes?serie=D');
check($present($r['body'], $idD3) && !$present($r['body'], $idC1) && !$present($r['body'], $idSans), 'filtre D : seulement les classes de la série D');
$r = http($nomCopie, 'admin', $admin, 'GET', '/classes?serie=C');
check($present($r['body'], $idC1) && !$present($r['body'], $idD3), 'filtre C : seulement la série C');
$r = http($nomCopie, 'admin', $admin, 'GET', '/classes?serie=__aucune');
check($present($r['body'], $idSans) && !$present($r['body'], $idC1) && !$present($r['body'], $idD3), 'filtre « sans série »');
$r = http($nomCopie, 'admin', $admin, 'GET', '/classes?serie=' . rawurlencode("D' OR '1'='1"));
check($r['status'] === 200 && !$present($r['body'], $idC1), 'valeur de filtre piégée : aucun effet, aucune erreur');

// ═══════════════════════════════════════════════════════════
section('7. Isolation multi-établissement');
$pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('autre-lycee', 'Autre lycée', 'AL', 'lycee')");
$etab2 = (int)$pdo->lastInsertId();
$pdo->exec("UPDATE users SET etablissement_id = $etab2 WHERE id = $eleveUid");
$reglage($etab2, ['Terminale' => ['A']]);
check(S::pour($etab2)->seriesPour('Terminale') === ['A'] && S::pour(1)->seriesPour('Terminale') === ['C', 'D', 'TI'], 'configuration propre à chaque établissement');
$ctx2 = $adm('GET', '/classes/create', [], ['REQUEST_AS_ETAB' => (string)$etab2]);
check($ctx2['status'] === 403 || !str_contains($ctx2['body'], 'name="niveau"'), 'administrateur de l\'établissement 1 sans droits dans l\'établissement 2 : formulaire de classe refusé là-bas', (string)$ctx2['status']);
$pdo->exec("INSERT INTO classes (etablissement_id, nom, niveau, serie, annee_scolaire, max_eleves) VALUES ($etab2, 'E2A', 'Terminale', 'A', '2026-2027', 30)");
$idE2 = (int)$pdo->lastInsertId();
$adm('POST', "/classes/$idE2", ['niveau' => 'Terminale', 'nom' => 'E2A', 'serie' => 'C', 'annee_scolaire' => '2026-2027', 'max_eleves' => 30, 'description' => 'pirate']);
check($serieDe($idE2) === 'A' && $pdo->query("SELECT description FROM classes WHERE id = $idE2")->fetchColumn() !== 'pirate', 'établissement 1 ne peut pas modifier la classe d\'un autre établissement');
$r = http($nomCopie, 'admin', $admin, 'GET', '/classes?serie=A');
check(!$present($r['body'], $idE2), 'liste de l\'établissement 1 : jamais les classes d\'un autre établissement');

// ═══════════════════════════════════════════════════════════
section('8. Rattachement ciblé : classe 131 « 2nde C1 » → série C');
$c131 = $pdo->query("SELECT id, nom, niveau, annee_scolaire, serie FROM classes WHERE id = 131")->fetch(PDO::FETCH_ASSOC);
check($c131 && $c131['nom'] === '2nde C1' && $c131['niveau'] === 'Seconde' && $c131['serie'] === null, 'avant : classe 131 = Seconde « 2nde C1 », serie NULL', json_encode($c131, JSON_UNESCAPED_UNICODE));
$avantTout = $pdo->query('SELECT * FROM classes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$st = $pdo->prepare("UPDATE classes SET serie = 'C' WHERE id = 131 AND nom = '2nde C1' AND niveau = 'Seconde' AND serie IS NULL");
$st->execute();
check($st->rowCount() === 1, 'mise à jour ciblée : exactement 1 ligne');
$apresTout = $pdo->query('SELECT * FROM classes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$diff = [];
foreach ($avantTout as $i => $l) { if ($l != $apresTout[$i]) { $diff[] = (int)$l['id']; } }
check($diff === [131] && count($avantTout) === count($apresTout), 'seule la classe 131 a changé, aucune ligne ajoutée ou supprimée', json_encode($diff));
$l131 = $pdo->query('SELECT * FROM classes WHERE id = 131')->fetch(PDO::FETCH_ASSOC);
$o131 = $avantTout[array_search(131, array_column($avantTout, 'id'))];
unset($l131['serie'], $o131['serie']);
check($l131 == $o131 && $serieDe(131) === 'C', 'après : serie = C, tous les autres champs de la classe inchangés');
check((int)$pdo->query("SELECT COUNT(*) FROM classes WHERE id IN (1,2,3,4,132) AND serie IS NOT NULL")->fetchColumn() === 0, 'les classes 1, 2, 3, 4 et 132 restent à NULL');
$st->execute();
check($st->rowCount() === 0, 'rejouée : sans effet (idempotente)');

// ═══════════════════════════════════════════════════════════
section('9. Inscriptions, réinscriptions, bulletins, moyennes');
$checksumsApres = $checksum($tablesProtegees);
check($checksumsApres === $checksumsAvant, 'tables élèves, inscriptions, enseignements, évaluations, matières, bulletins et moyennes : contenu strictement identique', json_encode(array_keys(array_diff_assoc($checksumsApres, $checksumsAvant))));
$reinsc = new \App\Models\ReinscriptionModel();
$src = (object)['niveau' => 'Seconde', 'nom' => 'C1'];
$dest = [(object)['id' => 7, 'niveau' => 'Première', 'nom' => 'C1', 'serie' => 'C'], (object)['id' => 8, 'niveau' => 'Première', 'nom' => 'A', 'serie' => 'A']];
$sug = $reinsc->suggestDestination($src, $dest);
check($sug !== null && $sug->id === 7, 'suggestion de réinscription : inchangée (même nom, niveau suivant), la série n\'interfère pas');
check($reinsc->suggestDestination((object)['niveau' => 'Terminale', 'nom' => 'D1'], $dest) === null, 'Terminale : sortants par défaut, inchangé');
$r = http($nomCopie, 'admin', $admin, 'GET', '/reinscription');
check($r['status'] === 200, 'page réinscription : 200');
$r = http($nomCopie, 'admin', $admin, 'GET', '/reinscription/plan?source=2025-2026&destination=2026-2027');
check($r['status'] === 200 || $r['status'] === 302, 'plan de réinscription : répond sans erreur', (string)$r['status']);
$r = http($nomCopie, 'admin', $admin, 'GET', '/classes/131');
check($r['status'] === 200, 'fiche de la classe 131 (avec élèves inscrits) : 200');

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
