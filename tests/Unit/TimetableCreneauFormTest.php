<?php
/**
 * Tests — Lot 1 : formulaire « ajouter un créneau » de l'emploi du temps (liste des matières planifiables).
 *
 * Défaut corrigé : la requête de TimetableController::ajouterCreneauForm lisait matieres.actif / matieres.couleur,
 * colonnes qui n'existent pas (erreur SQL 1054). La liste vient désormais de TimetableRepository::matieresPlanifiables().
 *
 * Toutes les requêtes HTTP visent une COPIE jetable de la base (REQUEST_AS_DB) ; la base réelle n'est jamais écrite
 * (empreintes avant/après comparées).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/TimetableCreneauFormTest.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ROOT', dirname(__DIR__, 2));
require ROOT . '/tests/Support/DbCopy.php';

use Tests\Support\DbCopy;

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  [OK]   $label\n"; } else { $fail++; echo "  [FAIL] $label" . ($detail !== '' ? " — $detail" : '') . "\n"; }
}
function section(string $t): void { echo "\n=== $t ===\n"; }

/** Exécute une requête de l'application complète sur la copie ; retourne [résultat JSON, corps]. */
function http(string $db, string $role, int $uid, string $method, string $uri, array $post = [], array $env = []): array
{
    $bodyFile = tempnam(sys_get_temp_dir(), 'ra_body_');
    $envVars = array_merge($_ENV, ['REQUEST_AS_DB' => $db, 'REQUEST_AS_COMMIT' => '1', 'REQUEST_AS_BODY_OUT' => $bodyFile], $env);
    $cmd = [PHP_BINARY, ROOT . '/tests/Support/request_as.php', $role, (string)$uid, $method, $uri];
    if ($post !== []) { $cmd[] = json_encode($post); }
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT, $envVars + getenv());
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    $body = (string)file_get_contents($bodyFile);
    @unlink($bodyFile);
    $res = [];
    if (preg_match('/RESULT:(\{.*\})/', $out, $m)) { $res = json_decode($m[1], true) ?: []; }
    return [$res, $body, $out];
}

$copy = DbCopy::create(ROOT);
if ($copy === null) { echo "Copie impossible (mysqldump/mysql indisponible) : test non exécuté.\n"; exit(1); }

$realConn = DbCopy::connectionFromEnv(ROOT);
$real = new PDO("mysql:host={$realConn['host']};port={$realConn['port']};dbname={$realConn['db']};charset=utf8mb4", $realConn['user'], $realConn['pass']);
$avant = DbCopy::allDataHashes($real);

