<?php
/**
 * Tests — logo propre à chaque établissement sur ses documents ; le logo EduNova reste celui de la plateforme.
 *
 *  1. BrandingData : logo propre / logo de la plateforme / aucun logo
 *  2. Isolation : deux écoles, deux logos, aucune fuite ; école sans logo = aucun logo (jamais celui d'EduNova)
 *  3. En-tête commun des documents (partial) et bulletins (infoEtablissement)
 *  4. Envoi sécurisé : types, taille, dimensions, SVG actif, extension déduite du contenu
 *  5. Paramètres → Apparence : affichage, retrait du logo, permissions, conservation des anciens fichiers
 *  6. Couverture : tous les documents imprimables utilisent le logo propre ; la plateforme garde EduNova
 *
 * Copie jetable de la base ; cache branding isolé (disque temporaire) pour ne jamais polluer le cache réel.
 *
 * Lance : C:\wamp64\bin\php\php8.3.28\php.exe tests/Unit/LogoEtablissementTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
define('BASE_URL', '/ecole_app');
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\' => '/app/'] as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php';
            if (is_file($f)) { require $f; return; }
        }
    }
});
require_once ROOT_PATH . '/tests/Support/DbCopy.php';
if (!function_exists('e')) { function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }   // aide d'échappement des vues

use App\Modules\Academique\Repositories\BulletinRepository;
use Core\Cache\CacheManager;
use Core\Cache\FileCache;
use Core\Database;
use Core\Tenant\BrandingData;
use Core\Tenant\BrandingService;
use Core\Tenant\IdentiteDocument;
use Core\Tenant\TenantContext;
use Core\Tenant\TenantStorageService;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
function leve(callable $f): ?string { try { $f(); return null; } catch (\Throwable $e) { return $e->getMessage(); } }
function http(string $nomCopie, string $role, int $uid, string $methode, string $uri, array $post = [], bool $commit = false): array
{
    $corps = sys_get_temp_dir() . '/logo_' . bin2hex(random_bytes(4)) . '.html';
    $p = proc_open([PHP_BINARY, ROOT_PATH . '/tests/Support/request_as.php', $role, (string)$uid, $methode, '/ecole_app' . $uri, json_encode($post)],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH,
        array_merge(getenv(), ['REQUEST_AS_DB' => $nomCopie, 'REQUEST_AS_BODY_OUT' => $corps] + ($commit ? ['REQUEST_AS_COMMIT' => '1'] : [])));
    $sortie = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    $r = preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: []) : [];
    $body = (string)@file_get_contents($corps); @unlink($corps);
    return ['status' => (int)($r['status'] ?? 0), 'body' => $body];
}

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$admin = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' AND etablissement_id = 1 ORDER BY id LIMIT 1")->fetchColumn();

// Cache branding EN MÉMOIRE DISQUE TEMPORAIRE pour ce processus ; les sous-processus HTTP utilisent le cache réel : nettoyé en fin de test.
$dirCache = sys_get_temp_dir() . '/logo_cache_' . bin2hex(random_bytes(4));
CacheManager::setDriver(new FileCache($dirCache));
$aNettoyer = [];   // fichiers créés dans public/uploads/branding/
register_shutdown_function(function () use (&$aNettoyer, $dirCache): void {
    foreach ($aNettoyer as $f) { if (is_file($f)) { @unlink($f); } $d = dirname($f); if (is_dir($d) && count(scandir($d)) === 2) { @rmdir($d); } }
    try { \Core\Tenant\TenantCache::make()->forgetCategory(1, 'branding'); } catch (\Throwable) {}   // jamais de résidu du test dans le cache de l'application
    $reel = new FileCache(ROOT_PATH . '/storage/cache'); $reel->flushPrefix('tenant:1:branding:');
    foreach (glob($dirCache . '/*') ?: [] as $f) { @unlink($f); } @rmdir($dirCache);
});
$png = function (string $chemin, int $w = 40, int $h = 40) use (&$aNettoyer): void {
    if (!is_dir(dirname($chemin))) { mkdir(dirname($chemin), 0755, true); }
    $im = imagecreatetruecolor($w, $h); imagepng($im, $chemin); $aNettoyer[] = $chemin;
};

// ═══════════════════════════════════════════════════════════
section('1. BrandingData : logo propre ou logo de la plateforme');
$base = BrandingData::defaults(1);
check($base->aUnLogoPropre() === false && $base->logoPourDocuments() === null, 'logo de repli (EduNova) : pas un logo propre, jamais imprimé');
$mk = fn(?string $logo) => new BrandingData(1, $logo, $logo, null, '#7c3aed', '#0ea5e9', 'Ecole X', null, 'Inter', 'light', null, null, null, null, null, true);
check($mk('/assets/img/edunova-logo.png')->logoPourDocuments() === null && $mk('/assets/img/EduNova-logo-dark.svg')->logoPourDocuments() === null, 'visuels EduNova (quelle que soit la casse ou la variante) : exclus des documents');
check($mk(null)->logoPourDocuments() === null && $mk('')->logoPourDocuments() === null && $mk('   ')->aUnLogoPropre() === false, 'logo absent, vide ou blanc : aucun logo');
check($mk('/uploads/branding/7/logo_ab12.png')->logoPourDocuments() === '/uploads/branding/7/logo_ab12.png', 'logo téléversé par l\'établissement : imprimé');
check($mk('/assets/img/logo.jpeg')->aUnLogoPropre() === true, 'logo propre déjà en place (/assets/img/logo.jpeg) : conservé');

// ═══════════════════════════════════════════════════════════
section('2. Isolation entre établissements');
$mkEtab = function (string $slug, string $nom) use ($pdo): int {
    $pdo->prepare("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES (?, ?, ?, 'lycee')")->execute([$slug, $nom, substr($nom, 0, 10)]);
    return (int)$pdo->lastInsertId();
};
$E2 = $mkEtab('ecole-alpha', 'Lycée Alpha'); $E3 = $mkEtab('ecole-beta', 'Collège Beta'); $E4 = $mkEtab('ecole-sans-logo', 'Ecole Sans Logo');
$png(ROOT_PATH . "/public/uploads/branding/$E2/logo_alpha.png");
$png(ROOT_PATH . "/public/uploads/branding/$E3/logo_beta.png");
$brandingSql = $pdo->prepare('INSERT INTO etablissement_branding (etablissement_id, logo_url, app_name) VALUES (?, ?, ?)');
$brandingSql->execute([$E2, "/uploads/branding/$E2/logo_alpha.png", 'Lycée Alpha']);
$brandingSql->execute([$E3, "/uploads/branding/$E3/logo_beta.png", 'Collège Beta']);
$brandingSql->execute([$E4, null, 'EduNova']);   // école créée sans logo : le nom par défaut est celui de la plateforme
$svc = BrandingService::make();
$i2 = IdentiteDocument::pour($E2); $i3 = IdentiteDocument::pour($E3); $i4 = IdentiteDocument::pour($E4);
check($svc->get($E2)->logoPourDocuments() === "/uploads/branding/$E2/logo_alpha.png" && $svc->get($E3)->logoPourDocuments() === "/uploads/branding/$E3/logo_beta.png", 'chaque école lit son propre logo');
check(str_ends_with((string)$i2['logo_src'], "/uploads/branding/$E2/logo_alpha.png") && str_ends_with((string)$i3['logo_src'], "/uploads/branding/$E3/logo_beta.png"), 'identité de document : URL du logo de l\'école, préfixe de l\'application inclus (/ecole_app/…)');
check(!str_contains((string)$i2['logo_src'], "branding/$E3/") && !str_contains((string)$i3['logo_src'], "branding/$E2/"), 'aucune fuite : le logo d\'une école n\'apparaît pas dans l\'identité de l\'autre');
check($i2['nom'] === 'Lycée Alpha' && $i3['nom'] === 'Collège Beta', 'nom de chaque école');
check($i4['logo'] === null && $i4['logo_src'] === null, 'école sans logo : aucun logo (le logo EduNova ne le remplace pas)');
check($i4['nom'] === 'Ecole Sans Logo', 'école sans logo ni nom d\'affichage : son nom officiel, jamais « EduNova »', $i4['nom']);
$brandingSql2 = $pdo->prepare("UPDATE etablissement_branding SET logo_url = ? WHERE etablissement_id = ?");
$brandingSql2->execute(["/uploads/branding/$E2/inexistant.png", $E2]); $svc->clearCacheFor($E2);
check(IdentiteDocument::pour($E2)['logo_src'] === null, 'fichier de logo absent du disque : aucune image cassée dans le document');
$brandingSql2->execute(["/uploads/branding/$E2/logo_alpha.png", $E2]); $svc->clearCacheFor($E2);
check(str_contains(json_encode($svc->get(1)), 'edunova') === false || $svc->get(1)->logoPourDocuments() !== null, 'établissement par défaut : son propre logo, pas celui d\'EduNova', (string)$svc->get(1)->logoUrl);

// ═══════════════════════════════════════════════════════════
section('3. En-tête des documents et bulletins');
$rendre = function (array $identite, string $titre = '') {
    ob_start(); $enteteTitre = $titre; include ROOT_PATH . '/app/Views/partials/entete-document.php'; return (string)ob_get_clean();
};
$h2 = $rendre($i2, 'REÇU'); $h3 = $rendre($i3); $h4 = $rendre($i4);
check(str_contains($h2, 'logo_alpha.png') && str_contains($h2, 'Lycée Alpha') && !str_contains($h2, 'logo_beta') && !str_contains($h2, 'Beta'), 'en-tête de l\'école A : son logo et son nom, rien de l\'école B');
check(str_contains($h3, 'logo_beta.png') && !str_contains($h3, 'logo_alpha') && !str_contains($h3, 'Alpha'), 'en-tête de l\'école B : son logo et son nom, rien de l\'école A');
check(!str_contains($h4, '<img') && str_contains($h4, 'Ecole Sans Logo') && stripos($h4, 'edunova') === false, 'école sans logo : pas d\'image, son nom, aucune mention EduNova');
check(str_contains($h2, 'REÇU'), 'titre de document facultatif affiché');
$rep = new BulletinRepository();
TenantContext::set($E2); $b2 = $rep->infoEtablissement();
TenantContext::set($E4); $b4 = $rep->infoEtablissement();
TenantContext::set(1);   $b1 = $rep->infoEtablissement();
TenantContext::clear();
check($b2['logo'] === "/uploads/branding/$E2/logo_alpha.png", 'bulletins : le logo de l\'école A', (string)$b2['logo']);
check($b4['logo'] === null, 'bulletins : école sans logo = aucun logo (pas EduNova)');
check($b1['logo'] !== null && !str_contains((string)$b1['logo'], 'edunova'), 'bulletins : établissement par défaut = son logo propre');
ob_start(); $etablissement = $b4; include ROOT_PATH . '/app/Modules/Academique/Views/bulletins/partials/header-etablissement.php'; $hb4 = (string)ob_get_clean();
check(!str_contains($hb4, '<img') && !str_contains($hb4, 'Logo</span>'), 'en-tête de bulletin sans logo : cellule vide (ni image, ni pastille « Logo »)');
ob_start(); $etablissement = $b2; include ROOT_PATH . '/app/Modules/Academique/Views/bulletins/partials/header-etablissement.php'; $hb2 = (string)ob_get_clean();
check(str_contains($hb2, '/ecole_app/uploads/branding/' . $E2 . '/logo_alpha.png'), 'en-tête de bulletin : logo préfixé par le dossier de l\'application');

// ═══════════════════════════════════════════════════════════
section('4. Envoi sécurisé du logo');
$tmp = sys_get_temp_dir() . '/logo_up_' . bin2hex(random_bytes(3));
@mkdir($tmp);
$fich = function (string $nom, string $contenu) use ($tmp): string { $f = $tmp . '/' . $nom; file_put_contents($f, $contenu); return $f; };
$valide = fn(string $f) => leve(fn() => TenantStorageService::validerImage($f, (int)filesize($f)));
ob_start(); $im = imagecreatetruecolor(50, 50); imagepng($im); $pngData = (string)ob_get_clean();
check(TenantStorageService::validerImage($fich('ok.png', $pngData), strlen($pngData)) === 'image/png', 'PNG valide accepté');
ob_start(); imagejpeg($im); $jpgData = (string)ob_get_clean();
check(TenantStorageService::validerImage($fich('ok.jpg', $jpgData), strlen($jpgData)) === 'image/jpeg', 'JPEG valide accepté');
check($valide($fich('faux.png', "<?php echo 'x'; ?>")) !== null, 'fichier texte/PHP renommé en .png : refusé');
check($valide($fich('doc.pdf', "%PDF-1.4\n%fake")) !== null, 'PDF : refusé');
$svgOk = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4" fill="#06c"/></svg>';
check(TenantStorageService::validerImage($fich('ok.svg', $svgOk), strlen($svgOk)) === 'image/svg+xml', 'SVG sans code actif : accepté');
foreach ([
    'script'      => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    'onload'      => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><circle r="2"/></svg>',
    'javascript'  => '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><circle r="2"/></a></svg>',
    'externe'     => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><image xlink:href="http://exemple.test/x.png"/></svg>',
    'foreign'     => '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><div>x</div></foreignObject></svg>',
    'entite'      => '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>',
] as $nom => $contenu) {
    check($valide($fich("mal_$nom.svg", $contenu)) !== null, "SVG dangereux ($nom) : refusé");
}
check($valide($fich('gros.png', $pngData . str_repeat("\0", 2 * 1024 * 1024 + 1))) !== null, 'fichier de plus de 2 Mo : refusé');
$im2 = imagecreatetruecolor(5000, 4); ob_start(); imagepng($im2); $large = (string)ob_get_clean();
check($valide($fich('large.png', $large)) !== null, 'image de 5000 px de large : refusée');
$ref = new ReflectionClass(TenantStorageService::class);
$ext = $ref->getReflectionConstant('EXT_PAR_MIME')->getValue();
check($ext['image/png'] === 'png' && $ext['image/svg+xml'] === 'svg' && !in_array('php', $ext, true), 'extension de stockage déduite du contenu (png, jpg, webp, ico, svg) : jamais « php »');
$src = (string)file_get_contents(ROOT_PATH . '/core/Tenant/TenantStorageService.php');
check(!str_contains($src, "pathinfo(\$uploadedFile['name']"), 'le nom fourni par le client n\'est plus utilisé pour l\'extension');
foreach (glob($tmp . '/*') as $f) { @unlink($f); } @rmdir($tmp);

// ═══════════════════════════════════════════════════════════
section('5. Paramètres → Apparence');
$sec = 0;
$pdo->exec("INSERT INTO users (etablissement_id, nom, email, password, role) VALUES (1, 'Secretaire Test', 'secr-logo@exemple.test', 'x', 'secretaire')");
$sec = (int)$pdo->lastInsertId();
$r = http($nomCopie, 'admin', $admin, 'GET', '/parametres/apparence');
check($r['status'] === 200 && str_contains($r['body'], 'name="logo"') && str_contains($r['body'], 'Retirer mon logo') && str_contains($r['body'], 'Le logo EduNova reste celui de la plateforme'), 'page Apparence : champ d\'import, option de retrait, mention de la plateforme');
http($nomCopie, 'secretaire', $sec, 'POST', '/parametres/branding', ['remove_logo' => '1', '_retour' => 'apparence'], true);
check($pdo->query('SELECT logo_url FROM etablissement_branding WHERE etablissement_id = 1')->fetchColumn() === '/assets/img/logo.jpeg', 'rôle sans branding.update : ne peut pas modifier le logo');
$r = http($nomCopie, 'secretaire', $sec, 'GET', '/parametres/apparence');
check(!str_contains($r['body'], 'name="logo"') || $r['status'] !== 200, 'rôle sans branding.view : page refusée');
http($nomCopie, 'admin', $admin, 'POST', '/parametres/branding', ['remove_logo' => '1', '_retour' => 'apparence'], true);
check($pdo->query('SELECT logo_url FROM etablissement_branding WHERE etablissement_id = 1')->fetchColumn() === null, 'administrateur : « Retirer mon logo » enregistre l\'absence de logo');
check(is_file(ROOT_PATH . '/public/assets/img/logo.jpeg'), 'le fichier lui-même n\'est pas supprimé (documents déjà émis)');
$a = $pdo->query("SELECT apres FROM audit_logs WHERE entite = 'branding' ORDER BY id DESC LIMIT 1")->fetchColumn();
check($a !== false && str_contains((string)$a, 'logo_url'), 'audit : le retrait du logo est tracé', (string)$a);
$r = http($nomCopie, 'admin', $admin, 'GET', '/parametres/apparence');
check(str_contains($r['body'], 'Aucun logo propre') && !str_contains($r['body'], 'Retirer mon logo'), 'après retrait : message « Aucun logo propre », plus de case de retrait');
\Core\Tenant\TenantCache::make()->forgetCategory(1, 'branding');
check(IdentiteDocument::pour(1)['logo_src'] === null, 'après retrait : les documents n\'impriment aucun logo (pas celui d\'EduNova)');
$ctl = (string)file_get_contents(ROOT_PATH . '/app/Controllers/SettingsController.php');
check(!preg_match('/updateBranding.*?storage->delete/s', $ctl), 'remplacer le logo ne supprime plus l\'ancien fichier (les bulletins émis le référencent)');

// ═══════════════════════════════════════════════════════════
section('6. Couverture des documents et logo de la plateforme');
$docs = [
    'bulletins (en-tête)'          => ['app/Modules/Academique/Views/bulletins/partials/header-etablissement.php', 'logo'],
    'bulletins (export HTML)'      => ['app/Modules/Academique/Services/BulletinGenerator.php', 'BASE_URL'],
    'reçus de paiement'            => ['app/Modules/Finance/Views/paiements/recu_print.php', 'IdentiteDocument'],
    'factures'                     => ['app/Modules/Finance/Views/factures/print.php', 'IdentiteDocument'],
    'journal de caisse'            => ['app/Modules/Finance/Views/caisse/journal_print.php', 'entete-document'],
    'rapports financiers'          => ['app/Modules/Finance/Views/rapports/print.php', 'entete-document'],
    'rapport comptable'            => ['app/Views/comptabilite/rapport_print.php', 'entete-document'],
    'rapports de direction'        => ['app/Views/rapports/print.php', 'entete-document'],
    'liste des élèves (PDF)'       => ['app/Views/eleves/export-pdf.php', 'entete-document'],
];
foreach ($docs as $nom => [$fichier, $motif]) {
    check(str_contains((string)file_get_contents(ROOT_PATH . '/' . $fichier), $motif), "document « $nom » : utilise l'identité/logo de l'école");
}
foreach (['app/Modules/Finance/Views/paiements/recu_print.php', 'app/Modules/Finance/Views/factures/print.php', 'app/Views/eleves/export-pdf.php', 'app/Modules/Finance/Views/rapports/print.php'] as $f) {
    $c = (string)file_get_contents(ROOT_PATH . '/' . $f);
    $c = preg_replace('#/\*.*?\*/#s', '', $c);   // les commentaires ne s'impriment pas
    check(!str_contains($c, '->logoUrl') && !preg_match('/edunova|Ecole App|Finance V2/i', $c), "$f : ni logo brut du branding, ni nom de plateforme codé en dur");
}
$pf = (string)file_get_contents(ROOT_PATH . '/app/Views/layouts/platform-auth.php');
check(str_contains($pf, '/assets/img/edunova-logo.png'), 'écran de connexion de la plateforme : logo EduNova inchangé');
check(is_file(ROOT_PATH . '/public/assets/img/edunova-logo.png'), 'fichier du logo EduNova présent');

