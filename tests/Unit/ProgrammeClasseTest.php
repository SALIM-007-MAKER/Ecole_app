<?php
/**
 * Tests — programme pédagogique d'une classe (migration T075, table classe_matieres, service, interface).
 *
 * 1. Avant la migration : fonctionnalité inerte, application inchangée
 * 2. Migration T075 : structure, contraintes, idempotence, retour arrière, aucune donnée existante touchée
 * 3. Validation : coefficient (0 à 20, une décimale, 0 = non comptée) et volume horaire (0 à 40)
 * 4. Programme : ajout, valeurs par défaut, INDÉPENDANCE vis-à-vis des enseignements, Conduite exclue, isolation entre établissements
 * 5. Enregistrement groupé, retrait, initialisation depuis les enseignements (explicite, sans écrasement)
 * 6. Intégrité : matière RESTRICT, suppression de classe en CASCADE, journal d'audit
 * 7. Verrou : bulletin publié ou archivé = programme en lecture seule ; brouillon = modifiable ; bulletins jamais touchés
 * 8. Notes : confirmation explicite exigée, jamais de recalcul
 * 9. Reproduction dans les classes parallèles (compléter / aligner), refus des classes non parallèles
 * 10. HTTP : onglet, actions, permissions, isolation
 * 11. Non-régression : élèves, inscriptions, enseignements, matières, évaluations, notes, moyennes, bulletins inchangés
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/ProgrammeClasseTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\Models\\' => '/app/Models/', 'App\\Services\\' => '/app/Services/', 'App\\Modules\\' => '/app/Modules/', 'App\\Shared\\' => '/app/Shared/', 'App\\Controllers\\' => '/app/Controllers/'] as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php';
            if (is_file($f)) { require $f; return; }
        }
    }
});
// Les exceptions du programme sont déclarées dans le fichier du service.
require_once ROOT_PATH . '/app/Services/ProgrammeClasseService.php';
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use App\Services\ProgrammeClasseService as P;
use App\Services\ProgrammeConfirmationException;
use App\Services\ProgrammeException;
use App\Services\ProgrammeVerrouilleException;
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
/** Classe de l'exception levée par $f, ou null. */
function leve(callable $f): ?string
{
    try { $f(); return null; } catch (\Throwable $e) { return $e::class; }
}

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$admin = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' AND etablissement_id = 1 ORDER BY id LIMIT 1")->fetchColumn();
if ($admin === 0) { exit("ECHEC : aucun administrateur d'école dans la copie\n"); }

