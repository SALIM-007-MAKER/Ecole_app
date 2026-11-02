<?php

/**
 * Opérateurs de la plateforme EduNova (Super-Admin SaaS) — création du PREMIER opérateur et procédure de récupération d'accès.
 *
 * Un opérateur est une identité INDÉPENDANTE des comptes d'établissement (aucun lien avec `users`). Aucun compte par défaut n'existe :
 * sur une installation neuve (Docker, hébergeur) il faut créer le premier opérateur avec ce script, qui exige un accès serveur.
 *
 * Usage :
 *   Création       : php database/create-operator.php --email=moi@exemple.tld --nom="Prénom Nom" [--niveau=super_admin|admin|support]
 *   Récupération   : php database/create-operator.php --reinitialiser --email=moi@exemple.tld     (nouveau mot de passe, changement obligatoire)
 *   Révocation     : php database/create-operator.php --desactiver --email=...   |   --activer --email=...
 *   Inventaire     : php database/create-operator.php --lister
 *
 * Mot de passe : variable d'environnement OPERATOR_PASSWORD (14 caractères minimum, politique stricte), sinon un mot de passe aléatoire de
 * 24 caractères est généré et affiché UNE seule fois (changement obligatoire à la première connexion). Jamais en argument de ligne de
 * commande, jamais écrit dans un fichier ni dans un journal.
 *
 * Docker : docker compose exec -e OPERATOR_PASSWORD=... app php database/create-operator.php --email=... --nom="..."
 * Chaque opération est inscrite dans le journal d'audit global (acteur « cli »). Rien n'est jamais supprimé.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $classe): void {
    if (str_starts_with($classe, 'Core\\')) {
        $fichier = ROOT_PATH . '/core/' . str_replace('\\', '/', substr($classe, 5)) . '.php';
        if (is_file($fichier)) { require $fichier; }
    }
});

use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformOperatorService;

$env = [];
if (is_file(ROOT_PATH . '/.env')) {
    foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
        [$k, $v] = explode('=', $l, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}
// Hébergeur / Docker : les paramètres de base arrivent par variables d'environnement (elles priment sur .env).
foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $cle) {
    $v = getenv($cle);
    if ($v !== false && $v !== '') { $env[$cle] = $v; }
}
// Cible alternative réservée aux tests : une base jetable dont le nom l'indique, jamais une autre.
$base = getenv('OPERATOR_DB');
if ($base !== false && $base !== '') {
    if (!preg_match('/^ecole_app_(copy|install)_[a-f0-9]+$/', $base)) { fwrite(STDERR, "REFUS : OPERATOR_DB doit être ecole_app_copy_* ou ecole_app_install_*\n"); exit(2); }
    $env['DB_DATABASE'] = $base;
}
$_ENV = array_merge($_ENV, $env);

function arreter(string $m, int $code = 1): never { fwrite(STDERR, "ECHEC : $m\n"); exit($code); }

$opt = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/s', $a, $m)) { $opt[$m[1]] = $m[2] ?? '1'; }
    else { arreter("argument inconnu « $a ». Le mot de passe ne se passe jamais en argument : utilisez OPERATOR_PASSWORD.", 2); }
}
if (isset($opt['password']) || isset($opt['mot-de-passe'])) { arreter("le mot de passe ne se passe jamais en argument de ligne de commande : utilisez la variable d'environnement OPERATOR_PASSWORD.", 2); }
foreach (array_keys($opt) as $cle) {
    if (!in_array($cle, ['email', 'nom', 'niveau', 'reinitialiser', 'activer', 'desactiver', 'lister'], true)) { arreter("option inconnue --$cle.", 2); }
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306', $env['DB_DATABASE'] ?? 'ecole_app'),
        $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    arreter('connexion à la base impossible : ' . $e->getMessage());
}

foreach (['platform_operators' => 'email', 'platform_audit_logs' => 'action'] as $table => $colonne) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $q->execute([$table, $colonne]);
    if (!(int)$q->fetchColumn()) { arreter("`$table.$colonne` absent : appliquez d'abord les migrations (php database/migrate.php, migration T064)."); }
}

$service = new PlatformOperatorService($pdo);
$email = strtolower(trim($opt['email'] ?? ''));
$mdp = getenv('OPERATOR_PASSWORD');
$mdp = ($mdp === false || $mdp === '') ? null : $mdp;

if (isset($opt['lister'])) {
    $lignes = $pdo->query("SELECT id, email, niveau, actif, last_login_at, (password_hash IS NOT NULL) AS peut_se_connecter FROM platform_operators ORDER BY id")->fetchAll();
    foreach ($lignes as $l) {
        echo sprintf("#%d  %-35s %-11s %s  %s  dernière connexion : %s\n", $l['id'], $l['email'] ?? '(ancien compte lié à un établissement)', $l['niveau'],
            $l['actif'] ? 'actif  ' : 'inactif', $l['peut_se_connecter'] ? 'connexion possible' : 'connexion impossible', $l['last_login_at'] ?? 'jamais');
    }
    echo count($lignes) . " opérateur(s). Utilisables : " . $service->compterUtilisables() . "\n";
    exit(0);
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { arreter('--email obligatoire et valide.', 2); }

try {
    if (isset($opt['reinitialiser'])) {
        $r = $service->reinitialiserMotDePasse($email, $mdp, PlatformOperatorService::ACTEUR_CLI);
        echo "Mot de passe de « {$r['email']} » réinitialisé ; le compte est actif ; changement obligatoire à la prochaine connexion.\n";
        if ($r['password'] !== null) { echo "Mot de passe provisoire (affiché une seule fois) : {$r['password']}\n"; }
    } elseif (isset($opt['activer']) || isset($opt['desactiver'])) {
        if (!$service->definirActif($email, isset($opt['activer']), PlatformOperatorService::ACTEUR_CLI)) { arreter('aucun opérateur avec cette adresse e-mail.', 3); }
        echo 'Opérateur « ' . $email . ' » ' . (isset($opt['activer']) ? 'activé' : 'désactivé') . ".\n";
    } else {
        $nom = trim($opt['nom'] ?? '');
        if ($nom === '') { arreter('--nom obligatoire.', 2); }
        $r = $service->creer($email, $nom, $opt['niveau'] ?? 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI);
        echo "Opérateur #{$r['id']} « {$r['email']} » créé (niveau " . ($opt['niveau'] ?? 'super_admin') . ").\n";
        if ($r['password'] !== null) {
            echo "Mot de passe provisoire (affiché une seule fois, à changer dès la première connexion) : {$r['password']}\n";
        }
        echo "Connexion : /platform/login\n";
    }
} catch (InvalidArgumentException $e) {
    arreter($e->getMessage(), 2);
}

if (PlatformAuditService::echecsDeLaRequete() > 0) {
    fwrite(STDERR, "ATTENTION : l'opération a été effectuée mais son enregistrement dans le journal d'audit a ÉCHOUÉ (fichier de secours : storage/logs).\n");
    exit(4);
}
