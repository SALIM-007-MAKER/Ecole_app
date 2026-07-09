<?php

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\DTO\ClasseDTO;
use App\Modules\Scolarite\Events\ClasseCreated;
use App\Modules\Scolarite\Events\ClasseUpdated;
use App\Modules\Scolarite\Events\ClasseDeleted;
use App\Modules\Scolarite\Models\ClasseModel;
use App\Modules\Scolarite\Repositories\ClasseRepository;
use App\Services\AuditService;
use Core\EventDispatcher;

class ClasseService
{
    private ClasseModel      $model;
    private ClasseRepository $repo;
    private AuditService     $audit;

    public function __construct()
    {
        $this->model = new ClasseModel();
        $this->repo  = new ClasseRepository();
        $this->audit = new AuditService();
    }

    public function creer(ClasseDTO $dto, int $userId): int
    {
        $data    = $dto->toArray();
        $classeId = $this->model->insert($data);

        if ($classeId === 0) {
            throw new \RuntimeException("Erreur lors de la création de la classe.");
        }

        EventDispatcher::dispatch(new ClasseCreated(
            classeId:      $classeId,
            nom:           $dto->nom,
            niveau:        $dto->niveau,
            anneeScolaire: $dto->anneeScolaire,
            createdById:   $userId,
        ));

        return $classeId;
    }

    public function modifier(int $classeId, ClasseDTO $dto, int $userId): void
    {
        $avant = $this->model->findById($classeId);
        $data  = $dto->toArray();

        $this->model->update($classeId, $data);

        $changedFields = [];
        if ($avant) {
            foreach ($data as $k => $v) {
                $prev = $avant->$k ?? null;
                if ((string)$prev !== (string)$v) {
                    $changedFields['avant'][$k] = $prev;
                    $changedFields['apres'][$k] = $v;
                }
            }
        }

        EventDispatcher::dispatch(new ClasseUpdated($classeId, $userId, $changedFields));
    }

    public function supprimer(int $classeId, int $userId): void
    {
        $classe = $this->model->findById($classeId);
        if (!$classe) {
            throw new \RuntimeException("Classe introuvable.");
        }

        $details = $this->repo->findWithDetails($classeId);
        if ($details && (int)$details->nb_eleves > 0) {
            throw new \RuntimeException(
                "Impossible de supprimer une classe contenant des élèves ({$details->nb_eleves} élève(s) affecté(s))."
            );
        }

        $this->model->delete($classeId);

        EventDispatcher::dispatch(new ClasseDeleted(
            classeId:    $classeId,
            nom:         $classe->niveau . ' ' . $classe->nom,
            deletedById: $userId,
        ));
    }

    /**
     * Retourne {actuel, max, libre} pour la barre de capacité.
     */
    public function verifierCapacite(int $classeId): array
    {
        $details = $this->repo->findWithDetails($classeId);
        if (!$details) {
            return ['actuel' => 0, 'max' => 0, 'libre' => 0];
        }
        $actuel = (int)$details->nb_eleves;
        $max    = (int)$details->max_eleves;
        return [
            'actuel' => $actuel,
            'max'    => $max,
            'libre'  => max(0, $max - $actuel),
        ];
    }
}
