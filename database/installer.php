<?php

/**
 * Initialisation d'une installation neuve : premier établissement + premier compte administrateur.
 *
 * À lancer UNE fois, sur une base importée depuis database/schema/ecole_app_schema_*.sql (voir docs/deploiement/INSTALLATION.md §4).
 *
 * Usage :
 *   php database/installer.php --slug=mon-ecole --nom="Mon École" --nom-court="Mon École" --admin-email=admin@mon-ecole.tld --admin-nom="Nom Prénom"
 *   Mot de passe : variable d'environnement INSTALL_ADMIN_PASSWORD (12 caractères minimum), sinon un mot de passe aléatoire est
 *   généré et affiché UNE fois. Il n'est jamais écrit dans un fichier ni dans un journal.
 *
 * Refuse de s'exécuter si un établissement existe déjà (pas d'écrasement). Rien n'est supprimé. Tout est fait en une transaction.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

$env = [];
if (is_file(ROOT_PATH . '/.env')) {
    foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
        [$k, $v] = explode('=', $l, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}
// Cible alternative réservée aux tests : une base jetable dont le nom l'indique, jamais une autre.
$base = getenv('INSTALL_DB');
if ($base !== false && $base !== '') {
    if (!preg_match('/^ecole_app_(copy|install)_[a-f0-9]+$/', $base)) { fwrite(STDERR, "REFUS : INSTALL_DB doit être ecole_app_copy_* ou ecole_app_install_*\n"); exit(2); }
    $env['DB_DATABASE'] = $base;
}

$opt = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)=(.*)$/s', $a, $m)) { $opt[$m[1]] = $m[2]; }
}
function arreter(string $m, int $code = 1): never { fwrite(STDERR, "ECHEC : $m\n"); exit($code); }

$slug = strtolower(trim($opt['slug'] ?? ''));
$nom  = trim($opt['nom'] ?? '');
$court = trim($opt['nom-court'] ?? $nom);
$email = strtolower(trim($opt['admin-email'] ?? ''));
$adminNom = trim($opt['admin-nom'] ?? '');
if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || strlen($slug) > 60) { arreter('--slug obligatoire : lettres minuscules, chiffres et tirets (60 max).', 2); }
if ($nom === '' || strlen($nom) > 200 || $court === '' || strlen($court) > 60) { arreter('--nom (200 max) et --nom-court (60 max) obligatoires.', 2); }
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) { arreter('--admin-email invalide.', 2); }
if ($adminNom === '' || strlen($adminNom) > 100) { arreter('--admin-nom obligatoire (100 max).', 2); }

$mdp = getenv('INSTALL_ADMIN_PASSWORD');
$genere = false;
if ($mdp === false || $mdp === '') {
    $mdp = rtrim(strtr(base64_encode(random_bytes(15)), '+/', 'Aa'), '=') . '!7';
    $genere = true;
} elseif (strlen($mdp) < 12) {
    arreter('INSTALL_ADMIN_PASSWORD doit faire 12 caractères minimum.', 2);
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

foreach (['etablissements', 'users', 'user_etablissements', 'user_roles_etab', 'etab_roles'] as $t) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $q->execute([$t]);
    if (!(int)$q->fetchColumn()) { arreter("table `$t` absente : importez d'abord database/schema/ecole_app_schema_*.sql."); }
}
if ((int)$pdo->query('SELECT COUNT(*) FROM etablissements')->fetchColumn() > 0) {
    arreter("installation déjà initialisée (un établissement existe) : rien n'a été modifié.", 3);
}
$roleAdmin = $pdo->query("SELECT id FROM etab_roles WHERE code = 'admin' AND etablissement_id IS NULL AND is_system = 1 LIMIT 1")->fetchColumn();
if (!$roleAdmin) { arreter("rôle système « admin » introuvable (le schéma de référence contient les rôles RBAC)."); }

$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO etablissements (slug, nom, nom_court, type, statut, activated_at) VALUES (?, ?, ?, 'lycee', 'active', NOW())")
        ->execute([$slug, $nom, $court]);
    $etabId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO users (etablissement_id, nom, email, password, role, actif) VALUES (?, ?, ?, ?, 'admin', 1)")
        ->execute([$etabId, $adminNom, $email, password_hash($mdp, PASSWORD_BCRYPT)]);
    $userId = (int)$pdo->lastInsertId();

    $pdo->prepare('INSERT INTO user_etablissements (user_id, etablissement_id, is_primary) VALUES (?, ?, 1)')->execute([$userId, $etabId]);
    $pdo->prepare('INSERT INTO user_roles_etab (user_id, etablissement_id, role_id) VALUES (?, ?, ?)')->execute([$userId, $etabId, (int)$roleAdmin]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    arreter('création annulée (aucune modification) : ' . $e->getMessage());
}

echo "Établissement #$etabId « $nom » créé ; administrateur « $email » créé (id $userId).\n";
if ($genere) {
    echo "Mot de passe initial (affiché une seule fois, à changer dès la première connexion) : $mdp\n";
}
echo "Étapes suivantes : TENANT_DEFAULT_ID=$etabId dans .env si nécessaire, puis connexion et configuration de l'année scolaire (Paramètres).\n";
