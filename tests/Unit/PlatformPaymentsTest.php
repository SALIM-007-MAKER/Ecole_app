<?php
/**
 * Tests — paiements manuels et revenus SaaS (étape 5 du Super-Admin SaaS, migration T070).
 *
 * 1. Migration T070 : additive, idempotente, déclencheurs (aucune suppression, champs financiers figés, annulation seule permise)
 * 2. Saisie : montants (formats, décimales, XOF entier), modes, références, dates, essai, autre établissement, devise de la période
 * 3. Solde : dépassement refusé, solde exact accepté, annulation qui libère le solde
 * 4. Double saisie : même référence externe refusée tant que le premier paiement n'est pas annulé
 * 5. Annulation : motif obligatoire, ligne conservée, déjà annulé
 * 6. Audit strict : traces payment.recorded / payment.cancelled, échec de l'audit = opération annulée, transaction englobante
 * 7. Situation par période (essai / prix inconnu / impayé / partiel / soldé)
 * 8. Revenus : totaux PAR DEVISE (jamais mélangées), annulés exclus, mensuel / mode / plan, reste à encaisser, période
 * 9. Parcours HTTP : saisie, annulation, reçu, revenus et export réservés au super_admin ; export CSV inscrit au journal
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/PlatformPaymentsTest.php
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
use Core\Platform\PaymentException;
use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformOperatorService;
use Core\Platform\PlatformPaymentService;
use Core\Platform\PlatformPlanService;
use Core\Platform\PlatformRevenueService;
use Core\Platform\PlatformSubscriptionService;

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
function http(string $nomCopie, string $operateur, string $methode, string $uri, array $post = [], array $env = []): array
{
    global $dossierSecours;
    [$code, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/platform_request.php', $operateur, $methode, $uri, json_encode($post)],
        ['REQUEST_AS_DB' => $nomCopie, 'PLATFORM_REQ_AUDIT_DIR' => $dossierSecours] + $env);
    return preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0, 'brut' => $sortie, 'code' => $code];
}
function operateur(int $id, string $niveau): string { return json_encode(['id' => $id, 'niveau' => $niveau, 'email' => "op$id@exemple.test", 'nom' => "Op $id"]); }
/** Message de la PaymentException levée par $f, ou null si rien n'est levé. */
function refus(callable $f): ?string
{
    try { $f(); return null; } catch (PaymentException $e) { return $e->getMessage(); }
}
function instant(string $ymdHis, string $tz = 'Africa/Niamey'): DateTimeImmutable { return new DateTimeImmutable($ymdHis, new DateTimeZone($tz)); }

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$dossierSecours = sys_get_temp_dir() . '/ppay_' . bin2hex(random_bytes(4));
PlatformAuditService::definirDossierSecours($dossierSecours);
$ops = new PlatformOperatorService($pdo);
$plans = new PlatformPlanService($pdo);
$mdp = 'Zq7!pLm2#vXk9@Rt';
$superId = $ops->creer('super@exemple.test', 'Super Opérateur', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$adminId = $ops->creer('admin@exemple.test', 'Admin Opérateur', 'admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$maintenant = instant('2026-03-10 12:00:00');
$horloge = static function () use (&$maintenant): DateTimeImmutable { return $maintenant; };
$sub = new PlatformSubscriptionService($pdo, $horloge);
$pay = new PlatformPaymentService($pdo, $horloge);
$planA = $plans->create(['code' => 'xof-a', 'nom' => 'Plan A', 'prix_mensuel' => '5000', 'prix_annuel' => '50000', 'duree_essai_jours' => 14, 'jours_grace' => 5, 'max_users' => 10, 'max_eleves' => 100, 'storage_quota_mb' => 500], $superId);
$n = 0;
$etab = static function (string $statut = 'active') use ($pdo, &$n): int {
    $n++;
    $c = ['slug' => "p-$n-" . bin2hex(random_bytes(2)), 'nom' => "Ecole P$n", 'nom_court' => "P$n", 'type' => 'lycee', 'statut' => $statut];
    $pdo->prepare('INSERT INTO etablissements (' . implode(',', array_keys($c)) . ') VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')')->execute(array_values($c));
    return (int)$pdo->lastInsertId();
};
$ligne = static fn(int $id) => $pdo->query("SELECT * FROM platform_payments WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
$nbPay = static fn() => (int)$pdo->query('SELECT COUNT(*) FROM platform_payments')->fetchColumn();
$audits = static fn(string $action) => $pdo->query('SELECT * FROM platform_audit_logs WHERE action = ' . $pdo->quote($action) . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$saisie = static fn(string $montant, string $mode = 'especes', string $ref = '', string $date = '2026-03-10', string $note = ''): array
    => ['montant' => $montant, 'mode' => $mode, 'reference_externe' => $ref, 'date_paiement' => $date, 'note' => $note];

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('1. Migration T070');
$migration = require ROOT_PATH . '/database/migrations/T070_paiements_plateforme.php';
$migration['run']($pdo);
$migration['run']($pdo);
check((int)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_payments'")->fetchColumn() === 1, 'table platform_payments créée, rejouable sans erreur (idempotente)');
check(PlatformPaymentService::disponible($pdo), 'service disponible après T070');
check((int)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = 'platform_payments'")->fetchColumn() === 2, 'deux déclencheurs posés (suppression, modification)');

$e1 = $etab();
$s1 = $sub->souscrire($e1, $planA, 'monthly', $superId);   // 5000 XOF
// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('2. Saisie');
$p1 = $pay->enregistrer($e1, $s1, $saisie('2 000'), $superId);
$l = $ligne($p1);
check($l['montant'] === '2000.00' && $l['devise'] === 'XOF' && $l['statut'] === 'recorded' && (int)$l['recorded_by'] === $superId && $l['mode'] === 'especes' && $l['reference_externe'] === null,
    'paiement enregistré : montant normalisé (espaces), devise = celle de la période, statut recorded, opérateur tracé');
check($l['reference'] === sprintf('PAY-2026-%06d', $p1), 'référence PAY-AAAA-NNNNNN attribuée', (string)$l['reference']);
$pNbsp = $pay->enregistrer($e1, $s1, $saisie("500\u{00A0}", 'especes'), $superId);
check($ligne($pNbsp)['montant'] === '500.00', 'espace insécable toléré dans le montant');
$pdo->prepare("UPDATE platform_payments SET statut = 'cancelled', cancelled_at = NOW(), cancel_reason = 'nettoyage' WHERE id = ?")->execute([$pNbsp]);   // revient à 2000 encaissés

$cas = [
    'décimales sur XOF'            => $saisie('1000,50'),
    'zéro'                         => $saisie('0'),
    'négatif'                      => $saisie('-5'),
    'texte'                        => $saisie('abc'),
    'vide'                         => $saisie(''),
    'notation scientifique'        => $saisie('1e3'),
    'trop de chiffres'             => $saisie('12345678901'),
    'mode inconnu'                 => $saisie('100', 'bitcoin'),
    'virement sans référence'      => $saisie('100', 'virement'),
    'orange money sans référence'  => $saisie('100', 'orange_money'),
    'date future'                  => $saisie('100', 'especes', '', '2026-03-11'),
    'date impossible'              => $saisie('100', 'especes', '', '2026-02-30'),
    'date au mauvais format'       => $saisie('100', 'especes', '', '10/03/2026'),
    'date avant 2000'              => $saisie('100', 'especes', '', '1999-12-31'),
    'note trop longue'             => $saisie('100', 'especes', '', '2026-03-10', str_repeat('x', 256)),
    'référence trop longue'        => $saisie('100', 'virement', str_repeat('r', 101)),
];
foreach ($cas as $nom => $data) {
    $avant = $nbPay();
    $m = refus(fn() => $pay->enregistrer($e1, $s1, $data, $superId));
    check($m !== null && $nbPay() === $avant, "refusé sans écriture : $nom", (string)$m);
}
$temoin = $pay->enregistrer($e1, $s1, $saisie('100', 'especes', '', '2026-03-10'), $superId);
check($temoin > 0 && $ligne($temoin)['montant'] === '100.00', 'témoin : un paiement valide passe (la date du jour est acceptée)');
$pay->annuler($temoin, 'témoin', $superId);
$cheque = $pay->enregistrer($e1, $s1, $saisie('250', 'cheque'), $superId);
check($ligne($cheque)['mode'] === 'cheque', 'chèque accepté sans référence');
$pay->annuler($cheque, 'témoin', $superId);

$autre = $etab(); $sAutre = $sub->souscrire($autre, $planA, 'monthly', $superId);
check(refus(fn() => $pay->enregistrer($e1, $sAutre, $saisie('100'), $superId)) !== null, 'période d\'un autre établissement refusée');
check(refus(fn() => $pay->enregistrer($e1, 999999, $saisie('100'), $superId)) !== null, 'période inconnue refusée');
$eT = $etab('trial'); $sT = $sub->assigner($eT, $planA, $superId);
$m = refus(fn() => $pay->enregistrer($eT, $sT, $saisie('100'), $superId));
check($m !== null && str_contains($m, 'essai'), 'un essai ne se paie pas', (string)$m);

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('3. Solde');
$m = refus(fn() => $pay->enregistrer($e1, $s1, $saisie('3001'), $superId));
check($m !== null && str_contains($m, '3 000') && str_contains($m, 'XOF'), 'dépassement du solde refusé avec le solde restant dans le message', (string)$m);
$p3 = $pay->enregistrer($e1, $s1, $saisie('3000'), $superId);
check($pay->situationParPeriode($e1)[$s1]['etat'] === 'solde' && $pay->situationParPeriode($e1)[$s1]['reste'] === '0.00', 'solde exact accepté : période soldée, reste 0');
check(refus(fn() => $pay->enregistrer($e1, $s1, $saisie('1'), $superId)) !== null, 'période soldée : tout nouveau paiement refusé');
$pay->annuler($p3, 'Chèque rejeté', $superId);
check($pay->situationParPeriode($e1)[$s1]['etat'] === 'partiel' && $pay->situationParPeriode($e1)[$s1]['reste'] === '3000.00', 'annulation : le solde est rendu, période de nouveau partielle');
check($pay->enregistrer($e1, $s1, $saisie('3000'), $superId) > 0, 'après annulation, le montant peut être ressaisi');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('4. Double saisie');
$e4 = $etab(); $s4 = $sub->souscrire($e4, $planA, 'yearly', $superId);   // 50000
$v1 = $pay->enregistrer($e4, $s4, $saisie('1000', 'virement', 'VIR-0001'), $superId);
$m = refus(fn() => $pay->enregistrer($e4, $s4, $saisie('1000', 'virement', 'VIR-0001'), $superId));
check($m !== null && str_contains($m, (string)$ligne($v1)['reference']), 'même référence + même mode : refusé, le message cite le paiement existant', (string)$m);
check(refus(fn() => $pay->enregistrer($autre, $sAutre, $saisie('1000', 'virement', 'VIR-0001'), $superId)) !== null, 'refusé aussi pour un autre établissement (une opération bancaire = un seul paiement)');
check(refus(fn() => $pay->enregistrer($e4, $s4, $saisie('1000', 'orange_money', 'VIR-0001'), $superId)) === null, 'même référence dans un autre mode : acceptée');
$sqlState = null;
try {
    $pdo->exec("INSERT INTO platform_payments (etablissement_id, subscription_id, montant, devise, mode, reference_externe, date_paiement) VALUES ($e4, $s4, 1, 'XOF', 'virement', 'VIR-0001', '2026-03-10')");
} catch (PDOException $ex) { $sqlState = $ex->getCode(); }
check($sqlState === '23000', 'la base refuse aussi le doublon (index unique), même sans passer par le service');
$pay->annuler($v1, 'Saisie en double', $superId);
check(refus(fn() => $pay->enregistrer($e4, $s4, $saisie('1000', 'virement', 'VIR-0001'), $superId)) === null, 'après annulation, la référence peut être ressaisie');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('5. Annulation et déclencheurs');
$e5 = $etab(); $s5 = $sub->souscrire($e5, $planA, 'monthly', $superId);
$p5 = $pay->enregistrer($e5, $s5, $saisie('1000'), $superId);
$avant = $nbPay();
check(refus(fn() => $pay->annuler($p5, '   ', $superId)) !== null && $ligne($p5)['statut'] === 'recorded', 'annulation sans motif refusée');
check(refus(fn() => $pay->annuler($p5, str_repeat('m', 256), $superId)) !== null, 'motif trop long refusé');
check(refus(fn() => $pay->annuler(999999, 'x', $superId)) !== null, 'paiement inconnu refusé');
$pay->annuler($p5, 'Erreur de saisie', $superId);
$l = $ligne($p5);
check($l['statut'] === 'cancelled' && $l['cancel_reason'] === 'Erreur de saisie' && (int)$l['cancelled_by'] === $superId && $l['cancelled_at'] !== null && $nbPay() === $avant,
    'annulation : ligne conservée avec motif, opérateur et date');
check(refus(fn() => $pay->annuler($p5, 'encore', $superId)) !== null, 'déjà annulé : refusé');
$tente = static function (string $sql) use ($pdo): bool { try { $pdo->exec($sql); return false; } catch (PDOException) { return true; } };
$p5b = $pay->enregistrer($e5, $s5, $saisie('700'), $superId);
check($tente("DELETE FROM platform_payments WHERE id = $p5b"), 'DELETE refusé par la base');
check($tente("UPDATE platform_payments SET montant = 1 WHERE id = $p5b"), 'UPDATE du montant refusé');
check($tente("UPDATE platform_payments SET devise = 'EUR' WHERE id = $p5b"), 'UPDATE de la devise refusé');
check($tente("UPDATE platform_payments SET subscription_id = $s1 WHERE id = $p5b"), 'rattachement à une autre période refusé');
check($tente("UPDATE platform_payments SET date_paiement = '2026-01-01' WHERE id = $p5b"), 'UPDATE de la date refusé');
check($tente("UPDATE platform_payments SET reference = 'PAY-FAUX' WHERE id = $p5b"), 'UPDATE de la référence déjà attribuée refusé');
check($tente("UPDATE platform_payments SET statut = 'recorded' WHERE id = $p5"), 'un paiement annulé ne redevient pas valide');
check($tente("UPDATE platform_payments SET cancel_reason = 'autre motif' WHERE id = $p5"), 'le motif d\'une annulation ne se réécrit pas');
check($ligne($p5b)['montant'] === '700.00', 'montant resté intact après toutes les tentatives');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('6. Audit strict');
$rec = $audits('payment.recorded');
$der = end($rec);
$apres = json_decode((string)$der['nouvelle_valeur'], true) ?: [];
check(count($rec) >= 6 && $der['entity_type'] === 'payment' && (int)$der['entity_id'] === $p5b && (int)$der['etablissement_id'] === $e5, 'chaque paiement est inscrit au journal global (entité, établissement)');
check(($apres['montant'] ?? '') === '700.00' && ($apres['devise'] ?? '') === 'XOF' && str_starts_with((string)($apres['reference'] ?? ''), 'PAY-') && $der['actor_email'] === 'super@exemple.test', 'la trace contient montant, devise, référence et l\'opérateur');
$can = $audits('payment.cancelled');
$derC = end($can);
$avantC = json_decode((string)$derC['ancienne_valeur'], true) ?: [];
check(count($can) >= 3 && ($avantC['statut'] ?? '') === 'recorded' && (int)$derC['entity_id'] > 0, 'chaque annulation est inscrite avec l\'état avant');
check(!str_contains(json_encode($rec), 'mot_de_passe') && !str_contains(json_encode($rec), 'password'), 'aucun secret dans les traces');

$e6 = $etab(); $s6 = $sub->souscrire($e6, $planA, 'monthly', $superId);
$p6 = $pay->enregistrer($e6, $s6, $saisie('1000'), $superId);
$avantNb = $nbPay();
$pdo->exec("CREATE TRIGGER t_pay_audit_ko BEFORE INSERT ON platform_audit_logs FOR EACH ROW BEGIN
    IF NEW.action LIKE 'payment.%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'echec audit simule'; END IF; END");
try {
    $ex = null;
    try { $pay->enregistrer($e6, $s6, $saisie('500'), $superId); } catch (\Throwable $t) { $ex = $t; }
    check($ex instanceof PaymentException && $ex->estAudit() && str_contains($ex->getMessage(), 'trace d\'audit') && $nbPay() === $avantNb, 'audit impossible : saisie refusée, aucune ligne écrite');
    $ex = null;
    try { $pay->annuler($p6, 'Test', $superId); } catch (\Throwable $t) { $ex = $t; }
    check($ex instanceof PaymentException && $ligne($p6)['statut'] === 'recorded', 'audit impossible : annulation annulée, paiement intact');
    $pdo->beginTransaction();
    $ex = null;
    try { $pay->enregistrer($e6, $s6, $saisie('500'), $superId); } catch (\Throwable $t) { $ex = $t; }
    check($ex !== null && $pdo->inTransaction() && $nbPay() === $avantNb, 'transaction englobante : seule l\'opération est annulée, celle de l\'appelant reste ouverte');
    $pdo->rollBack();
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS t_pay_audit_ko');
}
check($pay->enregistrer($e6, $s6, $saisie('500'), $superId) > 0, 'audit rétabli : la saisie fonctionne de nouveau');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('7. Situation par période');
$e7 = $etab(); $s7a = $sub->souscrire($e7, $planA, 'monthly', $superId);
$sit = $pay->situationParPeriode($e7);
check($sit[$s7a]['etat'] === 'impaye' && $sit[$s7a]['paye'] === '0.00' && $sit[$s7a]['reste'] === '5000.00', 'aucun paiement : impayé, reste = prix figé');
$pay->enregistrer($e7, $s7a, $saisie('1500'), $superId);
$sit = $pay->situationParPeriode($e7);
check($sit[$s7a]['etat'] === 'partiel' && $sit[$s7a]['paye'] === '1500.00' && $sit[$s7a]['reste'] === '3500.00', 'paiement partiel : encaissé et reste exacts');
$sit = $pay->situationParPeriode($eT);
check($sit[$sT]['etat'] === 'essai' && $sit[$sT]['reste'] === null, 'essai : pas de règlement attendu');
$sit = $pay->situationParPeriode(1);
$legacy = array_values($sit)[0] ?? null;
check($legacy !== null && $legacy['etat'] === 'inconnu' && $legacy['reste'] === null, 'période reprise de l\'existant (prix inconnu) : état inconnu');
$legacyId = (int)array_key_first($sit);
check($pay->enregistrer(1, $legacyId, $saisie('2500,50'), $superId) > 0 && $pay->situationParPeriode(1)[$legacyId]['paye'] === '2500.50', 'prix inconnu : paiement accepté sans plafond, devise DZD à deux décimales');
$p7 = $pay->enregistrer($e7, $s7a, $saisie('3500'), $superId);
$sub->renouveler($e7, $superId);
$sit = $pay->situationParPeriode($e7);
check(count($sit) === 2 && $sit[$s7a]['etat'] === 'solde' && array_values($sit)[1]['etat'] === 'impaye', 'renouvellement : l\'ancienne période reste soldée, la nouvelle est impayée');
check(count(array_filter($pay->liste($e7))) === 2, 'liste des paiements d\'un établissement');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('8. Revenus');
$eR = $etab(); $sR = $sub->souscrire($eR, $planA, 'yearly', $superId);   // 50000 XOF
$rA = $pay->enregistrer($eR, $sR, $saisie('3000', 'especes', '', '2025-06-05'), $superId);
$rB = $pay->enregistrer($eR, $sR, $saisie('2000', 'orange_money', 'OM-77', '2025-07-10'), $superId);
$rC = $pay->enregistrer(1, $legacyId, $saisie('1000', 'virement', 'DZ-1', '2025-07-12'), $superId);
$rD = $pay->enregistrer($eR, $sR, $saisie('500', 'especes', '', '2025-07-15'), $superId);
$pay->annuler($rD, 'Erreur', $superId);
$rev = new PlatformRevenueService($pdo);
$sy = $rev->synthese('2025-01-01', '2025-12-31');
check($sy['totaux'] === ['DZD' => ['total' => '1000.00', 'nb' => 1], 'XOF' => ['total' => '5000.00', 'nb' => 2]], 'totaux par devise, annulés exclus', json_encode($sy['totaux']));
check(!isset($sy['totaux']['EUR']) && count($sy['totaux']) === 2, 'les devises ne sont jamais additionnées ni converties');
check($sy['annules'] === ['XOF' => ['total' => '500.00', 'nb' => 1]], 'annulés comptés à part', json_encode($sy['annules']));
check($sy['mensuel']['XOF'] === ['2025-06' => '3000.00', '2025-07' => '2000.00'] && $sy['mensuel']['DZD'] === ['2025-07' => '1000.00'], 'répartition mensuelle par devise', json_encode($sy['mensuel']));
check($sy['par_mode']['XOF'] === ['especes' => '3000.00', 'orange_money' => '2000.00'] && $sy['par_mode']['DZD'] === ['virement' => '1000.00'], 'répartition par mode', json_encode($sy['par_mode']));
check($sy['par_plan']['XOF'] === ['Plan A' => '5000.00'], 'répartition par plan', json_encode($sy['par_plan']));
$sy2 = $rev->synthese('2025-07-01', '2025-07-10');
check($sy2['totaux']['XOF']['total'] === '2000.00' && !isset($sy2['totaux']['DZD']), 'bornes de période incluses (10 juillet compris, 12 juillet exclu)');
check($rev->synthese('2024-01-01', '2024-12-31')['totaux'] === [], 'période sans encaissement : aucun total');
$attendu = 0.0;
foreach ($pdo->query("SELECT sb.prix_applique, COALESCE((SELECT SUM(montant) FROM platform_payments x WHERE x.subscription_id = sb.id AND x.statut = 'recorded'), 0) AS paye
                        FROM platform_subscriptions sb WHERE sb.statut IN ('active','grace','cancelled') AND sb.cycle <> 'trial' AND sb.prix_applique IS NOT NULL AND sb.devise = 'XOF'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $attendu += max(0, (float)$r['prix_applique'] - (float)$r['paye']);
}
check(abs((float)($sy['a_encaisser']['XOF'] ?? 0) - $attendu) < 0.005 && $attendu > 0, 'reste à encaisser (périodes en cours) = prix figés - encaissés', ($sy['a_encaisser']['XOF'] ?? '?') . " vs $attendu");
check(!isset($sy['a_encaisser']['DZD']), 'prix inconnu : jamais compté dans le reste à encaisser');
$lignes = iterator_to_array($rev->iterer('2025-01-01', '2025-12-31'), false);
check(count($lignes) === 4 && in_array('cancelled', array_column($lignes, 'statut'), true), 'export : tous les statuts (l\'annulation reste visible)');
$p = PlatformRevenueService::periode('2026-05-01', '2026-02-01', instant('2026-11-04 10:00:00'));
check($p === ['du' => '2026-02-01', 'au' => '2026-05-01'], 'bornes inversées : remises dans l\'ordre');
$p = PlatformRevenueService::periode('n\'importe quoi', '', instant('2026-11-04 10:00:00'));
check($p === ['du' => '2026-01-01', 'au' => '2026-11-04'], 'bornes invalides : année en cours jusqu\'à aujourd\'hui (heure locale)');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('9. Parcours HTTP');
$S = operateur($superId, 'super_admin'); $A = operateur($adminId, 'admin');
$eH = $etab(); $sH = $sub->souscrire($eH, $planA, 'monthly', $superId);
$P = '/ecole_app/platform';
$post = ['subscription_id' => (string)$sH, 'montant' => '1500', 'mode' => 'especes', 'date_paiement' => '2026-03-10', 'reference_externe' => '', 'note' => ''];
$avant = $nbPay();
$r = http($nomCopie, $A, 'POST', "$P/etablissements/$eH/paiements", $post);
check($r['status'] === 403 && $nbPay() === $avant, 'admin : saisie refusée (403)', json_encode($r));
$r = http($nomCopie, 'guest', 'POST', "$P/etablissements/$eH/paiements", $post);
check($nbPay() === $avant && $r['status'] !== 200, 'anonyme : saisie refusée');
$r = http($nomCopie, $S, 'POST', "$P/etablissements/$eH/paiements", $post, ['REQUEST_AS_NO_CSRF' => '1']);
check($nbPay() === $avant, 'sans jeton CSRF valide : rien n\'est écrit', json_encode($r));
$r = http($nomCopie, $S, 'POST', "$P/etablissements/$eH/paiements", $post);
check($r['status'] === 302 && $nbPay() === $avant + 1, 'super_admin : paiement enregistré puis redirection vers le reçu', json_encode($r));
$idH = (int)$pdo->query('SELECT MAX(id) FROM platform_payments')->fetchColumn();
check(str_contains((string)($r['location'] ?? ''), "/platform/paiements/$idH"), 'redirection vers /platform/paiements/{id}', (string)($r['location'] ?? ''));
$r = http($nomCopie, $S, 'POST', "$P/etablissements/$eH/paiements", ['montant' => '99999'] + $post);
check($nbPay() === $avant + 1, 'montant au-delà du solde : aucune écriture');
$r = http($nomCopie, $S, 'GET', "$P/paiements/$idH");
check($r['status'] === 200 && str_contains((string)$r['text'], 'PAY-') && str_contains((string)$r['text'], 'Annuler ce paiement'), 'super_admin : reçu affiché avec le formulaire d\'annulation');
check(http($nomCopie, $A, 'GET', "$P/paiements/$idH")['status'] === 403, 'admin : reçu refusé');
check(http($nomCopie, $S, 'GET', "$P/paiements/999999")['status'] === 404, 'reçu inconnu : 404');
$r = http($nomCopie, $S, 'GET', "$P/etablissements/$eH");
check($r['status'] === 200 && str_contains((string)$r['text'], 'Paiements (saisie manuelle)') && str_contains((string)$r['text'], 'Partiel'), 'fiche établissement (super_admin) : section Paiements avec la situation');
$r = http($nomCopie, $A, 'GET', "$P/etablissements/$eH");
check($r['status'] === 200 && !str_contains((string)$r['text'], 'Paiements (saisie manuelle)') && !str_contains((string)$r['text'], 'PAY-'), 'fiche établissement (admin) : aucune donnée de paiement');
$r = http($nomCopie, $A, 'POST', "$P/paiements/$idH/annuler", ['motif' => 'Test']);
check($ligne($idH)['statut'] === 'recorded', 'admin : annulation refusée');
$r = http($nomCopie, $S, 'POST', "$P/paiements/$idH/annuler", ['motif' => '']);
check($ligne($idH)['statut'] === 'recorded', 'annulation sans motif : refusée');
$r = http($nomCopie, $S, 'POST', "$P/paiements/$idH/annuler", ['motif' => 'Erreur de caisse']);
check($ligne($idH)['statut'] === 'cancelled', 'super_admin : annulation enregistrée');
$r = http($nomCopie, $S, 'GET', "$P/paiements/$idH");
check(str_contains((string)$r['text'], 'PAIEMENT ANNULÉ') && str_contains((string)$r['text'], 'Erreur de caisse'), 'reçu annulé : mention et motif');

$r = http($nomCopie, $A, 'GET', "$P/revenus");
check($r['status'] === 403, 'admin : page des revenus refusée');
$r = http($nomCopie, $S, 'GET', "$P/revenus?du=2025-01-01&au=2025-12-31");
check($r['status'] === 200 && str_contains((string)$r['text'], 'Encaissé (XOF)') && str_contains((string)$r['text'], 'Encaissé (DZD)') && str_contains((string)$r['text'], 'Orange Money'), 'super_admin : revenus par devise et par mode', json_encode(['s' => $r['status']]));
check(http($nomCopie, $A, 'GET', "$P/revenus/export")['status'] === 403, 'admin : export refusé');
$avantExport = count($audits('revenue.exported'));
$corps = sys_get_temp_dir() . '/prev_export_' . bin2hex(random_bytes(3)) . '.csv';
$pdo->prepare('UPDATE etablissements SET nom = ? WHERE id = ?')->execute(['=HYPERLINK("http://x")', $eR]);
$r = http($nomCopie, $S, 'GET', "$P/revenus/export?du=2025-01-01&au=2025-12-31", [], ['REQUEST_AS_BODY_OUT' => $corps]);
$csv = is_file($corps) ? (string)file_get_contents($corps) : '';
@unlink($corps);
check($r['status'] === 200 && str_starts_with($csv, "\xEF\xBB\xBFreference;date_paiement;etablissement"), 'export : CSV UTF-8 avec BOM et en-têtes');
check(substr_count($csv, "\n") - 1 === 4 && str_contains($csv, 'cancelled') && str_contains($csv, 'Orange Money'), 'export : une ligne par paiement de la période (annulé inclus), mode en clair');
check(str_contains($csv, "'=HYPERLINK") && !preg_match('/;=HYPERLINK/', $csv), 'export : injection de formule neutralisée');
check(count($audits('revenue.exported')) === $avantExport + 1, 'l\'export est inscrit au journal global');

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
