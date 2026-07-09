<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Services;

use App\Modules\Inventaire\DTO\FournisseurDTO;
use App\Modules\Inventaire\Repositories\FournisseurRepository;
use App\Modules\Inventaire\Events\FournisseurAjoute;
use App\Modules\Inventaire\Events\FournisseurBloque;
use App\Services\AuditService;
use Core\EventDispatcher;

class FournisseurService
{
    private FournisseurRepository $fournisseurs;

    public function __construct()
    {
        $this->fournisseurs = new FournisseurRepository();
    }

    public function lister(int $etablissementId, ?string $statut = null): array
    {
        return $this->fournisseurs->all($etablissementId, $statut);
    }

    public function trouver(int $id): ?array
    {
        return $this->fournisseurs->findById($id);
    }

    public function creer(FournisseurDTO $dto, int $userId, int $etablissementId): int
    {
        $id = $this->fournisseurs->create([
            ':nom'                => $dto->nom,
            ':code'               => $dto->code,
            ':email'              => $dto->email,
            ':telephone'          => $dto->telephone,
            ':adresse'            => $dto->adresse,
            ':site_web'           => $dto->siteWeb,
            ':rib'                => $dto->rib,
            ':delai_livraison_j'  => $dto->delaiLivraisonJ,
            ':conditions_paiement'=> $dto->conditionsPaiement,
            ':statut'             => $dto->statut,
            ':notes'              => $dto->notes,
            ':etablissement_id'   => $etablissementId,
        ]);

        AuditService::logCreate('inv_fournisseurs', $id, $userId, ['nom' => $dto->nom]);
        EventDispatcher::dispatch(new FournisseurAjoute($id, $dto->nom, $userId, $etablissementId));
        return $id;
    }

    public function modifier(int $id, FournisseurDTO $dto, int $userId): void
    {
        $this->fournisseurs->update($id, [
            ':nom'                => $dto->nom,
            ':code'               => $dto->code,
            ':email'              => $dto->email,
            ':telephone'          => $dto->telephone,
            ':adresse'            => $dto->adresse,
            ':site_web'           => $dto->siteWeb,
            ':rib'                => $dto->rib,
            ':delai_livraison_j'  => $dto->delaiLivraisonJ,
            ':conditions_paiement'=> $dto->conditionsPaiement,
            ':statut'             => $dto->statut,
            ':notes'              => $dto->notes,
        ]);
        AuditService::log('update', 'inv_fournisseurs', $id, $userId, ['nom' => $dto->nom]);
    }

    public function bloquer(int $id, int $userId): void
    {
        $f = $this->fournisseurs->findById($id);
        if (!$f) throw new \RuntimeException("Fournisseur introuvable.");
        $this->fournisseurs->updateStatut($id, 'bloque');
        AuditService::log('block', 'inv_fournisseurs', $id, $userId, []);
        EventDispatcher::dispatch(new FournisseurBloque($id, $f['nom'], $userId));
    }

    public function archiver(int $id, int $userId): void
    {
        if ($this->fournisseurs->hasCommandes($id)) {
            throw new \RuntimeException("Impossible d'archiver : commandes associées.");
        }
        $this->fournisseurs->softDelete($id);
        AuditService::log('archive', 'inv_fournisseurs', $id, $userId, []);
    }
}
