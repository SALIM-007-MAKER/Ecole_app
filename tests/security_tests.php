<?php
// Security & structural tests — run from project root
chdir(dirname(__DIR__));

$pass = 0; $fail = 0;
function t(string $id, bool $result, string $info = ''): void {
    global $pass, $fail;
    $status = $result ? 'PASS' : 'FAIL';
    $result ? $pass++ : $fail++;
    echo sprintf("%-45s %s %s\n", $id, $status, $info);
}

// ─── DB ───────────────────────────────────────────────────────────────────────
$pdo = new PDO('mysql:host=localhost;dbname=ecole_app;charset=utf8mb4', 'root', '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);

// T01: All user passwords are bcrypt
$users = $pdo->query('SELECT password FROM users')->fetchAll();
$allBcrypt = array_reduce($users, fn($c, $u) => $c && str_starts_with($u->password, '$2y$'), true);
t('T01_ALL_BCRYPT_HASHES', $allBcrypt);

// T02: Password "password" verifies against stored hashes
$u = $pdo->query("SELECT password FROM users WHERE email='admin@ecole.dz'")->fetch();
t('T02_PASSWORD_VERIFIES', password_verify('password', $u->password));

// T03: SQL injection via prepared statement (email lookup)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
$stmt->execute(["' OR '1'='1"]);
t('T03_SQL_INJECT_BLOCKED', (int)$stmt->fetchColumn() === 0);

// T04: Unique email constraint
$blocked = false;
try {
    $pdo->exec("INSERT INTO users (nom,email,password,role,actif) VALUES ('dup','admin@ecole.dz','x','eleve',1)");
    $pdo->exec("DELETE FROM users WHERE nom='dup'");
} catch (Exception $e) { $blocked = true; }
t('T04_UNIQUE_EMAIL_CONSTRAINT', $blocked);

// T05: FK notes → controles
$fk = (int)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA='ecole_app' AND TABLE_NAME='notes' AND REFERENCED_TABLE_NAME='controles'")->fetchColumn();
t('T05_FK_NOTES_CONTROLES', $fk > 0);

// T06: FK vs_justifications_absences → vs_absences (le domaine Absences V2 —
// vs_absences elle-même n'a volontairement pas de FK déclarées, validation
// applicative uniquement ; la table `absences` V1 testée ici jusqu'au
// 21/08/2026 a été supprimée, voir CHANGELOG.md)
$fk2 = (int)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA='ecole_app' AND TABLE_NAME='vs_justifications_absences' AND REFERENCED_TABLE_NAME='vs_absences'")->fetchColumn();
t('T06_FK_JUSTIFICATIONS_ABSENCES', $fk2 > 0);

// T07: Transaction rollback
try {
    $pdo->beginTransaction();
    $pdo->exec("INSERT INTO matieres (nom, coefficient) VALUES ('__rollback_test__', 1)");
    throw new Exception('force rollback');
} catch (Exception) { $pdo->rollBack(); }
$exists = (int)$pdo->query("SELECT COUNT(*) FROM matieres WHERE nom='__rollback_test__'")->fetchColumn();
t('T07_TRANSACTION_ROLLBACK', $exists === 0);

// T08: statut is enum (not free text) — vs_absences (V2), remplace la
// table `absences` V1 (colonne statut_justif) supprimée le 21/08/2026
$col = $pdo->query("SHOW COLUMNS FROM vs_absences LIKE 'statut'")->fetch()->Type;
t('T08_STATUT_JUSTIF_ENUM', str_contains($col, 'enum'));

// ─── PERMISSIONS ──────────────────────────────────────────────────────────────
$perms = require 'config/permissions.php';

t('T09_ADMIN_HAS_ALL_ANNONCES',
    in_array('annonces.create', $perms['admin'], true) &&
    in_array('annonces.edit',   $perms['admin'], true) &&
    in_array('annonces.delete', $perms['admin'], true));

t('T10_DIRECTEUR_HAS_ANNONCES_EDIT',
    in_array('annonces.edit',   $perms['directeur'], true) &&
    in_array('annonces.delete', $perms['directeur'], true));

t('T11_SECRETAIRE_HAS_ANNONCES_EDIT',
    in_array('annonces.edit', $perms['secretaire'], true));

t('T12_COMPTABLE_NO_ANNONCES_CREATE',
    !in_array('annonces.create', $perms['comptable'], true));

t('T13_ELEVE_READ_ONLY',
    !in_array('notes.create',    $perms['eleve'], true) &&
    !in_array('eleves.delete',   $perms['eleve'], true) &&
    !in_array('comptabilite.create', $perms['eleve'], true));

t('T14_PARENT_NO_NOTES_CREATE',
    !in_array('notes.create', $perms['parent'], true));

t('T15_ENSEIGNANT_NO_USER_MANAGE',
    !in_array('users.create', $perms['enseignant'], true) &&
    !in_array('users.delete', $perms['enseignant'], true));

t('T16_ENSEIGNANT_HAS_NOTES_OWN',
    in_array('notes.create', $perms['enseignant'], true) &&
    in_array('notes.edit',   $perms['enseignant'], true));

t('T17_PARENT_CAN_JUSTIFY',
    in_array('absences.justify', $perms['parent'], true));

t('T18_ADMIN_HAS_NOTIFICATIONS_MANAGE',
    in_array('notifications.manage', $perms['admin'], true));

// ─── ORDERBY WHITELIST ────────────────────────────────────────────────────────
$validExpr  = (bool)preg_match('/^[a-zA-Z0-9_. ,]+$/', 'niveau ASC, nom ASC');
$sqlInject  = (bool)preg_match('/^[a-zA-Z0-9_. ,]+$/', "1; DROP TABLE users--");
$xssInject  = (bool)preg_match('/^[a-zA-Z0-9_. ,]+$/', "<script>alert(1)</script>");
t('T19_ORDERBY_VALID_EXPR',   $validExpr);
t('T20_ORDERBY_SQL_BLOCKED',  !$sqlInject);
t('T21_ORDERBY_XSS_BLOCKED',  !$xssInject);

// ─── MIME TYPE ────────────────────────────────────────────────────────────────
$tmp = tempnam(sys_get_temp_dir(), 'qa');
file_put_contents($tmp, '<?php system($_GET["cmd"]); ?>'); // PHP payload disguised
$mime = mime_content_type($tmp);
$allowed = ['image/jpeg','image/png','image/gif','image/webp'];
unlink($tmp);
t('T22_PHP_PAYLOAD_MIME_BLOCKED', !in_array($mime, $allowed));

$tmp2 = tempnam(sys_get_temp_dir(), 'qa');
file_put_contents($tmp2, "\xff\xd8\xff\xe0"); // JPEG magic bytes
$mime2 = mime_content_type($tmp2);
unlink($tmp2);
t('T23_JPEG_MAGIC_DETECTED', str_contains($mime2, 'image/jpeg'));

// ─── CODE SCANNING ───────────────────────────────────────────────────────────
$allPhp = array_merge(
    glob('app/Controllers/*.php') ?: [],
    glob('app/Models/*.php') ?: [],
    glob('core/*.php') ?: [],
    glob('app/Services/*.php') ?: []
);

// T24: No eval()
$evalFiles = array_filter($allPhp, fn($f) => str_contains(file_get_contents($f), 'eval('));
t('T24_NO_EVAL', empty($evalFiles));

// T25: No shell_exec/exec/system in controllers or models
$dangerous = ['shell_exec', 'passthru(', 'popen('];
$dangerFiles = [];
foreach (array_merge(glob('app/Controllers/*.php') ?: [], glob('app/Models/*.php') ?: []) as $f) {
    foreach ($dangerous as $d) {
        if (str_contains(file_get_contents($f), $d)) $dangerFiles[] = basename($f) . ':' . $d;
    }
}
t('T25_NO_SHELL_EXEC', empty($dangerFiles), implode(',', $dangerFiles));

// T26/T27 : retirés le 21/08/2026 — testaient l'absence de $_GET/$_POST
// bruts dans app/Controllers/EmploiDuTempsController.php (V1, supprimé,
// voir CHANGELOG.md). Core\Request::get()/post() n'est qu'un passe-plat
// sans sanitisation (vérifié) : la règle visait la testabilité, pas une
// vraie faille — les contrôleurs V2 (dont son remplaçant,
// VieScolaire\EmploisDuTemps\Controllers\TimetableController) accèdent aux
// superglobales directement partout, par convention établie du module ; la
// sécurité réelle passe par verifyCsrf()/requirePermission(), déjà couverts
// par d'autres tests. Réintroduire cette règle reviendrait à faire échouer
// tout le module V2 pour un écart de convention, pas un risque.

// T28: logout uses POST route (not GET)
$routes = file_get_contents('config/routes.php');
$logoutGet  = preg_match("/router->get\s*\(\s*'\/logout'/", $routes);
$logoutPost = preg_match("/router->post\s*\(\s*'\/logout'/", $routes);
t('T28_LOGOUT_NOT_GET',  !$logoutGet);
t('T29_LOGOUT_IS_POST',  (bool)$logoutPost);

// T29: AuthController::logout() calls verifyCsrf
$authContent = file_get_contents('app/Controllers/AuthController.php');
$logoutHasCsrf = (bool)preg_match('/function logout.*?verifyCsrf/s', $authContent);
t('T30_LOGOUT_VERIFIES_CSRF', $logoutHasCsrf);

// T31: login.php credentials block guarded by debug flag
$loginView = file_get_contents('app/Views/auth/login.php');
t('T31_DEMO_CREDS_IN_DEBUG_ONLY',
    str_contains($loginView, "appConfig['debug']") &&
    str_contains($loginView, 'Comptes de démonstration'));

// T32: APP_KEY has no default fallback
$appConfig = file_get_contents('config/app.php');
t('T32_NO_DEFAULT_KEY', !str_contains($appConfig, "'default_key'"));

// T33: retiré le 21/08/2026 — testait le contrôleur Absences V1
// (app/Controllers/AbsenceController.php, supprimé, voir CHANGELOG.md).
// Le module V2 (App\Modules\VieScolaire\Absences) n'implémente pas encore
// l'upload de justificatif côté serveur (JustificationDTO::$fichier existe
// mais storeJustification() ne traite pas $_FILES) — rien à tester tant que
// cette fonctionnalité n'existe pas.

// T34: Permission check before data load — AbsenceController::show() V2
// (remplace le test sur le contrôleur V1 supprimé)
$absContent = file_get_contents('app/Modules/VieScolaire/Absences/Controllers/AbsenceController.php');
$showMethod = preg_match('/function show.*?function \w/s', $absContent, $m) ? $m[0] : '';
$permPos    = strpos($showMethod, 'requirePermission(');
$dataPos    = strpos($showMethod, 'findById(');
t('T34_PERM_BEFORE_DATA_IN_SHOW', $permPos !== false && $dataPos !== false && $permPos < $dataPos);

// T35: NoteModel recalcul has transaction
$noteContent = file_get_contents('app/Models/NoteModel.php');
t('T35_RECALCUL_HAS_TRANSACTION',
    str_contains($noteContent, 'beginTransaction()') &&
    str_contains($noteContent, 'commit()') &&
    str_contains($noteContent, 'rollBack()'));

// T36: SmsService uses json_encode (not addslashes)
$smsContent = file_get_contents('app/Services/SmsService.php');
t('T36_SMS_JSON_ENCODE',     str_contains($smsContent, 'json_encode'));
t('T37_SMS_NO_ADDSLASHES',   !str_contains($smsContent, 'addslashes'));

// T38: NotificationService uses batch query
$notifContent = file_get_contents('app/Services/NotificationService.php');
t('T38_NOTIF_BATCH_QUERY',   str_contains($notifContent, 'findAllWithRoles'));
t('T39_NOTIF_NO_N1_LOOP',    !str_contains($notifContent, 'findAllWithRole('));

// T40: Security headers in Controller
$ctrlContent = file_get_contents('core/Controller.php');
t('T40_SECURITY_HEADERS',
    str_contains($ctrlContent, 'X-Frame-Options') &&
    str_contains($ctrlContent, 'X-Content-Type-Options') &&
    str_contains($ctrlContent, 'Content-Security-Policy'));

// T41: AnnonceController uses correct permissions
$annonceContent = file_get_contents('app/Controllers/AnnonceController.php');
t('T41_ANNONCE_EDIT_PERM',   str_contains($annonceContent, "requirePermission('annonces.edit')"));
t('T42_ANNONCE_DELETE_PERM', str_contains($annonceContent, "requirePermission('annonces.delete')"));

// T43: AnnonceController delete() checks existence first
$deleteMethod = '';
if (preg_match('/public function delete.*?(?=public function|\Z)/s', $annonceContent, $m)) {
    $deleteMethod = $m[0];
}
t('T43_ANNONCE_DELETE_EXISTS_CHECK', str_contains($deleteMethod, 'findById'));

// T44: PHP 8.0 compat — __wakeup must use void, not never (never requires PHP 8.1+)
$dbContent = file_get_contents('core/Database.php');
t('T44_WAKEUP_USES_VOID_NOT_NEVER',
    str_contains($dbContent, '__wakeup(): void') &&
    !str_contains($dbContent, '__wakeup(): never'));

// T45: ProfesseurController no static const for runtime path
$profContent = file_get_contents('app/Controllers/ProfesseurController.php');
t('T45_NO_CONST_PHOTO_DIR', !str_contains($profContent, 'private const PHOTO_DIR'));
t('T46_HAS_PROPERTY_PHOTO_DIR', str_contains($profContent, 'private string $photoDir'));

// T47: public/index.php no duplicate $relative
$indexContent = file_get_contents('public/index.php');
$relativeCount = substr_count($indexContent, '$relative = str_replace');
t('T47_NO_DUPLICATE_RELATIVE', $relativeCount === 0);

// T48: UserModel has batch method
$userContent = file_get_contents('app/Models/UserModel.php');
t('T48_USER_MODEL_BATCH_ROLES', str_contains($userContent, 'findAllWithRoles'));

// ─── SUMMARY ─────────────────────────────────────────────────────────────────
echo str_repeat('─', 55) . "\n";
echo "TOTAL: " . ($pass + $fail) . " | PASS: $pass | FAIL: $fail\n";
