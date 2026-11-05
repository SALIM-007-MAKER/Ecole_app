<?php
/**
 * Tests — création d'un établissement par CYCLES (primaire / collège / lycée combinables) ou type hors cycle
 * (université, formation professionnelle).
 *
 * 1. Règles pures (EtablissementCycles) : déduction du type, validation, ordre, doublons, incohérences
 * 2. Cohérence avec la nomenclature réelle (ClasseModel::NIVEAUX) : aucune dérive
 * 3. Service : cycles_actifs alimenté dans la même transaction, type déduit, audit, rollback si invalide
 * 4. Compatibilité : appel historique (clé `type` seule) inchangé, établissements existants intacts
 * 5. HTTP : formulaire, POST, refus du niveau support, validation côté serveur
 * 6. Paramètres académiques : « au moins un cycle » maintenu pour un établissement scolaire, levé pour hors cycle
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/EtablissementCyclesTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\Models\\' => '/app/Models/', 'App\\Services\\' => '/app/Services/', 'App\\Shared\\' => '/app/Shared/', 'App\\Controllers\\' => '/app/Controllers/'] as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php';
            if (is_file($f)) { require $f; return; }
        }
    }
});
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use Core\Database;
use Core\Platform\EtablissementCycles as EC;
use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformEtablissementService;
use Core\Platform\PlatformOperatorService;

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
/** Requête HTTP simulée du portail plateforme. @return array<string,mixed> */
function http(string $nomCopie, string $operateur, string $methode, string $uri, array $post = []): array
{
    global $dossierSecours;
    [$code, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/platform_request.php', $operateur, $methode, $uri, json_encode($post)],
        ['REQUEST_AS_DB' => $nomCopie, 'PLATFORM_REQ_AUDIT_DIR' => $dossierSecours]);
    return preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0, 'brut' => $sortie, 'code' => $code];
}
function operateur(int $id, string $niveau): string { return json_encode(['id' => $id, 'niveau' => $niveau, 'email' => "op$id@exemple.test", 'nom' => "Op $id"]); }
/** Message de l'InvalidArgumentException levée par $f, ou null si aucune. */
function refus(callable $f): ?string
{
    try { $f(); return null; } catch (\InvalidArgumentException $e) { return $e->getMessage(); }
}

// ═══════════════════════════════════════════════════════════
section('1. Règles pures : déduction du type et validation');
$cas = [
    [['Primaire'], 'ecole_primaire'], [['Préscolaire'], 'ecole_primaire'], [['Préscolaire', 'Primaire'], 'ecole_primaire'],
    [['Collège'], 'college'], [['Lycée'], 'lycee'],
    [['Collège', 'Lycée'], 'groupe_scolaire'], [['Primaire', 'Collège'], 'groupe_scolaire'],
    [['Primaire', 'Collège', 'Lycée'], 'groupe_scolaire'], [['Préscolaire', 'Primaire', 'Collège', 'Lycée'], 'groupe_scolaire'],
];
foreach ($cas as [$cycles, $attendu]) {
    $r = EC::depuisSaisie($cycles, '');
    check($r['type'] === $attendu && $r['cycles'] === $cycles, implode('+', $cycles) . " → $attendu");
}
$r = EC::depuisSaisie(['Lycée', 'Collège', 'Collège'], null);
check($r['cycles'] === ['Collège', 'Lycée'] && $r['type'] === 'groupe_scolaire', 'ordre pédagogique rétabli et doublons retirés');
foreach (['universite', 'formation_pro'] as $t) {
    $r = EC::depuisSaisie([], $t);
    check($r['type'] === $t && $r['cycles'] === [], "$t : type conservé, aucun cycle");
}
check(refus(fn() => EC::depuisSaisie([], '')) !== null, 'aucun cycle ni type hors cycle : refusé');
check(refus(fn() => EC::depuisSaisie(null, '')) !== null, 'saisie absente : refusée');
check(refus(fn() => EC::depuisSaisie(['Primaire'], 'universite')) !== null, 'cycles + université : incohérence refusée');
check(refus(fn() => EC::depuisSaisie(['Maternelle'], '')) !== null, 'cycle inconnu refusé (pas ignoré en silence)');
check(refus(fn() => EC::depuisSaisie([['x']], '')) !== null, 'élément non texte refusé');
check(refus(fn() => EC::depuisSaisie('Primaire', '')) !== null, 'cycles non tableau refusé');
check(refus(fn() => EC::depuisSaisie([], 'groupe_scolaire')) !== null, 'groupe_scolaire n\'est pas un « hors cycle » saisissable');
check(refus(fn() => EC::depuisSaisie([], 'lycee')) !== null, 'lycee n\'est pas un « hors cycle » saisissable');
check(EC::estHorsCycle('universite') && EC::estHorsCycle('formation_pro') && !EC::estHorsCycle('lycee') && !EC::estHorsCycle(null), 'estHorsCycle');

