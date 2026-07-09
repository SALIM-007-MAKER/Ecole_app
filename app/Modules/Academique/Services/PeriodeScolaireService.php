<?php

namespace App\Modules\Academique\Services;

use App\Modules\Academique\DTO\PeriodeScolaireDTO;
use App\Modules\Academique\Events\PeriodeActivated;
use App\Modules\Academique\Events\PeriodeArchived;
use App\Modules\Academique\Events\PeriodeCreated;
use App\Modules\Academique\Events\PeriodeLocked;
use App\Modules\Academique\Events\PeriodeUnlocked;
use App\Modules\Academique\Events\PeriodeUpdated;
use App\Modules\Academique\Models\PeriodeScolaireModel;
use App\Modules\Academique\Repositories\PeriodeScolaireRepository;
use App\Services\AuditService;
use Core\EventDispatcher;

class PeriodeScolaireService
{
    private PeriodeScolaireModel      $model;
    private PeriodeScolaireRepository $repo;
    private AuditService              $audit;

    public function __construct()
    {
        $this->model = new PeriodeScolaireModel();
        $this->repo  = new PeriodeScolaireRepository();
        $this->audit = new AuditService();
    }

    // ─── Création ────────────────────────────────────────────────────────────

    public function creer(PeriodeScolaireDTO $dto, int $userId): int
    {
        if ($this->repo->existsForAnneeTypeNumero($dto->anneeScolaire, $dto->typePeriode, $dto->numero)) {
            $typeLabel = PeriodeScolaireDTO::typeLabels()[$dto->typePeriode] ?? $dto->typePeriode;
            throw new \RuntimeException(
                "{$typeLabel} {$dto->numero} existe déjà pour l'année {$dto->anneeScolaire}."
            );
        }

        $data = array_merge($dto->toArray(), [
            'statut'              => 'ouverte',
            'is_active'           => 0,
            'notes_saisie_ouverte'=> 1,
            'created_by'          => $userId,
        ]);

        $periodeId = $this->model->insert($data);
        if ($periodeId === 0) {
            throw new \RuntimeException("Erreur lors de la création de la période.");
        }

        EventDispatcher::dispatch(new PeriodeCreated(
            periodeId:    $periodeId,
            nom:          $dto->nom,
            anneeScolaire:$dto->anneeScolaire,
            typePeriode:  $dto->typePeriode,
            numero:       $dto->numero,
            createdById:  $userId,
        ));

        return $periodeId;
    }

    // ─── Modification ────────────────────────────────────────────────────────

    public function modifier(int $periodeId, PeriodeScolaireDTO $dto, int $userId, bool $isAdmin = false): void
    {
        $periode = $this->model->findById($periodeId);
        if (!$periode) {
            throw new \RuntimeException("Période introuvable (id={$periodeId}).");
        }

        if ($periode->statut === 'verrouillee' && !$isAdmin) {
            throw new \RuntimeException(
                "La période « {$periode->nom} » est verrouillée. Seul un administrateur peut la modifier."
            );
        }

        if ($periode->statut === 'archivee') {
            throw new \RuntimeException("Impossible de modifier une période archivée.");
        }

        if ($this->repo->existsForAnneeTypeNumero($dto->anneeScolaire, $dto->typePeriode, $dto->numero, $periodeId)) {
            $typeLabel = PeriodeScolaireDTO::typeLabels()[$dto->typePeriode] ?? $dto->typePeriode;
            throw new \RuntimeException(
                "{$typeLabel} {$dto->numero} existe déjà pour l'année {$dto->anneeScolaire}."
            );
        }

        $avant = (array)$periode;
        $data  = $dto->toArray();

        $this->model->update($periodeId, $data);

        $changedFields = $this->diff($avant, $data);
        if (!empty($changedFields)) {
            EventDispatcher::dispatch(new PeriodeUpdated(
                periodeId:     $periodeId,
                updatedById:   $userId,
                changedFields: $changedFields,
            ));
        }
    }

    // ─── Activation ──────────────────────────────────────────────────────────

