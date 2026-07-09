<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Services;

use App\Modules\RH\Organisation\DTO\DepartmentDTO;
use App\Modules\RH\Organisation\DTO\ServiceDTO;
use App\Modules\RH\Organisation\DTO\OrganizationFiltersDTO;
use App\Modules\RH\Organisation\Repositories\OrganizationRepository;
use App\Modules\RH\Organisation\Events\DepartmentCreated;
use App\Modules\RH\Organisation\Events\DepartmentUpdated;
use App\Modules\RH\Organisation\Events\OrganizationUpdated;
use Core\EventDispatcher;

class DepartmentService
{
    private OrganizationRepository $repo;

    public function __construct()
    {
        $this->repo = new OrganizationRepository();
    }

    // ── Départements ──────────────────────────────────────────────────────────

    public function paginate(OrganizationFiltersDTO $f): array
    {
        return [
            'items' => $this->repo->findAllDepartements($f),
            'total' => $this->repo->countDepartements($f),
            'page'  => $f->page,
            'pages' => (int)ceil($this->repo->countDepartements($f) / $f->perPage),
        ];
    }

    public function findById(int $id): array
    {
        $d = $this->repo->findDepartementById($id);
        if ($d === null) {
            throw new \RuntimeException("Département introuvable.");
        }
        return $d;
    }

    public function findByIdWithArchived(int $id): array
    {
        $d = $this->repo->findDepartementById($id, withArchived: true);
        if ($d === null) {
            throw new \RuntimeException("Département introuvable.");
        }
        return $d;
    }

    public function creer(DepartmentDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        if ($this->repo->codeExistsDept($dto->code)) {
            throw new \RuntimeException("Le code « {$dto->code} » est déjà utilisé par un autre département.");
        }
        $id = $this->repo->insertDepartement($dto->toArray());
        $this->repo->logHistorique('departement', $id, 'creation', null, $dto->toArray(), $userId, '');
        EventDispatcher::dispatch(new DepartmentCreated($id, $dto->nom, $dto->code, $dto->parentId, $userId));
        return $id;
    }

    public function modifier(int $id, DepartmentDTO $dto, int $userId): void
    {
        $existing = $this->findById($id);
        $errors   = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        if ($this->repo->codeExistsDept($dto->code, $id)) {
            throw new \RuntimeException("Le code « {$dto->code} » est déjà utilisé par un autre département.");
        }
        $new = $dto->toArray();
        $diff = array_filter($new, fn($v, $k) => ($existing[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH);
        if ($diff !== []) {
            $this->repo->updateDepartement($id, $new);
            $this->repo->logHistorique('departement', $id, 'modification', $existing, $new, $userId, '');
            EventDispatcher::dispatch(new DepartmentUpdated($id, 'modification', $diff, $userId));
        }
    }

    public function archiver(int $id, int $userId): void
    {
        $d = $this->findById($id);
        $this->repo->softDeleteDepartement($id);
        $this->repo->logHistorique('departement', $id, 'archivage', ['actif' => 1], ['actif' => 0, 'deleted_at' => date('Y-m-d H:i:s')], $userId, '');
        EventDispatcher::dispatch(new DepartmentUpdated($id, 'archivage', ['nom' => $d['nom']], $userId));
    }

    public function restaurer(int $id, int $userId): void
    {
        $d = $this->repo->findDepartementById($id, withArchived: true);
        if ($d === null) {
            throw new \RuntimeException("Département introuvable.");
        }
        if ($d['deleted_at'] === null) {
            throw new \RuntimeException("Ce département n'est pas archivé.");
        }
        $this->repo->restoreDepartement($id);
        $this->repo->logHistorique('departement', $id, 'restauration', null, ['deleted_at' => null], $userId, '');
        EventDispatcher::dispatch(new DepartmentUpdated($id, 'restauration', ['nom' => $d['nom']], $userId));
    }

    public function tree(): array
    {
        return $this->repo->findDepartementsTree();
    }

    public function findDepartementsActifs(): array
    {
        return $this->repo->findDepartementsActifs();
    }

    // ── Services (sous-domaine) ───────────────────────────────────────────────

    public function findServicesByDept(int $deptId): array
    {
        return $this->repo->findServicesByDepartement($deptId);
    }

    public function findServiceById(int $id): array
    {
        $s = $this->repo->findServiceById($id);
        if ($s === null) {
            throw new \RuntimeException("Service introuvable.");
        }
        return $s;
    }

    public function creerService(ServiceDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        // Vérifier que le département parent existe
        if ($this->repo->findDepartementById($dto->departementId) === null) {
            throw new \RuntimeException("Le département sélectionné n'existe pas.");
        }
        if ($this->repo->codeExistsService($dto->code)) {
            throw new \RuntimeException("Le code « {$dto->code} » est déjà utilisé.");
        }
        $id = $this->repo->insertService($dto->toArray());
        $this->repo->logHistorique('service', $id, 'creation', null, $dto->toArray(), $userId, '');
        EventDispatcher::dispatch(new OrganizationUpdated('service', $id, 'creation', ['nom' => $dto->nom], $userId));
        return $id;
    }

    public function modifierService(int $id, ServiceDTO $dto, int $userId): void
    {
        $existing = $this->findServiceById($id);
        $errors   = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        if ($this->repo->codeExistsService($dto->code, $id)) {
            throw new \RuntimeException("Le code « {$dto->code} » est déjà utilisé.");
        }
        $new = $dto->toArray();
        $this->repo->updateService($id, $new);
        $this->repo->logHistorique('service', $id, 'modification', $existing, $new, $userId, '');
        EventDispatcher::dispatch(new OrganizationUpdated('service', $id, 'modification', $new, $userId));
    }

    public function archiverService(int $id, int $userId): void
    {
        $s = $this->findServiceById($id);
        $this->repo->softDeleteService($id);
        $this->repo->logHistorique('service', $id, 'archivage', null, ['deleted_at' => date('Y-m-d H:i:s')], $userId, '');
        EventDispatcher::dispatch(new OrganizationUpdated('service', $id, 'archivage', ['nom' => $s['nom']], $userId));
    }

    public function restaurerService(int $id, int $userId): void
    {
        $s = $this->repo->findServiceById($id, withArchived: true);
        if ($s === null || $s['deleted_at'] === null) {
            throw new \RuntimeException("Ce service n'est pas archivé ou introuvable.");
        }
        $this->repo->restoreService($id);
        EventDispatcher::dispatch(new OrganizationUpdated('service', $id, 'restauration', ['nom' => $s['nom']], $userId));
    }

    public function findServicesActifs(?int $deptId = null): array
    {
        return $this->repo->findServicesActifs($deptId);
    }
}
