<?php
/**
 * Tests — Phase 1 : authentification GLOBALE (connexion commune `/login`).
 *
 *  1. Connexion d'un utilisateur de l'établissement 1 (témoin) et de l'établissement 2 (le défaut corrigé)
 *  2. Réponse neutre : e-mail inconnu / mauvais mot de passe (même message, même redirection, aucune session)
 *  3. Compte désactivé ; limitation de débit inchangée
 *  4. Sélecteur multi-établissements : aucune session scolaire avant le choix, choix revérifié côté serveur, établissement non autorisé,
 *     établissement bloqué, expiration de l'état d'attente (10 minutes)
 *  5. Appartenance quittée, comptes historiques sans appartenance (repli), établissements bloqués (suspendu, résilié, archivé, abonnement)
 *  6. RÔLES : rôle propre à l'établissement, appartenance sans rôle refusée (jamais de repli sur users.role), rôles multiples (priorité
 *     fixe, réunion des permissions, indépendance de l'ordre en base)
 *  7. Rafraîchissement de session : rôle retiré => session invalidée ; rôle modifié => mis à jour
 *  8. Permissions RBAC V2 globales jamais héritées (mode strict), comportement historique conservé hors mode strict
 *  9. Aucun compte ni appartenance ni rôle créé / modifié / élevé par une connexion, un échec ou un choix
 * 10. Journal global : événements auth.*, acteur school_user, aucun secret ; journal en ajout seul
 * 11. Migration T068 : extension pure de l'ENUM, idempotente, retour arrière encadré ; compatibilité AVANT T068
 * 12. UserModel reste limité à son établissement (la recherche globale est isolée dans GlobalAuthService)
 *
 * Tout tourne sur des copies jetables ecole_app_copy_* : la base vivante n'est jamais touchée.
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/AuthGlobaleTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\Models\\' => '/app/Models/', 'App\\Services\\' => '/app/Services/', 'App\\Shared\\' => '/app/Shared/', 'App\\Controllers\\' => '/app/Controllers/'] as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php';
            if (is_file($f)) { require $f; return; }
        }
    }
});
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use App\Services\GlobalAuthService;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

$MDP = 'Mot-De-Passe-Test-2026!';
$HASH = password_hash($MDP, PASSWORD_BCRYPT);
$dossierTmp = sys_get_temp_dir() . '/authglob_' . bin2hex(random_bytes(4));
@mkdir($dossierTmp . '/throttle', 0775, true);
\Core\Platform\PlatformAuditService::definirDossierSecours($dossierTmp . '/secours');

/** Contexte d'une copie jetable : connexion PDO, nom de la base, compteur d'adresses IP distinctes (limitation de débit isolée par scénario). */
final class Ctx
{
    public int $ip = 10;
    public int $n = 0;
    public function __construct(public PDO $pdo, public string $db, public string $tmp) {}
    public function ip(): string { return '10.77.' . (++$this->ip % 250) . '.' . random_int(1, 250); }
}

/** Une requête HTTP complète (session persistante dans $session). @return array<string,mixed> */
function req(Ctx $c, string $session, string $methode, string $uri, array $post = [], ?string $ip = null): array
{
    $p = proc_open([PHP_BINARY, ROOT_PATH . '/tests/Support/auth_request.php', $session, $methode, '/ecole_app' . $uri, json_encode($post)],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH,
        array_merge(getenv(), ['REQUEST_AS_DB' => $c->db, 'AUTH_THROTTLE_DIR' => $c->tmp . '/throttle', 'AUTH_REQ_IP' => $ip ?? '127.0.0.1', 'AUTH_REQ_AUDIT_DIR' => $c->tmp . '/secours']));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    return preg_match('/^RESULT:(.*)$/m', $out, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0, 'brut' => substr($out, 0, 400)];
}
function nouvelleSession(Ctx $c): string { return $c->tmp . '/sess_' . bin2hex(random_bytes(4)) . '.json'; }
function login(Ctx $c, string $session, string $email, string $mdp, ?string $ip = null): array
{
    return req($c, $session, 'POST', '/login', ['email' => $email, 'password' => $mdp], $ip ?? $c->ip());
}
/** Messages flash « errors » aplatis. */
function messages(array $r): string
{
    $e = $r['flash']['errors'] ?? [];
    $out = [];
    array_walk_recursive($e, function ($v) use (&$out) { $out[] = (string)$v; });
    return implode(' | ', $out);
}

/** Crée un établissement de test ; $statut : active|suspended|cancelled|archived. */
function creerEtab(Ctx $c, string $slug, string $statut = 'active'): int
{
    $c->pdo->prepare("INSERT INTO etablissements (slug, nom, nom_court, type, statut) VALUES (?, ?, ?, 'lycee', ?)")->execute([$slug, "École $slug", strtoupper($slug), $statut]);
    return (int)$c->pdo->lastInsertId();
}
function idRole(Ctx $c, string $code): int
{
    $s = $c->pdo->prepare('SELECT id FROM etab_roles WHERE code = ? AND etablissement_id IS NULL'); $s->execute([$code]);
    return (int)$s->fetchColumn();
}
/**
 * Crée un utilisateur. $appartenances : [etab_id => [codes de rôle]] (lignes user_etablissements + user_roles_etab) ; $quittees : appartenances
 * avec left_at renseigné. users.role = $roleGlobal (rôle GLOBAL de la personne, jamais utilisé dès qu'une appartenance existe).
 */
