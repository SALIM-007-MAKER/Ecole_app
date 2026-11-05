<?php
/**
 * Tests — bascule des calculs vers le coefficient (et le volume horaire) PROPRE À LA CLASSE (sous-étape 3b).
 *
 * Sites concernés : BulletinRepository, RankingRepository (4 requêtes), AnalyticsRepository, EvaluationRepository, EnseignementModel.
 *
 * 1. Référence « avant » : résultats complets AVANT la migration T075 (code inerte, comportement historique)
 * 2. NON-RÉGRESSION : table créée mais vide → résultats strictement identiques à la référence (tous les sites)
 * 3. Programme d'une classe : seule cette classe change ; moyenne générale recalculée = formule Σ(moyenne × coefficient) / Σ(coefficient)
 * 4. Classes parallèles indépendantes dans une même requête (classement par niveau)
 * 5. Bulletins : aucun recalcul automatique, snapshot figé inchangé, aperçu = régénération explicite
 * 6. Affichages : évaluations, analyses, enseignements
 * 7. Réversibilité : retrait du programme = résultats d'origine ; retour arrière T075 = résultats d'origine
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/ProgrammeCalculsTest.php
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
require_once ROOT_PATH . '/app/Services/ProgrammeClasseService.php';
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use App\Models\EnseignementModel;
use App\Modules\Academique\DTO\BulletinData;
use App\Modules\Academique\Repositories\AnalyticsRepository;
use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Repositories\EvaluationRepository;
use App\Modules\Academique\Repositories\RankingRepository;
use App\Modules\Academique\Services\AcademicCalculationService;
use App\Modules\Academique\Services\BulletinGenerator;
use App\Modules\Academique\Services\RankingEngine;
use App\Services\ProgrammeClasseService as P;
use App\Services\ProgrammeSql;
use Core\Database;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
function empreinte(mixed $v): string { return md5(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)); }

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$pdo = Database::getInstance()->getConnection();
$admin = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' AND etablissement_id = 1 ORDER BY id LIMIT 1")->fetchColumn();

$calc = new AcademicCalculationService();
$rrepo = new RankingRepository();
$arepo = new AnalyticsRepository();
$erepo = new EvaluationRepository();
$ens = new EnseignementModel();
$engine = new RankingEngine($calc, $rrepo);
$gen = new BulletinGenerator($calc, $engine, new BulletinRepository());
$brepo = new BulletinRepository();

$mid = static fn(string $nom): int => (int)$pdo->query("SELECT id FROM matieres WHERE etablissement_id = 1 AND nom = " . $pdo->quote($nom))->fetchColumn();
$MATHS = $mid('Mathématiques'); $FRANCAIS = $mid('Français'); $ANGLAIS = $mid('Anglais');
$e131 = (int)$pdo->query('SELECT id FROM eleves WHERE classe_id = 131 AND actif = 1 ORDER BY id LIMIT 1')->fetchColumn();
$eleves131 = array_map('intval', $pdo->query('SELECT id FROM eleves WHERE classe_id = 131 AND actif = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
$e132 = (int)$pdo->query('SELECT id FROM eleves WHERE classe_id = 132 AND actif = 1 ORDER BY id LIMIT 1')->fetchColumn();
$prof = (int)$pdo->query('SELECT professeur_id FROM enseignements WHERE classe_id = 131 ORDER BY id LIMIT 1')->fetchColumn();
$evalMaths131 = (int)$pdo->query("SELECT id FROM evaluations WHERE classe_id = 131 AND matiere_id = $MATHS AND periode_scolaire_id = 90 ORDER BY id LIMIT 1")->fetchColumn();
if ($e131 === 0 || $e132 === 0 || $evalMaths131 === 0) { exit("ECHEC : jeu de démonstration inattendu\n"); }

/** Résultats de TOUS les sites concernés, sous forme d'empreintes comparables. @return array<string,string> */
$snapshot = static function () use ($rrepo, $arepo, $erepo, $ens, $engine, $gen, $brepo, $MATHS, $e131, $e132, $prof, $evalMaths131, $pdo): array {
    $ligneBulletin = static fn(BulletinData $b) => ['moy' => $b->moyennePeriode, 'rang' => $b->rang, 'lignes' => array_map(fn($l) => [$l['matiere_id'], $l['coefficient'], $l['moyenne']], $b->lignesMatieres)];
    $classement = static fn($r) => ['rankings' => $r->rankings, 'stats' => $r->statistiques];
    return [
        'ranking_classe_131'      => empreinte($rrepo->notesParClasseEtPeriode(131, 90)),
        'ranking_classe_132'      => empreinte($rrepo->notesParClasseEtPeriode(132, 101)),
        'ranking_niveau'          => empreinte([$rrepo->notesParNiveauEtPeriode('Seconde', 90), $rrepo->notesParNiveauEtPeriode('Seconde', 101)]),
        'ranking_matiere'         => empreinte([$rrepo->notesParMatiereEtPeriode($MATHS, 90), $rrepo->notesParMatiereEtPeriode($MATHS, 90, 131)]),
        'ranking_multi'           => empreinte($rrepo->notesParClasseMultiPeriodes(131, [90, 91])),
        'bulletin_lignes_131'     => empreinte($brepo->notesEleveParPeriode($e131, 131, 90)),
        'bulletin_lignes_132'     => empreinte($brepo->notesEleveParPeriode($e132, 132, 101)),
        'analytics_131'           => empreinte($arepo->moyennesParMatiere(90, 131)),
        'analytics_toutes'        => empreinte($arepo->moyennesParMatiere(90, null)),
        'evaluation_detail'       => empreinte($erepo->findWithDetails($evalMaths131)),
        'evaluation_liste'        => empreinte($erepo->paginate([], 1, 200)),
        'enseignements_131'       => empreinte($ens->findByClasseId(131)),
        'enseignements_132'       => empreinte($ens->findByClasseId(132)),
        'enseignements_prof'      => empreinte($ens->findByProfesseurId($prof)),
        'classement_engine_131'   => empreinte($classement($engine->classementClasse(131, 90))),
        'classement_engine_132'   => empreinte($classement($engine->classementClasse(132, 101))),
        'bulletin_apercu_131'     => empreinte($ligneBulletin($gen->previewBulletin($e131, 90))),
        'bulletin_apercu_132'     => empreinte($ligneBulletin($gen->previewBulletin($e132, 101))),
    ];
};
/** Moyenne attendue : Σ(moyenne × coefficient) / Σ(coefficient) sur les lignes de matières qui ont une moyenne. */
$attendue = static function (BulletinData $b, bool $avecConduite): float {
    $somme = 0.0; $coef = 0.0;
    foreach ($b->lignesMatieres as $l) {
        if ($l['moyenne'] === null || ($l['est_conduite'] && !$avecConduite)) { continue; }
        $somme += (float)$l['moyenne'] * (float)$l['coefficient'];
        $coef += (float)$l['coefficient'];
    }
    return $coef > 0 ? round($somme / $coef, 2) : 0.0;
};
$coefMatiere = static function (BulletinData $b, int $matiereId): ?float {
    foreach ($b->lignesMatieres as $l) { if ((int)$l['matiere_id'] === $matiereId) { return (float)$l['coefficient']; } }
    return null;
};

