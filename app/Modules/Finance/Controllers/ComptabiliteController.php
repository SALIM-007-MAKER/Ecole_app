<?php

namespace App\Modules\Finance\Controllers;

use App\Modules\Finance\DTO\EcritureFiltersDTO;
use App\Modules\Finance\DTO\ExerciceDTO;
use App\Modules\Finance\DTO\GrandLivreFiltersDTO;
use App\Modules\Finance\Policies\AccountingPolicy;
use App\Modules\Finance\Repositories\AccountingRepository;
use App\Modules\Finance\Services\AccountingService;
use Core\Controller;

class ComptabiliteController extends Controller
{
    private AccountingRepository $repo;
    private AccountingService    $service;
    private AccountingPolicy     $policy;

    public function __construct()
    {
        $this->repo    = new AccountingRepository();
        $this->service = new AccountingService();
        $this->policy  = new AccountingPolicy();
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function index(): void
    {
        $user = $this->currentUser();
        $this->requirePermission('finance.comptabilite.view');

        $exercice = $this->repo->findExerciceCourant();
        $stats    = $exercice ? $this->repo->getStatsDashboard($exercice->id) : null;
        $recent   = $exercice ? $this->repo->getRecentEcritures($exercice->id, 8) : [];
        $exercices = $this->repo->listExercices();
        $journaux  = $this->repo->listJournaux();

        $this->render('Finance::comptabilite/index', compact('exercice', 'stats', 'recent', 'exercices', 'journaux', 'user'));
    }

    // ── Plan comptable ────────────────────────────────────────────────────────

    public function planComptable(): void
    {
        $user = $this->currentUser();
        $this->requirePermission('finance.comptabilite.view');

        $exercice = $this->repo->findExerciceCourant();
        $comptes  = $exercice
            ? $this->repo->getComptesSoldes($exercice->id)
            : $this->repo->listComptes();

        $this->render('Finance::comptabilite/plan_comptable', compact('comptes', 'exercice', 'user'));
    }

    // ── Journal ───────────────────────────────────────────────────────────────

    public function journal(): void
    {
        $user = $this->currentUser();
        $this->requirePermission('finance.comptabilite.view');

        $filters  = EcritureFiltersDTO::fromRequest($_GET);
        $exercice = $filters->exerciceId
            ? $this->repo->findExercice($filters->exerciceId)
            : $this->repo->findExerciceCourant();

        if ($exercice && !$filters->exerciceId) {
            $filters = EcritureFiltersDTO::fromRequest(array_merge($_GET, ['exercice_id' => $exercice->id]));
        }

        $pagination = $this->repo->paginateEcritures([
            'exercice_id'  => $filters->exerciceId ?: ($exercice->id ?? 0),
            'periode_id'   => $filters->periodeId,
            'journal_code' => $filters->journalCode,
            'compte_code'  => $filters->compteCode,
            'statut'       => $filters->statut,
            'source'       => $filters->source,
            'q'            => $filters->q,
            'date_debut'   => $filters->dateDebut,
            'date_fin'     => $filters->dateFin,
        ], $filters->page, $filters->perPage);

        $exercices = $this->repo->listExercices();
        $journaux  = $this->repo->listJournaux();
        $periodes  = $exercice ? $this->repo->getPeriodesByExercice($exercice->id) : [];
        $comptes   = $this->repo->listComptes();
        $canSaisir = $this->policy->canSaisir($user);

        $this->render('Finance::comptabilite/journal', compact(
            'pagination', 'filters', 'exercice', 'exercices', 'journaux', 'periodes', 'comptes', 'canSaisir', 'user'
        ));
    }

    public function showEcriture(int $id): void
    {
        $user = $this->currentUser();
        $this->requirePermission('finance.comptabilite.view');

        $ecriture = $this->repo->findEcriture($id);
        if (!$ecriture) {
            $this->redirect('/v2/finance/comptabilite/journal', 'Écriture introuvable.', 'error');
            return;
        }

        $lignes    = $this->repo->getLignesByEcriture($id);
        $canSaisir = $this->policy->canSaisir($user);

        $this->render('Finance::comptabilite/ecriture_show', compact('ecriture', 'lignes', 'canSaisir', 'user'));
    }

    // ── Grand Livre ───────────────────────────────────────────────────────────

    public function grandLivre(): void
    {
        $user = $this->currentUser();
        $this->requirePermission('finance.comptabilite.view');

        $filters  = GrandLivreFiltersDTO::fromRequest($_GET);
        $exercice = $filters->exerciceId
            ? $this->repo->findExercice($filters->exerciceId)
            : $this->repo->findExerciceCourant();

        $lignes = $this->repo->getGrandLivre([
            'exercice_id' => $exercice->id ?? 0,
            'compte_code' => $filters->compteCode,
            'classe'      => $filters->classe,
            'type'        => $filters->type,
            'date_debut'  => $filters->dateDebut,
            'date_fin'    => $filters->dateFin,
        ]);

        // Grouper par compte
        $parCompte = [];
        foreach ($lignes as $l) {
            $parCompte[$l->compte_code][] = $l;
        }

        $exercices = $this->repo->listExercices();
        $comptes   = $this->repo->listComptes();

        $this->render('Finance::comptabilite/grand_livre', compact(
            'parCompte', 'filters', 'exercice', 'exercices', 'comptes', 'user'
        ));
    }

    // ── Balance générale ──────────────────────────────────────────────────────

    public function balance(): void
    {
        $user = $this->currentUser();
        $this->requirePermission('finance.comptabilite.view');

        $exerciceId = (int)($_GET['exercice_id'] ?? 0);
        $exercice   = $exerciceId
            ? $this->repo->findExercice($exerciceId)
            : $this->repo->findExerciceCourant();

        $lignesBalance = $exercice ? $this->repo->getBalance($exercice->id) : [];

        $totalDebit  = 0.0;
        $totalCredit = 0.0;
        foreach ($lignesBalance as $l) {
            $totalDebit  += (float)$l->total_debit;
            $totalCredit += (float)$l->total_credit;
        }

        $exercices = $this->repo->listExercices();

        $this->render('Finance::comptabilite/balance', compact(
            'lignesBalance', 'totalDebit', 'totalCredit', 'exercice', 'exercices', 'user'
        ));
    }

    // ── Exercices ─────────────────────────────────────────────────────────────

    public function exercices(): void
    {
        $user = $this->currentUser();
        $this->requirePermission('finance.comptabilite.view');

        $exercices = $this->repo->listExercices();
        $canGerer  = $this->policy->canGererExercice($user);

        $this->render('Finance::comptabilite/exercices', compact('exercices', 'canGerer', 'user'));
    }

    public function showExercice(int $id): void
    {
        $user = $this->currentUser();
        $this->requirePermission('finance.comptabilite.view');

        $exercice = $this->repo->findExercice($id);
        if (!$exercice) {
            $this->redirect('/v2/finance/comptabilite/exercices', 'Exercice introuvable.', 'error');
            return;
        }

        $periodes = $this->repo->getPeriodesByExercice($id);
        $canGerer = $this->policy->canGererExercice($user);

        $this->render('Finance::comptabilite/exercice_show', compact('exercice', 'periodes', 'canGerer', 'user'));
    }

    public function createExercice(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canGererExercice($user)) {
            $this->redirect('/v2/finance/comptabilite/exercices', 'Permission insuffisante.', 'error');
            return;
        }
        $this->render('Finance::comptabilite/exercice_form', ['user' => $user, 'errors' => [], 'old' => []]);
    }