function creerUser(Ctx $c, string $email, string $roleGlobal, int $etabOrigine, array $appartenances = [], array $quittees = [], int $actif = 1): int
{
    global $HASH;
    $c->pdo->prepare('INSERT INTO users (nom, prenom, email, password, role, actif, etablissement_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute(['Test', explode('@', $email)[0], $email, $HASH, $roleGlobal, $actif, $etabOrigine]);
    $id = (int)$c->pdo->lastInsertId();
    $primaire = 1;
    foreach ($appartenances as $etab => $roles) {
        $c->pdo->prepare('INSERT INTO user_etablissements (user_id, etablissement_id, is_primary) VALUES (?, ?, ?)')->execute([$id, $etab, $primaire]);
        $primaire = 0;
        foreach ($roles as $code) {
            $c->pdo->prepare('INSERT INTO user_roles_etab (user_id, etablissement_id, role_id) VALUES (?, ?, ?)')->execute([$id, $etab, idRole($c, $code)]);
        }
    }
    foreach ($quittees as $etab) {
        $c->pdo->prepare('INSERT INTO user_etablissements (user_id, etablissement_id, is_primary, left_at) VALUES (?, ?, 0, NOW())')->execute([$id, $etab]);
    }
    return $id;
}
function audits(Ctx $c, string $action): array
{
    $s = $c->pdo->prepare('SELECT * FROM platform_audit_logs WHERE action = ? ORDER BY id'); $s->execute([$action]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
function empreinteComptes(Ctx $c): string
{
    $q = fn(string $sql) => $c->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return md5(json_encode([
        $q('SELECT id, nom, prenom, email, password, role, actif, etablissement_id FROM users ORDER BY id'),
        $q('SELECT * FROM user_etablissements ORDER BY id'),
        $q('SELECT * FROM user_roles_etab ORDER BY id'),
        $q('SELECT * FROM etab_roles ORDER BY id'),
        $q('SELECT * FROM rbac_user_roles ORDER BY id'),
    ]));
}

// redirigerVersCopie : le singleton Database (UserModel, services sans argument) pointe vers la COPIE jetable, jamais vers la base vivante.
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible\n");
$copie2 = null;
try {
    $nom = $copie->connection()['db'];
    $pdo = \Core\Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (!preg_match('/^ecole_app_copy_[a-f0-9]+$/', (string)$pdo->query('SELECT DATABASE()')->fetchColumn())) { exit("REFUS : base de travail non jetable\n"); }
    $c = new Ctx($pdo, $nom, $dossierTmp);
    $auth = new GlobalAuthService($pdo);

    // ── Décor : établissements 2..6 et comptes de test ───────────────────────────────────────────────────────────────────────
    $E2 = creerEtab($c, 'ecole-deux');
    $E3 = creerEtab($c, 'ecole-trois-suspendue', 'suspended');
    $E4 = creerEtab($c, 'ecole-quatre-resiliee', 'cancelled');
    $E5 = creerEtab($c, 'ecole-cinq-archivee', 'archived');
    $E6 = creerEtab($c, 'ecole-six-expiree');   // active, mais abonnement expiré
    $plan = (int)$pdo->query('SELECT id FROM platform_plans ORDER BY id LIMIT 1')->fetchColumn();
    $pdo->exec("INSERT INTO platform_subscriptions (etablissement_id, plan_id, statut, cycle, date_debut, date_fin, devise) VALUES ($E6, $plan, 'expired', 'monthly', '2020-01-01', '2020-01-31', 'XOF')");

    $avantComptes = empreinteComptes($c);
    $admin1 = creerUser($c, 'admin.un@exemple.test', 'admin', 1, [1 => ['admin']]);
    $admin2 = creerUser($c, 'admin.deux@exemple.test', 'admin', $E2, [$E2 => ['admin']]);
    $multi  = creerUser($c, 'multi@exemple.test', 'admin', 1, [1 => ['admin'], $E2 => ['enseignant']]);
    $inactif = creerUser($c, 'inactif@exemple.test', 'admin', 1, [1 => ['admin']], [], 0);
    $compteApresDecor = empreinteComptes($c);

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('1. Connexion globale : établissement 1 (témoin) et établissement 2');
    $s = nouvelleSession($c);
    $r = login($c, $s, 'admin.un@exemple.test', $MDP);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === 1 && $r['user']['role'] === 'admin' && str_ends_with((string)$r['location'], '/dashboard'), 'témoin : admin de l\'établissement 1 connecté (rôle admin, établissement 1)', json_encode([$r['user'] ?? null, $r['location'] ?? null]));
    $adminPerms = $r['user']['permissions'] ?? [];
    $s = nouvelleSession($c);
    $r = login($c, $s, 'admin.deux@exemple.test', $MDP);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === $E2 && $r['user']['role'] === 'admin', 'admin de l\'établissement 2 : CONNECTÉ (le défaut corrigé), établissement 2', json_encode([$r['user'] ?? null, messages($r)]));
    $page = req($c, $s, 'GET', '/dashboard');
    check($page['status'] === 200 && $page['connecte'] === true, 'l\'établissement 2 accède à son tableau de bord (session valide, contexte établissement 2)', (string)($page['status'] ?? '?'));
    $der = (string)$pdo->query("SELECT derniere_connexion FROM users WHERE id = $admin2")->fetchColumn();
    check($der !== '' && $der !== '0000-00-00 00:00:00', 'dernière connexion enregistrée pour l\'utilisateur de l\'établissement 2 (plus de filtre sur l\'établissement par défaut)');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('2. Réponse neutre');
    $ipN = $c->ip();
    $sA = nouvelleSession($c); $sB = nouvelleSession($c);
    $rA = login($c, $sA, 'inconnu@exemple.test', $MDP, $ipN);
    $rB = login($c, $sB, 'admin.un@exemple.test', 'mauvais-mdp-123', $ipN);
    check($rA['connecte'] === false && $rB['connecte'] === false, 'e-mail inconnu et mauvais mot de passe : aucune session');
    check($rA['location'] === $rB['location'] && str_ends_with((string)$rA['location'], '/login'), 'même redirection', json_encode([$rA['location'], $rB['location']]));
    check(messages($rA) === messages($rB) && messages($rA) === 'Email ou mot de passe incorrect.', 'même message neutre', messages($rA) . ' / ' . messages($rB));
    $rC = login($c, nouvelleSession($c), 'admin.un@exemple.test', 'mauvais-mdp-123', $ipN);
    check(messages($rC) === messages($rA), 'le message ne dépend pas de l\'existence du compte');
    $t0 = microtime(true); $auth->authenticate('inconnu@exemple.test', $MDP); $dInconnu = microtime(true) - $t0;
    $t0 = microtime(true); $auth->authenticate('admin.un@exemple.test', 'faux'); $dFaux = microtime(true) - $t0;
    check($dInconnu > 0.01 && $dFaux > 0.01 && max($dInconnu, $dFaux) / min($dInconnu, $dFaux) < 4, 'e-mail inconnu : un hachage factice est vérifié (durées du même ordre de grandeur)', sprintf('inconnu=%.0f ms, faux=%.0f ms', $dInconnu * 1000, $dFaux * 1000));
    check($auth->authenticate('admin.un@exemple.test', $MDP) !== null && $auth->authenticate('admin.un@exemple.test', 'x') === null && $auth->authenticate('inconnu@exemple.test', 'x') === null, 'authenticate() : bon mot de passe accepté, faux et inconnu refusés');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('3. Compte désactivé et limitation de débit');
    $r = login($c, nouvelleSession($c), 'inactif@exemple.test', $MDP);
    check($r['connecte'] === false && str_contains(messages($r), 'désactivé'), 'compte désactivé : refusé avec le message habituel (après mot de passe correct)', messages($r));
    $ipT = $c->ip(); $sT = nouvelleSession($c);
    for ($i = 0; $i < 5; $i++) { login($c, $sT, 'admin.un@exemple.test', 'faux-' . $i, $ipT); }
    $r = login($c, $sT, 'admin.un@exemple.test', $MDP, $ipT);
    check($r['connecte'] === false && str_contains(messages($r), 'Trop de tentatives'), 'limitation de débit inchangée : 5 échecs pour ce compte depuis cette adresse = blocage, même avec le bon mot de passe', messages($r));
    $r = login($c, nouvelleSession($c), 'admin.un@exemple.test', $MDP, $c->ip());
    check($r['connecte'] === true, 'une autre adresse n\'est pas bloquée');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('4. Sélecteur multi-établissements');
    $s = nouvelleSession($c);
    $r = login($c, $s, 'multi@exemple.test', $MDP);
    check($r['connecte'] === false && $r['attente_choix'] === true && str_ends_with((string)$r['location'], '/choisir-etablissement'), 'plusieurs établissements : AUCUNE session scolaire avant le choix, état d\'attente ouvert', json_encode([$r['connecte'], $r['attente_choix'], $r['location']]));
    $sess = json_decode((string)file_get_contents($s), true);
    check(!isset($sess['_auth_user']) && isset($sess['_pending_auth_user_id']) && isset($sess['_pending_auth_at']), 'la session ne contient que l\'identifiant en attente et son horodatage (pas de _auth_user)');
    $page = req($c, $s, 'GET', '/choisir-etablissement');
    check($page['status'] === 200 && $page['connecte'] === false && str_contains((string)$page['html'], 'name="etablissement_id"'), 'page de choix affichée (HTTP 200) sans session scolaire, avec le formulaire de choix', (string)$page['status']);
    check(str_contains((string)$page['html'], 'École ecole-deux') && str_contains((string)$page['html'], 'EduNova'), 'la page de choix présente les deux établissements accessibles');
    $r = req($c, $s, 'GET', '/dashboard');
    check($r['connecte'] === false && str_contains((string)($r['location'] ?? ''), '/login'), 'avant le choix, une page protégée renvoie à la connexion');
    // choix non autorisé
    $r = req($c, $s, 'POST', '/choisir-etablissement', ['etablissement_id' => $E5]);
    check($r['connecte'] === false && $r['attente_choix'] === true && str_contains(messages($r), 'invalide'), 'établissement SANS appartenance : refusé, toujours en attente', messages($r));
    $r = req($c, $s, 'POST', '/choisir-etablissement', ['etablissement_id' => 99999]);
    check($r['connecte'] === false && str_contains(messages($r), 'invalide'), 'identifiant inexistant : refusé');
    $r = req($c, $s, 'POST', '/choisir-etablissement', ['etablissement_id' => $E2]);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === $E2 && $r['user']['role'] === 'enseignant' && $r['attente_choix'] === false, 'choix valide : session ouverte avec le rôle PROPRE à l\'établissement choisi (enseignant)', json_encode($r['user'] ?? null));
    $permsEns = $r['user']['permissions'] ?? [];
    $s = nouvelleSession($c);
    login($c, $s, 'multi@exemple.test', $MDP);
    $r = req($c, $s, 'POST', '/choisir-etablissement', ['etablissement_id' => 1]);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === 1 && $r['user']['role'] === 'admin', 'même utilisateur, établissement 1 : rôle admin (aucun cumul avec le rôle de l\'établissement 2)');
    check(count(array_diff($r['user']['permissions'], $permsEns)) > 0 && $permsEns !== [], 'permissions : celles de l\'admin dépassent celles de l\'enseignant, les deux jeux sont distincts (jamais cumulés)');
    check(!in_array('users.create', $permsEns, true) && in_array('users.create', $r['user']['permissions'], true), 'permission administrateur (users.create) : absente dans l\'établissement où l\'utilisateur est enseignant, présente là où il est admin');
    // établissement bloqué dans le choix
    $m3 = creerUser($c, 'multi3@exemple.test', 'admin', 1, [1 => ['admin'], $E2 => ['enseignant'], $E3 => ['admin']]);
    $s = nouvelleSession($c);
    $r = login($c, $s, 'multi3@exemple.test', $MDP);
    check($r['attente_choix'] === true, 'trois établissements dont un suspendu : sélecteur affiché (deux accessibles)');
    $r = req($c, $s, 'POST', '/choisir-etablissement', ['etablissement_id' => $E3]);
    check($r['connecte'] === false && $r['attente_choix'] === true && str_contains(messages($r), 'suspendu'), 'choix d\'un établissement suspendu : refusé avec la raison précise', messages($r));
    check(count(audits($c, 'auth.login_denied')) >= 1, 'refus journalisé (auth.login_denied)');
    // expiration de l'état d'attente
    $s = nouvelleSession($c);
    login($c, $s, 'multi@exemple.test', $MDP);
    $sess = json_decode((string)file_get_contents($s), true); $sess['_pending_auth_at'] = time() - 601; file_put_contents($s, json_encode($sess));
    $r = req($c, $s, 'POST', '/choisir-etablissement', ['etablissement_id' => 1]);
    check($r['connecte'] === false && $r['attente_choix'] === false && str_contains(messages($r), 'expiré'), 'état d\'attente de plus de 10 minutes : choix refusé, état purgé', messages($r));
    $s = nouvelleSession($c);
    login($c, $s, 'multi@exemple.test', $MDP);
    $sess = json_decode((string)file_get_contents($s), true); $sess['_pending_auth_at'] = time() - 601; file_put_contents($s, json_encode($sess));
    $r = req($c, $s, 'GET', '/choisir-etablissement');
    check($r['attente_choix'] === false && str_contains((string)($r['location'] ?? ''), '/login'), 'page de choix après expiration : renvoi à la connexion');
    $s = nouvelleSession($c);
    login($c, $s, 'multi@exemple.test', $MDP);
    $sess = json_decode((string)file_get_contents($s), true); unset($sess['_pending_auth_at']); file_put_contents($s, json_encode($sess));
    $r = req($c, $s, 'POST', '/choisir-etablissement', ['etablissement_id' => 1]);
    check($r['connecte'] === false, 'état d\'attente sans horodatage : considéré comme expiré');
    $s = nouvelleSession($c);
    login($c, $s, 'multi@exemple.test', $MDP);
    $sess = json_decode((string)file_get_contents($s), true); $sess['_pending_auth_at'] = time() - 599; file_put_contents($s, json_encode($sess));
    $r = req($c, $s, 'POST', '/choisir-etablissement', ['etablissement_id' => 1]);
    check($r['connecte'] === true, 'état d\'attente de 599 s : encore valide');
    // révoquée entre l'authentification et le choix
    $s = nouvelleSession($c);
    login($c, $s, 'multi@exemple.test', $MDP);
    $pdo->exec("UPDATE user_etablissements SET left_at = NOW() WHERE user_id = $multi AND etablissement_id = $E2");
    $r = req($c, $s, 'POST', '/choisir-etablissement', ['etablissement_id' => $E2]);
    check($r['connecte'] === false, 'appartenance quittée ENTRE la connexion et le choix : revérifiée côté serveur, refusée');
    $pdo->exec("UPDATE user_etablissements SET left_at = NULL WHERE user_id = $multi AND etablissement_id = $E2");

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('5. Appartenances et établissements bloqués');
    creerUser($c, 'ancien@exemple.test', 'admin', 1, [], [1]);   // ancien membre de l'établissement 1 (users.etablissement_id = 1 !)
    $r = login($c, nouvelleSession($c), 'ancien@exemple.test', $MDP);
    check($r['connecte'] === false && str_contains(messages($r), 'Aucun établissement'), 'appartenance QUITTÉE : refusée, sans repli sur users.etablissement_id', messages($r));
    creerUser($c, 'parent.historique@exemple.test', 'parent', 1);   // aucun user_etablissements : compte historique
    $r = login($c, nouvelleSession($c), 'parent.historique@exemple.test', $MDP);
    check($r['connecte'] === true && $r['user']['role'] === 'parent' && $r['user']['etablissement_id'] === 1, 'compte historique SANS aucune appartenance : repli (users.etablissement_id, users.role)', json_encode($r['user'] ?? null));
    creerUser($c, 'historique.suspendu@exemple.test', 'parent', $E3);
    $r = login($c, nouvelleSession($c), 'historique.suspendu@exemple.test', $MDP);
    check($r['connecte'] === false && str_contains(messages($r), 'suspendu'), 'repli historique vers un établissement suspendu : refusé', messages($r));
    $Edel = creerEtab($c, 'ecole-supprimee');
    $pdo->exec("UPDATE etablissements SET deleted_at = NOW() WHERE id = $Edel");
    creerUser($c, 'historique.supprime@exemple.test', 'parent', $Edel);
    $r = login($c, nouvelleSession($c), 'historique.supprime@exemple.test', $MDP);
    check($r['connecte'] === false && str_contains(messages($r), 'Aucun établissement'), 'repli vers un établissement supprimé : refusé, message précis, aucune session', messages($r));
    foreach ([[$E3, 'suspendu'], [$E4, 'résilié'], [$E5, 'archivé'], [$E6, 'expiré']] as [$etab, $mot]) {
        creerUser($c, "bloque$etab@exemple.test", 'admin', $etab, [$etab => ['admin']]);
        $r = login($c, nouvelleSession($c), "bloque$etab@exemple.test", $MDP);
        check($r['connecte'] === false && $r['attente_choix'] === false && str_contains(messages($r), $mot), "établissement « $mot » : accès refusé, message précis après authentification", messages($r));
        $r = login($c, nouvelleSession($c), "bloque$etab@exemple.test", 'mauvais-mdp-xyz');
        check(messages($r) === 'Email ou mot de passe incorrect.', "établissement « $mot » : mauvais mot de passe = réponse NEUTRE (aucune information sur l'établissement)");
    }
    $r = login($c, nouvelleSession($c), 'multi3@exemple.test', $MDP);   // E1 admin + E2 enseignant + E3 suspendue
    $cl = $auth->classer($auth->lookupByEmail('multi3@exemple.test'));
    check(count($cl['accessibles']) === 2 && count($cl['bloques']) === 1 && $cl['bloques'][0]['raison'] === 'suspendu' && $cl['legacy'] === false, 'classer() : 2 accessibles, 1 bloqué avec sa raison');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('6. Rôles par établissement');
    $roleManquant = creerUser($c, 'sansrole@exemple.test', 'admin', $E2, [$E2 => []]);   // appartenance SANS rôle ; users.role global = admin
    $s = nouvelleSession($c);
    $r = login($c, $s, 'sansrole@exemple.test', $MDP);
    check($r['connecte'] === false && $r['attente_choix'] === false && str_contains(messages($r), 'Aucun rôle'), 'appartenance sans rôle propre : REFUSÉE, jamais de repli sur le rôle global users.role = admin', messages($r) . ' ' . json_encode($r['user'] ?? null));
    check(count(audits($c, 'auth.role_missing')) >= 1, 'anomalie journalisée (auth.role_missing)');
    $ligne = audits($c, 'auth.role_missing')[0] ?? [];
    check((int)($ligne['entity_id'] ?? 0) === $roleManquant && ($ligne['entity_type'] ?? '') === 'user' && (int)($ligne['etablissement_id'] ?? 0) === $E2 && $ligne['operator_id'] === null, 'journal : utilisateur en entity_id (jamais en operator_id), établissement concerné renseigné');
    // appartenance sans rôle parmi d'autres : visible comme bloquée, non sélectionnable
    $mixte = creerUser($c, 'mixte@exemple.test', 'admin', 1, [1 => ['admin'], $E2 => []]);
    $r = login($c, nouvelleSession($c), 'mixte@exemple.test', $MDP);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === 1, 'un établissement accessible + un sans rôle : ouverture directe de l\'accessible');
    $cl = $auth->classer($auth->lookupByEmail('mixte@exemple.test'));
    check(count($cl['bloques']) === 1 && $cl['bloques'][0]['raison'] === 'role_manquant' && (int)$cl['bloques'][0]['id'] === $E2, 'l\'établissement sans rôle est classé bloqué (role_manquant)');
    // rôles multiples
    $m1 = creerUser($c, 'roles1@exemple.test', 'parent', 1, [1 => ['parent', 'enseignant']]);   // insertion : parent puis enseignant
    $m2 = creerUser($c, 'roles2@exemple.test', 'parent', 1, [1 => ['enseignant', 'parent']]);   // ordre inverse
    $r1 = login($c, nouvelleSession($c), 'roles1@exemple.test', $MDP);
    $r2 = login($c, nouvelleSession($c), 'roles2@exemple.test', $MDP);
    check($r1['user']['role'] === 'enseignant' && $r2['user']['role'] === 'enseignant', 'rôles multiples : rôle affiché selon la priorité fixe (enseignant avant parent), quel que soit l\'ordre d\'insertion', json_encode([$r1['user']['role'] ?? null, $r2['user']['role'] ?? null]));
    $tc = \Core\Tenant\TenantAuthContext::make();
    $union = array_values(array_unique(array_merge($tc->permissions($m1, 1) ?: [], [])));
    sort($union); $p1 = $r1['user']['permissions']; $p2 = $r2['user']['permissions'];
    $v1 = array_values(array_unique(array_map('strval', $p1))); sort($v1); $v2 = array_values(array_unique(array_map('strval', $p2))); sort($v2);
    check($v1 === $v2 && $v1 !== [], 'permissions identiques pour les deux ordres d\'insertion');
    check(count(array_diff($union, $p1)) === 0, 'permissions = réunion des permissions de TOUS les rôles de l\'établissement');
    check(count(audits($c, 'auth.multiple_roles')) >= 2, 'rôles multiples journalisés (auth.multiple_roles)');
    check(GlobalAuthService::trierRoles(['eleve', 'zebre', 'admin', 'parent', 'abc']) === ['admin', 'parent', 'eleve', 'abc', 'zebre'], 'trierRoles() : priorité fixe, codes inconnus ensuite par ordre alphabétique');
    check(GlobalAuthService::trierRoles(['parent', 'admin', 'parent']) === ['admin', 'parent'], 'trierRoles() : doublons supprimés');
    $apres = (int)$pdo->query("SELECT COUNT(*) FROM user_roles_etab WHERE user_id IN ($m1, $m2)")->fetchColumn();
    check($apres === 4, 'aucun rôle ajouté ni supprimé automatiquement');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('7. Rafraîchissement de session');
    $rr = creerUser($c, 'refresh@exemple.test', 'admin', 1, [1 => ['enseignant']]);
    $s = nouvelleSession($c);
    $r = login($c, $s, 'refresh@exemple.test', $MDP);
    check($r['connecte'] === true && $r['user']['role'] === 'enseignant', 'préparation : connecté enseignant');
    // rôle modifié => mis à jour
    $pdo->exec("UPDATE user_roles_etab SET role_id = " . idRole($c, 'secretaire') . " WHERE user_id = $rr AND etablissement_id = 1");
    $sess = json_decode((string)file_get_contents($s), true); $sess['_auth_refreshed_at'] = 1; file_put_contents($s, json_encode($sess));
    $r = req($c, $s, 'GET', '/dashboard');
    check($r['connecte'] === true && $r['user']['role'] === 'secretaire', 'rôle modifié en base : la session prend le nouveau rôle au rafraîchissement', json_encode($r['user'] ?? null));
    // rôle retiré => session invalidée
    $pdo->exec("DELETE FROM user_roles_etab WHERE user_id = $rr AND etablissement_id = 1");
    $sess = json_decode((string)file_get_contents($s), true); $sess['_auth_refreshed_at'] = 1; file_put_contents($s, json_encode($sess));
    $r = req($c, $s, 'GET', '/dashboard');
    check($r['connecte'] === false && str_contains((string)($r['location'] ?? ''), '/login'), 'rôle RETIRÉ : session invalidée au rafraîchissement suivant (aucun repli sur users.role = admin)', json_encode([$r['connecte'], $r['location']]));
    $r = req($c, $s, 'GET', '/dashboard');
    check($r['connecte'] === false, 'et elle reste invalide aux requêtes suivantes');
    check(count(audits($c, 'auth.role_missing')) >= 2, 'retrait de rôle en cours de session journalisé');
    // rafraîchissement non demandé : aucune relecture avant l'intervalle
    $rr2 = creerUser($c, 'refresh2@exemple.test', 'admin', 1, [1 => ['enseignant']]);
    $s = nouvelleSession($c);
    login($c, $s, 'refresh2@exemple.test', $MDP);
    $r = req($c, $s, 'GET', '/dashboard');   // première requête : relecture des droits (aucune horodatée), puis horodatage
    check($r['connecte'] === true, 'première requête après la connexion : droits relus, session valide');
    $pdo->exec("DELETE FROM user_roles_etab WHERE user_id = $rr2");
    $r = req($c, $s, 'GET', '/dashboard');
    check($r['connecte'] === true, 'dans l\'intervalle de rafraîchissement (60 s), la session n\'est pas relue (comportement existant conservé)');
    $sess = json_decode((string)file_get_contents($s), true); $sess['_auth_refreshed_at'] = time() - 61; file_put_contents($s, json_encode($sess));
    $r = req($c, $s, 'GET', '/dashboard');
    check($r['connecte'] === false, 'au-delà de l\'intervalle, la relecture constate le rôle retiré et invalide la session');
    // compte historique : rafraîchissement par users.role
    $s = nouvelleSession($c);
    login($c, $s, 'parent.historique@exemple.test', $MDP);
    $sess = json_decode((string)file_get_contents($s), true); $sess['_auth_refreshed_at'] = 1; file_put_contents($s, json_encode($sess));
    $r = req($c, $s, 'GET', '/dashboard');
    check($r['connecte'] === true && $r['user']['role'] === 'parent', 'compte historique : le rafraîchissement conserve le repli (rôle users.role), session valide');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('8. Permissions RBAC V2 globales');
    $pdo->exec("INSERT INTO etab_roles (code, etablissement_id, nom, is_system) VALUES ('role_vide', NULL, 'Rôle sans permission', 0)");
    $idVide = (int)$pdo->query("SELECT id FROM etab_roles WHERE code = 'role_vide'")->fetchColumn();
    $v = creerUser($c, 'rbacglobal@exemple.test', 'parent', $E2, [$E2 => []]);
    $pdo->exec("INSERT INTO user_roles_etab (user_id, etablissement_id, role_id) VALUES ($v, $E2, $idVide)");
    $rbacRoleId = (int)$pdo->query('SELECT role_id FROM rbac_user_roles LIMIT 1')->fetchColumn();
    $pdo->exec("INSERT INTO rbac_user_roles (user_id, role_id, actif) VALUES ($v, $rbacRoleId, 1)");   // permissions GLOBALES (administrateur V2)
    $r = login($c, nouvelleSession($c), 'rbacglobal@exemple.test', $MDP);
    check($r['connecte'] === true && $r['user']['role'] === 'role_vide', 'préparation : rôle sans permission dans l\'établissement 2 + droits RBAC globaux');
    check(!in_array('users.create', $r['user']['permissions'], true) && count($r['user']['permissions']) === 0, 'mode strict : AUCUNE permission héritée de rbac_user_roles', json_encode(array_slice($r['user']['permissions'] ?? [], 0, 5)));
    $um = new \App\Models\UserModel();
    $vObj = $auth->lookupById($v);
    $non = $um->getPermissions('role_vide', $v, $E2);
    $strict = $um->getPermissions('role_vide', $v, $E2, true);
    check(count($non) > 0 && count($strict) === 0, 'getPermissions : non strict = comportement historique (RBAC global appliqué), strict = aucune permission globale');
    $leg = creerUser($c, 'rbaclegacy@exemple.test', 'parent', 1);
    $pdo->exec("INSERT INTO rbac_user_roles (user_id, role_id, actif) VALUES ($leg, $rbacRoleId, 1)");
    $rl = login($c, nouvelleSession($c), 'rbaclegacy@exemple.test', $MDP);
    check($rl['connecte'] === true && in_array('users.create', $rl['user']['permissions'], true), 'compte historique sans appartenance : le comportement historique (RBAC V2) est conservé');
    $normal = $um->getPermissions('admin', $admin1, 1);
    check($normal === $um->getPermissions('admin', $admin1, 1, true) || count(array_diff($normal, $um->getPermissions('admin', $admin1, 1, true))) === 0, 'utilisateur avec rôle propre : mode strict et non strict donnent les mêmes permissions');
    check((string)(new ReflectionMethod($um, 'getPermissions'))->getParameters()[3]->getName() === 'strictTenant' && (new ReflectionMethod($um, 'getPermissions'))->getParameters()[3]->getDefaultValue() === false, 'getPermissions : paramètre $strictTenant, faux par défaut (autres appelants inchangés)');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('9. Aucune création ni élévation de privilèges');
    $avant = empreinteComptes($c);
    for ($i = 0; $i < 3; $i++) {
        login($c, nouvelleSession($c), 'admin.un@exemple.test', 'faux', $c->ip());
        login($c, nouvelleSession($c), 'inconnu@exemple.test', 'faux', $c->ip());
        login($c, nouvelleSession($c), 'sansrole@exemple.test', $MDP, $c->ip());
        $s = nouvelleSession($c);
        login($c, $s, 'multi@exemple.test', $MDP, $c->ip());
        req($c, $s, 'POST', '/choisir-etablissement', ['etablissement_id' => $E5]);
        req($c, $s, 'POST', '/choisir-etablissement', ['etablissement_id' => 1]);
        login($c, nouvelleSession($c), 'admin.deux@exemple.test', $MDP, $c->ip());
    }
    check(empreinteComptes($c) === $avant, 'connexions, échecs, refus et choix : aucune ligne users / user_etablissements / user_roles_etab / etab_roles / rbac_user_roles créée ou modifiée (hors dernière connexion)');

    check($compteApresDecor !== $avantComptes, 'témoin : l\'empreinte détecte bien une modification (le décor de test a ajouté des comptes)');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('10. Journal global');
    $succes = audits($c, 'auth.global_login_success');
    check(count($succes) >= 3, 'auth.global_login_success écrit');
    $l = $succes[0];
    check($l['actor_type'] === 'school_user' && $l['operator_id'] === null && $l['entity_type'] === 'user' && (int)$l['entity_id'] > 0 && $l['actor_email'] !== null, 'acteur school_user, identifiant dans entity_id, operator_id NULL', json_encode($l));
    check(count(audits($c, 'auth.tenant_selected')) >= 2, 'auth.tenant_selected écrit à chaque choix');
    $echecs = audits($c, 'auth.global_login_failure');
    check(count($echecs) >= 3 && $echecs[0]['actor_type'] === 'anonymous' && $echecs[0]['resultat'] === 'failure' && $echecs[0]['entity_id'] === null, 'auth.global_login_failure : acteur anonyme, résultat failure, aucune cible (e-mail inconnu ou faux : même enregistrement)');
    $tout = (string)$pdo->query("SELECT GROUP_CONCAT(COALESCE(ancienne_valeur,''), COALESCE(nouvelle_valeur,''), COALESCE(user_agent,'') SEPARATOR ' ') FROM platform_audit_logs WHERE action LIKE 'auth.%'")->fetchColumn();
    check(!str_contains($tout, $MDP) && !str_contains($tout, 'faux-') && !str_contains($tout, 'mauvais-mdp') && !str_contains($tout, '$2y$') && stripos($tout, 'password') === false, 'aucun mot de passe, hachage ni secret dans le journal');
    $e = null; try { $pdo->exec("UPDATE platform_audit_logs SET action = 'x' WHERE id = " . (int)$succes[0]['id']); } catch (PDOException $ex) { $e = $ex; }
    check($e !== null && str_contains($e->getMessage(), 'ajout seul'), 'le journal reste en ajout seul (déclencheur de modification actif)');
    $e = null; try { $pdo->exec('DELETE FROM platform_audit_logs WHERE id = ' . (int)$succes[0]['id']); } catch (PDOException $ex) { $e = $ex; }
    check($e !== null && str_contains($e->getMessage(), 'ajout seul'), 'et celui de suppression');
    $ipFlood = $c->ip(); $avantF = count(audits($c, 'auth.global_login_failure'));
    for ($i = 0; $i < 34; $i++) { login($c, nouvelleSession($c), "inconnu$i@exemple.test", 'x', $ipFlood); }
    check(count(audits($c, 'auth.global_login_failure')) - $avantF <= 30, 'un déluge d\'échecs depuis une adresse ne sature pas le journal (30 lignes au plus par fenêtre)', (string)(count(audits($c, 'auth.global_login_failure')) - $avantF));
    $pdoBrut = (int)$pdo->query("SELECT COUNT(*) FROM platform_audit_logs WHERE actor_type = 'operator' AND action LIKE 'auth.%'")->fetchColumn();
    check($pdoBrut === 0, 'aucun événement auth.* attribué à un opérateur de plateforme');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('12. UserModel reste limité à son établissement');
    $users = new \App\Models\UserModel();
    check($users->findByEmail('admin.deux@exemple.test') === false, 'UserModel::findByEmail (établissement par défaut) ne voit PAS l\'utilisateur de l\'établissement 2 : la recherche globale est isolée dans GlobalAuthService');
    check($users->findByEmail('admin.un@exemple.test') !== false, 'et voit toujours ceux de l\'établissement 1');
    check($auth->lookupByEmail('admin.deux@exemple.test') !== null, 'GlobalAuthService::lookupByEmail trouve l\'utilisateur de l\'établissement 2');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('11. Migration T068 et compatibilité avant T068');
    $migration = require ROOT_PATH . '/database/migrations/T068_audit_global_acteur_ecole.php';
    $type = fn(PDO $p) => (string)$p->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_audit_logs' AND COLUMN_NAME = 'actor_type'")->fetchColumn();
    check($type($pdo) === "enum('operator','system','anonymous','school_user')", 'après T068 : ENUM à quatre valeurs', $type($pdo));
    $defaut = (string)$pdo->query("SELECT COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_audit_logs' AND COLUMN_NAME = 'actor_type'")->fetchColumn();
    check($defaut === 'operator', 'défaut inchangé (operator)');
    $nb = (int)$pdo->query('SELECT COUNT(*) FROM platform_audit_logs')->fetchColumn();
    $migration['run']($pdo); $migration['run']($pdo);
    check($type($pdo) === "enum('operator','system','anonymous','school_user')" && (int)$pdo->query('SELECT COUNT(*) FROM platform_audit_logs')->fetchColumn() === $nb, 'T068 rejouée : idempotente, aucune ligne modifiée');
    $e = null; try { $migration['rollback']($pdo); } catch (RuntimeException $ex) { $e = $ex; }
    check($e !== null && str_contains($e->getMessage(), 'refusé') && $type($pdo) === "enum('operator','system','anonymous','school_user')", 'retour arrière REFUSÉ tant que des lignes utilisent school_user, rien n\'est modifié', $e ? $e->getMessage() : '');

    // Seconde copie : état « avant T068 » (journal sans ligne school_user)
    $copie2 = \Tests\Support\DbCopy::create(ROOT_PATH) ?? exit("ECHEC : seconde copie impossible\n");
    $pdo2 = $copie2->pdo(); $pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $c2 = new Ctx($pdo2, $copie2->name, $dossierTmp);
    $migration['rollback']($pdo2);
    check($type($pdo2) === "enum('operator','system','anonymous')", 'seconde copie sans ligne school_user : retour arrière appliqué, ENUM à trois valeurs');
    $n2 = $pdo2->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = 'platform_audit_logs'")->fetchColumn();
    check((int)$n2 === 2, 'les déclencheurs d\'ajout seul sont intacts après l\'ALTER');
    creerUser($c2, 'avant.t068@exemple.test', 'admin', 1, [1 => ['admin']]);
    $r = login($c2, nouvelleSession($c2), 'avant.t068@exemple.test', $MDP);
    check($r['connecte'] === true, 'AVANT T068 : la connexion fonctionne normalement');
    $ev = $pdo2->query("SELECT actor_type, actor_email, operator_id FROM platform_audit_logs WHERE action = 'auth.global_login_success' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    check($ev !== false && $ev['actor_type'] === 'anonymous' && $ev['actor_email'] === 'avant.t068@exemple.test' && $ev['operator_id'] === null, 'AVANT T068 : l\'événement est écrit avec l\'acteur « anonymous » (aucune erreur, aucun fichier de secours)', json_encode($ev));
    $migration['run']($pdo2);
    check($type($pdo2) === "enum('operator','system','anonymous','school_user')", 'T068 réappliquée sur la seconde copie');
    $r = login($c2, nouvelleSession($c2), 'avant.t068@exemple.test', $MDP);
    $ev = $pdo2->query("SELECT actor_type FROM platform_audit_logs WHERE action = 'auth.global_login_success' ORDER BY id DESC LIMIT 1")->fetchColumn();
    check($ev === 'school_user', 'APRÈS T068 : l\'acteur devient school_user');
    check(glob($dossierTmp . '/secours/*') === [] || glob($dossierTmp . '/secours/*') === false, 'aucun fichier de secours du journal global écrit : toutes les écritures d\'audit ont réussi (avant comme après T068)', implode(',', glob($dossierTmp . '/secours/*') ?: []));
} finally {
    $copie->drop();
    if ($copie2 !== null) { $copie2->drop(); }
    foreach (glob($dossierTmp . '/*') ?: [] as $f) { is_dir($f) ? array_map('unlink', glob($f . '/*') ?: []) : @unlink($f); }
}

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