// ═══════════════════════════════════════════════════════════
section('1. Référence : AVANT la migration (code inerte)');
P::oublierDetection();
check(P::disponible() === false && ProgrammeSql::actif() === false, 'table classe_matieres absente : fragments SQL en mode historique');
check(ProgrammeSql::coefficient('m') === 'm.coefficient' && ProgrammeSql::jointure('ev.classe_id') === ' ' && ProgrammeSql::volume('m') === 'm.volume_horaire', 'fragments : colonnes de la matière, aucune jointure');
$base = $snapshot();
check(count($base) === 18 && !in_array(empreinte([]), $base, true), 'référence : 18 jeux de résultats, aucun vide (le jeu de démonstration alimente tous les sites)', json_encode(array_keys(array_filter($base, fn($h) => $h === empreinte([])))));
$bBase = $gen->previewBulletin($e131, 90);
$avecConduite = abs($attendue($bBase, true) - $bBase->moyennePeriode) < 0.011;
$sansConduite = abs($attendue($bBase, false) - $bBase->moyennePeriode) < 0.011;
check($avecConduite || $sansConduite, 'la formule Σ(moyenne × coefficient) / Σ(coefficient) reproduit la moyenne du bulletin avant migration', "bulletin={$bBase->moyennePeriode} avec={$attendue($bBase, true)} sans={$attendue($bBase, false)}");
$conduite = $avecConduite;
$coefDefautMaths = $coefMatiere($bBase, $MATHS);
check($coefDefautMaths === 5.0, 'Mathématiques : coefficient par défaut 5 avant migration', (string)$coefDefautMaths);