function http(string $nomCopie, string $role, int $uid, string $methode, string $uri, array $post = [], bool $commit = false): array
{
    $corps = sys_get_temp_dir() . '/prog_' . bin2hex(random_bytes(4)) . '.html';
    [, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/request_as.php', $role, (string)$uid, $methode, '/ecole_app' . $uri, json_encode($post)],
        ['REQUEST_AS_DB' => $nomCopie, 'REQUEST_AS_BODY_OUT' => $corps] + ($commit ? ['REQUEST_AS_COMMIT' => '1'] : []));
    $r = preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: []) : [];
    $body = (string)@file_get_contents($corps); @unlink($corps);
    return ['status' => (int)($r['status'] ?? 0), 'body' => $body];
}
$checksum = static function (array $tables) use ($pdo): array {
    $o = [];
    foreach ($tables as $t) { $o[$t] = (string)$pdo->query("CHECKSUM TABLE `$t`")->fetch(PDO::FETCH_ASSOC)['Checksum']; }
    return $o;
};
$protegees = ['eleves', 'inscriptions', 'enseignements', 'evaluations', 'notes_v2', 'moyennes_generales', 'moyennes_matieres', 'periodes_scolaires'];
$avantTout = $checksum($protegees);
$matieresAvant = md5(json_encode($pdo->query('SELECT * FROM matieres WHERE etablissement_id = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)));   // matières de l'établissement réel (le test crée une matière dans un autre établissement)
$moyennesAvant = [(int)$pdo->query('SELECT COUNT(*) FROM moyennes_generales')->fetchColumn(), (int)$pdo->query('SELECT COUNT(*) FROM moyennes_matieres')->fetchColumn()];
// Jeu de démonstration : les classes 1 à 4 n'ont ni notes ni bulletins ; 131 et 132 ont évaluations, notes et bulletins en brouillon.
$matiere = static fn(string $nom): int => (int)$pdo->query("SELECT id FROM matieres WHERE etablissement_id = 1 AND nom = " . $pdo->quote($nom))->fetchColumn();
$MATHS = $matiere('Mathématiques'); $FRANCAIS = $matiere('Français'); $ANGLAIS = $matiere('Anglais'); $EPS = $matiere('EPS'); $CONDUITE = $matiere('Conduite'); $ARABE = $matiere('Arabe');
$lignes = static fn(int $classe): array => $pdo->query("SELECT matiere_id, coefficient, volume_horaire, ordre FROM classe_matieres WHERE classe_id = $classe ORDER BY matiere_id")->fetchAll(PDO::FETCH_ASSOC);
$nbLignes = static fn(int $classe): int => (int)$pdo->query("SELECT COUNT(*) FROM classe_matieres WHERE classe_id = $classe")->fetchColumn();

// ═══════════════════════════════════════════════════════════
section('1. Avant la migration : fonctionnalité inerte');
P::oublierDetection();
check(P::disponible() === false, 'table classe_matieres absente (état réel avant T075)');
$r = http($nomCopie, 'admin', $admin, 'GET', '/classes/131?tab=programme');
check($r['status'] === 200 && !str_contains($r['body'], 'id="tab-btn-programme"'), 'fiche de classe : 200, aucun onglet Programme');
$r = http($nomCopie, 'admin', $admin, 'POST', '/classes/131/programme/ajouter', ['matiere_id' => $MATHS], true);
check($r['status'] !== 500 && !str_contains($r['body'], 'Fatal error') && !str_contains($r['body'], 'PDOException'), 'POST programme sans table : refus propre, aucune erreur SQL', (string)$r['status']);
$r = http($nomCopie, 'admin', $admin, 'GET', '/classes/131');
check($r['status'] === 200, 'fiche de classe : onglets Élèves et Enseignements intacts');

// ═══════════════════════════════════════════════════════════
section('2. Migration T075');
$m = require ROOT_PATH . '/database/migrations/T075_classe_matieres.php';
check($m['id'] === 'T075' && $m['reversible'] === true, 'identifiant T075, réversible');
$m['run']($pdo);
P::oublierDetection();
check(P::disponible() === true, 'détection : fonctionnalité disponible');
$cols = $pdo->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classe_matieres'")->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
check(($cols['coefficient']['COLUMN_TYPE'] ?? '') === 'decimal(3,1)' && ($cols['volume_horaire']['COLUMN_TYPE'] ?? '') === 'tinyint unsigned' && ($cols['classe_id']['IS_NULLABLE'] ?? '') === 'NO'
    && isset($cols['etablissement_id'], $cols['matiere_id'], $cols['ordre']), 'colonnes : coefficient DECIMAL(3,1), volume TINYINT UNSIGNED, ids non nuls');
$ddl = $pdo->query('SHOW CREATE TABLE classe_matieres')->fetch(PDO::FETCH_NUM)[1];
check(str_contains($ddl, 'UNIQUE KEY `uq_cm_classe_matiere` (`classe_id`,`matiere_id`)'), 'unicité (classe, matière)');
check(str_contains($ddl, 'fk_cm_matiere') && str_contains($ddl, 'ON DELETE RESTRICT') && preg_match('/fk_cm_classe.*ON DELETE CASCADE/', $ddl) === 1, 'clés étrangères : matière RESTRICT, classe CASCADE');
check(str_contains($ddl, 'chk_cm_coefficient') && str_contains($ddl, 'chk_cm_volume'), 'bornes contrôlées aussi en base (CHECK)');
check($nbLignes(131) === 0 && (int)$pdo->query('SELECT COUNT(*) FROM classe_matieres')->fetchColumn() === 0, 'aucune ligne insérée : les classes existantes n\'ont pas de programme');
$m['run']($pdo); $m['run']($pdo);
check($pdo->query('SHOW CREATE TABLE classe_matieres')->fetch(PDO::FETCH_NUM)[1] === $ddl, 'idempotence : deux relances, structure identique');
check($checksum($protegees) === $avantTout, 'tables existantes : contenu strictement identique après la migration');
$m['rollback']($pdo);
check((int)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classe_matieres'")->fetchColumn() === 0 && $checksum($protegees) === $avantTout, 'retour arrière : table retirée, tables existantes intactes');
$m['run']($pdo);
P::oublierDetection();
check(P::disponible() === true, 'ré-application après retour arrière');
$check = leve(fn() => $pdo->exec("INSERT INTO classe_matieres (etablissement_id, classe_id, matiere_id, coefficient, volume_horaire) VALUES (1, 131, $MATHS, 21, 2)"));
check($check !== null, 'base : coefficient 21 refusé (CHECK)');
$check = leve(fn() => $pdo->exec("INSERT INTO classe_matieres (etablissement_id, classe_id, matiere_id, coefficient, volume_horaire) VALUES (1, 131, $MATHS, 2, 41)"));
check($check !== null, 'base : volume 41 refusé (CHECK)');

// ═══════════════════════════════════════════════════════════
section('3. Validation');
foreach ([['3', 3.0], ['3,5', 3.5], ['0', 0.0], ['20', 20.0], [' 2.5 ', 2.5], [4, 4.0], [1.5, 1.5], ['0,5', 0.5]] as [$in, $out]) {
    check(P::coefficientValide($in) === $out, 'coefficient ' . json_encode($in) . ' → ' . $out);
}
foreach (['20,1', '21', '-1', 'abc', '', '2,55', '1e1', '3 4', '0x10', '3,', '.5', null, [], true] as $mauvais) {
    check(leve(fn() => P::coefficientValide($mauvais)) === ProgrammeException::class, 'coefficient ' . json_encode($mauvais) . ' refusé');
}
foreach ([['5', 5], ['0', 0], ['40', 40], [6, 6], [' 7 ', 7], [3.0, 3]] as [$in, $out]) {
    check(P::volumeValide($in) === $out, 'volume ' . json_encode($in) . ' → ' . $out);
}
foreach (['41', '-1', '2.5', 'abc', '', '5h', null, [], 40.5, 41] as $mauvais) {
    check(leve(fn() => P::volumeValide($mauvais)) === ProgrammeException::class, 'volume ' . json_encode($mauvais) . ' refusé');
}

// ═══════════════════════════════════════════════════════════
section('4. Programme : ajout, valeurs par défaut, indépendance des enseignements');
$svc = new P($pdo, 1, $admin);
check($svc->programme(1) === [], 'programme initial vide (aucun remplissage automatique)');
$ajoutables = array_column($svc->ajoutables(1), 'id');
check(in_array($MATHS, array_map('intval', $ajoutables), true) && !in_array($CONDUITE, array_map('intval', $ajoutables), true), 'matières ajoutables : la Conduite est exclue');
$svc->ajouter(1, $MATHS, '4,5', '6');
$l = $svc->programme(1)[0];
check((float)$l['coefficient'] === 4.5 && (int)$l['volume_horaire'] === 6 && (float)$l['coefficient_defaut'] === 5.0 && (int)$l['volume_defaut'] === 5, 'Mathématiques : coefficient 4,5 et 6 h propres à la classe, défauts de la matière conservés (5 et 5 h)');
$svc->ajouter(1, $FRANCAIS);
$fr = array_column($svc->programme(1), null, 'matiere_id')[$FRANCAIS];
check((float)$fr['coefficient'] === 3.0 && (int)$fr['volume_horaire'] === 4, 'champs vides : valeurs par défaut de la matière reprises');
check(leve(fn() => $svc->ajouter(1, $MATHS)) === ProgrammeException::class, 'doublon refusé');
check(leve(fn() => $svc->ajouter(1, $CONDUITE)) === ProgrammeException::class, 'Conduite refusée au programme');
check(leve(fn() => $svc->ajouter(1, 999999)) === ProgrammeException::class, 'matière inexistante refusée');
check(leve(fn() => $svc->ajouter(1, $ANGLAIS, '99')) === ProgrammeException::class && $nbLignes(1) === 2, 'coefficient hors bornes refusé, rien d\'écrit');
check(leve(fn() => $svc->ajouter(999999, $ANGLAIS)) === ProgrammeException::class, 'classe inexistante refusée');

$sansEns = (int)$pdo->query("SELECT m.id FROM matieres m WHERE m.etablissement_id = 1 AND m.est_conduite IS NULL AND m.id NOT IN (SELECT matiere_id FROM enseignements WHERE classe_id = 1) AND m.id NOT IN (SELECT matiere_id FROM classe_matieres WHERE classe_id = 1) ORDER BY m.id LIMIT 1")->fetchColumn();
$nbEns = (int)$pdo->query('SELECT COUNT(*) FROM enseignements')->fetchColumn();
check($sansEns > 0, 'jeu de données : une matière sans enseignant dans la classe 1 existe', (string)$sansEns);
$svc->ajouter(1, $sansEns, '2', '3');
$ls = array_column($svc->programme(1), null, 'matiere_id');
check(isset($ls[$sansEns]) && (int)$ls[$sansEns]['nb_enseignements'] === 0, 'matière au programme SANS enseignant affecté : acceptée (programme indépendant des enseignements)');
check((int)$pdo->query('SELECT COUNT(*) FROM enseignements')->fetchColumn() === $nbEns, 'aucun enseignement créé ni modifié par le programme');

$pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('autre-etab', 'Autre', 'AE', 'lycee')");
$etab2 = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO matieres (etablissement_id, nom, coefficient, categorie, volume_horaire) VALUES ($etab2, 'Matière autre école', 2.0, 'autre', 2)");
$matEtab2 = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire, max_eleves) VALUES ($etab2, 'X1', 'Seconde', '2025-2026', 30)");
$classeEtab2 = (int)$pdo->lastInsertId();
check(leve(fn() => $svc->ajouter(1, $matEtab2)) === ProgrammeException::class, 'isolation : matière d\'un autre établissement refusée');
check(leve(fn() => $svc->ajouter($classeEtab2, $MATHS)) === ProgrammeException::class, 'isolation : classe d\'un autre établissement refusée');
check(leve(fn() => $svc->programme($classeEtab2)) === ProgrammeException::class && leve(fn() => $svc->paralleles($classeEtab2)) === ProgrammeException::class, 'isolation : lecture du programme d\'une autre classe refusée');
$svc2 = new P($pdo, $etab2, null);
check(leve(fn() => $svc2->programme(1)) === ProgrammeException::class, 'isolation : l\'autre établissement ne voit pas la classe 1');

// ═══════════════════════════════════════════════════════════
section('5. Enregistrement groupé, retrait, initialisation depuis les enseignements');
$maj0 = $pdo->query("SELECT updated_at FROM classe_matieres WHERE classe_id = 1 AND matiere_id = $FRANCAIS")->fetchColumn();
check($svc->enregistrer(1, [$MATHS => ['coefficient' => '4,5', 'volume_horaire' => '6', 'ordre' => '0']]) === 0, 'valeurs inchangées : aucune écriture');
check($svc->enregistrer(1, [$MATHS => ['coefficient' => '3'], $FRANCAIS => ['volume_horaire' => '5', 'ordre' => '2']]) === 2, 'deux lignes modifiées en une fois');
$ls = array_column($svc->programme(1), null, 'matiere_id');
check((float)$ls[$MATHS]['coefficient'] === 3.0 && (int)$ls[$MATHS]['volume_horaire'] === 6 && (int)$ls[$FRANCAIS]['volume_horaire'] === 5 && (int)$ls[$FRANCAIS]['ordre'] === 2, 'valeurs enregistrées, champs non précisés conservés');
$avant = $lignes(1);
check(leve(fn() => $svc->enregistrer(1, [$MATHS => ['coefficient' => '7'], $FRANCAIS => ['coefficient' => 'oups']])) === ProgrammeException::class && $lignes(1) === $avant, 'une valeur invalide : tout est refusé, rien d\'écrit (tout ou rien)');
check(leve(fn() => $svc->enregistrer(1, [$ANGLAIS => ['coefficient' => '2']])) === ProgrammeException::class, 'matière absente du programme : refusée');
$svc->retirer(1, $sansEns);
check(!isset(array_column($svc->programme(1), null, 'matiere_id')[$sansEns]) && (int)$pdo->query("SELECT COUNT(*) FROM matieres WHERE id = $sansEns")->fetchColumn() === 1, 'retrait : ligne supprimée, matière intacte');
check(leve(fn() => $svc->retirer(1, $sansEns)) === ProgrammeException::class, 'retirer une matière absente : refusé');

check($nbLignes(3) === 0, 'classe 3 : sans programme');
$nbEns3 = (int)$pdo->query("SELECT COUNT(DISTINCT en.matiere_id) FROM enseignements en JOIN matieres m ON m.id = en.matiere_id WHERE en.classe_id = 3 AND m.est_conduite IS NULL")->fetchColumn();
$ajoutes = $svc->initialiserDepuisEnseignements(3);
check($ajoutes === $nbEns3 && $ajoutes > 0 && $nbLignes(3) === $nbEns3, "initialisation explicite : $ajoutes matière(s) reprises des enseignements");
$ecart = (int)$pdo->query("SELECT COUNT(*) FROM classe_matieres cm JOIN matieres m ON m.id = cm.matiere_id WHERE cm.classe_id = 3 AND (cm.coefficient <> m.coefficient OR cm.volume_horaire <> m.volume_horaire)")->fetchColumn();
check($ecart === 0, 'valeurs initiales = valeurs par défaut des matières (résultats inchangés)');
check((int)$pdo->query("SELECT COUNT(*) FROM classe_matieres WHERE classe_id = 3 AND matiere_id = $CONDUITE")->fetchColumn() === 0, 'Conduite non reprise');
check($svc->initialiserDepuisEnseignements(3) === 0, 'seconde initialisation : rien à ajouter, rien d\'écrasé');
$svc->enregistrer(3, [$MATHS => ['coefficient' => '1']]);
$svc->initialiserDepuisEnseignements(3);
check((float)array_column($svc->programme(3), null, 'matiere_id')[$MATHS]['coefficient'] === 1.0, 'une ligne personnalisée n\'est jamais écrasée par l\'initialisation');

// ═══════════════════════════════════════════════════════════
section('6. Intégrité et audit');
check(leve(fn() => $pdo->exec("DELETE FROM matieres WHERE id = $MATHS")) !== null && (int)$pdo->query("SELECT COUNT(*) FROM matieres WHERE id = $MATHS")->fetchColumn() === 1, 'matière au programme : suppression refusée par la base (RESTRICT)');
$http = http($nomCopie, 'admin', $admin, 'POST', "/matieres/$MATHS/delete", [], true);
check((int)$pdo->query("SELECT COUNT(*) FROM matieres WHERE id = $MATHS")->fetchColumn() === 1 && !str_contains($http['body'], 'PDOException'), 'suppression de la matière depuis l\'application : refusée proprement');
$pdo->exec("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire, max_eleves) VALUES (1, 'TMP', 'Seconde', '2030-2031', 30)");
$tmp = (int)$pdo->lastInsertId();
$svc->ajouter($tmp, $MATHS);
$pdo->exec("DELETE FROM classes WHERE id = $tmp");
check($nbLignes($tmp) === 0 && (int)$pdo->query("SELECT COUNT(*) FROM matieres WHERE id = $MATHS")->fetchColumn() === 1, 'suppression d\'une classe : son programme part avec elle, la matière reste');
$aud = $pdo->query("SELECT action, entite, avant, apres FROM audit_logs WHERE module = 'classes' AND entite = 'programme_classe' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$actions = array_column($aud, 'action');
check(in_array('create', $actions, true) && in_array('update', $actions, true) && in_array('delete', $actions, true), 'journal d\'audit : création, modification et retrait consignés', json_encode(array_count_values($actions)));
$maj = array_values(array_filter($aud, fn($a) => $a['action'] === 'update' && str_contains((string)$a['avant'], 'coefficient')));
check($maj !== [] && str_contains((string)$maj[0]['avant'], 'matiere') && str_contains((string)$maj[0]['apres'], 'coefficient'), 'audit : valeurs avant et après');

// ═══════════════════════════════════════════════════════════
section('7. Verrou : bulletins publiés ou archivés');
$L = 4;   // Terminale A : ni notes ni bulletins dans le jeu de démonstration
$periode = (int)$pdo->query('SELECT id FROM periodes_scolaires WHERE etablissement_id = 1 ORDER BY id LIMIT 1')->fetchColumn();
$eleveL = (int)$pdo->query("SELECT id FROM eleves WHERE classe_id = $L AND actif = 1 ORDER BY id LIMIT 1")->fetchColumn();
$eleve1 = (int)$pdo->query('SELECT id FROM eleves WHERE classe_id = 1 AND actif = 1 ORDER BY id LIMIT 1')->fetchColumn();
check($svc->verrou(131) === null && $svc->verrou(132) === null, 'classes 131 et 132 : bulletins en BROUILLON dans le jeu de démonstration, programme modifiable');
$svc->ajouter($L, $MATHS, '5', '5');
check($svc->verrou($L) === null, 'sans bulletin : programme modifiable');
$ins = $pdo->prepare("INSERT INTO bulletins_v2 (eleve_id, classe_id, periode_id, verification_token, statut) VALUES (?, $L, ?, ?, ?)");
$ins->execute([$eleveL, $periode, str_repeat('a', 64), 'brouillon']);
check($svc->verrou($L) === null, 'bulletin en BROUILLON : programme toujours modifiable');
$svc->enregistrer($L, [$MATHS => ['coefficient' => '4']]);
check((float)array_column($svc->programme($L), null, 'matiere_id')[$MATHS]['coefficient'] === 4.0, 'modification acceptée avec un brouillon');
$pdo->exec("UPDATE bulletins_v2 SET statut = 'publie' WHERE classe_id = $L");
$bulletinAvant = $pdo->query("SELECT * FROM bulletins_v2 WHERE classe_id = $L")->fetchAll(PDO::FETCH_ASSOC);
$avantL = $lignes($L);
$verrou = $svc->verrou($L);
check($verrou !== null && str_contains($verrou, 'lecture seule'), 'bulletin PUBLIÉ : programme verrouillé, raison explicite');
foreach ([
    'ajouter' => fn() => $svc->ajouter($L, $FRANCAIS),
    'enregistrer' => fn() => $svc->enregistrer($L, [$MATHS => ['coefficient' => '9']], true),
    'retirer' => fn() => $svc->retirer($L, $MATHS, true),
    'initialiser' => fn() => $svc->initialiserDepuisEnseignements($L),
] as $op => $f) {
    check(leve($f) === ProgrammeVerrouilleException::class, "verrouillé : $op refusé");
}
check($lignes($L) === $avantL && $pdo->query("SELECT * FROM bulletins_v2 WHERE classe_id = $L")->fetchAll(PDO::FETCH_ASSOC) === $bulletinAvant, 'programme et bulletin publié strictement inchangés après les tentatives');
$pdo->exec("UPDATE bulletins_v2 SET statut = 'archive' WHERE classe_id = $L");
check($svc->verrou($L) !== null, 'bulletin ARCHIVÉ : programme verrouillé aussi');
$pdo->exec("UPDATE bulletins_v2 SET statut = 'brouillon' WHERE classe_id = $L");
check($svc->verrou($L) === null, 'plus de bulletin figé : déverrouillé');
$pdo->exec("DELETE FROM bulletins_v2 WHERE classe_id = $L");

// ═══════════════════════════════════════════════════════════
section('8. Notes : confirmation explicite, aucun recalcul');
// La classe 131 porte de VRAIES notes de démonstration (Mathématiques, Français, Anglais… notés ; Arabe, Sciences Naturelles, Philosophie non notés).
$svc->ajouter(131, $MATHS, '5', '5');
$svc->ajouter(131, $ARABE, '3', '5');
$notesAvant = $pdo->query('SELECT * FROM notes_v2 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$evalAvant = $pdo->query('SELECT * FROM evaluations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$bulAvant = $pdo->query('SELECT * FROM bulletins_v2 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
check($svc->notesExistent(131) === true && $svc->notesExistent(131, $MATHS) === true && $svc->notesExistent(131, $ARABE) === false && $svc->notesExistent(1) === false, 'détection des notes : par classe et par matière');
$avant131 = $lignes(131);
check(leve(fn() => $svc->enregistrer(131, [$MATHS => ['coefficient' => '6']])) === ProgrammeConfirmationException::class && $lignes(131) === $avant131, 'coefficient d\'une matière notée : confirmation exigée, rien d\'écrit');
check($svc->enregistrer(131, [$MATHS => ['volume_horaire' => '7']]) === 1, 'volume horaire seul (sans effet sur les moyennes) : pas de confirmation');
check($svc->enregistrer(131, [$ARABE => ['coefficient' => '2']]) === 1, 'matière sans notes : pas de confirmation');
check(leve(fn() => $svc->retirer(131, $MATHS)) === ProgrammeConfirmationException::class, 'retrait d\'une matière notée : confirmation exigée');
check($svc->enregistrer(131, [$MATHS => ['coefficient' => '6']], true) === 1 && (float)array_column($svc->programme(131), null, 'matiere_id')[$MATHS]['coefficient'] === 6.0, 'avec confirmation explicite : modifié');
$derniere = $pdo->query("SELECT apres FROM audit_logs WHERE entite = 'programme_classe' AND action = 'update' ORDER BY id DESC LIMIT 1")->fetchColumn();
check((json_decode((string)$derniere, true)['notes_confirmees'] ?? null) === true, 'la confirmation est consignée dans le journal', (string)$derniere);
check($pdo->query('SELECT * FROM notes_v2 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $notesAvant && $pdo->query('SELECT * FROM evaluations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $evalAvant && $pdo->query('SELECT * FROM bulletins_v2 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $bulAvant, 'notes, évaluations et bulletins strictement inchangés : aucun recalcul, aucune régénération');
$svc->enregistrer(131, [$MATHS => ['coefficient' => '5']], true);

// ═══════════════════════════════════════════════════════════
section('9. Reproduction dans les classes parallèles');
$typeEval = (int)$pdo->query('SELECT id FROM types_evaluations ORDER BY id LIMIT 1')->fetchColumn();
$classe = static function (string $nom, string $niveau, ?string $serie, string $annee) use ($pdo): int {
    $s = $pdo->prepare('INSERT INTO classes (etablissement_id, nom, niveau, serie, annee_scolaire, max_eleves) VALUES (1, ?, ?, ?, ?, 30)');
    $s->execute([$nom, $niveau, $serie, $annee]);
    return (int)$pdo->lastInsertId();
};
$D1 = $classe('D1', 'Terminale', 'D', '2027-2028'); $D2 = $classe('D2', 'Terminale', 'D', '2027-2028'); $D3 = $classe('D3', 'Terminale', 'D', '2027-2028');
$C1 = $classe('C1', 'Terminale', 'C', '2027-2028'); $D4 = $classe('D4', 'Terminale', 'D', '2028-2029'); $S1 = $classe('S1', 'Seconde', null, '2027-2028'); $S2 = $classe('S2', 'Seconde', null, '2027-2028');
$svc->ajouter($D1, $MATHS, '5', '6'); $svc->ajouter($D1, $FRANCAIS, '2', '3'); $svc->ajouter($D1, $ANGLAIS, '2', '2', 3);
check(array_column($svc->paralleles($D1), 'id') === [$D2, $D3], 'classes parallèles de Terminale D1 : D2 et D3 seulement (ni la série C, ni une autre année, ni un autre niveau)', json_encode(array_column($svc->paralleles($D1), 'id')));
check(array_column($svc->paralleles($S1), 'id') === [$S2], 'sans série (NULL) : deux classes sans série sont parallèles');
$svc->ajouter($D2, $MATHS, '3', '4');   // D2 a déjà une ligne personnalisée
$pdo->prepare("INSERT INTO bulletins_v2 (eleve_id, classe_id, periode_id, verification_token, statut) VALUES (?, ?, ?, ?, 'publie')")->execute([$eleve1, $D3, $periode, str_repeat('b', 64)]);
check(leve(fn() => $svc->reproduire($D1, [$C1])) === ProgrammeException::class && $nbLignes($C1) === 0, 'classe NON parallèle (autre série) : refusée, rien d\'écrit');
check(leve(fn() => $svc->reproduire($D1, [$D4])) === ProgrammeException::class, 'autre année : refusée');
check(leve(fn() => $svc->reproduire($D1, [$classeEtab2])) === ProgrammeException::class, 'classe d\'un autre établissement : refusée');
check(leve(fn() => $svc->reproduire($D1, [])) === ProgrammeException::class, 'aucune cible : refusé');
check(leve(fn() => $svc->reproduire($D1, [$D2], 'inconnu')) === ProgrammeException::class, 'mode inconnu : refusé');
check(leve(fn() => $svc->reproduire($D2, [$D1, $C1])) === ProgrammeException::class && $nbLignes($D1) === 3, 'une cible invalide dans la liste : tout refusé');
check(leve(fn() => $svc->reproduire($S1, [$S2])) === ProgrammeException::class, 'programme source vide : refusé');
$res = $svc->reproduire($D1, [$D2, $D3]);
$par = array_column($res, null, 'classe_id');
check($par[$D2]['ajoutees'] === 2 && $par[$D2]['mises_a_jour'] === 0 && $par[$D2]['ignoree'] === null, 'mode compléter : 2 matières ajoutées à D2');
$d2 = array_column($lignes($D2), null, 'matiere_id');
check((float)$d2[$MATHS]['coefficient'] === 3.0 && (int)$d2[$MATHS]['volume_horaire'] === 4, 'compléter : la ligne déjà personnalisée de D2 (Maths 3) n\'est PAS modifiée');
check((float)$d2[$FRANCAIS]['coefficient'] === 2.0 && (int)$d2[$ANGLAIS]['ordre'] === 3, 'compléter : lignes ajoutées avec les valeurs de la source (ordre compris)');
check($par[$D3]['ignoree'] !== null && str_contains($par[$D3]['ignoree'], 'verrouillé') && $nbLignes($D3) === 0, 'classe D3 verrouillée (bulletin publié) : ignorée avec sa raison, les autres sont traitées');
check(leve(fn() => $svc->reproduire($D1, [$D2], 'ecraser')) === ProgrammeConfirmationException::class && (float)array_column($lignes($D2), null, 'matiere_id')[$MATHS]['coefficient'] === 3.0, 'mode aligner sans confirmation : refusé');
$res = $svc->reproduire($D1, [$D2], 'ecraser', true);
check($res[0]['mises_a_jour'] === 1 && (float)array_column($lignes($D2), null, 'matiere_id')[$MATHS]['coefficient'] === 5.0, 'mode aligner confirmé : la ligne de D2 est alignée sur D1 (Maths 5)');
check($svc->reproduire($D1, [$D2])[0]['ajoutees'] === 0, 'relance : rien à ajouter');
// aligner avec notes
$pdo->prepare("INSERT INTO evaluations (periode_scolaire_id, type_evaluation_id, matiere_id, classe_id, libelle, coefficient, note_max, statut) VALUES (?, ?, ?, ?, 'Devoir D2', 1, 20, 'publiee')")->execute([$periode, $typeEval, $MATHS, $D2]);
$evalD2 = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO notes_v2 (evaluation_id, eleve_id, valeur, statut) VALUES (?, ?, 12, 'saisie')")->execute([$evalD2, $eleve1]);
$svc->enregistrer($D1, [$MATHS => ['coefficient' => '4']]);
$res = $svc->reproduire($D1, [$D2], 'ecraser', true);
check($res[0]['ignoree'] !== null && str_contains($res[0]['ignoree'], 'notes') && (float)array_column($lignes($D2), null, 'matiere_id')[$MATHS]['coefficient'] === 5.0, 'aligner une matière notée sans confirmation des notes : cible ignorée, valeur inchangée');
$res = $svc->reproduire($D1, [$D2], 'ecraser', true, true);
check($res[0]['ignoree'] === null && (float)array_column($lignes($D2), null, 'matiere_id')[$MATHS]['coefficient'] === 4.0, 'avec confirmation des notes : alignée');
check($nbLignes($D4) === 0 && $nbLignes($C1) === 0, 'les classes non parallèles n\'ont reçu aucune ligne');

// ═══════════════════════════════════════════════════════════
section('10. HTTP : onglet, actions, permissions');
$r = http($nomCopie, 'admin', $admin, 'GET', '/classes/131?tab=programme');
check($r['status'] === 200 && str_contains($r['body'], 'id="tab-btn-programme"') && str_contains($r['body'], 'Mathématiques') && str_contains($r['body'], 'name="programme[' . $MATHS . '][coefficient]"'), 'onglet Programme : affiché avec les lignes éditables');
check(str_contains($r['body'], 'id="tab-eleves"') && str_contains($r['body'], 'id="tab-enseignants"'), 'onglets Élèves et Enseignements toujours présents');
$r = http($nomCopie, 'admin', $admin, 'GET', "/classes/$D1?tab=programme");
check(str_contains($r['body'], 'Reproduire dans les classes parallèles') && str_contains($r['body'], 'name="cibles[]"'), 'classe avec parallèles : bloc de reproduction affiché');
$r = http($nomCopie, 'admin', $admin, 'GET', "/classes/$S2?tab=programme");
check($r['status'] === 200 && str_contains($r['body'], 'Initialiser depuis les enseignements') && str_contains($r['body'], 'Aucun programme défini'), 'classe sans programme : message et proposition d\'initialisation');

$h = static fn(string $u, array $p) => http($nomCopie, 'admin', $admin, 'POST', $u, $p, true);
$h("/classes/$S1/programme/ajouter", ['matiere_id' => $EPS, 'coefficient' => '1,5', 'volume_horaire' => '2']);
$ls = array_column($lignes($S1), null, 'matiere_id');
check(isset($ls[$EPS]) && (float)$ls[$EPS]['coefficient'] === 1.5, 'POST ajouter : ligne créée');
$h("/classes/$S1/programme/enregistrer", ['programme' => [$EPS => ['coefficient' => '2', 'volume_horaire' => '3', 'ordre' => '1']]]);
$ls = array_column($lignes($S1), null, 'matiere_id');
check((float)$ls[$EPS]['coefficient'] === 2.0 && (int)$ls[$EPS]['volume_horaire'] === 3, 'POST enregistrer : valeurs modifiées');
$h("/classes/$S1/programme/enregistrer", ['programme' => [$EPS => ['coefficient' => '50']]]);
check((float)array_column($lignes($S1), null, 'matiere_id')[$EPS]['coefficient'] === 2.0, 'POST avec valeur hors bornes : refusé côté serveur');
$h("/classes/$S1/programme/reproduire", ['cibles' => [$S2], 'mode' => 'completer']);
check($nbLignes($S2) === 1, 'POST reproduire : ligne reproduite dans la classe parallèle');
$h("/classes/$S1/programme/reproduire", ['cibles' => [$D1], 'mode' => 'completer']);
check($nbLignes($D1) === 3, 'POST reproduire vers une classe non parallèle : refusé');
$h("/classes/$S1/programme/retirer", ['matiere_id' => $EPS]);
check($nbLignes($S1) === 0, 'POST retirer : ligne supprimée');
$h("/classes/3/programme/initialiser", []);
check($nbLignes(3) === $nbEns3, 'POST initialiser : idempotent (rien de plus)');

$sansDroit = (function () use ($pdo): int {
    $pdo->exec("INSERT INTO users (etablissement_id, nom, email, password, role) VALUES (1, 'Sans droit', 'sansdroit2@exemple.test', 'x', 'eleve')");
    return (int)$pdo->lastInsertId();
})();
$avantDroit = $lignes($S2);
foreach (['ajouter' => ['matiere_id' => $FRANCAIS], 'enregistrer' => ['programme' => [$EPS => ['coefficient' => '9']]], 'retirer' => ['matiere_id' => $EPS], 'initialiser' => [], 'reproduire' => ['cibles' => [$S2]]] as $action => $post) {
    http($nomCopie, 'eleve', $sansDroit, 'POST', "/classes/$S1/programme/$action", $post, true);
}
check($lignes($S2) === $avantDroit && $nbLignes($S1) === 0, 'utilisateur sans classes.edit : aucune des 5 actions n\'écrit');
$h("/classes/$classeEtab2/programme/ajouter", ['matiere_id' => $MATHS]);
check($nbLignes($classeEtab2) === 0, 'classe d\'un autre établissement : action refusée, rien d\'écrit');
$r = http($nomCopie, 'eleve', $sansDroit, 'GET', "/classes/$S1?tab=programme");
check(!str_contains($r['body'], 'Enregistrer le programme') && !str_contains($r['body'], 'Ajouter une matière au programme'), 'sans classes.edit : aucun contrôle d\'édition affiché');

// ═══════════════════════════════════════════════════════════
section('11. Non-régression : rien d\'autre n\'est modifié');
$apresTout = $checksum($protegees);
$diff = array_keys(array_diff_assoc($apresTout, $avantTout));
sort($diff);
check($diff === ['evaluations', 'notes_v2'], 'seules les évaluations et notes que CE test a insérées diffèrent ; élèves, inscriptions, enseignements, moyennes et périodes inchangés', json_encode($diff));
check(md5(json_encode($pdo->query('SELECT * FROM matieres WHERE etablissement_id = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC))) === $matieresAvant, 'matières de l\'établissement : strictement inchangées (noms, coefficients, volumes, catégories)');
check([(int)$pdo->query('SELECT COUNT(*) FROM moyennes_generales')->fetchColumn(), (int)$pdo->query('SELECT COUNT(*) FROM moyennes_matieres')->fetchColumn()] === $moyennesAvant, 'aucune moyenne écrite ni modifiée (calculs non touchés par cette sous-étape)');
$c = $pdo->query("SELECT coefficient, volume_horaire FROM matieres WHERE id = $MATHS")->fetch(PDO::FETCH_ASSOC);
check((float)$c['coefficient'] === 5.0 && (int)$c['volume_horaire'] === 5, 'matières : coefficient et volume par défaut inchangés (Mathématiques 5 / 5 h)');

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
