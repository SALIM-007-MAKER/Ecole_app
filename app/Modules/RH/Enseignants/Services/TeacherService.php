<?php

namespace App\Modules\RH\Enseignants\Services;

use App\Modules\RH\Enseignants\DTO\QualificationDTO;
use App\Modules\RH\Enseignants\DTO\TeacherDTO;
use App\Modules\RH\Enseignants\DTO\TeacherFiltersDTO;
use App\Modules\RH\Enseignants\Events\TeacherAssigned;
use App\Modules\RH\Enseignants\Events\TeacherCreated;
use App\Modules\RH\Enseignants\Events\TeacherQualificationUpdated;
use App\Modules\RH\Enseignants\Events\TeacherUpdated;
use App\Modules\RH\Enseignants\Repositories\TeacherRepository;
use App\Services\AuditService;
use Core\EventDispatcher;

class TeacherService
{
    private TeacherRepository $repo;
    private AuditService      $audit;

    public function __construct()
    {
        $this->repo  = new TeacherRepository();
        $this->audit = new AuditService();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function findByIdIncludingArchived(int $id): ?array
    {
        return $this->repo->findByIdIncludingArchived($id);
    }

    public function findByEmployeId(int $employeId): ?array
    {
        return $this->repo->findByEmployeId($employeId);
    }

    public function paginate(TeacherFiltersDTO $filters): array
    {
        $rows  = $this->repo->findAll($filters);
        $total = $this->repo->countAll($filters);

        return [
            'data'      => $rows,
            'total'     => $total,
            'page'      => $filters->page,
            'per_page'  => $filters->perPage,
            'last_page' => (int)ceil($total / max($filters->perPage, 1)),
        ];
    }

    public function statistiques(): array
    {
        return [
            'par_statut'   => $this->repo->countByStatut(),
            'par_matiere'  => $this->repo->countByMatiere(),
            'archives'     => $this->repo->countArchives(),
        ];
    }

    public function matieres(int $enseignantId): array
    {
        return $this->repo->findMatieres($enseignantId);
    }

    public function qualifications(int $enseignantId): array
    {
        return $this->repo->findQualifications($enseignantId);
    }

    public function matieresPourSelect(): array
    {
        return $this->repo->findMatieresPourSelect();
    }

    public function employesSansProfile(): array
    {
        return $this->repo->findEmployesSansProfile();
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function creer(TeacherDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' | ', array_merge(...array_values($errors))));
        }

        if ($this->repo->employeHasProfile($dto->employeId)) {
            throw new \RuntimeException("Cet employé possède déjà un profil enseignant actif.");
        }

        $id = $this->repo->insert($dto->toArray());

        // Récupération des infos employé pour l'event
        $enseignant = $this->repo->findById($id);

        EventDispatcher::dispatch(new TeacherCreated(
            enseignantId:      $id,
            employeId:         $dto->employeId,
            matricule:         $enseignant['matricule'],
            nom:               $enseignant['nom'],
            prenom:            $enseignant['prenom'],
            statutPedagogique: $dto->statutPedagogique,
            creeParId:         $userId,
        ));

