<?php
chdir(dirname(__DIR__));

$pass = 0; $fail = 0; $warn = 0;
function t(string $id, bool $result, string $info = '', bool $isWarn = false): void {
    global $pass, $fail, $warn;
    if ($result)      { $status = 'PASS'; $pass++; }
    elseif ($isWarn)  { $status = 'WARN'; $warn++; }
    else              { $status = 'FAIL'; $fail++; }
    echo sprintf("%-52s %s %s\n", $id, $status, $info);
}

// ─── F01–F10 : Routes → Controllers exist ────────────────────────────────────
$routes = [
    'HomeController'          => 'app/Controllers/HomeController.php',
    'AuthController'          => 'app/Controllers/AuthController.php',
    'EleveController'         => 'app/Controllers/EleveController.php',
    'ProfesseurController'    => 'app/Controllers/ProfesseurController.php',
    'ClasseController'        => 'app/Controllers/ClasseController.php',
    'MatiereController'       => 'app/Controllers/MatiereController.php',
    'BulletinController'      => 'app/Controllers/BulletinController.php',
    // Absences / emplois du temps : domaines V2 du module Vie scolaire (unification V1/V2)
    'AbsenceController'       => 'app/Modules/VieScolaire/Absences/Controllers/AbsenceController.php',
    'EmploiDuTempsController' => 'app/Modules/VieScolaire/EmploisDuTemps/Controllers/TimetableController.php',
    'SalleController'         => 'app/Modules/VieScolaire/EmploisDuTemps/Controllers/SalleController.php',
    'PlageHoraireController'  => 'app/Modules/VieScolaire/EmploisDuTemps/Controllers/PlageHoraireController.php',
    'AnnonceController'       => 'app/Controllers/AnnonceController.php',
    'RapportController'       => 'app/Controllers/RapportController.php',
    'NotificationController'  => 'app/Controllers/NotificationController.php',
    'ParentController'        => 'app/Controllers/ParentController.php',
    'EspaceEleveController'   => 'app/Controllers/EspaceEleveController.php',
];
$i = 1;
foreach ($routes as $ctrl => $file) {
    t(sprintf('F%02d_CONTROLLER_EXISTS_%s', $i++, strtoupper(str_replace('Controller','', $ctrl))),
      file_exists($file), $file);
}

// ─── F21–F30 : Models exist ───────────────────────────────────────────────────
// Modèles V1 restés dans app/Models ; absences et emplois du temps ont migré vers le module Vie scolaire.
$vs = 'app/Modules/VieScolaire';
$models = [
    'UserModel'          => 'app/Models/UserModel.php',
    'EleveModel'         => 'app/Models/EleveModel.php',
    'ProfesseurModel'    => 'app/Models/ProfesseurModel.php',
    'ClasseModel'        => 'app/Models/ClasseModel.php',
    'MatiereModel'       => 'app/Models/MatiereModel.php',
    'NoteModel'          => 'app/Models/NoteModel.php',
    'AbsenceModel'       => "{$vs}/Absences/Models/AbsenceModel.php",
    'JustificationModel' => "{$vs}/Absences/Models/JustificationAbsenceModel.php",
    'AnnonceModel'       => 'app/Models/AnnonceModel.php',
    'EmploiDuTempsModel' => "{$vs}/EmploisDuTemps/Models/EmploiDuTempsModel.php",
    'SalleModel'         => "{$vs}/EmploisDuTemps/Models/SalleModel.php",
    'CreneauModel'       => "{$vs}/EmploisDuTemps/Models/CreneauModel.php",
    'NotificationModel'  => 'app/Models/NotificationModel.php',
    'NotificationLogModel'        => 'app/Models/NotificationLogModel.php',
    'NotificationPreferenceModel' => 'app/Models/NotificationPreferenceModel.php',
];
$i = 21;
foreach ($models as $m => $path) {
    t(sprintf('F%02d_MODEL_EXISTS_%s', $i++, strtoupper($m)), file_exists($path), $path);
}

// ─── F38–F50 : Views exist for key render() calls ─────────────────────────────
$views = [
    'home/dashboard'          => 'app/Views/home/dashboard.php',
    'auth/login'              => 'app/Views/auth/login.php',
    'eleves/index'            => 'app/Views/eleves/index.php',
    'professeurs/index'       => 'app/Views/professeurs/index.php',
    'absences/index'          => 'app/Modules/VieScolaire/Views/absences/index.php',
    'absences/pointage'       => 'app/Modules/VieScolaire/Views/absences/pointage.php',
    'annonces/index'          => 'app/Views/annonces/index.php',
    'annonces/form'           => 'app/Views/annonces/form.php',
    'emplois_du_temps/index'  => 'app/Modules/VieScolaire/Views/emplois_du_temps/index.php',
    'emplois_du_temps/form'   => 'app/Modules/VieScolaire/Views/emplois_du_temps/create.php',
    'layouts/main'            => 'app/Views/layouts/main.php',
    'layouts/auth'            => 'app/Views/layouts/auth.php',
    'errors/403'              => 'app/Views/errors/403.php',
    'errors/404'              => 'app/Views/errors/404.php',
    'rapports/index'          => 'app/Views/rapports/index.php',
    'bulletins/index'         => 'app/Views/bulletins/index.php',
];
$i = 38;
foreach ($views as $name => $file) {
    t(sprintf('F%02d_VIEW_%s', $i++, strtoupper(str_replace(['/','-'], '_', $name))),
      file_exists($file), $file);
}