try {
    $db = $copy->name;
    $pdo = $copy->pdo();
    $pdo->exec("SET NAMES utf8mb4");

    $admin = (int)$pdo->query("SELECT id FROM users WHERE role='admin' AND etablissement_id=1 ORDER BY id LIMIT 1")->fetchColumn();
    $ens   = (int)$pdo->query("SELECT id FROM users WHERE role='enseignant' AND etablissement_id=1 ORDER BY id LIMIT 1")->fetchColumn();
    $parent = (int)$pdo->query("SELECT id FROM users WHERE role='parent' ORDER BY id LIMIT 1")->fetchColumn();
    $plage = (int)$pdo->query("SELECT id FROM vs_edt_plages_horaires WHERE actif=1 ORDER BY id LIMIT 1")->fetchColumn();
    $salle = (int)$pdo->query("SELECT id FROM vs_edt_salles WHERE actif=1 ORDER BY id LIMIT 1")->fetchColumn();
    $francais = (int)$pdo->query("SELECT id FROM matieres WHERE nom LIKE 'Fran%' AND etablissement_id=1 ORDER BY id LIMIT 1")->fetchColumn();
    $nomFrancais = (string)$pdo->query("SELECT nom FROM matieres WHERE id=$francais")->fetchColumn();
    $pdo->exec("DELETE FROM vs_edt_creneaux");
    $pdo->exec("DELETE FROM vs_emplois_du_temps");   // état de départ propre (copie uniquement)

    section('Colonnes réelles de matieres (le schéma ne change pas)');
    $cols = $pdo->query("SHOW COLUMNS FROM matieres")->fetchAll(PDO::FETCH_COLUMN);
    check('matieres n\'a pas de colonne actif', !in_array('actif', $cols, true));
    check('matieres n\'a pas de colonne couleur', !in_array('couleur', $cols, true));

    section('Création de l\'emploi du temps (classe 131, période 90)');
    [$r] = http($db, 'admin', $admin, 'POST', '/ecole_app/v2/vie-scolaire/emplois-du-temps',
        ['classe_id' => 131, 'annee_scolaire' => '2025-2026', 'periode_id' => 90, 'semaine_type' => 'standard']);
    $edt = (int)$pdo->query("SELECT id FROM vs_emplois_du_temps WHERE classe_id=131 ORDER BY id DESC LIMIT 1")->fetchColumn();
    check('EDT créé (HTTP 302 ou 200)', in_array($r['status'] ?? 0, [200, 302], true) && $edt > 0, json_encode($r));

    section('Formulaire d\'ajout de créneau');
    [$r, $body, $raw] = http($db, 'admin', $admin, 'GET', "/ecole_app/v2/vie-scolaire/emplois-du-temps/$edt/creneaux/ajouter");
    check('HTTP 200', ($r['status'] ?? 0) === 200, json_encode($r));
    check('aucune erreur SQL 1054 / Unknown column', !preg_match('/1054|Unknown column|Champ .* inconnu|SQLSTATE/i', $body . $raw));
    check('matière ordinaire affichée (' . $nomFrancais . ')', $nomFrancais !== '' && str_contains($body, htmlspecialchars($nomFrancais, ENT_QUOTES, 'UTF-8')));
    check('le formulaire contient le jeton CSRF', str_contains($body, '_csrf_token'));
    $ids = [];
    if (preg_match('/<select[^>]*name="matiere_id".*?<\/select>/s', $body, $m)) { preg_match_all('/<option value="(\d+)"/', $m[0], $o); $ids = array_map('intval', $o[1]); }
    $attendues = array_map('intval', $pdo->query("SELECT id FROM matieres WHERE etablissement_id=1")->fetchAll(PDO::FETCH_COLUMN));
    sort($ids); sort($attendues);
    check('toutes les matières de l\'établissement sont proposées (' . count($attendues) . ')', $ids === $attendues, 'obtenu: ' . implode(',', $ids));

    section('Création d\'un créneau avec une matière ordinaire');
    [$r] = http($db, 'admin', $admin, 'POST', "/ecole_app/v2/vie-scolaire/emplois-du-temps/$edt/creneaux",
        ['matiere_id' => $francais, 'enseignant_id' => $ens, 'jour' => 1, 'plage_id' => $plage, 'salle_id' => $salle, 'type_cours' => 'cours']);
    check('POST créneau accepté (302)', ($r['status'] ?? 0) === 302, json_encode($r));
    $row = $pdo->query("SELECT * FROM vs_edt_creneaux WHERE emploi_du_temps_id=$edt")->fetch(PDO::FETCH_ASSOC);
    check('créneau enregistré en base', is_array($row) && (int)$row['matiere_id'] === $francais && (int)$row['classe_id'] === 131, json_encode($row));
    [$r, $body] = http($db, 'admin', $admin, 'GET', "/ecole_app/v2/vie-scolaire/emplois-du-temps/$edt");
    check('créneau relu dans la page de l\'EDT (HTTP 200 + nom de la matière)', ($r['status'] ?? 0) === 200 && str_contains($body, htmlspecialchars($nomFrancais, ENT_QUOTES, 'UTF-8')), json_encode($r));

    section('Autorisation et CSRF conservés');
    [$r] = http($db, 'parent', $parent, 'GET', "/ecole_app/v2/vie-scolaire/emplois-du-temps/$edt/creneaux/ajouter");
    check('parent : formulaire refusé (403 ou redirection)', in_array($r['status'] ?? 0, [302, 403], true) && empty($r['text']) === false, json_encode($r));
    [$r] = http($db, 'parent', $parent, 'POST', "/ecole_app/v2/vie-scolaire/emplois-du-temps/$edt/creneaux",
        ['matiere_id' => $francais, 'enseignant_id' => $ens, 'jour' => 2, 'plage_id' => $plage, 'salle_id' => $salle, 'type_cours' => 'cours']);
    check('parent : création refusée', in_array($r['status'] ?? 0, [302, 403], true), json_encode($r));
    [$r] = http($db, 'guest', 0, 'GET', "/ecole_app/v2/vie-scolaire/emplois-du-temps/$edt/creneaux/ajouter");
    check('visiteur : redirigé vers la connexion (302)', ($r['status'] ?? 0) === 302, json_encode($r));
    $avantCsrf = (int)$pdo->query("SELECT COUNT(*) FROM vs_edt_creneaux")->fetchColumn();
    [$r] = http($db, 'admin', $admin, 'POST', "/ecole_app/v2/vie-scolaire/emplois-du-temps/$edt/creneaux",
        ['matiere_id' => $francais, 'enseignant_id' => $ens, 'jour' => 3, 'plage_id' => $plage, 'salle_id' => $salle, 'type_cours' => 'cours'],
        ['REQUEST_AS_NO_CSRF' => '1']);
    $apresCsrf = (int)$pdo->query("SELECT COUNT(*) FROM vs_edt_creneaux")->fetchColumn();
    check('POST sans jeton CSRF : aucun créneau créé', $apresCsrf === $avantCsrf, "$avantCsrf -> $apresCsrf");
    check('POST sans jeton CSRF : refusé par redirection (302), pas de succès', ($r['status'] ?? 0) === 302);
    [$r] = http($db, 'admin', $admin, 'POST', "/ecole_app/v2/vie-scolaire/emplois-du-temps/$edt/creneaux",
        ['matiere_id' => $francais, 'enseignant_id' => $ens, 'jour' => 3, 'plage_id' => $plage, 'salle_id' => $salle, 'type_cours' => 'cours']);
    check('même requête AVEC jeton : créneau créé (le refus précédent venait bien du CSRF)', (int)$pdo->query("SELECT COUNT(*) FROM vs_edt_creneaux")->fetchColumn() === $avantCsrf + 1);
    $avantCsrf++;
    [$r] = http($db, 'admin', $admin, 'POST', "/ecole_app/v2/vie-scolaire/emplois-du-temps/$edt/creneaux",
        ['matiere_id' => $francais, 'enseignant_id' => $ens, 'jour' => 1, 'plage_id' => $plage, 'salle_id' => $salle, 'type_cours' => 'cours']);
    check('conflit horaire (même enseignant, même plage) : pas de doublon', (int)$pdo->query("SELECT COUNT(*) FROM vs_edt_creneaux")->fetchColumn() === $avantCsrf);

    section('Isolation par établissement de la liste des matières');
    $pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('lot1-autre', 'Autre école Lot1', 'Autre', 'lycee')");
    $autre = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO matieres (etablissement_id, nom, coefficient) VALUES ($autre, 'MatiereAutreEtabLot1', 1)");
    $adminAutre = null;
    // Dépôt direct : le dépôt est testé avec chaque identifiant d'établissement.
    $_ENV = array_merge($_ENV, DbCopy::connectionFromEnv(ROOT));
    $_ENV['DB_DATABASE'] = $db;
    $_ENV['DB_HOST'] = $realConn['host']; $_ENV['DB_USERNAME'] = $realConn['user']; $_ENV['DB_PASSWORD'] = $realConn['pass'];
    define('ROOT_PATH', ROOT);
    spl_autoload_register(function (string $c): void {
        foreach (['Core\\' => '/core/', 'App\\' => '/app/'] as $pre => $dir) {
            if (str_starts_with($c, $pre)) { $f = ROOT . $dir . str_replace('\\', '/', substr($c, strlen($pre))) . '.php'; if (is_file($f)) { require $f; return; } }
        }
    });
    $repo = new \App\Modules\VieScolaire\EmploisDuTemps\Repositories\TimetableRepository();
    $noms1 = array_column($repo->matieresPlanifiables(1), 'nom');
    $nomsA = array_column($repo->matieresPlanifiables($autre), 'nom');
    check('établissement 1 : ne contient pas la matière de l\'autre établissement', !in_array('MatiereAutreEtabLot1', $noms1, true));
    check('autre établissement : uniquement sa matière', $nomsA === ['MatiereAutreEtabLot1'], json_encode($nomsA));
    check('établissement inconnu (0) : liste vide (échec fermé)', $repo->matieresPlanifiables(0) === []);
    $ordre = array_column($repo->matieresPlanifiables(1), 'nom');
    $ordreSql = $pdo->query("SELECT nom FROM matieres WHERE etablissement_id = 1 ORDER BY nom ASC, id ASC")->fetchAll(PDO::FETCH_COLUMN);
    check('liste complète pour l’établissement 1', count($ordre) === count($attendues));
    check('ordre alphabétique (collation MySQL) respecté, comparé à ORDER BY nom', $ordre === $ordreSql);
    check('Conduite non filtrée au Lot 1 (traitée au Lot 2)', in_array('Conduite', $noms1, true));
} finally {
    $copy->drop();
}

$apres = DbCopy::allDataHashes($real);
check('base réelle inchangée (empreintes de toutes les tables)', $avant === $apres);

echo "\n--- $pass OK / $fail échec(s) ---\n";
exit($fail === 0 ? 0 : 1);