    public function activer(int $periodeId, int $userId): void
    {
        $periode = $this->model->findById($periodeId);
        if (!$periode) {
            throw new \RuntimeException("Période introuvable.");
        }
        if ($periode->statut === 'archivee') {
            throw new \RuntimeException("Impossible d'activer une période archivée.");
        }
        if ($periode->statut === 'verrouillee') {
            throw new \RuntimeException("Impossible d'activer une période verrouillée.");
        }
        if ((int)$periode->is_active === 1) {
            throw new \RuntimeException("Cette période est déjà active.");
        }

        // Garantie : une seule période active par annee_scolaire
        $this->model->deactiverTous($periode->annee_scolaire);
        $this->model->update($periodeId, ['is_active' => 1]);

        EventDispatcher::dispatch(new PeriodeActivated(
            periodeId:     $periodeId,
            nom:           $periode->nom,
            anneeScolaire: $periode->annee_scolaire,
            activatedById: $userId,
        ));
    }

    // ─── Fermeture ───────────────────────────────────────────────────────────

    public function fermer(int $periodeId, int $userId): void
    {
        $periode = $this->model->findById($periodeId);
        if (!$periode) {
            throw new \RuntimeException("Période introuvable.");
        }
        if ($periode->statut !== 'ouverte') {
            throw new \RuntimeException(
                "Seule une période ouverte peut être fermée (statut actuel : {$periode->statut})."
            );
        }

        $this->model->update($periodeId, [
            'statut'               => 'fermee',
            'notes_saisie_ouverte' => 0,
        ]);

        EventDispatcher::dispatch(new PeriodeUpdated(
            periodeId:     $periodeId,
            updatedById:   $userId,
            changedFields: [
                'avant' => ['statut' => 'ouverte', 'notes_saisie_ouverte' => 1],
                'apres' => ['statut' => 'fermee',  'notes_saisie_ouverte' => 0],
            ],
        ));
    }

    // ─── Verrouillage ────────────────────────────────────────────────────────

    public function verrouiller(int $periodeId, int $userId): void
    {
        $periode = $this->model->findById($periodeId);
        if (!$periode) {
            throw new \RuntimeException("Période introuvable.");
        }
        if ($periode->statut === 'verrouillee') {
            throw new \RuntimeException("Cette période est déjà verrouillée.");
        }
        if ($periode->statut === 'archivee') {
            throw new \RuntimeException("Impossible de verrouiller une période archivée.");
        }

        $this->model->update($periodeId, [
            'statut'               => 'verrouillee',
            'notes_saisie_ouverte' => 0,
            'verrouille_par'       => $userId,
            'verrouille_le'        => date('Y-m-d H:i:s'),
        ]);

        EventDispatcher::dispatch(new PeriodeLocked(
            periodeId:  $periodeId,
            nom:        $periode->nom,
            lockedById: $userId,
        ));
    }

    // ─── Déverrouillage ──────────────────────────────────────────────────────

    public function deverrouiller(int $periodeId, int $userId): void
    {
        $periode = $this->model->findById($periodeId);
        if (!$periode) {
            throw new \RuntimeException("Période introuvable.");
        }
        if ($periode->statut !== 'verrouillee') {
            throw new \RuntimeException(
                "Seule une période verrouillée peut être déverrouillée (statut actuel : {$periode->statut})."
            );
        }

        $this->model->update($periodeId, [
            'statut'         => 'fermee',
            'verrouille_par' => null,
            'verrouille_le'  => null,
        ]);

        EventDispatcher::dispatch(new PeriodeUnlocked(
            periodeId:    $periodeId,
            nom:          $periode->nom,
            unlockedById: $userId,
        ));
    }

    // ─── Archivage ───────────────────────────────────────────────────────────

    public function archiver(int $periodeId, int $userId): void
    {
        $periode = $this->model->findById($periodeId);
        if (!$periode) {
            throw new \RuntimeException("Période introuvable.");
        }
        if ($periode->statut === 'verrouillee') {
            throw new \RuntimeException("Déverrouillez la période avant de l'archiver.");
        }
        if ($periode->statut === 'archivee') {
            throw new \RuntimeException("Cette période est déjà archivée.");
        }

        $updates = ['statut' => 'archivee', 'notes_saisie_ouverte' => 0];
        if ((int)$periode->is_active === 1) {
            $updates['is_active'] = 0;
        }

        $this->model->update($periodeId, $updates);

        EventDispatcher::dispatch(new PeriodeArchived(
            periodeId:    $periodeId,
            nom:          $periode->nom,
            anneeScolaire:$periode->annee_scolaire,
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
