<?php

namespace App\Modules\Academique\Services;

use App\Modules\Academique\DTO\EvaluationDTO;
use App\Modules\Academique\Events\EvaluationArchived;
use App\Modules\Academique\Events\EvaluationCreated;
use App\Modules\Academique\Events\EvaluationLocked;
use App\Modules\Academique\Events\EvaluationPublished;
use App\Modules\Academique\Events\EvaluationUpdated;
use App\Modules\Academique\Models\EvaluationModel;
use App\Modules\Academique\Models\PeriodeScolaireModel;
use App\Modules\Academique\Models\TypeEvaluationModel;
use App\Modules\Academique\Repositories\EvaluationRepository;
use Core\EventDispatcher;

class EvaluationService
{
    private EvaluationModel      $model;
    private EvaluationRepository $repo;
    private PeriodeScolaireModel $periodeModel;
    private TypeEvaluationModel  $typeModel;

    public function __construct()
    {
        $this->model        = new EvaluationModel();
        $this->repo         = new EvaluationRepository();
        $this->periodeModel = new PeriodeScolaireModel();
        $this->typeModel    = new TypeEvaluationModel();
    }

    // ─── Création ────────────────────────────────────────────────────────────

    public function creer(EvaluationDTO $dto, int $userId): int
    {
        // Valider la période
        $periode = $this->periodeModel->findById($dto->periodeScolaireId);
        if (!$periode) {
            throw new \RuntimeException("Période scolaire introuvable.");
        }
        if ($periode->statut === 'archivee') {
            throw new \RuntimeException("Impossible de créer une évaluation dans une période archivée.");
        }
        if ($periode->statut === 'verrouillee') {
            throw new \RuntimeException("Impossible de créer une évaluation dans une période verrouillée.");
        }

        // Valider le type d'évaluation
        $type = $this->typeModel->findById($dto->typeEvaluationId);
        if (!$type) {
            throw new \RuntimeException("Type d'évaluation introuvable.");
        }
        if (!(int)$type->actif || (int)$type->est_archive) {
            throw new \RuntimeException(
                "Le type « {$type->nom} » est inactif ou archivé. Choisissez un type actif."
            );
        }

        $data = array_merge($dto->toArray(), [
            'statut'             => 'brouillon',
            'notes_saisie_ouverte' => 0,
            'created_by'         => $userId,
        ]);

        $evaluationId = $this->model->insert($data);
        if ($evaluationId === 0) {
            throw new \RuntimeException("Erreur lors de la création de l'évaluation.");
        }

        EventDispatcher::dispatch(new EvaluationCreated(
            evaluationId:      $evaluationId,
            libelle:           $dto->libelle,
            periodeScolaireId: $dto->periodeScolaireId,
            classeId:          $dto->classeId,
            matiereId:         $dto->matiereId,
            createdById:       $userId,
        ));

        return $evaluationId;
    }

    // ─── Modification ────────────────────────────────────────────────────────

    public function modifier(int $evaluationId, EvaluationDTO $dto, int $userId, bool $isAdmin = false): void
    {
        $evaluation = $this->model->findById($evaluationId);
        if (!$evaluation) {
            throw new \RuntimeException("Évaluation introuvable (id={$evaluationId}).");
        }
        if ($evaluation->statut === 'archivee') {
            throw new \RuntimeException("Impossible de modifier une évaluation archivée.");
        }
        if ($evaluation->statut === 'verrouillee' && !$isAdmin) {
            throw new \RuntimeException(
                "L'évaluation « {$evaluation->libelle} » est verrouillée. Seul un administrateur peut la modifier."
            );
        }

        // Valider la nouvelle période si elle change
        if ((int)$evaluation->periode_scolaire_id !== $dto->periodeScolaireId) {
            $periode = $this->periodeModel->findById($dto->periodeScolaireId);
            if (!$periode) {
                throw new \RuntimeException("Nouvelle période scolaire introuvable.");
            }
            if (in_array($periode->statut, ['archivee', 'verrouillee'], true)) {
                throw new \RuntimeException(
                    "Impossible de déplacer l'évaluation vers une période {$periode->statut}."
                );
            }
        }

        $avant = (array)$evaluation;
        $data  = $dto->toArray();

        $this->model->update($evaluationId, $data);

        $changedFields = $this->diff($avant, $data);
        if (!empty($changedFields)) {
            EventDispatcher::dispatch(new EvaluationUpdated(
                evaluationId:  $evaluationId,
                libelle:       $dto->libelle,
                updatedById:   $userId,
                changedFields: $changedFields,
            ));
        }
    }

