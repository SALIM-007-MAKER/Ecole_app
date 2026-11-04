<?php
/**
 * Tests — Phase 3 : mot de passe oublié et réinitialisation GLOBALE.
 *
 *  1. Demande : recherche globale (utilisateur d'un autre établissement), jeton haché, e-mail envoyé, réponse NEUTRE (existant / inconnu /
 *     désactivé / limité : mêmes message et redirection)
 *  2. Lien : invalide, expiré, déjà utilisé, jeton d'une autre adresse, essais limités
 *  3. Politique de la PERSONNE : administrateur 14 caractères + 3 familles ; sinon le réglage le plus élevé de ses établissements
 *  4. Réinitialisation : mot de passe effectivement changé, jeton à usage unique ATOMIQUE, autres jetons supprimés, comptes et rôles inchangés
 *  5. Sessions : invalidées après une réinitialisation ou un changement de mot de passe (la session qui change son mot de passe reste valide)
 *  6. SMTP indisponible : réponse neutre, événement d'audit, aucun jeton en journal
 *  7. Aucun jeton brut ni mot de passe dans la base, le journal global et les fichiers de journal
 *  8. Migration T069 : additive, idempotente, retour arrière ; compatibilité AVANT T069
 *
 * Aucun e-mail réel : le banc d'essai remplace la configuration SMTP par un faux serveur local (tests/Support/fake_smtp_server.php) ou un
 * port fermé. Travaille uniquement sur des copies jetables ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/AuthRecuperationTest.php
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
$tmp = sys_get_temp_dir() . '/authrec_' . bin2hex(random_bytes(4));
@mkdir($tmp . '/throttle', 0775, true);
\Core\Platform\PlatformAuditService::definirDossierSecours($tmp . '/secours');
$debutTest = time() - 2;
// Taille des fichiers de journal AVANT le test : seul ce qui est écrit PENDANT le test est contrôlé.
$taillesDebut = [];
foreach (glob(ROOT_PATH . '/storage/logs/*.log') ?: [] as $fl) { $taillesDebut[$fl] = filesize($fl); }

final class Ctx
{
    public function __construct(public PDO $pdo, public string $db, public string $tmp) {}
    public function ip(): string { return '10.99.' . random_int(1, 250) . '.' . random_int(1, 250); }
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
function texteFlash(array $r, string $cle): string
{
    $v = $r['flash'][$cle] ?? '';
    if (is_string($v)) { return $v; }
    $out = [];
    array_walk_recursive($v, function ($x) use (&$out) { $out[] = (string)$x; });
    return implode(' | ', $out);
}
function ouvrir(Ctx $c, string $email, string $mdp, ?int $etab = null): string
{
    $s = nouvelleSession($c);
    $r = req($c, $s, 'POST', '/login', ['email' => $email, 'password' => $mdp]);
    if (($r['attente_choix'] ?? false) && $etab !== null) { req($c, $s, 'POST', '/choisir-etablissement', ['etablissement_id' => $etab]); }
    return $s;
}

function creerEtab(Ctx $c, string $slug): int
{
    $c->pdo->prepare("INSERT INTO etablissements (slug, nom, nom_court, type, statut) VALUES (?, ?, ?, 'lycee', 'active')")->execute([$slug, "École $slug", strtoupper($slug)]);
    return (int)$c->pdo->lastInsertId();
}
function idRole(Ctx $c, string $code): int
{
    $s = $c->pdo->prepare('SELECT id FROM etab_roles WHERE code = ? AND etablissement_id IS NULL'); $s->execute([$code]);
    return (int)$s->fetchColumn();
}
function creerUser(Ctx $c, string $email, int $etabOrigine, array $appartenances, int $actif = 1): int
{
    global $HASH;
    $c->pdo->prepare('INSERT INTO users (nom, prenom, email, password, role, actif, etablissement_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute(['Dupont', 'Awa', $email, $HASH, 'enseignant', $actif, $etabOrigine]);
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
function empreinteDroits(Ctx $c): string
{
    $q = fn(string $sql) => $c->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return md5(json_encode([$q('SELECT id, role, actif, etablissement_id, email FROM users ORDER BY id'), $q('SELECT * FROM user_etablissements ORDER BY id'),
        $q('SELECT * FROM user_roles_etab ORDER BY id'), $q('SELECT * FROM etab_roles ORDER BY id'), $q('SELECT * FROM rbac_user_roles ORDER BY id')]));
}
function motDePasseDe(Ctx $c, int $id): string { return (string)$c->pdo->query("SELECT password FROM users WHERE id = $id")->fetchColumn(); }

/** Démarre un faux serveur SMTP (une connexion) ; renvoie [process, pipes, fichierPort, fichierTranscription]. @return array{0:resource,1:array,2:string,3:string,4:int} */
function demarrerSmtp(Ctx $c, array $env = []): array
{
    $fp = $c->tmp . '/port_' . bin2hex(random_bytes(3)); $ft = $c->tmp . '/transcription_' . bin2hex(random_bytes(3)) . '.json';
    $p = proc_open([PHP_BINARY, ROOT_PATH . '/tests/Support/fake_smtp_server.php', $fp, $ft, '1'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), $env));
    $t0 = microtime(true);
    while (!is_file($fp) && microtime(true) - $t0 < 8) { usleep(50000); }
    return [$p, $pipes, $fp, $ft, (int)@file_get_contents($fp)];
}
/** Attend la fin du faux serveur et renvoie sa transcription (null si aucune connexion). */
function terminerSmtp(array $s): ?array
{
    [$p, $pipes, , $ft] = $s;
    $statut = proc_get_status($p);
    $t0 = microtime(true);
    while ($statut['running'] && microtime(true) - $t0 < 6) { usleep(50000); $statut = proc_get_status($p); }
    if ($statut['running']) { proc_terminate($p); }
    foreach ($pipes as $x) { @fclose($x); }
    proc_close($p);
    $liste = is_file($ft) ? json_decode((string)file_get_contents($ft), true) : null;   // liste de transcriptions (une par connexion)
    return is_array($liste) && isset($liste[0]) ? $liste[0] : null;
}
/** Jeton brut contenu dans le message capturé (le corps peut être encodé : quoted-printable ou base64). */
function jetonDuMessage(?array $transcription): ?string
{
    $m = (string)($transcription['message'] ?? '');
    $candidats = [$m, quoted_printable_decode($m)];
    if (preg_match_all('/Content-Transfer-Encoding: base64\r\n\r\n(.*?)\r\n--/s', $m, $blocs)) {
        foreach ($blocs[1] as $b) { $candidats[] = (string)base64_decode(preg_replace('/\s+/', '', $b)); }
    }
    foreach ($candidats as $x) {
        if (preg_match('#reset-password/([a-f0-9]{64})#', $x, $r)) { return $r[1]; }
    }
    return null;
}
/** Demande de réinitialisation avec capture de l'e-mail. @return array{0:array,1:?string,2:?array} [réponse, jeton, transcription] */
function demander(Ctx $c, string $email, array $envSmtp = [], bool $avecServeur = true): array
{
    $smtp = $avecServeur ? demarrerSmtp($c, $envSmtp) : null;
    $r = req($c, nouvelleSession($c), 'POST', '/forgot-password', ['email' => $email], $smtp ? ['AUTH_REQ_MAIL_PORT' => (string)$smtp[4]] : []);
    $t = $smtp ? terminerSmtp($smtp) : null;
    return [$r, jetonDuMessage($t), $t];
}
function reinit(Ctx $c, string $token, string $mdp, ?string $confirm = null): array
{
    return req($c, nouvelleSession($c), 'POST', '/reset-password', ['token' => $token, 'password' => $mdp, 'password_confirmation' => $confirm ?? $mdp]);
}

