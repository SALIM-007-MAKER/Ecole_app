<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Services;

use App\Modules\Rapports\DTO\PlanificationDTO;
use App\Modules\Rapports\DTO\ReportFiltersDTO;
use App\Modules\Rapports\Events\RapportPlanifie;
use App\Modules\Rapports\Events\RapportExecute;
use App\Modules\Rapports\Repositories\PlanificationRepository;
use App\Modules\Rapports\Repositories\ExportRepository;
use Core\EventDispatcher;

class PlanificationService
{
    private PlanificationRepository $repo;
    private ExportRepository        $execRepo;
    private ReportGeneratorService  $generator;

    public function __construct()
    {
        $this->repo      = new PlanificationRepository();
        $this->execRepo  = new ExportRepository();
        $this->generator = new ReportGeneratorService();
    }

    public function lister(int $etablissementId, int $page = 1): array
    {
        return $this->repo->paginate($etablissementId, $page);
    }

    public function find(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function creer(PlanificationDTO $dto, int $userId, int $etablissementId): int
    {
        $id = $this->repo->create([
            'nom'              => $dto->nom,
            'domaine'          => $dto->domaine,
            'type_export'      => $dto->typeExport,
            'filtres'          => $dto->filtres,
            'frequence'        => $dto->frequence,
            'jour_execution'   => $dto->jourExecution,
            'heure_execution'  => $dto->heureExecution,
            'destinataires'    => $dto->destinataires,
            'actif'            => (int)$dto->actif,
            'created_by'       => $userId,
            'etablissement_id' => $etablissementId,
        ]);

        EventDispatcher::dispatch(new RapportPlanifie($id, $dto->nom, $dto->frequence, $userId, $etablissementId));
        return $id;
    }

    public function modifier(int $id, PlanificationDTO $dto): void
    {
        $this->repo->update($id, [
            'nom'             => $dto->nom,
            'domaine'         => $dto->domaine,
            'type_export'     => $dto->typeExport,
            'filtres'         => $dto->filtres,
            'frequence'       => $dto->frequence,
            'jour_execution'  => $dto->jourExecution,
            'heure_execution' => $dto->heureExecution,
            'destinataires'   => $dto->destinataires,
            'actif'           => (int)$dto->actif,
        ]);
    }

    public function supprimer(int $id): void
    {
        $this->repo->softDelete($id);
    }

    public function executerPlanifie(int $planId, int $etablissementId): void
    {
        $plan = $this->repo->find($planId);
        if (!$plan) return;

        $execId = $this->execRepo->createExecution([
            'rapport_planifie_id' => $planId,
            'domaine'             => $plan['domaine'],
            'type_export'         => $plan['type_export'],
            'statut'              => 'en_cours',
            'filtres'             => $plan['filtres'] ? json_decode($plan['filtres'], true) : null,
            'etablissement_id'    => $etablissementId,
        ]);
        $this->execRepo->updateExecution($execId, 'en_cours');

        try {
            $filters = ReportFiltersDTO::fromRequest(array_merge(
                json_decode($plan['filtres'] ?? '{}', true) ?? [],
                ['domaine' => $plan['domaine'], 'type_export' => $plan['type_export']],
            ));
            $this->generator->generer($filters, $etablissementId, 0);
            $this->execRepo->updateExecution($execId, 'termine', ['nb_lignes' => 0]);
            EventDispatcher::dispatch(new RapportExecute($execId, $plan['domaine'], 'termine', $etablissementId, $planId));
        } catch (\Throwable $e) {
            $this->execRepo->updateExecution($execId, 'erreur', ['erreur_message' => $e->getMessage()]);
            EventDispatcher::dispatch(new RapportExecute($execId, $plan['domaine'], 'erreur', $etablissementId, $planId));
        }
    }
}
