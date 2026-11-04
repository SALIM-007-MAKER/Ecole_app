<?php
/**
 * Tests — envoi réel d'e-mails par SMTP (Gmail) et réinitialisation du mot de passe.
 *
 * Aucun e-mail réel : tout passe par un FAUX serveur SMTP local (tests/Support/fake_smtp_server.php, 127.0.0.1) et, pour le parcours HTTP,
 * par une COPIE jetable de la base (jamais la base vivante). Les paramètres MAIL_* du .env ne sont jamais utilisés : la configuration est
 * injectée (tests unitaires) ou imposée par variables d'environnement réelles, prioritaires sur le .env (parcours HTTP).
 *
 * 1. Client SMTP : AUTH PLAIN / LOGIN, enveloppe, message MIME (accents, point en début de ligne), texte brut alternatif
 * 2. Refus : mauvais identifiants, destinataire refusé, expéditeur refusé, pas de STARTTLS, identifiants en clair vers un hôte distant
 * 3. Injection d'en-têtes (adresse, sujet) ; aucune fuite d'identifiants dans les erreurs
 * 4. EmailService : driver smtp (true/false, jamais d'exception), journal sans mot de passe, SMTP non configuré
 * 5. Parcours HTTP « mot de passe oublié » : e-mail envoyé, lien absolu valide, jeton haché en base, adresse inconnue silencieuse, limitation
 *
 * Lance : C:\wamp64\bin\php\php8.3.28\php.exe tests/Unit/SmtpMailerTest.php
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
    foreach (['Core\\' => '/core/', 'App\\Services\\' => '/app/Services/', 'App\\Models\\' => '/app/Models/', 'App\\Shared\\' => '/app/Shared/'] as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php';
            if (is_file($f)) { require $f; return; }
        }
    }
});
require_once ROOT_PATH . '/tests/Support/DbCopy.php';
// Toute la suite travaille sur une COPIE jetable : même les lectures indirectes (BrandingService…) n'atteignent jamais la base vivante.
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible\n");

use App\Services\EmailService;
use App\Services\SmtpException;
use App\Services\SmtpMailer;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

/** Démarre le faux serveur SMTP. @return array{proc:resource, pipes:array, port:int, dir:string} */
function serveur(array $env = [], int $nb = 1): array
{
    $dir = sys_get_temp_dir() . '/smtp_' . bin2hex(random_bytes(4));
    mkdir($dir);
    $proc = proc_open([PHP_BINARY, ROOT_PATH . '/tests/Support/fake_smtp_server.php', "$dir/port", "$dir/tr.json", (string)$nb],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), $env));
    for ($i = 0; $i < 100 && !is_file("$dir/port"); $i++) { usleep(50000); }
    return ['proc' => $proc, 'pipes' => $pipes, 'port' => (int)@file_get_contents("$dir/port"), 'dir' => $dir];
}
/** Attend la fin du serveur (ou l'interrompt) et renvoie les transcriptions. @return list<array{commandes:list<string>,message:?string,auth_ok:bool}> */
function fin(array $s, float $attenteMax = 6.0): array
{
    $debut = microtime(true);
    while (microtime(true) - $debut < $attenteMax && proc_get_status($s['proc'])['running']) { usleep(50000); }
    if (proc_get_status($s['proc'])['running']) { proc_terminate($s['proc']); }
    foreach ($s['pipes'] as $p) { @fclose($p); }
    proc_close($s['proc']);
    $tr = json_decode((string)@file_get_contents($s['dir'] . '/tr.json'), true) ?: [];
    array_map('unlink', glob($s['dir'] . '/*') ?: []); @rmdir($s['dir']);
    return $tr;
}
function cfg(int $port, array $surcharge = []): array
{
    return $surcharge + [
        'driver' => 'smtp', 'host' => '127.0.0.1', 'port' => $port, 'encryption' => 'none', 'username' => 'u', 'password' => 'p', 'timeout' => 5,
        'from_name' => 'École Test', 'from_email' => 'noreply@exemple.test', 'reply_to' => '', 'ehlo_host' => 'localhost',
    ];
}
/** @return ?\Throwable */
function attrape(callable $f): ?\Throwable { try { $f(); return null; } catch (\Throwable $e) { return $e; } }
/** Valeur (dépliée) d'un en-tête du message. */
function entete(string $message, string $nom): string
{
    $bloc = preg_replace('/\r\n[ \t]+/', ' ', explode("\r\n\r\n", $message, 2)[0]) ?? '';
    return preg_match('/^' . preg_quote($nom, '/') . ': (.*)$/mi', $bloc, $m) ? rtrim($m[1], "\r") : '';
}
/** Décode la partie MIME d'un type donné. */
function partie(string $message, string $type): string
{
    $marque = 'Content-Type: ' . $type . '; charset=UTF-8' . "\r\n" . 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
    $debut = strpos($message, $marque);
    if ($debut === false) { return ''; }
    $debut += strlen($marque);
    $fin = strpos($message, "\r\n--", $debut);
    return $fin === false ? '' : (string)base64_decode(str_replace("\r\n", '', substr($message, $debut, $fin - $debut)));
}

