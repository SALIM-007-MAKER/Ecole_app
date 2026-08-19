<?php
/**
 * Tests — Configuration des périodes scolaires Niger (trimestriel, préconfiguré)
 *
 * Vérifie : génération automatique depuis le modèle par défaut configurable,
 * calcul des dates (bascule d'année civile), contraintes (chevauchement,
 * bornes de l'année scolaire, doublons), cycle de vie (preparation → ouverte
 * → cloturee → archivee), verrouillage orthogonal, vérification de couverture.
 *
 * Toute la session tourne dans UNE transaction PDO annulée à la fin
 * (rollback) — zéro pollution de la base, rejouable à volonté.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/PeriodeScolaireNigerTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));

$envFile = ROOT_PATH . '/.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $val] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
}

spl_autoload_register(function (string $class): void {
    $namespaces = [
        'Core\\'                   => ROOT_PATH . '/core/',
        'App\\Modules\\Academique\\' => ROOT_PATH . '/app/Modules/Academique/',
        'App\\Services\\'          => ROOT_PATH . '/app/Services/',
    ];
    foreach ($namespaces as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file = $dir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) { require $file; return; }
        }
    }
});

use App\Modules\Academique\DTO\PeriodeConfigDTO;
use App\Modules\Academique\DTO\PeriodeScolaireDTO;
use App\Modules\Academique\Repositories\PeriodeScolaireRepository;
use App\Modules\Academique\Services\PeriodeConfigService;
use App\Modules\Academique\Services\PeriodeScolaireService;
use Core\Database;

$passed = 0;
$failed = 0;

function assert_true(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) { echo "\033[32m  ✓ $label\033[0m\n"; $passed++; }
    else       { echo "\033[31m  ✗ $label\033[0m\n"; $failed++; }
}

function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        echo "\033[32m  ✓ $label\033[0m\n"; $passed++;
    } else {
        echo "\033[31m  ✗ $label\033[0m\n";
        echo "      expected: " . var_export($expected, true) . "\n";
        echo "      actual:   " . var_export($actual, true) . "\n";
        $failed++;
    }
}

function assert_throws(callable $fn, string $label): void
{
    global $passed, $failed;
    try {
        $fn();
        echo "\033[31m  ✗ $label (aucune exception levée)\033[0m\n"; $failed++;
    } catch (\Throwable $e) {
        echo "\033[32m  ✓ $label\033[0m\n"; $passed++;
    }
}

function section(string $title): void
{
    echo "\n\033[1;36m── {$title} ──\033[0m\n";
}

$pdo = Database::getInstance()->getConnection();
$pdo->beginTransaction();

try {
    // ─── 1. Modèle par défaut : préconfiguré Niger, entièrement configurable ──
    section('Modèle par défaut (PeriodeConfigService)');

    $configService = new PeriodeConfigService();
    $template = $configService->getTemplate();
    assert_eq(3, count($template), 'Le modèle par défaut contient 3 entrées (trimestres)');

    $t1 = $template[0];
    assert_eq('Premier trimestre', $t1->nom_defaut, 'T1 : nom par défaut = Premier trimestre');
    assert_eq(10, (int)$t1->mois_debut, 'T1 : mois de début par défaut = octobre (10)');
    assert_eq(1,  (int)$t1->jour_debut, 'T1 : jour de début par défaut = 1er');
    assert_eq(12, (int)$t1->mois_fin,   'T1 : mois de fin par défaut = décembre (12)');
    assert_eq(31, (int)$t1->jour_fin,   'T1 : jour de fin par défaut = 31');

    // Modification par l'administrateur (le modèle doit rester configurable)
    $dtoConfig = PeriodeConfigDTO::fromRequest(1, [
        'nom_defaut' => 'Trimestre 1 (test)', 'mois_debut' => 9, 'jour_debut' => 15,
        'mois_fin' => 12, 'jour_fin' => 20, 'ordre' => 1,
    ]);
    assert_true(empty($dtoConfig->validate()), 'DTO config : validation OK pour des dates modifiées');
    $configService->mettreAJour(1, $dtoConfig, 1);
    $t1Modifie = $configService->getTemplate()[0];
    assert_eq('Trimestre 1 (test)', $t1Modifie->nom_defaut, 'Le modèle par défaut est bien modifiable (nom mis à jour)');
    assert_eq(9, (int)$t1Modifie->mois_debut, 'Le modèle par défaut est bien modifiable (mois de début mis à jour)');

    // Restauration pour la suite du test (Niger officiel)
    $configService->mettreAJour(1, PeriodeConfigDTO::fromRequest(1, [
        'nom_defaut' => 'Premier trimestre', 'mois_debut' => 10, 'jour_debut' => 1,
        'mois_fin' => 12, 'jour_fin' => 31, 'ordre' => 1,
    ]), 1);

    // ─── 2. Calcul des dates : bascule d'année civile ─────────────────────────
    section('Calcul des dates (bascule année civile)');

    $dates = $configService->calculerDates('2025-2026');
    assert_eq(3, count($dates), 'calculerDates() retourne 3 périodes pour 2025-2026');
    assert_eq('2025-10-01', $dates[0]['date_debut'], 'T1 début = 2025-10-01 (année de début Y1)');
    assert_eq('2025-12-31', $dates[0]['date_fin'],   'T1 fin = 2025-12-31 (Y1)');
    assert_eq('2026-01-01', $dates[1]['date_debut'], 'T2 début = 2026-01-01 (année de fin Y2)');
    assert_eq('2026-03-31', $dates[1]['date_fin'],   'T2 fin = 2026-03-31 (Y2)');
    assert_eq('2026-04-01', $dates[2]['date_debut'], 'T3 début = 2026-04-01 (Y2)');
    assert_eq('2026-06-30', $dates[2]['date_fin'],   'T3 fin = 2026-06-30 (Y2)');

    $bornes = $configService->bornesAnneeScolaire('2025-2026');
    assert_eq('2025-08-01', $bornes['min'], 'Bornes année scolaire : min = 2025-08-01');
    assert_eq('2026-07-31', $bornes['max'], 'Bornes année scolaire : max = 2026-07-31');

    // ─── 3. Génération automatique (aucune date codée en dur côté service) ───
    section('Génération automatique des périodes');

    $service = new PeriodeScolaireService();
    $repo    = new PeriodeScolaireRepository();
    $annee   = '2031-2032'; // année isolée, garantie inexistante avant ce test

    $resultat = $service->genererParDefaut($annee, 1);
    assert_eq(3, count($resultat['crees']), 'Génération : 3 périodes créées pour ' . $annee);
    assert_true(empty($resultat['ignores']), 'Génération : aucune période ignorée (année vierge)');

    $periodes = $repo->findAllWithStats($annee);
    assert_eq(3, count($periodes), '3 périodes retrouvées en base pour ' . $annee);
    assert_eq('preparation', $periodes[0]->statut, 'Période générée : statut initial = preparation');
    assert_eq(1, (int)$periodes[0]->genere_automatiquement, 'Période générée : flag genere_automatiquement = 1');

    // Re-génération : doit ignorer les 3 numéros déjà existants
    $resultat2 = $service->genererParDefaut($annee, 1);
    assert_eq(0, count($resultat2['crees']), 'Re-génération : 0 période créée (déjà existantes)');
    assert_eq(3, count($resultat2['ignores']), 'Re-génération : 3 périodes ignorées (doublons numéro)');

    // ─── 4. Contraintes : chevauchement, bornes, doublons ─────────────────────
    section('Contraintes de validation');

    $anneeContraintes = '2032-2033';

    // Chevauchement : une période T1 [10-01, 12-31] existe déjà (générée par défaut)
    $service->genererParDefaut($anneeContraintes, 1);

    assert_throws(function () use ($service, $anneeContraintes) {
        $dto = new PeriodeScolaireDTO(
            anneeScolaire: $anneeContraintes, typePeriode: 'custom', numero: 9,
            nom: 'Période chevauchante', dateDebut: '2032-11-01', dateFin: '2032-11-15', ordre: 9,
        );
        $service->creer($dto, 1);
    }, 'Chevauchement avec une période existante → rejeté');

    assert_throws(function () use ($service, $anneeContraintes) {
        $dto = new PeriodeScolaireDTO(
            anneeScolaire: $anneeContraintes, typePeriode: 'custom', numero: 9,
            nom: 'Hors bornes', dateDebut: '2032-07-01', dateFin: '2032-07-15', ordre: 9,
        );
        $service->creer($dto, 1);
    }, 'Dates hors des bornes de l\'année scolaire → rejeté');

    assert_throws(function () use ($service, $anneeContraintes) {
        $dto = new PeriodeScolaireDTO(
            anneeScolaire: $anneeContraintes, typePeriode: 'trimestre', numero: 1,
            nom: 'Doublon numéro', dateDebut: null, dateFin: null, ordre: 1,
        );
        $service->creer($dto, 1);
    }, 'Doublon (même année/type/numéro) → rejeté');

    assert_throws(function () use ($service, $anneeContraintes) {
        $dto = new PeriodeScolaireDTO(
            anneeScolaire: $anneeContraintes, typePeriode: 'custom', numero: 8,
            nom: 'Premier trimestre', dateDebut: null, dateFin: null, ordre: 8,
        );
        $service->creer($dto, 1);
    }, 'Doublon (même nom) → rejeté');

    assert_throws(function () {
        $dto = new PeriodeScolaireDTO(
            anneeScolaire: '2033-2034', typePeriode: 'custom', numero: 1,
            nom: 'Fin avant début', dateDebut: '2033-10-10', dateFin: '2033-10-01', ordre: 1,
        );
        $errors = $dto->validate();
        if (!empty($errors)) throw new \RuntimeException('validation rejetée');
    }, 'Date de fin antérieure à la date de début → rejeté (DTO)');

    // Une période valide et non chevauchante doit passer (juillet : hors T1/T2/T3, dans les bornes)
    $dto = new PeriodeScolaireDTO(
        anneeScolaire: $anneeContraintes, typePeriode: 'custom', numero: 9,
        nom: 'Stage optionnel', dateDebut: '2033-07-05', dateFin: '2033-07-10', ordre: 9,
    );
    $idValide = $service->creer($dto, 1);
    assert_true($idValide > 0, 'Période valide, non chevauchante → créée avec succès');

    // ─── 5. Vérification de couverture ─────────────────────────────────────────
    section('Vérification de couverture');

    $couverture = $service->verifierCouverture($anneeContraintes);
    assert_true(!$couverture['couverte'], 'Couverture incomplète détectée (seul T1 + un stage ponctuel existent)');
    assert_true(count($couverture['gaps']) > 0, 'Au moins un trou de couverture signalé');

    $anneeComplete = '2034-2035';
    $service->genererParDefaut($anneeComplete, 1);
    $couvertureComplete = $service->verifierCouverture($anneeComplete);
    assert_true($couvertureComplete['couverte'], 'Couverture complète après génération des 3 trimestres officiels');
    assert_eq(0, count($couvertureComplete['gaps']), 'Aucun trou après génération complète');

    // ─── 6. Cycle de vie : preparation → ouverte → cloturee → archivee ────────
    section('Cycle de vie et verrouillage');

    $periodesCycle = $repo->findAllWithStats($anneeComplete);
    $idCycle = $periodesCycle[0]->id;

    assert_throws(fn() => $service->cloturer($idCycle, 1), 'Clôturer depuis "preparation" → rejeté (transition invalide)');

    $service->ouvrir($idCycle, 1);
    $p = $repo->findWithStats($idCycle);
    assert_eq('ouverte', $p->statut, 'ouvrir() : preparation → ouverte');

    assert_throws(fn() => $service->archiver($idCycle, 1), 'Archiver depuis "ouverte" → rejeté (doit être clôturée)');

    $service->activer($idCycle, 1);
    $p = $repo->findWithStats($idCycle);
    assert_eq(1, (int)$p->is_active, 'activer() : is_active = 1 depuis le statut "ouverte"');

    $service->cloturer($idCycle, 1);
    $p = $repo->findWithStats($idCycle);
    assert_eq('cloturee', $p->statut, 'cloturer() : ouverte → cloturee');
    assert_eq(0, (int)$p->notes_saisie_ouverte, 'cloturer() : saisie de notes bloquée');

    $service->verrouiller($idCycle, 1);
    $p = $repo->findWithStats($idCycle);
    assert_true($p->verrouille_par !== null, 'verrouiller() : verrouille_par renseigné');
    assert_eq('cloturee', $p->statut, 'verrouiller() : le statut reste "cloturee" (verrouillage orthogonal)');

    assert_throws(fn() => $service->archiver($idCycle, 1), 'Archiver une période verrouillée → rejeté');
    assert_throws(fn() => $service->reouvrir($idCycle, 1), 'Réouvrir une période verrouillée → rejeté');

    $service->deverrouiller($idCycle, 1);
    $p = $repo->findWithStats($idCycle);
    assert_true($p->verrouille_par === null, 'deverrouiller() : verrouille_par redevient NULL');

    $service->archiver($idCycle, 1);
    $p = $repo->findWithStats($idCycle);
    assert_eq('archivee', $p->statut, 'archiver() : cloturee → archivee');

    assert_throws(function () use ($service, $idCycle) {
        $dto = new PeriodeScolaireDTO(
            anneeScolaire: '2034-2035', typePeriode: 'trimestre', numero: 1,
            nom: 'Tentative modif', dateDebut: null, dateFin: null, ordre: 1,
        );
        $service->modifier($idCycle, $dto, 1, false);
    }, 'Modifier une période archivée → rejeté');

    // ─── 7. Édition libre du statut (admin) via modifier() ────────────────────
    section('Édition directe du statut (admin)');

    $periodesCycle2 = $repo->findAllWithStats($anneeComplete);
    $idCycle2 = $periodesCycle2[1]->id; // T2, encore en "preparation"

    $dtoStatut = new PeriodeScolaireDTO(
        anneeScolaire: $anneeComplete, typePeriode: 'trimestre', numero: $periodesCycle2[1]->numero,
        nom: $periodesCycle2[1]->nom, dateDebut: $periodesCycle2[1]->date_debut, dateFin: $periodesCycle2[1]->date_fin,
        ordre: (int)$periodesCycle2[1]->ordre, statut: 'ouverte',
    );
    $service->modifier($idCycle2, $dtoStatut, 1, isAdmin: true);
    $p2 = $repo->findWithStats($idCycle2);
    assert_eq('ouverte', $p2->statut, 'Admin : édition directe du statut acceptée (preparation → ouverte, hors machine d\'états)');

    assert_throws(function () use ($service, $anneeComplete, $periodesCycle2) {
        $dtoStatutNonAdmin = new PeriodeScolaireDTO(
            anneeScolaire: $anneeComplete, typePeriode: 'trimestre', numero: $periodesCycle2[2]->numero,
            nom: $periodesCycle2[2]->nom, dateDebut: $periodesCycle2[2]->date_debut, dateFin: $periodesCycle2[2]->date_fin,
            ordre: (int)$periodesCycle2[2]->ordre, statut: 'ouverte',
        );
        $service->modifier($periodesCycle2[2]->id, $dtoStatutNonAdmin, 1, isAdmin: false);
    }, 'Non-admin : édition directe du statut refusée');

} finally {
    $pdo->rollBack();
    echo "\n(rollback effectué — aucune donnée de test persistée)\n";
}

echo "\n";
if ($failed === 0) {
    echo "\033[1;32mRésultat : {$passed}/{$passed} assertions passées\033[0m\n";
    exit(0);
}
echo "\033[1;31mRésultat : {$passed} OK / {$failed} FAIL\033[0m\n";
exit(1);
