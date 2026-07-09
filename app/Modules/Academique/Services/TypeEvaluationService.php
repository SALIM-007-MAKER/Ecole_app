<?php

namespace App\Modules\Academique\Services;

use App\Modules\Academique\DTO\TypeEvaluationDTO;
use App\Modules\Academique\Events\EvaluationTypeActivated;
use App\Modules\Academique\Events\EvaluationTypeArchived;
use App\Modules\Academique\Events\EvaluationTypeCreated;
use App\Modules\Academique\Events\EvaluationTypeDeactivated;
use App\Modules\Academique\Events\EvaluationTypeUpdated;
use App\Modules\Academique\Models\TypeEvaluationModel;
use App\Modules\Academique\Repositories\TypeEvaluationRepository;
use Core\EventDispatcher;

class TypeEvaluationService
{
    private TypeEvaluationModel      $model;
    private TypeEvaluationRepository $repo;

    public function __construct()
    {
        $this->model = new TypeEvaluationModel();
        $this->repo  = new TypeEvaluationRepository();
    }

    // ─── Création ────────────────────────────────────────────────────────────

    public function creer(TypeEvaluationDTO $dto, int $userId): int
    {
        if ($this->repo->codeExists($dto->code)) {
            throw new \RuntimeException(
                "Le code « {$dto->code} » est déjà utilisé par un autre type d'évaluation."
            );
        }

        $data = array_merge($dto->toArray(), [
            'actif'       => 1,
            'est_archive' => 0,
            'est_systeme' => 0,
            'created_by'  => $userId,
        ]);

        $typeId = $this->model->insert($data);
        if ($typeId === 0) {
            throw new \RuntimeException("Erreur lors de la création du type d'évaluation.");
        }

        EventDispatcher::dispatch(new EvaluationTypeCreated(
            typeId:      $typeId,
            code:        $dto->code,
            nom:         $dto->nom,
            createdById: $userId,
        ));

        return $typeId;
    }

    // ─── Modification ────────────────────────────────────────────────────────

    public function modifier(int $typeId, TypeEvaluationDTO $dto, int $userId, bool $isAdmin = false): void
    {
        $type = $this->model->findById($typeId);
        if (!$type) {
            throw new \RuntimeException("Type d'évaluation introuvable (id={$typeId}).");
        }
        if ((int)$type->est_archive) {
            throw new \RuntimeException("Impossible de modifier un type archivé.");
        }
        if ((int)$type->est_systeme && !$isAdmin) {
            throw new \RuntimeException(
                "Le type « {$type->nom} » est un type système. Sa modification est réservée aux administrateurs."
            );
        }

        $avant = (array)$type;
        $data  = $dto->toUpdateArray(); // exclut le code (immuable)

        $this->model->update($typeId, $data);

        $changedFields = $this->diff($avant, $data);
        if (!empty($changedFields)) {
            EventDispatcher::dispatch(new EvaluationTypeUpdated(
                typeId:        $typeId,
                code:          $type->code,
                updatedById:   $userId,
                changedFields: $changedFields,
            ));
        }
    }

    // ─── Activation ──────────────────────────────────────────────────────────

    public function activer(int $typeId, int $userId): void
    {
        $type = $this->model->findById($typeId);
        if (!$type) {
            throw new \RuntimeException("Type d'évaluation introuvable.");
        }
        if ((int)$type->actif) {
            throw new \RuntimeException("Ce type est déjà actif.");
        }
        if ((int)$type->est_archive) {
            throw new \RuntimeException("Impossible d'activer un type archivé.");
        }

        $this->model->update($typeId, ['actif' => 1]);

        EventDispatcher::dispatch(new EvaluationTypeActivated(
            typeId:        $typeId,
            code:          $type->code,
            nom:           $type->nom,
            activatedById: $userId,
        ));
    }

    // ─── Désactivation ───────────────────────────────────────────────────────

    public function desactiver(int $typeId, int $userId, bool $isAdmin = false): void
    {
        $type = $this->model->findById($typeId);
        if (!$type) {
            throw new \RuntimeException("Type d'évaluation introuvable.");
        }
        if (!(int)$type->actif) {
            throw new \RuntimeException("Ce type est déjà inactif.");
        }
        if ((int)$type->est_archive) {
            throw new \RuntimeException("Type archivé : impossible de modifier son statut.");
        }
        if ((int)$type->est_systeme && !$isAdmin) {
            throw new \RuntimeException(
                "Le type « {$type->nom} » est un type système. Sa désactivation est réservée aux administrateurs."
            );
        }

        $this->model->update($typeId, ['actif' => 0]);

        EventDispatcher::dispatch(new EvaluationTypeDeactivated(
            typeId:          $typeId,
            code:            $type->code,
            nom:             $type->nom,
            deactivatedById: $userId,
        ));
    }

    // ─── Archivage ───────────────────────────────────────────────────────────

    public function archiver(int $typeId, int $userId): void
    {
        $type = $this->model->findById($typeId);
        if (!$type) {
            throw new \RuntimeException("Type d'évaluation introuvable.");
        }
        if ((int)$type->est_archive) {
            throw new \RuntimeException("Ce type est déjà archivé.");
        }
        if ($this->repo->isUsed($typeId, $type->code)) {
            throw new \RuntimeException(
                "Impossible d'archiver « {$type->nom} » : ce type est utilisé par des évaluations existantes. Désactivez-le à la place."
            );
        }

        $this->model->update($typeId, [
            'actif'       => 0,
            'est_archive' => 1,
        ]);

        EventDispatcher::dispatch(new EvaluationTypeArchived(
            typeId:      $typeId,
            code:        $type->code,
            nom:         $type->nom,
            archivedById:$userId,
        ));
    }

    // ─── Helper privé ────────────────────────────────────────────────────────

    private function diff(array $avant, array $apres): array
    {
        $changedAvant = [];
        $changedApres = [];

        foreach ($apres as $k => $v) {
            $prevVal = $avant[$k] ?? null;
            if ((string)$prevVal !== (string)$v) {
                $changedAvant[$k] = $prevVal;
                $changedApres[$k] = $v;
            }
        }

        if (empty($changedAvant)) return [];
        return ['avant' => $changedAvant, 'apres' => $changedApres];
    }
}
