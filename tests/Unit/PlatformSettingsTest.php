<?php
/**
 * Tests — paramètres globaux de la plateforme (étape 6 du Super-Admin SaaS, migration T071).
 *
 * 1. Migration T071 : additive, idempotente, aucune ligne insérée (comportement existant conservé)
 * 2. Priorité : surcharge en base > .env > défaut ; env invalide ignoré ; valeur vide = suppression de la surcharge
 * 3. Validation : devise, fuseau, nombres bornés, texte ; toutes les erreurs remontées, rien d'écrit en cas d'erreur
 * 4. Effets : fuseau des abonnements, devise et valeurs par défaut des nouveaux plans (les plans existants ne changent pas)
 * 5. Audit strict : seulement les clés modifiées (avant / après), aucun audit sans changement, échec d'audit = rien de modifié
 * 6. Table absente : lecture sans erreur (repli sur le .env), écriture refusée proprement
 * 7. Parcours HTTP : super_admin uniquement, coordonnées de paiement visibles, formulaires préremplis
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/PlatformSettingsTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
unset($_ENV['PLATFORM_TIMEZONE'], $_ENV['PLATFORM_DEFAULT_CURRENCY']);
putenv('PLATFORM_TIMEZONE'); putenv('PLATFORM_DEFAULT_CURRENCY');
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
use Core\Platform\PlatformOperatorService;
use Core\Platform\PlatformPaymentService;
use Core\Platform\PlatformPlanService;
use Core\Platform\PlatformSettings;
use Core\Platform\PlatformSubscriptionService;
use Core\Platform\SubscriptionRules;

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
/** Erreurs de validation levées par $f, ou null. */
function erreurs(callable $f): ?array
{
    try { $f(); return null; } catch (PlanValidationException $e) { return $e->getErrors(); }
}

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$dossierSecours = sys_get_temp_dir() . '/pset_' . bin2hex(random_bytes(4));
PlatformAuditService::definirDossierSecours($dossierSecours);
$ops = new PlatformOperatorService($pdo);
$plans = new PlatformPlanService($pdo);
$mdp = 'Zq7!pLm2#vXk9@Rt';
$superId = $ops->creer('super@exemple.test', 'Super Opérateur', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$adminId = $ops->creer('admin@exemple.test', 'Admin Opérateur', 'admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$reg = new PlatformSettings($pdo);
$lignes = static fn() => $pdo->query('SELECT cle, valeur FROM platform_settings ORDER BY cle')->fetchAll(PDO::FETCH_KEY_PAIR);
$audits = static fn() => $pdo->query("SELECT * FROM platform_audit_logs WHERE action = 'settings.updated' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$nouveauPlan = static function (array $extra = []) use ($plans, $superId): array {
    static $i = 0; $i++;
    $id = $plans->create(['code' => "set-$i", 'nom' => "Plan S$i", 'prix_mensuel' => '1000', 'prix_annuel' => '10000', 'max_users' => 5, 'max_eleves' => 50, 'storage_quota_mb' => 100] + $extra, $superId);
    return (array)$plans->find((int)$id);
};

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('1. Migration T071');
$migration = require ROOT_PATH . '/database/migrations/T071_parametres_plateforme.php';
$migration['rollback']($pdo);
check(!PlatformSettings::disponible($pdo), 'sans la table : indisponible (état d\'avant T071)');
$migration['run']($pdo);
$migration['run']($pdo);
check(PlatformSettings::disponible($pdo), 'table platform_settings créée, rejouable sans erreur (idempotente)');
check($lignes() === [], 'aucune ligne insérée : le comportement existant est conservé');
PlatformSettings::oublier();
check(PlatformSettings::valeur(PlatformSettings::DEVISE) === 'XOF' && PlatformSettings::valeur(PlatformSettings::FUSEAU) === 'Africa/Niamey'
    && PlatformSettings::valeur(PlatformSettings::ESSAI) === '0' && PlatformSettings::valeur(PlatformSettings::GRACE) === '0' && PlatformSettings::valeur(PlatformSettings::PAIEMENT) === '',
    'sans surcharge ni .env : valeurs par défaut du code');
check(SubscriptionRules::fuseau()->getName() === 'Africa/Niamey' && PlatformPlanService::deviseReference() === 'XOF', 'les règles d\'abonnement et de plan restent inchangées');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('2. Priorité des sources');
$_ENV['PLATFORM_DEFAULT_CURRENCY'] = 'EUR'; $_ENV['PLATFORM_TIMEZONE'] = 'Europe/Paris';
check(PlatformSettings::valeur(PlatformSettings::DEVISE) === 'EUR' && PlatformSettings::valeur(PlatformSettings::FUSEAU) === 'Europe/Paris', '.env pris en compte sans surcharge');
$_ENV['PLATFORM_DEFAULT_CURRENCY'] = 'euro'; $_ENV['PLATFORM_TIMEZONE'] = 'Pas/UnFuseau';
check(PlatformSettings::valeur(PlatformSettings::DEVISE) === 'XOF' && PlatformSettings::valeur(PlatformSettings::FUSEAU) === 'Africa/Niamey', '.env invalide ignoré : retour au défaut');
$_ENV['PLATFORM_DEFAULT_CURRENCY'] = 'EUR'; $_ENV['PLATFORM_TIMEZONE'] = 'Europe/Paris';
$reg->enregistrer([PlatformSettings::DEVISE => 'xaf', PlatformSettings::FUSEAU => 'Africa/Abidjan'], $superId);
check(PlatformSettings::valeur(PlatformSettings::DEVISE) === 'XAF' && PlatformSettings::valeur(PlatformSettings::FUSEAU) === 'Africa/Abidjan', 'la surcharge l\'emporte sur le .env (devise passée en majuscules)');
check($lignes() === [PlatformSettings::DEVISE => 'XAF', PlatformSettings::FUSEAU => 'Africa/Abidjan'], 'seules les deux lignes modifiées sont écrites');
$t = $reg->tous();
check($t[PlatformSettings::DEVISE]['source'] === 'base' && $t[PlatformSettings::ESSAI]['source'] === 'defaut' && $t[PlatformSettings::DEVISE]['modifie_par'] === 'super@exemple.test', 'l\'écran connaît la source de chaque valeur et le dernier auteur');
$reg->enregistrer([PlatformSettings::DEVISE => ''], $superId);
check(PlatformSettings::valeur(PlatformSettings::DEVISE) === 'EUR' && !array_key_exists(PlatformSettings::DEVISE, $lignes()), 'valeur vide : surcharge supprimée, retour au .env');
$reg->enregistrer([PlatformSettings::FUSEAU => ''], $superId);
unset($_ENV['PLATFORM_DEFAULT_CURRENCY'], $_ENV['PLATFORM_TIMEZONE']);
check($lignes() === [] && PlatformSettings::valeur(PlatformSettings::FUSEAU) === 'Africa/Niamey', 'plus aucune surcharge : retour au défaut');
$reg->enregistrer(['cle_inconnue' => 'x', PlatformSettings::ESSAI => '7'], $superId);
check($lignes() === [PlatformSettings::ESSAI => '7'], 'une clé inconnue est ignorée');
$reg->enregistrer([PlatformSettings::ESSAI => ''], $superId);

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('3. Validation');
$cas = [
    'devise trop courte'         => [PlatformSettings::DEVISE => 'XO'],
    'devise avec chiffre'        => [PlatformSettings::DEVISE => 'X0F'],
    'devise trop longue'         => [PlatformSettings::DEVISE => 'XOFX'],
    'fuseau inconnu'             => [PlatformSettings::FUSEAU => 'Mars/Olympus'],
    'fuseau en minuscules'       => [PlatformSettings::FUSEAU => 'africa/niamey'],
    'essai négatif'              => [PlatformSettings::ESSAI => '-1'],
    'essai au-delà de 365'       => [PlatformSettings::ESSAI => '366'],
    'essai décimal'              => [PlatformSettings::ESSAI => '12.5'],
    'essai texte'                => [PlatformSettings::ESSAI => 'abc'],
    'grâce au-delà de 90'        => [PlatformSettings::GRACE => '91'],
    'coordonnées trop longues'   => [PlatformSettings::PAIEMENT => str_repeat('x', 501)],
    'coordonnées avec contrôle'  => [PlatformSettings::PAIEMENT => "Orange\x07Money"],
];
foreach ($cas as $nom => $data) {
    $e = erreurs(fn() => $reg->enregistrer($data, $superId));
    check($e !== null && $lignes() === [], "refusé sans écriture : $nom", json_encode($e));
}
$e = erreurs(fn() => $reg->enregistrer([PlatformSettings::DEVISE => 'XO', PlatformSettings::ESSAI => '999', PlatformSettings::GRACE => '5'], $superId));
check($e !== null && count($e) === 2 && $lignes() === [], 'toutes les erreurs remontent ensemble, et la partie valide n\'est pas écrite non plus', json_encode($e));
$reg->enregistrer([PlatformSettings::ESSAI => '365', PlatformSettings::GRACE => '90', PlatformSettings::PAIEMENT => "Orange Money : 90 00 00 00\nBanque : BIA Niger"], $superId);
check($lignes()[PlatformSettings::ESSAI] === '365' && $lignes()[PlatformSettings::GRACE] === '90' && str_contains($lignes()[PlatformSettings::PAIEMENT], "\n"), 'bornes incluses acceptées ; texte multiligne conservé');
$reg->enregistrer([PlatformSettings::ESSAI => '', PlatformSettings::GRACE => '', PlatformSettings::PAIEMENT => ''], $superId);

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('4. Effets');
$avantPlan = $nouveauPlan();
check((int)$avantPlan['duree_essai_jours'] === 0 && (int)$avantPlan['jours_grace'] === 0 && $avantPlan['devise'] === 'XOF', 'nouveau plan sans surcharge : valeurs d\'avant');
$reg->enregistrer([PlatformSettings::ESSAI => '21', PlatformSettings::GRACE => '10', PlatformSettings::DEVISE => 'XAF', PlatformSettings::FUSEAU => 'Pacific/Auckland'], $superId);
$apresPlan = $nouveauPlan();
check((int)$apresPlan['duree_essai_jours'] === 21 && (int)$apresPlan['jours_grace'] === 10 && $apresPlan['devise'] === 'XAF', 'nouveau plan : essai, grâce et devise par défaut pris dans les paramètres');
$explicite = $nouveauPlan(['duree_essai_jours' => 3, 'jours_grace' => 1, 'devise' => 'XOF']);
check((int)$explicite['duree_essai_jours'] === 3 && (int)$explicite['jours_grace'] === 1 && $explicite['devise'] === 'XOF', 'valeurs saisies explicitement : jamais écrasées par les défauts');
$ancien = (array)$plans->find((int)$avantPlan['id']);
check((int)$ancien['duree_essai_jours'] === 0 && $ancien['devise'] === 'XOF', 'plan existant : inchangé');
check(PlatformPlanService::deviseReference() === 'XAF', 'devise de référence des écrans plan');
check(SubscriptionRules::fuseau()->getName() === 'Pacific/Auckland', 'fuseau des abonnements pris dans les paramètres');
$minuit = new DateTimeImmutable('2026-03-10 12:30:00', new DateTimeZone('UTC'));   // Auckland = UTC+13 : déjà le 11 mars
check(SubscriptionRules::aujourdhui($minuit)->format('Y-m-d') === '2026-03-11', 'le « jour » des abonnements suit le fuseau choisi');
$reg->enregistrer([PlatformSettings::FUSEAU => '', PlatformSettings::DEVISE => '', PlatformSettings::ESSAI => '', PlatformSettings::GRACE => ''], $superId);
check(SubscriptionRules::fuseau()->getName() === 'Africa/Niamey' && PlatformPlanService::deviseReference() === 'XOF', 'suppression des surcharges : retour immédiat au défaut');

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('5. Audit strict');
$avant = count($audits());
$reg->enregistrer([PlatformSettings::ESSAI => '14', PlatformSettings::GRACE => '5'], $superId);
$a = $audits(); $der = end($a);
$av = json_decode((string)$der['ancienne_valeur'], true); $ap = json_decode((string)$der['nouvelle_valeur'], true);
check(count($a) === $avant + 1 && $der['entity_type'] === 'platform_settings' && $der['actor_email'] === 'super@exemple.test', 'un changement = une entrée du journal global, avec l\'opérateur');
check($av === [PlatformSettings::ESSAI => null, PlatformSettings::GRACE => null] && $ap === [PlatformSettings::ESSAI => '14', PlatformSettings::GRACE => '5'], 'ancienne et nouvelle valeurs enregistrées', json_encode([$av, $ap]));
$n = count($audits());
$retour = $reg->enregistrer([PlatformSettings::ESSAI => '14', PlatformSettings::GRACE => '5'], $superId);
check($retour === [] && count($audits()) === $n, 'aucun changement : ni écriture ni entrée de journal');
$retour = $reg->enregistrer([PlatformSettings::ESSAI => '30'], $superId);
$a = $audits(); $der = end($a);
check($retour === [PlatformSettings::ESSAI] && json_decode((string)$der['ancienne_valeur'], true) === [PlatformSettings::ESSAI => '14'] && !str_contains((string)$der['nouvelle_valeur'], 'grace'), 'seule la clé modifiée figure dans la trace');
$reg->enregistrer([PlatformSettings::PAIEMENT => 'Orange Money : 90 00 00 00'], $superId);
check(count($audits()) === $n + 2, 'texte libre audité aussi');

$avantL = $lignes();
$pdo->exec("CREATE TRIGGER t_set_audit_ko BEFORE INSERT ON platform_audit_logs FOR EACH ROW BEGIN
    IF NEW.action = 'settings.updated' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'echec audit simule'; END IF; END");
try {
    $ex = null;
    try { $reg->enregistrer([PlatformSettings::ESSAI => '99', PlatformSettings::DEVISE => 'EUR'], $superId); } catch (\Throwable $t) { $ex = $t; }
    check($ex instanceof PlanAuditException && str_contains($ex->getMessage(), 'trace d\'audit') && $lignes() === $avantL, 'audit impossible : refus clair, aucune ligne modifiée');
    check(PlatformSettings::valeur(PlatformSettings::ESSAI) === '30', 'la valeur effective reste l\'ancienne');
    $pdo->beginTransaction();
    $ex = null;
    try { $reg->enregistrer([PlatformSettings::ESSAI => '99'], $superId); } catch (\Throwable $t) { $ex = $t; }
    check($ex !== null && $pdo->inTransaction() && $lignes() === $avantL, 'transaction englobante : seule l\'opération est annulée, celle de l\'appelant reste ouverte');
    $pdo->rollBack();
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS t_set_audit_ko');
}
$reg->enregistrer([PlatformSettings::ESSAI => '', PlatformSettings::GRACE => '', PlatformSettings::PAIEMENT => ''], $superId);

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('6. Table absente');
$migration['rollback']($pdo);
PlatformSettings::oublier();
$_ENV['PLATFORM_DEFAULT_CURRENCY'] = 'EUR';
check(PlatformSettings::valeur(PlatformSettings::DEVISE) === 'EUR' && SubscriptionRules::fuseau()->getName() === 'Africa/Niamey', 'lecture sans erreur : repli sur le .env puis le défaut');
$ex = null;
try { $reg->enregistrer([PlatformSettings::ESSAI => '5'], $superId); } catch (\Throwable $t) { $ex = $t; }
check($ex instanceof PlanAuditException && str_contains($ex->getMessage(), 'T071'), 'écriture refusée avec un message clair (migration à appliquer)');
check(count($reg->tous()) === 5 && $reg->tous()[PlatformSettings::DEVISE]['effective'] === 'EUR', 'l\'écran se construit quand même');
unset($_ENV['PLATFORM_DEFAULT_CURRENCY']);
$migration['run']($pdo);
PlatformSettings::oublier();

// ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('7. Parcours HTTP');
$S = operateur($superId, 'super_admin'); $A = operateur($adminId, 'admin');
$P = '/ecole_app/platform';
$r = http($nomCopie, $A, 'GET', "$P/parametres");
check($r['status'] === 403, 'admin : page des paramètres refusée', json_encode($r));
$r = http($nomCopie, $A, 'POST', "$P/parametres", [PlatformSettings::ESSAI => '9']);
check($r['status'] === 403 && $lignes() === [], 'admin : modification refusée');
$r = http($nomCopie, 'guest', 'POST', "$P/parametres", [PlatformSettings::ESSAI => '9']);
check($lignes() === [] && $r['status'] !== 200, 'anonyme : modification refusée');
$r = http($nomCopie, $S, 'POST', "$P/parametres", [PlatformSettings::ESSAI => '9'], ['REQUEST_AS_NO_CSRF' => '1']);
check($lignes() === [], 'sans jeton CSRF valide : rien n\'est écrit');
$r = http($nomCopie, $S, 'GET', "$P/parametres");
check($r['status'] === 200 && str_contains((string)$r['text'], 'Devise de référence') && str_contains((string)$r['text'], 'Fuseau horaire') && str_contains((string)$r['text'], 'Coordonnées de paiement'), 'super_admin : page des paramètres affichée');
$r = http($nomCopie, $S, 'POST', "$P/parametres", [PlatformSettings::DEVISE => 'xof', PlatformSettings::ESSAI => '9', PlatformSettings::GRACE => '4', PlatformSettings::PAIEMENT => 'Orange Money : 90 11 22 33 <script>x</script>']);
check($r['status'] === 302 && $lignes()[PlatformSettings::ESSAI] === '9' && $lignes()[PlatformSettings::DEVISE] === 'XOF', 'super_admin : enregistrement puis redirection', json_encode($r));
$r = http($nomCopie, $S, 'POST', "$P/parametres", [PlatformSettings::ESSAI => '9999', PlatformSettings::GRACE => '1']);
check($lignes()[PlatformSettings::GRACE] === '4', 'saisie invalide : rien n\'est modifié (même la partie valide)');
$r = http($nomCopie, $S, 'GET', "$P/parametres");
check(str_contains((string)$r['text'], 'Actuellement : 9') && !str_contains((string)$r['text'], '<script>'), 'écran à jour, texte libre échappé');
$r = http($nomCopie, $S, 'GET', "$P/plans/create");
check($r['status'] === 200, 'formulaire de plan affiché avec les défauts', json_encode(['s' => $r['status']]));

$eH = (function () use ($pdo): int {
    $pdo->prepare("INSERT INTO etablissements (slug, nom, nom_court, type, statut) VALUES (?,?,?,?,'active')")->execute(['set-' . bin2hex(random_bytes(2)), 'Ecole Param', 'EP', 'lycee']);
    return (int)$pdo->lastInsertId();
})();
$sub = new PlatformSubscriptionService($pdo);
$planS = $nouveauPlan();
$sH = $sub->souscrire($eH, (int)$planS['id'], 'monthly', $superId);
$pay = new PlatformPaymentService($pdo);
$idPay = $pay->enregistrer($eH, $sH, ['montant' => '500', 'mode' => 'especes', 'date_paiement' => '2026-03-10'], $superId);
$r = http($nomCopie, $S, 'GET', "$P/paiements/$idPay");
check($r['status'] === 200 && str_contains((string)$r['text'], 'Coordonnées de paiement') && str_contains((string)$r['text'], 'Orange Money : 90 11 22 33'), 'reçu : coordonnées de paiement affichées');
$r = http($nomCopie, $S, 'GET', "$P/etablissements/$eH");
check(str_contains((string)$r['text'], 'Coordonnées de paiement :') && str_contains((string)$r['text'], '90 11 22 33'), 'fiche établissement : rappel des coordonnées de paiement');
$http = http($nomCopie, $S, 'POST', "$P/parametres", [PlatformSettings::PAIEMENT => '']);
$r = http($nomCopie, $S, 'GET', "$P/paiements/$idPay");
check(!str_contains((string)$r['text'], 'Coordonnées de paiement'), 'coordonnées vidées : le bloc disparaît du reçu');

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
