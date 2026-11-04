<?php
/**
 * Tests — abonnements SaaS (étape 4 du Super-Admin SaaS, migration T067).
 *
 * 1. Règles pures : fuseau Africa/Niamey, fins de mois, périodes, bascule à minuit local, évaluation (essai / actif / grâce / expiré / annulé)
 * 2. Migration T067 : additive, idempotente, reprise de l'existant SANS modifier etablissements, unicité du courant
 * 3. Essai : durée du plan, un seul essai, prolongation exceptionnelle auditée
 * 4. Souscription, renouvellement (continuité, conditions figées), changement de plan immédiat, annulation
 * 5. Devise : DZD refusé pour toute souscription payante, aucune conversion
 * 6. Suspension automatique vs manuelle (origine, priorité, levée, motifs préservés)
 * 7. Script d'échéances : transitions, idempotence, simulation, établissement déjà suspendu par un opérateur
 * 8. Atomicité : échec de l'audit = opération annulée ; transaction englobante
 * 9. Accès (garde de session) et câblage des services existants (création d'établissement, assignation)
 * 10. Parcours HTTP : écriture réservée au super_admin
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/PlatformSubscriptionsTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
unset($_ENV['PLATFORM_TIMEZONE']);
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
use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformEtablissementService;
use Core\Platform\PlatformOperatorService;
use Core\Platform\PlatformPlanService;
use Core\Platform\PlatformSubscriptionService;
use Core\Platform\SubscriptionException;
use Core\Platform\SubscriptionRules;
use Core\Tenant\SessionTenantGuard;

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
function http(string $nomCopie, string $operateur, string $methode, string $uri, array $post = []): array
{
    global $dossierSecours;
    [$code, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/platform_request.php', $operateur, $methode, $uri, json_encode($post)],
        ['REQUEST_AS_DB' => $nomCopie, 'PLATFORM_REQ_AUDIT_DIR' => $dossierSecours]);
    return preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0, 'brut' => $sortie, 'code' => $code];
}
function operateur(int $id, string $niveau): string { return json_encode(['id' => $id, 'niveau' => $niveau, 'email' => "op$id@exemple.test", 'nom' => "Op $id"]); }
/** Message de la SubscriptionException levée par $f, ou null si rien n'est levé. */
function refus(callable $f): ?string
{
    try { $f(); return null; } catch (SubscriptionException $e) { return $e->getMessage(); }
}
function instant(string $ymdHis, string $tz = 'Africa/Niamey'): DateTimeImmutable { return new DateTimeImmutable($ymdHis, new DateTimeZone($tz)); }

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('1. Règles pures');
check(SubscriptionRules::fuseau()->getName() === 'Africa/Niamey', 'fuseau par défaut = Africa/Niamey');
$_ENV['PLATFORM_TIMEZONE'] = 'Europe/Paris';
check(SubscriptionRules::fuseau()->getName() === 'Europe/Paris', 'fuseau lu dans la configuration');
$_ENV['PLATFORM_TIMEZONE'] = 'Pas/UnFuseau';
check(SubscriptionRules::fuseau()->getName() === 'Africa/Niamey', 'fuseau invalide : repli sur Africa/Niamey');
unset($_ENV['PLATFORM_TIMEZONE']);
$d = fn(string $s) => SubscriptionRules::date($s);
check(SubscriptionRules::ajouterMois($d('2026-01-31'), 1)->format('Y-m-d') === '2026-02-28', '31 janvier + 1 mois = 28 février');
check(SubscriptionRules::ajouterMois($d('2028-01-31'), 1)->format('Y-m-d') === '2028-02-29', '31 janvier 2028 + 1 mois = 29 février (bissextile)');
check(SubscriptionRules::ajouterMois($d('2026-03-31'), 1)->format('Y-m-d') === '2026-04-30', '31 mars + 1 mois = 30 avril');
check(SubscriptionRules::ajouterMois($d('2026-12-15'), 1)->format('Y-m-d') === '2027-01-15', 'passage d\'année');
check(SubscriptionRules::finDePeriode($d('2026-03-10'), 'monthly')->format('Y-m-d') === '2026-04-09', 'mensuel : 10 mars -> 9 avril inclus');
check(SubscriptionRules::finDePeriode($d('2026-01-01'), 'monthly')->format('Y-m-d') === '2026-01-31', 'mensuel : 1er janvier -> 31 janvier');
check(SubscriptionRules::finDePeriode($d('2026-03-10'), 'yearly')->format('Y-m-d') === '2027-03-09', 'annuel : 10 mars 2026 -> 9 mars 2027');
check(SubscriptionRules::finDePeriode($d('2028-02-29'), 'yearly')->format('Y-m-d') === '2029-02-27', 'annuel depuis le 29 février : fin le 27 février 2029 (clamp)');
check(SubscriptionRules::finDEssai($d('2026-03-10'), 14)->format('Y-m-d') === '2026-03-23', 'essai de 14 jours : le jour de départ compte (10 -> 23 mars)');
$aucunCycle = false; try { SubscriptionRules::finDePeriode($d('2026-03-10'), 'weekly'); } catch (\InvalidArgumentException) { $aucunCycle = true; }
check($aucunCycle, 'cycle inconnu refusé');
// Bascule à minuit LOCAL (Niamey = UTC+1) : 30 nov 23:30 UTC = 1er déc 00:30 à Niamey.
check(SubscriptionRules::aujourdhui(instant('2026-11-30 23:30:00', 'UTC'))->format('Y-m-d') === '2026-12-01', '23h30 UTC = lendemain à Niamey (UTC+1)');
check(SubscriptionRules::aujourdhui(instant('2026-11-30 22:59:59', 'UTC'))->format('Y-m-d') === '2026-11-30', '22h59 UTC = même jour à Niamey');
$base = ['statut' => 'active', 'cycle' => 'monthly', 'date_fin' => '2026-04-09', 'jours_grace_appliques' => 5];
$ev = fn(array $s, string $j) => SubscriptionRules::evaluer($s + $base, $d($j));
check($ev([], '2026-04-09')['etat'] === 'active' && $ev([], '2026-04-09')['acces'], 'le jour de date_fin (inclus) : encore actif');
$e = $ev([], '2026-04-10');
check($e['etat'] === 'grace' && $e['acces'] && $e['en_grace'] && $e['acces_jusqua'] === '2026-04-14', 'lendemain de date_fin : grâce, accès maintenu jusqu\'au 14');
check($ev([], '2026-04-14')['etat'] === 'grace', 'dernier jour de grâce : accès');
$e = $ev([], '2026-04-15');
check($e['etat'] === 'expired' && !$e['acces'], 'lendemain de la grâce : expiré, accès coupé');
check($ev(['jours_grace_appliques' => 0], '2026-04-10')['etat'] === 'expired', 'grâce 0 : expiré dès le lendemain de date_fin');
check($ev(['date_fin' => null], '2030-01-01')['etat'] === 'active' && $ev(['date_fin' => null], '2030-01-01')['sans_echeance'], 'sans échéance : jamais expiré');
$essai = ['statut' => 'trial', 'cycle' => 'trial', 'jours_grace_appliques' => 9];
check($ev($essai, '2026-04-09')['etat'] === 'trial' && $ev($essai, '2026-04-10')['etat'] === 'expired', 'essai : pas de grâce, expiré le lendemain de la fin');
check(!$ev(['statut' => 'cancelled'], '2026-04-10')['acces'] && $ev(['statut' => 'cancelled'], '2026-04-09')['acces'] && $ev(['statut' => 'cancelled'], '2026-04-09')['etat'] === 'cancelled', 'annulé : accès jusqu\'à date_fin incluse, statut conservé');
check(!$ev(['statut' => 'cancelled', 'date_fin' => null], '2026-01-01')['acces'], 'annulé sans date : aucun accès');
check(!$ev(['statut' => 'expired'], '2026-01-01')['acces'] && !$ev(['statut' => 'superseded'], '2026-01-01')['acces'], 'expiré / remplacé : jamais d\'accès');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$dossierSecours = sys_get_temp_dir() . '/psubs_' . bin2hex(random_bytes(4));
PlatformAuditService::definirDossierSecours($dossierSecours);
$ops = new PlatformOperatorService($pdo);
$plans = new PlatformPlanService($pdo);
$mdp = 'Zq7!pLm2#vXk9@Rt';
$superId = $ops->creer('super@exemple.test', 'Super Opérateur', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$adminId = $ops->creer('admin@exemple.test', 'Admin Opérateur', 'admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$audits = static fn(string $action) => $pdo->query("SELECT * FROM platform_audit_logs WHERE action = " . $pdo->quote($action) . " ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$maintenant = instant('2026-03-10 12:00:00');
$horloge = static function () use (&$maintenant): DateTimeImmutable { return $maintenant; };
$sub = new PlatformSubscriptionService($pdo, $horloge);
$etabs = new PlatformEtablissementService($pdo, $horloge);
$planA = $plans->create(['code' => 'xof-a', 'nom' => 'Plan A', 'prix_mensuel' => '5000', 'prix_annuel' => '50000', 'duree_essai_jours' => 14, 'jours_grace' => 5, 'max_users' => 10, 'max_eleves' => 100, 'storage_quota_mb' => 500], $superId);
$planB = $plans->create(['code' => 'xof-b', 'nom' => 'Plan B', 'prix_mensuel' => '8000', 'prix_annuel' => '80000', 'duree_essai_jours' => 0, 'jours_grace' => 3, 'max_users' => 20, 'max_eleves' => 200, 'storage_quota_mb' => 900], $superId);
$planDzd = (int)$pdo->query("SELECT id FROM platform_plans WHERE code = 'starter'")->fetchColumn();
$planDzdEssai = $plans->create(['code' => 'dzd-essai', 'nom' => 'DZD essai', 'devise' => 'DZD', 'prix_mensuel' => '2500', 'prix_annuel' => '25000', 'duree_essai_jours' => 10], $superId);
$planPro = (int)$pdo->query("SELECT id FROM platform_plans WHERE code = 'pro'")->fetchColumn();
$n = 0;
/** Insère un établissement de test directement (horloge maîtrisée). */
$etab = static function (string $statut = 'trial', array $extra = []) use ($pdo, &$n): int {
    $n++;
    $c = ['slug' => "t-$n-" . bin2hex(random_bytes(2)), 'nom' => "Ecole $n", 'nom_court' => "E$n", 'type' => 'lycee', 'statut' => $statut] + $extra;
    $pdo->prepare('INSERT INTO etablissements (' . implode(',', array_keys($c)) . ') VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')')->execute(array_values($c));
    return (int)$pdo->lastInsertId();
};
$e = static fn(int $id) => $pdo->query("SELECT * FROM etablissements WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
$s = static fn(int $id) => $pdo->query("SELECT * FROM platform_subscriptions WHERE etablissement_id = $id ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$courant = static fn(int $id) => $pdo->query("SELECT * FROM platform_subscriptions WHERE etablissement_id = $id AND statut <> 'superseded' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('2. Migration T067');
$migration = require ROOT_PATH . '/database/migrations/T067_abonnements.php';
$colsE = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etablissements'")->fetchAll(PDO::FETCH_COLUMN);
check(in_array('suspension_origine', $colsE, true), 'colonne etablissements.suspension_origine présente');
check((int)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_subscriptions'")->fetchColumn() === 1, 'table platform_subscriptions présente');
check(PlatformSubscriptionService::disponible($pdo), 'service disponible après T067');
$demo = $s(1);
check(count($demo) === 1 && $demo[0]['statut'] === 'active' && $demo[0]['cycle'] === 'legacy' && $demo[0]['date_fin'] === null && $demo[0]['prix_applique'] === null && (int)$demo[0]['plan_id'] === $planPro,
    'jeu de référence : l\'établissement actif avec plan est repris SANS échéance (cycle legacy, prix inconnu)');
check($demo[0]['devise'] === 'DZD', 'la devise du plan est recopiée telle quelle (DZD), sans conversion');
// Reprise sur des cas variés, puis vérification que etablissements n'est PAS modifié.
$idTrial  = $etab('trial', ['plan_id' => $planA, 'trial_ends_at' => '2026-03-20']);
$idTrialSans = $etab('trial', ['plan_id' => $planA]);
$idActEch = $etab('active', ['plan_id' => $planA, 'plan_expires_at' => '2026-06-30', 'activated_at' => '2026-01-05 08:00:00']);
$idActSans = $etab('active', ['plan_id' => $planA]);
$idSusp   = $etab('suspended', ['plan_id' => $planA, 'suspended_at' => '2026-02-01 10:00:00', 'suspension_reason' => 'Impayé : décision du directeur']);
$idCanc   = $etab('cancelled', ['plan_id' => $planA]);
$idSuppr  = $etab('active', ['plan_id' => $planA, 'deleted_at' => '2026-02-02 00:00:00']);
$idSansPlan = $etab('trial', []);
$pdo->exec('DELETE FROM platform_subscriptions WHERE etablissement_id > 1');
$avantMd5 = md5(json_encode($pdo->query('SELECT * FROM etablissements ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)));
$migration['run']($pdo);
$apresMd5 = md5(json_encode($pdo->query('SELECT * FROM etablissements ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)));
check($avantMd5 === $apresMd5, 'la reprise ne modifie AUCUNE ligne de etablissements (statuts, dates, motifs inchangés)');
$r = $s($idTrial)[0] ?? [];
check(($r['statut'] ?? '') === 'trial' && $r['cycle'] === 'trial' && $r['date_fin'] === '2026-03-20' && $r['trial_ends_at'] === '2026-03-20', 'essai existant : date actuelle conservée');
$r = $s($idTrialSans)[0] ?? [];
check(($r['statut'] ?? '') === 'trial' && $r['date_fin'] === null, 'essai sans date : reste sans échéance');
$r = $s($idActEch)[0] ?? [];
check(($r['statut'] ?? '') === 'active' && $r['date_fin'] === '2026-06-30' && (int)$r['jours_grace_appliques'] === 0 && $r['date_debut'] === '2026-01-05', 'actif avec échéance : date conservée, grâce 0 (comportement actuel)');
check(array_key_exists('date_fin', $s($idActSans)[0] ?? []) && $s($idActSans)[0]['date_fin'] === null, 'actif sans échéance : reste sans échéance');
check(($s($idSusp)[0]['statut'] ?? '') === 'active' && $e($idSusp)['suspension_reason'] === 'Impayé : décision du directeur' && $e($idSusp)['suspension_origine'] === null, 'établissement suspendu : motif intact, origine NULL (traité comme manuel)');
check(($s($idCanc)[0]['statut'] ?? '') === 'cancelled', 'établissement annulé -> abonnement annulé');
check($s($idSuppr) === [] && $s($idSansPlan) === [], 'établissement supprimé ou sans plan : aucun abonnement créé');
$migration['run']($pdo);
check(count($s($idTrial)) === 1 && md5(json_encode($pdo->query('SELECT * FROM etablissements ORDER BY id')->fetchAll(PDO::FETCH_ASSOC))) === $apresMd5, 'T067 rejouée : idempotente (aucun doublon, etablissements inchangé)');
$doublon = refus(function () use ($pdo, $idTrial) { throw new SubscriptionException('x'); });
$sqlDoublon = null;
try {
    $pdo->exec("INSERT INTO platform_subscriptions (etablissement_id, plan_id, statut, cycle, date_debut, devise) VALUES ($idTrial, $planA, 'active', 'monthly', '2026-03-01', 'XOF')");
} catch (\PDOException $ex) { $sqlDoublon = $ex->getCode(); }
check($sqlDoublon === '23000', 'deux abonnements courants pour un même établissement : refusé par la base (index unique)');
$pdo->exec("INSERT INTO platform_subscriptions (etablissement_id, plan_id, statut, cycle, date_debut, devise) VALUES ($idTrial, $planA, 'expired', 'monthly', '2026-01-01', 'XOF')");
$pdo->exec("INSERT INTO platform_subscriptions (etablissement_id, plan_id, statut, cycle, date_debut, devise) VALUES ($idTrial, $planA, 'expired', 'monthly', '2026-02-01', 'XOF')");
check(count($s($idTrial)) === 3, 'plusieurs périodes expirées / remplacées coexistent (historique)');
$pdo->exec("DELETE FROM platform_subscriptions WHERE etablissement_id = $idTrial AND statut = 'expired'");

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('3. Essai');
$a = $etab('trial');
$id = $sub->assigner($a, $planA, $superId);
$r = $courant($a);
check((int)$r['id'] === $id && $r['statut'] === 'trial' && $r['cycle'] === 'trial' && $r['date_debut'] === '2026-03-10' && $r['date_fin'] === '2026-03-23', 'assignation à un établissement en essai : essai de 14 jours (duree_essai_jours du plan)');
check((int)$r['duree_essai_appliquee'] === 14 && (int)$r['jours_grace_appliques'] === 5 && $r['devise'] === 'XOF' && $r['prix_applique'] === '0.00', 'conditions figées dans la ligne (essai, grâce, devise)');
$ea = $e($a);
check((int)$ea['plan_id'] === $planA && $ea['trial_ends_at'] === '2026-03-23' && $ea['plan_expires_at'] === null && $ea['statut'] === 'trial', 'miroir sur etablissements : plan, fin d\'essai, statut inchangé');
check((int)$ea['max_users'] === 10 && (int)$ea['max_eleves'] === 100 && (int)$ea['storage_quota_mb'] === 500, 'quotas du plan copiés sur l\'établissement');
check(count($audits('subscription.created')) === 1, 'audit subscription.created écrit');
$m = refus(fn() => $sub->demarrerEssai($a, $planA, $superId));
check($m !== null && str_contains($m, 'déjà'), 'démarrer un second essai : refusé', (string)$m);
// Plan sans essai : abonnement mensuel actif d'emblée.
$b = $etab('trial');
$sub->assigner($b, $planB, $superId);
$r = $courant($b);
check($r['statut'] === 'active' && $r['cycle'] === 'monthly' && $r['date_fin'] === '2026-04-09' && $r['prix_applique'] === '8000.00', 'plan sans essai : abonnement mensuel actif d\'emblée au prix du plan');
// Aucun essai consommé mais établissement déjà actif et sans abonnement : sans échéance, aucune date inventée.
$c = $etab('active');
$sub->assigner($c, $planA, $superId);
$r = $courant($c);
check($r['statut'] === 'active' && $r['cycle'] === 'legacy' && $r['date_fin'] === null && $e($c)['plan_expires_at'] === null, 'établissement actif sans abonnement : abonnement SANS échéance');
// Essai plus consommé : une conversion puis plus jamais d'essai.
$sub->souscrire($a, $planA, 'monthly', $superId);
$m = refus(fn() => $sub->demarrerEssai($a, $planA, $superId));
check($m !== null, 'un essai déjà consommé ne redémarre pas, même après souscription');

// Prolongation exceptionnelle.
$p = $etab('trial');
$sub->assigner($p, $planA, $superId);
check(refus(fn() => $sub->prolongerEssai($p, 7, '', $superId)) !== null, 'prolongation sans motif refusée');
check(refus(fn() => $sub->prolongerEssai($p, 0, 'geste commercial', $superId)) !== null && refus(fn() => $sub->prolongerEssai($p, 91, 'geste commercial', $superId)) !== null, 'prolongation hors 1..90 jours refusée');
$sub->prolongerEssai($p, 7, 'geste commercial', $superId);
check($courant($p)['date_fin'] === '2026-03-30' && $courant($p)['trial_ends_at'] === '2026-03-30', 'prolongation de 7 jours à partir de la fin d\'essai (23 -> 30 mars)');
$au = $audits('subscription.trial_extended');
$aj = json_decode(end($au)['nouvelle_valeur'], true);
check($aj['jours'] === 7 && $aj['motif'] === 'geste commercial', 'prolongation auditée avec durée et motif');
check(refus(fn() => $sub->prolongerEssai($b, 7, 'motif valable', $superId)) !== null, 'on ne prolonge pas un abonnement payant');
// Prolongation d'un essai expiré, depuis aujourd'hui.
$maintenant = instant('2026-04-20 09:00:00');
$sub->appliquerEcheances();
check($courant($p)['statut'] === 'expired' && $e($p)['statut'] === 'suspended', 'essai échu : expiré et établissement suspendu automatiquement');
$sub->prolongerEssai($p, 10, 'dernier délai accordé', $superId);
$ep = $e($p);
check($courant($p)['statut'] === 'trial' && $courant($p)['date_fin'] === '2026-04-30' && $ep['statut'] === 'trial' && $ep['suspension_origine'] === null && $ep['suspension_reason'] === null, 'prolongation d\'un essai expiré : compté depuis aujourd\'hui, suspension automatique levée');
$maintenant = instant('2026-03-10 12:00:00');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('4. Souscription, renouvellement, changement de plan, annulation');
$x = $etab('active');
$sub->souscrire($x, $planA, 'monthly', $superId);
$r = $courant($x);
check($r['statut'] === 'active' && $r['cycle'] === 'monthly' && $r['date_debut'] === '2026-03-10' && $r['date_fin'] === '2026-04-09' && $r['grace_ends_at'] === '2026-04-14' && $r['prix_applique'] === '5000.00', 'souscription mensuelle : période, grâce et prix figés');
check($e($x)['plan_expires_at'] === '2026-04-14', 'miroir : plan_expires_at = dernier jour d\'accès (fin + grâce)');
check(refus(fn() => $sub->souscrire($x, $planA, 'monthly', $superId)) !== null, 'souscrire alors qu\'un abonnement payant est en cours : refusé');
check(refus(fn() => $sub->souscrire($x, $planA, 'weekly', $superId)) !== null, 'cycle invalide refusé');
// Gel des conditions : modifier le plan ne change pas l'abonnement.
$plans->update($planA, ['prix_mensuel' => '6000', 'confirmer_conditions' => '1'], $superId);
check($courant($x)['prix_applique'] === '5000.00', 'modifier le prix du plan ne touche pas l\'abonnement en cours (conditions figées)');
// Renouvellement : continuité, nouvelles conditions du plan.
$maintenant = instant('2026-03-25 10:00:00');
$sub->renouveler($x, $superId);
$h = $s($x);
check(count($h) === 2 && $h[0]['statut'] === 'superseded' && $h[1]['statut'] === 'active', 'renouvellement : ancienne période remplacée, nouvelle active');
check($h[1]['date_debut'] === '2026-04-10' && $h[1]['date_fin'] === '2026-05-09' && (int)$h[1]['precedent_id'] === (int)$h[0]['id'], 'renouvellement anticipé : la période commence le lendemain de date_fin (aucun trou, aucun chevauchement)');
check($h[1]['prix_applique'] === '6000.00', 'renouvellement : conditions ACTUELLES du plan par défaut');
// Conserver les conditions.
$plans->update($planA, ['prix_mensuel' => '7000', 'confirmer_conditions' => '1'], $superId);
$sub->renouveler($x, $superId, null, true);
check($courant($x)['prix_applique'] === '6000.00' && $courant($x)['date_debut'] === '2026-05-10', 'renouvellement avec conditions conservées : prix de la période précédente');
check(refus(fn() => $sub->renouveler($x, $superId, 'yearly', true)) !== null, 'conserver les conditions avec un autre cycle : refusé');
// Renouvellement pendant la grâce : continuité préservée.
$g = $etab('active');
$maintenant = instant('2026-03-10 12:00:00');
$sub->souscrire($g, $planB, 'monthly', $superId);   // fin 9 avril, grâce 3 j
$maintenant = instant('2026-04-11 12:00:00');
check($sub->etat($courant($g))['etat'] === 'grace', 'au 11 avril : en grâce');
$sub->renouveler($g, $superId);
check($courant($g)['date_debut'] === '2026-04-10' && $courant($g)['date_fin'] === '2026-05-09', 'renouvellement en grâce : continuité (début le 10 avril, pas le jour du paiement)');
// Après la grâce : renouveler est refusé, souscrire réactive.
$maintenant = instant('2026-06-01 12:00:00');
$m = refus(fn() => $sub->renouveler($g, $superId));
check($m !== null && str_contains($m, 'expiré'), 'renouveler un abonnement échu : refusé, il faut souscrire', (string)$m);
$sub->souscrire($g, $planB, 'yearly', $superId);
check($courant($g)['date_debut'] === '2026-06-01' && $courant($g)['date_fin'] === '2027-05-31' && $courant($g)['prix_applique'] === '80000.00', 'réactivation : nouvelle période depuis aujourd\'hui, tarif annuel');
$maintenant = instant('2026-03-10 12:00:00');
// Cycle inconnu (legacy) : renouveler exige un cycle.
$m = refus(fn() => $sub->renouveler($c, $superId));
check($m !== null && str_contains($m, 'cycle'), 'abonnement repris de l\'existant : le cycle doit être précisé', (string)$m);
$sub->renouveler($c, $superId, 'monthly');
check($courant($c)['date_debut'] === '2026-03-10' && $courant($c)['date_fin'] === '2026-04-09', 'sans échéance : la période démarre aujourd\'hui');
// Abonnement repris de l'existant (prix inconnu) : conserver les conditions est refusé tant qu'aucune période n'a figé un prix.
$legacyConserve = $etab('active');
$sub->assigner($legacyConserve, $planA, $superId);
$m = refus(fn() => $sub->renouveler($legacyConserve, $superId, 'monthly', true));
check($m !== null && str_contains($m, 'inconnues'), 'conserver des conditions inconnues (abonnement legacy) : refusé', (string)$m);
// Plan archivé.
$ar = $plans->create(['code' => 'a-archiver', 'nom' => 'Archivé', 'prix_mensuel' => '1000', 'prix_annuel' => '10000'], $superId);
$z = $etab('active');
$sub->souscrire($z, $ar, 'monthly', $superId);
$plans->toggleActive($ar, false, $superId);
$m = refus(fn() => $sub->renouveler($z, $superId));
check($m !== null && str_contains($m, 'archivé'), 'renouveler sur un plan archivé : refusé sans conservation des conditions', (string)$m);
$sub->renouveler($z, $superId, null, true);
check($courant($z)['date_debut'] === '2026-04-10' && $courant($z)['prix_applique'] === '1000.00', 'plan archivé : renouvellement possible en conservant les conditions');
check(refus(fn() => $sub->changerPlan($z, $ar, $superId)) !== null, 'un plan archivé n\'est pas assignable');

// Changement de plan immédiat, sans prorata.
$y = $etab('active');
$sub->souscrire($y, $planA, 'monthly', $superId);
$maintenant = instant('2026-03-20 10:00:00');
$avantFin = $courant($y)['date_fin'];
$sub->changerPlan($y, $planB, $superId);
$h = $s($y);
$r = $courant($y);
check($h[0]['statut'] === 'superseded' && (int)$r['plan_id'] === $planB && $r['date_debut'] === '2026-03-20' && $r['date_fin'] === $avantFin, 'changement de plan : immédiat, même échéance (pas de nouvelle période)');
check($r['prix_applique'] === '8000.00' && $r['cycle'] === 'monthly' && (int)$r['jours_grace_appliques'] === 3 && (int)$e($y)['max_users'] === 20, 'changement de plan : prix, grâce et quotas du nouveau plan, aucun prorata');
check(refus(fn() => $sub->changerPlan($y, $planB, $superId)) !== null, 'changer vers le plan actuel : refusé');
$maintenant = instant('2026-03-10 12:00:00');

// Annulation.
$k = $etab('active');
$sub->souscrire($k, $planA, 'monthly', $superId);
check(refus(fn() => $sub->annuler($k, '', $superId)) !== null, 'annulation sans motif refusée');
$sub->annuler($k, 'Fermeture de l\'école', $superId);
$r = $courant($k);
check($r['statut'] === 'cancelled' && $r['cancel_reason'] === 'Fermeture de l\'école' && $r['date_fin'] === '2026-04-09' && $e($k)['statut'] === 'active', 'annulation : accès maintenu jusqu\'à date_fin, établissement toujours actif');
check(refus(fn() => $sub->renouveler($k, $superId)) !== null, 'on ne renouvelle pas un abonnement annulé');
$maintenant = instant('2026-04-10 00:30:00');
$sub->appliquerEcheances();
$ek = $e($k);
check($courant($k)['statut'] === 'cancelled' && $ek['statut'] === 'suspended' && $ek['suspension_origine'] === 'subscription', 'annulation échue : suspension automatique (le statut reste « cancelled »)');
$maintenant = instant('2026-03-10 12:00:00');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('5. Devise');
$q = $etab('active');
$m = refus(fn() => $sub->souscrire($q, $planDzd, 'monthly', $superId));
check($m !== null && str_contains($m, 'DZD') && str_contains($m, 'conversion'), 'souscription à un plan en DZD refusée, sans conversion', (string)$m);
check($s($q) === [], 'aucun abonnement créé après le refus');
check(refus(fn() => $sub->assigner($q, $planDzd, $superId)) !== null, 'assignation (établissement actif) à un plan DZD refusée');
check(refus(fn() => $sub->assigner($etab('trial'), $planDzd, $superId)) !== null, 'plan DZD sans essai (démarrage payant d\'emblée) refusé');
$tr = $etab('trial');
check(refus(fn() => $sub->assigner($tr, $planDzdEssai, $superId)) === null && $courant($tr)['cycle'] === 'trial' && $courant($tr)['devise'] === 'DZD', 'essai sur un plan DZD toléré (aucun paiement), devise figée telle quelle');
check(refus(fn() => $sub->souscrire($tr, $planDzdEssai, 'yearly', $superId)) !== null, 'conversion de cet essai vers un plan DZD refusée');
check((int)$pdo->query("SELECT COUNT(*) FROM platform_plans WHERE code = 'starter' AND devise = 'DZD' AND prix_mensuel = 2500.00")->fetchColumn() === 1, 'le plan DZD n\'est ni converti ni modifié');
$sub->souscrire($tr, $planA, 'monthly', $superId);
check($courant($tr)['devise'] === 'XOF', 'après correction (plan XOF) : souscription acceptée');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('6. Suspension automatique / manuelle');
// Automatique : origine + motif propres, levée au renouvellement.
$u = $etab('active');
$sub->souscrire($u, $planB, 'monthly', $superId);   // fin 9 avril, grâce 3 jours
$maintenant = instant('2026-04-13 00:10:00');
$sub->appliquerEcheances();
$eu = $e($u);
check($courant($u)['statut'] === 'expired' && $eu['statut'] === 'suspended' && $eu['suspension_origine'] === 'subscription' && $eu['suspension_reason'] === 'Abonnement expiré' && $eu['suspended_at'] !== null, 'expiration : suspension automatique, origine « subscription », motif système');
$sub->souscrire($u, $planB, 'monthly', $superId);
$eu = $e($u);
check($eu['statut'] === 'active' && $eu['suspension_origine'] === null && $eu['suspension_reason'] === null && $eu['suspended_at'] === null, 'nouvelle souscription : suspension AUTOMATIQUE levée (origine, motif et date effacés)');
// Manuelle : prioritaire, jamais levée par un abonnement.
$v = $etab('active');
$sub->souscrire($v, $planB, 'monthly', $superId);
$etabs->suspend($v, $superId, 'Litige avec la direction');
$ev2 = $e($v);
check($ev2['statut'] === 'suspended' && $ev2['suspension_origine'] === 'manual' && $ev2['suspension_reason'] === 'Litige avec la direction', 'suspension d\'opérateur : origine « manual » et motif de l\'opérateur');
$sub->renouveler($v, $superId);
$ev2 = $e($v);
check($ev2['statut'] === 'suspended' && $ev2['suspension_origine'] === 'manual' && $ev2['suspension_reason'] === 'Litige avec la direction', 'renouvellement : la suspension manuelle n\'est ni levée ni écrasée');
$maintenant = instant('2026-06-20 00:10:00');
$sub->appliquerEcheances();
$ev2 = $e($v);
check($courant($v)['statut'] === 'expired' && $ev2['suspension_origine'] === 'manual' && $ev2['suspension_reason'] === 'Litige avec la direction', 'expiration pendant une suspension manuelle : motif et origine de l\'opérateur conservés');
$aud = $audits('subscription.expired');
$dernier = json_decode(end($aud)['nouvelle_valeur'], true);
check($dernier['suspension'] === 'suspension_existante_conservee', 'l\'audit indique que la suspension existante a été conservée');
$sub->souscrire($v, $planB, 'monthly', $superId);
check($e($v)['statut'] === 'suspended' && $e($v)['suspension_origine'] === 'manual', 'réactivation de l\'abonnement : établissement TOUJOURS suspendu (décision d\'opérateur)');
// L'opérateur peut lever sa propre décision tant que l'abonnement a un accès.
$etabs->activate($v, $superId);
check($e($v)['statut'] === 'active' && $e($v)['suspension_origine'] === null, 'activation manuelle (abonnement valide) : origine remise à NULL');
// Suspension manuelle d'un établissement déjà suspendu automatiquement : la décision manuelle prend le dessus, l'ancien motif reste dans l'audit.
$w = $etab('active');
$maintenant = instant('2026-03-10 12:00:00');
$sub->souscrire($w, $planB, 'monthly', $superId);
$maintenant = instant('2026-05-01 00:10:00');
$sub->appliquerEcheances();
check($e($w)['suspension_origine'] === 'subscription', 'préparation : suspendu automatiquement');
$etabs->suspend($w, $superId, 'Décision de la direction');
$ew = $e($w);
check($ew['suspension_origine'] === 'manual' && $ew['suspension_reason'] === 'Décision de la direction', 'suspension manuelle sur suspension automatique : devient manuelle');
$sa = $audits('tenant.suspended');
$avantAudit = json_decode(end($sa)['ancienne_valeur'], true);
check(($avantAudit['suspension_reason'] ?? null) === 'Abonnement expiré' && ($avantAudit['suspension_origine'] ?? null) === 'subscription', 'l\'ancien motif automatique est conservé dans l\'audit (avant)');
$sub->souscrire($w, $planB, 'monthly', $superId);
check($e($w)['statut'] === 'suspended' && $e($w)['suspension_origine'] === 'manual', 'souscription ultérieure : n\'écrase pas la décision manuelle');
// Suspension HISTORIQUE (origine NULL, antérieure à T067) : jamais levée ni écrasée par un abonnement, quelle que soit l'opération.
$maintenant = instant('2026-03-10 12:00:00');
$avantHist = [$e($idSusp)['statut'], $e($idSusp)['suspension_origine'], $e($idSusp)['suspension_reason'], $e($idSusp)['suspended_at']];
check($avantHist[0] === 'suspended' && $avantHist[1] === null && $avantHist[2] === 'Impayé : décision du directeur', 'préparation : suspension historique (origine NULL)');
$sub->renouveler($idSusp, $superId, 'monthly');
$sub->changerPlan($idSusp, $planB, $superId);
$apresHist = [$e($idSusp)['statut'], $e($idSusp)['suspension_origine'], $e($idSusp)['suspension_reason'], $e($idSusp)['suspended_at']];
check($apresHist === $avantHist, 'suspension historique : renouvellement et changement de plan ne la lèvent pas et ne la modifient pas');
$maintenant = instant('2028-01-01 00:10:00');
$sub->appliquerEcheances();
$apresHist = [$e($idSusp)['statut'], $e($idSusp)['suspension_origine'], $e($idSusp)['suspension_reason'], $e($idSusp)['suspended_at']];
check($courant($idSusp)['statut'] === 'expired' && $apresHist === $avantHist, 'suspension historique : l\'expiration ne la réécrit pas (motif et origine conservés)');
$sub->souscrire($idSusp, $planB, 'yearly', $superId);
$apresHist = [$e($idSusp)['statut'], $e($idSusp)['suspension_origine'], $e($idSusp)['suspension_reason'], $e($idSusp)['suspended_at']];
check($courant($idSusp)['statut'] === 'active' && $apresHist === $avantHist, 'suspension historique : la réactivation de l\'abonnement ne la lève pas');
$maintenant = instant('2026-03-10 12:00:00');
// Activation manuelle refusée sans accès.
$ac = $etab('active');
$sub->souscrire($ac, $planB, 'monthly', $superId);
$maintenant = instant('2026-09-01 00:10:00');
$sub->appliquerEcheances();
$m = refus(fn() => $etabs->activate($ac, $superId));
check($m !== null && str_contains($m, 'Activation refusée') && $e($ac)['statut'] === 'suspended', 'activation manuelle d\'un établissement sans accès d\'abonnement : refusée', (string)$m);
check(refus(fn() => $etabs->restore($ac, $superId)) !== null, 'restauration refusée dans le même cas');
// Archivé / annulé : jamais touchés par l'automatisme.
$ar2 = $etab('active');
$maintenant = instant('2026-03-10 12:00:00');
$sub->souscrire($ar2, $planB, 'monthly', $superId);
$etabs->archive($ar2, $superId);
$maintenant = instant('2026-09-01 00:10:00');
$sub->appliquerEcheances();
check($e($ar2)['statut'] === 'archived' && $e($ar2)['suspension_origine'] === null, 'établissement archivé : statut intact malgré l\'expiration');
$maintenant = instant('2026-03-10 12:00:00');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('7. Script d\'échéances');
$t1 = $etab('active'); $sub->souscrire($t1, $planA, 'monthly', $superId);   // fin 9 avril, grâce 5 => 14 avril
$t2 = $etab('active'); $sub->souscrire($t2, $planA, 'yearly', $superId);
$maintenant = instant('2026-04-09 23:59:00');
$r1 = $sub->appliquerEcheances();
check(!in_array($t1, array_column($r1['transitions'], 'etablissement_id'), true), 'le jour de date_fin à 23h59 : aucune transition');
$maintenant = instant('2026-04-10 00:00:30');
$dry = $sub->appliquerEcheances(true);
$cible = array_values(array_filter($dry['transitions'], fn($t) => $t['etablissement_id'] === $t1));
check($dry['dry_run'] && count($cible) === 1 && $cible[0]['vers'] === 'grace' && $courant($t1)['statut'] === 'active', 'simulation : transition annoncée, rien écrit');
$res = $sub->appliquerEcheances();
check($courant($t1)['statut'] === 'grace' && $e($t1)['statut'] === 'active' && $res['erreurs'] === 0, 'minuit local passé : abonnement en grâce, établissement toujours actif');
check(count(array_filter($audits('subscription.grace_started'), fn($a) => (int)$a['etablissement_id'] === $t1)) === 1 && (fn(array $l) => end($l))($audits("subscription.grace_started"))["actor_type"] === 'system', 'passage en grâce audité, acteur système');
$res = $sub->appliquerEcheances();
check(array_filter($res['transitions'], fn($t) => $t['etablissement_id'] === $t1) === [], 'seconde exécution : aucune nouvelle transition (idempotent)');
$maintenant = instant('2026-04-15 00:00:10');
$sub->appliquerEcheances(); $sub->appliquerEcheances();
check($courant($t1)['statut'] === 'expired' && $e($t1)['statut'] === 'suspended', 'après la grâce : expiré et suspendu automatiquement');
check(count(array_filter($audits('subscription.expired'), fn($a) => (int)$a['etablissement_id'] === $t1)) === 1, 'expiration auditée une seule fois malgré deux exécutions');
check($courant($t2)['statut'] === 'active' && $e($t2)['statut'] === 'active', 'un abonnement annuel non échu n\'est pas touché');
check($e(1)['statut'] === 'active' && $courant(1)['statut'] === 'active', 'l\'abonnement sans échéance n\'expire jamais');
$maintenant = instant('2026-03-10 12:00:00');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('8. Atomicité');
$au = $etab('active');
$sub->souscrire($au, $planA, 'monthly', $superId);
$avantLignes = count($s($au)); $avantEtab = md5(json_encode($e($au)));
$pdo->exec("CREATE TRIGGER t_sub_audit_ko BEFORE INSERT ON platform_audit_logs FOR EACH ROW BEGIN
    IF NEW.action LIKE 'subscription.%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'echec audit simule'; END IF; END");
try {
    $ex = null;
    try { $sub->renouveler($au, $superId); } catch (\Throwable $t) { $ex = $t; }
    check($ex instanceof SubscriptionException && $ex->estAudit() && str_contains($ex->getMessage(), 'trace d\'audit'), 'audit impossible : renouvellement refusé avec un message clair', $ex ? $ex->getMessage() : 'aucune exception');
    check(count($s($au)) === $avantLignes && $courant($au)['statut'] === 'active' && md5(json_encode($e($au))) === $avantEtab, 'audit impossible : aucune ligne ajoutée, ancienne période intacte, établissement intact');
    $ex = null;
    try { $sub->annuler($au, 'Test atomicité', $superId); } catch (\Throwable $t) { $ex = $t; }
    check($ex instanceof SubscriptionException && $courant($au)['statut'] === 'active', 'audit impossible : annulation annulée');
    $pdo->beginTransaction();
    $ex = null;
    try { $sub->changerPlan($au, $planB, $superId); } catch (\Throwable $t) { $ex = $t; }
    check($ex !== null && $pdo->inTransaction() && (int)$courant($au)['plan_id'] === $planA, 'transaction englobante : seule l\'opération est annulée, la transaction de l\'appelant reste ouverte');
    $pdo->rollBack();
    $maintenant = instant('2026-04-20 00:10:00');
    $res = $sub->appliquerEcheances();
    check($res['erreurs'] >= 1 && $courant($au)['statut'] === 'active' && $e($au)['statut'] === 'active', 'échéance sans audit possible : transition annulée, erreur comptée, établissement non suspendu');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS t_sub_audit_ko');
    $maintenant = instant('2026-03-10 12:00:00');
}
$lignesAvantOk = count($s($au));
$idOk = $sub->renouveler($au, $superId);
check($idOk > 0 && count($s($au)) === $lignesAvantOk + 1 && (int)$courant($au)['id'] === $idOk, 'sans l\'échec simulé, la même opération réussit (nouvelle période créée)');
$ex = null;
$pdo->exec("CREATE TRIGGER t_sub_ins_ko BEFORE INSERT ON platform_subscriptions FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'secret_table_x'; END");
try { $sub->renouveler($au, $superId); } catch (\Throwable $t) { $ex = $t; } finally { $pdo->exec('DROP TRIGGER IF EXISTS t_sub_ins_ko'); }
check($ex instanceof SubscriptionException && !str_contains($ex->getMessage(), 'secret_table_x') && !str_contains($ex->getMessage(), 'SQLSTATE') && str_contains($ex->getMessage(), 'référence'), 'erreur SQL : message générique avec référence, jamais le détail technique');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('9. Accès (garde de session) et câblage');
$gx = $etab('active');
$sub->souscrire($gx, $planB, 'monthly', $superId);   // fin 9 avril, grâce 3 => 12 avril
$acces = fn(string $quand) => PlatformSubscriptionService::accesInterdit($pdo, $gx, instant($quand));
check(!$acces('2026-04-09 20:00:00') && !$acces('2026-04-12 20:00:00') && $acces('2026-04-13 00:30:00'), 'garde : accès jusqu\'au dernier jour de grâce, refusé à minuit local suivant');
check(!PlatformSubscriptionService::accesInterdit($pdo, 1) && !PlatformSubscriptionService::accesInterdit($pdo, 999999), 'garde : sans échéance ou sans abonnement, jamais interdit');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('RENAME TABLE platform_subscriptions TO platform_subscriptions_off');
$cacheOk = PlatformSubscriptionService::accesInterdit($pdo, $gx, instant('2027-01-01 00:00:00'));
$pdo->exec('RENAME TABLE platform_subscriptions_off TO platform_subscriptions');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
check($cacheOk === false, 'garde : table absente ou panne -> jamais de blocage sur une erreur technique');
// Câblage : création d'établissement avec un plan = essai du plan, dans une transaction (horloge réelle).
$aujourdhui = SubscriptionRules::aujourdhui($maintenant)->format('Y-m-d');
$cree = $etabs->create(['slug' => 'ecole-cablee', 'nom' => 'École câblée', 'nom_court' => 'EC', 'type' => 'lycee', 'plan_id' => $planA], $superId);
$rc = $courant($cree);
check($rc !== false && $rc['cycle'] === 'trial' && $rc['date_debut'] === $aujourdhui && $rc['date_fin'] === SubscriptionRules::finDEssai(SubscriptionRules::aujourdhui($maintenant), 14)->format('Y-m-d') && $e($cree)['trial_ends_at'] === $rc['date_fin'], 'create() avec plan : essai de duree_essai_jours (plus de 30 jours en dur)');
$nAvant = (int)$pdo->query('SELECT COUNT(*) FROM etablissements')->fetchColumn();
$ex = null;
try { $etabs->create(['slug' => 'ecole-dzd', 'nom' => 'École DZD', 'nom_court' => 'ED', 'type' => 'lycee', 'plan_id' => $planDzdEssai], $superId); } catch (\Throwable $t) { $ex = $t; }
check($ex === null && (int)$pdo->query('SELECT COUNT(*) FROM etablissements')->fetchColumn() === $nAvant + 1, 'create() avec un plan DZD à essai : essai accepté (aucun paiement)');
$nAvant = (int)$pdo->query('SELECT COUNT(*) FROM etablissements')->fetchColumn();
$ex = null;
try { $etabs->create(['slug' => 'ecole-ko', 'nom' => 'École KO', 'nom_court' => 'EK', 'type' => 'lycee', 'plan_id' => $planB], $superId); $pdo->exec("CREATE TRIGGER t_sub_ko2 BEFORE INSERT ON platform_audit_logs FOR EACH ROW BEGIN IF NEW.action = 'subscription.created' THEN SIGNAL SQLSTATE '45000'; END IF; END");
    $etabs->create(['slug' => 'ecole-ko2', 'nom' => 'École KO2', 'nom_court' => 'EK2', 'type' => 'lycee', 'plan_id' => $planB], $superId);
} catch (\Throwable $t) { $ex = $t; } finally { $pdo->exec('DROP TRIGGER IF EXISTS t_sub_ko2'); }
check($ex !== null && (int)$pdo->query('SELECT COUNT(*) FROM etablissements')->fetchColumn() === $nAvant + 1 && (int)$pdo->query("SELECT COUNT(*) FROM etablissements WHERE slug = 'ecole-ko2'")->fetchColumn() === 0, 'create() : échec de l\'abonnement = établissement non créé (une seule transaction)');
$sansPlan = $etabs->create(['slug' => 'ecole-sans', 'nom' => 'École sans plan', 'nom_court' => 'ES', 'type' => 'lycee'], $superId);
check($s($sansPlan) === [] && $e($sansPlan)['trial_ends_at'] !== null, 'create() sans plan : aucun abonnement, comportement historique');
$as = $etab('trial');
check($etabs->assignPlan($as, $planA, $superId) === true && $courant($as)['cycle'] === 'trial' && (int)$e($as)['plan_id'] === $planA, 'assignPlan() : démarre l\'essai et copie les quotas');
check($etabs->assignPlan($as, $planB, $superId) === true && (int)$courant($as)['plan_id'] === $planB && count($audits('tenant.plan_assigned')) >= 2, 'assignPlan() : changement immédiat, ancien audit tenant.plan_assigned conservé');
$m = refus(fn() => $etabs->assignPlan($as, $planDzd, $superId));
check($m === null && (int)$courant($as)['plan_id'] === $planDzd && $courant($as)['cycle'] === 'trial', 'assignPlan() vers un plan DZD pendant l\'essai : toléré (aucun paiement), l\'abonnement reste un essai');
check(refus(fn() => $sub->souscrire($as, $planDzd, 'monthly', $superId)) !== null && $courant($as)['cycle'] === 'trial', 'mais sa conversion en abonnement payant DZD est refusée');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('10. Parcours HTTP');
$P = '/ecole_app/platform/etablissements';
$hx = $etab('active');
$S = operateur($superId, 'super_admin'); $A = operateur($adminId, 'admin');
$r = http($nomCopie, $A, 'POST', "$P/$hx/abonnement/souscrire", ['plan_id' => (string)$planA, 'cycle' => 'monthly']);
check($s($hx) === [], 'admin : souscription refusée (super_admin uniquement)', json_encode($r));
$r = http($nomCopie, $S, 'POST', "$P/$hx/abonnement/souscrire", ['plan_id' => (string)$planA, 'cycle' => 'monthly']);
check($r['status'] === 302 && count($s($hx)) === 1 && $courant($hx)['statut'] === 'active', 'super_admin : souscription créée', json_encode($r));
$r = http($nomCopie, $S, 'POST', "$P/$hx/abonnement/souscrire", ['plan_id' => (string)$planA, 'cycle' => 'monthly']);
check($r['status'] === 302 && count($s($hx)) === 1, 'souscription en double : refusée sans écriture');
$r = http($nomCopie, $A, 'POST', "$P/$hx/abonnement/annuler", ['motif' => 'Essai']);
check($courant($hx)['statut'] === 'active', 'admin : annulation refusée');
$r = http($nomCopie, $S, 'POST', "$P/$hx/abonnement/annuler", ['motif' => 'Fin de contrat']);
check($courant($hx)['statut'] === 'cancelled', 'super_admin : annulation enregistrée');
$r = http($nomCopie, $A, 'GET', "$P/$hx");
check($r['status'] === 200 && str_contains((string)$r['text'], 'Abonnement') && str_contains((string)$r['text'], 'Annulé'), 'fiche établissement : section Abonnement visible pour un admin');
check(!str_contains((string)$r['text'], 'Souscrire / réactiver'), 'fiche établissement : aucun formulaire d\'écriture pour un admin');
$r = http($nomCopie, $S, 'GET', "$P/$hx");
check(str_contains((string)$r['text'], 'Souscrire / réactiver') && str_contains((string)$r['text'], "Prolonger l'essai"), 'fiche établissement : formulaires visibles pour un super_admin');



// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('11. Échéances : rattrapage des abonnements déjà expirés');
$maintenant = instant('2026-03-10 12:00:00');
$ligneSql = static fn(int $id, string $statut, string $debut, ?string $fin, string $cycle = 'monthly') =>
    $pdo->exec("INSERT INTO platform_subscriptions (etablissement_id, plan_id, statut, cycle, date_debut, date_fin, devise, jours_grace_appliques) VALUES ($id, $planA, '$statut', '$cycle', '$debut', " . ($fin ? "'$fin'" : 'NULL') . ", 'XOF', 0)");
// a) établissement resté actif alors que son abonnement est déjà « expired » -> suspendu automatiquement, une seule fois
$ra = $etab('active', ['plan_id' => $planA]);
$ligneSql($ra, 'expired', '2026-01-01', '2026-01-31');
// b) suspension MANUELLE + abonnement déjà expiré -> intacte
$rb = $etab('suspended', ['plan_id' => $planA, 'suspended_at' => '2026-02-01 10:00:00', 'suspension_reason' => 'Décision manuelle', 'suspension_origine' => 'manual']);
$ligneSql($rb, 'expired', '2026-01-01', '2026-01-31');
// c) suspension HISTORIQUE (origine NULL) + abonnement déjà expiré -> intacte
$rc = $etab('suspended', ['plan_id' => $planA, 'suspended_at' => '2026-02-01 10:00:00', 'suspension_reason' => 'Ancienne suspension']);
$ligneSql($rc, 'expired', '2026-01-01', '2026-01-31');
// d) ancienne période expirée MAIS réabonné depuis -> ne doit jamais être suspendu
$rd = $etab('active', ['plan_id' => $planA]);
$ligneSql($rd, 'expired', '2025-11-01', '2025-11-30');
$ligneSql($rd, 'active', '2026-03-01', '2026-03-31');
// e) abonnement expiré sur un établissement archivé -> intact
$re = $etab('archived', ['plan_id' => $planA]);
$ligneSql($re, 'expired', '2026-01-01', '2026-01-31');
$avantB = $e($rb); $avantC = $e($rc); $avantD = $e($rd); $avantE = $e($re);
$dry = $sub->appliquerEcheances(true);
$cibles = array_column($dry['transitions'], null, 'etablissement_id');
check(isset($cibles[$ra]) && $cibles[$ra]['rattrapage'] === true && $cibles[$ra]['suspension_automatique'] === true, 'simulation : le rattrapage de l\'établissement actif est annoncé');
check(!isset($cibles[$rb]) && !isset($cibles[$rc]) && !isset($cibles[$rd]) && !isset($cibles[$re]), 'simulation : rien d\'annoncé pour suspension manuelle, historique, réabonné ou archivé');
check($e($ra)['statut'] === 'active', 'simulation : aucune écriture');
$res = $sub->appliquerEcheances();
$era = $e($ra);
check($era['statut'] === 'suspended' && $era['suspension_origine'] === 'subscription' && $era['suspension_reason'] === 'Abonnement expiré' && $res['erreurs'] === 0, 'rattrapage : établissement suspendu automatiquement');
check($e($rb) === $avantB, 'suspension manuelle : ligne etablissements strictement identique (motif, origine, date)');
check($e($rc) === $avantC, 'suspension historique (origine NULL) : ligne etablissements strictement identique');
check($e($rd) === $avantD && $e($rd)['statut'] === 'active', 'établissement réabonné : jamais suspendu à cause d\'une ancienne période expirée');
check($e($re) === $avantE, 'établissement archivé : intact');
$nExp = count($audits('subscription.expired'));
$res2 = $sub->appliquerEcheances();
check(!isset(array_column($res2['transitions'], null, 'etablissement_id')[$ra]) && count($audits('subscription.expired')) === $nExp, 'seconde exécution : rien de plus (idempotent), pas de doublon d\'audit');
$ar = array_values(array_filter($audits('subscription.expired'), fn($a) => (int)$a['etablissement_id'] === $ra));
check(count($ar) === 1 && (json_decode($ar[0]['nouvelle_valeur'], true)['rattrapage'] ?? null) === true, 'rattrapage audité (une ligne, marqué « rattrapage »)');

// f) essai échu repris tel quel par T067 : dates conservées, puis expiré et suspendu par le script
$idEssaiEchu = $etab('trial', ['plan_id' => $planA, 'trial_ends_at' => '2026-03-01']);
$migration['run']($pdo);
$avantEssai = $e($idEssaiEchu);
check($s($idEssaiEchu)[0]['statut'] === 'trial' && $s($idEssaiEchu)[0]['date_fin'] === '2026-03-01', 'essai échu repris par T067 avec sa date actuelle');
check(PlatformSubscriptionService::accesInterdit($pdo, $idEssaiEchu, $maintenant), 'le garde refuse déjà l\'accès (calcul par les dates)');
$sub->appliquerEcheances();
$ee = $e($idEssaiEchu);
check($courant($idEssaiEchu)['statut'] === 'expired' && $ee['statut'] === 'suspended' && $ee['suspension_origine'] === 'subscription' && $ee['trial_ends_at'] === $avantEssai['trial_ends_at'], 'essai échu : expiré, suspendu automatiquement, trial_ends_at inchangé');
// g) essai échu sur établissement déjà suspendu manuellement : abonnement expiré, suspension de l'opérateur intacte
$idEssaiMan = $etab('suspended', ['plan_id' => $planA, 'trial_ends_at' => '2026-03-01', 'suspension_reason' => 'Motif opérateur', 'suspension_origine' => 'manual', 'suspended_at' => '2026-03-02 08:00:00']);
$migration['run']($pdo);
$avantMan = $e($idEssaiMan);
$sub->appliquerEcheances();
check($e($idEssaiMan) === $avantMan, 'essai échu + suspension manuelle : etablissements strictement identique');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('12. Contrôles d\'accès : tout ce qui modifie un abonnement est réservé au super_admin');
$maintenant = instant('2026-03-10 12:00:00');
$cible = $etab('trial');
$nEtabs = (int)$pdo->query('SELECT COUNT(*) FROM etablissements')->fetchColumn();
$r = http($nomCopie, $A, 'POST', "$P/$cible/plan", ['plan_id' => (string)$planA]);
check($s($cible) === [] && $e($cible)['plan_id'] === null, 'admin : assignation de plan refusée (aucun abonnement créé)', json_encode($r));
$r = http($nomCopie, 'guest', 'POST', "$P/$cible/plan", ['plan_id' => (string)$planA]);
check($s($cible) === [], 'visiteur non connecté : assignation refusée');
foreach (['souscrire' => ['plan_id' => (string)$planA, 'cycle' => 'monthly'], 'renouveler' => [], 'annuler' => ['motif' => 'Test accès'], 'prolonger-essai' => ['jours' => '5', 'motif' => 'Test accès']] as $action => $donnees) {
    http($nomCopie, 'guest', 'POST', "$P/$cible/abonnement/$action", $donnees);
}
check($s($cible) === [], 'visiteur non connecté : aucune opération d\'abonnement possible');
$r = http($nomCopie, $A, 'POST', "$P", ['slug' => 'ecole-admin-plan', 'nom' => 'Admin Plan', 'nom_court' => 'AP', 'type' => 'lycee', 'plan_id' => (string)$planA]);
check((int)$pdo->query('SELECT COUNT(*) FROM etablissements')->fetchColumn() === $nEtabs && (int)$pdo->query("SELECT COUNT(*) FROM etablissements WHERE slug = 'ecole-admin-plan'")->fetchColumn() === 0, 'admin : création d\'établissement AVEC plan refusée (rien créé)');
$r = http($nomCopie, $A, 'POST', "$P", ['slug' => 'ecole-admin-sans', 'nom' => 'Admin Sans', 'nom_court' => 'AS', 'type' => 'lycee']);
$idSans = (int)$pdo->query("SELECT id FROM etablissements WHERE slug = 'ecole-admin-sans'")->fetchColumn();
check($idSans > 0 && $s($idSans) === [], 'admin : création d\'établissement SANS plan toujours possible, sans abonnement');
$r = http($nomCopie, $A, 'GET', "$P/create");
check($r['status'] === 200 && !str_contains((string)$r['text'], 'Plan initial'), 'admin : le formulaire de création ne propose pas de plan');
$r = http($nomCopie, $S, 'GET', "$P/create");
check($r['status'] === 200 && str_contains((string)$r['text'], 'Plan initial'), 'super_admin : le formulaire de création propose le plan');
$r = http($nomCopie, $A, 'GET', "$P/$cible");
check($r['status'] === 200 && !str_contains((string)$r['text'], 'Assigner un plan') && !str_contains((string)$r['text'], 'Souscrire / réactiver'), 'admin : fiche sans formulaire d\'assignation ni d\'abonnement');
$r = http($nomCopie, $S, 'POST', "$P/$cible/plan", ['plan_id' => (string)$planA]);
check($courant($cible) && $courant($cible)['cycle'] === 'trial' && (int)$e($cible)['plan_id'] === $planA, 'super_admin : assignation de plan acceptée (essai démarré)');
$finAvant = $courant($cible)['date_fin'];
$r = http($nomCopie, $A, 'POST', "$P/$cible/abonnement/prolonger-essai", ['jours' => '5', 'motif' => 'Test accès']);
$r = http($nomCopie, $A, 'POST', "$P/$cible/abonnement/annuler", ['motif' => 'Test accès']);
check($courant($cible)['date_fin'] === $finAvant && $courant($cible)['statut'] === 'trial', 'admin : prolongation et annulation refusées (abonnement inchangé)');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('13. Bandeau de période de grâce');
$jourReel = SubscriptionRules::aujourdhui();
$grace = PlatformSubscriptionService::graceEnCours($pdo, $gx, instant('2026-04-11 12:00:00'));
check($grace !== null && $grace['date_fin'] === '2026-04-09' && $grace['acces_jusqua'] === '2026-04-12' && $grace['jours_restants'] === 1, 'graceEnCours : date de fin, dernier jour d\'accès et jours restants');
check(PlatformSubscriptionService::graceEnCours($pdo, $gx, instant('2026-04-09 12:00:00')) === null, 'pas de bandeau avant la fin de période');
check(PlatformSubscriptionService::graceEnCours($pdo, $gx, instant('2026-04-13 12:00:00')) === null, 'pas de bandeau une fois la grâce écoulée (accès coupé)');
check(PlatformSubscriptionService::graceEnCours($pdo, 999999) === null && PlatformSubscriptionService::graceEnCours($pdo, 1) === null, 'pas de bandeau sans abonnement ou sans échéance');
$adminEcole = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' AND etablissement_id = 1 AND actif = 1 ORDER BY id LIMIT 1")->fetchColumn();
$autreRole = $pdo->query("SELECT id, role FROM users WHERE etablissement_id = 1 AND actif = 1 AND role NOT IN ('admin','directeur','super_admin') ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$page = static function (string $role, int $uid) use ($nomCopie): array {
    $corps = sys_get_temp_dir() . '/psubs_body_' . bin2hex(random_bytes(4)) . '.html';
    [, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/request_as.php', $role, (string)$uid, 'GET', '/ecole_app/dashboard', '{}'], ['REQUEST_AS_DB' => $nomCopie, 'REQUEST_AS_BODY_OUT' => $corps]);
    $res = preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0, 'brut' => $sortie];
    $res['text'] = (string)@file_get_contents($corps);   // page complète (l'extrait du banc d'essai est tronqué)
    @unlink($corps);
    return $res;
};
$fixer = static function (string $finRelative, int $graceJours) use ($pdo, $jourReel): void {
    $pdo->exec("UPDATE platform_subscriptions SET statut = 'active', date_fin = '" . $jourReel->modify($finRelative)->format('Y-m-d') . "', jours_grace_appliques = $graceJours WHERE etablissement_id = 1");
};
$fixer('-1 day', 5);
$r = $page('admin', $adminEcole);
check($r['status'] === 200 && str_contains((string)$r['text'], 'Abonnement échu') && str_contains((string)$r['text'], 'data-bandeau="grace"'), 'administrateur d\'école en période de grâce : bandeau affiché, accès conservé', json_encode(['s' => $r['status'] ?? null]));
if ($autreRole) {
    $r = $page($autreRole['role'], (int)$autreRole['id']);
    check($r['status'] === 200 && strlen((string)$r['text']) > 1000 && !str_contains((string)$r['text'], 'data-bandeau="grace"'), 'autre rôle (' . $autreRole['role'] . ') : aucun bandeau');
}
$fixer('+20 days', 5);
$r = $page('admin', $adminEcole);
check($r['status'] === 200 && !str_contains((string)$r['text'], 'data-bandeau="grace"'), 'abonnement en cours : aucun bandeau');
$fixer('-30 days', 5);
$r = $page('admin', $adminEcole);
check($r['status'] !== 200 && !str_contains((string)$r['text'], 'data-bandeau="grace"'), 'grâce écoulée : accès refusé par le garde, pas de bandeau', json_encode(['s' => $r['status'] ?? null]));

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