        return $id;
    }

    // ── Modification ──────────────────────────────────────────────────────────

    public function modifier(int $id, TeacherDTO $dto, int $userId): void
    {
        $enseignant = $this->repo->findById($id);
        if ($enseignant === null) {
            throw new \RuntimeException("Enseignant introuvable.");
        }

        // L'employe_id ne peut pas changer (relation immuable)
        $errors = $dto->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' | ', array_merge(...array_values($errors))));
        }

        $data    = $dto->toArray();
        unset($data['employe_id']); // immuable
        $changes = $this->audit->diff($enseignant, $data);

        $this->repo->update($id, $data);

        EventDispatcher::dispatch(new TeacherUpdated(
            enseignantId:  $id,
            matricule:     $enseignant['matricule'],
            changes:       $changes,
            modifieParId:  $userId,
        ));
    }

    // ── Affectation matières ──────────────────────────────────────────────────

    public function assignerMatieres(int $id, array $matieres, int $userId): void
    {
        $enseignant = $this->repo->findById($id);
        if ($enseignant === null) {
            throw new \RuntimeException("Enseignant introuvable.");
        }

        $this->repo->deleteMatieres($id);

        $inserted = [];
        foreach ($matieres as $i => $m) {
            $matiereId = (int)($m['matiere_id'] ?? 0);
            if ($matiereId <= 0) {
                continue;
            }
            $niveaux  = trim($m['niveaux'] ?? '') ?: null;
            $priorite = (int)($m['priorite'] ?? ($i + 1));
            $this->repo->insertMatiere($id, $matiereId, $niveaux, $priorite);
            $inserted[] = ['matiere_id' => $matiereId, 'niveaux' => $niveaux];
        }

        // Mise à jour charge horaire actuelle basée sur volume_horaire des matières
        $this->repo->updateChargeActuelle($id);

        EventDispatcher::dispatch(new TeacherAssigned(
            enseignantId: $id,
            matricule:    $enseignant['matricule'],
            matieres:     $inserted,
            assigneParId: $userId,
        ));
    }

    // ── Qualifications ────────────────────────────────────────────────────────

    public function ajouterQualification(int $id, QualificationDTO $dto, int $userId): int
    {
        $enseignant = $this->repo->findById($id);
        if ($enseignant === null) {
            throw new \RuntimeException("Enseignant introuvable.");
        }

        $errors = $dto->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' | ', array_merge(...array_values($errors))));
        }

        $qualId = $this->repo->insertQualification($id, $dto->toArray());

        EventDispatcher::dispatch(new TeacherQualificationUpdated(
            enseignantId:      $id,
            matricule:         $enseignant['matricule'],
            typeQualification: $dto->type,
            intitule:          $dto->intitule,
            modifieParId:      $userId,
        ));

        return $qualId;
    }

    public function supprimerQualification(int $id, int $qualId, int $userId): void
    {
        $enseignant = $this->repo->findById($id);
        if ($enseignant === null) {
            throw new \RuntimeException("Enseignant introuvable.");
        }

        $this->repo->deleteQualification($qualId, $id);

        EventDispatcher::dispatch(new TeacherQualificationUpdated(
            enseignantId:      $id,
            matricule:         $enseignant['matricule'],
            typeQualification: 'suppression',
            intitule:          "Qualification #{$qualId} supprimée",
            modifieParId:      $userId,
        ));
    }

    // ── Archivage ─────────────────────────────────────────────────────────────

    public function archiver(int $id, int $userId): void
    {
        $enseignant = $this->repo->findById($id);
        if ($enseignant === null) {
            throw new \RuntimeException("Enseignant introuvable ou déjà archivé.");
        }

        $this->repo->softDelete($id);

        EventDispatcher::dispatch(new TeacherUpdated(
            enseignantId:  $id,
            matricule:     $enseignant['matricule'],
            changes:       ['archived' => ['from' => false, 'to' => true]],
            modifieParId:  $userId,
        ));
    }

    // ── Restauration ─────────────────────────────────────────────────────────

    public function restaurer(int $id, int $userId): void
    {
        $enseignant = $this->repo->findByIdIncludingArchived($id);
        if ($enseignant === null) {
            throw new \RuntimeException("Enseignant introuvable.");
        }
        if ($enseignant['deleted_at'] === null) {
            throw new \RuntimeException("Ce profil enseignant n'est pas archivé.");
        }

        $this->repo->restore($id);

        EventDispatcher::dispatch(new TeacherUpdated(
            enseignantId:  $id,
            matricule:     $enseignant['matricule'],
            changes:       ['archived' => ['from' => true, 'to' => false]],
            modifieParId:  $userId,
        ));
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function exporterCsv(TeacherFiltersDTO $filters): string
    {
        $all = new TeacherFiltersDTO(
            q:          $filters->q,
            statut:     $filters->statut,
            matiereId:  $filters->matiereId,
            includeArch: $filters->includeArch,
            page:       1,
            perPage:    9999,
        );

        $rows = $this->repo->findAll($all);

        $csv  = "\xEF\xBB\xBF";
        $csv .= "Matricule,Nom,Prénom,Statut Pédagogique,Spécialité,Charge Max,Charge Actuelle,Nb Matières,E-mail Pro\n";

        foreach ($rows as $r) {
            $csv .= implode(',', [
                '"' . str_replace('"', '""', $r['matricule']              ?? '') . '"',
                '"' . str_replace('"', '""', $r['nom']                    ?? '') . '"',
                '"' . str_replace('"', '""', $r['prenom']                 ?? '') . '"',
                '"' . str_replace('"', '""', $r['statut_pedagogique']     ?? '') . '"',
                '"' . str_replace('"', '""', $r['specialite_principale']  ?? '') . '"',
                '"' . str_replace('"', '""', (string)($r['charge_horaire_max']     ?? '')) . '"',
                '"' . str_replace('"', '""', (string)($r['charge_horaire_actuelle'] ?? '')) . '"',
                '"' . str_replace('"', '""', (string)($r['nb_matieres']   ?? 0)) . '"',
                '"' . str_replace('"', '""', $r['email_pro']              ?? '') . '"',
            ]) . "\n";
        }

        return $csv;
    }
}
