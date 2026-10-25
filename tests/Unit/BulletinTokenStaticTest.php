<?php
/**
 * Garde-fous STATIQUES (analyse du code source) pour les jetons de bulletins :
 *   - aucun secret par défaut : le littéral historique « ecole_app_v2 » ne doit apparaître que dans les rares
 *     fichiers autorisés à DÉTECTER les anciens jetons (outil de rotation, scripts manuels) — jamais dans le code
 *     qui émet ou vérifie un jeton ;
 *   - aucun repli vers APP_KEY / JWT_SECRET : BULLETIN_TOKEN_SECRET n'est lu que par BulletinTokenService ;
 *   - aucun jeton complet dans les journaux ni dans l'audit : toute ligne de journalisation qui manipule un jeton
 *     doit passer par fingerprint() ;
 *   - la page de vérification publique n'accède à aucune donnée de bulletin ;
 *   - la publication n'est exposée qu'en POST ; .env.example ne contient aucune valeur de secret.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/BulletinTokenStaticTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));

$passed = 0; $failed = 0;
function check(bool $ok, string $label): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

/** @return array<string,string> chemin relatif => contenu, pour tous les .php d'un dossier */
function sources(string ...$dirs): array
{
    $out = [];
    foreach ($dirs as $d) {
        $base = ROOT_PATH . '/' . $d;
        if (!is_dir($base)) { continue; }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[str_replace(str_replace('\\', '/', ROOT_PATH) . '/', '', str_replace('\\', '/', $f->getPathname()))] = (string)file_get_contents($f->getPathname());
            }
        }
    }
    return $out;
}

$code = sources('app', 'core', 'config', 'public', 'database/migrations');
// Fichiers autorisés à connaître le littéral historique, uniquement pour DÉTECTER d'anciens jetons faibles.
$autorisesLitteral = [
    'app/Modules/Academique/Services/BulletinTokenRotator.php',
];

// ════════════════════════════════════════════════════════════════════════════
section('1. Aucun secret par défaut');

$avecLitteral = array_keys(array_filter($code, fn($c) => str_contains($c, 'ecole_app_v2')));
$interdits = array_values(array_diff($avecLitteral, $autorisesLitteral));
check($interdits === [], 'le littéral « ecole_app_v2 » est absent de tout le code applicatif' . ($interdits ? ' — trouvé dans : ' . implode(', ', $interdits) : ''));

$gen = $code['app/Modules/Academique/Services/BulletinGenerator.php'] ?? '';
check(!preg_match('/\$appKey/', $gen), 'BulletinGenerator n\'a plus de paramètre $appKey');
check(!preg_match('/hash\(\s*[\'"]sha256[\'"]\s*,\s*"bulletin:/', $gen), 'BulletinGenerator ne calcule plus de jeton par concaténation SHA-256');
check(str_contains($gen, 'throw BulletinTokenException::missing()'), 'BulletinGenerator refuse explicitement d\'émettre un jeton sans service de jetons');
$factory = $code['app/Modules/Academique/Services/BulletinEngineFactory.php'] ?? '';
check(!str_contains($factory, 'random_bytes') && !str_contains($factory, 'bulletin_secret') && !str_contains($factory, "->set("),
      'BulletinEngineFactory ne génère ni ne stocke plus de secret en base');

// ════════════════════════════════════════════════════════════════════════════
section('2. Aucun repli vers un autre secret');

$svc = $code['app/Modules/Academique/Services/BulletinTokenService.php'] ?? '';
$svcCode = preg_replace('/\/\*.*?\*\/|\/\/[^\n]*/s', '', $svc);   // code sans commentaires
check(substr_count($svcCode, 'APP_KEY') === 1 && preg_match('/FORBIDDEN_REUSE\s*=\s*\[[^\]]*APP_KEY/s', $svcCode) === 1, 'APP_KEY n\'apparaît, dans le code, que dans la liste des secrets INTERDITS à la réutilisation');
check(!preg_match('/\?\?\s*[\'"][^\'"]{4,}[\'"]/', $svcCode), 'BulletinTokenService : aucune valeur par défaut (?? \'…\') dans le code');
$lecteurs = [];
// Lecture de la variable avec son nom littéral (tableau $_ENV, $_SERVER ou getenv) : seul le service la lit, par sa constante ENV_VAR.
foreach ($code as $path => $src) {
    $sansCommentaires = preg_replace('/\/\*.*?\*\/|\/\/[^\n]*/s', '', $src);
    if (preg_match('/(\$_ENV|\$_SERVER)\s*\[\s*[\'"]BULLETIN_TOKEN_SECRET|getenv\s*\(\s*[\'"]BULLETIN_TOKEN_SECRET/', $sansCommentaires)) { $lecteurs[] = $path; }
}
check($lecteurs === [], 'aucun fichier ne lit BULLETIN_TOKEN_SECRET directement (seul BulletinTokenService le lit, via sa constante)'
      . ($lecteurs ? ' — lu dans : ' . implode(', ', $lecteurs) : ''));
check(str_contains($svc, "ENV_VAR = 'BULLETIN_TOKEN_SECRET'"), 'BulletinTokenService : le nom de la variable est défini à un seul endroit');
check(!str_contains($code['config/app.php'] ?? '', 'BULLETIN_TOKEN_SECRET'), 'config/app.php n\'expose pas le secret dans la configuration');

