<?php
/**
 * Tests — audit des actions sensibles PAR ÉTABLISSEMENT (étape 2 du Super-Admin SaaS) : audit_logs rattaché à un établissement.
 *
 *  1. Migration T065 : colonnes, index, clé étrangère, rétro-remplissage prudent, déclencheurs, retour arrière, permission audit.view
 *  (les sections suivantes sont ajoutées bloc par bloc : AuditService, instrumentation, consultation, alertes, portail admin)
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/AuditEtablissementTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\Shared\\' => '/app/Shared/', 'App\\Services\\' => '/app/Services/', 'App\\Controllers\\' => '/app/Controllers/',
              'App\\Models\\' => '/app/Models/', 'App\\Modules\\' => '/app/Modules/', 'App\\Listeners\\' => '/app/Listeners/', 'App\\Events\\' => '/app/Events/'] as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php';
            if (is_file($f)) { require $f; return; }
        }
    }
});
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use App\Services\AuditIndisponibleException;
use App\Services\AuditService;
use Core\Tenant\TenantContext;
use Core\Database;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
/** @return array{0:int,1:string} */
function lancer(array $cmd, array $env = []): array
{
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), $env));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out];
}

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$un = static fn(string $sql, array $p = []) => (function () use ($pdo, $sql, $p) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();
$dossierSecours = sys_get_temp_dir() . '/audit_etab_' . bin2hex(random_bytes(4));
@mkdir($dossierSecours, 0775, true);
putenv('AUDIT_FALLBACK_DIR=' . $dossierSecours);

// ── Deux établissements et des acteurs aux appartenances variées ──────────────────────────────────────────────────────────────
$ETAB1 = 1;
$pdo->exec("INSERT INTO etablissements (slug, nom, nom_court, type, statut) VALUES ('audit-b-" . bin2hex(random_bytes(3)) . "', 'École B Audit', 'EBA', 'lycee', 'active')");
$ETAB2 = (int)$pdo->lastInsertId();
$hash = password_hash('Test-1234!', PASSWORD_BCRYPT);
$creerUser = static function (string $nom, int $etab, string $role = 'secretaire') use ($pdo, $hash): int {
    $pdo->prepare("INSERT INTO users (nom, prenom, email, password, role, actif, etablissement_id) VALUES (?, 'Test', ?, ?, ?, 1, ?)")
        ->execute([$nom, strtolower($nom) . '.' . bin2hex(random_bytes(3)) . '@exemple.test', $hash, $role, $etab]);
    return (int)$pdo->lastInsertId();
};
$uA = $creerUser('SeulEtab1', $ETAB1);                       // un seul établissement : rétro-remplissage autorisé
$uB = $creerUser('MembreDeux', $ETAB1);                       // rattaché à 1 mais membre de l'établissement 2 : ambigu
$pdo->prepare('INSERT INTO user_etablissements (user_id, etablissement_id, is_primary) VALUES (?, ?, 0)')->execute([$uB, $ETAB2]);
$uC = $creerUser('RoleAilleurs', $ETAB1);                     // rôle dans l'établissement 2 : ambigu
$roleAdmin = (int)$pdo->query("SELECT id FROM etab_roles WHERE code = 'admin' AND etablissement_id IS NULL")->fetchColumn();
$pdo->prepare('INSERT INTO user_roles_etab (user_id, etablissement_id, role_id) VALUES (?, ?, ?)')->execute([$uC, $ETAB2, $roleAdmin]);
$uD = $creerUser('DernierAilleurs', $ETAB1);                  // dernier établissement mémorisé différent : ambigu
$pdo->prepare('UPDATE users SET dernier_etablissement_id = ? WHERE id = ?')->execute([$ETAB2, $uD]);
$uE = $creerUser('SeulEtab2', $ETAB2);                        // un seul établissement (le 2) : rétro-remplissage autorisé

// ══════════════════════════════════════════════════════════════════════
section('1. Migration T065');
// ══════════════════════════════════════════════════════════════════════
// Lignes « historiques » : écrites sans les nouvelles colonnes, comme avant T065 (déclencheurs éventuels retirés pour l'insertion de test).
$pdo->exec('DROP TRIGGER IF EXISTS `trg_audit_logs_no_update`');
$pdo->exec('DROP TRIGGER IF EXISTS `trg_audit_logs_no_delete`');
$ins = $pdo->prepare("INSERT INTO audit_logs (user_id, action, module, entite, entite_id, avant, apres, ip) VALUES (?, 'create', 'finance', 'paiement', 7, NULL, '{\"montant\": 100}', '10.0.0.1')");
$hist = [];
foreach (['A' => $uA, 'B' => $uB, 'C' => $uC, 'D' => $uD, 'E' => $uE, 'NUL' => null] as $k => $uid) { $ins->execute([$uid]); $hist[$k] = (int)$pdo->lastInsertId(); }
$colsHistoriques = 'id, user_id, action, module, entite, entite_id, avant, apres, ip, user_agent, created_at';
$empreinte = static fn() => md5(json_encode($pdo->query("SELECT $colsHistoriques FROM audit_logs ORDER BY id")->fetchAll(PDO::FETCH_NUM)));
$avantHash = $empreinte(); $avantN = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$fkUserAvant = (int)$un("SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND CONSTRAINT_NAME = 'fk_al_user'");

$migr = require ROOT_PATH . '/database/migrations/T065_audit_par_etablissement.php';
($migr['run'])($pdo);
$col = static fn(string $c) => $pdo->query("SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = '$c'")->fetch(PDO::FETCH_ASSOC);
check($col('etablissement_id') !== false && $col('etablissement_id')['IS_NULLABLE'] === 'YES', 'etablissement_id ajoutée, NULLABLE (NULL = non démontré)');
check($col('actor_nom') !== false && $col('actor_role') !== false, 'actor_nom et actor_role ajoutées (instantanés de l\'acteur)');
check(($col('resultat')['COLUMN_DEFAULT'] ?? '') === 'success' && str_contains((string)$col('resultat')['COLUMN_TYPE'], "'failure'"), 'resultat ajoutée (success par défaut)');
check((int)$un("SELECT COUNT(DISTINCT INDEX_NAME) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND INDEX_NAME IN ('idx_al_etab_date','idx_al_etab_module')") === 2, 'index (établissement, date) et (établissement, module, action)');
check((int)$un("SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = 'etablissement_id' AND REFERENCED_TABLE_NAME = 'etablissements'") === 1, 'clé étrangère vers etablissements');
check($fkUserAvant === 1 && (int)$un("SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND CONSTRAINT_NAME = 'fk_al_user'") === 1, 'clé étrangère existante fk_al_user préservée');
check($empreinte() === $avantHash && (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === $avantN, 'toutes les lignes historiques sont intactes (' . $avantN . ' lignes, empreinte identique des colonnes d\'origine)');

$etabDe = static fn(int $id) => $pdo->query("SELECT etablissement_id FROM audit_logs WHERE id = $id")->fetchColumn();
check((int)$etabDe($hist['A']) === $ETAB1, 'rétro-remplissage : acteur d\'un seul établissement (1) → établissement 1');
check((int)$etabDe($hist['E']) === $ETAB2, 'rétro-remplissage : acteur d\'un seul établissement (2) → établissement 2 (pas d\'attribution à l\'établissement 1)');
check($etabDe($hist['B']) === null, 'acteur membre de deux établissements → NULL (ambigu)');
check($etabDe($hist['C']) === null, 'acteur avec un rôle dans un autre établissement → NULL (ambigu)');
check($etabDe($hist['D']) === null, 'acteur dont le dernier établissement diffère → NULL (ambigu)');
check($etabDe($hist['NUL']) === null, 'événement sans acteur (système) → NULL');
$faux = (int)$un("SELECT COUNT(*) FROM audit_logs al JOIN users u ON u.id = al.user_id WHERE al.etablissement_id IS NOT NULL AND al.etablissement_id <> u.etablissement_id");
check($faux === 0, 'aucune ligne rétro-remplie ne contredit l\'établissement du compte (' . (int)$un('SELECT COUNT(*) FROM audit_logs WHERE etablissement_id IS NOT NULL') . ' lignes attribuées)');
check((int)$un("SELECT COUNT(*) FROM audit_logs WHERE actor_nom IS NOT NULL OR actor_role IS NOT NULL") === 0, 'les instantanés d\'acteur ne sont pas inventés pour l\'historique (NULL)');

$trig = static fn() => (int)$un("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = 'audit_logs'");
check($trig() === 2, 'deux déclencheurs protègent audit_logs');
foreach (["UPDATE audit_logs SET action = 'falsifie' WHERE id = {$hist['A']}" => 'UPDATE', 'DELETE FROM audit_logs WHERE id = ' . $hist['A'] => 'DELETE', 'UPDATE audit_logs SET etablissement_id = 2' => 'UPDATE de masse'] as $sql => $nom) {
    try { $pdo->exec($sql); check(false, "$nom refusé"); }
    catch (PDOException $e) { check(str_contains($e->getMessage(), 'ajout seul'), "$nom refusé par la base"); }
}

($migr['run'])($pdo);
check($trig() === 2 && $empreinte() === $avantHash && (int)$un("SELECT COUNT(*) FROM etab_permissions WHERE code = 'audit.view'") === 1 && (int)$etabDe($hist['A']) === $ETAB1, 'T065 est idempotente (rejouée : mêmes déclencheurs, mêmes données, une seule permission)');

($migr['rollback'])($pdo);
check($trig() === 0, 'retour arrière : les déclencheurs sont retirés');
check($col('etablissement_id') !== false && $empreinte() === $avantHash && (int)$etabDe($hist['E']) === $ETAB2 && (int)$un("SELECT COUNT(*) FROM etab_permissions WHERE code = 'audit.view'") === 1, 'retour arrière : colonnes, données rétro-remplies et permission sont CONSERVÉES');
($migr['run'])($pdo);
check($trig() === 2, 'la migration rejouée après retour arrière rétablit la protection');

$roles = $pdo->query("SELECT r.code FROM etab_role_permissions rp JOIN etab_roles r ON r.id = rp.role_id JOIN etab_permissions p ON p.id = rp.permission_id
                        WHERE p.code = 'audit.view' ORDER BY r.code")->fetchAll(PDO::FETCH_COLUMN);
check($roles === ['admin', 'directeur'], 'audit.view attribuée aux seuls rôles admin et directeur', implode(',', $roles));
$perm = require ROOT_PATH . '/config/permissions.php';
$sans = array_filter(array_keys($perm), fn($r) => in_array('audit.view', $perm[$r], true));
check(array_values($sans) === ['admin', 'directeur'] || array_values($sans) === ['directeur', 'admin'], 'config/permissions.php : audit.view uniquement pour admin et directeur (parité config ↔ base)', implode(',', $sans));

// ══════════════════════════════════════════════════════════════════════
section('2. AuditService : attribution à l\'établissement, acteur, données personnelles');
// ══════════════════════════════════════════════════════════════════════
AuditService::definirDossierSecours($dossierSecours);
AuditService::oublierSchema();
$audit = new AuditService();
$derniere = static fn() => $pdo->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$uStaff = $creerUser('Gestionnaire', $ETAB1, 'directeur');
$uEleve = $creerUser('ElevePerso', $ETAB1, 'eleve');
$_SERVER['REMOTE_ADDR'] = '203.0.113.9'; $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4'; $_SERVER['HTTP_USER_AGENT'] = 'test-audit';

TenantContext::clear();
$audit->log($uStaff, 'test.sans_contexte', 'audit_test', 'x', 1);
$l = $derniere();
check($l['etablissement_id'] === null, 'sans contexte tenant : etablissement_id = NULL (jamais l\'établissement par défaut 1, bien que l\'acteur appartienne à l\'établissement 1)');
TenantContext::set($ETAB2);
$audit->log($uStaff, 'test.contexte2', 'audit_test', 'x', 1);
check((int)$derniere()['etablissement_id'] === $ETAB2, 'avec contexte tenant 2 : etablissement_id = 2, même si le compte de l\'acteur est rattaché à l\'établissement 1 (le contexte fait foi, pas le compte)');
TenantContext::set($ETAB1);
$audit->log($uStaff, 'test.contexte1', 'audit_test', 'x', 1);
$l = $derniere();
check((int)$l['etablissement_id'] === $ETAB1 && $l['resultat'] === 'success', 'avec contexte tenant 1 : etablissement_id = 1, résultat success');
check($l['actor_nom'] === 'Test Gestionnaire' && $l['actor_role'] === 'directeur', 'acteur figé : nom et rôle au moment de l\'action', (string)$l['actor_nom']);
check($l['ip'] === '203.0.113.9' && $l['user_agent'] === 'test-audit', 'IP = REMOTE_ADDR : X-Forwarded-For falsifié ignoré sans proxy de confiance');
$audit->log($uEleve, 'test.eleve', 'audit_test', 'x', 1);
$l = $derniere();
check($l['actor_nom'] === null && $l['actor_role'] === 'eleve' && (int)$l['user_id'] === $uEleve, 'rôle élève : aucun nom conservé (identifiant seul)');
$audit->log(0, 'test.user0', 'audit_test', 'x', 1);
check($derniere()['user_id'] === null && $derniere()['action'] === 'test.user0', 'identifiant d\'acteur 0 : enregistré avec user_id NULL (pas d\'échec de clé étrangère)');
$audit->log($uStaff, 'test.masque', 'audit_test', 'eleve', 5, ['prenom' => 'Awa', 'statut' => 'a'], ['prenom' => 'Awa', 'telephone' => '+22790000000', 'email' => 'awa@exemple.test', 'adresse' => 'Rue 1',
    'date_naissance' => '2010-01-01', 'password' => 'x', 'imbrique' => ['token' => 'abc', 'nom_eleve' => 'Diallo', 'classe_id' => 3], 'eleve_id' => 5]);
$l = $derniere(); $apres = json_decode($l['apres'], true);
$brut = $l['avant'] . $l['apres'];
check($apres['prenom'] === '***' && $apres['telephone'] === '***' && $apres['email'] === '***' && $apres['adresse'] === '***' && $apres['date_naissance'] === '***', 'données personnelles masquées (prénom, téléphone, e-mail, adresse, date de naissance)');
check($apres['password'] === '***' && $apres['imbrique']['token'] === '***' && $apres['imbrique']['nom_eleve'] === '***', 'secrets et noms d\'élèves masqués, y compris dans les tableaux imbriqués');
check($apres['eleve_id'] === 5 && $apres['imbrique']['classe_id'] === 3 && !str_contains($brut, 'Awa') && !str_contains($brut, 'Diallo') && !str_contains($brut, 'exemple.test'), 'identifiants conservés ; aucun nom, prénom ni coordonnée en clair dans la trace');

// ══════════════════════════════════════════════════════════════════════
section('3. Lecture : établissement explicite obligatoire, isolation stricte');
// ══════════════════════════════════════════════════════════════════════
TenantContext::set($ETAB1);
for ($i = 1; $i <= 7; $i++) { $audit->log($uStaff, $i % 2 ? 'secret.un' : 'secret.deux', 'fin_test', 'paiement', 5100 + $i, ['montant' => $i], ['montant' => $i + 1]); }
TenantContext::set($ETAB2);
for ($i = 1; $i <= 4; $i++) { $audit->log($uE, 'autre.action', 'aca_test', 'note', 5900 + $i, null, ['valeur' => $i]); }
$audit->log($uE, 'secret.un', 'fin_test', 'paiement', 5101);   // même type d'événement et même identifiant d'entité que l'établissement 1
TenantContext::clear();
$r1 = $audit->rechercher($ETAB1, ['module' => 'fin_test']);
$r2 = $audit->rechercher($ETAB2, ['module' => 'fin_test']);
check($r1['total'] === 7 && $r2['total'] === 1, 'chaque établissement ne voit que ses propres événements (7 contre 1)', "{$r1['total']} / {$r2['total']}");
check(array_values(array_unique(array_map(fn($x) => (int)$x['etablissement_id'], $r1['rows']))) === [$ETAB1] && array_values(array_unique(array_map(fn($x) => (int)$x['etablissement_id'], $r2['rows']))) === [$ETAB2], 'toutes les lignes renvoyées portent l\'établissement demandé');
$ligne2 = (int)$pdo->query("SELECT id FROM audit_logs WHERE etablissement_id = $ETAB2 AND module = 'aca_test' LIMIT 1")->fetchColumn();
check($audit->trouver($ETAB1, $ligne2) === null, 'détail : l\'identifiant d\'un événement d\'un AUTRE établissement est indiscernable d\'un identifiant inexistant (null → 404)');
check($audit->trouver($ETAB2, $ligne2) !== null && $audit->trouver($ETAB1, 987654321) === null, 'détail : accessible dans son propre établissement ; identifiant inconnu → null');
check(count($audit->getEntityHistory($ETAB1, 'paiement', 5101)) === 1 && count($audit->getEntityHistory($ETAB2, 'paiement', 5101)) === 1, 'historique d\'entité : le même type + identifiant dans deux établissements reste séparé');
check($audit->getEntityHistory($ETAB1, 'note', 5901) === [], 'historique d\'entité : une entité d\'un autre établissement n\'est pas retrouvable');
check(count($audit->getUserHistory($ETAB1, $uStaff)) >= 7 && $audit->getUserHistory($ETAB1, $uE) === [] && $audit->getUserHistory($ETAB2, $uStaff) !== [], 'historique d\'utilisateur : borné à l\'établissement');
check($audit->rechercher($ETAB1, ['user_id' => $uE])['total'] === 0, 'filtre « utilisateur » d\'un autre établissement : aucun résultat, aucune fuite');
foreach ([0, -1] as $mauvais) {
    foreach (['rechercher' => fn() => $audit->rechercher($mauvais, []), 'trouver' => fn() => $audit->trouver($mauvais, 1), 'historique entité' => fn() => $audit->getEntityHistory($mauvais, 'x', 1),
              'historique utilisateur' => fn() => $audit->getUserHistory($mauvais, 1), 'modules' => fn() => $audit->modules($mauvais), 'acteurs' => fn() => $audit->acteurs($mauvais)] as $nom => $appel) {
        try { $appel(); check(false, "$nom($mauvais) refusé"); } catch (\InvalidArgumentException) { check(true, "$nom : établissement $mauvais refusé (lecture sans établissement explicite impossible)"); }
    }
}
$a1 = $audit->acteurs($ETAB1); $a2 = $audit->acteurs($ETAB2);
check(in_array($uStaff, array_column($a1, 'id'), true) && !in_array($uE, array_column($a1, 'id'), true) && in_array($uE, array_column($a2, 'id'), true), 'liste des acteurs du filtre : seulement ceux de l\'établissement');
check(!in_array('aca_test', $audit->modules($ETAB1), true) && in_array('aca_test', $audit->modules($ETAB2), true) && in_array('secret.deux', $audit->actions($ETAB1), true) && !in_array('autre.action', $audit->actions($ETAB1), true), 'listes de modules et d\'actions limitées à l\'établissement');
check($audit->rechercher($ETAB1, ['q' => "' OR 1=1 --"])['total'] === 0 && $audit->rechercher($ETAB1, ['q' => '%'])['total'] === 0, 'recherche : injection SQL et jokers % neutralisés');
check($audit->rechercher($ETAB1, ['q' => 'secret'])['total'] === 7 && $audit->rechercher($ETAB1, ['q' => '5103'])['total'] === 1, 'recherche : par texte et par identifiant d\'entité, bornée à l\'établissement');
check($audit->rechercher($ETAB1, ['entite' => 'paiement', 'entite_id' => 5101, 'module' => 'fin_test'])['total'] === 1, 'filtre par type et identifiant d\'entité');
$p = $audit->rechercher($ETAB1, ['module' => 'fin_test'], 1, 10);
check($p['par_page'] === 10 && count($p['rows']) === 7 && $p['pages'] === 1, 'pagination : une page de 7 lignes');
for ($i = 0; $i < 30; $i++) { TenantContext::set($ETAB1); $audit->log($uStaff, 'volume', 'volume', 'x', $i); } TenantContext::clear();
$p3 = $audit->rechercher($ETAB1, ['module' => 'volume'], 3, 10);
check($p3['pages'] === 3 && $p3['page'] === 3 && count($p3['rows']) === 10 && $audit->rechercher($ETAB1, ['module' => 'volume'], 99, 10)['page'] === 3 && $audit->rechercher($ETAB1, ['module' => 'volume'], -5, 10)['page'] === 1, 'pagination : pages, plafonnement des numéros hors limites');
check($audit->rechercher($ETAB1, ['date_debut' => date('Y-m-d'), 'date_fin' => date('Y-m-d'), 'module' => 'volume'])['total'] === 30 && $audit->rechercher($ETAB1, ['date_debut' => date('Y-m-d', strtotime('+1 day')), 'module' => 'volume'])['total'] === 0 && $audit->rechercher($ETAB1, ['date_debut' => '2026-13-45', 'module' => 'volume'])['total'] === 30, 'filtres par période (aujourd\'hui, demain, date invalide ignorée)');
$tousAvant = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
check($audit->rechercher($ETAB1, ['action' => 'secret.un'])['total'] === 4 && $audit->rechercher($ETAB1, ['action' => 'secret.un', 'module' => 'aca_test'])['total'] === 0, 'filtres par action et par module combinés');
check($audit->rechercher($ETAB1, ['module' => 'fin_test'])['rows'][0]['acteur_libelle'] === 'Test Gestionnaire', 'libellé de l\'acteur issu de l\'instantané');
check((int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === $tousAvant, 'la lecture n\'écrit rien');

// ══════════════════════════════════════════════════════════════════════
section('4. Tolérance de schéma : base où T065 n\'est pas encore appliquée');
// ══════════════════════════════════════════════════════════════════════
$pdo->exec('DROP TRIGGER IF EXISTS trg_audit_logs_no_update'); $pdo->exec('DROP TRIGGER IF EXISTS trg_audit_logs_no_delete');
$pdo->exec('ALTER TABLE audit_logs DROP FOREIGN KEY fk_al_etablissement');
$pdo->exec('ALTER TABLE audit_logs DROP COLUMN etablissement_id, DROP COLUMN actor_nom, DROP COLUMN actor_role, DROP COLUMN resultat');
AuditService::oublierSchema();
$nAvant = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
TenantContext::set($ETAB1);
$audit->log($uStaff, 'test.ancien_schema', 'audit_test', 'x', 1, null, ['a' => 1]);
$audit->log($uB, 'test.ancien_schema_ambigu', 'audit_test', 'x', 1, null, ['a' => 1]);
check((int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === $nAvant + 2 && AuditService::echecsDeLaRequete() === 0, 'écriture au mieux : fonctionne sur le schéma historique (colonnes absentes ignorées, aucun échec)');
$audit->logStrict($pdo, $uStaff, 'test.ancien_schema_strict', 'audit_test', 'x', 1);
check((int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'test.ancien_schema_strict'")->fetchColumn() === 1, 'écriture critique : fonctionne aussi sur le schéma historique');
check($audit->schemaPret() === false, 'schemaPret() = false : la consultation par établissement est signalée indisponible');
($migr['run'])($pdo);
AuditService::oublierSchema();
check($audit->schemaPret() === true, 'T065 rejouée : schéma prêt');
check((int)$pdo->query("SELECT etablissement_id FROM audit_logs WHERE action = 'test.ancien_schema'")->fetchColumn() === $ETAB1 && $pdo->query("SELECT etablissement_id FROM audit_logs WHERE action = 'test.ancien_schema_ambigu'")->fetchColumn() === null, 'lignes écrites avant T065 : l\'acteur à établissement unique est rattaché (preuve démontrée), l\'acteur membre de deux établissements reste NULL');
TenantContext::clear();

// ══════════════════════════════════════════════════════════════════════
section('5. Opérations CRITIQUES : l\'échec de l\'audit annule réellement l\'opération (état réel des données vérifié)');
// ══════════════════════════════════════════════════════════════════════
use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Repositories\RankingRepository;
use App\Modules\Academique\Services\AcademicCalculationService;
use App\Modules\Academique\Services\BulletinGenerator;
use App\Modules\Academique\Services\BulletinTokenService;
use App\Modules\Academique\Services\RankingEngine;
use App\Modules\Finance\DTO\PaymentDTO;
use App\Modules\Finance\DTO\RefundDTO;
use App\Modules\Finance\Services\AccountingService;
use App\Modules\Finance\Services\PaymentService;
use Tests\Support\BulletinFixtures;
require_once ROOT_PATH . '/tests/Support/BulletinFixtures.php';

$SECRET = hash('sha256', 'audit-etab-test-secret');
$_ENV['BULLETIN_TOKEN_SECRET'] = $SECRET; putenv('BULLETIN_TOKEN_SECRET=' . $SECRET);
$admin1 = (int)$un("SELECT id FROM users WHERE role = 'admin' AND etablissement_id = 1 AND actif = 1 ORDER BY id LIMIT 1");
// La copie de référence date de l'exercice 2025-2026 : on étend son calendrier pour que la date du jour soit comptabilisable (copie jetable uniquement).
$pdo->exec("UPDATE finance_exercices SET date_fin = DATE_ADD(CURDATE(), INTERVAL 400 DAY) WHERE id = 1");
$pdo->exec("UPDATE finance_periodes_comptables SET date_debut = DATE_SUB(CURDATE(), INTERVAL 60 DAY), date_fin = DATE_ADD(CURDATE(), INTERVAL 400 DAY) WHERE id = 12");
AuditService::oublierSchema();
TenantContext::set($ETAB1);
AuditService::reinitialiserCompteur();
$casser  = static fn() => $pdo->exec('RENAME TABLE audit_logs TO audit_logs_hs');
$reparer = static fn() => $pdo->exec('RENAME TABLE audit_logs_hs TO audit_logs');
$ligneAudit = static fn(string $module, string $action, string $entite, int $id) => $pdo->query(
    "SELECT * FROM audit_logs WHERE module = '$module' AND action = '$action' AND entite = '$entite' AND entite_id = $id ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$empreinteTables = static function (array $requetes) use ($pdo): string {
    $r = [];
    foreach ($requetes as $q) { $r[] = $pdo->query($q)->fetchAll(PDO::FETCH_NUM); }
    return md5(json_encode($r));
};

/**
 * Exécute $operation avec l'audit en panne : elle doit lever AuditIndisponibleException et laisser les données STRICTEMENT identiques
 * (mesurées en base, pas simulées). Puis, l'audit rétabli, la même opération doit aboutir.
 *
 * @return mixed résultat de l'opération réussie
 */
$critique = function (string $nom, callable $operation, callable $etat) use ($pdo, $casser, $reparer) {
    $avant = $etat();
    $casser();
    $exception = null;
    try { $operation(); } catch (\Throwable $e) { $exception = $e; } finally { $reparer(); }
    check($exception instanceof AuditIndisponibleException, "$nom : panne d'audit → AuditIndisponibleException", $exception ? get_class($exception) . ' : ' . $exception->getMessage() : 'aucune exception : l\'opération a été validée sans trace');
    check($etat() === $avant, "$nom : données RÉELLES strictement identiques après l'échec (rollback effectif)");
    check($exception !== null && !preg_match('/SQLSTATE|audit_logs|PDO/i', $exception->getMessage()), "$nom : message utilisateur sans détail technique", $exception ? $exception->getMessage() : '');
    check(!$pdo->inTransaction(), "$nom : aucune transaction laissée ouverte");
    return $operation();
};
$paiements = new PaymentService();
$compta = new AccountingService();
$aujourdhui = date('Y-m-d');

// ── Paiement : enregistrement ─────────────────────────────────────────────────────────────────────────────────────────────
$F1 = (int)$un("SELECT id FROM finance_factures WHERE statut = 'emise' AND montant_total - montant_paye >= 20000 ORDER BY id LIMIT 1");
$etatFacture = static fn(int $f) => static fn() => $empreinteTables([
    "SELECT montant_paye, statut FROM finance_factures WHERE id = $f", "SELECT id, statut FROM finance_paiements WHERE facture_id = $f ORDER BY id",
    "SELECT id FROM finance_recus WHERE facture_id = $f ORDER BY id", "SELECT id FROM finance_trop_percus WHERE facture_id = $f ORDER BY id"]);
$P1 = $critique('Enregistrement d\'un paiement', fn() => $paiements->enregistrer(new PaymentDTO($F1, 10000.0, 'ESP', $aujourdhui), $admin1), $etatFacture($F1));
$a = $ligneAudit('finance', 'create', 'paiement', (int)$P1);
check($a !== false && (int)$a['etablissement_id'] === $ETAB1 && (int)$a['user_id'] === $admin1 && $a['actor_role'] === 'admin' && $a['resultat'] === 'success', 'paiement : trace confirmée (établissement 1, acteur et rôle figés)');
check((int)$un("SELECT COUNT(*) FROM audit_logs WHERE module = 'finance' AND action = 'create' AND entite = 'paiement' AND entite_id = ?", [$P1]) === 1, 'paiement : exactement UNE trace (pas de doublon)');
check((float)$un('SELECT montant_paye FROM finance_factures WHERE id = ?', [$F1]) === 10000.0, 'paiement : la facture a bien été mise à jour par l\'opération réussie',
    'montant_paye=' . $un('SELECT montant_paye FROM finance_factures WHERE id = ?', [$F1]) . ' facture=' . $F1 . ' paiement=' . $P1 . ' montant_applique=' . $un('SELECT montant_applique FROM finance_paiements WHERE id = ?', [$P1]));

// ── Paiement : initiation puis complétion ─────────────────────────────────────────────────────────────────────────────────
$F2 = (int)$un("SELECT id FROM finance_factures WHERE statut = 'emise' AND montant_total - montant_paye >= 20000 AND id <> ? ORDER BY id LIMIT 1", [$F1]);
$etatPaiements = static fn(int $f) => static fn() => $empreinteTables(["SELECT id, statut FROM finance_paiements WHERE facture_id = $f ORDER BY id"]);
$P2 = $critique('Initiation d\'un paiement', fn() => $paiements->initierPaiement(new PaymentDTO($F2, 8000.0, 'ESP', $aujourdhui), $admin1), $etatPaiements($F2));
check($ligneAudit('finance', 'create', 'paiement', (int)$P2) !== false, 'initiation : trace confirmée');
$critique('Complétion d\'un paiement', fn() => $paiements->completer((int)$P2, $admin1), $etatFacture($F2));
check($un('SELECT statut FROM finance_paiements WHERE id = ?', [$P2]) === 'complete' && $ligneAudit('finance', 'update', 'paiement', (int)$P2) !== false, 'complétion : paiement complété et trace confirmée');

// ── Paiement : annulation ─────────────────────────────────────────────────────────────────────────────────────────────────
$F3 = (int)$un("SELECT id FROM finance_factures WHERE statut = 'emise' AND montant_total - montant_paye >= 20000 AND id NOT IN (?, ?) ORDER BY id LIMIT 1", [$F1, $F2]);
$P3 = $paiements->initierPaiement(new PaymentDTO($F3, 5000.0, 'ESP', $aujourdhui), $admin1);
$critique('Annulation d\'un paiement', fn() => $paiements->annuler((int)$P3, 'Erreur de saisie', $admin1), static fn() => $empreinteTables(["SELECT statut, annule_par, motif_annulation FROM finance_paiements WHERE id = $P3"]));
$ann = $ligneAudit('finance', 'update', 'paiement', (int)$P3);
check($un('SELECT statut FROM finance_paiements WHERE id = ?', [$P3]) === 'annule' && $ann !== false && str_contains((string)$ann['apres'], 'Erreur de saisie'), 'annulation : paiement annulé et trace confirmée (motif conservé)');

// ── Paiement : remboursement ──────────────────────────────────────────────────────────────────────────────────────────────
$critique('Remboursement', fn() => $paiements->rembourser((int)$P1, new RefundDTO(4000.0, 'ESP', 'Remboursement test'), $admin1),
    static fn() => $empreinteTables(["SELECT statut FROM finance_paiements WHERE id = $P1", "SELECT id FROM finance_remboursements WHERE paiement_id = $P1", "SELECT montant_paye, statut FROM finance_factures WHERE id = $F1"]));
$rb = $ligneAudit('finance', 'update', 'paiement', (int)$P1);
check($un('SELECT statut FROM finance_paiements WHERE id = ?', [$P1]) === 'rembourse' && $rb !== false && str_contains((string)$rb['apres'], 'rembourse'), 'remboursement : effectué et trace confirmée');

// ── Comptabilité : extourne ───────────────────────────────────────────────────────────────────────────────────────────────
$E1 = (int)$un("SELECT id FROM finance_ecritures WHERE statut = 'valide' ORDER BY id LIMIT 1");
$etatCompta = static fn() => $empreinteTables(["SELECT id, statut FROM finance_ecritures ORDER BY id", 'SELECT COUNT(*) FROM finance_lignes_ecriture', 'SELECT id, statut FROM finance_periodes_comptables ORDER BY id']);
$nouvelle = $critique('Extourne comptable', fn() => $compta->extourner($E1, 'Erreur de compte', $admin1), $etatCompta);
$ex = $ligneAudit('Finance', 'extourne', 'ecriture', $E1);
check($un('SELECT statut FROM finance_ecritures WHERE id = ?', [$E1]) === 'extourne' && (int)$nouvelle > 0 && $ex !== false && (int)$ex['etablissement_id'] === $ETAB1, 'extourne : écriture d\'extourne créée, originale marquée, trace confirmée');

// ── Comptabilité : clôture de période puis d'exercice ─────────────────────────────────────────────────────────────────────
$PER = 5;
$critique('Clôture comptable (période)', fn() => $compta->cloturerPeriode($PER, $admin1), static fn() => $empreinteTables(["SELECT id, statut, cloture_par FROM finance_periodes_comptables ORDER BY id"]));
check($un('SELECT statut FROM finance_periodes_comptables WHERE id = ?', [$PER]) === 'cloturee' && $ligneAudit('Finance', 'cloturer_periode', 'periode_comptable', $PER) !== false, 'clôture de période : effective et trace confirmée');
foreach ($pdo->query("SELECT id FROM finance_periodes_comptables WHERE statut = 'ouverte' AND exercice_id = 1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $pid) { $compta->cloturerPeriode((int)$pid, $admin1); }
check((int)$un("SELECT COUNT(*) FROM finance_periodes_comptables WHERE exercice_id = 1 AND statut = 'ouverte'") === 0, 'préparation : toutes les périodes de l\'exercice sont clôturées (chaque clôture auditée)');
// Écouteurs RÉELS enregistrés comme en production (AccountingHandler) + un espion qui compte les diffusions de FiscalYearClosed.
$espion = new class implements \Core\Listener { public int $recus = 0; public function handle(\Core\Event $event): void { $this->recus++; } };
\Core\EventDispatcher::forget(\App\Modules\Finance\Events\FiscalYearClosed::class);
\Core\EventDispatcher::listen(\App\Modules\Finance\Events\FiscalYearClosed::class, new \App\Modules\Finance\Listeners\AccountingHandler());
\Core\EventDispatcher::listen(\App\Modules\Finance\Events\FiscalYearClosed::class, $espion);
$critique('Clôture comptable (exercice)', fn() => $compta->cloturerExercice(1, $admin1),
    static fn() => $empreinteTables(["SELECT id, statut, date_cloture, cloture_par FROM finance_exercices ORDER BY id", "SELECT id, statut, cloture_par FROM finance_periodes_comptables ORDER BY id", "SELECT id, source, statut FROM finance_ecritures ORDER BY id", 'SELECT COUNT(*) FROM finance_lignes_ecriture']));
check($un('SELECT statut FROM finance_exercices WHERE id = 1') === 'cloture' && $ligneAudit('Finance', 'cloturer_exercice', 'exercice', 1) !== false, 'clôture d\'exercice : effective et trace confirmée');
check((int)$un("SELECT COUNT(*) FROM audit_logs WHERE action = 'cloturer_exercice' AND entite_id = 1") === 1, 'NON-RÉGRESSION — une clôture d\'exercice produit EXACTEMENT UNE entrée d\'audit (listener AccountingHandler enregistré, plus de doublon)');
check((int)$un("SELECT COUNT(*) FROM audit_logs WHERE action = 'cloturer_exercice' AND entite = 'exercice_comptable'") === 0, 'non-régression : plus aucune entrée « exercice_comptable » issue du listener');
$clot = json_decode((string)$un("SELECT apres FROM audit_logs WHERE action = 'cloturer_exercice' AND entite_id = 1"), true);
check(($clot['statut'] ?? '') === 'cloture' && isset($clot['resultat_net'], $clot['libelle'], $clot['date_fin']) && (int)$un("SELECT etablissement_id FROM audit_logs WHERE action = 'cloturer_exercice' AND entite_id = 1") === $ETAB1, 'l\'entrée unique conserve tout ce que portaient les deux anciennes (statut, résultat net, libellé, date de fin) et l\'établissement');
check($espion->recus === 1, 'l\'événement FiscalYearClosed est diffusé UNE fois, après validation (jamais pour la tentative annulée par la panne d\'audit)', (string)$espion->recus);
$avantListener = (int)$un('SELECT COUNT(*) FROM audit_logs');
(new \App\Modules\Finance\Listeners\AccountingHandler())->handle(new \App\Modules\Finance\Events\FiscalYearClosed(exerciceId: 1, libelle: 'X', dateFin: '2027-01-01', resultatNet: 0.0, closedById: $admin1));
check((int)$un('SELECT COUNT(*) FROM audit_logs') === $avantListener, 'le listener AccountingHandler n\'écrit plus aucune entrée d\'audit pour FiscalYearClosed');
\Core\EventDispatcher::forget(\App\Modules\Finance\Events\FiscalYearClosed::class);

// ── Publication d'un bulletin ─────────────────────────────────────────────────────────────────────────────────────────────
$fx = new BulletinFixtures($pdo);
$calc = new AcademicCalculationService(['note_passage' => 10.0]);
$gen = new BulletinGenerator($calc, new RankingEngine($calc, new RankingRepository(), ['note_passage' => 10.0]), new BulletinRepository(), 8.0, 10.0, null, null, null, BulletinTokenService::fromEnvironment());
$tokens = BulletinTokenService::fromEnvironment();
$perB = $fx->periode();
$eB = $fx->eleve('PersoPublic');
$jeton = $tokens->issue($ETAB1, $eB, $perB);
$bul = $fx->bulletin($eB, $perB, $jeton, 'brouillon', 2);
$fx->rendrePubliable($bul['id'], $eB, $perB);
$critique('Publication d\'un bulletin', fn() => $gen->publierBulletin($jeton, $admin1), static fn() => $empreinteTables(["SELECT id, statut, published_at FROM bulletins_v2 WHERE id = {$bul['id']}"]));
$pb = $ligneAudit('academique', 'publier', 'bulletin', $eB);
check($un('SELECT statut FROM bulletins_v2 WHERE id = ?', [$bul['id']]) === 'publie' && $pb !== false && (int)$pb['etablissement_id'] === $ETAB1, 'publication : bulletin publié et trace confirmée');
check((int)$un("SELECT COUNT(*) FROM audit_logs WHERE module = 'academique' AND action = 'publier' AND entite = 'bulletin' AND entite_id = ?", [$eB]) === 1, 'publication : exactement UNE trace');
check(!str_contains((string)$pb['apres'], $jeton) && str_contains((string)$pb['apres'], 'jeton_empreinte'), 'publication : le jeton n\'est jamais écrit, seulement son empreinte');
check(AuditService::echecsDeLaRequete() === 0, 'les écritures critiques n\'utilisent jamais le repli silencieux (aucun échec « au mieux » compté)');
TenantContext::clear();

// ══════════════════════════════════════════════════════════════════════
section('6. Lacunes comblées : paramètres, utilisateurs et rôles, mot de passe, sanctions, récompenses, noms d\'élèves');
// ══════════════════════════════════════════════════════════════════════
use App\Events\EleveCreated;
use App\Listeners\AuditHandler;
use App\Modules\VieScolaire\Discipline\Events\DisciplinaryActionAssigned;
use App\Modules\VieScolaire\Discipline\Events\DisciplineCaseClosed;
use App\Modules\VieScolaire\Discipline\Listeners\AuditListener as DisciplineAuditListener;
use App\Modules\VieScolaire\Recompenses\Events\RewardGranted;
use App\Modules\VieScolaire\Recompenses\Events\RewardRevoked;
use App\Modules\VieScolaire\Recompenses\Listeners\AuditListener as RecompensesAuditListener;
use Core\Tenant\SettingsService;

AuditService::oublierSchema();
TenantContext::set($ETAB1);
$n0 = static fn() => (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM audit_logs')->fetchColumn();
$apresId = static fn(int $depuis, string $where) => $pdo->query("SELECT * FROM audit_logs WHERE id > $depuis AND $where ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// ── Élèves : plus aucun nom ni prénom ─────────────────────────────────────────────────────────────────────────────────────
$d = $n0();
(new AuditHandler())->handle(new EleveCreated(eleveId: 4242, nom: 'Diallo', prenom: 'Aïssata', matricule: 'M-2026-77', classeId: 3, createdById: $admin1));
$l = $apresId($d, "module = 'eleves'")[0] ?? null;
check($l !== null && (int)$l['etablissement_id'] === $ETAB1 && (int)$l['entite_id'] === 4242, 'création d\'un élève : événement journalisé dans l\'établissement 1');
check($l !== null && !str_contains((string)$l['apres'], 'Diallo') && !str_contains((string)$l['apres'], 'Aïssata') && str_contains((string)$l['apres'], 'M-2026-77'), 'création d\'un élève : ni nom ni prénom dans la trace, matricule et identifiants conservés', (string)($l['apres'] ?? ''));

// ── Paramètres de l'établissement ─────────────────────────────────────────────────────────────────────────────────────────
$reglages = SettingsService::make();
$d = $n0();
$reglages->set($ETAB1, 'academique', 'note_passage', '11', 'string', $admin1);
$p = $apresId($d, "module = 'parametres'");
check(count($p) === 1 && (int)$p[0]['etablissement_id'] === $ETAB1 && (int)$p[0]['user_id'] === $admin1, 'paramètre modifié : un événement, établissement 1, acteur identifié');
$reglages->set($ETAB1, 'academique', 'note_passage', '12', 'string', $admin1);
$p = $apresId($d, "module = 'parametres'");
$derniere = end($p);
check(count($p) === 2 && json_decode($derniere['avant'], true)['valeur'] === '11' && json_decode($derniere['apres'], true)['valeur'] === '12' && json_decode($derniere['apres'], true)['cle'] === 'note_passage', 'ancienne et nouvelle valeur du paramètre conservées');
$reglages->set($ETAB1, 'academique', 'note_passage', '12', 'string', $admin1);
check(count($apresId($d, "module = 'parametres'")) === 2, 'réenregistrement de la même valeur : aucun événement parasite');
$reglages->set($ETAB1, 'notifications', 'smtp_password', 'mot-de-passe-secret-1', 'string', $admin1);
$reglages->set($ETAB1, 'notifications', 'smtp_password', 'mot-de-passe-secret-2', 'string', $admin1);
$brut = json_encode($apresId($d, "module = 'parametres'"));
check(!str_contains($brut, 'mot-de-passe-secret') && str_contains($brut, '***'), 'paramètre secret : valeur masquée (avant et après)');

// ── Utilisateurs et rôles (parcours HTTP réel, écritures conservées sur la copie jetable) ───────────────────────────────
$requete = static function (string $methode, string $uri, array $post = []) use ($nomCopie, $admin1): array {
    [, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/request_as.php', 'admin', (string)$admin1, $methode, '/ecole_app' . $uri, json_encode($post)], ['REQUEST_AS_DB' => $nomCopie, 'REQUEST_AS_COMMIT' => '1']);
    return preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0, 'brut' => $sortie];
};
$emailNouveau = 'nouveau.' . bin2hex(random_bytes(3)) . '@exemple.test';
$d = $n0();
$r = $requete('POST', '/utilisateurs/store', ['nom' => 'Nouvel Agent', 'prenom' => 'Prénom', 'email' => $emailNouveau, 'role' => 'secretaire', 'password' => 'Un-Mot-De-Passe-Solide-9', 'password_confirmation' => 'Un-Mot-De-Passe-Solide-9', 'telephone' => '+22799000000']);
$nid = (int)$un('SELECT id FROM users WHERE email = ?', [$emailNouveau]);
$l = $apresId($d, "module = 'utilisateurs' AND action = 'create'")[0] ?? null;
check($nid > 0 && $l !== null && (int)$l['entite_id'] === $nid && (int)$l['etablissement_id'] === $ETAB1 && (int)$l['user_id'] === $admin1 && $l['actor_role'] === 'admin', 'création d\'un utilisateur : journalisée (établissement 1, acteur et rôle)', json_encode($r));
check($l !== null && !str_contains((string)$l['apres'], 'exemple.test') && !str_contains((string)$l['apres'], '99000000') && !str_contains((string)$l['apres'], 'Solide') && json_decode($l['apres'], true)['role'] === 'secretaire', 'création : ni e-mail, ni téléphone, ni mot de passe dans la trace ; rôle conservé');
$d = $n0();
$requete('POST', "/utilisateurs/$nid", ['nom' => 'Nouvel Agent', 'prenom' => 'Prénom', 'email' => $emailNouveau, 'role' => 'comptable', 'telephone' => '+22799000001', 'actif' => '1', 'password' => 'Autre-Mot-De-Passe-Solide-7', 'password_confirmation' => 'Autre-Mot-De-Passe-Solide-7']);
$l = $apresId($d, "module = 'utilisateurs' AND action = 'update'")[0] ?? null;
$apres = $l ? json_decode($l['apres'], true) : [];
$avant = $l ? json_decode($l['avant'], true) : [];
check($l !== null && $avant['role'] === 'secretaire' && $apres['role'] === 'comptable', 'changement de rôle : ancien et nouveau rôle conservés');
check($l !== null && in_array('mot_de_passe', $apres['champs_modifies'], true) && in_array('telephone', $apres['champs_modifies'], true) && !str_contains((string)$l['apres'], 'Solide') && !str_contains((string)$l['apres'], '99000001'), 'modification : nature des champs modifiés (mot de passe, téléphone) sans aucune valeur');
$d = $n0();
$requete('POST', "/utilisateurs/$nid/toggle-actif");
$l = $apresId($d, "module = 'utilisateurs' AND action = 'update'")[0] ?? null;
check($l !== null && json_decode($l['avant'], true)['actif'] === 1 && json_decode($l['apres'], true)['actif'] === 0, 'désactivation d\'un compte : journalisée (actif 1 → 0)');
$d = $n0();
$requete('POST', "/utilisateurs/$nid/delete");
$l = $apresId($d, "module = 'utilisateurs' AND action = 'delete'")[0] ?? null;
check((int)$un('SELECT COUNT(*) FROM users WHERE id = ?', [$nid]) === 0 && $l !== null && (int)$l['entite_id'] === $nid && (int)$l['etablissement_id'] === $ETAB1, 'suppression d\'un compte : journalisée, la trace survit au compte supprimé');
check((int)$un("SELECT COUNT(*) FROM audit_logs WHERE module = 'utilisateurs' AND entite_id = ? AND user_id IS NULL", [$nid]) === 0 && (int)$un("SELECT COUNT(*) FROM audit_logs WHERE entite_id = ? AND module = 'utilisateurs'", [$nid]) === 4, '4 événements sur ce compte (création, modification, désactivation, suppression), tous rattachés à leur acteur');

// ── Mot de passe depuis le profil ─────────────────────────────────────────────────────────────────────────────────────────
$d = $n0();
$requete('POST', '/profile/password', ['current_password' => 'Test-1234!', 'password' => 'Soleil-Levant-Niger-2027#', 'password_confirmation' => 'Soleil-Levant-Niger-2027#']);
$l = $apresId($d, "module = 'auth' AND action = 'password_changed'")[0] ?? null;
check($l !== null && (int)$l['user_id'] === $admin1 && (int)$l['etablissement_id'] === $ETAB1 && !str_contains((string)$l['apres'], 'Soleil'), 'changement de mot de passe : journalisé, sans aucun mot de passe');
// Les requêtes suivantes simulent des sessions ouvertes AVANT ce changement : on retire la date de changement pour qu'elles restent valides (l'invalidation est testée dans AuthSessionTest).
try { $pdo->prepare('UPDATE users SET password_changed_at = NULL WHERE id = ?')->execute([$admin1]); } catch (\Throwable) { /* colonne absente (T069 non appliquée) */ }

// ── Sanctions et récompenses : désormais en base, par établissement ───────────────────────────────────────────────────
$d = $n0();
(new DisciplineAuditListener())->handle(new DisciplinaryActionAssigned(sanctionId: 8801, dossierId: 7701, eleveId: 4242, typeSanction: 'avertissement', dateSanction: '2026-11-02', anneeScolaire: '2026-2027', prononceParId: $admin1));
(new DisciplineAuditListener())->handle(new DisciplineCaseClosed(dossierId: 7701, eleveId: 4242, classeId: 3, anneeScolaire: '2026-2027', nbIncidentsTotal: 2, closParId: $admin1));
(new RecompensesAuditListener())->handle(new RewardGranted(rewardId: 6601, eleveId: 4242, classeId: 3, anneeScolaire: '2026-2027', categorieCode: 'excellence', niveau: 'or', motif: 'Très bon trimestre', attribueParId: $admin1));
(new RecompensesAuditListener())->handle(new RewardRevoked(rewardId: 6601, eleveId: 4242, classeId: 3, anneeScolaire: '2026-2027', motifRevocation: 'Erreur de saisie', revoqueParId: $admin1));
$v = $apresId($d, "module = 'vie_scolaire'");
$actions = array_column($v, 'action');
check($actions === ['sanction_prononcee', 'dossier_clos', 'recompense_attribuee', 'recompense_revoquee'], 'sanctions et récompenses : 4 événements écrits en base', implode(',', $actions));
check(count(array_filter($v, fn($x) => (int)$x['etablissement_id'] === $ETAB1)) === 4 && (int)$audit->rechercher($ETAB2, ['q' => 'sanction_prononcee'])['total'] === 0, 'sanctions et récompenses : rattachées à l\'établissement 1, invisibles pour l\'établissement 2');
check(!str_contains(json_encode($v), 'Diallo') && str_contains((string)$v[0]['apres'], '4242') && (int)$v[0]['entite_id'] === 8801 && $v[0]['entite'] === 'sanction', 'sanctions : identifiants de l\'élève et de la sanction, aucun nom');
TenantContext::clear();

// ══════════════════════════════════════════════════════════════════════
section('7. Consultation HTTP : permission audit.view, isolation par établissement, manipulation d\'URL');
// ══════════════════════════════════════════════════════════════════════
AuditService::oublierSchema();
// Événements reconnaissables dans chaque établissement (écrits directement : la consultation est testée, pas l'écriture)
$vues = static fn(string $action, int $etab, string $module = 'vue_test', ?int $user = null, string $entite = 'doc', ?int $eid = null, ?string $apres = null) =>
    $pdo->prepare('INSERT INTO audit_logs (etablissement_id, user_id, action, module, entite, entite_id, apres, resultat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$etab, $user, $action, $module, $entite, $eid, $apres, 'success']);
for ($i = 1; $i <= 55; $i++) { $vues("evenement.etab1.$i", $ETAB1, 'vue_test', $admin1, 'doc', 7000 + $i, '{"montant": 10}'); }
$vues('evenement.etab2.secret', $ETAB2, 'vue_test', $uE, 'doc', 8000, '{"note": "CONFIDENTIEL-ETAB2"}');
$vues('<script>alert(1)</script>', $ETAB1, 'xss_test', $admin1, 'doc', 9000);
$idEtab2 = (int)$un("SELECT id FROM audit_logs WHERE action = 'evenement.etab2.secret'");
$idEtab1 = (int)$un("SELECT id FROM audit_logs WHERE action = 'evenement.etab1.1'");
$compte = static fn(string $role): int => (int)$un("SELECT id FROM users WHERE role = ? AND etablissement_id = 1 AND actif = 1 ORDER BY id LIMIT 1", [$role]);
$directeur1 = $compte('directeur');
$http = static function (?string $role, int $uid, string $uri, array $env = []) use ($nomCopie, $dossierSecours): array {
    $corps = sys_get_temp_dir() . '/audit_http_' . bin2hex(random_bytes(4)) . '.html';
    [, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/request_as.php', $role ?? 'guest', (string)$uid, 'GET', '/ecole_app' . $uri],
        $env + ['REQUEST_AS_DB' => $nomCopie, 'REQUEST_AS_BODY_OUT' => $corps, 'AUDIT_FALLBACK_DIR' => $dossierSecours]);
    $r = preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0];
    $r['corps'] = (string)@file_get_contents($corps); @unlink($corps);
    return $r;
};
$texte = static fn(array $r): string => (string)preg_replace('/\s+/', ' ', strip_tags($r['corps']));

$r = $http(null, 0, '/parametres/audit');
check(in_array($r['status'], [302, 200], true) && !str_contains($r['corps'], 'evenement.etab1'), 'visiteur sans session : aucune donnée du journal');
foreach (['secretaire', 'comptable', 'enseignant', 'parent', 'eleve'] as $role) {
    $u = $compte($role);
    if ($u === 0) { continue; }
    $r = $http($role, $u, '/parametres/audit');
    check($r['status'] === 403 && !str_contains($r['corps'], 'evenement.etab1'), "$role : accès refusé (403), aucune donnée");
    $r = $http($role, $u, "/parametres/audit/$idEtab1");
    check($r['status'] === 403, "$role : détail d'un événement refusé (403)");
}
$r = $http('admin', $admin1, '/parametres/audit?q=evenement.etab');
$t = $texte($r);
check($r['status'] === 200 && str_contains($t, 'evenement.etab1.') && !str_contains($t, 'evenement.etab2') && !str_contains($t, 'CONFIDENTIEL-ETAB2'), 'administrateur : voit les événements de son établissement, aucun de l\'établissement 2');
check(str_contains($t, '56 événement(s)') || preg_match('/(\d+) événement\(s\) de votre établissement/', $t) === 1, 'administrateur : le total affiché concerne son établissement', substr($t, 0, 80));
$r = $http('directeur', $directeur1, '/parametres/audit?q=evenement.etab1.3');
check($r['status'] === 200 && str_contains($texte($r), 'evenement.etab1.3'), 'directeur : accès autorisé à l\'ensemble du journal de son établissement');
// Manipulation de l'URL et des filtres
$r = $http('admin', $admin1, '/parametres/audit?etablissement_id=' . $ETAB2 . '&etab=' . $ETAB2 . '&tenant=' . $ETAB2 . '&q=evenement.etab2');
check($r['status'] === 200 && !str_contains($r['corps'], 'evenement.etab2.secret') && !str_contains($r['corps'], 'CONFIDENTIEL-ETAB2'), 'URL : etablissement_id / etab / tenant ignorés, aucune donnée de l\'établissement 2');
$r = $http('admin', $admin1, '/parametres/audit?user_id=' . $uE);
check($r['status'] === 200 && !str_contains($r['corps'], 'evenement.etab2.secret') && str_contains($texte($r), 'Aucun événement'), 'filtre sur un utilisateur de l\'établissement 2 : aucun résultat');
$r = $http('admin', $admin1, "/parametres/audit/$idEtab2");
check($r['status'] === 404 && !str_contains($r['corps'], 'CONFIDENTIEL-ETAB2'), 'détail d\'un événement de l\'établissement 2 par son identifiant : 404, aucune fuite');
$r = $http('admin', $admin1, "/parametres/audit/$idEtab1");
check($r['status'] === 200 && str_contains($texte($r), 'Ancienne valeur') && str_contains($r['corps'], '&quot;montant&quot;: 10'), 'détail d\'un événement de son propre établissement : accessible, avec ancienne et nouvelle valeur');
$r = $http('admin', $admin1, '/parametres/audit/99999999');
check($r['status'] === 404, 'événement inexistant : 404');
$r = $http('admin', $admin1, '/parametres/audit/abc');
check(in_array($r['status'], [404, 400], true), 'identifiant non numérique : refusé', (string)$r['status']);
$r = $http('admin', $admin1, '/parametres/audit', ['REQUEST_AS_ETAB' => (string)$ETAB2]);
check(!str_contains($r['corps'], 'evenement.etab2.secret') && !str_contains($r['corps'], 'CONFIDENTIEL-ETAB2'), 'session d\'un établissement où l\'utilisateur n\'appartient pas : aucune donnée de l\'établissement 2', 'statut ' . $r['status']);
$r = $http('admin', $admin1, "/parametres/audit?q=" . rawurlencode("' OR '1'='1") . '&module=' . rawurlencode("x' OR 1=1 --"));
check($r['status'] === 200 && !str_contains($r['corps'], 'evenement.etab2.secret') && str_contains($texte($r), 'Aucun événement'), 'tentative d\'injection SQL dans les filtres : aucun effet');
$r = $http('admin', $admin1, '/parametres/audit?module=xss_test');
check($r['status'] === 200 && !str_contains($r['corps'], '<script>alert(1)') && str_contains($r['corps'], '&lt;script&gt;alert(1)&lt;/script&gt;'), 'contenu hostile dans un événement : échappé à l\'affichage');
$r = $http('admin', $admin1, '/parametres/audit?module=vue_test&page=2');
check($r['status'] === 200 && str_contains($texte($r), 'Page 2 sur 2'), 'pagination HTTP : page 2 sur 2');
$r = $http('admin', $admin1, '/parametres/audit?date_debut=' . date('Y-m-d') . '&date_fin=' . date('Y-m-d') . '&module=vue_test');
check($r['status'] === 200 && str_contains($texte($r), 'evenement.etab1.'), 'filtre par période HTTP');
check((int)$un("SELECT COUNT(*) FROM audit_logs WHERE module = 'audit'") === 0, 'la consultation du journal n\'écrit pas dans le journal');
$r = $http('admin', $admin1, '/parametres');
check($r['status'] === 200 && str_contains($r['corps'], '/parametres/audit'), 'carte « Journal d\'audit » présente dans Paramètres pour l\'administrateur');
$r = $http('secretaire', $compte('secretaire'), '/parametres');
check(!str_contains($r['corps'], '/parametres/audit'), 'carte « Journal d\'audit » absente pour un rôle sans audit.view');

// ══════════════════════════════════════════════════════════════════════
section('8. Journalisation non critique : panne, alerte limitée à l\'établissement concerné, rejeu');
// ══════════════════════════════════════════════════════════════════════
foreach (glob($dossierSecours . '/*') ?: [] as $f) { @unlink($f); }
AuditService::oublierSchema(); AuditService::reinitialiserCompteur();
$journalTechnique = ROOT_PATH . '/storage/logs/app-' . date('Y-m-d') . '.log';
clearstatcache(); $tailleAvant = is_file($journalTechnique) ? filesize($journalTechnique) : 0;
$platAvant = (int)$pdo->query('SELECT COUNT(*) FROM platform_audit_logs')->fetchColumn();
$nAvant = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();

$casser();
TenantContext::set($ETAB1);
$reglages->set($ETAB1, 'academique', 'note_passage', '13', 'string', $admin1);                  // opération non critique : doit aboutir
$audit->log($uStaff, 'panne.etab1', 'vue_test', 'doc', 1, null, ['a' => 1]);
TenantContext::set($ETAB2);
$audit->log($uE, 'panne.etab2', 'vue_test', 'doc', 2, null, ['a' => 2]);
TenantContext::clear();
$audit->log($uStaff, 'panne.sans_contexte', 'vue_test', 'doc', 3);
$reparer();
check($un("SELECT valeur FROM etab_settings WHERE etablissement_id = 1 AND section = 'academique' AND cle = 'note_passage'") === '13', 'opération non critique (modification de paramètre) : AUTORISÉE malgré la panne, donnée enregistrée');
check(AuditService::echecsDeLaRequete() === 4, 'les 4 échecs d\'écriture sont comptés (jamais présentés comme réussis)', (string)AuditService::echecsDeLaRequete());
check((int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === $nAvant, 'aucune ligne n\'a été inscrite dans le journal pendant la panne');
$fichiers = glob($dossierSecours . '/audit_fallback-*.jsonl');
$lignes = array_map(fn($x) => json_decode($x, true), file($fichiers[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
$etabs = array_map(fn($l) => $l['etablissement_id'], $lignes);
sort($etabs);
check(count($fichiers) === 1 && count($lignes) === 4 && $etabs === [null, $ETAB1, $ETAB1, $ETAB2], 'enregistrement de secours : 4 événements complets, chacun avec son établissement (ou NULL)', json_encode($etabs));
check(!str_contains(json_encode($lignes), 'password') && $lignes[0]['_cause'] !== '', 'secours : cause technique consignée, aucun secret');
clearstatcache(); $texteLog = (string)substr((string)@file_get_contents($journalTechnique), $tailleAvant);
check(substr_count($texteLog, 'écriture d\'audit échouée') === 4, 'journal technique : 4 erreurs consignées (module, action, établissement, cause)');
check((int)$pdo->query('SELECT COUNT(*) FROM platform_audit_logs')->fetchColumn() === $platAvant, 'indépendance : le journal global platform_audit_logs n\'a reçu aucun de ces événements');

// Alerte visible des seuls titulaires de audit.view, et uniquement pour LEUR établissement
$adminB = $creerUser('AdminB', $ETAB2, 'admin');
$sec1 = $compte('secretaire');
$r1 = $http('admin', $admin1, '/parametres/audit?module=vue_test');
$t1 = $texte($r1);
check(str_contains($t1, 'traçabilité incomplète') && str_contains($t1, '2 événement(s) de votre établissement'), 'administrateur de l\'établissement 1 : alerte visible, 2 événements (les siens, pas ceux de l\'établissement 2 ni sans établissement)', substr($t1, 0, 60));
check(!preg_match('/PDOException|SQLSTATE|audit_logs|RuntimeException/', $r1['corps']), 'alerte : aucun détail technique affiché');
$r2 = $http('admin', $adminB, '/parametres/audit?module=vue_test', ['REQUEST_AS_COMMIT' => '1']);   // premier accès d'un nouvel établissement : l'amorçage comptable ouvre sa propre transaction
$t2 = $texte($r2);
check($r2['status'] === 200 && str_contains($t2, 'traçabilité incomplète') && str_contains($t2, '1 événement(s) de votre établissement'), 'administrateur de l\'établissement 2 : alerte visible, 1 seul événement (le sien)', substr($t2, 0, 500) . ' | ' . substr($r2['corps'], 0, 600));
$rs = $http('secretaire', $sec1, '/parametres');
check($rs['status'] !== 0 && !str_contains($texte($rs), 'traçabilité incomplète'), 'rôle sans audit.view : aucune alerte');
check(AuditService::resumeSecours($ETAB1)['count'] === 2 && AuditService::resumeSecours($ETAB2)['count'] === 1 && AuditService::resumeSecours(999)['count'] === 0 && AuditService::totalSecours() === 4, 'comptages : établissement 1 = 2, établissement 2 = 1, inconnu = 0 ; total serveur = 4 (réservé aux scripts)');

// Rejeu
$rejeu = lancer([PHP_BINARY, ROOT_PATH . '/database/audit-rejouer.php'], ['DB_DATABASE' => $nomCopie, 'AUDIT_FALLBACK_DIR' => $dossierSecours, 'PLATFORM_AUDIT_FALLBACK_DIR' => $dossierSecours . '/plateforme_vide']);
check($rejeu[0] === 0 && str_contains($rejeu[1], 'Journaux des établissements : 4 événement(s) rejoué(s) ; 0 en échec'), 'rejeu : database/audit-rejouer.php réinscrit les 4 événements', trim($rejeu[1]));
$rej = $pdo->query("SELECT action, etablissement_id, created_at FROM audit_logs WHERE action LIKE 'panne.%' ORDER BY action")->fetchAll(PDO::FETCH_ASSOC);
$map = array_column($rej, 'etablissement_id', 'action');
check(count($rej) === 3 && (int)$pdo->query("SELECT etablissement_id FROM audit_logs WHERE module = 'parametres' AND apres LIKE '%\"13\"%' ORDER BY id DESC LIMIT 1")->fetchColumn() === $ETAB1 && (int)($map['panne.etab1'] ?? 0) === $ETAB1 && (int)($map['panne.etab2'] ?? 0) === $ETAB2 && array_key_exists('panne.sans_contexte', $map) && $map['panne.sans_contexte'] === null, 'rejeu : établissement d\'origine conservé (1, 2 et NULL), aucune attribution à l\'établissement 1 par défaut', json_encode([$rej, $ETAB1, $ETAB2, $pdo->query("SELECT id, etablissement_id, apres FROM audit_logs WHERE module = 'parametres' ORDER BY id DESC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC)]));
check(strtotime($rej[0]['created_at']) <= time() && count(glob($dossierSecours . '/*.rejoue')) === 1 && glob($dossierSecours . '/audit_fallback-*.jsonl') === [], 'rejeu : date d\'origine conservée ; fichier renommé .rejoue (jamais supprimé)');
check((int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'panne.%'")->fetchColumn() === 3 && (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === $nAvant + 4, 'rejeu : exactement 4 lignes ajoutées, rien d\'autre modifié', 'lignes=' . $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() . ' attendu=' . ($nAvant + 4));
$r1 = $http('admin', $admin1, '/parametres/audit?module=vue_test');
check($r1['status'] === 200 && !str_contains($texte($r1), 'traçabilité incomplète'), 'après le rejeu, l\'alerte disparaît');
check(str_contains($texte($http('admin', $admin1, '/parametres/audit?q=panne.etab1')), 'panne.etab1') && !str_contains($texte($http('admin', $admin1, '/parametres/audit?q=panne.etab2')), 'panne.etab2'), 'rejeu : l\'événement rejoué est visible dans SON établissement uniquement');
$rejeu2 = lancer([PHP_BINARY, ROOT_PATH . '/database/audit-rejouer.php'], ['DB_DATABASE' => $nomCopie, 'AUDIT_FALLBACK_DIR' => $dossierSecours, 'PLATFORM_AUDIT_FALLBACK_DIR' => $dossierSecours . '/plateforme_vide']);
check($rejeu2[0] === 0 && str_contains($rejeu2[1], 'Aucun événement en attente') && (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === $nAvant + 4, 'rejeu relancé : rien à rejouer, aucun doublon');
// Ligne injouable : conservée, code de sortie non nul
file_put_contents($dossierSecours . '/audit_fallback-' . date('Y-m-d') . '.jsonl', json_encode(['etablissement_id' => $ETAB1, 'user_id' => 987654321, 'action' => 'injouable', 'module' => 'vue_test', 'created_at' => date('Y-m-d H:i:s')]) . "\n");
$rejeu3 = lancer([PHP_BINARY, ROOT_PATH . '/database/audit-rejouer.php'], ['DB_DATABASE' => $nomCopie, 'AUDIT_FALLBACK_DIR' => $dossierSecours, 'PLATFORM_AUDIT_FALLBACK_DIR' => $dossierSecours . '/plateforme_vide']);
check($rejeu3[0] === 3 && str_contains($rejeu3[1], '1 en échec') && AuditService::resumeSecours($ETAB1)['count'] === 1, 'ligne injouable : conservée dans le fichier de secours, alerte maintenue, code de sortie 3');
foreach (glob($dossierSecours . '/audit_fallback-*.jsonl') ?: [] as $f) { @unlink($f); }
TenantContext::clear();

// ══════════════════════════════════════════════════════════════════════
section('9. Portail admin (module désactivé) : lecteurs isolés ; garde-fou contre toute lecture non isolée ; limites documentées');
// ══════════════════════════════════════════════════════════════════════
use App\Modules\Portals\Admin\Widgets\ActiviteRecenteWidget;
use Core\Platform\PlatformAuditService;

AuditService::oublierSchema();
$widget = new ActiviteRecenteWidget();
$w1 = $widget->getData($ETAB1, $admin1, [])['logs'];
$w2 = $widget->getData($ETAB2, $uE, [])['logs'];
$actions1 = array_column($w1, 'action'); $actions2 = array_column($w2, 'action');
check(count($w1) === 10 && !in_array('evenement.etab2.secret', $actions1, true) && !in_array('panne.etab2', $actions1, true), 'widget « activité récente » : 10 événements de l\'établissement 1, aucun de l\'établissement 2');
check($w2 !== [] && count(array_filter($actions2, fn($a) => str_starts_with($a, 'evenement.etab1'))) === 0, 'widget « activité récente » : l\'établissement 2 ne voit aucun événement de l\'établissement 1');
check($widget->getData(0, 0, [])['logs'] === [] && $widget->getData(-5, 0, [])['logs'] === [], 'widget : établissement invalide → liste vide (jamais de lecture globale)');
$sourceAdmin = file_get_contents(ROOT_PATH . '/app/Modules/Portals/Admin/Controllers/AdminPortalController.php');
check(str_contains($sourceAdmin, '->rechercher($etab,') && !preg_match('/FROM\s+audit_logs/i', $sourceAdmin), 'AdminPortalController::audit() lit le journal par AuditService::rechercher($etab) — plus aucune requête SQL globale');

// Garde-fou : aucune lecture SQL de audit_logs en dehors d'AuditService (qui impose un établissement explicite)
$lectures = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_PATH . '/app', FilesystemIterator::SKIP_DOTS));
foreach ([ROOT_PATH . '/core', ROOT_PATH . '/app'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $fichier) {
        if ($fichier->getExtension() !== 'php') { continue; }
        $chemin = str_replace('\\', '/', $fichier->getPathname());
        if (str_ends_with($chemin, 'app/Services/AuditService.php')) { continue; }
        $src = file_get_contents($chemin);
        if (preg_match('/(FROM|JOIN)\s+`?audit_logs`?/i', $src)) { $lectures[] = substr($chemin, strlen(ROOT_PATH) + 1); }
    }
}
check($lectures === [], 'garde-fou : aucune lecture SQL de audit_logs hors AuditService (qui exige un établissement explicite)', implode(', ', $lectures));
$ecritures = [];
foreach ([ROOT_PATH . '/core', ROOT_PATH . '/app'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $fichier) {
        if ($fichier->getExtension() !== 'php') { continue; }
        $chemin = str_replace('\\', '/', $fichier->getPathname());
        if (str_ends_with($chemin, 'app/Services/AuditService.php')) { continue; }
        if (preg_match('/INSERT\s+INTO\s+`?audit_logs`?/i', file_get_contents($chemin))) { $ecritures[] = substr($chemin, strlen(ROOT_PATH) + 1); }
    }
}
check($ecritures === ['app/Modules/Academique/Services/BulletinTokenRotator.php'], 'écritures directes dans audit_logs hors AuditService : seule la rotation des jetons (script d\'administration, hors contexte tenant)', implode(', ', $ecritures));

// Indépendance des deux journaux
$platAvant = (int)$pdo->query('SELECT COUNT(*) FROM platform_audit_logs')->fetchColumn(); $audAvant = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
PlatformAuditService::definirDossierSecours($dossierSecours);
(new PlatformAuditService($pdo))->record('test.global_independant', ['entity_type' => 'x']);
TenantContext::set($ETAB1); $audit->log($admin1, 'test.etablissement_independant', 'vue_test', 'x', 1); TenantContext::clear();
check((int)$pdo->query('SELECT COUNT(*) FROM platform_audit_logs')->fetchColumn() === $platAvant + 1 && (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === $audAvant + 1
      && (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'test.global_independant'")->fetchColumn() === 0 && (int)$pdo->query("SELECT COUNT(*) FROM platform_audit_logs WHERE action = 'test.etablissement_independant'")->fetchColumn() === 0,
      'les deux journaux sont indépendants : chaque événement n\'est écrit que dans son propre journal');
foreach (['platform_audit_logs' => 'trg_platform_audit_no_update', 'audit_logs' => 'trg_audit_logs_no_update'] as $table => $trigger) {
    check((int)$un("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?", [$trigger]) === 1, "protection de $table intacte ($trigger)");
}

// Limites documentées de la protection
$uSupprime = $creerUser('CompteTemporaire', $ETAB1, 'comptable');
TenantContext::set($ETAB1); $audit->log($uSupprime, 'test.avant_suppression_compte', 'vue_test', 'x', 1); TenantContext::clear();
$idLigne = (int)$un("SELECT id FROM audit_logs WHERE action = 'test.avant_suppression_compte'");
$pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uSupprime]);
$ligneApres = $pdo->query("SELECT user_id, actor_nom, actor_role, etablissement_id FROM audit_logs WHERE id = $idLigne")->fetch(PDO::FETCH_ASSOC);
check($ligneApres !== false && $ligneApres['user_id'] === null && $ligneApres['actor_nom'] === 'Test CompteTemporaire' && $ligneApres['actor_role'] === 'comptable' && (int)$ligneApres['etablissement_id'] === $ETAB1,
      'LIMITE DOCUMENTÉE : la suppression physique d\'un compte met user_id à NULL malgré les déclencheurs (action de clé étrangère), mais l\'acteur reste identifié par son instantané (nom, rôle)');
try { $pdo->exec('TRUNCATE TABLE audit_logs'); $tronque = true; } catch (PDOException) { $tronque = false; }
check($tronque === true, 'LIMITE DOCUMENTÉE : TRUNCATE n\'est pas arrêté par les déclencheurs — à interdire par les droits MySQL en production (docs/deploiement/AUDIT_ETABLISSEMENT.md)');

// ══════════════════════════════════════════════════════════════════════
section('10. Vérification de cohérence d\'AuditService : connexion reçue, absence de doublon, secours masqué, logLogin, signatures');
// ══════════════════════════════════════════════════════════════════════
AuditService::oublierSchema(); AuditService::reinitialiserCompteur();
foreach (glob($dossierSecours . '/*') ?: [] as $f) { @unlink($f); }
TenantContext::set($ETAB1);
$nb = static fn(string $w = '1=1') => (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE $w")->fetchColumn();

// (a) logStrict écrit sur le PDO REÇU, dans SA transaction (pas sur la connexion par défaut)
$conn = $copie->connection();
$pdoB = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['db']};charset=utf8mb4", $conn['user'], $conn['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
check(spl_object_id($pdoB) !== spl_object_id($pdo), 'préparation : deuxième connexion PDO indépendante de la connexion par défaut');
$pdoB->beginTransaction();
$audit->logStrict($pdoB, $admin1, 'test.connexion_recue', 'verif_test', 'x', 1, null, ['a' => 1]);
$vuParB = (int)$pdoB->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'test.connexion_recue'")->fetchColumn();
$vuParA = $nb("action = 'test.connexion_recue'");
check($vuParB === 1 && $vuParA === 0, 'logStrict : la ligne est dans la transaction de la connexion REÇUE (visible de B, invisible de la connexion par défaut A tant que B n\'a pas validé)');
$pdoB->rollBack();
check($nb("action = 'test.connexion_recue'") === 0 && (int)$pdoB->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'test.connexion_recue'")->fetchColumn() === 0, 'rollback de la connexion reçue : la trace disparaît avec l\'opération (aucune écriture parallèle sur une autre connexion)');
$pdoB->beginTransaction();
$audit->logStrict($pdoB, $admin1, 'test.connexion_recue', 'verif_test', 'x', 1);
$pdoB->commit();
check($nb("action = 'test.connexion_recue'") === 1, 'commit de la connexion reçue : la trace devient visible');
$src = file_get_contents(ROOT_PATH . '/app/Services/AuditService.php');
preg_match('/public function logStrict\(.*?\r?\n    }\r?\n/s', $src, $mLs);
check(isset($mLs[0]) && !str_contains($mLs[0], 'Database::getInstance') && str_contains($mLs[0], '$pdo'), 'code de logStrict : aucune référence à Database::getInstance()');

// (b) aucune écriture en double
$d = $nb();
$audit->log($admin1, 'test.une_fois', 'verif_test', 'x', 1, null, ['a' => 1]);
check($nb() === $d + 1, 'log() : exactement UNE ligne par appel');
$audit->logCreate($admin1, 'verif_test', 'x', 2, ['a' => 1]);
check($nb() === $d + 2, 'logCreate() : exactement UNE ligne');
$audit->logUpdate($admin1, 'verif_test', 'x', 2, ['a' => 1], ['a' => 2]);
check($nb() === $d + 3, 'logUpdate() avec changement : exactement UNE ligne');
$audit->logUpdate($admin1, 'verif_test', 'x', 2, ['a' => 1], ['a' => 1]);
check($nb() === $d + 3, 'logUpdate() sans changement : aucune ligne');
$audit->logDelete($admin1, 'verif_test', 'x', 2, ['a' => 2]);
check($nb() === $d + 4, 'logDelete() : exactement UNE ligne');
$audit->logStrict($pdo, $admin1, 'test.strict_une_fois', 'verif_test', 'x', 3);
$audit->logUpdateStrict($pdo, $admin1, 'verif_test', 'x', 3, ['a' => 1], ['a' => 2]);
check($nb() === $d + 6, 'logStrict() et logUpdateStrict() : une ligne chacun');
check(AuditService::echecsDeLaRequete() === 0, 'aucune écriture au mieux n\'a échoué ni été redirigée vers le secours dans ce scénario nominal');
check(glob($dossierSecours . '/audit_fallback-*.jsonl') === [], 'scénario nominal : aucun fichier de secours créé (pas d\'écriture parallèle)');

// (c) le fichier de secours ne reçoit JAMAIS de données non masquées
$casser();
$audit->log($admin1, 'test.secours_masque', 'verif_test', 'eleve', 9,
    ['prenom' => 'Awa', 'telephone' => '+22790001111', 'mot_de_passe' => 'secret-A'],
    ['prenom' => 'Awa', 'email' => 'awa.diallo@exemple.test', 'adresse' => 'Quartier Plateau', 'date_naissance' => '2010-05-05', 'password' => 'secret-B', 'sous' => ['token' => 'jeton-C', 'nom_eleve' => 'Diallo'], 'eleve_id' => 9]);
$reparer();
$brutSecours = implode("\n", array_map(fn($f) => (string)file_get_contents($f), glob($dossierSecours . '/audit_fallback-*.jsonl') ?: []));
check($brutSecours !== '' && substr_count($brutSecours, "\n") === 0 || substr_count(trim($brutSecours), "\n") === 0, 'secours : exactement UNE ligne pour un échec (pas de doublon)');
$contenuSecours = json_decode(trim($brutSecours), true);
check(is_array($contenuSecours) && $contenuSecours['action'] === 'test.secours_masque' && (int)json_decode($contenuSecours['apres'], true)['eleve_id'] === 9, 'secours : l\'événement complet est conservé (action, identifiants)');
$interdits = ['Awa', 'Diallo', 'awa.diallo', 'exemple.test', '22790001111', 'Quartier Plateau', '2010-05-05', 'secret-A', 'secret-B', 'jeton-C'];
$fuites = array_filter($interdits, fn($m) => str_contains($brutSecours, $m));
check($fuites === [], 'secours : AUCUNE donnée personnelle ni secret en clair (masqués avant l\'écriture du fichier)', implode(', ', $fuites));
$rej = (new AuditService())->rejouerSecours($pdo);
$rejoue = $pdo->query("SELECT avant, apres FROM audit_logs WHERE action = 'test.secours_masque'")->fetch(PDO::FETCH_ASSOC);
check($rej['rejoues'] === 1 && $nb("action = 'test.secours_masque'") === 1 && !str_contains($rejoue['avant'] . $rejoue['apres'], 'Awa') && str_contains($rejoue['apres'], '***'), 'rejeu : une seule ligne réinscrite, toujours masquée');
foreach (glob($dossierSecours . '/*') ?: [] as $f) { @unlink($f); }

// (d) logLogin : jamais l'adresse e-mail en clair
$email = 'Awa.Diallo@Exemple.Test';
$d = $nb();
$audit->logLogin($admin1, $email, true, '198.51.100.7', $ETAB1);
$ok = $pdo->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$audit->logLogin(null, $email, false, '198.51.100.8');
$ko = $pdo->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
check($nb() === $d + 2 && $ok['action'] === 'login' && $ok['resultat'] === 'success' && (int)$ok['etablissement_id'] === $ETAB1 && $ok['ip'] === '198.51.100.7', 'logLogin (succès) : une ligne, établissement explicite fourni, adresse IP fournie');
check($ko['action'] === 'login_failed' && $ko['resultat'] === 'failure' && $ko['etablissement_id'] === null && $ko['user_id'] === null, 'logLogin (échec) : une ligne, sans établissement ni utilisateur inventés');
$brutLogin = strtolower(json_encode([$ok, $ko]));
check(!str_contains($brutLogin, 'awa') && !str_contains($brutLogin, 'diallo') && !str_contains($brutLogin, 'exemple.test') && !str_contains($brutLogin, '@'), 'logLogin : l\'adresse e-mail n\'est écrite NULLE PART en clair (ni dans apres, ni ailleurs)');
$empreinte = json_decode($ok['apres'], true)['email_empreinte'] ?? '';
check(preg_match('/^[0-9a-f]{16}$/', $empreinte) === 1 && $empreinte === json_decode($ko['apres'], true)['email_empreinte'], 'logLogin : seule une empreinte tronquée (16 caractères hexadécimaux) permet de corréler les tentatives');

// (e) signatures : toute lecture exige un établissement explicite en premier paramètre
$refl = new ReflectionClass(AuditService::class);
$lectures = ['rechercher', 'trouver', 'getEntityHistory', 'getUserHistory', 'modules', 'actions', 'acteurs'];
$tous = true;
foreach ($lectures as $m) { $p = $refl->getMethod($m)->getParameters()[0]; $tous = $tous && $p->getName() === 'etablissementId' && (string)$p->getType() === 'int' && !$p->isOptional(); }
check($tous && !$refl->hasMethod('search'), 'signatures : les 7 méthodes de lecture exigent `int $etablissementId` en premier paramètre ; l\'ancienne search() sans établissement n\'existe plus');
$pdoDefaut = $refl->getMethod('logStrict')->getParameters()[0];
check((string)$pdoDefaut->getType() === 'PDO' && !$pdoDefaut->isOptional(), 'signatures : logStrict exige un PDO en premier paramètre (jamais optionnel)');
$anciennes = ['log' => [3, 7], 'logCreate' => [5, 5], 'logUpdate' => [6, 6], 'logDelete' => [5, 5], 'logLogin' => [4, 5], 'logPermissionDenied' => [3, 3], 'diff' => [2, 2]];
$compat = true;
foreach ($anciennes as $m => [$min, $max]) { $rm = $refl->getMethod($m); $compat = $compat && $rm->getNumberOfRequiredParameters() === $min && $rm->getNumberOfParameters() === $max && $rm->isPublic(); }
check($compat, 'signatures : les méthodes publiques historiques (log, logCreate, logUpdate, logDelete, logLogin, logPermissionDenied, diff) gardent leur nombre de paramètres obligatoires (compatibles avec tous les appelants actifs)');
TenantContext::clear();

// ══════════════════════════════════════════════════════════════════════
section('11. Interface d\'audit minimale : périmètre statique, établissement du TenantContext, isolation symétrique et énumération d\'identifiants');
// ══════════════════════════════════════════════════════════════════════
AuditService::oublierSchema();
// ── Analyse statique du périmètre de l'interface ──
$ctrl = file_get_contents(ROOT_PATH . '/app/Controllers/AuditController.php');
preg_match_all('/\$this->audit->(\w+)\(/', $ctrl, $mAppels);
$appels = array_values(array_unique($mAppels[1]));
sort($appels);
check($appels === ['acteurs', 'actions', 'modules', 'rechercher', 'schemaPret', 'trouver'], 'contrôleur : n\'utilise QUE les méthodes de lecture tenant-scoped d\'AuditService (+ schemaPret)', implode(',', $appels));
check(substr_count($ctrl, 'TenantContext::current()') === 1 && !preg_match('/\$_(GET|POST|REQUEST|COOKIE)|request->(get|post|input|all)\(\s*[\'"](etab|etablissement|tenant)/i', $ctrl), 'contrôleur : l\'établissement vient d\'UN seul endroit, TenantContext::current() ; aucune lecture d\'un paramètre etab/etablissement/tenant');
check(!str_contains($ctrl, 'Database::') && !preg_match('/\bSELECT\b/i', $ctrl), 'contrôleur : aucun accès direct à la base');
preg_match_all('/function (\w+)\(/', $ctrl, $mFn);
check(array_values(array_diff($mFn[1], ['__construct', 'index', 'show', 'etablissementValide'])) === [], 'contrôleur : seules les actions index et show existent (aucune modification, suppression ni export)');
$routes = file(ROOT_PATH . '/config/routes.php', FILE_IGNORE_NEW_LINES);
$routesAudit = array_values(array_filter($routes, fn($l) => str_contains($l, '/parametres/audit') && !str_starts_with(trim($l), '//')));
check(count($routesAudit) === 2 && count(array_filter($routesAudit, fn($l) => str_contains($l, '$router->get('))) === 2, 'routes : exactement deux routes /parametres/audit, toutes en GET');
$vuesAudit = file_get_contents(ROOT_PATH . '/app/Views/audit/index.php') . file_get_contents(ROOT_PATH . '/app/Views/audit/show.php');
check(!preg_match('/method\s*=\s*["\']post/i', $vuesAudit) && !preg_match('/name\s*=\s*["\'](etablissement_id|etab|tenant)/i', $vuesAudit), 'vues : aucun formulaire POST ; aucun champ etablissement/tenant modifiable');
$platform = '';
$consultationSuperAdmin = ROOT_PATH . '/app/Controllers/PlatformEtablissementAuditController.php';   // seule lecture autorisée (cf. ci-dessous)
foreach (glob(ROOT_PATH . '/app/Controllers/Platform*.php') as $f) { if ($f !== $consultationSuperAdmin) { $platform .= file_get_contents($f); } }
foreach (glob(ROOT_PATH . '/core/Platform/*.php') as $f) { $platform .= file_get_contents($f); }
$platform = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $platform);   // le code seul : les commentaires peuvent parler de audit_logs pour affirmer l'indépendance
check(!preg_match('/(?<![a-z_])audit_logs|App\\\\Services\\\\AuditService/', $platform), 'périmètre : hors la consultation dédiée, aucun code du portail plateforme ne lit les journaux d\'établissement');
$codeConsult = (string)file_get_contents($consultationSuperAdmin);
$routesConsult = array_filter($routes, fn($x) => str_contains($x, 'PlatformEtablissementAuditController') && !str_starts_with(trim($x), '//'));
check(substr_count($codeConsult, "requirePlatformLevel('super_admin')") === 3 && count($routesConsult) === 3 && count(array_filter($routesConsult, fn($x) => str_contains($x, '$router->get('))) === 3, 'consultation Super-Admin : 3 routes, toutes en GET, chaque action réservée au niveau super_admin');
$modulesActifs = require ROOT_PATH . '/config/modules.php';
check($modulesActifs['portals']['enabled'] === false && $modulesActifs['scolarite']['enabled'] === false && $modulesActifs['rh']['enabled'] === false, 'aucun module désactivé n\'a été réactivé (portals, scolarite, rh toujours à false)');

// ── Isolation symétrique entre deux établissements ──
$dirB = $creerUser('DirecteurB', $ETAB2, 'directeur');
$pdo->prepare('INSERT IGNORE INTO user_etablissements (user_id, etablissement_id, is_primary) VALUES (?, ?, 1)')->execute([$dirB, $ETAB2]);
$roleDir = (int)$un("SELECT id FROM etab_roles WHERE code = 'directeur' AND etablissement_id IS NULL");
$pdo->prepare('INSERT IGNORE INTO user_roles_etab (user_id, etablissement_id, role_id) VALUES (?, ?, ?)')->execute([$dirB, $ETAB2, $roleDir]);
$ev = $pdo->prepare('INSERT INTO audit_logs (etablissement_id, user_id, action, module, entite, entite_id, apres, resultat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$ids = ['t1' => [], 't2' => [], 'nul' => []];
for ($i = 1; $i <= 5; $i++) { $ev->execute([$ETAB1, $admin1, "iso.t1.$i", 'iso_test', 'doc', 100 + $i, '{"marqueur": "SECRET-T1-' . $i . '"}', 'success']); $ids['t1'][] = (int)$pdo->lastInsertId(); }
for ($i = 1; $i <= 5; $i++) { $ev->execute([$ETAB2, $uE, "iso.t2.$i", 'iso_test', 'doc', 200 + $i, '{"marqueur": "SECRET-T2-' . $i . '"}', 'success']); $ids['t2'][] = (int)$pdo->lastInsertId(); }
$ev->execute([null, null, 'iso.sans_etablissement', 'iso_test', 'doc', 300, '{"marqueur": "SECRET-NUL"}', 'success']); $ids['nul'][] = (int)$pdo->lastInsertId();
$acteurs = ['admin ét.1' => ['admin', $admin1, 't1'], 'admin ét.2' => ['admin', $adminB, 't2'], 'directeur ét.2' => ['directeur', $dirB, 't2']];
foreach ($acteurs as $libelle => [$role, $uid, $propre]) {
    $autre = $propre === 't1' ? 't2' : 't1';
    $ok = 0; $refus = 0; $fuite = [];
    foreach ($ids[$propre] as $id) { $r = $http($role, $uid, "/parametres/audit/$id", ['REQUEST_AS_COMMIT' => '1']); if ($r['status'] === 200 && str_contains($r['corps'], 'SECRET-' . strtoupper($propre))) { $ok++; } }
    foreach (array_merge($ids[$autre], $ids['nul']) as $id) {
        $r = $http($role, $uid, "/parametres/audit/$id", ['REQUEST_AS_COMMIT' => '1']);
        if ($r['status'] === 404) { $refus++; }
        if (str_contains($r['corps'], 'SECRET-')) { $fuite[] = $id; }
    }
    check($ok === 5, "$libelle : accède à ses 5 événements par identifiant direct");
    check($refus === 6 && $fuite === [], "$libelle : identifiants des 5 événements de l'autre établissement + 1 sans établissement → 404, aucune donnée divulguée", 'refus=' . $refus . ' fuites=' . implode(',', $fuite));
}
// Listes : chacun ne voit que les siens
foreach ($acteurs as $libelle => [$role, $uid, $propre]) {
    $autre = $propre === 't1' ? 't2' : 't1';
    $r = $http($role, $uid, '/parametres/audit?module=iso_test', ['REQUEST_AS_COMMIT' => '1']);
    $t = $texte($r);
    check($r['status'] === 200 && str_contains($t, "iso.$propre.1") && !str_contains($t, "iso.$autre.") && !str_contains($t, 'iso.sans_etablissement') && !str_contains($r['corps'], 'SECRET-' . strtoupper($autre)), "$libelle : la liste ne contient que les événements de son établissement (ni l'autre, ni ceux sans établissement)");
}
// Un identifiant d'événement ne permet pas non plus de contourner le contrôle d'accès par rôle
$sec2 = $creerUser('SecretaireB', $ETAB2, 'secretaire');
$r = $http('secretaire', $sec2, '/parametres/audit/' . $ids['t2'][0], ['REQUEST_AS_COMMIT' => '1']);
check($r['status'] === 403 && !str_contains($r['corps'], 'SECRET-'), 'secrétaire de l\'établissement 2 : même sur un événement de SON établissement, accès refusé (403) sans audit.view');
TenantContext::clear();

// ── (les sections suivantes sont insérées ici) ───────────────────────────────────────────────────────────────────────────────

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
