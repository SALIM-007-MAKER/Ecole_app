<?php

namespace App\Modules\Finance\Controllers;

use Core\Controller;
use App\Modules\Finance\DTO\CashRegisterDTO;
use App\Modules\Finance\DTO\CashMovementDTO;
use App\Modules\Finance\DTO\CashSessionFiltersDTO;
use App\Modules\Finance\Policies\CashRegisterPolicy;
use App\Modules\Finance\Repositories\CashRegisterRepository;
use App\Modules\Finance\Repositories\CashMovementRepository;
use App\Modules\Finance\Services\CashRegisterService;

class CaisseController extends Controller
{
    private CashRegisterService    $service;
    private CashRegisterRepository $repo;
    private CashMovementRepository $mouvRepo;
    private CashRegisterPolicy     $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service  = new CashRegisterService();
        $this->repo     = new CashRegisterRepository();
        $this->mouvRepo = new CashMovementRepository();
        $this->policy   = new CashRegisterPolicy();
    }

    // GET /v2/finance/caisse
    public function index(): void
    {
        $user = $this->currentUser();
        $this->requirePermission('finance.caisse.view');

        $filters  = CashSessionFiltersDTO::fromRequest($_GET);
        $result   = $this->repo->paginate($filters);
        $stats    = $this->repo->statsGlobal($filters->dateDebut, $filters->dateFin);
        $actives  = $this->repo->findAnyActive();
        $caissiers = $this->repo->getCaissiers();

        $this->render('Finance::caisse/index', [
            'title'      => 'Caisse',
            'result'     => $result,
            'filters'    => $filters,
            'stats'      => $stats,
            'actives'    => $actives,
            'caissiers'  => $caissiers,
            'statuts'    => \App\Modules\Finance\Models\SessionCaisseModel::STATUTS,
            'canOuvrir'  => $this->policy->canOuvrir($user),
            'canAdmin'   => $this->policy->canAdmin($user),
            'maSession'  => $this->service->getSessionActive((int)$user['id']),
        ]);
    }

    // GET /v2/finance/caisse/active
    public function active(): void
    {
        $user    = $this->currentUser();
        $session = $this->service->getSessionActive((int)$user['id']);
        if ($session) {
            $this->redirect('/v2/finance/caisse/' . $session->id);
        } else {
            $this->redirect('/v2/finance/caisse/create');
        }
    }

    // GET /v2/finance/caisse/create
    public function create(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canOuvrir($user)) {
            \Core\Session::setFlash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/caisse');
            return;
        }

        $sessionActive = $this->service->getSessionActive((int)$user['id']);
        if ($sessionActive) {
            \Core\Session::setFlash('warning', 'Vous avez déjà une session ouverte : ' . $sessionActive->numero);
            $this->redirect('/v2/finance/caisse/' . $sessionActive->id);
            return;
        }

        $this->render('Finance::caisse/form', [
            'title'  => 'Ouvrir la caisse',
            'errors' => [],
            'old'    => ['solde_initial' => '0', 'date' => date('Y-m-d'), 'heure' => date('H:i')],
        ]);
    }

    // POST /v2/finance/caisse
    public function store(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canOuvrir($user)) {
            $this->json(['error' => 'Accès refusé.'], 403);
            return;
        }
        $this->verifyCsrf();

        $dto    = CashRegisterDTO::fromRequest($_POST);
        $errors = $dto->validate();
        if ($errors) {
            $this->render('Finance::caisse/form', [
                'title'  => 'Ouvrir la caisse',
                'errors' => $errors,
                'old'    => $_POST,
            ]);
            return;
        }

        try {
            $sessionId = $this->service->ouvrir($dto, (int)$user['id']);
            \Core\Session::setFlash('success', 'Caisse ouverte avec succès.');
            $this->redirect('/v2/finance/caisse/' . $sessionId);
        } catch (\Throwable $e) {
            $this->render('Finance::caisse/form', [
                'title'  => 'Ouvrir la caisse',
                'errors' => ['global' => $e->getMessage()],
                'old'    => $_POST,
            ]);
        }
    }

    // GET /v2/finance/caisse/{id}
    public function show(int $id): void
    {
        $user    = $this->currentUser();
        $session = $this->repo->findWithDetails($id);
        if (!$session || !$this->policy->canViewSession($user, $session)) {
            \Core\Session::setFlash('error', 'Session introuvable ou accès refusé.');
            $this->redirect('/v2/finance/caisse');
            return;
        }

        $mouvements  = $this->mouvRepo->getBySession($id, true);
        $statsType   = $this->mouvRepo->statsParType($id);
        $journal     = $this->repo->getJournal($id);

        $this->render('Finance::caisse/show', [
            'title'       => 'Caisse ' . $session->numero,
            'session'     => $session,
            'mouvements'  => $mouvements,
            'stats_type'  => $statsType,
            'journal'     => $journal,
            'types'       => \App\Modules\Finance\Models\MouvementCaisseModel::TYPES,
            'canMouvement'=> $this->policy->canEnregistrerMouvement($user),
            'canAnnuler'  => $this->policy->canAnnulerMouvement($user),
            'canFermer'   => $this->policy->canFermer($user),
            'canRapproche'=> $this->policy->canRapprocher($user),
            'isOwner'     => (int)$session->caissier_id === (int)$user['id'],
        ]);
    }

    // GET /v2/finance/caisse/{id}/fermer
    public function fermerForm(int $id): void
    {
        $user    = $this->currentUser();
        $session = $this->repo->findWithDetails($id);
        if (!$session || !$this->policy->canFermer($user)) {
            \Core\Session::setFlash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/caisse/' . $id);
            return;
        }
        if (!in_array($session->statut, ['ouverte', 'en_activite'], true)) {
            \Core\Session::setFlash('error', 'Cette session ne peut pas être fermée.');
            $this->redirect('/v2/finance/caisse/' . $id);
            return;
        }

        $totaux = $this->repo->calculerTotaux($id);

        $this->render('Finance::caisse/fermer', [
            'title'   => 'Fermer la caisse ' . $session->numero,
            'session' => $session,
            'totaux'  => $totaux,
            'errors'  => [],
            'old'     => ['solde_reel' => '', 'note' => ''],
        ]);
    }

    // POST /v2/finance/caisse/{id}/fermer
    public function fermer(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canFermer($user)) {
            \Core\Session::setFlash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/caisse/' . $id);
            return;
        }
        $this->verifyCsrf();

        $soldeReel = (float)($_POST['solde_reel'] ?? 0);
        $note      = trim($_POST['note'] ?? '') ?: null;

        if ($soldeReel < 0) {
            $session = $this->repo->findWithDetails($id);
            $totaux  = $this->repo->calculerTotaux($id);
            $this->render('Finance::caisse/fermer', [
                'title'   => 'Fermer la caisse ' . $session->numero,
                'session' => $session,
                'totaux'  => $totaux,
                'errors'  => ['solde_reel' => 'Le solde réel ne peut pas être négatif.'],
                'old'     => $_POST,
            ]);
            return;
        }

        try {
            $this->service->fermer($id, $soldeReel, $note, (int)$user['id']);
            \Core\Session::setFlash('success', 'Caisse fermée avec succès.');
            $this->redirect('/v2/finance/caisse/' . $id);
        } catch (\Throwable $e) {
            \Core\Session::setFlash('error', $e->getMessage());
            $this->redirect('/v2/finance/caisse/' . $id . '/fermer');
        }
    }

    // POST /v2/finance/caisse/{id}/mouvement
    public function storeMouvement(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canEnregistrerMouvement($user)) {
            \Core\Session::setFlash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/caisse/' . $id);
            return;
        }
        $this->verifyCsrf();

        $dto    = CashMovementDTO::fromRequest($_POST);
        $errors = $dto->validate();
        if ($errors) {
            \Core\Session::setFlash('error', implode(' | ', $errors));
            $this->redirect('/v2/finance/caisse/' . $id);
            return;
        }

        try {
            $this->service->enregistrerMouvement($id, $dto, (int)$user['id']);
            \Core\Session::setFlash('success', 'Mouvement enregistré.');
        } catch (\Throwable $e) {
            \Core\Session::setFlash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/caisse/' . $id);
    }

    // POST /v2/finance/caisse/{id}/mouvement/{mouvId}/annuler
    public function annulerMouvement(int $id, int $mouvId): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canAnnulerMouvement($user)) {
            \Core\Session::setFlash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/caisse/' . $id);
            return;
        }
        $this->verifyCsrf();

        $motif = trim($_POST['motif'] ?? '');
        if (empty($motif)) {
            \Core\Session::setFlash('error', 'Un motif est requis pour annuler un mouvement.');
            $this->redirect('/v2/finance/caisse/' . $id);
            return;
        }

        try {
            $this->service->annulerMouvement($mouvId, $motif, (int)$user['id']);
            \Core\Session::setFlash('success', 'Mouvement annulé. L\'opération inverse a été enregistrée.');
        } catch (\Throwable $e) {
            \Core\Session::setFlash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/caisse/' . $id);
    }

    // POST /v2/finance/caisse/{id}/rapprocher
    public function rapprocher(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canRapprocher($user)) {
            \Core\Session::setFlash('error', 'Accès refusé.');
            $this->redirect('/v2/finance/caisse/' . $id);
            return;
        }
        $this->verifyCsrf();

        $note = trim($_POST['note'] ?? '');
        try {
            $this->service->rapprocher($id, $note, (int)$user['id']);
            \Core\Session::setFlash('success', 'Journal rapproché et validé.');
        } catch (\Throwable $e) {
            \Core\Session::setFlash('error', $e->getMessage());
        }
        $this->redirect('/v2/finance/caisse/' . $id);
    }

    // GET /v2/finance/caisse/{id}/journal/print
    public function journalPrint(int $id): void
    {
        $user    = $this->currentUser();
        $session = $this->repo->findWithDetails($id);
        if (!$session || !$this->policy->canViewSession($user, $session)) {
            $this->redirect('/v2/finance/caisse');
            return;
        }

        $mouvements = $this->mouvRepo->getBySession($id, true);
        $totaux     = $this->repo->calculerTotaux($id);
        $journal    = $this->repo->getJournal($id);

        $this->render('Finance::caisse/journal_print', [
            'session'    => $session,
            'mouvements' => $mouvements,
            'totaux'     => $totaux,
            'journal'    => $journal,
            'types'      => \App\Modules\Finance\Models\MouvementCaisseModel::TYPES,
        ]);
    }
}
