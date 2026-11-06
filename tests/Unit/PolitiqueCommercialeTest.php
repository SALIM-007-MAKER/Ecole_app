<?php
/**
 * Tests — database/appliquer-politique-commerciale.php (grille tarifaire en XOF, quotas, essai et grâce, réglages globaux).
 *
 * 1. Simulation : aucune écriture, avant/après affichés
 * 2. Garde-fous : cible de test, sauvegarde (absente, hors dossier, nom invalide, incomplète, trop ancienne), plan utilisé, plan absent
 * 3. Application : valeurs exactes des trois plans, règle annuelle (10 mensualités), réglages globaux, audit « système »
 * 4. Périmètre : abonnements, établissements et paiements STRICTEMENT inchangés ; modules, noms, descriptions et API des plans inchangés
 * 5. Idempotence et reprise partielle
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée (son empreinte est contrôlée).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/PolitiqueCommercialeTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'Core\\')) {
        $f = ROOT_PATH . '/core/' . str_replace('\\', '/', substr($c, 5)) . '.php';
        if (is_file($f)) { require $f; }
    }
});
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use Core\Database;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

// Base vivante : empreinte avant (elle ne doit jamais bouger).
$conn = \Tests\Support\DbCopy::connectionFromEnv(ROOT_PATH);
$vivante = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['db']};charset=utf8mb4", $conn['user'], $conn['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$empreinteVivante = static fn(): array => array_map(
    fn($t) => (string)$vivante->query("CHECKSUM TABLE `$t`")->fetch(PDO::FETCH_ASSOC)['Checksum'],
    ['platform_plans' => 'platform_plans', 'platform_settings' => 'platform_settings', 'platform_audit_logs' => 'platform_audit_logs', 'platform_subscriptions' => 'platform_subscriptions']
);
$vivanteAvant = $empreinteVivante();

$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();

/** Lance le script ; @return array{0:int,1:string} code de sortie et sortie complète. */
function script(string $nomCopie, array $args = [], ?string $cible = null): array
{
    $env = array_merge(getenv(), ['POLITIQUE_DB' => $cible ?? $nomCopie]);
    $p = proc_open(array_merge([PHP_BINARY, ROOT_PATH . '/database/appliquer-politique-commerciale.php'], $args),
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, $env);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out];
}
$plans = static fn(): array => array_column($pdo->query('SELECT * FROM platform_plans ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), null, 'code');
$somme = static function (array $tables) use ($pdo): array {
    $o = [];
    foreach ($tables as $t) { $o[$t] = (string)$pdo->query("CHECKSUM TABLE `$t`")->fetch(PDO::FETCH_ASSOC)['Checksum']; }
    return $o;
};
$auditPlans = static fn(): array => $pdo->query("SELECT actor_type, operator_id, entity_id, ancienne_valeur, nouvelle_valeur FROM platform_audit_logs WHERE action = 'plan.updated' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$perimetre = ['platform_subscriptions', 'etablissements', 'platform_payments'];
$conserve = static fn(array $p): array => array_map(fn($x) => [$x['nom'], $x['description'], $x['modules_inclus'], $x['api_calls_per_day'], $x['ordre'], $x['visible_catalogue'], $x['actif'], $x['code']], $p);
$pdo->exec("INSERT INTO platform_settings (cle, valeur) VALUES ('__garde__', 'x') ON DUPLICATE KEY UPDATE valeur = valeur");
$pdo->exec("DELETE FROM platform_settings WHERE cle = '__garde__'");

$avantPlans = $plans();
check(array_keys($avantPlans) === ['starter', 'pro', 'enterprise'] && $avantPlans['starter']['devise'] === 'DZD' && (int)$avantPlans['starter']['max_eleves'] === 200, 'départ : trois plans en DZD aux anciennes valeurs (jeu de référence)');
$sommesAvant = $somme($perimetre);
$planSomme = $somme(['platform_plans', 'platform_settings', 'platform_audit_logs']);
$conservesAvant = $conserve($avantPlans);
$abonnementPro = (int)$pdo->query("SELECT COUNT(*) FROM platform_subscriptions s JOIN platform_plans p ON p.id = s.plan_id WHERE p.code = 'pro'")->fetchColumn();

// ═══════════════════════════════════════════════════════════
section('1. Simulation : aucune écriture');
[$code, $sortie] = script($nomCopie);
check($code === 0 && str_contains($sortie, 'simulation : aucune écriture') && str_contains($sortie, 'SIMULATION TERMINÉE'), 'la simulation se termine sans erreur', $sortie);
check(str_contains($sortie, '2500  →  7500') && str_contains($sortie, '65000  →  150000') && str_contains($sortie, '150000  →  300000'), 'avant → après affiché pour les prix des trois plans');
check(str_contains($sortie, 'DZD  →  XOF') && str_contains($sortie, 'Essai (jours)') && str_contains($sortie, 'devise_reference'), 'devise, essai et réglages globaux affichés');
check($somme(['platform_plans', 'platform_settings', 'platform_audit_logs']) === $planSomme, 'aucune écriture : plans, réglages et audit identiques');

// ═══════════════════════════════════════════════════════════
section('2. Garde-fous');
[$code, $sortie] = script($nomCopie, [], 'ecole_app');
check($code === 2 && str_contains($sortie, 'REFUS'), 'cible de test : la base vivante « ecole_app » est refusée');
[$code, $sortie] = script($nomCopie, [], 'autre_base');
check($code === 2, 'cible de test : toute base qui n\'est pas ecole_app_copy_* est refusée');
[$code, $sortie] = script($nomCopie, ['--appliquer']);
check($code === 1 && str_contains($sortie, '--sauvegarde=<fichier> est obligatoire'), 'application sans sauvegarde : refusée');
[$code, $sortie] = script($nomCopie, ['--appliquer', '--sauvegarde=database/backups/auto_ecole_app_20000101_000000.sql']);
check($code === 1 && str_contains($sortie, 'database/backups'), 'sauvegarde inexistante : refusée');
[$code, $sortie] = script($nomCopie, ['--appliquer', '--sauvegarde=composer.json']);
check($code === 1, 'fichier hors de database/backups : refusé');
$dossier = ROOT_PATH . '/database/backups';
$cree = [];
$faux = static function (string $nom, string $contenu, ?int $age = null) use ($dossier, &$cree): string {
    $f = $dossier . '/' . $nom; file_put_contents($f, $contenu); $cree[] = $f;
    if ($age !== null) { touch($f, time() - $age); }
    return 'database/backups/' . $nom;
};
try {
    $grand = str_repeat("INSERT INTO t VALUES (1,'x');\n", 3000);
    $f1 = $faux('auto_ecole_app_29990101_000001.sql', $grand . "-- fin tronquée\n");
    [$code, $sortie] = script($nomCopie, ['--appliquer', "--sauvegarde=$f1"]);
    check($code === 1 && str_contains($sortie, 'incomplète'), 'sauvegarde sans marque de fin de dump : refusée');
    $f2 = $faux('auto_ecole_app_29990101_000002.sql', $grand . "-- Dump completed on 2026-11-06\n", 7200);
    [$code, $sortie] = script($nomCopie, ['--appliquer', "--sauvegarde=$f2"]);
    check($code === 1 && str_contains($sortie, 'plus de 60 minutes'), 'sauvegarde de plus de 60 minutes : refusée');
    $f3 = $faux('auto_ecole_app_29990101_000003.sql', "-- Dump completed\n");
    [$code, $sortie] = script($nomCopie, ['--appliquer', "--sauvegarde=$f3"]);
    check($code === 1 && str_contains($sortie, 'petite'), 'sauvegarde anormalement petite : refusée');
    $f4 = $faux('sauvegarde_quelconque.sql', $grand . "-- Dump completed\n");
    [$code, $sortie] = script($nomCopie, ['--appliquer', "--sauvegarde=$f4"]);
    check($code === 1 && str_contains($sortie, 'nom de sauvegarde inattendu'), 'nom de fichier inattendu : refusé');
    $bonne = $faux('auto_ecole_app_29990101_000004.sql', $grand . "-- Dump completed on 2026-11-06\n");
    [$code, $sortie] = script($nomCopie, ['--appliquer', "--sauvegarde=$bonne"]);
    check($code === 1 && str_contains($sortie, '--confirmer-plan-utilise'), 'plan Pro utilisé : confirmation explicite exigée, même avec une bonne sauvegarde', $sortie);
    check($somme(['platform_plans', 'platform_settings', 'platform_audit_logs']) === $planSomme, 'toutes ces refus : rien n\'est écrit');

    // ═══════════════════════════════════════════════════════
    section('3. Application');
    [$code, $sortie] = script($nomCopie, ['--appliquer', "--sauvegarde=$bonne", '--confirmer-plan-utilise']);
    check($code === 0 && str_contains($sortie, 'Sauvegarde contrôlée') && str_contains($sortie, 'sha256'), 'application réussie, sauvegarde contrôlée (empreinte affichée)', $sortie);
    check(str_contains($sortie, 'strictement inchangés'), 'le script constate lui-même : abonnements, établissements et paiements inchangés');
    $apres = $plans();
    $attendu = [
        'starter'    => ['XOF', 7500, 75000, 500, 30, 2048],
        'pro'        => ['XOF', 15000, 150000, 1500, 100, 10240],
        'enterprise' => ['XOF', 30000, 300000, 10000, 500, 51200],
    ];
    foreach ($attendu as $c => [$devise, $m, $a, $el, $us, $st]) {
        $p = $apres[$c];
        check($p['devise'] === $devise && (float)$p['prix_mensuel'] === (float)$m && (float)$p['prix_annuel'] === (float)$a && (int)$p['max_eleves'] === $el && (int)$p['max_users'] === $us && (int)$p['storage_quota_mb'] === $st,
            "plan $c : $devise, $m / $a, $el élèves, $us utilisateurs, $st Mo", json_encode($p));
        check((int)$p['duree_essai_jours'] === 15 && (int)$p['jours_grace'] === 5, "plan $c : 15 jours d'essai et 5 jours de grâce");
        check(abs((float)$p['prix_annuel'] - 10 * (float)$p['prix_mensuel']) < 0.001, "plan $c : règle annuelle = 10 mensualités");
        check(fmod((float)$p['prix_mensuel'], 1) === 0.0 && fmod((float)$p['prix_annuel'], 1) === 0.0, "plan $c : montants entiers (le XOF n'a pas de décimales)");
        check($p['updated_at'] !== null, "plan $c : date de modification renseignée");
    }
    $reg = (new \Core\Platform\PlatformSettings($pdo))->tous();
    check(($reg['devise_reference']['effective'] ?? null) === 'XOF' && ($reg['devise_reference']['source'] ?? null) === 'base'
        && ($reg['essai_defaut_jours']['effective'] ?? null) === '15' && ($reg['grace_defaut_jours']['effective'] ?? null) === '5', 'réglages globaux : devise XOF, essai 15, grâce 5 (enregistrés)', json_encode(array_map(fn($r) => [$r['effective'] ?? null, $r['source'] ?? null], array_intersect_key($reg, array_flip(['devise_reference', 'essai_defaut_jours', 'grace_defaut_jours'])))));
    $aud = $auditPlans();
    check(count($aud) === 3 && array_unique(array_column($aud, 'actor_type')) === ['system'] && array_unique(array_column($aud, 'operator_id')) === [null], 'audit : 3 lignes plan.updated, acteur « système » (aucun opérateur désigné à tort)');
    $pro = array_values(array_filter($aud, fn($l) => str_contains((string)$l['nouvelle_valeur'], 'confirmation_explicite')));
    check(count($pro) === 1 && str_contains((string)$pro[0]['ancienne_valeur'], 'DZD') && str_contains((string)$pro[0]['nouvelle_valeur'], 'XOF'), 'audit du plan Pro : valeurs avant (DZD) et après (XOF), confirmation explicite consignée');

    // ═══════════════════════════════════════════════════════
    section('4. Périmètre : rien d\'autre n\'est touché');
    check($somme($perimetre) === $sommesAvant, 'abonnements, établissements et paiements : empreinte strictement identique', json_encode([$somme($perimetre), $sommesAvant]));
    check($conserve($apres) === $conservesAvant, 'noms, descriptions, modules inclus, appels d\'API, ordre, visibilité et état des plans : inchangés');
    $sub = $pdo->query("SELECT devise, statut, cycle FROM platform_subscriptions WHERE est_courant = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    check($abonnementPro === 0 || ($sub && $sub['devise'] === 'DZD'), 'abonnement existant : il garde son instantané (devise DZD), un changement de plan n\'est pas rétroactif');

    // ═══════════════════════════════════════════════════════
    section('5. Idempotence et reprise');
    $sommeApres = $somme(['platform_plans', 'platform_settings', 'platform_audit_logs']);
    [$code, $sortie] = script($nomCopie, ['--appliquer', "--sauvegarde=$bonne", '--confirmer-plan-utilise']);
    check($code === 0 && str_contains($sortie, 'Rien à faire'), 'relance : « rien à faire »');
    check($somme(['platform_plans', 'platform_settings', 'platform_audit_logs']) === $sommeApres && count($auditPlans()) === 3, 'relance : aucune écriture, aucun nouvel audit');
    [$code, $sortie] = script($nomCopie);
    check($code === 0 && str_contains($sortie, 'Rien à faire'), 'simulation après application : « rien à faire »');
    // Reprise : un plan revient à l'ancienne valeur (restauration partielle) -> seul celui-là est refait.
    $pdo->exec("UPDATE platform_plans SET devise = 'DZD', prix_mensuel = 2500, prix_annuel = 25000 WHERE code = 'starter'");
    [$code, $sortie] = script($nomCopie, ['--appliquer', "--sauvegarde=$bonne"]);
    $apres2 = $plans();
    check($code === 0 && (float)$apres2['starter']['prix_mensuel'] === 7500.0 && $apres2['starter']['devise'] === 'XOF' && !str_contains($sortie, '✓ plan pro') && !str_contains($sortie, '✓ plan enterprise'),
        'reprise : seul le plan revenu à l\'ancienne valeur est refait (le plan Pro, déjà conforme, n\'exige plus de confirmation)', $sortie);
    // Plan absent : refus net, rien n'est écrit.
    $pdo->exec("DELETE FROM platform_plans WHERE code = 'enterprise'");
    $sommeSansEnt = $somme(['platform_plans', 'platform_settings', 'platform_audit_logs']);
    [$code, $sortie] = script($nomCopie, ['--appliquer', "--sauvegarde=$bonne", '--confirmer-plan-utilise']);
    check($code === 1 && str_contains($sortie, "« enterprise » n'existe pas") && $somme(['platform_plans', 'platform_settings', 'platform_audit_logs']) === $sommeSansEnt, 'plan manquant : refus, rien n\'est écrit');
} finally {
    foreach ($cree as $f) { @unlink($f); }
}
check(array_filter(glob($dossier . '/auto_ecole_app_29990101_*.sql') ?: []) === [], 'fichiers de sauvegarde factices supprimés');

check($empreinteVivante() === $vivanteAvant, 'base vivante : plans, réglages, audit et abonnements strictement inchangés');
check(count(array_filter($vivante->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN), fn($d) => str_starts_with($d, 'ecole_app_copy_'))) === 1, 'une seule copie temporaire (la nôtre, supprimée en fin de script)');

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