    public function storeExercice(): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canGererExercice($user)) {
            $this->redirect('/v2/finance/comptabilite/exercices', 'Permission insuffisante.', 'error');
            return;
        }
        $this->verifyCsrf();

        $dto    = ExerciceDTO::fromRequest($_POST);
        $errors = $dto->validate();

        if (!empty($errors)) {
            $this->render('Finance::comptabilite/exercice_form', ['user' => $user, 'errors' => $errors, 'old' => $_POST]);
            return;
        }

        try {
            $id = $this->service->creerExercice($dto, (int)$user['id']);
            $this->redirect("/v2/finance/comptabilite/exercices/{$id}", "Exercice créé avec succès.", 'success');
        } catch (\InvalidArgumentException $e) {
            $this->render('Finance::comptabilite/exercice_form', ['user' => $user, 'errors' => ['general' => $e->getMessage()], 'old' => $_POST]);
        }
    }

    public function cloturerPeriode(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canCloturerPeriode($user)) {
            $this->json(['error' => 'Permission insuffisante.'], 403);
            return;
        }
        $this->verifyCsrf();

        try {
            $this->service->cloturerPeriode($id, (int)$user['id']);
            $periode = $this->repo->findPeriode($id);
            $redirect = $periode ? "/v2/finance/comptabilite/exercices/{$periode->exercice_id}" : '/v2/finance/comptabilite/exercices';
            $this->redirect($redirect, "Période clôturée avec succès.", 'success');
        } catch (\DomainException $e) {
            $this->redirect('/v2/finance/comptabilite/exercices', $e->getMessage(), 'error');
        }
    }

    public function cloturerExercice(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canCloturerExercice($user)) {
            $this->redirect('/v2/finance/comptabilite/exercices', 'Permission insuffisante.', 'error');
            return;
        }
        $this->verifyCsrf();

        try {
            $this->service->cloturerExercice($id, (int)$user['id']);
            $this->redirect("/v2/finance/comptabilite/exercices/{$id}", "Exercice clôturé avec succès.", 'success');
        } catch (\DomainException $e) {
            $this->redirect("/v2/finance/comptabilite/exercices/{$id}", $e->getMessage(), 'error');
        }
    }

    public function extourner(int $id): void
    {
        $user = $this->currentUser();
        if (!$this->policy->canExtourner($user)) {
            $this->redirect('/v2/finance/comptabilite/journal', 'Permission insuffisante.', 'error');
            return;
        }
        $this->verifyCsrf();

        $motif = trim($_POST['motif'] ?? '');
        if ($motif === '') {
            $this->redirect("/v2/finance/comptabilite/ecritures/{$id}", 'Le motif d\'extourne est obligatoire.', 'error');
            return;
        }

        try {
            $newId = $this->service->extourner($id, $motif, (int)$user['id']);
            $this->redirect("/v2/finance/comptabilite/ecritures/{$newId}", "Écriture d'extourne #{$newId} créée.", 'success');
        } catch (\DomainException $e) {
            $this->redirect("/v2/finance/comptabilite/ecritures/{$id}", $e->getMessage(), 'error');
        }
    }
}
