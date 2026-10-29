<?php
/**
 * Tests — Lot 3, étape 1 : contexte d'établissement déduit de la SESSION authentifiée (SessionTenantGuard + Application).
 *
 * A. Garde (unitaire, sur copie) : membre, repli V1 des parents sans appartenance, établissement absent / inconnu / supprimé /
 *    suspendu / annulé / archivé, compte désactivé, ancien membre, visiteur, routes plateforme.
 * B. HTTP complet entre DEUX établissements (copie) : l'établissement 2 ne lit ni ne modifie les données scopées de
 *    l'établissement 1, et inversement ; un contexte invalide est refusé (session invalidée, redirection vers la connexion) ;
 *    les parents sans appartenance et les routes plateforme ne sont pas bloqués.
 * C. Information : accès par identifiant des modules Académique et Vie scolaire, traités aux étapes 2 et 3 (non comptés).
 *
 * Tout tourne sur une COPIE RÉELLE jetable (DbCopy) ; la base source n'est jamais écrite (empreinte avant / après).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/TenantSessionContextTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);
define('BASE_URL', '/ecole_app');
require_once ROOT_PATH . '/tests/Support/DbCopy.php';
require_once ROOT_PATH . '/tests/Support/ConduiteCopy.php';

use Core\Tenant\SessionTenantGuard;
use Tests\Support\ConduiteCopy;
use Tests\Support\DbCopy;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function info(string $label, bool $protege): void { echo ($protege ? "\033[32m  ✓ (info)" : "\033[33m  ⚠ (info, étape 2/3)") . " $label\033[0m\n"; }
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
    $nom = $copy->name;
    $http = fn(string $role, int $uid, string $m, string $u, array $post = [], array $env = []) => ConduiteCopy::http(ROOT_PATH, $nom, $role, $uid, $m, "/ecole_app{$u}", $post, $env);
    $one = fn(string $sql, array $p = []) => (function () use ($db, $sql, $p) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();

    // ── Jeu de données : un SECOND établissement complet (élève, classe, matière, administrateur) ──
    $db->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('lot3-e2', 'Ecole Deux Lot3', 'E2', 'lycee')");
    $E2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire) VALUES ($E2, 'CLASSE-E2-ZETA', '6eme', '2025-2026')");
    $C2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO eleves (etablissement_id, matricule, nom, prenom, date_naissance, sexe, classe_id, actif) VALUES ($E2, 'E2-0001', 'ELEVEDEUXZETA', 'Zoe', '2012-01-01', 'F', $C2, 1)");
    $EL2 = (int)$db->lastInsertId();
    $db->exec("INSERT INTO matieres (etablissement_id, nom, coefficient, volume_horaire) VALUES ($E2, 'MATIERE-E2-ZETA', 2, 3)");
    $M2 = (int)$db->lastInsertId();
    $mkUser = function (string $email, string $role, int $etab, int $actif = 1, bool $membre = true) use ($db): int {
        $db->prepare('INSERT INTO users (etablissement_id, nom, prenom, email, password, role, actif) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$etab, 'N' . $email, 'P', $email, 'x', $role, $actif]);
        $id = (int)$db->lastInsertId();
        if ($membre) { $db->prepare('INSERT INTO user_etablissements (user_id, etablissement_id, is_primary) VALUES (?, ?, 1)')->execute([$id, $etab]); }
        return $id;
    };
    $ADMIN2 = $mkUser('admin2-lot3@example.test', 'admin', $E2);
    $ADMIN1 = 1; $PARENT_SANS_MEMBRE = 281;
    $E1 = 1;

    // ═════════════════════════════════════════════════════════════════════
    section('A. SessionTenantGuard — règles de résolution (fail-closed)');
    $g = new SessionTenantGuard($db);
    $s = fn(int $id, $etab) => ['id' => $id, 'etablissement_id' => $etab];
    $r = $g->evaluer($s($ADMIN1, $E1));
    check($r['ok'] && $r['etablissement_id'] === $E1 && $r['etablissement'] !== null, 'membre de l\'établissement 1 : accepté, contexte = 1');
    $r = $g->evaluer($s($ADMIN2, $E2));
    check($r['ok'] && $r['etablissement_id'] === $E2, 'membre de l\'établissement 2 : accepté, contexte = 2');
    $r = $g->evaluer($s($ADMIN1, $E2));
    check(!$r['ok'] && $r['raison'] === SessionTenantGuard::RAISON_NON_MEMBRE, 'admin de l\'établissement 1 avec une session pointant l\'établissement 2 : refusé (non membre)');
    $r = $g->evaluer($s($ADMIN2, $E1));
    check(!$r['ok'] && $r['raison'] === SessionTenantGuard::RAISON_NON_MEMBRE, 'admin de l\'établissement 2 avec une session pointant l\'établissement 1 : refusé');
    $r = $g->evaluer($s($PARENT_SANS_MEMBRE, $E1));
    check($r['ok'] && $r['etablissement_id'] === $E1, 'parent sans appartenance enregistrée, users.etablissement_id = 1 : accepté (repli V1 identique à la connexion)');
    $r = $g->evaluer($s($PARENT_SANS_MEMBRE, $E2));
    check(!$r['ok'], 'ce même parent avec une session pointant l\'établissement 2 : refusé');
    foreach ([null => 'absent (null)', 0 => '0', '' => 'vide', 'abc' => 'non numérique', -3 => 'négatif'] as $val => $lib) {
        $r = $g->evaluer(['id' => $ADMIN1, 'etablissement_id' => $val === 0 ? 0 : ($val === '' ? '' : $val)]);
        check(!$r['ok'] && $r['etablissement_id'] === null, "établissement $lib : refusé, aucun établissement par défaut");
    }
    $r = $g->evaluer(['id' => $ADMIN1]);
    check(!$r['ok'] && $r['raison'] === SessionTenantGuard::RAISON_ETAB_ABSENT, 'clé etablissement_id absente de la session : refusé');
    $r = $g->evaluer($s($ADMIN1, 999999));
    check(!$r['ok'] && $r['raison'] === SessionTenantGuard::RAISON_ETAB_INTROUVABLE, 'établissement inexistant : refusé');
    $statutDe = fn(string $st) => (function () use ($db, $E2, $ADMIN2, $g, $st) { $db->exec("UPDATE etablissements SET statut = '$st' WHERE id = $E2"); return $g->evaluer(['id' => $ADMIN2, 'etablissement_id' => $E2]); })();
    foreach (['suspended', 'cancelled', 'archived'] as $st) {
        $r = $statutDe($st);
        check(!$r['ok'] && $r['raison'] === SessionTenantGuard::RAISON_ETAB_INDISPONIBLE, "établissement « $st » : refusé");
    }
    foreach (['trial', 'active'] as $st) { check($statutDe($st)['ok'], "établissement « $st » : accepté"); }
    $db->exec("UPDATE etablissements SET deleted_at = NOW() WHERE id = $E2");
    $r = $g->evaluer($s($ADMIN2, $E2));
    check(!$r['ok'] && $r['raison'] === SessionTenantGuard::RAISON_ETAB_INTROUVABLE, 'établissement supprimé (deleted_at) : refusé');
    $db->exec("UPDATE etablissements SET deleted_at = NULL WHERE id = $E2");
    $INACTIF = $mkUser('inactif-lot3@example.test', 'admin', $E2, 0);
    $r = $g->evaluer($s($INACTIF, $E2));
    check(!$r['ok'] && $r['raison'] === SessionTenantGuard::RAISON_COMPTE_DESACTIVE, 'compte désactivé : refusé');
    $r = $g->evaluer($s(987654, $E1));
    check(!$r['ok'] && $r['raison'] === SessionTenantGuard::RAISON_UTILISATEUR_INVALIDE, 'utilisateur inexistant : refusé');
    $r = $g->evaluer(['id' => 0, 'etablissement_id' => $E1]);
    check(!$r['ok'], 'identifiant utilisateur nul : refusé');
    $ANCIEN = $mkUser('ancien-lot3@example.test', 'admin', $E2);
    $db->exec("UPDATE user_etablissements SET left_at = NOW() WHERE user_id = $ANCIEN");
    $r = $g->evaluer($s($ANCIEN, $E2));
    check(!$r['ok'], 'ancien membre (left_at renseigné) : refusé, le repli V1 ne le réadmet pas');
    $r = $g->evaluer(null);
    check($r['ok'] && $r['raison'] === SessionTenantGuard::RAISON_ANONYME && $r['etablissement_id'] === null, 'visiteur non connecté : aucun contexte, non bloqué');
    check(SessionTenantGuard::routeSansContexte('/ecole_app/platform/dashboard', '/ecole_app') && SessionTenantGuard::routeSansContexte('/ecole_app/platform', '/ecole_app') && SessionTenantGuard::routeSansContexte('/platform/login?x=1', '')
          && !SessionTenantGuard::routeSansContexte('/ecole_app/platformfoo', '/ecole_app') && !SessionTenantGuard::routeSansContexte('/ecole_app/eleves/1', '/ecole_app') && !SessionTenantGuard::routeSansContexte('/ecole_app/v2/platform/x', '/ecole_app'),
          'routes plateforme (/platform/*) exclues du contexte d\'établissement ; aucune autre');

    // ═════════════════════════════════════════════════════════════════════
    section('B1. HTTP — référence : l\'établissement 1 voit toujours ses données');
    [$r, $b] = $http('admin', $ADMIN1, 'GET', '/eleves/1');
    check(($r['status'] ?? 0) === 200 && str_contains($b, 'Nom08'), 'admin 1 : fiche de l\'élève 1');
    [$r, $b] = $http('admin', $ADMIN1, 'GET', '/classes/131');
    check(($r['status'] ?? 0) === 200 && str_contains($b, '2nde C1'), 'admin 1 : fiche de la classe 131');
    [$r, $b] = $http('admin', $ADMIN1, 'GET', '/matieres/190');
    check(($r['status'] ?? 0) === 200 && str_contains($b, 'Conduite'), 'admin 1 : fiche de la matière 190');
    [$r, $b] = $http('admin', $ADMIN1, 'GET', '/eleves');
    check(($r['status'] ?? 0) === 200 && str_contains($b, 'Nom08') && !str_contains($b, 'ELEVEDEUXZETA'), 'admin 1 : liste des élèves (ses élèves, pas ceux de l\'établissement 2)');
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/eleves');
    check(($r['status'] ?? 0) === 200 && str_contains($b, 'ELEVEDEUXZETA') && !str_contains($b, 'Nom08'), 'admin 2 : liste des élèves = les siens uniquement');
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/classes');
    check(str_contains($b, 'CLASSE-E2-ZETA') && !str_contains($b, '2nde C1'), 'admin 2 : liste des classes = les siennes uniquement');
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/matieres');
    check(str_contains($b, 'MATIERE-E2-ZETA') && !str_contains($b, 'Conduite'), 'admin 2 : liste des matières = les siennes uniquement');

    section('B2. HTTP — l\'établissement 2 ne lit pas l\'établissement 1 (V1 scopé)');
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/eleves/1');
    check(!str_contains($b, 'Nom08'), 'admin 2 : fiche de l\'élève 1 (étab. 1) — aucune donnée', 'statut ' . ($r['status'] ?? '?'));
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/classes/131');
    check(!str_contains($b, '2nde C1'), 'admin 2 : fiche de la classe 131 (étab. 1) — aucune donnée', 'statut ' . ($r['status'] ?? '?'));
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/matieres/190');
    check(!str_contains($b, 'Conduite'), 'admin 2 : fiche de la matière 190 (étab. 1) — aucune donnée', 'statut ' . ($r['status'] ?? '?'));
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/eleves/1/edit');
    check(!str_contains($b, 'Nom08'), 'admin 2 : formulaire de modification de l\'élève 1 — aucune donnée');

    section('B3. HTTP — l\'établissement 2 ne modifie pas l\'établissement 1 (V1 scopé)');
    $http('admin', $ADMIN2, 'POST', '/matieres/4', ['nom' => 'PIRATE-MATIERE', 'coefficient' => '2', 'volume_horaire' => '3', 'categorie' => 'autre']);
    check($one('SELECT nom FROM matieres WHERE id = 4') !== 'PIRATE-MATIERE', 'admin 2 : modification de la matière 4 (étab. 1) sans effet');
    $http('admin', $ADMIN2, 'POST', '/eleves/1/delete');
    check((int)$one('SELECT COUNT(*) FROM eleves WHERE id = 1') === 1, 'admin 2 : suppression de l\'élève 1 (étab. 1) sans effet');
    $http('admin', $ADMIN2, 'POST', '/classes/131/delete');
    check((int)$one('SELECT COUNT(*) FROM classes WHERE id = 131') === 1, 'admin 2 : suppression de la classe 131 (étab. 1) sans effet');
    $http('admin', $ADMIN2, 'POST', '/matieres/4/delete');
    check((int)$one('SELECT COUNT(*) FROM matieres WHERE id = 4') === 1, 'admin 2 : suppression de la matière 4 (étab. 1) sans effet');

    section('B4. HTTP — l\'établissement 1 ne lit ni ne modifie l\'établissement 2');
    [$r, $b] = $http('admin', $ADMIN1, 'GET', "/eleves/$EL2");
    check(!str_contains($b, 'ELEVEDEUXZETA'), 'admin 1 : fiche d\'un élève de l\'établissement 2 — aucune donnée');
    [$r, $b] = $http('admin', $ADMIN1, 'GET', "/classes/$C2");
    check(!str_contains($b, 'CLASSE-E2-ZETA'), 'admin 1 : fiche d\'une classe de l\'établissement 2 — aucune donnée');
    [$r, $b] = $http('admin', $ADMIN1, 'GET', "/matieres/$M2");
    check(!str_contains($b, 'MATIERE-E2-ZETA'), 'admin 1 : fiche d\'une matière de l\'établissement 2 — aucune donnée');
    $http('admin', $ADMIN1, 'POST', "/matieres/$M2", ['nom' => 'PIRATE-1', 'coefficient' => '2', 'volume_horaire' => '3', 'categorie' => 'autre']);
    check($one('SELECT nom FROM matieres WHERE id = ?', [$M2]) === 'MATIERE-E2-ZETA', 'admin 1 : modification d\'une matière de l\'établissement 2 sans effet');
    $http('admin', $ADMIN1, 'POST', "/eleves/$EL2/delete");
    check((int)$one('SELECT COUNT(*) FROM eleves WHERE id = ?', [$EL2]) === 1, 'admin 1 : suppression d\'un élève de l\'établissement 2 sans effet');

    section('B5. HTTP — contexte invalide : accès refusé, session invalidée, jamais d\'établissement par défaut');
    foreach ([['admin', $ADMIN1, (string)$E2, 'admin 1 avec une session pointant l\'établissement 2 (non membre)'],
              ['admin', $ADMIN2, '1', 'admin 2 avec une session pointant l\'établissement 1 (non membre)'],
              ['admin', $ADMIN1, '999999', 'admin 1 avec un établissement inexistant']] as [$role, $uid, $etab, $lib]) {
        [$r, $b] = $http($role, $uid, 'GET', '/eleves/1', [], ['REQUEST_AS_ETAB' => $etab]);
        $loc = implode(' ', array_map('strval', (array)($r['headers'] ?? [])));
        check(($r['status'] ?? 0) === 302 && str_contains($loc, '/login') && !str_contains($b, 'Nom08'), "$lib : redirigé vers la connexion, aucune donnée", 'statut ' . ($r['status'] ?? '?'));
    }
    $db->exec("UPDATE etablissements SET statut = 'suspended' WHERE id = $E2");
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/eleves');
    check(($r['status'] ?? 0) === 302 && !str_contains($b, 'ELEVEDEUXZETA'), 'établissement 2 suspendu : accès refusé à ses propres membres');
    $db->exec("UPDATE etablissements SET statut = 'active' WHERE id = $E2");
    $db->exec("UPDATE users SET actif = 0 WHERE id = $ADMIN2");
    [$r] = $http('admin', $ADMIN2, 'GET', '/eleves');
    check(($r['status'] ?? 0) === 302, 'compte désactivé en cours de session : accès refusé à la requête suivante');
    $db->exec("UPDATE users SET actif = 1 WHERE id = $ADMIN2");
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/eleves');
    check(($r['status'] ?? 0) === 200 && str_contains($b, 'ELEVEDEUXZETA'), 'compte réactivé : accès rétabli');

    section('B6. HTTP — non-régression : parents sans appartenance et routes plateforme');
    [$r] = $http('parent', $PARENT_SANS_MEMBRE, 'GET', '/dashboard');
    check(in_array($r['status'] ?? 0, [200, 302], true) && !str_contains(implode(' ', array_map('strval', (array)($r['headers'] ?? []))), '/login'), 'parent sans appartenance enregistrée : session acceptée (repli V1), pas de redirection vers la connexion', 'statut ' . ($r['status'] ?? '?'));
    [$r] = $http('admin', $ADMIN1, 'GET', '/platform/login', [], ['REQUEST_AS_ETAB' => '999999']);
    check(($r['status'] ?? 0) === 200, 'route plateforme : non soumise au contexte d\'établissement (même avec une session d\'établissement invalide)');
    [$r] = $http('guest', 0, 'GET', '/login');
    check(($r['status'] ?? 0) === 200, 'visiteur : page de connexion inchangée');

    // ═════════════════════════════════════════════════════════════════════
    section('C. Information — accès par identifiant des modules Académique et Vie scolaire (étapes 2 et 3, non comptés)');
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/v2/academique/evaluations/4');
    info('admin 2 : fiche de l\'évaluation 4 (étab. 1) masquée', !str_contains($b, 'Conduite'));
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/v2/academique/evaluations/4/notes');
    info('admin 2 : notes de l\'évaluation 4 (étab. 1) masquées', !str_contains($b, 'Nom08'));
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/v2/academique/bulletins/1/90/imprimer');
    info('admin 2 : bulletin de l\'élève 1 (étab. 1) masqué', !str_contains($b, 'Nom08'));
    $db->exec("UPDATE evaluations SET statut = 'brouillon' WHERE id = 12");
    $http('admin', $ADMIN2, 'POST', '/v2/academique/evaluations/12/publier');
    info('admin 2 : publication d\'une évaluation de l\'étab. 1 sans effet', $one('SELECT statut FROM evaluations WHERE id = 12') === 'brouillon');
} finally {
    $copy->drop();
}

section('D. Base réelle');
check(DbCopy::allDataHashes($source) === $avantSource, 'base réelle inchangée (empreinte de toutes les tables)');
check(count(array_filter($source->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN), fn($d) => str_starts_with($d, 'ecole_app_copy_'))) === 0, 'aucune base temporaire restante');

echo "\n" . ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : $passed/" . ($passed + $failed) . " assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
