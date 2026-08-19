<?php
/**
 * Tests d'isolation multi-tenant — Phase 14.3 (Migration des tables métier)
 *
 * Exerce EleveModel, ClasseModel, NoteModel, AbsenceModel sous deux
 * TenantContext distincts (établissement A = celui existant, id=1 ;
 * établissement B = créé pour ce test) et vérifie qu'aucune lecture,
 * recherche, pagination, création, modification ou suppression ne peut
 * traverser la frontière entre les deux tenants.
 *
 * Toute la session tourne dans UNE transaction PDO annulée à la fin
 * (rollback) — zéro pollution de la base, rejouable à volonté.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/TenantIsolationTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));

$envFile = ROOT_PATH . '/.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $val] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
}

spl_autoload_register(function (string $class): void {
    $namespaces = [
        'Core\\'        => ROOT_PATH . '/core/',
        'App\\Models\\' => ROOT_PATH . '/app/Models/',
    ];
    foreach ($namespaces as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file = $dir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) { require $file; return; }
        }
    }
});

use App\Models\AbsenceModel;
use App\Models\ClasseModel;
use App\Models\EleveModel;
use App\Models\NoteModel;
use Core\Database;
use Core\Tenant\TenantContext;

$passed = 0;
$failed = 0;

function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        echo "\033[32m  ✓ $label\033[0m\n";
        $passed++;
    } else {
        echo "\033[31m  ✗ $label\033[0m\n";
        echo "      expected: " . var_export($expected, true) . "\n";
        echo "      actual:   " . var_export($actual, true) . "\n";
        $failed++;
    }
}
function assert_true(bool $v, string $label): void { assert_eq(true, $v, $label); }
function assert_false(bool $v, string $label): void { assert_eq(false, $v, $label); }
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

$pdo = Database::getInstance()->getConnection();
$pdo->beginTransaction();

try {
    // ── Setup : établissement B (tenant secondaire pour le test) ─────────────
    $pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type, pays, statut)
                VALUES ('test-tenant-b', 'Établissement Test B', 'Test B', 'lycee', 'DZ', 'active')");
    $etabB = (int)$pdo->lastInsertId();
    $etabA = 1; // établissement existant (edunova-demo)

    echo "Établissement A (existant) = {$etabA}, Établissement B (test) = {$etabB}\n";

    // ══════════════════════════════════════════════════════════════════════
    section('1. Création — chaque tenant écrit dans son propre espace');
    // ══════════════════════════════════════════════════════════════════════

    TenantContext::set($etabA);
    $classeModel = new ClasseModel();
    $classeAId = $classeModel->insert([
        'nom' => 'IsoTestA', 'niveau' => 'Seconde', 'annee_scolaire' => '2025-2026', 'max_eleves' => 30,
    ]);
    $eleveModel = new EleveModel();
    $eleveAId = $eleveModel->insert([
        'matricule' => 'ISO-A-001', 'nom' => 'IsolationA', 'prenom' => 'Test',
        'sexe' => 'M', 'date_naissance' => '2010-01-01', 'classe_id' => $classeAId, 'actif' => 1,
    ]);

    TenantContext::set($etabB);
    $classeBId = $classeModel->insert([
        'nom' => 'IsoTestB', 'niveau' => 'Seconde', 'annee_scolaire' => '2025-2026', 'max_eleves' => 30,
    ]);
    $eleveBId = $eleveModel->insert([
        'matricule' => 'ISO-B-001', 'nom' => 'IsolationB', 'prenom' => 'Test',
        'sexe' => 'F', 'date_naissance' => '2010-02-02', 'classe_id' => $classeBId, 'actif' => 1,
    ]);

    $rowClasseA = $pdo->query("SELECT etablissement_id FROM classes WHERE id={$classeAId}")->fetch();
    $rowEleveB  = $pdo->query("SELECT etablissement_id FROM eleves WHERE id={$eleveBId}")->fetch();
    assert_eq($etabA, (int)$rowClasseA->etablissement_id, 'classe A taguée avec etablissement_id A');
    assert_eq($etabB, (int)$rowEleveB->etablissement_id,  'élève B taguée avec etablissement_id B');

    // ══════════════════════════════════════════════════════════════════════
    section('2. Lecture (findById) — pas de fuite via ID deviné (IDOR)');
    // ══════════════════════════════════════════════════════════════════════

    TenantContext::set($etabA);
    $foundOwn   = $eleveModel->findById($eleveAId);
    $foundOther = $eleveModel->findById($eleveBId);
    assert_true($foundOwn !== false, 'Tenant A retrouve son propre élève par ID');
    assert_false($foundOther, 'Tenant A NE PEUT PAS lire l\'élève de Tenant B par ID');

    TenantContext::set($etabB);
    $foundOwnB   = $eleveModel->findById($eleveBId);
    $foundOtherB = $eleveModel->findById($eleveAId);
    assert_true($foundOwnB !== false, 'Tenant B retrouve son propre élève par ID');
    assert_false($foundOtherB, 'Tenant B NE PEUT PAS lire l\'élève de Tenant A par ID');

    // ══════════════════════════════════════════════════════════════════════
    section('3. Recherche & pagination — chaque tenant ne voit que ses données');
    // ══════════════════════════════════════════════════════════════════════

    TenantContext::set($etabA);
    $searchA = $eleveModel->findAllFiltered(['q' => 'Isolation']);
    $namesA = array_map(fn($r) => $r->nom, $searchA);
    assert_true(in_array('IsolationA', $namesA, true), 'Recherche "Isolation" (tenant A) trouve IsolationA');
    assert_false(in_array('IsolationB', $namesA, true), 'Recherche "Isolation" (tenant A) NE trouve PAS IsolationB');

    TenantContext::set($etabB);
    $searchB = $eleveModel->findAllFiltered(['q' => 'Isolation']);
    $namesB = array_map(fn($r) => $r->nom, $searchB);
    assert_true(in_array('IsolationB', $namesB, true), 'Recherche "Isolation" (tenant B) trouve IsolationB');
    assert_false(in_array('IsolationA', $namesB, true), 'Recherche "Isolation" (tenant B) NE trouve PAS IsolationA');

    TenantContext::set($etabA);
    $pageA = $eleveModel->paginateFiltered(1, 100, []);
    $pageAIds = array_map(fn($r) => (int)$r->id, $pageA['data']);
    assert_true(in_array($eleveAId, $pageAIds, true), 'Pagination tenant A contient élève A');
    assert_false(in_array($eleveBId, $pageAIds, true), 'Pagination tenant A NE contient PAS élève B');

    TenantContext::set($etabB);
    $pageB = $eleveModel->paginateFiltered(1, 100, []);
    $pageBIds = array_map(fn($r) => (int)$r->id, $pageB['data']);
    assert_true(in_array($eleveBId, $pageBIds, true), 'Pagination tenant B contient élève B');
    assert_false(in_array($eleveAId, $pageBIds, true), 'Pagination tenant B NE contient PAS élève A');

    // Statistiques agrégées (countBySexe) — pas de mélange des comptes
    TenantContext::set($etabA);
    $sexeStatsA = $eleveModel->countBySexe();
    $mA = array_column($sexeStatsA, 'nb', 'sexe');
    TenantContext::set($etabB);
    $sexeStatsB = $eleveModel->countBySexe();
    $mB = array_column($sexeStatsB, 'nb', 'sexe');
    // B n'a qu'1 fille de test créée dans ce test -> son compte F doit être exactement 1
    assert_eq(1, (int)($mB['F'] ?? 0), 'countBySexe(tenant B) : exactement 1 fille (isolé des autres tenants)');

    // ══════════════════════════════════════════════════════════════════════
    section('4. Modification (update) — pas de fuite en écriture inter-tenant');
    // ══════════════════════════════════════════════════════════════════════

    TenantContext::set($etabA);
    $updateResult = $eleveModel->update($eleveBId, ['nom' => 'HACKED-BY-A']);
    $checkB = $pdo->query("SELECT nom FROM eleves WHERE id={$eleveBId}")->fetch();
    assert_eq('IsolationB', $checkB->nom, 'Tenant A ne peut PAS modifier l\'élève de Tenant B (update no-op)');

    TenantContext::set($etabA);
    $eleveModel->update($eleveAId, ['telephone' => '0555000000']);
    $checkA = $pdo->query("SELECT telephone FROM eleves WHERE id={$eleveAId}")->fetch();
    assert_eq('0555000000', $checkA->telephone, 'Tenant A peut modifier son propre élève');

    // ══════════════════════════════════════════════════════════════════════
    section('5. Suppression (delete) — pas de fuite en suppression inter-tenant');
    // ══════════════════════════════════════════════════════════════════════

    TenantContext::set($etabA);
    $eleveModel->delete($eleveBId);
    $stillExistsB = $pdo->query("SELECT id FROM eleves WHERE id={$eleveBId}")->fetch();
    assert_true($stillExistsB !== false, 'Tenant A ne peut PAS supprimer l\'élève de Tenant B (delete no-op)');

    // ══════════════════════════════════════════════════════════════════════
    section('6. NoteModel — écriture (upsert) et lecture isolées');
    // ══════════════════════════════════════════════════════════════════════

    // Un contrôle n'a pas etablissement_id (table hors périmètre 14.3) : on
    // vérifie uniquement que l'écriture des notes elles-mêmes est isolée.
    $pdo->exec("INSERT INTO periodes (nom, type, annee_scolaire, actif, etablissement_id) VALUES ('P-Iso', 'trimestre', '2025-2026', 1, {$etabA})");
    $periodeAId = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO matieres (nom, coefficient, volume_horaire, etablissement_id) VALUES ('MatIso', 1, 2, {$etabA})");
    $matiereAId = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO controles (classe_id, matiere_id, periode_id, libelle, type, coefficient, note_max, date_controle)
                VALUES ({$classeAId}, {$matiereAId}, {$periodeAId}, 'Ctrl Iso', 'devoir', 1, 20, CURDATE())");
    $controleId = (int)$pdo->lastInsertId();

    TenantContext::set($etabA);
    $noteModel = new NoteModel();
    $noteModel->upsert($eleveAId, $controleId, 15.5, 0);
    $noteRow = $pdo->query("SELECT note, etablissement_id FROM notes WHERE eleve_id={$eleveAId} AND controle_id={$controleId}")->fetch();
    assert_eq('15.50', $noteRow->note, 'NoteModel::upsert() enregistre la note');
    assert_eq($etabA, (int)$noteRow->etablissement_id, 'NoteModel::upsert() tague etablissement_id automatiquement');

    // ══════════════════════════════════════════════════════════════════════
    section('7. AbsenceModel — pointage (storePointage) isolé');
    // ══════════════════════════════════════════════════════════════════════

    // Date fixe (pas date('Y-m-d')/CURDATE()) : PHP tourne en UTC alors que MySQL
    // utilise le fuseau horaire système (UTC+1 ici) — les deux peuvent désigner
    // des jours calendaires différents pendant l'heure qui suit minuit UTC,
    // rendant une comparaison date('Y-m-d') (écriture) vs CURDATE() (lecture)
    // intermittente indépendamment de toute logique métier. Découvert Phase 14.12.
    $pointageDate = '2026-01-15';
    TenantContext::set($etabA);
    $absenceModel = new AbsenceModel();
    $absenceModel->storePointage(
        $classeAId, $pointageDate, 'matin',
        [$eleveAId => 'absence'], [], [$eleveAId => 'Test isolation'], 1
    );
    $absRow = $pdo->query("SELECT etablissement_id, motif FROM absences WHERE eleve_id={$eleveAId} AND date_absence='{$pointageDate}' AND session='matin'")->fetch();
    assert_true($absRow !== false, 'AbsenceModel::storePointage() enregistre l\'absence');
    assert_eq($etabA, (int)$absRow->etablissement_id, 'AbsenceModel::storePointage() tague etablissement_id automatiquement');

    // Tenant B ne doit voir aucune des absences/notes de Tenant A
    TenantContext::set($etabB);
    $absencesB = $absenceModel->findForEleve($eleveAId); // IDOR : élève A vu depuis contexte B
    assert_eq(0, count($absencesB), 'Tenant B ne peut pas lire les absences de l\'élève A (IDOR bloqué)');

    // ══════════════════════════════════════════════════════════════════════
    section('8. Régression — comportement par défaut (sans contexte) inchangé');
    // ══════════════════════════════════════════════════════════════════════

    TenantContext::clear();
    $freshEleve = new EleveModel();
    $noContextResult = $freshEleve->findById($eleveAId);
    assert_true($noContextResult !== false, 'Sans TenantContext positionné, comportement de repli = etablissement_id 1 (identique à avant Phase 14.3)');

} finally {
    $pdo->rollBack();
    TenantContext::clear();
    echo "\n(rollback effectué — aucune donnée de test persistée)\n";
}

// ── Résumé ──────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n\033[1m" . ($failed === 0 ? "\033[32m" : "\033[31m");
echo "Résultat : $passed/$total assertions passées";
echo "\033[0m\n\n";

if ($failed > 0) {
    exit(1);
}