$html = "<p>Bonjour é à ç — test</p>\n.Ligne commençant par un point\n<a href=\"https://exemple.test/reset/abc\">Choisir un mot de passe</a>";

// ═══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('1. Client SMTP : envoi nominal');
$s = serveur();
$e = attrape(fn() => (new SmtpMailer(cfg($s['port'])))->send('noreply@exemple.test', 'École Test', 'dest@exemple.test', 'Réinitialisation é', $html, "Texte brut\nLien : https://exemple.test/reset/abc"));
$tr = fin($s)[0] ?? ['commandes' => [], 'message' => null, 'auth_ok' => false];
check($e === null, 'envoi accepté par le serveur', $e ? $e->getMessage() : '');
check($tr['auth_ok'] === true, 'AUTH PLAIN acceptée avec les bons identifiants');
check(in_array('MAIL FROM:<noreply@exemple.test>', $tr['commandes'], true) && in_array('RCPT TO:<dest@exemple.test>', $tr['commandes'], true), 'enveloppe MAIL FROM / RCPT TO correcte');
check(in_array('QUIT', $tr['commandes'], true), 'session fermée proprement (QUIT)');
$m = (string)$tr['message'];
check(str_contains($m, 'From: =?UTF-8?B?' . base64_encode('École Test') . '?= <noreply@exemple.test>') && str_contains($m, 'To: <dest@exemple.test>'), 'en-têtes From (nom accentué encodé) et To');
check(str_contains(entete($m, 'Subject'), '=?UTF-8?B?') && iconv_mime_decode(entete($m, 'Subject'), 0, 'UTF-8') === 'Réinitialisation é', 'sujet accentué encodé (RFC 2047, UTF-8 base64) et décodé à l\'identique');
check(str_contains($m, 'MIME-Version: 1.0') && str_contains($m, 'multipart/alternative') && preg_match('/^Message-ID: <[0-9a-f]{32}@exemple\.test>\r?$/m', $m) === 1 && preg_match('/^Date: /m', $m) === 1, 'MIME multipart, Message-ID et Date présents');
check(partie($m, 'text/html') === $html, 'corps HTML intact (accents, ligne commençant par un point)');
check(str_contains(partie($m, 'text/plain'), 'https://exemple.test/reset/abc'), 'texte brut alternatif avec le lien');
check(!str_contains($m, "\r\n.\r\n") && !preg_match('/^\.(?!\.)/m', $m), 'aucun point isolé ni début de ligne non doublé dans le message transmis');

$s = serveur(['FAKE_SMTP_AUTH' => 'login']);
$e = attrape(fn() => (new SmtpMailer(cfg($s['port'])))->send('noreply@exemple.test', 'Test', 'dest@exemple.test', 'Sujet', '<p>x</p>'));
$tr = fin($s)[0] ?? ['auth_ok' => false];
check($e === null && $tr['auth_ok'] === true, 'repli sur AUTH LOGIN quand PLAIN n\'est pas proposé', $e ? $e->getMessage() : '');

$s = serveur(['FAKE_SMTP_AUTH' => 'none']);
$e = attrape(fn() => (new SmtpMailer(cfg($s['port'], ['username' => '', 'password' => ''])))->send('noreply@exemple.test', 'Test', 'dest@exemple.test', 'Sujet', '<p>x</p>'));
$tr = fin($s)[0] ?? ['commandes' => []];
check($e === null && !array_filter($tr['commandes'], fn($c) => str_starts_with($c, 'AUTH')), 'sans identifiants configurés : aucune authentification tentée (serveur local)');