    // ─── Publication ─────────────────────────────────────────────────────────

    public function publier(int $evaluationId, int $userId): void
    {
        $evaluation = $this->model->findById($evaluationId);
        if (!$evaluation) {
            throw new \RuntimeException("Évaluation introuvable.");
        }
        if ($evaluation->statut !== 'brouillon') {
            throw new \RuntimeException(
                "Seule une évaluation en brouillon peut être publiée (statut actuel : {$evaluation->statut})."
            );
        }

        $this->model->update($evaluationId, [
            'statut'              => 'publiee',
            'notes_saisie_ouverte'=> 1,
        ]);

        EventDispatcher::dispatch(new EvaluationPublished(
            evaluationId: $evaluationId,
            libelle:      $evaluation->libelle,
            classeId:     (int)$evaluation->classe_id,
            matiereId:    (int)$evaluation->matiere_id,
            publishedById:$userId,
        ));
    }

    // ─── Verrouillage ────────────────────────────────────────────────────────

    public function verrouiller(int $evaluationId, int $userId): void
    {
        $evaluation = $this->model->findById($evaluationId);
        if (!$evaluation) {
            throw new \RuntimeException("Évaluation introuvable.");
        }
        if ($evaluation->statut !== 'publiee') {
            throw new \RuntimeException(
                "Seule une évaluation publiée peut être verrouillée (statut actuel : {$evaluation->statut})."
            );
        }

        $this->model->update($evaluationId, [
            'statut'               => 'verrouillee',
            'notes_saisie_ouverte' => 0,
            'verrouille_par'       => $userId,
            'verrouille_le'        => date('Y-m-d H:i:s'),
        ]);

        EventDispatcher::dispatch(new EvaluationLocked(
            evaluationId: $evaluationId,
            libelle:      $evaluation->libelle,
            lockedById:   $userId,
        ));
    }

    // ─── Déverrouillage ──────────────────────────────────────────────────────

    public function deverrouiller(int $evaluationId, int $userId): void
    {
        $evaluation = $this->model->findById($evaluationId);
        if (!$evaluation) {
            throw new \RuntimeException("Évaluation introuvable.");
        }
        if ($evaluation->statut !== 'verrouillee') {
            throw new \RuntimeException(
                "Seule une évaluation verrouillée peut être déverrouillée (statut actuel : {$evaluation->statut})."
            );
        }

        $this->model->update($evaluationId, [
            'statut'               => 'publiee',
            'notes_saisie_ouverte' => 1,
            'verrouille_par'       => null,
            'verrouille_le'        => null,
        ]);

        // Pas d'événement EvaluationUnlocked — on dispatche EvaluationUpdated
        EventDispatcher::dispatch(new EvaluationUpdated(
            evaluationId:  $evaluationId,
            libelle:       $evaluation->libelle,
            updatedById:   $userId,
            changedFields: [
                'avant' => ['statut' => 'verrouillee', 'notes_saisie_ouverte' => 0],
                'apres' => ['statut' => 'publiee',     'notes_saisie_ouverte' => 1],
            ],
        ));
    }

    // ─── Archivage ───────────────────────────────────────────────────────────

    public function archiver(int $evaluationId, int $userId): void
    {
        $evaluation = $this->model->findById($evaluationId);
        if (!$evaluation) {
            throw new \RuntimeException("Évaluation introuvable.");
        }
        if ($evaluation->statut === 'archivee') {
            throw new \RuntimeException("Cette évaluation est déjà archivée.");
        }
        if ($this->repo->hasNotes($evaluationId)) {
            throw new \RuntimeException(
                "Impossible d'archiver « {$evaluation->libelle} » : des notes ont déjà été saisies."
            );
        }

        $this->model->update($evaluationId, [
            'statut'               => 'archivee',
            'notes_saisie_ouverte' => 0,
        ]);

        EventDispatcher::dispatch(new EvaluationArchived(
            evaluationId: $evaluationId,
            libelle:      $evaluation->libelle,
            archivedById: $userId,
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