// ═══════════════════════════════════════════════════════════
section('2. NON-RÉGRESSION : table créée mais vide');
$m = require ROOT_PATH . '/database/migrations/T075_classe_matieres.php';
$m['run']($pdo);
P::oublierDetection();
check(P::disponible() === true && ProgrammeSql::actif() === true, 'table présente : jointures actives');
check(str_contains(ProgrammeSql::coefficient('m'), 'COALESCE(cmp.coefficient, m.coefficient)') && str_contains(ProgrammeSql::jointure('ev.classe_id'), 'LEFT JOIN classe_matieres cmp'), 'fragments : COALESCE(programme, matière) et LEFT JOIN');
$apres = $snapshot();
foreach ($base as $cle => $h) {
    check($apres[$cle] === $h, "identique à la référence : $cle");
}

// ═══════════════════════════════════════════════════════════
section('3. Programme d\'une classe : seule cette classe change');
$svc = new P($pdo, 1, $admin);
$svc->ajouter(131, $MATHS, '2', '5');
$rows131 = $rrepo->notesParClasseEtPeriode(131, 90);
$cm = array_unique(array_map(fn($r) => (float)$r->coeff_matiere, array_filter($rows131, fn($r) => (int)$r->matiere_id === $MATHS)));
$autres = array_unique(array_map(fn($r) => (int)$r->matiere_id . ':' . (float)$r->coeff_matiere, array_filter($rows131, fn($r) => (int)$r->matiere_id !== $MATHS)));
check($cm === [2.0], 'classement : Mathématiques à 2 dans la classe 131 (programme)', json_encode(array_values($cm)));
$defauts = [];
foreach ($pdo->query("SELECT id, coefficient FROM matieres WHERE etablissement_id = 1")->fetchAll(PDO::FETCH_ASSOC) as $r) { $defauts[(int)$r['id'] . ':' . (float)$r['coefficient']] = true; }
check(array_diff($autres, array_keys($defauts)) === [], 'classement : les autres matières de 131 gardent leur coefficient par défaut');
$apres = $snapshot();
foreach (['ranking_classe_132', 'bulletin_lignes_132', 'enseignements_132', 'classement_engine_132', 'bulletin_apercu_132'] as $cle) {
    check($apres[$cle] === $base[$cle], "classe 132 (sans programme) strictement inchangée : $cle");
}
foreach (['ranking_classe_131', 'ranking_multi', 'bulletin_lignes_131', 'enseignements_131', 'bulletin_apercu_131', 'classement_engine_131'] as $cle) {
    check($apres[$cle] !== $base[$cle], "classe 131 : résultat modifié par son programme : $cle");
}
$b = $gen->previewBulletin($e131, 90);
check($coefMatiere($b, $MATHS) === 2.0 && $coefMatiere($b, $FRANCAIS) === (float)$pdo->query("SELECT coefficient FROM matieres WHERE id = $FRANCAIS")->fetchColumn(), 'bulletin : Mathématiques à 2, Français à son coefficient par défaut');
check(abs($attendue($b, $conduite) - $b->moyennePeriode) < 0.011, 'moyenne générale recalculée = Σ(moyenne × coefficient) / Σ(coefficient) avec le coefficient de la classe', "bulletin={$b->moyennePeriode} attendu={$attendue($b, $conduite)}");
check(abs($b->moyennePeriode - $bBase->moyennePeriode) > 0.001, 'la moyenne générale a effectivement changé', "{$bBase->moyennePeriode} → {$b->moyennePeriode}");
$svc->enregistrer(131, [$MATHS => ['coefficient' => '20']], true);
$b20 = $gen->previewBulletin($e131, 90);
check($coefMatiere($b20, $MATHS) === 20.0 && abs($attendue($b20, $conduite) - $b20->moyennePeriode) < 0.011, 'coefficient 20 (borne haute) : appliqué, formule vérifiée');
$svc->enregistrer(131, [$MATHS => ['coefficient' => '0']], true);
$b0 = $gen->previewBulletin($e131, 90);
$lm = array_values(array_filter($b0->lignesMatieres, fn($l) => (int)$l['matiere_id'] === $MATHS))[0] ?? null;
check($lm !== null && (float)$lm['coefficient'] === 0.0 && abs($attendue($b0, $conduite) - $b0->moyennePeriode) < 0.011, 'coefficient 0 : matière suivie mais non comptée dans la moyenne, formule vérifiée');
$svc->enregistrer(131, [$MATHS => ['coefficient' => '2']], true);

