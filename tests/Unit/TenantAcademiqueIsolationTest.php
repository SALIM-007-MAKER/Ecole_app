<?php
/**
 * Tests — Lot 3, étape 2 : isolation inter-établissements du module Académique.
 *
 * Deux établissements complets (classe, élèves, matière, période, type, évaluation, notes, bulletin) sur une COPIE jetable avec
 * T048-T053 appliquées. Chaque point d'accès par identifiant est essayé dans les DEUX sens (lecture et écriture) :
 * périodes, types d'évaluation, évaluations, notes, saisie / import, bulletins, appréciations, résultats, analytique.
 * Les identifiants soumis dans les formulaires (classe, matière, période, type) doivent appartenir à l'établissement courant.
 *
 * La base source n'est jamais écrite (empreinte avant / après).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/TenantAcademiqueIsolationTest.php
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
    foreach (['T051_periodes_scolaires_etablissement', 'T052_types_evaluations_etablissement', 'T053_periodes_config_etablissement'] as $f) {
        $m = require ROOT_PATH . "/database/migrations/$f.php";
        ob_start(); ($m['run'])($db); ob_end_clean();
    }
    $nom = $copy->name;
    $http = fn(string $role, int $uid, string $m, string $u, array $post = [], array $env = []) => ConduiteCopy::http(ROOT_PATH, $nom, $role, $uid, $m, "/ecole_app{$u}", $post, $env);
    $one = fn(string $sql, array $p = []) => (function () use ($db, $sql, $p) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();
    $tok = fn() => bin2hex(random_bytes(32));

    // ── Établissement 1 : données réelles (copie) ── repères
    $E1 = 1; $ADMIN1 = 1;
    $EV1 = 6;            // évaluation ordinaire (Français, 131 / 90)
    $EVC1 = 4;           // évaluation de Conduite
    $N1 = (int)$one('SELECT id FROM notes_v2 WHERE evaluation_id = 6 LIMIT 1');
    $P1 = 90; $T1 = (int)$one('SELECT id FROM types_evaluations WHERE etablissement_id = 1 AND code = \'devoir\'');

    // ── Établissement 2 : jeu complet ──
    $db->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('lot3-acad', 'Ecole Deux Acad', 'E2A', 'lycee')");
    $E2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO users (etablissement_id, nom, prenom, email, password, role) VALUES ($E2, 'Admin2', 'Acad', 'admin2-acad@example.test', 'x', 'admin')");
    $ADMIN2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO user_etablissements (user_id, etablissement_id, is_primary) VALUES ($ADMIN2, $E2, 1)");
    $db->exec("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire) VALUES ($E2, 'CLASSE-E2-ACAD', '6eme', '2025-2026')");
    $C2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO eleves (etablissement_id, matricule, nom, prenom, date_naissance, sexe, classe_id, actif) VALUES ($E2, 'E2A-1', 'ELEVEDEUXACAD', 'Zoe', '2012-01-01', 'F', $C2, 1)");
    $EL2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire, statut) VALUES ($EL2, $C2, '2025-2026', 'validee')");
    $db->exec("INSERT INTO matieres (etablissement_id, nom, coefficient, volume_horaire) VALUES ($E2, 'MATIERE-E2-ACAD', 2, 3)");
    $M2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO periodes_scolaires (etablissement_id, annee_scolaire, type_periode, numero, nom, statut, is_active, notes_saisie_ouverte, ordre) VALUES ($E2, '2025-2026', 'semestre', 1, 'PERIODE-E2-ACAD', 'ouverte', 1, 1, 1)");
    $P2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO types_evaluations (etablissement_id, code, nom, actif, est_systeme) VALUES ($E2, 'devoir', 'TYPE-E2-ACAD', 1, 0)");
    $T2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO evaluations (periode_scolaire_id, type_evaluation_id, matiere_id, classe_id, libelle, coefficient, note_max, statut, notes_saisie_ouverte, date_evaluation)
               VALUES ($P2, $T2, $M2, $C2, 'EVAL-E2-ACAD', 1, 20, 'publiee', 1, '2026-01-10')");
    $EV2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO notes_v2 (evaluation_id, eleve_id, valeur, est_absent, statut) VALUES ($EV2, $EL2, 11, 0, 'saisie')");
    $N2 = (int)$db->lastInsertId();
    $json = json_decode((string)$db->query('SELECT data_json FROM bulletins_v2 ORDER BY id LIMIT 1')->fetchColumn(), true);
    $t2 = $tok(); $json['verification_token'] = $t2; $json['eleve_nom'] = 'ELEVEDEUXACAD'; $json['eleve_id'] = $EL2; $json['classe_id'] = $C2; $json['periode_id'] = $P2;
    $db->prepare("INSERT INTO bulletins_v2 (eleve_id, classe_id, periode_id, verification_token, token_version, moyenne, rang, nb_eleves, mention_code, decision, statut, data_json, generated_by, generated_at)
                  VALUES (?, ?, ?, ?, 2, 11, 1, 1, 'P', 'admis', 'brouillon', ?, 1, NOW())")->execute([$EL2, $C2, $P2, $t2, json_encode($json, JSON_UNESCAPED_UNICODE)]);
    $B2 = (int)$db->lastInsertId();
    $db->exec("UPDATE evaluations SET notes_saisie_ouverte = 1 WHERE id IN ($EV1, $EVC1)");

    $absent = fn(string $corps, string ...$marqueurs) => array_reduce($marqueurs, fn($ok, $m) => $ok && !str_contains($corps, $m), true);
    $lire = function (string $role, int $uid, string $uri, string ...$marqueurs) use ($http, $absent): array {
        [$r, $b] = $http($role, $uid, 'GET', $uri);
        return [$absent($b, ...$marqueurs), 'statut ' . ($r['status'] ?? '?')];
    };

    // ═════════════════════════════════════════════════════════════════════
    section('1. Référence : chaque établissement voit ses propres données');
    [$r, $b] = $http('admin', $ADMIN1, 'GET', "/v2/academique/evaluations/$EV1");
    check(($r['status'] ?? 0) === 200 && str_contains($b, 'Fran'), 'admin 1 : fiche de son évaluation');
    [$r, $b] = $http('admin', $ADMIN2, 'GET', "/v2/academique/evaluations/$EV2");
    check(($r['status'] ?? 0) === 200 && str_contains($b, 'EVAL-E2-ACAD'), 'admin 2 : fiche de son évaluation');
    [$r, $b] = $http('admin', $ADMIN2, 'GET', "/v2/academique/periodes/$P2");
    check(str_contains($b, 'PERIODE-E2-ACAD'), 'témoin positif : admin 2 lit sa propre fiche de période (une page qui échoue ne prouverait rien)');
    [$r, $b] = $http('admin', $ADMIN1, 'GET', "/v2/academique/periodes/$P1");
    check(str_contains($b, 'Semestre'), 'témoin positif : admin 1 lit sa propre fiche de période');
    [$r, $b] = $http('admin', $ADMIN2, 'GET', "/v2/academique/evaluations/$EV2/notes");
    check(str_contains($b, 'ELEVEDEUXACAD'), 'témoin positif : admin 2 lit les notes de sa propre évaluation');
    [$r, $b] = $http('admin', $ADMIN2, 'GET', "/v2/academique/bulletins/$EL2/$P2/imprimer");
    check(str_contains($b, 'ELEVEDEUXACAD'), 'témoin positif : admin 2 imprime le bulletin de son propre élève');
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/v2/academique/periodes');
    check(str_contains($b, 'PERIODE-E2-ACAD') && !str_contains($b, 'Deuxième Semestre'), 'admin 2 : liste des périodes = les siennes uniquement');
    [$r, $b] = $http('admin', $ADMIN1, 'GET', '/v2/academique/periodes');
    check(str_contains($b, 'Semestre') && !str_contains($b, 'PERIODE-E2-ACAD'), 'admin 1 : liste des périodes = les siennes uniquement');
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/v2/academique/evaluations/create');
    check(str_contains($b, 'TYPE-E2-ACAD') && str_contains($b, 'PERIODE-E2-ACAD') && str_contains($b, 'MATIERE-E2-ACAD') && str_contains($b, 'CLASSE-E2-ACAD') && !str_contains($b, 'Examen') && !str_contains($b, '2nde C1') && !str_contains($b, 'Conduite'), 'admin 2 : formulaire d\'évaluation = types, périodes, matières et classes de son établissement uniquement');
    [$r, $b] = $http('admin', $ADMIN1, 'GET', '/v2/academique/evaluations/create');
    check(!str_contains($b, 'TYPE-E2-ACAD') && !str_contains($b, 'PERIODE-E2-ACAD') && !str_contains($b, 'MATIERE-E2-ACAD') && !str_contains($b, 'CLASSE-E2-ACAD') && str_contains($b, 'Examen'), 'admin 1 : formulaire d\'évaluation sans aucun référentiel de l\'établissement 2');

    // ═════════════════════════════════════════════════════════════════════
    section('2. Lectures : l\'établissement 2 ne voit rien de l\'établissement 1');
    $lectures = [
        ["/v2/academique/evaluations/$EVC1", 'Conduite'], ["/v2/academique/evaluations/$EV1", 'Fran'], ["/v2/academique/evaluations/$EV1/edit", 'Fran'],
        ["/v2/academique/evaluations/$EV1/notes", 'Nom08'], ["/v2/academique/evaluations/$EV1/notes/saisie", 'Nom08'], ["/v2/academique/evaluations/$EV1/notes/importer", 'Fran'],
        ["/v2/academique/notes/$N1", 'Nom08'], ['/v2/academique/evaluations', 'Fran', 'Conduite'],
        ['/v2/academique/bulletins/1/90/imprimer', 'Nom08'], ['/v2/academique/bulletins/1/90/appreciation-directeur', 'Nom08'],
        ['/v2/academique/classes/131/matieres/4/appreciations?periode_id=90', 'Nom08'],
        ["/v2/academique/periodes/$P1", 'Semestre'], ["/v2/academique/periodes/$P1/edit", 'Semestre'],
        ['/v2/academique/resultats/classe?classe_id=131&periode_id=90', 'Nom08', 'Nom03'], ['/v2/academique/resultats/classement?classe_id=131&periode_id=90', 'Nom08', 'Nom03'],
        ['/v2/academique/resultats/moyennes?classe_id=131&periode_id=90', 'Nom08', 'Nom03'], ['/v2/academique/notes?eleve_id=1', 'Nom08'],
    ];
    foreach ($lectures as $l) {
        $uri = array_shift($l);
        [$ok, $det] = $lire('admin', $ADMIN2, $uri, ...$l);
        check($ok, "admin 2 : GET $uri — aucune donnée de l'établissement 1", $det);
    }

    section('3. Lectures : l\'établissement 1 ne voit rien de l\'établissement 2');
    $lectures2 = [
        ["/v2/academique/evaluations/$EV2", 'EVAL-E2-ACAD'], ["/v2/academique/evaluations/$EV2/edit", 'EVAL-E2-ACAD'], ["/v2/academique/evaluations/$EV2/notes", 'ELEVEDEUXACAD'],
        ["/v2/academique/notes/$N2", 'ELEVEDEUXACAD'], ['/v2/academique/evaluations', 'EVAL-E2-ACAD'], ["/v2/academique/bulletins/$EL2/$P2/imprimer", 'ELEVEDEUXACAD'],
        ["/v2/academique/periodes/$P2", 'PERIODE-E2-ACAD'],
        ["/v2/academique/resultats/classe?classe_id=$C2&periode_id=$P2", 'ELEVEDEUXACAD'], ["/v2/academique/classes/$C2/matieres/$M2/appreciations?periode_id=$P2", 'ELEVEDEUXACAD'],
    ];
    foreach ($lectures2 as $l) {
        $uri = array_shift($l);
        [$ok, $det] = $lire('admin', $ADMIN1, $uri, ...$l);
        check($ok, "admin 1 : GET $uri — aucune donnée de l'établissement 2", $det);
    }

    // ═════════════════════════════════════════════════════════════════════
    section('4. Écritures : l\'établissement 2 ne modifie rien de l\'établissement 1');
    $st = fn(int $id) => $one('SELECT statut FROM evaluations WHERE id = ?', [$id]);
    $db->exec("UPDATE evaluations SET statut = 'brouillon', notes_saisie_ouverte = 0 WHERE id = 12");
    $http('admin', $ADMIN2, 'POST', '/v2/academique/evaluations/12/publier');
    check($st(12) === 'brouillon', 'publier une évaluation de l\'établissement 1');
    $http('admin', $ADMIN2, 'POST', "/v2/academique/evaluations/$EV1/verrouiller");
    check($st($EV1) === 'publiee', 'verrouiller une évaluation de l\'établissement 1');
    $http('admin', $ADMIN2, 'POST', "/v2/academique/evaluations/$EV1/archiver");
    check($st($EV1) === 'publiee', 'archiver une évaluation de l\'établissement 1');
    $form = fn(int $periode, int $type, int $matiere, int $classe, string $lib) => ['periode_scolaire_id' => $periode, 'type_evaluation_id' => $type, 'matiere_id' => $matiere, 'classe_id' => $classe, 'libelle' => $lib, 'coefficient' => '1', 'note_max' => '20', 'date_evaluation' => '2026-01-10'];
    $http('admin', $ADMIN2, 'POST', "/v2/academique/evaluations/$EV1", $form($P1, $T1, 4, 131, 'PIRATE-EVAL'));
    check($one('SELECT libelle FROM evaluations WHERE id = ?', [$EV1]) !== 'PIRATE-EVAL', 'modifier une évaluation de l\'établissement 1');
    $v0 = $one('SELECT valeur FROM notes_v2 WHERE id = ?', [$N1]);
    $http('admin', $ADMIN2, 'POST', "/v2/academique/notes/$N1", ['valeur' => '1']);
    check($one('SELECT valeur FROM notes_v2 WHERE id = ?', [$N1]) === $v0, 'modifier une note de l\'établissement 1');
    $http('admin', $ADMIN2, 'POST', "/v2/academique/notes/$N1/delete");
    check((int)$one('SELECT COUNT(*) FROM notes_v2 WHERE id = ?', [$N1]) === 1, 'supprimer une note de l\'établissement 1');
    $n0 = (int)$one('SELECT COUNT(*) FROM notes_v2');
    $http('admin', $ADMIN2, 'POST', "/v2/academique/evaluations/$EV1/notes", ['notes' => [1 => ['valeur' => '3']]]);
    check((int)$one('SELECT COUNT(*) FROM notes_v2') === $n0 && $one('SELECT valeur FROM notes_v2 WHERE id = ?', [$N1]) === $v0, 'saisir des notes sur une évaluation de l\'établissement 1');
    $publiees0 = (int)$one("SELECT COUNT(*) FROM notes_v2 WHERE evaluation_id = $EV1 AND statut = 'publiee'");   // état de la copie avant la requête (pas la base vivante, qui peut être vide)
    $http('admin', $ADMIN2, 'POST', "/v2/academique/evaluations/$EV1/notes/publier");
    check((int)$one("SELECT COUNT(*) FROM notes_v2 WHERE evaluation_id = $EV1 AND statut = 'publiee'") === $publiees0, 'publier les notes d\'une évaluation de l\'établissement 1');
    $verrouillees0 = (int)$one("SELECT COUNT(*) FROM notes_v2 WHERE evaluation_id = $EV1 AND statut = 'verrouillee'");   // état de la copie avant la requête
    $http('admin', $ADMIN2, 'POST', "/v2/academique/evaluations/$EV1/notes/verrouiller");
    check((int)$one("SELECT COUNT(*) FROM notes_v2 WHERE evaluation_id = $EV1 AND statut = 'verrouillee'") === $verrouillees0, 'verrouiller les notes d\'une évaluation de l\'établissement 1');
    $statutP0 = $one('SELECT statut FROM periodes_scolaires WHERE id = ?', [$P1]);
    $http('admin', $ADMIN2, 'POST', "/v2/academique/periodes/$P1/cloturer");
    $statutP = $one('SELECT statut FROM periodes_scolaires WHERE id = ?', [$P1]);
    check($statutP === $statutP0, 'clôturer une période de l\'établissement 1');
    $http('admin', $ADMIN2, 'POST', "/v2/academique/periodes/$P1/archiver");
    check($one('SELECT statut FROM periodes_scolaires WHERE id = ?', [$P1]) === $statutP, 'archiver une période de l\'établissement 1');
    $http('admin', $ADMIN2, 'POST', "/v2/academique/periodes/$P1", ['nom' => 'PIRATE-PERIODE', 'annee_scolaire' => '2025-2026', 'type_periode' => 'semestre', 'numero' => 2]);
    check($one('SELECT nom FROM periodes_scolaires WHERE id = ?', [$P1]) !== 'PIRATE-PERIODE', 'modifier une période de l\'établissement 1');
    $http('admin', $ADMIN2, 'POST', '/v2/academique/bulletins/1/90/appreciation-directeur', ['appreciation_directeur' => 'PIRATE-APPR']);
    check($one('SELECT appreciation_directeur FROM bulletins_v2 WHERE eleve_id = 1 AND periode_id = 90') !== 'PIRATE-APPR', 'écrire l\'appréciation du directeur sur un bulletin de l\'établissement 1');
    $http('admin', $ADMIN2, 'POST', '/v2/academique/bulletins/1/90/regenerer');
    $http('admin', $ADMIN2, 'POST', '/v2/academique/bulletins/1/90/publier');
    check($one('SELECT statut FROM bulletins_v2 WHERE eleve_id = 1 AND periode_id = 90') === 'brouillon', 'régénérer / publier un bulletin de l\'établissement 1');
    $a0 = (int)$one('SELECT COUNT(*) FROM appreciations_matiere');
    $http('admin', $ADMIN2, 'POST', '/v2/academique/classes/131/matieres/4/appreciations', ['periode_id' => 90, 'appreciations' => [1 => ['texte' => 'PIRATE']]]);
    check((int)$one('SELECT COUNT(*) FROM appreciations_matiere') === $a0 && (int)$one("SELECT COUNT(*) FROM appreciations_matiere WHERE texte = 'PIRATE'") === 0, 'saisir des appréciations de matière pour une classe de l\'établissement 1');

    section('5. Écritures : l\'établissement 1 ne modifie rien de l\'établissement 2');
    $http('admin', $ADMIN1, 'POST', "/v2/academique/evaluations/$EV2/verrouiller");
    check($st($EV2) === 'publiee', 'verrouiller une évaluation de l\'établissement 2');
    $http('admin', $ADMIN1, 'POST', "/v2/academique/notes/$N2", ['valeur' => '2']);
    check((float)$one('SELECT valeur FROM notes_v2 WHERE id = ?', [$N2]) === 11.0, 'modifier une note de l\'établissement 2');
    $http('admin', $ADMIN1, 'POST', "/v2/academique/periodes/$P2/archiver");
    check($one('SELECT statut FROM periodes_scolaires WHERE id = ?', [$P2]) === 'ouverte', 'archiver une période de l\'établissement 2');
    $http('admin', $ADMIN1, 'POST', "/v2/academique/bulletins/$EL2/$P2/publier");
    check($one('SELECT statut FROM bulletins_v2 WHERE id = ?', [$B2]) === 'brouillon', 'publier un bulletin de l\'établissement 2');

    // ═════════════════════════════════════════════════════════════════════
    section('6. Identifiants de formulaire : ils doivent appartenir à l\'établissement courant');
    $nbEv = fn() => (int)$one('SELECT COUNT(*) FROM evaluations');
    $e0 = $nbEv();
    $http('admin', $ADMIN2, 'POST', '/v2/academique/evaluations', $form($P2, $T2, $M2, 131, 'X-CLASSE-ETRANGERE'));
    check($nbEv() === $e0, 'créer une évaluation sur une classe d\'un autre établissement');
    $http('admin', $ADMIN2, 'POST', '/v2/academique/evaluations', $form($P2, $T2, 4, $C2, 'X-MATIERE-ETRANGERE'));
    check($nbEv() === $e0, 'créer une évaluation avec une matière d\'un autre établissement');
    $http('admin', $ADMIN2, 'POST', '/v2/academique/evaluations', $form($P1, $T2, $M2, $C2, 'X-PERIODE-ETRANGERE'));
    check($nbEv() === $e0, 'créer une évaluation avec une période d\'un autre établissement');
    $http('admin', $ADMIN2, 'POST', '/v2/academique/evaluations', $form($P2, $T1, $M2, $C2, 'X-TYPE-ETRANGER'));
    check($nbEv() === $e0, 'créer une évaluation avec un type d\'un autre établissement');
    $http('admin', $ADMIN2, 'POST', '/v2/academique/evaluations', $form($P2, $T2, $M2, $C2, 'EVAL-E2-LEGITIME'));
    check($nbEv() === $e0 + 1, 'créer une évaluation entièrement cohérente de l\'établissement 2 : acceptée');
    $idNew = (int)$one("SELECT id FROM evaluations WHERE libelle = 'EVAL-E2-LEGITIME'");
    $http('admin', $ADMIN2, 'POST', "/v2/academique/evaluations/$idNew", $form($P1, $T2, $M2, $C2, 'EVAL-E2-LEGITIME'));
    check($one('SELECT periode_scolaire_id FROM evaluations WHERE id = ?', [$idNew]) === $P2, 'déplacer une évaluation vers la période d\'un autre établissement');
    $p0 = (int)$one('SELECT COUNT(*) FROM periodes_scolaires');
    $http('admin', $ADMIN2, 'POST', '/v2/academique/periodes', ['annee_scolaire' => '2030-2031', 'type_periode' => 'semestre', 'numero' => 1, 'nom' => 'PERIODE-E2-NOUVELLE', 'date_debut' => '2030-10-01', 'date_fin' => '2031-01-31']);
    check((int)$one('SELECT COUNT(*) FROM periodes_scolaires') === $p0 + 1 && (int)$one("SELECT etablissement_id FROM periodes_scolaires WHERE nom = 'PERIODE-E2-NOUVELLE'") === $E2, 'une période créée par l\'établissement 2 lui appartient');

    // ═════════════════════════════════════════════════════════════════════
    section('7. Référentiels non rattachés (propriété non démontrée) : invisibles de tous');
    $db->exec("INSERT INTO periodes_scolaires (etablissement_id, annee_scolaire, type_periode, numero, nom, statut, ordre) VALUES (NULL, '2025-2026', 'semestre', 9, 'PERIODE-ORPHELINE', 'ouverte', 9)");
    [$r, $b] = $http('admin', $ADMIN1, 'GET', '/v2/academique/periodes');
    check(!str_contains($b, 'PERIODE-ORPHELINE'), 'une période sans propriétaire n\'est visible d\'aucun établissement');
    [$r, $b] = $http('admin', $ADMIN1, 'GET', '/v2/academique/types-evaluations');
    check(!str_contains($b, 'oral') || (int)$one("SELECT COUNT(*) FROM types_evaluations WHERE etablissement_id = 1 AND code = 'oral'") === 1, 'les gabarits du catalogue (sans propriétaire) ne sont pas listés comme types de l\'établissement');
} finally {
    $copy->drop();
}

section('8. Base réelle');
check(DbCopy::allDataHashes($source) === $avantSource, 'base réelle inchangée (empreinte de toutes les tables)');
check(count(array_filter($source->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN), fn($d) => str_starts_with($d, 'ecole_app_copy_'))) === 0, 'aucune base temporaire restante');

echo "\n" . ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : $passed/" . ($passed + $failed) . " assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
