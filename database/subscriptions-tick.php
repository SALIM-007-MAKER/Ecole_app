<?php

/**
 * Échéances d'abonnement (CLI, à planifier chaque jour peu après minuit, fuseau PLATFORM_TIMEZONE = Africa/Niamey par défaut).
 *
 *   php database/subscriptions-tick.php [--dry-run]
 *
 * Applique : actif -> grâce -> expiré, essai -> expiré, annulation échue ; suspend AUTOMATIQUEMENT (origine « subscription ») les
 * établissements dont l'accès cesse, y compris par RATTRAPAGE ceux dont l'abonnement est déjà « expired » mais qui sont restés trial/active
 * (seule la dernière période de chaque établissement compte). Une suspension existante — d'opérateur ou historique — n'est jamais modifiée. Idempotent. --dry-run liste sans rien écrire.
 * À lancer d'abord avec --dry-run sur une installation existante : les essais déjà échus seraient suspendus.
 * Sortie : code 0, ou 1 si au moins un abonnement n'a pas pu être traité.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
spl_autoload_register(static function (string $classe): void {
    if (str_starts_with($classe, 'Core\\')) {
        $f = ROOT_PATH . '/core/' . str_replace('\\', '/', substr($classe, 5)) . '.php';
        if (is_file($f)) { require $f; }
    }
});

$env = [];
if (is_file(ROOT_PATH . '/.env')) {
    foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
        [$k, $v] = explode('=', $l, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}
foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'PLATFORM_TIMEZONE'] as $cle) {
    $v = getenv($cle);
    if ($v !== false && $v !== '') { $env[$cle] = $v; }
}
if (isset($env['PLATFORM_TIMEZONE'])) { $_ENV['PLATFORM_TIMEZONE'] = $env['PLATFORM_TIMEZONE']; }

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306', $env['DB_DATABASE'] ?? 'ecole_app'),
        $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'ECHEC : connexion à la base impossible : ' . $e->getMessage() . "\n");
    exit(1);
}

if (!Core\Platform\PlatformSubscriptionService::disponible($pdo)) {
    fwrite(STDERR, "Migration T067 non appliquée : rien à faire.\n");
    exit(0);
}

$dry = in_array('--dry-run', $argv, true);
$res = (new Core\Platform\PlatformSubscriptionService($pdo))->appliquerEcheances($dry);
foreach ($res['transitions'] as $t) {
    printf("%s abonnement #%d (établissement #%d) : %s -> %s%s\n", $dry ? '[simulation]' : '[appliqué]', $t['abonnement_id'], $t['etablissement_id'],
        $t['de'], $t['vers'], $t['suspension_automatique'] ? ' + suspension automatique' : '') . ($t['rattrapage'] ? ' [rattrapage : abonnement déjà expiré]' : '');
}
printf("%d transition(s)%s, %d erreur(s).\n", count($res['transitions']), $dry ? ' (simulation)' : '', $res['erreurs']);
exit($res['erreurs'] > 0 ? 1 : 0);
