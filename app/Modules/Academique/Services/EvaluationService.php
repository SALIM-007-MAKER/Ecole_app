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
use App\Modules\Scolarite\Repositories\InscriptionRepository;
use Core\EventDispatcher;

class EvaluationService
{
    private EvaluationModel        $model;
    private EvaluationRepository   $repo;
    private PeriodeScolaireModel   $periodeModel;
    private TypeEvaluationModel    $typeModel;
    private InscriptionRepository  $inscriptionRepo;

    public function __construct()
    {
        $this->model           = new EvaluationModel();
        $this->repo            = new EvaluationRepository();
        $this->periodeModel    = new PeriodeScolaireModel();
        $this->typeModel       = new TypeEvaluationModel();
        $this->inscriptionRepo = new InscriptionRepository();
    }

    // ─── Création ────────────────────────────────────────────────────────────

    public function creer(EvaluationDTO $dto, int $userId): int
    {
        $this->verifierReferences($dto);

        // Valider la période
        $periode = $this->periodeModel->findById($dto->periodeScolaireId);
        if (!$periode) {
            throw new \RuntimeException("Période scolaire introuvable.");
        }
        if ($periode->statut === 'archivee') {
            throw new \RuntimeException("Impossible de créer une évaluation dans une période archivée.");
        }
        if (($periode->verrouille_par ?? null) !== null) {
            throw new \RuntimeException("Impossible de créer une évaluation dans une période verrouillée.");
        }

        // AN-C-001 : la classe doit avoir au moins un élève activement inscrit
        // pour l'année scolaire de la période — sinon l'évaluation ne peut
        // rattacher aucune note à un élève réellement inscrit cette année-là.
        if (!$this->inscriptionRepo->existsForClasseEtAnnee($dto->classeId, $periode->annee_scolaire)) {
            throw new \RuntimeException(
                "Aucun élève n'est inscrit dans cette classe pour l'année scolaire {$periode->annee_scolaire}."
            );
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

        // Conduite (marqueur matieres.est_conduite) : coefficient 1 et barème 20 imposés à la création, et une seule
        // évaluation non archivée par classe et par période (les doublons historiques ne sont jamais touchés).
        $estConduite = $this->repo->matiereEstConduite($dto->matiereId);
        if ($estConduite === null) {
            throw new \RuntimeException("Matière introuvable.");
        }
        if ($estConduite) {
            $dto = $this->conduiteImposee($dto);
            $this->refuserSecondeConduite($dto->classeId, $dto->periodeScolaireId, null);
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
        $this->verifierReferences($dto);
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
            if ($periode->statut === 'archivee' || ($periode->verrouille_par ?? null) !== null) {
                $motif = $periode->statut === 'archivee' ? 'archivée' : 'verrouillée';
                throw new \RuntimeException("Impossible de déplacer l'évaluation vers une période {$motif}.");
            }
        }

        // Règle d'unicité de la Conduite : uniquement si la modification change la matière, la classe ou la période
        // (modifier le libellé d'une évaluation historique ne touche ni son coefficient ni sa note maximale).
        $matiereChange  = (int)$evaluation->matiere_id !== $dto->matiereId;
        $contexteChange = $matiereChange
            || (int)$evaluation->classe_id !== $dto->classeId
            || (int)$evaluation->periode_scolaire_id !== $dto->periodeScolaireId;
        if ($contexteChange) {
            $estConduite = $this->repo->matiereEstConduite($dto->matiereId);
            if ($estConduite === null) {
                throw new \RuntimeException("Matière introuvable.");
            }
            if ($estConduite) {
                if ($matiereChange) {
                    $dto = $this->conduiteImposee($dto);
                }
                $this->refuserSecondeConduite($dto->classeId, $dto->periodeScolaireId, $evaluationId);
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

    /**
     * Isolation (Lot 3) : tous les identifiants soumis par le formulaire doivent appartenir à l'établissement courant — classe,
     * matière, enseignant, période et type d'évaluation. Un identifiant étranger est traité comme inexistant.
     */
    private function verifierReferences(EvaluationDTO $dto): void
    {
        if (!$this->repo->classeAppartient($dto->classeId)) {
            throw new \RuntimeException("Classe introuvable.");
        }
        if ($this->repo->matiereEstConduite($dto->matiereId) === null) {
            throw new \RuntimeException("Matière introuvable.");
        }
        if ($dto->enseignantId !== null && !$this->repo->enseignantAppartient($dto->enseignantId)) {
            throw new \RuntimeException("Enseignant introuvable.");
        }
        if (!$this->periodeModel->findById($dto->periodeScolaireId)) {
            throw new \RuntimeException("Période scolaire introuvable.");
        }
        if (!$this->typeModel->findById($dto->typeEvaluationId)) {
            throw new \RuntimeException("Type d'évaluation introuvable.");
        }
    }

    /** Conduite : coefficient d'évaluation 1 et note maximale 20. */
    private function conduiteImposee(EvaluationDTO $dto): EvaluationDTO
    {
        return new EvaluationDTO(
            periodeScolaireId: $dto->periodeScolaireId,
            typeEvaluationId:  $dto->typeEvaluationId,
            matiereId:         $dto->matiereId,
            classeId:          $dto->classeId,
            enseignantId:      $dto->enseignantId,
            libelle:           $dto->libelle,
            description:       $dto->description,
            dateEvaluation:    $dto->dateEvaluation,
            coefficient:       1.00,
            noteMax:           20.00,
        );
    }

    private function refuserSecondeConduite(int $classeId, int $periodeId, ?int $exceptId): void
    {
        if ($this->repo->conduiteExisteDeja($classeId, $periodeId, $exceptId)) {
            throw new \RuntimeException(
                "Une évaluation de Conduite existe déjà pour cette classe et cette période. "
                . "Une seule évaluation de Conduite non archivée est autorisée par classe et par période."
            );
        }
    }

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
