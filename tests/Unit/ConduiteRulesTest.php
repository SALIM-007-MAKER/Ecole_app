<?php
/**
 * Tests d'acceptation — Lot 2 (Conduite), partie « règles » : politiques, huit groupes d'actions par rôle et par route,
 * matière de Conduite (formulaires, suppression, listes d'affectation), emplois du temps, absence de la mention « absent »,
 * règle d'une évaluation de Conduite par classe et période, complétude stricte.
 *
 * Tout tourne sur une COPIE RÉELLE jetable de la base (DbCopy) sur laquelle T048-T050 sont appliquées ; la base source
 * n'est jamais écrite (empreinte de toutes ses tables comparée avant / après). Droits de bulletin accordés dans la copie seule.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/ConduiteRulesTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);
define('BASE_URL', '/ecole_app');
require_once ROOT_PATH . '/tests/Support/DbCopy.php';
require_once ROOT_PATH . '/tests/Support/ConduiteCopy.php';

use Tests\Support\ConduiteCopy;
use Tests\Support\DbCopy;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) { continue; }
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\' => '/app/'] as $pre => $dir) {
        if (str_starts_with($c, $pre)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($pre))) . '.php';
            if (is_file($f)) { require_once $f; return; }
        }
    }
});

use App\Modules\Academique\Policies\AppreciationPolicy;
use App\Modules\Academique\Policies\EvaluationPolicy;
use App\Modules\Academique\Policies\NotePolicy;
use App\Modules\Academique\Services\ConduiteCompletudeService;

$conn = DbCopy::connectionFromEnv(ROOT_PATH);
$source = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['db']};charset=utf8mb4", $conn['user'], $conn['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$avantSource = DbCopy::allDataHashes($source);
$copy = DbCopy::create(ROOT_PATH);
if ($copy === null) { echo "SKIP : copie réelle impossible\n"; exit(0); }

try {
    $db = $copy->pdo();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('SET NAMES utf8mb4');
    ConduiteCopy::migrer($db, ROOT_PATH);
    ConduiteCopy::accorderDroitsBulletin($db);
    $nom = $copy->name;
    $_ENV['DB_DATABASE'] = $nom;   // TOUT code in-process vise la copie, jamais la base réelle (à poser avant la première connexion applicative)
    $http = fn(string $role, int $uid, string $m, string $u, array $post = [], array $env = []) => ConduiteCopy::http(ROOT_PATH, $nom, $role, $uid, $m, "/ecole_app{$u}", $post, $env);
    $one = fn(string $sql, array $p = []) => (function () use ($db, $sql, $p) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();

    // ── Faits de référence (copie) ──
    $ADMIN = 1; $DIR = 2; $ENS = 5;
    $conduite = (int)$one("SELECT id FROM matieres WHERE est_conduite = 1 AND etablissement_id = 1");
    $ordinaire = (int)$one("SELECT id FROM matieres WHERE est_conduite IS NULL AND etablissement_id = 1 AND nom LIKE 'Fran%'");
    $typeId = (int)$one("SELECT type_evaluation_id FROM evaluations WHERE id = 4");
    $clone = function (int $srcId, array $over) use ($db): int {
        $row = $db->query("SELECT * FROM evaluations WHERE id = $srcId")->fetch();
        unset($row['id'], $row['created_at'], $row['updated_at']);
        $row = array_merge($row, $over);
        $cols = array_keys($row);
        $db->prepare('INSERT INTO evaluations (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($row));
        return (int)$db->lastInsertId();
    };
    $statutEval = fn(int $id) => $one('SELECT statut FROM evaluations WHERE id = ?', [$id]);

    // ═════════════════════════════════════════════════════════════════════
    section('1. Politiques — Conduite (marqueur) : admin / directeur / enseignant');
    $perms = require ROOT_PATH . '/config/permissions.php';
    $u = fn(string $r) => ['role' => $r, 'permissions' => $perms[$r] ?? []];
    $admin = $u('admin'); $dir = $u('directeur'); $ens = $u('enseignant');
    $evC = (object)['matiere_est_conduite' => 1, 'statut' => 'brouillon', 'notes_saisie_ouverte' => 1];
    $evO = (object)['matiere_est_conduite' => null, 'statut' => 'brouillon', 'notes_saisie_ouverte' => 1];
    $ep = new EvaluationPolicy(); $np = new NotePolicy(); $ap = new AppreciationPolicy();
    check(in_array('academique.conduite.manage', $admin['permissions'], true) && in_array('academique.conduite.manage', $dir['permissions'], true) && !in_array('academique.conduite.manage', $ens['permissions'], true),
          'academique.conduite.manage : admin et directeur, jamais l\'enseignant (config/permissions.php)');
    check($ep->canCreate($ens, true) === false && $ep->canCreate($ens, false) === true, 'évaluation : l\'enseignant ne crée pas de Conduite, mais crée une matière ordinaire');
    check($ep->canCreate($admin, true) && $ep->canCreate($dir, true), 'évaluation : admin et directeur créent une Conduite');
    check(!$ep->canUpdate($ens, $evC) && $ep->canUpdate($ens, $evO) && $ep->canUpdate($admin, $evC) && $ep->canUpdate($dir, $evC), 'évaluation : modification (enseignant refusé sur la Conduite seulement)');
    $arch = (object)['matiere_est_conduite' => 1, 'statut' => 'archivee'];
    check(!$ep->canUpdate($admin, $arch), 'évaluation de Conduite archivée : non modifiable, même par l\'admin');
    check(!$ep->canPublish($ens, $evC) && $ep->canPublish($ens, $evO) && $ep->canPublish($admin, $evC), 'évaluation : publication (Conduite réservée à conduite.manage)');
    $evCp = (object)['matiere_est_conduite' => 1, 'statut' => 'publiee']; $evOp = (object)['matiere_est_conduite' => null, 'statut' => 'publiee'];
    check(!$ep->canVerrouiller($ens, $evCp) && $ep->canVerrouiller($ens, $evOp) && $ep->canVerrouiller($admin, $evCp), 'évaluation : verrouillage');
    $evCv = (object)['matiere_est_conduite' => 1, 'statut' => 'verrouillee']; $evOv = (object)['matiere_est_conduite' => null, 'statut' => 'verrouillee'];
    $sansAdmin = fn(array $usr) => ['role' => $usr['role'], 'permissions' => array_values(array_filter($usr['permissions'], fn($p) => !str_ends_with($p, '.admin')))];   // palier RBAC actif : pas de .admin
check(!$ep->canDeverrouiller($ens, $evCv) && $ep->canDeverrouiller($sansAdmin($admin), $evCv) && !$ep->canDeverrouiller($sansAdmin($admin), $evOv) && $ep->canDeverrouiller($admin, $evOv), 'évaluation : déverrouillage (Conduite = conduite.manage seul ; ordinaire = .admin, inchangé)');
    check(!$ep->canArchiver($ens, $evCp) && $ep->canArchiver($dir, $evCp), 'évaluation : archivage');
    $nC = (object)['notes_saisie_ouverte' => 1, 'matiere_est_conduite' => 1, 'statut' => 'saisie'];
    $nO = (object)['notes_saisie_ouverte' => 1, 'matiere_est_conduite' => null, 'statut' => 'saisie'];
    check(!$np->canSaisir($ens, $nC) && $np->canSaisir($ens, $nO) && $np->canSaisir($admin, $nC) && $np->canSaisir($dir, $nC), 'notes : saisie');
    check(!$np->canImporter($ens, $nC) && $np->canImporter($ens, $nO) && $np->canImporter($admin, $nC), 'notes : import');
    check(!$np->canModifier($ens, $nC) && $np->canModifier($ens, $nO) && $np->canModifier($admin, $nC), 'notes : modification');
    check(!$np->canPublier($ens, $nC) && $np->canPublier($ens, $nO) && $np->canPublier($admin, $nC), 'notes : publication');
    check(!$np->canSupprimer($ens, $nC) && $np->canSupprimer($sansAdmin($admin), $nC) && !$np->canSupprimer($sansAdmin($admin), $nO) && $np->canSupprimer($admin, $nO), 'notes : suppression (Conduite = conduite.manage seul ; ordinaire = .admin, inchangé)');
    check(!$np->canVerrouiller($ens, $nC) && $np->canVerrouiller($admin, $nC), 'notes : verrouillage');
    check(!$ap->canSaisir($ens, null, $conduite, 131, '2025-2026', true) && $ap->canSaisir($admin, null, $conduite, 131, '2025-2026', true), 'appréciations : Conduite réservée à conduite.manage');
    check($ap->canSaisir($admin, null, $ordinaire, 131, '2025-2026', false), 'appréciations : matière ordinaire inchangée');

    // ═════════════════════════════════════════════════════════════════════
    section('2. A7/A10 — huit groupes d\'actions par route (admin, directeur, enseignant) — Conduite et témoin ordinaire');
    $db->exec("UPDATE evaluations SET notes_saisie_ouverte = 1 WHERE id IN (4, 6)");
    $evOrd = 6;   // 131 / 90 / Français (matière ordinaire) — témoin
    // consultation conservée pour l'enseignant (A10)
    foreach ([[4, 'Conduite'], [$evOrd, 'ordinaire']] as [$id, $lib]) {
        [$r] = $http('enseignant', $ENS, 'GET', "/v2/academique/evaluations/$id");
        check(($r['status'] ?? 0) === 200, "A10 enseignant : fiche d'évaluation $lib (200)");
        [$r] = $http('enseignant', $ENS, 'GET', "/v2/academique/evaluations/$id/notes");
        check(($r['status'] ?? 0) === 200, "A10 enseignant : notes par élève, évaluation $lib (200)");
    }
    [$r] = $http('enseignant', $ENS, 'GET', '/v2/academique/evaluations');
    check(($r['status'] ?? 0) === 200, 'A10 enseignant : liste des évaluations (200)');

    // Groupe 1 — créer une évaluation
    $db->exec("UPDATE evaluations SET statut = 'archivee' WHERE id = 12");   // libère (131, période 91) pour la Conduite (copie seulement)
    $formEval = fn(int $matiere, string $lib, array $extra = []) => array_merge([
        'periode_scolaire_id' => 91, 'type_evaluation_id' => $typeId, 'matiere_id' => $matiere, 'classe_id' => 131,
        'libelle' => $lib, 'coefficient' => '3', 'note_max' => '10', 'date_evaluation' => '2026-01-10',
    ], $extra);
    $nbEv = fn() => (int)$one('SELECT COUNT(*) FROM evaluations');
    $n0 = $nbEv();
    $http('enseignant', $ENS, 'POST', '/v2/academique/evaluations', $formEval($conduite, 'Conduite par enseignant'));
    check($nbEv() === $n0, 'G1 enseignant : création d\'une évaluation de Conduite refusée');
    $http('enseignant', $ENS, 'POST', '/v2/academique/evaluations', $formEval($ordinaire, 'Ordinaire par enseignant'));
    check($nbEv() === $n0 + 1, 'G1 enseignant : création d\'une évaluation ordinaire toujours permise (témoin)');
    $http('admin', $ADMIN, 'POST', '/v2/academique/evaluations', $formEval($conduite, 'Conduite par admin'));
    $idCadmin = (int)$one("SELECT id FROM evaluations WHERE libelle = 'Conduite par admin'");
    check($idCadmin > 0, 'G1 admin : création d\'une évaluation de Conduite acceptée');
    check($idCadmin > 0 && (float)$one('SELECT coefficient FROM evaluations WHERE id = ?', [$idCadmin]) === 1.0 && (float)$one('SELECT note_max FROM evaluations WHERE id = ?', [$idCadmin]) === 20.0,
          'A6 création neuve : coefficient 1 et note maximale 20 imposés (3 et 10 envoyés)');
    $http('directeur', $DIR, 'POST', '/v2/academique/evaluations', $formEval($conduite, 'Conduite par directeur'));
    check((int)$one("SELECT COUNT(*) FROM evaluations WHERE libelle = 'Conduite par directeur'") === 0,
          'A6 une seconde évaluation de Conduite non archivée pour (131, 91) est refusée, même pour le directeur');

    // Groupe 2 — modifier une évaluation
    $brC = $clone(4, ['statut' => 'brouillon', 'notes_saisie_ouverte' => 0, 'libelle' => 'BrC', 'periode_scolaire_id' => 90, 'classe_id' => 131]);
    $brO = $clone($evOrd, ['statut' => 'brouillon', 'notes_saisie_ouverte' => 0, 'libelle' => 'BrO']);
    $edit = fn(int $id, string $matiere, int $mat, string $lib) => [
        'periode_scolaire_id' => 90, 'type_evaluation_id' => $typeId, 'matiere_id' => $mat, 'classe_id' => 131,
        'libelle' => $lib, 'coefficient' => '1', 'note_max' => '20', 'date_evaluation' => '2026-01-10',
    ];
    $lib = fn(int $id) => $one('SELECT libelle FROM evaluations WHERE id = ?', [$id]);
    $http('enseignant', $ENS, 'POST', "/v2/academique/evaluations/$brC", $edit($brC, 'c', $conduite, 'BrC-ens'));
    check($lib($brC) === 'BrC', 'G2 enseignant : modification d\'une évaluation de Conduite refusée');
    $http('enseignant', $ENS, 'POST', "/v2/academique/evaluations/$brO", $edit($brO, 'o', $ordinaire, 'BrO-ens'));
    check($lib($brO) === 'BrO-ens', 'G2 enseignant : modification d\'une évaluation ordinaire permise (témoin)');
    $http('admin', $ADMIN, 'POST', "/v2/academique/evaluations/$brC", $edit($brC, 'c', $conduite, 'BrC-admin'));
    check($lib($brC) === 'BrC-admin', 'G2 admin : modification d\'une évaluation de Conduite acceptée');
    $http('directeur', $DIR, 'POST', "/v2/academique/evaluations/$brC", $edit($brC, 'c', $conduite, 'BrC-dir'));
    check($lib($brC) === 'BrC-dir', 'G2 directeur : modification acceptée');
    $http('enseignant', $ENS, 'POST', "/v2/academique/evaluations/$brO", $edit($brO, 'o', $conduite, 'BrO-bascule'));
    check((int)$one('SELECT matiere_id FROM evaluations WHERE id = ?', [$brO]) === $ordinaire, 'G2 enseignant : bascule d\'une évaluation ordinaire vers la Conduite refusée');
    // A6 : le libellé d'une évaluation historique ne touche pas son coefficient (évaluation 55 = coefficient 2)
    $http('admin', $ADMIN, 'POST', '/v2/academique/evaluations/55', [
        'periode_scolaire_id' => 100, 'type_evaluation_id' => (int)$one('SELECT type_evaluation_id FROM evaluations WHERE id = 55'), 'matiere_id' => $conduite,
        'classe_id' => 132, 'libelle' => 'Libellé modifié', 'coefficient' => '2', 'note_max' => '20', 'date_evaluation' => '2026-01-10',
    ]);
    check($lib(55) === 'Libellé modifié' && (float)$one('SELECT coefficient FROM evaluations WHERE id = 55') === 2.0, 'A6 : libellé de l\'évaluation 55 modifié, coefficient 2,00 conservé (doublon historique toléré)');
    $n1 = $nbEv();
    $http('admin', $ADMIN, 'POST', '/v2/academique/evaluations', array_merge($formEval($conduite, 'Troisième Conduite 132'), ['periode_scolaire_id' => 100, 'classe_id' => 132]));
    check($nbEv() === $n1, 'A6 : créer une évaluation de Conduite de plus pour (132, 100) est refusé');

    // Groupes 3 — publier / verrouiller une évaluation
    $http('enseignant', $ENS, 'POST', "/v2/academique/evaluations/$brC/publier");
    check($statutEval($brC) === 'brouillon', 'G3 enseignant : publication d\'une évaluation de Conduite refusée');
    $http('enseignant', $ENS, 'POST', "/v2/academique/evaluations/$brO/publier");
    check($statutEval($brO) === 'publiee', 'G3 enseignant : publication d\'une évaluation ordinaire permise (témoin)');
    $http('admin', $ADMIN, 'POST', "/v2/academique/evaluations/$brC/publier");
    check($statutEval($brC) === 'publiee', 'G3 admin : publication d\'une évaluation de Conduite acceptée');
    $http('enseignant', $ENS, 'POST', "/v2/academique/evaluations/$brC/verrouiller");
    check($statutEval($brC) === 'publiee', 'G3 enseignant : verrouillage d\'une évaluation de Conduite refusé');
    $http('enseignant', $ENS, 'POST', "/v2/academique/evaluations/$brO/verrouiller");
    check($statutEval($brO) === 'verrouillee', 'G3 enseignant : verrouillage d\'une évaluation ordinaire permis (témoin)');
    $http('directeur', $DIR, 'POST', "/v2/academique/evaluations/$brC/verrouiller");
    check($statutEval($brC) === 'verrouillee', 'G3 directeur : verrouillage d\'une évaluation de Conduite accepté');

    // Groupe 10 — déverrouiller / archiver
    $http('enseignant', $ENS, 'POST', "/v2/academique/evaluations/$brC/deverrouiller");
    check($statutEval($brC) === 'verrouillee', 'G8 enseignant : déverrouillage d\'une évaluation de Conduite refusé');
    $http('admin', $ADMIN, 'POST', "/v2/academique/evaluations/$brC/deverrouiller");
    check($statutEval($brC) === 'publiee', 'G8 admin : déverrouillage d\'une évaluation de Conduite accepté (conduite.manage)');

    // Groupes 4 à 7 — notes (saisie, modification, publication, verrouillage, suppression)
    $note = fn(int $ev, int $el) => $db->query("SELECT * FROM notes_v2 WHERE evaluation_id = $ev AND eleve_id = $el")->fetch();
    $db->exec("UPDATE evaluations SET notes_saisie_ouverte = 1 WHERE id IN ($brC, $brO)");
    $db->exec("UPDATE evaluations SET statut = 'publiee' WHERE id = $brO");
    $http('enseignant', $ENS, 'POST', "/v2/academique/evaluations/$brC/notes", ['notes' => [1 => ['valeur' => '15']]]);
    check($note($brC, 1) === false, 'G4 enseignant : saisie d\'une note de Conduite refusée');
    $http('enseignant', $ENS, 'POST', "/v2/academique/evaluations/$brO/notes", ['notes' => [1 => ['valeur' => '12']]]);
    check(($n = $note($brO, 1)) !== false && (float)$n['valeur'] === 12.0, 'G4 enseignant : saisie d\'une note ordinaire permise (témoin)');
    $http('admin', $ADMIN, 'POST', "/v2/academique/evaluations/$brC/notes", ['notes' => [1 => ['valeur' => '15'], 2 => ['valeur' => '0']]]);
    check(($n = $note($brC, 1)) !== false && (float)$n['valeur'] === 15.0 && ($z = $note($brC, 2)) !== false && (float)$z['valeur'] === 0.0, 'G4 admin : saisie de notes de Conduite acceptée (zéro valide)');
    $http('directeur', $DIR, 'POST', "/v2/academique/evaluations/$brC/notes", ['notes' => [3 => ['valeur' => '14']]]);
    check($note($brC, 3) !== false, 'G4 directeur : saisie acceptée');
    $nC1 = (int)$note($brC, 1)['id']; $nO1 = (int)$note($brO, 1)['id'];
    $http('enseignant', $ENS, 'POST', "/v2/academique/notes/$nC1", ['valeur' => '5']);
    check((float)$note($brC, 1)['valeur'] === 15.0, 'G5 enseignant : modification d\'une note de Conduite refusée');
    $http('enseignant', $ENS, 'POST', "/v2/academique/notes/$nO1", ['valeur' => '13']);
    check((float)$note($brO, 1)['valeur'] === 13.0, 'G5 enseignant : modification d\'une note ordinaire permise (témoin)');
    $http('admin', $ADMIN, 'POST', "/v2/academique/notes/$nC1", ['valeur' => '16']);
    check((float)$note($brC, 1)['valeur'] === 16.0, 'G5 admin : modification d\'une note de Conduite acceptée');
    $http('enseignant', $ENS, 'POST', "/v2/academique/evaluations/$brC/notes/publier");
    check($note($brC, 1)['statut'] === 'saisie', 'G6 enseignant : publication des notes de Conduite refusée');
    $http('enseignant', $ENS, 'POST', "/v2/academique/evaluations/$brO/notes/publier");
    check($note($brO, 1)['statut'] === 'publiee', 'G6 enseignant : publication des notes ordinaires permise (témoin)');
    $http('admin', $ADMIN, 'POST', "/v2/academique/evaluations/$brC/notes/publier");
    check($note($brC, 1)['statut'] === 'publiee', 'G6 admin : publication des notes de Conduite acceptée');
    $db->exec("UPDATE evaluations SET statut = 'verrouillee', notes_saisie_ouverte = 0 WHERE id = $brC");
    $http('enseignant', $ENS, 'POST', "/v2/academique/evaluations/$brC/notes/verrouiller");
    check($note($brC, 1)['statut'] === 'publiee', 'G7 enseignant : verrouillage des notes de Conduite refusé');
    $http('directeur', $DIR, 'POST', "/v2/academique/evaluations/$brC/notes/verrouiller");
    check($note($brC, 1)['statut'] === 'verrouillee', 'G7 directeur : verrouillage des notes de Conduite accepté');
    $db->exec("UPDATE notes_v2 SET statut = 'saisie' WHERE evaluation_id = $brC");
    $http('enseignant', $ENS, 'POST', "/v2/academique/notes/$nC1/delete");
    check($note($brC, 1) !== false, 'G8 enseignant : suppression d\'une note de Conduite refusée');
    $http('admin', $ADMIN, 'POST', "/v2/academique/notes/$nC1/delete");
    check($note($brC, 1) === false, 'G8 admin : suppression d\'une note de Conduite acceptée (conduite.manage)');
    $http('admin', $ADMIN, 'POST', "/v2/academique/notes/$nO1/delete");
    check($note($brO, 1) !== false, 'G8 admin : suppression d\'une note ordinaire inchangée (notes.admin absent du palier actif) — témoin');
    $db->exec("DELETE FROM notes_v2 WHERE evaluation_id = $brC");
    $db->exec("UPDATE evaluations SET statut = 'publiee' WHERE id = $brC");
    $http('enseignant', $ENS, 'POST', "/v2/academique/evaluations/$brC/archiver");
    check($statutEval($brC) === 'publiee', 'G8 enseignant : archivage d\'une évaluation de Conduite refusé');
    $http('directeur', $DIR, 'POST', "/v2/academique/evaluations/$brC/archiver");
    check($statutEval($brC) === 'archivee', 'G8 directeur : archivage d\'une évaluation de Conduite accepté');

    // ═════════════════════════════════════════════════════════════════════
    section('3. A5 — pas de mention « absent » pour la Conduite (formulaire, serveur, import CSV)');
    [$r, $body] = $http('admin', $ADMIN, 'GET', '/v2/academique/evaluations/4/notes/saisie');
    check(($r['status'] ?? 0) === 200 && !str_contains($body, 'est_absent') && !str_contains($body, 'Tout absent'), 'formulaire de Conduite : ni case « absent » ni bouton « Tout absent »');
    [$r, $body] = $http('admin', $ADMIN, 'GET', "/v2/academique/evaluations/$evOrd/notes/saisie");
    check(($r['status'] ?? 0) === 200 && str_contains($body, 'est_absent') && str_contains($body, 'Tout absent'), 'formulaire d\'une matière ordinaire : case « absent » conservée');
    $avant = $db->query('SELECT COUNT(*) FROM notes_v2 WHERE evaluation_id = 4 AND eleve_id = 121')->fetchColumn();
    $http('admin', $ADMIN, 'POST', '/v2/academique/evaluations/4/notes', ['notes' => [121 => ['est_absent' => '1']]]);
    check((int)$db->query('SELECT COUNT(*) FROM notes_v2 WHERE evaluation_id = 4 AND eleve_id = 121')->fetchColumn() === (int)$avant, 'envoi direct est_absent = 1 sur la Conduite : refusé, aucune ligne créée');
    $http('admin', $ADMIN, 'POST', '/v2/academique/evaluations/4/notes', ['notes' => [121 => ['valeur' => '']]]);
    check((int)$db->query('SELECT COUNT(*) FROM notes_v2 WHERE evaluation_id = 4 AND eleve_id = 121')->fetchColumn() === (int)$avant, 'ligne vide pour la Conduite : non enregistrée (note « non saisie »)');
    $res = (new \App\Modules\Academique\Services\NoteService())->importerCsv(4, "eleve_id,valeur,absent\n121,,1\n", 1);
    check($res['created'] === 0 && count($res['errors']) === 1 && !(bool)$db->query('SELECT COUNT(*) FROM notes_v2 WHERE evaluation_id = 4 AND eleve_id = 121')->fetchColumn(), 'import CSV : ligne absent = 1 comptée en erreur, rien enregistré');
    $nbExistantsAbsents = (int)$db->query('SELECT COUNT(*) FROM notes_v2 n JOIN evaluations e ON e.id = n.evaluation_id WHERE e.matiere_id = ' . $conduite . ' AND n.est_absent = 1')->fetchColumn();
    check($nbExistantsAbsents === 0, 'aucune note de Conduite existante n\'est « absente »');

    // ═════════════════════════════════════════════════════════════════════
    section('4. Matière de Conduite — formulaires, suppression, volume horaire, listes d\'affectation');
    $http('admin', $ADMIN, 'POST', "/matieres/$conduite/delete");
    check((int)$one('SELECT COUNT(*) FROM matieres WHERE id = ?', [$conduite]) === 1, 'suppression de la Conduite par l\'interface : refusée');
    $http('admin', $ADMIN, 'POST', "/matieres/$conduite", ['nom' => 'Conduite et discipline', 'coefficient' => '5', 'volume_horaire' => '7', 'categorie' => 'autre', 'responsable_id' => '', 'description' => 'x']);
    $m = $db->query("SELECT nom, coefficient, volume_horaire, est_conduite FROM matieres WHERE id = $conduite")->fetch();
    check($m['nom'] === 'Conduite et discipline' && (float)$m['coefficient'] === 1.0 && (int)$m['volume_horaire'] === 0 && (int)$m['est_conduite'] === 1,
          'modification de la Conduite : libellé modifiable, coefficient forcé à 1, volume à 0, marqueur conservé', json_encode($m));
    $http('admin', $ADMIN, 'POST', "/matieres/$conduite", ['nom' => 'Conduite', 'coefficient' => '1', 'volume_horaire' => '0', 'categorie' => 'autre', 'responsable_id' => '', 'description' => '']);
    [$r, $body] = $http('admin', $ADMIN, 'GET', "/matieres/$conduite");
    check(($r['status'] ?? 0) === 200 && str_contains($body, 'Matière de Conduite') && str_contains($body, 'Protection SQL : opérationnelle'), 'fiche de la Conduite : bandeau et état en direct « Protection SQL : opérationnelle »');
    check(!str_contains($body, 'deleteModal'), 'fiche de la Conduite : aucun bouton de suppression');
    $db->exec('DROP TRIGGER trg_matieres_conduite_bd');
    [$r, $body] = $http('admin', $ADMIN, 'GET', "/matieres/$conduite");
    check(str_contains($body, 'NON opérationnelle'), 'protection supprimée : l\'état affiché passe à « NON opérationnelle » (calculé en direct)');
    \App\Modules\Academique\Services\ConduiteProtectionStatus::installer($db);
    check(\App\Modules\Academique\Services\ConduiteProtectionStatus::check($db)['ok'], 'protection réinstallée sur la copie');
    $nb = (int)$one('SELECT COUNT(*) FROM matieres');
    $http('admin', $ADMIN, 'POST', '/matieres', ['nom' => 'Conduite bis', 'coefficient' => '1', 'volume_horaire' => '0', 'categorie' => 'autre', 'est_conduite' => '1']);
    check((int)$one('SELECT COUNT(*) FROM matieres') === $nb, 'créer une seconde Conduite dans l\'établissement : refusé');
    $http('admin', $ADMIN, 'POST', '/matieres', ['nom' => 'SansHeures', 'coefficient' => '2', 'volume_horaire' => '0', 'categorie' => 'autre']);
    check((int)$one('SELECT COUNT(*) FROM matieres') === $nb, 'volume 0 refusé pour une matière ordinaire (validation 1 à 30 conservée)');
    $http('admin', $ADMIN, 'POST', '/matieres', ['nom' => 'Ordinaire Lot2', 'coefficient' => '2', 'volume_horaire' => '3', 'categorie' => 'autre', 'est_conduite' => '1']);
    $nv = $db->query("SELECT est_conduite FROM matieres WHERE nom = 'Ordinaire Lot2'")->fetch();
    check($nv === false && (int)$one('SELECT COUNT(*) FROM matieres WHERE est_conduite = 1 AND etablissement_id = 1') === 1, 'case « Conduite » cochée alors qu\'une Conduite existe déjà : création refusée, une seule Conduite');
    [$r, $body] = $http('admin', $ADMIN, 'GET', '/classes/131');
    check(($r['status'] ?? 0) === 200 && !str_contains($body, 'Conduite (coef'), 'liste « affecter un enseignement » (classe) : sans Conduite');
    $nbEns = (int)$one('SELECT COUNT(*) FROM enseignements');
    $http('admin', $ADMIN, 'POST', '/classes/131/affecter-enseignant', ['professeur_id' => 1, 'matiere_id' => $conduite, 'annee_scolaire' => '2099-2100']);
    check((int)$one('SELECT COUNT(*) FROM enseignements') === $nbEns, 'affectation d\'un enseignant à la Conduite (POST direct, classe) : refusée');
    $http('admin', $ADMIN, 'POST', '/professeurs/1/affecter-enseignement', ['matiere_id' => $conduite, 'classe_id' => 131, 'annee_scolaire' => '2099-2100']);
    check((int)$one('SELECT COUNT(*) FROM enseignements') === $nbEns, 'affectation d\'un enseignement de Conduite (POST direct, professeur) : refusée');
    check((int)$one('SELECT COUNT(*) FROM enseignements WHERE matiere_id = ?', [$conduite]) === (int)$source->query('SELECT COUNT(*) FROM enseignements WHERE matiere_id = ' . $conduite)->fetchColumn(), 'affectations historiques de la Conduite conservées');
    $totalHeures = (int)$one('SELECT SUM(volume_horaire) FROM matieres WHERE etablissement_id = 1');
    check($totalHeures === (int)$source->query("SELECT SUM(volume_horaire) - SUM(CASE WHEN LOWER(nom) = 'conduite' THEN volume_horaire ELSE 0 END) FROM matieres WHERE etablissement_id = 1")->fetchColumn() && (int)$one('SELECT volume_horaire FROM matieres WHERE id = ?', [$conduite]) === 0, 'totaux d\'heures : la Conduite ne compte plus aucune heure (valable que la source soit migrée ou non)');

    // ═════════════════════════════════════════════════════════════════════
    section('5. Emplois du temps — la Conduite n\'y figure jamais (liste et garde serveur)');
    $http('admin', $ADMIN, 'POST', '/v2/vie-scolaire/emplois-du-temps', ['classe_id' => 131, 'annee_scolaire' => '2025-2026', 'periode_id' => 90, 'semaine_type' => 'standard']);
    $edt = (int)$one('SELECT MAX(id) FROM vs_emplois_du_temps');
    $plage = (int)$one('SELECT id FROM vs_edt_plages_horaires WHERE actif = 1 ORDER BY id LIMIT 1');
    $salle = (int)$one('SELECT id FROM vs_edt_salles WHERE actif = 1 ORDER BY id LIMIT 1');
    $prof = (int)$one("SELECT id FROM users WHERE role = 'enseignant' ORDER BY id LIMIT 1");
    [$r, $body] = $http('admin', $ADMIN, 'GET', "/v2/vie-scolaire/emplois-du-temps/$edt/creneaux/ajouter");
    $ids = [];
    if (preg_match('/<select[^>]*name="matiere_id".*?<\/select>/s', $body, $mm)) { preg_match_all('/<option value="(\d+)"/', $mm[0], $o); $ids = array_map('intval', $o[1]); }
    check(($r['status'] ?? 0) === 200 && $ids !== [] && !in_array($conduite, $ids, true), 'liste des matières du formulaire de créneau : la Conduite est absente (' . count($ids) . ' matières)');
    $http('admin', $ADMIN, 'POST', "/v2/vie-scolaire/emplois-du-temps/$edt/creneaux", ['matiere_id' => $conduite, 'enseignant_id' => $prof, 'jour' => 2, 'plage_id' => $plage, 'salle_id' => $salle, 'type_cours' => 'cours']);
    check((int)$one('SELECT COUNT(*) FROM vs_edt_creneaux') === 0, 'POST direct d\'un créneau de Conduite : refusé par le serveur');
    $http('admin', $ADMIN, 'POST', "/v2/vie-scolaire/emplois-du-temps/$edt/creneaux", ['matiere_id' => $ordinaire, 'enseignant_id' => $prof, 'jour' => 2, 'plage_id' => $plage, 'salle_id' => $salle, 'type_cours' => 'cours']);
    $creneau = (int)$one('SELECT id FROM vs_edt_creneaux LIMIT 1');
    check($creneau > 0, 'créneau d\'une matière ordinaire : toujours accepté (témoin)');
    $http('admin', $ADMIN, 'POST', "/v2/vie-scolaire/emplois-du-temps/creneaux/$creneau/modifier", ['matiere_id' => $conduite, 'enseignant_id' => $prof, 'jour' => 2, 'plage_id' => $plage, 'salle_id' => $salle, 'type_cours' => 'cours']);
    check((int)$one('SELECT matiere_id FROM vs_edt_creneaux WHERE id = ?', [$creneau]) === $ordinaire, 'modification d\'un créneau vers la Conduite : refusée');

    // ═════════════════════════════════════════════════════════════════════
    section('6. A1/A2/R2 — complétude stricte (service, sur la copie)');
    $svcC = new ConduiteCompletudeService($db);
    $db->exec("UPDATE eleves SET actif = 1 WHERE classe_id IN (131, 132)");
    $e = $svcC->etatClasse(131, 90);
    check($e['concerne'] && !$e['complete'] && $e['raison'] === 'notes_manquantes' && count($e['manquants']) === 1 && $e['manquants'][0]['eleve_id'] === 121,
          'A1 classe 131 / 90 : incomplète, seul l\'élève 121 (aucune note) manque', json_encode($e['manquants']));
    check(str_contains($e['message'], '1 élève'), 'message : liste des élèves manquants');
    $db->exec('UPDATE eleves SET actif = 0 WHERE id = 121');
    check($svcC->etatClasse(131, 90)['complete'] === true, 'A1 élève inactif sans note : classe complète');
    $db->exec('UPDATE eleves SET actif = 1 WHERE id = 121');
    $db->exec('INSERT INTO notes_v2 (evaluation_id, eleve_id, valeur, est_absent, statut) VALUES (4, 121, NULL, 1, \'saisie\')');
    check(!$svcC->etatClasse(131, 90)['complete'], 'A1 ligne forcée avec absence cochée : comptée manquante');
    $db->exec('UPDATE notes_v2 SET valeur = 0, est_absent = 0 WHERE evaluation_id = 4 AND eleve_id = 121');
    check($svcC->etatClasse(131, 90)['complete'], 'zéro est une note valide');
    $db->exec('UPDATE notes_v2 SET valeur = 25 WHERE evaluation_id = 4 AND eleve_id = 121');
    check(!$svcC->etatClasse(131, 90)['complete'], 'valeur au-dessus du barème : invalide, comptée manquante');
    $db->exec('DELETE FROM notes_v2 WHERE evaluation_id = 4 AND eleve_id = 121');
    $e132 = $svcC->etatClasse(132, 100);
    check($e132['complete'] && count($e132['evaluations']) === 2, 'R2 classe 132 : deux évaluations actives, toutes exigées, classe complète');
    $db->exec('DELETE FROM notes_v2 WHERE evaluation_id = 55 AND eleve_id = 109');
    $e132 = $svcC->etatClasse(132, 100);
    check(!$e132['complete'] && $e132['manquants'][0]['evaluation_id'] === 55, 'R2 : une note manquante dans la seconde évaluation rend la classe incomplète');
    check((int)$one("SELECT COUNT(*) FROM evaluations WHERE matiere_id = $conduite AND classe_id = 132 AND periode_scolaire_id = 100 AND statut <> 'archivee'") === 2, 'R2 : aucune évaluation archivée automatiquement');
    $db->exec("UPDATE evaluations SET statut = 'brouillon' WHERE matiere_id = $conduite AND classe_id = 131 AND periode_scolaire_id = 90");
    $e = $svcC->etatClasse(131, 90);
    check(!$e['complete'] && $e['raison'] === 'evaluation_non_publiee', 'A2 évaluation de Conduite en brouillon seulement : « évaluation non publiée »', $e['raison']);
    $db->exec("UPDATE evaluations SET statut = 'archivee' WHERE matiere_id = $conduite AND classe_id = 131 AND periode_scolaire_id = 90");
    $e = $svcC->etatClasse(131, 90);
    check(!$e['complete'] && $e['raison'] === 'aucune_evaluation', 'A2 aucune évaluation de Conduite active : « aucune évaluation »', $e['raison']);
    $db->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('lot2-sans-conduite', 'Sans Conduite', 'SC', 'lycee')");
    $etab2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire) VALUES ($etab2, 'X1', '6eme', '2025-2026')");
    $cl2 = (int)$db->lastInsertId();
    $e = $svcC->etatClasse($cl2, 90);
    check($e['concerne'] === false && $e['complete'] === true && $e['raison'] === 'non_concerne', 'A2 établissement sans matière de Conduite marquée : aucun contrôle');
} finally {
    $copy->drop();
}

section('7. Base réelle');
check(DbCopy::allDataHashes($source) === $avantSource, 'base réelle inchangée (empreinte de toutes les tables)');
check(count(array_filter($source->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN), fn($d) => str_starts_with($d, 'ecole_app_copy_'))) === 0, 'aucune base temporaire restante');

echo "\n" . ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : $passed/" . ($passed + $failed) . " assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
