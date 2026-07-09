<?php

namespace App\Modules\VieScolaire\Absences\Services;

use App\Modules\VieScolaire\Absences\DTO\AbsenceDTO;
use App\Modules\VieScolaire\Absences\DTO\AbsenceFiltersDTO;
use App\Modules\VieScolaire\Absences\DTO\JustificationDTO;
use App\Modules\VieScolaire\Absences\Events\AbsenceJustified;
use App\Modules\VieScolaire\Absences\Events\AbsenceRejected;
use App\Modules\VieScolaire\Absences\Events\StudentAbsent;
use App\Modules\VieScolaire\Absences\Repositories\AbsenceRepository;
use App\Services\AuditService;
use Core\EventDispatcher;

class AbsenceService
{
    private AbsenceRepository $repo;
    private AuditService      $audit;

    public function __construct()
    {
        $this->repo  = new AbsenceRepository();
        $this->audit = new AuditService();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function paginate(AbsenceFiltersDTO $filters): array
    {
        $rows  = $this->repo->findAll($filters);
        $total = $this->repo->countAll($filters);

        return [
            'data'       => $rows,
            'total'      => $total,
            'page'       => $filters->page,
            'per_page'   => $filters->perPage,
            'last_page'  => (int)ceil($total / max($filters->perPage, 1)),
        ];
    }

    public function motifs(): array
    {
        return $this->repo->findAllMotifs();
    }

    public function statistiquesEleve(int $eleveId, string $anneeScolaire): array
    {
        return $this->repo->countByEleveAndAnnee($eleveId, $anneeScolaire);
    }

    public function statistiquesClasse(int $classeId, string $anneeScolaire): array
    {
        return $this->repo->statsByClasse($classeId, $anneeScolaire);
    }

    // ── Saisie d'absence ──────────────────────────────────────────────────────

    public function enregistrer(AbsenceDTO $dto, int $classeId, string $anneeScolaire, int $saisieParId): int
    {
        $errors = $dto->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        $data = array_merge($dto->toArray(), [
            'classe_id'      => $classeId,
            'annee_scolaire' => $anneeScolaire,
            'saisie_par'     => $saisieParId,
        ]);

        $absenceId = $this->repo->insert($data);

        $this->audit->logCreate($saisieParId, 'vie_scolaire', 'absence', $absenceId, $data);

        EventDispatcher::dispatch(new StudentAbsent(
            absenceId:   $absenceId,
            eleveId:     $dto->eleveId,
            classeId:    $classeId,
            dateAbsence: $dto->dateAbsence,
            type:        $dto->type,
            saisieParId: $saisieParId,
        ));

        return $absenceId;
    }

    // ── Justification ─────────────────────────────────────────────────────────

    public function soumettrJustification(int $absenceId, JustificationDTO $dto, int $soumisParId): int
    {
        $absence = $this->repo->findById($absenceId);
        if ($absence === null) {
            throw new \RuntimeException("Absence introuvable.");
        }
        if ($absence['statut'] === 'justifiee') {
            throw new \RuntimeException("Cette absence est déjà justifiée.");
        }

        $errors = $dto->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        $justificationId = $this->repo->insertJustification([
            'absence_id'  => $absenceId,
            'motif_id'    => $dto->motifId,
            'description' => $dto->description,
            'fichier'     => $dto->fichier,
            'soumis_par'  => $soumisParId,
        ]);

        $this->repo->updateStatut($absenceId, 'en_attente');

        $this->audit->log($soumisParId, 'justify', 'vie_scolaire', 'absence', $absenceId);

        return $justificationId;
    }

    public function validerJustification(int $absenceId, int $valideParId): void
    {
        $absence = $this->repo->findById($absenceId);
        if ($absence === null) {
            throw new \RuntimeException("Absence introuvable.");
        }
        if ($absence['statut'] !== 'en_attente') {
            throw new \RuntimeException("Aucune justification en attente pour cette absence.");
        }

        $justification = $this->repo->findJustificationByAbsence($absenceId);
        if ($justification === null) {
            throw new \RuntimeException("Justification introuvable.");
        }

        $this->repo->validerJustification($justification['id'], $valideParId);
        $this->repo->updateStatut($absenceId, 'justifiee');

        $this->audit->log($valideParId, 'validate', 'vie_scolaire', 'absence', $absenceId);

        EventDispatcher::dispatch(new AbsenceJustified(
            absenceId:        $absenceId,
            justificationId:  $justification['id'],
            eleveId:          (int)$absence['eleve_id'],
            classeId:         (int)$absence['classe_id'],
            valideParId:      $valideParId,
        ));
    }

    public function refuserJustification(int $absenceId, string $motifRefus, int $rejeteParId): void
    {
        $absence = $this->repo->findById($absenceId);
        if ($absence === null) {
            throw new \RuntimeException("Absence introuvable.");
        }
        if ($absence['statut'] !== 'en_attente') {
            throw new \RuntimeException("Aucune justification en attente pour cette absence.");
        }
        if (empty(trim($motifRefus))) {
            throw new \InvalidArgumentException("Le motif de refus est obligatoire.");
        }

        $justification = $this->repo->findJustificationByAbsence($absenceId);
        if ($justification === null) {
            throw new \RuntimeException("Justification introuvable.");
        }

        $this->repo->refuserJustification($justification['id'], $rejeteParId, $motifRefus);
        $this->repo->updateStatut($absenceId, 'refusee');

        $this->audit->log($rejeteParId, 'reject', 'vie_scolaire', 'absence', $absenceId);

        EventDispatcher::dispatch(new AbsenceRejected(
            absenceId:       $absenceId,
            justificationId: $justification['id'],
            eleveId:         (int)$absence['eleve_id'],
            classeId:        (int)$absence['classe_id'],
            motifRefus:      $motifRefus,
            rejeteParId:     $rejeteParId,
        ));
    }

    // ── Archivage (soft delete) ───────────────────────────────────────────────

    public function archiver(int $absenceId, int $userId): void
    {
        $absence = $this->repo->findById($absenceId);
        if ($absence === null) {
            throw new \RuntimeException("Absence introuvable.");
        }

        $this->repo->softDelete($absenceId);

        $this->audit->logDelete($userId, 'vie_scolaire', 'absence', $absenceId, $absence);
    }
}
