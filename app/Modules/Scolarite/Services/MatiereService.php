<?php

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\DTO\MatiereDTO;
use App\Modules\Scolarite\Models\MatiereModel;
use App\Modules\Scolarite\Repositories\MatiereRepository;
use App\Modules\Scolarite\Events\MatiereCreated;
use App\Modules\Scolarite\Events\MatiereUpdated;
use App\Modules\Scolarite\Events\MatiereArchived;
use App\Modules\Scolarite\Events\MatiereAssignedToClasse;
use App\Modules\Scolarite\Events\MatiereRemovedFromClasse;
use Core\EventDispatcher;

class MatiereService
{
    private MatiereModel      $model;
    private MatiereRepository $repo;

    public function __construct(MatiereRepository $repo)
    {
        $this->model = new MatiereModel();
        $this->repo  = $repo;
    }

    // ─── Créer ────────────────────────────────────────────────────────────────

    public function creer(MatiereDTO $dto, int $userId): int
    {
        // Unicité du nom (globale — pas par niveau en V2)
        if ($this->repo->nomExists($dto->nom)) {
            throw new \RuntimeException("Une matière avec le nom « {$dto->nom} » existe déjà.");
        }

        $data         = $dto->toArray();
        $data['actif'] = 1;

        $matiereId = $this->model->insert($data);

        EventDispatcher::dispatch(new MatiereCreated($matiereId, $dto->nom, $dto->coefficient, $userId));

        return $matiereId;
    }

    // ─── Modifier ─────────────────────────────────────────────────────────────

    public function modifier(int $matiereId, MatiereDTO $dto, int $userId): void
    {
        $matiere = $this->model->findById($matiereId);
        if (!$matiere) {
            throw new \RuntimeException("Matière introuvable (id=$matiereId).");
        }

        // Unicité du nom (exclure la matière courante)
        if ($this->repo->nomExists($dto->nom, $matiereId)) {
            throw new \RuntimeException("Une autre matière avec le nom « {$dto->nom} » existe déjà.");
        }

        $data    = $dto->toArray();
        $changed = [];
        foreach ($data as $field => $newVal) {
            if ((string)($matiere->$field ?? '') !== (string)($newVal ?? '')) {
                $changed[] = $field;
            }
        }

        $this->model->update($matiereId, $data);

        if (!empty($changed)) {
            EventDispatcher::dispatch(new MatiereUpdated($matiereId, $userId, $changed));
        }
    }

    // ─── Archiver ─────────────────────────────────────────────────────────────

    public function archiver(int $matiereId, int $userId): void
    {
        $matiere = $this->model->findById($matiereId);
        if (!$matiere) {
            throw new \RuntimeException("Matière introuvable (id=$matiereId).");
        }

        // Avertir si la matière est encore utilisée dans des enseignements actifs
        $nbEns = $this->repo->countEnseignements($matiereId);
        if ($nbEns > 0) {
            throw new \RuntimeException(
                "Impossible d'archiver : cette matière est utilisée dans $nbEns enseignement(s) actif(s). " .
                "Retirez-la des classes concernées avant d'archiver."
            );
        }

        $this->model->update($matiereId, ['actif' => 0]);

        EventDispatcher::dispatch(new MatiereArchived($matiereId, $matiere->nom, $userId));
    }

    // ─── Vérifier avant suppression physique ─────────────────────────────────

    public function verifierSuppression(int $matiereId): void
    {
        $nbEns   = $this->repo->countEnseignements($matiereId);
        $nbNotes = $this->repo->countNotes($matiereId);

        if ($nbEns > 0) {
            throw new \RuntimeException(
                "Impossible de supprimer : cette matière est référencée dans $nbEns enseignement(s)."
            );
        }
        if ($nbNotes > 0) {
            throw new \RuntimeException(
                "Impossible de supprimer : cette matière est référencée dans $nbNotes note(s). " .
                "Utilisez l'archivage à la place."
            );
        }
    }

    // ─── Assigner à une classe (via AffectationService) ──────────────────────

    public function notifierAssignation(
        int    $matiereId,
        int    $classeId,
        int    $professeurId,
        string $anneeScolaire,
        int    $userId
    ): void {
        EventDispatcher::dispatch(
            new MatiereAssignedToClasse($matiereId, $classeId, $professeurId, $anneeScolaire, $userId)
        );
    }

    // ─── Retirer d'une classe ─────────────────────────────────────────────────

    public function notifierRetrait(int $matiereId, int $classeId, int $userId): void
    {
        EventDispatcher::dispatch(
            new MatiereRemovedFromClasse($matiereId, $classeId, $userId)
        );
    }
}
