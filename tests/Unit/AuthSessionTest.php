<?php
/**
 * Tests — Phase 2 : changement d'établissement sécurisé et opérations sur son PROPRE compte.
 *
 *  1. Bascule valide : rôle PROPRE à l'établissement visé, permissions sans cumul, audit (de / vers), message, liste de bascule
 *  2. Bascule refusée : sans appartenance, établissement suspendu / abonnement expiré / sans rôle (contexte inchangé), même établissement,
 *     CSRF absent, session expirée, visiteur
 *  3. Utilisateur rattaché à A (users.etablissement_id) connecté à B : bascule dans les DEUX sens (cas qui échouait avant la Phase 2)
 *  4. Purge du contexte de l'établissement quitté ; données visibles = celles du nouvel établissement ; déconnexion puis reconnexion
 *  5. Liste de bascule limitée aux établissements accessibles
 *  6. Profil, photo, mot de passe d'un membre inter-établissements : lecture et écritures effectives ; jamais le compte d'un autre
 *  7. Aucune création ni élévation de privilèges (comptes, appartenances, rôles inchangés par une bascule ou une mise à jour de profil)
 *  8. RBAC V2 : permissions globales jamais héritées d'un établissement à l'autre
 *
 * Travaille uniquement sur des copies jetables ecole_app_copy_* : la base vivante n'est jamais touchée.
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/AuthSessionTest.php
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
$NOUVEAU = 'Nouveau-Mdp-Solide-2027#x';
$HASH = password_hash($MDP, PASSWORD_BCRYPT);
$tmp = sys_get_temp_dir() . '/authsess_' . bin2hex(random_bytes(4));
@mkdir($tmp . '/throttle', 0775, true);
\Core\Platform\PlatformAuditService::definirDossierSecours($tmp . '/secours');

final class Ctx
{
    public function __construct(public PDO $pdo, public string $db, public string $tmp) {}
    public function ip(): string { return '10.88.' . random_int(1, 250) . '.' . random_int(1, 250); }
}

/** @return array<string,mixed> */
function req(Ctx $c, string $session, string $methode, string $uri, array $post = [], array $env = []): array
{
    $p = proc_open([PHP_BINARY, ROOT_PATH . '/tests/Support/auth_request.php', $session, $methode, '/ecole_app' . $uri, json_encode($post)],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH,
        array_merge(getenv(), ['REQUEST_AS_DB' => $c->db, 'AUTH_THROTTLE_DIR' => $c->tmp . '/throttle', 'AUTH_REQ_IP' => $c->ip(), 'AUTH_REQ_AUDIT_DIR' => $c->tmp . '/secours'], $env));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    return preg_match('/^RESULT:(.*)$/m', $out, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0, 'brut' => substr($out, 0, 400)];
}
function nouvelleSession(Ctx $c): string { return $c->tmp . '/sess_' . bin2hex(random_bytes(4)) . '.json'; }
function lireSession(string $f): array { return json_decode((string)file_get_contents($f), true) ?: []; }
function ecrireSession(string $f, array $s): void { file_put_contents($f, json_encode($s)); }
/** Connexion jusqu'à une session ouverte sur $etab (choix automatique si plusieurs établissements). */
function ouvrir(Ctx $c, string $email, ?int $etab = null): string
{
    global $MDP;
    $s = nouvelleSession($c);
    $r = req($c, $s, 'POST', '/login', ['email' => $email, 'password' => $MDP]);
    if (($r['attente_choix'] ?? false) && $etab !== null) { $r = req($c, $s, 'POST', '/choisir-etablissement', ['etablissement_id' => $etab]); }
    return $s;
}
function etat(Ctx $c, string $session): array { return req($c, $session, 'GET', '/dashboard'); }
function messages(array $r, string $cle = 'errors'): string
{
    $e = $r['flash'][$cle] ?? [];
    $out = [];
    array_walk_recursive($e, function ($v) use (&$out) { $out[] = (string)$v; });
    return implode(' | ', $out);
}
function flashTexte(array $r, string $cle): string { return is_string($r['flash'][$cle] ?? null) ? $r['flash'][$cle] : messages($r, $cle); }

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
/** $appartenances : [etab_id => [codes de rôle]] ; $etabOrigine : users.etablissement_id (établissement « d'origine » de la personne). */
function creerUser(Ctx $c, string $email, int $etabOrigine, array $appartenances, string $roleGlobal = 'admin'): int
{
    global $HASH;
    $c->pdo->prepare('INSERT INTO users (nom, prenom, email, password, role, actif, etablissement_id) VALUES (?, ?, ?, ?, ?, 1, ?)')
        ->execute(['Nom' . explode('@', $email)[0], 'Prenom', $email, $HASH, $roleGlobal, $etabOrigine]);
    $id = (int)$c->pdo->lastInsertId();
    $prim = 1;
    foreach ($appartenances as $etab => $roles) {
        $c->pdo->prepare('INSERT INTO user_etablissements (user_id, etablissement_id, is_primary) VALUES (?, ?, ?)')->execute([$id, $etab, $prim]);
        $prim = 0;
        foreach ($roles as $code) {
            $c->pdo->prepare('INSERT INTO user_roles_etab (user_id, etablissement_id, role_id) VALUES (?, ?, ?)')->execute([$id, $etab, idRole($c, $code)]);
        }
    }
    return $id;
}
function audits(Ctx $c, string $action): array
{
    $s = $c->pdo->prepare('SELECT * FROM platform_audit_logs WHERE action = ? ORDER BY id'); $s->execute([$action]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
function empreintePrivileges(Ctx $c): string
{
    $q = fn(string $sql) => $c->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return md5(json_encode([$q('SELECT id, role, actif, etablissement_id FROM users ORDER BY id'), $q('SELECT * FROM user_etablissements ORDER BY id'),
        $q('SELECT * FROM user_roles_etab ORDER BY id'), $q('SELECT * FROM etab_roles ORDER BY id'), $q('SELECT * FROM rbac_user_roles ORDER BY id')]));
}

$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible\n");
try {
    $nom = $copie->connection()['db'];
    $pdo = \Core\Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (!preg_match('/^ecole_app_copy_[a-f0-9]+$/', (string)$pdo->query('SELECT DATABASE()')->fetchColumn())) { exit("REFUS : base de travail non jetable\n"); }
    $c = new Ctx($pdo, $nom, $tmp);

    $E2 = creerEtab($c, 'ecole-deux');
    $E3 = creerEtab($c, 'ecole-trois-suspendue', 'suspended');
    $E5 = creerEtab($c, 'ecole-cinq');          // établissement SANS appartenance pour la plupart des tests
    $E6 = creerEtab($c, 'ecole-six-expiree');
    $E7 = creerEtab($c, 'ecole-sept-sans-role');
    $plan = (int)$pdo->query('SELECT id FROM platform_plans ORDER BY id LIMIT 1')->fetchColumn();
    $pdo->exec("INSERT INTO platform_subscriptions (etablissement_id, plan_id, statut, cycle, date_debut, date_fin, devise) VALUES ($E6, $plan, 'expired', 'monthly', '2020-01-01', '2020-01-31', 'XOF')");

    // Utilisateurs
    $X  = creerUser($c, 'x.multi@exemple.test', 1, [1 => ['admin'], $E2 => ['enseignant'], $E3 => ['admin'], $E6 => ['admin'], $E7 => []]);   // origine 1
    $Y  = creerUser($c, 'y.origine2@exemple.test', $E2, [1 => ['admin'], $E2 => ['admin']]);                                                    // origine = établissement 2
    $Z  = creerUser($c, 'z.deux@exemple.test', 1, [1 => ['admin'], $E2 => ['admin'], $E5 => ['comptable']]);
    $AUTRE = creerUser($c, 'autre.compte@exemple.test', 1, [1 => ['admin']]);
    // Données visibles par établissement (pour la page des élèves)
    $pdo->exec("INSERT INTO classes (etablissement_id, nom, niveau, annee_scolaire) VALUES ($E2, 'CLASSE-E2-SESS', '6eme', '2025-2026')");
    $C2 = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO eleves (etablissement_id, matricule, nom, prenom, date_naissance, sexe, classe_id, actif) VALUES ($E2, 'E2S-1', 'ELEVEDEUXSESS', 'Zoe', '2012-01-01', 'F', $C2, 1)");
    $nomEleve1 = (string)$pdo->query('SELECT nom FROM eleves WHERE etablissement_id = 1 AND actif = 1 ORDER BY id LIMIT 1')->fetchColumn();

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('1. Bascule valide');
    $s = ouvrir($c, 'x.multi@exemple.test', 1);
    $r = etat($c, $s);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === 1 && $r['user']['role'] === 'admin', 'préparation : connecté à l\'établissement 1 en admin');
    $permsAdmin = $r['user']['permissions'];
    $r = req($c, $s, 'POST', '/auth/switch-school', ['school_id' => $E2]);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === $E2 && $r['user']['role'] === 'enseignant', 'bascule vers l\'établissement 2 : rôle PROPRE à cet établissement (enseignant)', json_encode([$r['user'] ?? null, $r['location'] ?? null]));
    $permsEns = $r['user']['permissions'];
    check($permsEns !== [] && count(array_diff($permsAdmin, $permsEns)) > 0 && !in_array('users.create', $permsEns, true), 'permissions recalculées pour l\'établissement visé : aucun cumul avec celles de l\'établissement quitté');
    check(str_contains(flashTexte($r, 'success'), 'École ecole-deux'), 'message de confirmation : « Vous êtes maintenant connecté à … »', flashTexte($r, 'success'));
    $au = audits($c, 'auth.tenant_switch');
    $det = json_decode((string)(end($au)['nouvelle_valeur'] ?? '{}'), true);
    check(count($au) === 1 && (int)($det['de'] ?? 0) === 1 && (int)($det['vers'] ?? 0) === $E2 && end($au)['actor_type'] === 'school_user', 'audit auth.tenant_switch : établissement quitté (de) et visé (vers), acteur school_user');
    $r = etat($c, $s);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === $E2 && $r['status'] === 200, 'la session reste valide sur l\'établissement 2 aux requêtes suivantes');
    $r = req($c, $s, 'POST', '/auth/switch-school', ['school_id' => 1]);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === 1 && $r['user']['role'] === 'admin' && in_array('users.create', $r['user']['permissions'], true), 'retour à l\'établissement 1 : rôle admin et permissions administrateur rétablis');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('2. Bascule refusée');
    $avant = empreintePrivileges($c);
    $r = req($c, $s, 'POST', '/auth/switch-school', ['school_id' => $E5]);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === 1 && str_contains(flashTexte($r, 'error'), 'n\'appartenez pas'), 'établissement SANS appartenance : refusé, contexte inchangé (établissement 1)', flashTexte($r, 'error'));
    check(count(audits($c, 'auth.login_denied')) >= 1, 'refus journalisé (auth.login_denied, étape bascule)');
    $r = req($c, $s, 'POST', '/auth/switch-school', ['school_id' => 99999]);
    check($r['user']['etablissement_id'] === 1 && str_contains(flashTexte($r, 'error'), 'n\'appartenez pas'), 'identifiant inexistant : refusé, contexte inchangé');
    $r = req($c, $s, 'POST', '/auth/switch-school', ['school_id' => $E3]);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === 1 && str_contains(flashTexte($r, 'error'), 'suspendu'), 'établissement SUSPENDU : refusé avec la raison, établissement courant conservé', flashTexte($r, 'error'));
    $r = req($c, $s, 'POST', '/auth/switch-school', ['school_id' => $E6]);
    check($r['user']['etablissement_id'] === 1 && str_contains(flashTexte($r, 'error'), 'expiré'), 'abonnement EXPIRÉ : refusé avec la raison', flashTexte($r, 'error'));
    $r = req($c, $s, 'POST', '/auth/switch-school', ['school_id' => $E7]);
    check($r['user']['etablissement_id'] === 1 && str_contains(flashTexte($r, 'error'), 'Aucun rôle'), 'appartenance SANS rôle propre : refusée', flashTexte($r, 'error'));
    $nAudit = count(audits($c, 'auth.tenant_switch'));
    $r = req($c, $s, 'POST', '/auth/switch-school', ['school_id' => 1]);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === 1 && count(audits($c, 'auth.tenant_switch')) === $nAudit, 'même établissement : aucune action (aucun événement de bascule)');
    $r = req($c, $s, 'POST', '/auth/switch-school', ['school_id' => $E2], ['REQUEST_AS_NO_CSRF' => '1']);
    $r2 = etat($c, $s);
    check($r2['connecte'] === true && $r2['user']['etablissement_id'] === 1, 'CSRF absent : bascule refusée, établissement inchangé');
    $sExp = ouvrir($c, 'x.multi@exemple.test', 1);
    $sess = lireSession($sExp); $sess['_last_activity'] = time() - 999999; ecrireSession($sExp, $sess);
    $r = req($c, $sExp, 'POST', '/auth/switch-school', ['school_id' => $E2]);
    check($r['connecte'] === false, 'session expirée (inactivité) : bascule impossible, session invalidée', json_encode($r['user'] ?? null));
    $r = req($c, nouvelleSession($c), 'POST', '/auth/switch-school', ['school_id' => $E2]);
    check($r['connecte'] === false && str_contains((string)($r['location'] ?? ''), '/login'), 'visiteur non connecté : renvoyé à la connexion');
    check(empreintePrivileges($c) === $avant, 'aucun refus ne modifie un compte, une appartenance ou un rôle');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('3. Utilisateur dont l\'établissement d\'origine est l\'établissement 2');
    $sY = ouvrir($c, 'y.origine2@exemple.test', 1);
    $r = etat($c, $sY);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === 1, 'préparation : Y (origine = établissement 2) connecté à l\'établissement 1');
    $r = req($c, $sY, 'POST', '/auth/switch-school', ['school_id' => $E2]);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === $E2, 'bascule 1 → 2 (vers son établissement d\'origine)', json_encode([$r['user'] ?? null]));
    $r = req($c, $sY, 'POST', '/auth/switch-school', ['school_id' => 1]);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === 1, 'bascule 2 → 1 : fonctionne aussi hors de l\'établissement d\'origine (échouait avant la Phase 2)', json_encode([$r['user'] ?? null, messages($r), flashTexte($r, 'error')]));
    $r = req($c, $sY, 'POST', '/auth/switch-school', ['school_id' => $E2]);
    $r = req($c, $sY, 'POST', '/auth/switch-school', ['school_id' => 1]);
    check($r['connecte'] === true && $r['user']['etablissement_id'] === 1, 'allers-retours répétés : toujours valides');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('4. Purge du contexte et données visibles');
    $sZ = ouvrir($c, 'z.deux@exemple.test', 1);
    $r = req($c, $sZ, 'GET', '/eleves');
    check($r['status'] === 200 && str_contains((string)$r['html'], $nomEleve1) && !str_contains((string)$r['html'], 'ELEVEDEUXSESS'), 'établissement 1 : la liste des élèves montre les siens et pas ceux de l\'établissement 2');
    $sess = lireSession($sZ);
    $sess['bi_config_direction'] = ['cache' => 'etab1']; $sess['__etab_slug'] = 'edunova-demo'; $sess['old_input'] = ['nom' => 'saisie-etab1']; $sess['errors'] = ['x' => ['erreur-etab1']];
    ecrireSession($sZ, $sess);
    $r = req($c, $sZ, 'POST', '/auth/switch-school', ['school_id' => $E2]);
    $cles = $r['cles_session'] ?? [];
    check($r['connecte'] === true && $r['user']['etablissement_id'] === $E2, 'bascule vers l\'établissement 2');
    check(!in_array('bi_config_direction', $cles, true) && !in_array('__etab_slug', $cles, true) && !in_array('old_input', $cles, true) && !in_array('errors', $cles, true) && !in_array('_auth_refreshed_at', $cles, true), 'données de l\'établissement quitté purgées (BI, slug, saisies, erreurs, horodatage de rafraîchissement)', implode(',', $cles));
    $r = req($c, $sZ, 'GET', '/eleves');
    check($r['status'] === 200 && str_contains((string)$r['html'], 'ELEVEDEUXSESS') && !str_contains((string)$r['html'], $nomEleve1), 'après la bascule : la liste montre UNIQUEMENT les élèves de l\'établissement 2');
    $r = req($c, $sZ, 'POST', '/logout', []);
    check($r['connecte'] === false, 'déconnexion : session fermée');
    $r = req($c, $sZ, 'GET', '/dashboard');
    check($r['connecte'] === false && str_contains((string)($r['location'] ?? ''), '/login'), 'après déconnexion : aucun accès, aucun contexte résiduel');
    $r = req($c, $sZ, 'POST', '/login', ['email' => 'z.deux@exemple.test', 'password' => $MDP]);
    check($r['attente_choix'] === true && $r['connecte'] === false, 'reconnexion : retour au choix d\'établissement, aucun établissement mémorisé dans la session');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('5. Liste de bascule');
    $sX = ouvrir($c, 'x.multi@exemple.test', 1);
    $r = etat($c, $sX);
    $dispo = $r['user']['available'] ?? [];
    sort($dispo);
    check($dispo === [1, $E2], 'liste de bascule = établissements ACCESSIBLES seulement (ni suspendu, ni expiré, ni sans rôle)', json_encode($dispo));
    $sM = ouvrir($c, 'autre.compte@exemple.test', 1);
    check((etat($c, $sM)['user']['available'] ?? null) === [], 'un seul établissement : liste vide');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('6. Profil, photo et mot de passe d\'un membre inter-établissements');
    $sY = ouvrir($c, 'y.origine2@exemple.test', 1);   // origine = établissement 2, connecté à l'établissement 1
    $r = req($c, $sY, 'GET', '/profile');
    check($r['status'] === 200 && str_contains((string)$r['html'], 'value="y.origine2@exemple.test"') && str_contains((string)$r['html'], 'Nomy.origine2'), 'profil affiché avec SES données (compte rattaché à un autre établissement)', (string)$r['status']);
    $r = req($c, $sY, 'POST', '/profile', ['nom' => 'NouveauNom', 'prenom' => 'NouveauPrenom', 'email' => 'y.origine2@exemple.test', 'telephone' => '+22790000000']);
    $u = $pdo->query("SELECT nom, prenom, telephone FROM users WHERE id = $Y")->fetch(PDO::FETCH_ASSOC);
    check($u === ['nom' => 'NouveauNom', 'prenom' => 'NouveauPrenom', 'telephone' => '+22790000000'], 'mise à jour du profil : effective et persistante (avant : sans effet, message de succès trompeur)', json_encode($u));
    check(str_contains(flashTexte($r, 'success'), 'Profil'), 'message de succès cohérent avec le résultat');
    $autreAvant = json_encode($pdo->query("SELECT nom, prenom, email, telephone, password, role FROM users WHERE id = $AUTRE")->fetch(PDO::FETCH_ASSOC));
    req($c, $sY, 'POST', '/profile', ['id' => $AUTRE, 'user_id' => $AUTRE, 'nom' => 'Piraté', 'prenom' => 'Piraté', 'email' => 'y.origine2@exemple.test', 'telephone' => '0', 'role' => 'eleve', 'etablissement_id' => 1]);
    check(json_encode($pdo->query("SELECT nom, prenom, email, telephone, password, role FROM users WHERE id = $AUTRE")->fetch(PDO::FETCH_ASSOC)) === $autreAvant, 'impossible de modifier le compte d\'un AUTRE utilisateur (identifiant ou champs forcés ignorés)');
    $uy = $pdo->query("SELECT role, etablissement_id, actif FROM users WHERE id = $Y")->fetch(PDO::FETCH_ASSOC);
    check($uy['role'] === 'admin' && (int)$uy['etablissement_id'] === $E2 && (int)$uy['actif'] === 1, 'rôle, établissement d\'origine et état non modifiables par le profil (liste blanche)');
    $avantInvalide = json_encode($pdo->query("SELECT nom, email FROM users WHERE id = $Y")->fetch(PDO::FETCH_ASSOC));
    req($c, $sY, 'POST', '/profile', ['nom' => 'X', 'prenom' => 'Y', 'email' => 'pas-un-email', 'telephone' => '']);
    check(json_encode($pdo->query("SELECT nom, email FROM users WHERE id = $Y")->fetch(PDO::FETCH_ASSOC)) === $avantInvalide, 'données invalides : rien n\'est écrit');
    req($c, $sY, 'POST', '/profile', ['nom' => 'NouveauNom', 'prenom' => 'NouveauPrenom', 'email' => 'autre.compte@exemple.test', 'telephone' => '']);
    check((string)$pdo->query("SELECT email FROM users WHERE id = $Y")->fetchColumn() === 'y.origine2@exemple.test', 'e-mail déjà utilisé par un autre compte (unicité globale) : refusé');
    // photo
    $pdo->exec("UPDATE users SET photo = 'storage/uploads/avatars/test-sess.png' WHERE id = $Y");
    $r = req($c, $sY, 'POST', '/profile/photo/supprimer', []);
    check($pdo->query("SELECT photo FROM users WHERE id = $Y")->fetchColumn() === null, 'suppression de la photo : effective pour un membre inter-établissements');
    // mot de passe
    $r = req($c, $sY, 'POST', '/profile/password', ['current_password' => 'faux-mdp-actuel', 'password' => $NOUVEAU, 'password_confirmation' => $NOUVEAU]);
    check(password_verify($MDP, (string)$pdo->query("SELECT password FROM users WHERE id = $Y")->fetchColumn()), 'mauvais mot de passe actuel : refusé, mot de passe inchangé');
    $r = req($c, $sY, 'POST', '/profile/password', ['current_password' => $MDP, 'password' => $NOUVEAU, 'password_confirmation' => $NOUVEAU]);
    check(password_verify($NOUVEAU, (string)$pdo->query("SELECT password FROM users WHERE id = $Y")->fetchColumn()), 'changement du mot de passe : effectif (avant : sans effet pour un compte hors de l\'établissement courant)');
    $r = req($c, nouvelleSession($c), 'POST', '/login', ['email' => 'y.origine2@exemple.test', 'password' => $NOUVEAU]);
    check($r['connecte'] === true || $r['attente_choix'] === true, 'connexion avec le nouveau mot de passe');
    $r = req($c, nouvelleSession($c), 'POST', '/login', ['email' => 'y.origine2@exemple.test', 'password' => $MDP]);
    check($r['connecte'] === false && $r['attente_choix'] === false, 'l\'ancien mot de passe ne fonctionne plus');
    // utilisateur d'origine = établissement courant : non-régression
    $sA = ouvrir($c, 'autre.compte@exemple.test', 1);
    req($c, $sA, 'POST', '/profile', ['nom' => 'NomAutre', 'prenom' => 'P', 'email' => 'autre.compte@exemple.test', 'telephone' => '1']);
    check((string)$pdo->query("SELECT nom FROM users WHERE id = $AUTRE")->fetchColumn() === 'NomAutre', 'non-régression : un utilisateur de son établissement d\'origine met à jour son profil');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('7. Aucune création ni élévation de privilèges');
    $avant = empreintePrivileges($c);
    $sx = ouvrir($c, 'z.deux@exemple.test', 1);
    foreach ([$E2, 1, $E5, $E2, 1, $E3, 99999] as $cible) { req($c, $sx, 'POST', '/auth/switch-school', ['school_id' => $cible]); }
    req($c, $sx, 'POST', '/profile', ['nom' => 'Z', 'prenom' => 'Z', 'email' => 'z.deux@exemple.test', 'telephone' => '2', 'role' => 'admin', 'etablissement_id' => $E2]);
    check(empreintePrivileges($c) === $avant, 'bascules, refus et mises à jour de profil : comptes, appartenances, rôles et droits RBAC inchangés');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('8. RBAC V2 global');
    $pdo->exec("INSERT INTO etab_roles (code, etablissement_id, nom, is_system) VALUES ('role_vide', NULL, 'Rôle sans permission', 0)");
    $idVide = (int)$pdo->query("SELECT id FROM etab_roles WHERE code = 'role_vide'")->fetchColumn();
    $W = creerUser($c, 'w.rbac@exemple.test', 1, [1 => ['admin'], $E2 => []]);
    $pdo->exec("INSERT INTO user_roles_etab (user_id, etablissement_id, role_id) VALUES ($W, $E2, $idVide)");
    $rbacRole = (int)$pdo->query('SELECT role_id FROM rbac_user_roles LIMIT 1')->fetchColumn();
    $pdo->exec("INSERT INTO rbac_user_roles (user_id, role_id, actif) VALUES ($W, $rbacRole, 1)");
    $sW = ouvrir($c, 'w.rbac@exemple.test', 1);
    $r = etat($c, $sW);
    check(in_array('users.create', $r['user']['permissions'], true), 'préparation : permissions administrateur dans l\'établissement 1');
    $r = req($c, $sW, 'POST', '/auth/switch-school', ['school_id' => $E2]);
    check($r['connecte'] === true && $r['user']['role'] === 'role_vide' && $r['user']['permissions'] === [], 'bascule vers un établissement où le rôle n\'a aucune permission : AUCUNE permission héritée de l\'établissement quitté ni de rbac_user_roles', json_encode(array_slice($r['user']['permissions'] ?? [], 0, 4)));
    $r = etat($c, $sW);
    check($r['connecte'] === true && $r['user']['permissions'] === [], 'et après le rafraîchissement de session suivant');
} finally {
    $copie->drop();
    foreach (glob($tmp . '/*') ?: [] as $f) { is_dir($f) ? array_map('unlink', glob($f . '/*') ?: []) : @unlink($f); }
}

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
