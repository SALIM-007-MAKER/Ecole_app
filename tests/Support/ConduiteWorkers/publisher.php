<?php
// Publie un bulletin avec le VRAI code (BulletinGenerator::publierBulletin) dans un processus à part.
// usage: publisher.php <copie> <eleveId> <periodeId> [fichier_attente]
require __DIR__ . '/common.php';
[, $db, $eleve, $periode] = $argv;
$attente = $argv[4] ?? null;
charger_application($db);
if ($attente !== null) { $t = microtime(true); while (!file_exists($attente) && microtime(true) - $t < 20) { usleep(50000); } }
$calc = new App\Modules\Academique\Services\AcademicCalculationService(['note_passage' => 10.0]);
$rank = new App\Modules\Academique\Services\RankingEngine($calc, new App\Modules\Academique\Repositories\RankingRepository(), ['note_passage' => 10.0]);
$gen = new App\Modules\Academique\Services\BulletinGenerator($calc, $rank, new App\Modules\Academique\Repositories\BulletinRepository(), 8.0, 10.0, null, null, null, App\Modules\Academique\Services\BulletinTokenService::fromEnvironment());
$repo = new App\Modules\Academique\Repositories\BulletinRepository();
$row = $repo->findByEleveEtPeriode((int)$eleve, (int)$periode);
$t0 = microtime(true);
try {
    $gen->publierBulletin((string)$row['verification_token'], 1);
    $res = 'PUBLIEE';
} catch (Throwable $e) {
    $res = 'REFUSEE : ' . substr($e->getMessage(), 0, 110);
}
echo json_encode(['resultat' => $res, 'ms' => (int)round((microtime(true) - $t0) * 1000)], JSON_UNESCAPED_UNICODE) . "\n";
