<?php
/**
 * Tests de sécurité — page PUBLIQUE de vérification des bulletins (/v2/academique/bulletins/verify/{jeton})
 *
 * Règle testée : seul un bulletin ÉMIS (statut « publie » ou « archive ») est vérifiable. Un brouillon,
 * un jeton inconnu, tronqué, modifié ou malformé produisent la MÊME réponse externe : HTTP 404, même
 * contenu, sans nom d'élève, moyenne, classe ni indication d'existence.
 *
 * Partie A : contrôleur appelé en direct, avec des bulletins de test (publié, archivé, brouillon) créés dans
 *            une transaction annulée à la fin — c'est la seule façon de tester le cas « publié » car aucun
 *            bulletin réel n'est publié.
 * Partie B : requêtes HTTP réelles de bout en bout, en visiteur non connecté (tests/Support/request_as.php,
 *            rôle « guest »), avec les bulletins réels : aucun brouillon, y compris ceux dont le jeton est
 *            calculable avec l'ancien littéral public, ne doit être vérifiable. Lecture seule.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/BulletinVerificationPublicTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) { continue; }
    [$key, $val] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
}
if (!defined('BASE_URL')) { define('BASE_URL', '/ecole_app'); }

spl_autoload_register(function (string $class): void {
    $map = [
        'Core\\' => '/core/', 'App\\Controllers\\' => '/app/Controllers/', 'App\\Models\\' => '/app/Models/',
        'App\\Services\\' => '/app/Services/', 'App\\Modules\\' => '/app/Modules/', 'App\\Shared\\' => '/app/Shared/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = ROOT_PATH . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) { require_once $file; return; }
        }
    }
});

use App\Modules\Academique\Controllers\BulletinController;
use Core\Database;
use Core\Tenant\TenantContext;

$passed = 0; $failed = 0; $skipped = 0;
function check(bool $ok, string $label): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label\033[0m\n"; }
}
function skip(string $label, string $why): void { global $skipped; $skipped++; echo "\033[33m  - SKIP $label ($why)\033[0m\n"; }
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

/**
 * Contrôleur de test : capture la vue choisie et les données qui lui sont transmises, au lieu de la rendre.
 * (La vue de vérification déclare une fonction globale e() sans garde : elle ne peut pas être rendue deux fois
 * dans un même processus. La réponse HTTP réelle — corps compris — est vérifiée en partie B.)
 */
class ControleurVerificationTest extends BulletinController
{
    public array $rendu = [];
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $this->rendu = [$view, $data, $layout];
    }
}

/** Appelle l'action publique du contrôleur et retourne [statut HTTP, [vue, données, layout]]. */
function verifier(string $token): array
{
    http_response_code(200);
    $c = new ControleurVerificationTest();
    $c->verifier($token);
    return [http_response_code() ?: 200, $c->rendu];
}

/** Empreinte de ce qui sera rendu : même vue + mêmes données = même réponse externe. */
function empreinte(array $rendu): string { return md5(serialize($rendu)); }