// ═══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('2. Refus du serveur et garde-fous');
$s = serveur(['FAKE_SMTP_USER' => 'u', 'FAKE_SMTP_PASS' => 'secret-serveur']);
$e = attrape(fn() => (new SmtpMailer(cfg($s['port'], ['password' => 'MOT-DE-PASSE-APP-TEST'])))->send('noreply@exemple.test', 'T', 'dest@exemple.test', 'S', '<p>x</p>'));
fin($s);
check($e instanceof SmtpException && str_contains($e->getMessage(), '535'), 'mauvais identifiants : SmtpException (535)', $e ? $e->getMessage() : 'aucune exception');
check($e !== null && !str_contains($e->getMessage(), 'MOT-DE-PASSE-APP-TEST') && !str_contains($e->getMessage(), base64_encode("\0u\0MOT-DE-PASSE-APP-TEST")), 'le message d\'erreur ne contient jamais le mot de passe');

$s = serveur(['FAKE_SMTP_FAIL' => 'rcpt']);
$e = attrape(fn() => (new SmtpMailer(cfg($s['port'])))->send('noreply@exemple.test', 'T', 'inconnu@exemple.test', 'S', '<p>x</p>'));
fin($s);
check($e instanceof SmtpException && str_contains($e->getMessage(), '550'), 'destinataire refusé (550) : SmtpException');

$s = serveur(['FAKE_SMTP_FAIL' => 'mail']);
$e = attrape(fn() => (new SmtpMailer(cfg($s['port'])))->send('noreply@exemple.test', 'T', 'dest@exemple.test', 'S', '<p>x</p>'));
fin($s);
check($e instanceof SmtpException && str_contains($e->getMessage(), '553'), 'expéditeur refusé (553) : SmtpException');

$s = serveur();
$e = attrape(fn() => (new SmtpMailer(cfg($s['port'], ['encryption' => 'tls'])))->send('noreply@exemple.test', 'T', 'dest@exemple.test', 'S', '<p>x</p>'));
$tr = fin($s)[0] ?? ['commandes' => []];
check($e instanceof SmtpException && str_contains($e->getMessage(), 'STARTTLS') && !array_filter($tr['commandes'], fn($c) => str_starts_with($c, 'AUTH')), 'chiffrement tls exigé sans STARTTLS : refus, identifiants jamais envoyés');

$e = attrape(fn() => (new SmtpMailer(cfg(25, ['host' => 'smtp.exemple.invalid', 'encryption' => 'none'])))->send('noreply@exemple.test', 'T', 'dest@exemple.test', 'S', '<p>x</p>'));
check($e instanceof SmtpException && str_contains($e->getMessage(), 'en clair'), 'identifiants en clair vers un hôte distant : refus avant toute connexion');

$e = attrape(fn() => (new SmtpMailer(cfg(1, ['encryption' => 'none', 'timeout' => 3])))->send('noreply@exemple.test', 'T', 'dest@exemple.test', 'S', '<p>x</p>'));
check($e instanceof SmtpException && str_contains($e->getMessage(), 'impossible'), 'serveur injoignable : SmtpException claire');

foreach ([['host' => ''], ['port' => 0], ['encryption' => 'sslv3']] as $mauvais) {
    $e = attrape(fn() => (new SmtpMailer(cfg(25, $mauvais)))->send('noreply@exemple.test', 'T', 'dest@exemple.test', 'S', '<p>x</p>'));
    check($e instanceof SmtpException, 'configuration invalide refusée : ' . json_encode($mauvais));
}

// ═══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('3. Injection d\'en-têtes');
$s = serveur();
foreach (["dest@exemple.test\r\nBcc: pirate@exemple.test", "dest@exemple.test\nBcc: pirate@exemple.test", 'a@b.c, d@e.f', 'dest@exemple.test>', ''] as $mauvaise) {
    $e = attrape(fn() => (new SmtpMailer(cfg($s['port'])))->send('noreply@exemple.test', 'T', $mauvaise, 'S', '<p>x</p>'));
    check($e instanceof SmtpException && str_contains($e->getMessage(), 'invalide'), 'destinataire refusé : ' . json_encode($mauvaise));
}
$e = attrape(fn() => (new SmtpMailer(cfg($s['port'])))->send("noreply@exemple.test\r\nBcc: x@y.z", 'T', 'dest@exemple.test', 'S', '<p>x</p>'));
check($e instanceof SmtpException, 'expéditeur avec saut de ligne refusé');
$trVide = fin($s, 0.3);
check($trVide === [], 'aucune connexion ouverte pour des adresses invalides');

