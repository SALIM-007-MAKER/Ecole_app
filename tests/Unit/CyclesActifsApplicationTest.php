<?php
/**
 * Tests — application RÉELLE des cycles activés (academique.cycles_actifs) aux formulaires de classes, aux frais et aux factures.
 *
 * 1. CyclesActifsService : défaut = tous les cycles, restriction, valeurs invalides/vides, niveaux conservés, niveauAutorise
 * 2. Formulaires de classe : seuls les niveaux des cycles activés sont proposés (création) ; le niveau actuel reste proposé (édition)
 * 3. Validation serveur : niveau d'un cycle inactif ou inconnu refusé ; niveau actuel inchangé accepté
 * 4. Frais et génération de factures : propositions filtrées
 * 5. Non-régression : aucune donnée existante modifiée ou masquée, établissement sans réglage inchangé
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/CyclesActifsApplicationTest.php
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

use App\Services\CyclesActifsService;
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
$ecrire = static function (?array $cycles) use ($pdo): void {
    $pdo->exec("DELETE FROM etab_settings WHERE etablissement_id = 1 AND section = 'academique' AND cle = 'cycles_actifs'");
    if ($cycles !== null) {
        $pdo->prepare("INSERT INTO etab_settings (etablissement_id, section, cle, valeur, type) VALUES (1, 'academique', 'cycles_actifs', ?, 'json')")
            ->execute([json_encode($cycles, JSON_UNESCAPED_UNICODE)]);
    }
    \Core\Tenant\TenantCache::make()->forget(1, 'settings', 'academique');
};
/** Requête HTTP en tant qu'admin d'école ; corps complet retourné. @return array{status:int, body:string} */
function http(string $nomCopie, int $uid, string $methode, string $uri, array $post = [], bool $commit = false): array
{
    $corps = sys_get_temp_dir() . '/cact_' . bin2hex(random_bytes(4)) . '.html';
    [, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/request_as.php', 'admin', (string)$uid, $methode, '/ecole_app' . $uri, json_encode($post)],
        ['REQUEST_AS_DB' => $nomCopie, 'REQUEST_AS_BODY_OUT' => $corps] + ($commit ? ['REQUEST_AS_COMMIT' => '1'] : []));
    $r = preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: []) : [];
    $body = (string)@file_get_contents($corps); @unlink($corps);
    return ['status' => (int)($r['status'] ?? 0), 'body' => $body];
}
/** Valeurs des <option value="…"> d'un select donné. @return list<string> */
function options(string $html, string $selectName): array
{
    if (!preg_match('/<select[^>]*name="' . preg_quote($selectName, '/') . '"[^>]*>(.*?)<\/select>/s', $html, $m)) { return []; }
    preg_match_all('/<option[^>]*value="([^"]*)"/', $m[1], $o);
    return array_values(array_filter($o[1], fn($v) => $v !== ''));
}
$compte = static fn(string $nom, string $niveau): int => (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE nom = " . $pdo->quote($nom) . " AND niveau = " . $pdo->quote($niveau))->fetchColumn();

// ═══════════════════════════════════════════════════════════
section('1. CyclesActifsService');
$ecrire(null);
$svc = CyclesActifsService::pour(1);
check($svc->cycles() === array_keys(\App\Models\ClasseModel::NIVEAUX), 'réglage absent : tous les cycles (établissements existants inchangés)');
check(count($svc->niveaux()) === count(\App\Models\ClasseModel::NIVEAUX_ORDRE), 'réglage absent : tous les niveaux proposés');
$ecrire(['Collège', 'Lycée']);
$svc = CyclesActifsService::pour(1);
check($svc->cycles() === ['Collège', 'Lycée'], 'collège + lycée : deux cycles');
check($svc->niveaux() === ['6e', '5e', '4e', '3e', 'Seconde', 'Première', 'Terminale'], 'niveaux restreints, ordre pédagogique conservé');
check(array_keys($svc->niveauxParCycle()) === ['Collège', 'Lycée'], 'niveauxParCycle : mêmes groupes que la nomenclature, filtrés');
check(in_array('CM2', $svc->niveaux(['CM2']), true) && array_key_exists('Primaire', $svc->niveauxParCycle(['CM2'])), 'niveau conservé : son cycle est réintégré');
check(!in_array('CM2', $svc->niveaux(), true), 'sans « conserver » : CM2 non proposé');
check($svc->niveauAutorise('Seconde') && !$svc->niveauAutorise('CM2'), 'niveauAutorise : cycle actif oui, inactif non');
check($svc->niveauAutorise('CM2', 'CM2') && !$svc->niveauAutorise('CM1', 'CM2'), 'niveauAutorise : niveau actuel inchangé accepté, autre niveau inactif refusé');
$ecrire([]);
check(CyclesActifsService::pour(1)->cycles() === array_keys(\App\Models\ClasseModel::NIVEAUX), 'liste vide (université / formation pro) : aucune restriction');
$ecrire(['Maternelle', 'Lycée', 'Lycée']);
check(CyclesActifsService::pour(1)->cycles() === ['Lycée'], 'valeurs inconnues et doublons ignorés');
$pdo->exec("DELETE FROM etab_settings WHERE etablissement_id = 1 AND cle = 'cycles_actifs'");
$pdo->exec("INSERT INTO etab_settings (etablissement_id, section, cle, valeur, type) VALUES (1, 'academique', 'cycles_actifs', 'pas-du-json', 'string')");
\Core\Tenant\TenantCache::make()->forget(1, 'settings', 'academique');
check(CyclesActifsService::pour(1)->cycles() === array_keys(\App\Models\ClasseModel::NIVEAUX), 'valeur illisible : retour sûr à tous les cycles');

// ═══════════════════════════════════════════════════════════
section('2. Formulaires de classe');
$ecrire(['Collège', 'Lycée']);
$avantClasses = $pdo->query('SELECT id, nom, niveau, annee_scolaire, max_eleves FROM classes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$r = http($nomCopie, $admin, 'GET', '/classes/create');
check($r['status'] === 200, 'GET création de classe : 200', (string)$r['status']);
$opt = options($r['body'], 'niveau');
check($opt === ['6e', '5e', '4e', '3e', 'Seconde', 'Première', 'Terminale'], 'création : seuls les niveaux du collège et du lycée sont proposés', json_encode($opt));
check(!in_array('CM2', $opt, true) && !in_array('PS', $opt, true), 'création : primaire et préscolaire absents');

$pdo->exec("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire, max_eleves) VALUES (1, 'HIST', 'CM2', '2024-2025', 30)");
$idCm2 = (int)$pdo->lastInsertId();
$r = http($nomCopie, $admin, 'GET', "/classes/$idCm2/edit");
$opt = options($r['body'], 'niveau');
check($r['status'] === 200 && in_array('CM2', $opt, true) && in_array('Terminale', $opt, true), 'édition : le niveau actuel (CM2, cycle désactivé) reste proposé', json_encode($opt));
check(!in_array('PS', $opt, true), 'édition : les autres niveaux de cycles désactivés ne sont pas proposés');

// ═══════════════════════════════════════════════════════════
section('3. Validation côté serveur');
$base = ['annee_scolaire' => '2026-2027', 'max_eleves' => 30, 'description' => ''];
http($nomCopie, $admin, 'POST', '/classes/store', ['niveau' => 'CM2', 'nom' => 'X1'] + $base, true);
check($compte('X1', 'CM2') === 0, 'POST création avec un niveau de cycle INACTIF : refusé');
http($nomCopie, $admin, 'POST', '/classes/store', ['niveau' => 'Zzz', 'nom' => 'X2'] + $base, true);
check($compte('X2', 'Zzz') === 0, 'POST création avec un niveau INCONNU : refusé (avant : accepté sans contrôle)');
http($nomCopie, $admin, 'POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'D1'] + $base, true);
check($compte('D1', 'Terminale') === 1, 'POST création Terminale D1 (cycle actif) : accepté');
http($nomCopie, $admin, 'POST', '/classes/store', ['niveau' => 'Terminale', 'nom' => 'D2'] + $base, true);
check($compte('D2', 'Terminale') === 1, 'classes parallèles (Terminale D2) : acceptées, sans limite');

http($nomCopie, $admin, 'POST', "/classes/$idCm2", ['niveau' => 'CM2', 'nom' => 'HIST', 'annee_scolaire' => '2024-2025', 'max_eleves' => 35, 'description' => 'modifiée'], true);
$apres = $pdo->query("SELECT max_eleves, description FROM classes WHERE id = $idCm2")->fetch(PDO::FETCH_ASSOC);
check((int)$apres['max_eleves'] === 35 && $apres['description'] === 'modifiée', 'modification d\'une classe d\'un cycle désactivé, niveau inchangé : acceptée');
http($nomCopie, $admin, 'POST', "/classes/$idCm2", ['niveau' => 'CM1', 'nom' => 'HIST', 'annee_scolaire' => '2024-2025', 'max_eleves' => 40, 'description' => 'x'], true);
check((string)$pdo->query("SELECT niveau FROM classes WHERE id = $idCm2")->fetchColumn() === 'CM2', 'changement vers un AUTRE niveau de cycle inactif : refusé, niveau inchangé');
http($nomCopie, $admin, 'POST', "/classes/$idCm2", ['niveau' => 'Seconde', 'nom' => 'HIST', 'annee_scolaire' => '2024-2025', 'max_eleves' => 35, 'description' => 'm'], true);
check((string)$pdo->query("SELECT niveau FROM classes WHERE id = $idCm2")->fetchColumn() === 'Seconde', 'changement vers un niveau de cycle actif : accepté');

// ═══════════════════════════════════════════════════════════
section('4. Frais et génération de factures');
$r = http($nomCopie, $admin, 'GET', '/v2/finance/frais/create');
$cases = [];
preg_match_all('/name="niveaux_cibles\[\]" value="([^"]*)"/', $r['body'], $cases);
check($r['status'] === 200 && $cases[1] === ['6e', '5e', '4e', '3e', 'Seconde', 'Première', 'Terminale'], 'formulaire de frais : niveaux des cycles activés seulement', json_encode($cases[1]) . ' ' . $r['status']);
$r = http($nomCopie, $admin, 'GET', '/v2/finance/factures/generer');
$opt = options($r['body'], 'niveau');
check($r['status'] === 200 && $opt === ['6e', '5e', '4e', '3e', 'Seconde', 'Première', 'Terminale'], 'génération de factures : vrais niveaux (valeur de classes.niveau) des cycles activés', json_encode($opt) . ' ' . $r['status']);

// ═══════════════════════════════════════════════════════════
section('5. Non-régression : aucune donnée masquée ni modifiée');
$ecrire(['Lycée']);
$r = http($nomCopie, $admin, 'GET', '/classes');
check($r['status'] === 200, 'liste des classes : 200 avec un seul cycle actif');
$vus = 0;
foreach ($avantClasses as $c) { if (str_contains($r['body'], '/classes/' . $c['id'])) { $vus++; } }
check($vus === count($avantClasses), 'liste des classes : toutes les classes existantes restent visibles', "$vus / " . count($avantClasses));
$verif = $pdo->query('SELECT id, nom, niveau, annee_scolaire, max_eleves FROM classes WHERE id IN (' . implode(',', array_column($avantClasses, 'id')) . ') ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
check($verif === $avantClasses, 'classes existantes : nom, niveau, année et capacité inchangés');
$ecrire(null);
$r = http($nomCopie, $admin, 'GET', '/classes/create');
check(count(options($r['body'], 'niveau')) === count(\App\Models\ClasseModel::NIVEAUX_ORDRE), 'établissement sans réglage : tous les niveaux comme avant');

// ═══════════════════════════════════════════════════════════
section('6. Université / formation professionnelle (cycles_actifs vide)');
foreach (['universite', 'formation_pro'] as $typeHors) {
    $pdo->exec("UPDATE etablissements SET type = '$typeHors' WHERE id = 1");
    $ecrire([]);
    $tous = \App\Models\ClasseModel::NIVEAUX_ORDRE;
    $r = http($nomCopie, $admin, 'GET', '/classes/create');
    check($r['status'] === 200 && options($r['body'], 'niveau') === $tous, "$typeHors : création de classe ouverte, aucun niveau bloqué", (string)$r['status']);
    http($nomCopie, $admin, 'POST', '/classes/store', ['niveau' => 'CM2', 'nom' => strtoupper(substr($typeHors, 0, 2)) . '9', 'annee_scolaire' => '2026-2027', 'max_eleves' => 30, 'description' => ''], true);
    check($compte(strtoupper(substr($typeHors, 0, 2)) . '9', 'CM2') === 1, "$typeHors : une classe peut être créée (pas bloqué par l'absence de cycle)");
    $r = http($nomCopie, $admin, 'GET', '/v2/finance/frais/create');
    preg_match_all('/name="niveaux_cibles\[\]" value="([^"]*)"/', $r['body'], $cases);
    check($r['status'] === 200 && $cases[1] === $tous, "$typeHors : frais, tous les niveaux proposés", $r['status'] . ' ' . count($cases[1]));
    $r = http($nomCopie, $admin, 'GET', '/v2/finance/factures/generer');
    check($r['status'] === 200 && options($r['body'], 'niveau') === $tous, "$typeHors : génération de factures ouverte, tous les niveaux réels (page 200)", (string)$r['status']);
    $r = http($nomCopie, $admin, 'GET', '/parametres/academique');
    check($r['status'] === 200, "$typeHors : page Paramètres académiques accessible avec cycles_actifs vide", (string)$r['status']);
    $r = http($nomCopie, $admin, 'GET', '/classes');
    check($r['status'] === 200, "$typeHors : liste des classes accessible");
}
$pdo->exec("UPDATE etablissements SET type = 'lycee' WHERE id = 1");

// ═══════════════════════════════════════════════════════════
section('7. Génération de factures en masse : filtre par niveau');
$repoFact = new \App\Modules\Finance\Repositories\InvoiceRepository();
$parSeconde = $repoFact->getElevesByNiveau('Seconde', '2025-2026');
$parCycle = $repoFact->getElevesByNiveau('Lycée', '2025-2026');
$tousEleves = $repoFact->getElevesByNiveau('', '2025-2026');
check(count($parSeconde) > 0, 'filtre par NIVEAU réel (« Seconde ») : élèves trouvés', (string)count($parSeconde));
check(count($tousEleves) >= count($parSeconde), 'sans filtre : tous les élèves actifs');
echo ($parCycle === []
    ? "\033[33m  ⚠ CONSTAT : filtre par NOM DE CYCLE (« Lycée ») : 0 élève — les classes stockent des niveaux, pas des cycles\033[0m\n"
    : "  (le filtre par cycle retourne " . count($parCycle) . " élèves)\n");

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
