<?php
/**
 * Tests — ressources front servies localement (Tailwind compilé, Font Awesome, Lucide, Chart.js) : aucun CDN, feuilles présentes et bien placées,
 * CSP sans hôte CDN, service worker sans pré-cache externe, aucune classe Tailwind contradictoire (hors motif volontaire « flex hidden »),
 * couverture des couleurs construites dynamiquement dans la feuille compilée. Test statique : aucune base de données.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/AssetsLocauxTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

/** @return list<string> tous les fichiers PHP de app/ */
function fichiersPhp(): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_PATH . '/app', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { if (str_ends_with((string)$f, '.php')) { $out[] = str_replace('\\', '/', (string)$f); } }
    return $out;
}

section('1. Aucune ressource CDN dans le code servi');
$hotes = '#cdn\.tailwindcss\.com|jsdelivr\.net|unpkg\.com|cdnjs\.cloudflare\.com#';
$fautifs = [];
foreach (array_merge(fichiersPhp(), [ROOT_PATH . '/public/offline.html', ROOT_PATH . '/public/manifest.json']) as $f) {
    if (preg_match($hotes, (string)file_get_contents($f))) { $fautifs[] = basename($f); }
}
check($fautifs === [], 'aucun gabarit, vue ni page statique ne référence un CDN (Tailwind, jsdelivr, unpkg, cdnjs)', implode(', ', $fautifs));
$sw = (string)file_get_contents(ROOT_PATH . '/public/sw.js');
check(str_contains($sw, 'const CDN_ASSETS = [];') && !preg_match('#https://cdn\.#', $sw), 'service worker : plus aucun pré-cache externe (l\'installation ne dépend plus d\'Internet)');
check(str_contains($sw, 'assets/vendor/tailwind/tailwind-preflight.css'), 'service worker : la feuille de la page hors ligne est dans l\'App Shell');

section('2. Fichiers locaux présents');
$attendus = ['tailwind/tailwind.css' => 100000, 'tailwind/tailwind-preflight.css' => 100000, 'fontawesome/css/all.min.css' => 50000, 'fontawesome/webfonts/fa-solid-900.woff2' => 100000,
    'fontawesome/webfonts/fa-regular-400.woff2' => 10000, 'fontawesome/webfonts/fa-brands-400.woff2' => 50000, 'lucide/lucide.min.js' => 100000, 'chartjs/chart.umd.js' => 100000];
foreach ($attendus as $rel => $min) {
    $f = ROOT_PATH . '/public/assets/vendor/' . $rel;
    check(is_file($f) && filesize($f) >= $min, "vendor/$rel présent (" . (is_file($f) ? filesize($f) : 0) . ' octets)');
}

section('3. Feuille Tailwind placée en FIN de <head>, après app.css (même cascade qu\'avec le CDN)');
$layouts = ['app/Views/layouts/main.php' => 'tailwind.css', 'app/Views/layouts/auth.php' => 'tailwind.css', 'app/Views/layouts/platform.php' => 'tailwind.css', 'app/Views/layouts/platform-auth.php' => 'tailwind.css', 'app/Views/home/index.php' => 'tailwind.css',
    'app/Views/layouts/print.php' => 'tailwind-preflight.css', 'app/Views/eleves/export-pdf.php' => 'tailwind-preflight.css', 'app/Views/errors/403.php' => 'tailwind-preflight.css', 'app/Modules/Portals/Views/layouts/portal-base.php' => 'tailwind-preflight.css',
    'app/Modules/Rapports/Views/dashboards/_layout.php' => 'tailwind-preflight.css', 'app/Modules/Rapports/Views/exports/apercu.php' => 'tailwind-preflight.css', 'app/Modules/Communication/Views/templates/preview.php' => 'tailwind-preflight.css', 'app/Modules/Documents/Views/shares/public.php' => 'tailwind-preflight.css'];
foreach ($layouts as $rel => $feuille) {
    $s = (string)file_get_contents(ROOT_PATH . '/' . $rel);
    $iLien = strpos($s, 'assets/vendor/tailwind/' . $feuille); $iFin = strpos($s, '</head>'); $iApp = strpos($s, 'assets/css/app.css');
    $bon = $iLien !== false && $iFin !== false && $iLien < $iFin && ($iApp === false || $iApp < $iLien) && !str_contains($s, 'tailwind.config');
    check($bon, "$rel : $feuille en fin de <head>" . ($iApp !== false ? ', après app.css' : '') . ', sans configuration JavaScript');
}
$main = (string)file_get_contents(ROOT_PATH . '/app/Views/layouts/main.php');
check(str_contains($main, '--font-sans'), 'gabarit principal : la police de l\'établissement est transmise par --font-sans');
check(str_contains((string)file_get_contents(ROOT_PATH . '/app/Modules/Portals/Views/layouts/portal-base.php'), '--portal:'), 'portails : la couleur du portail est transmise par --portal');

section('4. CSP sans hôte CDN');
$ctl = (string)file_get_contents(ROOT_PATH . '/core/Controller.php');
preg_match('/Content-Security-Policy: ([^"]+)"/', $ctl, $m);
$csp = $m[1] ?? '';
check($csp !== '' && !preg_match($hotes, $csp), 'la politique ne cite aucun hôte CDN', $csp);
check(str_contains($csp, "default-src 'self'") && str_contains($csp, 'fonts.googleapis.com'), 'politique restreinte à l\'origine + police de l\'établissement (Google Fonts)');