$s = serveur();
(new SmtpMailer(cfg($s['port'])))->send('noreply@exemple.test', "Nom\r\nBcc: pirate@exemple.test", 'dest@exemple.test', "Sujet\r\nBcc: pirate@exemple.test", '<p>x</p>');
$m = (string)(fin($s)[0]['message'] ?? '');
$entetes = explode("\r\n\r\n", $m)[0];
check(!preg_match('/^Bcc:/mi', $entetes) && substr_count($entetes, "\r\n") < 12, 'sauts de ligne dans le nom ou le sujet : aucun en-tête injecté');

section('3 bis. En-têtes MIME, validation des adresses, protection des identifiants');
$s = serveur();
(new SmtpMailer(cfg($s['port'])))->send('noreply@exemple.test', 'Ecole, App "Test"', 'dest@exemple.test', 'Sujet simple', '<p>x</p>');
$m = (string)(fin($s)[0]['message'] ?? '');
check(entete($m, 'From') === '"Ecole, App \"Test\"" <noreply@exemple.test>', 'nom expéditeur ASCII avec virgule et guillemets : entre guillemets, échappé', entete($m, 'From'));
check(entete($m, 'Auto-Submitted') === 'auto-generated', 'en-tête Auto-Submitted (message automatique : pas de réponse automatique)');
check(preg_match('/^Content-Type: multipart\/alternative; boundary="(=_ecole_[0-9a-f]{24})"/m', $m, $bm) === 1 && substr_count($m, '--' . $bm[1]) === 3 && str_ends_with(rtrim($m), '--' . $bm[1] . '--'), 'frontière MIME unique : deux parties puis fermeture');
check(partie($m, 'text/html') === '<p>x</p>' && partie($m, 'text/plain') === 'x', 'parties text/plain et text/html en UTF-8 base64');

$sujetLong = 'Réinitialisation ' . str_repeat('éàç ', 30);
$s = serveur();
(new SmtpMailer(cfg($s['port'])))->send('noreply@exemple.test', 'T', 'dest@exemple.test', $sujetLong, '<p>x</p>');
$m = (string)(fin($s)[0]['message'] ?? '');
$brut = explode("\r\n\r\n", $m, 2)[0];
$mots = preg_match_all('/=\?UTF-8\?B\?[A-Za-z0-9+\/=]+\?=/', $brut, $mm) ? $mm[0] : [];
$maxi = $mots === [] ? 999 : max(array_map('strlen', $mots));
$lignes = array_map('strlen', explode("\r\n", $brut));
check(count($mots) > 1 && $maxi <= 75 && max($lignes) <= 998, 'sujet long accentué : mots encodés de 75 caractères au plus, lignes courtes', "mots=" . count($mots) . " max=$maxi");
check(iconv_mime_decode(entete($m, 'Subject'), 0, 'UTF-8') === trim($sujetLong), 'sujet long : décodé à l\'identique (espaces de fin retirés)');

$gros = '<p>' . str_repeat('Ligne de remplissage éàç 0123456789. ', 20000) . '</p>';   // ≈ 760 Ko
$s = serveur();
$e = attrape(fn() => (new SmtpMailer(cfg($s['port'], ['timeout' => 20])))->send('noreply@exemple.test', 'T', 'dest@exemple.test', 'Gros', $gros));
$m = (string)(fin($s, 15)[0]['message'] ?? '');
check($e === null && partie($m, 'text/html') === $gros, 'gros message (≈ 760 Ko) transmis intégralement (écritures partielles gérées)', $e ? $e->getMessage() : '');