// ════════════════════════════════════════════════════════════════════════════
section('3. Aucun jeton complet dans les journaux ni dans l\'audit');

$suspects = [];
foreach ($code as $path => $src) {
    if (!str_starts_with($path, 'app/Modules/Academique/') && !str_starts_with($path, 'app/Controllers/')) { continue; }
    foreach (explode("\n", $src) as $i => $ligne) {
        $journalise = preg_match('/Logger::(security|info|error|warning|critical|debug)\s*\(|->audit->(log|logCreate|logUpdate)\s*\(|AuditService\(\)\)->log\(/', $ligne);
        if ($journalise && preg_match('/\$token\b|verificationToken|verification_token/', $ligne) && !str_contains($ligne, 'fingerprint(')) {
            $suspects[] = $path . ':' . ($i + 1);
        }
    }
}
check($suspects === [], 'aucune ligne de journalisation ne contient un jeton sans passer par fingerprint()' . ($suspects ? ' — ' . implode(', ', $suspects) : ''));

$handler = $code['app/Modules/Academique/Listeners/BulletinHandler.php'] ?? '';
check(!preg_match("/'token'\s*=>/", $handler) && substr_count($handler, "'jeton_empreinte'") === 5, 'BulletinHandler : 5 événements d\'audit, tous avec une empreinte (clé « jeton_empreinte »), aucune clé « token »');
check(!preg_match('/RuntimeException\([^;]*\$verificationToken/', $gen), 'BulletinGenerator : aucun message d\'exception ne contient le jeton');
$repo = $code['app/Modules/Academique/Repositories/BulletinRepository.php'] ?? '';
check(!preg_match('/RuntimeException\([^;]*(getMessage|verificationToken|\$token)/', $repo), 'BulletinRepository : aucun message d\'exception ne reprend un texte SQL (qui peut contenir un jeton) ni le jeton');

// ════════════════════════════════════════════════════════════════════════════
section('4. Page publique et routes');

$vue = $code['app/Modules/Academique/Views/bulletins/verification.php'] ?? '';
$vueSansDoc = preg_replace('/\/\*.*?\*\//s', '', $vue);
check(!str_contains($vueSansDoc, '$bulletin') && !str_contains($vueSansDoc, 'eleveNom') && !str_contains($vueSansDoc, 'moyenne'), 'la vue de vérification n\'accède à aucune donnée de bulletin');

$routes = (string)file_get_contents(ROOT_PATH . '/config/routes.php');
check(preg_match('/\$router->get\s*\([^;]*(publier|archiver)/', $routes) === 0, 'aucune route GET pour publier / archiver');
check(preg_match('/\$router->post\s*\(\s*\'\/v2\/academique\/bulletins\/\{eleveId\}\/\{periodeId\}\/publier\'/', $routes) === 1
   && preg_match('/\$router->post\s*\(\s*\'\/v2\/academique\/bulletins\/\{eleveId\}\/\{periodeId\}\/archiver\'/', $routes) === 1, 'publier et archiver sont déclarés en POST uniquement');

$ctrl = $code['app/Modules/Academique/Controllers/BulletinController.php'] ?? '';
check(preg_match('/function changerStatut\(.*?\{\s*\$this->requireAuth\(\);\s*\$this->verifyCsrf\(\);/s', $ctrl) === 1, 'changerStatut() : authentification puis CSRF avant toute autre action');
$corps = substr($ctrl, (int)strpos($ctrl, 'function changerStatut('));
$pCsrf = strpos($corps, 'verifyCsrf()'); $pGate = strpos($corps, 'BulletinPublicationGate::isOpen()'); $pPol = strpos($corps, 'canPublish($user)');
check($pCsrf !== false && $pGate !== false && $pPol !== false && $pCsrf < $pGate && $pGate < $pPol, 'ordre des contrôles dans changerStatut() : CSRF, puis interrupteur, puis politique');

// ════════════════════════════════════════════════════════════════════════════
section('5. Configuration livrée');

$exemple = (string)file_get_contents(ROOT_PATH . '/.env.example');
check(preg_match('/^BULLETIN_TOKEN_SECRET=\s*$/m', $exemple) === 1, '.env.example : BULLETIN_TOKEN_SECRET présent, SANS valeur');
check(preg_match('/^BULLETIN_PUBLICATION_ENABLED=false\s*$/m', $exemple) === 1, '.env.example : interrupteur de publication fermé par défaut');
check(preg_match('/^TRUSTED_PROXIES=\s*$/m', $exemple) === 1, '.env.example : aucun proxy de confiance par défaut');
$compose = (string)file_get_contents(ROOT_PATH . '/docker-compose.yml');
check(str_contains($compose, 'BULLETIN_TOKEN_SECRET: ${BULLETIN_TOKEN_SECRET:?'), 'docker-compose.yml : BULLETIN_TOKEN_SECRET requis, sans valeur par défaut');
check(!preg_match('/BULLETIN_TOKEN_SECRET:\s*\$\{BULLETIN_TOKEN_SECRET:-/', $compose), 'docker-compose.yml : aucune valeur de repli (:-) pour le secret');
$gi = (string)file_get_contents(ROOT_PATH . '/.gitignore');
check(preg_match('/^\.env\s*$/m', $gi) === 1, '.env est ignoré par Git');

echo "\n";
$total = $passed + $failed;
echo ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : {$passed}/{$total} assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