section('5. Aucune classe Tailwind contradictoire (hors « flex hidden », volontaire)');
function famille(string $c): ?string
{
    if (str_contains($c, '[')) { return null; }
    $v = ''; if (preg_match('/^((?:[a-z0-9-]+:)+)(.+)$/', $c, $m)) { $v = $m[1]; $c = $m[2]; }
    $c = ltrim($c, '-');
    $regles = ['p' => '/^p-/', 'px' => '/^px-/', 'py' => '/^py-/', 'pt' => '/^pt-/', 'pb' => '/^pb-/', 'pl' => '/^pl-/', 'pr' => '/^pr-/', 'm' => '/^m-/', 'mx' => '/^mx-/', 'my' => '/^my-/', 'mt' => '/^mt-/', 'mb' => '/^mb-/', 'ml' => '/^ml-/', 'mr' => '/^mr-/',
        'gap' => '/^gap-\d/', 'w' => '/^w-/', 'h' => '/^h-/', 'rounded' => '/^rounded(-(none|sm|md|lg|xl|2xl|3xl|full))?$/', 'fs' => '/^text-(xs|sm|base|lg|xl|[2-9]xl)$/', 'fw' => '/^font-(thin|light|normal|medium|semibold|bold|extrabold|black)$/',
        'ta' => '/^text-(left|center|right|justify)$/', 'dsp' => '/^(block|inline-block|inline|flex|inline-flex|grid|hidden)$/', 'jc' => '/^justify-/', 'ai' => '/^items-/',
        'bg' => '/^bg-(white|black|transparent|[a-z]+-\d{2,3})$/', 'tc' => '/^text-(white|black|transparent|[a-z]+-\d{2,3})$/', 'bc' => '/^border-(white|black|transparent|[a-z]+-\d{2,3})$/'];
    foreach ($regles as $nom => $re) { if (preg_match($re, $c)) { return $v . $nom; } }
    return null;
}
$conflits = [];
foreach (fichiersPhp() as $f) {
    foreach (file($f) as $i => $ligne) {
        if (!preg_match_all('/class="([^"]*)"/', $ligne, $mm)) { continue; }
        foreach ($mm[1] as $cls) {
            $vus = [];
            foreach (preg_split('/\s+/', trim((string)preg_replace('/<\?.*?\?>/', ' ', $cls))) as $c) { $k = famille($c); if ($k !== null) { $vus[$k][] = $c; } }
            foreach ($vus as $k => $liste) {
                $u = array_unique($liste);
                if (count($u) > 1 && !($k === 'dsp' && in_array('hidden', $u, true))) { $conflits[] = str_replace(ROOT_PATH . '/', '', $f) . ':' . ($i + 1) . ' ' . implode(' ', $u); }
            }
        }
    }
}
check($conflits === [], 'aucun attribut class ne contient deux utilitaires de la même famille (le rendu ne dépend plus de l\'ordre de la feuille)', implode(' ; ', array_slice($conflits, 0, 3)));

section('6. Feuille compilée à jour pour les couleurs construites en PHP');
$css = (string)file_get_contents(ROOT_PATH . '/public/assets/vendor/tailwind/tailwind.css');
$manquantes = [];
foreach (fichiersPhp() as $f) {
    if (preg_match_all('/(?<![\w:-])((?:bg|text|border)-(?:red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|slate|gray|zinc|neutral|stone)-(?:50|100|200|300|400|500|600|700|800|900))(?![\w-])/', (string)file_get_contents($f), $mm)) {
        foreach (array_unique($mm[1]) as $c) { if (!str_contains($css, '.' . $c . '{') && !str_contains($css, '.' . $c . ',') && !str_contains($css, '.' . $c . ':')) { $manquantes[$c] = true; } }
    }
}
check($manquantes === [], 'toutes les classes de couleur écrites dans les vues existent dans tailwind.css (sinon : recompiler, voir tools/tailwind/README.md)', implode(', ', array_slice(array_keys($manquantes), 0, 8)));
$iFlex = strpos($css, '.flex{'); $iHidden = strpos($css, '.hidden{');
check($iFlex !== false && $iHidden !== false && $iHidden > $iFlex, '« hidden » est défini après « flex » : les fenêtres cachées par défaut (flex hidden) restent cachées, comme avec le CDN');

section('7. Boutons d\'action nets : réinitialisation sans preflight et icônes qui ne s\'écrasent pas');
$appCss = (string)file_get_contents(ROOT_PATH . '/public/assets/css/app.css');
check(str_contains($appCss, ':where(button[class]:not([class*="border"]))') && preg_match('/:where\(button\[class\]:not\(\[class\*="border"\]\)\)\s*\{[^}]*background-color:\s*transparent[^}]*border:\s*0/', $appCss) === 1, 'app.css : les boutons sans bordure déclarée perdent la bordure et le fond gris du navigateur (spécificité nulle : :where)');
check(preg_match('/svg\.lucide\s*\{[^}]*max-width:\s*none[^}]*flex-shrink:\s*0/', $appCss) === 1, 'app.css : les icônes Lucide ne sont plus comprimées par « svg { max-width:100% } »');
check(!str_contains((string)file_get_contents(ROOT_PATH . '/app/Views/layouts/main.php'), 'tailwind-preflight'), 'la mise en page principale reste sans preflight (c\'est app.css qui réinitialise les boutons)');

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