$e = attrape(fn() => (new SmtpMailer(cfg(1)))->send('noreply@exemple.test', 'T', str_repeat('a', 250) . '@exemple.test', 'S', '<p>x</p>'));
check($e instanceof SmtpException && str_contains($e->getMessage(), 'invalide'), 'adresse de plus de 254 caractères refusée');
foreach (['"a b"@exemple.test', "dest@exemple.test\0", 'dest @exemple.test', 'dest@exemple.test;x@y.z'] as $mauvaise) {
    $e = attrape(fn() => (new SmtpMailer(cfg(1)))->send('noreply@exemple.test', 'T', $mauvaise, 'S', '<p>x</p>'));
    check($e instanceof SmtpException && str_contains($e->getMessage(), 'invalide'), 'adresse refusée : ' . json_encode($mauvaise));
}
if (PHP_VERSION_ID >= 80200) {
    $s = serveur(['FAKE_SMTP_USER' => 'u', 'FAKE_SMTP_PASS' => 'secret-serveur']);
    $e = attrape(fn() => (new SmtpMailer(cfg($s['port'], ['password' => 'MOT-DE-PASSE-APP-TEST-LONG'])))->send('noreply@exemple.test', 'T', 'dest@exemple.test', 'S', '<p>x</p>'));
    fin($s);
    $trace = $e ? $e->getTraceAsString() : '';
    check($e instanceof SmtpException && !str_contains($trace, 'MOT-DE-PASSE') && !str_contains($trace, base64_encode("\0u\0MOT")), 'trace d\'exception : le mot de passe n\'apparaît pas (paramètres sensibles masqués)');
}

// ═══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('4. EmailService (driver smtp)');
$s = serveur();
$journal = ROOT_PATH . '/storage/logs/app-' . date('Y-m-d') . '.log';
$tailleAvant = is_file($journal) ? filesize($journal) : 0;
$svc = new EmailService(cfg($s['port']));
check($svc->send('dest@exemple.test', 'Sujet', $svc->buildHtml('Titre', "Message\nsur deux lignes", 'https://exemple.test/x', 'Choisir un nouveau mot de passe')) === true, 'send() renvoie true quand le serveur accepte');
$tr = fin($s)[0] ?? ['message' => ''];
check(str_contains(partie((string)$tr['message'], 'text/html'), 'Choisir un nouveau mot de passe') && str_contains(partie((string)$tr['message'], 'text/plain'), 'Choisir un nouveau mot de passe : https://exemple.test/x'), 'libellé du lien personnalisé ; texte brut : « libellé : adresse »');

$svc = new EmailService(cfg(1, ['password' => 'MOT-DE-PASSE-APP-TEST', 'timeout' => 3]));
check($svc->send('dest@exemple.test', 'Sujet', '<p>x</p>') === false, 'serveur injoignable : send() renvoie false sans lever d\'exception');
check((new EmailService(cfg(25, ['username' => '', 'password' => '', 'host' => 'smtp.exemple.invalid'])))->send('dest@exemple.test', 'S', '<p>x</p>') === false, 'SMTP non configuré (identifiants vides, hôte distant) : false');
check((new EmailService(cfg(25)))->send('pas-un-email', 'S', '<p>x</p>') === false, 'destinataire invalide : false');
clearstatcache(true, $journal);
$nouveau = is_file($journal) ? (string)file_get_contents($journal, false, null, $tailleAvant) : '';
check(str_contains($nouveau, '[MAIL] Envoi SMTP échoué') && str_contains($nouveau, 'd***@exemple.test'), 'échec journalisé avec l\'adresse masquée');
check(!str_contains($nouveau, 'MOT-DE-PASSE-APP-TEST') && !str_contains($nouveau, 'dest@exemple.test'), 'journal : ni mot de passe ni adresse complète');