// ═══════════════════════════════════════════════════════════
section('2. Cohérence avec la nomenclature réelle');
check(EC::CYCLES === array_keys(\App\Models\ClasseModel::NIVEAUX), 'cycles proposés = clés de ClasseModel::NIVEAUX', json_encode(array_keys(\App\Models\ClasseModel::NIVEAUX)));

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$dossierSecours = sys_get_temp_dir() . '/ecyc_' . bin2hex(random_bytes(4));
PlatformAuditService::definirDossierSecours($dossierSecours);
$ops = new PlatformOperatorService($pdo);
$etabs = new PlatformEtablissementService($pdo);
$mdp = 'Zq7!pLm2#vXk9@Rt';
$superId = $ops->creer('super@exemple.test', 'Super Opérateur', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$adminId = $ops->creer('admin@exemple.test', 'Admin Opérateur', 'admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$supportId = $ops->creer('support@exemple.test', 'Support Opérateur', 'support', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$lire = static function (int $id) use ($pdo): array {
    $s = $pdo->prepare("SELECT valeur, type, updated_by FROM etab_settings WHERE etablissement_id = ? AND section = 'academique' AND cle = 'cycles_actifs'");
    $s->execute([$id]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: [];
};
$typeDe = static fn(int $id): string => (string)$pdo->query("SELECT type FROM etablissements WHERE id = $id")->fetchColumn();
$nbEtabs = static fn(): int => (int)$pdo->query('SELECT COUNT(*) FROM etablissements')->fetchColumn();

// ═══════════════════════════════════════════════════════════
section('3. Service : création par cycles');
$avantExistants = $pdo->query("SELECT id, type FROM etablissements ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
$avantSettings = (int)$pdo->query("SELECT COUNT(*) FROM etab_settings WHERE cle = 'cycles_actifs'")->fetchColumn();

$id1 = $etabs->create(['slug' => 'ecole-cl-ly', 'nom' => 'Collège Lycée', 'nom_court' => 'CL', 'cycles' => ['Collège', 'Lycée'], 'type_hors_cycle' => ''], $superId);
$s = $lire($id1);
check($typeDe($id1) === 'groupe_scolaire', 'collège + lycée → type groupe_scolaire');
check($s && json_decode($s['valeur'], true) === ['Collège', 'Lycée'] && $s['type'] === 'json', 'cycles_actifs = ["Collège","Lycée"] (json)', json_encode($s));
check($s && $s['updated_by'] === null, 'updated_by NULL (opérateur plateforme ≠ users)');

$id2 = $etabs->create(['slug' => 'ecole-col', 'nom' => 'Collège seul', 'nom_court' => 'CS', 'cycles' => ['Collège'], 'type_hors_cycle' => ''], $superId);
check($typeDe($id2) === 'college' && json_decode($lire($id2)['valeur'], true) === ['Collège'], 'collège seul → college, cycles_actifs = ["Collège"]');

$id3 = $etabs->create(['slug' => 'ecole-3cy', 'nom' => 'Trois cycles', 'nom_court' => 'T3', 'cycles' => ['Lycée', 'Primaire', 'Collège'], 'type_hors_cycle' => ''], $superId);
check($typeDe($id3) === 'groupe_scolaire' && json_decode($lire($id3)['valeur'], true) === ['Primaire', 'Collège', 'Lycée'], 'primaire+collège+lycée → groupe_scolaire, ordre pédagogique');

$id4 = $etabs->create(['slug' => 'univ-test', 'nom' => 'Université Test', 'nom_court' => 'UT', 'cycles' => [], 'type_hors_cycle' => 'universite'], $superId);
check($typeDe($id4) === 'universite' && json_decode($lire($id4)['valeur'], true) === [], 'université : type universite, cycles_actifs = [] (aucun cycle scolaire)');
$id5 = $etabs->create(['slug' => 'fp-test', 'nom' => 'Formation Pro', 'nom_court' => 'FP', 'cycles' => [], 'type_hors_cycle' => 'formation_pro'], $superId);
check($typeDe($id5) === 'formation_pro' && json_decode($lire($id5)['valeur'], true) === [], 'formation pro : type formation_pro, cycles_actifs = []');

$settings = \Core\Tenant\SettingsService::make();
// Les copies jetables réutilisent les mêmes identifiants d'établissement d'un test à l'autre : on invalide le cache de paramètres (fichier partagé).
foreach ([$id1, $id2, $id3, $id4, $id5] as $idCache) { \Core\Tenant\TenantCache::make()->forget($idCache, 'settings', 'academique'); }
check($settings->get($id1, 'academique', 'cycles_actifs', null) === ['Collège', 'Lycée'], 'relecture via SettingsService (celui de la page Paramètres) identique');

$aud = $pdo->query("SELECT nouvelle_valeur FROM platform_audit_logs WHERE action = 'tenant.created' AND entity_id = $id1")->fetchColumn();
$audV = json_decode((string)$aud, true);
check(($audV['type'] ?? null) === 'groupe_scolaire' && ($audV['cycles'] ?? null) === ['Collège', 'Lycée'], 'audit tenant.created : type et cycles consignés', (string)$aud);
check((int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE etablissement_id = $id1")->fetchColumn() === 0, 'aucune entrée parasite dans le journal de l\'école (écriture directe)');

$avant = $nbEtabs();
foreach ([
    'aucun cycle' => ['cycles' => [], 'type_hors_cycle' => ''],
    'cycle inconnu' => ['cycles' => ['Maternelle'], 'type_hors_cycle' => ''],
    'cycles + université' => ['cycles' => ['Lycée'], 'type_hors_cycle' => 'universite'],
    'type hors cycle inconnu' => ['cycles' => [], 'type_hors_cycle' => 'lycee'],
] as $nom => $saisie) {
    $m = refus(fn() => $etabs->create(['slug' => 'refus-' . bin2hex(random_bytes(3)), 'nom' => 'Refus', 'nom_court' => 'R'] + $saisie, $superId));
    check($m !== null, "refusé : $nom", (string)$m);
}
check($nbEtabs() === $avant, 'aucun établissement créé par les saisies invalides');
check((int)$pdo->query("SELECT COUNT(*) FROM etab_settings WHERE cle = 'cycles_actifs'")->fetchColumn() === $avantSettings + 5, 'aucun cycles_actifs orphelin (5 créés, aucun de plus)');

// ═══════════════════════════════════════════════════════════
section('4. Compatibilité : appel historique et établissements existants');
$idH = $etabs->create(['slug' => 'ecole-histo', 'nom' => 'Historique', 'nom_court' => 'H', 'type' => 'ecole_primaire'], $superId);
check($typeDe($idH) === 'ecole_primaire', 'appel historique (type seul) : type conservé tel quel');
check($lire($idH) === [], 'appel historique : aucun cycles_actifs écrit (comportement inchangé, défaut = tous les cycles)');
check($settings->get($idH, 'academique', 'cycles_actifs', 'defaut') === 'defaut', 'relecture : le défaut de la page Paramètres s\'applique');
$idHx = $etabs->create(['slug' => 'ecole-histo2', 'nom' => 'Historique 2', 'nom_court' => 'H2', 'type' => 'inconnu'], $superId);
check($typeDe($idHx) === 'lycee', 'appel historique : type invalide → lycee (comportement inchangé)');
$apres = $pdo->query("SELECT id, type FROM etablissements WHERE id IN (" . implode(',', array_keys($avantExistants)) . ") ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
check($apres === $avantExistants, 'établissements existants : types inchangés');

// ═══════════════════════════════════════════════════════════
section('5. HTTP : formulaire, permissions, validation serveur');
$fichierCorps = sys_get_temp_dir() . '/ecyc_corps_' . bin2hex(random_bytes(4)) . '.html';
putenv('REQUEST_AS_BODY_OUT=' . $fichierCorps);
$get = http($nomCopie, operateur($superId, 'super_admin'), 'GET', '/platform/etablissements/create');
putenv('REQUEST_AS_BODY_OUT');
$html = (string)@file_get_contents($fichierCorps);
@unlink($fichierCorps);
check(($get['status'] ?? 0) === 200, 'GET formulaire : 200 (super_admin)', json_encode($get));
check(str_contains($html, 'cycles[]') && str_contains($html, 'type_hors_cycle'), 'formulaire : cases de cycles et type hors cycle présents');
check(!str_contains($html, 'name="type"'), 'formulaire : l\'ancien champ « Type » est retiré');
check((http($nomCopie, operateur($adminId, 'admin'), 'GET', '/platform/etablissements/create')['status'] ?? 0) === 200, 'GET formulaire : 200 (admin)');
check((http($nomCopie, operateur($supportId, 'support'), 'GET', '/platform/etablissements/create')['status'] ?? 0) === 403, 'GET formulaire : 403 (support)');
$avant = $nbEtabs();
$r = http($nomCopie, operateur($supportId, 'support'), 'POST', '/platform/etablissements', ['slug' => 'http-support', 'nom' => 'X', 'cycles' => ['Lycée'], 'type_hors_cycle' => '']);
check(in_array($r['status'] ?? 0, [403], true) && $nbEtabs() === $avant, 'POST support : refusé, rien créé', json_encode($r));
$r = http($nomCopie, operateur($adminId, 'admin'), 'POST', '/platform/etablissements', ['slug' => 'http-admin', 'nom' => 'Via HTTP', 'cycles' => ['Collège', 'Lycée'], 'type_hors_cycle' => '']);
$idHttp = (int)$pdo->query("SELECT id FROM etablissements WHERE slug = 'http-admin'")->fetchColumn();
check($idHttp > 0 && $typeDe($idHttp) === 'groupe_scolaire' && json_decode($lire($idHttp)['valeur'] ?? 'null', true) === ['Collège', 'Lycée'], 'POST admin : créé, type déduit, cycles_actifs alimenté', json_encode($r));
$r = http($nomCopie, operateur($adminId, 'admin'), 'POST', '/platform/etablissements', ['slug' => 'http-vide', 'nom' => 'Vide', 'cycles' => [], 'type_hors_cycle' => '']);
check((int)$pdo->query("SELECT COUNT(*) FROM etablissements WHERE slug = 'http-vide'")->fetchColumn() === 0, 'POST sans cycle ni type hors cycle : rien créé');
$r = http($nomCopie, operateur($adminId, 'admin'), 'POST', '/platform/etablissements', ['slug' => 'http-forge', 'nom' => 'Forgé', 'cycles' => ['Lycée'], 'type_hors_cycle' => 'universite']);
check((int)$pdo->query("SELECT COUNT(*) FROM etablissements WHERE slug = 'http-forge'")->fetchColumn() === 0, 'POST forgé (cycles + université) : rien créé');
$r = http($nomCopie, operateur($adminId, 'admin'), 'POST', '/platform/etablissements', ['slug' => 'http-type', 'nom' => 'Type forcé', 'type' => 'universite', 'cycles' => ['Primaire'], 'type_hors_cycle' => '']);
$idT = (int)$pdo->query("SELECT id FROM etablissements WHERE slug = 'http-type'")->fetchColumn();
check($idT > 0 && $typeDe($idT) === 'ecole_primaire', 'POST : un champ `type` forgé est ignoré, le type reste déduit des cycles');

$r = http($nomCopie, operateur($adminId, 'admin'), 'POST', '/platform/etablissements', ['slug' => 'http-histo', 'nom' => 'Client historique', 'type' => 'college']);
$idHh = (int)$pdo->query("SELECT id FROM etablissements WHERE slug = 'http-histo'")->fetchColumn();
check($idHh > 0 && $typeDe($idHh) === 'college' && $lire($idHh) === [], 'POST historique (champ `type` seul) : accepté comme avant, aucun cycles_actifs écrit', json_encode($r));

// ═══════════════════════════════════════════════════════════
section('6. Paramètres académiques : « au moins un cycle » selon le type');
$adminEcole = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' AND etablissement_id = 1 ORDER BY id LIMIT 1")->fetchColumn();
$poster = static function (array $post) use ($nomCopie, $adminEcole): void {
    lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/request_as.php', 'admin', (string)$adminEcole, 'POST', '/ecole_app/parametres/academique', json_encode($post)],
        ['REQUEST_AS_DB' => $nomCopie, 'REQUEST_AS_COMMIT' => '1']);
};
if ($adminEcole === 0) {
    check(false, 'précondition : un administrateur d\'école existe dans la copie');
} else {
    $typeOrigine = $typeDe(1);
    $pdo->exec("UPDATE etablissements SET type = 'lycee' WHERE id = 1");
    $pdo->exec("DELETE FROM etab_settings WHERE etablissement_id = 1 AND section = 'academique' AND cle = 'cycles_actifs'");
    $poster(['cycles' => ['Lycée']]);
    check(json_decode($lire(1)['valeur'] ?? 'null', true) === ['Lycée'], 'établissement scolaire : un cycle enregistré');
    $poster(['cycles' => []]);
    check(json_decode($lire(1)['valeur'] ?? 'null', true) === ['Lycée'], 'établissement scolaire : liste vide REFUSÉE (règle maintenue, valeur inchangée)');
    $pdo->exec("UPDATE etablissements SET type = 'universite' WHERE id = 1");
    $poster(['cycles' => []]);
    check(json_decode($lire(1)['valeur'] ?? 'null', true) === [], 'université : liste vide acceptée (règle non applicable)');
    $pdo->exec("UPDATE etablissements SET type = " . $pdo->quote($typeOrigine) . " WHERE id = 1");
}

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
