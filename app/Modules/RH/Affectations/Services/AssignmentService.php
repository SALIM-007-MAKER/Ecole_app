<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\Services;

use App\Modules\RH\Affectations\DTO\AssignmentDTO;
use App\Modules\RH\Affectations\DTO\AssignmentFiltersDTO;
use App\Modules\RH\Affectations\DTO\MatiereAssignmentDTO;
use App\Modules\RH\Affectations\Repositories\AssignmentRepository;
use App\Modules\RH\Affectations\Events\AssignmentCreated;
use App\Modules\RH\Affectations\Events\AssignmentUpdated;
use App\Modules\RH\Affectations\Events\AssignmentTransferred;
use App\Modules\RH\Affectations\Events\AssignmentArchived;
use Core\EventDispatcher;

class AssignmentService
{
    private AssignmentRepository $repo;

    public function __construct()
    {
        $this->repo = new AssignmentRepository();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function paginate(AssignmentFiltersDTO $f): array
    {
        $total = $this->repo->count($f);
        return [
            'items' => $this->repo->findAll($f),
            'total' => $total,
            'page'  => $f->page,
            'pages' => (int)ceil(max(1, $total) / $f->perPage),
        ];
    }

    public function findById(int $id): array
    {
        $a = $this->repo->findById($id);
        if ($a === null) {
            throw new \RuntimeException('Affectation introuvable.');
        }
        return $a;
    }

    public function findByEmploye(int $employeId): array
    {
        return $this->repo->findByEmploye($employeId);
    }

    public function findMatieres(int $affectationId): array
    {
        return $this->repo->findMatieres($affectationId);
    }

    public function findHistorique(int $affectationId): array
    {
        return $this->repo->findHistorique($affectationId);
    }

    public function statistiques(): array
    {
        return $this->repo->statistiques();
    }

    public function referentiels(?int $employeId = null): array
    {
        return [
            'employes'     => $this->repo->findEmployes(),
            'postes'       => $this->repo->findPostes(),
            'departements' => $this->repo->findDepartements(),
            'services'     => $this->repo->findServices(),
            'matieres'     => $this->tryFindMatieres(),
            'classes'      => $this->tryFindClasses(),
            'contrats'     => $employeId ? $this->repo->findContratsActifsByEmploye($employeId) : [],
        ];
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function creer(AssignmentDTO $dto, int $userId, string $userName): int
    {
        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        // Règle : un seul contrat actif pour cet employé doit être lié
        if ($dto->contratId !== null && !$this->repo->contratActifEmploye($dto->employeId, $dto->contratId)) {
            throw new \RuntimeException(
                'Le contrat sélectionné n\'est pas actif ou n\'appartient pas à cet employé.'
            );
        }

        // Règle : une seule affectation principale active à la fois
        if ($dto->type === 'principale' && $this->repo->hasPrincipaleActive($dto->employeId)) {
            throw new \RuntimeException(
                'Cet employé a déjà une affectation principale active. '
                . 'Clôturez-la avant d\'en créer une nouvelle.'
            );
        }

        // Règle : pas de doublon exact pour secondaire/temporaire
        if ($dto->type !== 'principale') {
            if ($this->repo->hasExactOverlap(
                $dto->employeId, $dto->type, $dto->dateDebut, $dto->dateFin,
                $dto->posteId, $dto->departementId
            )) {
                throw new \RuntimeException(
                    'Une affectation identique (même type, même poste, même département) '
                    . 'est déjà active sur cette période pour cet employé.'
                );
            }
        }

        $data = array_merge($dto->toArray(), [
            'statut'     => 'active',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $id = $this->repo->insert($data);

        $this->repo->logHistorique($id, 'creation', null, $data, 'Création de l\'affectation', $userId, $userName);

        EventDispatcher::dispatch(new AssignmentCreated(
            $id, $dto->employeId, $dto->type, $dto->posteId, $dto->departementId, $dto->dateDebut, $userId
        ));

        return $id;
    }

    // ── Modification générale ─────────────────────────────────────────────────

    public function modifier(int $id, AssignmentDTO $dto, int $userId, string $userName): void
    {
        $existing = $this->findById($id);

        if ($existing['statut'] === 'terminee') {
            throw new \RuntimeException('Une affectation terminée ne peut plus être modifiée.');
        }

        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        // Re-check principale unicité si on change le type
        if ($dto->type === 'principale' && $existing['type'] !== 'principale') {
            if ($this->repo->hasPrincipaleActive((int)$existing['employe_id'], $id)) {
                throw new \RuntimeException('Cet employé a déjà une affectation principale active.');
            }
        }

        $new  = $dto->toArray();
        $diff = array_filter($new, fn($v, $k) => ($existing[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH);

        if ($diff !== []) {
            $this->repo->update($id, array_merge($diff, ['updated_by' => $userId]));
            $this->repo->logHistorique($id, 'modification',
                array_intersect_key($existing, $diff), $diff, null, $userId, $userName);
            EventDispatcher::dispatch(new AssignmentUpdated($id, (int)$existing['employe_id'], 'modification', $diff, $userId));
        }
    }

    // ── Transfert (changement de poste / département / service / responsable) ──

    public function transferer(int $id, array $destination, string $motif, int $userId, string $userName): void
    {
        $existing = $this->findById($id);

        if ($existing['statut'] !== 'active') {
            throw new \RuntimeException('Seule une affectation active peut faire l\'objet d\'un transfert.');
        }
        if (trim($motif) === '') {
            throw new \InvalidArgumentException('Le motif du transfert est obligatoire.');
        }

        $champsConcernes = ['poste_id', 'departement_id', 'service_id', 'responsable_id'];
        $from = array_intersect_key($existing, array_flip($champsConcernes));
        $to   = array_intersect_key($destination, array_flip($champsConcernes));

        // N'appliquer que les champs effectivement modifiés
        $diff = array_filter($to, fn($v, $k) => ($from[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH);

        if (empty($diff)) {
            throw new \RuntimeException('Aucun changement détecté dans les champs de transfert.');
        }

        $this->repo->update($id, array_merge($diff, ['updated_by' => $userId]));
        $this->repo->logHistorique($id, 'transfert', $from, $to, $motif, $userId, $userName);

        EventDispatcher::dispatch(new AssignmentTransferred($id, (int)$existing['employe_id'], $from, $to, $motif, $userId));
    }

    // ── Suspension ────────────────────────────────────────────────────────────

    public function suspendre(int $id, int $userId, string $userName): void
    {
        $a = $this->findById($id);
        if ($a['statut'] !== 'active') {
            throw new \RuntimeException('Seule une affectation active peut être suspendue.');
        }
        $this->repo->updateStatut($id, 'suspendue');
        $this->repo->logHistorique($id, 'suspension', ['statut' => 'active'], ['statut' => 'suspendue'], null, $userId, $userName);
        EventDispatcher::dispatch(new AssignmentUpdated($id, (int)$a['employe_id'], 'suspension', ['statut' => 'suspendue'], $userId));
    }

    // ── Réactivation ─────────────────────────────────────────────────────────

    public function reactiver(int $id, int $userId, string $userName): void
    {
        $a = $this->findById($id);
        if ($a['statut'] !== 'suspendue') {
            throw new \RuntimeException('Seule une affectation suspendue peut être réactivée.');
        }
        // Re-vérification unicité principale
        if ($a['type'] === 'principale' && $this->repo->hasPrincipaleActive((int)$a['employe_id'], $id)) {
            throw new \RuntimeException('L\'employé a déjà une autre affectation principale active.');
        }
        $this->repo->updateStatut($id, 'active');
        $this->repo->logHistorique($id, 'reactivation', ['statut' => 'suspendue'], ['statut' => 'active'], null, $userId, $userName);
        EventDispatcher::dispatch(new AssignmentUpdated($id, (int)$a['employe_id'], 'reactivation', ['statut' => 'active'], $userId));
    }

    // ── Clôture (fin d'affectation sans suppression) ──────────────────────────

    public function clore(int $id, string $motif, int $userId, string $userName): void
    {
        $a = $this->findById($id);
        if ($a['statut'] === 'terminee') {
            throw new \RuntimeException('Cette affectation est déjà terminée.');
        }
        $dateFin = date('Y-m-d');
        $this->repo->update($id, [
            'statut'     => 'terminee',
            'date_fin'   => $dateFin,
            'updated_by' => $userId,
        ]);
        $this->repo->logHistorique($id, 'cloture',
            ['statut' => $a['statut']], ['statut' => 'terminee', 'date_fin' => $dateFin],
            $motif, $userId, $userName);
        EventDispatcher::dispatch(new AssignmentUpdated($id, (int)$a['employe_id'], 'cloture',
            ['statut' => 'terminee', 'date_fin' => $dateFin], $userId));
    }

    // ── Archivage ─────────────────────────────────────────────────────────────

    public function archiver(int $id, string $motif, int $userId, string $userName): void
    {
        $a = $this->findById($id);
        if ($a['statut'] === 'active') {
            throw new \RuntimeException(
                'Clôturez ou suspendez l\'affectation avant de l\'archiver.'
            );
        }
        $this->repo->softDelete($id);
        $this->repo->logHistorique($id, 'archivage', null, ['archived' => true], $motif, $userId, $userName);
        EventDispatcher::dispatch(new AssignmentArchived($id, (int)$a['employe_id'], $motif, $userId));
    }

    // ── Matières (enseignants) ────────────────────────────────────────────────

    public function ajouterMatiere(int $affectationId, MatiereAssignmentDTO $dto, int $userId): int
    {
        $a = $this->findById($affectationId);

        if ($a['statut'] !== 'active') {
            throw new \RuntimeException('Les matières ne peuvent être ajoutées qu\'à une affectation active.');
        }

        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        $id = $this->repo->insertMatiere($affectationId, $dto->toArray(), $userId);

        EventDispatcher::dispatch(new AssignmentUpdated(
            $affectationId, (int)$a['employe_id'], 'matiere_added',
            ['matiere_id' => $dto->matiereId, 'classe_id' => $dto->classeId, 'niveau' => $dto->niveau],
            $userId
        ));

        return $id;
    }

    public function retirerMatiere(int $affectationId, int $matiereAssignId, int $userId): void
    {
        $a = $this->findById($affectationId);
        $this->repo->closeMatiereAssignment($matiereAssignId, date('Y-m-d'));

        EventDispatcher::dispatch(new AssignmentUpdated(
            $affectationId, (int)$a['employe_id'], 'matiere_removed',
            ['matiere_assign_id' => $matiereAssignId], $userId
        ));
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function exporterCsv(AssignmentFiltersDTO $f): string
    {
        $allFilters = new AssignmentFiltersDTO(
            q: $f->q, type: $f->type, statut: $f->statut,
            employeId: $f->employeId, departementId: $f->departementId,
            posteId: $f->posteId, includeArch: $f->includeArch,
            page: 1, perPage: 9999
        );
        $rows = $this->repo->findAll($allFilters);

        $bom  = "\xEF\xBB\xBF";
        $cols = ['Employé', 'Matricule', 'Type', 'Statut', 'Poste', 'Département', 'Service', 'Responsable', 'Début', 'Fin'];
        $lines = [implode(';', $cols)];

        foreach ($rows as $r) {
            $lines[] = implode(';', [
                $r['employe_nom'],
                $r['employe_matricule'],
                $r['type'],
                $r['statut'],
                $r['poste_intitule']    ?? '',
                $r['departement_nom']   ?? '',
                $r['service_nom']       ?? '',
                $r['responsable_nom']   ?? '',
                $r['date_debut'],
                $r['date_fin'] ?? 'Indéterminée',
            ]);
        }

        return $bom . implode("\n", $lines);
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function tryFindMatieres(): array
    {
        try {
            return $this->repo->findMatieresList();
        } catch (\Throwable) {
            return [];
        }
    }

    private function tryFindClasses(): array
    {
        try {
            return $this->repo->findClassesList();
        } catch (\Throwable) {
            return [];
        }
    }
}
