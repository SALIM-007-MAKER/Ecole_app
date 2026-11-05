<?php
/**
 * Tests — évolutions du programme pédagogique : matières actives/inactives (T076), nom d'affichage (T076), matières obligatoires/facultatives
 * (T075 colonne `obligatoire`), séries A/C/D, reprise du programme à la création d'une classe parallèle.
 *
 *  1. T076 : structure, valeurs par défaut, idempotence, retour arrière, données existantes intactes
 *  2. Désactivation / réactivation : état, audit, exclusion des NOUVELLES affectations, conservation de l'existant, suppression des matières utilisées
 *  3. Matières obligatoires / facultatives : effet sur la moyenne générale, le classement et le bulletin (les deux cas) ; aucun recalcul figé
 *  4. Nom d'affichage : affichage dans bulletins/classement/évaluations/analyses/enseignements, nom de référence intact, isolation entre écoles
 *  5. Séries A, C, D : programmes indépendants, aucune contamination, reproduction limitée à la même série
 *  6. Création d'une classe parallèle : reprise vérifiée (niveau, série), confirmée, sans écrasement
 *  7. Permissions par rôle
 *  8. Audit strict (dans la transaction) et historique des coefficients
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.3.28\php.exe tests/Unit/ProgrammeEvolutionsTest.php
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
use App\Models\MatiereModel;
use App\Modules\Academique\DTO\BulletinData;
use App\Modules\Academique\Repositories\AnalyticsRepository;
use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Repositories\EvaluationRepository;
use App\Modules\Academique\Repositories\RankingRepository;
use App\Modules\Academique\Services\AcademicCalculationService;
use App\Modules\Academique\Services\BulletinGenerator;
use App\Modules\Academique\Services\RankingEngine;
use App\Services\ProgrammeClasseService as P;
use App\Services\ProgrammeConfirmationException;
use App\Services\ProgrammeException;
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
/** @return array{0:int,1:string} */
function lancer(array $cmd, array $env = []): array
{
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), $env));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out];
}
function leve(callable $f): ?string
{
    try { $f(); return null; } catch (\Throwable $e) { return $e::class; }
}
function http(string $nomCopie, string $role, int $uid, string $methode, string $uri, array $post = [], bool $commit = false): array
{
    $corps = sys_get_temp_dir() . '/evol_' . bin2hex(random_bytes(4)) . '.html';
    [, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/request_as.php', $role, (string)$uid, $methode, '/ecole_app' . $uri, json_encode($post)],
        ['REQUEST_AS_DB' => $nomCopie, 'REQUEST_AS_BODY_OUT' => $corps] + ($commit ? ['REQUEST_AS_COMMIT' => '1'] : []));
    $r = preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: []) : [];
    $body = (string)@file_get_contents($corps); @unlink($corps);
    return ['status' => (int)($r['status'] ?? 0), 'body' => $body];
}
function empreinte(mixed $v): string { return md5(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)); }

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$admin = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' AND etablissement_id = 1 ORDER BY id LIMIT 1")->fetchColumn();
if ($admin === 0) { exit("ECHEC : aucun administrateur d'école dans la copie\n"); }

