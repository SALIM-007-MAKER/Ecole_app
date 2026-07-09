<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Services;

use App\Modules\RH\Organisation\DTO\PositionDTO;
use App\Modules\RH\Organisation\DTO\OrganizationFiltersDTO;
use App\Modules\RH\Organisation\Repositories\OrganizationRepository;
use App\Modules\RH\Organisation\Events\PositionCreated;
use App\Modules\RH\Organisation\Events\OrganizationUpdated;
use Core\EventDispatcher;

class PositionService
{
    private OrganizationRepository $repo;

    public function __construct()
    {
        $this->repo = new OrganizationRepository();
    }

    public function paginate(OrganizationFiltersDTO $f): array
    {
        return [
            'items' => $this->repo->findAllPostes($f),
            'total' => $this->repo->countPostes($f),
            'page'  => $f->page,
            'pages' => (int)ceil(max(1, $this->repo->countPostes($f)) / $f->perPage),
        ];
    }

    public function findById(int $id): array
    {
        $p = $this->repo->findPosteById($id);
        if ($p === null) {
            throw new \RuntimeException("Poste introuvable.");
        }
        return $p;
    }

    public function findByIdWithArchived(int $id): array
    {
        $p = $this->repo->findPosteById($id, withArchived: true);
        if ($p === null) {
            throw new \RuntimeException("Poste introuvable.");
        }
        return $p;
    }

    public function creer(PositionDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        if ($this->repo->codeExistsPoste($dto->code)) {
            throw new \RuntimeException("Le code « {$dto->code} » est déjà utilisé par un autre poste.");
        }
        $id = $this->repo->insertPoste($dto->toArray());
        $this->repo->logHistorique('poste', $id, 'creation', null, $dto->toArray(), $userId, '');
        EventDispatcher::dispatch(new PositionCreated($id, $dto->intitule, $dto->code, $dto->categorie, $dto->departementId, $userId));
        return $id;
    }

    public function modifier(int $id, PositionDTO $dto, int $userId): void
    {
        $existing = $this->findById($id);
        $errors   = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        if ($this->repo->codeExistsPoste($dto->code, $id)) {
            throw new \RuntimeException("Le code « {$dto->code} » est déjà utilisé par un autre poste.");
        }
        $new  = $dto->toArray();
        $diff = array_filter($new, fn($v, $k) => ($existing[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH);
        if ($diff !== []) {
            $this->repo->updatePoste($id, $new);
            $this->repo->logHistorique('poste', $id, 'modification', $existing, $new, $userId, '');
            EventDispatcher::dispatch(new OrganizationUpdated('poste', $id, 'modification', $diff, $userId));
        }
    }

    public function archiver(int $id, int $userId): void
    {
        $p = $this->findById($id);
        $nbOccupants = $this->repo->countEmployesOnPoste($id);
        if ($nbOccupants > 0) {
            throw new \RuntimeException(
                "Impossible d'archiver : $nbOccupants employé(s) occupe(nt) encore ce poste. Réaffectez-les d'abord."
            );
        }
        $this->repo->softDeletePoste($id);
        $this->repo->logHistorique('poste', $id, 'archivage', null, ['deleted_at' => date('Y-m-d H:i:s')], $userId, '');
        EventDispatcher::dispatch(new OrganizationUpdated('poste', $id, 'archivage', ['intitule' => $p['intitule']], $userId));
    }

    public function restaurer(int $id, int $userId): void
    {
        $p = $this->repo->findPosteById($id, withArchived: true);
        if ($p === null || $p['deleted_at'] === null) {
            throw new \RuntimeException("Ce poste n'est pas archivé ou introuvable.");
        }
        $this->repo->restorePoste($id);
        EventDispatcher::dispatch(new OrganizationUpdated('poste', $id, 'restauration', ['intitule' => $p['intitule']], $userId));
    }

    // ── Fonctions ─────────────────────────────────────────────────────────────

    public function findAllFonctions(bool $withArchived = false): array
    {
        return $this->repo->findAllFonctions($withArchived);
    }

    public function findFonctionById(int $id): array
    {
        $f = $this->repo->findFonctionById($id);
        if ($f === null) {
            throw new \RuntimeException("Fonction introuvable.");
        }
        return $f;
    }

    public function creerFonction(array $data, int $userId): int
    {
        $nom      = trim($data['nom'] ?? '');
        $code     = strtoupper(trim($data['code'] ?? ''));
        $niveau   = max(1, min(3, (int)($data['niveau'] ?? 1)));
        $perimetre = in_array($data['perimetre'] ?? '', ['etablissement','departement','service','transversal'], true)
                   ? $data['perimetre']
                   : 'transversal';
        if ($nom === '') {
            throw new \InvalidArgumentException("Le nom de la fonction est obligatoire.");
        }
        if (!preg_match('/^[A-Z0-9_]{2,30}$/', $code)) {
            throw new \InvalidArgumentException("Le code est invalide (majuscules, 2-30 caractères).");
        }
        if ($this->repo->codeExistsFonction($code)) {
            throw new \RuntimeException("Le code « $code » est déjà utilisé.");
        }
        $payload = ['nom' => $nom, 'code' => $code, 'description' => $data['description'] ?? null,
                    'niveau' => $niveau, 'perimetre' => $perimetre, 'actif' => 1];
        $id = $this->repo->insertFonction($payload);
        EventDispatcher::dispatch(new OrganizationUpdated('fonction', $id, 'creation', ['nom' => $nom], $userId));
        return $id;
    }

    public function modifierFonction(int $id, array $data, int $userId): void
    {
        $f    = $this->findFonctionById($id);
        $nom  = trim($data['nom'] ?? '');
        $code = strtoupper(trim($data['code'] ?? ''));
        if ($nom === '') {
            throw new \InvalidArgumentException("Le nom de la fonction est obligatoire.");
        }
        if ($this->repo->codeExistsFonction($code, $id)) {
            throw new \RuntimeException("Le code « $code » est déjà utilisé.");
        }
        $payload = ['nom' => $nom, 'code' => $code,
                    'description' => $data['description'] ?? null,
                    'niveau'      => max(1, min(3, (int)($data['niveau'] ?? 1))),
                    'perimetre'   => $data['perimetre'] ?? $f['perimetre'],
                    'actif'       => isset($data['actif']) ? 1 : 0];
        $this->repo->updateFonction($id, $payload);
        EventDispatcher::dispatch(new OrganizationUpdated('fonction', $id, 'modification', $payload, $userId));
    }

    public function archiverFonction(int $id, int $userId): void
    {
        $f = $this->findFonctionById($id);
        $this->repo->softDeleteFonction($id);
        EventDispatcher::dispatch(new OrganizationUpdated('fonction', $id, 'archivage', ['nom' => $f['nom']], $userId));
    }
}