// ═══════════════════════════════════════════════════════════
section('4. Classes parallèles indépendantes dans une même requête');
$pdo->exec("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire, max_eleves) VALUES (1, 'X2', 'Seconde', '2025-2026', 30)");
$X = (int)$pdo->lastInsertId();
$moved = $eleves131[1];
$pdo->exec("UPDATE eleves SET classe_id = $X WHERE id = $moved");
foreach ($pdo->query("SELECT * FROM evaluations WHERE classe_id = 131 AND periode_scolaire_id = 90 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $ev) {
    $old = (int)$ev['id'];
    unset($ev['id']);
    $ev['classe_id'] = $X;
    $cols = implode('`, `', array_keys($ev));
    $pdo->prepare("INSERT INTO evaluations (`$cols`) VALUES (" . implode(', ', array_fill(0, count($ev), '?')) . ")")->execute(array_values($ev));
    $new = (int)$pdo->lastInsertId();
    foreach ($pdo->query("SELECT * FROM notes_v2 WHERE evaluation_id = $old AND eleve_id = $moved")->fetchAll(PDO::FETCH_ASSOC) as $n) {
        unset($n['id']); $n['evaluation_id'] = $new;
        $nc = implode('`, `', array_keys($n));
        $pdo->prepare("INSERT INTO notes_v2 (`$nc`) VALUES (" . implode(', ', array_fill(0, count($n), '?')) . ")")->execute(array_values($n));
    }
}
$niv = $rrepo->notesParNiveauEtPeriode('Seconde', 90);
$coefsParClasse = static function (array $rows) use ($MATHS): array {
    $o = [];
    foreach ($rows as $r) { if ((int)$r->matiere_id === $MATHS) { $o[(int)$r->classe_id][(string)(float)$r->coeff_matiere] = true; } }
    return array_map(fn($k) => array_map('floatval', array_keys($k)), $o);
};
$c = $coefsParClasse($niv);
check(($c[131] ?? null) === [2.0] && ($c[$X] ?? null) === [5.0], 'classement par niveau : classe 131 (programme) à 2 ET classe parallèle sans programme à 5 dans la MÊME requête', json_encode($c));
$svc->ajouter($X, $MATHS, '4', '5');
$c = $coefsParClasse($rrepo->notesParNiveauEtPeriode('Seconde', 90));
check(($c[131] ?? null) === [2.0] && ($c[$X] ?? null) === [4.0], 'chaque classe parallèle a son propre coefficient (2 et 4)', json_encode($c));
$cMat = $coefsParClasse($rrepo->notesParMatiereEtPeriode($MATHS, 90));
check(($cMat[131] ?? null) === [2.0] && ($cMat[$X] ?? null) === [4.0], 'classement par matière : coefficients propres à chaque classe', json_encode($cMat));
$bx = $gen->previewBulletin($moved, 90);
check($coefMatiere($bx, $MATHS) === 4.0 && abs($attendue($bx, $conduite) - $bx->moyennePeriode) < 0.011, 'bulletin de l\'élève de la classe parallèle : coefficient 4, moyenne conforme à la formule');
$b131bis = $gen->previewBulletin($e131, 90);
check($coefMatiere($b131bis, $MATHS) === 2.0, 'bulletin de la classe 131 : toujours 2 (indépendant de la classe parallèle)');
$svc->retirer($X, $MATHS, true);
check($coefsParClasse($rrepo->notesParNiveauEtPeriode('Seconde', 90))[$X] === [5.0], 'retrait du programme de la classe parallèle : retour à la valeur par défaut (5)');

// ═══════════════════════════════════════════════════════════
section('5. Bulletins : aucun recalcul automatique, snapshot figé');
$bul = $pdo->query("SELECT * FROM bulletins_v2 WHERE classe_id = 131 AND data_json IS NOT NULL ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check($bul !== false, 'jeu de démonstration : un bulletin de la classe 131 avec snapshot');
$allBulletins = $pdo->query('SELECT * FROM bulletins_v2 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$snapAvant = BulletinData::fromArray(json_decode((string)$bul['data_json'], true) ?: []);
$svc->enregistrer(131, [$MATHS => ['coefficient' => '3']], true);
check($pdo->query('SELECT * FROM bulletins_v2 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $allBulletins, 'changer le programme ne modifie AUCUN bulletin (36 bulletins strictement identiques)');
$snapApres = BulletinData::fromArray(json_decode((string)$pdo->query("SELECT data_json FROM bulletins_v2 WHERE id = {$bul['id']}")->fetchColumn(), true) ?: []);
check($coefMatiere($snapApres, $MATHS) === $coefMatiere($snapAvant, $MATHS) && $snapApres->moyennePeriode === $snapAvant->moyennePeriode, 'le snapshot du bulletin garde ses coefficients et sa moyenne d\'origine (pas de recalcul automatique)', (string)$coefMatiere($snapApres, $MATHS));
$apercu = $gen->previewBulletin((int)$bul['eleve_id'], (int)$bul['periode_id']);
check($coefMatiere($apercu, $MATHS) === 3.0, 'un NOUVEL aperçu (action explicite) utilise le programme courant (3), sans rien persister');
check($pdo->query('SELECT * FROM bulletins_v2 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $allBulletins, 'l\'aperçu n\'écrit rien');
$pdo->exec("UPDATE bulletins_v2 SET statut = 'publie' WHERE id = {$bul['id']}");
check($svc->verrou(131) !== null, 'dès qu\'un bulletin est publié : le programme est verrouillé');
$avant = $pdo->query('SELECT coefficient FROM classe_matieres WHERE classe_id = 131 AND matiere_id = ' . $MATHS)->fetchColumn();
check((function () use ($svc, $MATHS) { try { $svc->enregistrer(131, [$MATHS => ['coefficient' => '9']], true); return false; } catch (\Throwable $e) { return true; } })()
    && $pdo->query('SELECT coefficient FROM classe_matieres WHERE classe_id = 131 AND matiere_id = ' . $MATHS)->fetchColumn() === $avant, 'modification refusée : le classement en direct ne peut plus diverger du bulletin figé');
$pdo->exec("UPDATE bulletins_v2 SET statut = 'brouillon' WHERE id = {$bul['id']}");

// ═══════════════════════════════════════════════════════════
section('6. Affichages : évaluations, analyses, enseignements');
$ev = $erepo->findWithDetails($evalMaths131);
check((float)$ev->matiere_coef === 3.0, 'évaluation de Mathématiques de la classe 131 : coefficient de la matière affiché = 3 (programme)', (string)$ev->matiere_coef);
$ev132 = (int)$pdo->query("SELECT id FROM evaluations WHERE classe_id = 132 AND matiere_id = $MATHS ORDER BY id LIMIT 1")->fetchColumn();
if ($ev132 > 0) {
    check((float)$erepo->findWithDetails($ev132)->matiere_coef === 5.0, 'évaluation de Mathématiques de la classe 132 : 5 (valeur par défaut)');
}
$an = array_column($arepo->moyennesParMatiere(90, 131), null, 'matiere_id');
check(isset($an[$MATHS]) && (float)$an[$MATHS]['coefficient'] === 3.0, 'analyses d\'UNE classe : coefficient du programme (3)', json_encode($an[$MATHS] ?? null));
$anTous = array_column($arepo->moyennesParMatiere(90, null), null, 'matiere_id');
check(isset($anTous[$MATHS]) && (float)$anTous[$MATHS]['coefficient'] === 5.0, 'analyses toutes classes confondues : coefficient par défaut de la matière (5)');
check(count($an) > 0 && count($anTous) > 0, 'analyses : requêtes valides (GROUP BY accepté en mode strict)');
$en131 = array_column($ens->findByClasseId(131), null, 'matiere_id');
$mathsEns = array_values(array_filter($ens->findByClasseId(131), fn($r) => (int)$r->matiere_id === $MATHS));
check($mathsEns !== [] && (float)$mathsEns[0]->coefficient === 3.0 && (int)$mathsEns[0]->volume_horaire === 5, 'onglet Enseignements de la classe : coefficient 3 et volume du programme');
$mathsEns132 = array_values(array_filter($ens->findByClasseId(132), fn($r) => (int)$r->matiere_id === $MATHS));
check($mathsEns132 === [] || (float)$mathsEns132[0]->coefficient === 5.0, 'classe 132 : coefficient par défaut');
$svc->enregistrer(131, [$MATHS => ['volume_horaire' => '8']]);
$mathsEns = array_values(array_filter($ens->findByClasseId(131), fn($r) => (int)$r->matiere_id === $MATHS));
check((int)$mathsEns[0]->volume_horaire === 8 && (float)$pdo->query("SELECT volume_horaire FROM matieres WHERE id = $MATHS")->fetchColumn() === 5.0, 'volume horaire : propre à la classe (8) ; la matière garde 5');
$profEns = $ens->findByProfesseurId($prof);
check(count($profEns) > 0, 'enseignements d\'un professeur : requête valide (GROUP BY accepté), valeurs de la classe');

// ═══════════════════════════════════════════════════════════
section('7. Réversibilité');
$svc->retirer(131, $MATHS, true);
$bRetour = $gen->previewBulletin($e131, 90);
check($coefMatiere($bRetour, $MATHS) === 5.0 && abs($attendue($bRetour, $conduite) - $bRetour->moyennePeriode) < 0.011, 'programme retiré : coefficient d\'origine (5) retrouvé, formule vérifiée');
$pdo->exec("UPDATE eleves SET classe_id = 131 WHERE id = $moved");
$pdo->exec("DELETE FROM notes_v2 WHERE evaluation_id IN (SELECT id FROM evaluations WHERE classe_id = $X)");
$pdo->exec("DELETE FROM evaluations WHERE classe_id = $X");
$pdo->exec("DELETE FROM classes WHERE id = $X");
$final = $snapshot();
foreach ($base as $cle => $h) {
    check($final[$cle] === $h, "jeu de démonstration restauré, programme retiré : identique à la référence ($cle)");
}
$m['rollback']($pdo);
P::oublierDetection();
check(ProgrammeSql::actif() === false, 'retour arrière T075 : fragments SQL en mode historique');
$rollback = $snapshot();
foreach ($base as $cle => $h) {
    check($rollback[$cle] === $h, "après retour arrière : identique à la référence ($cle)");
}

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
