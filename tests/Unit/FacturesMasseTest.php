<?php
/**
 * Tests — génération de factures EN MASSE (Finance) : modes « Une classe », « Un niveau entier », « Tous les élèves ».
 *
 * Régressions corrigées :
 *  - le filtre « par niveau » recevait des NOMS DE CYCLES (« Lycée ») alors que classes.niveau contient « Seconde », « 3e »… → 0 facture ;
 *  - le mode choisi (`scope`) était ignoré : « Une classe » sans classe sélectionnée facturait TOUS les élèves actifs.
 *
 * 1. Formulaire : vrais niveaux, filtrés par cycles activés, niveaux des classes existantes conservés
 * 2. Par niveau : factures réellement créées pour les bons élèves, idempotence, nom de cycle refusé
 * 3. Par classe : la classe choisie seulement ; sans classe → rien ; classe cachée ignorée en mode niveau
 * 4. Tous les élèves : tous, en ignorant niveau et classe postés
 * 5. Ancien client (sans `scope`) : comportement historique conservé
 * 6. Isolation : une classe d'un autre établissement ne facture personne
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/FacturesMasseTest.php
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
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

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

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$admin = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' AND etablissement_id = 1 ORDER BY id LIMIT 1")->fetchColumn();
$frais = (int)$pdo->query("SELECT id FROM finance_frais_types WHERE statut = 'actif' AND etablissement_id = 1 AND montant_defaut > 0 ORDER BY id LIMIT 1")->fetchColumn();
if ($admin === 0 || $frais === 0) { exit("ECHEC : administrateur d'école ou type de frais introuvable dans la copie\n"); }
$ANNEE = '2025-2026';

function http(string $nomCopie, int $uid, string $methode, string $uri, array $post = [], bool $commit = false): array
{
    $corps = sys_get_temp_dir() . '/fm_' . bin2hex(random_bytes(4)) . '.html';
    [, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/request_as.php', 'admin', (string)$uid, $methode, '/ecole_app' . $uri, json_encode($post)],
        ['REQUEST_AS_DB' => $nomCopie, 'REQUEST_AS_BODY_OUT' => $corps] + ($commit ? ['REQUEST_AS_COMMIT' => '1'] : []));
    $r = preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: []) : [];
    $body = (string)@file_get_contents($corps); @unlink($corps);
    return ['status' => (int)($r['status'] ?? 0), 'body' => $body];
}
function options(string $html, string $selectName): array
{
    if (!preg_match('/<select[^>]*name="' . preg_quote($selectName, '/') . '"[^>]*>(.*?)<\/select>/s', $html, $m)) { return []; }
    preg_match_all('/<option[^>]*value="([^"]*)"/', $m[1], $o);
    return array_values(array_filter($o[1], fn($v) => $v !== ''));
}
/** Élèves (ids) ayant une facture pour ces frais et cette année. @return list<int> */
$factures = static function () use ($pdo, $frais, $ANNEE): array {
    $s = $pdo->prepare("SELECT DISTINCT ff.eleve_id FROM finance_factures ff JOIN finance_lignes_facture lf ON lf.facture_id = ff.id
                        WHERE ff.annee_scolaire = ? AND lf.frais_type_id = ? AND ff.statut <> 'annulee' ORDER BY ff.eleve_id");
    $s->execute([$ANNEE, $frais]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
};
/** Élèves actifs d'un niveau / d'une classe / tous. @return list<int> */
$eleves = static function (?string $niveau = null, ?int $classe = null) use ($pdo): array {
    $sql = "SELECT e.id FROM eleves e JOIN classes c ON c.id = e.classe_id WHERE e.actif = 1 AND e.etablissement_id = 1";
    if ($niveau !== null) { $sql .= " AND c.niveau = " . $pdo->quote($niveau); }
    if ($classe !== null) { $sql .= " AND c.id = " . (int)$classe; }
    return array_map('intval', $pdo->query($sql . ' ORDER BY e.id')->fetchAll(PDO::FETCH_COLUMN));
};
$generer = static fn(array $post) => http($nomCopie, $admin, 'POST', '/v2/finance/factures/generer', ['annee_scolaire' => $ANNEE, 'frais_type_ids' => [$frais]] + $post, true);
$reinit = static function () use ($pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['finance_lignes_facture', 'finance_factures'] as $t) { $pdo->exec("DELETE FROM $t"); }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};
$ecrireCycles = static function (?array $cycles) use ($pdo): void {
    $pdo->exec("DELETE FROM etab_settings WHERE etablissement_id = 1 AND section = 'academique' AND cle = 'cycles_actifs'");
    if ($cycles !== null) {
        $pdo->prepare("INSERT INTO etab_settings (etablissement_id, section, cle, valeur, type) VALUES (1, 'academique', 'cycles_actifs', ?, 'json')")->execute([json_encode($cycles, JSON_UNESCAPED_UNICODE)]);
    }
    \Core\Tenant\TenantCache::make()->forget(1, 'settings', 'academique');
};

$secondeEleves = $eleves('Seconde');
$premiereEleves = $eleves('Première');
$tousEleves = $eleves();
$classe131 = $eleves(null, 131);
check(count($secondeEleves) >= 2 && count($premiereEleves) >= 1 && count($classe131) >= 2 && count($tousEleves) > count($secondeEleves),
    'jeu de données : plusieurs élèves actifs répartis en Seconde, Première et Terminale', json_encode([count($secondeEleves), count($premiereEleves), count($classe131), count($tousEleves)]));

// ═══════════════════════════════════════════════════════════
section('1. Formulaire : vrais niveaux, cycles activés');
$ecrireCycles(['Lycée']);
$r = http($nomCopie, $admin, 'GET', '/v2/finance/factures/generer');
check($r['status'] === 200 && options($r['body'], 'niveau') === ['Seconde', 'Première', 'Terminale'], 'cycle Lycée seul : niveaux réels Seconde, Première, Terminale', json_encode(options($r['body'], 'niveau')));
$ecrireCycles(['Collège', 'Lycée']);
check(options(http($nomCopie, $admin, 'GET', '/v2/finance/factures/generer')['body'], 'niveau') === ['6e', '5e', '4e', '3e', 'Seconde', 'Première', 'Terminale'], 'collège + lycée : 7 niveaux, ordre pédagogique');
$pdo->exec("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire, max_eleves) VALUES (1, 'OLD', 'CM2', '2025-2026', 30)");
$ecrireCycles(['Lycée']);
$opt = options(http($nomCopie, $admin, 'GET', '/v2/finance/factures/generer')['body'], 'niveau');
check(in_array('CM2', $opt, true) && !in_array('PS', $opt, true), 'niveau d\'une classe existante (cycle désactivé) conservé ; autres niveaux inactifs absents', json_encode($opt));
$pdo->exec("DELETE FROM classes WHERE nom = 'OLD' AND niveau = 'CM2'");
$ecrireCycles(null);

// ═══════════════════════════════════════════════════════════
section('2. Par niveau');
$reinit();
$generer(['scope' => 'niveau', 'niveau' => 'Lycée']);
check($factures() === [], 'nom de CYCLE (« Lycée ») refusé : aucune facture');
$generer(['scope' => 'niveau', 'niveau' => 'Zzz']);
check($factures() === [], 'niveau inconnu refusé : aucune facture');
$generer(['scope' => 'niveau', 'niveau' => '']);
check($factures() === [], 'niveau vide refusé : aucune facture');
$generer(['scope' => 'niveau', 'niveau' => 'Seconde']);
check($factures() === $secondeEleves, 'niveau « Seconde » : une facture pour chaque élève de Seconde, aucun autre', json_encode([$factures(), $secondeEleves]));
$nb = (int)$pdo->query('SELECT COUNT(*) FROM finance_factures')->fetchColumn();
$generer(['scope' => 'niveau', 'niveau' => 'Seconde']);
check((int)$pdo->query('SELECT COUNT(*) FROM finance_factures')->fetchColumn() === $nb, 'relance identique : aucune facture en double (idempotent)');
$lignes = (int)$pdo->query("SELECT COUNT(*) FROM finance_lignes_facture WHERE frais_type_id = $frais")->fetchColumn();
check($lignes === count($secondeEleves), 'une ligne de facture par élève et par frais', (string)$lignes);

// ═══════════════════════════════════════════════════════════
section('3. Par classe');
$reinit();
$generer(['scope' => 'classe', 'classe_id' => '']);
check($factures() === [], 'mode « Une classe » SANS classe : aucune facture (avant : tous les élèves facturés)');
$generer(['scope' => 'classe', 'classe_id' => '131', 'niveau' => 'Première']);
check($factures() === $classe131, 'classe 131 : ses élèves seulement (le niveau posté est ignoré)', json_encode([$factures(), $classe131]));
$reinit();
$generer(['scope' => 'niveau', 'niveau' => 'Première', 'classe_id' => '131']);
check($factures() === $premiereEleves, 'mode niveau avec une classe cachée postée : le niveau fait foi', json_encode([$factures(), $premiereEleves]));

// ═══════════════════════════════════════════════════════════
section('4. Tous les élèves');
$reinit();
$generer(['scope' => 'tous', 'niveau' => 'Seconde', 'classe_id' => '131']);
check($factures() === $tousEleves, 'tous les élèves actifs facturés, niveau et classe postés ignorés', json_encode([count($factures()), count($tousEleves)]));
$inactif = (int)$pdo->query("SELECT COUNT(*) FROM eleves WHERE actif = 0")->fetchColumn();
check(count(array_intersect($factures(), array_map('intval', $pdo->query('SELECT id FROM eleves WHERE actif = 0')->fetchAll(PDO::FETCH_COLUMN)))) === 0, "élèves inactifs ($inactif) non facturés");

// ═══════════════════════════════════════════════════════════
section('5. Ancien client (sans `scope`) : comportement historique');
$reinit();
$generer(['classe_id' => '131']);
check($factures() === $classe131, 'sans scope + classe : facture la classe');
$reinit();
$generer(['niveau' => 'Seconde']);
check($factures() === $secondeEleves, 'sans scope + niveau : facture le niveau');
$reinit();
$generer([]);
check($factures() === $tousEleves, 'sans scope ni classe ni niveau : tous les élèves (comme avant)');

// ═══════════════════════════════════════════════════════════
section('6. Isolation multi-établissement');
$reinit();
$pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('autre-ecole', 'Autre école', 'AE', 'lycee')");
$etab2 = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire, max_eleves) VALUES ($etab2, 'Z1', 'Terminale', '2025-2026', 30)");
$classeAutre = (int)$pdo->lastInsertId();
$generer(['scope' => 'classe', 'classe_id' => (string)$classeAutre]);
check($factures() === [], 'classe d\'un AUTRE établissement : aucune facture générée');
$generer(['scope' => 'niveau', 'niveau' => 'Terminale']);
$terminale = $eleves('Terminale');
check($factures() === $terminale, 'niveau Terminale : seulement les élèves de l\'établissement courant', json_encode([$factures(), $terminale]));

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
