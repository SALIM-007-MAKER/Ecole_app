<?php
/**
 * Tests — routage des identifiants numériques et validations de saisie (parcours métier, écarts restants).
 * Sans base de données : réflexion sur Core\Router::pathToRegex et DTO purs.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/RoutesEtSaisiesTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
spl_autoload_register(function (string $c): void {
    foreach (["Core\\" => '/core/', "App\\" => '/app/'] as $pre => $dir) {
        if (str_starts_with($c, $pre)) { $f = ROOT_PATH . $dir . str_replace("\\", '/', substr($c, strlen($pre))) . '.php'; if (is_file($f)) { require_once $f; } return; }
    }
});

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}

echo "\n\033[33m── 1. Routeur : identifiants numériques\033[0m\n";
$r = (new ReflectionClass(\Core\Router::class))->newInstanceWithoutConstructor();
$m = new ReflectionMethod($r, 'pathToRegex'); $m->setAccessible(true);
$re = fn(string $path) => $m->invoke($r, $path);
check(preg_match($re('/v2/academique/periodes/{id}'), '/v2/academique/periodes/42') === 1, '{id} accepte un nombre');
check(preg_match($re('/v2/academique/periodes/{id}'), '/v2/academique/periodes/generer') === 0, '{id} refuse un mot (« generer » n\'est plus pris pour un identifiant)');
check(preg_match($re('/classes/{id}/edit'), '/classes/7/edit') === 1 && preg_match($re('/classes/{id}/edit'), '/classes/x/edit') === 0, '{id} au milieu d\'un chemin');
check(preg_match($re('/b/{eleveId}/{periodeId}/imprimer'), '/b/3/9/imprimer') === 1 && preg_match($re('/b/{eleveId}/{periodeId}/imprimer'), '/b/a/9/imprimer') === 0, '{xxxId} numérique');
check(preg_match($re('/rapports/export/excel/{type}'), '/rapports/export/excel/scolaire') === 1, '{type} reste textuel');
check(preg_match($re('/v2/academique/bulletins/verify/{token}'), '/v2/academique/bulletins/verify/abc123def') === 1, '{token} reste textuel');

echo "\n\033[33m── 2. Absence : date\033[0m\n";
$futur = date('Y-m-d', strtotime('+3 days'));
$dto = fn(string $d, ?string $h1 = null, ?string $h2 = null) => \App\Modules\VieScolaire\Absences\DTO\AbsenceDTO::fromRequest(['eleve_id' => 1, 'date_absence' => $d, 'type' => 'absence', 'heure_debut' => (string)$h1, 'heure_fin' => (string)$h2, 'duree_heures' => '', 'observation' => '']);
check($dto($futur)->validate() !== [], 'une date dans le futur est refusée');
check($dto(date('Y-m-d'))->validate() === [], 'la date du jour est acceptée');
check($dto('2026-01-10')->validate() === [], 'une date passée est acceptée');

echo "\n\033[33m── 3. Note : valeur numérique\033[0m\n";
check(\App\Modules\Academique\DTO\NoteDTO::nombre('12,5') === 12.5 && \App\Modules\Academique\DTO\NoteDTO::nombre('12.5') === 12.5 && \App\Modules\Academique\DTO\NoteDTO::nombre(' 0 ') === 0.0, 'virgule et point décimaux acceptés');
check(\App\Modules\Academique\DTO\NoteDTO::nombre('abc') === null && \App\Modules\Academique\DTO\NoteDTO::nombre('1e999') === null && \App\Modules\Academique\DTO\NoteDTO::nombre([]) === null, 'texte, infini, tableau : non numériques');
$n = \App\Modules\Academique\DTO\NoteDTO::fromRequest(['evaluation_id' => 1, 'eleve_id' => 2, 'valeur' => 'abc']);
check($n->valeurInvalide === true && $n->valeur === null, 'NoteDTO marque « abc » invalide au lieu de le convertir en 0');
$n2 = \App\Modules\Academique\DTO\NoteDTO::fromRequest(['evaluation_id' => 1, 'eleve_id' => 2, 'valeur' => '']);
check($n2->valeurInvalide === false && $n2->valeur === null, 'valeur vide : ni invalide ni zéro');

echo "\n" . ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : $passed/" . ($passed + $failed) . " assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