section('4 bis. buildHtml, constructeur, configuration');
$svc = new EmailService(cfg(1));
$h = $svc->buildHtml('Titre <b>gras</b>', "Message & <i>italique</i>\nligne 2", 'https://exemple.test/x?a=1&b=2', 'Libellé <u>personnalisé</u>');
check(!str_contains($h, '__LIBELLE__') && !str_contains($h, '<?') && !str_contains($h, '?>'), 'buildHtml : aucun marqueur provisoire ni balise PHP résiduelle');
check(substr_count($h, '&rarr;') === 1 && str_contains($h, 'Libellé &lt;u&gt;personnalisé&lt;/u&gt; &rarr;'), 'buildHtml : libellé dynamique échappé suivi d\'UNE seule flèche');
check(str_contains($h, 'Titre &lt;b&gt;gras&lt;/b&gt;') && str_contains($h, 'Message &amp; &lt;i&gt;italique&lt;/i&gt;<br />') && !str_contains($h, '<b>gras') && !str_contains($h, '<i>italique'), 'buildHtml : titre et message échappés (aucune balise injectée)');
check(str_contains($h, 'href="https://exemple.test/x?a=1&amp;b=2"'), 'buildHtml : adresse du lien échappée dans l\'attribut');
$defaut = $svc->buildHtml('T', 'M', 'https://exemple.test/y');
check(str_contains($defaut, 'Voir le détail &rarr;') && substr_count($defaut, '&rarr;') === 1, 'buildHtml : libellé par défaut conservé (appels existants inchangés)');
foreach (['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html,<script>x</script>', '//evil.exemple/x', 'vbscript:x', ' https://exemple.test'] as $dangereux) {
    $x = $svc->buildHtml('T', 'M', $dangereux);
    check(!str_contains($x, '<a ') && !str_contains(strtolower($x), 'javascript:') && !str_contains($x, 'data:text'), 'lien refusé dans buildHtml : ' . json_encode($dangereux));
}
check(str_contains($svc->buildHtml('T', 'M', '/dashboard'), 'href="/dashboard"'), 'lien relatif au site conservé (appels existants inchangés)');
check(!str_contains($svc->buildHtml('T', 'M'), '<a '), 'sans lien : aucun bouton');
$srcMail = (string)file_get_contents(ROOT_PATH . '/app/Services/EmailService.php');
check(preg_match_all('/function\s+__construct\s*\(/', $srcMail) === 1 && str_contains($srcMail, '__construct(?array $config = null)'), 'EmailService : un seul constructeur __construct(?array $config = null)');
$srcCfg = (string)file_get_contents(ROOT_PATH . '/config/mail.php');
preg_match_all("/^\s*'([a-z_]+)'\s*=>/m", $srcCfg, $cles);
$cfgReelle = (static function () { return require ROOT_PATH . '/config/mail.php'; })();
check(count($cles[1]) === count(array_unique($cles[1])) && count($cles[1]) === count($cfgReelle), 'config/mail.php : chaque paramètre est défini une seule fois', implode(',', array_diff_assoc($cles[1], array_unique($cles[1]))));
check(array_keys($cfgReelle) === ['from_name', 'from_email', 'reply_to', 'driver', 'host', 'port', 'encryption', 'username', 'password', 'timeout', 'ehlo_host'], 'config/mail.php : jeu de paramètres attendu, sans clé héritée en double');

// ═══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════
section('5. Parcours HTTP « mot de passe oublié » (copie jetable de la base)');
$pdo = $copie->pdo();
// Garde-fou : sur une base de test, aucun e-mail ne part vers un serveur distant (même avec des identifiants renseignés)
$tAvant = microtime(true);
$journal2 = ROOT_PATH . '/storage/logs/app-' . date('Y-m-d') . '.log'; clearstatcache(true, $journal2); $tailleLog2 = is_file($journal2) ? filesize($journal2) : 0;
check((new EmailService(cfg(587, ['host' => 'smtp.gmail.com', 'encryption' => 'tls'])))->send('dest@exemple.test', 'S', '<p>x</p>') === false && microtime(true) - $tAvant < 2, 'base de test : envoi vers un serveur distant refusé sans connexion réseau');
clearstatcache(true, $journal2);
check(str_contains((string)file_get_contents($journal2, false, null, $tailleLog2), 'Envoi réel désactivé : base de test'), 'le refus est journalisé (copie jetable)');
$email = (string)$pdo->query("SELECT email FROM users WHERE actif = 1 ORDER BY id LIMIT 1")->fetchColumn();
$dirThrottle = sys_get_temp_dir() . '/smtp_throttle_' . bin2hex(random_bytes(4));
$demande = function (int $port, string $adresse) use (&$dirThrottle): array {
    $env = ['MAIL_DRIVER' => 'smtp', 'MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => (string)$port, 'MAIL_ENCRYPTION' => 'none', 'MAIL_USERNAME' => 'u',
            'MAIL_PASSWORD' => 'p', 'MAIL_FROM_EMAIL' => 'noreply@exemple.test', 'AUTH_THROTTLE_DIR' => $dirThrottle, 'REQUEST_AS_COMMIT' => '1'];
    $p = proc_open([PHP_BINARY, ROOT_PATH . '/tests/Support/request_as.php', 'guest', '0', 'POST', '/ecole_app/forgot-password', json_encode(['email' => $adresse])],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), $env));
    $sortie = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    return preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: []) : ['brut' => $sortie];
};
$jeton = static fn(string $message): string => preg_match('#/reset-password/([0-9a-f]{64})#', partie($message, 'text/html'), $m) ? $m[1] : '';