// ─── F60 : Core classes exist ─────────────────────────────────────────────────
$cores = ['Controller','Model','Database','Router','Request','Session','View','Logger'];
$i = 60;
foreach ($cores as $c) {
    t(sprintf('F%02d_CORE_%s', $i++, strtoupper($c)), file_exists("core/{$c}.php"));
}

// ─── F70 : Model method existence (via reflection) ────────────────────────────
define('ROOT_PATH', __DIR__ . '/..');
// Load classes manually for reflection
spl_autoload_register(function(string $class): void {
    $map = ['Core\\' => ROOT_PATH . '/core/', 'App\\Models\\' => ROOT_PATH . '/app/Models/',
            'App\\Services\\' => ROOT_PATH . '/app/Services/',
            'App\\Modules\\' => ROOT_PATH . '/app/Modules/'];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $f = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($f)) require_once $f;
        }
    }
});

// Patch Database to avoid real connection in reflection context
// We only do reflection, not actual instantiation
$methodChecks = [
    ['App\\Models\\NoteModel', ['recalculerClasse','recalculerDepuisControle','calculerMoyenneMatiere','getBulletinData','upsert']],
    ['App\\Models\\UserModel', ['findByEmail','getPermissions','findAllWithRoles','findAllWithRole']],
    ['App\\Modules\\VieScolaire\\Absences\\Services\\AbsenceService', ['paginate','enregistrer','soumettrJustification','validerJustification','statistiquesClasse']],
    ['App\\Modules\\VieScolaire\\Absences\\Repositories\\AbsenceRepository', ['findById','findRosterForPointage','countByEleveAndDateRange']],
    ['App\\Models\\AnnonceModel', ['findPubliees','findById']],
    ['App\\Modules\\VieScolaire\\EmploisDuTemps\\Repositories\\TimetableRepository', ['findEdtById','findCreneauxByEdt','checkConflitEnseignant','checkConflitSalle','checkConflitClasse']],
];

$i = 70;
foreach ($methodChecks as [$class, $methods]) {
    try {
        $ref = new ReflectionClass($class);
        foreach ($methods as $method) {
            $exists = $ref->hasMethod($method);
            t(sprintf('F%02d_%s::%s', $i++, class_basename($class), $method), $exists);
        }
    } catch (Throwable $e) {
        t(sprintf('F%02d_%s_REFLECTION', $i++, class_basename($class)), false, $e->getMessage());
    }
}

function class_basename(string $class): string {
    return substr($class, strrpos($class, '\\') + 1);
}

// ─── F90 : Integration — DB model queries ─────────────────────────────────────
// We need a working DB connection — patch Database singleton via config
$_SERVER['DOCUMENT_ROOT'] = ROOT_PATH . '/public';