$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible\n");
$copie2 = null;
try {
    $nom = $copie->connection()['db'];
    $pdo = \Core\Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (!preg_match('/^ecole_app_copy_[a-f0-9]+$/', (string)$pdo->query('SELECT DATABASE()')->fetchColumn())) { exit("REFUS : base de travail non jetable\n"); }
    $c = new Ctx($pdo, $nom, $tmp);
    $auth = new GlobalAuthService($pdo);
    $E2 = creerEtab($c, 'ecole-deux');
    \Core\Tenant\TenantCache::make()->forgetCategory($E2, 'settings');

    $U1 = creerUser($c, 'u1.enseignant@exemple.test', 1, [1 => ['enseignant']]);            // non administrateur, établissement 1
    $U2 = creerUser($c, 'u2.admin2@exemple.test', $E2, [$E2 => ['admin']]);                 // administrateur de l'établissement 2 (origine 2)
    $U3 = creerUser($c, 'u3.multi@exemple.test', 1, [1 => ['admin'], $E2 => ['enseignant']]);
    $U4 = creerUser($c, 'u4.deux@exemple.test', $E2, [$E2 => ['enseignant']]);              // enseignant de l'établissement 2 seulement
    $U5 = creerUser($c, 'u5.inactif@exemple.test', 1, [1 => ['enseignant']], 0);
    $nbJetons = fn() => (int)$pdo->query('SELECT COUNT(*) FROM password_resets')->fetchColumn();
    $reponseNeutre = 'Si cet email est enregistré, un lien de réinitialisation a été envoyé.';

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('1. Demande de réinitialisation');
    [$r, $jeton, $t] = demander($c, 'u1.enseignant@exemple.test');
    check($r['status'] === 302 && str_ends_with((string)$r['location'], '/forgot-password') && texteFlash($r, 'success') === $reponseNeutre, 'compte existant : réponse neutre (message et redirection)', texteFlash($r, 'success') . ' ' . ($r['location'] ?? ''));
    check($jeton !== null && strlen($jeton) === 64, 'un e-mail est envoyé (faux serveur SMTP) et contient le lien avec un jeton de 64 caractères hexadécimaux');
    $ligne = $pdo->query('SELECT * FROM password_resets ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    check($jeton !== null && $ligne['token'] === hash('sha256', $jeton) && $ligne['token'] !== $jeton && $ligne['email'] === 'u1.enseignant@exemple.test' && (int)$ligne['used'] === 0, 'le jeton est stocké sous forme de HACHAGE SHA-256, jamais en clair, lié à l\'adresse du compte');
    $duree = (int)$pdo->query('SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) FROM password_resets ORDER BY id DESC LIMIT 1')->fetchColumn();   // horloge de MySQL (PHP et MySQL n'ont pas le même fuseau)
    check($duree > 3500 && $duree <= 3600, 'expiration à 1 heure', (string)$duree);
    check(str_contains((string)($t['message'] ?? ''), 'Subject') || count($t['commandes'] ?? []) > 3, 'le message a bien été remis au serveur SMTP');
    $JETON1 = (string)$jeton;
    $avantNeutre = [$r['status'], $r['location'], texteFlash($r, 'success')];

    [$r2, $j2] = demander($c, 'inconnu@exemple.test', [], false);
    [$r3, $j3] = demander($c, 'u5.inactif@exemple.test', [], false);
    check([$r2['status'], $r2['location'], texteFlash($r2, 'success')] === $avantNeutre && [$r3['status'], $r3['location'], texteFlash($r3, 'success')] === $avantNeutre, 'e-mail inconnu et compte désactivé : MÊME réponse (statut, redirection, message)');
    check($nbJetons() === 1, 'aucun jeton créé pour l\'adresse inconnue ni pour le compte désactivé');
    check(count(audits($c, 'auth.password_reset_mail_failed')) === 0, 'aucune tentative d\'envoi pour ces deux cas');
    [$r4, $j4] = demander($c, 'u4.deux@exemple.test');
    check($j4 !== null && texteFlash($r4, 'success') === $reponseNeutre, 'utilisateur d\'un AUTRE établissement (établissement 2) : e-mail envoyé (avant la Phase 3 : aucun e-mail, sans erreur)');
    $dem = audits($c, 'auth.password_reset_requested');
    check(count($dem) === 4 && $dem[0]['resultat'] === 'success', 'chaque demande journalisée (auth.password_reset_requested), y compris les adresses inconnues', (string)count($dem));
    // limitation de débit : 3 demandes par adresse et IP
    $ipFixe = '10.55.55.55';
    for ($i = 0; $i < 4; $i++) {
        $rt = req($c, nouvelleSession($c), 'POST', '/forgot-password', ['email' => 'limite@exemple.test'], ['AUTH_REQ_IP' => $ipFixe]);
    }
    check([$rt['status'], $rt['location'], texteFlash($rt, 'success')] === $avantNeutre, 'demande limitée (4e depuis la même adresse) : MÊME réponse neutre, aucune information sur la limitation');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('2. Lien de réinitialisation');
    $r = req($c, nouvelleSession($c), 'GET', "/reset-password/$JETON1");
    check($r['status'] === 200 && str_contains((string)$r['html'], 'name="token"'), 'lien valide : formulaire affiché');
    $r = req($c, nouvelleSession($c), 'GET', '/reset-password/' . str_repeat('a', 64));
    check($r['status'] === 302 && str_contains(texteFlash($r, 'error'), 'invalide'), 'jeton inventé : refusé');
    $r = reinit($c, str_repeat('b', 64), 'Nouveau-Mdp-Solide-2027#x');
    check($r['status'] === 302 && str_contains(texteFlash($r, 'error'), 'invalide') && motDePasseDe($c, $U1) === $HASH, 'POST avec un jeton inventé : refusé, mot de passe inchangé');
    check(count(audits($c, 'auth.password_reset_failed')) >= 1, 'échec journalisé (auth.password_reset_failed), sans le jeton');
    $r = reinit($c, '', 'Nouveau-Mdp-Solide-2027#x');
    check(str_contains(texteFlash($r, 'error'), 'invalide'), 'jeton vide : refusé');
    // expiré
    $pdo->exec("UPDATE password_resets SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE token = '" . hash('sha256', $JETON1) . "'");
    $r = reinit($c, $JETON1, 'Nouveau-Mdp-Solide-2027#x');
    check(str_contains(texteFlash($r, 'error'), 'expiré') && motDePasseDe($c, $U1) === $HASH, 'lien expiré : refusé, mot de passe inchangé');
    $r = req($c, nouvelleSession($c), 'GET', "/reset-password/$JETON1");
    check($r['status'] === 302, 'lien expiré : le formulaire n\'est plus affiché');
    $pdo->exec("UPDATE password_resets SET expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE token = '" . hash('sha256', $JETON1) . "'");

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('3. Politique de mot de passe de la personne');
    $avant = empreinteDroits($c);
    $r = reinit($c, $JETON1, 'court');
    check(str_contains(texteFlash($r, 'errors'), 'au moins 8') && motDePasseDe($c, $U1) === $HASH, 'non administrateur : 8 caractères minimum (réglage par défaut), refus sans consommer le lien');
    check($auth->jetonReinitialisationValide($JETON1) !== null, 'le jeton n\'est PAS consommé par un mot de passe refusé (l\'utilisateur peut corriger)');
    $r = reinit($c, $JETON1, 'Nouveau-Mdp-Solide-2027#x', 'autre-confirmation');
    check(str_contains(texteFlash($r, 'errors'), 'correspondent pas') && motDePasseDe($c, $U1) === $HASH, 'confirmation différente : refusée');
    $pdo->exec("INSERT INTO etab_settings (etablissement_id, section, cle, valeur, type) VALUES ($E2, 'securite', 'password_min_length', '12', 'integer')");
    \Core\Tenant\TenantCache::make()->forgetCategory($E2, 'settings');
    check($auth->politiqueMotDePasse($auth->lookupById($U1)) === ['admin' => false, 'min' => 8], 'politique d\'un enseignant de l\'établissement 1 seul : 8');
    check($auth->politiqueMotDePasse($auth->lookupById($U4)) === ['admin' => false, 'min' => 12], 'politique d\'un enseignant de l\'établissement 2 (réglage 12) : 12');
    $polMulti = $auth->politiqueMotDePasse($auth->lookupById($U3));
    check($polMulti['admin'] === true && $polMulti['min'] === 14, 'administrateur dans UN de ses établissements : politique administrateur (14, 3 familles) même s\'il est enseignant ailleurs', json_encode($polMulti));
    [$rA, $jA] = demander($c, 'u2.admin2@exemple.test');
    $r = reinit($c, (string)$jA, 'Abcdef123!');
    check(str_contains(texteFlash($r, 'errors'), '14'), 'administrateur : un mot de passe de 10 caractères est refusé (14 minimum)', texteFlash($r, 'errors'));
    $r = reinit($c, (string)$jA, 'abcdefghijklmnop');
    check(texteFlash($r, 'errors') !== '', 'administrateur : 16 caractères mais une seule famille : refusé');
    $r = reinit($c, (string)$jA, 'dupont-Awa-2026-Soleil!');
    check(texteFlash($r, 'errors') !== '', 'administrateur : un mot de passe contenant son nom est refusé');
    check($auth->jetonReinitialisationValide((string)$jA) !== null && motDePasseDe($c, $U2) === $HASH, 'rien n\'est consommé ni modifié par ces refus');
    $r = reinit($c, (string)$jA, 'Soleil-Levant-Niger-2027#');
    check($r['status'] === 302 && str_ends_with((string)$r['location'], '/login') && password_verify('Soleil-Levant-Niger-2027#', motDePasseDe($c, $U2)), 'administrateur : mot de passe conforme accepté (utilisateur d\'un autre établissement : changement EFFECTIF)', texteFlash($r, 'error') . texteFlash($r, 'errors'));

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('4. Réinitialisation effective et jeton à usage unique');
    $NOUVEAU = 'Nouveau-Mdp-Solide-2027#x';
    // un second jeton en attente pour la même adresse doit disparaître après la réinitialisation
    [$rb, $jetonB] = demander($c, 'u1.enseignant@exemple.test');   // remplace JETON1 (ancien jeton supprimé à chaque demande)
    check($auth->jetonReinitialisationValide($JETON1) === null && $auth->jetonReinitialisationValide((string)$jetonB) !== null, 'une nouvelle demande INVALIDE le lien précédent');
    $avant = empreinteDroits($c);
    $r = reinit($c, (string)$jetonB, $NOUVEAU);
    check($r['status'] === 302 && str_ends_with((string)$r['location'], '/login') && str_contains(texteFlash($r, 'success'), 'succès'), 'réinitialisation acceptée');
    check(password_verify($NOUVEAU, motDePasseDe($c, $U1)) && !password_verify($MDP, motDePasseDe($c, $U1)), 'mot de passe effectivement changé (ancien refusé, nouveau accepté)');
    $rl = req($c, nouvelleSession($c), 'POST', '/login', ['email' => 'u1.enseignant@exemple.test', 'password' => $NOUVEAU]);
    check($rl['connecte'] === true, 'connexion avec le nouveau mot de passe');
    $rl = req($c, nouvelleSession($c), 'POST', '/login', ['email' => 'u1.enseignant@exemple.test', 'password' => $MDP]);
    check($rl['connecte'] === false, 'ancien mot de passe refusé');
    $r = reinit($c, (string)$jetonB, 'Un-Autre-Mdp-Solide-2028#y');
    check(str_contains(texteFlash($r, 'error'), 'invalide') && password_verify($NOUVEAU, motDePasseDe($c, $U1)), 'RÉUTILISATION du même lien : refusée, mot de passe inchangé');
    check((int)$pdo->query("SELECT COUNT(*) FROM password_resets WHERE email = 'u1.enseignant@exemple.test'")->fetchColumn() === 1 && $auth->jetonReinitialisationValide((string)$jetonB) === null, 'le jeton consommé n\'est plus valide ; aucun autre jeton en attente pour cette adresse');
    check(empreinteDroits($c) === $avant, 'comptes, appartenances, rôles et droits RBAC inchangés par une réinitialisation');
    check(count(audits($c, 'auth.password_reset')) >= 2, 'réinitialisation journalisée (auth.password_reset)');
    // jeton d'une autre adresse : ne modifie que SON compte
    [$rc, $jC] = demander($c, 'u3.multi@exemple.test');
    [$rd, $jD] = demander($c, 'u4.deux@exemple.test');
    $avantU3 = motDePasseDe($c, $U3);
    reinit($c, (string)$jD, 'Quatrieme-Mdp-Solide-2029#z');
    check(motDePasseDe($c, $U3) === $avantU3 && password_verify('Quatrieme-Mdp-Solide-2029#z', motDePasseDe($c, $U4)), 'un jeton ne change QUE le mot de passe de son propre compte');
    // atomicité : le jeton est consommé entre le contrôle de validité et la mise à jour (course entre deux requêtes)
    $jetonAtom = (string)$jC;
    $valideAvant = $auth->jetonReinitialisationValide($jetonAtom) !== null;
    $pdo->exec("UPDATE password_resets SET used = 1 WHERE token = '" . hash('sha256', $jetonAtom) . "'");   // « l'autre requête » gagne la course
    $perdant = $auth->reinitialiserMotDePasse($jetonAtom, password_hash('Perdant-Mdp-Solide-2030#w', PASSWORD_BCRYPT));
    check($valideAvant && $perdant === null && motDePasseDe($c, $U3) === $avantU3, 'usage unique ATOMIQUE : la requête qui perd la course ne change pas le mot de passe');
    $pdo->exec("UPDATE password_resets SET used = 0 WHERE token = '" . hash('sha256', $jetonAtom) . "'");
    $gagnant = $auth->reinitialiserMotDePasse($jetonAtom, password_hash('Gagnant-Mdp-Solide-2031#v', PASSWORD_BCRYPT));
    $second = $auth->reinitialiserMotDePasse($jetonAtom, password_hash('Second-Mdp-Solide-2032#u', PASSWORD_BCRYPT));
    check($gagnant !== null && $second === null && password_verify('Gagnant-Mdp-Solide-2031#v', motDePasseDe($c, $U3)), 'deux utilisations successives du même jeton : une seule réussit');
    // jeton EXPIRÉ présenté directement à la consommation atomique (aucun contrôle préalable) : refusé
    [$rx, $jX] = demander($c, 'u3.multi@exemple.test');
    $pdo->exec("UPDATE password_resets SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE token = '" . hash('sha256', (string)$jX) . "'");
    $avantExp = motDePasseDe($c, $U3);
    check($auth->reinitialiserMotDePasse((string)$jX, password_hash('Expire-Mdp-Solide-2038#o', PASSWORD_BCRYPT)) === null && motDePasseDe($c, $U3) === $avantExp, 'usage unique ATOMIQUE : un jeton expiré est refusé par la consommation elle-même (condition dans la requête)');
    // transaction englobante : un échec ne consomme pas le jeton
    [$re, $jE] = demander($c, 'u1.enseignant@exemple.test');
    $pdo->exec('CREATE TRIGGER t_users_ko BEFORE UPDATE ON users FOR EACH ROW BEGIN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'echec simule\'; END');
    $levee = null;
    try { $auth->reinitialiserMotDePasse((string)$jE, password_hash('Echec-Mdp-Solide-2033#t', PASSWORD_BCRYPT)); } catch (\Throwable $ex) { $levee = $ex; }
    $pdo->exec('DROP TRIGGER IF EXISTS t_users_ko');
    check($levee !== null && $auth->jetonReinitialisationValide((string)$jE) !== null && password_verify($NOUVEAU, motDePasseDe($c, $U1)), 'échec de la mise à jour du mot de passe : jeton NON consommé (aucun effet partiel)');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('5. Invalidation des sessions');
    $pdo->exec("UPDATE users SET password = '" . password_hash($MDP, PASSWORD_BCRYPT) . "', password_changed_at = NULL WHERE id = $U1");
    $sAvant = ouvrir($c, 'u1.enseignant@exemple.test', $MDP);
    $r = req($c, $sAvant, 'GET', '/dashboard');
    check($r['connecte'] === true, 'préparation : session ouverte AVANT la réinitialisation');
    [$rf, $jF] = demander($c, 'u1.enseignant@exemple.test');
    $r = reinit($c, (string)$jF, 'Apres-Reset-Mdp-Solide-2034#s');
    $col = $pdo->query("SELECT password_changed_at FROM users WHERE id = $U1")->fetchColumn();
    check($col !== null && $col !== false, 'users.password_changed_at renseigné par la réinitialisation');
    $r = req($c, $sAvant, 'GET', '/dashboard');
    check($r['connecte'] === true, 'dans l\'intervalle de rafraîchissement (60 s), la session ouverte reste valide (délai documenté)');
    $sess = lireSession($sAvant); $sess['_auth_refreshed_at'] = time() - 61; ecrireSession($sAvant, $sess);
    $r = req($c, $sAvant, 'GET', '/dashboard');
    check($r['connecte'] === false && str_contains((string)($r['location'] ?? ''), '/login'), 'après le rafraîchissement suivant : la session ouverte avant la réinitialisation est INVALIDÉE');
    $sApres = ouvrir($c, 'u1.enseignant@exemple.test', 'Apres-Reset-Mdp-Solide-2034#s');
    req($c, $sApres, 'GET', '/dashboard');
    $sess = lireSession($sApres); $sess['_auth_refreshed_at'] = time() - 61; ecrireSession($sApres, $sess);
    $r = req($c, $sApres, 'GET', '/dashboard');
    check($r['connecte'] === true, 'une session ouverte APRÈS la réinitialisation reste valide');
    // changement depuis le profil : la session courante reste valide, les autres sont invalidées
    $sA = ouvrir($c, 'u1.enseignant@exemple.test', 'Apres-Reset-Mdp-Solide-2034#s');
    $sB = ouvrir($c, 'u1.enseignant@exemple.test', 'Apres-Reset-Mdp-Solide-2034#s');
    req($c, $sA, 'GET', '/dashboard'); req($c, $sB, 'GET', '/dashboard');
    [$rg, $jG] = demander($c, 'u1.enseignant@exemple.test');   // un lien en attente doit être révoqué par le changement
    sleep(1);   // la date du mot de passe a une résolution d'une seconde
    $r = req($c, $sA, 'POST', '/profile/password', ['current_password' => 'Apres-Reset-Mdp-Solide-2034#s', 'password' => 'Depuis-Profil-Mdp-2035#r', 'password_confirmation' => 'Depuis-Profil-Mdp-2035#r']);
    check(password_verify('Depuis-Profil-Mdp-2035#r', motDePasseDe($c, $U1)) && str_contains(texteFlash($r, 'success'), 'modifié'), 'changement depuis le profil : effectif');
    check($auth->jetonReinitialisationValide((string)$jG) === null, 'un lien de réinitialisation en attente est RÉVOQUÉ par le changement de mot de passe');
    foreach ([$sA, $sB] as $sx) { $sess = lireSession($sx); $sess['_auth_refreshed_at'] = time() - 61; ecrireSession($sx, $sess); }
    $rA = req($c, $sA, 'GET', '/dashboard'); $rB = req($c, $sB, 'GET', '/dashboard');
    check($rA['connecte'] === true && $rB['connecte'] === false, 'la session qui change son mot de passe reste valide ; l\'AUTRE session est invalidée');
    check(count(audits($c, 'auth.password_changed')) >= 1, 'changement journalisé (auth.password_changed)');
    $r = req($c, $sA, 'POST', '/profile/password', ['current_password' => 'Depuis-Profil-Mdp-2035#r', 'password' => 'court', 'password_confirmation' => 'court']);
    check(password_verify('Depuis-Profil-Mdp-2035#r', motDePasseDe($c, $U1)), 'profil : un mot de passe trop court est refusé');
    $sAdm = ouvrir($c, 'u2.admin2@exemple.test', 'Soleil-Levant-Niger-2027#');
    $r = req($c, $sAdm, 'POST', '/profile/password', ['current_password' => 'Soleil-Levant-Niger-2027#', 'password' => 'Abcdef123!', 'password_confirmation' => 'Abcdef123!']);
    check(password_verify('Soleil-Levant-Niger-2027#', motDePasseDe($c, $U2)), 'profil : un ADMINISTRATEUR ne peut pas affaiblir son mot de passe (politique 14 + 3 familles)');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('6. Essais de lien limités et SMTP indisponible');
    $ipRp = '10.66.66.66'; $dernier = null;
    for ($i = 0; $i < 11; $i++) { $dernier = req($c, nouvelleSession($c), 'POST', '/reset-password', ['token' => str_repeat('c', 64), 'password' => 'x', 'password_confirmation' => 'x'], ['AUTH_REQ_IP' => $ipRp]); }
    check(str_contains(texteFlash($dernier, 'error'), 'Trop de tentatives'), '10 liens invalides depuis une adresse : les essais suivants sont bloqués');
    [$rh, $jH] = demander($c, 'u1.enseignant@exemple.test');
    $dernier = req($c, nouvelleSession($c), 'POST', '/reset-password', ['token' => (string)$jH, 'password' => 'Quelconque-Mdp-2036#q', 'password_confirmation' => 'Quelconque-Mdp-2036#q'], ['AUTH_REQ_IP' => $ipRp]);
    check(str_contains(texteFlash($dernier, 'error'), 'Trop de tentatives'), 'depuis cette adresse, même un lien valide est refusé pendant le blocage');
    // Adresses neuves : la limitation de demandes (5 par adresse, toutes IP confondues) s'applique aux demandes déjà faites pour u1.
    creerUser($c, 'u6.smtp@exemple.test', 1, [1 => ['enseignant']]);
    creerUser($c, 'u7.smtp@exemple.test', 1, [1 => ['enseignant']]);
    $nMail = count(audits($c, 'auth.password_reset_mail_failed'));
    [$ri, $jI, $tI] = demander($c, 'u6.smtp@exemple.test', ['FAKE_SMTP_FAIL' => 'rcpt']);
    check([$ri['status'], $ri['location'], texteFlash($ri, 'success')] === $avantNeutre, 'SMTP qui refuse le destinataire : réponse NEUTRE identique à celle d\'un envoi réussi');
    $ech = audits($c, 'auth.password_reset_mail_failed');
    check(count($ech) === $nMail + 1 && end($ech)['resultat'] === 'failure' && str_contains((string)end($ech)['nouvelle_valeur'], 'envoi_impossible'), 'échec d\'envoi journalisé (auth.password_reset_mail_failed)');
    $nMail = count($ech);
    [$rj, $jJ] = demander($c, 'u7.smtp@exemple.test', [], false);   // aucun serveur : port fermé
    check([$rj['status'], $rj['location'], texteFlash($rj, 'success')] === $avantNeutre && count(audits($c, 'auth.password_reset_mail_failed')) === $nMail + 1, 'serveur SMTP injoignable : réponse neutre, échec journalisé');
    $jetonStocke = (string)$pdo->query("SELECT token FROM password_resets WHERE email = 'u7.smtp@exemple.test' ORDER BY id DESC LIMIT 1")->fetchColumn();
    check(strlen($jetonStocke) === 64 && $auth->jetonReinitialisationValide($jetonStocke) === null, 'le jeton stocké est le hachage : il ne se présente pas lui-même comme un lien valide');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('7. Aucun jeton brut ni mot de passe dans les journaux');
    $secrets = array_filter([$JETON1, (string)$jetonB, (string)$jA, (string)$jC, (string)$jD, (string)$jE, (string)$jF, (string)$jG, (string)$jH, $NOUVEAU, 'Apres-Reset-Mdp-Solide-2034#s', 'Depuis-Profil-Mdp-2035#r', 'Soleil-Levant-Niger-2027#', 'Nouveau-Mdp-Solide-2027#x']);
    $journal = (string)$pdo->query("SELECT GROUP_CONCAT(COALESCE(ancienne_valeur,''), COALESCE(nouvelle_valeur,''), COALESCE(actor_email,''), COALESCE(user_agent,'') SEPARATOR ' ') FROM platform_audit_logs")->fetchColumn();
    $audit2 = (string)$pdo->query("SELECT GROUP_CONCAT(COALESCE(avant,''), COALESCE(apres,'') SEPARATOR ' ') FROM audit_logs")->fetchColumn();
    $fuite = [];
    foreach ($secrets as $x) {
        if (str_contains($journal, $x) || str_contains($audit2, $x)) { $fuite[] = 'base:' . substr($x, 0, 8); }
    }
    // Aucun LIEN de réinitialisation (même d'un jeton que le test ne connaît pas : envois en échec) dans les journaux d'audit.
    if (preg_match('#reset-password/[a-f0-9]{16,}#', $journal . $audit2) === 1) { $fuite[] = 'base:lien'; }
    $fichiers = array_merge(glob(ROOT_PATH . '/storage/logs/*.log') ?: [], glob($tmp . '/secours/*') ?: []);
    foreach ($fichiers as $fl) {
        if (filemtime($fl) < $debutTest) { continue; }
        $contenu = substr((string)file_get_contents($fl), $taillesDebut[$fl] ?? 0);
        foreach ($secrets as $x) { if (str_contains($contenu, $x)) { $fuite[] = basename($fl) . ':' . substr($x, 0, 8); } }
        if (preg_match('#reset-password/[a-f0-9]{16,}#', $contenu) === 1) { $fuite[] = basename($fl) . ':lien'; }
    }
    check($fuite === [], 'aucun jeton brut ni mot de passe dans les journaux d\'audit ni dans les fichiers de journal du serveur', implode(', ', $fuite));
    $brut = (string)$pdo->query("SELECT GROUP_CONCAT(email, token SEPARATOR ' ') FROM password_resets")->fetchColumn();
    $fuiteTable = false; foreach ($secrets as $x) { if (strlen($x) === 64 && str_contains($brut, $x)) { $fuiteTable = true; } }
    check(!$fuiteTable, 'la table password_resets ne contient aucun jeton en clair');
    $evts = (int)$pdo->query("SELECT COUNT(*) FROM platform_audit_logs WHERE action LIKE 'auth.password%'")->fetchColumn();
    check($evts >= 10, 'événements auth.password_* écrits dans le journal global', (string)$evts);
    check(glob($tmp . '/secours/*') === [] || glob($tmp . '/secours/*') === false, 'aucun fichier de secours : toutes les écritures d\'audit ont réussi');

    // ═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
    section('8. Migration T069 et compatibilité avant T069');
    $migration = require ROOT_PATH . '/database/migrations/T069_users_password_changed_at.php';
    $info = $pdo->query("SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_changed_at'")->fetch(PDO::FETCH_ASSOC);
    check($info !== false && $info['COLUMN_TYPE'] === 'datetime' && $info['IS_NULLABLE'] === 'YES' && $info['COLUMN_DEFAULT'] === null, 'T069 : colonne datetime NULL sans défaut');
    $nbU = md5(json_encode($pdo->query('SELECT id, email, password FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)));
    $migration['run']($pdo); $migration['run']($pdo);
    check(md5(json_encode($pdo->query('SELECT id, email, password FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC))) === $nbU, 'T069 rejouée : idempotente, aucune donnée modifiée');
    $copie2 = \Tests\Support\DbCopy::create(ROOT_PATH) ?? exit("ECHEC : seconde copie impossible\n");
    $pdo2 = $copie2->pdo(); $pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $c2 = new Ctx($pdo2, $copie2->name, $tmp);
    $pdo2->exec("INSERT INTO etablissements (slug, nom, nom_court, type, statut) VALUES ('e2b', 'E2B', 'E2B', 'lycee', 'active')");
    $E2b = (int)$pdo2->lastInsertId();
    $migration['rollback']($pdo2);
    check((int)$pdo2->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_changed_at'")->fetchColumn() === 0, 'retour arrière : colonne supprimée');
    $U9 = creerUser($c2, 'avant.t069@exemple.test', 1, [1 => ['enseignant']]);
    [$rk, $jK] = demander($c2, 'avant.t069@exemple.test');
    check($jK !== null, 'AVANT T069 : la demande fonctionne (e-mail envoyé)');
    $r = reinit($c2, (string)$jK, 'Avant-T069-Mdp-Solide-2037#p');
    check(password_verify('Avant-T069-Mdp-Solide-2037#p', (string)$pdo2->query("SELECT password FROM users WHERE id = $U9")->fetchColumn()), 'AVANT T069 : la réinitialisation fonctionne (aucune erreur, colonne absente détectée)');
    $sOld = ouvrir($c2, 'avant.t069@exemple.test', 'Avant-T069-Mdp-Solide-2037#p');
    req($c2, $sOld, 'GET', '/dashboard');
    $sess = lireSession($sOld); $sess['_auth_refreshed_at'] = time() - 61; ecrireSession($sOld, $sess);
    $r = req($c2, $sOld, 'GET', '/dashboard');
    check($r['connecte'] === true, 'AVANT T069 : les sessions fonctionnent normalement (aucune invalidation, aucune erreur)');
    $migration['run']($pdo2);
    check((int)$pdo2->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_changed_at'")->fetchColumn() === 1, 'T069 réappliquée sur la seconde copie');
} finally {
    \Core\Tenant\TenantCache::make()->flushTenant($E2 ?? 0);
    $copie->drop();
    if ($copie2 !== null) { $copie2->drop(); }
    foreach (glob($tmp . '/*') ?: [] as $f) { is_dir($f) ? array_map('unlink', glob($f . '/*') ?: []) : @unlink($f); }
}

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