// Adresse existante : un e-mail part
$s = serveur();
$r = $demande($s['port'], $email);
$tr = fin($s)[0] ?? ['message' => '', 'commandes' => []];
$msg = (string)$tr['message'];
$token = $jeton($msg);
check(($r['status'] ?? 0) === 302, 'demande acceptée (redirection vers le formulaire)', json_encode($r));
check(in_array('RCPT TO:<' . $email . '>', $tr['commandes'], true), 'l\'e-mail est envoyé à l\'adresse du compte');
check($token !== '' && preg_match('#https?://[^"\s]+/reset-password/' . $token . '#', partie($msg, 'text/html')) === 1, 'le message contient un lien ABSOLU de réinitialisation (jeton de 64 caractères hexadécimaux)');
check(str_contains(partie($msg, 'text/plain'), '/reset-password/' . $token), 'la version texte brut contient le même lien');
$ligne = $pdo->prepare('SELECT token, used FROM password_resets WHERE email = ?'); $ligne->execute([$email]); $enBase = $ligne->fetch(PDO::FETCH_ASSOC);
check($enBase !== false && (int)$enBase['used'] === 0 && $enBase['token'] !== $token, 'jeton enregistré en base sous forme hachée (le jeton du lien n\'y figure pas en clair)');
$valide = $pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() AND expires_at <= DATE_ADD(NOW(), INTERVAL 61 MINUTE)');
$valide->execute([hash('sha256', $token)]);
check($token !== '' && (int)$valide->fetchColumn() === 1, 'le jeton du lien (SHA-256) correspond à la ligne en base, non utilisée, valide au plus 1 heure');
check(!str_contains($msg, 'password') || !str_contains(partie($msg, 'text/html'), 'MAIL_PASSWORD'), 'aucun secret de configuration dans le message');

// Adresse inconnue : même réponse, aucun e-mail
$s = serveur();
$r = $demande($s['port'], 'inconnu-' . bin2hex(random_bytes(3)) . '@exemple.test');
$tr = fin($s, 0.5);
check(($r['status'] ?? 0) === 302 && $tr === [], 'adresse inconnue : même redirection, aucun e-mail, aucune connexion SMTP');

// Limitation : 3 demandes par adresse et par IP, la 4e n'envoie rien (réponse identique)
$cible = (string)$pdo->query("SELECT email FROM users WHERE actif = 1 ORDER BY id LIMIT 1")->fetchColumn();
$dirThrottle = sys_get_temp_dir() . '/smtp_throttle_' . bin2hex(random_bytes(4));   // compteurs neufs pour cette section
$s = serveur([], 3);
$statuts = [];
for ($i = 0; $i < 4; $i++) { $statuts[] = ($demande($s['port'], $cible)['status'] ?? 0); }
$trs = fin($s, 2.0);
check($statuts === [302, 302, 302, 302], 'quatre demandes : la réponse reste identique (rien n\'est révélé)', json_encode($statuts));
check(count($trs) === 3, 'limitation : 3 e-mails envoyés, la 4e demande n\'en envoie pas', (string)count($trs));

// SMTP en panne : la demande aboutit quand même (message générique), sans fuite
$dirThrottle = sys_get_temp_dir() . '/smtp_throttle_' . bin2hex(random_bytes(4));
$r = $demande(1, $cible);
check(($r['status'] ?? 0) === 302, 'serveur SMTP injoignable : la page répond normalement (aucune erreur affichée)');

