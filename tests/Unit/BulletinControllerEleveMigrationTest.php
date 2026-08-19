<?php
/**
 * Tests — migration de BulletinController::eleve() (route /bulletins/{id})
 * vers le moteur Académique V2 (BulletinEngineFactory / BulletinGenerator).
 *
 * Combine :
 *  - une vérification statique du code source de BulletinController::eleve()
 *    (aucun appel à NoteModel/PeriodeModel dans cette méthode précise — les
 *    autres actions du contrôleur, index()/classe()/classement(), restent
 *    volontairement sur le moteur V1 pour cette phase et ne sont pas testées
 *    ici) ;
 *  - un test fonctionnel contre la base réelle (comme tests/Unit/
 *    RbacTenantTest.php) exerçant BulletinEngineFactory::previewBulletin(),
 *    exactement l'appel fait par le contrôleur migré.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/BulletinControllerEleveMigrationTest.php
 */

declare(strict_types=1);

// ─── Autoloader ──────────────────────────────────────────────────────────────

define('ROOT_PATH', dirname(__DIR__, 2));

$envFile = ROOT_PATH . '/.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}

spl_autoload_register(function (string $class): void {
    $namespaces = [
        'Core\\'             => ROOT_PATH . '/core/',
        'App\\Controllers\\' => ROOT_PATH . '/app/Controllers/',
        'App\\Models\\'      => ROOT_PATH . '/app/Models/',
        'App\\Services\\'    => ROOT_PATH . '/app/Services/',
        'App\\Modules\\'     => ROOT_PATH . '/app/Modules/',
        'App\\Shared\\'      => ROOT_PATH . '/app/Shared/',
    ];
    foreach ($namespaces as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $dir . $rel . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

use App\Modules\Academique\Services\BulletinEngineFactory;
use App\Modules\Academique\Repositories\PeriodeScolaireRepository;
use Core\Database;

// ─── Helpers (mêmes conventions que BulletinGeneratorTest.php) ───────────────

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

function assert_true(bool $val, string $label): void  { assert_eq(true, $val, $label); }
function assert_false(bool $val, string $label): void { assert_eq(false, $val, $label); }

function section(string $title): void
{
    echo "\n\033[33m── $title\033[0m\n";
}

// ─── 1. Vérification statique — BulletinController::eleve() ──────────────────

section('BulletinController::eleve() — aucune trace du moteur V1');

$source = file_get_contents(ROOT_PATH . '/app/Controllers/BulletinController.php');

// Isole le corps de la méthode eleve() (jusqu'à la méthode privée suivante).
$start = strpos($source, 'public function eleve(string $id)');
$end   = strpos($source, 'private function previewBulletinSafe(');
assert_true($start !== false && $end !== false, 'Méthode eleve() et previewBulletinSafe() localisées dans le fichier');
$eleveBody = ($start !== false && $end !== false) ? substr($source, $start, $end - $start) : '';

assert_false(str_contains($eleveBody, '$this->noteModel'),    "eleve() n'appelle pas \$this->noteModel");
assert_false(str_contains($eleveBody, '$this->periodeModel'), "eleve() n'appelle pas \$this->periodeModel");
assert_false(str_contains($eleveBody, 'getBulletinData'),     "eleve() n'appelle pas NoteModel::getBulletinData()");
assert_false(preg_match('/\bSUM\s*\(/i', $eleveBody) === 1,   "eleve() ne contient aucun calcul SQL manuel de moyenne (SUM)");
assert_true(str_contains($eleveBody, 'BulletinEngineFactory'),'eleve() délègue à BulletinEngineFactory');
assert_true(str_contains($eleveBody, 'previewBulletinSafe'),  'eleve() consomme previewBulletinSafe()');

// La classe continue d'importer NoteModel/PeriodeModel pour index()/classe()/
// classement(), volontairement non migrées dans cette phase — on vérifie que
// ces trois actions existent toujours (fichiers V1 non supprimés).
foreach (['function index()', 'function classe()', 'function classement()'] as $needle) {
    assert_true(str_contains($source, $needle), "$needle toujours présente (V1 non supprimé, hors périmètre de cette phase)");
}

// ─── 2. Vue bulletins/eleve.php — plus d'accès à l'ancienne forme tableau ────

section('Vue bulletins/eleve.php — adaptée au DTO BulletinData');

$viewSource = file_get_contents(ROOT_PATH . '/app/Views/bulletins/eleve.php');
assert_false(str_contains($viewSource, "\$b['matieres']"), "La vue n'utilise plus \$b['matieres'] (ancien format V1)");
assert_true(str_contains($viewSource, '$bulletin->lignesMatieres') || str_contains($viewSource, '$bulletin?->'),
    'La vue lit les propriétés du DTO BulletinData');

// ─── 3. Test fonctionnel — moteur réel contre la base ─────────────────────────

section('BulletinEngineFactory::previewBulletin() — données réelles');

$pdo = Database::getInstance()->getConnection();
$row = $pdo->query(
    "SELECT n.eleve_id, ev.classe_id, ev.periode_scolaire_id
       FROM notes_v2 n
       JOIN evaluations ev ON ev.id = n.evaluation_id
      WHERE ev.statut IN ('publiee','verrouillee')
      LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "\033[33m  ⚠ Aucune évaluation publiée en base — section ignorée (pas un échec)\033[0m\n";
} else {
    $eleveId   = (int)$row['eleve_id'];
    $periodeId = (int)$row['periode_scolaire_id'];

    try {
        $bulletin = BulletinEngineFactory::make(1)->previewBulletin($eleveId, $periodeId);
        assert_true(true, "previewBulletin($eleveId, $periodeId) ne lève aucune exception");
        assert_true(count($bulletin->lignesMatieres) > 0, 'lignesMatieres non vide');
        assert_true($bulletin->moyennePeriode >= 0.0 && $bulletin->moyennePeriode <= 20.0, 'moyennePeriode dans [0,20]');
        assert_true($bulletin->nbEleves >= 1, 'nbEleves >= 1');
        assert_true($bulletin->eleveNom !== '', 'eleveNom renseigné');
        assert_true($bulletin->periodeNom !== '', 'periodeNom renseigné (periodes_scolaires, pas periodes V1)');

        // Chaque ligne matière expose bien moyenne/mention calculées par le
        // moteur — aucune valeur ne doit provenir d'un recalcul local.
        $first = $bulletin->lignesMatieres[0];
        foreach (['matiere_nom', 'coefficient', 'moyenne', 'mention_label', 'notes'] as $key) {
            assert_true(array_key_exists($key, $first), "lignesMatieres[0] contient la clé '$key'");
        }
    } catch (\Throwable $e) {
        assert_true(false, 'previewBulletin() ne devait pas lever ' . get_class($e) . ' : ' . $e->getMessage());
    }

    // periode_id invalide (ex. ancien id V1 coïncidant par hasard, ou pur
    // paramètre corrompu) -> RuntimeException, absorbée par
    // BulletinController::previewBulletinSafe() -> repli sur l'état "aucun
    // résultat" déjà géré par la vue, jamais une page cassée.
    try {
        BulletinEngineFactory::make(1)->previewBulletin($eleveId, 999999999);
        assert_true(false, 'periode_id inexistant aurait dû lever une RuntimeException');
    } catch (\RuntimeException) {
        assert_true(true, 'periode_id inexistant lève RuntimeException (absorbée par previewBulletinSafe)');
    } catch (\Throwable $e) {
        assert_true(false, 'Mauvais type d\'exception : ' . get_class($e));
    }
}

// ─── 4. PeriodeScolaireRepository — résolution de periode_id par défaut ──────

section('PeriodeScolaireRepository — repli utilisé par eleve() quand periode_id est absent/invalide');

$periodeRepo = new PeriodeScolaireRepository();
$periodes    = $periodeRepo->findForSelect();
assert_true(is_array($periodes), 'findForSelect() retourne un tableau');

if (empty($periodes)) {
    echo "\033[33m  ⚠ Aucune periodes_scolaires en base — assertions de repli ignorées\033[0m\n";
} else {
    $active = $periodeRepo->findActive();
    assert_true($active === null || isset($active->id), 'findActive() retourne null ou un objet avec id');
}

// ─── Résumé ────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n\033[1m" . ($failed === 0 ? "\033[32m" : "\033[31m");
echo "Résultat : $passed/$total assertions passées";
echo "\033[0m\n\n";

if ($failed > 0) exit(1);
