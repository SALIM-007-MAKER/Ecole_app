<?php
/**
 * Tests — plans SaaS (étape 3 du Super-Admin SaaS, migration T066).
 *
 * 1. Migration T066 : additive, idempotente, lignes DZD existantes inchangées (aucune conversion), défaut de devise XOF, retour arrière sans perte
 * 2. Validation : code, nom, entiers, essai/grâce, devise, prix (XOF entier, 2 décimales sinon), modules (clés de config/modules.php)
 * 3. Création : valeurs par défaut (devise de référence de la configuration), champs commerciaux
 * 4. Modification PARTIELLE : une clé absente conserve la valeur ; code immuable ; aucune écriture si rien ne change
 * 5. Plan utilisé : changement de prix/devise exigeant la confirmation explicite, audit avant/après
 * 6. Archivage / restauration : établissements conservés, plan archivé non assignable (assignation et création d'établissement)
 * 7. Parcours HTTP : lecture pour tout opérateur, écriture réservée au super_admin, refus journalisés, formulaire d'édition
 * 8. Journal d'audit global
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/PlatformPlansTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
unset($_ENV['PLATFORM_DEFAULT_CURRENCY']);   // le test vérifie le repli XOF
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\Shared\\' => '/app/Shared/', 'App\\Controllers\\' => '/app/Controllers/'] as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php';
            if (is_file($f)) { require $f; return; }
        }
    }
});
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use Core\Database;
use Core\Platform\PlanAuditException;
use Core\Platform\PlanValidationException;
use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformEtablissementService;
use Core\Platform\PlatformOperatorService;
use Core\Platform\PlatformPlanService;

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
/** Erreurs de validation levées par $f (liste vide si aucune exception de validation). @return list<string> */
function erreurs(callable $f): array
{
    try { $f(); return []; } catch (PlanValidationException $e) { return $e->getErrors(); }
}
/** Dernier élément d'une liste. */
function dernier(array $l): array { return $l[array_key_last($l)]; }
function contient(array $liste, string $fragment): bool
{
    foreach ($liste as $m) { if (mb_stripos($m, $fragment) !== false) { return true; } }
    return false;
}

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$dossierSecours = sys_get_temp_dir() . '/pplans_' . bin2hex(random_bytes(4));
PlatformAuditService::definirDossierSecours($dossierSecours);
$ops = new PlatformOperatorService($pdo);
$plans = new PlatformPlanService($pdo);
$etabs = new PlatformEtablissementService($pdo);
$mdp = 'Zq7!pLm2#vXk9@Rt';
$superId = $ops->creer('super@exemple.test', 'Super Opérateur', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$adminId = $ops->creer('admin@exemple.test', 'Admin Opérateur', 'admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$supportId = $ops->creer('support@exemple.test', 'Support Opérateur', 'support', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$planBase = ['code' => 'base', 'nom' => 'Base', 'prix_mensuel' => '5000', 'prix_annuel' => '50000'];
$compteurPlans = static fn(string ...$codes) => (int)$pdo->query('SELECT COUNT(*) FROM platform_plans WHERE code IN (' . implode(',', array_map([$pdo, 'quote'], $codes)) . ')')->fetchColumn();
$audits = static fn(string $action) => $pdo->query("SELECT * FROM platform_audit_logs WHERE action = " . $pdo->quote($action) . " ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('1. Migration T066 : additive, idempotente, plans DZD préservés');
$migration = require ROOT_PATH . '/database/migrations/T066_plans_conditions_commerciales.php';
$cols = $pdo->query("SELECT COLUMN_NAME, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_plans'")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach (['duree_essai_jours', 'jours_grace', 'ordre', 'visible_catalogue', 'updated_at'] as $c) {
    check(array_key_exists($c, $cols), "colonne $c présente");
}
check($cols['devise'] === 'XOF', 'défaut de colonne devise = XOF', (string)$cols['devise']);
$seed = $pdo->query('SELECT code, devise, prix_mensuel, prix_annuel, max_users, max_eleves, modules_inclus, actif, duree_essai_jours, jours_grace, ordre, visible_catalogue FROM platform_plans ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
check(count($seed) >= 3 && array_column(array_slice($seed, 0, 3), 'code') === ['starter', 'pro', 'enterprise'], 'les trois plans existants sont présents');
check(array_unique(array_column(array_slice($seed, 0, 3), 'devise')) === ['DZD'], 'les plans existants restent en DZD (aucune réinterprétation)');
check(array_column(array_slice($seed, 0, 3), 'prix_mensuel') === ['2500.00', '6500.00', '15000.00'] && array_column(array_slice($seed, 0, 3), 'prix_annuel') === ['25000.00', '65000.00', '150000.00'], 'prix existants inchangés (aucune conversion DZD → XOF)');
check(count(array_unique(array_map(null, array_column($seed, 'duree_essai_jours'), array_column($seed, 'jours_grace'), array_column($seed, 'ordre'), array_column($seed, 'visible_catalogue')), SORT_REGULAR)) === 1
    && (int)$seed[0]['duree_essai_jours'] === 0 && (int)$seed[0]['visible_catalogue'] === 1, 'nouvelles colonnes : valeurs neutres sur les lignes existantes');
$empreinte = md5(json_encode($pdo->query('SELECT * FROM platform_plans ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)));
$migration['run']($pdo); $migration['run']($pdo);
check(md5(json_encode($pdo->query('SELECT * FROM platform_plans ORDER BY id')->fetchAll(PDO::FETCH_ASSOC))) === $empreinte, 'T066 rejouée deux fois : aucune ligne modifiée (idempotente)');
$migration['rollback']($pdo);
$apresRetour = $pdo->query("SELECT COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_plans' AND COLUMN_NAME = 'devise'")->fetchColumn();
check($apresRetour === 'DZD' && md5(json_encode($pdo->query('SELECT * FROM platform_plans ORDER BY id')->fetchAll(PDO::FETCH_ASSOC))) === $empreinte, 'retour arrière : défaut remis à DZD, colonnes et données conservées');
$migration['run']($pdo);
check(PlatformPlanService::deviseReference() === 'XOF', 'devise de référence = XOF à défaut de configuration');
$_ENV['PLATFORM_DEFAULT_CURRENCY'] = 'xof ';
check(PlatformPlanService::deviseReference() === 'XOF', 'devise de référence lue dans la configuration (normalisée)');
$_ENV['PLATFORM_DEFAULT_CURRENCY'] = 'pas-une-devise';
check(PlatformPlanService::deviseReference() === 'XOF', 'configuration invalide : repli sur XOF');
unset($_ENV['PLATFORM_DEFAULT_CURRENCY']);

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('2. Validation');
$cree = static fn(array $d) => $plans->create($d + $planBase, $superId);
check(contient(erreurs(fn() => $cree(['code' => 'X'])), 'Code de plan invalide'), 'code en majuscule refusé');
check(contient(erreurs(fn() => $cree(['code' => 'a'])), 'Code de plan invalide'), 'code trop court refusé');
check(contient(erreurs(fn() => $cree(['code' => 'a b'])), 'Code de plan invalide'), 'code avec espace refusé');
check(contient(erreurs(fn() => $cree(['code' => 'pro'])), 'existe déjà'), 'code déjà utilisé refusé');
check(contient(erreurs(fn() => $cree(['nom' => ' '])), 'nom'), 'nom vide refusé');
check(contient(erreurs(fn() => $cree(['max_users' => '0'])), 'utilisateurs'), 'max_users = 0 refusé');
check(contient(erreurs(fn() => $cree(['max_users' => '70000'])), 'utilisateurs'), 'max_users hors borne refusé');
check(contient(erreurs(fn() => $cree(['max_eleves' => 'abc'])), 'élèves'), 'max_eleves non numérique refusé');
check(contient(erreurs(fn() => $cree(['max_eleves' => ''])), 'élèves'), 'max_eleves vide refusé (pas de remise à une valeur par défaut)');
check(contient(erreurs(fn() => $cree(['storage_quota_mb' => '-5'])), 'stockage'), 'stockage négatif refusé');
check(contient(erreurs(fn() => $cree(['duree_essai_jours' => '366'])), 'essai'), 'essai > 365 jours refusé');
check(contient(erreurs(fn() => $cree(['duree_essai_jours' => '-1'])), 'essai'), 'essai négatif refusé');
check(contient(erreurs(fn() => $cree(['jours_grace' => '91'])), 'grâce'), 'grâce > 90 jours refusée');
check(contient(erreurs(fn() => $cree(['ordre' => '40000'])), 'Ordre'), 'ordre hors borne refusé');
check(contient(erreurs(fn() => $cree(['devise' => 'xo'])), 'Devise invalide'), 'devise de 2 lettres refusée');
check(contient(erreurs(fn() => $cree(['devise' => 'XO1'])), 'Devise invalide'), 'devise avec chiffre refusée');
check(contient(erreurs(fn() => $cree(['devise' => ''])), 'Devise invalide'), 'devise vide refusée');
check(contient(erreurs(fn() => $cree(['prix_mensuel' => '-1'])), 'Prix mensuel'), 'prix négatif refusé');
check(contient(erreurs(fn() => $cree(['prix_mensuel' => 'gratuit'])), 'Prix mensuel'), 'prix non numérique refusé');
check(contient(erreurs(fn() => $cree(['prix_annuel' => '1e3'])), 'Prix annuel'), 'prix en notation scientifique refusé');
check(contient(erreurs(fn() => $cree(['prix_mensuel' => '5000.50'])), 'XOF'), 'XOF avec décimales refusé');
check(contient(erreurs(fn() => $cree(['devise' => 'EUR', 'prix_mensuel' => '10.999'])), 'Prix mensuel'), 'EUR avec 3 décimales refusé');
check(contient(erreurs(fn() => $cree(['prix_mensuel' => '123456789'])), 'Prix mensuel'), 'prix trop grand refusé');
check(erreurs(fn() => $cree(['code' => 'plan-eur', 'devise' => 'eur', 'prix_mensuel' => '10,50', 'prix_annuel' => '105'])) === [], 'EUR à 2 décimales (virgule acceptée) accepté');
check($pdo->query("SELECT devise, prix_mensuel FROM platform_plans WHERE code = 'plan-eur'")->fetch(PDO::FETCH_ASSOC) === ['devise' => 'EUR', 'prix_mensuel' => '10.50'], 'devise normalisée en majuscules, montant stocké tel que saisi');
check(contient(erreurs(fn() => $cree(['modules_inclus' => ['finance', 'inconnu']])), 'Module inconnu'), 'module inconnu refusé');
check(contient(erreurs(fn() => $cree(['modules_inclus' => 'finance'])), 'Modules'), 'modules non fournis en liste refusés');
check(contient(erreurs(fn() => $cree(['modules_inclus' => ['<script>']])), 'Module inconnu'), 'module de type balise refusé');
$connus = PlatformPlanService::modulesConnus();
$registre = require ROOT_PATH . '/config/modules.php';
check($connus === array_keys($registre) && in_array('rh', $connus, true) && in_array('scolarite', $connus, true), 'modules connus = toutes les clés de config/modules.php (modules désactivés compris)');
$avantRegistre = md5_file(ROOT_PATH . '/config/modules.php');
check(erreurs(fn() => $cree(['code' => 'avec-rh', 'modules_inclus' => ['rh', 'scolarite', 'rh']])) === [], 'module désactivé accepté et doublon fusionné');
check(json_decode($pdo->query("SELECT modules_inclus FROM platform_plans WHERE code = 'avec-rh'")->fetchColumn(), true) === ['rh', 'scolarite'], 'modules stockés sans doublon');
check(md5_file(ROOT_PATH . '/config/modules.php') === $avantRegistre, 'config/modules.php inchangé (cocher un module ne l\'active pas)');
$plusieurs = erreurs(fn() => $cree(['code' => 'X', 'nom' => '', 'devise' => '1']));
check(count($plusieurs) >= 3, 'toutes les erreurs sont renvoyées ensemble', (string)count($plusieurs));
check((int)$pdo->query("SELECT COUNT(*) FROM platform_plans WHERE code IN ('X','x')")->fetchColumn() === 0, 'aucun plan écrit en cas d\'erreur');

// Visibilité catalogue : booléen strict ; modules : message clair pour une valeur qui n'est pas une chaîne
$nCreesAvant = count($audits('plan.created'));
$idStarter = (int)$pdo->query("SELECT id FROM platform_plans WHERE code = 'starter'")->fetchColumn();
foreach (['abc' => 'texte libre', '' => 'chaîne vide', '2' => '2', 'peut-etre' => 'peut-être'] as $mauvais => $lib) {
    check(contient(erreurs(fn() => $cree(['code' => 'vis-ko', 'visible_catalogue' => (string)$mauvais])), 'Visibilité'), "visible_catalogue « $lib » refusé");
}
check(contient(erreurs(fn() => $cree(['code' => 'vis-ko', 'visible_catalogue' => ['1']])), 'Visibilité'), 'visible_catalogue en tableau refusé');
check(contient(erreurs(fn() => $cree(['code' => 'vis-ko', 'visible_catalogue' => null])), 'Visibilité'), 'visible_catalogue null refusé');
check(contient(erreurs(fn() => $cree(['code' => 'vis-ko', 'visible_catalogue' => 1.5])), 'Visibilité'), 'visible_catalogue décimal refusé');
check(contient(erreurs(fn() => $plans->update($idStarter, ['visible_catalogue' => 'abc'], $superId)), 'Visibilité') && (int)$plans->find($idStarter)['visible_catalogue'] === 1, 'modification : visible_catalogue invalide refusé, plan intact');
foreach ([true, false, 0, 1, '0', '1', 'true', 'false', 'on', 'off', ' 1 '] as $bon) {
    check(erreurs(fn() => $plans->normaliser(['visible_catalogue' => $bon], $plans->find($idStarter))) === [], 'visible_catalogue ' . var_export($bon, true) . ' accepté');
}
check($plans->normaliser(['visible_catalogue' => 'false'], $plans->find($idStarter))['visible_catalogue'] === 0 && $plans->normaliser(['visible_catalogue' => 'on'], $plans->find($idStarter))['visible_catalogue'] === 1, 'visible_catalogue : valeurs lues correctement (false → 0, on → 1)');
foreach ([[123], [['finance']], [null], [''], ['  '], [true]] as $mauvais) {
    $e = erreurs(fn() => $cree(['code' => 'mod-ko', 'modules_inclus' => $mauvais]));
    check(contient($e, 'Module : valeur invalide') && !contient($e, 'Module inconnu') && !contient($e, '« »'), 'module non textuel ' . json_encode($mauvais) . ' : message « valeur invalide », pas « Module inconnu »');
}
check(contient(erreurs(fn() => $cree(['code' => 'mod-ko', 'modules_inclus' => ['finance', 12, 'zzz']])), 'Module inconnu : « zzz »'), 'module inconnu textuel : nom affiché');
check(count($audits('plan.created')) === $nCreesAvant && $compteurPlans('mod-ko', 'vis-ko') === 0, 'validations refusées : ni plan ni audit écrits');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('3. Création');
$id = $plans->create(['code' => 'complet', 'nom' => 'Complet', 'description' => 'Plan de test', 'max_users' => 40, 'max_eleves' => 400, 'storage_quota_mb' => 2048,
    'api_calls_per_day' => 5000, 'prix_mensuel' => '7500', 'prix_annuel' => '75000', 'duree_essai_jours' => 14, 'jours_grace' => 7, 'ordre' => 3,
    'visible_catalogue' => '0', 'modules_inclus' => ['finance', 'api']], $superId);
$p = $plans->find($id);
check($p['devise'] === 'XOF', 'devise par défaut d\'un nouveau plan = XOF');
check((int)$p['duree_essai_jours'] === 14 && (int)$p['jours_grace'] === 7 && (int)$p['ordre'] === 3 && (int)$p['visible_catalogue'] === 0 && (int)$p['actif'] === 1, 'essai, grâce, ordre, visibilité enregistrés');
check($p['prix_mensuel'] === '7500.00' && $p['description'] === 'Plan de test', 'prix et description enregistrés');
$ordre = array_column($plans->all(), 'code');
check($ordre[0] === 'starter' && array_search('complet', $ordre, true) > array_search('starter', $ordre, true), 'liste triée par ordre puis id');
$defauts = $plans->find($cree(['code' => 'defauts']));
check((int)$defauts['max_users'] === 50 && (int)$defauts['max_eleves'] === 500 && (int)$defauts['duree_essai_jours'] === 0 && (int)$defauts['visible_catalogue'] === 1, 'création sans champ optionnel : valeurs par défaut');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('4. Modification partielle');
$pro = $pdo->query("SELECT * FROM platform_plans WHERE code = 'pro'")->fetch(PDO::FETCH_ASSOC);
$proId = (int)$pro['id'];
check($plans->update($proId, ['nom' => 'Pro Renommé'], $superId) === true, 'modifier le seul nom réussit');
$pro2 = $plans->find($proId);
$inchanges = array_diff_key($pro, ['nom' => 1, 'updated_at' => 1]);
check(array_diff_assoc(array_diff_key($pro2, ['nom' => 1, 'updated_at' => 1]), $inchanges) === [], 'toutes les autres colonnes conservées (limites, modules, prix DZD, devise)');
check($pro2['nom'] === 'Pro Renommé' && $pro2['devise'] === 'DZD' && $pro2['prix_mensuel'] === '6500.00' && (int)$pro2['max_users'] === 80, 'plan DZD : devise et prix conservés, limites non remises à 50/500');
check($pro2['updated_at'] !== null, 'updated_at renseigné à la modification');
check($plans->update($proId, ['nom' => 'Pro Renommé'], $superId) === false, 'données identiques : aucune modification');
check(count($audits('plan.updated')) === 1, 'aucune écriture d\'audit quand rien ne change');
check($plans->update($proId, ['jours_grace' => '5'], $superId) === true && (int)$plans->find($proId)['jours_grace'] === 5 && $plans->find($proId)['modules_inclus'] === $pro['modules_inclus'], 'un seul champ commercial modifié, modules conservés');
check($plans->update($proId, ['modules_present' => '1', 'modules_inclus' => ['finance']], $superId) === true && json_decode($plans->find($proId)['modules_inclus'], true) === ['finance'], 'liste de modules remplacée quand elle est soumise');
check($plans->update($proId, ['modules_present' => '1', 'modules_inclus' => []], $superId) === true && json_decode($plans->find($proId)['modules_inclus'], true) === [], 'liste de modules vidée explicitement (aucune case cochée)');
check(contient(erreurs(fn() => $plans->update($proId, ['code' => 'autre'], $superId)), 'code'), 'code immuable');
check($plans->find($proId)['code'] === 'pro', 'code inchangé après tentative');
check(contient(erreurs(fn() => $plans->update($proId, ['max_users' => '0'], $superId)), 'utilisateurs') && (int)$plans->find($proId)['max_users'] === 80, 'valeur invalide : refus et plan intact');
check($plans->update(999999, ['nom' => 'Fantôme'], $superId) === false, 'plan inexistant : false');
$starter = (int)$pdo->query("SELECT id FROM platform_plans WHERE code = 'starter'")->fetchColumn();
check(erreurs(fn() => $plans->update($starter, ['prix_mensuel' => '3000.50'], $superId)) === [], 'plan DZD à 2 décimales : montant décimal accepté');
check(contient(erreurs(fn() => $plans->update($starter, ['devise' => 'XOF'], $superId)), 'entier'), 'passer en XOF avec un montant décimal existant : refus, aucune conversion');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('5. Plan utilisé : prix / devise avec confirmation explicite');
// Établissement DÉJÀ rattaché à un plan DZD (situation héritée) : posé directement, car une nouvelle souscription DZD est refusée (étape 4).
$etabId = $etabs->create(['slug' => 'ecole-plan-test', 'nom' => 'École Plan Test', 'nom_court' => 'EPT', 'type' => 'lycee'], $superId);
$pdo->prepare('UPDATE etablissements SET plan_id = ? WHERE id = ?')->execute([$proId, $etabId]);
check($plans->usageCount($proId) >= 1, 'le plan est utilisé par un établissement');
$msg = erreurs(fn() => $plans->update($proId, ['prix_mensuel' => '7000'], $superId));
check(contient($msg, 'confirmation explicite'), 'changement de prix sans confirmation : refusé');
check($plans->find($proId)['prix_mensuel'] === '6500.00', 'prix inchangé sans confirmation');
check(contient(erreurs(fn() => $plans->update($proId, ['devise' => 'XOF', 'prix_mensuel' => '6500', 'prix_annuel' => '65000'], $superId)), 'confirmation explicite'), 'changement de devise sans confirmation : refusé');
check($plans->update($proId, ['nom' => 'Pro Bis'], $superId) === true, 'modification sans prix ni devise : pas de confirmation requise');
$nAvant = count($audits('plan.updated'));
check($plans->update($proId, ['devise' => 'XOF', 'prix_mensuel' => '6500', 'prix_annuel' => '65000', 'confirmer_conditions' => '1'], $superId) === true, 'changement de devise avec confirmation accepté');
$ligne = $plans->find($proId);
check($ligne['devise'] === 'XOF' && $ligne['prix_mensuel'] === '6500.00', 'devise changée, montants fournis par l\'opérateur (aucune conversion automatique)');
$a = array_slice($audits('plan.updated'), $nAvant)[0] ?? null;
$av = $a ? json_decode($a['ancienne_valeur'], true) : [];
$ap = $a ? json_decode($a['nouvelle_valeur'], true) : [];
check($av['devise'] === 'DZD' && $ap['devise'] === 'XOF' && $av['prix_mensuel'] === '6500.00', 'audit : devise avant (DZD) / après (XOF) et prix');
check(($ap['confirmation_explicite'] ?? false) === true && (int)($ap['etablissements_concernes'] ?? 0) >= 1, 'audit : confirmation explicite et nombre d\'établissements concernés');
check($a && (int)$a['entity_id'] === $proId && $a['actor_email'] === 'super@exemple.test', 'audit : plan et opérateur');
$libre = $plans->create(['code' => 'libre', 'nom' => 'Libre', 'prix_mensuel' => '1000', 'prix_annuel' => '10000'], $superId);
check($plans->update($libre, ['prix_mensuel' => '2000'], $superId) === true, 'plan inutilisé : prix modifiable sans confirmation');
check(array_key_exists('confirmation_explicite', json_decode(dernier($audits('plan.updated'))['nouvelle_valeur'], true)) === false, 'plan inutilisé : pas de marqueur de confirmation dans l\'audit');
// Les conditions sont lues dans le plan au moment de l'assignation : l'établissement garde ses limites copiées.
$etabAvant = $etabs->find($etabId);
$plans->update($proId, ['max_users' => '999', 'confirmer_conditions' => '1'], $superId);
check((int)$etabs->find($etabId)['max_users'] === (int)$etabAvant['max_users'], 'les limites copiées sur l\'établissement ne changent pas rétroactivement');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('6. Archivage et restauration');
$nbEtabs = $plans->usageCount($proId);
check($plans->toggleActive($proId, false, $superId) === true && (int)$plans->find($proId)['actif'] === 0, 'archivage');
check($plans->usageCount($proId) === $nbEtabs && (int)$etabs->find($etabId)['plan_id'] === $proId, 'établissements existants conservés sur le plan archivé');
check($etabs->assignPlan($etabId, $proId, $superId) === false, 'plan archivé : assignation refusée');
$autre = $etabs->create(['slug' => 'ecole-autre', 'nom' => 'École Autre', 'nom_court' => 'EA', 'type' => 'lycee'], $superId);
check($etabs->assignPlan($autre, $proId, $superId) === false && $etabs->find($autre)['plan_id'] === null, 'plan archivé : établissement non rattaché');
$refus = false;
try { $etabs->create(['slug' => 'ecole-refus', 'nom' => 'Refus', 'nom_court' => 'R', 'type' => 'lycee', 'plan_id' => $proId], $superId); } catch (InvalidArgumentException) { $refus = true; }
check($refus && (int)$pdo->query("SELECT COUNT(*) FROM etablissements WHERE slug = 'ecole-refus'")->fetchColumn() === 0, 'plan archivé : création d\'établissement avec ce plan refusée');
check(count($plans->all(false)) === count($plans->all()) - 1, 'all(false) exclut les plans archivés');
$arch = dernier($audits('plan.toggled'));
check(json_decode($arch['ancienne_valeur'], true)['actif'] === 1 && json_decode($arch['nouvelle_valeur'], true)['actif'] === 0 && (int)json_decode($arch['nouvelle_valeur'], true)['etablissements_conserves'] === $nbEtabs, 'audit archivage : 1 → 0 et établissements conservés');
check($plans->toggleActive($proId, true, $superId) === true && $etabs->assignPlan($autre, $proId, $superId) === true, 'restauration : plan de nouveau assignable');
$rest = dernier($audits('plan.toggled'));
check(json_decode($rest['nouvelle_valeur'], true)['actif'] === 1, 'audit restauration');
check($plans->toggleActive(999999, false, $superId) === false, 'archiver un plan inexistant : false');
check((int)$pdo->query("SELECT COUNT(*) FROM platform_plans WHERE code IN ('starter','pro','enterprise')")->fetchColumn() === 3, 'aucune suppression physique');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('6 bis. Idempotence de l\'archivage et atomicité des modifications');
$idem = $plans->create(['code' => 'idem', 'nom' => 'Idempotent'] + $planBase, $superId);
$nTog = count($audits('plan.toggled'));
check($plans->toggleActive($idem, true, $superId) === false, 'restaurer un plan déjà actif : false');
check(count($audits('plan.toggled')) === $nTog && $plans->find($idem)['updated_at'] === null, 'plan déjà actif : ni audit ni updated_at');
check($plans->toggleActive($idem, false, $superId) === true && count($audits('plan.toggled')) === $nTog + 1, 'archivage réel : audit écrit');
$majArchive = $plans->find($idem)['updated_at'];
$pdo->exec("UPDATE platform_plans SET updated_at = '2020-01-01 00:00:00' WHERE id = $idem");   // date témoin : doit rester intacte
check($plans->toggleActive($idem, false, $superId) === false, 'archiver un plan déjà archivé : false');
check(count($audits('plan.toggled')) === $nTog + 1 && $plans->find($idem)['updated_at'] === '2020-01-01 00:00:00', 'plan déjà archivé : audit et updated_at inchangés');
check($majArchive !== null && $plans->toggleActive($idem, true, $superId) === true, 'restauration réelle');

// Atomicité : un déclencheur fait échouer la 2e écriture (updated_at) de ce seul plan ; la 1re écriture doit être annulée.
$pdo->exec("CREATE TRIGGER t_plan_echec BEFORE UPDATE ON platform_plans FOR EACH ROW BEGIN
    IF NEW.code = 'atomique' AND NEW.updated_at IS NOT NULL AND OLD.updated_at IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'echec simule'; END IF; END");
try {
    $atom = $plans->create(['code' => 'atomique', 'nom' => 'Atomique'] + $planBase, $superId);
    $avantAtom = $plans->find($atom);
    $nUp = count($audits('plan.updated'));
    $leve = false;
    try { $plans->update($atom, ['nom' => 'Nom modifié', 'max_users' => '77'], $superId); } catch (\Throwable) { $leve = true; }
    $apres = $plans->find($atom);
    check($leve, 'échec en cours de modification : exception relancée');
    check($apres['nom'] === $avantAtom['nom'] && (int)$apres['max_users'] === (int)$avantAtom['max_users'], 'échec : première écriture annulée (transaction)');
    check(count($audits('plan.updated')) === $nUp && !$pdo->inTransaction(), 'échec : aucun audit, aucune transaction laissée ouverte');
    // Transaction englobante : seul le travail du service est annulé (point de sauvegarde), le reste de la transaction demeure.
    $pdo->beginTransaction();
    $pdo->exec("UPDATE platform_plans SET description = 'témoin englobant' WHERE id = $atom");
    $leve = false;
    try { $plans->update($atom, ['nom' => 'Autre nom'], $superId); } catch (\Throwable) { $leve = true; }
    $dedans = $plans->find($atom);
    check($leve && $pdo->inTransaction() && $dedans['nom'] === $avantAtom['nom'] && $dedans['description'] === 'témoin englobant', 'dans une transaction englobante : seule l\'opération du service est annulée');
    $pdo->rollBack();
    check($plans->find($atom)['description'] === null, 'la transaction englobante reste maîtresse de son annulation');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS t_plan_echec');
}
check($plans->update($atom, ['nom' => 'Atomique OK'], $superId) === true, 'sans l\'échec simulé, la même modification réussit');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('6 ter. Création atomique : échec de l\'audit et collision de code');
$secoursAvant = glob($dossierSecours . '/platform_audit_fallback-*.jsonl') ?: [];
$pdo->exec("CREATE TRIGGER t_audit_echec BEFORE INSERT ON platform_audit_logs FOR EACH ROW BEGIN
    IF NEW.action = 'plan.created' AND NEW.nouvelle_valeur LIKE '%audit-ko%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'echec audit simule'; END IF; END");
try {
    $nPlansAvant = $compteurPlans('audit-ko'); $nCreesAvant = count($audits('plan.created')); $echecsAvant = PlatformAuditService::echecsDeLaRequete();
    $levee = null;
    try { $plans->create(['code' => 'audit-ko', 'nom' => 'Audit KO'] + $planBase, $superId); } catch (\Throwable $e) { $levee = $e; }
    check($levee instanceof PlanAuditException && !($levee instanceof PlanValidationException) && str_contains($levee->getMessage(), 'Aucun plan'), 'échec de l\'audit : création refusée avec un message clair', $levee ? get_class($levee) . ' ' . $levee->getMessage() : 'aucune exception');
    check($levee !== null && $levee->getPrevious() !== null, 'la cause technique est conservée (exception chaînée) sans être affichée');
    check($nPlansAvant === 0 && $compteurPlans('audit-ko') === 0, 'échec de l\'audit : le plan n\'est PAS créé (insertion annulée)');
    check(count($audits('plan.created')) === $nCreesAvant && !$pdo->inTransaction(), 'échec de l\'audit : aucune trace partielle, aucune transaction ouverte');
    check(PlatformAuditService::echecsDeLaRequete() === $echecsAvant && (glob($dossierSecours . '/platform_audit_fallback-*.jsonl') ?: []) === $secoursAvant, 'trace stricte : aucun fichier de secours ni compteur d\'échec (rien à rejouer pour un plan inexistant)');
    // Dans une transaction englobante : seul le travail du service est annulé
    $pdo->beginTransaction();
    $pdo->exec("UPDATE platform_plans SET description = 'témoin création' WHERE code = 'starter'");
    $levee = null;
    try { $plans->create(['code' => 'audit-ko', 'nom' => 'Audit KO'] + $planBase, $superId); } catch (\Throwable $e) { $levee = $e; }
    check($levee instanceof PlanAuditException && $pdo->inTransaction() && $compteurPlans('audit-ko') === 0 && $pdo->query("SELECT description FROM platform_plans WHERE code = 'starter'")->fetchColumn() === 'témoin création', 'transaction englobante : seule la création est annulée');
    $pdo->rollBack();
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS t_audit_echec');
}
$okId = $plans->create(['code' => 'audit-ko', 'nom' => 'Audit rétabli'] + $planBase, $superId);
check($okId > 0 && $compteurPlans('audit-ko') === 1 && count($audits('plan.created')) === $nCreesAvant + 1, 'journal rétabli : le même code se crée normalement, avec sa trace');
check((int)(dernier($audits('plan.created'))['entity_id'] ?? 0) === $okId, 'la trace pointe vers le plan réellement créé');

// Collision : un autre opérateur crée le même code entre la vérification et l'insertion (instantané ancien + 2e connexion)
$cx = $copie->connection();
$connexion2 = new PDO("mysql:host={$cx['host']};port={$cx['port']};dbname={$cx['db']};charset=utf8mb4", $cx['user'], $cx['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$nCreesAvant = count($audits('plan.created'));
$pdo->beginTransaction();
$pdo->query('SELECT COUNT(*) FROM platform_plans')->fetchColumn();   // instantané de lecture ancré avant la course
$connexion2->exec("INSERT INTO platform_plans (code, nom, modules_inclus) VALUES ('course', 'Plan concurrent', '[]')");   // validé aussitôt (autocommit)
check((int)$pdo->query("SELECT COUNT(*) FROM platform_plans WHERE code = 'course'")->fetchColumn() === 0, 'course simulée : la vérification préalable ne voit pas encore le plan concurrent');
$e = erreurs(fn() => $plans->create(['code' => 'course', 'nom' => 'Perdant de la course'] + $planBase, $superId));
check(contient($e, 'existe déjà') && !contient($e, 'SQLSTATE') && !contient($e, 'Duplicate') && !contient($e, 'Integrity'), 'collision de code : message de validation clair, aucune erreur SQL brute', implode(' | ', $e));
check($pdo->inTransaction(), 'collision : la transaction englobante reste utilisable');
$pdo->rollBack();
check($pdo->query("SELECT nom FROM platform_plans WHERE code = 'course'")->fetchColumn() === 'Plan concurrent' && $compteurPlans('course') === 1, 'collision : un seul plan « course », celui du premier arrivé');
check(count($audits('plan.created')) === $nCreesAvant, 'collision : aucune trace de création pour le perdant');
unset($connexion2);

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('6 quater. Modification et archivage / restauration : audit strict et transactionnel');
$idKo = $plans->create(['code' => 'maj-audit', 'nom' => 'Maj Audit', 'max_users' => 20] + $planBase, $superId);
$declencheur = static fn(int $planId) => $pdo->exec("CREATE TRIGGER t_audit_echec_maj BEFORE INSERT ON platform_audit_logs FOR EACH ROW BEGIN
    IF NEW.action IN ('plan.updated', 'plan.toggled') AND NEW.entity_id = $planId THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'echec audit simule'; END IF; END");
$secoursAvant = glob($dossierSecours . '/platform_audit_fallback-*.jsonl') ?: [];
$instantane = static fn() => $pdo->query("SELECT * FROM platform_plans WHERE id = $idKo")->fetch(PDO::FETCH_ASSOC);
$nMaj = count($audits('plan.updated')); $nTog = count($audits('plan.toggled')); $echecsAvant = PlatformAuditService::echecsDeLaRequete();
$attrape = static function (callable $f): ?\Throwable { try { $f(); return null; } catch (\Throwable $e) { return $e; } };

// Normal, avant la panne : audit avant / après de la modification et de l'archivage
check($plans->update($idKo, ['nom' => 'Maj Audit v2', 'max_users' => '25'], $superId) === true, 'modification normale réussit');
$m = dernier($audits('plan.updated'));
$mav = json_decode($m['ancienne_valeur'], true); $map = json_decode($m['nouvelle_valeur'], true);
check($mav['nom'] === 'Maj Audit' && $map['nom'] === 'Maj Audit v2' && (int)$mav['max_users'] === 20 && (int)$map['max_users'] === 25 && (int)$m['entity_id'] === $idKo, 'modification normale : audit avant / après');
check($plans->toggleActive($idKo, false, $superId) === true, 'archivage normal réussit');
$t = dernier($audits('plan.toggled'));
check(json_decode($t['ancienne_valeur'], true)['actif'] === 1 && json_decode($t['nouvelle_valeur'], true)['actif'] === 0, 'archivage normal : audit avant / après');
check($plans->toggleActive($idKo, true, $superId) === true && json_decode(dernier($audits('plan.toggled'))['nouvelle_valeur'], true)['actif'] === 1, 'restauration normale : audit avant / après');
$nMaj = count($audits('plan.updated')); $nTog = count($audits('plan.toggled'));

$declencheur($idKo);
try {
    // Modification : l'audit échoue -> plan strictement inchangé
    $avant = $instantane();
    $e = $attrape(fn() => $plans->update($idKo, ['nom' => 'Ne doit pas passer', 'max_users' => '99', 'jours_grace' => '9', 'modules_present' => '1', 'modules_inclus' => ['rh']], $superId));
    check($e instanceof PlanAuditException && !($e instanceof PlanValidationException) && str_contains($e->getMessage(), 'Modification annulée') && str_contains($e->getMessage(), 'inchangé'), 'échec de l\'audit : modification refusée avec un message clair', $e ? get_class($e) . ' ' . $e->getMessage() : 'aucune exception');
    check($e !== null && $e->getPrevious() !== null, 'modification : cause technique conservée (exception chaînée)');
    check($instantane() === $avant, 'échec de l\'audit : AUCUNE colonne modifiée (données, modules, updated_at)');
    check(count($audits('plan.updated')) === $nMaj && !$pdo->inTransaction(), 'modification : aucune trace partielle, aucune transaction ouverte');

    // Archivage : l'audit échoue -> toujours actif
    $e = $attrape(fn() => $plans->toggleActive($idKo, false, $superId));
    check($e instanceof PlanAuditException && str_contains($e->getMessage(), 'Archivage annulé'), 'échec de l\'audit : archivage refusé avec un message clair', $e ? $e->getMessage() : 'aucune exception');
    check($instantane() === $avant && (int)$instantane()['actif'] === 1, 'échec de l\'audit : le plan reste actif (état et updated_at inchangés)');
    check(count($audits('plan.toggled')) === $nTog && !$pdo->inTransaction(), 'archivage : aucune trace partielle, aucune transaction ouverte');
    // Restauration : l'audit échoue -> toujours archivé (l'archivage préalable se fait sans le déclencheur)
    $pdo->exec('DROP TRIGGER t_audit_echec_maj');
    $plans->toggleActive($idKo, false, $superId);
    $nTog = count($audits('plan.toggled'));
    $declencheur($idKo);
    $archive = $instantane();
    $e = $attrape(fn() => $plans->toggleActive($idKo, true, $superId));
    check($e instanceof PlanAuditException && str_contains($e->getMessage(), 'Restauration annulée'), 'échec de l\'audit : restauration refusée avec un message clair', $e ? $e->getMessage() : 'aucune exception');
    check($instantane() === $archive && (int)$instantane()['actif'] === 0 && count($audits('plan.toggled')) === $nTog && !$pdo->inTransaction(), 'échec de l\'audit : le plan reste archivé, aucune trace, aucune transaction ouverte');
    // Panne stricte : ni compteur d'échec, ni fichier de secours
    check(PlatformAuditService::echecsDeLaRequete() === $echecsAvant && (glob($dossierSecours . '/platform_audit_fallback-*.jsonl') ?: []) === $secoursAvant, 'audit strict : aucun fichier de secours ni compteur d\'échec');

    // Transaction englobante : seul le travail du service est annulé, la transaction de l'appelant reste intacte et maîtresse
    $pdo->beginTransaction();
    $pdo->exec("UPDATE platform_plans SET description = 'témoin appelant' WHERE id = $idKo");
    $e1 = $attrape(fn() => $plans->update($idKo, ['nom' => 'Autre nom'], $superId));
    $e2 = $attrape(fn() => $plans->toggleActive($idKo, true, $superId));
    $dedans = $instantane();
    check($e1 instanceof PlanAuditException && $e2 instanceof PlanAuditException && $pdo->inTransaction(), 'transaction englobante : les deux échecs sont levés, la transaction de l\'appelant reste ouverte');
    check($dedans['description'] === 'témoin appelant' && $dedans['nom'] === 'Maj Audit v2' && (int)$dedans['actif'] === 0, 'transaction englobante : travail de l\'appelant conservé, travail du service annulé');
    $pdo->rollBack();
    check($instantane()['description'] === null && !$pdo->inTransaction(), 'la transaction englobante reste maîtresse de son annulation');
    // Données invalides ou rien à modifier : aucune exception d'audit, aucune écriture
    check(contient(erreurs(fn() => $plans->update($idKo, ['max_users' => '0'], $superId)), 'utilisateurs') && $plans->update($idKo, ['nom' => 'Maj Audit v2'], $superId) === false, 'validation et « aucun changement » : aucune écriture d\'audit tentée');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS t_audit_echec_maj');
}
// Journal rétabli : mêmes opérations, avec trace
check($plans->update($idKo, ['nom' => 'Maj Audit v3'], $superId) === true && $plans->toggleActive($idKo, true, $superId) === true, 'journal rétabli : modification et restauration réussissent');
check(count($audits('plan.updated')) === $nMaj + 1 && count($audits('plan.toggled')) === $nTog + 1 && (int)$plans->find($idKo)['actif'] === 1 && !$pdo->inTransaction(), 'journal rétabli : une trace par opération, plan restauré, aucune transaction ouverte');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('7. Parcours HTTP et droits');
$P = '/ecole_app/platform/plans';
$compte = static fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
$r = http($nomCopie, 'guest', 'GET', $P);
check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/login'), 'visiteur anonyme : redirigé vers la connexion');
foreach (['support' => $supportId, 'admin' => $adminId] as $niveau => $opId) {
    $o = operateur($opId, $niveau);
    $r = http($nomCopie, $o, 'GET', $P);
    check($r['status'] === 200 && str_contains((string)$r['text'], 'Lecture seule') && !str_contains((string)$r['text'], 'Nouveau plan') && str_contains((string)$r['text'], 'Starter'), "$niveau : liste en lecture seule, sans bouton d'écriture");
    check(!str_contains((string)$r['text'], 'Modifier') && !str_contains((string)$r['text'], 'Archiver'), "$niveau : aucun bouton Modifier / Archiver");
    foreach (["GET $P/create", "GET $P/$proId/modifier"] as $req) {
        [$m, $u] = explode(' ', $req);
        check(http($nomCopie, $o, $m, $u)['status'] === 403, "$niveau : $req refusé (403)");
    }
    $nbPlans = $compte('SELECT COUNT(*) FROM platform_plans'); $nomAvant = $plans->find($proId)['nom']; $actifAvant = $plans->find($proId)['actif'];
    check(http($nomCopie, $o, 'POST', $P, $planBase + ['code' => "refus-$niveau"])['status'] === 403 && $compte('SELECT COUNT(*) FROM platform_plans') === $nbPlans, "$niveau : création refusée, aucun plan créé");
    check(http($nomCopie, $o, 'POST', "$P/$proId", ['nom' => 'Piraté'])['status'] === 403 && $plans->find($proId)['nom'] === $nomAvant, "$niveau : modification refusée, plan intact");
    check(http($nomCopie, $o, 'POST', "$P/$proId/toggle", ['active' => '0'])['status'] === 403 && $plans->find($proId)['actif'] === $actifAvant, "$niveau : archivage refusé, plan intact");
}
$denied = $compte("SELECT COUNT(*) FROM platform_audit_logs WHERE action = 'access.denied' AND resultat = 'denied'");
check($denied >= 10, 'les refus d\'accès sont journalisés', (string)$denied);

$S = operateur($superId, 'super_admin');
$r = http($nomCopie, $S, 'GET', $P);
check($r['status'] === 200 && str_contains((string)$r['text'], 'Nouveau plan') && str_contains((string)$r['text'], 'Modifier') && str_contains((string)$r['text'], 'Archiver'), 'super_admin : liste avec les actions d\'écriture');
check(str_contains((string)$r['text'], 'Devise à vérifier'), 'plans DZD : badge « Devise à vérifier » (alerte, sans conversion)');
$r = http($nomCopie, $S, 'GET', "$P/create");
check($r['status'] === 200 && str_contains((string)$r['text'], 'Modules inclus') && str_contains((string)$r['text'], 'Durée d\'essai') && str_contains((string)$r['text'], 'Jours de grâce') && str_contains((string)$r['text'], 'Ordre d\'affichage') && str_contains((string)$r['text'], 'Visible dans le catalogue'), 'super_admin : formulaire de création complet');
foreach ($connus as $m) { if (!str_contains((string)$r['text'], $m)) { check(false, "module $m absent du formulaire"); } }
check(true, 'formulaire : toutes les clés de config/modules.php proposées');
$r = http($nomCopie, $S, 'GET', "$P/$proId/modifier");
check($r['status'] === 200 && str_contains((string)$r['text'], 'Modifier le plan') && str_contains((string)$r['text'], 'confirmation explicite'), 'super_admin : formulaire d\'édition avec avertissement de plan utilisé');
check(http($nomCopie, $S, 'GET', "$P/999999/modifier")['status'] === 404, 'formulaire d\'édition d\'un plan inexistant : 404');

$r = http($nomCopie, $S, 'POST', $P, ['code' => 'http-plan', 'nom' => 'Plan HTTP', 'max_users' => '30', 'max_eleves' => '300', 'storage_quota_mb' => '1000', 'api_calls_per_day' => '1000',
    'devise' => 'xof', 'prix_mensuel' => '4000', 'prix_annuel' => '40000', 'duree_essai_jours' => '10', 'jours_grace' => '3', 'ordre' => '9', 'visible_catalogue' => '1',
    'modules_present' => '1', 'modules_inclus' => ['finance', 'rh'], 'role' => 'hack', 'actif' => '0']);
$h = $pdo->query("SELECT * FROM platform_plans WHERE code = 'http-plan'")->fetch(PDO::FETCH_ASSOC);
check($r['status'] === 302 && $h !== false && $h['devise'] === 'XOF' && (int)$h['duree_essai_jours'] === 10 && json_decode($h['modules_inclus'], true) === ['finance', 'rh'], 'super_admin : création HTTP');
check((int)$h['actif'] === 1, 'champ non prévu (actif) ignoré à la création');
$hid = (int)$h['id'];
$r = http($nomCopie, $S, 'POST', $P, ['code' => 'http-bad', 'nom' => 'X', 'devise' => 'zz', 'prix_mensuel' => '-3']);
check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/plans/create') && $compte("SELECT COUNT(*) FROM platform_plans WHERE code = 'http-bad'") === 0, 'création invalide : retour au formulaire, rien écrit');
// Journal d'audit indisponible pendant la création : retour au formulaire, aucun plan, aucun fichier de secours
$pdo->exec("CREATE TRIGGER t_audit_echec_http BEFORE INSERT ON platform_audit_logs FOR EACH ROW BEGIN
    IF NEW.action = 'plan.created' AND NEW.nouvelle_valeur LIKE '%http-audit-ko%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'echec audit simule'; END IF; END");
try {
    $secoursHttp = glob($dossierSecours . '/platform_audit_fallback-*.jsonl') ?: [];
    $r = http($nomCopie, $S, 'POST', $P, ['code' => 'http-audit-ko', 'nom' => 'Plan audit KO', 'prix_mensuel' => '1000', 'prix_annuel' => '10000']);
    check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/plans/create'), 'HTTP : échec de l\'audit à la création : retour au formulaire (pas d\'erreur serveur)', (string)($r['status'] ?? ''));
    check($compte("SELECT COUNT(*) FROM platform_plans WHERE code = 'http-audit-ko'") === 0, 'HTTP : échec de l\'audit : aucun plan créé');
    check((glob($dossierSecours . '/platform_audit_fallback-*.jsonl') ?: []) === $secoursHttp, 'HTTP : échec strict : aucun fichier de secours');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS t_audit_echec_http');
}
// Audit indisponible pendant la modification / l'archivage : retour avec message, plan inchangé
$planHttp = $plans->find($idKo);
$declencheur($idKo);
try {
    $r = http($nomCopie, $S, 'POST', "$P/$idKo", ['nom' => 'Http audit KO', 'max_users' => '33']);
    check($r['status'] === 302 && str_contains((string)$r['location'], "/platform/plans/$idKo/modifier") && $plans->find($idKo) === $planHttp, 'HTTP : échec de l\'audit à la modification : retour au formulaire, plan inchangé');
    $r = http($nomCopie, $S, 'POST', "$P/$idKo/toggle", ['active' => '0']);
    check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/plans') && $plans->find($idKo) === $planHttp, 'HTTP : échec de l\'audit à l\'archivage : plan toujours actif et inchangé');
    check((glob($dossierSecours . '/platform_audit_fallback-*.jsonl') ?: []) === $secoursAvant, 'HTTP : échec strict : aucun fichier de secours');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS t_audit_echec_maj');
}
// Erreur SQL inattendue pendant l'écriture du plan : jamais affichée à l'opérateur, opération annulée, détail journalisé côté serveur
$jeton = 'sqlko' . bin2hex(random_bytes(4));
$pdo->exec("CREATE TRIGGER t_plan_sql_ko_up BEFORE UPDATE ON platform_plans FOR EACH ROW BEGIN
    IF NEW.id = $idKo AND (NEW.nom = 'SQL-KO' OR NEW.actif <> OLD.actif) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'echec sql simule $jeton'; END IF; END");
$pdo->exec("CREATE TRIGGER t_plan_sql_ko_in BEFORE INSERT ON platform_plans FOR EACH ROW BEGIN
    IF NEW.code = 'sql-ko-http' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'echec sql simule $jeton'; END IF; END");
try {
    $planSql = $plans->find($idKo);
    $nMajSql = count($audits('plan.updated')); $nTogSql = count($audits('plan.toggled')); $nCreeSql = count($audits('plan.created'));
    $secoursSql = glob($dossierSecours . '/platform_audit_fallback-*.jsonl') ?: [];
    $journalApp = ROOT_PATH . '/storage/logs/app-' . date('Y-m-d') . '.log';
    $tailleLog = is_file($journalApp) ? filesize($journalApp) : 0;
    $nouveauLog = static function () use ($journalApp, &$tailleLog): string {
        clearstatcache(true, $journalApp);
        return is_file($journalApp) ? (string)file_get_contents($journalApp, false, null, $tailleLog) : '';
    };
    $sansDetailSql = static fn(string $t): bool => !str_contains($t, 'SQLSTATE') && !str_contains($t, $GLOBALS['jeton']) && !str_contains($t, '1644')
        && !str_contains($t, 'platform_plans') && !str_contains($t, 'Unknown error') && !str_contains($t, 'echec sql simule');

    // Niveau service : une erreur SQL reste une PDOException (jamais déguisée en échec d'audit) et l'opération est annulée
    $e = $attrape(fn() => $plans->update($idKo, ['nom' => 'SQL-KO'], $superId));
    check($e instanceof \PDOException && !($e instanceof PlanAuditException) && $plans->find($idKo) === $planSql && !$pdo->inTransaction(), 'service : erreur SQL pendant l\'UPDATE : PDOException relancée, plan inchangé, aucune transaction ouverte');

    // HTTP : modification
    $r = http($nomCopie, $S, 'POST', "$P/$idKo", ['nom' => 'SQL-KO', 'max_users' => '41']);
    $flash = json_encode($r['flash'] ?? [], JSON_UNESCAPED_UNICODE);
    check($r['status'] === 302 && str_contains((string)$r['location'], "/platform/plans/$idKo/modifier"), 'HTTP : erreur SQL à la modification : retour au formulaire (pas de page d\'erreur)', (string)($r['status'] ?? ''));
    check($sansDetailSql($flash) && str_contains($flash, 'erreur technique'), 'HTTP : aucun détail SQL affiché à l\'opérateur, message générique à la place', $flash);
    check($plans->find($idKo) === $planSql && count($audits('plan.updated')) === $nMajSql, 'HTTP : plan inchangé et aucune trace d\'audit partielle');
    $reference = preg_match('/Référence à communiquer à l\'administrateur : ([0-9a-f]{8})/u', $flash, $m) ? $m[1] : '';
    $log = $nouveauLog();
    check($reference !== '' && str_contains($log, "réf. $reference") && str_contains($log, $jeton) && str_contains($log, 'PDOException') && str_contains($log, 'modification du plan'), 'HTTP : le détail technique (classe, message SQL) est journalisé côté serveur avec la référence affichée', $reference);
    $tailleLog += strlen($log);

    // HTTP : archivage (l'UPDATE de l'état échoue)
    $r = http($nomCopie, $S, 'POST', "$P/$idKo/toggle", ['active' => '0']);
    $flash = json_encode($r['flash'] ?? [], JSON_UNESCAPED_UNICODE);
    check($r['status'] === 302 && $sansDetailSql($flash) && str_contains($flash, 'erreur technique') && $plans->find($idKo) === $planSql && count($audits('plan.toggled')) === $nTogSql, 'HTTP : erreur SQL à l\'archivage : message générique, plan toujours actif, aucune trace');
    $log = $nouveauLog();
    check(str_contains($log, $jeton) && str_contains($log, 'archivage du plan'), 'HTTP : archivage : détail technique journalisé côté serveur');
    $tailleLog += strlen($log);

    // HTTP : création (l'INSERT échoue)
    $r = http($nomCopie, $S, 'POST', $P, ['code' => 'sql-ko-http', 'nom' => 'Plan SQL KO', 'prix_mensuel' => '1000', 'prix_annuel' => '10000']);
    $flash = json_encode($r['flash'] ?? [], JSON_UNESCAPED_UNICODE);
    check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/plans/create') && $sansDetailSql($flash) && str_contains($flash, 'erreur technique'), 'HTTP : erreur SQL à la création : retour au formulaire, message générique');
    check($compteurPlans('sql-ko-http') === 0 && count($audits('plan.created')) === $nCreeSql && str_contains($nouveauLog(), $jeton), 'HTTP : création : aucun plan, aucune trace, détail technique journalisé');
    check((glob($dossierSecours . '/platform_audit_fallback-*.jsonl') ?: []) === $secoursSql, 'HTTP : erreur SQL : aucun fichier de secours d\'audit');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS t_plan_sql_ko_up');
    $pdo->exec('DROP TRIGGER IF EXISTS t_plan_sql_ko_in');
}
$r = http($nomCopie, $S, 'POST', "$P/$hid", ['nom' => 'Plan HTTP v2']);
$h2 = $plans->find($hid);
check($r['status'] === 302 && $h2['nom'] === 'Plan HTTP v2' && (int)$h2['max_users'] === 30 && $h2['prix_mensuel'] === '4000.00' && json_decode($h2['modules_inclus'], true) === ['finance', 'rh'], 'modification HTTP partielle : seul le nom change');
$r = http($nomCopie, $S, 'POST', "$P/$hid", ['nom' => 'Plan HTTP v3', 'modules_present' => '1', 'visible_catalogue' => '0']);
$h3 = $plans->find($hid);
check($h3['nom'] === 'Plan HTTP v3' && json_decode($h3['modules_inclus'], true) === [] && (int)$h3['visible_catalogue'] === 0, 'formulaire sans case de module cochée : liste vidée ; visibilité décochée');
$r = http($nomCopie, $S, 'POST', "$P/$hid", ['code' => 'pirate', 'max_users' => '0']);
check($r['status'] === 302 && str_contains((string)$r['location'], "/platform/plans/$hid/modifier") && $plans->find($hid)['code'] === 'http-plan' && (int)$plans->find($hid)['max_users'] === 30, 'modification invalide : retour au formulaire, plan intact');
$r = http($nomCopie, $S, 'POST', "$P/$proId", ['prix_mensuel' => '9000']);
check($plans->find($proId)['prix_mensuel'] === '6500.00', 'HTTP : prix d\'un plan utilisé sans confirmation refusé');
$r = http($nomCopie, $S, 'POST', "$P/$proId", ['prix_mensuel' => '9000', 'confirmer_conditions' => '1']);
check($plans->find($proId)['prix_mensuel'] === '9000.00', 'HTTP : prix d\'un plan utilisé avec confirmation accepté');
$r = http($nomCopie, $S, 'POST', "$P/$hid/toggle", ['active' => '0']);
check($r['status'] === 302 && (int)$plans->find($hid)['actif'] === 0, 'HTTP : archivage');
$r = http($nomCopie, $S, 'GET', $P);
check(str_contains((string)$r['text'], 'Archivé') && str_contains((string)$r['text'], 'Restaurer'), 'liste : plan archivé signalé, bouton Restaurer');
$r = http($nomCopie, $S, 'POST', "$P/$hid/toggle", ['active' => '1']);
check((int)$plans->find($hid)['actif'] === 1, 'HTTP : restauration');
$r = http($nomCopie, $S, 'POST', "$P/999999/toggle", ['active' => '0']);
check($r['status'] === 302, 'HTTP : archivage d\'un plan inexistant sans erreur serveur');
// Sans jeton CSRF
[$c, $o] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/platform_request.php', $S, 'POST', "$P/$hid", json_encode(['nom' => 'Sans CSRF'])],
    ['REQUEST_AS_DB' => $nomCopie, 'PLATFORM_REQ_AUDIT_DIR' => $dossierSecours, 'REQUEST_AS_NO_CSRF' => '1']);
check($plans->find($hid)['nom'] !== 'Sans CSRF', 'modification sans jeton CSRF refusée');
// Archivé non assignable via HTTP
$plans->toggleActive($hid, false, $superId);
$nbAvant = $compte('SELECT COUNT(*) FROM etablissements');
$r = http($nomCopie, $S, 'POST', "/ecole_app/platform/etablissements/$autre/plan", ['plan_id' => (string)$hid]);
check((int)$etabs->find($autre)['plan_id'] !== $hid, 'HTTP : plan archivé non assignable à un établissement');
$r = http($nomCopie, $S, 'POST', '/ecole_app/platform/etablissements', ['slug' => 'ecole-http-arch', 'nom' => 'HTTP Arch', 'nom_court' => 'HA', 'type' => 'lycee', 'plan_id' => (string)$hid]);
check($compte('SELECT COUNT(*) FROM etablissements') === $nbAvant, 'HTTP : création d\'établissement avec un plan archivé refusée');
$r = http($nomCopie, operateur($adminId, 'admin'), 'GET', '/ecole_app/platform/etablissements/create');
check($r['status'] === 200 && !str_contains((string)$r['text'], 'Plan HTTP v3'), 'formulaire d\'établissement : plans archivés non proposés');
// Page de détail : le plan actuel archivé reste visible comme plan existant mais n'est pas proposé à l'affectation.
$etabArch = $etabs->create(['slug' => 'ecole-plan-archive', 'nom' => 'École Plan Archivé', 'nom_court' => 'EPA', 'type' => 'lycee', 'plan_id' => $idem], $superId);
$plans->toggleActive($idem, false, $superId);
$r = http($nomCopie, $S, 'GET', "/ecole_app/platform/etablissements/$etabArch");
$t = (string)$r['text'];
check($r['status'] === 200 && str_contains($t, 'Plan actuel') && str_contains($t, 'Idempotent') && str_contains($t, 'archivé'), 'détail établissement : plan actuel archivé affiché comme plan existant');
check(str_contains($t, 'Idempotent (archivé — plan actuel, non assignable)') && substr_count($t, 'Idempotent') === 3, 'détail établissement : plan archivé affiché comme plan actuel (fiche, historique d\'abonnement), une seule option d\'assignation le concernant');
$corps = sys_get_temp_dir() . '/pplans_body_' . bin2hex(random_bytes(4)) . '.html';
lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/platform_request.php', $S, 'GET', "/ecole_app/platform/etablissements/$etabArch", '{}'],
    ['REQUEST_AS_DB' => $nomCopie, 'PLATFORM_REQ_AUDIT_DIR' => $dossierSecours, 'REQUEST_AS_BODY_OUT' => $corps]);
$html = (string)@file_get_contents($corps); @unlink($corps);
check(preg_match('/<option value="" disabled selected>Idempotent \(archivé/u', $html) === 1 && !str_contains($html, '<option value="' . $idem . '"'), 'détail établissement : plan archivé = option désactivée et sélectionnée, jamais une option assignable');
check((int)$etabs->find($etabArch)['plan_id'] === $idem, 'le rattachement existant est conservé');
$r = http($nomCopie, operateur($adminId, 'admin'), 'GET', "/ecole_app/platform/etablissements/$etabId");
check(str_contains((string)$r['text'], 'Plan actuel') && !str_contains((string)$r['text'], 'choisir un plan actif'), 'détail établissement : plan actif, pas de sélecteur d\'attente');
// Archivage via HTTP d\'un plan déjà archivé : message sans modification
$nA = count($audits('plan.toggled'));
$r = http($nomCopie, $S, 'POST', "$P/$idem/toggle", ['active' => '0']);
check($r['status'] === 302 && count($audits('plan.toggled')) === $nA, 'HTTP : archiver un plan déjà archivé n\'écrit rien');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('8. Journal d\'audit global');
check(count($audits('plan.created')) >= 3, 'plan.created journalisé');
$pc = $audits('plan.created')[0];
$pcv = json_decode($pc['nouvelle_valeur'], true);
check(isset($pcv['devise'], $pcv['duree_essai_jours'], $pcv['jours_grace'], $pcv['ordre'], $pcv['visible_catalogue'], $pcv['modules_inclus']), 'plan.created : nouveaux champs présents dans la nouvelle valeur');
$http = $pdo->query("SELECT * FROM platform_audit_logs WHERE action = 'plan.created' AND entity_id = $hid")->fetch(PDO::FETCH_ASSOC);
check($http && $http['actor_email'] === 'super@exemple.test' && $http['operator_id'] !== null, 'création HTTP : opérateur identifié dans l\'audit');
$bruit = (string)$pdo->query("SELECT GROUP_CONCAT(COALESCE(ancienne_valeur,''), COALESCE(nouvelle_valeur,'')) FROM platform_audit_logs WHERE action LIKE 'plan.%'")->fetchColumn();
check(!str_contains($bruit, 'test-csrf-token') && !str_contains($bruit, $mdp), 'aucun secret dans les valeurs auditées');
check(PlatformAuditService::echecsDeLaRequete() === 0, 'aucune écriture d\'audit en échec');

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
