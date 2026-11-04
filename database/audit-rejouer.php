<?php

/**
 * Rejoue dans la base les événements d'audit écrits dans les fichiers de secours pendant une panne du journal :
 *   - journal GLOBAL de la plateforme : storage/logs/platform_audit_fallback-*.jsonl  → platform_audit_logs ;
 *   - journal des ÉTABLISSEMENTS      : storage/logs/audit_fallback-*.jsonl           → audit_logs (établissement d'origine conservé).
 * À lancer une fois la base de nouveau disponible : les alertes disparaissent ensuite.
 *
 * Usage : php database/audit-rejouer.php
 *
 * Les lignes rejouées gardent leur date d'origine. Un fichier entièrement rejoué est renommé en « .rejoue » (jamais supprimé) ; les lignes qui
 * échouent encore restent dans le fichier. Aucune donnée existante n'est modifiée (ajout seul). Réservé à l'accès serveur.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $classe): void {
    foreach (['Core\\' => '/core/', 'App\\Services\\' => '/app/Services/', 'App\\Shared\\' => '/app/Shared/'] as $prefixe => $dossier) {
        if (str_starts_with($classe, $prefixe)) {
            $fichier = ROOT_PATH . $dossier . str_replace('\\', '/', substr($classe, strlen($prefixe))) . '.php';
            if (is_file($fichier)) { require $fichier; }
            return;
        }
    }
});

use App\Services\AuditService;
use Core\Platform\PlatformAuditService;

$env = [];
if (is_file(ROOT_PATH . '/.env')) {
    foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
        [$k, $v] = explode('=', $l, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}
foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $cle) {
    $v = getenv($cle);
    if ($v !== false && $v !== '') { $env[$cle] = $v; }
}

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

$enAttentePlateforme = PlatformAuditService::resumeSecours()['count'];
$enAttenteEtablissements = AuditService::totalSecours();
if ($enAttentePlateforme === 0 && $enAttenteEtablissements === 0) {
    echo "Aucun événement en attente dans les fichiers de secours.\n";
    exit(0);
}

$echecs = 0;
if ($enAttentePlateforme > 0) {
    $r = PlatformAuditService::make($pdo)->rejouerSecours();
    echo "Journal global : {$r['rejoues']} événement(s) rejoué(s) ; {$r['echecs']} en échec (conservés dans le fichier de secours).\n";
    $echecs += $r['echecs'];
}
if ($enAttenteEtablissements > 0) {
    $r = (new AuditService())->rejouerSecours($pdo);
    echo "Journaux des établissements : {$r['rejoues']} événement(s) rejoué(s) ; {$r['echecs']} en échec (conservés dans le fichier de secours).\n";
    $echecs += $r['echecs'];
}
exit($echecs > 0 ? 3 : 0);
