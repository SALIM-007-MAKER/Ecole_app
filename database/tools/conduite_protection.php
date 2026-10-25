<?php

/**
 * Contrôle de la protection SQL de la Conduite (déclencheurs T050) — LECTURE SEULE.
 *
 * Usage : C:/wamp64/bin/php/php8.2.29/php.exe database/tools/conduite_protection.php --check
 *
 * Ne lit que information_schema (métadonnées) : aucune écriture, aucune tentative de suppression de matière.
 * Code retour : 0 = protection opérationnelle ; 1 = absente, incomplète ou modifiée ; 2 = erreur d'usage ou de connexion.
 * Un déploiement est considéré échoué si migrate.php ou cet outil retourne autre chose que 0.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/app/Modules/Academique/Services/ConduiteProtectionStatus.php';

use App\Modules\Academique\Services\ConduiteProtectionStatus;

if (!in_array('--check', $argv ?? [], true)) {
    fwrite(STDERR, "Usage : php database/tools/conduite_protection.php --check\n");
    exit(2);
}

$env = [];
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
// Cible alternative réservée aux tests : une copie jetable uniquement.
$base = $env['DB_DATABASE'] ?? 'ecole_app';
$alt = getenv('CONDUITE_CHECK_DB');
if ($alt !== false && $alt !== '') {
    if (!preg_match('/^ecole_app_copy_[a-f0-9]+$/', $alt)) { fwrite(STDERR, "REFUS : CONDUITE_CHECK_DB doit être une copie ecole_app_copy_*\n"); exit(2); }
    $base = $alt;
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'] ?? 'localhost', $env['DB_PORT'] ?? '3306', $base),
        $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $etat = ConduiteProtectionStatus::check($pdo);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . "\n");
    exit(2);
}

echo $etat['message'] . "\n";
foreach ($etat['details'] as $nom => $detail) { echo "  - $nom : $detail\n"; }
exit($etat['ok'] ? 0 : 1);