section('7. Portail Super-Admin : identité de la plateforme, jamais celle d\'un établissement');
$pl = BrandingData::plateforme();
check($pl->logoUrl === '/assets/img/edunova-logo.png' && $pl->faviconUrl === '/assets/img/edunova-logo.png' && $pl->appName === 'EduNova' && $pl->aUnLogoPropre() === false, 'identité de la plateforme : logo, favicon et nom EduNova');
foreach (['app/Views/layouts/platform.php', 'app/Views/layouts/platform-auth.php'] as $f) {
    $c = (string)file_get_contents(ROOT_PATH . '/' . $f);
    check(str_contains($c, 'BrandingData::plateforme()') && !str_contains($c, 'BrandingService'), "$f : n'utilise plus le branding d'un établissement");
}
// La section 5 a retiré le logo de l'établissement par défaut : on le rétablit (copie jetable) pour que ce contrôle détecte une fuite de son logo
$pdo->exec("UPDATE etablissement_branding SET logo_url = '/assets/img/logo.jpeg' WHERE etablissement_id = 1");
(new FileCache(ROOT_PATH . '/storage/cache'))->flushPrefix('tenant:1:branding:');
$logoEtab1 = (string)$pdo->query('SELECT logo_url FROM etablissement_branding WHERE etablissement_id = 1')->fetchColumn();
$operateur = json_encode(['id' => 1, 'email' => 'op@exemple.test', 'nom' => 'Op', 'niveau' => 'super_admin', 'must_change' => false]);
$platHttp = function (string $op, string $uri) use ($nomCopie): array {
    $corps = sys_get_temp_dir() . '/plat_' . bin2hex(random_bytes(4)) . '.html';
    $p = proc_open([PHP_BINARY, ROOT_PATH . '/tests/Support/platform_request.php', $op, 'GET', '/ecole_app' . $uri, '{}'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH,
        array_merge(getenv(), ['REQUEST_AS_DB' => $nomCopie, 'REQUEST_AS_BODY_OUT' => $corps]));
    $sortie = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    $r = preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: []) : [];
    $body = (string)@file_get_contents($corps); @unlink($corps);
    return ['status' => (int)($r['status'] ?? 0), 'body' => $body];
};
check($logoEtab1 !== '' && !str_contains($logoEtab1, 'edunova'), 'prérequis : l\'établissement par défaut a son propre logo (' . $logoEtab1 . ')');
$r = $platHttp($operateur, '/platform/dashboard');
check($r['status'] === 200 && str_contains($r['body'], 'edunova-logo.png') && !str_contains($r['body'], $logoEtab1), 'tableau de bord Super-Admin : logo EduNova, aucune trace du logo de l\'établissement', (string)$r['status']);
$r = $platHttp('guest', '/platform/login');
check($r['status'] === 200 && str_contains($r['body'], 'edunova-logo.png') && !str_contains($r['body'], $logoEtab1), 'connexion Super-Admin : logo et favicon EduNova, aucune trace du logo de l\'établissement', (string)$r['status']);

echo "\n" . ($failed === 0 ? "\033[32m" : "\033[31m") . "$passed OK, $failed KO\033[0m\n";
exit($failed === 0 ? 0 : 1);