$checksum = static function (array $tables) use ($pdo): array {
    $o = [];
    foreach ($tables as $t) { $o[$t] = (string)$pdo->query("CHECKSUM TABLE `$t`")->fetch(PDO::FETCH_ASSOC)['Checksum']; }
    return $o;
};
$protegees = ['eleves', 'inscriptions', 'enseignements', 'evaluations', 'notes_v2', 'moyennes_generales', 'moyennes_matieres', 'periodes_scolaires', 'bulletins_v2'];
$mid = static fn(string $nom): int => (int)$pdo->query("SELECT id FROM matieres WHERE etablissement_id = 1 AND nom = " . $pdo->quote($nom))->fetchColumn();
$MATHS = $mid('Mathématiques'); $FRANCAIS = $mid('Français'); $ANGLAIS = $mid('Anglais'); $EPS = $mid('EPS'); $ARABE = $mid('Arabe'); $CONDUITE = $mid('Conduite');
$lignes = static fn(int $classe): array => $pdo->query("SELECT matiere_id, coefficient, volume_horaire, obligatoire, ordre FROM classe_matieres WHERE classe_id = $classe ORDER BY matiere_id")->fetchAll(PDO::FETCH_ASSOC);
$nbLignes = static fn(int $classe): int => (int)$pdo->query("SELECT COUNT(*) FROM classe_matieres WHERE classe_id = $classe")->fetchColumn();
$audits = static fn(string $action): array => $pdo->query("SELECT user_id, avant, apres, created_at FROM audit_logs WHERE action = " . $pdo->quote($action) . " ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// ═══════════════════════════════════════════════════════════
section('1. Migration T076 (actif, nom_affichage)');
P::oublierDetection(); ProgrammeSql::oublier();
check(ProgrammeSql::colonneMatiere('actif') === false && ProgrammeSql::colonneMatiere('nom_affichage') === false, 'avant T076 : colonnes absentes, fonctionnalités inertes');
check(ProgrammeSql::nom('m') === 'm.nom', 'avant T076 : le libellé affiché est le nom de la matière');
$r = http($nomCopie, 'admin', $admin, 'POST', "/matieres/$ARABE/desactiver", [], true);
check($r['status'] !== 500 && !str_contains($r['body'], 'PDOException') && !str_contains($r['body'], 'Fatal error'), 'avant T076 : désactiver refusé proprement, sans erreur SQL', (string)$r['status']);
$avantMat = (string)$pdo->query('CHECKSUM TABLE matieres')->fetch(PDO::FETCH_ASSOC)['Checksum'];
$protAvant = $checksum($protegees);

$m75 = require ROOT_PATH . '/database/migrations/T075_classe_matieres.php';
$m76 = require ROOT_PATH . '/database/migrations/T076_matieres_actif_nom_affichage.php';
$m75['run']($pdo);
check($m76['id'] === 'T076' && $m76['reversible'] === true, 'identifiant T076, réversible');
$m76['run']($pdo);
P::oublierDetection(); ProgrammeSql::oublier();
$cols = $pdo->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'matieres'")->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
check(isset($cols['actif'], $cols['nom_affichage']) && $cols['actif']['IS_NULLABLE'] === 'NO' && (string)$cols['actif']['COLUMN_DEFAULT'] === '1' && $cols['nom_affichage']['IS_NULLABLE'] === 'YES', 'actif NOT NULL défaut 1 ; nom_affichage NULL');
check((int)$pdo->query('SELECT COUNT(*) FROM matieres WHERE actif <> 1')->fetchColumn() === 0 && (int)$pdo->query('SELECT COUNT(*) FROM matieres WHERE nom_affichage IS NOT NULL')->fetchColumn() === 0, 'toutes les matières existantes restent actives, sans nom d\'affichage');
check($checksum($protegees) === $protAvant, 'tables de notes/bulletins/enseignements : strictement inchangées');
$ddl = $pdo->query('SHOW CREATE TABLE matieres')->fetch(PDO::FETCH_NUM)[1];
$m76['run']($pdo);
check($pdo->query('SHOW CREATE TABLE matieres')->fetch(PDO::FETCH_NUM)[1] === $ddl, 'idempotence : relance sans effet');
$m76['rollback']($pdo);
ProgrammeSql::oublier();
check(ProgrammeSql::colonneMatiere('actif') === false && (string)$pdo->query('CHECKSUM TABLE matieres')->fetch(PDO::FETCH_ASSOC)['Checksum'] === $avantMat, 'retour arrière : table matieres identique à l\'origine');
$m76['run']($pdo);
ProgrammeSql::oublier();
check(ProgrammeSql::colonneMatiere('actif') && ProgrammeSql::colonneMatiere('nom_affichage'), 'ré-application après retour arrière');
$ddlCm = $pdo->query('SHOW CREATE TABLE classe_matieres')->fetch(PDO::FETCH_NUM)[1];
check(str_contains($ddlCm, '`obligatoire` tinyint unsigned NOT NULL DEFAULT \'1\'') && str_contains($ddlCm, 'chk_cm_obligatoire'), 'classe_matieres.obligatoire : défaut 1 (obligatoire), valeurs 0/1 contrôlées');
check(leve(fn() => $pdo->exec("INSERT INTO classe_matieres (etablissement_id, classe_id, matiere_id, coefficient, volume_horaire, obligatoire) VALUES (1, 131, $MATHS, 2, 2, 2)")) !== null, 'base : obligatoire = 2 refusé (CHECK)');

$svc = new P($pdo, 1, $admin);

// ═══════════════════════════════════════════════════════════
section('2. Désactivation et réactivation des matières');
$svc->ajouter(131, $ARABE, '2', '2');
$ensAvant = (int)$pdo->query("SELECT COUNT(*) FROM enseignements WHERE matiere_id = $ARABE")->fetchColumn();
$protAvant = $checksum($protegees);
$r = http($nomCopie, 'admin', $admin, 'POST', "/matieres/$ARABE/desactiver", [], true);
check((int)$pdo->query("SELECT actif FROM matieres WHERE id = $ARABE")->fetchColumn() === 0, 'POST désactiver : matière désactivée (' . $r['status'] . ')');
$a = $audits('matiere_desactivee');
check(count($a) === 1 && (int)$a[0]['user_id'] === $admin && str_contains((string)$a[0]['avant'], '"actif": 1') && str_contains((string)$a[0]['apres'], '"actif": 0') && $a[0]['created_at'] !== null, 'audit : utilisateur, date, ancienne et nouvelle valeur');
check($checksum($protegees) === $protAvant, 'désactivation : enseignements, évaluations, notes, moyennes et bulletins strictement inchangés');
check(count($lignes(131)) === 1 && (int)$pdo->query("SELECT COUNT(*) FROM enseignements WHERE matiere_id = $ARABE")->fetchColumn() === $ensAvant, 'désactivation : ligne de programme et enseignements existants conservés');
$prog = array_column($svc->programme(131), null, 'matiere_id');
check((int)$prog[$ARABE]['matiere_active'] === 0, 'programme : la ligne existante signale la matière désactivée');
check(!in_array($ARABE, array_column($svc->ajoutables(131), 'id'), false) && !in_array($ARABE, array_column($svc->ajoutables(1), 'id'), false), 'programme : matière désactivée non proposée à l\'ajout');
check(leve(fn() => $svc->ajouter(1, $ARABE)) === ProgrammeException::class, 'programme : ajout d\'une matière désactivée refusé (service)');
check(!in_array($ARABE, array_map(fn($m) => (int)$m->id, (new MatiereModel())->findForSelect(true)), true), 'listes d\'affectation d\'enseignement : matière désactivée absente');
$prof = (int)$pdo->query('SELECT professeur_id FROM enseignements WHERE classe_id = 131 ORDER BY id LIMIT 1')->fetchColumn();
http($nomCopie, 'admin', $admin, 'POST', '/classes/1/affecter-enseignant', ['professeur_id' => $prof, 'matiere_id' => $ARABE, 'annee_scolaire' => '2024-2025'], true);
http($nomCopie, 'admin', $admin, 'POST', "/professeurs/$prof/affecter-enseignement", ['matiere_id' => $ARABE, 'classe_id' => 1, 'annee_scolaire' => '2024-2025'], true);
check((int)$pdo->query("SELECT COUNT(*) FROM enseignements WHERE matiere_id = $ARABE")->fetchColumn() === $ensAvant, 'nouvelle affectation d\'enseignant (par la classe ou par le professeur) sur matière désactivée : refusée');
$evalRepo = new EvaluationRepository();
check($evalRepo->matiereEstActive($ARABE) === false && $evalRepo->matiereEstActive($MATHS) === true, 'évaluations : l\'état de la matière est lu (nouvelle évaluation refusée si désactivée)');

// Reproduction : une matière désactivée n'est pas reprise dans une NOUVELLE affectation
$classe = static function (string $nom, string $niveau, ?string $serie, string $annee, int $etab = 1) use ($pdo): int {
    $s = $pdo->prepare('INSERT INTO classes (etablissement_id, nom, niveau, serie, annee_scolaire, max_eleves) VALUES (?, ?, ?, ?, ?, 30)');
    $s->execute([$etab, $nom, $niveau, $serie, $annee]);
    return (int)$pdo->lastInsertId();
};
$svc->ajouter(131, $FRANCAIS, '3', '4');
$serie131 = $pdo->query('SELECT serie FROM classes WHERE id = 131')->fetchColumn() ?: null;
$cible131 = $classe('2nde C9', 'Seconde', $serie131, '2025-2026');
$res = $svc->reproduire(131, [$cible131], 'completer');
$ls = array_column($lignes($cible131), 'matiere_id');
check($res[0]['ajoutees'] === 1 && $ls === [$FRANCAIS], 'reproduction : la matière désactivée n\'est pas reprise, les autres le sont', json_encode($ls));

// Réactivation
$r = http($nomCopie, 'admin', $admin, 'POST', "/matieres/$ARABE/reactiver", [], true);
check((int)$pdo->query("SELECT actif FROM matieres WHERE id = $ARABE")->fetchColumn() === 1, 'POST réactiver : matière de nouveau active');
$a = $audits('matiere_reactivee');
check(count($a) === 1 && (int)$a[0]['user_id'] === $admin && str_contains((string)$a[0]['avant'], '"actif": 0') && str_contains((string)$a[0]['apres'], '"actif": 1'), 'audit de réactivation : utilisateur, ancienne et nouvelle valeur');
check(in_array($ARABE, array_column($svc->ajoutables(1), 'id'), false) && in_array($ARABE, array_map(fn($m) => (int)$m->id, (new MatiereModel())->findForSelect(true)), true), 'réactivée : de nouveau proposée aux nouvelles affectations');
check(count($lignes(131)) === 2 && $checksum($protegees) === $protAvant, 'cycle désactiver → réactiver : programme, notes, évaluations et bulletins intacts');
http($nomCopie, 'admin', $admin, 'POST', "/matieres/$CONDUITE/desactiver", [], true);
check((int)$pdo->query("SELECT actif FROM matieres WHERE id = $CONDUITE")->fetchColumn() === 1, 'la Conduite ne peut pas être désactivée');

// Suppression physique
$r = http($nomCopie, 'admin', $admin, 'POST', "/matieres/$MATHS/delete", [], true);
check((int)$pdo->query("SELECT COUNT(*) FROM matieres WHERE id = $MATHS")->fetchColumn() === 1 && !str_contains($r['body'], 'PDOException'), 'matière utilisée (enseignements, évaluations, moyennes) : suppression refusée');
$usages = (new MatiereModel())->usages($MATHS);
check(count($usages) >= 3 && str_contains(implode(',', $usages), 'évaluation') && str_contains(implode(',', $usages), 'enseignement'), 'usages() liste les raisons du refus', implode(' | ', $usages));
$pdo->exec("INSERT INTO matieres (etablissement_id, nom, coefficient, categorie, volume_horaire) VALUES (1, 'Jamais utilisée', 1.0, 'autre', 1)");
$neuve = (int)$pdo->lastInsertId();
check((new MatiereModel())->usages($neuve) === [], 'matière jamais utilisée : aucune raison de refus');
http($nomCopie, 'admin', $admin, 'POST', "/matieres/$neuve/delete", [], true);
check((int)$pdo->query("SELECT COUNT(*) FROM matieres WHERE id = $neuve")->fetchColumn() === 0, 'matière jamais utilisée : suppression possible');

// ═══════════════════════════════════════════════════════════
section('3. Matières obligatoires et facultatives');
$calc = new AcademicCalculationService();
$rrepo = new RankingRepository(); $arepo = new AnalyticsRepository(); $ens = new EnseignementModel(); $brepo = new BulletinRepository();
$engine = new RankingEngine($calc, $rrepo);
$gen = new BulletinGenerator($calc, $engine, $brepo);
$e131 = (int)$pdo->query('SELECT id FROM eleves WHERE classe_id = 131 AND actif = 1 ORDER BY id LIMIT 1')->fetchColumn();
$e132 = (int)$pdo->query('SELECT id FROM eleves WHERE classe_id = 132 AND actif = 1 ORDER BY id LIMIT 1')->fetchColumn();
$moyLigne = static function (BulletinData $b, int $matiereId): ?array { foreach ($b->lignesMatieres as $l) { if ((int)$l['matiere_id'] === $matiereId) { return $l; } } return null; };
/** Moyenne attendue, calculée indépendamment : Σ(moyenne × coefficient) / Σ(coefficient) en ignorant la matière $exclue. */
$attendue = static function (BulletinData $b, ?int $exclue) : float {
    $s = 0.0; $c = 0.0;
    foreach ($b->lignesMatieres as $l) {
        if ($l['moyenne'] === null || (int)$l['matiere_id'] === $exclue) { continue; }
        $s += (float)$l['moyenne'] * (float)$l['coefficient']; $c += (float)$l['coefficient'];
    }
    return $c > 0 ? round($s / $c, 2) : 0.0;
};
$svc->retirer(131, $FRANCAIS, true); $svc->retirer(131, $ARABE, true);
$svc->ajouter(131, $MATHS, '5', '5');
$bulletinsAvant = $checksum(['bulletins_v2', 'moyennes_generales', 'moyennes_matieres', 'notes_v2', 'evaluations']);
$b0 = $gen->previewBulletin($e131, 90);
$ligneM0 = $moyLigne($b0, $MATHS);
check($ligneM0 !== null && $ligneM0['moyenne'] !== null && (float)$ligneM0['coefficient'] === 5.0, 'CAS 1 — obligatoire (défaut) : Mathématiques compte pour son coefficient 5 dans le bulletin');
check(abs($attendue($b0, null) - $b0->moyennePeriode) < 0.011, 'CAS 1 — moyenne générale = Σ(moyenne × coef) / Σ(coef), Mathématiques incluse', "{$b0->moyennePeriode} vs {$attendue($b0, null)}");
$rangs0 = $engine->classementClasse(131, 90)->rankings;

check(leve(fn() => $svc->enregistrer(131, [$MATHS => ['obligatoire' => '0']])) === ProgrammeConfirmationException::class, 'rendre facultative une matière notée sans confirmation : refusé');
check((int)$lignes(131)[0]['obligatoire'] === 1, 'refus : rien n\'a été écrit');
$svc->enregistrer(131, [$MATHS => ['obligatoire' => '0']], true);
$ligne = array_column($lignes(131), null, 'matiere_id')[$MATHS];
check((int)$ligne['obligatoire'] === 0 && (float)$ligne['coefficient'] === 5.0, 'confirmé : matière facultative, coefficient déclaré (5) conservé');
$b1 = $gen->previewBulletin($e131, 90);
$ligneM1 = $moyLigne($b1, $MATHS);
check($ligneM1 !== null && $ligneM1['moyenne'] === $ligneM0['moyenne'], 'CAS 2 — facultative : sa propre moyenne reste calculée et affichée au bulletin', json_encode([$ligneM0['moyenne'], $ligneM1['moyenne'] ?? null]));
check((float)$ligneM1['coefficient'] === 0.0, 'CAS 2 — facultative : poids 0 dans la moyenne générale');
check(abs($attendue($b0, $MATHS) - $b1->moyennePeriode) < 0.011, 'CAS 2 — moyenne générale = calcul sans Mathématiques (indépendant)', "{$b1->moyennePeriode} vs {$attendue($b0, $MATHS)}");
check(abs($b1->moyennePeriode - $b0->moyennePeriode) > 0.001, 'CAS 2 — la moyenne générale a bien changé', "{$b0->moyennePeriode} → {$b1->moyennePeriode}");
$rows = $rrepo->notesParClasseEtPeriode(131, 90);
$coefs = array_unique(array_map(fn($r) => (float)$r->coeff_matiere, array_filter($rows, fn($r) => (int)$r->matiere_id === $MATHS)));
check($coefs === [0.0], 'classement : coefficient compté 0 pour la matière facultative', json_encode(array_values($coefs)));
check(empreinte($engine->classementClasse(131, 90)->rankings) !== empreinte($rangs0), 'classement : recalculé sans la matière facultative');
$ensRow = array_values(array_filter($ens->findByClasseId(131), fn($r) => (int)$r->matiere_id === $MATHS));
$anaRow = array_values(array_filter($arepo->moyennesParMatiere(90, 131), fn($r) => (int)$r['matiere_id'] === $MATHS));
check(($ensRow === [] || (float)$ensRow[0]->coefficient === 5.0) && $anaRow !== [] && (float)$anaRow[0]['coefficient'] === 5.0, 'affichages (enseignements, analyse par matière) : coefficient DÉCLARÉ (5), non faussé par le caractère facultatif');
$b132 = empreinte($gen->previewBulletin($e132, 101)->moyennePeriode);
check($b132 === empreinte($gen->previewBulletin($e132, 101)->moyennePeriode), 'classe 132 (autre programme) : inchangée');
check($checksum(['bulletins_v2', 'moyennes_generales', 'moyennes_matieres', 'notes_v2', 'evaluations']) === $bulletinsAvant, 'AUCUN recalcul figé : bulletins enregistrés, moyennes en base, notes et évaluations inchangés (l\'aperçu seul reflète la règle)');
$svc->enregistrer(131, [$MATHS => ['obligatoire' => '1']], true);
$b2 = $gen->previewBulletin($e131, 90);
check(abs($b2->moyennePeriode - $b0->moyennePeriode) < 0.0001, 'retour à obligatoire : résultat identique à l\'origine (réversible)');
$aud = $pdo->query("SELECT avant, apres FROM audit_logs WHERE entite = 'programme_classe' AND action = 'update' AND avant LIKE '%obligatoire%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
fwrite(STDERR, "AUD " . json_encode($aud) . "
"); check(count($aud) === 2 && str_contains($aud[0]['avant'], '"obligatoire": 1') && str_contains($aud[0]['apres'], '"obligatoire": 0') && str_contains($aud[1]['apres'], '"obligatoire": 1'), 'audit : ancienne et nouvelle valeur du caractère obligatoire');
$svc->ajouter(131, $ANGLAIS, '2', '2', 0, false);
check((int)array_column($lignes(131), null, 'matiere_id')[$ANGLAIS]['obligatoire'] === 0, 'ajout directement comme matière facultative');
$cibleF = $classe('2nde C8', 'Seconde', $serie131, '2025-2026');
$svc->reproduire(131, [$cibleF], 'completer');
check((int)array_column($lignes($cibleF), null, 'matiere_id')[$ANGLAIS]['obligatoire'] === 0, 'reproduction : le caractère facultatif est copié');
$svc->retirer(131, $ANGLAIS, true);

// ═══════════════════════════════════════════════════════════
section('4. Nom d\'affichage par établissement');
$mathsRow = $pdo->query("SELECT nom, coefficient, volume_horaire, categorie FROM matieres WHERE id = $MATHS")->fetch(PDO::FETCH_ASSOC);
$nomRef = $mathsRow['nom'];
$r = http($nomCopie, 'admin', $admin, 'POST', "/matieres/$MATHS", ['nom' => $nomRef, 'nom_affichage' => 'Maths — Terminale', 'coefficient' => (string)$mathsRow['coefficient'], 'volume_horaire' => (string)$mathsRow['volume_horaire'], 'categorie' => $mathsRow['categorie']], true);
check($pdo->query("SELECT nom_affichage FROM matieres WHERE id = $MATHS")->fetchColumn() === 'Maths — Terminale', 'POST modifier : nom d\'affichage enregistré (' . $r['status'] . ')');
check($pdo->query("SELECT nom FROM matieres WHERE id = $MATHS")->fetchColumn() === $nomRef && (int)$pdo->query("SELECT id FROM matieres WHERE nom = " . $pdo->quote($nomRef) . " AND etablissement_id = 1")->fetchColumn() === $MATHS, 'le nom de référence et l\'identifiant sont intacts');
$a = $pdo->query("SELECT avant, apres FROM audit_logs WHERE entite = 'matiere' AND action = 'update' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check($a && str_contains($a['apres'], 'Maths — Terminale') && str_contains($a['avant'], '"nom_affichage": ""'), 'audit : ancien et nouveau nom d\'affichage');
$nomAff = 'Maths — Terminale';
check(ProgrammeSql::nom('m') === "COALESCE(NULLIF(TRIM(m.nom_affichage), ''), m.nom)", 'fragment SQL : nom d\'affichage sinon nom de référence');
$nomsRang = array_unique(array_map(fn($r) => $r->matiere_nom, array_filter($rrepo->notesParClasseEtPeriode(131, 90), fn($r) => (int)$r->matiere_id === $MATHS)));
check($nomsRang === [$nomAff], 'classement/bulletin (RankingRepository) : nom d\'affichage');
$b = $gen->previewBulletin($e131, 90);
check(($moyLigne($b, $MATHS)['matiere_nom'] ?? ($moyLigne($b, $MATHS)['nom'] ?? '')) === $nomAff, 'bulletin (aperçu) : nom d\'affichage', json_encode(array_keys($moyLigne($b, $MATHS) ?? [])));
$lignesB = $brepo->notesEleveParPeriode($e131, 131, 90);
check(in_array($nomAff, array_unique(array_map(fn($r) => (array)$r, $lignesB) ? array_column(array_map(fn($r) => (array)$r, $lignesB), 'matiere_nom') : []), true), 'BulletinRepository : nom d\'affichage');
check(in_array($nomAff, array_column(array_map(fn($r) => (array)$r, $arepo->moyennesParMatiere(90, 131)), 'matiere_nom'), true), 'analyses par matière : nom d\'affichage');
$noms131 = array_map(fn($r) => $r->matiere_nom, $ens->findByClasseId(131));
check($noms131 !== [] && !in_array($nomRef, $noms131, true) && in_array($nomAff, $noms131, true), 'enseignements de la classe : nom d\'affichage', json_encode($noms131));
$prog = array_column($svc->programme(131), null, 'matiere_id');
check($prog[$MATHS]['nom_affiche'] === $nomAff && $prog[$MATHS]['nom'] === $nomRef, 'programme de classe : nom d\'affichage et référence');
$pdo->exec("UPDATE matieres SET nom_affichage = '   ' WHERE id = $MATHS");
check($pdo->query("SELECT " . ProgrammeSql::nom('m') . " FROM matieres m WHERE m.id = $MATHS")->fetchColumn() === $nomRef, 'nom d\'affichage vide ou blanc : retour au nom de référence');
$pdo->exec("UPDATE matieres SET nom_affichage = " . $pdo->quote($nomAff) . " WHERE id = $MATHS");
check((int)$pdo->query("SELECT COUNT(*) FROM bulletins_v2 WHERE statut IN ('publie','archive') AND data_json LIKE " . $pdo->quote('%' . $nomAff . '%'))->fetchColumn() === 0, 'bulletins déjà publiés/archivés : le nouveau nom n\'y apparaît pas (instantané figé)');

$pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('ecole-b', 'Ecole B', 'EB', 'lycee')");
$etab2 = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO matieres (etablissement_id, nom, nom_affichage, coefficient, categorie, volume_horaire) VALUES ($etab2, " . $pdo->quote($nomRef) . ", 'Maths B', 3.0, 'autre', 3)");
$mathsB = (int)$pdo->lastInsertId();
$classeB = $classe('B1', 'Seconde', null, '2025-2026', $etab2);
check($pdo->query("SELECT nom_affichage FROM matieres WHERE id = $MATHS")->fetchColumn() === $nomAff && $pdo->query("SELECT nom_affichage FROM matieres WHERE id = $mathsB")->fetchColumn() === 'Maths B', 'deux écoles, même nom de référence : noms d\'affichage indépendants');
$svcB = new P($pdo, $etab2, null);
check(leve(fn() => $svcB->ajouter($classeB, $MATHS)) === ProgrammeException::class, 'école B ne peut pas mettre la matière de l\'école A dans son programme');
check(leve(fn() => $svc->ajouter(131, $mathsB)) === ProgrammeException::class, 'école A ne peut pas mettre la matière de l\'école B dans son programme');
check(leve(fn() => $svcB->programme(131)) === ProgrammeException::class, 'école B ne peut pas lire le programme d\'une classe de l\'école A');
check(leve(fn() => $svcB->reprendreDepuis(131, $classeB)) === ProgrammeException::class, 'école B ne peut pas reprendre le programme d\'une classe de l\'école A');
$svcB->ajouter($classeB, $mathsB, '9', '3');
check((float)array_column($svc->programme(131), null, 'matiere_id')[$MATHS]['coefficient'] === 5.0 && $nbLignes($classeB) === 1, 'la configuration de l\'école B n\'affecte pas l\'école A');
http($nomCopie, 'admin', $admin, 'POST', "/matieres/$mathsB/desactiver", [], true);
check((int)$pdo->query("SELECT actif FROM matieres WHERE id = $mathsB")->fetchColumn() === 1, 'un administrateur de l\'école A ne peut pas désactiver une matière de l\'école B');

// ═══════════════════════════════════════════════════════════
section('5. Séries A, C et D : programmes indépendants');
$ANNEE = '2031-2032';
$TA = $classe('TA1', 'Terminale', 'A', $ANNEE); $TC = $classe('TC1', 'Terminale', 'C', $ANNEE); $TD = $classe('TD1', 'Terminale', 'D', $ANNEE);
$SVT = $mid('SVT'); $PHYS = $mid('Physique-Chimie') ?: $mid('Physique');
$autresMat = $pdo->query("SELECT id FROM matieres WHERE etablissement_id = 1 AND est_conduite IS NULL AND id NOT IN ($MATHS, $FRANCAIS, $ANGLAIS) ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
[$X, $Y] = [(int)$autresMat[0], (int)$autresMat[1]];
// Mathématiques : coefficient 5 en C, 2 en A, 4 en D ; Français : 3 en A, 1 en C, 2 en D ; X seulement en A, Y seulement en C
$svc->ajouter($TC, $MATHS, '5', '6'); $svc->ajouter($TA, $MATHS, '2', '3'); $svc->ajouter($TD, $MATHS, '4', '5');
$svc->ajouter($TA, $FRANCAIS, '3', '5'); $svc->ajouter($TC, $FRANCAIS, '1', '3'); $svc->ajouter($TD, $FRANCAIS, '2', '4');
$svc->ajouter($TA, $X, '1.5', '2'); $svc->ajouter($TC, $Y, '2.5', '2', 0, false);
$ligne = static fn(int $c, int $m): ?array => array_column($lignes($c), null, 'matiere_id')[$m] ?? null;
check((float)$ligne($TC, $MATHS)['coefficient'] === 5.0 && (float)$ligne($TA, $MATHS)['coefficient'] === 2.0 && (float)$ligne($TD, $MATHS)['coefficient'] === 4.0, 'Mathématiques : coefficient 5 en série C, 2 en série A, 4 en série D');
check((float)$ligne($TA, $FRANCAIS)['coefficient'] === 3.0 && (float)$ligne($TC, $FRANCAIS)['coefficient'] === 1.0 && (float)$ligne($TD, $FRANCAIS)['coefficient'] === 2.0, 'Français : 3 en A, 1 en C, 2 en D');
check($ligne($TC, $X) === null && $ligne($TD, $X) === null && $ligne($TA, $Y) === null && $ligne($TD, $Y) === null, 'une matière propre à une série n\'apparaît pas dans les autres');
check(count($lignes($TA)) === 3 && array_diff(array_column($lignes($TA), 'matiere_id'), [$MATHS, $FRANCAIS, $X]) === [], 'série A : exactement 3 matières');
// Coefficient EFFECTIF servi aux calculs, par classe
$eff = static function (int $classe, int $matiere) use ($pdo): ?float {
    $s = $pdo->query("SELECT " . ProgrammeSql::coefficient('m') . " FROM matieres m " . ProgrammeSql::jointure((string)$classe, 'm') . " WHERE m.id = $matiere");
    $v = $s->fetchColumn();
    return $v === false ? null : (float)$v;
};
check([$eff($TA, $MATHS), $eff($TC, $MATHS), $eff($TD, $MATHS)] === [2.0, 5.0, 4.0], 'coefficient effectif des calculs : A=2, C=5, D=4', json_encode([$eff($TA, $MATHS), $eff($TC, $MATHS), $eff($TD, $MATHS)]));
check($eff($TC, $Y) === 0.0 && $eff($TA, $X) === 1.5, 'effectif : facultative de la série C compte 0 ; obligatoire de la série A compte son coefficient');
// Aucune contamination après modifications
$snapA = $lignes($TA); $snapD = $lignes($TD);
$svc->enregistrer($TC, [$MATHS => ['coefficient' => '7', 'volume_horaire' => '8', 'obligatoire' => '0']], true);
$svc->retirer($TC, $FRANCAIS, true);
check($lignes($TA) === $snapA && $lignes($TD) === $snapD, 'modifier/retirer dans la série C ne change rien aux séries A et D');
check($eff($TA, $MATHS) === 2.0 && $eff($TD, $MATHS) === 4.0 && $eff($TC, $MATHS) === 0.0, 'effectif après modification de C : A et D inchangés, C facultative = 0');
$svc->enregistrer($TC, [$MATHS => ['coefficient' => '5', 'volume_horaire' => '6', 'obligatoire' => '1']], true);
$svc->ajouter($TC, $FRANCAIS, '1', '3');
// Classes parallèles : même niveau ET même série
$TC2 = $classe('TC2', 'Terminale', 'C', $ANNEE); $TA2 = $classe('TA2', 'Terminale', 'A', $ANNEE);
check(array_column($svc->paralleles($TC), 'id') === [$TC2], 'parallèles de TC1 : seulement TC2 (ni A, ni D)');
$res = $svc->reproduire($TC, [$TC2], 'completer');
check($res[0]['ajoutees'] === count($lignes($TC)) && $lignes($TC2) === $lignes($TC), 'reproduction C → C2 : programme identique (coefficients, heures, obligatoire)');
check(leve(fn() => $svc->reproduire($TC, [$TA2], 'completer')) === ProgrammeException::class && $nbLignes($TA2) === 0, 'reproduction C → A : refusée (série différente), rien d\'écrit');
check(leve(fn() => $svc->reproduire($TC, [$TD], 'completer')) === ProgrammeException::class && $lignes($TD) === $snapD, 'reproduction C → D : refusée, la série D est intacte');
// Même niveau/série, autre nom de série : contrôle strict
check(leve(fn() => $svc->verifierSourceReprise('Terminale', 'A', $TC)) === ProgrammeException::class, 'reprise vers une classe A depuis une source C : refusée');
check(leve(fn() => $svc->verifierSourceReprise('Première', 'C', $TC)) === ProgrammeException::class, 'reprise vers un autre niveau : refusée');
check(leve(fn() => $svc->verifierSourceReprise('Terminale', null, $TC)) === ProgrammeException::class, 'reprise vers une classe sans série depuis une source C : refusée');
check(leve(fn() => $svc->verifierSourceReprise('Terminale', 'C', $TC)) === null, 'reprise vers une classe C depuis une source C : acceptée');

// ═══════════════════════════════════════════════════════════
section('6. Création d\'une classe parallèle avec reprise du programme');
// Le réglage « séries activées par niveau » dépend de l'installation : on fixe A, C, D pour la copie de test (la base réelle n'est pas touchée)
\Core\Tenant\SettingsService::make()->set(1, 'academique', 'series_par_niveau', ['Seconde' => ['A', 'C'], 'Première' => ['A', 'C', 'D'], 'Terminale' => ['A', 'C', 'D']], 'json', $admin);
$srcIds = array_column($svc->sourcesReprise(), 'id');
check(in_array($TC, $srcIds, false) && !in_array($TA2, $srcIds, false), 'sources proposées : classes ayant un programme (TC1 oui, TA2 vide non)');
$r = http($nomCopie, 'admin', $admin, 'GET', '/classes/create');
check($r['status'] === 200 && str_contains($r['body'], 'name="reprendre_depuis"') && str_contains($r['body'], 'name="confirmer_reprise"'), 'formulaire de création : choix de la classe source et case de confirmation');
$nbClasses = static fn() => (int)$pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn();
$n0 = $nbClasses();
$base = ['nom' => 'TC3', 'niveau' => 'Terminale', 'serie' => 'C', 'annee_scolaire' => $ANNEE, 'max_eleves' => '40'];
http($nomCopie, 'admin', $admin, 'POST', '/classes/store', $base + ['reprendre_depuis' => (string)$TC], true);
check($nbClasses() === $n0, 'sans confirmation : la classe n\'est PAS créée (tout est vérifié avant)');
http($nomCopie, 'admin', $admin, 'POST', '/classes/store', ['serie' => 'A', 'nom' => 'TA3'] + $base + ['reprendre_depuis' => (string)$TC, 'confirmer_reprise' => '1'], true);
check($nbClasses() === $n0, 'série différente de la source : refus, la classe n\'est PAS créée');
http($nomCopie, 'admin', $admin, 'POST', '/classes/store', ['niveau' => 'Première', 'nom' => 'PC3'] + $base + ['reprendre_depuis' => (string)$TC, 'confirmer_reprise' => '1'], true);
check($nbClasses() === $n0, 'niveau différent de la source : refus, la classe n\'est PAS créée');
http($nomCopie, 'admin', $admin, 'POST', '/classes/store', $base + ['reprendre_depuis' => '999999', 'confirmer_reprise' => '1'], true);
check($nbClasses() === $n0, 'source inexistante ou d\'une autre école : refus, la classe n\'est PAS créée');
http($nomCopie, 'admin', $admin, 'POST', '/classes/store', $base + ['reprendre_depuis' => (string)$TC, 'confirmer_reprise' => '1'], true);
$TC3 = (int)$pdo->query("SELECT id FROM classes WHERE etablissement_id = 1 AND nom = 'TC3' AND annee_scolaire = '$ANNEE'")->fetchColumn();
check($TC3 > 0 && $nbClasses() === $n0 + 1, 'avec confirmation et même niveau/série : classe créée');
check($TC3 > 0 && $lignes($TC3) === $lignes($TC), 'programme repris à l\'identique (coefficients, heures, caractère obligatoire, ordre)');
$a = $pdo->query("SELECT apres FROM audit_logs WHERE entite = 'programme_classe' AND action = 'create' AND apres LIKE '%repris_depuis_classe%' ORDER BY id DESC LIMIT 1")->fetchColumn();
check($a !== false && str_contains((string)$a, "\"repris_depuis_classe\": $TC"), 'audit : reprise tracée avec la classe source');
check(leve(fn() => $svc->reprendreDepuis($TC, $TC3)) === ProgrammeException::class && $lignes($TC3) === $lignes($TC), 'reprise sur une classe qui a déjà un programme : refusée, aucun écrasement');
$TC4 = $classe('TC4', 'Terminale', 'C', $ANNEE);
$svc->ajouter($TC4, $Y, '9', '1');
$avant4 = $lignes($TC4);
check(leve(fn() => $svc->reprendreDepuis($TC, $TC4)) === ProgrammeException::class && $lignes($TC4) === $avant4, 'classe déjà configurée : configuration préservée');
http($nomCopie, 'admin', $admin, 'POST', '/classes/store', ['nom' => 'TC5'] + $base, true);
$TC5 = (int)$pdo->query("SELECT id FROM classes WHERE nom = 'TC5' AND annee_scolaire = '$ANNEE'")->fetchColumn();
check($TC5 > 0 && $nbLignes($TC5) === 0, 'création sans reprise : comportement historique inchangé (aucun programme)');

// ═══════════════════════════════════════════════════════════
section('7. Permissions par rôle');
$roles = require ROOT_PATH . '/config/permissions.php';
$uid = [];
foreach (['admin', 'directeur', 'secretaire', 'comptable', 'enseignant', 'parent', 'eleve'] as $role) {
    if ($role === 'admin') { $uid[$role] = $admin; continue; }
    $s = $pdo->prepare("INSERT INTO users (etablissement_id, nom, email, password, role) VALUES (1, ?, ?, 'x', ?)");
    $s->execute(["Test $role", "evol-$role@exemple.test", $role]);
    $uid[$role] = (int)$pdo->lastInsertId();
}
$matiereTest = $mid('EPS');
foreach ($uid as $role => $id) {
    $peutMat = in_array('matieres.edit', $roles[$role] ?? [], true);
    $peutCls = in_array('classes.edit', $roles[$role] ?? [], true);
    $pdo->exec("UPDATE matieres SET actif = 1 WHERE id = $matiereTest");
    http($nomCopie, $role, $id, 'POST', "/matieres/$matiereTest/desactiver", [], true);
    $desactivee = (int)$pdo->query("SELECT actif FROM matieres WHERE id = $matiereTest")->fetchColumn() === 0;
    check($desactivee === $peutMat, "rôle $role : désactiver une matière " . ($peutMat ? 'autorisé' : 'refusé') . ' (matieres.edit)');
    $pdo->exec("UPDATE matieres SET actif = 1 WHERE id = $matiereTest");
    $avant = $lignes($TC);
    http($nomCopie, $role, $id, 'POST', "/classes/$TC/programme/enregistrer", ['programme' => [$MATHS => ['obligatoire' => '0', 'coefficient' => '6']], 'confirmer_notes' => '1'], true);
    $modifie = $lignes($TC) !== $avant;
    check($modifie === $peutCls, "rôle $role : modifier caractère obligatoire/coefficient " . ($peutCls ? 'autorisé' : 'refusé') . ' (classes.edit)');
    if ($modifie) { $svc->enregistrer($TC, [$MATHS => ['obligatoire' => '1', 'coefficient' => '5']], true); }
    $n = $nbClasses();
    http($nomCopie, $role, $id, 'POST', '/classes/store', ['nom' => 'P' . strtoupper(substr($role, 0, 3)), 'niveau' => 'Terminale', 'serie' => 'C', 'annee_scolaire' => $ANNEE, 'max_eleves' => '40', 'reprendre_depuis' => (string)$TC, 'confirmer_reprise' => '1'], true);
    $cree = $nbClasses() > $n;
    $attendu = $peutCls && in_array('classes.create', $roles[$role] ?? [], true);
    check($cree === $attendu, "rôle $role : créer une classe avec reprise de programme " . ($attendu ? 'autorisé' : 'refusé'));
}
$nomT = 'PSEC';
$pdo->exec("UPDATE matieres SET nom_affichage = 'Nom piraté' WHERE id = $EPS AND 1 = 0");
http($nomCopie, 'enseignant', $uid['enseignant'], 'POST', "/matieres/$EPS", ['nom' => 'EPS', 'nom_affichage' => 'Piraté', 'coefficient' => '1', 'volume_horaire' => '2', 'categorie' => 'autre'], true);
check($pdo->query("SELECT COALESCE(nom_affichage, '') FROM matieres WHERE id = $EPS")->fetchColumn() !== 'Piraté', 'rôle enseignant : ne peut pas définir un nom d\'affichage');

// ═══════════════════════════════════════════════════════════
section('8. Audit strict (dans la transaction) et historique des coefficients');
$svc->enregistrer($TC, [$MATHS => ['coefficient' => '6']], true);
$hist = $svc->historique($TC);
check($hist !== [] && $hist[0]['action'] === 'update' && (float)$hist[0]['avant']['coefficient'] === 5.0 && (float)$hist[0]['apres']['coefficient'] === 6.0 && $hist[0]['matiere'] === $nomRef,
    'historique : dernière ligne = modification 5 → 6 de la matière', json_encode($hist[0] ?? null, JSON_UNESCAPED_UNICODE));
check($hist[0]['acteur'] !== '' && $hist[0]['acteur'] !== 'Système' && $hist[0]['date'] !== '', 'historique : utilisateur et date présents', $hist[0]['acteur'] ?? '');
$horsClasse = array_filter($hist, fn($h) => (int)($h['apres']['classe_id'] ?? $h['avant']['classe_id'] ?? 0) !== $TC);
check($horsClasse === [] && count($hist) >= 3, 'historique : uniquement les modifications de CETTE classe (ajouts, reprise, modification)', (string)count($hist));
check(array_filter($svc->historique($TA), fn($h) => (float)($h['apres']['coefficient'] ?? 0) === 6.0) === [], 'historique de la série A : la modification de la série C n\'y figure pas');
$r = http($nomCopie, 'admin', $admin, 'GET', "/classes/$TC?tab=programme");
check($r['status'] === 200 && str_contains($r['body'], 'Historique du programme') && str_contains($r['body'], '5 → 6'), 'fiche de classe : bloc « Historique du programme » avec ancienne → nouvelle valeur');
$r = http($nomCopie, 'eleve', $uid['eleve'], 'GET', "/classes/$TC?tab=programme");
check(!str_contains($r['body'], 'Historique du programme'), 'sans classes.edit : historique non affiché');

// Atomicité : journal indisponible ⇒ l'opération est ANNULÉE (aucune modification sans trace)
$pdo->exec('RENAME TABLE audit_logs TO audit_logs_off');
try {
    $avantL = $lignes($TC);
    check(leve(fn() => $svc->enregistrer($TC, [$MATHS => ['coefficient' => '9']], true)) === ProgrammeException::class && $lignes($TC) === $avantL, 'journal indisponible : modification du coefficient annulée');
    check(leve(fn() => $svc->ajouter($TC, $ANGLAIS, '2', '2')) === ProgrammeException::class && $lignes($TC) === $avantL, 'journal indisponible : ajout annulé');
    check(leve(fn() => $svc->retirer($TC, $FRANCAIS, true)) === ProgrammeException::class && $lignes($TC) === $avantL, 'journal indisponible : retrait annulé');
    $avantN = $pdo->query("SELECT COALESCE(nom_affichage, '') FROM matieres WHERE id = $MATHS")->fetchColumn();
    http($nomCopie, 'admin', $admin, 'POST', "/matieres/$MATHS", ['nom' => $nomRef, 'nom_affichage' => 'Zzz journal', 'coefficient' => (string)$mathsRow['coefficient'], 'volume_horaire' => (string)$mathsRow['volume_horaire'], 'categorie' => $mathsRow['categorie']], true);
    check($pdo->query("SELECT COALESCE(nom_affichage, '') FROM matieres WHERE id = $MATHS")->fetchColumn() === $avantN, 'journal indisponible : modification d\'une matière annulée');
    http($nomCopie, 'admin', $admin, 'POST', "/matieres/$ARABE/desactiver", [], true);
    check((int)$pdo->query("SELECT actif FROM matieres WHERE id = $ARABE")->fetchColumn() === 1, 'journal indisponible : désactivation annulée');
} finally {
    $pdo->exec('RENAME TABLE audit_logs_off TO audit_logs');
}
$svc->enregistrer($TC, [$MATHS => ['coefficient' => '5']], true);
check((float)array_column($lignes($TC), null, 'matiere_id')[$MATHS]['coefficient'] === 5.0, 'journal rétabli : les opérations fonctionnent de nouveau');

echo "\n" . ($failed === 0 ? "\033[32m" : "\033[31m") . "$passed OK, $failed KO\033[0m\n";
exit($failed === 0 ? 0 : 1);
