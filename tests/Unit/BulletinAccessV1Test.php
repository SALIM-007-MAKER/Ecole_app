<?php
/**
 * Tests de sécurité — accès aux bulletins et résultats (V1 /bulletins/* et V2 impression)
 *
 * Garantit que :
 *   - un ÉLÈVE ne consulte que son propre bulletin ; un PARENT que ceux de ses enfants ;
 *   - un ENSEIGNANT ne consulte que les élèves des classes où il est affecté pour l'année de la
 *     période demandée (affectations réelles `enseignements`, jamais un paramètre du navigateur) ;
 *   - les pages « résultats de classe » / « classement » de l'ancien module /bulletins sont
 *     réservées au personnel (aucun parent, élève ni enseignant) ;
 *   - un identifiant falsifié (élève inexistant, classe d'un autre, période inexistante) ne donne
 *     jamais accès et ne révèle pas si la ressource existe (même réponse 403) ;
 *   - la seule requête POST concernée (appréciation du chef d'établissement) refuse les rôles
 *     restreints, sans modification des données.
 *
 * Partie A : policies et périmètre sur données de test isolées (transaction annulée à la fin).
 * Partie B : requêtes de bout en bout (routeur → contrôleur → policy) avec les comptes réels,
 *            via tests/Support/request_as.php ; les fixtures sont découvertes dans la base et un
 *            scénario sans donnée adaptée est signalé « SKIP » (jamais compté comme réussi).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/BulletinAccessV1Test.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) { continue; }
    [$key, $val] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
}

spl_autoload_register(function (string $class): void {
    $map = [
        'Core\\' => '/core/', 'App\\Models\\' => '/app/Models/', 'App\\Services\\' => '/app/Services/',
        'App\\Modules\\' => '/app/Modules/', 'App\\Shared\\' => '/app/Shared/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = ROOT_PATH . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) { require_once $file; return; }
        }
    }
});

use App\Models\UserModel;
use App\Modules\Academique\Policies\BulletinPolicy;
use App\Modules\Academique\Services\TeacherScope;
use Core\Database;
use Core\Tenant\TenantContext;

$passed = 0; $failed = 0; $skipped = 0;

function check(bool $ok, string $label): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label\033[0m\n"; }
}
function skip(string $label, string $why): void
{
    global $skipped;
    $skipped++;
    echo "\033[33m  - SKIP $label ($why)\033[0m\n";
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

$pdo = Database::getInstance()->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ════════════════════════════════════════════════════════════════════════════
// PARTIE A — policies et périmètre (données de test isolées, transaction annulée)
// ════════════════════════════════════════════════════════════════════════════

$pdo->beginTransaction();
try {
    TenantContext::set(1);

    // Deux années scolaires distinctes, avec une période chacune (données existantes)
    $periodes = $pdo->query("SELECT id, annee_scolaire FROM periodes_scolaires ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $p1 = $periodes[0] ?? null;
    $p2 = null;
    foreach ($periodes as $p) { if ($p1 && $p['annee_scolaire'] !== $p1['annee_scolaire']) { $p2 = $p; break; } }
    $matiereId = (int)$pdo->query("SELECT MIN(id) FROM matieres")->fetchColumn();

    if (!$p1 || !$p2 || !$matiereId) {
        skip('Partie A', 'il faut 2 années scolaires avec période et au moins une matière');
    } else {
        $annee = $p1['annee_scolaire'];
        $uniq  = substr(md5((string)microtime(true)), 0, 8);

        $mkUser = function (string $role, string $tag) use ($pdo, $uniq): int {
            $pdo->prepare("INSERT INTO users (etablissement_id, nom, prenom, email, password, role, actif) VALUES (1, ?, 'Test', ?, 'x', ?, 1)")
                ->execute(["BA_$tag", "ba-$tag-$uniq@test.local", $role]);
            return (int)$pdo->lastInsertId();
        };
        $mkClasse = function (string $nom) use ($pdo, $annee): int {
            $pdo->prepare("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire) VALUES (1, ?, 'Test', ?)")->execute([$nom, $annee]);
            return (int)$pdo->lastInsertId();
        };
        $mkEleve = function (string $tag, int $classeId, ?int $userId, ?int $parentId) use ($pdo, $uniq): int {
            $pdo->prepare("INSERT INTO eleves (etablissement_id, matricule, nom, prenom, date_naissance, sexe, classe_id, parent_id, user_id, actif)
                           VALUES (1, ?, ?, 'Test', '2010-01-01', 'M', ?, ?, ?, 1)")
                ->execute([substr("BA-$tag-$uniq", 0, 20), "BA_$tag", $classeId, $parentId, $userId]);
            return (int)$pdo->lastInsertId();
        };

        $uTeacher   = $mkUser('enseignant', 'T1');
        $uTeacherNo = $mkUser('enseignant', 'T2');   // enseignant SANS fiche professeur
        $uParent1   = $mkUser('parent', 'P1');
        $uParent2   = $mkUser('parent', 'P2');
        $uEleveA1   = $mkUser('eleve', 'E1');
        $uEleveB1   = $mkUser('eleve', 'E2');
        $classeA = $mkClasse("BA_A_$uniq");
        $classeB = $mkClasse("BA_B_$uniq");
        $a1 = $mkEleve('a1', $classeA, $uEleveA1, $uParent1);   // élève de la classe A, enfant du parent 1
        $a2 = $mkEleve('a2', $classeA, null, $uParent2);        // camarade de classe, enfant du parent 2
        $b1 = $mkEleve('b1', $classeB, $uEleveB1, $uParent2);   // élève d'une AUTRE classe

        // L'enseignant n'est affecté qu'à la classe A, pour l'année $annee
        $pdo->prepare("INSERT INTO professeurs (etablissement_id, user_id, nom, prenom, specialite, actif) VALUES (1, ?, 'BA_Prof', 'Test', 'Test', 1)")->execute([$uTeacher]);
        $profId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO enseignements (etablissement_id, professeur_id, matiere_id, classe_id, annee_scolaire) VALUES (1, ?, ?, ?, ?)")
            ->execute([$profId, $matiereId, $classeA, $annee]);

        $cfg   = fn(string $role): array => (new UserModel())->getPermissions($role, 0, null);   // permissions de config/permissions.php
        $ctx   = fn(string $role, int $uid, array $perms): array => ['id' => $uid, 'role' => $role, 'etablissement_id' => 1, 'permissions' => $perms];
        $policy = new BulletinPolicy();
        $scope  = new TeacherScope();
        $P1 = (int)$p1['id'];            // période de l'année où l'enseignant est affecté
        $P2 = (int)$p2['id'];            // période d'une AUTRE année (aucune affectation)

        section('A1. Élève : son bulletin seulement');
        $eleveA1 = $ctx('eleve', $uEleveA1, $cfg('eleve'));
        check($policy->canViewForParent($eleveA1, $a1, $P1) === true,  'élève → son propre bulletin : autorisé');
        check($policy->canViewForParent($eleveA1, $a2, $P1) === false, 'élève → bulletin d\'un camarade de la MÊME classe : refusé');
        check($policy->canViewForParent($eleveA1, $b1, $P1) === false, 'élève → bulletin d\'un élève d\'une AUTRE classe : refusé');
        check($policy->canViewForParent($eleveA1, 999999999, $P1) === false, 'élève → identifiant inexistant : refusé');

        section('A2. Parent : ses enfants seulement');
        $parent1 = $ctx('parent', $uParent1, $cfg('parent'));
        $parent2 = $ctx('parent', $uParent2, $cfg('parent'));
        check($policy->canViewForParent($parent1, $a1, $P1) === true,  'parent 1 → son enfant : autorisé');
        check($policy->canViewForParent($parent1, $a2, $P1) === false, 'parent 1 → enfant d\'un autre parent (même classe) : refusé');
        check($policy->canViewForParent($parent1, $b1, $P1) === false, 'parent 1 → élève d\'une autre classe : refusé');
        check($policy->canViewForParent($parent2, $a2, $P1) === true && $policy->canViewForParent($parent2, $b1, $P1) === true,
              'parent 2 → ses deux enfants (classes différentes) : autorisé');
        check($policy->canViewForParent($parent2, $a1, $P1) === false, 'parent 2 → enfant du parent 1 : refusé');

        section('A3. Enseignant : élèves de ses classes affectées, pour l\'année de la période');
        $teacher   = $ctx('enseignant', $uTeacher,   $cfg('enseignant'));
        $teacherNo = $ctx('enseignant', $uTeacherNo, $cfg('enseignant'));
        check($policy->canViewForParent($teacher, $a1, $P1) === true,  'enseignant → élève de sa classe, bonne année : autorisé');
        check($policy->canViewForParent($teacher, $b1, $P1) === false, 'enseignant → élève d\'une classe NON affectée : refusé');
        check($policy->canViewForParent($teacher, $a1, $P2) === false, 'enseignant → sa classe mais année sans affectation : refusé');
        check($policy->canViewForParent($teacher, $a1, null) === false, 'enseignant → période absente : refusé (refus par défaut)');
        check($policy->canViewForParent($teacher, $a1, 999999999) === false, 'enseignant → période inexistante : refusé');
        check($policy->canViewForParent($teacher, 999999999, $P1) === false, 'enseignant → élève inexistant : refusé');
        check($policy->canViewForParent($teacherNo, $a1, $P1) === false, 'enseignant sans fiche professeur : refusé');
        check($scope->classeIds($uTeacher, 1, $annee) === [$classeA], 'TeacherScope::classeIds = [classe affectée]');
        check($scope->matiereIds($uTeacher, 1, $classeA, $annee) === [$matiereId], 'TeacherScope::matiereIds = matières affectées');
        check($scope->matiereIds($uTeacher, 1, $classeB, $annee) === [], 'TeacherScope::matiereIds (classe non affectée) = vide');

        section('A4. Résultats de classe / classement V1 : personnel uniquement');
        $all = ['bulletins.view', 'academique.bulletin.view', 'academique.bulletin.admin', '*'];
        foreach (['parent' => $uParent1, 'eleve' => $uEleveA1, 'enseignant' => $uTeacher] as $role => $uid) {
            check($policy->canBrowseClassResults($ctx($role, $uid, $all)) === false, "$role, même avec toutes les permissions : refusé");
        }
        check($policy->canBrowseClassResults($ctx('admin', 1, $cfg('admin'))) === true,       'admin : autorisé');
        check($policy->canBrowseClassResults($ctx('directeur', 2, $cfg('directeur'))) === true, 'directeur : autorisé');
        check($policy->canBrowseClassResults($ctx('secretaire', 3, $cfg('secretaire'))) === true, 'secrétaire (bulletins.view) : autorisé');
        check($policy->canBrowseClassResults($ctx('comptable', 4, $cfg('comptable'))) === false, 'comptable (sans permission bulletins) : refusé');

        section('A5. Régression : ancienne configuration (parent avec bulletins.view) ne rouvre rien');
        $oldParent = $ctx('parent', $uParent1, array_merge($cfg('parent'), ['bulletins.view', 'bulletins.print']));
        check($policy->canViewForParent($oldParent, $a2, $P1) === false, 'parent dont la session contient encore bulletins.view : enfant d\'autrui refusé');
        check($policy->canBrowseClassResults($oldParent) === false,       'parent dont la session contient encore bulletins.view : pas de résultats de classe');

        section('A6. Configuration : parent et élève n\'ont plus bulletins.view');
        $conf = require ROOT_PATH . '/config/permissions.php';
        check(!in_array('bulletins.view', $conf['parent'], true), 'config : parent sans bulletins.view');
        check(!in_array('bulletins.view', $conf['eleve'], true),  'config : eleve sans bulletins.view');
        $stmt = $pdo->query("SELECT r.code FROM etab_role_permissions rp JOIN etab_roles r ON r.id = rp.role_id
                              JOIN etab_permissions p ON p.id = rp.permission_id WHERE p.code = 'bulletins.view'");
        $rolesBase = $stmt->fetchAll(PDO::FETCH_COLUMN);
        check(!in_array('parent', $rolesBase, true) && !in_array('eleve', $rolesBase, true), 'base (etab_role_permissions) : parent et eleve sans bulletins.view');
        check(in_array('admin', $rolesBase, true) && in_array('secretaire', $rolesBase, true), 'base : le personnel conserve bulletins.view');
    }
} finally {
    $pdo->rollBack();
    TenantContext::clear();
    echo "\n(rollback effectué — aucune donnée de test persistée)\n";
}

// ════════════════════════════════════════════════════════════════════════════
// PARTIE B — requêtes de bout en bout avec les comptes réels
// ════════════════════════════════════════════════════════════════════════════

/** Exécute une requête de l'application complète sous l'identité donnée. */
function http(string $role, int $uid, string $method, string $uri, array $post = []): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' -d display_errors=0 ' . escapeshellarg(ROOT_PATH . '/tests/Support/request_as.php')
         . ' ' . escapeshellarg($role) . ' ' . $uid . ' ' . $method . ' ' . escapeshellarg('/ecole_app' . $uri)
         . ($post ? ' ' . escapeshellarg(json_encode($post)) : '');
    $out = (string)shell_exec($cmd);
    foreach (explode("\n", $out) as $l) {
        if (str_starts_with($l, 'RESULT:')) { return json_decode(substr($l, 7), true) ?: ['status' => 0]; }
    }
    return ['status' => 0, 'error' => 'pas de résultat'];
}
function fetchRow(PDO $pdo, string $sql, array $args = []): ?array
{
    $s = $pdo->prepare($sql); $s->execute($args);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

section('B0. Découverte des données de test');

$periode = fetchRow($pdo, "SELECT p.id, p.annee_scolaire FROM periodes_scolaires p ORDER BY p.id DESC LIMIT 1");

// Élève avec compte
$eleveAcc = fetchRow($pdo, "SELECT e.id AS eid, e.classe_id, u.id AS uid FROM eleves e JOIN users u ON u.id = e.user_id
                             WHERE u.role = 'eleve' AND u.actif = 1 AND e.classe_id IS NOT NULL ORDER BY e.id LIMIT 1");
$autreMemeClasse = $eleveAcc ? fetchRow($pdo, "SELECT id FROM eleves WHERE classe_id = ? AND id <> ? ORDER BY id LIMIT 1", [$eleveAcc['classe_id'], $eleveAcc['eid']]) : null;
$autreClasse     = $eleveAcc ? fetchRow($pdo, "SELECT id, classe_id FROM eleves WHERE classe_id IS NOT NULL AND classe_id <> ? ORDER BY id LIMIT 1", [$eleveAcc['classe_id']]) : null;

// Parent avec enfant(s)
$parentAcc = fetchRow($pdo, "SELECT u.id AS uid, MIN(e.id) AS enfant FROM users u JOIN eleves e ON e.parent_id = u.id
                              WHERE u.role = 'parent' AND u.actif = 1 GROUP BY u.id ORDER BY u.id LIMIT 1");
$pasEnfant = $parentAcc ? fetchRow($pdo, "SELECT id FROM eleves WHERE (parent_id IS NULL OR parent_id <> ?) ORDER BY id LIMIT 1", [$parentAcc['uid']]) : null;

// Enseignant avec compte, affectations, et une période de l'année concernée
$ens = fetchRow($pdo, "SELECT p.user_id AS uid, en.annee_scolaire AS annee, MIN(en.classe_id) AS classe
                         FROM professeurs p JOIN enseignements en ON en.professeur_id = p.id
                         JOIN users u ON u.id = p.user_id AND u.role = 'enseignant' AND u.actif = 1
                        WHERE p.actif = 1 AND EXISTS (SELECT 1 FROM periodes_scolaires ps WHERE ps.annee_scolaire = en.annee_scolaire)
                        GROUP BY p.user_id, en.annee_scolaire ORDER BY en.annee_scolaire DESC LIMIT 1");
$ensPeriode = $ens ? fetchRow($pdo, "SELECT id FROM periodes_scolaires WHERE annee_scolaire = ? ORDER BY id LIMIT 1", [$ens['annee']]) : null;
$ensEleveOk = $ens ? fetchRow($pdo, "SELECT id FROM eleves WHERE classe_id = ? ORDER BY id LIMIT 1", [$ens['classe']]) : null;
$ensEleveKo = $ens ? fetchRow($pdo, "SELECT id, classe_id FROM eleves el WHERE el.classe_id IS NOT NULL AND el.classe_id NOT IN
                                       (SELECT classe_id FROM enseignements en2 JOIN professeurs p2 ON p2.id = en2.professeur_id
                                         WHERE p2.user_id = ? AND en2.annee_scolaire = ?) ORDER BY id LIMIT 1", [$ens['uid'], $ens['annee']]) : null;

$admin = fetchRow($pdo, "SELECT id FROM users WHERE role = 'admin' AND actif = 1 ORDER BY id LIMIT 1");
$secr  = fetchRow($pdo, "SELECT id FROM users WHERE role = 'secretaire' AND actif = 1 ORDER BY id LIMIT 1");

echo '  élève=' . ($eleveAcc ? 'ok' : 'absent') . ' parent=' . ($parentAcc ? 'ok' : 'absent') . ' enseignant=' . ($ens && $ensPeriode && $ensEleveOk && $ensEleveKo ? 'ok' : 'incomplet')
   . ' admin=' . ($admin ? 'ok' : 'absent') . ' secrétaire=' . ($secr ? 'ok' : 'absent') . "\n";

$P = $periode ? (int)$periode['id'] : 0;

// ── Élève
section('B1. Élève : routes V1/V2 sur des données d\'autrui');
if (!$eleveAcc || !$autreClasse || !$P) {
    skip('B1', 'compte élève ou données manquantes');
} else {
    $uid = (int)$eleveAcc['uid']; $own = (int)$eleveAcc['eid'];
    $cibles = ['même classe' => $autreMemeClasse['id'] ?? null, 'autre classe' => $autreClasse['id']];
    foreach ($cibles as $lbl => $cible) {
        if ($cible === null) { skip("élève → bulletin V1 d'un élève de $lbl", 'aucun élève de ce type'); continue; }
        check(http('eleve', $uid, 'GET', "/bulletins/$cible?periode_id=$P")['status'] === 403, "élève → /bulletins/{élève $lbl} : 403");
        check(http('eleve', $uid, 'GET', "/v2/academique/bulletins/$cible/$P/imprimer")['status'] === 403, "élève → impression V2 d'un élève de $lbl : 403");
    }
    check(http('eleve', $uid, 'GET', "/bulletins/$own?periode_id=$P")['status'] === 403, 'élève → /bulletins/{son id} (route V1 du personnel) : 403');
    check(http('eleve', $uid, 'GET', "/bulletins/99999999?periode_id=$P")['status'] === 403, 'élève → /bulletins/{inexistant} : 403 (même réponse, pas d\'énumération)');
    check(http('eleve', $uid, 'GET', "/bulletins/classe?classe_id={$autreClasse['classe_id']}&periode_id=$P")['status'] === 403, 'élève → résultats d\'une classe : 403');
    check(http('eleve', $uid, 'GET', "/bulletins/classement?classe_id={$eleveAcc['classe_id']}&periode_id=$P")['status'] === 403, 'élève → classement (même sa propre classe) : 403');
    check(http('eleve', $uid, 'GET', '/bulletins')['status'] === 403, 'élève → /bulletins : 403');
    $r = http('eleve', $uid, 'GET', '/eleve/bulletin');
    check($r['status'] === 200 && !$r['forbidden'], 'élève → son écran /eleve/bulletin : accessible');
    $row = fetchRow($pdo, "SELECT periode_id FROM bulletins_v2 WHERE eleve_id = ? LIMIT 1", [$own]);
    if ($row) { check(http('eleve', $uid, 'GET', "/v2/academique/bulletins/$own/{$row['periode_id']}/imprimer")['status'] === 200, 'élève → impression V2 de son propre bulletin : 200'); }
    else      { skip('élève → impression de son propre bulletin', 'aucun bulletin généré pour cet élève'); }
}

// ── Parent
section('B2. Parent : routes V1/V2 sur des données d\'autrui');
if (!$parentAcc || !$pasEnfant || !$P) {
    skip('B2', 'compte parent avec enfant ou données manquantes');
} else {
    $uid = (int)$parentAcc['uid']; $enfant = (int)$parentAcc['enfant']; $autre = (int)$pasEnfant['id'];
    check(http('parent', $uid, 'GET', "/bulletins/$autre?periode_id=$P")['status'] === 403, 'parent → /bulletins/{élève qui n\'est pas son enfant} : 403');
    check(http('parent', $uid, 'GET', "/bulletins/$enfant?periode_id=$P")['status'] === 403, 'parent → /bulletins/{son enfant} (route V1 du personnel) : 403');
    check(http('parent', $uid, 'GET', "/v2/academique/bulletins/$autre/$P/imprimer")['status'] === 403, 'parent → impression V2 d\'un élève qui n\'est pas son enfant : 403');
    check(http('parent', $uid, 'GET', "/bulletins/classe?classe_id=" . ($autreClasse['classe_id'] ?? 1) . "&periode_id=$P")['status'] === 403, 'parent → résultats d\'une classe : 403');
    check(http('parent', $uid, 'GET', "/bulletins/classement?classe_id=" . ($autreClasse['classe_id'] ?? 1) . "&periode_id=$P")['status'] === 403, 'parent → classement : 403');
    $r = http('parent', $uid, 'GET', "/parent/bulletin?eleve_id=$autre");
    check($r['status'] === 200 && !$r['forbidden'], 'parent → /parent/bulletin?eleve_id={autrui} : page de SES enfants seulement (aucune fuite d\'un autre élève)');
    $row = fetchRow($pdo, "SELECT periode_id FROM bulletins_v2 WHERE eleve_id = ? LIMIT 1", [$enfant]);
    if ($row) { check(http('parent', $uid, 'GET', "/v2/academique/bulletins/$enfant/{$row['periode_id']}/imprimer")['status'] === 200, 'parent → impression V2 du bulletin de son enfant : 200'); }
    else      { skip('parent → impression du bulletin de son enfant', 'aucun bulletin généré pour cet enfant'); }
}

// ── Enseignant
section('B3. Enseignant : périmètre d\'affectation');
if (!$ens || !$ensPeriode || !$ensEleveOk || !$ensEleveKo) {
    skip('B3', 'enseignant avec affectations, ou élève hors périmètre, manquant');
} else {
    $uid = (int)$ens['uid']; $pid = (int)$ensPeriode['id']; $ok = (int)$ensEleveOk['id']; $ko = (int)$ensEleveKo['id'];
    check(http('enseignant', $uid, 'GET', "/bulletins/$ok?periode_id=$pid")['status'] === 200, 'enseignant → /bulletins/{élève de sa classe} : 200');
    check(http('enseignant', $uid, 'GET', "/bulletins/$ko?periode_id=$pid")['status'] === 403, 'enseignant → /bulletins/{élève d\'une classe non affectée} : 403');
    check(http('enseignant', $uid, 'GET', "/bulletins/99999999?periode_id=$pid")['status'] === 403, 'enseignant → /bulletins/{inexistant} : 403');
    check(http('enseignant', $uid, 'GET', "/v2/academique/bulletins/$ko/$pid/imprimer")['status'] === 403, 'enseignant → impression V2 hors périmètre : 403');
    foreach (["/bulletins", "/bulletins/classe?classe_id={$ens['classe']}&periode_id=$pid", "/bulletins/classement?classe_id={$ens['classe']}&periode_id=$pid",
              "/bulletins/classe?classe_id={$ensEleveKo['classe_id']}&periode_id=$pid"] as $u) {
        check(http('enseignant', $uid, 'GET', $u)['status'] === 403, "enseignant → $u : 403 (résultats/classement généraux non exposés)");
    }
    // Année sans affectation pour cet enseignant : même élève de sa classe, autre année
    $autrePeriode = fetchRow($pdo, "SELECT id FROM periodes_scolaires WHERE annee_scolaire <> ? ORDER BY id LIMIT 1", [$ens['annee']]);
    $affectAutreAnnee = $autrePeriode ? fetchRow($pdo, "SELECT 1 AS x FROM enseignements en JOIN professeurs p ON p.id = en.professeur_id
                                                          JOIN periodes_scolaires ps ON ps.id = ? AND ps.annee_scolaire = en.annee_scolaire
                                                         WHERE p.user_id = ? AND en.classe_id = ? LIMIT 1", [$autrePeriode['id'], $uid, $ens['classe']]) : null;
    if ($autrePeriode && !$affectAutreAnnee) {
        check(http('enseignant', $uid, 'GET', "/bulletins/$ok?periode_id={$autrePeriode['id']}")['status'] === 403, 'enseignant → sa classe mais sur une année où il n\'y est pas affecté : 403');
    } else {
        skip('enseignant → année sans affectation', 'toutes les années contiennent une affectation pour cette classe');
    }
}

// ── POST : appréciation du chef d'établissement
section('B4. POST appréciation du chef d\'établissement : rôles restreints refusés, données inchangées');
$bul = fetchRow($pdo, "SELECT b.eleve_id, b.periode_id, b.appreciation_directeur FROM bulletins_v2 b ORDER BY b.id LIMIT 1");
if (!$bul) {
    skip('B4', 'aucun bulletin généré');
} else {
    $url = "/v2/academique/bulletins/{$bul['eleve_id']}/{$bul['periode_id']}/appreciation-directeur";
    $comptes = array_filter([
        ['eleve', $eleveAcc['uid'] ?? null], ['parent', $parentAcc['uid'] ?? null], ['enseignant', $ens['uid'] ?? null],
    ], fn($c) => $c[1] !== null);
    foreach ($comptes as [$role, $uid]) {
        $r = http($role, (int)$uid, 'POST', $url, ['appreciation_directeur' => 'TEST-INTRUSION']);
        check($r['status'] === 403, "$role → POST appréciation : 403");
        $apres = fetchRow($pdo, "SELECT appreciation_directeur FROM bulletins_v2 WHERE eleve_id = ? AND periode_id = ?", [$bul['eleve_id'], $bul['periode_id']]);
        check(($apres['appreciation_directeur'] ?? null) === $bul['appreciation_directeur'], "$role → POST appréciation : valeur en base inchangée");
        check(http($role, (int)$uid, 'GET', $url)['status'] === 403, "$role → GET formulaire d'appréciation : 403");
    }
    if (!$comptes) { skip('B4', 'aucun compte restreint disponible'); }
}

// ── Personnel : l'accès légitime est conservé
section('B5. Personnel : accès légitime conservé');
if ($admin && $P) {
    $r = http('admin', (int)$admin['id'], 'GET', "/bulletins/classe?classe_id=" . ($autreClasse['classe_id'] ?? ($ens['classe'] ?? 1)) . "&periode_id=$P");
    check($r['status'] === 200 && !$r['forbidden'], 'admin → résultats d\'une classe : 200');
    $id = $autreClasse['id'] ?? ($ensEleveOk['id'] ?? null);
    if ($id) { check(http('admin', (int)$admin['id'], 'GET', "/bulletins/$id?periode_id=$P")['status'] === 200, 'admin → bulletin d\'un élève : 200'); }
} else { skip('admin', 'compte admin manquant'); }
if ($secr && $P) {
    $id = $autreClasse['id'] ?? ($ensEleveOk['id'] ?? null);
    if ($id) { check(http('secretaire', (int)$secr['id'], 'GET', "/bulletins/$id?periode_id=$P")['status'] === 200, 'secrétaire → bulletin d\'un élève (bulletins.view) : 200'); }
} else { skip('secrétaire', 'compte secrétaire manquant'); }

echo "\n";
$total = $passed + $failed;
echo ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : {$passed}/{$total} assertions passées"
   . ($skipped ? " ({$skipped} scénario(s) SKIP — non comptés)" : '') . "\033[0m\n";
exit($failed === 0 ? 0 : 1);