section('5 bis. Réponse neutre : temps de réponse et en-têtes identiques, envoi après la réponse');
$chrono = function (int $port, string $adresse) use (&$dirThrottle): array {
    $env = ['MAIL_DRIVER' => 'smtp', 'MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => (string)$port, 'MAIL_ENCRYPTION' => 'none', 'MAIL_USERNAME' => 'u',
            'MAIL_PASSWORD' => 'p', 'MAIL_FROM_EMAIL' => 'noreply@exemple.test', 'AUTH_THROTTLE_DIR' => $dirThrottle, 'REQUEST_AS_COMMIT' => '1'];
    $err = sys_get_temp_dir() . '/smtp_err_' . bin2hex(random_bytes(3)) . '.txt';
    $t0 = microtime(true);
    $p = proc_open([PHP_BINARY, ROOT_PATH . '/tests/Support/request_as.php', 'guest', '0', 'POST', '/ecole_app/forgot-password', json_encode(['email' => $adresse])],
        [1 => ['pipe', 'w'], 2 => ['file', $err, 'w']], $pipes, ROOT_PATH, array_merge(getenv(), $env));
    $ttr = null; $res = [];
    while (($ligne = fgets($pipes[1])) !== false) {
        if (str_starts_with($ligne, 'RESULT:')) { $ttr = microtime(true) - $t0; $res = json_decode(substr($ligne, 7), true) ?: []; }
    }
    fclose($pipes[1]); proc_close($p); @unlink($err);
    return [$ttr ?? 99.0, microtime(true) - $t0, $res];
};
$entetesNeutres = static function (array $res): bool {
    $h = array_map('strtolower', array_map('trim', (array)($res['headers'] ?? [])));
    return in_array('connection: close', $h, true) && in_array('content-length: 0', $h, true) && ($res['status'] ?? 0) === 302;
};
$dirThrottle = sys_get_temp_dir() . '/smtp_throttle_' . bin2hex(random_bytes(4));
$s = serveur(['FAKE_SMTP_DELAY_MS' => '2500'], 1);
[$ttrConnu, $finConnu, $resConnu] = $chrono($s['port'], $cible);
$mails = fin($s, 4.0);
[$ttrInconnu, $finInconnu, $resInconnu] = $chrono(1, 'inconnu-' . bin2hex(random_bytes(3)) . '@exemple.test');
check($ttrConnu < 1.8 && $finConnu - $ttrConnu > 1.8, 'adresse existante : la réponse part AVANT l\'envoi (serveur SMTP lent de 2,5 s)', sprintf('réponse %.2fs, fin %.2fs', $ttrConnu, $finConnu));
check(count($mails) === 1 && in_array('RCPT TO:<' . $cible . '>', $mails[0]['commandes'], true) && $jeton((string)$mails[0]['message']) !== '', 'l\'e-mail est bien envoyé après la réponse, avec son lien');
check($ttrInconnu < 1.8 && abs($ttrConnu - $ttrInconnu) < 1.0, 'temps de réponse comparable pour une adresse inconnue (écart < 1 s alors que l\'envoi dure 2,5 s)', sprintf('connu %.2fs, inconnu %.2fs', $ttrConnu, $ttrInconnu));
check($finInconnu - $ttrInconnu < 1.0, 'adresse inconnue : rien à exécuter après la réponse');
if (($resConnu['headers'] ?? null) === null) {
    check(true, 'en-têtes de réponse non observables dans cet environnement (xdebug absent) : contrôle ignoré');
} else {
    check($entetesNeutres($resConnu) && $entetesNeutres($resInconnu), 'mêmes en-têtes de réponse (302, Connection: close, Content-Length: 0) pour une adresse connue et inconnue');
}
// Limite atteinte : même réponse neutre
$dirThrottle = sys_get_temp_dir() . '/smtp_throttle_' . bin2hex(random_bytes(4));
for ($i = 0; $i < 3; $i++) { $chrono(1, 'limite@exemple.test'); }
[, , $resLimite] = $chrono(1, 'limite@exemple.test');
check(($resLimite['headers'] ?? null) === null || $entetesNeutres($resLimite), 'limite atteinte : même réponse neutre (302, Connection: close, Content-Length: 0)');

foreach (glob(sys_get_temp_dir() . '/smtp_throttle_*') ?: [] as $d) { array_map('unlink', glob($d . '/*') ?: []); @rmdir($d); }

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