$pdo = Database::getInstance()->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ════════════════════════════════════════════════════════════════════════════
// PARTIE A — contrôleur, bulletins de test dans une transaction annulée
// ════════════════════════════════════════════════════════════════════════════
$pdo->beginTransaction();
try {
    TenantContext::set(1);
    $modele  = $pdo->query("SELECT data_json FROM bulletins_v2 ORDER BY id LIMIT 1")->fetchColumn();
    $periode = (int)$pdo->query("SELECT id FROM periodes_scolaires ORDER BY id LIMIT 1")->fetchColumn();
    if (!$modele || !$periode) {
        skip('Partie A', 'aucun bulletin modèle ou aucune période');
    } else {
        $uniq = substr(md5((string)microtime(true)), 0, 8);
        $pdo->prepare("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire) VALUES (1, ?, 'Test', '2099-2100')")->execute(["BV_$uniq"]);
        $classeId = (int)$pdo->lastInsertId();
        $mkEleve = function (string $tag) use ($pdo, $classeId, $uniq): int {
            $pdo->prepare("INSERT INTO eleves (etablissement_id, matricule, nom, prenom, date_naissance, sexe, classe_id, actif)
                           VALUES (1, ?, ?, 'Prenom', '2010-01-01', 'M', ?, 1)")
                ->execute([substr("BV-$tag-$uniq", 0, 20), "SECRETNOM$tag", $classeId]);
            return (int)$pdo->lastInsertId();
        };
        // Un bulletin de test par statut ; jeton fort aléatoire (comme en production)
        $tokens = []; $noms = [];
        foreach (['publie', 'archive', 'brouillon'] as $statut) {
            $eid = $mkEleve($statut);
            $tok = bin2hex(random_bytes(32));
            $json = json_decode((string)$modele, true);
            $json['verification_token'] = $tok;
            $json['eleve_nom'] = "SECRETNOM$statut";
            $json['statut'] = $statut;
            $pdo->prepare("INSERT INTO bulletins_v2 (eleve_id, classe_id, periode_id, verification_token, moyenne, rang, nb_eleves, mention_code, decision, statut, data_json, generated_by, generated_at)
                           VALUES (?, ?, ?, ?, 12.34, 1, 1, 'AB', 'admis', ?, ?, 1, NOW())")
                ->execute([$eid, $classeId, $periode, $tok, $statut, json_encode($json, JSON_UNESCAPED_UNICODE)]);
            $tokens[$statut] = $tok; $noms[$statut] = "SECRETNOM$statut";
        }

        section('A1. Bulletin émis : vérifiable');
        [$code, $rendu] = verifier($tokens['publie']);
        check($code === 200 && ($rendu[1]['trouve'] ?? false) === true, 'publié + jeton valide : 200, vérification positive');
        [$code, $rendu] = verifier($tokens['archive']);
        check($code === 200 && ($rendu[1]['trouve'] ?? false) === true, 'archivé + jeton valide : 200, vérification positive');

        section('A2. Brouillon, jeton inconnu ou invalide : MÊME réponse 404');
        [$codeInconnu, $renduInconnu] = verifier(bin2hex(random_bytes(32)));
        check($codeInconnu === 404 && ($renduInconnu[1]['trouve'] ?? null) === false, 'jeton inconnu : 404, vérification négative');
        [$codeBrouillon, $renduBrouillon] = verifier($tokens['brouillon']);
        check($codeBrouillon === 404, 'brouillon avec son VRAI jeton : 404');
        check(empreinte($renduBrouillon) === empreinte($renduInconnu), 'brouillon : même vue et mêmes données qu\'un jeton inconnu (réponse externe identique)');
        $serialise = serialize($renduBrouillon);
        check(!str_contains($serialise, $noms['brouillon']) && !str_contains($serialise, '12.34') && !isset($renduBrouillon[1]['bulletin']),
              'brouillon : aucune donnée de bulletin (nom, moyenne) n\'est transmise à la vue');
        check(!str_contains($serialise, 'brouillon'), 'brouillon : l\'état du bulletin n\'est pas transmis à la vue');

        $mauvais = [
            'dernier caractère modifié' => substr($tokens['publie'], 0, -1) . (substr($tokens['publie'], -1) === 'a' ? 'b' : 'a'),
            'tronqué'                   => substr($tokens['publie'], 0, 32),
            'préfixe seul'              => substr($tokens['publie'], 0, 8),
            'majuscules'                => strtoupper($tokens['publie']),
            'vide'                      => '',
            'espaces'                   => ' ' . $tokens['publie'] . ' ',
            'injection SQL'             => "' OR '1'='1",
            'caractères spéciaux'       => '../../etc/passwd%00',
        ];
        foreach ($mauvais as $label => $t) {
            [$c, $r] = verifier($t);
            check($c === 404 && empreinte($r) === empreinte($renduInconnu), "jeton $label : 404 identique au jeton inconnu");
        }
        // Collision de casse : certains collations MySQL ignorent la casse — le jeton en majuscules ne doit rien valider.
        // (couvert par « majuscules » ci-dessus)
    }
} finally {
    $pdo->rollBack();
    TenantContext::clear();
    echo "\n(rollback effectué — aucune donnée de test persistée)\n";
}

// ════════════════════════════════════════════════════════════════════════════
// PARTIE B — HTTP réel, visiteur non connecté, bulletins réels (lecture seule)
// ════════════════════════════════════════════════════════════════════════════
function http_guest(string $token): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' -d display_errors=0 ' . escapeshellarg(ROOT_PATH . '/tests/Support/request_as.php')
         . ' guest 0 GET ' . escapeshellarg('/ecole_app/v2/academique/bulletins/verify/' . $token);
    foreach (explode("\n", (string)shell_exec($cmd)) as $l) {
        if (str_starts_with($l, 'RESULT:')) { return json_decode(substr($l, 7), true) ?: ['status' => 0]; }
    }
    return ['status' => 0];
}

section('B. HTTP réel (visiteur non connecté) sur les bulletins réels');
$ref = http_guest(bin2hex(random_bytes(32)));
check($ref['status'] === 404, 'jeton aléatoire : 404');

$rows = $pdo->query("SELECT eleve_id, periode_id, verification_token, statut FROM bulletins_v2 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$brouillons = array_values(array_filter($rows, fn($r) => $r['statut'] === 'brouillon'));
$faibles = array_values(array_filter($brouillons, fn($r) => hash_equals(
    hash('sha256', "bulletin:{$r['eleve_id']}:{$r['periode_id']}:ecole_app_v2"), $r['verification_token'])));
$forts = array_values(array_filter($brouillons, fn($r) => !in_array($r, $faibles, true)));

if (!$brouillons) {
    skip('B brouillons réels', 'aucun brouillon en base');
} else {
    $echantillon = array_merge($faibles, array_slice($forts, 0, 3));
    $tousRefuses = true; $memeReponse = true;
    foreach ($echantillon as $r) {
        $res = http_guest($r['verification_token']);
        if ($res['status'] !== 404) { $tousRefuses = false; }
        if (($res['md5'] ?? null) !== ($ref['md5'] ?? 'x')) { $memeReponse = false; }
    }
    check($tousRefuses, 'brouillons réels (' . count($faibles) . ' à jeton faible + ' . min(3, count($forts)) . ' à jeton fort) avec leur VRAI jeton : tous en 404');
    check($memeReponse, 'brouillons réels : réponse strictement identique à celle d\'un jeton inconnu (même empreinte)');
    // L'attaque d'origine : calculer le jeton avec le littéral public pour un couple (élève, période) quelconque
    $attaque = true;
    foreach ($rows as $r) {
        $calc = hash('sha256', "bulletin:{$r['eleve_id']}:{$r['periode_id']}:ecole_app_v2");
        if ($r['statut'] === 'brouillon' && http_guest($calc)['status'] === 200) { $attaque = false; break; }
    }
    check($attaque, 'attaque par jeton calculé avec l\'ancien littéral : aucun brouillon n\'est exposé');
}

$publies = array_values(array_filter($rows, fn($r) => $r['statut'] !== 'brouillon'));
if ($publies) {
    check(http_guest($publies[0]['verification_token'])['status'] === 200, 'bulletin réel publié/archivé avec son jeton : 200');
} else {
    skip('bulletin réel publié', 'aucun bulletin publié en base (cas couvert en partie A)');
}

echo "\n";
$total = $passed + $failed;
echo ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : {$passed}/{$total} assertions passées"
   . ($skipped ? " ({$skipped} scénario(s) SKIP — non comptés)" : '') . "\033[0m\n";
exit($failed === 0 ? 0 : 1);
