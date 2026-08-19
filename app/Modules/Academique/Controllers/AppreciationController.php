<?php

declare(strict_types=1);

namespace App\Modules\Academique\Controllers;

use App\Models\ClasseModel;
use App\Models\EleveModel;
use App\Models\MatiereModel;
use App\Models\ProfesseurModel;
use App\Modules\Academique\DTO\AppreciationBatchDTO;
use App\Modules\Academique\Models\PeriodeScolaireModel;
use App\Modules\Academique\Policies\AppreciationPolicy;
use App\Modules\Academique\Repositories\AppreciationMatiereRepository;
use App\Modules\Academique\Services\AppreciationService;
use Core\Controller;
use Core\Session;

/**
 * Saisie manuelle, par le professeur, de l'appréciation par matière affichée
 * dans le bulletin V1 papier (colonne « Appréciations des Professeurs »,
 * app/Modules/Academique/Views/bulletins/partials/tableau-matieres.php).
 * Routes déclarées dans config/routes.php (module 'academique' désactivé,
 * même raison que le bloc Bulletin V1 papier).
 */
class AppreciationController extends Controller
{
    private ClasseModel               $classeModel;
    private MatiereModel               $matiereModel;
    private PeriodeScolaireModel       $periodeModel;
    private ProfesseurModel            $professeurModel;
    private AppreciationMatiereRepository $repo;
    private AppreciationService         $service;
    private AppreciationPolicy          $policy;

    public function __construct()
    {
        parent::__construct();
        $this->classeModel     = new ClasseModel();
        $this->matiereModel    = new MatiereModel();
        $this->periodeModel    = new PeriodeScolaireModel();
        $this->professeurModel = new ProfesseurModel();
        $this->repo            = new AppreciationMatiereRepository();
        $this->service          = new AppreciationService();
        $this->policy           = new AppreciationPolicy();
    }

    /** Sélection classe / matière / période avant saisie. */
    public function index(): void
    {
        $this->requirePermission('academique.notes.view');

        $this->render('Academique::appreciations/index', [
            'title'    => 'Appréciations par matière',
            'classes'  => $this->classeModel->findAll('niveau'),
            'matieres' => $this->matiereModel->findAll('nom'),
            'periodes' => $this->periodeModel->findAll('id'),
        ]);
    }

    public function saisie(string $classeId, string $matiereId): void
    {
        $this->requirePermission('academique.notes.view');

        $classeId  = (int)$classeId;
        $matiereId = (int)$matiereId;
        $periodeId = (int)$this->request->get('periode_id', 0);

        $classe  = $this->classeModel->findById($classeId);
        $matiere = $this->matiereModel->findById($matiereId);
        $periode = $periodeId ? $this->periodeModel->findById($periodeId) : false;

        if (!$classe || !$matiere || !$periode) {
            Session::flash('error', 'Classe, matière ou période introuvable.');
            $this->redirect(BASE_URL . '/v2/academique/appreciations');
            return;
        }

        $eleves = $this->repo->listElevesAvecAppreciation($classeId, $matiereId, $periodeId);

        $this->render('Academique::appreciations/saisie', [
            'title'     => "Appréciations — {$matiere->nom} — {$classe->niveau} {$classe->nom}",
            'classe'    => $classe,
            'matiere'   => $matiere,
            'periode'   => $periode,
            'eleves'    => $eleves,
        ]);
    }

    public function store(string $classeId, string $matiereId): void
    {
        $this->requirePermission('academique.notes.manage');
        $this->verifyCsrf();

        $classeId  = (int)$classeId;
        $matiereId = (int)$matiereId;
        $periodeId = (int)$this->request->post('periode_id', 0);
        $user      = $this->currentUser();

        $classe  = $this->classeModel->findById($classeId);
        $matiere = $this->matiereModel->findById($matiereId);
        $periode = $periodeId ? $this->periodeModel->findById($periodeId) : false;

        if (!$classe || !$matiere || !$periode) {
            Session::flash('error', 'Classe, matière ou période introuvable.');
            $this->redirect(BASE_URL . '/v2/academique/appreciations');
            return;
        }

        $professeur = $this->professeurModel->findByUserId((int)$user['id']);
        $profId     = $professeur->id ?? null;

        if (!$this->policy->canSaisir($user, $profId, $matiereId, $classeId, $periode->annee_scolaire)) {
            Session::flash('error', "Vous n'êtes pas affecté à cette matière pour cette classe.");
            $this->redirect(BASE_URL . "/v2/academique/classes/{$classeId}/matieres/{$matiereId}/appreciations?periode_id={$periodeId}");
            return;
        }

        $batch   = AppreciationBatchDTO::fromRequest($classeId, $matiereId, $periodeId, $_POST);
        $results = $this->service->saisirBatch($batch, (int)$user['etablissement_id'], $profId, (int)$user['id']);

        Session::flash('success', "{$results['created']} appréciation(s) créée(s), {$results['updated']} mise(s) à jour.");
        $this->redirect(BASE_URL . "/v2/academique/classes/{$classeId}/matieres/{$matiereId}/appreciations?periode_id={$periodeId}");
    }
}