// Test actual model queries
try {
    // Manual DB setup
    $pdo = new PDO('mysql:host=localhost;dbname=ecole_app;charset=utf8mb4', 'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);

    // F90: eleves table has data with correct structure
    $eleve = $pdo->query('SELECT * FROM eleves LIMIT 1')->fetch();
    t('F90_ELEVE_HAS_REQUIRED_FIELDS',
        isset($eleve->id, $eleve->nom, $eleve->prenom, $eleve->matricule, $eleve->sexe));

    // F91: classes have niveau
    $classe = $pdo->query('SELECT * FROM classes LIMIT 1')->fetch();
    t('F91_CLASSE_HAS_NIVEAU', isset($classe->id, $classe->nom, $classe->niveau));

    // F92: matieres have coefficient
    $mat = $pdo->query('SELECT * FROM matieres LIMIT 1')->fetch();
    t('F92_MATIERE_HAS_COEFFICIENT', isset($mat->id, $mat->nom, $mat->coefficient));

    // F93: periodes exist and have correct fields
    $per = $pdo->query('SELECT COUNT(*) FROM periodes')->fetchColumn();
    t('F93_PERIODES_EXIST', (int)$per > 0, "count: $per");

    // F94: controles table has periode_id (correct schema)
    $cols = $pdo->query('DESCRIBE controles')->fetchAll(PDO::FETCH_COLUMN);
    t('F94_CONTROLES_HAS_PERIODE_ID', in_array('periode_id', $cols));
    t('F95_CONTROLES_HAS_MATIERE_ID', in_array('matiere_id', $cols));
    t('F96_CONTROLES_NO_TRIMESTRE_COL', !in_array('trimestre', $cols));

    // F97: notes table has controle_id (not old schema)
    $noteCols = $pdo->query('DESCRIBE notes')->fetchAll(PDO::FETCH_COLUMN);
    t('F97_NOTES_HAS_CONTROLE_ID', in_array('controle_id', $noteCols));
    t('F98_NOTES_NO_MATIERE_ID',   !in_array('matiere_id', $noteCols));

    // F99/F100 (V2) : vs_absences porte un statut de justification (enum) et la classe
    $absCols = $pdo->query('DESCRIBE vs_absences')->fetchAll(PDO::FETCH_COLUMN);
    t('F99_ABSENCES_HAS_STATUT',        in_array('statut', $absCols));
    t('F100_ABSENCES_HAS_CLASSE_ID',    in_array('classe_id', $absCols));

    // F101–F103 (V2) : les créneaux d'emploi du temps référencent une plage horaire et un jour
    $edtCols = $pdo->query('DESCRIBE vs_edt_creneaux')->fetchAll(PDO::FETCH_COLUMN);
    t('F101_EDT_HAS_PLAGE_ID',      in_array('plage_id',       $edtCols));
    t('F102_EDT_HAS_JOUR',          in_array('jour',           $edtCols));
    t('F103_EDT_HAS_ANNEE_SCOLAIRE',in_array('annee_scolaire', $edtCols));

    // F104 (V2) : vs_justifications_absences référence l'absence justifiée
    $justCols = $pdo->query('DESCRIBE vs_justifications_absences')->fetchAll(PDO::FETCH_COLUMN);
    t('F104_JUSTIF_HAS_ABSENCE_ID', in_array('absence_id', $justCols));

    // F105: notification_logs uses trigger_type (not trigger)
    $logCols = $pdo->query('DESCRIBE notification_logs')->fetchAll(PDO::FETCH_COLUMN);
    t('F105_NOTIF_LOG_TRIGGER_TYPE_COL',  in_array('trigger_type', $logCols));
    t('F106_NOTIF_LOG_NO_TRIGGER_COL',    !in_array('trigger', $logCols));

    // F107/F108 (V2) : finance_factures / finance_lignes_facture ont les FK attendues
    // — remplace les anciens tests sur frais_eleves (V1, décommissionnée)
    $factureCols = $pdo->query('DESCRIBE finance_factures')->fetchAll(PDO::FETCH_COLUMN);
    t('F107_FINANCE_FACTURES_HAS_ELEVE_ID', in_array('eleve_id', $factureCols));
    $ligneCols = $pdo->query('DESCRIBE finance_lignes_facture')->fetchAll(PDO::FETCH_COLUMN);
    t('F108_FINANCE_LIGNES_HAS_FRAIS_TYPE', in_array('frais_type_id', $ligneCols));

    // F109: password_resets table exists and has used column
    $prCols = $pdo->query('DESCRIBE password_resets')->fetchAll(PDO::FETCH_COLUMN);
    t('F109_PWD_RESETS_HAS_USED',    in_array('used', $prCols));
    t('F110_PWD_RESETS_HAS_EXPIRES', in_array('expires_at', $prCols));

} catch (Throwable $e) {
    t('F90_DB_INTEGRATION', false, $e->getMessage());
}

// ─── F111–F115 : Config files valid ───────────────────────────────────────────
$configs = ['config/app.php','config/database.php','config/routes.php','config/permissions.php'];
$i = 111;
foreach ($configs as $cfg) {
    $valid = file_exists($cfg);
    if ($valid) {
        // Test that the file returns valid data
        try {
            if ($cfg !== 'config/routes.php') {
                $data = require $cfg;
                $valid = is_array($data);
            }
        } catch (Throwable) { $valid = false; }
    }
    t(sprintf('F%03d_CONFIG_%s', $i++, strtoupper(str_replace(['config/','.php','/'], ['','','_'], $cfg))), $valid);
}

// F116: .env file absence doesn't crash app (graceful fallback)
$appCfg = require 'config/app.php';
t('F116_APP_NAME_SET',     !empty($appCfg['name']));
t('F117_APP_DEBUG_BOOL',   is_bool($appCfg['debug']));
t('F118_APP_TIMEZONE_SET', !empty($appCfg['timezone']));
t('F119_APP_KEY_NOT_DEFAULT', $appCfg['key'] !== 'default_key');

// ─── SUMMARY ─────────────────────────────────────────────────────────────────
echo str_repeat('─', 60) . "\n";
echo "TOTAL: " . ($pass + $fail + $warn) . " | PASS: $pass | WARN: $warn | FAIL: $fail\n";
