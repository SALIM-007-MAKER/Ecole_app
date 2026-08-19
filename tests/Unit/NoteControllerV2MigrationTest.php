<?php
/**
 * Tests — migration de la saisie de notes V1 (App\Controllers\NoteController)
 * vers le pipeline Académique V2 (Academique\Controllers\NoteController +
 * NoteService).
 *
 * Combine :
 *  - vérification statique (RBAC .admin manquant corrigé, routes, contrôleur
 *    V1 non supprimé) ;
 *  - cycle de vie fonctionnel complet contre la base réelle : création
 *    d'évaluation -> ouverture de la saisie -> saisie de notes (notes_v2) ->
 *    publication -> vérification de la notification -> verrouillage
 *    évaluation + notes -> import CSV sur une évaluation séparée ->
 *    validation saisie -> bulletin (BulletinEngineFactory reprend bien les
 *    notes fraîchement saisies) ;
 *  - nettoyage complet des données de test créées (aucune pollution
 *    persistante de la base).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/NoteControllerV2MigrationTest.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ROOT_PATH', dirname(__DIR__, 2));
define('BASE_URL', '/ecole_app');

$envFile = ROOT_PATH . '/.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}

spl_autoload_register(function (string $class): void {
    $namespaces = [
        'Core\\'             => ROOT_PATH . '/core/',
        'App\\Controllers\\' => ROOT_PATH . '/app/Controllers/',
        'App\\Models\\'      => ROOT_PATH . '/app/Models/',
        'App\\Services\\'    => ROOT_PATH . '/app/Services/',
        'App\\Modules\\'     => ROOT_PATH . '/app/Modules/',
        'App\\Shared\\'      => ROOT_PATH . '/app/Shared/',
        'App\\Events\\'      => ROOT_PATH . '/app/Events/',
        'App\\Listeners\\'   => ROOT_PATH . '/app/Listeners/',
    ];
    foreach ($namespaces as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $dir . $rel . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
});

use App\Modules\Academique\DTO\EvaluationDTO;
use App\Modules\Academique\DTO\NoteBatchDTO;
use App\Modules\Academique\Services\EvaluationService;
use App\Modules\Academique\Services\NoteService;
use App\Modules\Academique\Services\BulletinEngineFactory;
use Core\Database;
use Core\EventDispatcher;

// Même enregistrement des listeners que Core\Application::run() — sans cette
// étape, EventDispatcher::dispatch() ne trouve aucun listener et le test ne
// vérifierait rien de réel sur la notification.
$eventsConfig = require ROOT_PATH . '/config/events.php';
foreach ($eventsConfig as $eventClass => $listeners) {
    foreach ($listeners as $listener) {
        EventDispatcher::listen($eventClass, $listener);
    }
}

$passed = 0;
$failed = 0;

function assert_true(bool $val, string $label): void
{
    global $passed, $failed;
    if ($val) { echo "\033[32m  ✓ $label\033[0m\n"; $passed++; }
    else      { echo "\033[31m  ✗ $label\033[0m\n"; $failed++; }
}

function section(string $title): void { echo "\n\033[33m── $title\033[0m\n"; }

// ─── 1. Vérifications statiques ───────────────────────────────────────────────

section('Statique — RBAC, routes, suppression finale de V1');

$perms = require ROOT_PATH . '/config/permissions.php';
foreach (['admin', 'directeur'] as $role) {
    assert_true(in_array('academique.notes.admin', $perms[$role] ?? [], true), "$role possède academique.notes.admin (verrouillage) — corrigé, absent avant ce chantier");
    assert_true(in_array('academique.evaluations.admin', $perms[$role] ?? [], true), "$role possède academique.evaluations.admin");
}
assert_true(!in_array('academique.notes.admin', $perms['enseignant'] ?? [], true), "enseignant N'A PAS academique.notes.admin (verrouillage réservé admin/directeur, comme en V1)");

// Mise à jour post-nettoyage final : App\Controllers\NoteController (V1) et
// ses vues/routes ont été supprimés (plus aucune référence entrante restante,
// parité fonctionnelle V2 confirmée) — ces assertions vérifiaient auparavant
// leur PRÉSENCE (phase intermédiaire) ; elles vérifient maintenant leur
// absence effective, comme garde-fou contre toute réintroduction accidentelle.
assert_true(!file_exists(ROOT_PATH . '/app/Controllers/NoteController.php'), 'App\\Controllers\\NoteController (V1) définitivement supprimé');
assert_true(!is_dir(ROOT_PATH . '/app/Views/notes'), 'Vues V1 (app/Views/notes/) définitivement supprimées');
assert_true(!file_exists(ROOT_PATH . '/app/Models/ControleModel.php'), 'App\\Models\\ControleModel (V1, plus aucun appelant) supprimé');
assert_true(!file_exists(ROOT_PATH . '/app/Events/NoteAjoutee.php'), 'Event NoteAjoutee (V1, plus aucun émetteur) supprimé');
assert_true(!file_exists(ROOT_PATH . '/app/Events/ControleUpdated.php'), 'Event ControleUpdated (V1, plus aucun émetteur) supprimé');

$routesSrc = file_get_contents(ROOT_PATH . '/config/routes.php');
foreach (["'/notes/saisie/{id}'", "'/notes/controles'", "NoteController@"] as $needle) {
    assert_true(!str_contains($routesSrc, $needle), "Route V1 bien supprimée : $needle");
}

// NoteModel/PeriodeModel restent volontairement — seul accès programmatique
// à l'historique des tables `notes`/`periodes` (classées "conserver
// temporairement" dans le rapport de nettoyage, pas du code mort : encore
// testées indépendamment, ex. TenantIsolationTest pour NoteModel).
assert_true(file_exists(ROOT_PATH . '/app/Models/NoteModel.php'), 'App\\Models\\NoteModel conservé (accès historique, encore testé)');
assert_true(file_exists(ROOT_PATH . '/app/Models/PeriodeModel.php'), 'App\\Models\\PeriodeModel conservé (accès historique)');

$homeDashboardSrc = file_get_contents(ROOT_PATH . '/app/Views/home/dashboard.php');
assert_true(!str_contains($homeDashboardSrc, "/notes/controles/create"), "home/dashboard.php ne pointe plus vers /notes/controles/create");
assert_true(substr_count($homeDashboardSrc, '/v2/academique/evaluations') >= 4, "home/dashboard.php pointe vers /v2/academique/evaluations (liens migrés)");

$eventsSrc = file_get_contents(ROOT_PATH . '/config/events.php');
assert_true(str_contains($eventsSrc, 'NoteNotificationHandler'), 'NoteNotificationHandler enregistré dans config/events.php');

// ─── 2. Cycle de vie fonctionnel complet ──────────────────────────────────────

section('Cycle de vie — création -> saisie -> publication -> verrouillage');

$pdo = Database::getInstance()->getConnection();
$ADMIN_USER_ID = 1;
$CLASSE_ID     = 131;
$PERIODE_ID    = 90;
$MATIERE_ID    = 1;
$TYPE_EVAL_ID  = 1;

// Fixture : un élève de test avec parent_id renseigné (les élèves seedés
// n'en ont pas), pour pouvoir vérifier la notification de bout en bout.
$testEleveId = null;
$testEvalIds = [];
$notifIdsBefore = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM notifications")->fetchColumn();

try {
    $pdo->prepare(
        "INSERT INTO eleves (matricule, nom, prenom, date_naissance, sexe, classe_id, parent_id, actif, etablissement_id)
         VALUES ('TEST-MIGR-NOTE', 'TestMigration', 'Eleve', '2010-01-01', 'M', :cid, :pid, 1, 1)"
    )->execute([':cid' => $CLASSE_ID, ':pid' => $ADMIN_USER_ID]);
    $testEleveId = (int)$pdo->lastInsertId();
    assert_true($testEleveId > 0, "Élève de test créé (id=$testEleveId, parent_id=$ADMIN_USER_ID)");

    // Il faut une inscription active pour que EvaluationService::creer() accepte
    // la classe — mais la classe 131 a déjà des inscrits actifs (vérifié : 5),
    // donc ce n'est pas strictement nécessaire pour cet élève de test. On
    // l'ajoute quand même par cohérence (InscriptionRepository::
    // findActiveByEleve() est vérifié par NoteService::saisirBatch()).
    $anneeScolaire = $pdo->query("SELECT annee_scolaire FROM periodes_scolaires WHERE id = $PERIODE_ID")->fetchColumn();
    try {
        $pdo->prepare(
            "INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire, statut)
             VALUES (:eid, :cid, :annee, 'validee')"
        )->execute([':eid' => $testEleveId, ':cid' => $CLASSE_ID, ':annee' => $anneeScolaire]);
    } catch (\Throwable $e) {
        echo "\033[33m  ⚠ Inscription de test non créée (" . $e->getMessage() . ") — on continue\033[0m\n";
    }

    $evalService = new EvaluationService();
    $noteService = new NoteService();

    // ── Création ──
    $dto = new EvaluationDTO(
        periodeScolaireId: $PERIODE_ID,
        typeEvaluationId:  $TYPE_EVAL_ID,
        matiereId:         $MATIERE_ID,
        classeId:           $CLASSE_ID,
        enseignantId:       null,
        libelle:            'TEST_MIGRATION_' . time(),
        description:        'Évaluation de test — migration NoteController V1->V2',
        dateEvaluation:     date('Y-m-d'),
        coefficient:        1.0,
        noteMax:            20.0,
    );
    $evalId = $evalService->creer($dto, $ADMIN_USER_ID);
    $testEvalIds[] = $evalId;
    $eval = $pdo->query("SELECT statut, notes_saisie_ouverte FROM evaluations WHERE id = $evalId")->fetch(PDO::FETCH_OBJ);
    assert_true($eval->statut === 'brouillon', "Évaluation créée en statut 'brouillon' (id=$evalId)");
    assert_true((int)$eval->notes_saisie_ouverte === 0, "Saisie fermée à la création (comportement V2 attendu)");

    // ── Publication de l'évaluation (ouvre la saisie) ──
    $evalService->publier($evalId, $ADMIN_USER_ID);
    $eval = $pdo->query("SELECT statut, notes_saisie_ouverte FROM evaluations WHERE id = $evalId")->fetch(PDO::FETCH_OBJ);
    assert_true($eval->statut === 'publiee', "Évaluation publiée (statut='publiee')");
    assert_true((int)$eval->notes_saisie_ouverte === 1, "Saisie ouverte après publication de l'évaluation");

    // ── Saisie de notes (NoteService::saisirBatch -> notes_v2) ──
    $noteDto = \App\Modules\Academique\DTO\NoteDTO::fromRequest([
        'evaluation_id' => $evalId, 'eleve_id' => $testEleveId, 'valeur' => '14.5', 'est_absent' => '0',
    ]);
    $result = $noteService->saisirBatch(new NoteBatchDTO($evalId, [$noteDto]), $ADMIN_USER_ID);
    assert_true(($result['created'] ?? 0) === 1, "1 note créée via NoteService::saisirBatch()");

    $noteRow = $pdo->query("SELECT valeur, statut FROM notes_v2 WHERE evaluation_id = $evalId AND eleve_id = $testEleveId")->fetch(PDO::FETCH_OBJ);
    assert_true($noteRow !== false, "La note est bien présente dans notes_v2 (pas dans la table `notes` V1)");
    assert_true($noteRow !== false && abs((float)$noteRow->valeur - 14.5) < 0.001, "Valeur correcte en base (14.5)");
    assert_true($noteRow !== false && $noteRow->statut === 'saisie', "Statut initial de la note = 'saisie'");

    $v1Count = (int)$pdo->query("SELECT COUNT(*) FROM notes WHERE eleve_id = $testEleveId")->fetchColumn();
    assert_true($v1Count === 0, "Aucune ligne créée dans la table `notes` V1 pour cet élève de test");

    // ── Publication des notes (NoteService::publierTout -> NotePublished) ──
    $countPub = $noteService->publierTout($evalId, $ADMIN_USER_ID);
    assert_true($countPub === 1, "1 note publiée via publierTout()");
    $noteRow = $pdo->query("SELECT statut FROM notes_v2 WHERE evaluation_id = $evalId AND eleve_id = $testEleveId")->fetch(PDO::FETCH_OBJ);
    assert_true($noteRow->statut === 'publiee', "Statut de la note = 'publiee' après publication");

    // ── Vérification : la notification (NoteNotificationHandler) a bien été envoyée ──
    $notif = $pdo->prepare(
        "SELECT * FROM notifications WHERE user_id = :uid AND type = 'note' AND id > :after ORDER BY id DESC LIMIT 1"
    );
    $notif->execute([':uid' => $ADMIN_USER_ID, ':after' => $notifIdsBefore]);
    $notifRow = $notif->fetch(PDO::FETCH_OBJ);
    assert_true($notifRow !== false, "NotePublished a déclenché une notification pour le parent (NoteNotificationHandler) — l'événement ne disparaît pas silencieusement");
    if ($notifRow !== false) {
        assert_true(str_contains($notifRow->message, 'TestMigration'), "Notification correctement liée à l'élève de test (message: \"{$notifRow->message}\")");
    }

    // ── Verrouillage : évaluation puis notes ──
    $evalService->verrouiller($evalId, $ADMIN_USER_ID);
    $eval = $pdo->query("SELECT statut FROM evaluations WHERE id = $evalId")->fetch(PDO::FETCH_OBJ);
    assert_true($eval->statut === 'verrouillee', "Évaluation verrouillée");

    $countLock = $noteService->verrouillerTout($evalId, $ADMIN_USER_ID);
    assert_true($countLock === 1, "1 note verrouillée via verrouillerTout()");
    $noteRow = $pdo->query("SELECT statut, verrouille_par FROM notes_v2 WHERE evaluation_id = $evalId AND eleve_id = $testEleveId")->fetch(PDO::FETCH_OBJ);
    assert_true($noteRow->statut === 'verrouillee', "Statut de la note = 'verrouillee'");
    assert_true((int)$noteRow->verrouille_par === $ADMIN_USER_ID, "verrouille_par correctement renseigné");

    // Une note verrouillée ne doit plus être modifiable
    $blocked = false;
    try {
        $noteService->modifier((int)$pdo->query("SELECT id FROM notes_v2 WHERE evaluation_id = $evalId AND eleve_id = $testEleveId")->fetchColumn(),
            \App\Modules\Academique\DTO\NoteDTO::fromRequest(['evaluation_id' => $evalId, 'eleve_id' => $testEleveId, 'valeur' => '20', 'est_absent' => '0']),
            $ADMIN_USER_ID
        );
    } catch (\RuntimeException $e) {
        $blocked = str_contains($e->getMessage(), 'verrouillée');
    }
    assert_true($blocked, "Une note verrouillée refuse toute modification ultérieure");

    // ── Import CSV (évaluation séparée, non verrouillée) ──
    section('Import CSV');

    $dto2 = new EvaluationDTO(
        periodeScolaireId: $PERIODE_ID, typeEvaluationId: $TYPE_EVAL_ID, matiereId: $MATIERE_ID, classeId: $CLASSE_ID,
        enseignantId: null, libelle: 'TEST_MIGRATION_CSV_' . time(), description: '', dateEvaluation: date('Y-m-d'),
        coefficient: 1.0, noteMax: 20.0,
    );
    $evalId2 = $evalService->creer($dto2, $ADMIN_USER_ID);
    $testEvalIds[] = $evalId2;
    $evalService->publier($evalId2, $ADMIN_USER_ID);

    $csv = "eleve_id,valeur,absent,commentaire\n{$testEleveId},17,0,Bon travail\n";
    $csvResult = $noteService->importerCsv($evalId2, $csv, $ADMIN_USER_ID);
    assert_true(($csvResult['created'] ?? 0) === 1, "Import CSV : 1 note créée");
    $csvNote = $pdo->query("SELECT valeur FROM notes_v2 WHERE evaluation_id = $evalId2 AND eleve_id = $testEleveId")->fetch(PDO::FETCH_OBJ);
    assert_true($csvNote !== false && abs((float)$csvNote->valeur - 17.0) < 0.001, "Valeur importée correcte (17.0) dans notes_v2");

    // ── Validation saisie -> bulletin ──
    section('Validation saisie -> bulletin (BulletinEngineFactory)');

    $noteService->publierTout($evalId2, $ADMIN_USER_ID);
    try {
        $bulletin = BulletinEngineFactory::make(1)->previewBulletin($testEleveId, $PERIODE_ID);
        $found = null;
        foreach ($bulletin->lignesMatieres as $l) {
            if ((int)$l['matiere_id'] === $MATIERE_ID) { $found = $l; break; }
        }
        assert_true($found !== null, "Le bulletin de l'élève de test inclut bien la matière notée (aucune notes_v2 ignorée)");
        if ($found !== null) {
            assert_true($found['moyenne'] !== null, "Moyenne calculée (non nulle) pour la matière — les notes saisies via V2 sont bien prises en compte");
            $valeurs = array_column($found['notes'], 'valeur');
            assert_true(in_array(14.5, $valeurs, true), "La note saisie manuellement (14.5) apparaît dans le bulletin");
            assert_true(in_array(17.0, $valeurs, true), "La note importée par CSV (17.0) apparaît dans le bulletin");
        }
    } catch (\Throwable $e) {
        assert_true(false, "previewBulletin() n'a pas dû échouer : " . $e->getMessage());
    }

} finally {
    // ─── Nettoyage complet ────────────────────────────────────────────────────
    section('Nettoyage des données de test');

    if ($testEleveId !== null) {
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND type = 'note' AND id > ?")
            ->execute([$ADMIN_USER_ID, $notifIdsBefore]);
        foreach ($testEvalIds as $eid) {
            $pdo->prepare("DELETE FROM notes_historique WHERE note_id IN (SELECT id FROM notes_v2 WHERE evaluation_id = ?)")->execute([$eid]);
            $pdo->prepare("DELETE FROM notes_v2 WHERE evaluation_id = ?")->execute([$eid]);
            $pdo->prepare("DELETE FROM evaluations WHERE id = ?")->execute([$eid]);
        }
        $pdo->prepare("DELETE FROM inscriptions WHERE eleve_id = ?")->execute([$testEleveId]);
        $pdo->prepare("DELETE FROM eleves WHERE id = ?")->execute([$testEleveId]);
        $stillThere = $pdo->query("SELECT COUNT(*) FROM eleves WHERE id = $testEleveId")->fetchColumn();
        assert_true((int)$stillThere === 0, "Élève de test supprimé — base restaurée à son état initial");
    }
}

// ─── Résumé ────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n\033[1m" . ($failed === 0 ? "\033[32m" : "\033[31m");
echo "Résultat : $passed/$total assertions passées";
echo "\033[0m\n\n";

if ($failed > 0) exit(1);
