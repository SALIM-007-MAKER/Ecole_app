<?php
/**
 * Tests — requêtes compatibles avec le sql_mode STRICT de WAMP/MySQL 8 (ONLY_FULL_GROUP_BY, DISTINCT + ORDER BY).
 *
 * Régressions corrigées :
 *  - FinancialReportRepository::getEvolutionMensuelle : colonne mois_label absente du GROUP BY (tableau de bord financier) ;
 *  - CashRegisterRepository::getCaissiers : DISTINCT avec ORDER BY sur une colonne hors de la liste SELECT (page Caisse) ;
 *  - TimetableRepository::countHeuresByEnseignant : colonne non agrégée dans une sous-requête (emplois du temps enseignant), ET calcul faux
 *    (nombre de créneaux × durée d'une plage quelconque) remplacé par la somme réelle des durées.
 *
 * Travaille sur une copie jetable de la base, avec ONLY_FULL_GROUP_BY imposé explicitement à la session.
 *
 * Lance : C:\wamp64\bin\php\php8.3.28\php.exe tests/Unit/SqlStrictModeTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\' => '/app/'] as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php';
            if (is_file($f)) { require $f; return; }
        }
    }
});
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use App\Modules\Finance\Repositories\CashRegisterRepository;
use App\Modules\Finance\Repositories\FinancialReportRepository;
use App\Modules\VieScolaire\EmploisDuTemps\Repositories\TimetableRepository;
use Core\Database;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
function leve(callable $f): ?string { try { $f(); return null; } catch (\Throwable $e) { return $e->getMessage(); } }

$_SESSION = [];
\Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible\n");
$pdo = Database::getInstance()->getConnection();
// Mode strict de WAMP imposé explicitement : le test échoue aussi sur un poste dont le mode serait plus permissif
$pdo->exec("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
check(str_contains((string)$pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn(), 'ONLY_FULL_GROUP_BY'), 'session en ONLY_FULL_GROUP_BY');

section('1. Tableau de bord financier : évolution mensuelle');
$rapport = new FinancialReportRepository();
$err = leve(fn() => $rapport->getEvolutionMensuelle((int)date('Y')));
check($err === null, 'getEvolutionMensuelle : requête valide en mode strict', (string)$err);
check(is_array($rapport->getEvolutionMensuelle(2026)), 'résultat : une liste (vide sur une base sans paiement)');

section('2. Caisse : liste des caissiers');
$caisse = new CashRegisterRepository();
$err = leve(fn() => $caisse->getCaissiers());
check($err === null, 'getCaissiers : requête valide en mode strict', (string)$err);

section('3. Emplois du temps enseignant : heures par matière');
$timetable = new TimetableRepository();
$err = leve(fn() => $timetable->countHeuresByEnseignant(1, '2025-2026'));
check($err === null, 'countHeuresByEnseignant : requête valide en mode strict', (string)$err);

// Données minimales (contraintes de clés désactivées sur la copie jetable) : deux plages de durées DIFFÉRENTES
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$plage = function (string $libelle, string $debut, string $fin) use ($pdo): int {
    $pdo->prepare('INSERT INTO vs_edt_plages_horaires (libelle, heure_debut, heure_fin) VALUES (?, ?, ?)')->execute([$libelle, $debut, $fin]);
    return (int)$pdo->lastInsertId();
};
$p1h = $plage('Test 1h', '08:00:00', '09:00:00');
$p2h = $plage('Test 2h', '09:00:00', '11:00:00');
$pdo->exec("INSERT INTO vs_emplois_du_temps (annee_scolaire, classe_id, cree_par) VALUES ('2099-2100', 9991, 1)");
$edt = (int)$pdo->lastInsertId();
$creneau = function (int $jour, int $plageId, int $matiere) use ($pdo, $edt): void {
    $pdo->prepare("INSERT INTO vs_edt_creneaux (annee_scolaire, classe_id, emploi_du_temps_id, enseignant_id, jour, matiere_id, plage_id) VALUES ('2099-2100', 9991, ?, 777, ?, ?, ?)")
        ->execute([$edt, $jour, $matiere, $plageId]);
};
$creneau(1, $p1h, 9001);   // matière 9001 : 1 h
$creneau(2, $p2h, 9001);   //                + 2 h = 3 h
$creneau(3, $p2h, 9002);   // matière 9002 : 2 h
$pdo->exec("INSERT INTO matieres (id, etablissement_id, nom, coefficient, categorie, volume_horaire) VALUES (9001, 1, 'Matière test A', 1, 'autre', 3), (9002, 1, 'Matière test B', 1, 'autre', 2)");
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
$lignes = $timetable->countHeuresByEnseignant(777, '2099-2100');
$par = array_column($lignes, null, 'matiere_id');
check(count($lignes) === 2, 'une ligne par matière', (string)count($lignes));
check((int)($par[9001]['nb_creneaux'] ?? 0) === 2 && abs((float)($par[9001]['heures_estimees'] ?? 0) - 3.0) < 0.001, 'matière sur deux plages (1 h + 2 h) : 2 créneaux, 3,0 heures (somme réelle)', json_encode($par[9001] ?? null));
check((int)($par[9002]['nb_creneaux'] ?? 0) === 1 && abs((float)($par[9002]['heures_estimees'] ?? 0) - 2.0) < 0.001, 'matière sur une plage de 2 h : 1 créneau, 2,0 heures', json_encode($par[9002] ?? null));
check((float)$lignes[0]['nb_creneaux'] >= (float)$lignes[1]['nb_creneaux'], 'tri par nombre de créneaux décroissant conservé');

echo "\n" . ($failed === 0 ? "\033[32m" : "\033[31m") . "$passed OK, $failed KO\033[0m\n";
exit($failed === 0 ? 0 : 1);
