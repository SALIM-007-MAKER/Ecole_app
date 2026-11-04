<?php
/**
 * Tests — Lot 3, étape 2 : migrations T051 (périodes), T052 (types d'évaluation), T053 (configuration des périodes), sur COPIES jetables.
 *
 * Vérifie : rattachement démontré (jamais deviné), lignes ambiguës laissées à NULL et signalées, catalogue système et copies par
 * établissement, idempotence, retour arrière EXACT (schéma et données), compatibilité du code avant migration (référentiels
 * partagés, aucune page cassée), amorçage d'un nouvel établissement, isolation des modèles.
 *
 * AUCUNE migration n'est appliquée à la base réelle : la source n'est jamais écrite (empreinte avant / après).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/TenantAcademiqueMigrationsTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);
define('BASE_URL', '/ecole_app');
require_once ROOT_PATH . '/tests/Support/DbCopy.php';
require_once ROOT_PATH . '/tests/Support/ConduiteCopy.php';
require_once ROOT_PATH . '/tests/Support/Lot3Copy.php';

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

use App\Modules\Academique\Models\PeriodeScolaireModel;
use App\Modules\Academique\Models\TypeEvaluationModel;
use App\Modules\Academique\Repositories\PeriodeConfigRepository;
use App\Modules\Academique\Repositories\TypeEvaluationRepository;
use Core\Tenant\TenantContext;
use Core\Tenant\TenantScope;

$conn = DbCopy::connectionFromEnv(ROOT_PATH);
$source = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['db']};charset=utf8mb4", $conn['user'], $conn['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$avantSource = DbCopy::allDataHashes($source);
$migrationsSourceAvant = $source->query("SELECT migration FROM migrations_log WHERE migration IN ('T051','T052','T053')")->fetchAll(PDO::FETCH_COLUMN);
$copy = DbCopy::create(ROOT_PATH);
if ($copy === null) { echo "SKIP : copie réelle impossible\n"; exit(0); }

try {
    $db = $copy->pdo();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('SET NAMES utf8mb4');
    $_ENV['DB_DATABASE'] = $copy->name;
    ConduiteCopy::migrer($db, ROOT_PATH);
    \Tests\Support\Lot3Copy::retourAvantLot3($db, ROOT_PATH);   // la base réelle porte désormais T051–T058 : la copie est ramenée à l'état « avant »
    $mig = [];
    foreach (['T051_periodes_scolaires_etablissement', 'T052_types_evaluations_etablissement', 'T053_periodes_config_etablissement'] as $f) { $mig[substr($f, 0, 4)] = require ROOT_PATH . "/database/migrations/$f.php"; }
    $tables = ['periodes_scolaires', 'types_evaluations', 'periodes_scolaires_config'];
    $http = fn(string $role, int $uid, string $m, string $u) => ConduiteCopy::http(ROOT_PATH, $copy->name, $role, $uid, $m, "/ecole_app{$u}");
    $one = fn(string $sql) => $db->query($sql)->fetchColumn();
    $colonne = fn(string $t) => (int)$one("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$t' AND column_name = 'etablissement_id'");

    // ═════════════════════════════════════════════════════════════════════
    section('1. Compatibilité AVANT migration : référentiels partagés, aucune page cassée');
    check($colonne('periodes_scolaires') === 0 && $colonne('types_evaluations') === 0, 'état de départ : colonnes absentes');
    foreach (['/v2/academique/periodes', '/v2/academique/evaluations/create', '/v2/academique/evaluations'] as $u) {
        [$r, $b] = $http('admin', 1, 'GET', $u);
        check(($r['status'] ?? 0) === 200 && !preg_match('/SQLSTATE|Unknown column/i', $b), "$u : pas d'erreur SQL sur une base non migrée");
    }
    [$r, $b] = $http('admin', 1, 'GET', '/v2/academique/periodes');
    check(str_contains($b, 'Semestre'), 'liste des périodes affichée (partagée) avant migration');

    // ═════════════════════════════════════════════════════════════════════
    section('2. Ambiguïtés : période sans usage, période partagée par deux établissements');
    $db->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('lot3-mig', 'Ecole Mig', 'EM', 'lycee')");
    $E2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire) VALUES ($E2, 'CL-MIG', '6eme', '2025-2026')");
    $C2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO periodes_scolaires (annee_scolaire, type_periode, numero, nom, statut, ordre) VALUES ('2040-2041', 'semestre', 1, 'SANS-USAGE', 'ouverte', 1)");
    $pSans = (int)$db->lastInsertId();
    $db->exec("INSERT INTO periodes_scolaires (annee_scolaire, type_periode, numero, nom, statut, ordre) VALUES ('2041-2042', 'semestre', 1, 'PARTAGEE', 'ouverte', 1)");
    $pPart = (int)$db->lastInsertId();
    $matiere1 = (int)$one('SELECT id FROM matieres WHERE etablissement_id = 1 LIMIT 1');
    $db->exec("INSERT INTO matieres (etablissement_id, nom, coefficient, volume_horaire) VALUES ($E2, 'M-MIG', 1, 2)");
    $matiere2 = (int)$db->lastInsertId();
    $type3 = (int)$one("SELECT id FROM types_evaluations WHERE code = 'examen'");
    $db->exec("INSERT INTO types_evaluations (code, nom, actif, est_systeme) VALUES ('perso2', 'TYPE-PERSO-E2', 1, 0)");
    $tPerso2 = (int)$db->lastInsertId();
    foreach ([[131, $matiere1, $type3], [$C2, $matiere2, $tPerso2]] as [$cl, $mat, $typ]) {
        $db->exec("INSERT INTO evaluations (periode_scolaire_id, type_evaluation_id, matiere_id, classe_id, libelle, coefficient, note_max, statut, date_evaluation) VALUES ($pPart, $typ, $mat, $cl, 'PARTAGE', 1, 20, 'brouillon', '2041-10-10')");
    }
    $db->exec("INSERT INTO types_evaluations (code, nom, actif, est_systeme) VALUES ('perso', 'TYPE-PERSO-SANS-USAGE', 1, 0)");
    $tPerso = (int)$db->lastInsertId();
    $avantTout = ['schema' => DbCopy::schema($db, $tables), 'data' => DbCopy::allDataHashes($db)];

    ob_start();
    foreach ($mig as $m) { ($m['run'])($db); }
    $sortie = ob_get_clean();
    check($colonne('periodes_scolaires') === 1 && $colonne('types_evaluations') === 1 && $colonne('periodes_scolaires_config') === 1, 'T051, T052 et T053 appliquées : colonnes présentes');
    foreach ([90, 91, 100, 101] as $p) { check((int)$one("SELECT etablissement_id FROM periodes_scolaires WHERE id = $p") === 1, "période #$p rattachée à l'établissement 1 (utilisée par les classes de l'établissement 1 uniquement)"); }
    check($one("SELECT etablissement_id FROM periodes_scolaires WHERE id = $pSans") === null && str_contains($sortie, "période #$pSans"), 'période sans usage : laissée à NULL et signalée [AMBIGU]');
    check($one("SELECT etablissement_id FROM periodes_scolaires WHERE id = $pPart") === null && str_contains($sortie, "période #$pPart"), 'période utilisée par deux établissements : laissée à NULL et signalée [AMBIGU]');
    check(str_contains($sortie, "type #$tPerso") && $one("SELECT etablissement_id FROM types_evaluations WHERE id = $tPerso") === null, 'type non système sans usage : laissé à NULL et signalé [AMBIGU]');
    check(!str_contains($sortie, 'type #1 ') && !str_contains($sortie, 'type #4 '), 'les types système inutilisés ne sont PAS signalés (ce sont des gabarits du catalogue)');
    // types
    $parCode = fn(string $code, ?int $etab) => (int)$one("SELECT COUNT(*) FROM types_evaluations WHERE code = '$code' AND est_systeme = 1 AND etablissement_id " . ($etab === null ? 'IS NULL' : "= $etab"));
    foreach (['controle', 'devoir', 'examen', 'tp', 'oral'] as $code) {
        check($parCode($code, null) === 1, "catalogue : exactement un gabarit système « $code » sans propriétaire");
        check($parCode($code, 1) === 1 && $parCode($code, $E2) === 1, "« $code » : une copie propre à l'établissement 1 et une à l'établissement 2");
    }
    check((int)$one("SELECT etablissement_id FROM types_evaluations WHERE id = $type3") === 1, 'le type « examen » utilisé garde son identifiant et appartient à l\'établissement 1 (les évaluations ne sont pas repointées)');
    check((int)$one("SELECT etablissement_id FROM types_evaluations WHERE id = $tPerso2") === $E2 && !str_contains($sortie, "type #$tPerso2 "), 'type personnalisé utilisé par l\'établissement 2 seul : rattaché à l\'établissement 2 par l\'usage');
    check((int)$one('SELECT COUNT(*) FROM evaluations ev JOIN types_evaluations t ON t.id = ev.type_evaluation_id JOIN classes c ON c.id = ev.classe_id WHERE t.etablissement_id IS NOT NULL AND t.etablissement_id <> c.etablissement_id') === 0, 'contrôle croisé : aucune évaluation ne pointe un type d\'un autre établissement');
    // config
    check((int)$one('SELECT COUNT(*) FROM periodes_scolaires_config WHERE etablissement_id IS NULL') === 2 && (int)$one('SELECT COUNT(*) FROM periodes_scolaires_config WHERE etablissement_id = 1') === 2 && (int)$one("SELECT COUNT(*) FROM periodes_scolaires_config WHERE etablissement_id = $E2") === 2,
          'configuration des périodes : gabarit (2 lignes) + une copie par établissement');

    section('3. Idempotence et retour arrière exact');
    ob_start(); $apres1 = DbCopy::allDataHashes($db); foreach ($mig as $m) { ($m['run'])($db); } ob_end_clean();
    check(DbCopy::allDataHashes($db) === $apres1, 'rejouer T051-T053 ne change rien (idempotentes)');
    $db->exec("UPDATE evaluations SET type_evaluation_id = (SELECT id FROM types_evaluations WHERE code = 'examen' AND etablissement_id = 1) WHERE type_evaluation_id IN (SELECT id FROM (SELECT id FROM types_evaluations WHERE code = 'examen' AND etablissement_id = $E2) x)");
    foreach (array_reverse($mig) as $m) { ($m['rollback'])($db); }
    check(DbCopy::schema($db, $tables) === $avantTout['schema'], 'retour arrière : schéma des trois tables identique à l\'état initial');
    $apresRollback = DbCopy::allDataHashes($db);
    $diff = array_keys(array_filter($apresRollback, fn($h, $t) => ($avantTout['data'][$t] ?? null) !== $h, ARRAY_FILTER_USE_BOTH));
    check($diff === [], 'retour arrière : données identiques à l\'état initial', implode(',', $diff));

    // ═════════════════════════════════════════════════════════════════════
    section('4. Isolation des modèles et amorçage (migrations rejouées)');
    ob_start(); foreach ($mig as $m) { ($m['run'])($db); } ob_end_clean();
    TenantScope::oublierSchema();
    $db->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('lot3-nouveau', 'Ecole Nouvelle', 'EN', 'lycee')");
    $E3 = (int)$db->lastInsertId();
    TenantContext::set($E3);
    check(count((new PeriodeScolaireModel())->findForSelect()) === 0, 'un établissement neuf ne voit aucune période (aucune période ne lui est attribuée)');
    check((new PeriodeScolaireModel())->findById(90) === false, 'findById d\'une période d\'un autre établissement : introuvable');
    check((new PeriodeScolaireModel())->update(90, ['nom' => 'PIRATE']) === true && (string)$one('SELECT nom FROM periodes_scolaires WHERE id = 90') !== 'PIRATE', 'update d\'une période d\'un autre établissement : sans effet');
    check((new TypeEvaluationModel())->findById((int)$one("SELECT id FROM types_evaluations WHERE code = 'devoir' AND etablissement_id = 1")) === false, 'findById d\'un type d\'un autre établissement : introuvable');
    check(count((new TypeEvaluationModel())->findActifs()) === 0, 'un établissement neuf n\'a encore aucun type avant amorçage');
    (new TypeEvaluationRepository())->amorcer();
    $types = (new TypeEvaluationModel())->findActifs();
    check(count($types) === 5 && (int)$one("SELECT COUNT(*) FROM types_evaluations WHERE etablissement_id = $E3") === 5, 'amorçage : l\'établissement neuf reçoit sa copie des 5 types système (catalogue)');
    (new TypeEvaluationRepository())->amorcer();
    check((int)$one("SELECT COUNT(*) FROM types_evaluations WHERE etablissement_id = $E3") === 5, 'amorçage idempotent');
    $cfg = new PeriodeConfigRepository();
    check(count($cfg->all()) === 2 && (int)$one("SELECT COUNT(*) FROM periodes_scolaires_config WHERE etablissement_id = $E3") === 2, 'configuration des périodes : copie du gabarit à la première lecture');
    $cfg->update(1, ['nom_defaut' => 'Trimestre E3'], 0);
    check((string)$one('SELECT nom_defaut FROM periodes_scolaires_config WHERE etablissement_id = 1 AND numero = 1') !== 'Trimestre E3', 'modifier la configuration d\'un établissement ne touche pas celle des autres');
    $p = new PeriodeScolaireModel();
    $id = $p->insert(['annee_scolaire' => '2030-2031', 'type_periode' => 'semestre', 'numero' => 1, 'nom' => 'PERIODE-E3', 'statut' => 'preparation', 'ordre' => 1]);
    check((int)$one("SELECT etablissement_id FROM periodes_scolaires WHERE id = $id") === $E3, 'une période créée porte l\'établissement courant');
    $p->deactiverTous('2025-2026');
    check((int)$one('SELECT COUNT(*) FROM periodes_scolaires WHERE annee_scolaire = \'2025-2026\' AND is_active = 1') === (int)$source->query("SELECT COUNT(*) FROM periodes_scolaires WHERE annee_scolaire = '2025-2026' AND is_active = 1")->fetchColumn(), 'désactiver les périodes actives d\'une année ne touche pas celles des autres établissements');
    TenantContext::clear();
} finally {
    $copy->drop();
}

section('5. Base réelle');
check(DbCopy::allDataHashes($source) === $avantSource, 'base réelle inchangée (empreinte de toutes les tables)');
$migrationsSourceApres = $source->query("SELECT migration FROM migrations_log WHERE migration IN ('T051','T052','T053')")->fetchAll(PDO::FETCH_COLUMN);
check($migrationsSourceApres === $migrationsSourceAvant, 'aucune migration T051-T053 enregistrée ni appliquée sur la base réelle');
check(count(array_filter($source->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN), fn($d) => str_starts_with($d, 'ecole_app_copy_'))) === 0, 'aucune base temporaire restante');

echo "\n" . ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : $passed/" . ($passed + $failed) . " assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
